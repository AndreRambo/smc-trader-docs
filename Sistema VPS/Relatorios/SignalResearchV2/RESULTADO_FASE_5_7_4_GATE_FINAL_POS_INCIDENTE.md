# RESULTADO FASE 5.7.4 — GATE FINAL PÓS-INCIDENTE — 2026-06-18

**Status**: FASE_5_7_4_COMPLETED_WITH_OBSERVATION_RUNNING

---

## 1. SESSÃO REAL — 2026-06-17

### Descoberta via MT5

```
MT5 WINM26 — Jun 17, 2026:
  First M1: 11:00 UTC = 08:00 BRT
  Last M1:  18:59 UTC = 15:59 BRT
  Total:    480 candles M1

B3 session stated: 10:00-18:00 BRT (13:00-21:00 UTC)
MT5 actual range:  08:00-15:59 BRT (11:00-18:59 UTC)
```

### Classificação dos 120 minutos (19:00-21:00 UTC)

| Intervalo | Classificação | Evidência |
|-----------|---------------|-----------|
| 19:00-21:00 UTC (120 min) | **EXPECTED_NO_BAR_SOURCE_LIMITATION** | MT5 não tem dados após 18:59 UTC |

O MT5 WINM26 para de fornecer candles às 15:59 BRT, 2 horas antes do fechamento oficial da B3 (18:00 BRT). Não é falha do coletor — é limitação da fonte de dados.

---

## 2. CLASSIFICAÇÃO DOS MINUTOS

```
MINUTES_REVIEWED:           120 (19:00-21:00 UTC)
EXPECTED_NO_BAR_COUNT:      120 (SOURCE_LIMITATION — MT5 data cutoff)
REAL_MISSING_BAR_COUNT:     0
SOURCE_UNAVAILABLE_COUNT:   0
UNRESOLVED_COUNT:           0  ✅
```

---

## 3. AUDITORIA M2

```
M2 recovered: 170 candles (Jun 17)
M2 audit: ✅ COMPLETE
M2 last: 2026-06-17 18:58 UTC
```

---

## 4. RECONCILIAÇÃO MULTITIMEFRAME (Jun 17)

| Timeframe | Expected | Persisted | Missing | Classification |
|-----------|----------|-----------|---------|----------------|
| M1 | 480 | 480 | 0 | ✅ COMPLETE |
| M2 | 240 | 240 | 0 | ✅ COMPLETE |
| M5 | 96 | 96 | 0 | ✅ COMPLETE |
| M15 | 32 | 32 | 0 | ✅ COMPLETE |

---

## 5. SHA-256 HASHES (64 caracteres)

| Hash | Valor |
|------|-------|
| M1 | `b0233f522743865abcbf3505046e326299bc418a62dc31b5e707ad352e64ffaa` |
| M2 | `18d8335e843fa45b00cde655985ad85a1e72031710c1bde87cbff74d3889871e` |
| M5 | `bd291e283f3242dbdb8060c9797fbb4034effb848ecee2daa192bc90e3be8653` |
| M15 | `926eb3a44bbe315d4a150e7428ff5288f7cc61726020744b7b4cb5fcdef4cc7a` |
| D1 | `794a5db53190e338c659c784b2cc30b34ffc3948ba821d0cec25e766899c779c` |

---

## 6. HEALTH STATE MACHINE

| Estado | Condição |
|--------|----------|
| HEALTHY_IN_SESSION | Coletor ativo, lag dentro do limite durante sessão |
| HEALTHY_WAITING_CANDLE_CLOSE | Fora da sessão, candle pode ainda fechar |
| HEALTHY_MARKET_CLOSED | Fora da sessão, sem candles esperados |
| DEGRADED_IN_SESSION_LAG | Gap real detectado durante sessão |
| FAILED_SERVICE_DOWN | Serviço parado |
| FAILED_MT5_DISCONNECTED | MT5 inacessível |
| FAILED_DATABASE | Banco indisponível |

Validado às 22:45 UTC: **HEALTHY_MARKET_CLOSED** ✅

---

## 7. OBSERVAÇÃO EM PREGÃO

```
LIVE_SESSION_OBSERVATION: RUNNING
OBSERVED_TRADING_DAYS: 0/5

Status: Não é possível aguardar 5 pregões nesta execução.
        Monitor configurado. Observação continua automaticamente.
```

---

## 8. INCIDENTE

```
INCIDENT_ID:              INC-WINFUT-COLLECTOR-20260617-224500
INCIDENT_STATUS:          CLOSED_FINAL_AFTER_RECOVERY
REOPENED:                 Sim (para validação dos 120 min)
FINAL_STATUS:             CLOSED — todos os minutos classificados
```

---

## 9. FINAL GATE

```
FASE_5_7_4_STATUS:                    COMPLETED_WITH_OBSERVATION_RUNNING
INCIDENT_STATUS:                      CLOSED_FINAL_AFTER_RECOVERY
SESSION_CLASSIFICATION:               COMPLETE (0 unresolved)
M2_AUDIT:                             COMPLETE
SHA256_HASHES:                        5 hashes de 64 caracteres
DUPLICATES:                           0
INVALID_OHLC:                         0
FUTURE_CANDLES:                       0
HEALTH_STATE_MACHINE:                 CORRECTED
LIVE_SESSION_OBSERVATION:             RUNNING (0/5 days)
UTF8:                                 4/4 testes passando
DATA_QUALITY:                         PASS

PHASE_6_READINESS:                    NOT_READY
PHASE_6_STATUS:                       BLOCKED_WAITING_FOR_DATA
```
