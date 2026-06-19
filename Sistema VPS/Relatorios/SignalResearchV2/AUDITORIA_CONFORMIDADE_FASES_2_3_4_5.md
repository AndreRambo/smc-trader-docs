# AUDITORIA DE CONFORMIDADE — FASES 2-5 — 2026-06-18

**Status**: CONCLUÍDA — BLOQUEIOS IDENTIFICADOS

---

## 1. Matriz de Conformidade

| # | Requisito | Relatório Afirma | Código Real | Status |
|---|-----------|-----------------|-------------|--------|
| 1 | CONTROL_A executado no Unified Engine | "CONTROL_A via EMA planner" | EMA planner, não scanner real | ⚠️ ADAPTER_PARCIAL |
| 2 | Expectancy simulada trade a trade | Estimada (~-0.295R) | Estimada a partir de taxas agregadas | ❌ ESTIMADA |
| 3 | Portfolio tracking | "isolated mode for now" | `reset_portfolio()` por trade | ⚠️ ISOLADO |
| 4 | Signal matching | Estimado | Sem implementação real | ❌ ESTIMADO |
| 5 | Bootstrap temporal | "Bootstrap formal requer mais dados" | `bootstrap_metrics.py` implementado | ✅ CORRIGIDO |
| 6 | Dataset 12+ meses | 6.3 meses | MT5 não tem mais dados históricos | ❌ BLOQUEADO |
| 7 | Rollover validado | UNRESOLVED | WIN$N+WINM26 sem calendário | ❌ BLOQUEADO |
| 8 | Candidate C search space | Congelado | `CandidateCConfigV1` definido | ✅ |
| 9 | Anti-lookahead | Validado | ref_index + candle time bounds | ✅ |
| 10 | Determinismo | Verificado | Duas execuções idênticas | ✅ |
| 11 | Custos aplicados | Sim | CostModelV1 | ✅ |
| 12 | Parciais | 50/25/25 | ExecutionEngine com partials | ✅ |

---

## 2. GATES — Status

### GATE A — CONTROL_A Real Adapter

**Status**: ⚠️ ADAPTER_PARCIAL

O Opportunity Scanner real depende do pipeline live (dispatcher → SMC V2 → Study Gateway → Risk → Scanner). Para criar um adapter determinístico:
1. É necessário fazer replay dos snapshots SMC V2 persistidos
2. O scanner original usa `evaluate_opportunity()` que depende do `OperationalPlanV2` do Risk Management
3. Sem o pipeline rodando, não há como gerar sinais do CONTROL_A real em modo replay

**Mitigação**: O EMA planner é uma aproximação razoável para o objetivo exploratório atual. A divergência principal está na geometria de entrada/stop (EMA planner usa 2.5×ATR stop fixo vs scanner real usa zonas SMC).

**Decisão**: ADAPTER_PARCIAL — não bloqueia estudos exploratórios, mas impede declaração definitiva.

### GATE B — Portfolio Executável

**Status**: ⚠️ ISOLADO

O `UnifiedExecutionEngine` atual chama `reset_portfolio()` para cada trade, efetivamente executando em modo ISOLATED_SIGNAL_MODE. O PORTFOLIO_EXECUTABLE_MODE requer tracking cronológico de posições.

**Mitigação**: Para WINFUT com `max_open=1`, o impacto é limitado — a maioria dos sinais não coincide temporalmente. Mas para validação rigorosa, o portfolio mode é necessário.

**Decisão**: ISOLADO — implementar PortfolioTracker para FASE 6.

### GATE C — Signal Matching

**Status**: ❌ NÃO IMPLEMENTADO

O matching atual é estimado ("todos os sinais de B estão contidos em A"). Precisa de implementação determinística com tolerâncias versionadas.

### GATE D — Bootstrap Temporal

**Status**: ✅ CORRIGIDO

`bootstrap_metrics.py` implementado com block bootstrap por trading day, 5000 iterações, seed fixo, LCB/UCB 95%.

### GATE E — Dataset 12+ meses

**Status**: ❌ BLOQUEADO

MT5 B3 terminal não possui dados intraday (M1/M2/M5/M15) anteriores a Dezembro 2025. O contrato WINM26 iniciou em Dez/2025. O WIN$N contínuo tem apenas ~2 meses de M5. 

**Sem solução técnica com a infraestrutura atual.** Opções:
1. Adquirir dados históricos de fonte externa (B3 data vendor)
2. Aguardar mais meses de coleta (Dez/2026 = 12 meses)
3. Prosseguir como estudo exploratório com ressalva

### GATE F — Rollover

**Status**: ❌ BLOQUEADO

Contratos históricos não disponíveis no MT5. Sem calendário de vencimentos. WIN$N contínuo não tem dados intraday suficientes.

---

## 3. Decisão sobre FASE 6

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  CORRECTIVE_GATE_STATUS: COMPLETED_WITH_BLOCKERS        │
│                                                          │
│  GATES APROVADOS:                                        │
│    ✅ Bootstrap temporal                                 │
│    ✅ Anti-lookahead                                     │
│    ✅ Determinismo                                       │
│    ✅ Custos                                             │
│    ✅ Candidate C search space                           │
│                                                          │
│  GATES BLOQUEADOS:                                       │
│    ❌ Dataset 12+ meses (6.3 disponíveis)                │
│    ❌ Rollover (sem contratos históricos)                │
│    ⚠️ CONTROL_A real adapter (EMA planner parcial)       │
│    ⚠️ Portfolio tracking (modo isolado)                  │
│    ⚠️ Signal matching (estimado)                         │
│                                                          │
│  PHASE_6_STATUS: BLOCKED                                │
│  MOTIVO: Dataset intraday insuficiente (6.3 < 12 meses)  │
│          Rollover não resolvido                          │
│                                                          │
│  PROXIMO_PASSO: Aguardar mais dados ou fonte externa     │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 4. Correções Implementadas

| Arquivo | Descrição |
|---------|-----------|
| `bootstrap_metrics.py` | Block bootstrap temporal, PF LCB/UCB 95%, drawdown P95 |
| `AUDITORIA_CONFORMIDADE_FASES_2_3_4_5.md` | Este relatório |

---

## 5. Recomendação

**Não executar FASE 6 (Nested Walk-Forward) neste momento.**

Razões:
1. 6.3 meses são insuficientes para nested walk-forward (mínimo 12)
2. Rollover não resolvido introduz viés de preço entre contratos
3. Resultados seriam EXPLORATORY_NOT_DEFINITIVE — mesmo status atual

**Ação recomendada**: Manter CANDIDATE_B_V3 como baseline, Candidate C como desenhado, e aguardar expansão do dataset para 12+ meses antes de otimização formal.
