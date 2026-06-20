# Arquitetura Per-Type: Renderizadores Independentes por Tipo SMC

**Data:** 2026-06-20
**Status:** Implementado e funcionando em produção
**Princípio:** Cada tipo de zona SMC tem seu próprio pipeline isolado — normalizer + renderer + primitive. Se FVG quebra, os outros 6 continuam.

---

## Motivação

A abordagem unificada anterior (1 `SmcPaneRenderer` para todos os 9 tipos) causava:
- Debug impossível: `items=0` sem saber qual tipo falhou
- Bug em OB afetava FVG (budget compartilhado de `maxActiveZones`)
- Toggle SMC ON/OFF global, sem controle individual
- Labels misturadas entre tipos

**Solução:** 7 pipelines independentes, cada um dono do seu próprio normalizer, renderer, primitive, budget, e contadores de debug.

---

## Arquitetura Final

```
GET /api/zones/{ticker}  (endpoint unificado existente — zero alterações)
         │
         │ useSmcPerType.ts
         │ 1 fetch → normalizeSmcZones() → split por type
         │
         ├──► RenderableSmcItem[] (FVG)  ──► FvgPrimitive  ──► FvgRenderer  [FVG:draw]
         ├──► RenderableSmcItem[] (OB)   ──► ObPrimitive   ──► ObRenderer   [OB:draw]
         ├──► RenderableSmcItem[] (BPR)  ──► BprPrimitive  ──► BprRenderer  [BPR:draw]
         ├──► RenderableSmcItem[] (BOS)  ──► BosPrimitive  ──► BosRenderer  [BOS:draw]
         ├──► RenderableSmcItem[] (CHOCH)──► ChochPrimitive──► ChochRenderer[CHOCH:draw]
         ├──► RenderableSmcItem[] (LIQ)  ──► LiqPrimitive  ──► LiqRenderer  [LIQ:draw]
         └──► RenderableSmcItem[] (SWING)──► SwingPrimitive──► SwingRenderer[SWING:draw]
                                             │
                                    Todos attachados ao candleSeries
                                    Z-order: FVG → OB → BPR → BOS → CHOCH → LIQ → SWING

Admin UI: [FVG][OB][BPR][BOS][CHOCH][LIQ][SWING] — toggles individuais
```

---

## Arquivos

### Frontend — 24 novos arquivos

```
hooks/useSmcPerType.ts              — fetch único, split por tipo

components/chart/smc/
  smcRenderUtils.ts                 — xFromTime, yOfPrice, LabelPlacer, drawLabel
  smcTypes.ts                       — + FvgRow, ObRow, etc.
  smcStyle.ts                       — + SMC_COLORS

  normalizers/
    fvgNormalizer.ts                — FvgRow[] → RenderableSmcItem[]
    obNormalizer.ts                 — ObRow[]  → RenderableSmcItem[]
    bosNormalizer.ts                — BosChochRow[] → RenderableSmcItem[] (split BOS/CHOCH)
    liqNormalizer.ts                — LiquidityRow[] → RenderableSmcItem[]
    swingNormalizer.ts              — SwingRow[] → RenderableSmcItem[]
    bprNormalizer.ts                — BprRow[] → RenderableSmcItem[]

  renderers/
    FvgRenderer.ts                  — retângulos fill+borda, labels
    ObRenderer.ts                   — retângulos tracejados, labels
    BprRenderer.ts                  — retângulos hachurados, labels
    BosRenderer.ts                  — linhas sólidas 2px, labels
    ChochRenderer.ts                — linhas tracejadas 1.5px, labels
    LiquidityRenderer.ts            — linhas pontilhadas 1.5px, labels
    SwingRenderer.ts                — triângulos HH/HL, labels

  primitives/
    FvgPrimitive.ts                 — ISeriesPrimitive wrapper
    ObPrimitive.ts
    BprPrimitive.ts
    BosPrimitive.ts
    ChochPrimitive.ts
    LiquidityPrimitive.ts
    SwingPrimitive.ts
```

### Frontend — 1 arquivo modificado

```
components/CandlestickChart.tsx    — 7 primitives + toggles individuais na toolbar
```

### Frontend — arquivos legados (mantidos para compatibilidade)

```
SmcSeriesPrimitive.ts, SmcPaneView.ts, SmcPaneRenderer.ts,
smcNormalize.ts, smcVisibility.ts, smcLabelCollision.ts
```

### Backend — preparado para sync per-table (não ativado ainda)

```
11 migrations 2026_06_20_*         — tabelas smc_v2_* no Hostinger
10 models app/Models/Smc/          — Eloquent per tabela
SyncTableController.php            — POST /api/sync/tables/push
SmcZoneService.php                 — leitura 9 tabelas → ApiZone[]
MarketDataController.php           — + smcByType() + canonicalTimeframe()
routes/api.php                     — + GET /api/zones/{ticker}/smc/{type}
config/smc.php                     — feature flag SMC_USE_NEW_TABLES
```

### VPS — 1 arquivo modificado

```
infra/sync_v2.py                   — + sync_v2_shadow_tables() + dual-path fallback
tools/resync_winfut_full.py        — script de re-sync completo
```

---

## Debug por Tipo

Cada renderer emite seus próprios logs, isolados:

```
[FVG:draw] drawn=278 skippedX=2 skippedY=1
[OB:draw] drawn=55 skippedX=0 skippedY=5
[BPR:draw] drawn=12
[BOS:draw] drawn=34 skipped=2
[CHOCH:draw] drawn=22 skipped=1
[LIQ:draw] drawn=30 skipped=0
[SWING:draw] drawn=140 skipped=10
```

Se FVG falhar, os logs mostram exatamente qual renderer quebrou, sem afetar OB/BOS/etc.

---

## Labels

- **Zonas (FVG, OB, BPR):** Label no `fromTime` (início da zona)
- **Estruturas (BOS, CHOCH, LIQ):** Label no ponto médio `(fromTime + toTime) / 2` (evita aglomeração na borda esquerda)
- **Swings:** Triângulos HH/HL + label
- **Anti-colisão:** LabelPlacer por renderer (20 slots FVG/OB/SWING, 10 slots BOS/CHOCH/LIQ/BPR)

---

## Deploy

```bash
# Backend (migrations + routes)
cd MaximusTrader && bash tools/deploy.sh --backend

# Frontend (per-type renderers)
cd MaximusTrader && bash tools/deploy.sh --frontend

# VPS re-sync
cd "SMC_Trader_System 7.0" && source venv/bin/activate
python tools/resync_winfut_full.py
```
