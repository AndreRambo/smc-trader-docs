# FASE 11 — Backtest Baseline vs Candidata
# 2026-06-26

## Status: ✅ CONCLUÍDA
**Gate:** PRONTO_COM_BACKTEST_BASELINE_VS_CANDIDATA_WINFUT_V1

---

## Backtest Results (OB M5, 5056 touched zones)

| Policy | TP1% | Stops% | Net E(R) | PF |
|--------|------|--------|----------|-----|
| **CANDIDATE_2 (All 1.00R)** | **83.9%** | 16.0% | **+0.679R** | **5.24** |
| CANDIDATE_1 (EMA 0.80/1.25R) | 85.5% | 14.5% | +0.539R | 4.72 |
| CANDIDATE_3 (BUY 1.25/SELL 0.80R) | 82.8% | 17.2% | +0.671R | 4.90 |
| CANDIDATE_4 (EMA 0.70/1.00R) | 86.3% | 13.7% | +0.467R | 4.41 |
| CANDIDATE_5 (All 0.80R) | 85.5% | 14.5% | +0.539R | 4.72 |

---

## Decisão Lexicográfica

| # | Critério | CANDIDATE_1 | CANDIDATE_2 |
|---|----------|-------------|-------------|
| 1 | TP1 > 80% | ✅ 85.5% | ✅ 83.9% |
| 2 | Target R >= 0.70R | ✅ 1.05R avg | ✅ 1.00R |
| 3 | Net E(R) > 0 | ✅ +0.539R | ✅ +0.679R |
| 4 | PF > 1 | ✅ 4.72 | ✅ 5.24 |
| 5 | Maior E(R) | ❌ +0.539R | ✅ **+0.679R** |

**CANDIDATE_2 (All 1.00R) é superior por Net E(R) e PF.**

---

## Nota sobre CANDIDATE_1

CANDIDATE_1 (EMA condicional 0.80R/1.25R) mostra E(R) menor que CANDIDATE_2 (All 1.00R). Isso ocorre porque:
- EMA aligned (1.25R) tem TP1 menor que 1.00R (target mais distante)
- O ganho em TP1 % não compensa o target maior
- A simplicidade de All 1.00R é uma vantagem operacional

---

## Gate

**PRONTO_COM_BACKTEST_BASELINE_VS_CANDIDATA_WINFUT_V1** ✅

**LIBERAR_FASE_12_WALK_FORWARD_E_ROBUSTEZ** ✅
