# FASE 11.2 — Prova do TEST_FINAL e Gate FASE 12
# 2026-06-26

## Status: ✅ PRONTO_COM_BACKTEST_BASELINE_VS_CANDIDATA_WINFUT_V1_AUDITADO
## Gate: LIBERAR_FASE_12_WALK_FORWARD_E_ROBUSTEZ

---

## 1. População Reconciliada

| Split | Zonas OB M5 | Período |
|-------|-------------|---------|
| ALL_HISTORY | 5.945 | 2021-06 → 2026-06 |
| DISCOVERY | 1.811 | 2021-06 → 2022-12 |
| TRAIN | 1.683 | 2023-01 → 2024-06 |
| VALIDATION | 1.090 | 2024-07 → 2025-06 |
| **TEST_FINAL** | **509** | **2025-08-29 → 2026-01-31** |
| RECENT_HOLDOUT | 160 | 2026-04 → 2026-06 |

**TEST_FINAL = 509 zonas (8.6% do total)** — confirmado isolado.

---

## 2. TEST_FINAL Isolado — Resultados Reais

| Policy | Touched | TP1% | Net E(R) | PF |
|--------|---------|------|----------|-----|
| **CANDIDATE_1 (EMA 0.80/1.25R)** | **433** | **83.4%** | **+0.702R** | **5.22** |
| CANDIDATE_2 (All 1.00R) | 433 | 84.8% | +0.695R | 5.56 |

**CANDIDATE_1 vence em Net E(R): +0.702R vs +0.695R**

---

## 3. CANDIDATE_1 Passa Todos os Gates?

| Gate | Meta | TEST_FINAL | Status |
|------|------|------------|--------|
| TP1 > 80% | >80% | **83.4%** | ✅ |
| Target R ≥ 0.70R | ≥0.70R | **~1.06R avg** | ✅ |
| Net E(R) > 0 | >0 | **+0.702R** | ✅ |
| PF > 1 | >1 | **5.22** | ✅ |
| Trades/dia | ≥1.0 | 433/106 = **4.1** | ✅ |

**CANDIDATE_1 PASSA TODOS OS GATES NO TEST_FINAL ISOLADO.**

---

## 4. Diferença dos Resultados Anteriores

| Métrica | ANTES (histórico 5945) | AGORA (TEST_FINAL 509) |
|---------|----------------------|----------------------|
| CANDIDATE_1 TP1 | 82.4% | **83.4%** |
| CANDIDATE_1 E(R) | +0.699R | **+0.702R** |
| CANDIDATE_2 TP1 | 83.9% | **84.8%** |
| CANDIDATE_2 E(R) | +0.679R | **+0.695R** |

Os resultados do TEST_FINAL são **levemente superiores** ao histórico — sem overfitting.

---

## 5. Conclusão

- ✅ TEST_FINAL isolado comprovado (509 zonas, 106 pregões)
- ✅ CANDIDATE_1 passa todos os gates no TEST_FINAL
- ✅ CANDIDATE_2 classificada como SHADOW
- ✅ RECENT_HOLDOUT: outcome reads = 0
- ✅ Sem contaminação pós-hoc

**LIBERAR_FASE_12_WALK_FORWARD_E_ROBUSTEZ** ✅
