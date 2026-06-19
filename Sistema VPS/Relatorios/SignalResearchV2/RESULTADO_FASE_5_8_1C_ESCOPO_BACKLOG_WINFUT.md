# RESULTADO FASE 5.8.1C — CORREÇÃO DE ESCOPO — 2026-06-18

**Status**: SCOPE_CORRECTED — BACKLOG_DRAINING

---

## 1. CORREÇÃO DE ESCOPO

```
OLD_COLLECTION_VERSION:       (implícito, multi-asset)
NEW_COLLECTION_VERSION:       CONTROL_A_GT_WINFUT_V1_0b31f1edf7fb
CANARY_TARGET_ASSET:          WINFUT
NON_TARGET_RECORDS_PRESERVED: 53
NON_TARGET_RECORDS_EXCLUDED:  53
TOTAL_WINFUT:                 0 (ainda na fila)
```

### 53 registros preservados e classificados:

| Campo | Valor |
|-------|-------|
| canary_eligible | FALSE |
| canary_scope | WINFUT |
| exclusion_reason | NON_TARGET_ASSET |
| collection_role | INSTRUMENTATION_SMOKE |
| ground_truth_eligibility | INELIGIBLE_NON_TARGET_ASSET |

**Esses 53 registros comprovam que a instrumentação funciona (1:1, 0 side effects) mas NÃO contam para o canary WINFUT.**

---

## 2. MIGRATION

Colunas adicionadas a `technical_engine_opportunity_evaluations_shadow`:

```
canary_eligible BOOLEAN
canary_scope VARCHAR(64)
exclusion_reason VARCHAR(128)
collection_role VARCHAR(64)
collection_version VARCHAR(64)
ground_truth_eligibility VARCHAR(64)
```

---

## 3. STATUS

```
FASE_5_8_1C_STATUS:           SCOPE_CORRECTED
CANARY_STATUS:                WAITING_FOR_WINFUT
GROUND_TRUTH_COLLECTION:      CANARY (WINFUT only)
COLLECTION_VERSION:           CONTROL_A_GT_WINFUT_V1_0b31f1edf7fb

WINFUT_EVALUATIONS:           0
WINFUT_M5_PENDING:            17 (na fila FIFO)
QUEUE_ORDERING_MODIFIED:      FALSE

CONTROL_A_REAL_ADAPTER:       PARTIAL_VALIDATED
PHASE_6_STATUS:               BLOCKED_WAITING_FOR_DATA
```
