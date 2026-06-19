# RESULTADO FASE 5.8.1D — DRENAGEM BACKLOG — 2026-06-18

**Status**: BACKLOG_DRAINING

---

## 1. AUDITORIA DA FILA

```
BACKLOG_TOTAL_PENDING:        4.851
WINFUT_M5_PENDING:            17
EVENTS_AHEAD_OF_FIRST_WINFUT: 506
OLDEST_PENDING:               2026-06-17 18:20 UTC
OLDEST_WINFUT_M5:             2026-06-17 19:08 UTC

TOTAL_EVALUATIONS:            58 (0 WINFUT)
QUEUE_ORDERING_MODIFIED:      FALSE
```

---

## 2. ETA

```
PROCESSING_RATE_OBSERVED:     ~3-5 avaliações/hora (apenas eventos M5 geram eval)
FIRST_WINFUT_ETA:             Várias horas (506 eventos à frente)
DRAIN_COMPLETE_ETA:           Dias (4.851 eventos, ~3-5 eval/h)

OBSERVAÇÃO: A maioria dos eventos é M1/M2 (handlers leves, não geram eval).
            Apenas M5 chama _run_scanner(). A taxa de drenagem para
            ground truth é limitada pela proporção de eventos M5.
```

---

## 3. PLANO DE CANARY

```
ABORDAGEM:                    Aguardar eventos WINFUT M5 FRESCOS do próximo pregão
PROXIMO_PREGAO:               Hoje ~13:00 UTC (10:00 BRT)
                              Eventos fresh entrarão no final da fila
                              Serão processados após o backlog atual

CANARY_META:                  100 avaliações WINFUT ELIGIBLE_FORWARD
                              ou 1 pregão WINFUT completo
```

---

## 4. STATUS

```
FASE_5_8_1D_STATUS:           BACKLOG_DRAINING
CANARY_STATUS:                WAITING_FOR_WINFUT
GROUND_TRUTH_COLLECTION:      CANARY
COLLECTION_VERSION:           CONTROL_A_GT_WINFUT_V1_0b31f1edf7fb

SERVICE:                      active (PID 562168)
FEATURE_FLAG:                 comprovada (58 avaliações de 7 ativos)
SIDE_EFFECTS:                 0

QUEUE_ORDERING_MODIFIED:      FALSE
WINFUT_EVALUATIONS:           0
NON_WINFUT_PRESERVED:         58 (INSTRUMENTATION_SMOKE)

CONTROL_A_REAL_ADAPTER:       PARTIAL_VALIDATED
PHASE_6_STATUS:               BLOCKED_WAITING_FOR_DATA
```
