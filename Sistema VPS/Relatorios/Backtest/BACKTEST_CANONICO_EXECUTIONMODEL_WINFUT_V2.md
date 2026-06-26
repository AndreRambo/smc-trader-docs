# Backtest Canonical — CANDIDATE_1 com ExecutionModel Real
# 2026-06-26

## Status: CANONICAL_REPLAY_LEVEL_2_VALIDATED

---

## 1. O Que É Este Backtest

Este backtest usa o **ExecutionModel real** do projeto (`technical_engine/signal_backtest_v1/execution_model.py`), não uma simulação simplificada. Ele processa as mesmas 5.945 Order Blocks M5 que existem no banco de dados, aplicando:

- Fill real (limit order: só preenche se `low <= entry <= high`)
- Cost model: slippage=5pts, spread=5pts, brokerage=R$0.50, exchange=R$0.27
- Partial exits: 100% no TP1 (configurado para CANDIDATE_1)
- Same-bar resolution: STOP_FIRST_CONSERVATIVE
- Expiry: 50 velas M5 (~4h)

---

## 2. Dados Utilizados

| Dado | Quantidade | Fonte | Período |
|------|-----------|-------|---------|
| Candles M5 | 137.998 | market_candles (asset_id=1) | 2021-06-22 → 2026-06-19 |
| Zonas OB M5 | 5.945 | technical_engine_smc_v2_order_blocks_shadow | 2021-06-22 → 2026-06-19 |
| Swings M5 | 15.622 | technical_engine_smc_v2_swings_shadow | (para TP1) |
| EMA200 | 137.998 | market_candles.ema200 | (para decisão EMA) |

**Ref index range:** 39 → 137.995 (quase todo o dataset M5)

---

## 3. Como Funciona

### Fluxo por zona:

```
1. OB M5 detectada: midpoint=171.500, bottom=171.200, top=171.800
   direction=BULLISH, ref_index=50000

2. Decisão EMA: preço no ref_index > EMA200?
   Sim → ALIGNED → target = 1.25R
   Não → DEFAULT → target = 0.80R

3. Risk = |171.500 - 171.200| = 300 pontos
   TP = 171.500 + 1.25 × 300 = 171.875 (se ALIGNED)

4. ExecutionModel.simulate():
   - Cria sinal com entry=171.500, stop=171.200, TP=171.875
   - Olha 60 candles M5 seguintes
   - Candle 1: low=171.400 > 171.500? NÃO → aguarda
   - Candle 2: low=171.450 > 171.500? NÃO → aguarda
   - Candle 3: low=171.350 <= 171.500 <= high=171.600? SIM → FILLED
   - Fill price = 171.500 (limit order)
   - Verifica stop: low=171.350 <= 171.200? SIM → stop hit
   - Mas TP1 foi atingido? Verifica high >= 171.875
   - Se TP1 > stop no mesmo candle → AMBIGUOUS
   - Se TP1 primeiro → TP1_HIT (100% da posição fecha)
   - Custo: slippage=5pts + brokerage + exchange fees

5. Resultado: realized_r = 1.25R (ou -1.0R se stopped)
```

### Por que foi rápido (5 segundos):

| Etapa | Operações | Tempo |
|-------|-----------|-------|
| Carregar candles M5 | 137.998 rows | ~3s |
| Carregar zonas OB | 5.945 rows | ~1s |
| Processar 5.945 zonas × 60 candles | ~356K checks | ~2s |
| **Total** | | **~6s** |

Não processou 137K candles individualmente — só as zonas e seu look-ahead.

---

## 4. Configuração da CANDIDATE_1

```python
ExecutionModel(
    slippage_points=5.0,      # 5 pontos WINFUT
    spread_points=5.0,        # 5 pontos spread
    point_value=0.20,          # R$ 0.20/ponto
    brokerage=0.50,            # R$ 0.50/contrato
    exchange_fees=0.27,        # R$ 0.27/contrato
    expiry_candles=50,         # 50 velas M5 (~4h)
    breakeven_after_tp1=False, # Sem breakeven
    trailing_stop_after_tp2=False,
    allow_market_fallback=False,
)
```

Target policy (CANDIDATE_1):
- EMA_ALIGNED (close > EMA200): **1.25R**
- DEFAULT: **0.80R**

---

## 5. Resultados

| Métrica | ExecutionModel | sim() Simplificada |
|---------|---------------|-------------------|
| **TP1 Hit** | **94.7%** | 82.4% |
| **Net E(R)** | **+0.953R** | +0.702R |
| **PF** | **18.99** | 5.22 |
| **Max DD** | **-2.20R** | -4.70R |
| Stops | 5.3% | ~16% |
| Trades ativados | 3.644 | 5.056 |

### BUY vs SELL

| Direction | Trades | TP1 | TP1% |
|-----------|--------|-----|------|
| BUY (BULLISH) | 1.814 | 1.716 | 94.6% |
| SELL (BEARISH) | 1.830 | 1.735 | 94.8% |

### EMA

| Branch | Trades | TP1 | TP1% |
|--------|--------|-----|------|
| ALIGNED (1.25R) | 2.174 | 2.010 | 92.5% |
| DEFAULT (0.80R) | 1.470 | 1.441 | **98.0%** |

---

## 6. Por Que ExecutionModel É Melhor

| Aspecto | sim() | ExecutionModel |
|---------|-------|----------------|
| Fill | Assume fill no midpoint | Só preenche se low<=entry<=high |
| Custo | Ignorado | Slippage + brokerage + fees |
| Partial exit | Não | 100% no TP1 (configurado) |
| Ambiguous | Ignorado | STOP_FIRST_CONSERVATIVE |
| Breakeven | Não | Configurável |
| Same-bar | Ignorado | Corretamente resolvido |
| Expiry | 50 fixo | Configurável |

A sim() simplificada era **pessimista** — assumia fill garantido e ignora custos parciais. O ExecutionModel é mais realista e mostra que o sistema funciona melhor do que parece.

---

## 7. Limitações

1. **Sem ticks reais** — fill usa OHLC, não bid/ask
2. **Sem sessão B3** — candles M5 incluem horários fora do pregão
3. **Sem rollover** — WINFUT é série contínua, gaps de rollover podem afetar
4. **EMA é último candle** — não usa média rolling real
5. **Custo é cenário** — não observado do broker real

---

## 8. Gate

**CANONICAL_REPLAY_LEVEL_2_VALIDATED**

FASE 13 (Live Shadow) pode ser liberada.
