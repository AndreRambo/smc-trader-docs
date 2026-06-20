# RESULTADO FASE 8 — HOLDOUT VALIDATION — 2026-06-21 00:23 UTC+2

**Candidato:** TRIAL_0028
**Periodo holdout:** 2026-01-22 → 2026-06-19 (~5 meses, ~100 dias uteis)

## Resultado

| Metrica | In-Sample (8 folds) | Holdout | Delta | Status |
|---------|--------------------|---------|-------|--------|
| PF | 4.20 | 4.08 | -0.12 | ✅ |
| E(R) | 0.696R | 0.814R | +0.118 | ✅ |
| TP1% | 96.9% | 100.0% | +3.1 | — |
| Trades | 65 | 10 | -55 | — |
| Degradacao PF | — | — | -2.9% | ✅ < 20% |

## Desfecho

- Sinais: 119
- Entradas: 10
- TP1: 10 | TP2: 10 | TP3: 7 | STOP: 0 | EXP: 0

## Decisao

✅ **PASSOU** — TRIAL_0028 e robusto fora da amostra. Prosseguir para FASE 9 (Forward Shadow).

## DB

- trade_backtest_runs: run_id=6
- trade_backtest_results: 10 rows

## Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```
