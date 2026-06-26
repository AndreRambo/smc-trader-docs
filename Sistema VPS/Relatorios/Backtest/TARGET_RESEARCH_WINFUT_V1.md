# Relatório Completo — FASE 8 Pesquisa TP1 por Contexto
# 2026-06-26

## Grade Fixa em R — OB M5

| Target R | TP1% | CI95 | Net E(R) | PF | Classificacao |
|----------|------|------|----------|-----|---------------|
| 0.70R | 86.3% | [85.3-87.2%] | +0.467R | 4.41 | HIGH_TP1 |
| 0.80R | 85.5% | [84.5-86.4%] | +0.539R | 4.72 | BALANCED |
| 0.90R | 84.5% | [83.5-85.5%] | +0.606R | 4.92 | BALANCED |
| **1.00R** | **83.9%** | [82.9-84.9%] | **+0.679R** | **5.24** | **DEFAULT** |
| 1.10R | 82.5% | [81.4-83.5%] | +0.733R | 5.20 | BALANCED |
| 1.25R | 80.7% | [79.5-81.7%] | +0.816R | 5.24 | HIGH_EXPECTANCY |
| 1.50R | 76.7% | [75.5-77.8%] | +0.918R | 4.95 | LOW_TP1 |
| 2.00R | 68.3% | [67.1-69.6%] | +1.052R | 4.34 | REJECTED (<80%) |

---

## Contexto EMA (1.00R)

| Context | TP1% | Net E(R) | PF | Delta TP1 |
|---------|------|----------|-----|-----------|
| **EMA aligned** | **88.3%** | +0.767R | **7.58** | **+11.2pp** |
| EMA not aligned | 77.1% | +0.543R | 3.38 | baseline |

**EMA aligned transforma 1.00R de 83.9% para 88.3% TP1.**

---

## Politicas Contextuais (Top 5)

| # | Policy | TP1% | Avg R | Net E(R) | PF |
|---|--------|------|-------|----------|-----|
| 1 | **EMA cond 0.80R/1.25R** | **82.5%** | 1.07R | **+0.720R** | 5.13 |
| 2 | All 1.00R | 83.9% | 1.00R | +0.679R | 5.24 |
| 3 | BUY 1.25R / SELL 0.80R | 82.8% | 1.03R | +0.671R | 4.90 |
| 4 | EMA cond 0.70R/1.00R | 84.9% | 0.88R | +0.604R | 4.99 |
| 5 | All 0.80R | 85.5% | 0.80R | +0.539R | 4.72 |

---

## Candidatos para FASE 9

| # | Policy | TP1% | Target R | E(R) | PF | Papel |
|---|--------|------|----------|------|-----|-------|
| 1 | EMA cond 0.80R/1.25R | 82.5% | 1.07R avg | +0.720R | 5.13 | **DEFAULT_CONTEXTUAL** |
| 2 | All 1.00R | 83.9% | 1.00R | +0.679R | 5.24 | **CONSERVATIVE** |
| 3 | BUY 1.25R / SELL 0.80R | 82.8% | 1.03R | +0.671R | 4.90 | DIRECTIONAL |
| 4 | EMA cond 0.70R/1.00R | 84.9% | 0.88R | +0.604R | 4.99 | HIGH_TP1 |
| 5 | All 0.80R | 85.5% | 0.80R | +0.539R | 4.72 | CONSERVATIVE_R |

---

## Gate

**PRONTO_COM_PESQUISA_TP1_POR_CONTEXTO_WINFUT_V1** ✅

**LIBERAR_FASE_9_PROBABILIDADE_E_SCORE** ✅
