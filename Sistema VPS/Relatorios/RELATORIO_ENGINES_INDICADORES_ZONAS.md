# Relatório: Engines de Indicadores e Zonas — SMC Trader System 7.0

> Gerado: 2026-06-27 | Fonte: `docs_geral/ARQUITETURA_OFICIAL.md`

---

## 1. Engines que calculam Indicadores Técnicos

### 1.1 Coleta + Indicadores Base (Market Candles)

| Engine | Arquivo | Indicadores calculados | Saída |
|--------|---------|----------------------|-------|
| **mt5_core.py** | `infra/mt5_core.py` | EMA20, EMA200, RSI, ATR, Volume | `market_candles` (OHLCV + indicadores, todos TFs, 11 ativos) |
| **run_b3.py** | `run_b3.py` | Loop 60s, 6 TFs, coleta candle + indicadores | `market_candles` |
| **run_forex.py** | `run_forex.py` | Loop 60s, 6 TFs, coleta candle + indicadores | `market_candles` |

**Indicadores armazenados em `market_candles`:**
- **EMA20** (Média Móvel Exponencial 20)
- **EMA200** (Média Móvel Exponencial 200)
- **RSI** (Índice de Força Relativa)
- **ATR** (Average True Range)
- **Volume** (volume do candle)

### 1.2 Context Engines (Indicadores Derivados)

| Engine | Arquivo | O que calcula | Saída |
|--------|---------|---------------|-------|
| **Elliott Wave** | `technical_engine/elliott/context.py` | 14 pivots, 9 wave legs, trend, stage, pattern inference | `technical_engine_elliott_shadow` (ctx_json) |
| **Elliott Sanity** | `technical_engine/elliott/sanity.py` | 4 regras: wave_count≥2, anti-lookahead, direction válido, sem overlapping | Validação |
| **Wyckoff Phase** | `technical_engine/wyckoff/context.py` | Range/volume context, event detection (SPRING/UT/SOS/SOW), phase inference | `technical_engine_wyckoff_shadow` (ctx_json) |
| **Wyckoff Sanity** | `technical_engine/wyckoff/sanity.py` | 3 regras: phase identificada, anti-lookahead, volume_context presente | Validação |
| **Market Profile** | `technical_engine/contextual_market_profile/builder.py` | Volatility (LOW/NORMAL/HIGH/EXTREME), session ID, market regime, HTF bias | Contexto para confluência |

### 1.3 Confluência + Study Gateway

| Engine | Arquivo | Função |
|--------|---------|--------|
| **Confluence V2** | `technical_engine/study_gateway/confluence_v2.py` | Fusão de 6 fontes (SMC+Elliott+Wyckoff+Contextual+MTF) com pesos calibrados |
| **Context States** | `technical_engine/study_gateway/context_states.py` | Helper Elliott/Wyckoff + RiskManagementConfig + MTFConfig |
| **SMC V2 Adapter** | `technical_engine/study_gateway/smc_v2_adapter.py` | SMC persisted → Envelope canônico + Readiness determinístico (PRONTO/MONITORAR/BLOQUEADO) |
| **TechnicalTruthEnvelopeV2** | `technical_engine/study_gateway/models_v2.py` | SHA-256 imutável, sanity gates, blockers |

---

## 2. Engines que calculam Zonas (SMC)

### 2.1 SMC Engine V2 (`technical_engine/smc_engine_v2/`) — **STABLE_FROZEN_V2**

| Engine | Arquivo | Zona calculada | Testes | Detalhes |
|--------|---------|---------------|--------|----------|
| **Pipeline** | `pipeline.py` | Orquestrador (10 passos) | — | Swings_df compartilhado, raise_on_error |
| **FVG** | `fvg.py` | **Fair Value Gaps** | 38 | 3-candle imbalance, mitigation 50%, vetorizado, displacement |
| **Order Blocks** | `order_blocks.py` | **Order Blocks** | 30 | prev+wick, quality scoring (size+session), config-driven |
| **Structure** | `structure.py` | **BOS/CHOCH** | 20 | Padrão 4 swings, close_break, 62% continuação |
| **Liquidity** | `liquidity.py` | **Liquidity Levels** | 10 | ATR-based cluster, swept detection |
| **BPR** | `bpr.py` | **Balanced Price Ranges** | 12 | Overlap FVG bull+bear, dedup >60%, quality scoring |
| **Swings** | `swings.py` | **Swing Points** | 8 | Rolling window, sem forced alternation |
| **Sessions** | `sessions.py` | **Session Markers** | — | London/B3/NY/Asia |
| **Retracements** | `retracements.py` | **Retracement %** | — | Percentuais de retracemento |
| **Previous High/Low** | `previous_high_low.py` | **PDH/PDL** | — | Previous Day High/Low |
| **Persistence** | `persistence.py` | Persistência | — | 10 tabelas shadow, load/save |
| **Config** | `config.py` | Configuração | — | OBQualityConfig, BPRQualityConfig |

**Total: 121 testes no SMC Engine V2.**

### 2.2 Execution Contexts (Entradas/Stop/Targets)

| Engine | Arquivo | Função |
|--------|---------|--------|
| **Risk Management V2** | `technical_engine/study_gateway/risk_management_v2.py` | OperationalPlanV2: entrada/stop/TP1-3/R:R/sizing + MTF 3 camadas |
| **Hit Rates V2** | `technical_engine/study_gateway/hit_rates_v2.py` | Walk-forward simulator + expectancy_R |
| **Forward Runner** | `technical_engine/study_gateway/forward_runner.py` | Forward shadow gateway (6L), loop ONCE/LOOP, idempotência |

---

## 3. Tabelas Oficiais e Localização

### 3.1 Tabelas Oficiais (VPS — write pelos robôs)

| Tabela | Conteúdo | Engine responsável |
|--------|----------|-------------------|
| `market_candles` | OHLCV + EMA20/EMA200/RSI/ATR | `mt5_core.py` → `run_b3.py` / `run_forex.py` |
| `assets` | Mapeamento id↔ticker↔alias (11 ativos) | Setup manual/config |
| `analysis_history` | Estudos históricos (legado) | Legado, não usado pelo V2 |

### 3.2 Tabelas Shadow SMC V2 (VPS — somente leitura para estudo)

| Tabela shadow | Zona armazenada | Engine que popula |
|---------------|----------------|-------------------|
| `technical_engine_smc_v2_runs_shadow` | Metadados de run | `pipeline.py` |
| `technical_engine_smc_v2_fvg_shadow` | **Fair Value Gaps** | `fvg.py` |
| `technical_engine_smc_v2_order_blocks_shadow` | **Order Blocks** (com quality scoring) | `order_blocks.py` |
| `technical_engine_smc_v2_bos_choch_shadow` | **BOS/CHOCH** (structural breaks) | `structure.py` |
| `technical_engine_smc_v2_liquidity_shadow` | **Liquidity Levels** | `liquidity.py` |
| `technical_engine_smc_v2_swings_shadow` | **Swing Points** | `swings.py` |
| `technical_engine_smc_v2_sessions_shadow` | **Session Markers** | `sessions.py` |
| `technical_engine_smc_v2_retracements_shadow` | **Retracement %** | `retracements.py` |
| `technical_engine_smc_v2_previous_high_low_shadow` | **PDH/PDL** | `previous_high_low.py` |
| `technical_engine_smc_v2_visual_overlays_shadow` | Visual Overlays | `persistence.py` |

### 3.3 Tabelas Shadow Context (VPS)

| Tabela shadow | Conteúdo | Engine que popula |
|---------------|----------|-------------------|
| `technical_engine_elliott_shadow` | Elliott ctx_json (symbol, timeframe) | `elliott/context.py` via TRIGGER 4b |
| `technical_engine_wyckoff_shadow` | Wyckoff ctx_json (symbol, timeframe) | `wyckoff/context.py` via TRIGGER 4b |

### 3.4 Tabelas Shadow Study (VPS)

| Tabela shadow | Conteúdo |
|---------------|----------|
| `technical_engine_study_replay_runs_shadow` | Replay runs |
| `technical_engine_study_replay_samples_shadow` | Replay samples (com truth_envelope_v2) |
| `technical_engine_study_replay_metrics_shadow` | Replay metrics |

### 3.5 Tabelas Shadow V2 (Rebuild Causal — Jun 2026)

| Tabela shadow | Conteúdo |
|---------------|----------|
| `technical_engine_calculation_runs_shadow` | Runs versionados (status ENUM: PENDING→BUILDING→VALIDATING→READY→ACTIVE→SUPERSEDED→FAILED) |
| `technical_engine_active_runs_shadow` | Tracking atômico de ativação |
| `technical_engine_structure_events_shadow` | Eventos lifecycle append-only: STRUCTURE_ORIGINATED→CONFIRMED→AVAILABLE→FIRST_TOUCH→TOUCHED→MITIGATED→INVALIDATED→EXPIRED→SWEPT |
| `technical_engine_indicator_values_shadow` | Persistência per-candle de EMA/ATR |
| `technical_engine_rebuild_artifacts_shadow` | Hash tracking para rebuilds |

---

## 4. Resumo Visual — Fluxo de Cálculo

```
┌─────────────────────────────────────────────────────────────────┐
│ DATA COLLECTION                                                  │
│ run_b3.py / run_forex.py                                        │
│   └→ market_candles (EMA20, EMA200, RSI, ATR, Volume)           │
└──────────────────┬──────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────────┐
│ SMC ENGINE V2 (smc_engine_v2/)                                  │
│   Pipeline → FVG + OB + BOS/CHOCH + Liquidity + BPR +           │
│              Swings + Sessions + Retracements + PDH/PDL          │
│   └→ 10 tabelas shadow technical_engine_smc_v2_*_shadow          │
└──────────────────┬──────────────────────────────────────────────┘
                   │
       ┌───────────┴───────────┐
       ▼                       ▼
┌──────────────┐    ┌──────────────────┐
│ ELLIOTT      │    │ WYCKOFF          │
│ context.py   │    │ context.py       │
│ (14 pivots,  │    │ (phase, events,  │
│  9 legs)     │    │  range/volume)   │
└──────┬───────┘    └────────┬─────────┘
       │                     │
       └──────────┬──────────┘
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│ CONTEXT ENGINES                                                  │
│   contextual_market_profile/builder.py (volatility, regime)     │
│   confluence_v2.py (6 fontes + MTF ponderada H4/M15/M5)        │
│   models_v2.py (TechnicalTruthEnvelopeV2, SHA-256)              │
│   smc_v2_adapter.py (persisted → envelope + readiness)          │
└──────────────────┬──────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────────┐
│ RISK MANAGEMENT V2                                               │
│   risk_management_v2.py (OperationalPlanV2)                     │
│   MTF 3 camadas: Gate HTF + Confluência + Alinhamento Espacial │
│   → entrada, stop, TP1-3, R:R, sizing, confiança                │
└──────────────────┬──────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────────┐
│ OUTPUT                                                          │
│   professional_study_renderer.py → Estudo profissional markdown  │
│   forward_runner.py → Forward shadow gateway (6L)               │
│   opportunity_scanner/ → Sinais → Site + App Android            │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Zonas Oficiais por Tipo — Resumo Rápido

| Tipo de Zona | Tabela Shadow | Engine | Qty (V1/V2) |
|-------------|---------------|--------|-------------|
| **FVG** | `technical_engine_smc_v2_fvg_shadow` | `fvg.py` | 309K (FVG) / V2: ~14K across TFs |
| **Order Blocks** | `technical_engine_smc_v2_order_blocks_shadow` | `order_blocks.py` | 61K (OB) / V2: ~2.8K across TFs |
| **BOS/CHOCH** | `technical_engine_smc_v2_bos_choch_shadow` | `structure.py` | 63K |
| **Liquidity** | `technical_engine_smc_v2_liquidity_shadow` | `liquidity.py` | 38K |
| **Swings** | `technical_engine_smc_v2_swings_shadow` | `swings.py` | 165K / V2: ~7K across TFs |
| **Sessions** | `technical_engine_smc_v2_sessions_shadow` | `sessions.py` | 1.46M |
| **Retracements** | `technical_engine_smc_v2_retracements_shadow` | `retracements.py` | 1.46M |
| **PDH/PDL** | `technical_engine_smc_v2_previous_high_low_shadow` | `previous_high_low.py` | 1.46M |
| **Visual Overlays** | `technical_engine_smc_v2_visual_overlays_shadow` | `persistence.py` | 1.48M |
| **Elliott Wave** | `technical_engine_elliott_shadow` | `elliott/context.py` | 36 (snapshot) |
| **Wyckoff Phase** | `technical_engine_wyckoff_shadow` | `wyckoff/context.py` | 36 (snapshot) |

---

## 6. Localização Física dos Módulos

```
SMC_Trader_System 7.0/
├── technical_engine/
│   ├── smc_engine_v2/          ← 12 módulos SMC (FVG, OB, BOS, etc.)
│   │   ├── pipeline.py         ← Orquestrador 10 passos
│   │   ├── fvg.py              ← Fair Value Gaps
│   │   ├── order_blocks.py     ← Order Blocks
│   │   ├── structure.py        ← BOS/CHOCH
│   │   ├── liquidity.py        ← Liquidity
│   │   ├── bpr.py              ← Balanced Price Ranges
│   │   ├── swings.py           ← Swings
│   │   ├── sessions.py         ← Sessions
│   │   ├── retracements.py     ← Retracements
│   │   ├── previous_high_low.py← PDH/PDL
│   │   ├── persistence.py      ← 10 tabelas shadow
│   │   └── config.py           ← OBQualityConfig/BPRQualityConfig
│   ├── elliott/                ← Elliott Wave (context + sanity)
│   ├── wyckoff/                ← Wyckoff (context + sanity)
│   ├── contextual_market_profile/ ← Volatility/session/regime
│   ├── study_gateway/          ← Confluence, Envelope, Risk Mgmt, Forward
│   │   ├── confluence_v2.py    ← 6 fontes + MTF ponderada
│   │   ├── models_v2.py        ← TechnicalTruthEnvelopeV2
│   │   ├── smc_v2_adapter.py   ← Persisted → Envelope
│   │   ├── context_states.py   ← Elliott/Wyckoff helper
│   │   ├── risk_management_v2.py← OperationalPlanV2
│   │   ├── hit_rates_v2.py     ← Walk-forward simulator
│   │   └── forward_runner.py   ← Forward shadow gateway
│   └── opportunity_scanner/    ← Scanner S1-S21
├── infra/
│   ├── mt5_core.py             ← Indicadores base (EMA/RSI/ATR)
│   └── sync_v2.py              ← Sync VPS → Site
├── run_b3.py                   ← Robô B3 (coleta + indicadores)
└── run_forex.py                ← Robô Forex (coleta + indicadores)
```

---

## 7. Guardrails de Cálculo

- `shadow_only=True` → Nunca escrever em tabelas oficiais
- `llm_decision_used=False` → LLM é redatora, nunca motor
- `smc_recomputed=False` → SMC consumido por run_id, nunca recalculado
- `anti_lookahead=True` → exclude_last_open_candle, available_at < study_time
- `deterministico=True` → Mesmo input = mesmo output (SHA-256)
- `probabilidade_proibida=True` → "Taxa histórica de alcance", nunca "probabilidade"
- **Elliott/Wyckoff**: snapshot-only (não histórico por zona). Desabilitados para discriminação em OB M5.
- **BOS/CHOCH e Liquidity**: não produzidos pelo engine no run V2 atual (D1/H4/H1/M15).
