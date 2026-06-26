# FASE 6 — Analise de Gatilhos WINFUT V1

**Data:** 2026-06-25 23:36 BRT
**Zonas OB M5:** 5929 | **Touched:** 5040

## A. Baselines

| Baseline | Touched | TP1% | CI95 | Net E(R) | PF | Support |
|----------|---------|------|------|----------|-----|---------|
| OB M5 (no filter)      |  5040 | 82.2% | [81.1-83.2%] | 0.7946 | 5.47 | STRONG |
| OB M5 + EMA aligned    |  3447 | 82.7% | [81.4-84.0%] | 0.7630 | 5.44 | STRONG |

## B. Gatilhos Individuais (efeito incremental vs baseline A)

| Trigger | Present TP1% | Absent TP1% | Δ TP1 (pp) | Δ E(R) | n_present | n_absent |
|---------|-------------|-------------|------------|--------|-----------|----------|
| close_aligned          | 82.5% | 62.5% | +20.0 | -0.1279 |  4952 |    88 |
| displacement           | 80.9% | 83.0% | -2.1 | +0.1348 |  1903 |  3137 |
| ema_aligned            | 82.7% | 80.9% | +1.8 | -0.1000 |  3447 |  1593 |
| rejection_any          | 84.5% | 82.0% | +2.6 | -0.1667 |   426 |  4614 |
| volume_above_rolling   | 80.6% | 86.3% | -5.8 | +0.0236 |  3649 |  1391 |

## C. EMA-Stratified Triggers

| EMA | Trigger | Touched | TP1% | Net E(R) | PF |
|-----|---------|---------|------|----------|-----|
| not_aligned  | displacement         |  1593 | 80.9% | 0.8630 | 5.52 |
| not_aligned  | rejection_any        |  1593 | 80.9% | 0.8630 | 5.52 |
| not_aligned  | volume_above_rolling |  1593 | 80.9% | 0.8630 | 5.52 |
| aligned      | displacement         |  3447 | 82.7% | 0.7630 | 5.44 |
| aligned      | rejection_any        |  3447 | 82.7% | 0.7630 | 5.44 |
| aligned      | volume_above_rolling |  3447 | 82.7% | 0.7630 | 5.44 |

## D. BUY vs SELL

| Direction | Touched | TP1% | Net E(R) | PF |
|-----------|---------|------|----------|-----|
| BUY       |  2530 | 82.3% | 0.7948 | 5.51 |
| SELL      |  2510 | 82.0% | 0.7944 | 5.43 |