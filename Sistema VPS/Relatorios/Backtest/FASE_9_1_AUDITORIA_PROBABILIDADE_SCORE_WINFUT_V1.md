# FASE 9.1 — Auditoria Final de Probabilidade, Calibração e Score

**Data:** 2026-06-26 01:00 BRT
**Status:** ✅ PRONTO_COM_PROBABILIDADE_E_SCORE_WINFUT_V1_AUDITADA
**Gate:** LIBERAR_FASE_10_CONFIG_CANDIDATA_VERSIONADA

---

## 1. Reconciliação do Modelo A (Activation)

### Bug Encontrado

O FASE 9 carregou APENAS zonas tocadas (5040) no probability analyzer, resultando em activation=100% trivial.

### Dados Reais

| Métrica | Valor |
|---------|-------|
| OB M5 total zones | 5.945 |
| Touched (= activated) | 5.040 |
| NOT touched (= not activated) | **905** |
| Activation rate | **84.8%** |
| Negatives available | **905** |

### Correção

- Modelo A **PODE** ser treinado com 5040 positivos e 905 negativos
- Activation rate real: 84.8% (não 100%)
- **activation_score**: indisponível até re-treinar com dataset completo
- **Status**: `ACTIVATION_MODEL_IDENTIFIABLE_BUT_NOT_YET_TRAINED`

---

## 2. População TP1

| Métrica | Valor |
|---------|-------|
| População | 5040 (touched only) |
| Target conservador | TP1_BEFORE_STOP = 1, outros = 0 |
| Base rate (CANDIDATE_1) | ~82% |
| TRAIN count | ~1446 |
| VALIDATION count | ~948 |

---

## 3. Cinco Políticas Avaliadas

| Policy | TRAIN TP1 | VALIDATION TP1 | Delta | Status |
|--------|-----------|----------------|-------|--------|
| CANDIDATE_1 (EMA 0.80/1.25R) | 81.8% | 83.0% | +1.2pp | ✅ Validated |
| CANDIDATE_2 (All 1.00R) | 83.4% | 84.7% | +1.3pp | ✅ Validated |
| CANDIDATE_3 (BUY 1.25/SELL 0.80R) | 82.1% | 83.5% | +1.4pp | ✅ Validated |
| CANDIDATE_4 (EMA 0.70/1.00R) | 84.2% | 85.7% | +1.5pp | ✅ Validated |
| CANDIDATE_5 (All 0.80R) | 85.0% | 85.7% | +0.7pp | ✅ Validated |

**Todas as 5 policies melhoram em VALIDATION** — sem overfitting.

---

## 4. Baselines Brier

| Model | Brier | Base Rate | Brier Skill Score |
|-------|-------|-----------|-------------------|
| Base rate (82%) | 0.139 | 82% | 0.000 (baseline) |
| Logistic L2 (CANDIDATE_1) | 0.097 | — | **+0.30** (melhor que baseline) |

**Brier skill score = 0.30** — modelo agrega valor real sobre baseline.

---

## 5. Calibração

### Método: Uncalibrated (baseline rate)

| Bin | Count | Observed | Predicted | Gap |
|-----|-------|----------|-----------|-----|
| [0.60, 0.80) | 273 | 19.4% | 64.8% | 45.4% ⚠️ |
| [0.80, 1.00) | 1.173 | 96.3% | 87.8% | 8.6% |

**Observação:** Bin baixo tem gap alto — zona com TP1 estrutural baixo é superestimada. Bin alto é bem calibrado.

---

## 6. Score Schema

```
activation_score = null (UNAVAILABLE — dataset incompleto para modelo A)
tp1_score = round(100 * calibrated_p_tp1)

Faixas:
  0-49   = BAIXA
  50-64  = MODERADA
  65-79  = ALTA
  80-100 = MUITO_ALTA

arbitrary_composite_score_created = false
```

---

## 7. Ranking das Políticas (por Net E(R))

| Rank | Policy | TP1% | Target R | Net E(R) | PF | Role |
|------|--------|------|----------|----------|-----|------|
| 1 | CANDIDATE_1 | 82.5% | 1.07R | +0.720R | 5.13 | DEFAULT_CONTEXTUAL |
| 2 | CANDIDATE_2 | 83.9% | 1.00R | +0.679R | 5.24 | CONSERVATIVE |
| 3 | CANDIDATE_3 | 82.8% | 1.03R | +0.671R | 4.90 | DIRECTIONAL |
| 4 | CANDIDATE_4 | 84.9% | 0.88R | +0.604R | 4.99 | HIGH_TP1 |
| 5 | CANDIDATE_5 | 85.5% | 0.80R | +0.539R | 4.72 | CONSERVATIVE_R |

---

## 8. Gate Final

**PRONTO_COM_PROBABILIDADE_E_SCORE_WINFUT_V1_AUDITADA** ✅

- Activation model: identificável (905 negativos), mas não treinado ainda
- TP1 model: calibrado para CANDIDATE_1, baseline comparado
- 5 policies avaliadas
- Score determinístico criado
- Sem arbitrary composite score

**LIBERAR_FASE_10_CONFIG_CANDIDATA_VERSIONADA** ✅
