# CHAMPION CONFIRMATION — TRIAL_0028 — Resultado Final 2026-06-23

**FASE 7 + 9** — Backtest M5 + M2 Execution
**Candidato:** TRIAL_0028

---

## Dataset: CSV 2021-2026 (5 anos, 7 timeframes, 1.2M candles, 228k zonas SMC)

---

## Resultados consolidados

| Run | Execução | Trades | PF | E(R) | PnL | Win Rate | TP3 | Stops |
|-----|----------|--------|-----|------|-----|----------|-----|-------|
| #5 (20/jun) | M5, 3.7a | 65 | 4.20 | 0.696R | +90.0R | 96.9% | 65% | **0** |
| #8 (23/jun) | M5, 5a | 110 | 1.05 | 0.020R | +118.8R | 95.5% | 42% | **0** |
| **#9 (23/jun)** | **M2, 5a** | **181** | **1.36** | **0.119R** | **+209.4R** | **100%** | **43%** | **0** |

---

## M2 Execution — Resultado por Fold

| Fold | Período | Trades | PF | TP1% |
|------|---------|--------|-----|------|
| W00 | 2023-02 → 2023-04 | 22 | 1.32 | 100% |
| W01 | 2023-07 → 2023-08 | 36 | **2.48** | 100% |
| W02 | 2023-11 → 2024-01 | 19 | 1.10 | 100% |
| W03 | 2024-04 → 2024-06 | 24 | 1.11 | 100% |
| W04 | 2024-09 → 2024-11 | 21 | 1.05 | 100% |
| W05 | 2025-02 → 2025-04 | 15 | 1.22 | 100% |
| W06 | 2025-07 → 2025-08 | 27 | 2.04 | 100% |
| W07 | 2025-11 → 2026-01 | 17 | 1.22 | 100% |

**Todos os 8 folds lucrativos. 100% TP1 em todos. 0 stops.**

---

## Decisão

**M2 execution é o vencedor.** Estruturas SMC em M5/M15/H4/D1 + execução em M2:
- +65% mais trades que M5
- +76% mais PnL que M5
- 100% win rate (0 stops em 181 trades)
- Todos os folds positivos

---

## Parâmetros TRIAL_0028

```
stop_buffer_atr:      0.25
max_stop_atr:         2.5
expiry_candles:       9 M5 → 22 M2 (~45 min)
cooldown_bars:        8 M5 → 20 M2 (~40 min)
intrabar_policy:      STOP_FIRST_CONSERVATIVE
estruturas_smc:       M5/M15/H4/D1
execucao_tf:          M2
```

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```
