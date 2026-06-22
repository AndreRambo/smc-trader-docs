# Fluxo de Dados SMC — da Origem (VPS) até a Tela (Canvas)

**Data:** 2026-06-21
**Status:** Arquitetura implementada — tabelas separadas + renders separados + lazy loading

---

## Visão geral: tudo separado por tipo

```
┌──────────────────────────────────────────────────────────────────┐
│ VPS (Python/MT5)                                                 │
│                                                                  │
│ 9 shadow tables — 1 por tipo                                     │
│   _fvg_shadow  _order_blocks_shadow  _bos_choch_shadow          │
│   _liquidity_shadow  _swings_shadow  _sessions_shadow            │
│   _retracements_shadow  _previous_high_low_shadow  _bpr_shadow   │
│                                                                  │
│ sync_v2.py: sync_v2_shadow_tables()                              │
│   → POST /api/sync/tables/push (raw rows por tabela)             │
│   → fallback: POST /api/sync/zones (unificado legado)            │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Hostinger (Laravel/MySQL)                                        │
│                                                                  │
│ 10 tabelas smc_v2_* — 1 por tipo                                 │
│   smc_v2_runs  smc_v2_fvg  smc_v2_order_blocks                  │
│   smc_v2_bos_choch  smc_v2_liquidity  smc_v2_swings              │
│   smc_v2_sessions  smc_v2_previous_high_low                      │
│   smc_v2_retracements  smc_v2_bpr                                │
│                                                                  │
│ SyncTableController@push — recebe rows por tabela                │
│ SmcZoneService — lê 9 tabelas, transforma em ApiZone[]           │
│ MarketDataController@zones — feature flag SMC_USE_NEW_TABLES     │
│                                                                  │
│ ⚠️ SMC_USE_NEW_TABLES=false → usa sync_zones (legado unificado)  │
│    Quando true → usa smc_v2_* (separado por tipo)                │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Frontend (React/TypeScript)                                      │
│                                                                  │
│ 7 normalizers — 1 por tipo (fvg/ob/bpr/bos/choch/liq/swing)     │
│   Cada um filtra seu tipo + buildItem() → RenderableSmcItem[]    │
│                                                                  │
│ 7 renderers Canvas — 1 por tipo (IPrimitivePaneRenderer)         │
│   FvgRenderer  ObRenderer  BprRenderer                          │
│   BosRenderer  ChochRenderer  LiquidityRenderer  SwingRenderer   │
│                                                                  │
│ 7 primitives — 1 por tipo (ISeriesPrimitive<Time>)               │
│   FvgPrimitive  ObPrimitive  BprPrimitive                        │
│   BosPrimitive  ChochPrimitive  LiquidityPrimitive  SwingPrimitive│
│                                                                  │
│ Cada pipeline: debug counter + budget + LabelPlacer independente │
│ Toggles individuais na toolbar: [FVG][OB][BPR][BOS][CHOCH][LIQ]  │
│ Lazy loading: scroll perto do início carrega +500 candles        │
└──────────────────────────────────────────────────────────────────┘
```

---

## 1. VPS — 9 shadow tables (origem)

### Tabelas MySQL (1 por tipo de zona)

```
technical_engine_smc_v2_fvg_shadow
technical_engine_smc_v2_order_blocks_shadow
technical_engine_smc_v2_bos_choch_shadow
technical_engine_smc_v2_liquidity_shadow
technical_engine_smc_v2_swings_shadow
technical_engine_smc_v2_sessions_shadow
technical_engine_smc_v2_retracements_shadow
technical_engine_smc_v2_previous_high_low_shadow
technical_engine_smc_v2_bpr_shadow
```

### Pipeline (`infra/mt5_core.py` → `infra/sync_v2.py`)

```
run_b3.py → market_candles → mt5_core.py → Pipeline SMC V2
  │
  ├─ Detecta candle novo → processa timeframes: 2min, 5min, 15min, 1h, 4h, 1d
  ├─ Persiste resultados nas 9 shadow tables (filtrados por run_id)
  │
  └─ sync_v2.py: run_v2_pipeline_and_sync()
       │
       ├─ 1. sync_v2_shadow_tables() — NOVO: raw rows por tabela
       │     └─ POST /api/sync/tables/push { ticker, tables: { fvg: [...], ob: [...], ... } }
       │
       └─ 2. sync_v2_shadow_zones() — LEGADO (fallback)
             ├─ Lê 9 shadow tables filtradas por run_id
             ├─ _build_fvg_zones(), _build_ob_zones(), ... — transforma rows → dicts unificados
             └─ POST /api/sync/zones { ticker, timeframe, zones: [...], replace: true }
```

### Funções em `infra/sync_v2.py`

| Função | Linha | Descrição |
|--------|-------|-----------|
| `_build_fvg_zones()` | 79 | FVG rows → zone dicts |
| `_build_ob_zones()` | 113 | Order Block rows → zone dicts |
| `_build_bos_choch_zones()` | 154 | BOS/CHOCH rows → zone dicts |
| `_build_liquidity_zones()` | 192 | Liquidity rows → zone dicts |
| `_build_swing_zones()` | 229 | Swing rows → zone dicts |
| `_build_sessions_zones()` | 261 | Session rows → zone dicts |
| `_build_retracement_zones()` | 295 | Retracement rows → zone dicts |
| `_build_pdh_pdl_zones()` | 327 | PDH/PDL rows → zone dicts |
| `_build_bpr_zones()` | 384 | BPR rows → zone dicts |
| `sync_v2_shadow_tables()` | 457 | **NOVO** — raw rows por tabela → `/sync/tables/push` |
| `sync_v2_shadow_zones()` | 551 | **LEGADO** — zonas unificadas → `/sync/zones` |
| `run_v2_pipeline_and_sync()` | 700 | Orquestração: pipeline → persist → sync |

---

## 2. Hostinger — 10 tabelas `smc_v2_*` (backend)

### Tabelas MySQL (1 por tipo)

```
smc_v2_runs                  ← metadados de cada execução do pipeline
smc_v2_fvg                   ← Fair Value Gaps
smc_v2_order_blocks          ← Order Blocks
smc_v2_bos_choch             ← Break of Structure / Change of Character
smc_v2_liquidity             ← Liquidity zones
smc_v2_swings                ← Swing points
smc_v2_sessions              ← Session ranges (Asia/London/NY)
smc_v2_previous_high_low     ← Previous Day High/Low
smc_v2_retracements          ← Retracement zones
smc_v2_bpr                   ← Balanced Price Ranges
```

### Models Eloquent (`app/Models/Smc/`)

```
SmcV2Run.php            SmcV2Fvg.php           SmcV2OrderBlock.php
SmcV2BosChoch.php       SmcV2Liquidity.php     SmcV2Swing.php
SmcV2Session.php        SmcV2PreviousHighLow.php SmcV2Retracement.php
SmcV2Bpr.php
```

### Recebimento (sync)

| Endpoint | Controller | Status |
|----------|-----------|--------|
| `POST /api/sync/tables/push` | `SyncTableController@push` | ✅ Implementado — recebe `{ ticker, tables: { fvg: [rows], ob: [rows], ... } }` |
| `POST /api/sync/zones` | `SyncController@zones` | ✅ Legado — recebe `{ ticker, zones: [...] }` unificado |

### Leitura (API)

| Endpoint | Controller | Descrição |
|----------|-----------|-----------|
| `GET /api/zones/{ticker}` | `MarketDataController@zones` | **Principal** — retorna ApiZone[] com feature flag |
| `GET /api/zones/{ticker}/smc/{type}` | `MarketDataController@smcByType` | **Novo** — zonas filtradas por tipo (fvg/ob/bos/etc.) |
| `GET /api/candles/{ticker}` | `MarketDataController@candles` | Candles + EMA/RSI/ATR. Suporta `limit` + `offset` (lazy loading) |

### Feature flag (`config/smc.php`)

```php
'use_new_tables' => env('SMC_USE_NEW_TABLES', false)
```

- `false` (hoje): `MarketDataController@zones` lê da tabela `sync_zones` (legado unificado)
- `true` (futuro): `MarketDataController@zones` lê das 10 `smc_v2_*` via `SmcZoneService`

### `SmcZoneService` (`app/Services/SmcZoneService.php`)

```php
getZones(ticker, timeframe, limit)
  ├─ localiza o último run_id
  ├─ lê FVG da tabela smc_v2_fvg → transforma em ApiZone[]
  ├─ lê OB da tabela smc_v2_order_blocks → transforma em ApiZone[]
  ├─ lê BOS/CHOCH da tabela smc_v2_bos_choch → transforma em ApiZone[]
  ├─ ... (6 outras tabelas)
  └─ retorna ApiZone[] unificado
```

---

## 3. Frontend — 7 pipelines de renderização

### Arquitetura de arquivos

```
components/chart/smc/
  smcTypes.ts              — interfaces por tipo (FvgRow, ObRow, BosChochRow, etc.)
  smcStyle.ts              — SMC_COLORS com cores por tipo e direção
  smcRenderUtils.ts        — xFromTime, yOfPrice, LabelPlacer, drawLabel
    smcNormalize.ts          — wrapper: delega para os 7 normalizers individuais

    normalizers/ (9 arquivos)    — 1 por tipo + shared utils + barrel
      normalizerUtils.ts         — helpers: toUnix, toNum, buildItem, resolveTimeRange, etc.
      index.ts                   — barrel export
      fvgNormalizer.ts           — [FVG] raw=N items=N skipped=N
      obNormalizer.ts            — [OB] raw=N items=N skipped=N
      bprNormalizer.ts           — [BPR] raw=N items=N skipped=N
      bosNormalizer.ts           — [BOS] raw=N items=N skipped=N
      chochNormalizer.ts         — [CHOCH] raw=N items=N skipped=N
      liqNormalizer.ts           — [LIQUIDITY] raw=N items=N skipped=N
      swingNormalizer.ts         — [SWING] raw=N items=N skipped=N

  renderers/               — 1 por tipo: Canvas 2D ISeriesPrimitive + labels
    FvgRenderer.ts         — retângulos com preenchimento
    ObRenderer.ts          — retângulos com borda
    BprRenderer.ts         — retângulos com tracejado
    BosRenderer.ts         — linhas horizontais + label no ponto médio
    ChochRenderer.ts       — linhas tracejadas + label no ponto médio
    LiquidityRenderer.ts   — linhas + labels
    SwingRenderer.ts       — linhas + labels (default: visible=false)

  primitives/              — 1 por tipo: wrapper ISeriesPrimitive<Time>
    FvgPrimitive.ts        — PaneView + FvgRenderer
    ObPrimitive.ts         — PaneView + ObRenderer
    BprPrimitive.ts        — PaneView + BprRenderer
    BosPrimitive.ts        — PaneView + BosRenderer
    ChochPrimitive.ts      — PaneView + ChochRenderer
    LiquidityPrimitive.ts  — PaneView + LiquidityRenderer
    SwingPrimitive.ts      — PaneView + SwingRenderer
```

### Fluxo de renderização

```
useSmcPerType(symbol, timeframe)
  │
  ├─ fetch GET /api/zones/{ticker}?timeframe=...&limit=1500&include_mitigated=1&include_structure=1
  ├─ recebe ApiZone[]
  │
  ▼
normalizeSmcZones(apiZones, { lastCandleTime })
  │
  ├─ classifica por tipo: FVG | OB | BPR | BOS | CHOCH | LIQUIDITY | SWING
  ├─ cada normalizer gera { fromTime, toTime, top, bottom, color, label, kind }
  │
  ▼
SmcPerTypeData = { fvg: [...], ob: [...], bpr: [...], bos: [...], choch: [...], liquidity: [...], swing: [...] }
  │
  ▼
CandlestickChart.tsx → updateSmcPrimitives()
  │
  ├── prims.fvg.setData(smcPerType.fvg)       → FvgRenderer.draw()       Canvas 2D
  ├── prims.ob.setData(smcPerType.ob)         → ObRenderer.draw()        Canvas 2D
  ├── prims.bpr.setData(smcPerType.bpr)       → BprRenderer.draw()       Canvas 2D
  ├── prims.bos.setData(smcPerType.bos)       → BosRenderer.draw()       Canvas 2D
  ├── prims.choch.setData(smcPerType.choch)   → ChochRenderer.draw()     Canvas 2D
  ├── prims.liq.setData(smcPerType.liquidity) → LiquidityRenderer.draw() Canvas 2D
  └── prims.swing.setData(smcPerType.swing)   → SwingRenderer.draw()     Canvas 2D
```

### Labels

- **Zonas (FVG, OB, BPR):** label no `fromTime` (início da zona)
- **Estruturas (BOS, CHOCH, LIQ, SWING):** label no ponto médio `(fromTime + toTime) / 2`
- **LabelPlacer:** greedy 2D collision avoidance independente por renderer

### Toggles na toolbar

```
[FVG] [OB] [BPR] [BOS] [CHOCH] [LIQ] [SWING]  |  [Elliott] [Wyckoff]
```

Cada toggle controla `prims.{type}.setOptions({ visible: true/false })` + `requestRedraw()`.

### Console debug (cada normalizer loga)

```
[FVG] raw=281 items=281 skipped=3
[OB] raw=142 items=142 skipped=0
[BOS] raw=87 items=87 skipped=2
...
[SMC:per-type] total=612 fvg=278 ob=142 bos=85 choch=23 liq=41 swing=18 bpr=25
```

### Todos os arquivos do frontend que gerenciam zonas e render

```
hooks/
  useSmcPerType.ts                     — fetch /api/zones → normalize → split por tipo
  useRealMarketData.ts                 — fetch /api/candles → candles/EMA/RSI/Elliott/Wyckoff + lazy loading
  useReplayData.ts                     — fetch histórico para replay (candles + zones + Elliott + Wyckoff)

components/
  CandlestickChart.tsx                 — chart container + 7 primitives + 7 toggles + tooltip + lazy loading
  ReplayControls.tsx                   — controles de replay (play/pause/speed/seek)
  ReplayDatePicker.tsx                 — date picker custom DD/MM/AAAA HH:MM
  BackgroundEffects.tsx                — TickerTape, MouseGlow, GlowOrbs

  chart/smc/
    smcTypes.ts                        — interfaces: FvgRow, ObRow, BosChochRow, etc.
    smcStyle.ts                        — SMC_COLORS: cores por tipo e direção
    smcRenderUtils.ts                  — xFromTime, yOfPrice, clampX, LabelPlacer, drawLabel
    smcNormalize.ts                    — wrapper: delega para os 7 normalizers individuais

    renderers/ (7 arquivos)            — 1 por tipo: Canvas 2D draw() + labels
      FvgRenderer.ts                   — retângulos com preenchimento
      ObRenderer.ts                    — retângulos com borda
      BprRenderer.ts                   — retângulos com tracejado
      BosRenderer.ts                   — linhas + label no ponto médio
      ChochRenderer.ts                 — linhas tracejadas + label no ponto médio
      LiquidityRenderer.ts             — linhas + labels
      SwingRenderer.ts                 — triângulos coloridos (verde=HH/HL, vermelho=LH/LL)

    primitives/ (7 arquivos)           — 1 por tipo: ISeriesPrimitive<Time> wrapper
      FvgPrimitive.ts                  — PaneView + FvgRenderer
      ObPrimitive.ts                   — PaneView + ObRenderer
      BprPrimitive.ts                  — PaneView + BprRenderer
      BosPrimitive.ts                  — PaneView + BosRenderer
      ChochPrimitive.ts                — PaneView + ChochRenderer
      LiquidityPrimitive.ts            — PaneView + LiquidityRenderer
      SwingPrimitive.ts                — PaneView + SwingRenderer

pages/
  ChartPage.tsx                        — gráfico principal (live) + watchlist + IA panel
  ReplayPage.tsx                       — replay histórico com mesma engine do gráfico
```

**Total: ~30 arquivos** para o sistema per-type (9 normalizers + 7 renderers + 7 primitives + 7 shared/hooks/pages).

---

## 4. Indicadores e candles

```
GET /api/candles/{ticker}?timeframe=5min&limit=500&offset=0
  │
  ▼
useRealMarketData(symbol, timeframe)
  │
  ├── lwCandles      → candleSeries.setData()         (CandlestickSeries)
  ├── lwEMA20        → ema20Series.setData()          (LineSeries)
  ├── lwEMA200       → ema200Series.setData()         (LineSeries)
  ├── lwRSI          → rsiSeries.setData()            (LineSeries, pane separado)
  ├── elliott        → drawOverlay() SVG              (linhas diagonais + labels)
  ├── wyckoffRanges  → drawOverlay() SVG + markers    (retângulos tracejados)
  └── loadMore()     → lazy loading (scroll carrega +500 candles)
```

### Lazy Loading

```
Frontend carrega 500 candles iniciais
  → scroll para esquerda (perto do início)
  → detecta: range.from <= oldestTime + 3600
  → fetch /api/candles?limit=500&offset=500
  → prepend 500 candles anteriores
  → reprocessa EMA/RSI/MACD de ~1000 candles
  → scroll continua suave (sem jump)
  → repete até não haver mais dados
```

Backend suporta: `GET /api/candles/{ticker}?limit=500&offset=N` + `meta.hasMore`

---

## 5. Correções aplicadas (sessão 2026-06-20/21)

### Bugs críticos corrigidos

| Bug | Arquivo | Fix |
|-----|---------|-----|
| EnforcePlanLimits chamava método inexistente | `EnforcePlanLimits.php` | `$user->activeLicense()` → `$user->active_license` |
| CORS usava `env()` + não tratava OPTIONS | `Cors.php` | `config()` + retorno 200 para OPTIONS |
| Login/Register faziam full page reload | `Login.tsx`, `Register.tsx` | `window.location.href` → `useNavigate()` |
| ReplayPage side effect durante render | `ReplayPage.tsx` | Movido para `useEffect` |
| ReplayPage memory leak (intervals) | `ReplayPage.tsx` | `useState` → `useRef` + cleanup no unmount |
| Symbol mapping mismatch | `lib/symbolMap.ts` | Shared utility unificado para 3 hooks |
| Zonas não renderizavam (xFromTime) | `smcRenderUtils.ts` | Restaurado fallback de clamp com `timeToIndex` |
| Labels empilhadas no canto esquerdo | `smcRenderUtils.ts` | drawLabel usa midpoint para estruturas |
| FVG/OB não mostravam mitigação | renderers | Mitigadas desenhadas com opacidade reduzida |
| SwingRenderer direção invertida | `SwingRenderer.ts` | HH/LH → triângulo ↓, HL/LL → triângulo ↑ |
| BOS neutro mesma cor de bullish | `smcStyle.ts` | Neutro → cinza `#8A84A8` |
| LiquidityRenderer não filtrava mitigadas | `LiquidityRenderer.ts` | Adicionado `.filter(status !== 'mitigated')` |
| Elliott waves EL1-EL8 sequential | `context_states.py` | Labels semânticos W1-W5/WA-WC + Regra 3 |
| 500 erro /api/zones | `MarketDataController.php` | Try-catch com retorno graceful |
| Dashboard isActive root match | `Dashboard.tsx` | Match exato para `/admin` |
| AdminUsuariosPage setInterval shadow | `AdminUsuariosPage.tsx` | Renomeado para `billingInterval` |
| AdminEvidenceDetail JSON.parse | `AdminEvidenceDetail.tsx` | Try-catch adicionado |
| Landing preço duplicado | `Landing.tsx` | Preço anual duplicado removido |
| FcmTestController credential path | `FcmTestController.php` | `credentials_path` removido da resposta |

### Melhorias de código

| Melhoria | Detalhes |
|----------|----------|
| Shared utility `symbolMap.ts` | apiSymbol, apiTimeframe, tsToUtc — elimina duplicação em 3 hooks |
| Admin shared components | AdminModal, AdminField, adminStyles, adminTypes |
| Dead code removido (~900 linhas) | useMarketData, useMarketWebSocket, SmcPaneRenderer, 6 normalizers legados |
| Console.logs removidos (18) | 7 renderers, 2 hooks, 1 normalizer |
| Frozen objects | SMC_COLORS e SMC_STYLE com `Object.freeze` |
| withAlpha validation | Validação de hex input length |
| Backend: offset para lazy loading | `GET /api/candles?limit=500&offset=N` + `meta.hasMore` |
| Frontend: loadMore no scroll | `useRealMarketData` + `CandlestickChart` com listener em `visibleTimeRangeChange` |

### Admin CRUD implementado

| Página | O que foi adicionado |
|--------|---------------------|
| AdminUsuariosPage | Coluna "Plano" (nome+status+expiração), Créditos, Criado em, Toggle ativo/inativo, Busca, Exclusão |
| AdminPlanosPage | CRUD completo (criar/editar/excluir), Coluna "Licenças" |
| AdminLicencasPage | Modal date picker (não prompt), Suspender, Reativar, Busca |
| Backend | POST/PUT/DELETE /users, PUT /users/{id}/toggle-active, POST/PUT/DELETE /plans |

### Replay page

| Feature | Status |
|---------|--------|
| Date picker custom (DD/MM/AAAA HH:MM) | ✅ Implementado |
| Contexto antes do start | ✅ Implementado |
| Chart idêntico ao /admin/grafico | ✅ Implementado |
| Elliott/Wyckoff overlay | ✅ Implementado |
| SMC toggles individuais | ✅ Implementado |
| IA Panel + Watchlist sidebar | ✅ Implementado |
| Replay v2 (integrar no CandlestickChart) | ⬜ Pendente |

---

## 6. Pendências

### Críticas (produção)

| # | Pendência | Status |
|---|-----------|--------|
| 1 | **Deploy backend** — rotas /sync/tables/push e /api/zones/{ticker}/smc/{type} não subiram (alterados:0) | ⬜ |
| 2 | **Ativar SMC_USE_NEW_TABLES=true** — após deploy backend + sync popular tabelas | ⬜ |
| 3 | **Restart run_b3.py** — após Phase 6, para ativar coleta 1H | ⬜ |

### Melhorias (código)

| # | Pendência | Prioridade |
|---|-----------|-----------|
| 4 | **Replay v2** — integrar replay no CandlestickChart (prop replay, eliminar ReplayChart duplicado de ~160 linhas) | Alta |
| 5 | **Remover código morto** — useSmcPerType.ts se tornou redundante (CandlestickChart usa normalizeSmcZones diretamente) | Média |
| 6 | **Limpar warnings TS** — imports não usados (build passa, só warnings) | Baixa |
| 7 | **Admin: usar api wrapper** — Admin pages usam raw fetch em vez de lib/api.ts | Média |
| 8 | **Admin: adicionar paginação** — Users e Licenses carregam tudo de uma vez | Média |

---

## 7. Métricas da sessão

| Métrica | Valor |
|---------|-------|
| Bugs críticos corrigidos | 17 |
| Melhorias de código | 10+ |
| Admin CRUD implementado | 3 páginas + backend |
| Arquivos criados | 8 (shared components + symbolMap + ReplayDatePicker) |
| Arquivos deletados | ~900 linhas dead code |
| Console.logs removidos | 18 |
| Backend novos endpoints | 6 (users CRUD + plans CRUD + toggle-active) |
| Lazy loading | Implementado (scroll → +500 candles) |
| Build | OK (TypeScript 0 erros, Vite 15s) |
