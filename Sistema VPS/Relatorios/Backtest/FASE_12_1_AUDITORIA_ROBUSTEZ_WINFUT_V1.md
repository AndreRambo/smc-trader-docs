# FASE 12.1 — Auditoria Final Walk-Forward e Robustez
# 2026-06-26

## Status: ✅ PRONTO_COM_WALK_FORWARD_E_ROBUSTEZ_WINFUT_V1_AUDITADO
## Gate: LIBERAR_FASE_13_LIVE_SHADOW

---

## 1. BUY vs SELL

| Direction | N | TP1% | Net E(R) | PF |
|-----------|-----|------|----------|-----|
| BUY | 2.540 | 82.0% | +0.689R | 4.85 |
| SELL | 2.516 | 82.7% | +0.709R | 5.11 |

✅ Ambas positivas. SELL ligeiramente superior. Balanceado.

---

## 2. EMA Branches

| Branch | N | TP1% | Net E(R) | PF |
|--------|-----|------|----------|-----|
| **EMA ALIGNED (1.25R)** | **2.982** | **81.2%** | **+0.828R** | **5.43** |
| DEFAULT (0.80R) | 2.074 | 84.0% | +0.513R | 4.21 |

✅ Ambos exercidos. ALIGNED tem maior E(R) e PF. DEFAULT tem maior TP1.

---

## 3. Cost Stress

| Multiplier | Net E(R) | PF | Gate (>0, >1) |
|------------|----------|-----|---------------|
| x1.0 (base) | +0.689R | 4.88 | ✅ |
| x1.5 | +0.684R | 4.84 | ✅ |
| **x2.0** | **+0.679R** | **4.79** | **✅** |
| x3.0 | +0.669R | 4.70 | ✅ |

✅ ROBUST_TO_COST_X3 — mesmo com 3x custos, positivo.

---

## 4. Concentração

| Métrica | Valor |
|---------|-------|
| Top 1 day | 0.2% |
| Top 5 days | 1.2% |
| Top 10 days | 2.3% |
| Total trading days | 1.241 |

✅ **LOW_CONCENTRATION** — lucro distribuído uniformemente.

---

## 5. Drawdown

| Métrica | Valor |
|---------|-------|
| Max drawdown | **-4.70R** |
| Recovery factor | ~10x |

✅ Max DD de -4.70R é moderado para 5+ anos de operação.

---

## 6. Walk-Forward Quarterly (resumo)

| Gate | Resultado | Status |
|------|-----------|--------|
| Positive expectancy ≥75% | **16/16 (100%)** | ✅ |
| PF > 1 ≥75% | **16/16 (100%)** | ✅ |
| Median TP1 > 80% | **82.9%** | ✅ |
| Worst quarter TP1 ≥ 75% | **76.7%** | ✅ |
| All years positive | **True** | ✅ |

---

## 7. Bootstrap (5.000 iterações)

| Métrica | Mediana | CI95 | P(gate) |
|---------|---------|------|---------|
| TP1 | 82.1% | [80.6, 83.6%] | 99.6% |
| E(R) | +0.697R | [0.662, 0.729R] | 100% |

---

## 8. Decisão

**CANDIDATE_1 é ROBUSTA em todas as dimensões testadas:**

| Gate | Status |
|------|--------|
| BUY positivo | ✅ +0.689R |
| SELL positivo | ✅ +0.709R |
| EMA ALIGNED exercido | ✅ +0.828R |
| EMA DEFAULT exercido | ✅ +0.513R |
| COST_X2 aprovado | ✅ +0.679R |
| Concentração baixa | ✅ 2.3% top 10 |
| Drawdown moderado | ✅ -4.70R |
| Bootstrap favorável | ✅ P(TP1>80%)=99.6% |

**LIBERAR_FASE_13_LIVE_SHADOW** ✅
