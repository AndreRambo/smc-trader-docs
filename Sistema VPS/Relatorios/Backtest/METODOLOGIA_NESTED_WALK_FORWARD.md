# Metodologia: Nested Walk-Forward Optimization (SMC V2)

**Versão:** 1.1 | **Data:** 2026-06-24
**Aplicado em:** WINFUT ✅ | **Pipeline:** WDOFUT → EUR/USD → USD/JPY → GBP/USD → USD/CHF → USD/CAD → AUD/USD → NZD/USD → GOLD → SILVER → NASDAQ → S&P 500 (12 ativos)

---

## ⚠️ REGRA: Execução em M2 (NÃO M5)

**Comprovado:** M2 execution supera M5 em todas as métricas:

| Métrica | M5 | M2 | Delta |
|---------|-----|-----|-------|
| Trades | 110 | **181** | +65% |
| PF | 1.05 | **1.36** | +30% |
| PnL | +118.8R | **+209.4R** | +76% |
| Stops | 0 | 0 | — |

**Estruturas SMC continuam em M5/M15/H4/D1.** Apenas a execução (entrada, stop, alvos) usa velas M2. Motivo: M2 oferece entradas mais finas, stops mais apertados (ATR menor) e melhor R:R.

**Conversão de parâmetros M5 → M2:**
- `expiry_candles = int(expiry_candles_m5 × 2.5)` — manter mesma duração em minutos
- `cooldown_bars` — mesmo raciocínio, mas cooldown usa índices M5
- `ATR` — naturalmente menor nas velas M2

---

## 1. Visão Geral

O processo tem 4 fases:

```
FASE 6: Nested Walk-Forward  →  encontra melhor combinação de parâmetros
FASE 7: Champion Confirmation →  backtest fold-by-fold do campeão
FASE 8: Holdout Validation    →  teste em dados nunca vistos
FASE 9: Forward Shadow         →  paper trading ao vivo 60-90 dias
```

### Objetivo

Encontrar parâmetros que maximizem **Profit Factor** e **Expectancy R**, 
mantendo **0 stops** e **alta assertividade TP1**, sem overfitting.

---

## 2. Dataset

### Requisitos

| Timeframe | Papel | Velocidade |
|-----------|-------|------------|
| D1 | HTF bias | ~1.200 candles |
| H4 | HTF bias | ~4.000 candles |
| M15 | Âncora do stop | ~50.000 candles |
| **M5** | **Setup + Entrada** | **~140.000 candles** |
| **M2** | **Execução (opcional)** | **~350.000 candles** |

### Período mínimo

**5 anos** de dados para cobrir múltiplos regimes de mercado (bull, bear, lateral).

### Fonte

- **CSV do MT5 desktop** (WIN$ contínuo) para M1/M2/M5/M15
- **API MT5** para H4/D1 (poucas velas, API suficiente)
- Importação: `tools/import_winfut_csv.py`

### Zonas SMC obrigatórias

Antes do backtest, calcular zonas SMC V2 para **todo o histórico**:
- FVGs, Order Blocks, BOS/CHOCH, Swings, Liquidity, Sessions, Retracements
- Script: `tools/backfill_smc_zones_winfut.py`
- Tempo: ~12h para 1.2M candles (6 TFs)

---

## 3. Search Space

### Parâmetros a otimizar

| Parâmetro | Valores típicos | O que faz |
|-----------|-----------------|-----------|
| `stop_buffer_atr` | 0.10, 0.15, 0.20, 0.25 | Distância do stop (multiplicador ATR) |
| `max_stop_atr` | 2.0, 2.5, 3.0 | Limite máximo do stop |
| `expiry_candles_m5` | 6, 9, 12 | Tempo de expiração da ordem |
| `session_only` | True, False | Operar só no pregão? |
| `require_htf_for_tp3` | True, False | Exigir H4/D1 alinhado para TP3? |
| `breakeven_after_tp1` | True, False | Stop → breakeven após TP1 |
| `trailing_stop_after_tp2` | True, False | Stop → TP1 após TP2 |
| `cooldown_bars_m5` | 3, 5, 8 | Mínimo entre sinais |

### Tamanho do search space

- **8 parâmetros binários/3-valores** → ~1.728 combinações
- **200 trials** (amostragem aleatória determinística, seed fixo)
- **8 folds** de walk-forward (~2 meses cada)

### Parâmetros fixos (não otimizar)

- `require_m2_confirmation: False` (placeholder)
- `intrabar_policy: STOP_FIRST_CONSERVATIVE`
- Custos: spread=5pts, slippage=5pts, corretagem=R$0.50, bolsa=R$0.27

---

## 4. Execução

### Walk-Forward Windows

```
W00: 2023-02-06 → 2023-04-05
W01: 2023-07-02 → 2023-08-29
W02: 2023-11-25 → 2024-01-22
W03: 2024-04-19 → 2024-06-16
W04: 2024-09-12 → 2024-11-09
W05: 2025-02-05 → 2025-04-04
W06: 2025-07-01 → 2025-08-28
W07: 2025-11-24 → 2026-01-21
```

### Paralelização

```bash
# 4 workers, cada com 50 trials
# --execution-tf M2 é OBRIGATÓRIO (M5 provado inferior)
for w in 0 1 2 3; do
    nohup python -u tools/run_phase6_nested_wf.py --run \
        --execution-tf M2 --worker-id $w --total-workers 4 &
done
```

> **Nota:** `--execution-tf M2` requer preload de `2min` no CachedSMCRepository.
> O backfill SMC (zonas) deve incluir `2min` apenas para visualização no site/app.
> Para o backtest, apenas as velas M2 são necessárias — as estruturas continuam em M5/M15/H4/D1.

### Performance

| Métrica | 1 worker | 4 workers |
|---------|----------|-----------|
| Tempo/fold | ~110s | ~110s |
| Tempo total | ~48h | **~12h** |
| RAM/worker | ~1.5GB | ~1.5GB |
| RAM total | 1.5GB | ~6GB |

### Scripts

| Script | Função |
|--------|--------|
| `tools/run_phase6_nested_wf.py --run` | Executa o nested WF |
| `tools/run_phase6_nested_wf.py --finalize` | Seleciona o Champion |
| `tools/run_champion_m2_exec.py` | Champion confirmation M2 (salva trades no MySQL) |

---

## 5. Métricas de Avaliação

### Por trial (média dos 8 folds)

| Métrica | Alvo | Descrição |
|---------|------|-----------|
| **Profit Factor** | > 2.0 | Lucro bruto / perda bruta |
| **Expectancy R** | > 0.3R | Retorno médio por trade |
| **TP1 before stop** | > 95% | % trades que atingem TP1 sem stop |
| **TP3 before stop** | > 35% | % trades que atingem TP3 sem stop |
| **Max Drawdown** | < 10R | Drawdown máximo em R |
| **Assertividade (Entry%)** | > 50% | % sinais que viram trade |

### ⚠️ REGRA: Formato padrão da tabela de resultados

**SEMPRE usar este formato para comparar trials/candidatos:**

```
#  Trial   PF     E(R)   Trades  T/dia  Entry%  TP1%   TP2%   TP3%
--  ------  -----  -----  ------  -----  ------  -----  -----  -----
1   TRIAL   3.33   0.486  550     1.2    53.8    100.0  45.0   42.5
```

**Colunas obrigatórias:**
- `#` — ranking
- `Trial` — identificador
- `PF` — Profit Factor médio (8 folds)
- `E(R)` — Expectancy R média
- `Trades` — total de entradas válidas (NUNCA sinais totais)
- `T/dia` — trades por dia (trades / (folds × ~58 dias))
- `Entry%` — assertividade (entradas / sinais × 100)
- `TP1%` — % trades que atingem TP1 sem stop
- `TP2%` — % trades que atingem TP2 sem stop
- `TP3%` — % trades que atingem TP3 sem stop

> ⚠️ `Trades` deve usar `total_valid_entries` (entradas reais), NUNCA `sample_size` (sinais totais).

### Por fold (consistência)

- **Todos os folds devem ser lucrativos** (PF > 1.0)
- **TP1 deve ser > 90% em todos os folds**
- Folds com TP1=0% ou PF<0.5 → **rejeitar o trial**

---

## 6. Seleção do Champion

### Critérios (em ordem de prioridade)

1. **Consistência entre folds** — desvio padrão do PF entre folds
2. **Profit Factor médio** — quanto maior, melhor
3. **Expectancy R** — retorno por unidade de risco
4. **TP3 rate** — indica trades que vão até o alvo máximo
5. **Número de trades** — mais trades = mais robustez estatística

### O que NÃO fazer

- ❌ Selecionar apenas pelo maior PF (pode ser overfitting)
- ❌ Ignorar folds com PF baixo (inconsistência)
- ❌ Usar holdout na seleção (vaza informação)

### Bootstrap

Após selecionar o Champion:
1. Bootstrap temporal (5.000 iterações)
2. Calcular PF_LCB_95 (limite inferior do intervalo de confiança 95%)
3. Se PF_LCB_95 > 1.0 → candidato é robusto
4. PBO (Probability of Backtest Overfitting) < 0.05 ideal

---

## 7. Champion Confirmation + Holdout

### FASE 7: Champion Confirmation

- Backtest fold-by-fold do campeão
- Persiste cada trade no MySQL (`trade_backtest_results`)
- Gera relatório `CHAMPION_CONFIRMATION_*.md`

### FASE 8: Holdout Validation

- Período: últimos 5 meses (nunca usados no treino)
- Degradação aceitável: < 20% no PF
- Se passar → candidato é robusto OOS

### FASE 9: Forward Shadow

- 60-90 dias de paper trading ao vivo
- Sem dinheiro real
- Confirma se performance live ≈ backtest

---

## 8. Pipeline Multi-Ativo

### Ordem de execução (12 ativos)

| # | Ativo | Alias MT5 | Mercado | Sessão | ATR típico | Notas |
|---|-------|-----------|---------|--------|------------|-------|
| 1 | **WINFUT** | WIN$ | B3 | 09-18h | 100-300 pts | ✅ Concluído |
| 2 | WDOFUT | WDO$ | B3 | 09-18h | 5-15 pts | Point value R$10/pt |
| 3 | EUR/USD | EURUSDm | Forex | 24h | 8-15 pips | `session_only=False` |
| 4 | USD/JPY | USDJPYm | Forex | 24h | 8-15 pips | `session_only=False` |
| 5 | GBP/USD | GBPUSDm | Forex | 24h | 10-20 pips | `session_only=False` |
| 6 | USD/CHF | USDCHFm | Forex | 24h | 8-12 pips | `session_only=False` |
| 7 | USD/CAD | USDCADm | Forex | 24h | 8-15 pips | `session_only=False` |
| 8 | AUD/USD | AUDUSDm | Forex | 24h | 8-15 pips | `session_only=False` |
| 9 | NZD/USD | NZDUSDm | Forex | 24h | 8-15 pips | `session_only=False` |
| 10 | GOLD | XAUUSDm | Forex | 24h | 15-30 pips | `session_only=False` |
| 11 | SILVER | XAGUSDm | Forex | 24h | 20-40 pips | `session_only=False` |
| 12 | NASDAQ | USTECm | Índice | 24h* | 50-150 pts | `session_only=False` |
| 13 | S&P 500 | US500m | Índice | 24h* | 30-80 pts | `session_only=False` |

\* CFD 24h, mas liquidez reduzida fora do horário US

### Ajustes por mercado

| Mercado | `session_only` | Stop ATR | Spread/Slippage |
|---------|---------------|----------|-----------------|
| B3 (WINFUT/WDOFUT) | **True** | Normal | 5/5 pts |
| Forex (majors) | **False** | Normal | 2/2 pips |
| Forex (GOLD/SILVER) | **False** | Dobro (volátil) | 5/5 pips |
| Índices (NASDAQ/SP500) | **False** | Normal | 5/5 pts |

### Diretório de CSVs

```
/home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/
├── WINFUT/
│   ├── WINFUT_M1_2021_2026.csv
│   ├── WINFUT_M2_2021_2026.csv
│   └── ...
├── WDOFUT/
├── EURUSD/
├── USDJPY/
...
```

### Estratégia de paralelização

**Por ativo:** 4 workers × ~12h = cada ativo em ~12h
**Multi-ativo:** rodar 2-3 ativos em paralelo (8-12 workers total)
- RAM: ~1.5GB por worker → 12 workers = 18GB (não cabe nos 12GB)
- **Recomendado:** 1-2 ativos por vez (4-8 workers, ~6-12GB RAM)

### Setup por ativo (checklist)

Para cada novo ativo:
1. Coletar 5 anos de dados (CSV MT5) → `data/csv_import/{ATIVO}/`
2. Criar asset no banco (`get_or_create_asset`)
3. Importar velas (`tools/import_winfut_csv.py` adaptado)
4. Calcular zonas SMC V2 (`tools/backfill_smc_zones_winfut.py`)
5. Sync com site (`sync_all.sh --data-only`)
6. Rodar nested WF (200 trials × 8 folds, M2)
7. Champion confirmation + holdout
8. Forward shadow 60-90 dias

---

## 9. Guardrails

```
shadow_only=true           → nunca emitir ordens reais
research_only=true         → apenas pesquisa
can_promote_trade=false    → sem promoção para live
anti_lookahead=true        → sem vazamento de futuro
deterministic=true         → seed fixo, reprodutível
```

---

## 10. Tipos de Sinais (Convenção Visual)

Cada sinal no `meta_json` carrega `signal_type`:

| signal_type | Significado | Visualização |
|-------------|-------------|--------------|
| `STANDARD` | Sinal normal (TP1→TP2→TP3) | Cor padrão (ex: azul) |
| `SCALP` | TP1-only (saída no primeiro alvo) | Cor destacada (ex: laranja) |

O cliente diferencia visualmente: sinais SCALP são entrada rápida, STANDARD podem correr até TP3.

---

## 11. Comandos Rápidos

```bash
# 1. Importar dados CSV
python tools/import_winfut_csv.py

# 2. Calcular zonas SMC
python tools/backfill_smc_zones_winfut.py

# 3. Sync com site
bash sync_all.sh --data-only

# 4. Rodar nested WF (4 workers)
for w in 0 1 2 3; do
    nohup python -u tools/run_phase6_nested_wf.py --run \
        --execution-tf M2 --worker-id $w --total-workers 4 &
done

# 5. Finalize (selecionar Champion)
python tools/run_phase6_nested_wf.py --finalize \
    --execution-tf M2 --total-workers 4

# 6. Champion confirmation M2 (salva trades no DB)
python tools/run_champion_m2_exec.py
```
