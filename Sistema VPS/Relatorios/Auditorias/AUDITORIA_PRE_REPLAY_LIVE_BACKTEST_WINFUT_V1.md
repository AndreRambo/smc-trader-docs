# AUDITORIA PRE-REPLAY LIVE BACKTEST — WINFUT V1
# 2026-06-26

## 1. Resumo Executivo

**Gate:** `READY_FOR_PLAN_WITH_DATA_RECONSTRUCTION`
**Nível máximo possível:** `LEVEL_2_EXECUTION_COST_REALISTIC`

O projeto possui dados suficientes para um replay histórico com ExecutionModel real (costs, fill, same-bar). **Não** possui ticks/bid-ask para LEVEL_3. A aba Replay do site já existe mas não tem backend de eventos.

---

## 2. Dados Disponíveis

### Candles

| TF | Count | Período | Status |
|----|-------|---------|--------|
| 1min | 689.573 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 2min | 345.466 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 5min | 137.998 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 15min | 46.419 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 60min | 12.018 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 4h | 3.733 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |
| 1d | 1.246 | 2021-06 → 2026-06 | ✅ AVAILABLE_AND_CAUSAL |

- 0 duplicatas, 0 OHLC inválido, 0 indicadores faltando
- Timezone: timestamps em formato datetime (BRT/UTC)
- Volume: tick volume do MT5

### Ticks/Bid-Ask

| Item | Status |
|------|--------|
| Tick tables | **MISSING** |
| Bid/Ask history | **MISSING** |
| Spread history | **MISSING** |

**Nível:** LEVEL_2_EXECUTION_COST_REALISTIC (sem ticks)

---

## 3. Zonas SMC V2

| Tipo | Rows | TFs | available_at | Status |
|------|------|-----|-------------|--------|
| FVG | 103.872 | 6 | ✅ 0 nulls | AVAILABLE_AND_CAUSAL |
| OB | 23.934 | 6 | display_from (sem coluna available_at) | AVAILABLE_BUT_NEEDS_DERIVATION |
| BOS | 24.829 | 6 | event_time/broken_at | AVAILABLE_AND_CAUSAL |
| Swings | 62.713 | 6 | origin_at/confirmed_at | AVAILABLE_AND_CAUSAL |
| Liquidity | 14.768 | 6 | swept_at/event_time | AVAILABLE_AND_CAUSAL |

**OB Issue:** Não tem coluna `available_at` — usa `display_from`. Pode ser derivado causalmente.

---

## 4. Elliott/Wyckoff

| Tabela | Rows | Status |
|--------|------|--------|
| technical_engine_elliott_shadow | 6 | **AVAILABLE_BUT_NOT_CAUSAL** (snapshot único) |
| technical_engine_wyckoff_shadow | 6 | **AVAILABLE_BUT_NOT_CAUSAL** (snapshot único) |

**Bloqueio parcial:** Não há dados históricos EW por candle. Apenas snapshot final.

---

## 5. Engine de Oportunidades

| Componente | Caminho | Reutilizável? |
|------------|---------|---------------|
| Evaluator | opportunity_scanner/evaluator.py | ✅ REUSABLE_WITH_ADAPTER |
| Signal Builder | opportunity_scanner/signal_builder.py | ✅ REUSABLE_WITH_ADAPTER |
| Config | opportunity_scanner/config.py | ✅ REUSABLE |
| State Machine | plan_lifecycle.py | ✅ REUSABLE |
| Notification | notifier.py | ⚠️ Precisa adapter |

**Call path:** evaluator → filters → signal_builder → persistence → notification

**HistoricalClock necessário:** A engine usa `datetime.now()`. Para replay, precisa receber clock injetável.

---

## 6. ExecutionModel

| Aspecto | Status |
|---------|--------|
| Reutilizável | ✅ REUSABLE_WITH_ADAPTER |
| Input | SignalCandidateV1 + future candles |
| Fill | Limit order (low<=entry<=high) |
| Costs | slippage=5pts, brokerage=R$0.50, exchange=R$0.27 |
| Same-bar | STOP_FIRST_CONSERVATIVE |
| Partial exits | Configurável |
| Breakeven | Configurável |
| Market fallback | Configurável |
| Expiry | Configurável (50 M5 candles) |

**Adapter necessário:** SimpleSignal → SignalCandidateV1 + SimpleCandle → CandleRef

---

## 7. Banco de Dados

| Tabela | Rows | Uso |
|--------|------|-----|
| market_candles | 1.236.453 | Candles oficiais |
| smc_v2_*_shadow | 230.046 | Zonas persistidas |
| trade_backtest_results | 854 | Trades históricos |
| trade_backtest_runs | 11 | Runs de backtest |
| **Tabelas de replay** | **0** | **MISSING** |

**Estrutura necessária (não existe):**
- backtest_runs
- backtest_opportunities
- backtest_events
- backtest_trades
- backtest_daily_metrics

---

## 8. Splits

| Split | Período | Status |
|-------|---------|--------|
| DISCOVERY | 2021-06 → 2022-12 | ✅ AVAILABLE |
| TRAIN | 2023-01 → 2024-06 | ✅ AVAILABLE |
| VALIDATION | 2024-07 → 2025-06 | ✅ AVAILABLE |
| TEST_FINAL | 2025-08 → 2026-01 | ✅ AVAILABLE |
| RECENT_HOLDOUT | 2026-04 → 2026-06 | ✅ AVAILABLE (outcome reads = 0) |

---

## 9. Site Replay (MaximusTrader)

| Componente | Status | Notas |
|------------|--------|-------|
| ReplayPage.tsx | ✅ EXISTE | Lê candles/zonas via API |
| useReplayData.ts | ✅ EXISTE | Carrega dados do backend |
| ReplayControls.tsx | ✅ EXISTE | Play/pause/speed |
| ReplayDatePicker.tsx | ✅ EXISTE | Seleção de período |
| **Backend Replay API** | ❌ NÃO EXISTE | Não há endpoint dedicado |
| **Event Stream** | ❌ NÃO EXISTE | Sem tabela de eventos |

**O site já renderiza candles + zonas no gráfico.** Mas não tem backend de replay com eventos (sinais, fills, trades).

---

## 10. Bloqueadores

| # | Bloqueador | Severidade | Solução |
|---|------------|-----------|---------|
| 1 | Sem ticks/bid-ask | HIGH | LEVEL_2 aceitável (OHLC) |
| 2 | Elliott/Wyckoff não históricos | MEDIUM | Novo backfill ou ignorar na FASE 13 |
| 3 | Sem tabelas de replay no banco | HIGH | Criar migrations shadow |
| 4 | Backend replay API não existe | HIGH | Criar endpoint + controller |
| 5 | Event stream não existe | MEDIUM | Criar tabela de eventos |
| 6 | OB sem coluna available_at | LOW | Derivar de display_from |
| 7 | HistoricalClock não existe | MEDIUM | Criar adapter simples |

---

## 11. Gate

**READY_FOR_PLAN_WITH_DATA_RECONSTRUCTION**

Dados existentes são suficientes para replay LEVEL_2. Gaps são solucionáveis por implementação (migrations shadow, adapter, event stream). Não há bloqueio arquitetural fundamental.

---

## 12. Próximo

Com base nesta auditoria, o plano executivo deve cobrir:
1. Criar migrations shadow para backtest/replay
2. Criar adapter ExecutionModel → backtest runner
3. Criar HistoricalClock adapter
4. Criar event stream persistence
5. Integrar com aba Replay do site
6. Executar replay canônico com ExecutionModel real
