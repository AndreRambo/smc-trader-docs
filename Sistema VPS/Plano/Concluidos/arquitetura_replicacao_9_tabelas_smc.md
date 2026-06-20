# Arquitetura: Replicação 9 Tabelas Shadow SMC no Hostinger

**Data:** 2026-06-20
**Status:** Backend implementado, deploy pendente
**Feature flag:** `SMC_USE_NEW_TABLES=false` (ativar após deploy)

---

## Motivação

A tabela unificada `sync_zones` no Hostinger perde colunas nativas das shadow tables do VPS (quality_score, swing_type, range_percent, etc.) — dados que existem apenas como JSON opaco no campo `payload`, causando bugs de renderização.

**Solução:** Replicar as 9 tabelas shadow com schemas idênticos no Hostinger, sincronizar linhas brutas, transformar no backend PHP para o formato ApiZone que o frontend já espera.

---

## Arquitetura

```
VPS MySQL (9 shadow tables)                    Hostinger MySQL (9 smc_v2_* tables)
         │                                                │
         │ sync_v2.py: sync_v2_shadow_tables()            │
         │ envia linhas brutas por tabela                 │
         ├──────────────────────────────────────────────►│ POST /api/sync/tables/push
         │                                                │
         │ (fallback) sync_v2_shadow_zones()              │
         ├──────────────────────────────────────────────►│ POST /api/sync/zones (antigo)
         │                                                │
         │                                                │ SmcZoneService
         │                                                │ lê 9 tabelas → transforma → ApiZone[]
         │                                                │
         │                              Frontend ◄────────┤ GET /api/zones/{ticker}
         │                              (sem alterações)  │
```

---

## Arquivos Criados

### Hostinger (MaximusTrader/backend)

#### Migrations (11 arquivos)
```
database/migrations/
  2026_06_20_000001_create_smc_v2_runs_table.php
  2026_06_20_000002_create_smc_v2_fvg_table.php
  2026_06_20_000003_create_smc_v2_order_blocks_table.php
  2026_06_20_000004_create_smc_v2_bos_choch_table.php
  2026_06_20_000005_create_smc_v2_liquidity_table.php
  2026_06_20_000006_create_smc_v2_swings_table.php
  2026_06_20_000007_create_smc_v2_sessions_table.php
  2026_06_20_000008_create_smc_v2_previous_high_low_table.php
  2026_06_20_000009_create_smc_v2_retracements_table.php
  2026_06_20_000010_create_smc_v2_bpr_table.php
  2026_06_20_000011_add_raw_json_to_smc_v2_tables.php
```

#### Models (10 arquivos)
```
app/Models/Smc/
  SmcV2Run.php
  SmcV2Fvg.php
  SmcV2OrderBlock.php
  SmcV2BosChoch.php
  SmcV2Liquidity.php
  SmcV2Swing.php
  SmcV2Session.php
  SmcV2PreviousHighLow.php
  SmcV2Retracement.php
  SmcV2Bpr.php
```

#### Controller
```
app/Http/Controllers/Api/SyncTableController.php
  - POST /api/sync/tables/push (HMAC protegido)
  - Recebe raw rows por tabela, faz DELETE+INSERT com replace=true
```

#### Service
```
app/Services/SmcZoneService.php (24KB)
  - getZones(ticker, timeframe, limit) → ApiZone[]
  - 9 métodos de transformação (fvgToZones, obToZones, etc.)
  - Espelha _build_*_zones() do sync_v2.py em PHP
```

#### Config
```
config/smc.php
  - 'use_new_tables' => env('SMC_USE_NEW_TABLES', false)
```

#### Rotas
```
routes/api.php
  - POST /api/sync/tables/push → SyncTableController@push
```

#### Modificado
```
app/Http/Controllers/Api/MarketDataController.php
  - Adicionado dual-path: se config('smc.use_new_tables') → SmcZoneService
  - Fallback para SyncZone (tabela antiga)
  - Adicionado canonicalTimeframe() helper
```

### VPS (SMC_Trader_System 7.0)

#### Modificado
```
infra/sync_v2.py
  - + _row_to_serializable() — converte DB row → JSON-safe dict
  - + sync_v2_shadow_tables() — envia raw rows para /sync/tables/push
  - Modificado sync_v2_shadow_zones() — dual-path (novo → fallback antigo)
```

### Frontend
```
ZERO alterações — contrato ApiZone[] preservado
```

---

## Fluxo de Dados Detalhado

### Sync (VPS → Hostinger)

1. `sync_v2_shadow_zones()` é chamado (TRIGGER 4 do mt5_core.py)
2. Tenta `sync_v2_shadow_tables()`:
   - Query por run_id nas 9 tabelas shadow
   - `_row_to_serializable()` converte datetime/Decimal → str/float
   - POST para `/sync/tables/push` com `{ticker, timeframe, run_id, replace: true, tables: {fvg: [...], ob: [...], ...}}`
3. Se 404 (endpoint não existe) → fallback para `/sync/zones` (builder antigo)

### Receive (Hostinger)

1. `SyncTableController@push` recebe o payload
2. `SyncAsset::firstOrCreate` pelo ticker
3. Se `replace=true`: DELETE de todas as 10 tabelas por (ticker, timeframe, run_id)
4. Bulk INSERT em chunks de 200 por tabela
5. Retorna `{ok: true, counts: {fvg: 234, ob: 89, ...}}`

### Read (Hostinger → Frontend)

1. `GET /api/zones/WINFUT?timeframe=5min&limit=1500`
2. Se `SMC_USE_NEW_TABLES=true`:
   - `SmcZoneService::getZones()` → query no run mais recente em `smc_v2_runs`
   - 9 SELECTs nas tabelas `smc_v2_*` filtrando por `run_id`
   - Cada linha transformada para ApiZone (id, zone_type, type, price_top, price_bottom, top, bottom, timeframe, status, mitigated_at, display_from, display_to, created_at_candle, payload)
   - Merge, sort por prioridade (OB > FVG > BPR > BOS > CHOCH > LIQUIDITY > SWING > ...), slice(1500)
3. Se `SMC_USE_NEW_TABLES=false`:
   - Query na tabela `sync_zones` (caminho antigo)

---

## Schemas das 10 Tabelas

### smc_v2_runs
| Coluna | Tipo |
|--------|------|
| id | BIGINT PK |
| sync_asset_id | FK sync_assets |
| ticker | VARCHAR(64) |
| timeframe | VARCHAR(16) |
| run_id | VARCHAR(64) UNIQUE |
| source_engine | VARCHAR(64) |
| engine_version | VARCHAR(64) |
| calculation_mode | VARCHAR(64) |
| window_size | INT |
| candles_limit | INT |
| first_candle_at | DATETIME |
| last_candle_at | DATETIME |
| parameters_json | JSON |
| input_hash | VARCHAR(128) |
| output_hash | VARCHAR(128) |
| status | VARCHAR(32) |
| error_message | TEXT |
| created_at/updated_at | TIMESTAMP |

### smc_v2_fvg
| Coluna | Tipo |
|--------|------|
| id | BIGINT PK |
| sync_asset_id, ticker, timeframe, run_id | FK + strings |
| ref_index | INT |
| fvg | INT |
| direction_label | VARCHAR(16) |
| top, bottom, midpoint | DECIMAL(18,6) |
| mitigated_index | INT |
| origin_at, confirmed_at, available_at, mitigated_at, display_from, display_to | DATETIME |
| raw_json | JSON (adicionado via migration 000011) |
| created_at/updated_at | TIMESTAMP |

### smc_v2_order_blocks
FVG + ob_volume, percentage, ob (INT)

### smc_v2_bos_choch
FVG-like + event_type, direction, level, broken_index, event_time, broken_at, line_start_time, line_end_time, line_price, close_break

### smc_v2_liquidity
BOS-like + liquidity, end_index, swept_index, end_at, swept_at, range_percent

### smc_v2_swings
ref_index, high_low, swing_type, level, event_time, origin_at, confirmed_at, available_at

### smc_v2_sessions
ref_index, session_name, candle_time, active, session_high, session_low, start_time, end_time, time_zone

### smc_v2_previous_high_low
ref_index, candle_time, previous_high, previous_low, broken_high, broken_low, time_frame

### smc_v2_retracements
ref_index, candle_time, direction, current_retracement_pct, deepest_retracement_pct

### smc_v2_bpr
FVG-like + quality_score, quality_label, size_pts, bull_fvg_ref, bear_fvg_ref

---

## Deploy

```bash
# 1. Deploy backend (roda migrations)
cd MaximusTrader && bash tools/deploy.sh --backend

# 2. Ativar feature flag no .env do Hostinger
SMC_USE_NEW_TABLES=true

# 3. Forçar re-sync no VPS
python3 -c "from infra.sync_v2 import sync_v2_shadow_zones; print(sync_v2_shadow_zones('WINFUT', 1, '5min'))"

# 4. Verificar GET /api/zones/WINFUT?timeframe=5min
```

---

## Rollback

```bash
# Desativar flag → volta a usar sync_zones
SMC_USE_NEW_TABLES=false

# Remover tabelas
php artisan migrate:rollback --step=11
```
