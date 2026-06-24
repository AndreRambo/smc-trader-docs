# M2 EXECUTION — TRIAL_0028 — 2026-06-24 18:11 UTC+2

**FASE 9** — Backtest com execucao M2 (estruturas M5)

**Run ID (DB):** `13`
**Execucao:** 5362 segundos
**Folds:** 8 janelas de ~2 meses cada

## Ensemble (3 configs com prioridade)

| Prio | Config | PF | T/dia | Breakeven | Session |
|------|--------|-----|-------|-----------|---------|
| 1 | TRIAL_0051 | — | — | True | True |
| 2 | TRIAL_0034 | — | — | True | True |
| 3 | TRIAL_0168 | — | — | True | False |
| 4 | TRIAL_0191 | — | — | True | True |

Expiry M2: 15 (~30 min) | Cooldown M2: 7
Prioridade: TRIAL_0051 > TRIAL_0034 > TRIAL_0168 (1 sinal/candle)

## Resultados Consolidados

| Metrica | Valor |
|---------|-------|
| Sinais totais | 3463 |
| Entradas validas | 196 |
| Profit Factor | 1.99 |
| Expectancy R | 0.323R |
| TP1 antes do Stop | 100.0% |
| Max Drawdown | 12.4R |
| Win Rate | 100.0% |
| PnL Total | 230.0R |

## Por Fold

| Fold | Periodo | PF | E(R) | TP1% | Trades | DD |
|------|---------|-----|------|------|--------|-----|
| W00 | 2023-02-06->2023-04-05 | 2.33 | 0.371 | 100.0% | 20 | 6.47R |
| W01 | 2023-07-02->2023-08-29 | 2.53 | 0.438 | 100.0% | 36 | 8.0R |
| W02 | 2023-11-25->2024-01-22 | 1.78 | 0.269 | 100.0% | 25 | 12.0R |
| W03 | 2024-04-19->2024-06-16 | 1.44 | 0.179 | 100.0% | 21 | 9.37R |
| W04 | 2024-09-12->2024-11-09 | 1.90 | 0.302 | 100.0% | 28 | 6.55R |
| W05 | 2025-02-05->2025-04-04 | 2.08 | 0.351 | 100.0% | 15 | 8.74R |
| W06 | 2025-07-01->2025-08-28 | 2.35 | 0.401 | 100.0% | 29 | 11.66R |
| W07 | 2025-11-24->2026-01-21 | 1.96 | 0.319 | 100.0% | 22 | 12.44R |

## Desfecho por Trade

| Outcome | Count | % |
|---------|-------|---|
| TP1 | 196 | 100.0% |
| TP2 | 75 | 38.3% |
| TP3 | 70 | 35.7% |
| STOP | 0 | 0.0% |
| EXPIRED | 0 | 0.0% |

## Comparacao M5 vs M2

| Metrica | Run #8 (M5) | Run #9 (M2) | Delta |
|---------|-------------|-------------|-------|
| Trades | 110 | 196 | +86 |
| PnL | +118.8R | +230.0R | +111.2R |
| TP3 rate | 41.8% | 35.7% | — |
| Stops | 0 | 0 | — |

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```