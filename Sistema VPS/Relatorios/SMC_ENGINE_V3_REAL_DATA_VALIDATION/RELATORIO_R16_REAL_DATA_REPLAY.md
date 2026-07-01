# RELATÓRIO R16 — REPLAY CANDLE A CANDLE
## Validação de 5 Modos + Correção de Inconsistência Causal em PDH/PDL

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `tools/smc_v3_validation/run_smc_v3_real_data_replay.py`
**Arquivo corrigido:** `technical_engine/smc_engine_v3/incremental/components/previous_high_low.py`

---

## 1. Ferramenta Criada

`run_smc_v3_real_data_replay.py` executa e compara 5 modos exigidos pelo plano:

- **FULL_BATCH** — todos os candles em uma chamada
- **PREFIX_REPLAY** — processa apenas os primeiros N candles isoladamente, compara `structure_id`s contra o subconjunto correspondente do FULL_BATCH
- **CHUNK_REPLAY** — processa em blocos fixos (mesma instância de engine, sem snapshot/restore)
- **CHECKPOINT_RESUME** — processa metade, tira snapshot, restaura em engine novo, processa a segunda metade; compara `state_hash` final
- **PERSISTED_REPLAY** — mesmo que CHECKPOINT_RESUME, mas através da camada real de persistência SQLite (`BackfillRunner` + checkpoint em banco)

## 2. P0 Encontrado e Corrigido: Inconsistência Causal em PDH/PDL

### 2.1 Sintoma

Ao validar `PREFIX_REPLAY` numa amostra de 500 candles reais (split em 250), uma estrutura `PDH` divergiu: presente no `FULL_BATCH` dentro da janela de prefixo, ausente no run isolado de 250 candles.

### 2.2 Causa raiz

`_publish_pdh_pdl()` (corrigida no R7/R13 para usar o candle-gatilho do novo dia como fonte de `confirmed_at`/`available_at`) ainda usava `extreme_candle_id` (o candle do dia **anterior** que registrou o high/low) para popular `confirmation_candle_id`, `availability_candle_id` e `source_candle_id` do evento — uma **inconsistência interna**: os timestamps (`confirmed_at`/`available_at`) refletiam o candle-gatilho correto, mas os campos de `candle_id` associados apontavam para um candle diferente e anterior.

Isso não era look-ahead real (a estrutura já era causal), mas quebrava qualquer verificação que assumisse `confirmation_candle_id` como a referência correta para "quando esta estrutura foi de fato emitida" — exatamente o que a ferramenta de replay desta fase verifica.

### 2.3 Correção

`confirmation_candle_id`, `availability_candle_id` e `source_candle_id` (do evento `AVAILABLE`) agora usam `candle.candle_id` (o candle-gatilho), consistente com `confirmed_at`/`available_at`/`event_at`. `origin_candle_id` permanece como o candle extremo (correto — é genuinamente onde o nível se originou).

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
FULL_BATCH: 12.018 candles, 24.113 structures, errors=[]
PREFIX_REPLAY (n=6.009): match=True diff_count=0
CHUNK_REPLAY (chunk=250): match=True diff_count=0
CHECKPOINT_RESUME (split=6.009): match=True (hash idêntico)
PERSISTED_REPLAY (split=6.009): match=True (hash idêntico)

future_data_violations: 0
prefix_divergence_count: 0
unexplained_id_changes: 0
```

**Todos os 5 modos convergem perfeitamente** sobre as 24.113 estruturas produzidas por 5 anos de dados reais de WINFUT.

## 4. Testes de Regressão

```
pytest tests/test_technical_engine/test_smc_engine_v2_phase04.py -k PreviousHighLow
8 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (235.4s)
```

---

## 5. GATE

```
R16_REAL_DATA_REPLAY_PASS
```

**Justificativa:**
- 5 modos de replay implementados e validados com dados reais completos (12.018 candles H1)
- P0 real de inconsistência causal em PDH/PDL encontrado e corrigido antes de comprometer a fase
- `future_data_violations = 0`, `prefix_divergence_count = 0`, `unexplained_id_changes = 0` — critérios do plano atendidos exatamente
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R17 — Paridade.
