# RELATÓRIO R21 — HOLDOUT 2026

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `tools/smc_v3_validation/run_holdout_validation.py`
**Parâmetros:** congelados desde R20 — nenhuma mudança de código nesta fase.

---

## 1. Metodologia

O motor incremental é **stateful** — rodar apenas os dados de 2026 isoladamente ("cold start") não refletiria o comportamento real em produção, onde o engine acumula estado desde 2021. `run_holdout_validation.py` roda o **stream completo** (2021-06-22 a 2026-06-19, 12.018 candles H1) e isola as estruturas cujo `availability_candle_id` cai dentro do período de holdout (`trading_date >= 2026-01-02`, conforme split definido no R2).

## 2. Resultado (Holdout: 2026-01-02 a 2026-06-19, 1.146 candles H1 reais)

```
Total candles no stream: 12.018
Holdout inicia no índice 10.872 (2026-01-02T09:00:00-03:00)
Candles no holdout: 1.146
Full-stream errors: []
```

| Engine | Estruturas no Holdout | Distribuição |
|---|---:|---|
| sessions | 460 | OPEN=230 CLOSE=230 |
| swings | 142 | HIGH=77 LOW=65 |
| bos_choch | 84 | WICK_SWEEP_HIGH=15 BOS_BULLISH=21 WICK_SWEEP_LOW=13 CHOCH_BULLISH=6 CHOCH_BEARISH=8 BOS_BEARISH=21 |
| previous_high_low | 230 | PDH=115 PDL=115 |
| retracements | 473 | FIBONACCI_ANCHOR=77 FIBO_LEVEL=330 DEALING_RANGE=66 |
| liquidity | 129 | BUYSIDE=71 SELLSIDE=58 |
| fvg | 461 | FVG_BEARISH=156 FVG_BULLISH=152 IFVG_BEARISH=83 IFVG_BULLISH=70 |
| bpr | 156 | BEARISH=86 BULLISH=70 |
| order_blocks | 164 | OB_BULLISH=95 OB_BEARISH=69 |

**Total: 2.299 estruturas produzidas no período de holdout, zero erros.**

## 3. Congelamento de Parâmetros

Nenhuma alteração de código foi feita entre R20 e R21 — os parâmetros usados no holdout são exatamente os mesmos validados em R3-R19 sobre Development (2021-2024) e Validation (2025).

## 4. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (261.9s)
```

---

## 5. GATE

```
R21_HOLDOUT_PASS
```

**Justificativa:**
- Stream completo processado sem erros, incluindo o período de holdout congelado
- 2.299 estruturas reais produzidas em 1.146 candles de holdout, com distribuição proporcionalmente consistente com Development/Validation (nenhuma anomalia de contagem)
- Parâmetros congelados desde R20, sem alteração de algoritmo durante o holdout
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R22 — Gate Final para Opportunity Backtest.
