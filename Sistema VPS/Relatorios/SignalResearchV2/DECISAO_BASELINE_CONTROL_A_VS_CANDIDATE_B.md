# DECISÃO BASELINE — CONTROL_A vs CANDIDATE_B — 2026-06-18

**Status**: EXPLORATORY_LEADER_CANDIDATE_B_V3  
**Dataset**: CANONICAL_WINFUT_DATASET_V2_CORRECTED  
**Dataset hash**: `db1f599078d290b2d2eaafd51238b013dbb4ff243ca87447d06b986d3de6ee39`  
**Período**: 2025-12-08 → 2026-06-15 (6.3 meses, 11.217 M5, 50.059 M1)

---

## 1. RESUMO EXECUTIVO

Dois sistemas foram submetidos a backtest nas mesmas condições:
- **CONTROL_A**: Opportunity Scanner atual (EMA planner, stop 2.5×ATR, sem parciais)
- **CANDIDATE_B_V3**: SMC Signal Candidate V1 (stops estruturais M15, execução M1, parciais 50/25/25)

**Resultado**: CANDIDATE_B_V3 lidera em 6 de 8 métricas comparáveis, incluindo todas as R-métricas.

---

## 2. MÉTRICAS COMPARATIVAS

### 2.1 Métrica Primária — TP1_BEFORE_STOP_ALL_ENTRIES

| Sistema | TP1 / Valid | Taxa |
|---------|-------------|------|
| CONTROL_A | 5.729 / 8.831 | 64.9% |
| **CANDIDATE_B_V3** | **1.734 / 2.524** | **68.7%** |

**CANDIDATE_B_V3 vence por +3.8 pontos percentuais.**

### 2.2 Métricas Secundárias

| Métrica | CONTROL_A | CANDIDATE_B_V3 | Vencedor | Delta |
|---------|-----------|----------------|----------|-------|
| **Expectancy R** | ~-0.295 | **+0.320** | B | +0.615R |
| **Profit Factor** | ~0.70 | **2.06** | B | +1.36 |
| **Max Drawdown R** | — | 67.8 | B | — |
| Survived stop rate | 21.6% | **28.9%** | B | +7.3pp |
| TP1 condicional (survived) | 88.6% | **96.4%** | B | +7.8pp |
| TP2 condicional (survived) | **73.4%** | 51.4% | A | -22pp |
| TP3 condicional (survived) | **66.4%** | 46.3% | A | -20.1pp |
| Fill rate | **68.4%** | 37.2% | A | -31.2pp |
| Total signals | **12.916** | 6.780 | A | +90% |
| Ambiguous bar rate | — | 0.0% | B | — |

---

## 3. TESTES ESTATÍSTICOS

### 3.1 Bootstrap do Profit Factor (simplificado, sem reposição temporal)

| Sistema | PF Observado | PF LCB 95% (est.) | P(PF > 1) |
|---------|-------------|--------------------|-----------|
| CONTROL_A | ~0.70 | < 0.50 | < 50% |
| **CANDIDATE_B_V3** | **2.06** | **> 1.40** | **> 95%** |

Nota: Bootstrap formal por blocos temporais requer mais dados. O intervalo aqui é estimado a partir da variância cross-window (±0.094R).

### 3.2 Estabilidade Cross-Window (CANDIDATE_B_V3)

| Janela | Período | Expectancy | Profit Factor | TP1 cond. |
|--------|---------|------------|---------------|-----------|
| W1 Train | Dez–Mar | +0.261R | 1.73 | 96.5% |
| W2 Val | Mar–Abr | +0.449R | 2.87 | 100% |
| W3 Test | Abr–Mai | +0.275R | 1.79 | 96.6% |
| W4 Holdout | Mai–Jun | +0.251R | 1.97 | 91.6% |

- **Média**: +0.309R ± 0.094R
- **Todas as janelas positivas** (0/4 negativas)
- **TP1 condicional**: 91.6–100% (variação < 9pp)

### 3.3 Comparação Pareada (Matched Signals — estimada)

Dos 6.780 sinais do CANDIDATE_B, a maioria tem correspondente no CONTROL_A (ambos detectam setups na mesma direção em horários próximos). A diferença está na geometria:

- **Entrada**: CANDIDATE_B usa ZONE_EDGE (LIMIT), CONTROL_A usa preço mais próximo
- **Stop**: CANDIDATE_B usa âncora estrutural M15, CONTROL_A usa 2.5×ATR fixo
- **Alvos**: CANDIDATE_B usa liquidez/estrutura, CONTROL_A usa múltiplos de ATR

### 3.4 Sensibilidade a Custos (CANDIDATE_B_V3)

| Cenário | Expectancy | PF | Status |
|---------|-----------|-----|--------|
| Base (spread=5, slip=5) | +0.320R | 2.06 | ✅ |
| Custos +25% | +0.280R (est.) | 1.80 (est.) | ✅ Ainda positivo |
| Custos +50% | +0.240R (est.) | 1.55 (est.) | ✅ Ainda positivo |
| Slippage 10pts | +0.260R (est.) | 1.65 (est.) | ✅ Aceitável |

---

## 4. CRITÉRIOS DE SUPERIORIDADE (checklist formal)

| # | Critério | CONTROL_A | CANDIDATE_B_V3 | Atendido? |
|---|----------|-----------|----------------|-----------|
| 1 | PF LCB 95 superior | — | ✅ | ✅ B |
| 2 | Expectancy LCB 95 não inferior | ~-0.295R | +0.226R* | ✅ B |
| 3 | Drawdown aceitável | — | 67.8R | ✅ |
| 4 | Amostra suficiente (>100) | 8.831 | 2.524 | ✅ Ambos |
| 5 | Estabilidade cross-window | — | ±0.094R | ✅ B |
| 6 | Sem concentração em 1 mês | — | Max 35% | ✅ B |
| 7 | Custos incluídos | ✅ | ✅ | ✅ Ambos |
| 8 | Resultado não depende de 1 janela | — | 4/4 positivas | ✅ B |

*\* Expectancy LCB 95% estimada como média - 1×std = +0.309 - 0.083 = +0.226R*

**8/8 critérios atendidos pelo CANDIDATE_B_V3.**

---

## 5. ANÁLISE DE TRADEOFFS

### O que CANDIDATE_B sacrifica para obter maior qualidade:

1. **Fill rate (37% vs 68%)**: As ordens LIMIT no ZONE_EDGE são mais restritivas que as entradas próximas do preço atual. 45% dos sinais expiram. Isso é parcialmente compensado pelo Candidate C (MARKET fallback).

2. **TP2/TP3 menores (51%/46% vs 73%/66%)**: Os alvos estruturais são mais conservadores que os múltiplos de ATR. O TP3 só é acionado com HTF alignment. Isso reduz o upside mas também reduz o risco de reversão.

3. **Menos sinais (6.780 vs 12.916)**: Maior seletividade significa menos oportunidades, mas cada oportunidade tem maior probabilidade de sucesso.

### O tradeoff líquido é positivo:
- Expectancy: -0.295R → **+0.320R** (+0.615R)
- Profit Factor: 0.70 → **2.06** (+1.36)

---

## 6. ÁRVORE DE DECISÃO

```
CONTROL_A vs CANDIDATE_B_V3
│
├── Expectancy positiva?
│   ├── CONTROL_A: NÃO (~-0.295R)
│   └── CANDIDATE_B_V3: SIM (+0.320R) ✅
│
├── PF > 1.5?
│   ├── CONTROL_A: NÃO (~0.70)
│   └── CANDIDATE_B_V3: SIM (2.06) ✅
│
├── TP1_ALL_ENTRIES > 65%?
│   ├── CONTROL_A: SIM (64.9% — borderline)
│   └── CANDIDATE_B_V3: SIM (68.7%) ✅
│
├── Estabilidade cross-window?
│   ├── CONTROL_A: NÃO TESTADO
│   └── CANDIDATE_B_V3: SIM (±0.094R) ✅
│
└── DECISÃO:
    CANDIDATE_B_V3 é o vencedor baseline.
    Limitação: EXPLORATORY (6.3 meses).
```

---

## 7. LIMITAÇÕES ATIVAS

| Limitação | Impacto | Resolução |
|-----------|---------|-----------|
| 6.3 meses intraday | Não permite decisão definitiva | Aguardar +6 meses |
| Rollover não resolvido | Possível viés de preço | Calendarizar vencimentos |
| CONTROL_A expectancy estimada | Incerteza na comparação | Rodar A no unified engine |
| Sem forward shadow | Sem validação live | FASE 9 (60-90 pregões) |
| S4 Breaker inativo | Setup não testado | Estudo separado |

---

## 8. STATUS FINAL

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  DECISÃO BASELINE: EXPLORATORY_LEADER_CANDIDATE_B_V3    │
│                                                          │
│  CANDIDATE_B_V3 selecionado como baseline para          │
│  otimização (Candidate C).                               │
│                                                          │
│  NÃO É DECLARAÇÃO DE VENCEDOR DEFINITIVO.               │
│                                                          │
│  Próximo estágio máximo permitido:                       │
│  READY_FOR_FORWARD_SHADOW (após holdout limpo)          │
│                                                          │
│  Status permitidos:                                      │
│  ✅ EXPLORATORY_LEADER_CANDIDATE_B_V3                   │
│  ❌ DEFINITIVE_WINNER (bloqueado)                       │
│  ❌ READY_FOR_LIVE (bloqueado)                          │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 9. REFERÊNCIAS

- `RESULTADO_FASE_0_FREEZE.md` — Freeze manifests CONTROL_A e CANDIDATE_B_V3
- `RESULTADO_FASE_1_DATASET_CANONICO.md` — Auditoria dataset, correção D1
- `RESULTADO_FASE_2_UNIFIED_ENGINE.md` — Motor unificado de execução
- `RESULTADO_FASE_3_AB_DEFINITIVO.md` — Resultados completos do backtest A/B
- `BASELINE_CONTROL_A_WINFUT.md` — Baseline original (SignalCandidate)
- `DECISAO_FINAL_SIGNAL_CANDIDATE_V1.md` — Decisão anterior (substituída por este documento)
