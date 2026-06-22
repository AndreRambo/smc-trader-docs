# CHAMPION CONFIRMATION — TRIAL_0028 — 2026-06-21 16:22 UTC+2

**FASE 7** — Backtest de confirmacao (fold-by-fold) + DB persistence

**Run ID (DB):** `7`
**Execucao:** 499 segundos
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
| Sinais totais | 0 |
| Entradas validas | 0 |
| Profit Factor | 0.00 |
| Expectancy R | 0.000R |
| TP1 antes do Stop | 0.0% |
| Max Drawdown | 0.0R |
| Win Rate | 0.0% |
| PnL Total | 0.0R |

## Por Fold

| Fold | Periodo | PF | E(R) | TP1% | Trades | DD |
|------|---------|-----|------|------|--------|-----|
| W00 | 2023-02-06→2023-04-05 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W01 | 2023-07-02→2023-08-29 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W02 | 2023-11-25→2024-01-22 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W03 | 2024-04-19→2024-06-16 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W04 | 2024-09-12→2024-11-09 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W05 | 2025-02-05→2025-04-04 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W06 | 2025-07-01→2025-08-28 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |
| W07 | 2025-11-24→2026-01-21 | 0.00 | 0.000 | 0.0% | 0 | 0.0R |

## Desfecho por Trade

| Outcome | Count | % |
|---------|-------|---|
| TP1 | 0 | 0.0% |
| TP2 | 0 | 0.0% |
| TP3 | 0 | 0.0% |
| STOP | 0 | 0.0% |
| EXPIRED | 0 | 0.0% |

## Persistencia

- **trade_backtest_runs:** run_id=7
- **trade_backtest_results:** 0 rows

```sql
SELECT * FROM trade_backtest_runs WHERE id = 7;
SELECT COUNT(*) FROM trade_backtest_results WHERE run_id = 7;
SELECT outcome_final, COUNT(*) FROM trade_backtest_results WHERE run_id = 7 GROUP BY 1;
```

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```