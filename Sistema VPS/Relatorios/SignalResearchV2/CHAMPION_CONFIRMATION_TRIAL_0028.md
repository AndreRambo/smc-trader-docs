# CHAMPION CONFIRMATION — TRIAL_0028 — 2026-06-21 00:10 UTC+2

**FASE 7** — Backtest de confirmacao (fold-by-fold) + DB persistence

**Run ID (DB):** `5`
**Execucao:** 718 segundos
**Folds:** 8 janelas de ~2 meses cada

## Parametros

```
stop_buffer_atr:      0.25
max_stop_atr:         2.5
expiry_candles_m5:    9
session_only:         False
require_htf_for_tp3:  False
breakeven_after_tp1:  False
cooldown_bars_m5:     8
intrabar_policy:      STOP_FIRST_CONSERVATIVE
```

## Resultados Consolidados

| Metrica | Valor |
|---------|-------|
| Sinais totais | 707 |
| Entradas validas | 65 |
| Profit Factor | 4.20 |
| Expectancy R | 0.696R |
| TP1 antes do Stop | 96.9% |
| Max Drawdown | 7.0R |
| Win Rate | 96.9% |
| PnL Total | 90.1R |

## Por Fold

| Fold | Periodo | PF | E(R) | TP1% | Trades | DD |
|------|---------|-----|------|------|--------|-----|
| W00 | 2023-02-06→2023-04-05 | 1.59 | 0.163 | 90.9% | 22 | 6.96R |
| W01 | 2023-07-02→2023-08-29 | 4.21 | 0.714 | 100.0% | 4 | 3.0R |
| W02 | 2023-11-25→2024-01-22 | 3.40 | 0.663 | 100.0% | 3 | 3.0R |
| W03 | 2024-04-19→2024-06-16 | 7.82 | 0.909 | 100.0% | 12 | 2.0R |
| W04 | 2024-09-12→2024-11-09 | 2.34 | 0.447 | 100.0% | 2 | 2.88R |
| W05 | 2025-02-05→2025-04-04 | 3.99 | 0.630 | 100.0% | 1 | 2.0R |
| W06 | 2025-07-01→2025-08-28 | 4.71 | 0.823 | 100.0% | 10 | 2.0R |
| W07 | 2025-11-24→2026-01-21 | 999.00 | 1.942 | 100.0% | 11 | 0.0R |

## Desfecho por Trade

| Outcome | Count | % |
|---------|-------|---|
| TP1 | 63 | 96.9% |
| TP2 | 45 | 69.2% |
| TP3 | 42 | 64.6% |
| STOP | 0 | 0.0% |
| EXPIRED | 0 | 0.0% |

## Persistencia

- **trade_backtest_runs:** run_id=5
- **trade_backtest_results:** 65 rows

```sql
SELECT * FROM trade_backtest_runs WHERE id = 5;
SELECT COUNT(*) FROM trade_backtest_results WHERE run_id = 5;
SELECT outcome_final, COUNT(*) FROM trade_backtest_results WHERE run_id = 5 GROUP BY 1;
```

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```