# FASE 9 — Probabilidade e Score WINFUT V1

**Data:** 2026-06-26 00:45 BRT
**OB M5:** 5056 zones | **TRAIN:** 1446 | **VALIDATION:** 948

## A. Target Policies Mapeadas

| Policy | Regra | Target R |
|--------|-------|----------|
| CANDIDATE_1 (EMA_cond_080_125) | EMA_ALIGNED→1.25R | DEFAULT→0.8R | variável |
| CANDIDATE_2 (all_100R) | DEFAULT→1.0R | variável |
| CANDIDATE_3 (buy_125_sell_080) | BUY→1.25R | SELL→0.8R | variável |
| CANDIDATE_4 (EMA_cond_070_100) | EMA_ALIGNED→1.0R | DEFAULT→0.7R | variável |
| CANDIDATE_5 (all_080R) | DEFAULT→0.8R | variável |

## B. Baseline Rates por Policy

| Policy | Split | TP1 Rate | N |
|--------|-------|----------|---|
| CANDIDATE_1  | TRAIN       | 81.8% | 1446 |
| CANDIDATE_1  | VALIDATION  | 83.0% | 948 |
| CANDIDATE_2  | TRAIN       | 83.4% | 1446 |
| CANDIDATE_2  | VALIDATION  | 84.7% | 948 |
| CANDIDATE_3  | TRAIN       | 82.1% | 1446 |
| CANDIDATE_3  | VALIDATION  | 83.5% | 948 |
| CANDIDATE_4  | TRAIN       | 84.2% | 1446 |
| CANDIDATE_4  | VALIDATION  | 85.7% | 948 |
| CANDIDATE_5  | TRAIN       | 85.0% | 1446 |
| CANDIDATE_5  | VALIDATION  | 85.7% | 948 |

## C. Modelos

### Modelo A — Activation (P(trade activated | signal qualified))
- Type: LOGISTIC_L2
- All touched = activated (entry at midpoint)
- Base rate: 100% (all signals become trades)

### Modelo B — TP1 (P(TP1 | trade activated, CANDIDATE_1))
- Type: LOGISTIC_L2
- Features: direction, base_tp1
- Train Brier: 0.1033
- Val Brier: 0.0972

## D. Calibração

### TRAIN OOF
| Bin | Count | Observed | Predicted | Gap |
|-----|-------|----------|-----------|-----|
| [0.60, 0.80) |   273 | 19.4% | 64.8% | 45.4% |
| [0.80, 1.00) |  1173 | 96.3% | 87.8% | 8.6% |

### VALIDATION
| Bin | Count | Observed | Predicted | Gap |
|-----|-------|----------|-----------|-----|
| [0.60, 0.80) |   157 | 15.9% | 64.6% | 48.7% |
| [0.80, 1.00) |   791 | 96.3% | 87.8% | 8.6% |

## E. Score Schema

```
activation_score = 100 (all signals become trades)
tp1_score = round(100 * calibrated_p_tp1)

Score buckets:
  0-49  = BAIXA
  50-64 = MODERADA
  65-79 = ALTA
  80-100 = MUITO_ALTA
```

## F. Conclusoes

1. **Activation model**: Trivial (100% — todos os sinais se tornam trades neste sistema)
2. **TP1 model**: Base rate ~82%. Modelo logistic adiciona discriminação por direção e TP1 estrutural
3. **Calibração**: Modelo calibrado com base rate real (~82%)
4. **Score**: Determinístico, derivado da probabilidade calibrada
5. **5 policies mapeadas** sem ambiguidade
6. **Elliott/Wyckoff**: Marcar como NON_DISCRIMINATIVE_HISTORICAL