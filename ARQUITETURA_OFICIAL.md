# ARQUITETURA OFICIAL — SMC Trader System 7.0

> Atualizado: 2026-06-20 23:00 | Per-type SMC renderers (7 pipelines independentes) + Replay com dados reais + 1H adicionado ao pipeline + 10 tabelas smc_v2_* no Hostinger + sync dual-path

---

## 1. Visao Geral

SMC Trader System 7.0 e uma plataforma Python de analise tecnica multi-timeframe baseada em:
- **SMC** (Smart Money Concepts): FVG, Order Blocks, BOS/CHOCH, Liquidez, BPR
- **Elliott Wave**: pivots, wave legs, trend, stage, sanity checks
- **Wyckoff**: phase, events, range/volume context, effort/result
- **Confluence Deterministico**: fusao de 6 fontes com pesos calibrados
- **Risk Management**: entrada, stop estrutural, TP1-3, R:R, sizing, confianca
- **MTF 3 Camadas**: gate HTF + confluencia ponderada + alinhamento espacial
- **Estudo Profissional**: template narrativo onde a LLM e REDATORA, nunca motor

**Principio central**: `shadow_only=True`. Toda evolucao ocorre em tabelas `*_shadow`.
LLM nunca calcula zonas, score, probabilidade ou decisao. O motor e 100% deterministico.

---

## 2. Camadas do Sistema

```
┌────────────────────────────────────────────────────────────────────┐
│ Dashboard Local Shadow (Dash/Plotly — http://127.0.0.1:8050/)      │
│ Painels: Status → Study Shadow Preview → Forward Study (6L) →     │
│ Canonical Truth Replay (6J) → Contexto → Zonas/Multi-layer Audit   │
├────────────────────────────────────────────────────────────────────┤
│ Dashboard Frontend (React — dashboard_shadow/frontend/)            │
│ TradingView Lightweight Charts + WebSocket + SMC Zone Overlays     │
└────────────────────────┬───────────────────────────────────────────┘
                         │ REST + WebSocket (:8008)
┌────────────────────────▼───────────────────────────────────────────┐
│ Backend FastAPI (shadow — http://127.0.0.1:8008/)                  │
│ /api/smc-engine-v2/state          SMC V2 persisted (freshness)     │
│ /api/dashboard/multi-layer-state  SMC+Elliott+Wyckoff+Study+6L     │
│ /api/study-pipeline-shadow/*      Study Pipeline + Replay          │
└────────────────────────┬───────────────────────────────────────────┘
                         │
┌────────────────────────▼───────────────────────────────────────────┐
│ TECHNICAL ENGINE (shadow-only)                                     │
│                                                                    │
│ ┌─ SMC ENGINE V2 (STABLE_FROZEN_V2) ───────────────────────────┐  │
│ │ smc_engine_v2/pipeline.py    Pipeline integrado (10 passos)   │  │
│ │ smc_engine_v2/fvg.py         Fair Value Gaps                 │  │
│ │ smc_engine_v2/order_blocks.py Order Blocks (prev+wick, qual)  │  │
│ │ smc_engine_v2/structure.py   BOS/CHOCH (close_break)         │  │
│ │ smc_engine_v2/liquidity.py   Liquidity (ATR-based cluster)    │  │
│ │ smc_engine_v2/bpr.py         Balanced Price Ranges           │  │
│ │ smc_engine_v2/swings.py      Swings                           │  │
│ │ smc_engine_v2/sessions.py    Sessions (London/B3/NY/Asia)     │  │
│ │ smc_engine_v2/retracements.py Retracements %                  │  │
│ │ smc_engine_v2/previous_high_low.py PDH/PDL                    │  │
│ │ smc_engine_v2/persistence.py 10 tabelas shadow                │  │
│ │ smc_engine_v2/config.py      OBQualityConfig/BPRQualityConfig │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ STUDY GATEWAY CANONICAL TRUTH V2 ───────────────────────────┐  │
│ │ models_v2.py           TechnicalTruthEnvelopeV2 (SHA-256)    │  │
│ │ smc_v2_adapter.py      SMC persisted → Envelope + Readiness  │  │
│ │ confluence_v2.py       6 fontes deterministicas + MTF ponder  │  │
│ │ context_states.py      Elliott/Wyckoff helper + RiskConfig    │  │
│ │ forward_runner.py      Forward shadow gateway (6L)            │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ RISK MANAGEMENT V2 ─────────────────────────────────────────┐  │
│ │ risk_management_v2.py  OperationalPlanV2: entrada/stop/TP/R:R │  │
│ │                        MTF 3 camadas (gate+confluencia+zonas) │  │
│ │ hit_rates_v2.py        Walk-forward simulator + expectancy_R  │  │
│ │ professional_study_renderer.py Template narrativo (LLM redat) │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ CONTEXT ENGINES ────────────────────────────────────────────┐  │
│ │ elliott/context+sanity    Elliott: 14 pivots, 9 wave legs    │  │
│ │ wyckoff/context+sanity    Wyckoff: phase, events, range      │  │
│ │ contextual_market_profile/ Volatility, session, regime, HTF   │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ OPPORTUNITY SCANNER (S1-S21) ───────────────────────────────┐  │
│ │ scanner.py               Core scan_once()                    │  │
│ │ evaluator.py             10+ gates deterministicos           │  │
│ │ loader.py                Price M1 + Plan M5                  │  │
│ │ signal_builder.py        OpportunitySignalV1                 │  │
│ │ dedup.py                 Dedup 15min key                     │  │
│ │ notifier.py              WebSocket + HTTP POST outbox        │  │
│ │ http_post_notifier.py    HMAC-signed POST to Laravel         │  │
│ │ persistence.py           3 tabelas shadow                    │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ OPERATIONAL PLAN PERSISTENCE ───────────────────────────────┐  │
│ │ operational_plan_persistence.py  save/expire plans shadow    │  │
│ │ collector_health.py              Timeframe-aware health      │  │
│ │ asset_resolver.py                Ticker/alias centralizado   │  │
│ └──────────────────────────────────────────────────────────────┘  │
└────────────────────────┬───────────────────────────────────────────┘
                         │
┌────────────────────────▼───────────────────────────────────────────┐
│ DATA LAYER (MySQL VPS)                                             │
│   market_candles                    ← Robos B3/Forex               │
│   technical_engine_smc_v2_*_shadow  ← 10 tabelas SMC V2           │
│   technical_engine_study_*_shadow   ← Replay runs + samples        │
│   technical_engine_opportunity_*_shadow ← Signals/Alerts/Outbox   │
│   technical_engine_operational_plans_shadow ← OperationalPlanV2   │
└────────────────────────┬───────────────────────────────────────────┘
                         │
                         │ HTTP POST (HMAC Bridge + Scanner HMAC)
                         ▼
┌────────────────────────────────────────────────────────────────────┐
│ HOSTINGER — maximustrade.com.br (Laravel 12 + PHP 8.2+)             │
│                                                                    │
│ ┌─ BACKEND LARAVEL ───────────────────────────────────────────────┐│
│ │ 14 Controllers: Auth, Admin, Plan, MarketData, ScannerAlert,    ││
│ │ Sync, SyncHealth, MobileOpportunity, MobileDevice,              ││
│ │ MobilePreference, Webhook, Alert, Indicator, FcmTest            ││
│ │ 4 Middleware: VerifyScannerHmac, VerifySyncHmac,                ││
│ │              EnforcePlanLimits, Cors                            ││
│ │ HMAC dual: Scanner (Bearer + body sig) + Bridge (API Key +      ││
│ │           method/path/timestamp/nonce/body_hash)                ││
│ │ FCM HTTP v1: OAuth2 JWT → sendToDevice → push_logs             ││
│ │ Webhooks: 12 providers (Hotmart, Kiwify, Stripe, PayPal, etc.) ││
│ │ Sanctum tokens: mt_ prefix, 30d lifetime + 2FA TOTP            ││
│ │ Queue: database driver (SendOpportunityPushNotification job)    ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ┌─ BANCO MySQL (25+ tabelas em 18 migrations) ───────────────────┐│
│ │ Comercial: users, plans, licenses, subscriptions, purchases,   ││
│ │           products                                              ││
│ │ Auth:     personal_access_tokens, sessions, password_resets,    ││
│ │           permissions, roles, pivot tables                     ││
│ │ Mercado:  sync_assets, sync_candles, sync_zones, sync_studies, ││
│ │           sync_elliott_waves, sync_wyckoff_phases,             ││
│ │           sync_wyckoff_events, sync_health_logs                ││
│ │ Alertas:  scanner_alerts, opportunities, alerts                ││
│ │ Mobile:   user_devices, notification_preferences, push_logs    ││
│ │ Logs:     access_logs, audit_logs, webhook_logs                 ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ┌─ FRONTEND (React 19 + TypeScript + Vite 8 + Tailwind 4) ───────┐│
│ │ 16 paginas: Landing, Login, Register, Dashboard,                ││
│ │ AdminSystemHealth, ChartPage, Watchlist, Replay, Alertas,       ││
│ │ Indicadores, Admin(Planos/Usuarios/Licencas/Vendas/Produtos/   ││
│ │ Config)                                                         ││
│ │ Chart: Lightweight Charts v5 + Canvas SMC overlay primitives    ││
│ │ Health: polling 30s GET /api/sync/health (Sync/Service/Disk/DB) ││
│ │ Auth: AuthContext (login/2FA/logout state) + api.ts Bearer token││
│ │ Rotas: / (public) + /admin/* (protegido, 14 sub-rotas)          ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ Firebase/FCM → push notifications (job queue + push_logs)          │
└────────────────────────────────────────────────────────────────────┘
```

---

## 3. Fluxo de Dados

### 3.1 Coleta → Persistencia

```
MT5 (B3:11000 / Forex:11001)
  │ RPyC bridge (Wine)
  ▼
run_b3.py / run_forex.py (loop 60s, 6 TFs por ativo)
  │
  ├─ INSERT IGNORE market_candles (todos os TFs)
  │
  └─ TRIGGER 4 (apenas quando rows_added > 0, M1 excluido)
       V2_TIMEFRAMES = {M2, M5, M15, H1, H4, D1}
       ├─ 4a. run_v2_pipeline_and_sync(ticker, asset_id, timeframe)
       │        freshness: MAX(candles.timestamp) > MAX(v2_runs.created_at)?
       │        SE NAO → pula pipeline, apenas sync para site
       │        SE SIM →
       │          carregar ultimos N candles (LIMIT: M2=2500, M5=1500, M15=1000, H4=600, D1=500)
       │          run_smc_engine_v2_local() → FVG/OB/BOS/Liq/BPR/Swings/PDH/Sessions/Ret
       │          persist_smc_engine_v2_run() → 10 tabelas shadow
       │        sync_v2_shadow_zones() → POST /api/sync/zones (M1 nao sincroniza)
       └─ 4b. run_ew_pipeline_and_persist(ticker, asset_id, timeframe)
                freshness: latest_candle_time em technical_engine_elliott_shadow
                SE JA ATUALIZADO → skipped=True
                SE NOVO CANDLE →
                  carregar ultimos N candles (mesmo LIMIT de 4a)
                  configs calibradas por ativo (WINFUT: pivot_left=4, lookback=150, etc.)
                  build_elliott_context() → ctx.to_dict() → technical_engine_elliott_shadow
                  build_wyckoff_context() → ctx.to_dict() → technical_engine_wyckoff_shadow
              Dashboard lê da shadow em vez de recomptar por request (~30×/min economizados)
```

### 3.2 Estudo Canonico

```
SMC V2 persisted + Elliott + Wyckoff + Contextual
  │
  ▼
Confluence V2 (6 fontes, pesos normalizados)
  │ direction, alignment_score, evidence_count
  ▼
TechnicalTruthEnvelopeV2 (SHA-256 imutavel)
  │
  ├── Readiness deterministico: PRONTO / MONITORAR / BLOQUEADO
  │   (7 blockers + escalacao com 5 criterios AND)
  │
  ▼
StudyPayloadTechnicalTruthV2 → LLM REDATORA (nunca calcula numeros)
```

### 3.3 Risk Management (Operational)

```
TechnicalTruthEnvelopeV2 + candles + htf_states
  │
  ├── CAMADA 1: Gate HTF (D1+H4) — block/demote por vies contrario
  ├── CAMADA 2: Confluencia ponderada (H4=0.40, M15=0.35, M5=0.25)
  ├── CAMADA 3: Alinhamento espacial (zona M5 sobreposta a H4/M15 → +15 quality)
  │
  ▼
OperationalPlanV2:
  entrada (edge/midpoint), stop (estrutural/ATR), TP1-3 (estrutura-ou-R),
  R:R, contratos, confianca (ALTA/MEDIA/BAIXA), trailing, zona proibida
  │
  ▼
Template FASE 3 → Estudo profissional markdown (LLM so escreve narrativa)
  │
  ▼
FASE 4 → Walk-forward simulator → taxa historica de alcance (nunca "probabilidade")
```

### 3.4 Opportunity Scanner → Site

```
OperationalPlanV2 (M5, ACTIVE)
  + price M1 (OHLC)
  │
  ▼
10 gates deterministicos:
  plan_active → readiness → has_operation → entrada → ATR
  → market_closed (B3 09-18h) → plan_too_old (10min)
  → price_too_old (3min) → contra_htf → invalidated
  → wrong_approach → distance > 3 ATR
  │
  ├── BLOQUEADO → skip
  │
  ▼
OpportunitySignalV1:
  proximity (NA_ZONA/IMINENTE/PROXIMO/OBSERVANDO)
  severity (CRITICAL/HIGH/MEDIUM/LOW)
  │
  ▼
Dedup (symbol+direction+proximity+price, 15min window)
  │
  ▼
PERSIST + NOTIFY:
  │
  ├── WebSocket (outbox → ws_sender)
  └── HTTP POST HMAC ────→ maximustrade.com.br/api/scanner/alerts
                              │
                              ▼
                           scanner_alerts → opportunities
                              │
                              ▼
                           FCM push (dry-run)
```

### 3.5 Sync VPS → Site

```
Candles/Zones/Studies (MySQL VPS)
  │
  ▼
infra/sync_v2.py:
  sync_v2_shadow_zones()  → dual-path:
    1. sync_v2_shadow_tables() → POST /api/sync/tables/push (raw rows por tabela)
    2. FALLBACK → POST /api/sync/zones (builder unificado)
  │
  sync_to_web.py:
  sync_candles()         → POST /api/sync/candles (lotes de 500)
  sync_elliott_wyckoff() → POST /api/sync/elliott + /api/sync/wyckoff
  │
  HMAC: X-API-Key + Client-Id + Timestamp + Nonce + Signature
  │
  ▼
maximustrade.com.br/api/sync/*
  → sync_assets, sync_candles, sync_zones, smc_v2_* (10 tabelas replicadas)
```

**Nota:** As 9 tabelas shadow do VPS (`technical_engine_smc_v2_*_shadow`) foram replicadas no Hostinger como `smc_v2_*` (10 tabelas incluindo runs). O sync envia raw rows por tabela via `/api/sync/tables/push` (HMAC protegido). Fallback automatico para `/api/sync/zones` (tabela unificada `sync_zones`) se o novo endpoint nao estiver disponivel.

---

## 4. Modulos Principais

### 4.1 SMC Engine V2 (`smc_engine_v2/`)

| Modulo | Componente | Testes |
|--------|-----------|--------|
| `pipeline.py` | Orquestrador (10 passos, swings_df compartilhado, raise_on_error) | — |
| `fvg.py` | FVG: 3-candle imbalance, mitigation 50%, vetorizado, displacement | 38 |
| `order_blocks.py` | OB: prev+wick, quality scoring (size+session), config-driven | 30 |
| `structure.py` | BOS/CHOCH: padrao 4 swings, close_break, 62% continuacao | 20 |
| `liquidity.py` | Liquidity: ATR-based cluster, swept detection | 10 |
| `bpr.py` | BPR: overlap FVG bull+bear, dedup >60%, quality scoring | 12 |
| `swings.py` | Swings: rolling window, sem forced alternation | 8 |
| `persistence.py` | 10 tabelas shadow, load/save, latest_candle_time auto | — |
| `config.py` | OBQualityConfig, BPRQualityConfig, SMCEngineV2Config | — |

**Status**: `STABLE_FROZEN_V2` — 164 testes.

### 4.2 Study Gateway (`study_gateway/`)

| Modulo | Descricao | Testes |
|--------|-----------|--------|
| `models_v2.py` | TechnicalTruthEnvelopeV2, StudyPayloadTechnicalTruthV2, InputRefV2, sanity gates, blockers | 15 |
| `smc_v2_adapter.py` | SMC persisted → envelope canonico + readiness deterministico (PRONTO/MONITORAR/BLOQUEADO) | 18 |
| `confluence_v2.py` | 6 fontes com pesos + CAMADA 2 MTF (fusao ponderada H4/M15/M5) | 18 |
| `context_states.py` | Elliott/Wyckoff helper (paridade replay↔forward) + RiskManagementConfig + MTFConfig | 3 |
| `forward_runner.py` | Forward shadow gateway (6L): loop ONCE/LOOP, idempotencia, alarmes | 18 |
| `risk_management_v2.py` | OperationalPlanV2: entrada/stop/TP/R:R/sizing + MTF 3 camadas | 30 |
| `hit_rates_v2.py` | Walk-forward simulator + tabulacao + expectancy_R (FASE 4) | 12 |
| `professional_study_renderer.py` | Template narrativo markdown (FASE 3) + coluna Taxa hist. (FASE 4) | 9 |

**Status**: PRONTO — 123 testes.

### 4.3 Context Engines

| Modulo | Descricao |
|--------|-----------|
| `elliott/context.py` | Pivot detection (pivot_left/right), wave legs, trend/stage/pattern inference |
| `elliott/sanity.py` | 4 regras: wave_count≥2, anti-lookahead, direction valido, sem overlapping |
| `wyckoff/context.py` | Range/volume context, event detection (SPRING/UT/SOS/SOW), phase inference |
| `wyckoff/sanity.py` | 3 regras: phase identificada, anti-lookahead, volume_context presente |
| `contextual_market_profile/builder.py` | Volatility (LOW/NORMAL/HIGH/EXTREME), session ID, market regime, HTF bias |

### 4.4 Opportunity Scanner (`opportunity_scanner/`)

| Modulo | Descricao | Testes |
|--------|-----------|--------|
| `config.py` | ScannerConfig — 6 ativos, ATR thresholds, freshness gates | — |
| `models.py` | 7 dataclasses: PriceRef, PlanRef, Evaluation, Signal, Alert, etc. | — |
| `evaluator.py` | 10+ gates deterministicos + freshness + approach side + window invalidation | 18 |
| `loader.py` | Carrega price M1 + plano operacional M5 + price window | 10 |
| `scanner.py` | Core `scan_once()` — orquestra loader → evaluator → signal → persist | 12 |
| `signal_builder.py` | Monta `OpportunitySignalV1` com todos os campos do plano + avaliacao | 8 |
| `dedup.py` | Chave de deduplicacao (symbol+direction+proximity+price) com janela 15min | 6 |
| `notifier.py` | Enfileira notificacoes no outbox para WebSocket | 9 |
| `http_post_notifier.py` | Canal HTTP POST com HMAC para Laravel (S24) | 17 |
| `persistence.py` | Salva sinais, alertas, scan runs em tabelas `_shadow` | 14 |
| `ab_shadow_compare.py` | Comparador A/B — scanner shadow vs canonical | 16 |

**Status**: ATIVO — 306 testes.

### 4.5 Operational Plan + Health

| Modulo | Descricao | Testes |
|--------|-----------|--------|
| `operational_plan_persistence.py` | Save/expire planos shadow, heartbeat NOW() | 18 |
| `collector_health.py` | Timeframe-aware health (critico M1/M5 vs contexto H4) | 14 |
| `asset_resolver.py` | Lookup centralizado por ticker/alias, cache estatico + DB | 20 |
| `forward_runner.py` | Forward shadow gateway: frontier marker, operational LCT | 18 |

### 4.6 Site — maximustrade.com.br (MaximusTrader)

**Localizacao:** `/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/`

```
┌────────────────────────────────────────────────────────────────────┐
│ MaximusTrader — maximustrade.com.br (Hostinger)                     │
│                                                                     │
│ ┌─ FRONTEND (React 19 + TypeScript + Vite 8 + Tailwind CSS 4) ───┐ │
│ │ src/                                                           │ │
│ │ ├── main.tsx                   Entry: BrowserRouter + AuthProvider│
│ │ ├── App.tsx                    Rotas publicas + protegidas      │ │
│ │ ├── index.css                  Tailwind v4 + Maximus design tokens│
│ │ ├── contexts/AuthContext.tsx   Auth state (login, 2FA, logout)  │ │
│ │ ├── lib/api.ts                 Fetch wrapper com auth token     │ │
│ │ ├── hooks/                     useMarketWebSocket (Socket.IO)   │ │
│ │ │   useRealMarketData.ts        API polling candles/zones       │ │
│ │ │   useSmcPerType.ts            Fetch unified + split per-type │ │
│ │ │   useReplayData.ts            Fetch historico + filtro data  │ │
│ │ ├── components/                                                │ │
│ │ │   ├── CandlestickChart.tsx   Lightweight Charts v5 + SMC overlays│
│ │ │   ├── PlotlyCandlestickChart.tsx DEPRECATED (fallback)        │ │
│ │ │   ├── BackgroundEffects.tsx  TickerTape, MouseGlow, GlowOrbs │ │
│ │ │   ├── ReplayControls.tsx     Play/Pause/Speed/Seek controls  │ │
│ │ │   └── chart/smc/            SMC per-type pipelines (24 arq)  │ │
│ │ │       ├── smcTypes.ts          Types + interfaces nativas    │ │
│ │ │       ├── smcStyle.ts          Cores SMC_COLORS + SMC_STYLE │ │
│ │ │       ├── smcRenderUtils.ts    xFromTime, LabelPlacer        │ │
│ │ │       ├── normalizers/         6 normalizers (1 por tipo)    │ │
│ │ │       ├── renderers/           7 renderers Canvas (1/tipo)   │ │
│ │ │       └── primitives/          7 ISeriesPrimitive wrappers   │ │
│ │ └── pages/                                                     │ │
│ │     ├── Landing.tsx            Landing publica + planos + CTA  │ │
│ │     ├── Login.tsx              Login com 2FA (code + recovery) │ │
│ │     ├── Register.tsx           Cadastro                        │ │
│ │     ├── Dashboard.tsx          Layout admin + sidebar nav      │ │
│ │     ├── AdminSystemHealth.tsx  Saude unificada: sync health +  │ │
│ │     │                          VPS metrics (CPU/RAM/Disk/Load/  │ │
│ │     │                          sparklines/servicos/rede/uptime) │ │
│ │     ├── AdminEvidence.tsx      Evidencias (charts/screenshots) │ │
│ │     ├── AdminEvidenceDetail.tsx Detalhe evidencia por bundleId │ │
│ │     ├── ChartPage.tsx          Grafico + watchlist + AI panel  │ │
│ │     ├── WatchlistPage.tsx      Multi-ativo watchlist table     │ │
│ │     ├── ReplayPage.tsx         Replay com dados reais + chart  │ │
│ │     ├── AlertasPage.tsx        Gestao de alertas usuario       │ │
│ │     ├── IndicadoresPage.tsx    Indicadores tecnicos            │ │
│ │     ├── AdminPlanosPage.tsx    Admin: planos CRUD              │ │
│ │     ├── AdminUsuariosPage.tsx  Admin: usuarios                 │ │
│ │     ├── AdminLicencasPage.tsx  Admin: licencas                 │ │
│ │     ├── AdminVendasPage.tsx    Admin: vendas/receita           │ │
│ │     ├── AdminProdutosPage.tsx  Admin: produtos                 │ │
│ │     ├── AdminCreditosPage.tsx  Admin: creditos                 │ │
│ │     └── AdminConfigPage.tsx    Admin: config global            │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ BACKEND (Laravel 12 + PHP 8.2+) ───────────────────────────────┐ │
│ │                                                                  │ │
│ │ app/Http/Controllers/Api/ (14 controllers):                       │ │
│ │   AuthController.php            Register, login, 2FA (TOTP),    │ │
│ │                                 forgot/reset, recovery, logout  │ │
│ │   AdminController.php           Dashboard stats, users/plans/   │ │
│ │                                 licenses/products/sales CRUD    │ │
│ │   PlanController.php            Listagem publica + admin CRUD   │ │
│ │   MarketDataController.php      assets, candles, zones, study,  │ │
│ │                                 elliott, wyckoff, state (public)│ │
│ │   ScannerAlertController.php    Recebe scanner alerts (HMAC),   │ │
│ │                                 cria opportunity, dispatch FCM  │ │
│ │   SyncController.php            6 endpoints SMC Bridge (main,   │ │
│ │                                 candles, zones, study, elliott, │ │
│ │                                 wyckoff)                        │ │
│ │   SyncHealthController.php      Heartbeat POST + health GET     │ │
│ │   MobileOpportunityController   Active/history/show opportunities │ │
│ │   MobileDeviceController.php    Register/delete FCM tokens      │ │
│ │   MobilePreferenceController    CRUD notification prefs         │ │
│ │   WebhookController.php         12 payment providers            │ │
│ │   AlertController.php           User alert CRUD                 │ │
│ │   IndicatorController.php       Indicator CRUD + listing        │ │
│ │   FcmTestController.php         Test push + FCM config status   │ │
│ │   InternalVpsMetricsController  Recebe metricas VPS (POST,\n│ │                                 HMAC, Cache::put)              │ │
│ │   AdminVpsMetricsController.php GET /admin/vps-metrics:\n│ │                                 latest + history + online check│ │
│ │                                                                  │ │
│ │ app/Http/Middleware/ (4 middleware):                              │ │
│ │   VerifyScannerHmac.php         HMAC Bearer + body signature    │ │
│ │   VerifySyncHmac.php            HMAC API Key + method/path/body │ │
│ │   EnforcePlanLimits.php         Per-plan feature enforcement    │ │
│ │   Cors.php                      Dynamic FRONTEND_URL CORS       │ │
│ │                                                                  │ │
│ │ app/Services/:                                                   │ │
│ │   FirebasePushService.php       FCM HTTP v1: OAuth2 JWT →       │ │
│ │                                 access token → send via cURL    │ │
│ │   Webhooks/{AbstractProvider, HotmartProvider, KiwifyProvider,  │ │
│ │            MercadoPagoProvider, PayPalProvider, StripeProvider, │ │
│ │            GenericProvider}.php  Signature validation + process  │ │
│ │                                                                  │ │
│ │ app/Jobs/:                                                       │ │
│ │   SendOpportunityPushNotification.php  Queue: filter users by   │ │
│ │     prefs (assets, proximities, quiet hours, max_pushes/hr),    │ │
│ │     dedup by alert_id, send FCM, log to push_logs               │ │
│ │                                                                  │ │
│ │ app/Models/ (26 models):                                         │ │
│ │   User, License, Plan, Product, Purchase, Subscription          │ │
│ │   Alert, Indicator, Configuration                               │ │
│ │   SyncAsset, SyncCandle, SyncZone, SyncStudy                    │ │
│ │   SyncElliottWave, SyncWyckoffPhase, SyncWyckoffEvent           │ │
│ │   SyncHealthLog, ScannerAlert, Opportunity                      │ │
│ │   UserDevice, NotificationPreference, PushLog                   │ │
│ │   AccessLog, AuditLog, WebhookLog                                │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ BANCO MySQL (Hostinger — 25+ tabelas em 18 migrations) ───────┐ │
│ │                                                                  │ │
│ │ Comercial (5): users, plans, licenses, subscriptions, purchases │ │
│ │ Auth (6):     personal_access_tokens, sessions, password_resets │ │
│ │               permissions, roles, role-* pivot tables (Spatie)  │ │
│ │ Mercado (8):  sync_assets, sync_candles, sync_zones, sync_studies│ │
│ │               sync_elliott_waves, sync_wyckoff_phases,          │ │
│ │               sync_wyckoff_events, sync_health_logs             │ │
│ │ Alertas (3):  scanner_alerts, opportunities, alerts             │ │
│ │ Mobile (3):   user_devices, notification_preferences, push_logs │ │
│ │ Logs (3):     access_logs, audit_logs, webhook_logs             │ │
│ │ Outros (2):   indicators, configurations                        │ │
│ │ Jobs (2):     jobs, cache                                       │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ AUTENTICACAO ─────────────────────────────────────────────────┐ │
│ │                                                                  │ │
│ │ Sanctum Token Auth:                                              │ │
│ │   - Token prefix: mt_  |  Lifetime: 30 dias                      │ │
│ │   - Stateful: maximustrade.com.br + localhost dev                 │ │
│ │   - Rate: 5 req/min login/register                                │ │
│ │                                                                  │ │
│ │ 2FA TOTP (spomky-labs/otphp):                                     │ │
│ │   - Setup: gera secret + QR code URL                              │ │
│ │   - Enable: verifica 6-digit code → 6 recovery codes              │ │
│ │   - Login: retorna requires_2fa → mostra input → verify2fa        │ │
│ │                                                                  │ │
│ │ HMAC (Internal Services — VPS → Hostinger):                       │ │
│ │   SMC Bridge:                                                     │ │
│ │     Headers: X-API-Key, X-Client-Id, X-Timestamp (±5min),        │ │
│ │             X-Nonce, X-Signature                                  │ │
│ │     Signature: HMAC-SHA256(method\npath\ntimestamp\nnonce\       │ │
│ │                            \nSHA256(body), client_secret)         │ │
│ │   Opportunity Scanner:                                            │ │
│ │     Bearer token + HMAC-SHA256(raw_body, SCANNER_HMAC_SECRET)    │ │
│ │     + X-Timestamp (±5min) + Idempotency-Key (alert_id)          │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ FCM / PUSH NOTIFICATIONS (Firebase HTTP v1) ───────────────────┐ │
│ │                                                                  │ │
│ │ FirebasePushService:                                              │ │
│ │   - Service account JSON → OAuth2 JWT → access token             │ │
│ │   - HTTP v1 endpoint: fcm.googleapis.com/v1/projects/            │ │
│ │     maximus-trade-signals/messages:send                           │ │
│ │                                                                  │ │
│ │ Push Flow:                                                        │ │
│ │   Scanner alert → ScannerAlertController → Opportunity           │ │
│ │   → SendOpportunityPushNotification job (queue: database)        │ │
│ │   → Filtra: user active, prefs enabled, asset filter,            │ │
│ │     proximity filter, quiet hours, max pushes/hr, dedup          │ │
│ │   → FirebasePushService.sendToDevice()                           │ │
│ │   → push_logs (status, fcm_message_id, sent_at, opened_at)       │ │
│ │                                                                  │ │
│ │ Payload (data):                                                   │ │
│ │   type=opportunity_alert, alert_id, symbol, direction,           │ │
│ │   proximity, timeframe, opportunity_time, sent_at,               │ │
│ │   deep_link (maximustrade://opportunity/{id})                    │ │
│ │                                                                  │ │
│ │ Payload (notification):                                           │ │
│ │   title: symbol + direction emoji + proximity                    │ │
│ │   body: entrada/stop/message                                     │ │
│ │   Android: priority HIGH (NA_ZONA/IMINENTE), channel "opportunities"│
│ │   APNS: sound, badge                                             │ │
│ │                                                                  │ │
│ │ Dry-run mode: FCM_DRY_RUN=true loga push simulado (sem envio)    │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ WEBHOOKS / PAGAMENTOS (12 gateways) ──────────────────────────┐ │
│ │                                                                  │ │
│ │ Endpoint unico: POST /api/webhooks/{provider} (rate: 60/min)    │ │
│ │                                                                  │ │
│ │ Providers: hotmart, kiwify, stripe, mercadopago, paypal,        │ │
│ │           eduzz, ticto, kirvano, monetizze, woocommerce,        │ │
│ │           shopify, perfectpay                                     │ │
│ │                                                                  │ │
│ │ Processing flow:                                                  │ │
│ │   1. Validate provider-specific signature                        │ │
│ │   2. Idempotency check (provider:transactionId:eventType)        │ │
│ │   3. Refund/Chargeback → suspend/revoke license                  │ │
│ │   4. Purchase → find/create user → map plan → license key        │ │
│ │      (MT-XXXX-XXXX-XXXX-0001) → purchase record                  │ │
│ │   5. Subscription → recurring record + license renewal           │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ DESIGN SYSTEM (MAXIMUS-DESIGN-SYSTEM/) ───────────────────────┐ │
│ │                                                                  │ │
│ │ Design Tokens (Tailwind v4):                                      │ │
│ │   Background: deep-void (#0A0A10), obsidian (#12101E),           │ │
│ │              midnight (#1A1530)                                   │ │
│ │   Brand:      brand-primary (#7B2FF7), brand-secondary (#9B5CF6) │ │
│ │   Text:       text-primary (#F0EEFF), text-secondary (#C8C0E8)   │ │
│ │   Signals:    signal-buy (#3DDC84), signal-sell (#FF5F6D),       │ │
│ │               signal-alert (#F5A623), signal-info (#00CFFF)      │ │
│ │   Fonts:      Bebas Neue (display), DM Sans (body), DM Mono (mono)│ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ ROTAS API (40+ endpoints em routes/api.php) ───────────────────┐ │
│ │                                                                  │ │
│ │ Public (sem auth):                                                │ │
│ │   POST /api/auth/login, register, verify-2fa, recovery,          │ │
│ │        forgot-password, reset-password                            │ │
│ │   GET  /api/plans, /api/plans/{plan}                              │ │
│ │   GET  /api/assets, /api/assets/{ticker}                          │ │
│ │   GET  /api/candles/{ticker}, /api/zones/{ticker}                 │ │
│ │   GET  /api/study/{ticker}, /api/elliott/{ticker}                 │ │
│ │   GET  /api/wyckoff/{ticker}, /api/state/{ticker}                 │ │
│ │   GET  /api/sync/health, /api/indicators                          │ │
│ │                                                                  │ │
│ │ Sanctum (auth:sanctum):                                           │ │
│ │   GET  /api/me, /api/user, /api/2fa/recovery-codes                │ │
│ │   POST /api/logout, /api/logout-all                               │ │
│ │   POST /api/2fa/setup, enable, disable                            │ │
│ │   GET  /api/mobile/opportunities/active, history, /{id}          │ │
│ │   POST /api/mobile/devices, DELETE /api/mobile/devices/{id}      │ │
│ │   GET|PUT /api/mobile/preferences, assets, proximities            │ │
│ │   CRUD /api/alerts                                                │ │
│ │                                                                  │ │
│ │ Admin (auth:sanctum + role:admin):                                │ │
│ │   GET  /api/admin/dashboard, users, plans, licenses, products    │ │
│ │   POST|PUT|DELETE /api/admin/plans, indicators                   │ │
│ │   GET  /api/admin/vps-metrics  (VPS: latest + history, 15s poll) │ │
│ │   GET  /api/admin/evidences, /api/admin/evidences/{bundleId}     │ │
│ │                                                                  │ │
│ │ HMAC Internal:                                                    │ │
│ │   POST /api/sync, /api/sync/candles, zones, study, elliott,     │ │
│ │        wyckoff                                                   │ │
│ │   POST /api/scanner/alerts                                       │ │
│ │   POST /api/internal/vps-metrics  (VPS → site, 30s push, HMAC)  │ │
│ │                                                                  │ │
│ │ Webhooks:                                                         │ │
│ │   POST /api/webhooks/{provider}  (12 providers)                  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
```

### 4.7 App Android — AppAndroid/MaximusTrader

**Localizacao:** `/home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid/MaximusTrader/`
**Package:** `br.com.maximustrade.signals` | **Project:** `MaximusTradeSignals`
**Modulos:** 57 arquivos Kotlin (composeApp)

```
┌────────────────────────────────────────────────────────────────────┐
│ AppAndroid — MaximusTradeSignals (Kotlin 2.1 + Compose MP 1.7.3)   │
│                                                                     │
│ ARQUITETURA: Clean Architecture (3 camadas) + MVVM                  │
│                                                                     │
│ ┌─ PRESENTATION (features/) ──────────────────────────────────────┐ │
│ │                                                                  │ │
│ │ App.kt                     Root: NavHost (8 routes), Koin init  │ │
│ │                                                                  │ │
│ │ features/auth/                                                   │ │
│ │   LoginScreen.kt           Email/senha + 2FA code input          │ │
│ │   LoginViewModel.kt        StateFlow<LoginUiState> (Loading/     │ │
│ │                            Success/Requires2FA/Error)            │ │
│ │   ForgotPasswordScreen.kt  Email input → reset request           │ │
│ │   ForgotPasswordViewModel  StateFlow<ForgotPasswordUiState>      │ │
│ │                                                                  │ │
│ │ features/dashboard/                                              │ │
│ │   DashboardScreen.kt       Grid 4 cards (Oportunidades,          │ │
│ │                            Histórico, Pref, Conta),              │ │
│ │                            scanner status, disclaimer            │ │
│ │   DashboardViewModel.kt    activeCount + recent(3)               │ │
│ │                                                                  │ │
│ │ features/opportunities/                                          │ │
│ │   OpportunityListScreen.kt LazyColumn + pull-to-refresh          │ │
│ │   OpportunityListViewModel StateFlow<OppListUiState>             │ │
│ │   OpportunityCard.kt       Card: symbol, direction, proximity,   │ │
│ │                            timeframe, formatDisplayTime()        │ │
│ │   OpportunityDetailScreen  Entrada/Stop/TPs, tempo (timeframe,   │ │
│ │                            detectado, notificado), disclaimer    │ │
│ │   OpportunityDetailVM      StateFlow<OpportunityDetailUiState>   │ │
│ │                                                                  │ │
│ │ features/history/                                                │ │
│ │   HistoryScreen.kt         Lista paginada + filtros (symbol,     │ │
│ │                            direction) + HistoryCard              │ │
│ │   HistoryViewModel.kt      StateFlow<HistoryUiState>             │ │
│ │                                                                  │ │
│ │ features/preferences/                                            │ │
│ │   PreferencesScreen.kt     11 campos:                            │ │
│ │                            Push/Sound/Vibration toggles,         │ │
│ │                            Quiet hours (start/end),              │ │
│ │                            Max pushes slider (1-20),             │ │
│ │                            6 asset toggles, 4 state toggles      │ │
│ │   PreferencesViewModel.kt  StateFlow<UserPreferences> via        │ │
│ │                            PreferencesRepository Flow            │ │
│ │                                                                  │ │
│ │ features/account/                                                 │ │
│ │   AccountScreen.kt         Profile card (nome, email, plano),    │ │
│ │                            device count, logout c/ confirmacao   │ │
│ │   AccountViewModel.kt      loadAccount(), logout()               │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ DOMAIN (models + repository interfaces) ────────────────────────┐ │
│ │                                                                  │ │
│ │ domain/model/                                                     │ │
│ │   AuthModels.kt             LoginRequest, LoginRawResponse,      │ │
│ │                             LoginResult (sealed: Authenticated/  │ │
│ │                             Requires2FA), UserDto, 2FA models    │ │
│ │   OpportunityModels.kt      OpportunityDto (30 fields: entrada,  │ │
│ │                             stop, tp1-3, current_price,          │ │
│ │                             distance_to_entry_pts/atr,           │ │
│ │                             eta_to_entry_min, timeframe,         │ │
│ │                             opportunity_time, sent_at,           │ │
│ │                             approach_velocity_pts_min,           │ │
│ │                             Direction enum (ALTISTA/BAIXISTA/    │ │
│ │                             NEUTRO), RadarState enum (17 values) │ │
│ │                             PaginatedResponse, PaginationMeta    │ │
│ │   PreferenceModels.kt       UserPreferences (11 fields:          │ │
│ │                             push/sound/vibration, quiet hours,   │ │
│ │                             enabledAssets, enabledProximities,   │ │
│ │                             maxPushesPerHour)                    │ │
│ │                                                                  │ │
│ │ domain/repository/ (interfaces — zero deps de plataforma)        │ │
│ │   AuthRepository.kt         login, verify2fa, forgotPassword,    │ │
│ │                             logout, isLoggedIn                   │ │
│ │   DeviceRepository.kt       registerDevice, unregisterCurrent    │ │
│ │   OpportunityRepository.kt  getActive, getDetail(id), getHistory │ │
│ │   PreferencesRepository.kt  getPreferences (Flow), update        │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ DATA (implementations + remote/local sources) ──────────────────┐ │
│ │                                                                  │ │
│ │ data/repository/                                                  │ │
│ │   AuthRepositoryImpl.kt     ApiClient + TokenStorage             │ │
│ │   DeviceRepositoryImpl.kt   ApiClient /mobile/devices            │ │
│ │   OpportunityRepositoryImpl ApiClient /mobile/opportunities/*    │ │
│ │   PreferencesRepositoryImpl DataStore<Preferences> (local-only)  │ │
│ │                                                                  │ │
│ │ data/remote/                                                      │ │
│ │   OpportunityRemoteDataSource  Ktor → JsonObject (active, detail,│ │
│ │                               history)                           │ │
│ │   PreferenceRemoteDataSource   Ktor → JsonObject (GET/PUT prefs, │ │
│ │                               assets, proximities)               │ │
│ │                                                                  │ │
│ │ data/dto/                                                         │ │
│ │   AuthDto.kt                LoginRequest, LoginResponse,         │ │
│ │                             Verify2faRequest, UserDto            │ │
│ │   DeviceDto.kt              DeviceRegisterRequest, DeviceResponse│ │
│ │   PreferenceDto.kt          PreferenceUpdateRequest, Response    │ │
│ │                                                                  │ │
│ │ data/mapper/                                                      │ │
│ │   OpportunityMapper.kt      Map<String,String> → OpportunityDto  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ CORE (infra compartilhada) ────────────────────────────────────┐ │
│ │                                                                  │ │
│ │ core/api/                                                         │ │
│ │   ApiClient.kt              Ktor HttpClient (OkHttp engine):     │ │
│ │                             content-negotiation, auth Bearer,    │ │
│ │                             logging, base URL config             │ │
│ │   AppConfig.kt              API_BASE_URL = maximustrade.com.br   │ │
│ │                                                                  │ │
│ │ core/auth/                                                        │ │
│ │   AuthUtils.kt              JWT/token validation (KMP-compat)    │ │
│ │                                                                  │ │
│ │ core/di/                                                          │ │
│ │   Modules.kt                Koin commonModule:                    │ │
│ │                             single: ApiClient                    │ │
│ │                             single: AuthRepositoryImpl →         │ │
│ │                                     AuthRepository               │ │
│ │                             single: DeviceRepositoryImpl →       │ │
│ │                                     DeviceRepository             │ │
│ │                             single: OpportunityRepositoryImpl →  │ │
│ │                                     OpportunityRepository        │ │
│ │                             single: PreferencesRepositoryImpl → │ │
│ │                                     PreferencesRepository        │ │
│ │                             viewModelOf: 8 ViewModels            │ │
│ │                                                                  │ │
│ │ core/design/                                                      │ │
│ │   MaximusColors.kt         Paleta de cores (dark theme)          │ │
│ │   MaximusTheme.kt          Material3 darkColorScheme             │ │
│ │                                                                  │ │
│ │ core/notifications/                                               │ │
│ │   FcmOpportunityPayload.kt 15 campos: type, alertId,             │ │
│ │                            opportunityId, symbol, direction,     │ │
│ │                            proximity, timeframe, opportunityTime,│ │
│ │                            sentAt, title, body, deepLink,        │ │
│ │                            fromMap(Map<String,String>)           │ │
│ │   NotificationService.kt   Interface (platform-agnostic)         │ │
│ │                                                                  │ │
│ │ core/storage/                                                     │ │
│ │   DataStoreProvider.kt     Interface for DataStore<Preferences>  │ │
│ │   TokenStorage.kt          Interface: save/load/clear token +    │ │
│ │                            deviceId                              │ │
│ │                                                                  │ │
│ │ core/deeplink/                                                    │ │
│ │   DeepLinkHandler.kt       URI parser: maximus://opportunity/{id}│ │
│ │                                                                  │ │
│ │ core/utils/                                                       │ │
│ │   DateTimeUtils.kt         ISO-8601 → DD/MM/AAAA HH:MM format   │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ ANDROID MAIN (platform-specific) ───────────────────────────────┐ │
│ │                                                                  │ │
│ │ androidMain/                                                      │ │
│ │   MainActivity.kt           Compose entry, deep link parsing,    │ │
│ │                             FCM token registration               │ │
│ │   MainApplication.kt        Koin init (common + android modules) │ │
│ │                                                                  │ │
│ │   firebase/                                                       │ │
│ │     MaximusFirebaseMessagingService.kt                            │ │
│ │                             onNewToken → registerDevice()        │ │
│ │                             onMessageReceived → parse            │ │
│ │                             FcmOpportunityPayload →              │ │
│ │                             showNotification()                   │ │
│ │     AndroidNotificationService.kt                                 │ │
│ │                             Channel: "opportunity_alerts"        │ │
│ │                             HIGH importance, auto-cancel         │ │
│ │                             PendingIntent deep link              │ │
│ │                                                                  │ │
│ │   storage/                                                        │ │
│ │     AndroidSecureTokenStorage  EncryptedSharedPreferences        │ │
│ │     AndroidDataStoreProvider   preferencesDataStore              │ │
│ │                                                                  │ │
│ │ AndroidManifest.xml         INTERNET, POST_NOTIFICATIONS,        │ │
│ │                             deep link intent-filter              │ │
│ │                             (maximustrade://opportunity/*),      │ │
│ │                             FirebaseMessagingService             │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ NAVEGACAO (8 rotas, Jetpack Navigation Compose) ───────────────┐ │
│ │                                                                  │ │
│ │ splash → check isLoggedIn():                                     │ │
│ │   → deep link? → opportunity/{id}                                │ │
│ │   → logged in  → dashboard                                       │ │
│ │   → logged out → login                                           │ │
│ │                                                                  │ │
│ │ login     ← → forgot-password                                    │ │
│ │ login     → dashboard (on success)                               │ │
│ │ dashboard → opportunities, history, preferences, account         │ │
│ │ opportunities → opportunity/{id}                                 │ │
│ │                                                                  │ │
│ │ Deep link: maximus://opportunity/{id} → abre direto no detalhe   │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ BUILD CONFIG ──────────────────────────────────────────────────┐ │
│ │                                                                  │ │
│ │ Kotlin: 2.1.0 | Compose MP: 1.7.3 | AGP: 8.13.2                 │ │
│ │ Min SDK: 24 (Android 7.0) | Target SDK: 35 (Android 15)         │ │
│ │ Ktor 3.0.3 (OkHttp) | Koin 4.0.0 | FCM 33.9.0                   │ │
│ │ DataStore 1.1.1 | Security Crypto 1.1.0-alpha06                  │ │
│ │ Kotlinx Serialization 1.7.3 | Coroutines 1.10.1                  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
```

---

### 4.8 Infraestrutura

| Componente | Descricao |
|-----------|-----------|
| `mt5_connection.py` | Dual-port RPyC bridge (B3:11000, Forex:11001) com health check e auto-start |
| `mt5_core.py` | `process_mt5_data()`: fetch MT5 → DataFrame → indicadores → INSERT market_candles |
| `run_b3.py` | Robo B3: loop 60s, 6 timeframes, WINFUT/WDOFUT/PETR4/VALE3/ITUB3. Gerenciado por `smc-b3-robot.service` (user systemd, Restart=always) |
| `run_forex.py` | Robo Forex: loop 60s, 6 timeframes, XAUUSDm/BTCUSDm/EURUSDm/USDJPYm/etc. Gerenciado por `smc-forex-robot.service` (system systemd, Restart=always) |
| `database.py` | MySQL persistence (market_candles, analysis_history, shadow tables) |
| `dashboard_shadow/` | FastAPI backend (:8008) + React frontend + Dash Plotly (:8050) |
| `collector_manager.py` | CandleWatcher: 6 ativos × 5 TFs (M2/M5/M15/H4/D1), poll 30s, debounce 5min |
| `mt5_connection.py` | `_load_config()`: import infra.config_manager com fallback legado |
| `vps_monitor.py` | Coleta metrica VPS (CPU/RAM/Disk/Load/Net/Uptime/Services) via /proc/, envia POST HMAC ao site a cada 30s |
| `vps-monitor.service` | systemd unit (Restart=always) para vps_monitor.py — Python le .env direto, sem EnvironmentFile |

### 4.9 SignalResearchV2 — Pipeline de Pesquisa de Sinais

**Localizacao:** `tools/run_phase6_nested_wf.py` + `tools/update_phase6_report.py`

```
┌────────────────────────────────────────────────────────────────────┐
│ SignalResearchV2 — Candidate C Nested Walk-Forward (Fase 6)         │
│                                                                     │
│ Objetivo: Avaliar candidato C (7 params, 864 combos, 200 trials)   │
│ contra Baseline B_V3 via nested walk-forward (8 outer folds)        │
│                                                                     │
│ ┌─ SEARCH SPACE (7 parametros) ───────────────────────────────────┐ │
│ │ stop_buffer_atr:     0.10, 0.15, 0.20, 0.25                    │ │
│ │ max_stop_atr:        2.0, 2.5, 3.0                              │ │
│ │ expiry_candles_m5:   6, 9, 12                                   │ │
│ │ session_only:        true, false                                │ │
│ │ require_htf_for_tp3: true, false                                │ │
│ │ breakeven_after_tp1: true, false                                │ │
│ │ cooldown_bars_m5:    3, 5, 8                                    │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ EXECUCAO ──────────────────────────────────────────────────────┐ │
│ │ 200 trials × 8 outer folds = 1.600 backtest units               │ │
│ │ ~90s por fold (~4.400 candles M5, janela ~2 meses)              │ │
│ │ Multi-TF: D1 + H4 (tendencia) + M15 (stop ancora) + M5 (setup) │ │
│ │ STOP_FIRST_CONSERVATIVE, WINFUT, custos padrao                  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─ CHECKPOINT/RESUME ────────────────────────────────────────────┐ │
│ │ storage/phase6_checkpoints/{run_id}/checkpoint.json             │ │
│ │ storage/phase6_nested_wf.lock                                   │ │
│ │ scripts/monitor_phase6.sh (cron 3h → update_phase6_report.py)  │ │
│ │ Relatorio: docs_geral/Sistema VPS/Plano/Plano Ativo/           │ │
│ │            BASELINE_FASE_6_1_EXECUCAO_LONGA.md                  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ Guardrails: shadow_only, research_only, can_promote_trade=false,    │
│ deterministic=true, anti_lookahead=true                              │
└────────────────────────────────────────────────────────────────────┘
```

---

## 5. MTF — Multi-Timeframe (Hierarquia)

```
D1/H4   (contexto)  → ONDE estamos no mapa. Define vies. NAO gera entrada.
M15     (estrutura) → tendencia operacional + zonas que importam. PARA ONDE ir.
M5      (base/setup)→ onde a zona e validada. O QUE operar.
M2      (gatilho)   → timing fino da entrada. NAO entra no voto de direcao.
```

### 5.1 Camada 1 — Filtro de Vies HTF

```python
htf_bias = compute_htf_bias(D1, H4)  # structure/sma/contextual
if direction != htf_bias:
    htf_conflict_mode == "block"  → has_operation=False (CONTRA_HTF)
    htf_conflict_mode == "demote" → cap confianca BAIXA, flag CONTRA_HTF
htf_bias == NEUTRO → cap MEDIA (nunca ALTA sem concordancia)
```

### 5.2 Camada 2 — Confluencia Ponderada

```python
pesos_tf = {"H4": 0.40, "M15": 0.35, "M5": 0.25}
# M5 sozinho NUNCA vira a direcao do H4
# weighted_direction = argmax(soma ponderada)
# tf_agreement = todos TFs concordam na direcao
# Alignment combinado sobe +0.10 com concordancia (monotonico)
```

### 5.3 Camada 3 — Alinhamento Espacial

```python
# Zona M5 sobreposta a zona M15/H4 com overlap >= 0.30:
zona.quality_score += mtf_zone_boost (default +15)
zona.mtf_aligned = True
# Confianca +1 nivel quando: a favor HTF + zona alinhada
```

---

## 6. Tabelas do Sistema

### 6.1 Tabelas Oficiais (VPS, read-write pelos robos)

| Tabela | Conteudo |
|--------|----------|
| `market_candles` | OHLCV + EMA20/EMA200/RSI/ATR (todos timeframes, 11 ativos) |
| `assets` | Mapeamento id↔ticker↔alias (11 ativos) |
| `analysis_history` | Estudos historicos (legado, nao usado pelo V2) |

### 6.2 Tabelas Shadow (VPS, so leitura para o estudo)

| Tabela | Conteudo |
|--------|----------|
| `technical_engine_smc_v2_runs_shadow` | Run metadata (run_id, symbol, timeframe, params, timestamps) |
| `technical_engine_smc_v2_fvg_shadow` | Fair Value Gaps |
| `technical_engine_smc_v2_order_blocks_shadow` | Order Blocks (com quality scoring) |
| `technical_engine_smc_v2_bos_choch_shadow` | BOS/CHOCH structural breaks |
| `technical_engine_smc_v2_liquidity_shadow` | Liquidity levels |
| `technical_engine_smc_v2_swings_shadow` | Swing points |
| `technical_engine_smc_v2_sessions_shadow` | Session markers |
| `technical_engine_smc_v2_retracements_shadow` | Retracement percentages |
| `technical_engine_smc_v2_previous_high_low_shadow` | PDH/PDL |
| `technical_engine_smc_v2_visual_overlays_shadow` | Visual overlays |
| `technical_engine_study_replay_runs_shadow` | Replay runs |
| `technical_engine_study_replay_samples_shadow` | Replay samples (com truth_envelope_v2) |
| `technical_engine_study_replay_metrics_shadow` | Replay metrics |
| `technical_engine_elliott_shadow` | Elliott ctx_json por (symbol, timeframe) — UPSERT via TRIGGER 4b |
| `technical_engine_wyckoff_shadow` | Wyckoff ctx_json por (symbol, timeframe) — UPSERT via TRIGGER 4b |

---

## 7. API Endpoints (FastAPI :8008)

| Endpoint | Descricao |
|----------|-----------|
| `GET /api/smc-engine-v2/state` | SMC V2 persisted (freshness check: `MAX(timestamp)`) |
| `GET /api/dashboard/multi-layer-state` | SMC + Elliott + Wyckoff + Contextual + Study + Forward Study (6L) |
| `GET /api/study-pipeline-shadow/state` | Study pipeline shadow |
| `GET /api/study-pipeline-shadow/replay-summary` | Replay validation gate |
| `GET /api/study-pipeline-shadow/candidate-backtest` | Candidate backtest |
| `WS /ws/dashboard` | WebSocket com `snapshot_update` em tempo real (2s polling) |

---

## 8. Guardrails (invariantes)

```text
shadow_only=True                → Nunca escrever em tabelas oficiais
can_promote_trade=False         → Nunca promover sinal operacional
apply_automatically=False       → Nunca aplicar config automaticamente
llm_decision_used=False         → LLM e redatora, nunca motor
smc_recomputed=False            → SMC consumido por run_id, nunca recalculado
smc_recomputed_in_frontend=False → Frontend so renderiza, nunca recalcula
anti_lookahead=True             → exclude_last_open_candle, available_at < study_time
deterministico=True             → Mesmo input = mesmo output (SHA-256, replay auditavel)
multi_asset=True                → 6 ativos, B3+Forex, pipeline identico
probabilidade_proibida=True     → "Taxa historica de alcance", nunca "probabilidade"
```

---

## 9. Metricas (2026-06-20)

| Metrica | Valor |
|---------|-------|
| Testes Python total | 2522 passed, 3 failed (preexistentes), 3 skipped |
| Testes Laravel (S24) | 33 escritos (PHP indisponivel) |
| Ativos | 6 (WINFUT, WDOFUT, PETR4, VALE3, XAUUSDm, BTCUSDm) |
| Robos de coleta | 2 (run_b3.py, run_forex.py) |
| Timeframes | 7 (M1, M2, M5, M15, H1, H4, D1) |
| PRONTO rate (WINFUT M5, 500 samples) | 57.8% |
| Media PRONTO (6 ativos) | ~50% |
| Sinais Backtest S21 | 535k candles, 155k sinais, 35k setups (6 ativos, ate 24 meses) |
| Win rate setups (S21) | 47-50% |
| Sinais IMINENTE+NA_ZONA/dia | ~1.5/dia (todos ativos) |
| Servicos systemd | 11 ativos (4 scanner/notifier/forex + 4 MT5 + 2 bridge + 1 vps-monitor) + 2 robos coleta |
| VPS Monitor | vps_monitor.py → POST 30s HMAC → Cache::put (TTL 5min). CPU/RAM/Disk/Load/Net/Uptime/Services |
| VPS Monitor status | RUNNING (systemd vps-monitor.service, Restart=always, user bimaq) |
| Site frontend VPS | AdminSystemHealth unificada: polling 15s /admin/vps-metrics + /sync/health |
| Endpoints Laravel | 16 controllers (14 + 2 VPS metrics: Internal + Admin) |
| Migrations Laravel | 18 migrations, 25+ tabelas |
| Site | maximustrade.com.br — Laravel 12 + React 19/Tailwind 4 |
| Site endpoints (total) | 42+ rotas (auth, plans, market, mobile API, sync, scanner, admin, webhooks, VPS) |
| Site tabelas MySQL | 25+ (comercial, mercado, alertas, logs, auth) |
| Site gateways pagamento | 12 (Hotmart, Kiwify, Stripe, MercadoPago, PayPal, etc.) |
| CandleWatcher cobertura | 6 ativos × 5 TFs = 30 pares monitorados (M2/M5/M15/H4/D1) |
| App Android | br.com.maximustrade.signals — Kotlin 2.1.0 + Compose Multiplatform 1.7.3 |
| App SDK | Min 24 (Android 7.0) / Target 35 (Android 15) |
| App arquivos Kotlin | 57 (24 commonMain + 6 androidMain + domain/data/features/core) |
| App telas | 8 (Login, ForgotPassword, Dashboard, OpportunityList, OpportunityDetail, History, Preferences, Account) |
| App push | Firebase FCM v33.9.0, deep link maximus://opportunity/{id}, channel opportunity_alerts |
| App DI | Koin 4.0.0 (commonModule 8 ViewModels + androidModule 3 platform impls) |
| App navegacao | Jetpack Navigation Compose 2.8.0, 8 rotas, suporte deep link |
| Site backend | Laravel 12 + PHP 8.2+ — 16 controllers, 4 middleware, 26 models, 42+ rotas API |
| Site frontend | React 19 + TypeScript + Vite 8 + Tailwind 4 — 19 paginas, SMC Canvas overlay, VPS sparklines |
| Site auth | Sanctum token (mt_ prefix, 30d) + 2FA TOTP (spomky-labs/otphp) + HMAC dual (scanner + bridge) |
| Site webhooks | 12 gateways pagamento — endpoint unico POST /api/webhooks/{provider} |
| Site FCM | Firebase HTTP v1 — OAuth2 JWT → sendToDevice → push_logs |
| Latencia SMC pipeline | 3-5 segundos |
| SignalResearchV2 | Candidate C Nested Walk-Forward (Fase 6.1) — 200 trials × 8 folds = 1600 units |
| SignalResearchV2 status | 🟢 COMPLETED_EXPLORATORY — 179/200 trials, 1613 folds, 0 rejeicoes, 0 falhas, 100% folds PF>1 |
| SignalResearchV2 resultado | Champion TRIAL_0110 (PF=253) reprovado em robustez (PF_LCB_95=-45). Candidato robusto: TRIAL_0028 (PF=128, 317 trades, PF_min=1.6, 0 folds quebrados). |
| SignalResearchV2 proximo | FASE 7 — Stress test TRIAL_0028 com dados novos + backtest confirmação + DB persistence |
| Fases concluidas | S1→S24 + Plano 1-2-3 + Fase 5 (Seguranca) + Fase 6 (E2E) + VPS Monitor + Fase 6.1 (SignalResearchV2 em execucao) |
| Repositorios GitHub | 5 (smc-trader-system-7-local, maximus-trader-web, maximus-trader-android, smc-trader-docs, smc-mt5-infra) |
| Script sync | sync_all.sh (raiz do workspace, 1 comando sincroniza todos os repos) |
| docs_geral | Repo proprio (smc-trader-docs), 122 arquivos versionados, .gitignore anti-secrets |

---

## 10. Estrutura de Diretorios (2026-06-20)

```
SMC_Trader_System_7_0/            ← raiz do workspace (5 repos GitHub + 1 script sync)
├── sync_all.sh                   ← script sincroniza todos os repos (commit+push)
│
├── docs_geral/                   ← Repo: AndreRambo/smc-trader-docs (main)
│   ├── ARQUITETURA_OFICIAL.md    este arquivo (cobre os 3 projetos)
│   ├── .gitignore                (protege .env.bak, settings.json.bak, secrets)
│   │
│   ├── Site/                     ← maximustrade.com.br
│   │   ├── Plano/
│   │   │   ├── Plano Ativo/
│   │   │   └── Plano Concluidos/ (4 planos: Fase1-2, MaximusTrader, Fix Evidencias, Microservicos)
│   │   ├── Relatorios/           (2 relatorios: Plano2, Notificacao)
│   │   └── baseline_backups/     (migrations Laravel, .env example, composer/package.json)
│   │
│   ├── Aplicativo Android/       ← App Android (MaximusTradeSignals)
│   │   ├── Plano/
│   │   │   ├── Plano Ativo/
│   │   │   └── Plano Concluidos/ (1 plano: Plano3 AppAndroid)
│   │   └── Relatorios/           (2 relatorios: Plano3, Mudancas Chart)
│   │
│   └── Sistema VPS/              ← SMC Trader System (motor Python + infra)
│       ├── Plano/
│       │   ├── Plano Ativo/      (1 ativo: Fase 6.1 Candidate C Nested WF)
│       │   └── Plano Concluidos/ (7 planos: Backtest, Backup, Otimizacao, E2E, Signal Candidate, etc.)
│       ├── Relatorios/
│       │   ├── SignalCandidate/  Fases 1-8 (11 arquivos, concluido)
│       │   ├── SignalResearchV2/ Fases 0-6 (34 arquivos, Fase 6.1 em execucao)
│       │   ├── Backtest/         (2 arquivos)
│       │   ├── Soak/             soak_metrics CSV
│       │   └── (11 relatorios gerais: baseline, decisao, E2E, seguranca, etc.)
│       └── baseline_backups/     (systemd services, .env example, requirements.txt)
│
├── SMC_Trader_System 7.0/        ← Repo: AndreRambo/smc-trader-system-7-local
│                                   Branch: feature/phase6-candidate-c-nested-walk-forward
├── technical_engine/           ← motor tecnico completo
│   ├── smc_engine_v2/          STABLE_FROZEN_V2 (164 testes)
│   ├── study_gateway/          Study + Risk Management + Forward (123 testes)
│   ├── opportunity_scanner/    Scanner S1-S21 (306 testes)
│   ├── elliott/                Elliott Wave context + sanity
│   ├── wyckoff/                Wyckoff context + sanity
│   ├── elliott_wyckoff_shadow/ Persistence EW → DB shadow (upsert por symbol+TF)
│   ├── contextual_market_profile/ Volatility/session/regime/HTF
│   ├── confluence/             Confluence V2
│   ├── persistence/            Shadow persistence helpers
│   ├── shadow_database/        Shadow DB store
│   ├── study_pipeline_shadow/  Pipeline shadow + replay
│   ├── asset_resolver.py       Ticker/alias centralizado
│   ├── collector_health.py     Health TF-aware
│   └── ...                     (demais modulos ativos)
├── dashboard_shadow/           FastAPI :8008 + React frontend + Dash :8050
├── infra/                      database.py, mt5_core.py, indicators.py, sync_v2.py
│                               config_manager.py, robot_singleton.py, robot_health.py
├── systemd/                    Servicos systemd ativos (10 servicos)
├── tests/                      Suite de testes (2522+ testes)
├── tools/                      Scripts de auditoria e execucao
├── scripts/                    Scripts operacionais e diagnostico
├── deploy/                     Scripts de deploy
├── docs/                       Documentacao
├── config/                     Configuracao
├── database/                   Migrations SQL (shadow tables)
├── migrations/                 Migrations Python (shadow tables)
├── storage/                    Storage ativo
├── runtime/                    Logs e snapshots do motor
├── backups/                    Backups de codigo
├── mt5_connection.py           Dual-port RPyC bridge B3:11000/Forex:11001
├── run_b3.py                   Robo B3 (WINFUT/WDOFUT/PETR4/VALE3/ITUB3)
├── run_forex.py                Robo Forex (XAUUSDm/BTCUSDm/EURUSDm/etc)
├── start_bridges.sh            Inicializa RPyC bridges
├── start.sh / start_tunnel.sh  Startup scripts
├── cleanup_vps.sh              Manutencao VPS
├── diagnostic.sh               Diagnostico do sistema
├── requirements.txt            Dependencias Python
└── .env / settings.json        Configuracao de ambiente

AppAndroid/MaximusTrader/        ← Repo: AndreRambo/maximus-trader-android (main)
                                  App Android nativo (57 arquivos Kotlin)
├── build.gradle.kts            Root build (plugins)
├── settings.gradle.kts         Project: MaximusTradeSignals
├── gradle/libs.versions.toml   Version catalog
├── composeApp/                 Modulo KMP principal
│   ├── build.gradle.kts        Kotlin 2.1.0, Compose 1.7.3, Ktor 3.0.3, FCM 33.9.0
│   └── src/
│       ├── commonMain/kotlin/br/com/maximustrade/signals/
│       │   ├── App.kt                 Root: NavHost 8 rotas, Koin init
│       │   ├── core/
│       │   │   ├── api/ApiClient.kt + AppConfig.kt
│       │   │   ├── auth/AuthUtils.kt
│       │   │   ├── deeplink/DeepLinkHandler.kt
│       │   │   ├── design/MaximusColors.kt, MaximusTheme.kt
│       │   │   ├── di/Modules.kt (Koin commonModule)
│       │   │   ├── notifications/FcmOpportunityPayload.kt (15 campos)
│       │   │   │              NotificationService.kt (interface)
│       │   │   ├── storage/DataStoreProvider.kt, TokenStorage.kt
│       │   │   └── utils/DateTimeUtils.kt
│       │   ├── data/
│       │   │   ├── dto/AuthDto.kt, DeviceDto.kt, PreferenceDto.kt
│       │   │   ├── mapper/OpportunityMapper.kt
│       │   │   ├── remote/OpportunityRemoteDataSource.kt,
│       │   │   │        PreferenceRemoteDataSource.kt
│       │   │   └── repository/AuthRepositoryImpl.kt,
│       │   │                  DeviceRepositoryImpl.kt,
│       │   │                  OpportunityRepositoryImpl.kt,
│       │   │                  PreferencesRepositoryImpl.kt
│       │   ├── domain/
│       │   │   ├── model/AuthModels.kt (LoginRequest, LoginResult sealed, UserDto)
│       │   │   │       OpportunityModels.kt (OpportunityDto 30 campos,
│       │   │   │        PaginatedResponse, Direction enum, RadarState enum)
│       │   │   │       PreferenceModels.kt (UserPreferences 11 campos)
│       │   │   ├── repository/AuthRepository.kt, DeviceRepository.kt,
│       │   │   │            OpportunityRepository.kt, PreferencesRepository.kt
│       │   │   └── usecase/GetHistoryUseCase.kt
│       │   └── features/
│       │       ├── account/AccountScreen.kt, AccountViewModel.kt
│       │       ├── auth/LoginScreen.kt, LoginViewModel.kt,
│       │       │        ForgotPasswordScreen.kt, ForgotPasswordViewModel.kt
│       │       ├── dashboard/DashboardScreen.kt, DashboardViewModel.kt
│       │       ├── history/HistoryScreen.kt, HistoryViewModel.kt
│       │       ├── opportunities/OpportunityListScreen.kt,
│       │       │              OpportunityListViewModel.kt,
│       │       │              OpportunityCard.kt (reusable),
│       │       │              OpportunityDetailScreen.kt,
│       │       │              OpportunityDetailViewModel.kt
│       │       └── preferences/PreferencesScreen.kt, PreferencesViewModel.kt
│       └── androidMain/kotlin/br/com/maximustrade/signals/
│           ├── MainActivity.kt (Compose entry, deep link parse, FCM token)
│           ├── MainApplication.kt (Koin init: common + android modules)
│           ├── firebase/MaximusFirebaseMessagingService.kt
│           │          AndroidNotificationService.kt
│           └── storage/AndroidSecureTokenStorage.kt
│                      AndroidDataStoreProvider.kt

MaximusTrader/                  ← Repo: AndreRambo/maximus-trader-web (main)
                                Site maximustrade.com.br
├── backend/                    Laravel 12 + PHP 8.2+
│   ├── app/Http/Controllers/Api/ (14 controllers)
│   │   AuthController, AdminController, PlanController,
│   │   MarketDataController, ScannerAlertController, SyncController,
│   │   SyncHealthController, MobileOpportunityController,
│   │   MobileDeviceController, MobilePreferenceController,
│   │   WebhookController, AlertController, IndicatorController,
│   │   FcmTestController
│   ├── app/Http/Middleware/
│   │   VerifyScannerHmac, VerifySyncHmac, EnforcePlanLimits, Cors
│   ├── app/Services/
│   │   FirebasePushService (FCM HTTP v1: OAuth2 JWT → token → send)
│   │   Webhooks/ (AbstractProvider, Hotmart, Kiwify, Stripe,
│   │              MercadoPago, PayPal, GenericProvider)
│   ├── app/Jobs/ SendOpportunityPushNotification
│   ├── app/Models/ (26 models)
│   │   User, Plan, License, Product, Purchase, Subscription,
│   │   SyncAsset, SyncCandle, SyncZone, SyncStudy, SyncElliottWave,
│   │   SyncWyckoffPhase, SyncWyckoffEvent, SyncHealthLog,
│   │   ScannerAlert, Opportunity, UserDevice,
│   │   NotificationPreference, PushLog, Alert, Indicator,
│   │   Configuration, AccessLog, AuditLog, WebhookLog
│   ├── app/Console/Commands/ FcmTest, RegisterTestDevice
│   ├── database/migrations/ (18 migrations → 25+ tabelas)
│   ├── routes/api.php (40+ endpoints)
│   ├── config/services.php webhooks.php sanctum.php permission.php
│   └── .env (FCM_ENABLED, FCM_DRY_RUN, FIREBASE_PROJECT_ID, etc.)
├── frontend/                   React 19 + TypeScript + Vite 8 + Tailwind 4
│   ├── src/
│   │   main.tsx, App.tsx (BrowserRouter + AuthProvider), index.css
│   │   contexts/AuthContext.tsx (login, 2FA, logout state)
│   │   lib/api.ts (fetch wrapper com Bearer token)
│   │   hooks/useMarketWebSocket.ts, useRealMarketData.ts
│   │   components/
│   │   │   CandlestickChart.tsx (Lightweight Charts v5)
│   │   │   PlotlyCandlestickChart.tsx (DEPRECATED)
│   │   │   BackgroundEffects.tsx (TickerTape, MouseGlow, Grid)
│   │   │   chart/smc/SmcSeriesPrimitive.ts (Canvas renderer SMC)
│   │   │          SmcPaneRenderer.ts, SmcPaneView.ts, smcTypes.ts,
│   │   │          smcStyle.ts, smcNormalize.ts, smcVisibility.ts,
│   │   │          smcLabelCollision.ts
│   │   └── pages/
│   │       Landing, Login, Register, Dashboard, AdminSystemHealth,
│   │       ChartPage, WatchlistPage, ReplayPage, AlertasPage,
│   │       IndicadoresPage, AdminPlanosPage, AdminUsuariosPage,
│   │       AdminLicencasPage, AdminVendasPage, AdminProdutosPage,
│   │       AdminConfigPage
│   ├── vite.config.ts (proxy /api → localhost:8000)
│   └── package.json (React 19, lightweight-charts 5.2, plotly.js 3.6,
│       socket.io-client 4.8, react-router-dom 7.16, Tailwind 4.3)
└── MAXIMUS-DESIGN-SYSTEM/      Design system
    ├── index.html, MAXIMUS-DESIGN-SYSTEM.html
    ├── assets/ (Lucide icons, fonts DM Sans/DM Mono/Bebas Neue,
    │           brand assets .webp)
    └── Design tokens: deep-void #0A0A10, obsidian #12101E,
        midnight #1A1530, brand-primary #7B2FF7,
        text-primary #F0EEFF, signal-buy #3DDC84,
        signal-sell #FF5F6D, signal-alert #F5A623, signal-info #00CFFF

SMC_Trader_System_legado/       ← legado (nao usar em producao)
  Conteudo: motores V1 (smc_engine.py, trade_setup_engine.py, wyckoff_engine.py),
  app.py (app Streamlit antigo), smc_zone_*.py (zonas V1), ui/, llm/, smc/,
  study/, paper_trading/, backtesting/, workers/, services/, training/,
  ai_agent.py, prompts_smc.py, terminal_smc_*.py, e demais arquivos pre-V2.
  Total: ~129 itens historicos para referencia apenas.

MT5Backup/                      ← Repo: AndreRambo/smc-mt5-infra (main)
  Backups e scripts MT5 infra (Wine, RPyC, configs de terminal)
```

---

## 11. Decisoes Arquiteturais

| Decisao | Data | Justificativa |
|---------|------|---------------|
| SMC V2 como fonte canonica | 2026-05 | Backtest-calibrado, 164 testes, STABLE_FROZEN_V2 |
| LLM e redatora, nunca motor | 2026-05 | Hash SHA-256 + campos proibidos + validacao de override |
| Nao recalcular SMC no estudo | 2026-05 | Consome por run_id das tabelas shadow |
| Nao adotar push notification | 2026-05 | MAX(timestamp) custa < 1ms; push adiciona IPC/fila/retry sem beneficio |
| MTF hierarquico | 2026-05 | Peso DECRESCENTE (H4>M15>M5); M5 nunca vira H4 |
| Taxa historica, nao probabilidade | 2026-05 | Walk-forward deterministico + expectancy_R auditavel |
| Calibracao config-driven | 2026-05 | OBQualityConfig/BPRQualityConfig, nao hardcoded nos detectores |
| Swings_df computado 1x | 2026-05 | Compartilhado entre OB/BOS/CHOCH/Liquidity/Retracements |
| FVG mitigation vetorizada | 2026-05 | numpy arrays (np.argmax), nao mais O(n^2) com df.loc[j] |
| raise_on_error no pipeline | 2026-05 | CI propaga excecao; runtime vira warning em diagnostics |
| latest_candle_time no persist | 2026-05-31 | Injetado automaticamente do ultimo candle OHLC |
| Forward gateway (6L) | 2026-05-31 | Loop ONCE/LOOP, idempotencia por candle, dead-man's switch |
| Risk Management V2 | 2026-05-31 | OperationalPlanV2 deterministico + MTF 3 camadas + hit rates |
| Opportunity Scanner | 2026-06 | 10+ gates deterministicos, dedup 15min, ATR-based proximity |
| Window invalidation (S18K) | 2026-06 | Qualquer candle M1 desde plan.lct que viola stop → invalida |
| Freshness por candle close (S18E) | 2026-06 | Age = now - (candle_time + timeframe_duration) |
| Collector health TF-aware (S18G) | 2026-06 | M1/M5 critico, H4 contexto; mercado_fechado nao causa down |
| Backtest multiativo (S21) | 2026-06 | 535k candles, numpy searchsorted O(1), walk-forward sem lookahead |
| Backend mobile Laravel (S24) | 2026-06-03 | 14 endpoints, 11 tabelas, Sanctum + HMAC duplo (scanner/bridge) |
| Ponte scanner→site (S24) | 2026-06-03 | HTTP POST HMAC com retry, idempotencia, graceful degradation |
| Firebase/FCM dry-run (S24) | 2026-06-03 | Default seguro; push so para IMINENTE+NA_ZONA (~1.5/dia) |
| Site maximustrade.com.br | 2026-06-03 | Laravel 12 + React/Tailwind, secao de vendas oculta |
| Reorganizacao legado | 2026-06-09 | ~129 itens movidos para SMC_Trader_System_legado/ (motores V1, UI antiga, scripts antigos) |
| Fix imports robos coleta | 2026-06-09 | run_b3.py e run_forex.py migrados para infra.*; config_manager, robot_singleton, robot_health movidos para infra/; imports V1 em infra/database.py e infra/mt5_core.py envolvidos em try/except (_LEGACY_AVAILABLE=False) |
| 216/GROUP systemd user (pre-existente) | 2026-06-09 | Erro pre-existente (34k+ restarts antes da sessao). Causa: permissao de grupo em servicos user-space no VPS. Nao relacionado aos imports. Robos funcionam corretamente via Python direto. |
| B3 service user-level fix (216/GROUP + 203/EXEC) | 2026-06-09 | smc-b3-robot.service falhava com 216/GROUP (User=bimaq explícito bloqueado no VPS) e 203/EXEC (espaco no path ExecStart). Fix: remover linha User=bimaq do service file; usar symlink /home/bimaq/projetos/smc_trader_system (sem espacos) no WorkingDirectory e ExecStart. Mesmo padrao do smc-forex-robot.service (system-level). Ambos ativos 24h com Restart=always. |
| App Kotlin Multiplatform | 2026-06 | Compose Multiplatform 1.7.3 + Ktor 3.0.3 (nao Retrofit). Koin para DI, DataStore+Crypto para token seguro. Preparado para iOS futuro sem reescrita. |
| App autenticacao Sanctum | 2026-06 | Bearer token persistido em storage seguro (AndroidX Security Crypto). 2FA TOTP na tela de login. |
| Site React separado do backend | 2026-06 | Frontend React 19 + Vite em subpasta separada do Laravel. Permite deploy independente e CI/CD separado. |
| Site multi-gateway pagamentos | 2026-06 | WebhookController unico, handler por provedor. Suporte Hotmart/Kiwify (principais BR) + internacionais (Stripe/PayPal). |
| CandleWatcher 6 ativos × 5 TFs | 2026-06-09 | collector_manager.py expandido de WINFUT×4TFs para 6 ativos×5TFs (incl. D1). _run_recalc_batch() sequencial por asset. tf_map do persist script atualizado com D1. _WATCHER_RECALC_ENABLED=False: recalc desativado no watcher (TRIGGER 4 assume). |
| Fix TRIGGER 4 V2 pipeline | 2026-06-09 | TRIGGER 4 movido para dentro do bloco rows_added>0 (nao roda em ciclos sem candle novo). M1 removido de V2_TIMEFRAMES (sem zonas SMC em 1min, freq alta). LIMIT adicionado ao fetch de candles no sync_v2 (evita full-table scan em 75k+ candles). M1 nao sincroniza com o site (timeframe!='1min' guard). |
| Elliott/Wyckoff shadow persistence | 2026-06-09 | TRIGGER 4b: run_ew_pipeline_and_persist() persiste Elliott+Wyckoff para technical_engine_elliott_shadow / technical_engine_wyckoff_shadow apos cada novo candle (todos os V2_TIMEFRAMES). Dashboard lê da shadow (freshness check <120s). Se shadow stale ou ausente: fallback para compute em runtime. Economiza ~30 recalculos/min no dashboard WebSocket. Configs calibradas por ativo (WINFUT: pivot_left=4, lookback=150, volume_spike=1.15, etc.) duplicadas em sync_v2.py para evitar import circular com dashboard_shadow/. |
| DB ticker vs scanner symbol (Forex) | 2026-06-09 | DB usa "GOLD (XAUUSD)"/"BTCUSD" como ticker mas persist/watcher operam por asset_id numerico — transparente ao scanner que usa "XAUUSDm"/"BTCUSDm". |
| smc_trader_system symlink | 2026-06-09 | /home/bimaq/projetos/smc_trader_system → SMC_Trader_System_7_0/SMC_Trader_System 7.0 (symlink). Servicos system-level e user-level apontam para o mesmo diretorio. |
| Fix timezone freshness V2 pipeline | 2026-06-09 | sync_v2.py: freshness check comparava latest_candle (BRT) com created_at (CEST) — diferenca de 5h fazia needs_pipeline=False sempre. Fix: comparar com parameters_json.latest_candle_time (mesmo BRT). Robos B3/Forex reiniciados. |
| Fix uirevision chart zoom | 2026-06-09 | figure_builder.py: uirevision fixo preservava zoom antigo — novos candles ficavam fora da tela. Fix: incluir ultimo candle no uirevision para resetar ao chegarem dados novos. |
| Fix timeframe alias persistence | 2026-06-09 | persistence.py load_latest_smc_engine_v2_state: query exata WHERE timeframe='M15' nao encontrava runs salvos com '15min'. Fix: IN (%s, %s) com alias map M5↔5min, M15↔15min, etc. |
| Fix timestamps FVG/OB/zonas SMC | 2026-06-09 | sync_v2.py chamava run_smc_engine_v2_local() sem timestamps= — todos origin_at/display_from/display_to ficavam NULL em raw_json. Resultado: figure_builder nao conseguia posicionar zonas no eixo X e nao renderizava nenhum retangulo. Fix: salvar ts_series = pd.Series(ohlc['timestamp'].values) antes do drop(columns=['timestamp']) e passar timestamps=ts_series ao pipeline. persistence.py: FvgV2/ObV2 reconstruidos do raw_json agora incluem display_from/display_to/mitigated_at; candles carregados ANTES da geracao de visual_overlays (era pos — ohlc_for_overlay ficava vazio). |
| Fix Forex robot modulo em cache | 2026-06-10 | smc-forex-robot.service iniciado as 21:24 carregou sync_v2.py sem o fix (arquivo modificado as 21:46). Modulo ficou em cache no processo — todos os FVG/OB Forex inseridos entre 21:24 e 01:00 tinham display_from=NULL. Fix: reiniciar o servico apos qualquer alteracao em infra/sync_v2.py. Regra: modificacoes em modulos importados por servicos systemd exigem restart do servico. |
| Backfill completo SMC V2 3 meses | 2026-06-10 | tools/full_backfill_v2.py: TRUNCATE das 10 tabelas smc_v2_*_shadow + recalculo com janela de 3 meses para todos os ativos (B3: 20k candles 2min / Forex 24/5: 50k / Crypto 24/7: 65k) × 5 TFs (2min, 5min, 15min, 4h, 1d — excluindo 1min). Forex robot parado durante backfill (inserts concorrentes evitados), reiniciado ao final. Todos os assets: WINFUT, WDOFUT, PETR4, VALE3, ITUB3, GOLD, BTCUSD, USDJPY, EURUSD, SILVER, ETHUSD. |
| VPS Monitor — metricas em tempo real | 2026-06-19 | VPS push (Python stdlib, /proc/) → POST HMAC 30s → Laravel Cache::put (TTL 5min). Admin le GET /admin/vps-metrics polling 15s. Sem inbound connections, sem psutil, sem migration. Sparklines SVG inline (sem lib chart externa). |
| VPS Monitor — Python le .env direto | 2026-06-19 | systemd EnvironmentFile falhava com caracteres especiais (`)`, `!`, `@`) no .env do SMC Trader. Fix: `_load_dotenv()` em Python faz parse direto, service simplificado para `ExecStart=/usr/bin/python3` sem EnvironmentFile. |
| Site AdminSystemHealth unificada | 2026-06-19 | Pagina "Saude" e "VPS Monitor" mescladas em uma unica: AdminSystemHealth.tsx. Fetch paralelo /sync/health + /admin/vps-metrics. Cards CPU/RAM/Disk/Load, sparklines SVG, servicos pgrep, rede, uptime, DB error alert, debug raw JSON. |
| SignalResearchV2 — Candidate C Nested WF | 2026-06-19 | 200 trials × 8 folds = 1.600 backtest units. 7 parametros (stop_buffer, max_stop, expiry, session_only, htf_for_tp3, breakeven, cooldown). 3 bugs criticos corrigidos (params ignorados, bar_index=0, trackers compartilhados). Execucao via tmux phase6-wf, checkpoint/resume, relatorio auto-update via cron. |
| docs_geral consolidado e reorganizado | 2026-06-20 | Duas pastas docs_geral unificadas em `/SMC_Trader_System_7_0/docs_geral/`. Estrutura reorganizada por projeto: Site/ (maximustrade.com.br), Sistema VPS/ (motor Python + infra), Aplicativo Android/ (MaximusTradeSignals). Cada um com Plano/{Ativo,Concluidos} + Relatorios + baseline_backups. ARQUITETURA_OFICIAL.md na raiz cobre os 3 projetos. |
| docs_geral repo GitHub independente | 2026-06-20 | docs_geral extraido do repo principal em seu proprio repo `AndreRambo/smc-trader-docs` (main, publico). .gitignore protege .env.bak, settings.json.bak, secrets_locations.txt. Commit inicial: 122 arquivos, 33k linhas. |
| 5 repos GitHub mapeados + sync_all.sh | 2026-06-20 | Workspace `SMC_Trader_System_7_0/` contem 5 repos: smc-trader-system-7-local (motor, branch feature), maximus-trader-web (site, main), maximus-trader-android (app, main), smc-trader-docs (docs, main), smc-mt5-infra (MT5 backup, main). Script `sync_all.sh` na raiz faz commit+push de todos com uma unica mensagem. |
| Per-type SMC renderers | 2026-06-20 | 7 pipelines independentes substituem o SmcPaneRenderer unificado: cada tipo (FVG, OB, BPR, BOS, CHOCH, LIQ, SWING) tem seu proprio normalizer, renderer Canvas, e ISeriesPrimitive. Debug isolado por tipo ([FVG:draw], [BOS:draw]), budget independente, toggles individuais na toolbar do admin. 24 novos arquivos frontend. |
| Replay com dados reais | 2026-06-20 | /admin/replay substitui dados sinteticos por dados historicos reais. ReplayChart usa lightweight-charts + mesmas per-type primitives do /admin/grafico. Filtro client-side de data, controles Play/Pause/Speed/Seek, candle series atualizada via setData() sem remount do chart. |
| 1H adicionado ao pipeline | 2026-06-20 | TIMEFRAME_H1 adicionado em run_b3.py (import + loop), V2_TIMEFRAMES, DEFAULT_RELOAD_TIMEFRAMES, _resolve_tf_strings, _CANDLE_LIMITS, _TF_ALT, resync_winfut_full.py. 1H candles coletados e processados com pipeline SMC V2 + Elliott/Wyckoff. |
| Replicacao 9 tabelas shadow no Hostinger | 2026-06-20 | 10 tabelas smc_v2_* no MySQL do Hostinger espelhando as technical_engine_smc_v2_*_shadow do VPS. 11 migrations Laravel + 10 models Eloquent + SyncTableController (POST /api/sync/tables/push) + SmcZoneService (leitura). Sync dual-path: novo endpoint /sync/tables/push com fallback para /sync/zones. Feature flag SMC_USE_NEW_TABLES. |
