# RESULTADO FASE 3 — SIMULADOR DE BACKTEST — 2026-06-17

**Status**: ✅ CONCLUÍDO  
**Testes**: 35/35 passando (Fase 1: 23 + Fase 2: 12)

---

## Entregas

### Arquivos criados (4)

| Arquivo | Descrição | Linhas |
|---------|-----------|--------|
| `execution_model.py` | ExecutionModel — simula fill (LIMIT/MARKET), STOP_FIRST, expiracao | 240 |
| `event_simulator.py` | EventSimulator — itera candles M5, gera sinais, simula execucao | 80 |
| `metrics.py` | compute_metrics — TP1_BEFORE_STOP_RATE, expectancy, PF, DD, MAE, MFE, robustness | 210 |
| `runner.py` | run_backtest + run_walk_forward — orquestracao completa | 130 |

### Execução verificada

**Testes unitários do ExecutionModel:**
- ✅ TP1_HIT: entrada LIMIT → price sobe → TP1 atingido (+1.67R)
- ✅ STOP_LOSS: entrada LIMIT → price cai → stop atingido (-1.0R)
- ✅ AMBIGUOUS: candle toca stop E TP1 → STOP_FIRST_CONSERVATIVE → STOP_LOSS

### Funcionalidades

**Execution Model:**
- Ordem LIMIT: fill only if `low <= entry <= high`
- Ordem MARKET: next_open + slippage
- STOP_FIRST_CONSERVATIVE: stop ganha em candle ambíguo
- Expiração por candles (default: 6 M5)
- Custos: spread + slippage + brokerage + exchange fees

**Métricas:**
- Primária: TP1_BEFORE_STOP_RATE (condicional em entradas que sobreviveram ao stop)
- Secundárias: TP2/TP3_BEFORE_STOP_RATE, expectancy_R, profit_factor, max_drawdown_R
- MAE/MFE: adverse e favorable excursion medianas
- Ambiguity: ambiguous_bar_rate
- Robustness: ROBUSTNESS_SCORE_V1 (composto ponderado)

**Runner:**
- `run_backtest()`: período fixo com min_candles_before
- `run_walk_forward()`: janelas deslizantes train/val/test

### Performance

Backtest de 15 dias (1370 candles M5) gera sinais em ~1s por candle devido a DB round-trips.
Para backtest completo, o repositório será otimizado na Fase 4 com cache em memória.

---

## Limitações conhecidas

1. Performance: cada candle faz DB queries → ~1s/candle. Cache será implementado na Fase 4.
2. Walk-forward: estrutura implementada, tuning de grid na Fase 4.
3. Sem persistência automática dos resultados (será na Fase 4).

---

## Guardrails (todos ativos)

```
STOP_FIRST_CONSERVATIVE ✅  (candle ambíguo → stop ganha)
anti_lookahead=True ✅       (candles filtrados por up_to_time)
shadow_only=True ✅          (sem escrita em produção)
deterministico=True ✅       (sem random, sem LLM)
```

---

## Módulos completos (16 arquivos)

```
technical_engine/signal_candidate_v1/  (9 arquivos)
  __init__.py, enums.py, models.py, config.py,
  hashing.py, serializer.py, errors.py,
  persistence.py, repositories.py,
  setup_detector.py, entry_selector.py,
  stop_selector.py, target_selector.py,
  geometry_validator.py, signal_builder.py

technical_engine/signal_backtest_v1/  (6 arquivos)
  __init__.py, models.py,
  execution_model.py, event_simulator.py,
  metrics.py, runner.py
```

## Próxima Fase

**FASE 4 — BACKTEST EXPLORATÓRIO**
- Otimizar repositório com cache em memória
- Executar grid de configurações pré-registrada (1296 combos)
- Filtrar configurações inválidas
- Identificar setups promissores para walk-forward
