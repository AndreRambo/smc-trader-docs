# CHAMPION CONFIRMATION — TRIAL_0028 — 2026-06-22

**FASE 7** — Backtest de confirmação (fold-by-fold) + DB persistence
**Candidato:** TRIAL_0028 (PF=128, 317 trades, PF_min=1.6, 0 folds TP1=0%)

---

## Histórico de execuções

| Run ID | Data | Dataset | Zonas SMC | Resultado |
|--------|------|---------|-----------|-----------|
| #5 | 20/jun | 2022-11 → 2026-06 (API) | ✅ Completas | **65 trades, PF=4.20, 0 stops** |
| #7 | 21/jun | Reordenado (API) | ❌ Vazias (truncate) | 0 trades |
| #8 | 🔜 | **2021-06 → 2026-06 (CSV)** | 🔄 Pendente | — |

---

## Dataset atual: CSV 2021-2026

| TF | Barras | Período |
|----|--------|---------|
| 1min | 689.573 | 2021-06-22 → 2026-06-19 |
| 2min | 345.466 | 2021-06-22 → 2026-06-19 |
| 5min | 137.998 | 2021-06-22 → 2026-06-19 |
| 15min | 46.419 | 2021-06-22 → 2026-06-19 |
| H1 | 12.018 | 2021-06-22 → 2026-06-19 |
| H4 | 3.733 | 2021-06-22 → 2026-06-19 |
| D1 | 1.246 | 2021-06-22 → 2026-06-19 |

Fonte: CSVs exportados do MT5 desktop (WIN$).

---

## Parâmetros TRIAL_0028

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

---

## Próximos passos

1. Backfill SMC V2 para os 7 timeframes
2. Sync zonas + candles → Hostinger
3. Re-run champion confirmation (Run #8)
4. Comparar com Run #5 (dataset antigo)

---

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```
