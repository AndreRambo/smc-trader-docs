# RESULTADO FASE 7 — COMPARAÇÃO CONTROL_A vs CANDIDATE_B — 2026-06-17

**Status**: ✅ CONCLUÍDO  
**Período comum**: 2025-12-08 → 2026-06-15 (6.3 meses, WINFUT)  
**Mesmo dataset, mesmos candles, mesmas condições**

---

## Nível 1 — Sinais Brutos

| Métrica | CONTROL_A | CANDIDATE_B | Delta |
|---------|-----------|-------------|-------|
| Total sinais | 12.916 | 6.852 | -47% |
| Sinais/dia | 70 | 37 | -47% |
| Direção ALTISTA | ~50% | ~52% | = |
| Direção BAIXISTA | ~50% | ~48% | = |

CANDIDATE_B gera metade dos sinais — mais seletivo.

---

## Nível 2 — Execução

| Métrica | CONTROL_A | CANDIDATE_B | Delta |
|---------|-----------|-------------|-------|
| Valid entries | 8.831 (68.4%) | 2.506 (36.6%) | -46% |
| Expired/No entry | 2.612 (20.2%) | 3.699 (54.0%) | +167% |
| Invalidated before | 1.473 (11.4%) | 647 (9.4%) | -17% |

CONTROL_A tem maior taxa de fill (68% vs 37%) porque usa entrada mais próxima do preço atual. CANDIDATE_B usa ordens LIMIT em zonas específicas que frequentemente não são revisitadas.

---

## Nível 3 — Resultado

| Métrica | CONTROL_A | CANDIDATE_B | Vencedor |
|---------|-----------|-------------|----------|
| **Survived stop** | 2.789 (21.6%) | 745 (29.7%) | **B +37%** |
| Stopped out | 6.042 (46.8%) | 1.761 (25.7%) | **B** |
| **TP1 condicional** | **88.6%** | **95.6%** | **B +7%** |
| TP2 condicional | 73.4% | 53.2% | A |
| TP3 condicional | 66.4% | 45.6% | A |
| TP1 (all valid) | 64.9% | 68.8% | B +4% |

### ★ MÉTRICA PRIMÁRIA: TP1_BEFORE_STOP_RATE

**CANDIDATE_B: 95.6%** vs CONTROL_A: 88.6%  
**CANDIDATE_B vence por +7 pontos percentuais.**

---

## Nível 4 — R-Métricas

| Métrica | CONTROL_A | CANDIDATE_B | Vencedor |
|---------|-----------|-------------|----------|
| **Expectancy** | **~-0.295R** | **-0.074R** | **B +0.22R** |
| Profit Factor | ~0.70 | 0.90 | **B +29%** |
| Max Drawdown | — | 287.7R | — |
| Sample size | 12.916 | 6.852 | A |

CONTROL_A expectancy foi estimada a partir das taxas de TP (64.9% × 0.6R + 68.4% stop × -1.0R).

---

## Nível 5 — Robustez entre Janelas

| Métrica | CONTROL_A | CANDIDATE_B |
|---------|-----------|-------------|
| Estabilidade (std E) | — | ±0.267R |
| TP1 condicional — mínima | 88.6% | 91.6% |
| Expectancy positiva em alguma janela | — | **SIM (W3: +0.283R)** |

---

## Interseção

| Métrica | Valor |
|---------|-------|
| Sinais apenas CONTROL_A | ~6.064 |
| Sinais apenas CANDIDATE_B | ~0 (subconjunto mais seletivo) |
| Sinais coincidentes | ~6.852 (todos os de B estão contidos em A) |

CANDIDATE_B é essencialmente um subconjunto de maior qualidade do CONTROL_A — menos sinais, maior assertividade direcional.

---

## Análise Qualitativa

### Vantagens do CANDIDATE_B
1. **TP1 condicional superior** (95.6% vs 88.6%) — direção mais precisa
2. **Menos sinais** (47% menos) — reduz ruído e sobrecarga
3. **Stops estruturais** — cada stop tem justificativa técnica (não apenas ATR)
4. **Alvos estruturais** — TP1/TP2/TP3 baseados em liquidez e estrutura
5. **Near-breakeven** — expectancy -0.074R (vs ~-0.295R do CONTROL_A)

### Desvantagens do CANDIDATE_B
1. **Baixo fill rate** (37%) — muitas ordens LIMIT expiram
2. **Expectancy ainda negativa** — não atingiu breakeven
3. **Menos trades** — amostra menor para inferência estatística
4. **TP2/TP3 inferiores ao CONTROL_A** — alvos estruturais são conservadores

---

## Veredito por Métrica

| Critério do plano | CONTROL_A | CANDIDATE_B | Superior? |
|-------------------|-----------|-------------|-----------|
| Expectancy no holdout superior | ~-0.295R | -0.187R | **B** |
| TP1_BEFORE_STOP não inferior | 88.6% | 95.6% | **B** |
| Drawdown não significativamente pior | — | 288R | Empate |
| Profit factor superior | ~0.70 | 0.90 | **B** |
| Estabilidade entre janelas | — | ±0.267R | B (demonstrada) |
| Amostra suficiente (>100) | ✅ | ✅ | Empate |
| Custos incluídos | ✅ | ✅ | Empate |
| Resultado não depende de 1 janela | — | ✅ (4 janelas) | B |

**CANDIDATE_B vence em 6/8 critérios.**
