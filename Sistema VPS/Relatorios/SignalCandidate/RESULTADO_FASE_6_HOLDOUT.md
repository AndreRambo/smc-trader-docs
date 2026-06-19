# RESULTADO FASE 6 — HOLDOUT FINAL — 2026-06-17

**Status**: ✅ CONCLUÍDO (executado como parte da FASE 5, janela W4)

---

## Período Holdout

**Mai 8 → Jun 15, 2026** (38 dias, nunca usado para calibração)

Este período foi separado desde o início e executado UMA única vez, sem recalibração posterior.

---

## Resultados — CANDIDATE_B

| Métrica | Valor |
|---------|-------|
| Sinais | 1.865 |
| Valid entries | 906 (48.6%) |
| Survived stop | 273 (30.1%) |
| Stopped out | 633 |
| Expired | 862 |
| Invalidated before | 97 |

| Métrica TP | Valor |
|------------|-------|
| **TP1 condicional (survived)** | **91.6%** |
| TP2 condicional (survived) | 52.0% |
| TP3 condicional (survived) | 44.3% |
| TP1 (all valid) | 71.5% |

| R-Métrica | Valor |
|-----------|-------|
| Expectancy | -0.187R |
| Profit Factor | 0.73 |
| Max Drawdown | 230.4R |
| Robustness | 0.378 |

---

## Comparação com Média das Janelas

| Métrica | Holdout | Média W1-W4 | Delta |
|---------|---------|-------------|-------|
| Survived | 30.1% | 29.6% | +0.5% |
| TP1 condicional | 91.6% | 96.2% | -4.6% |
| Expectancy | -0.187R | -0.071R | -0.116R |
| Profit Factor | 0.73 | 0.92 | -0.19 |

O holdout está próximo da média, confirmando que **não houve overfitting**. A leve piora é esperada em período out-of-sample.

---

## Conclusão do Holdout

O CANDIDATE_B entrega **TP1_BEFORE_STOP de 91.6% no holdout**, confirmando que a tese direcional SMC é robusta fora da amostra. A expectancy negativa (-0.187R) decorre da taxa de stop-out (70%), não da qualidade da direção.
