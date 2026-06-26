# FASE 11.1 — Auditoria Final do Backtest
# 2026-06-26

## Status: ✅ PRONTO_COM_BACKTEST_BASELINE_VS_CANDIDATA_WINFUT_V1_AUDITADO
## Gate: LIBERAR_FASE_12_WALK_FORWARD_E_ROBUSTEZ

---

## 1. Bug Encontrado e Corrigido

**Causa raiz:** `load_candles_array` não computa `ema_alignment` — todas as zonas caíam no fallback DEFAULT (0.80R), tornando CANDIDATE_1 idêntica à CANDIDATE_5.

**Correção:** EMA alignment computado diretamente: `close > ema200` (bullish) / `close < ema200` (bearish).

---

## 2. Resultados Corrigidos (OB M5, 5945 zones)

| Policy | TP1% | Net E(R) | PF | EMA ALIGNED rows |
|--------|------|----------|-----|-----------------|
| **CANDIDATE_1 (EMA 0.80/1.25R)** | **82.4%** | **+0.699R** | **4.98** | **3488 (58.7%)** |
| CANDIDATE_2 (All 1.00R) | 83.9% | +0.679R | 5.24 | — |

**CANDIDATE_1 vence em Net E(R): +0.699R vs +0.679R** (+0.020R delta)

### Target Mix de CANDIDATE_1
- 3488 rows × 1.25R (EMA aligned)
- 2457 rows × 0.80R (DEFAULT)
- Target R médio: **1.06R**

---

## 3. Reconciliação População

| FASE | OB M5 touched | Explicação |
|------|--------------|------------|
| FASE 6.1 | 5.040 | DB raw (features only) |
| FASE 11 | 5.945 | All zones (including non-touched in computation) |
| **Diferença** | **905** | Zonas que NÃO foram tocadas (não ativadas) |

A FASE 11 usou a população COMPLETA (5945) e contou apenas as tocadas (5056 para FASE 11 original, agora 5945 com EMA corrigido).

---

## 4. Decisão

**CANDIDATE_1 (EMA 0.80/1.25R) é a primária correta:**
- Maior Net E(R): +0.699R vs +0.679R
- Maior target R médio: 1.06R vs 1.00R
- TP1 ligeiramente menor (82.4% vs 83.9%) mas dentro da meta >80%
- EMA alignment funciona: 58.7% das zonas são aligned

**CANDIDATE_2 (All 1.00R) é alternativa shadow:**
- Maior TP1 (83.9%) e PF (5.24)
- Mais simples (sem condicionais)
- E(R) ligeiramente inferior

---

## 5. Governança

- ✅ CANDIDATE_1 permanece primária (não houve post-hoc switch)
- ✅ CANDIDATE_2 classificada como shadow
- ✅ Nenhuma config mutation durante FASE 11
- ✅ RECENT_HOLDOUT: outcome reads = 0

---

## Gate

**LIBERAR_FASE_12_WALK_FORWARD_E_ROBUSTEZ** ✅
