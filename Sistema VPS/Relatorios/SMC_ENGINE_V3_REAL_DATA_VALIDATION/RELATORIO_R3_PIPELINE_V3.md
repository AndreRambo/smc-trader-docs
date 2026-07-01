# RELATÓRIO R3 — PIPELINE V3
## Entrypoint Causal Canônico e Execução com Dados Reais

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `technical_engine/smc_engine_v3/pipeline_v3.py`

---

## 1. Decisão de Design (ver RELATORIO_R0_ADDENDUM_CORRECAO.md)

Em vez de reescrever algoritmos de detecção do zero, `run_smc_engine_v3()` foi construído como um **driver batch fino** sobre o motor incremental já causal (`incremental/engine.py` + `incremental/components/*`), reaproveitando:

- 9 componentes causais (`SwingComponent`, `FvgComponent`, `ObComponent`, `BosChochComponent`, `LiquidityComponent`, `BprComponent`, `PreviousHighLowComponent`, `SessionsComponent`, `RetracementsComponent`)
- `SmcEngineV2Incremental.on_candle_closed()` — validação de sequência monotônica, detecção de duplicata/gap, hash de estado determinístico
- `BatchAdapter` — itera candles chamando `on_candle_closed()` um a um

O pipeline batch legado (`pipeline.py`, `swings.py`, `fvg.py` na raiz do módulo — com look-ahead documentado no R0) **não foi usado como base** e permanece intocado.

## 2. Contrato `SmcEngineV3Result`

```python
@dataclass
class SmcEngineV3Result:
    asset_id: str
    symbol: str
    timeframe: str
    engine_version: str          # "smc_engine_v3_causal"
    calculation_version: str
    parameter_hash: str
    candles_processed: int
    structures_by_component: dict[str, list[StructureEmission]]
    events_by_component: dict[str, list[StructureEventEmission]]
    diagnostics: dict[str, Any]
    final_state_hash: str
    errors: list[str]
```

Nenhum record V2 é retornado como saída canônica — `StructureEmission`/`StructureEventEmission` são os únicos tipos de dado emitidos, com `origin_at`/`confirmed_at`/`available_at` obrigatórios no payload de cada estrutura causal.

**`raise_on_error=True` por padrão** — exceções de componentes ou do motor (candle fora de sequência, gap, duplicata conflitante) propagam ao chamador.

## 3. Correção da Ordem Causal

`_COMPONENT_ORDER` em `incremental/engine.py` foi corrigida de:

```
swings, fvg, order_blocks, bos_choch, liquidity, bpr, previous_high_low, sessions, retracements
```

para a ordem oficial do plano mestre (seção 5):

```
sessions, swings, bos_choch, previous_high_low, retracements, liquidity, fvg, bpr, order_blocks
```

**Achado relevante:** cada componente do motor incremental rastreia seu próprio estado interno necessário (ex.: `BprComponent` reimplementa a detecção de FVG internamente em vez de consumir a saída do `FvgComponent` no mesmo tick; `BosChochComponent` rastreia seus próprios swings confirmados). Por isso, a reordenação **não alterou nenhum resultado numérico** (confirmado pelos 2.102 testes de `tests/test_technical_engine/` — 100% PASS antes e depois da mudança). A correção de ordem foi aplicada por conformidade ao contrato do plano e para manter a lista agregada de eventos por tick em ordem de leitura causal.

**P1 registrado para R6/R9/R12:** a reimplementação independente de swing/FVG dentro de `BosChochComponent`/`BprComponent`/`ObComponent` diverge do contrato do plano ("Order Block consome StructureEventV3", "Liquidity consome EqualLevelClusterV3"). Cada componente hoje é uma implementação paralela e não uma consumidora formal da saída do componente upstream. Isso não causa look-ahead (cada um é causal isoladamente), mas é um risco de divergência entre "a fonte de verdade" (ex.: `SwingComponent`) e o que `BosChochComponent` calcula internamente. A consolidação para consumo cruzado real fica registrada como trabalho das Fases R6, R9 e R12.

## 4. Execução com Dados Reais

### 4.1 Amostra de Validação Rápida (500 candles H1)

```
candles_processed: 500
structure counts: sessions=223 swings=63 bos_choch=192 previous_high_low=40
                   retracements=187 liquidity=63 fvg=143 bpr=78 order_blocks=77
errors: []
```

### 4.2 Execução Completa — WINFUT H1 Canônico (12.018 candles reais, 2021-06-22 a 2026-06-19)

```
candles_processed: 12018
structure counts:
  sessions=4984  swings=1519  bos_choch=4775  previous_high_low=1000
  retracements=4472  liquidity=1527  fvg=2966  bpr=1370  order_blocks=1761
event counts:
  sessions=4984  swings=1519  bos_choch=4775  previous_high_low=1000
  retracements=6503  liquidity=2992  fvg=10246  bpr=2725  order_blocks=6099
final_state_hash: c054c520294c8701...
diagnostics:
  structures_emitted_total: 24374
  events_emitted_total: 40843
  duplicate_idempotent_count: 0
  gap_count: 0
errors: []
tempo de execução: 6m26.9s (386.9s) — 12.018 candles reais, sem falha
```

**Zero erros, zero gaps, zero conflitos de duplicata em 12.018 candles reais.**

**Observação de performance (P1, não bloqueante):** latência do último tick = 22,4ms, refletindo custo O(n) crescente em alguns componentes (ex.: `BprComponent`/`ObComponent` reescaneiam buffers de zonas ativas). Aceitável para H1 (12k candles), mas **a serem monitorados na Fase R16 (replay)** ao processar M1 (689k candles) — pode exigir otimização de buffer antes do replay completo em M1.

### 4.3 Verificação de Prefix Invariance (crítica para causalidade)

Executado como parte da validação R3 (não é a Fase R17 formal de paridade, mas uma verificação de sanidade prévia):

```
run_smc_engine_v3(rows[:150])              -> state_hash = e6719239...
run_smc_engine_v3(rows[:300])[:150 prefix] -> state_hash = e6719239... (IDÊNTICO)
swing IDs do run de 150 candles ⊆ swing IDs do run de 300 candles: True (19 ⊆ 39)
```

**Confirmado: processar um prefixo de N candles produz exatamente o mesmo resultado que processar N candles isoladamente.** Nenhum componente usa dados além do que já foi entregue via `on_candle_closed()`. Esta é a propriedade estrutural que elimina look-ahead por construção — validação formal completa ocorre na Fase R17.

## 5. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2102 passed, 0 failed (237.2s)
```

Confirmado que a mudança de `_COMPONENT_ORDER` não introduziu nenhuma regressão.

---

## 6. GATE

```
R3_V3_PIPELINE_CANONICAL_PASS
```

**Justificativa:**
- `run_smc_engine_v3()` e `SmcEngineV3Result` existem e não retornam records V2 (P0-01 do R0 resolvido)
- Ordem causal oficial implementada em `_COMPONENT_ORDER` (P0-02 do R0 resolvido para o motor incremental; o pipeline batch legado permanece com a ordem antiga, mas não é mais usado como base da V3)
- `raise_on_error=True` por padrão
- Executado com sucesso em 12.018 candles reais de WINFUT H1, zero erros
- Prefix invariance confirmada preliminarmente (validação formal completa: Fase R17)
- 2.102 testes de regressão, 0 falhas

**P0-03 (look-ahead em `swings.py` legado) e P0-04 (look-ahead em `fvg.py` legado) permanecem tecnicamente abertos no pipeline BATCH LEGADO**, mas não afetam o gate R3 porque esse pipeline não é mais o caminho canônico da V3 — o `RELATORIO_R0_ADDENDUM_CORRECAO.md` documenta que o motor incremental usado por `pipeline_v3.py` não tem este defeito.

**P1 aberto:** reimplementação independente (não consumo cruzado formal) de Swing/FVG dentro de `BosChochComponent`/`BprComponent`/`ObComponent` — a ser resolvido nas Fases R6/R9/R12.

**Próxima fase:** R4 — Sessions.
