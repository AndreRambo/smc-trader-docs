# ARQUITETURA OFICIAL — SMC Trader System 7.0

> **Atualizado: 2026-07-02 | CORREÇÃO CIRÚRGICA — coleta real + cutover:**
> auditoria completa do documento contra o sistema real (`Sistema VPS/
> Relatorios/AUDITORIA_ARQUITETURA_OFICIAL_VS_REALIDADE_2026-07-02.md`)
> encontrou drift real: (1) `run_b3.py`/`run_forex.py` (§3.1, §4.8, §9, §10)
> descritos como coletores ativos — na verdade `smc-b3-robot.service` nem está
> instalado e `smc-forex-robot.service` está inativo; o coletor real é
> `services/asset_collector/` + `services/candle_event_processor/` (ver
> §3.1-REAL); (2) `technical_engine/smc_engine_v2/` (§4.1, §10) descrito como
> "STABLE_FROZEN_V2" ativo — na verdade é um stub que só levanta `ImportError`,
> congelado em backup desde a Fase M-1C; o código real vive em `smc_engine/`
> (§4.10/§4.11); (3) adicionada §4.11-A documentando o cutover do motor
> incremental como principal (Fase S + `incremental/live/`, validado contra
> MySQL real e 3 meses de dados reais, zero erros). **Decisão operacional
> vigente:** coleta ao vivo pausada por decisão do produto
> (`smc-asset-collector@WINFUT.service` desativado em 2026-07-02) — todo
> cálculo roda sobre CSV local enquanto essa decisão vigorar.
> `smc-b3-robot.service`/`smc-forex-robot.service` não devem ser reativados.
>
> **Atualizado: 2026-07-01 | CORREÇÃO CRÍTICA:** auditoria confirmou que o pipeline
> batch "CANONICAL_V3" (§4.10) **não é causal** — look-ahead estrutural confirmado
> em `swings.py` (idêntico nos caminhos LEGACY_V2 e CANONICAL_V3) e `fvg.py`
> (parâmetro `detection_definition` vestigial, sem efeito na detecção). É esse
> pipeline que roda em produção hoje via `run_b3.py`/`infra/sync_v2.py`. O motor
> realmente causal e validado (2.103 testes, dados reais WINFUT 2021-2026,
> `future_data_violations=0`) é o `incremental/` (§4.11) — **shadow-only, ainda
> não substituiu o pipeline batch em produção**. Ver
> `Sistema VPS/Relatorios/RELATORIO_AUDITORIA_LOOKAHEAD_PIPELINE_BATCH_PRODUCAO.md`
> e `Sistema VPS/Relatorios/SMC_ENGINE_V3_REAL_DATA_VALIDATION/RELATORIO_FINAL_SMC_V3_REAL_DATA_VALIDATION.md`.
>
> **Atualizado: 2026-07-01 (mesmo dia) | CORREÇÃO IMPLEMENTADA (opt-in, default inalterado):**
> a correção real do look-ahead acima foi implementada de forma aditiva — novas
> funções `swings.to_causal_swings_view()` e `fvg.to_causal_fvg_view()` reindexam
> pivôs/FVGs para sua linha de disponibilidade causal; `pipeline.py` ganhou o
> parâmetro `causal_swings_fvg: bool = False` para consumi-las em
> order_blocks/structure/liquidity/retracements/bpr. **Default `False` preserva
> o comportamento de produção exatamente como está** — nenhuma mudança de
> comportamento até decisão explícita de ativar a flag. Também corrigido,
> incondicionalmente: metadado `confirmed_index`/`available_index` em
> `fvg.calculate_fvg_records()` (apontava para o candle errado). 164 testes
> passando (7 novos), zero regressões. Ver §9 de
> `RELATORIO_AUDITORIA_LOOKAHEAD_PIPELINE_BATCH_PRODUCAO.md` para detalhes.
>
> Histórico: 2026-06-30 | **SMC Engine V3 — CANONICAL_V3 ativo** (rótulo de classificação/lifecycle, ver correção acima). 8 engines batch refatoradas (sessions.py, swings.py, structure.py, previous_high_low.py, retracements.py, liquidity.py, order_blocks.py, fvg.py) com detection_definition="CANONICAL_V3" como padrão. Temporal window, SHA-256 IDs, guardrails shadow_only. Backward compat LEGACY_V2 preservada. Incremental track (PHASE_08+R1-R5A) mantido como track paralelo. PR merged. Ver §4.10.
>
> Histórico: 2026-06-30 | SMC Engine V2 Incremental Unified — remediação pré-cutover R1→R4 concluída + R5A-MTF em andamento.
>
> Histórico: 2026-06-28 | FASE V4_04 concluída: Repositories transacionais, RSI-Heikin Ashi, persistência controlada de indicadores D1. Plano em `Sistema VPS/Plano/Plano Ativo/PLANO_EXECUTIVO_WINFUT_CAUSAL_LIVE_REPLAY_V4_ATUALIZADO_V1_2.md`. 80 testes V4, 18 tabelas `winfut_lr_v4_*`, schema-validate PASS, transaction-probe PASS.

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

**Principio central**: `shadow_only=True` — guardrail comportamental, não convenção de nomenclatura:
nenhuma tabela do motor alimenta execução real de ordens, `can_promote_trade=False` sempre,
nada é promovido automaticamente. Historicamente isso era 1:1 com tabelas sufixadas `*_shadow`;
desde 2026-07-02 o schema nativo do motor incremental (`smc_v2_structures`, `smc_v2_structure_events`,
`smc_v2_engine_runs`, etc. — §4.11-A) não usa mais esse sufixo, mas o guardrail permanece idêntico:
essas tabelas também não alimentam trade real. LLM nunca calcula zonas, score, probabilidade ou
decisao. O motor e 100% deterministico.

---

## 2. Camadas do Sistema

> **Reescrito 2026-07-02** para refletir o sistema real (ver auditoria
> `Sistema VPS/Relatorios/AUDITORIA_ARQUITETURA_OFICIAL_VS_REALIDADE_2026-07-02.md`).
> Ordem causal de baixo para cima (coleta → motor → dados → dashboards/site);
> os dashboards aparecem no topo só porque são o ponto de leitura do usuário,
> não porque são a origem do dado.
>
> **Estado operacional em 2026-07-02:** coleta ao vivo PAUSADA por decisão do
> produto (`smc-asset-collector@WINFUT.service` e todos os serviços
> downstream inativos). Todo o processamento roda sobre **CSV local**
> (`data/csv_import/canonical/` → `tools/fase_s_simulacao/`) enquanto essa
> decisão vigorar. `smc-b3-robot.service`/`smc-forex-robot.service` (robôs
> antigos) permanecem parados e **não devem ser reativados** — foram
> superados pela arquitetura `services/` abaixo.

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
│ TECHNICAL ENGINE (guardrail: can_promote_trade=False, ver §8)      │
│                                                                    │
│ ┌─ SMC ENGINE V3 — BATCH CANÔNICO (technical_engine/smc_engine/, §4.10)│
│ │ pipeline.py    run_smc_engine_v2_local() — orquestrador 10 passos│  │
│ │ fvg.py / order_blocks.py / structure.py / liquidity.py / bpr.py │  │
│ │ swings.py / sessions.py / retracements.py / previous_high_low.py│  │
│ │ persistence.py  persist_smc_engine_v2_run() → 9 tabelas legadas│  │
│ │ ⚠ não-causal por padrão (look-ahead em swings/fvg); correção   │  │
│ │   opt-in `causal_swings_fvg=True`, default False. Ver §4.10.   │  │
│ │ (smc_engine_v2/ antigo: CONGELADO EM BACKUP, raise ImportError  │  │
│ │  — não é código vivo, ver §4.1)                                 │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ SMC ENGINE V3 INCREMENTAL — motor causal (incremental/, §4.11)┐  │
│ │ engine.py  SmcEngineV2Incremental — candle a candle, O(1),     │  │
│ │            causal (available_at guard), snapshot/restore       │  │
│ │ components/  swing, fvg, ob, bos_choch, liquidity, bpr,        │  │
│ │              retracements, sessions, previous_high_low          │  │
│ │ incremental/live/  cutover p/ produção (§4.11-A) — código       │  │
│ │   pronto e validado (Fase S + MySQL real), NÃO ativado como    │  │
│ │   serviço systemd ainda                                        │  │
│ └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│ ┌─ STUDY GATEWAY CANONICAL TRUTH V2 ───────────────────────────┐  │
│ │ models_v2.py           TechnicalTruthEnvelopeV2 (SHA-256)    │  │
│ │ smc_v2_adapter.py      SMC persisted → Envelope + Readiness  │  │
│ │ confluence_v2.py       6 fontes deterministicas + MTF ponder  │  │
│ │ context_states.py      Elliott/Wyckoff helper + RiskConfig    │  │
│ │ forward_runner.py      Forward shadow gateway (6L) — produção │  │
│ │                        disparada por timer 15min (systemd     │  │
│ │                        timer), não candle a candle             │  │
│ │ incremental_opportunity_bridge.py  cadeia real (Elliott/       │  │
│ │   Wyckoff+envelope+risk+scanner) disparada CANDLE A CANDLE —   │  │
│ │   usada pelo cutover (§4.11-A), ainda não em produção          │  │
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
                         │ consome candles fechados via outbox (dispatcher)
┌────────────────────────▲───────────────────────────────────────────┐
│ COLETA / DISPATCH (services/) — real, ver §3.1-REAL                │
│                                                                    │
│ MT5 (B3:11000/Forex:11001, RPyC/Wine) → smc-mt5-b3-terminal.service│
│                                          smc-mt5-fx-terminal.service│
│   │                                                                │
│   ▼                                                                │
│ services/asset_collector/  (smc-asset-collector@<SYMBOL>.service,  │
│   templated por ativo) → INSERT market_candles + publica evento    │
│   no outbox `technical_engine_candle_events`                       │
│   │                                                                │
│   ▼                                                                │
│ services/candle_event_processor/  (smc-candle-event-processor.     │
│   service) → consome outbox (claim FOR UPDATE SKIP LOCKED) →       │
│   roteia por timeframe → chama SMC ENGINE V3 (batch, acima) →      │
│   só M5 continua para Study/Risk/Scanner                           │
│                                                                    │
│ ⚠ ESTADO ATUAL (2026-07-02): TODOS os serviços acima estão         │
│ INATIVOS (pausados por decisão do produto). Processamento hoje     │
│ roda 100% sobre CSV local via tools/fase_s_simulacao/ + o cutover  │
│ do motor incremental (§4.11-A) — não sobre coleta ao vivo.         │
│ run_b3.py/run_forex.py (mecanismo histórico, §4.8) NÃO reativar.   │
└────────────────────────┬───────────────────────────────────────────┘
                         │
┌────────────────────────▼───────────────────────────────────────────┐
│ DATA LAYER (MySQL VPS)                                             │
│   market_candles                    ← services/asset_collector     │
│                                        (ou reload manual via CSV,   │
│                                        ver §4.11-A, enquanto pausado)│
│   technical_engine_smc_v2_*_shadow  ← 9 tabelas SMC V3 (batch,     │
│                                        nomes legados "v2")          │
│   smc_v2_structures / smc_v2_structure_events / smc_v2_checkpoints │
│     / smc_v2_active_stream_versions / smc_v2_engine_runs ← schema  │
│     nativo do motor incremental (aplicado 2026-07-02, cutover      │
│     ainda não ativado como serviço)                                │
│   technical_engine_study_*_shadow   ← Replay runs + samples        │
│   technical_engine_opportunity_*_shadow ← Signals/Alerts/Outbox   │
│   technical_engine_operational_plans_shadow ← OperationalPlanV2   │
│   technical_engine_candle_events    ← outbox coleta→dispatch       │
│   winfut_lr_v4_*                   ← 18 tabelas Live-Replay V4    │
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

> **Atualizado 2026-07-02:** o texto abaixo (`run_b3.py`/`run_forex.py` via
> `smc-b3-robot.service`/`smc-forex-robot.service`) descreve um mecanismo de
> coleta que **não é o que roda hoje**. Confirmado ao vivo (`systemctl`):
> `smc-b3-robot.service` nem está instalado; `smc-forex-robot.service` está
> inativo. Preservado abaixo só como referência histórica do desenho antigo —
> ver o fluxo REAL logo em seguida.

```
[HISTÓRICO — não reflete o mecanismo em uso]
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

#### 3.1-REAL — Arquitetura de serviços atual (services/)

```
MT5 (B3:11000 / Forex:11001)
  │ RPyC bridge (Wine) — smc-mt5-b3-terminal.service / smc-mt5-fx-terminal.service
  ▼
services/asset_collector/ (smc-asset-collector@<SYMBOL>.service, templated por ativo)
  │  cli.py → worker.py → candle_fetcher.py → mt5_gateway.py → closed_candle_detector.py
  ├─ INSERT market_candles
  └─ event_publisher.py → publica evento no outbox technical_engine_candle_events
                            (status=PENDING, available_at)
                            │
                            ▼
services/candle_event_processor/ (smc-candle-event-processor.service)
  │  processor.py: loop poll (POLL_IDLE_SECONDS=2.0)
  │  repository.py: EventRepository.claim_next()
  │    SELECT ... WHERE status='PENDING' AND available_at<=NOW(6)
  │    ORDER BY available_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED
  │  dispatcher.py: TIMEFRAME_DISPATCH_MAP roteia por event.timeframe
  │    (M1=no-op, M2/M5/M15/H4/D1 processam — H1 AUSENTE do mapa, gap conhecido)
  │    _load_candles_df() → run_smc_engine_v2_local() → persist_smc_engine_v2_run()
  │    (só _handle_m5 continua):
  │      → Study Gateway (build_context_states + build_technical_truth_envelope_v2)
  │      → Risk Management (build_operational_plan)
  │      → Opportunity Scanner (_run_scanner) → sync Laravel (HMAC POST)
  │
  └─ mark_completed(event_id) / mark_failed(event_id, backoff)
```

**Estado operacional em 2026-07-02** (confirmado via `systemctl is-active`): coleta pausada por decisão do produto — todos os cálculos passaram a rodar sobre CSV local (ver §4.11-A). `smc-asset-collector@WINFUT.service`, `smc-candle-event-processor.service`, `smc-study-forward-shadow.timer`, `smc-opportunity-scanner.service` e `smc-opportunity-notifier.service` estão todos **inativos**. Os terminais MT5 (`smc-mt5-b3-terminal`, `smc-mt5-fx-terminal`) permanecem ativos (bridge Wine/RPyC, sem consumidor downstream no momento). `smc-b3-robot.service`/`smc-forex-robot.service` (mecanismo histórico acima) **não devem ser reativados** — superados pela arquitetura `services/` acima.

### 3.2 Estudo Canonico

> **Correção 2026-07-02:** "SMC V2 persisted" é nome de função/tabela legado
> (`smc_v2_*`), não implica que o pacote `smc_engine_v2/` exista — os dados
> vêm de `technical_engine_smc_v2_*_shadow`, gravados pelo pipeline batch
> real (`smc_engine/`, §4.10) ou, no cutover ainda não ativado, pelo motor
> incremental (§4.11-A). Também há DOIS gatilhos possíveis para este fluxo,
> hoje ambos parados (coleta pausada, §2):
> - **Produção (histórico):** `forward_runner.py` via timer systemd
>   (`smc-study-forward-shadow.timer`, a cada 15 min, `--mode ONCE`) — lê o
>   SMC já persistido pelo batch, NÃO recalcula, dispara Elliott/Wyckoff +
>   monta o envelope a cada execução do timer.
> - **Cutover (código pronto, não ativado):** `incremental_opportunity_bridge.py`
>   dispara este mesmo fluxo a CADA CANDLE FECHADO do timeframe base (M5),
>   não a cada 15 min — validado na Fase S com zero erros (§4.11-A).

```
SMC persisted (technical_engine_smc_v2_*_shadow) + Elliott + Wyckoff + Contextual
  │  gatilho: timer 15min (produção histórica) OU candle-a-candle (cutover, §4.11-A)
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

> **Correção 2026-07-02:** `build_operational_plan(envelope, candles, htf_candles=None,
> htf_states=None)` — `htf_candles`/`htf_states` são **opcionais e, na prática, NUNCA
> são passados** hoje: nem `forward_runner.py` (produção histórica, `_persist_operational_plan`
> chama `build_operational_plan(envelope, candles)`) nem o cutover novo
> (`incremental/live/runner.py`, mesma chamada sem esses parâmetros) os fornecem.
> **Consequência real:** CAMADA 1 (Gate HTF) e CAMADA 3 (Alinhamento espacial com
> zonas H4/M15) estão implementadas e testadas isoladamente, mas rodam sempre no
> estado default — `htf_bias=NEUTRO` sem viés real de D1/H4, sem zonas HTF para
> boost de quality — em qualquer execução real até hoje. Só a CAMADA 2
> (confluência ponderada) de fato usa dado real, pois vem de dentro do próprio
> envelope (`confluence_v2.py`), não de `htf_candles`/`htf_states` externos.
> Ninguém decidiu isso como comportamento definitivo — é uma lacuna de wiring
> (P1 aberto, ver `PLANO_MIGRACAO_MOTOR_CAUSAL_INCREMENTAL_PRODUCAO_UNIFICADA.md`
> §8.2), não uma decisão de produto documentada.

```
TechnicalTruthEnvelopeV2 + candles (+ htf_candles/htf_states — opcionais,
                                     NUNCA fornecidos em nenhuma execução real hoje)
  │
  ├── CAMADA 1: Gate HTF (D1+H4) — block/demote por vies contrario
  │             ⚠ INATIVA NA PRÁTICA — sem htf_candles, sempre htf_bias=NEUTRO
  ├── CAMADA 2: Confluencia ponderada (H4=0.40, M15=0.35, M5=0.25)
  │             ✓ ATIVA — usa dado já presente no envelope (confluence_v2.py)
  ├── CAMADA 3: Alinhamento espacial (zona M5 sobreposta a H4/M15 → +15 quality)
  │             ⚠ INATIVA NA PRÁTICA — sem htf_states, sem zonas HTF pra comparar
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

> **Correção 2026-07-02:**
> - **Contagem de gates**: "10 gates" abaixo é uma contagem resumida antiga;
>   a tabela detalhada e verificada contra `evaluator.py` em §4.4.1 lista
>   **12 gates** numerados (inclui `stop_invalidated_by_window` como gate #1
>   separado de `invalidated` #11, e classificação de proximidade DISTANTE
>   como resultado, não gate). Ver §4.4.1 para a lista exata e ordem real.
> - **`contra_htf` é efetivamente um no-op hoje**: depende de `plan.mtf_align`,
>   que vem da CAMADA 1 de `risk_management_v2.py` — e essa camada nunca
>   recebe `htf_candles` reais (ver correção em §3.3). Na prática
>   `mtf_align` nunca chega a `"contra"`/`"contra_htf"`, então esse gate
>   nunca bloqueia nada em execução real até hoje.
> - **Estado operacional**: `smc-opportunity-scanner.service` e
>   `smc-opportunity-notifier.service` estão INATIVOS (coleta pausada, §2).
>   Este fluxo só roda hoje dentro do cutover/Fase S
>   (`incremental_opportunity_bridge.py` → `evaluate_opportunity()`, com um
>   `ScannerConfig` de replay que propositalmente desativa os gates de
>   frescor/mercado — ver §4.11 "Gates bypassados em replay"), não em
>   produção ao vivo.

```
OperationalPlanV2 (M5, ACTIVE)
  + price M1 (OHLC)
  │
  ▼
12 gates deterministicos (ver tabela completa em §4.4.1):
  stop_invalidated_by_window → plan_not_active → readiness → has_operation
  → missing_entry → missing_or_invalid_atr → market_closed (B3 09-18h)
  → plan_too_old (10min) → price_too_old (3min) → contra_htf (⚠ no-op, ver acima)
  → invalidated → wrong_approach
  │
  ├── BLOQUEADO → skip
  │
  ▼
OpportunitySignalV1:
  proximity (NA_ZONA/IMINENTE/PROXIMO/OBSERVANDO/DISTANTE)
  severity (CRITICAL/HIGH/MEDIUM/LOW/NONE)
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

> **Correção 2026-07-02:** `infra/sync_v2.py` **não é chamado pelo dispatcher
> real** (`services/candle_event_processor/dispatcher.py`) — confirmado via
> grep, nenhuma referência. É importado só por `infra/database.py`/
> `infra/mt5_core.py` (infraestrutura da era `run_b3.py`, hoje parada) e por
> scripts avulsos em `tools/` (uso manual/offline, não serviço). O dispatcher
> real tem sua própria função estreita, `_sync_to_laravel()`
> (`dispatcher.py:574`), chamada só dentro de `_handle_m5` — sincroniza
> apenas o bundle de evidência do scanner (sinal + envelope + plano), **não**
> o sync geral de candles/zonas/Elliott/Wyckoff descrito abaixo. Ou seja: o
> fluxo abaixo (`sync_v2_shadow_zones`, `sync_candles`, `sync_elliott_wyckoff`)
> está **dormente** — mesmo status operacional do `run_b3.py` (§3.1).
>
> **Cuidado com colisão de nomes:** `smc_v2_*` no Hostinger (10 tabelas,
> abaixo) é a réplica das 9 tabelas legadas `technical_engine_smc_v2_*_shadow`
> do VPS — **não tem relação nenhuma** com o schema nativo `smc_v2_structures`/
> `smc_v2_structure_events`/`smc_v2_engine_runs`/etc. do motor incremental
> (§4.11-A, também no VPS, aplicado 2026-07-02). São dois bancos de tabelas
> diferentes que só coincidem no prefixo `smc_v2_`.

```
[DORMENTE — não chamado por nenhum serviço ativo hoje, ver correção acima]
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
  → sync_assets, sync_candles, sync_zones, smc_v2_* (10 tabelas replicadas,
    NÃO confundir com o schema nativo do motor incremental — ver acima)
```

**Nota:** As 9 tabelas shadow do VPS (`technical_engine_smc_v2_*_shadow`) foram replicadas no Hostinger como `smc_v2_*` (10 tabelas incluindo runs). O sync envia raw rows por tabela via `/api/sync/tables/push` (HMAC protegido). Fallback automatico para `/api/sync/zones` (tabela unificada `sync_zones`) se o novo endpoint nao estiver disponivel. **Único caminho de sync realmente ativo hoje** (quando a coleta está ligada): `dispatcher.py::_sync_to_laravel()`, escopo estreito (só bundle de scanner alert do M5), não este fluxo geral.

---

## 4. Modulos Principais

> **Reorganizado 2026-07-02:** esta seção lista só módulos ATIVOS (código vivo, parte do fluxo real do sistema). Módulos congelados, históricos ou nunca implementados foram movidos para **§12. Módulos Congelados, Históricos e Propostas Não Implementadas** — inclui o antigo `smc_engine_v2/` (congelado em backup), Live-Replay V4 (track de P&D isolado, nunca teve run ACTIVE), SignalResearchV2 (pesquisa de sinais concluída, não é parte do motor causal) e a proposta de Volume Profile (nunca implementada).

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

#### 4.4.1 Evaluator — 12 Gates Determinísticos

`evaluate_opportunity(plan, price, config, now, recent_candles)` → `OpportunityEvaluation`

Cada gate retorna `allowed=False` com `reason` e `trigger_state` quando bloqueia. Todos são avaliados em sequência; o primeiro que bloqueia define o resultado.

| # | Gate | Condição | reason_code | trigger_state |
|---|------|----------|-------------|---------------|
| 1 | Stop Invalidated by Window (S18K) | candle M1 cruzou o stop antes da entrada | `invalidated_before_entry` | `INVALIDATED` |
| 2 | Plan Not Active | `plan.status != "ACTIVE"` | `plan_not_active` | `EXPIRED` |
| 3 | Readiness Not Allowed | `plan.readiness ∉ config.require_readiness` | `readiness_not_allowed` | `WAITING` |
| 4 | No Operation | `plan.has_operation == False` | `no_operation` | `WAITING` |
| 5 | Missing Entry | `plan.entrada is None` | `missing_entry` | `WAITING` |
| 6 | Missing/Invalid ATR | `price.atr is None ou ≤ 0` | `missing_or_invalid_atr` | `WAITING` |
| 7 | Market Closed (S18B) | B3 fora de 09–18h BRT | `market_closed` | `EXPIRED` |
| 8 | Plan Too Old (S18E) | `idade_plan > max_plan_age_minutes` (10min default) | `plan_too_old` | `EXPIRED` |
| 9 | Price Too Old (S18E) | `idade_price > max_price_age_minutes` (3min default) | `price_too_old` | `EXPIRED` |
| 10 | Contra HTF | `plan.mtf_align ∈ {"contra", "contra_htf"}` | `contra_htf` | `WAITING` |
| 11 | Stop Invalidated (S18J) | candle low≤stop (ALTISTA) ou high≥stop (BAIXISTA) | `invalidated_before_entry` | `INVALIDATED` |
| 12 | Wrong Approach (S18F) | preço do lado errado da entrada para a direção | `wrong_approach_side` | `WAITING` |

**Quando todos os gates passam**, o evaluator classifica a proximidade:

| Proximity | Condição (distância em ATR) | trigger_state | severity | allowed |
|-----------|----------------------------|---------------|----------|---------|
| `NA_ZONA` | candle toca entrada OU preço dentro da zona | `IN_ZONE` | `CRITICAL` | `True` |
| `IMINENTE` | `≤ 1.0 ATR` | `ARMED` | `HIGH` | `True` |
| `PROXIMO` | `≤ 2.0 ATR` | `ARMED` | `MEDIUM` | `True` |
| `OBSERVANDO` | `≤ 3.0 ATR` | `WATCHING` | `LOW` | `True` |
| `DISTANTE` | `> 3.0 ATR` | `WAITING` | `NONE` | `False` |

#### 4.4.2 Evidence Bundle — Composição

O `OpportunityEvidenceBundleV1` é montado pelo `EvidenceBundleBuilder` e contém 28 campos organizados em sub-modelos:

| Sub-modelo | Campos-chave | Propósito |
|------------|-------------|-----------|
| `AssetRef` | asset_id, symbol, market, currency | Identificação do ativo |
| `TimeframeRef` | feed(M1), base(M5), structure(M15), context([H4,D1]) | Hierarquia MTF |
| `TimingRef` | detected_at, opportunity_time, base_candle_time | Timestamps causais |
| `DecisionRef` | direction, readiness, proximity, severity, confidence_label | Decisão avaliada |
| `LevelsRef` | entry, stop, tp1–tp3, rr_tp1–rr_tp3 | Níveis de trade |
| `RiskRef` | risk_points, contracts, invalidation_rule, trailing_rule | Gestão de risco |
| `InputRefs` | smc_run_id, elliott_run_id, wyckoff_run_id, contextual_run_id | Rastreabilidade das fontes |
| `EngineVersions` | smc, elliott, wyckoff, study_gateway, risk_management, scanner | Versionamento |
| `Guardrails` | shadow_only, can_promote_trade, anti_lookahead | Controles de segurança |
| `HitRates` | sample_size, expectancy_r | Taxas históricas |
| `Narrative` | summary, smc/elliott/wyckoff/risk_explanation | Texto para o usuário |
| `ZonesRef` | fvg[], order_blocks[], bpr[], liquidity[], sessions[] | Zonas SMC envolvidas |
| `StructureRef` | bos_choch[], swings[] | Estruturas de suporte |
| `evidences` | categorias: smc, structure, contextual, elliott, wyckoff | Itens de evidência individual |

Cada `EvidenceItem` tem: `category`, `subtype`, `source_ref`, `selection_reason`, `payload`, `sort_order`.

O bundle é hasheado via SHA-256 do JSON canônico → `bundle_hash` (imutável após criação).

#### 4.4.3 Lifecycle — Máquina de 17 Estados

```
DETECTED → EVIDENCE_PENDING → EVIDENCE_READY → NOTIFICATION_PENDING → NOTIFIED
  → OPENED → WATCHING → ENTRY_TOUCHED → ENTRY_CONFIRMED
    → TP1_REACHED → TP2_REACHED → TP3_REACHED (terminal)
    → STOP_REACHED (terminal)
  → INVALIDATED (terminal)
  → EXPIRED (terminal)
  → CANCELLED (terminal)
```

**18 transições válidas.** Estados terminais (sem saída): `TP3_REACHED`, `STOP_REACHED`, `INVALIDATED`, `EXPIRED`, `CANCELLED`. `ERROR` é dead-end sem transições definidas.

O guard de transição é puramente a lookup table — sem validação de preço/hora no state machine. Regras de negócio (ex: "deve estar na zona para confirmar entry") são enforced pelo caller antes de chamar `transition()`.

#### 4.4.4 Scanner Orquestração — `scan_once()`

```
scan_once(persist=False)
  │
  ├─ Por cada symbol em config.assets:
  │    ├─ load_latest_price(conn, symbol, M1)
  │    ├─ load_latest_plan_for_scanner(conn, symbol, M5)
  │    ├─ load_price_window_since_plan(conn, symbol, M1)  ← S18K
  │    ├─ evaluate_opportunity(plan, price, config, recent_candles)
  │    ├─ Se allowed=False → skip
  │    ├─ should_emit_signal(evaluation, config)  ← dedup signal
  │    ├─ build_opportunity_signal(plan, price, evaluation, config)
  │    └─ persist + alert + notify (se persist=True)
  │         ├─ save_opportunity_signal
  │         ├─ load_recent_alert_by_dedup_key (15min window)
  │         ├─ save_opportunity_alert
  │         └─ enqueue_notification (WebSocket outbox)
  │
  └─ save_scan_run(conn, summary)
```

### 4.4-A Opportunity Evidence (`technical_engine/opportunity_evidence/`)

> **Adicionado 2026-07-02** — módulo ativo, usado pelo dispatcher real
> (`services/candle_event_processor/dispatcher.py:545-547`, dentro de
> `_handle_m5`), mas que não tinha entrada própria nesta seção (só descrito
> indiretamente em §4.4.2 "Evidence Bundle — Composição").

| Modulo | Descricao |
|--------|-----------|
| `builder.py` | `EvidenceBundleBuilder` — constrói `OpportunityEvidenceBundleV1` (28 campos, ver §4.4.2) a partir do signal do scanner |
| `models.py` | Dataclasses do bundle: `AssetRef`, `TimeframeRef`, `TimingRef`, `DecisionRef`, `LevelsRef`, `RiskRef`, `InputRefs`, `EngineVersions`, `Guardrails`, `HitRates`, `Narrative`, `ZonesRef`, `StructureRef`, `EvidenceItem`, `ZoneRef` |
| `validator.py` | `BundleValidator` — validação do bundle antes de persistir/publicar |
| `hashing.py` / `canonicalizer.py` | `compute_sha256` sobre JSON canônico → `bundle_hash` imutável |
| `persistence.py` | `EvidenceBundlePersistence` — grava o bundle |
| `outbox.py` | `EvidenceOutbox` — publica evento `OPPORTUNITY_EVIDENCE_CREATED` para consumo assíncrono |
| `repositories.py`, `serializer.py`, `enums.py`, `errors.py` | Suporte (acesso a dados, serialização, `ConfidenceLabel`, exceções) |

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
│ │ ├── lib/                                                     │ │
│ │ │   ├── api.ts                 Fetch wrapper com auth token     │ │
│ │ │   └── symbolMap.ts           Shared: apiSymbol, apiTimeframe, │ │
│ │ │                              tsToUtc (unificado 3 hooks)      │ │
│ │ ├── hooks/                                                   │ │
│ │ │   useRealMarketData.ts        API polling candles/zones       │ │
│ │ │   useSmcPerType.ts            Fetch unified + split per-type │ │
│ │ │   useReplayData.ts            Fetch historico + context data  │ │
│ │ ├── components/                                                │ │
│ │ │   ├── CandlestickChart.tsx   Lightweight Charts v5 + SMC +    │ │
│ │ │   │                          replay mode integrado            │ │
│ │ │   ├── BackgroundEffects.tsx  TickerTape, MouseGlow, GlowOrbs │ │
│ │ │   ├── ReplayControls.tsx     Play/Pause/Speed/Seek controls  │ │
│ │ │   ├── ReplayDatePicker.tsx   Date picker custom DD/MM/AAAA   │ │
│ │ │   ├── admin/                 Shared admin components          │ │
│ │ │   │   ├── AdminModal.tsx       Modal reutilizavel              │ │
│ │ │   │   ├── AdminField.tsx       Field/Input/Select              │ │
│ │ │   │   ├── adminStyles.ts       Estilos compartilhados          │ │
│ │ │ │   │   └── adminTypes.ts        Interfaces (User/Plan/License)  │ │
│ │ │   └── chart/smc/            SMC per-type pipelines (14 arq)  │ │
│ │ │       ├── smcTypes.ts          Types + interfaces nativas    │ │
│ │ │       ├── smcStyle.ts          Cores SMC_COLORS + SMC_STYLE │ │
│ │ │       ├── smcRenderUtils.ts    xFromTime, LabelPlacer        │ │
│ │ │       ├── smcNormalize.ts      Normalizador unificado         │ │
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
│ │     ├── ReplayPage.tsx         Replay Profit Pro: date picker, │ │
│ │     │                          context antes do start, play/    │ │
│ │     │                          pause/seek/speed, Elliott/Wyckoff│ │
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
**Modulos:** 72 arquivos Kotlin (composeApp)

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
│ │   OpportunityListScreen.kt LazyColumn + retry button             │ │
│ │   OpportunityListViewModel StateFlow<OppListUiState>             │ │
│ │   OpportunityCard.kt       Card: symbol, direction, proximity,   │ │
│ │                            timeframe, fmtPrice(), formatTime()   │ │
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
│ │                            TokenStorage: user data persistence   │ │
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
│ │                             PaginatedResponse, PaginationMeta,   │ │
│ │                             EvidenceBundle/Chart/HitRates DTOs   │ │
│ │   PreferenceModels.kt       UserPreferences (11 fields:          │ │
│ │                             push/sound/vibration, quiet hours,   │ │
│ │                             enabledAssets, enabledProximities,   │ │
│ │                             maxPushesPerHour)                    │ │
│ │   AccountType.kt            B3/FOREX/FULL enum with hardcoded    │ │
│ │                             allowedAssets (fallback for API)     │ │
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
│ │   AuthRepositoryImpl.kt     ApiClient + TokenStorage (saves      │ │
│ │                             UserDto on login/2FA)                │ │
│ │   DeviceRepositoryImpl.kt   ApiClient /mobile/devices            │ │
│ │   OpportunityRepositoryImpl ApiClient /mobile/opportunities/*    │ │
│ │   PreferencesRepositoryImpl DataStore + PreferenceRemoteDataSource│ │
│ │                             (local cache + server sync)           │ │
│ │                                                                  │ │
│ │ data/remote/                                                      │ │
│ │   PreferenceRemoteDataSource   Ktor → GET/PUT prefs, user/assets │ │
│ │   UserAssetRemoteDataSource    Ktor → GET /user/assets           │ │
│ │                                                                  │ │
│ │ data/dto/                                                         │ │
│ │   PreferenceDto.kt          PreferenceUpdateRequest, Response    │ │
│ │   UserAssetDto.kt           UserAssetResponse, UserAssetItem,    │ │
│ │                             UserAssetMeta (plan limits)          │ │
│ │                                                                  │ │
│ │ data/mapper/                                                      │ │
│ │   OpportunityMapper.kt      Map<String,String> → OpportunityDto  │ │
│ │                             (dead code — FCM uses FcmPayload)    │ │
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
│ │                             single: PreferenceRemoteDataSource   │ │
│ │                             single: UserAssetRemoteDataSource    │ │
│ │                             viewModelOf: 8 ViewModels            │ │
│ │                                                                  │ │
│ │ core/design/                                                      │ │
│ │   MaximusColors.kt         Paleta de cores (dark theme)          │ │
│ │   MaximusTheme.kt          Material3 darkColorScheme             │ │
│ │   LogoPainter.kt           Platform-specific logo painter        │ │
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
│ │   TokenStorage.kt          Interface: save/load/clear token,     │ │
│ │                            deviceId, user data (UserDto)         │ │
│ │                                                                  │ │
│ │ core/deeplink/                                                    │ │
│ │   DeepLinkHandler.kt       URI parser: maximus://opportunity/{id}│ │
│ │   DeepLinkEventBus.kt      SharedFlow<Long> for warm deep links  │ │
│ │                                                                  │ │
│ │ core/device/                                                      │ │
│ │   DeviceInfoProvider.kt    Interface: DeviceInfo (uuid, model,   │ │
│ │                            osVersion, platform)                  │ │
│ │                                                                  │ │
│ │ core/ui/                                                          │ │
│ │   ImageDecoder.kt          decodeBase64ToBytes/decodeToImageBitmap│ │
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
│ │                               + UserDto persistence (JSON)       │ │
│ │     AndroidDataStoreProvider   preferencesDataStore              │ │
│ │                                                                  │ │
│ │   core/device/                                                    │ │
│ │     AndroidDeviceInfoProvider  Device uuid/model/OS via Build     │ │
│ │                                                                  │ │
│ │   service/                                                        │ │
│ │     MaximusForegroundService   Persistent notification (no poll)  │ │
│ │                                                                  │ │
│ │   widget/                                                         │ │
│ │     OpportunityWidget          GlanceAppWidget: latest 5 opps    │ │
│ │     WidgetRefreshWorker        WorkManager: fetch active opps    │ │
│ │     WidgetDataStore            DataStore for widget data cache   │ │
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

> **Correção 2026-07-02:** `run_b3.py`/`run_forex.py` e seus serviços
> (`smc-b3-robot.service`, `smc-forex-robot.service`) estão listados abaixo por
> completude histórica, mas **não são o mecanismo de coleta real** —
> `smc-b3-robot.service` nem está instalado nesta VPS; `smc-forex-robot.service`
> está inativo. **Não devem ser reativados** (decisão do produto, 2026-07-02):
> a coleta ao vivo está deliberadamente pausada e todo o processamento roda
> sobre CSV local (§4.11-A). O mecanismo real de coleta, quando ativo, é
> `services/asset_collector/` (ver §3.1-REAL).

| Componente | Descricao |
|-----------|-----------|
| `mt5_connection.py` | Dual-port RPyC bridge (B3:11000, Forex:11001) com health check e auto-start |
| `mt5_core.py` | `process_mt5_data()`: fetch MT5 → DataFrame → indicadores → INSERT market_candles |
| `services/asset_collector/` | Coletor real por ativo (templated, `smc-asset-collector@<SYMBOL>.service`): `cli.py`→`worker.py`→`candle_fetcher.py`→`mt5_gateway.py`→`closed_candle_detector.py`→`event_publisher.py` (publica no outbox `technical_engine_candle_events`) |
| `services/candle_event_processor/` | Consumidor do outbox (`smc-candle-event-processor.service`): `processor.py` (poll loop) → `repository.py` (`EventRepository.claim_next`, `SELECT...FOR UPDATE SKIP LOCKED`) → `dispatcher.py` (roteia por timeframe, chama `run_smc_engine_v2_local()` + Study/Risk/Scanner para M5) |
| `run_b3.py` *(histórico, não ativo)* | Robo B3: loop 60s, 6 timeframes, WINFUT/WDOFUT/PETR4/VALE3/ITUB3. `smc-b3-robot.service` não instalado nesta VPS |
| `run_forex.py` *(histórico, não ativo)* | Robo Forex: loop 60s, 6 timeframes, XAUUSDm/BTCUSDm/EURUSDm/USDJPYm/etc. `smc-forex-robot.service` inativo |
| `database.py` | MySQL persistence (market_candles, analysis_history, shadow tables) |
| `dashboard_shadow/` | FastAPI backend (:8008) + React frontend + Dash Plotly (:8050) |
| `collector_manager.py` | CandleWatcher: 6 ativos × 5 TFs (M2/M5/M15/H4/D1), poll 30s, debounce 5min |
| `mt5_connection.py` | `_load_config()`: import infra.config_manager com fallback legado |
| `vps_monitor.py` | Coleta metrica VPS (CPU/RAM/Disk/Load/Net/Uptime) via /proc/, monitora 8 servicos via pgrep, envia POST HMAC ao site a cada 30s. Servicos: asset_collector, candle_event, mt5linux, sync_watcher, run_b3.py, run_forex.py, run_opportunity_scanner, Xvfb |
| `vps-monitor.service` | systemd unit (Restart=always) para vps_monitor.py — Python le .env direto, sem EnvironmentFile |

### 4.10 SMC Engine — Batch Canônico (`smc_engine/`)

> ⚠️ **CORREÇÃO (2026-07-01):** este pipeline batch é o que roda em produção hoje
> (via `run_b3.py` → `infra/sync_v2.py` → `pipeline.py:run_smc_engine_v2_local()`),
> mas **não é causal** — auditoria confirmou look-ahead estrutural em `swings.py`
> (`shift(-half_sl)`, presente **igualmente** nos caminhos `LEGACY_V2` e
> `CANONICAL_V3` — a refatoração de 2026-06-30 nunca corrigiu o algoritmo central
> de detecção de pivô) e em `fvg.py` (`shift(-1)`, função única sem ramificação
> por `detection_definition` — o parâmetro é vestigial nesse arquivo). A
> contaminação se propaga transitivamente para Order Block, Structure/BOS-CHOCH,
> Liquidity e Retracements, que consomem o `_swings_df` compartilhado. As
> melhorias descritas abaixo (EQH/EQL SUPERSEDED, lifecycle 10-state, etc.) são
> reais, mas **não implicam correção causal** — o rótulo "CANONICAL_V3" refere-se
> à qualidade de classificação, não à ausência de look-ahead. Ver
> `Sistema VPS/Relatorios/RELATORIO_AUDITORIA_LOOKAHEAD_PIPELINE_BATCH_PRODUCAO.md`
> para a auditoria completa. O motor causal real e validado (2.103 testes, dados
> reais WINFUT 2021-2026) é o `incremental/` descrito em §4.11 — **shadow-only,
> ainda não substituiu este pipeline em produção**.
>
> ⚠️ **CORREÇÃO IMPLEMENTADA (2026-07-01, opt-in):** `swings.to_causal_swings_view()`
> e `fvg.to_causal_fvg_view()` foram adicionadas — reindexam pivôs/FVGs para sua
> linha de disponibilidade causal. `pipeline.py` ganhou `causal_swings_fvg: bool
> = False` para consumi-las em OB/Structure/Liquidity/Retracements/BPR **sem
> alterar `swings.py`/`fvg.py` originais nem os 5 arquivos consumidores**.
> Default `False` — comportamento de produção **inalterado** até decisão
> explícita de ativar. Ver §9 do relatório de auditoria acima para detalhes e
> validação (164 testes, zero regressões).

**Objetivo:** correção algorítmica das 8 engines batch seguindo o plano mestre de orquestração e 8 planos operacionais individuais. Alvo: `technical_engine/smc_engine/` (arquivos planos). `shadow_only=True` em todo o runtime.

**Status:** `CANONICAL_V3` — 8 engines refatoradas com melhorias de classificação/lifecycle. **Detecção central de Swing e FVG permanece não-causal por padrão** — correção causal disponível via flag opt-in `causal_swings_fvg=True` (ver aviso acima), ainda não ativada por padrão. Branch `feature/smc-v2-incremental-unified` (merged → main). PR #1.

**Arquivos V3 (alvo único):**

| Arquivo | Engine | Principais correções |
|---|---|---|
| `sessions.py` | Sessions | IANA/zoneinfo DST-aware, market calendar, holidays, early close, multi-sessão, lifecycle |
| `swings.py` | Swing | EQH/EQL preservados (SUPERSEDED, nunca deletados), HH/HL/LH/LL, quality (prominence), Layer4 protected/weak |
| `structure.py` | Structure | State-machine BOS/CHOCH (não 4-swings), displacement, wick sweep, force score, SwingAvailabilityAdapter (§4.3 mapping) |
| `previous_high_low.py` | Previous HL | TOUCH/WICK_SWEEP/CLOSE_THROUGH distinction |
| `retracements.py` | Retracement | Premium/Equilibrium/Discount, internal calculator, range via Structure |
| `liquidity.py` | Liquidity | ERL/IRL real, lifecycle states, source promotion |
| `order_blocks.py` | Order Block | Lifecycle 10-state (CANDIDATE→...→EXPIRED), freshness, ob_subtype (NORMAL/REJECTION/STACKED) |
| `fvg.py` | FVG | FvgEventV3 canonical class, detect_ifvg_bpr() for BPR detection |

**Contratos congelados:** `technical_engine/smc_engine/contracts/` — temporal window, guardrails, IDs SHA-256, Scope enum. Re-exportam do kernel `technical_engine/contracts/`.

**Feature flags por engine:** `completions.py` — FeatureFlags com promote/rollback (shadow_v3 → canonical_v3 → legacy_v2).

**Shadow persistence:** `ShadowPersistenceCollector` (dry-run, 9 shadow tables).

**Replay MTF:** `replay_mtf.py` — ReplayMtfRunner com parity verification multi-asset.

**Parâmetro `detection_definition`:**
- `"CANONICAL_V3"` — padrão em todas as 8 engines
- `"LEGACY_V2"` — backward compat via parâmetro explícito

**Pacotes órfãos arquivados:** `technical_engine/_archived_v3_packages_unused/` — código morto do M0-G10 (zero consumidores reais).

### 4.11 SMC Engine Incremental Unified (`technical_engine/smc_engine/incremental/`)

> **Correção de path (2026-07-01):** este motor vive em
> `technical_engine/smc_engine/incremental/`, não em `smc_engine_v2/incremental/`
> — `technical_engine/smc_engine_v2/` contém apenas um `__init__.py` de 16 linhas,
> sem lógica própria. Nomes de função/classe internos ainda carregam "v2" por
> herança histórica (`SmcEngineV2Incremental`, tabelas `smc_v2_*`), mas o código
> vive fisicamente sob o namespace `smc_engine`.

**Objetivo:** engine SMC única para live + replay + backtest, O(1) por candle, causal (`available_at` guard), com IDs SHA-256 determinísticos para estruturas/eventos/checkpoints. Substituirá o pipeline batch (§4.10, confirmado não-causal) por cutover controlado (ainda **NÃO** em produção).

**Status (atualizado 2026-07-01):** validado end-to-end pelo plano
`PLANO_MESTRE_AUDITORIA_CORRECAO_VALIDACAO_SMC_V3_COM_CSVS_REAIS_WINFUT_2021_2026.md`
(fases R0-R22, branch `feature/smc-v3-causal-rebuild-real-data`) — 9 bugs reais
corrigidos com evidência em dados reais (sessão nunca fechava, BOS/CHOCH sem
retirada de nível, 2× aliasing em snapshot/checkpoint, EqualLevelCluster ausente,
2× memória sem limite, timestamps/candle_id inconsistentes em PDH/PDL), 2.103
testes de regressão, `future_data_violations=0` e `prefix_divergence_count=0`
confirmados em 12.018 candles reais de WINFUT (2021-2026) via replay em 5 modos.
Decisão formal: `SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS` — P1s abertos
(StructureLegV3 formal, consumo cruzado de Liquidity, decisão de produto sobre
`require_structure_break` em OB, M1 não validado em escala completa) exigem
revisão do dono do produto antes do cutover de produção. Relatório final:
`Sistema VPS/Relatorios/SMC_ENGINE_V3_REAL_DATA_VALIDATION/RELATORIO_FINAL_SMC_V3_REAL_DATA_VALIDATION.md`.
**Opportunity Backtest ainda NÃO autorizado.**

**Status anterior (histórico, pré-2026-07-01):** `PHASE_08_COMPLETE` + remediação pré-cutover **R1→R4 PASS** + **R5A em andamento**. Branch `feature/smc-v2-incremental-unified`. Spec: `docs/SMC_ENGINE_V2_INCREMENTAL_UNIFIED_SPEC/`.

| Camada | Componente | Notas |
|--------|-----------|-------|
| `incremental/engine.py` | `SmcEngineV2Incremental` — orquestra componentes por candle, snapshot/restore | state_hash sobre 12 campos funcionais |
| `incremental/components/` | swing, fvg, **ob**, bos_choch, liquidity, bpr, retracements, sessions, previous_high_low | causal; zonas ativas nunca truncadas (R1) |
| `incremental/persistence/` | repositories, adapter, backfill, schema, replay | escrita atômica por tick (SAVEPOINT), FK on, conflict-aware (R2) |
| `incremental/adapters` | Live / Replay / Batch / PersistedReplay | equivalência por state_hash (Phase 06) |
| `opportunity/replay_adapter.py` | `ReplayOpportunityAdapter` — traduz StructureEmission+Candle → plano/preço canônicos | chama `opportunity_scanner.evaluate_opportunity()` (R3) |
| `opportunity/canonical_backtest.py` | `CanonicalOpportunityBacktester` — backtest via scanner canônico | mesma decisão live↔replay |

**Remediação pré-cutover (R1→R5):**
- **R1 — Active-zone safety (PASS):** removida eviction silenciosa em FVG/OB/Liquidity/BPR/Retracements. Caps viram `SOFT_THRESHOLD_*` (observabilidade). Nenhum evento `EXPIRED`/`DROPPED` artificial.
- **R2 — Integridade de persistência (PASS, MySQL=PARTIAL):** `INSERT OR IGNORE` eliminado → SELECT→INSERT | IDEMPOTENT_MATCH | `PersistenceConflictError`. `PRAGMA foreign_keys=ON`. Backfill usa `write_tick_atomic()` (SAVEPOINT por tick). MySQL staging não validado (sem driver no ambiente).
- **R3 — Unificação do Opportunity Engine (PASS):** backtest deixa de usar evaluator simplificado; passa pelo `opportunity_scanner` canônico via adapter. `opportunity_scanner/*` intocado.
- **OB subtype parity:** `ob_subtype` (NORMAL/REJECTION/STACKED) portado batch→incremental, gravado no `payload` do OB (struct_id estável; TIER 2 confluência diferido no incremental). Decisão de rename `BREAKER`→`STACKED` (2026-06-30) para evitar colisão semântica com breaker-block clássico (`ZONE_TYPE_BREAKER`).
- **R4 — Shadow runtime real + rollback (PASS):** `shadow_runtime.py` injeta o engine incremental após cada persist batch; flag `SMC_V2_INCREMENTAL_SHADOW=true`; SQLite local (nunca MySQL produção); RESTART_CONTINUITY via checkpoint; ROLLBACK = desligar flag; ZERO_SHADOW_ORDERS garantido por código. 17 testes de gate passando (2026-06-30).
- **R5A — MTF candle-a-candle replay (EM ANDAMENTO):** `tools/r5a_mtf_replay.py` (604 linhas) simula o coletor B3 real: M2 inserido antes do M5, M15/H1/H4/D1 inseridos após o M5 quando a fronteira de período é atingida. **Bugs corrigidos nesta fase:** (1) timeout Hostinger ~600s/step → `SMC_V2_SKIP_HOSTINGER_SYNC=true` env var em `infra/sync_v2.py`; (2) FK `FOREIGN KEY constraint failed` em `smc_v2_structure_events` → `RetracementsComponent` emitia evento `ANCHOR_CHANGED` com `structure_id` nunca publicado como `StructureEmission` → corrigido com `_emit_anchor_structure()` emitindo `structure_type="FIBONACCI_ANCHOR"` antes do evento; (3) `assert_db_clean()` adicionado para abortar se banco não foi limpo (impede silent no-op). Verificação final (`--limit 1000 --bootstrap 100`) aguardando execução do usuário. Plano: `docs_geral/Sistema VPS/Plano/Plano Ativo/Plano_R5A_MTF.md`.

**Guardrails permanentes:** nenhuma zona ativa removida por limite de quantidade; `EXPIRED` só por regra de domínio causal/versionada; conflito de persistência nunca silencioso; cutover só após MySQL staging + shadow runtime real + rollback pós-restart.

#### R3 — Detalhe da Integração

O `ReplayOpportunityAdapter` traduz `StructureEmission + CandleEnvelope` → `PersistedOperationalPlanRef + LatestPriceRef` e chama `evaluate_opportunity()` canônico:

| Campo Canônico | Fonte no V2 |
|----------------|-------------|
| `direction` | BULLISH→ALTISTA, BEARISH→BAIXISTA |
| `entrada` | midpoint da zona (FVG/OB/BPR) |
| `stop` | zone_bottom − zone_size×0.2 (bull) ou zone_top + zone_size×0.2 (bear) |
| `tp1/tp2/tp3` | entry + risk × R:R (1.5R, 3.0R, 5.0R) |
| `readiness` | hardcoded `PRONTO` |
| `now` | `candle.available_at` (histórico, não wall clock) |
| `atr` | `candle.high − candle.low` (fallback se não fornecido) |

**Gates bypassados em replay** (não aplicáveis a dados históricos):

| Config | Valor Replay | O que bypassa |
|--------|-------------|---------------|
| `require_has_operation` | `False` | Permite entrada em planos frescos |
| `require_htf_aligned` | `False` | Sem confirmação HTF |
| `allow_market_closed_b3` | `True` | Fora de horário B3 |
| `max_plan_age_minutes` | `999999` | Plano pode ser antigo |
| `max_price_age_minutes` | `999999` | Preço pode ser stale |

**Gates que permanecem ativos:** causal guard (`available_at`), approach side, zone degenerate check, ATR validation, proximidade classification.

---

### 4.11-A Cutover — Motor Incremental como Principal (2026-07-02, em andamento)

**Objetivo:** tornar o motor incremental (§4.11) o caminho de produção principal, calculando tudo candle a candle, aposentando o pipeline batch (§4.10) como fallback/ferramenta offline (não apagado). Trabalho feito só em código nesta etapa — nenhum serviço systemd novo criado/ativado. Plano completo: `Sistema VPS/Plano/Plano Ativo/PLANO_MIGRACAO_MOTOR_CAUSAL_INCREMENTAL_PRODUCAO_UNIFICADA.md` (Seções 9-10).

**Decisão operacional vigente (2026-07-02):** coleta ao vivo pausada — `smc-asset-collector@WINFUT.service` desativado a pedido do produto. Todo cálculo (SMC + Elliott/Wyckoff + risk management + oportunidade) roda sobre **CSV local** (`data/csv_import/canonical/`) via o harness da Fase S e/ou recarregando `market_candles` a partir do CSV (`tools/fase_s_simulacao/reload_market_candles_from_csv.py`). `smc-b3-robot.service`/`smc-forex-robot.service` **não devem ser reativados**.

**Fase S — harness de simulação causal** (`tools/fase_s_simulacao/run_harness_csv_mtf.py`): relógio-mestre MTF (M2→D1) sobre CSV, acelerado mas respeitando a ordem causal real. Valida candle a candle:
- **S1+S2** — motor SMC incremental por timeframe.
- **S3** — Elliott/Wyckoff recalculados a cada candle (não a cada 15min como o `forward_runner` real faz), via a mesma `build_context_states()` de produção.
- **S4** — cadeia real de decisão (`build_technical_truth_envelope_v2` → `build_operational_plan` → `evaluate_opportunity`, todas funções REAIS de produção), via `technical_engine/study_gateway/incremental_opportunity_bridge.py`.

Resultado validado: 3 meses WINFUT / 6 timeframes, **zero erros** em todas as sub-fases.

**`technical_engine/smc_engine/incremental/live/`** — código de produção do cutover (ainda não ativado como serviço):

| Módulo | Papel |
|---|---|
| `mysql_repositories.py` | Porte MySQL das 6 classes de persistência nativa (`EngineRunRepositoryMySQL`, `StructureRepositoryMySQL`, `StructureEventRepositoryMySQL`, `CheckpointRepositoryMySQL`, `StreamVersionRepositoryMySQL`, `ReconciliationRepositoryMySQL`) — grava nos nomes oficiais `technical_engine_smc_engine_runs`/`technical_engine_smc_structures`/`technical_engine_smc_structure_events`/`technical_engine_smc_checkpoints`/`technical_engine_smc_active_stream_versions`/`technical_engine_smc_reconciliation` (§6.2 — views graváveis sobre o schema físico `smc_v2_*`, `migrations/20260629_smc_v2_incremental_schema.sql`, renomeado 2026-07-02 conforme `PLANO_PADRONIZACAO_SMC_ENGINE_TABELAS_SMC.md`) |
| `cutover_store.py` | `PersistedCutoverStore` — persiste o roteamento batch↔incremental por (asset, timeframe) em `smc_v2_active_stream_versions`, casca sobre o `CutoverManager` (Fase 08, antes só em memória) |
| `runner.py` | `IncrementalCandleRunner` — cold-start/warm-restart via checkpoint (`engine.snapshot()`/`restore()`), persistência nativa + dual-write legado (`legacy_shadow_adapter` → `persist_smc_engine_v2_run`, mesmas 9 tabelas que o site lê), dispara a cadeia S3/S4 real no fechamento do trigger-timeframe |

**Validação real** (`tools/fase_s_simulacao/run_live_replay_check.py`, contra MySQL real + `market_candles` recarregado com 3 meses de CSV via `reload_market_candles_from_csv.py`): cold-start (6.775 candles, 8.313 estruturas + 17.993 eventos nativos, 34 checkpoints), warm-restart via checkpoint confirmado, dual-write legado confirmado (novo run real para WINFUT nas tabelas `technical_engine_smc_v2_*_shadow`), 20 decisões de oportunidade reais via a cadeia completa — **zero erros** em todas as etapas.

**Pendente:** ativar como serviço systemd (decisão futura, não tomada), testes automatizados do `IncrementalCandleRunner` (hoje só validado via script manual), reconciliação periódica, decisão sobre `plan_id` sem dedup por conteúdo (`envelope_id` é UUID aleatório a cada trigger).

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

> **Atualizado 2026-07-02** (`PLANO_PADRONIZACAO_SMC_ENGINE_TABELAS_SMC.md`,
> Fase 3+4): cada tabela abaixo agora tem um **nome oficial novo**
> (`technical_engine_smc_*`, sem sufixo de versão) que é uma **view**
> gravável apontando para a tabela física antiga (`*_v2_*_shadow`, ainda a
> origem física dos dados). `persistence.py` já grava usando o nome novo.
> Código de leitura antigo (ex: `infra/sync_v2.py`, dormente) continua
> funcionando sem alteração, lendo a tabela física pelo nome antigo.

| Tabela física (origem) | Nome oficial novo (view gravável) | Conteudo |
|--------|--------|----------|
| `technical_engine_smc_v2_runs_shadow` | `technical_engine_smc_runs` | Run metadata (run_id, symbol, timeframe, params, timestamps) |
| `technical_engine_smc_v2_fvg_shadow` | `technical_engine_smc_fvg` | Fair Value Gaps |
| `technical_engine_smc_v2_order_blocks_shadow` | `technical_engine_smc_order_blocks` | Order Blocks (com quality scoring) |
| `technical_engine_smc_v2_bos_choch_shadow` | `technical_engine_smc_bos_choch` | BOS/CHOCH structural breaks |
| `technical_engine_smc_v2_liquidity_shadow` | `technical_engine_smc_liquidity` | Liquidity levels |
| `technical_engine_smc_v2_swings_shadow` | `technical_engine_smc_swings` | Swing points |
| `technical_engine_smc_v2_sessions_shadow` | `technical_engine_smc_sessions` | Session markers |
| `technical_engine_smc_v2_retracements_shadow` | `technical_engine_smc_retracements` | Retracement percentages |
| `technical_engine_smc_v2_previous_high_low_shadow` | `technical_engine_smc_previous_high_low` | PDH/PDL |
| `technical_engine_smc_v2_visual_overlays_shadow` | `technical_engine_smc_visual_overlays` | Visual overlays |

**Schema nativo do motor incremental** (`technical_engine/smc_engine/incremental/live/`, §4.11-A) — mesmo padrão, tabela física antiga + view gravável com nome novo:

| Tabela física (origem) | Nome oficial novo (view gravável) |
|--------|--------|
| `smc_v2_engine_runs` | `technical_engine_smc_engine_runs` |
| `smc_v2_structures` | `technical_engine_smc_structures` |
| `smc_v2_structure_events` | `technical_engine_smc_structure_events` |
| `smc_v2_checkpoints` | `technical_engine_smc_checkpoints` |
| `smc_v2_active_stream_versions` | `technical_engine_smc_active_stream_versions` |
| `smc_v2_reconciliation` | `technical_engine_smc_reconciliation` |
| `technical_engine_study_replay_runs_shadow` | Replay runs |
| `technical_engine_study_replay_samples_shadow` | Replay samples (com truth_envelope_v2) |
| `technical_engine_study_replay_metrics_shadow` | Replay metrics |
| `technical_engine_elliott_shadow` | Elliott ctx_json por (symbol, timeframe) — UPSERT via TRIGGER 4b |
| `technical_engine_wyckoff_shadow` | Wyckoff ctx_json por (symbol, timeframe) — UPSERT via TRIGGER 4b |

### 6.3 Tabelas Live-Replay V4 (VPS, isolamento por namespace `winfut_lr_v4_*`)

| Tabela | Conteudo |
|--------|----------|
| `winfut_lr_v4_parent_runs` | Run metadata (run_id, symbol, asset_id, status, hashes) |
| `winfut_lr_v4_timeframe_runs` | Timeframe runs (candle_count, indicator_count, structure_count) |
| `winfut_lr_v4_status_history` | Status transitions audit trail |
| `winfut_lr_v4_chunks` | Chunk tracking (chunk_number, first/last candle, status) |
| `winfut_lr_v4_checkpoints` | Checkpoint state (state_hash, content_hash, chunk_id FK) |
| `winfut_lr_v4_indicators` | Indicator emissions (15 RSI-HA features + 7 basic indicators) |
| `winfut_lr_v4_reconciliation` | Reconciliation residuals per chunk |
| `winfut_lr_v4_validations` | Validation results per chunk |
| `winfut_lr_v4_errors` | Error log per run/chunk |
| `winfut_lr_v4_artifacts` | Artifacts (reports, hashes, metadata) |
| `winfut_lr_v4_active_runs` | Active run tracking |
| `winfut_lr_v4_structures` | SMC structures (FVG, OB, BOS/CHOCH, etc.) |
| `winfut_lr_v4_structure_events` | Structure lifecycle events |
| `winfut_lr_v4_opportunities` | Opportunity signals |
| `winfut_lr_v4_opportunity_evidence` | Opportunity evidence |
| `winfut_lr_v4_outcomes` | Trade outcomes |
| `winfut_lr_v4_trade_events` | Trade events |
| `winfut_lr_v4_trade_simulations` | Trade simulations |

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

> **Correção 2026-07-02:** `shadow_only=True` é um guardrail COMPORTAMENTAL
> (nenhuma tabela alimenta execução real de ordens), não uma convenção de
> nomenclatura. Desde o cutover do motor incremental (§4.11-A), o schema
> nativo (`smc_v2_structures`/`smc_v2_engine_runs`/etc.) não usa mais o
> sufixo `*_shadow`, mas continua sujeito ao mesmo guardrail — nenhuma
> dessas tabelas é lida por execução de ordens real.

```text
shadow_only=True                → Nenhuma tabela alimenta execução real de ordens
                                   (não implica mais sufixo de nome *_shadow — ver acima)
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

## 9. Metricas (2026-06-28, estado operacional atualizado 2026-07-02)

> **Correção 2026-07-02:** linhas "Robôs de coleta" e "Serviços systemd"
> abaixo refletem o desenho antigo, não o estado real. Estado real
> confirmado via `systemctl is-active` em 2026-07-02:
> `smc-mt5-b3-terminal`/`smc-mt5-fx-terminal` ativos (bridge MT5, sem
> consumidor downstream); TODOS os demais (`smc-asset-collector@WINFUT`,
> `smc-candle-event-processor`, `smc-study-forward-shadow.timer`,
> `smc-opportunity-scanner`, `smc-opportunity-notifier`) inativos —
> **coleta ao vivo pausada por decisão do produto**, todo o processamento
> roda sobre CSV local (§4.11-A). `smc-b3-robot.service` não instalado;
> `smc-forex-robot.service` inativo — nenhum dos dois deve ser reativado.

| Metrica | Valor |
|---------|-------|
| Testes Python total | 2522 passed, 3 failed (preexistentes), 3 skipped |
| Testes V4 Live-Replay | 80 passed (51 foundation + 29 V4_04 repositories/RSI-HA/checkpoint) |
| Testes Laravel (S24) | 33 escritos (PHP indisponivel) |
| Ativos | 6 (WINFUT, WDOFUT, PETR4, VALE3, XAUUSDm, BTCUSDm) |
| Robos de coleta (histórico, não ativo) | 2 (run_b3.py, run_forex.py) — ver correção acima |
| Coleta real (quando ativa) | `services/asset_collector/` — pausada em 2026-07-02, tudo via CSV |
| Timeframes | 7 (M1, M2, M5, M15, H1, H4, D1) |
| PRONTO rate (WINFUT M5, 500 samples) | 57.8% |
| Media PRONTO (6 ativos) | ~50% |
| Sinais Backtest S21 | 535k candles, 155k sinais, 35k setups (6 ativos, ate 24 meses) |
| Win rate setups (S21) | 47-50% |
| Sinais IMINENTE+NA_ZONA/dia | ~1.5/dia (todos ativos) |
| Servicos systemd | 11 ativos (4 scanner/notifier/forex + 4 MT5 + 2 bridge + 1 vps-monitor) + 2 robos coleta |
| VPS Monitor | vps_monitor.py → POST 30s HMAC → Cache::put (TTL 5min). CPU/RAM/Disk/Load/Net/Uptime/Services. Monitora 8 servicos: asset_collector, candle_event, mt5linux, sync_watcher, run_b3.py, run_forex.py, run_opportunity_scanner, Xvfb |
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
| App arquivos Kotlin | 72 (commonMain + androidMain: domain/data/features/core + widget/service/firebase) |
| App telas | 8 (Login, ForgotPassword, Dashboard, OpportunityList, OpportunityDetail, History, Preferences, Account) |
| App push | Firebase FCM v33.9.0, deep link maximus://opportunity/{id}, channel opportunity_alerts |
| App DI | Koin 4.0.0 (commonModule 8 ViewModels + androidModule 3 platform impls) |
| App navegacao | Jetpack Navigation Compose 2.8.0, 8 rotas, suporte deep link |
| Site backend | Laravel 12 + PHP 8.2+ — 16 controllers, 4 middleware, 26 models, 48+ rotas API |
| Site frontend | React 19 + TypeScript + Vite 8 + Tailwind 4 — 19 paginas, SMC Canvas overlay, VPS sparklines |
| Site auth | Sanctum token (mt_ prefix, 30d) + 2FA TOTP (spomky-labs/otphp) + HMAC dual (scanner + bridge) |
| Site webhooks | 12 gateways pagamento — endpoint unico POST /api/webhooks/{provider} |
| Site FCM | Firebase HTTP v1 — OAuth2 JWT → sendToDevice → push_logs |
| Latencia SMC pipeline | 3-5 segundos |
| Frontend arquivos | 32 componentes, 3 hooks, 2 shared utilities, 4 admin components |
| Frontend dead code removido | ~900 linhas (useMarketData, useMarketWebSocket, SmcPaneRenderer, 6 normalizers, smcLabelCollision) |
| Frontend console.logs removidos | 18 statements (7 renderers, 2 hooks, 1 normalizer) |
| Replay features | Date picker custom, context antes do start, Elliott/Wyckoff overlay, SMC toggles, IA Panel, Watchlist |
| SignalResearchV2 | Candidate C Nested Walk-Forward (Fase 6.1) — 200 trials × 8 folds = 1600 units |
| SignalResearchV2 status | ✅ FASE 6.2 concluída (161/400 trials, dados reais). Nenhum trial ≥2 T/dia com PF>2. Nova abordagem: Análise Data-Driven (FASE A+B) |
| SignalResearchV2 DB | trade_backtest_runs (#5 M5 antigo, #8 M5 novo, #9 M2 vencedor) + trade_backtest_results (854 trades) |
| SignalResearchV2 proximo | FASE A: análise de zonas no banco → FASE B: backtest baseado em evidências |
| SignalResearchV2 metodologia | `Sistema VPS/Relatorios/Backtest/METODOLOGIA_NESTED_WALK_FORWARD.md` — processo completo para replicar em outros ativos |
| Fases concluidas | S1→S24 + Plano 1-2-3 + Fase 5 (Seguranca) + Fase 6 (E2E) + VPS Monitor + Fase 6.1 + Fase 6.2 (WF 400 trials) + Fase 7 |
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
│       │   ├── Plano Ativo/      (1 ativo: PLANO_ANALISE_DATA_DRIVEN_WINFUT.md — FASE A+B Análise Data-Driven)
│       │   └── Plano Concluidos/ (8 planos: Fase 6.1 WF, Backtest, Backup, Otimizacao, E2E, Signal Candidate, etc.)
│       ├── Relatorios/
│       │   ├── SignalCandidate/  Fases 1-8 (11 arquivos, concluido)
│       │   ├── SignalResearchV2/ Fases 0-6 (34 arquivos, Fase 6.2 concluida — 161/400 trials)
│       │   ├── Backtest/         (2 arquivos + ANALISE_ZONAS_WINFUT.md em progresso)
│       │   ├── Soak/             soak_metrics CSV
│       │   └── (11 relatorios gerais: baseline, decisao, E2E, seguranca, etc.)
│       └── baseline_backups/     (systemd services, .env example, requirements.txt)
│
├── SMC_Trader_System 7.0/        ← Repo: AndreRambo/smc-trader-system-7-local
│                                   Branch: feature/phase6-candidate-c-nested-walk-forward
├── technical_engine/           ← motor tecnico completo
│   ├── smc_engine_v2/          CONGELADO EM BACKUP (raise ImportError) — ver §4.1. Código real: smc_engine/
│   ├── smc_engine/          Batch canônico (§4.10) + incremental/ (motor causal, §4.11) + incremental/live/ (cutover, §4.11-A)
│   ├── live_replay_v4/         Live-Replay V4 (80 testes, 18 tabelas winfut_lr_v4_*)
│   │   ├── candle_clock.py     CandleClock + CandleRef + CandleAvailability
│   │   ├── contracts.py        CanonicalTimeframe, IndicatorEmission, IndicatorSnapshot
│   │   ├── config.py           LiveReplayV4Config, ENGINE_VERSION, SCHEMA_VERSION
│   │   ├── hashing.py          deterministic_hash, compute_state_hash, compute_content_hash
│   │   ├── identity.py         Structure identity + causal timestamps
│   │   ├── state.py            SerializableState with versioned restore
│   │   ├── exceptions.py       18 exceptions (CausalContract, HashConflict, RepositoryScope, etc.)
│   │   ├── validation.py       Foundation + indicator validation
│   │   ├── schema.py           Schema definition + validate_schema
│   │   ├── checkpoint.py       CheckpointService (create, load, validate)
│   │   ├── cli.py              CLI: schema-validate, transaction-probe, foundation-validate
│   │   ├── dry_run_d1.py       Dry-run D1 (250 candles, no writes)
│   │   ├── persist_sample_d1.py First persistence D1 (1743 indicators, INVALIDATED)
│   │   ├── persistence/
│   │   │   ├── allowlist.py    V4_TABLE_ALLOWLIST (18 tables)
│   │   │   ├── connection.py   get_connection (autocommit=False)
│   │   │   ├── repositories.py 11 repositories (Parent, Timeframe, Chunk, Indicator, etc.)
│   │   │   ├── transactions.py ChunkTransactionService + chunk_transaction
│   │   │   ├── mappings.py     indicator_to_row mapping
│   │   │   └── reconciliation.py Reconciliation helpers
│   │   └── indicators/
│   │       ├── engine.py       IncrementalIndicatorEngine
│   │       ├── registry.py     IndicatorRegistry (7 basic + RSI-HA)
│   │       ├── true_range.py   TRUE_RANGE
│   │       ├── range_metrics.py RANGE
│   │       ├── ema.py          EMA20, EMA200
│   │       ├── rsi.py          RSI14
│   │       ├── atr.py          ATR14
│   │       ├── volatility.py   VOLATILITY_BUCKET
│   │       └── rsi_heikin_ashi.py RSI_HEIKIN_ASHI_V1 (15 features, warm-up 31)
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
│   ├── live_replay_v4/         80 testes V4 (foundation + repositories + RSI-HA + checkpoint)
├── tools/                      Scripts de auditoria e execucao
├── scripts/                    Scripts operacionais e diagnostico
├── deploy/                     Scripts de deploy
├── docs/                       Documentacao
├── config/                     Configuracao
├── database/                   Migrations SQL (shadow tables)
├── migrations/                 Migrations Python (shadow tables)
├── storage/                    Storage ativo
│   └── live_replay_v4/         V4 artifacts (initial_state, dry_run, persisted_sample, gate)
├── runtime/                    Logs e snapshots do motor
├── backups/                    Backups de codigo
├── mt5_connection.py           Dual-port RPyC bridge B3:11000/Forex:11001
├── run_b3.py                   Robo B3 (histórico, NÃO ativo — service não instalado, não reativar)
├── run_forex.py                Robo Forex (histórico, NÃO ativo — service inativo, não reativar)
├── services/asset_collector/    Coletor real (quando ativo) — ver §3.1-REAL, §4.8
├── services/candle_event_processor/  Dispatcher real (quando ativo) — ver §3.1-REAL, §4.8
├── start_bridges.sh            Inicializa RPyC bridges
├── start.sh / start_tunnel.sh  Startup scripts
├── cleanup_vps.sh              Manutencao VPS
├── diagnostic.sh               Diagnostico do sistema
├── requirements.txt            Dependencias Python
└── .env / settings.json        Configuracao de ambiente

AppAndroid/MaximusTrader/        ← Repo: AndreRambo/maximus-trader-android (main)
                                  App Android nativo (72 arquivos Kotlin)
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
│       │   │   ├── deeplink/DeepLinkHandler.kt, DeepLinkEventBus.kt
│       │   │   ├── design/MaximusColors.kt, MaximusTheme.kt, LogoPainter.kt
│       │   │   ├── device/DeviceInfoProvider.kt (interface)
│       │   │   ├── di/Modules.kt (Koin commonModule)
│       │   │   ├── notifications/FcmOpportunityPayload.kt (15 campos)
│       │   │   │              NotificationService.kt (interface)
│       │   │   ├── storage/DataStoreProvider.kt, TokenStorage.kt
│       │   │   ├── ui/ImageDecoder.kt (base64 → bitmap)
│       │   │   └── utils/DateTimeUtils.kt
│       │   ├── data/
│       │   │   ├── dto/PreferenceDto.kt, UserAssetDto.kt
│       │   │   ├── mapper/OpportunityMapper.kt (dead code)
│       │   │   ├── remote/PreferenceRemoteDataSource.kt,
│       │   │   │        UserAssetRemoteDataSource.kt
│       │   │   └── repository/AuthRepositoryImpl.kt,
│       │   │                  DeviceRepositoryImpl.kt,
│       │   │                  OpportunityRepositoryImpl.kt,
│       │   │                  PreferencesRepositoryImpl.kt
│       │   ├── domain/
│       │   │   ├── model/AuthModels.kt (LoginRequest, LoginResult sealed, UserDto)
│       │   │   │       OpportunityModels.kt (OpportunityDto 30 campos,
│       │   │   │        PaginatedResponse, Direction enum, RadarState enum,
│       │   │   │        EvidenceBundle/Chart/HitRates DTOs)
│       │   │   │       PreferenceModels.kt (UserPreferences 11 campos)
│       │   │   │       AccountType.kt (B3/FOREX/FULL enum)
│       │   │   ├── repository/AuthRepository.kt, DeviceRepository.kt,
│       │   │   │            OpportunityRepository.kt, PreferencesRepository.kt
│       │   │   └── usecase/GetHistoryUseCase.kt (dead code)
│       │   └── features/
│       │       ├── account/AccountScreen.kt, AccountViewModel.kt
│       │       ├── auth/LoginScreen.kt, LoginViewModel.kt,
│       │       │        ForgotPasswordScreen.kt, ForgotPasswordViewModel.kt
│       │       ├── dashboard/DashboardScreen.kt, DashboardViewModel.kt
│       │       ├── history/HistoryScreen.kt, HistoryViewModel.kt
│       │       ├── opportunities/OpportunityListScreen.kt,
│       │       │              OpportunityListViewModel.kt,
│       │       │              OpportunityCard.kt (reusable, fmtPrice()),
│       │       │              OpportunityDetailScreen.kt,
│       │       │              OpportunityDetailViewModel.kt
│       │       └── preferences/PreferencesScreen.kt, PreferencesViewModel.kt
│       └── androidMain/kotlin/br/com/maximustrade/signals/
│           ├── MainActivity.kt (Compose entry, deep link parse, FCM token)
│           ├── MainApplication.kt (Koin init: common + android modules)
│           ├── core/device/AndroidDeviceInfoProvider.kt
│           ├── firebase/MaximusFirebaseMessagingService.kt
│           │          AndroidNotificationService.kt
│           ├── service/MaximusForegroundService.kt (persistent notification)
│           ├── storage/AndroidSecureTokenStorage.kt
│           │                      AndroidDataStoreProvider.kt
│           └── widget/OpportunityWidget.kt (Glance)
│                      WidgetRefreshWorker.kt (WorkManager)
│                      WidgetDataStore.kt

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
│   │   lib/
│   │   │   api.ts (fetch wrapper com Bearer token)
│   │   │   symbolMap.ts (shared: apiSymbol, apiTimeframe, tsToUtc)
│   │   hooks/
│   │   │   useRealMarketData.ts (API polling candles/zones/study)
│   │   │   useSmcPerType.ts (SMC per-type fetch + normalize)
│   │   │   useReplayData.ts (replay: candles + context + Elliott/Wyckoff)
│   │   components/
│   │   │   CandlestickChart.tsx (Lightweight Charts v5 + replay mode)
│   │   │   BackgroundEffects.tsx (TickerTape, MouseGlow, Grid)
│   │   │   ReplayControls.tsx (Play/Pause/Speed/Seek)
│   │   │   ReplayDatePicker.tsx (custom DD/MM/AAAA HH:MM)
│   │   │   chart/smc/
│   │   │       smcTypes.ts, smcStyle.ts, smcRenderUtils.ts, smcNormalize.ts
│   │   │       renderers/ (7 Canvas renderers: FVG/OB/BPR/BOS/CHOCH/LIQ/SWING)
│   │   │       primitives/ (7 ISeriesPrimitive wrappers)
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
| Live-Replay V4 isolamento namespace | 2026-06-27 | 18 tabelas `winfut_lr_v4_*` no mesmo banco, mas isoladas por namespace. Nenhuma tabela legada alterada. Branch dedicada `feature/winfut-causal-live-replay-v4`. |
| Allowlist imutavel de tabelas | 2026-06-27 | V4_TABLE_ALLOWLIST (frozenset) — todo repository valida contra a lista. CandleSourceRepository read-only para market_candles. Rejeita qualquer tabela fora do namespace. |
| Repositories sem commit interno | 2026-06-27 | Todos os repositories recebem connection/cursor externo, nunca executam commit/rollback. ChunkTransactionService gerencia a transação. |
| ChunkTransactionService 13 passos | 2026-06-27 | Valida parent_run → timeframe_run → chunk BUILDING → persist indicators → reconciliation → hash validation → checkpoint → validations → chunk COMPLETED → update counts → append history. 9 pontos de fault injection. |
| Checkpoint atômico | 2026-06-27 | Checkpoint só é gravado após reconciliação e validação de hashes. State hash validado no load. Versão obrigatória. Restore rejeita hash divergente. |
| RSI_HEIKIN_ASHI_V1 | 2026-06-27 | 15 features contínuas + 3 textuais. Pipeline: EMA3 → RSI Wilder 14 → Heikin Ashi no espaço RSI → OHLC4 → EMA14 sinal. Warm-up 31 candles. Sem intrabar, sem futuro. |
| Primeira persistência controlada | 2026-06-27 | Run D1 com 250 candles → 1743 emissões → 35 chunks → 1 checkpoint. Status final INVALIDATED (INTEGRATION_PROBE_COMPLETE). Nenhum run ACTIVE. |
| Fix uirevision chart zoom | 2026-06-09 | figure_builder.py: uirevision fixo preservava zoom antigo — novos candles ficavam fora da tela. Fix: incluir ultimo candle no uirevision para resetar ao chegarem dados novos. |
| Fix timeframe alias persistence | 2026-06-09 | persistence.py load_latest_smc_engine_v2_state: query exata WHERE timeframe='M15' nao encontrava runs salvos com '15min'. Fix: IN (%s, %s) com alias map M5↔5min, M15↔15min, etc. |
| Fix timestamps FVG/OB/zonas SMC | 2026-06-09 | sync_v2.py chamava run_smc_engine_v2_local() sem timestamps= — todos origin_at/display_from/display_to ficavam NULL em raw_json. Resultado: figure_builder nao conseguia posicionar zonas no eixo X e nao renderizava nenhum retangulo. Fix: salvar ts_series = pd.Series(ohlc['timestamp'].values) antes do drop(columns=['timestamp']) e passar timestamps=ts_series ao pipeline. persistence.py: FvgV2/ObV2 reconstruidos do raw_json agora incluem display_from/display_to/mitigated_at; candles carregados ANTES da geracao de visual_overlays (era pos — ohlc_for_overlay ficava vazio). |
| Fix Forex robot modulo em cache | 2026-06-10 | smc-forex-robot.service iniciado as 21:24 carregou sync_v2.py sem o fix (arquivo modificado as 21:46). Modulo ficou em cache no processo — todos os FVG/OB Forex inseridos entre 21:24 e 01:00 tinham display_from=NULL. Fix: reiniciar o servico apos qualquer alteracao em infra/sync_v2.py. Regra: modificacoes em modulos importados por servicos systemd exigem restart do servico. |
| Backfill completo SMC V2 3 meses | 2026-06-10 | tools/full_backfill_v2.py: TRUNCATE das 10 tabelas smc_v2_*_shadow + recalculo com janela de 3 meses para todos os ativos (B3: 20k candles 2min / Forex 24/5: 50k / Crypto 24/7: 65k) × 5 TFs (2min, 5min, 15min, 4h, 1d — excluindo 1min). Forex robot parado durante backfill (inserts concorrentes evitados), reiniciado ao final. Todos os assets: WINFUT, WDOFUT, PETR4, VALE3, ITUB3, GOLD, BTCUSD, USDJPY, EURUSD, SILVER, ETHUSD. |
| VPS Monitor — metricas em tempo real | 2026-06-19 | VPS push (Python stdlib, /proc/) → POST HMAC 30s → Laravel Cache::put (TTL 5min). Admin le GET /admin/vps-metrics polling 15s. Sem inbound connections, sem psutil, sem migration. Sparklines SVG inline (sem lib chart externa). Monitora 8 servicos via pgrep: asset_collector, candle_event, mt5linux, sync_watcher, run_b3.py, run_forex.py, run_opportunity_scanner, Xvfb. |
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
| Replay refatorado Profit Pro style | 2026-06-20 | CandlestickChart com modo replay integrado (props opcionais). ReplayDatePicker customizado (DD/MM/AAAA HH:MM com auto-advance). Contexto antes do start (carrega dados anteriores ao range). Elliott/Wyckoff overlay + SMC toggles + IA Panel + Watchlist no replay. useReplayData busca Elliott/Wyckoff/Study. |
| Auditoria geral site (75+ issues) | 2026-06-20 | Auditoria completa frontend+backend: 8 bugs criticos, 7 bugs menores, 21 melhorias. Fixes: EnforcePlanLimits (activeLicense→active_license), CORS (env→config+OPTIONS), Login/Register (useNavigate), ReplayPage (useEffect+useRef cleanup), symbol mapping (shared utility), dead code (~900 linhas), console.logs (18 removidos). |
| Shared utility symbolMap.ts | 2026-06-20 | src/lib/symbolMap.ts centraliza apiSymbol, apiTimeframe, tsToUtc. Elimina mismatch entre useRealMarketData (BTCUSD) e useReplayData (BTC (BTCUSD)). 3 hooks importam do mesmo source. |
| CORS middleware fix | 2026-06-20 | env() → config('app.frontend_url') para compatibilidade com config:cache. Adicionado retorno 200 para OPTIONS preflight requests. |
| Admin CRUD completo | 2026-06-20 | AdminUsuariosPage: coluna Plano (nome+status+expiração), Créditos, toggle ativo/inativo, busca, exclusão. AdminPlanosPage: criar/editar/excluir planos. AdminLicencasPage: modal data (não prompt), suspender, reativar. |
| Shared admin components | 2026-06-20 | src/components/admin/: AdminModal (reutilizável), AdminField/AdminInput/AdminSelect, adminStyles (btnPri/btnSec/btnRed/badge), adminTypes (UserItem/PlanItem/LicenseItem). Elimina duplicação entre 3 páginas admin. |
| Backend admin endpoints | 2026-06-20 | POST /users (admin create), PUT /users/{id}/toggle-active, DELETE /users/{id}, POST/PUT/DELETE /plans. AdminController::users() retorna active_license + credit_balance. Fix duplicate apiResource('plans'). |

---

## 12. Módulos Congelados, Históricos e Propostas Não Implementadas

> Movido de §4 (Módulos Principais) em 2026-07-02 — estes módulos não fazem parte do fluxo ativo do sistema hoje. Numeração original das subseções (`### 4.x`) preservada para não quebrar referências cruzadas existentes no restante do documento.

### 4.1 SMC Engine V2 (`smc_engine_v2/`) — CONGELADO EM BACKUP, NÃO É CÓDIGO VIVO

> **Correção 2026-07-02:** este pacote **não existe mais como código funcional**.
> `technical_engine/smc_engine_v2/__init__.py` hoje contém apenas
> `raise ImportError("smc_engine_v2 foi movido para backup (Fase M-1C). Use
> technical_engine.smc_engine.")`. Backup completo em
> `/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2`. Nenhum
> arquivo do repositório importa de `technical_engine.smc_engine_v2.*`
> (confirmado). Os módulos reais e ativos (fvg.py, order_blocks.py,
> structure.py, liquidity.py, bpr.py, swings.py, sessions.py, retracements.py,
> previous_high_low.py, persistence.py, config.py) vivem em
> `technical_engine/smc_engine/` — ver §4.10/§4.11 para o pipeline batch e o
> motor incremental REAIS. A tabela abaixo é preservada só como referência
> histórica da era pré-V3.

| Modulo (histórico, pacote morto) | Componente | Testes |
|--------|-----------|--------|
| `pipeline.py` | Orquestrador (10 passos, swings_df compartilhado, raise_on_error) | — |
| `fvg.py` | FVG: 3-candle imbalance, mitigation 50%, vetorizado, displacement | 38 |
| `order_blocks.py` | OB: prev+wick, quality scoring (size+session), config-driven, **ob_subtype** (NORMAL/REJECTION/STACKED, NTSL parity) + confluência opt-in | 30 |
| `structure.py` | BOS/CHOCH: padrao 4 swings, close_break, 62% continuacao | 20 |
| `liquidity.py` | Liquidity: ATR-based cluster, swept detection | 10 |
| `bpr.py` | BPR: overlap FVG bull+bear, dedup >60%, quality scoring | 12 |
| `swings.py` | Swings: rolling window, sem forced alternation | 8 |
| `persistence.py` | 10 tabelas shadow, load/save, latest_candle_time auto | — |
| `config.py` | OBQualityConfig, BPRQualityConfig, SMCEngineV2Config | — |

**Status**: histórico. Equivalente vivo e ativo: `technical_engine/smc_engine/` (§4.10) + `technical_engine/smc_engine/incremental/` (§4.11).

### 4.2 Live-Replay V4 (`live_replay_v4/`)

| Modulo | Componente | Testes |
|--------|-----------|--------|
| `candle_clock.py` | CandleClock, CandleRef, CandleAvailability, CandleStatus | 6 |
| `contracts.py` | CanonicalTimeframe, IndicatorEmission, IndicatorSnapshot, IndicatorSpec | 5 |
| `config.py` | LiveReplayV4Config, TemporalConfig, VolatilityBucketConfig | 2 |
| `hashing.py` | deterministic_hash, canonical_json_dumps, compute_state_hash, compute_content_hash | 6 |
| `identity.py` | Structure identity + causal timestamp validation | 3 |
| `state.py` | SerializableState with versioned restore | 4 |
| `exceptions.py` | 18 exceptions: CausalContract, FutureData, HashConflict, RepositoryScope, FaultInjection, etc. | — |
| `validation.py` | Foundation validation + indicator validation | 3 |
| `schema.py` | Schema definition + validate_schema | 3 |
| `checkpoint.py` | CheckpointService: create, load_latest, validate_integrity | 4 |
| `dry_run_d1.py` | Dry-run D1: 250 candles, 7 indicators + RSI-HA, no writes | — |
| `persist_sample_d1.py` | First persistence: 250 candles → 1743 indicators, INVALIDATED status | — |
| **persistence/** | | |
| `allowlist.py` | V4_TABLE_ALLOWLIST (18 tables), validate_table_name, is_allowed | 7 |
| `connection.py` | get_connection with autocommit=False | 2 |
| `repositories.py` | 11 repositories: ParentRun, TimeframeRun, StatusHistory, Chunk, Checkpoint, Indicator, Reconciliation, Validation, Error, Artifact, CandleSource | 12 |
| `transactions.py` | ChunkTransactionService (13 steps, 9 fault injection points), chunk_transaction context manager | 5 |
| `mappings.py` | indicator_to_row mapping | 1 |
| **indicators/** | | |
| `engine.py` | IncrementalIndicatorEngine: batch_reference, snapshot, restore | 8 |
| `registry.py` | IndicatorRegistry + build_default_registry (7 basic + RSI-HA) | 2 |
| `true_range.py` | TRUE_RANGE indicator | 2 |
| `range_metrics.py` | RANGE indicator | 2 |
| `ema.py` | EMA20, EMA200 indicators | 2 |
| `rsi.py` | RSI14 indicator | 2 |
| `atr.py` | ATR14 indicator | 2 |
| `volatility.py` | VOLATILITY_BUCKET indicator | 2 |
| `rsi_heikin_ashi.py` | RSI_HEIKIN_ASHI_V1: 15 features, 10 events, warm-up 31, batch/prefix/chunk invariance | 17 |

**Status**: FASE V4_04 concluída — 80 testes (51 existentes + 29 novos). Branch: `feature/winfut-causal-live-replay-v4`.

**Princípios V4:**
- `shadow_only=True` — nunca escrever em tabelas oficiais
- Nenhum commit dentro de repository
- Allowlist imutável de tabelas V4
- Checkpoint atômico após reconciliação e hash validation
- RSI-HA: sem intrabar, sem futuro, sem quantil full-window
- Estado serializável, batch reference, prefix/chunk/resume invariance
- Hash determinístico, nenhum NaN/Infinity

**Tabelas V4 (18):**
```
winfut_lr_v4_parent_runs        winfut_lr_v4_timeframe_runs
winfut_lr_v4_status_history     winfut_lr_v4_chunks
winfut_lr_v4_checkpoints        winfut_lr_v4_indicators
winfut_lr_v4_reconciliation     winfut_lr_v4_validations
winfut_lr_v4_errors             winfut_lr_v4_artifacts
winfut_lr_v4_active_runs        winfut_lr_v4_structures
winfut_lr_v4_structure_events   winfut_lr_v4_opportunities
winfut_lr_v4_opportunity_evidence winfut_lr_v4_outcomes
winfut_lr_v4_trade_events       winfut_lr_v4_trade_simulations
```

**RSI_HEIKIN_ASHI_V1 Features:**
```
RSI_HA_OPEN, RSI_HA_HIGH, RSI_HA_LOW, RSI_HA_CLOSE
RSI_HA_SIGNAL_EMA14, RSI_HA_DIRECTION, RSI_HA_BODY
RSI_HA_RANGE, RSI_HA_BODY_RATIO, RSI_HA_UPPER_WICK
RSI_HA_LOWER_WICK, RSI_HA_SIGNAL_SLOPE, RSI_HA_DISTANCE_TO_SIGNAL
RSI_HA_ZONE, RSI_HA_CROSS_EVENT
```

**RSI_HEIKIN_ASHI_V1 Events:**
```
BULLISH_FLIP, BEARISH_FLIP, CROSS_ABOVE_SIGNAL, CROSS_BELOW_SIGNAL
RECOVER_30, LOSE_30, CROSS_ABOVE_50, CROSS_BELOW_50
CROSS_ABOVE_70, CROSS_BELOW_70
```

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

### 4.11 Volume Profile (POC/VA) — Proposta de Confluência (NÃO-IMPLEMENTADO)

> **Status: PROPOSTA. Não implementado. Aditivo e OFF por default quando implementado. Não altera detecção nem calibração WINFUT_M5.**

**Objetivo:** usar POC (Point of Control), VAH/VAL (Value Area High/Low), HVN (High Volume Node) e LVN (Low Volume Node) como camada de confluência sobre os módulos SMC, Wyckoff e Elliott já existentes.

**Definições:**

| Conceito | Descrição |
|----------|-----------|
| POC (Point of Control) | Preço com maior volume negociado no perfil do período |
| Value Area (VA) | Faixa que concentra ~70% do volume; VAH = topo, VAL = fundo. **Nota:** o indicador NTSL de referência usa `Percentual_VA = 40%`; o padrão de mercado é 70%; o valor é parametrizável |
| HVN (High Volume Node) | Nó de alto volume — região de aceitação/equilíbrio |
| LVN (Low Volume Node) | Nó de baixo volume — região de rejeição/movimento rápido |
| Naked/Virgin POC | POC de sessão anterior nunca retocado → forte ímã de preço |

**Princípio de design:**
- POC/VA é **filtro e alvo**, NUNCA gatilho isolado. A entrada continua nascendo da estrutura (sweep → CHoCH/BOS → mitigação em OB/FVG). POC/VA apenas pondera/valida a zona ou define o objetivo.
- **Aditivo e OFF por padrão:** (proposto) `enable_volume_profile_confluence = False` — sem alterar a detecção atual nem a calibração WINFUT_M5. Mesma filosofia do `ob_subtype` (aditivo).
- **Determinístico e causal:** o perfil de volume usa apenas candles com `available_at <= candle atual` (sem lookahead). Naked POC só conta sessões anteriores já fechadas.

**Regras de confluência por módulo (proposta):**

#### SMC

| Regra | Descrição |
|-------|-----------|
| OB coincide com HVN | Institucional defendeu volume ali → BOOST de `quality_score` (+10 a +15 pts, parametrizável; alinhado com `mtf_zone_boost` existente) |
| FVG aponta para POC/HVN | POC/HVN vira alvo preferencial do FVG (take-profit/target) |
| OB/FVG sobre LVN | Zona de baixa aceitação → possível penalidade leve de quality (parametrizável, default 0 na v1) |
| Naked POC | Alvo magnético de take-profit quando o trade caminha na direção do POC não-tocado |

#### Wyckoff

| Regra | Descrição |
|-------|-----------|
| POC = região de "cause" | Alta densidade de volume = causa construída ali. Mapeamento POC↔fase Wyckoff como **contexto** (não gera entrada) |
| LVN = spring/upthrust rápidos | Rompimentos por LVN tendem a ser velozes (pouca aceitação). Flag de contexto para spring/upthrust |

#### Elliott

| Regra | Descrição |
|-------|-----------|
| POC como alvo corretivo | Ondas B, 2, 4 tendem a "buscar valor" = retornar ao POC/VA. POC/VA como alvo de retração corretiva |
| Naked POC como extensão | Alvo de extensão de ondas impulsivas (objetivo de preço) |
| Nota | POC/VA é **contexto/alvo**, nunca gatilho de contagem de ondas |

**Artefatos propostos:**

| Artefato | Descrição |
|----------|-----------|
| (proposto) `incremental/components/volume_profile.py` | Calcula HistogramaVolume por candle (tick approximation), POC, VAH, VAL, HVN/LVN. Espelha lógica do indicador NTSL de referência (ApplyTZ, `Percentual_VA` parametrizável, naked/virgin POC do dia anterior) |
| (proposto) campos no payload OB/FVG | `poc_aligned` (bool), `volume_node` ("HVN"\|"LVN"\|"NEUTRAL"), `nearest_poc` (float), `dist_to_poc_atr` (float). Em `payload_json` (sem DDL), mesmo padrão do `ob_subtype` |
| (proposto) config | `enable_volume_profile_confluence: bool = False`, `va_percent: float = 0.70`, `hvn_quality_boost: float = 12.0`, `lvn_quality_penalty: float = 0.0`, `poc_target_priority: bool = True` |
| (proposto) confluência no pipeline | Passo opcional que cruza OB/FVG × volume nodes, análogo ao passo de confluência OB × BOS/CHOCH × Liquidity (hoje OFF por default) |

**Hipótese a validar (não afirmação):** a combinação POC/HVN com OBs existentes como hipótese de melhoria de seletividade — a ser verificada por backtest A/B em shadow-only antes de qualquer claim de performance.

---
