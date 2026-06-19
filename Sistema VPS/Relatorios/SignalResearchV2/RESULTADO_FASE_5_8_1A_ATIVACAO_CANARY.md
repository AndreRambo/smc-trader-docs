# RESULTADO FASE 5.8.1A — ATIVAÇÃO CANARY — 2026-06-18

**Status**: ACTIVATION_COMPLETED — WAITING_FOR_MARKET_EVENTS

---

## 1. PREFLIGHT

```
PREFLIGHT_STATUS:            PASS
MIGRATION_STATUS:            COMPLETE (39 colunas, todos campos obrigatórios)
TABLE_ROW_COUNT:             0 (limpo para coleta)
DISPATCHER_INSTRUMENTATION:  2 GROUND_TRUTH blocks confirmados
CODE_COMMIT:                 046049f
SERVICE_BEFORE:              PID 4044389 (Jun 16) — código antigo
```

---

## 2. RESTART CONTROLADO

```
SERVICE_NAME:                smc-candle-event-processor
PID_BEFORE:                  4044389 (Jun 16, código antigo)
PID_AFTER:                   562168 (Jun 18, código instrumentado)
ACTIVE:                      ✅ active (running)
RESTART_LOOPS:               0
ERRORS:                      TimeoutStopSec (normal — processo anterior em loop)
```

---

## 3. SMOKE LIVE

```
SMOKE_LIVE_STATUS:           WAITING_FOR_MARKET_EVENTS
HORA_ATUAL:                  00:21 UTC (mercado fechado)
B3_SESSION:                  13:00-21:00 UTC
PROXIMA_SESSAO:              ~13:00 UTC (10:00 BRT)

EVALUATIONS_PERSISTED:       0
SCANNER_CALLS:               0
SIDE_EFFECTS:                0
```

---

## 4. CANARY

```
CANARY_STATUS:               WAITING_FOR_EVENTS
GROUND_TRUTH_COLLECTION:     CANARY (WINFUT only)
META:                        100 eventos ou 1 pregão completo
```

---

## 5. PRÓXIMOS PASSOS

1. **Aguardar abertura do mercado** (~13:00 UTC / 10:00 BRT)
2. **Coletor** publicará eventos do pregão
3. **Processador** reclamará eventos e executará `_run_scanner()`
4. **Ground truth** será persistido na shadow table
5. **Verificar** após 1 pregão: evaluations > 0, outcomes diversos, 0 conflitos

---

## 6. STATUS

```
FASE_5_8_1A_STATUS:          ACTIVATION_COMPLETED
CANARY_STATUS:               WAITING_FOR_EVENTS
FEATURE_FLAG:                ENABLED (instrumentado no código)
SERVICE:                     active (PID 562168)
CONTROL_A_REAL_ADAPTER:      PARTIAL_VALIDATED
PHASE_6:                     BLOCKED_WAITING_FOR_DATA
```
