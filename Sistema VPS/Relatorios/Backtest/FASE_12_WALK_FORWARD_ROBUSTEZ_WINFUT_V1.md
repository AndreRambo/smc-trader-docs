# FASE 12 — Walk-Forward e Robustez
# 2026-06-26

## Status: ✅ PRONTO_COM_WALK_FORWARD_E_ROBUSTEZ_WINFUT_V1
## Gate: LIBERAR_FASE_13_LIVE_SHADOW

---

## 1. Walk-Forward Quarterly (CANDIDATE_1)

| Window | Zones | TP1% | E(R) | PF | T/dia |
|--------|-------|------|------|-----|-------|
| Q1_2022 | 246 | 82.5% | +0.721R | 5.13 | 3.78 |
| Q2_2022 | 236 | 78.4% | +0.621R | 3.93 | 3.63 |
| Q3_2022 | 266 | 83.8% | +0.746R | 5.61 | 4.09 |
| Q4_2022 | 227 | 84.6% | +0.743R | 5.82 | 3.49 |
| Q1_2023 | 256 | **88.7%** | **+0.840R** | **8.42** | 3.94 |
| Q2_2023 | 243 | 82.3% | +0.706R | 4.99 | 3.74 |
| Q3_2023 | 236 | 76.7% | +0.575R | 3.47 | 3.63 |
| Q4_2023 | 258 | 77.5% | +0.599R | 3.67 | 3.97 |
| Q1_2024 | 257 | 84.0% | +0.734R | 5.71 | 3.95 |
| Q2_2024 | 280 | 82.9% | +0.712R | 5.24 | 4.31 |
| Q3_2024 | 279 | 84.6% | +0.771R | 6.12 | 4.29 |
| Q4_2024 | 249 | 80.3% | +0.661R | 4.36 | 3.83 |
| Q1_2025 | 243 | 83.1% | +0.713R | 5.23 | 3.74 |
| Q2_2025 | 259 | 83.8% | +0.710R | 5.38 | 3.98 |
| Q3_2025 | 264 | 78.0% | +0.597R | 3.72 | 4.06 |
| Q4_2025 | 252 | 82.9% | +0.698R | 5.09 | 3.88 |

---

## 2. Robustez Temporal

| Métrica | Valor | Gate | Status |
|---------|-------|------|--------|
| Positive expectancy quarters | **16/16 (100%)** | ≥75% | ✅ |
| PF > 1 quarters | **16/16 (100%)** | ≥75% | ✅ |
| TP1 > 80% quarters | 12/16 (75%) | — | ✅ |
| Median quarterly TP1 | **82.9%** | >80% | ✅ |
| Worst quarter TP1 | **76.7%** | ≥75% | ✅ |
| All years positive | **True** | True | ✅ |

---

## 3. Bootstrap Temporal (5.000 iterações)

| Métrica | Mediana | CI95 | P(satisfaz gate) |
|---------|---------|------|-------------------|
| TP1 | **82.1%** | [80.6, 83.6%] | **99.6%** |
| E(R) | **+0.697R** | [0.662, 0.729R] | **100%** |

---

## 4. Concentração

| Métrica | Valor |
|---------|-------|
| Top 1 trimestre share | 7.5% |
| Top 3 trimestres share | 21.1% |

Baixa concentração — lucro distribuído uniformemente.

---

## 5. Decisão

**CANDIDATE_1 é ROBUSTA:**
- 100% dos trimestres com expectancy positivo
- 100% dos trimestres com PF > 1
- Bootstrap: 99.6% de chance de TP1 > 80%
- Concentração baixa (7.5% top quarter)
- Todos os anos positivos

**LIBERAR_FASE_13_LIVE_SHADOW** ✅
