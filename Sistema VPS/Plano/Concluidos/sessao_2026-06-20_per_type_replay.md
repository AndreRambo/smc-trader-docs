# Sessão 2026-06-20 — Per-Type Renderers + Replay + 1H + Tabelas Replicadas

**Status:** FRONTEND FUNCIONANDO | Plano de refatoração do replay criado (calm-rolling-codd.md)
**Último commit VPS:** `ce493ec` — backup(vps): sincronização automática 2026-06-20
**Último commit MaximusTrader:** `1123e2a` — sync: automático 2026-06-20 22:12

---

## 1. Arquitetura Per-Type SMC Renderers

### Problema
SmcPaneRenderer unificado — debug impossível, budget compartilhado, toggle global.

### Solução
7 pipelines independentes, cada tipo com seu próprio normalizer + renderer + primitive.

### Arquivos criados (24 novos)
```
frontend/src/components/chart/smc/
  smcRenderUtils.ts              — xFromTime, yOfPrice, LabelPlacer, drawLabel
  smcTypes.ts                    — atualizado com FvgRow, ObRow, etc.
  smcStyle.ts                    — + SMC_COLORS
  
  normalizers/ (6 arquivos)      — fvgNormalizer, obNormalizer, bosNormalizer,
                                   liqNormalizer, swingNormalizer, bprNormalizer

  renderers/ (7 arquivos)        — FvgRenderer, ObRenderer, BprRenderer, BosRenderer,
                                   ChochRenderer, LiquidityRenderer, SwingRenderer
                                   (todos com labels + debug counters)

  primitives/ (7 arquivos)       — FvgPrimitive, ObPrimitive, BprPrimitive,
                                   BosPrimitive, ChochPrimitive, LiquidityPrimitive,
                                   SwingPrimitive
```

### Arquivos modificados
```
frontend/src/components/CandlestickChart.tsx  — 7 primitives + toggles individuais
frontend/src/hooks/useSmcPerType.ts           — fetch unified /api/zones + split per-type
```

### Labels
- Zonas (FVG, OB, BPR): label no `fromTime`
- Estruturas (BOS, CHOCH, LIQ): label no ponto médio `(from+to)/2`
- Anti-colisão: LabelPlacer por renderer

### Admin UI
Toolbar com toggles individuais: `[FVG][OB][BPR][BOS][CHOCH][LIQ][SWING]`

---

## 2. Replay com Dados Reais (/admin/replay)

### Problema
ReplayPage antiga usava dados sintéticos (Math.random()) em canvas nativo.

### Implementação atual (v1 standalone)
ReplayChart standalone (função interna no ReplayPage.tsx) com lightweight-charts +
mesmas per-type primitives. Chart monta UMA vez, candleSeries.setData() a cada step.

### Arquivos (v1)
```
frontend/src/pages/ReplayPage.tsx         — reescrito (controles + ReplayChart interno)
frontend/src/components/ReplayControls.tsx — Play/Pause/Speed/Seek
frontend/src/hooks/useReplayData.ts       — fetch histórico + filtro client-side
backend/.../MarketDataController.php      — + from/to no endpoint candles
```

### Funcionamento (v1)
1. DateRangePicker (from → to)
2. Fetch candles (GET /api/candles?limit=2000) + zonas (GET /api/zones?limit=3000)
3. Filtro client-side por data
4. Chart monta UMA vez, candleSeries.setData() a cada step
5. Zonas filtradas por display_from <= currentTime

### Limitações da v1
- **ReplayChart duplica ~160 linhas do CandlestickChart** (criação do chart, primitives, EMA, RSI)
- Sem Elliott/Wyckoff (não incluído no ReplayChart)
- Sem tooltip de crosshair
- `smcNormalize` chamado inline (não usa `useSmcPerType`)
- Toggles SMC não funcionam no replay

### Plano v2 (calm-rolling-codd.md)
**Adicionar prop `replay` ao CandlestickChart existente.** Zero duplicação.

```
ReplayPage
  ├── DateRangePicker
  ├── ReplayControls
  └── CandlestickChart  ← O MESMO COMPONENTE do /admin/grafico
        └── prop replay={{ candles, zones, currentIndex }}
```

Quando `replay` está presente:
- NÃO chama `useRealMarketData` (usa dados fornecidos)
- NÃO chama `useSmcPerType` (usa `normalizeSmcZones` nos zones fornecidos)
- `useEffect` de atualização chama `setData(candles.slice(0, currentIndex+1))`
- Zonas são re-normalizadas a cada step (filtrando por `display_from <= currentTime`)
- Chart NÃO é destruído/recriado — apenas dados são atualizados via `setData()`
- Elliott/Wyckoff, tooltip, toggles — tudo funciona igual ao gráfico live

### Etapas do plano v2
1. Adicionar interface `ReplayProps` e prop `replay?: ReplayProps` ao CandlestickChart
2. Condicionar data source: `replay ? replay.candles : liveData.lwCandles`
3. Atualizar useEffects para respeitar `replay` (setData progressivo)
4. Reescrever ReplayPage usando `<CandlestickChart replay={...} />` (remover ReplayChart interno)
5. Buscar Elliott/Wyckoff via endpoint normal no useReplayData

---

## 3. Timeframe 1H Adicionado

### Arquivos modificados
```
run_b3.py          — + TIMEFRAME_H1 import + loop
infra/mt5_core.py  — + 1h em DEFAULT_RELOAD_TIMEFRAMES, V2_TIMEFRAMES,
                     _resolve_tf_strings (tf_map + canonical)
infra/sync_v2.py   — + 1h em _TF_ALT (↔H1), _CANDLE_LIMITS (800 velas),
                     EW _CANDLE_LIMITS
tools/resync_winfut_full.py — + "1h" nos loops de pipeline e EW
```

### Pendente
Restart do run_b3.py (após Phase 6 terminar) para ativar coleta live de 1H.

---

## 4. Replicação 9 Tabelas Shadow no Hostinger

### Backend (11 migrations + 10 models)
```
database/migrations/2026_06_20_000001-000011_*.php  — 10 tabelas smc_v2_*
app/Models/Smc/SmcV2*.php (10 models)               — Eloquent per tabela
app/Http/Controllers/Api/SyncTableController.php     — POST /api/sync/tables/push
app/Services/SmcZoneService.php                      — leitura 9 tabelas → ApiZone[]
app/Http/Controllers/Api/MarketDataController.php    — + smcByType() + canonicalTimeframe()
routes/api.php                                       — + GET /api/zones/{ticker}/smc/{type}
config/smc.php                                       — feature flag SMC_USE_NEW_TABLES
```

### VPS
```
infra/sync_v2.py — + sync_v2_shadow_tables() + _row_to_serializable()
                   sync_v2_shadow_zones() dual-path (novo → fallback antigo)
```

### Status
- Tabelas criadas no Hostinger (migrations rodaram)
- Novo endpoint /sync/tables/push NÃO deployado (alterados: 0 no deploy.sh)
- Frontend NÃO depende das novas tabelas — usa endpoint unificado existente
- Feature flag SMC_USE_NEW_TABLES=false (não ativado)

---

## 5. Scripts VPS

### tools/resync_winfut_full.py
Re-sync completo WINFUT: pipeline SMC V2 + Elliott/Wyckoff + candles.
4 etapas: limpar freshness → pipeline zonas → Elliott/Wyckoff → candles em lotes.

---

## 6. Relatórios

### docs_geral/Sistema VPS/Plano/Concluidos/
- `arquitetura_replicacao_9_tabelas_smc.md` — plano original (2026-06-20)
- `arquitetura_per_type_renderers.md` — arquitetura final implementada (2026-06-20)

### docs_geral/ARQUITETURA_OFICIAL.md
Atualizado com per-type renderers, replay, 1H, tabelas replicadas.

### Planos pendentes
- `~/.claude/plans/calm-rolling-codd.md` — Plano: Replay com CandlestickChart Real (sem duplicação). Propõe adicionar prop `replay` ao CandlestickChart existente, eliminando o ReplayChart duplicado de ~160 linhas.

---

## 7. Pendências

1. ⬜ **Refatorar Replay → CandlestickChart** — plano `calm-rolling-codd.md`: adicionar prop `replay` ao CandlestickChart, eliminar ReplayChart duplicado, ganhar Elliott/Wyckoff/tooltip/toggles de graça
2. ⬜ **Deploy backend** — rotas /sync/tables/push e /api/zones/{ticker}/smc/{type} não subiram (alterados:0)
3. ⬜ **Ativar SMC_USE_NEW_TABLES** — após deploy backend + sync popular tabelas
4. ⬜ **Restart run_b3.py** — após Phase 6 terminar, para ativar coleta 1H
5. ⬜ **Replay Elliott/Wyckoff** — resolvido pelo item 1 (herda do CandlestickChart)
6. ⬜ **Limpar warnings TS** — imports não usados (build passa, só warnings)
