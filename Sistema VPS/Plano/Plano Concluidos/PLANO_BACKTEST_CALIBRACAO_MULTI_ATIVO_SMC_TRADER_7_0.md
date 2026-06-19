# Plano: Backtest, Calibração Multi-Ativo e Validação do Pipeline — SMC Trader 7.0

**Criado**: 2026-06-17  
**Branch**: fix/roadmap-closeout-e2e-soak-v1  
**Status das implementações**:  
- `get_asset_config()` → IMPLEMENTADO (`technical_engine/smc_engine_v2/config.py`)  
- dispatcher.py — 5 call sites corrigidos → IMPLEMENTADO  
- Testes: 1915+164 passed, 0 failures

---

## Contexto

O pipeline SMC Trader 7.0 está completamente operacional: 11 ativos coletando (B3 + Forex),
eventos outbox M1–D1, análise completa M5 (SMC V2 + Elliott + Wyckoff + Risk + Scanner),
dispatch HMAC-signed para Laravel → app Android.

O backtest S20 WINFUT (6 meses) gerou 19.120 sinais com win rate 97,47% @ 1,5R — resultado
muito alto que indica calibração específica demais para WINFUT M5. Todos os parâmetros de
qualidade de zonas (OB size thresholds, session scoring, BPR max_size) foram calibrados no
WINFUT B3 e ainda não foram adaptados para outros ativos, especialmente Forex (XAUUSDm/Gold).

**Objetivo**: Verificar o pipeline em produção, backtestear e calibrar XAUUSDm (Gold) primeiro,
depois os demais ativos, e implementar as melhorias de código necessárias.

---

## Problemas Identificados e Correções

### 1. Dispatcher ignorava configuração por ativo (CORRIGIDO)

**Arquivo**: `services/candle_event_processor/dispatcher.py`  
**Problema**: Criava `cfg = get_default_config()` mas nunca passava para `run_smc_engine_v2_local()`.
Todos os 11 ativos usavam thresholds WINFUT M5 mesmo sendo Forex/Crypto/Equities.  
**Correção**: Todos os 5 call sites agora passam `config=get_asset_config(symbol)`.

### 2. Session scoring errado para Forex (CORRIGIDO via get_asset_config)

- WINFUT/WDOFUT/Equities B3: pregão 10h-18h BRT = 13h-21h UTC ✓
- XAUUSDm (Gold): London+NY = 8h-17h UTC (não 13h-21h UTC)
- EURUSDm: London = 7h-16h UTC
- USDJPYm: Tokyo = 0h-9h UTC
- BTCUSDm/ETHUSDm: 24/7, sem penalidade de sessão

OBs do Gold formados às 8h-13h UTC (London) recebiam score 56 (FORA) em vez de 83 (PREGAO).

### 3. OB size thresholds errados para Forex/Crypto (CORRIGIDO via get_asset_config)

Thresholds anteriores (100/250/500 pts WINFUT) eram inúteis para:
- Gold: OBs típicos de $1–$15 → novo: (2.0, 6.0, 15.0) USD
- EUR/USD: OBs de 5–30 pips → novo: (0.0005, 0.0015, 0.0040)
- BTC: OBs de $200–$2000 → novo: (200.0, 600.0, 1500.0) USD
- Equities B3: OBs de R$0.10–R$0.80 → novo: (0.10, 0.35, 0.80)

### 4. BPR max_size calibrado apenas para WINFUT (CORRIGIDO via get_asset_config)

- WINFUT: 150.0 pts | WDOFUT: 30.0 pts
- XAUUSDm: 8.0 USD | XAGUSDm: 0.25 | EURUSDm: 0.003
- BTCUSDm: 700.0 USD | ETHUSDm: 35.0 USD

---

## Implementação: get_asset_config()

**Arquivo**: `technical_engine/smc_engine_v2/config.py` (após linha 139)

```python
def get_asset_config(symbol: str) -> SMCEngineV2Config:
    s = symbol.upper()
    # WINFUT → calibration_profile="WINFUT_M5", thresholds=(100,250,500), B3 pregão
    # WDOFUT → thresholds=(5,15,30), B3 pregão
    # PETR4/VALE3/ITUB3 → thresholds=(0.10,0.35,0.80), B3 pregão
    # XAUUSDm → thresholds=(2,6,15) USD, pregao_start_hour=8, pregao_end_hour=17, tz=0
    # XAGUSDm → thresholds=(0.03,0.10,0.25), London+NY session
    # EURUSDm → thresholds=(0.0005,0.0015,0.004), London session 7-16 UTC
    # USDJPYm → thresholds=(0.05,0.15,0.35), Tokyo 0-9 UTC
    # BTCUSDm → thresholds=(200,600,1500), 24/7 (session_pregao=72=session_fora)
    # ETHUSDm → thresholds=(5,15,40), 24/7
    # FALLBACK → DEFAULT_WINFUT_FALLBACK_{symbol}
```

---

## Diagnóstico XAUUSDm — Backtest S21 (baseline)

**Resultado S21** (asset_id=2, ~79K candles M5, 2024):

| Métrica | XAUUSDm | WINFUT |
|---------|---------|--------|
| Win rate (unique setups) | 48.6% | 97.47% |
| ALTISTA | 87.3% | — |
| BAIXISTA | 48.9% | — |
| PROXIMO | 70.6% | — |
| IMINENTE | 42.1% | — |
| avg_bars_to_entry | 75.8 bars M5 | — |

**Causas identificadas**:
1. Session bug: London OBs (8h-13h UTC) recebiam score 56 → agora 83
2. Size thresholds: quase todos OBs Gold classificados como "micro" (< 100 pts)
3. Trend viés ALTISTA (87%) durante bull market 2024 → BAIXISTA sem filtro de tendência HTF

---

## Fases de Execução

### Fase A — Verificação Pipeline ✅ (concluída sessão anterior)

Todos os 11 ativos com coleta ativa, eventos COMPLETED nas últimas 24h, dispatch outbox OK.

### Fase B — Backtest XAUUSDm Baseline ✅ (resultado S21 disponível)

Resultado documentado acima. Win rate 48.6% (unique_setups) com config WINFUT M5.

### Fase C — Calibração XAUUSDm ✅ (IMPLEMENTADO 2026-06-17)

`get_asset_config()` adicionado em `config.py`. Dispatcher.py corrigido em 5 call sites.
Testes: 1915+164 passando, 0 falhas.

### Fase D — Backtest XAUUSDm Calibrado

```bash
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
source venv/bin/activate
python tools/run_opportunity_scanner_backtest.py \
  --symbol XAUUSDm --asset-id 5 --months 6 --verbose \
  2>&1 | tee docs_geral/Relatorios/backtest_xauusdm_calibrado_$(date +%Y%m%d).log
```

**Nota**: O backtest tool S21 usa planos EMA-sintéticos, não o SMC V2 completo. Para um
backtest que use OB quality scoring, usar `tools/run_hit_rates_replay.py` com `--symbol XAUUSDm`.
O impacto da calibração é visível no pipeline live (dispatcher.py corrigido).

### Fase E — WDOFUT

```bash
python tools/run_opportunity_scanner_backtest.py \
  --symbol WDOFUT --asset-id 3 --months 6 --verbose
```

Calibração: já em `get_asset_config("WDOFUT")` — thresholds (5,15,30), B3 session.

### Fase F — Demais Ativos

Ordem de prioridade para backtests e monitoramento em produção:

1. **EURUSDm** (asset_id=6) — London 7-16 UTC, thresholds (0.0005, 0.0015, 0.004)
2. **PETR4** (asset_id=10) — B3 pregão, thresholds (0.10, 0.35, 0.80)
3. **VALE3** (asset_id=11) — B3 pregão, similar PETR4
4. **BTCUSDm** (asset_id=4) — 24/7, thresholds (200, 600, 1500)
5. **USDJPYm** (asset_id=7) — Tokyo 0-9 UTC, thresholds (0.05, 0.15, 0.35)
6. **XAGUSDm** (asset_id=8) — London+NY, thresholds (0.03, 0.10, 0.25)
7. **ETHUSDm** (asset_id=9) — 24/7, thresholds (5, 15, 40)
8. **ITUB3** (asset_id=12) — B3 pregão, similar PETR4

### Fase G — Filtros de Qualidade

#### G1 — Cooldown entre sinais do mesmo ativo
```python
# Em opportunity_scanner/scanner.py ou ScannerConfig
cooldown_same_asset_minutes: int = 15
cooldown_same_plan_minutes: int = 5
```

#### G2 — min_confidence default MEDIA
```python
min_confidence: str = "MEDIA"   # era "BAIXA"
```

#### G3 — Deduplicação por janela temporal
Não emitir sinal duplicado se já existe PENDENTE/RECENTE (< 30 min) para mesmo ativo
com entrada dentro de 0.5×ATR do sinal atual.

---

## Arquivos Modificados

| Arquivo | Mudança | Status |
|---------|---------|--------|
| `technical_engine/smc_engine_v2/config.py` | Adicionado `get_asset_config()` com 9 perfis | ✅ |
| `services/candle_event_processor/dispatcher.py` | 5 call sites passam `config=get_asset_config(symbol)` | ✅ |
| `opportunity_scanner/scanner.py` | Cooldown + min_confidence + dedup | PENDENTE |

---

## Configurações por Ativo (Resumo)

| Ativo | OB Thresholds | Sessão (UTC) | BPR max |
|-------|---------------|--------------|---------|
| WINFUT | 100 / 250 / 500 pts | 13-21 (B3) | 150.0 |
| WDOFUT | 5 / 15 / 30 pts | 13-21 (B3) | 30.0 |
| PETR4/VALE3/ITUB3 | 0.10 / 0.35 / 0.80 R$ | 13-21 (B3) | 0.80 |
| XAUUSDm | 2.0 / 6.0 / 15.0 USD | 8-17 (London+NY) | 8.0 |
| XAGUSDm | 0.03 / 0.10 / 0.25 USD | 8-17 (London+NY) | 0.25 |
| EURUSDm | 0.0005 / 0.0015 / 0.004 | 7-16 (London) | 0.003 |
| USDJPYm | 0.05 / 0.15 / 0.35 JPY | 0-9 (Tokyo) | 0.30 |
| BTCUSDm | 200 / 600 / 1500 USD | 0-24 (24/7) | 700.0 |
| ETHUSDm | 5.0 / 15.0 / 40.0 USD | 0-24 (24/7) | 35.0 |
