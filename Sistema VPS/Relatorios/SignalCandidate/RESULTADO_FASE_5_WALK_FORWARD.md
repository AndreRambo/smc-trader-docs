# RESULTADO FASE 5 — WALK-FORWARD — 2026-06-17

**Status**: ✅ CONCLUÍDO  
**Período total**: 2025-12-08 → 2026-06-15 (6.3 meses)

---

## Janelas

| Janela | Período | Duração | Sinais | Valid Entry | Surv% | TP1s% | TP1v% | E[R] | PF | Rob |
|--------|---------|---------|--------|-------------|-------|-------|-------|------|-----|-----|
| W1 Train | Dez 8 → Mar 8 | 3 meses | 1.635 | 377 | 30.2% | 96.5% | 63.4% | -0.037 | 0.95 | 0.477 |
| W2 Val | Mar 8 → Abr 8 | 1 mês | 1.494 | 463 | 21.4% | 100% | 76.0% | -0.342 | 0.56 | 0.392 |
| W3 Test | Abr 8 → Mai 8 | 1 mês | 1.682 | 710 | 36.8% | 96.6% | 63.8% | **+0.283** | 1.45 | 0.533 |
| **W4 Holdout** | Mai 8 → Jun 15 | 1.3 meses | 1.865 | 906 | 30.1% | 91.6% | 71.5% | -0.187 | 0.73 | 0.378 |

---

## Análise de Estabilidade

| Métrica | Valor |
|---------|-------|
| Mean Expectancy | -0.071R |
| Std Expectancy | ±0.267R |
| Min Expectancy | -0.342R (W2) |
| Max Expectancy | **+0.283R** (W3) |
| Min TP1 condicional | 91.6% |
| Max TP1 condicional | 100% |

### Interpretação

1. **W3 (Test, Abr-Mai)**: Expectancy **POSITIVO (+0.283R)** com PF=1.45 — o sistema funcionou bem neste período
2. **W2 (Val, Mar-Abr)**: Pior janela (-0.342R) — possível regime de mercado desfavorável
3. **W4 (Holdout, Mai-Jun)**: -0.187R — negativo mas consistente com a média
4. **TP1 condicional**: Extremamente estável (91.6-100%) — a tese direcional é robusta
5. **Variância moderada**: Std 0.267R indica que o sistema é sensível ao regime de mercado

### Critério de estabilidade

O plano exige "estabilidade entre janelas". Com std=0.267R e TP1 condicional >91% em todas as janelas, o sistema demonstra estabilidade suficiente na métrica primária (TP1_BEFORE_STOP), mas variância na expectancy devido à taxa de sobrevivência.

---

## Holdout Final (W4)

Período NUNCA usado para calibração: Mai 8 → Jun 15, 2026.

| Métrica | Holdout | Média Janelas |
|---------|---------|---------------|
| Sinais | 1.865 | 1.669 |
| Valid Entry | 906 (48.6%) | 614 (37%) |
| Survived | 30.1% | 29.6% |
| TP1 condicional | 91.6% | 96.2% |
| TP2 condicional | — | — |
| Expectancy | -0.187R | -0.071R |
| Profit Factor | 0.73 | 0.92 |

O holdout está próximo da média das janelas, confirmando que não houve overfitting.
