# M2 EXECUTION — TRIAL_0028 — 2026-06-23 16:56 UTC+2

**FASE 9** — Backtest com execucao M2 (estruturas M5)

**Run ID (DB):** `9`
**Execucao:** 2770 segundos
**Folds:** 8 janelas de ~2 meses cada

## Parametros

```
stop_buffer_atr:      0.25
max_stop_atr:         2.5
expiry_candles (M2):  22 (~44 min)
cooldown_bars (M2):   20 (~40 min)
intrabar_policy:      STOP_FIRST_CONSERVATIVE
execucao_tf:          M2
estruturas_smc:       M5/M15/H4/D1
```

## Resultados Consolidados

| Metrica | Valor |
|---------|-------|
| Sinais totais | 1818 |
| Entradas validas | 181 |
| Profit Factor | 1.36 |
| Expectancy R | 0.119R |
| TP1 antes do Stop | 100.0% |
| Max Drawdown | 14.5R |
| Win Rate | 100.0% |
| PnL Total | 209.4R |

## Por Fold

| Fold | Periodo | PF | E(R) | TP1% | Trades | DD |
|------|---------|-----|------|------|--------|-----|
| W00 | 2023-02-06->2023-04-05 | 1.32 | 0.101 | 100.0% | 22 | 7.77R |
| W01 | 2023-07-02->2023-08-29 | 2.48 | 0.388 | 100.0% | 36 | 5.32R |
| W02 | 2023-11-25->2024-01-22 | 1.10 | 0.039 | 100.0% | 19 | 12.08R |
| W03 | 2024-04-19->2024-06-16 | 1.11 | 0.041 | 100.0% | 24 | 12.77R |
| W04 | 2024-09-12->2024-11-09 | 1.05 | 0.019 | 100.0% | 21 | 14.47R |
| W05 | 2025-02-05->2025-04-04 | 1.22 | 0.080 | 100.0% | 15 | 8.33R |
| W06 | 2025-07-01->2025-08-28 | 2.04 | 0.275 | 100.0% | 27 | 4.63R |
| W07 | 2025-11-24->2026-01-21 | 1.22 | 0.073 | 100.0% | 17 | 9.01R |

## Desfecho por Trade

| Outcome | Count | % |
|---------|-------|---|
| TP1 | 181 | 100.0% |
| TP2 | 86 | 47.5% |
| TP3 | 77 | 42.5% |
| STOP | 0 | 0.0% |
| EXPIRED | 0 | 0.0% |

## Comparacao M5 vs M2

| Metrica | Run #8 (M5) | Run #9 (M2) | Delta |
|---------|-------------|-------------|-------|
| Trades | 110 | 181 | +71 |
| PnL | +118.8R | +209.4R | +90.6R |
| TP3 rate | 41.8% | 42.5% | — |
| Stops | 0 | 0 | — |

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```