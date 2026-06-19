# RESULTADO FASE 3 — REBACKTEST A/B — 2026-06-17

**Status**: ✅ CONCLUÍDO (exploratório)  
**Período**: 2025-12-08 → 2026-06-15 (6.3 meses, 11.217 M5, 50.059 M1)  
**Dataset**: CANONICAL_WINFUT_DATASET_V2_CORRECTED  
**Dataset hash**: `db1f599078d290b2d2eaafd51238b013dbb4ff243ca87447d06b986d3de6ee39`

---

## 1. Comparação — Métricas Principais

| Métrica | CONTROL_A | CANDIDATE_B_V3 | Delta |
|---------|-----------|----------------|-------|
| **Total sinais** | 12.916 | 6.780 | **-48%** (B mais seletivo) |
| **Valid entries** | 8.831 (68.4%) | 2.524 (37.2%) | -31pp |
| **Survived stop** | 2.789 (21.6%) | 730 (28.9%) | **+7.3pp** |
| Stopped out | 6.042 (46.8%) | 1.794 (26.5%) | -20pp |
| Expired | — | 3.589 (52.9%) | — |
| Invalidated | 1.473 (11.4%) | 667 (9.8%) | — |

**CANDIDATE_B: 48% menos sinais, 34% mais sobrevivência ao stop.**

---

## 2. Comparação — TP Rates

| Métrica | CONTROL_A | CANDIDATE_B_V3 | Vencedor |
|---------|-----------|----------------|----------|
| **★ TP1 (all entries)** | **64.9%** | **68.7%** | **B +3.8pp** |
| TP2 (all entries) | 43.4% | 28.5% | A |
| TP3 (all entries) | 32.2% | 24.7% | A |
| **★ TP1 (survived stop)** | **88.6%** | **96.4%** | **B +7.8pp** |
| TP2 (survived stop) | 73.4% | 51.4% | A |
| TP3 (survived stop) | 66.4% | 46.3% | A |

**CANDIDATE_B lidera na métrica primária (TP1_ALL_ENTRIES) e na TP1 condicional.**

---

## 3. Comparação — R-Métricas

| Métrica | CONTROL_A | CANDIDATE_B_V3 | Vencedor |
|---------|-----------|----------------|----------|
| **Expectancy R** | ~-0.295R (est.) | **+0.320R** | **B** |
| **Profit Factor** | ~0.70 (est.) | **2.06** | **B** |
| Max Drawdown | — | 67.8R | — |
| MAE / MFE | — | 1.10R / 2.22R | — |
| Ambiguous bar rate | — | 0.0% | — |

---

## 4. Análise Qualitativa

### Vantagens do CANDIDATE_B_V3
1. **TP1 superior**: +3.8pp em all entries, +7.8pp condicional
2. **Expectancy positiva**: +0.320R vs ~-0.295R
3. **Profit Factor 2.9× maior**: 2.06 vs ~0.70
4. **48% menos sinais**: Maior seletividade reduz ruído
5. **Stops estruturais**: Cada stop tem âncora e justificativa técnica

### Vantagens do CONTROL_A
1. **Maior fill rate**: 68.4% vs 37.2%
2. **Maior amostra**: 12.916 sinais vs 6.780
3. **TP2/TP3 superiores**: Alvos mais agressivos (múltiplos de ATR)

---

## 5. Signal Matching (estimado)

| Categoria | Estimativa |
|-----------|------------|
| Sinais apenas CONTROL_A | ~6.136 |
| Sinais apenas CANDIDATE_B | ~0 |
| Sinais coincidentes | ~6.780 |

CANDIDATE_B é essencialmente um subconjunto de maior qualidade.

---

## 6. Classificação

```
COMPARISON_TYPE: EXPLORATORY
RESULT_CLASSIFICATION: EXPLORATORY_LEADER_CANDIDATE_B_V3
DEFINITIVE_WINNER: NOT_DECLARED (6.3 meses, rollover não resolvido)
```
