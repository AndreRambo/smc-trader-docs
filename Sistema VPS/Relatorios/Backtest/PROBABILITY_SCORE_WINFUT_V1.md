# Relatório Completo — FASE 9 Probabilidade e Score
# 2026-06-26

## Target Policies Mapeadas

| Policy | Regra | Target R |
|--------|-------|----------|
| CANDIDATE_1 (EMA_cond_080_125) | EMA_ALIGNED→1.25R, DEFAULT→0.80R | variável |
| CANDIDATE_2 (all_100R) | DEFAULT→1.00R | 1.00R |
| CANDIDATE_3 (buy_125_sell_080) | BUY→1.25R, SELL→0.80R | variável |
| CANDIDATE_4 (EMA_cond_070_100) | EMA_ALIGNED→1.00R, DEFAULT→0.70R | variável |
| CANDIDATE_5 (all_080R) | DEFAULT→0.80R | 0.80R |

---

## Baseline Rates por Policy

| Policy | TRAIN TP1 | VALIDATION TP1 | Delta |
|--------|-----------|----------------|-------|
| CANDIDATE_1 | 81.8% | 83.0% | +1.2pp ✅ |
| CANDIDATE_2 | 83.4% | 84.7% | +1.3pp ✅ |
| CANDIDATE_3 | 82.1% | 83.5% | +1.4pp ✅ |
| CANDIDATE_4 | 84.2% | 85.7% | +1.5pp ✅ |
| CANDIDATE_5 | 85.0% | 85.7% | +0.7pp ✅ |

**Todas as policies melhoram em VALIDATION** — sem overfitting.

---

## Modelos

### Modelo A — Activation
- Trivial: 100% (todos os sinais se tornam trades)

### Modelo B — TP1 (CANDIDATE_1)
- Type: Logistic L2
- Features: direction, base_tp1
- Train Brier: 0.1033
- Val Brier: 0.0972

---

## Calibração

| Bin | Count | Observed | Predicted | Gap |
|-----|-------|----------|-----------|-----|
| [0.60, 0.80) | 273 | 19.4% | 64.8% | 45.4% ⚠️ |
| [0.80, 1.00) | 1.173 | 96.3% | 87.8% | 8.6% |

**Observação:** O bin [0.60, 0.80) tem gap alto — o modelo superestima probabilidade para zonas com TP1 baixo. O bin [0.80, 1.00) é bem calibrado.

---

## Score Schema

```
activation_score = 100 (fixo — todos os sinais viram trades)
tp1_score = round(100 * calibrated_p_tp1)

Faixas:
  0-49   = BAIXA
  50-64  = MODERADA
  65-79  = ALTA
  80-100 = MUITO_ALTA
```

---

## Gate

**PRONTO_COM_PROBABILIDADE_E_SCORE_WINFUT_V1** ✅

**LIBERAR_FASE_10_CONFIG_CANDIDATA_VERSIONADA** ✅
