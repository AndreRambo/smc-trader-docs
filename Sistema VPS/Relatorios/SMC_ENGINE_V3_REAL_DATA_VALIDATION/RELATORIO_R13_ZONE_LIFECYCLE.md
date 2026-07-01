# RELATÓRIO R13 — ZONE LIFECYCLE AUDIT
## Ferramentas de Auditoria + Correção de Timestamps Faltantes em PDH/PDL

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivos criados:** `tools/smc_v3_validation/export_zone_events.py`, `tools/smc_v3_validation/audit_zone_lifecycle.py`
**Arquivo corrigido:** `technical_engine/smc_engine_v3/incremental/components/previous_high_low.py`

---

## 1. Ferramentas Criadas

### `export_zone_events.py`
Consome `run_smc_engine_v3()` e produz dois CSVs, nunca sobrescrevendo histórico:
- **`zone_lifecycle_audit.csv`** — uma linha por estrutura: origem, confirmação, disponibilidade, preços, e a sequência completa de eventos (`event_sequence`, ex.: `AVAILABLE|TOUCHED|MITIGATED`)
- **`zone_state_transitions.csv`** — uma linha por evento, granular, fácil de filtrar/comparar

### `audit_zone_lifecycle.py`
Consome os dois CSVs e verifica invariantes explícitas do plano:
- timestamps ausentes (`confirmed_at`/`available_at` nulos)
- `event_id` duplicado
- eventos terminais (`MITIGATED`/`SWEPT`) duplicados para a mesma estrutura
- `events_per_broken_level <= 1` para BOS/CHOCH (reconfirma a invariante corrigida no R4/R6)

## 2. Bug Encontrado ao Rodar as Ferramentas: Timestamps Ausentes em PDH/PDL

Ao rodar `export_zone_events.py` pela primeira vez sobre dados reais, `audit_zone_lifecycle.py` reportou **110 estruturas com `confirmed_at`/`available_at` ausentes**, todas do tipo `PDH`/`PDL`.

**Causa raiz:** `_publish_pdh_pdl()` (introduzida no R7) nunca recebia o candle que disparou a publicação — o `StructureEmission` era criado sem `confirmed_at`/`available_at`, e o `StructureEventEmission` sem `event_at`. Bug real desde R7, não detectado até agora porque os testes sintéticos daquela fase não verificavam esses campos especificamente.

**Correção:** `_publish_pdh_pdl()` agora recebe o candle (o primeiro candle do **novo** dia de pregão — o gatilho que informa que o dia anterior terminou) e usa `candle.close_at`/`candle.available_at` para os timestamps. Semanticamente correto: o PDH/PDL só se torna "confirmado e disponível" no momento exato em que sabemos que o dia anterior encerrou.

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
total_structures: 24.113
total_events: 46.959
missing_timestamps: 0
duplicate_event_ids: 0
duplicate_terminal_events: 0
events_per_broken_level_violations: 0

GATE: R13_ZONE_LIFECYCLE_AUDIT_PASS
```

**Zero violações em qualquer categoria**, sobre a totalidade das zonas produzidas por todas as 9 engines ao longo de 5 anos de dados reais de WINFUT.

## 4. Testes de Regressão

```
pytest tests/test_technical_engine/test_smc_engine_v2_phase04.py -k PreviousHighLow
8 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (245.8s)
```

---

## 5. GATE

```
R13_ZONE_LIFECYCLE_AUDIT_PASS
```

**Justificativa:**
- Ferramentas `export_zone_events.py` e `audit_zone_lifecycle.py` criadas e funcionais
- Bug real de timestamps ausentes em PDH/PDL (desde R7) encontrado e corrigido pelas próprias ferramentas de auditoria desta fase
- Zero violações de qualquer invariante em 24.113 estruturas e 46.959 eventos reais
- 2.103 testes de regressão, 0 falhas
- Nenhuma transição sobrescreve histórico — cada evento é uma linha nova e imutável em `zone_state_transitions.csv`

**Próxima fase:** R14 — Persistência V3.
