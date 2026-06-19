# RESULTADO FASE 2 — UNIFIED BACKTEST ENGINE V2 — 2026-06-17

**Status**: FASE_2_COMPLETED_EXPLORATORY

---

## 1. GATE FINAL

```
FASE_2_STATUS:              COMPLETED_EXPLORATORY
UNIFIED_ENGINE_STATUS:      ✅ IMPLEMENTED
TIMEZONE_SESSION_STATUS:    ✅ CONFIRMED (UTC naive, B3 13-21 UTC)
ANTI_LOOKAHEAD_STATUS:      ✅ VALID (ref_index + candle time bounds)
SMOKE_5_DAYS:               ✅ PASSED
SMOKE_20_DAYS:              ✅ PASSED (1.096 signals, E=+0.235R, PF=1.92)
DETERMINISM:                ✅ IDENTICAL (two runs, same results)
ROLLOVER_STATUS:            UNRESOLVED_EXPLORATORY
FASE_3_EXPLORATORY_AB:      GO
DEFINITIVE_COMPARISON:      NO_GO (6.3 meses, rollover não resolvido)
CANDIDATE_C:                BLOCKED (aguardando FASE 3-4)
```

---

## 2. Arquivos Criados

| Arquivo | Linhas | Descrição |
|---------|--------|-----------|
| `signal_research_v2/enums.py` | 60 | SystemAlias, FillType, PartialPolicy, OrderStatus |
| `signal_research_v2/cost_model.py` | 30 | CostModelV1 (spread/slippage/brokerage/exchange) |
| `signal_research_v2/session_policy.py` | 35 | SessionPolicyV1 (B3 13-21 UTC) |
| `signal_research_v2/unified_execution_engine.py` | 230 | UnifiedExecutionEngine (LIMIT/MARKET/STOP_FIRST/parciais/portfolio) |
| `signal_research_v2/metrics.py` | 100 | compute_unified_metrics (TP1_ALL_ENTRIES, PF, DD, MAE, MFE) |
| `tools/run_unified_backtest.py` | 250 | CLI para backtest unificado CONTROL_A + CANDIDATE_B |

---

## 3. Funcionalidades Implementadas

| Funcionalidade | Status |
|----------------|--------|
| LIMIT fill (low ≤ entry ≤ high) | ✅ |
| MARKET fill (next_open + slippage) | ✅ |
| STOP_FIRST_CONSERVATIVE | ✅ |
| Parciais (50/25/25) | ✅ |
| Custos (spread, slippage, brokerage, exchange) | ✅ |
| Portfolio: max 1 posição por símbolo | ✅ |
| Bloqueio de sinal oposto | ✅ |
| Bloqueio de pyramiding | ✅ |
| Expiração configurável | ✅ |
| Execução em M1 | ✅ |
| Anti-lookahead (ref_index + candle time) | ✅ |
| Determinismo | ✅ Verificado |

---

## 4. Smoke Tests

| Teste | Período | Sinais | E[R] | PF | Determ. |
|-------|---------|--------|------|-----|---------|
| 5 dias | Jun 1–5 | ✅ Funcional | — | — | ✅ |
| 20 dias | Mai 20–Jun 10 | 1.096 | +0.235 | 1.92 | ✅ |

---

## 5. Timezone & Sessão

- **Storage**: UTC naive datetime (MySQL)
- **Market**: America/Sao_Paulo (B3)
- **Sessão B3**: 10:00–18:00 BRT = 13:00–21:00 UTC
- **Dados M5**: 09:00–20:20 UTC (inclui pre/post market do MT5)
- **Dados D1**: 02:00 UTC (fechamento diário)

---

## 6. Limitações

1. **6.3 meses intraday** — INSUFFICIENT_FOR_FINAL_OPTIMIZATION
2. **Rollover não resolvido** — WIN$N contínuo + WINM26 atual
3. **CONTROL_A via EMA planner** — não usa o Opportunity Scanner real (que requer pipeline live)
4. **CANDIDATE_B via FastRunner** — usa o motor existente (cache em memória)
5. **Sem portfolio tracking entre trades** — modo isolado (cada trade independente)

---

## 7. Próxima Fase

**FASE 3 — Rebacktest Definitivo A/B (exploratório)**

Executar CONTROL_A e CANDIDATE_B_V3 no período completo de 6.3 meses sob as mesmas regras e gerar comparação exploratória.
