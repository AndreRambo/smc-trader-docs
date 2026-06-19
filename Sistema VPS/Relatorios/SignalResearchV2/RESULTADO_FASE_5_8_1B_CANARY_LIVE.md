# RESULTADO FASE 5.8.1B — CANARY LIVE — 2026-06-18

**Status**: CANARY_RUNNING — backlog FIFO, WINFUT na fila

---

## 1. GROUND TRUTH — FUNCIONANDO

```
Total evaluations:            48 (06:50 UTC)
Símbolos com avaliações:      6 (BTCUSDm, ETHUSDm, EURUSDm, USDJPYm, XAGUSDm, XAUUSDm)
WINFUT evaluations:           0 (17 M5 events na fila FIFO)

SCANNER_CALLS:                48 (1 por evento M5)
PERSISTENCE_CALLS:            48 (1:1)
OUTPUT_EQUIVALENCE:           Preservado
SIDE_EFFECTS:                 0
```

---

## 2. DIAGNÓSTICO WINFUT

```
WINFUT M5 PENDING:            17 eventos
WINFUT M5 COMPLETED:          113 (processados antes do restart)
Posição na fila FIFO:         ~600 eventos à frente
Causa:                        Backlog pós-restart — 4.800+ eventos acumulados
                              Eventos processados em ordem cronológica (FIFO)
                              WINFUT M5 criados às 22:18 UTC (ontem) vs 
                              outros ativos desde 18:15 UTC (ontem)

Taxa de processamento:        ~10-15 eventos/min
Tempo estimado para WINFUT:   1-2 horas
```

---

## 3. VALIDAÇÕES (48 avaliações de outros ativos)

| Verificação | Resultado |
|-------------|-----------|
| 1:1 event→eval | ✅ 48/48 |
| Duplicatas | ✅ 0 |
| Conflitos idempotência | ✅ 0 |
| shadow_only=true | ✅ 48/48 |
| can_promote_trade=false | ✅ 48/48 |
| order_emitted=false | ✅ 48/48 |
| external_notification=false | ✅ 48/48 |
| Side effects | ✅ 0 |
| Output equivalence | ✅ Preservado |

---

## 4. OUTCOMES (todos os símbolos)

```
NO_OPPORTUNITY:               48 (100%)
  readiness=BLOQUEADO_TECNICO — esperado (fora do horário de mercado)
```

---

## 5. CANARY

```
CANARY_STATUS:                RUNNING
GROUND_TRUTH_COLLECTION:      CANARY
EVALUATIONS_PERSISTIDAS:      48
WINFUT:                       0 (aguardando fila)
META:                         100 avaliações totais ou 1 pregão WINFUT
```

---

## 6. STATUS

```
FASE_5_8_1B_STATUS:           CANARY_RUNNING
FEATURE_FLAG_RUNNING:         ✅ comprovado (48 avaliações reais)
SERVICE_PID:                  562168
SIDE_EFFECTS:                 0
CONTROL_A_REAL_ADAPTER:       PARTIAL_VALIDATED
PHASE_6_STATUS:               BLOCKED_WAITING_FOR_DATA
```
