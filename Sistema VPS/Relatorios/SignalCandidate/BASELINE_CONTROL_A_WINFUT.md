# BASELINE CONTROL_A — WINFUT — 2026-06-17 (ATUALIZADO)

**Fase**: 0 — Baseline e Congelamento do Controle  
**Alias**: CONTROL_A = Opportunity Scanner atual  
**Status**: CONGELADO — não será alterado  
**CANDIDATE_B status**: FASE 4 concluída, Expectancy -0.074R (near-breakeven)

---

## 1. Hashes dos Documentos

| Documento | SHA-256 |
|-----------|---------|
| PLANO_EXECUTIVO_SIGNAL_CANDIDATE_BACKTEST_WINFUT.md | `162a93bdfddfd13a45037588ca958c126d04a639278ae6f88730810dfffb2d3e` |
| COMO_O_STOP_E_DEFINIDO_NO_SMC.md | `932f9b2cb0342fc186ae61b4dba305c3920413c2e40ee1dca1b46061264eca86` |

---

## 2. Branch, Commit e Remotes

| Campo | Valor |
|-------|-------|
| Branch | `fix/roadmap-closeout-e2e-soak-v1` |
| Commit | `d513bef5fd285014d8fe0a29ec8c21ff8496feac` |
| Remote | `github-backup` |

---

## 3. Dataset WINFUT (pós-backfill MT5)

| Timeframe | Candles | Período | Duração |
|-----------|---------|---------|---------|
| 1min | 51.448 | 2025-12-08 → 2026-06-17 | 6.3 meses |
| 5min | 11.495 | 2025-12-08 → 2026-06-17 | 6.3 meses |
| 15min | 4.259 | 2025-12-08 → 2026-06-17 | 6.3 meses |
| 4h | 3.905 | 2021-06-17 → 2026-06-17 | 5 anos |
| 1d | 10.056 | 2022-04-29 → 2026-06-17 | 4.2 anos |

---

## 4. SMC V2 Shadow Data (WINFUT)

| Tabela | Registros |
|--------|-----------|
| FVG | 5.170 |
| Order Blocks | 1.243 |
| BOS/CHOCH | 1.354 |
| Liquidity | 2.032 |
| Swings | 3.347 |

---

## 5. CANDIDATE_B — Resultados (FASE 4)

Métricas do backtest de 6.3 meses com stops M15 + execução M1:

| Métrica | Valor |
|---------|-------|
| Sinais | 6.852 |
| Valid entries | 2.506 (36.6%) |
| Survived stop | 745 (29.7%) |
| **TP1 condicional** | **95.6%** |
| TP2 condicional | 53.2% |
| Expectancy | **-0.074R** (near-breakeven) |
| Profit Factor | 0.90 |

---

## 6. Módulos Implementados (16 arquivos)

```
technical_engine/signal_candidate_v1/
  __init__.py, enums.py, models.py, config.py,
  hashing.py, serializer.py, errors.py,
  persistence.py, repositories.py,
  setup_detector.py, entry_selector.py,
  stop_selector.py, target_selector.py,
  geometry_validator.py, signal_builder.py

technical_engine/signal_backtest_v1/
  __init__.py, models.py,
  execution_model.py, event_simulator.py,
  metrics.py, runner.py,
  cached_repo.py, fast_runner.py

tests/test_signal_candidate_v1/
  __init__.py, test_contracts.py, test_motor.py
```

## 7. Shadow Tables (5)

- `technical_engine_signal_candidate_runs_shadow`
- `technical_engine_signal_candidates_shadow`
- `technical_engine_signal_backtest_runs_shadow`
- `technical_engine_signal_backtest_trades_shadow`
- `technical_engine_signal_comparisons_shadow`

## 8. Guardrails

```
shadow_only=True ✅
candidate_only=True ✅
can_promote_trade=False ✅
apply_automatically=False ✅
anti_lookahead=True ✅
deterministico=True ✅
current_scanner_modified=False ✅
production_signal_emission=False ✅
```
