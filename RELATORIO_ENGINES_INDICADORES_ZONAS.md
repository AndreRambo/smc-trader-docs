# Relatório: Engines, Indicadores, Zonas e Tabelas — SMC Trader System 7.0

> Gerado: 2026-06-27 | Atualizado: 2026-06-30 (R5A-MTF) | Fonte: `ARQUITETURA_OFICIAL.md` + código fonte
> Base path: `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/`
> Total: **85 tabelas shadow** (produção) + 1 file store + **6 tabelas staging incremental** (pré-cutover, NÃO em produção — ver §3.11) + proposta Volume Profile (POC/VA) documentada em §3.12 (não implementada)

---

## 1. Visão Geral — Engines e Suas Tabelas

| Engine | # Tabelas | Prefixo das Tabelas | Caminho do Engine |
|--------|-----------|---------------------|-------------------|
| **SMC Engine V2** | 10 | `technical_engine_smc_v2_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v2/` |
| **Shadow Database Core** | 6 | `technical_engine_snapshots_*`, `technical_engine_study_*`, `technical_engine_consumer_*`, `technical_engine_visual_*`, `technical_engine_audit_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/shadow_database/` |
| **Elliott Wave** | 4 | `technical_engine_elliott_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/elliott/` |
| **Wyckoff** | 4 | `technical_engine_wyckoff_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/wyckoff/` |
| **Opportunity Scanner** | 4 | `technical_engine_opportunity_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_scanner/` |
| **Study Pipeline Assembly** | 4 | `technical_engine_study_pipeline_*`, `technical_engine_operational_studies_*`, `technical_engine_study_evidences_*`, `technical_engine_study_scenarios_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow/` |
| **Contextual Market Profile** | 1 | `technical_engine_contextual_market_profiles_shadow` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/contextual_market_profile/` |
| **Study Calibration** | 3 | `technical_engine_study_calibration_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_calibration/` |
| **Study Replay Validation** | 3 | `technical_engine_study_replay_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_validation/` |
| **Study Calibration Validation** | 2 | `technical_engine_study_calibration_validation_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_calibration_validation/` |
| **Study Candidate Backtest** | 2 | `technical_engine_study_candidate_backtest_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_candidate/` |
| **Operational Plans** | 2 | `technical_engine_operational_plans_*`, `technical_engine_operational_plan_setups_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_gateway/` |
| **Notification Outbox** | 1 | `technical_engine_opportunity_notification_outbox_shadow` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_scanner/` |
| **Scanner Config Profiles** | 1 | `technical_engine_opportunity_scanner_config_profiles_shadow` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_scanner/` |
| **Scanner Active Config** | 2 | `technical_engine_opportunity_scanner_active_config_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_scanner/` |
| **Audit Trail** | 1 | `technical_engine_audit_trail_shadow` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_gateway/` |
| **Hit Rates** | 1 | `technical_engine_risk_v2_hit_rates_shadow` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_gateway/` |
| **SMC Reference** | 10 | `technical_engine_smc_reference_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/` |
| **Causal Rebuild V1** | 5 | `technical_engine_calculation_runs_*`, `technical_engine_active_runs_*`, `technical_engine_structure_events_*`, `technical_engine_indicator_values_*`, `technical_engine_rebuild_artifacts_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/` |
| **Causal Rebuild V2** | 5 | `technical_engine_rebuild_v2_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/` |
| **Signal Candidate** | 5 | `technical_engine_signal_candidate_*`, `technical_engine_signal_backtest_*`, `technical_engine_signal_comparisons_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/signal_candidate_v1/` |
| **Asset Collector** | 3 | `technical_engine_candle_events`, `technical_engine_asset_worker_*`, `technical_engine_event_processor_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/` |
| **Evidence Bundles** | 3 | `technical_engine_opportunity_evidence_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_evidence/` |
| **Lifecycle & Outcomes** | 2 | `technical_engine_opportunity_lifecycle_*`, `technical_engine_opportunity_outcomes_*` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/` |
| **Zone Memory** | 0 (files) | `runtime/zone_memory_shadow/` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/zone_memory/` |

---

## 2. Infra — Indicadores Core

**Arquivo:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/infra/indicators.py`

| Indicador | Fórmula | Período Padrão |
|-----------|---------|----------------|
| EMA20 | Exponential Moving Average (span=20) | 20 |
| EMA200 | Exponential Moving Average (span=200) | 200 |
| RSI14 | Relative Strength Index (Wilder's RMA) | 14 |
| ATR14 | Average True Range (Wilder's RMA) | 14 |

**Tabela:** `market_candles` (tabela oficial, read-write pelos robôs)

**Robôs que utilizam:**
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/run_b3.py`
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/run_forex.py`

---

## 3. SMC Engine V2 — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v2/`
**Status:** `STABLE_FROZEN_V2` | **Testes:** 164

### 3.1 `technical_engine_smc_v2_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| source_engine | VARCHAR(64) NOT NULL |
| engine_version | VARCHAR(64) NULL |
| calculation_mode | VARCHAR(64) NOT NULL |
| window_size | INT NULL |
| candles_limit | INT NOT NULL |
| first_candle_at | DATETIME NULL |
| last_candle_at | DATETIME NULL |
| parameters_json | JSON NOT NULL |
| input_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| status | VARCHAR(32) NOT NULL |
| error_message | TEXT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 3.2 `technical_engine_smc_v2_fvg_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| fvg | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| top | DECIMAL(18,6) NOT NULL |
| bottom | DECIMAL(18,6) NOT NULL |
| midpoint | DECIMAL(18,6) NULL |
| mitigated_index | INT NULL |
| origin_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| mitigated_at | DATETIME NULL |
| display_from | DATETIME NULL |
| display_to | DATETIME NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| logical_structure_id | VARCHAR(128) NULL |
| global_ref_index | BIGINT NULL |
| origin_candle_id | BIGINT NULL |
| confirmation_candle_id | BIGINT NULL |
| payload_hash | VARCHAR(128) NULL |

### 3.3 `technical_engine_smc_v2_order_blocks_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| ob | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| top | DECIMAL(18,6) NOT NULL |
| bottom | DECIMAL(18,6) NOT NULL |
| midpoint | DECIMAL(18,6) NULL |
| ob_volume | DECIMAL(18,6) NULL |
| percentage | DECIMAL(18,6) NULL |
| mitigated_index | INT NULL |
| origin_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| mitigated_at | DATETIME NULL |
| display_from | DATETIME NULL |
| display_to | DATETIME NULL |
| quality_label | VARCHAR(16) NULL |
| quality_score | DECIMAL(10,2) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| logical_structure_id | VARCHAR(128) NULL |
| global_ref_index | BIGINT NULL |
| origin_candle_id | BIGINT NULL |
| confirmation_candle_id | BIGINT NULL |
| payload_hash | VARCHAR(128) NULL |

> **Enriquecimento OB (2026-06-30, NTSL parity):** os campos `ob_subtype`
> (`NORMAL`|`REJECTION`|`STACKED`), `structure_confirmed` e `liquidity_aligned`
> trafegam dentro de `raw_json` — **nenhuma migração DDL** foi necessária
> (`persistence.py` grava o registro inteiro em `raw_json`). Coluna SQL dedicada
> é opcional (apenas se filtro em SQL for desejado). Classificação aditiva: não
> altera detecção, `quality_label`/`quality_score` ou contagem de OBs.
> Decisão de rename `BREAKER`→`STACKED` (2026-06-30) para evitar colisão semântica
> com breaker-block clássico SMC (`ZONE_TYPE_BREAKER`, `signal_candidate_v1 S4_BREAKER`).

### 3.4 `technical_engine_smc_v2_bos_choch_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| event_type | VARCHAR(16) NOT NULL |
| direction | INT NULL |
| direction_label | VARCHAR(16) NULL |
| level | DECIMAL(18,6) NOT NULL |
| broken_index | INT NULL |
| event_time | DATETIME NULL |
| broken_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| line_start_time | DATETIME NULL |
| line_end_time | DATETIME NULL |
| line_price | DECIMAL(18,6) NULL |
| close_break | BOOLEAN NOT NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| logical_structure_id | VARCHAR(128) NULL |
| global_ref_index | BIGINT NULL |
| origin_candle_id | BIGINT NULL |
| confirmation_candle_id | BIGINT NULL |
| payload_hash | VARCHAR(128) NULL |

### 3.5 `technical_engine_smc_v2_liquidity_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| liquidity | INT NOT NULL |
| direction_label | VARCHAR(16) NULL |
| level | DECIMAL(18,6) NOT NULL |
| end_index | INT NULL |
| swept_index | INT NULL |
| event_time | DATETIME NULL |
| end_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| swept_at | DATETIME NULL |
| line_start_time | DATETIME NULL |
| line_end_time | DATETIME NULL |
| line_price | DECIMAL(18,6) NULL |
| range_percent | DECIMAL(18,8) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| logical_structure_id | VARCHAR(128) NULL |
| global_ref_index | BIGINT NULL |
| payload_hash | VARCHAR(128) NULL |

### 3.6 `technical_engine_smc_v2_swings_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| high_low | INT NOT NULL |
| swing_type | VARCHAR(32) NOT NULL |
| level | DECIMAL(18,6) NOT NULL |
| pivot_candle_id | BIGINT NULL |
| confirmation_candle_id | BIGINT NULL |
| event_time | DATETIME NULL |
| origin_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| logical_structure_id | VARCHAR(128) NULL |
| global_ref_index | BIGINT NULL |
| payload_hash | VARCHAR(128) NULL |

### 3.7 `technical_engine_smc_v2_sessions_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| session_name | VARCHAR(64) NOT NULL |
| candle_time | DATETIME NULL |
| active | INT NOT NULL |
| session_high | DECIMAL(18,6) NULL |
| session_low | DECIMAL(18,6) NULL |
| start_time | VARCHAR(16) NULL |
| end_time | VARCHAR(16) NULL |
| time_zone | VARCHAR(64) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 3.8 `technical_engine_smc_v2_retracements_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| candle_time | DATETIME NULL |
| direction | INT NULL |
| current_retracement_pct | DECIMAL(18,6) NULL |
| deepest_retracement_pct | DECIMAL(18,6) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 3.9 `technical_engine_smc_v2_previous_high_low_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| candle_time | DATETIME NULL |
| previous_high | DECIMAL(18,6) NULL |
| previous_low | DECIMAL(18,6) NULL |
| broken_high | INT NULL |
| broken_low | INT NULL |
| time_frame | VARCHAR(16) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 3.10 `technical_engine_smc_v2_visual_overlays_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| overlay_type | VARCHAR(64) NOT NULL |
| component | VARCHAR(64) NOT NULL |
| ref_index | INT NULL |
| x0 | DATETIME NULL |
| x1 | DATETIME NULL |
| y0 | DECIMAL(18,6) NULL |
| y1 | DECIMAL(18,6) NULL |
| line_price | DECIMAL(18,6) NULL |
| label | VARCHAR(128) NULL |
| status | VARCHAR(64) NULL |
| style_json | JSON NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 3.11 SMC Engine V2 Incremental — schema staging (pré-cutover)

**Diretório:** `technical_engine/smc_engine_v2/incremental/persistence/`
**Migration MySQL:** `migrations/20260629_smc_v2_incremental_schema.sql`
**Status:** **NÃO em produção.** Branch `feature/smc-v2-incremental-unified`. R1→R4 PASS. R5A em andamento (MTF candle-a-candle replay — verificação final aguardando execução). MySQL staging ainda não validado (R2 = PARTIAL, sem driver no ambiente). Ver `ARQUITETURA_OFICIAL.md` §4.10.

Schema com IDs SHA-256 determinísticos, escrita atômica por tick (SAVEPOINT), FK com `ON DELETE CASCADE`, e detecção de conflito (mesmo ID + payload diferente → `PersistenceConflictError`; nunca `INSERT OR IGNORE`):

| Tabela | Conteúdo | Chave |
|--------|----------|-------|
| `smc_v2_engine_runs` | um registro por run de backfill/live | `run_id` (PK) |
| `smc_v2_structures` | `StructureEmission` (append-only) — inclui `ob_subtype` no `payload_json`; aceita `structure_type="FIBONACCI_ANCHOR"` | `structure_id` (PK, FK→runs) |
| `smc_v2_structure_events` | `StructureEventEmission` (lifecycle AVAILABLE→TOUCHED→PARTIALLY_FILLED→MITIGATED→ANCHOR_CHANGED) | `event_id` (PK, FK→runs, FK→structures) |
| `smc_v2_checkpoints` | snapshots completos da engine (JSON) | `checkpoint_id` (PK, FK→runs) |
| `smc_v2_active_stream_versions` | ponteiro de run ativo por (asset_id, timeframe) | `UNIQUE(asset_id, timeframe)` |
| `smc_v2_reconciliation` | log de auditoria órfãos/residual | `id` (PK) |

> **Nota OB subtype no incremental:** `ob_subtype` (NORMAL/REJECTION/STACKED) é
> gravado em `smc_v2_structures.payload_json` (não em coluna dedicada), mantendo o
> `structure_id` estável. TIER 2 (`structure_confirmed`/`liquidity_aligned`) está
> diferido no incremental — exige orquestração de confluência cross-component.
> Rename `BREAKER`→`STACKED` aplicado em batch e incremental (2026-06-30).

> **Correção R5A — FIBONACCI_ANCHOR (2026-06-30):** `RetracementsComponent` gerava
> eventos `ANCHOR_CHANGED` com `structure_id` de um âncora sintético que NUNCA
> era publicado como `StructureEmission`, causando `FOREIGN KEY constraint failed`
> na tabela `smc_v2_structure_events`. Fix: adicionado `_emit_anchor_structure()`
> que emite o âncora com `structure_type="FIBONACCI_ANCHOR"` antes de qualquer
> evento que o referencie. O conjunto de `structure_type` válidos em
> `smc_v2_structures` agora inclui: `FVG`, `ORDER_BLOCK`, `BOS`, `CHOCH`,
> `LIQUIDITY`, `BPR`, `SWING`, `SESSION`, `PDH`, `PDL`, **`FIBONACCI_ANCHOR`**.
> Arquivo: `incremental/components/retracements.py`.

#### R3 — Integração Opportunity Engine (staging)

O backtest do engine incremental não usa mais um evaluator simplificado. Através do `ReplayOpportunityAdapter`, ele traduz `StructureEmission + CandleEnvelope` → `PersistedOperationalPlanRef + LatestPriceRef` e chama `evaluate_opportunity()` canônico (o mesmo de produção).

**Tradução V2 → Canônico:**

| Campo Canônico | Fonte V2 |
|----------------|----------|
| direction | BULLISH→ALTISTA, BEARISH→BAIXISTA |
| entrada | midpoint da zona |
| stop | zone_bottom − zone_size×0.2 (bull) |
| tp1/tp2/tp3 | entry + risk × R:R (1.5/3.0/5.0) |
| readiness | hardcoded PRONTO |
| now | candle.available_at (histórico) |

**Gates bypassados em replay:** has_operation, htf_aligned, market_closed, plan_age, price_age (não aplicáveis a dados históricos).

**Gates ativos em replay:** causal guard (available_at), approach side, zone degenerate, ATR validation, proximidade.

O `CanonicalOpportunityBacktester` roda o loop completo: processa candles → avalia zonas → emite decisões → simula intrabar (stop antes de TP) → compute stats (expectancy, PF, drawdown).

---

### 3.12 Volume Profile (POC/VA) — Proposta (NÃO-IMPLEMENTADO)

> **Status: PROPOSTA. Não implementado. Aditivo e OFF por default quando implementado. Nenhuma DDL, nenhuma migração. Não altera detecção nem calibração WINFUT_M5.**

**Objetivo:** POC, VAH/VAL, HVN e LVN como camada de confluência sobre SMC, Wyckoff e Elliott. Nenhum dado de volume profile é gravado em coluna dedicada — os campos (proposto) viajam em `raw_json` / `payload_json`, igual ao `ob_subtype`.

**Definições:**

| Conceito | Descrição |
|----------|-----------|
| POC | Preço com maior volume negociado no perfil do período |
| VA (Value Area) | Faixa de ~70% do volume; VAH = topo, VAL = fundo. `Percentual_VA` é parametrizável (NTSL usa 40%; padrão de mercado 70%) |
| HVN | Alto volume → aceitação/equilíbrio |
| LVN | Baixo volume → rejeição/movimento rápido |
| Naked POC | POC de sessão anterior nunca retocado → ímã de preço |

**Regras de confluência (proposta):**

| Módulo | Regra | Tipo |
|--------|-------|------|
| SMC | OB + HVN → boost `quality_score` (+10 a +15 pts, param.) | Filtro/ponderação |
| SMC | FVG → POC/HVN como alvo de take-profit | Alvo |
| SMC | OB/FVG sobre LVN → possível penalidade leve (default 0) | Filtro opcional |
| SMC | Naked POC → alvo magnético TP | Alvo |
| Wyckoff | POC = região de "cause" → contexto de fase | Contexto |
| Wyckoff | LVN → spring/upthrust rápidos → flag de contexto | Contexto |
| Elliott | POC/VA → alvo de ondas corretivas (B, 2, 4) | Alvo |
| Elliott | Naked POC → alvo de extensão impulsiva | Alvo |

**(proposto) Campos no payload OB/FVG** — sem DDL, gravados em `payload_json`:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| (proposto) `poc_aligned` | bool | OB/FVG coincide com POC/HVN |
| (proposto) `volume_node` | str | `"HVN"` \| `"LVN"` \| `"NEUTRAL"` |
| (proposto) `nearest_poc` | float | Preço do POC mais próximo |
| (proposto) `dist_to_poc_atr` | float | Distância ao POC em ATRs |

**(proposto) Config flags:**
- `enable_volume_profile_confluence: bool = False` (OFF por default)
- `va_percent: float = 0.70` (parametrizável; NTSL usa 0.40)
- `hvn_quality_boost: float = 12.0`
- `lvn_quality_penalty: float = 0.0` (default neutro)
- `poc_target_priority: bool = True`

**(proposto) Componente incremental:** `incremental/components/volume_profile.py` — calcula HistogramaVolume por candle (tick approximation), POC, VAH, VAL, HVN/LVN. Espelha lógica do indicador NTSL de referência (ApplyTZ, naked/virgin POC do dia anterior). Causal: usa apenas candles com `available_at <= candle atual`.

**Hipótese a validar:** combinação POC/HVN com OBs existentes como hipótese de melhoria de seletividade — a ser verificada por backtest A/B em shadow-only antes de qualquer claim de performance.

---

## 4. Elliott Wave — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/elliott/`

### 4.1 `technical_engine_elliott_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| latest_candle_time | DATETIME NOT NULL |
| ctx_json | LONGTEXT NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 4.2 `technical_engine_elliott_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| source_engine | VARCHAR(64) NOT NULL |
| engine_version | VARCHAR(64) NOT NULL |
| calculation_mode | VARCHAR(64) NOT NULL |
| candles_limit | INT NOT NULL |
| first_candle_at | DATETIME NULL |
| last_candle_at | DATETIME NULL |
| parameters_json | JSON NULL |
| input_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| status | VARCHAR(32) NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| raw_json | JSON NULL |

### 4.3 `technical_engine_elliott_waves_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| wave_label | VARCHAR(16) NULL |
| start_time | DATETIME NULL |
| end_time | DATETIME NULL |
| start_price | DECIMAL(18,6) NULL |
| end_price | DECIMAL(18,6) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 4.4 `technical_engine_elliott_overlays_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| overlay_type | VARCHAR(32) NOT NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 5. Wyckoff — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/wyckoff/`

### 5.1 `technical_engine_wyckoff_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| latest_candle_time | DATETIME NOT NULL |
| ctx_json | LONGTEXT NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 5.2 `technical_engine_wyckoff_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| source_engine | VARCHAR(64) NOT NULL |
| engine_version | VARCHAR(64) NOT NULL |
| calculation_mode | VARCHAR(64) NOT NULL |
| candles_limit | INT NOT NULL |
| first_candle_at | DATETIME NULL |
| last_candle_at | DATETIME NULL |
| parameters_json | JSON NULL |
| input_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| status | VARCHAR(32) NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| raw_json | JSON NULL |

### 5.3 `technical_engine_wyckoff_events_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| event_type | VARCHAR(32) NOT NULL |
| event_time | DATETIME NULL |
| price | DECIMAL(18,6) NULL |
| direction | VARCHAR(16) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 5.4 `technical_engine_wyckoff_phases_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| phase | VARCHAR(32) NOT NULL |
| start_time | DATETIME NULL |
| end_time | DATETIME NULL |
| range_high | DECIMAL(18,6) NULL |
| range_low | DECIMAL(18,6) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 5.5 `technical_engine_wyckoff_overlays_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(96) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| overlay_type | VARCHAR(32) NOT NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 6. Contextual Market Profile — Tabela e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/contextual_market_profile/`

### 6.1 `technical_engine_contextual_market_profiles_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| session_id | VARCHAR(64) NOT NULL |
| market_regime | VARCHAR(64) NULL |
| volatility_state | VARCHAR(32) NULL |
| atr | DECIMAL(18,6) NULL |
| adr | DECIMAL(18,6) NULL |
| adr_progress_pct | DECIMAL(18,6) NULL |
| session_range_pct_of_adr | DECIMAL(18,6) NULL |
| news_mode | VARCHAR(64) NULL |
| config_hash | VARCHAR(128) NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 7. Opportunity Scanner — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_scanner/`

### 7.1 `technical_engine_opportunity_signals_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| signal_id | VARCHAR(64) NOT NULL |
| signal_type | VARCHAR(32) NOT NULL DEFAULT 'OPPORTUNITY_SIGNAL' |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NULL |
| base_timeframe | VARCHAR(16) NOT NULL DEFAULT 'M5' |
| feed_timeframe | VARCHAR(16) NOT NULL DEFAULT 'M1' |
| detected_at | DATETIME NOT NULL |
| feed_candle_time | DATETIME NULL |
| base_candle_time | DATETIME NULL |
| current_price | DECIMAL(18,6) NULL |
| direction | VARCHAR(16) NULL |
| readiness | VARCHAR(32) NULL |
| operational_status | VARCHAR(32) NULL |
| has_operation | TINYINT(1) NULL |
| entrada | DECIMAL(18,6) NULL |
| stop | DECIMAL(18,6) NULL |
| tp1 | DECIMAL(18,6) NULL |
| tp2 | DECIMAL(18,6) NULL |
| tp3 | DECIMAL(18,6) NULL |
| rr_tp1 | DECIMAL(8,4) NULL |
| rr_tp2 | DECIMAL(8,4) NULL |
| rr_tp3 | DECIMAL(8,4) NULL |
| rr_ponderado | DECIMAL(8,4) NULL |
| confianca | VARCHAR(16) NULL |
| proximity | VARCHAR(32) NULL |
| trigger_state | VARCHAR(32) NULL |
| severity | VARCHAR(16) NULL |
| distance_to_entry_pts | DECIMAL(18,6) NULL |
| distance_to_entry_atr | DECIMAL(18,6) NULL |
| approach_side | VARCHAR(16) NULL |
| valid_approach | TINYINT(1) NULL |
| mtf_align | VARCHAR(32) NULL |
| htf_bias | VARCHAR(16) NULL |
| zone_aligned | TINYINT(1) NULL |
| plan_id | VARCHAR(64) NULL |
| setup_id | VARCHAR(64) NULL |
| envelope_id | VARCHAR(64) NULL |
| study_run_id | VARCHAR(64) NULL |
| smc_run_id | VARCHAR(64) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| dedup_key | VARCHAR(255) NULL |
| message | TEXT NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) DEFAULT 1 |
| alerta_nao_e_sinal | TINYINT(1) DEFAULT 1 |
| can_promote_trade | TINYINT(1) DEFAULT 0 |
| apply_automatically | TINYINT(1) DEFAULT 0 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 7.2 `technical_engine_opportunity_alerts_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| alert_id | VARCHAR(64) NOT NULL |
| signal_id | VARCHAR(64) NULL |
| dedup_key | VARCHAR(255) NULL |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NULL |
| base_timeframe | VARCHAR(16) NOT NULL DEFAULT 'M5' |
| feed_timeframe | VARCHAR(16) NOT NULL DEFAULT 'M1' |
| detected_at | DATETIME NOT NULL |
| feed_candle_time | DATETIME NULL |
| base_candle_time | DATETIME NULL |
| current_price | DECIMAL(18,6) NULL |
| direction | VARCHAR(16) NULL |
| readiness | VARCHAR(32) NULL |
| operational_status | VARCHAR(32) NULL |
| has_operation | TINYINT(1) NULL |
| entrada | DECIMAL(18,6) NULL |
| stop | DECIMAL(18,6) NULL |
| tp1 | DECIMAL(18,6) NULL |
| tp2 | DECIMAL(18,6) NULL |
| tp3 | DECIMAL(18,6) NULL |
| rr_tp1 | DECIMAL(8,4) NULL |
| rr_tp2 | DECIMAL(8,4) NULL |
| rr_tp3 | DECIMAL(8,4) NULL |
| rr_ponderado | DECIMAL(8,4) NULL |
| confianca | VARCHAR(16) NULL |
| proximity | VARCHAR(32) NULL |
| previous_proximity | VARCHAR(32) NULL |
| trigger_state | VARCHAR(32) NULL |
| severity | VARCHAR(16) NULL |
| distance_to_entry_pts | DECIMAL(18,6) NULL |
| distance_to_entry_atr | DECIMAL(18,6) NULL |
| approach_side | VARCHAR(16) NULL |
| valid_approach | TINYINT(1) NULL |
| mtf_align | VARCHAR(32) NULL |
| htf_bias | VARCHAR(16) NULL |
| zone_aligned | TINYINT(1) NULL |
| plan_id | VARCHAR(64) NULL |
| setup_id | VARCHAR(64) NULL |
| envelope_id | VARCHAR(64) NULL |
| study_run_id | VARCHAR(64) NULL |
| smc_run_id | VARCHAR(64) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| message | TEXT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'OPEN' |
| status_reason | TEXT NULL |
| expires_at | DATETIME NULL |
| notified | TINYINT(1) DEFAULT 0 |
| notified_at | DATETIME NULL |
| notification_channel | VARCHAR(32) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) DEFAULT 1 |
| alerta_nao_e_sinal | TINYINT(1) DEFAULT 1 |
| can_promote_trade | TINYINT(1) DEFAULT 0 |
| apply_automatically | TINYINT(1) DEFAULT 0 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 7.3 `technical_engine_opportunity_scanner_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| run_id | VARCHAR(64) NOT NULL |
| started_at | DATETIME NOT NULL |
| finished_at | DATETIME NOT NULL |
| duration_ms | DECIMAL(18,2) NULL |
| mode | VARCHAR(16) NOT NULL DEFAULT 'ONCE' |
| dry_run | TINYINT(1) DEFAULT 0 |
| assets_requested | INT DEFAULT 0 |
| assets_scanned | INT DEFAULT 0 |
| assets_skipped | INT DEFAULT 0 |
| signals_created | INT DEFAULT 0 |
| alerts_created | INT DEFAULT 0 |
| alerts_deduped | INT DEFAULT 0 |
| skip_reasons | JSON NULL |
| error_count | INT DEFAULT 0 |
| errors | JSON NULL |
| scanner_version | VARCHAR(32) NULL |
| config_hash | VARCHAR(128) NULL |
| raw_summary | JSON NOT NULL |
| shadow_only | TINYINT(1) DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 7.4 `technical_engine_opportunity_scanner_heartbeats_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| service_name | VARCHAR(64) NOT NULL DEFAULT 'opportunity_scanner' |
| instance_id | VARCHAR(64) NOT NULL |
| heartbeat_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| last_run_id | VARCHAR(64) NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'OK' |
| status_message | TEXT NULL |
| loop_mode | VARCHAR(16) NULL |
| scanner_version | VARCHAR(32) NULL |
| metadata | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 7.5 Conceitual — Como o Opportunity Scanner funciona

#### Pipeline completo (produção)

```
MT5 candle (M1) → run_b3.py → TRIGGER 4
  → run_smc_engine_v2_local() → FVG/OB/BOS/Liq/BPR/Swings/PDH/Sessions/Ret
  → run_ew_pipeline_and_persist() → Elliott + Wyckoff
  → Confluence V2 → TechnicalTruthEnvelopeV2
  → Risk Management V2 → OperationalPlanV2 (M5)
  → OpportunityScanner.scan_once()
       → load_latest_price(M1) + load_latest_plan(M5)
       → evaluate_opportunity(plan, price, config)
       → 12 gates determinísticos (ver §4.4.1 do ARQUITETURA_OFICIAL.md)
       → proximity: NA_ZONA/IMINENTE/PROXIMO/OBSERVANDO/DISTANTE
       → dedup (15min window)
       → persist signal + alert + notify (WebSocket + HTTP POST HMAC)
       → maximustrade.com.br → FCM push
```

#### 12 Gates do Evaluator

| # | Gate | Motivo de bloqueio |
|---|------|--------------------|
| 1 | Stop Invalidated by Window (S18K) | candle M1 cruzou stop antes da entrada |
| 2 | Plan Not Active | plano não está ACTIVE |
| 3 | Readiness Not Allowed | readiness fora do permitido |
| 4 | No Operation | plano sem operação |
| 5 | Missing Entry | plano sem entrada definida |
| 6 | Missing/Invalid ATR | ATR ausente ou ≤ 0 |
| 7 | Market Closed (S18B) | B3 fora de 09–18h BRT |
| 8 | Plan Too Old (S18E) | plano com candle fechado há >10min |
| 9 | Price Too Old (S18E) | preço M1 fechado há >3min |
| 10 | Contra HTF | plano contra o viés HTF |
| 11 | Stop Invalidated (S18J) | candle low≤stop ou high≥stop |
| 12 | Wrong Approach (S18F) | preço do lado errado da entrada |

#### Proximidade e Severidade

| Proximity | Distância (ATR) | Severity | trigger_state | allowed |
|-----------|----------------|----------|---------------|---------|
| NA_ZONA | candle toca entrada | CRITICAL | IN_ZONE | True |
| IMINENTE | ≤ 1.0 ATR | HIGH | ARMED | True |
| PROXIMO | ≤ 2.0 ATR | MEDIUM | ARMED | True |
| OBSERVANDO | ≤ 3.0 ATR | LOW | WATCHING | True |
| DISTANTE | > 3.0 ATR | NONE | WAITING | False |

#### Evidence Bundle (28 campos)

O `OpportunityEvidenceBundleV1` documenta TODA a evidência por trás de cada decisão:

| Categoria | Sub-modelos | Conteúdo |
|-----------|-------------|----------|
| Identificação | AssetRef, TimeframeRef, TimingRef | ativo, timeframes, timestamps |
| Decisão | DecisionRef, LevelsRef, RiskRef | direção, entrada/stop/TP, R:R, sizing |
| Fontes | InputRefs, EngineVersions | run_ids de SMC/Elliott/Wyckoff/Contextual |
| Segurança | Guardrails | shadow_only, anti_lookahead, can_promote_trade |
| Zonas | ZonesRef (fvg[], ob[], bpr[], liquidity[]) | zonas SMC envolvidas na decisão |
| Estruturas | StructureRef (bos_choch[], swings[]) | estruturas de suporte |
| Evidências | evidences (smc, structure, contextual) | itens individuais com source_ref |
| Narrativa | Narrative | smc/elliott/wyckoff/risk_explanation |
| Métricas | HitRates | taxa histórica, expectancy |

Bundle hasheado via SHA-256 → `bundle_hash` imutável.

#### Lifecycle — 17 Estados

```
DETECTED → EVIDENCE_PENDING → EVIDENCE_READY → NOTIFICATION_PENDING → NOTIFIED
  → OPENED → WATCHING → ENTRY_TOUCHED → ENTRY_CONFIRMED
    → TP1_REACHED → TP2_REACHED → TP3_REACHED ◄ terminal
    → STOP_REACHED ◄ terminal
  → INVALIDATED ◄ terminal
  → EXPIRED ◄ terminal
  → CANCELLED ◄ terminal
```

18 transições válidas. Estados terminais (sem saída): TP3, STOP, INVALIDATED, EXPIRED, CANCELLED. Guard é lookup table — regras de negócio enforced pelo caller.

---

## 8. Operational Plans — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_gateway/`

### 8.1 `technical_engine_operational_plans_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| plan_id | VARCHAR(64) NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| envelope_id | VARCHAR(64) NULL |
| study_run_id | VARCHAR(64) NULL |
| smc_run_id | VARCHAR(64) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| readiness | VARCHAR(32) NULL |
| operational_status | VARCHAR(32) NULL |
| direction | VARCHAR(16) NULL |
| has_operation | TINYINT(1) NULL |
| entrada | DECIMAL(18,6) NULL |
| stop | DECIMAL(18,6) NULL |
| tp1 | DECIMAL(18,6) NULL |
| tp2 | DECIMAL(18,6) NULL |
| tp3 | DECIMAL(18,6) NULL |
| rr_tp1 | DECIMAL(8,4) NULL |
| rr_tp2 | DECIMAL(8,4) NULL |
| rr_tp3 | DECIMAL(8,4) NULL |
| rr_ponderado | DECIMAL(8,4) NULL |
| r_pts | DECIMAL(18,6) NULL |
| contratos | INT NULL |
| confianca | VARCHAR(16) NULL |
| htf_bias | VARCHAR(16) NULL |
| mtf_align | VARCHAR(32) NULL |
| tf_agreement | TINYINT(1) NULL |
| zone_aligned | TINYINT(1) NULL |
| aligned_tfs | VARCHAR(128) NULL |
| setup_type | VARCHAR(32) NULL |
| entry_mode | VARCHAR(32) NULL |
| stop_method | VARCHAR(32) NULL |
| invalidation_reason | TEXT NULL |
| zone_id | VARCHAR(64) NULL |
| zone_type | VARCHAR(16) NULL |
| zone_low | DECIMAL(18,6) NULL |
| zone_high | DECIMAL(18,6) NULL |
| zone_mid | DECIMAL(18,6) NULL |
| latest_candle_time | DATETIME NULL |
| valid_from | DATETIME NULL |
| valid_until | DATETIME NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'ACTIVE' |
| status_reason | TEXT NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| can_promote_trade | TINYINT(1) NOT NULL DEFAULT 0 |
| apply_automatically | TINYINT(1) NOT NULL DEFAULT 0 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 8.2 `technical_engine_operational_plan_setups_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| setup_id | VARCHAR(64) NOT NULL |
| plan_id | VARCHAR(64) NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| setup_rank | INT NOT NULL DEFAULT 0 |
| setup_type | VARCHAR(32) NULL |
| direction | VARCHAR(16) NULL |
| entrada | DECIMAL(18,6) NULL |
| entry_mode | VARCHAR(32) NULL |
| stop | DECIMAL(18,6) NULL |
| stop_method | VARCHAR(32) NULL |
| r_pts | DECIMAL(18,6) NULL |
| tp1 | DECIMAL(18,6) NULL |
| tp2 | DECIMAL(18,6) NULL |
| tp3 | DECIMAL(18,6) NULL |
| rr_tp1 | DECIMAL(8,4) NULL |
| rr_tp2 | DECIMAL(8,4) NULL |
| rr_tp3 | DECIMAL(8,4) NULL |
| rr_ponderado | DECIMAL(8,4) NULL |
| contratos | INT NULL |
| confianca | VARCHAR(16) NULL |
| perfil_trader | VARCHAR(32) NULL |
| invalidacao | TEXT NULL |
| flags | JSON NULL |
| trailing_data | JSON NULL |
| mtf | JSON NULL |
| zone_id | VARCHAR(64) NULL |
| zone_type | VARCHAR(16) NULL |
| zone_low | DECIMAL(18,6) NULL |
| zone_high | DECIMAL(18,6) NULL |
| zone_mid | DECIMAL(18,6) NULL |
| zone_aligned | TINYINT(1) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 9. Shadow Database Core — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/shadow_database/`

### 9.1 `technical_engine_snapshots_shadow_v2`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| snapshot_id | VARCHAR(128) NOT NULL |
| shadow_snapshot_id | VARCHAR(64) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| asset_class | VARCHAR(64) NULL |
| timeframe | VARCHAR(32) NOT NULL |
| engine_version | VARCHAR(64) NOT NULL |
| config_version | VARCHAR(128) NULL |
| config_hash | VARCHAR(128) NULL |
| config_scope | VARCHAR(64) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| snapshot_hash | VARCHAR(128) NOT NULL |
| zones_count | INT NOT NULL DEFAULT 0 |
| ranked_zones_count | INT NOT NULL DEFAULT 0 |
| events_count | INT NOT NULL DEFAULT 0 |
| snapshot_json | LONGTEXT NOT NULL |
| candles_from | VARCHAR(32) NULL |
| candles_to | VARCHAR(32) NULL |
| last_processed_candle_time | DATETIME NULL |
| processed_only_closed_candles | TINYINT(1) NOT NULL DEFAULT 1 |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| can_promote_trade | TINYINT(1) NOT NULL DEFAULT 0 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 9.2 `technical_engine_study_payloads_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| payload_id | VARCHAR(128) NOT NULL |
| envelope_id | VARCHAR(128) NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| asset_class | VARCHAR(64) NULL |
| timeframe | VARCHAR(32) NOT NULL |
| source_snapshot_hash | VARCHAR(128) NULL |
| source_snapshot_id | VARCHAR(128) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| payload_hash | VARCHAR(128) NOT NULL |
| payload_json | LONGTEXT NOT NULL |
| primary_zones_count | INT NOT NULL DEFAULT 0 |
| secondary_zones_count | INT NOT NULL DEFAULT 0 |
| has_confluence_context | TINYINT(1) NOT NULL DEFAULT 0 |
| has_zone_memory_context | TINYINT(1) NOT NULL DEFAULT 0 |
| has_config_validation_context | TINYINT(1) NOT NULL DEFAULT 0 |
| deterministic_decision | VARCHAR(32) NULL |
| decision_source | VARCHAR(64) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 9.3 `technical_engine_study_drafts_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| draft_id | VARCHAR(128) NOT NULL |
| payload_id | VARCHAR(128) NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(32) NOT NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| draft_hash | VARCHAR(128) NOT NULL |
| draft_markdown | LONGTEXT NOT NULL |
| llm_used | VARCHAR(128) NULL |
| llm_validation_status | VARCHAR(32) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 9.4 `technical_engine_consumer_results_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| consumer_record_id | VARCHAR(128) NOT NULL |
| route | VARCHAR(64) NOT NULL |
| payload_id | VARCHAR(128) NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(32) NOT NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| fingerprint_before | VARCHAR(128) NULL |
| fingerprint_after | VARCHAR(128) NULL |
| result_hash | VARCHAR(128) NOT NULL |
| result_json | LONGTEXT NOT NULL |
| read_only | TINYINT(1) NOT NULL DEFAULT 1 |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 9.5 `technical_engine_visual_overlays_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| visual_payload_id | VARCHAR(128) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| asset_class | VARCHAR(64) NULL |
| timeframe | VARCHAR(32) NOT NULL |
| source_snapshot_hash | VARCHAR(128) NULL |
| source_payload_hash | VARCHAR(128) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| visual_payload_hash | VARCHAR(128) NOT NULL |
| candles_count | INT NOT NULL DEFAULT 0 |
| zones_count | INT NOT NULL DEFAULT 0 |
| visible_zones_count | INT NOT NULL DEFAULT 0 |
| hidden_zones_count | INT NOT NULL DEFAULT 0 |
| events_count | INT NOT NULL DEFAULT 0 |
| has_ranking_context | TINYINT(1) NOT NULL DEFAULT 0 |
| has_memory_context | TINYINT(1) NOT NULL DEFAULT 0 |
| has_confluence_context | TINYINT(1) NOT NULL DEFAULT 0 |
| has_study | TINYINT(1) NOT NULL DEFAULT 0 |
| x_axis_start | VARCHAR(32) NULL |
| x_axis_end | VARCHAR(32) NULL |
| y_axis_min | DECIMAL(20,6) NULL |
| y_axis_max | DECIMAL(20,6) NULL |
| no_1970_epoch_bug | TINYINT(1) NOT NULL DEFAULT 1 |
| candles_visible_not_compressed | TINYINT(1) NOT NULL DEFAULT 1 |
| payload_json | LONGTEXT NOT NULL |
| plotly_figure_json | LONGTEXT NULL |
| html_path | VARCHAR(512) NULL |
| report_path | VARCHAR(512) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 9.6 `technical_engine_audit_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| phase | VARCHAR(64) NOT NULL |
| asset_id | INT NOT NULL DEFAULT 0 |
| symbol | VARCHAR(32) NOT NULL DEFAULT '' |
| timeframe | VARCHAR(32) NOT NULL DEFAULT '' |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| tests_passed | INT NOT NULL DEFAULT 0 |
| tests_failed | INT NOT NULL DEFAULT 0 |
| audit_hash | VARCHAR(128) NOT NULL DEFAULT '' |
| audit_json | LONGTEXT NULL |
| report_path | VARCHAR(512) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 10. Study Pipeline Assembly — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow/`

### 10.1 `technical_engine_study_pipeline_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| trigger_timeframe | VARCHAR(16) NULL |
| context_timeframes_json | JSON NULL |
| input_hash | VARCHAR(128) NULL |
| config_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 10.2 `technical_engine_operational_studies_shadow_v1`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| study_id | VARCHAR(64) NOT NULL |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| bias | VARCHAR(16) NULL |
| action | VARCHAR(16) NULL |
| readiness | VARCHAR(16) NULL |
| confidence_label | VARCHAR(16) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| apply_automatically | TINYINT(1) NOT NULL DEFAULT 0 |
| can_promote_trade | TINYINT(1) NOT NULL DEFAULT 0 |
| available_at | DATETIME NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 10.3 `technical_engine_study_evidences_shadow_v1`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| evidence_id | VARCHAR(64) NOT NULL |
| study_id | VARCHAR(64) NOT NULL |
| source_layer | VARCHAR(32) NOT NULL |
| evidence_type | VARCHAR(64) NOT NULL |
| direction | VARCHAR(16) NULL |
| strength | VARCHAR(16) NULL |
| origin_at | DATETIME NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| price | DECIMAL(18,6) NULL |
| zone_ref | VARCHAR(64) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 10.4 `technical_engine_study_scenarios_shadow_v1`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| scenario_id | VARCHAR(64) NOT NULL |
| study_id | VARCHAR(64) NOT NULL |
| scenario_type | VARCHAR(32) NOT NULL |
| direction | VARCHAR(16) NULL |
| action | VARCHAR(16) NULL |
| readiness | VARCHAR(16) NULL |
| entry_zone_refs_json | JSON NULL |
| invalidation_refs_json | JSON NULL |
| target_refs_json | JSON NULL |
| conditions_json | JSON NULL |
| blockers_json | JSON NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 11. Study Calibration — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_calibration/`

### 11.1 `technical_engine_study_calibration_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| proposal_run_id | VARCHAR(64) NOT NULL |
| replay_run_id | VARCHAR(64) NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| trigger_timeframe | VARCHAR(16) NULL |
| scope_json | JSON NULL |
| input_metrics_json | JSON NULL |
| diagnostics_json | JSON NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| config_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 11.2 `technical_engine_study_calibration_proposals_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| proposal_id | VARCHAR(64) NOT NULL |
| proposal_run_id | VARCHAR(64) NOT NULL |
| proposal_type | VARCHAR(64) NULL |
| target_component | VARCHAR(64) NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'DRAFT' |
| risk_level | VARCHAR(16) NULL |
| requires_backtest_validation | TINYINT(1) DEFAULT 1 |
| apply_automatically | TINYINT(1) DEFAULT 0 |
| proposal_text | TEXT NULL |
| rationale_json | JSON NULL |
| supporting_metrics_json | JSON NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 11.3 `technical_engine_study_calibration_pattern_metrics_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| proposal_run_id | VARCHAR(64) NOT NULL |
| pattern_key | VARCHAR(255) NOT NULL |
| sample_count | INT NOT NULL DEFAULT 0 |
| aligned_count | INT NOT NULL DEFAULT 0 |
| neutral_count | INT NOT NULL DEFAULT 0 |
| conflicted_count | INT NOT NULL DEFAULT 0 |
| alignment_rate | DECIMAL(6,4) NULL |
| support_level | VARCHAR(16) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 12. Study Replay Validation — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_validation/`

### 12.1 `technical_engine_study_replay_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| trigger_timeframe | VARCHAR(16) NULL |
| context_timeframes_json | JSON NULL |
| start_at | VARCHAR(32) NULL |
| end_at | VARCHAR(32) NULL |
| study_window_bars | INT NOT NULL DEFAULT 500 |
| outcome_horizon_bars | INT NOT NULL DEFAULT 24 |
| step_bars | INT NOT NULL DEFAULT 5 |
| total_samples | INT NOT NULL DEFAULT 0 |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| config_hash | VARCHAR(128) NULL |
| input_hash | VARCHAR(128) NULL |
| output_hash | VARCHAR(128) NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 12.2 `technical_engine_study_replay_samples_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| sample_id | VARCHAR(64) NOT NULL |
| run_id | VARCHAR(64) NOT NULL |
| study_id | VARCHAR(64) NULL |
| study_run_id | VARCHAR(64) NULL |
| study_available_at | VARCHAR(32) NULL |
| evaluation_start_at | VARCHAR(32) NULL |
| evaluation_end_at | VARCHAR(32) NULL |
| bias | VARCHAR(16) NULL |
| action | VARCHAR(16) NULL |
| readiness | VARCHAR(16) NULL |
| confidence_label | VARCHAR(16) NULL |
| outcome_label | VARCHAR(32) NULL |
| direction_after_horizon | VARCHAR(16) NULL |
| mfe | DECIMAL(18,6) NULL |
| mae | DECIMAL(18,6) NULL |
| anti_lookahead_status | VARCHAR(16) NULL |
| raw_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 12.3 `technical_engine_study_replay_metrics_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| metric_name | VARCHAR(64) NOT NULL |
| metric_value | VARCHAR(255) NULL |
| metric_json | JSON NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 13. Study Calibration Validation — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_calibration_validation/`

### 13.1 `technical_engine_study_calibration_validation_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| validation_run_id | VARCHAR(64) NOT NULL |
| proposal_run_id | VARCHAR(64) NULL |
| replay_run_id | VARCHAR(64) NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| baseline_metrics_json | JSON NULL |
| candidate_metrics_json | JSON NULL |
| comparison_json | JSON NULL |
| recommendation | VARCHAR(64) NULL |
| status | VARCHAR(32) DEFAULT 'PENDING' |
| raw_json | JSON NOT NULL |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |

### 13.2 `technical_engine_study_calibration_validation_decisions_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| validation_run_id | VARCHAR(64) NOT NULL |
| proposal_id | VARCHAR(64) NOT NULL |
| previous_status | VARCHAR(32) NULL |
| decision | VARCHAR(32) NOT NULL DEFAULT 'KEEP_REVIEW' |
| risk_level | VARCHAR(16) NULL |
| apply_automatically | TINYINT(1) DEFAULT 0 |
| can_promote_trade | TINYINT(1) DEFAULT 0 |
| reason | TEXT NULL |
| raw_json | JSON NULL |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |

---

## 14. Study Candidate Backtest — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/study_pipeline_shadow_candidate/`

### 14.1 `technical_engine_study_candidate_backtest_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| candidate_run_id | VARCHAR(64) NOT NULL |
| proposal_validation_run_id | VARCHAR(64) NULL |
| proposal_run_id | VARCHAR(64) NULL |
| asset_id | BIGINT NULL |
| symbol | VARCHAR(64) NOT NULL |
| base_timeframe | VARCHAR(16) NOT NULL |
| valid_samples | INT DEFAULT 0 |
| recommendation | VARCHAR(64) NULL |
| baseline_metrics_json | JSON NULL |
| candidate_metrics_json | JSON NULL |
| comparison_json | JSON NULL |
| status | VARCHAR(32) DEFAULT 'PENDING' |
| raw_json | JSON NOT NULL |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |

### 14.2 `technical_engine_study_candidate_backtest_samples_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| sample_id | VARCHAR(64) NOT NULL |
| candidate_run_id | VARCHAR(64) NOT NULL |
| study_available_at | VARCHAR(32) NULL |
| baseline_bias | VARCHAR(16) NULL |
| candidate_bias | VARCHAR(16) NULL |
| baseline_readiness | VARCHAR(16) NULL |
| candidate_readiness | VARCHAR(16) NULL |
| baseline_outcome | VARCHAR(32) NULL |
| candidate_outcome | VARCHAR(32) NULL |
| candidate_rules_json | JSON NULL |
| raw_json | JSON NULL |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |

---

## 15. Notification Outbox — Tabela e Colunas

### 15.1 `technical_engine_opportunity_notification_outbox_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| notification_id | VARCHAR(64) NOT NULL |
| alert_id | VARCHAR(64) NULL |
| signal_id | VARCHAR(64) NULL |
| channel | VARCHAR(64) NOT NULL DEFAULT 'websocket' |
| destination | VARCHAR(128) NULL |
| payload | JSON NOT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| attempts | INT NOT NULL DEFAULT 0 |
| max_attempts | INT NOT NULL DEFAULT 3 |
| last_error | TEXT NULL |
| scheduled_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| sent_at | DATETIME NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

---

## 16. Scanner Config Profiles — Tabela e Colunas

### 16.1 `technical_engine_opportunity_scanner_config_profiles_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| profile_id | VARCHAR(64) NOT NULL |
| profile_name | VARCHAR(128) NOT NULL |
| profile_type | VARCHAR(32) NOT NULL DEFAULT 'SCANNER_CALIBRATION' |
| status | VARCHAR(32) NOT NULL DEFAULT 'DRAFT' |
| symbol | VARCHAR(64) NULL |
| hours_window | INT NULL |
| base_config_json | JSON NOT NULL |
| proposed_config_json | JSON NOT NULL |
| changes_json | JSON NOT NULL |
| reason_json | JSON NULL |
| metrics_snapshot_json | JSON NULL |
| created_by | VARCHAR(64) NULL |
| approved_by | VARCHAR(64) NULL |
| approved_at | DATETIME NULL |
| rejected_reason | VARCHAR(255) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| apply_automatically | TINYINT(1) NOT NULL DEFAULT 0 |
| can_promote_trade | TINYINT(1) NOT NULL DEFAULT 0 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP |

---

## 17. Scanner Active Config — Tabelas e Colunas

### 17.1 `technical_engine_opportunity_scanner_active_config_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| active_config_id | VARCHAR(64) NOT NULL |
| profile_id | VARCHAR(64) NOT NULL |
| profile_name | VARCHAR(128) NULL |
| symbol | VARCHAR(64) NULL |
| config_json | JSON NOT NULL |
| source | VARCHAR(32) NOT NULL DEFAULT 'MANUAL_PROFILE_APPLY' |
| status | VARCHAR(32) NOT NULL DEFAULT 'ACTIVE' |
| activated_by | VARCHAR(64) NULL |
| activated_at | DATETIME NOT NULL |
| deactivated_at | DATETIME NULL |
| deactivated_reason | VARCHAR(255) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| can_promote_trade | TINYINT(1) NOT NULL DEFAULT 0 |
| apply_automatically | TINYINT(1) NOT NULL DEFAULT 0 |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |

### 17.2 `technical_engine_opportunity_scanner_config_apply_history_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| apply_id | VARCHAR(64) NOT NULL |
| profile_id | VARCHAR(64) NOT NULL |
| previous_active_config_id | VARCHAR(64) NULL |
| new_active_config_id | VARCHAR(64) NOT NULL |
| action | VARCHAR(32) NOT NULL |
| applied_by | VARCHAR(64) NULL |
| reason | VARCHAR(255) NULL |
| config_snapshot | JSON NOT NULL |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |

---

## 18. Audit Trail — Tabela e Colunas

### 18.1 `technical_engine_audit_trail_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT |
| audit_id | VARCHAR(64) NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| study_time | DATETIME NULL |
| latest_candle_time | DATETIME NULL |
| smc_run_id | VARCHAR(64) NULL |
| study_run_id | VARCHAR(64) NULL |
| envelope_id | VARCHAR(64) NULL |
| plan_id | VARCHAR(64) NULL |
| technical_truth_hash | VARCHAR(128) NULL |
| rendered_text_hash | VARCHAR(128) NULL |
| config_hash | VARCHAR(128) NULL |
| readiness | VARCHAR(32) NULL |
| operational_status | VARCHAR(32) NULL |
| direction | VARCHAR(16) NULL |
| has_operation | TINYINT(1) NULL |
| blockers | JSON NULL |
| readiness_reasons | JSON NULL |
| locked_fields | JSON NULL |
| input_refs | JSON NULL |
| hit_rate_segment_label | VARCHAR(255) NULL |
| hit_rate_n_amostras | INT NULL |
| hit_rate_expectancy_R | DECIMAL(18,6) NULL |
| collector_health_status | VARCHAR(32) NULL |
| collector_health_snapshot | JSON NULL |
| engine_version | VARCHAR(32) NULL |
| risk_version | VARCHAR(32) NULL |
| renderer_version | VARCHAR(32) NULL |
| raw_json | JSON NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |

---

## 19. Hit Rates — Tabela e Colunas

### 19.1 `technical_engine_risk_v2_hit_rates_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| symbol | VARCHAR(32) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| setup_type | VARCHAR(64) NOT NULL |
| ativo | VARCHAR(32) NOT NULL |
| regime | VARCHAR(64) NOT NULL DEFAULT '?' |
| mtf_align | VARCHAR(32) NOT NULL DEFAULT 'htf_neutro' |
| sessao | VARCHAR(32) DEFAULT '?' |
| volatilidade | VARCHAR(32) DEFAULT '?' |
| quality_bucket | VARCHAR(16) DEFAULT '?' |
| segment_label | VARCHAR(255) NOT NULL |
| n_amostras | INT NOT NULL DEFAULT 0 |
| taxa_1R | DECIMAL(8,6) NOT NULL DEFAULT 0.000000 |
| taxa_2R | DECIMAL(8,6) NOT NULL DEFAULT 0.000000 |
| taxa_3R | DECIMAL(8,6) NOT NULL DEFAULT 0.000000 |
| taxa_stop | DECIMAL(8,6) NOT NULL DEFAULT 0.000000 |
| expectancy_R | DECIMAL(10,6) NOT NULL DEFAULT 0.000000 |
| raw_json | JSON NULL |
| shadow_only | TINYINT(1) DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

---

## 20. SMC Reference — Tabelas e Colunas

**SQL:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260516_create_smc_reference_shadow_tables.sql`

### 20.1 `technical_engine_smc_reference_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| candles_count | INT NOT NULL |
| first_candle_at | DATETIME NULL |
| last_candle_at | DATETIME NULL |
| library_name | VARCHAR(128) NOT NULL |
| library_commit | VARCHAR(64) NULL |
| library_version | VARCHAR(64) NULL |
| parameters_json | JSON NOT NULL |
| source_candle_hash | VARCHAR(128) NULL |
| status | VARCHAR(32) NOT NULL |
| error_message | TEXT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.2 `technical_engine_smc_reference_fvg_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| origin_time | DATETIME NULL |
| fvg_direction | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| price_top | DECIMAL(18,6) NOT NULL |
| price_bottom | DECIMAL(18,6) NOT NULL |
| midpoint | DECIMAL(18,6) NOT NULL |
| mitigated_index | INT NULL |
| mitigated_at | DATETIME NULL |
| display_from | DATETIME NULL |
| display_to | DATETIME NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.3 `technical_engine_smc_reference_swings_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| swing_time | DATETIME NULL |
| high_low | INT NOT NULL |
| swing_type | VARCHAR(16) NOT NULL |
| level | DECIMAL(18,6) NOT NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.4 `technical_engine_smc_reference_bos_choch_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| event_time | DATETIME NULL |
| event_type | VARCHAR(16) NOT NULL |
| direction | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| level | DECIMAL(18,6) NOT NULL |
| broken_index | INT NULL |
| broken_at | DATETIME NULL |
| line_start_time | DATETIME NULL |
| line_end_time | DATETIME NULL |
| line_price | DECIMAL(18,6) NOT NULL |
| close_break | BOOLEAN NOT NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.5 `technical_engine_smc_reference_order_blocks_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| origin_time | DATETIME NULL |
| ob_direction | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| price_top | DECIMAL(18,6) NOT NULL |
| price_bottom | DECIMAL(18,6) NOT NULL |
| midpoint | DECIMAL(18,6) NOT NULL |
| ob_volume | DECIMAL(18,6) NULL |
| percentage | DECIMAL(18,6) NULL |
| mitigated_index | INT NULL |
| mitigated_at | DATETIME NULL |
| display_from | DATETIME NULL |
| display_to | DATETIME NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.6 `technical_engine_smc_reference_liquidity_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| liquidity_time | DATETIME NULL |
| liquidity_direction | INT NOT NULL |
| direction_label | VARCHAR(16) NOT NULL |
| level | DECIMAL(18,6) NOT NULL |
| end_index | INT NULL |
| end_at | DATETIME NULL |
| swept_index | INT NULL |
| swept_at | DATETIME NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.7 `technical_engine_smc_reference_previous_high_low_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| candle_time | DATETIME NULL |
| previous_high | DECIMAL(18,6) NULL |
| previous_low | DECIMAL(18,6) NULL |
| broken_high | INT NULL |
| broken_low | INT NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.8 `technical_engine_smc_reference_sessions_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| session_name | VARCHAR(64) NOT NULL |
| ref_index | INT NOT NULL |
| candle_time | DATETIME NULL |
| active | INT NOT NULL |
| session_high | DECIMAL(18,6) NULL |
| session_low | DECIMAL(18,6) NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.9 `technical_engine_smc_reference_retracements_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| ref_index | INT NOT NULL |
| candle_time | DATETIME NULL |
| direction | INT NULL |
| current_retracement_pct | DECIMAL(18,6) NULL |
| deepest_retracement_pct | DECIMAL(18,6) NULL |
| raw_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 20.10 `technical_engine_smc_reference_visual_overlays_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(64) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| source | VARCHAR(32) NOT NULL DEFAULT 'SMC_REFERENCE' |
| payload_json | JSON NOT NULL |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 21. Causal Rebuild V1 — Tabelas e Colunas

**SQL:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260626_causal_rebuild_v1_schema.sql`

### 21.1 `technical_engine_calculation_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| run_uuid | VARCHAR(64) NOT NULL |
| engine_version | VARCHAR(64) NOT NULL |
| calculation_version | VARCHAR(64) NOT NULL |
| parameter_hash | VARCHAR(128) NOT NULL |
| data_hash | VARCHAR(128) NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| status | ENUM('PENDING','BUILDING','VALIDATING','READY','ACTIVE','SUPERSEDED','FAILED') NOT NULL DEFAULT 'PENDING' |
| started_at | DATETIME NULL |
| finished_at | DATETIME NULL |
| candles_processed | BIGINT NOT NULL DEFAULT 0 |
| structures_written | BIGINT NOT NULL DEFAULT 0 |
| events_written | BIGINT NOT NULL DEFAULT 0 |
| peak_memory_mb | DECIMAL(10,2) NULL |
| duration_seconds | DECIMAL(10,2) NULL |
| error_message | TEXT NULL |
| metadata_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 21.2 `technical_engine_active_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| dataset_definition | VARCHAR(128) NOT NULL DEFAULT 'SMC_V2_CAUSAL' |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| active_run_id | VARCHAR(128) NOT NULL |
| supersedes_run_id | VARCHAR(128) NULL |
| activated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| activated_by | VARCHAR(128) NULL DEFAULT 'CAUSAL_REBUILD_V1' |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 21.3 `technical_engine_structure_events_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| structure_type | VARCHAR(32) NOT NULL |
| structure_id | VARCHAR(128) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| sequence | INT NOT NULL DEFAULT 1 |
| event_type | VARCHAR(32) NOT NULL |
| event_at | DATETIME NOT NULL |
| source_candle_id | BIGINT NULL |
| state_before | JSON NULL |
| state_after | JSON NULL |
| reason_code | VARCHAR(64) NULL |
| payload_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 21.4 `technical_engine_indicator_values_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| candle_id | BIGINT NULL |
| candle_timestamp | DATETIME NOT NULL |
| indicator_name | VARCHAR(64) NOT NULL |
| indicator_version | VARCHAR(64) NOT NULL DEFAULT 'v1' |
| parameters_json | JSON NULL |
| value_json | JSON NOT NULL |
| source_close_time | DATETIME NOT NULL |
| calculated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| available_at | DATETIME NOT NULL |
| parameter_hash | VARCHAR(128) NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 21.5 `technical_engine_rebuild_artifacts_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| artifact_type | VARCHAR(64) NOT NULL |
| artifact_name | VARCHAR(256) NOT NULL |
| artifact_path | VARCHAR(512) NULL |
| artifact_hash | VARCHAR(128) NOT NULL |
| artifact_size_bytes | BIGINT NULL |
| metadata_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 22. Causal Rebuild V2 — Tabelas e Colunas

**Python:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260627_causal_rebuild_v2_schema.py`

### 22.1 `technical_engine_rebuild_v2_parent_runs`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| parent_run_id | VARCHAR(128) NOT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| engine_version | VARCHAR(64) NOT NULL |
| calculation_version | VARCHAR(64) NOT NULL |
| parameter_hash | VARCHAR(128) NOT NULL |
| data_hash | VARCHAR(128) NULL |
| status | ENUM('PENDING','BUILDING','VALIDATING','READY','ACTIVE','SUPERSEDED','FAILED') NOT NULL DEFAULT 'PENDING' |
| started_at | DATETIME NULL |
| finished_at | DATETIME NULL |
| total_candles | BIGINT NOT NULL DEFAULT 0 |
| total_structures | BIGINT NOT NULL DEFAULT 0 |
| total_events | BIGINT NOT NULL DEFAULT 0 |
| total_indicators | BIGINT NOT NULL DEFAULT 0 |
| error_code | VARCHAR(64) NULL |
| error_message | TEXT NULL |
| metadata_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 22.2 `technical_engine_rebuild_v2_timeframe_runs`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| parent_run_id | VARCHAR(128) NOT NULL |
| timeframe_run_id | VARCHAR(128) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| first_candle_id | BIGINT NULL |
| first_candle_time | DATETIME NULL |
| last_candle_id | BIGINT NULL |
| last_candle_time | DATETIME NULL |
| candle_count | BIGINT NOT NULL DEFAULT 0 |
| indicators_written | BIGINT NOT NULL DEFAULT 0 |
| structures_written | BIGINT NOT NULL DEFAULT 0 |
| events_written | BIGINT NOT NULL DEFAULT 0 |
| content_hash | VARCHAR(128) NULL |
| status | ENUM('PENDING','BUILDING','VALIDATING','READY','FAILED') NOT NULL DEFAULT 'PENDING' |
| errors_json | JSON NULL |
| metadata_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 22.3 `technical_engine_rebuild_v2_checkpoints`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| timeframe_run_id | VARCHAR(128) NOT NULL |
| sequence | INT NOT NULL |
| last_candle_id | BIGINT NULL |
| last_candle_time | DATETIME NULL |
| engine_state_hash | VARCHAR(128) NULL |
| artifact_path | VARCHAR(512) NULL |
| rows_written | BIGINT NOT NULL DEFAULT 0 |
| metadata_json | JSON NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 22.4 `technical_engine_rebuild_v2_structures`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| timeframe_run_id | VARCHAR(128) NOT NULL |
| logical_structure_id | VARCHAR(128) NOT NULL |
| global_ref_index | BIGINT NULL |
| asset_id | INT NOT NULL |
| symbol | VARCHAR(64) NOT NULL |
| timeframe | VARCHAR(16) NOT NULL |
| structure_type | VARCHAR(32) NOT NULL |
| origin_candle_id | BIGINT NULL |
| origin_at | DATETIME NULL |
| confirmation_candle_id | BIGINT NULL |
| confirmed_at | DATETIME NULL |
| available_at | DATETIME NULL |
| first_touch_at | DATETIME NULL |
| first_touch_candle_id | BIGINT NULL |
| first_touch_price | DECIMAL(20,6) NULL |
| touch_count | INT NOT NULL DEFAULT 0 |
| mitigated_at | DATETIME NULL |
| mitigation_candle_id | BIGINT NULL |
| mitigation_price | DECIMAL(20,6) NULL |
| invalidated_at | DATETIME NULL |
| invalidation_candle_id | BIGINT NULL |
| invalidation_price | DECIMAL(20,6) NULL |
| invalidation_reason | VARCHAR(128) NULL |
| expired_at | DATETIME NULL |
| expiry_reason | VARCHAR(128) NULL |
| direction | VARCHAR(16) NULL |
| direction_int | INT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'ACTIVE' |
| confirmation_type | VARCHAR(32) NULL |
| source_bos_id | VARCHAR(128) NULL |
| source_fvg_id | VARCHAR(128) NULL |
| source_displacement_id | VARCHAR(128) NULL |
| price_top | DECIMAL(20,6) NULL |
| price_bottom | DECIMAL(20,6) NULL |
| midpoint | DECIMAL(20,6) NULL |
| quality_label | VARCHAR(16) NULL |
| quality_score | DECIMAL(10,2) NULL |
| payload_json | JSON NULL |
| payload_hash | VARCHAR(128) NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 22.5 `technical_engine_rebuild_v2_events`

| Coluna | Tipo |
|--------|------|
| id | BIGINT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| timeframe_run_id | VARCHAR(128) NOT NULL |
| logical_structure_id | VARCHAR(128) NOT NULL |
| event_type | VARCHAR(32) NOT NULL |
| event_at | DATETIME NOT NULL |
| source_candle_id | BIGINT NULL |
| state_before | JSON NULL |
| state_after | JSON NULL |
| reason_code | VARCHAR(64) NULL |
| payload_json | JSON NULL |
| payload_hash | VARCHAR(128) NOT NULL |
| shadow_only | TINYINT(1) NOT NULL DEFAULT 1 |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 23. Signal Candidate — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/signal_candidate_v1/`

### 23.1 `technical_engine_signal_candidate_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | INT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| config_hash | VARCHAR(64) NOT NULL |
| dataset_hash | VARCHAR(64) |
| engine_version | VARCHAR(32) NOT NULL DEFAULT 'SIGNAL_CANDIDATE_V1_0_0' |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NOT NULL DEFAULT 1 |
| period_start | DATETIME |
| period_end | DATETIME |
| total_signals | INT DEFAULT 0 |
| total_blocked | INT DEFAULT 0 |
| status | VARCHAR(32) DEFAULT 'PENDING' |
| config_snapshot | JSON |
| error_message | TEXT |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |
| completed_at | DATETIME |

### 23.2 `technical_engine_signal_candidates_shadow`

| Coluna | Tipo |
|--------|------|
| id | INT AUTO_INCREMENT PRIMARY KEY |
| signal_id | VARCHAR(128) NOT NULL |
| run_id | VARCHAR(128) |
| config_hash | VARCHAR(64) |
| smc_state_hash | VARCHAR(64) |
| signal_hash | VARCHAR(64) NOT NULL |
| contract_version | VARCHAR(32) DEFAULT 'SIGNAL_CANDIDATE_V1_0_0' |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NOT NULL DEFAULT 1 |
| setup_type | VARCHAR(64) |
| direction | VARCHAR(16) NOT NULL |
| trend_context | VARCHAR(16) DEFAULT 'NEUTRAL' |
| confirmation | VARCHAR(32) |
| confirmation_triggered | BOOLEAN DEFAULT FALSE |
| entry_price | DOUBLE |
| entry_type | VARCHAR(32) |
| entry_method | VARCHAR(16) DEFAULT 'LIMIT' |
| stop_price | DOUBLE |
| stop_anchor_type | VARCHAR(64) |
| stop_method | VARCHAR(32) |
| stop_distance_atr | DOUBLE |
| tp1_price | DOUBLE |
| tp1_rr | DOUBLE |
| tp1_anchor_type | VARCHAR(64) |
| tp2_price | DOUBLE |
| tp2_rr | DOUBLE |
| tp3_price | DOUBLE |
| tp3_rr | DOUBLE |
| target_count | INT DEFAULT 0 |
| is_blocked | BOOLEAN DEFAULT FALSE |
| block_reasons | JSON |
| signal_time | DATETIME |
| evaluation_time | DATETIME |
| expiry_time | DATETIME |
| payload_json | JSON |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |

### 23.3 `technical_engine_signal_backtest_runs_shadow`

| Coluna | Tipo |
|--------|------|
| id | INT AUTO_INCREMENT PRIMARY KEY |
| run_id | VARCHAR(128) NOT NULL |
| candidate_run_id | VARCHAR(128) |
| control_run_id | VARCHAR(128) |
| config_hash | VARCHAR(64) NOT NULL |
| dataset_hash | VARCHAR(64) |
| engine_version | VARCHAR(32) DEFAULT 'SIGNAL_BACKTEST_V1_0_0' |
| symbol | VARCHAR(64) NOT NULL |
| asset_id | INT NOT NULL DEFAULT 1 |
| period_start | DATETIME |
| period_end | DATETIME |
| window_type | VARCHAR(16) DEFAULT 'TEST' |
| total_signals | INT DEFAULT 0 |
| total_trades | INT DEFAULT 0 |
| total_valid_entries | INT DEFAULT 0 |
| total_survived_stop | INT DEFAULT 0 |
| tp1_before_stop | INT DEFAULT 0 |
| tp1_before_stop_pct | DOUBLE DEFAULT 0 |
| tp2_before_stop | INT DEFAULT 0 |
| tp2_before_stop_pct | DOUBLE DEFAULT 0 |
| tp3_before_stop | INT DEFAULT 0 |
| tp3_before_stop_pct | DOUBLE DEFAULT 0 |
| expectancy_r | DOUBLE DEFAULT 0 |
| profit_factor | DOUBLE DEFAULT 0 |
| max_drawdown_r | DOUBLE DEFAULT 0 |
| average_r | DOUBLE DEFAULT 0 |
| median_mae_r | DOUBLE DEFAULT 0 |
| median_mfe_r | DOUBLE DEFAULT 0 |
| sample_size | INT DEFAULT 0 |
| robustness_score_v1 | DOUBLE DEFAULT 0 |
| low_sample | BOOLEAN DEFAULT FALSE |
| status | VARCHAR(32) DEFAULT 'PENDING' |
| error_message | TEXT |
| monthly_metrics | JSON |
| by_session | JSON |
| by_setup | JSON |
| by_direction | JSON |
| config_snapshot | JSON |
| payload_json | JSON |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |
| completed_at | DATETIME |

### 23.4 `technical_engine_signal_backtest_trades_shadow`

| Coluna | Tipo |
|--------|------|
| id | INT AUTO_INCREMENT PRIMARY KEY |
| trade_id | VARCHAR(128) NOT NULL |
| run_id | VARCHAR(128) NOT NULL |
| signal_id | VARCHAR(128) |
| signal_hash | VARCHAR(64) |
| entry_type | VARCHAR(32) |
| stop_anchor_type | VARCHAR(64) |
| stop_buffer_atr | DOUBLE |
| config_hash | VARCHAR(64) |
| setup_type | VARCHAR(64) |
| direction | VARCHAR(16) NOT NULL |
| window_type | VARCHAR(16) |
| signal_time | DATETIME |
| entry_time | DATETIME |
| resolution_time | DATETIME |
| entry_price | DOUBLE |
| stop_price | DOUBLE |
| tp1 | DOUBLE |
| tp2 | DOUBLE |
| tp3 | DOUBLE |
| result | VARCHAR(32) NOT NULL |
| hit_tp1 | BOOLEAN DEFAULT FALSE |
| hit_tp2 | BOOLEAN DEFAULT FALSE |
| hit_tp3 | BOOLEAN DEFAULT FALSE |
| hit_stop | BOOLEAN DEFAULT FALSE |
| hit_stop_before_entry | BOOLEAN DEFAULT FALSE |
| hit_stop_after_entry | BOOLEAN DEFAULT FALSE |
| valid_entry | BOOLEAN DEFAULT FALSE |
| realized_r | DOUBLE DEFAULT 0 |
| mae_r | DOUBLE DEFAULT 0 |
| mfe_r | DOUBLE DEFAULT 0 |
| bars_to_entry | INT |
| bars_to_tp1 | INT |
| bars_to_tp2 | INT |
| bars_to_tp3 | INT |
| bars_to_stop | INT |
| bars_to_resolution | INT |
| ambiguous_bar | BOOLEAN DEFAULT FALSE |
| resolution_policy | VARCHAR(32) |
| slippage | DOUBLE DEFAULT 0 |
| costs_brl | DOUBLE DEFAULT 0 |
| point_value_brl | DOUBLE DEFAULT 0.2 |
| contracts | INT DEFAULT 1 |
| gross_pnl_brl | DOUBLE DEFAULT 0 |
| net_pnl_brl | DOUBLE DEFAULT 0 |
| payload_json | JSON |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |

### 23.5 `technical_engine_signal_comparisons_shadow`

| Coluna | Tipo |
|--------|------|
| id | INT AUTO_INCREMENT PRIMARY KEY |
| report_id | VARCHAR(128) NOT NULL |
| contract_version | VARCHAR(32) DEFAULT 'SIGNAL_COMPARISON_V1_0_0' |
| control_run_id | VARCHAR(128) |
| candidate_run_id | VARCHAR(128) |
| control_alias | VARCHAR(32) DEFAULT 'CONTROL_A' |
| candidate_alias | VARCHAR(32) DEFAULT 'CANDIDATE_B' |
| symbol | VARCHAR(64) NOT NULL |
| period_start | DATETIME |
| period_end | DATETIME |
| control_total_signals | INT DEFAULT 0 |
| candidate_total_signals | INT DEFAULT 0 |
| signals_only_control | INT DEFAULT 0 |
| signals_only_candidate | INT DEFAULT 0 |
| signals_coincident | INT DEFAULT 0 |
| control_valid_entries | INT DEFAULT 0 |
| candidate_valid_entries | INT DEFAULT 0 |
| control_entry_rate | DOUBLE DEFAULT 0 |
| candidate_entry_rate | DOUBLE DEFAULT 0 |
| control_tp1_before_stop | DOUBLE DEFAULT 0 |
| candidate_tp1_before_stop | DOUBLE DEFAULT 0 |
| control_tp2_before_stop | DOUBLE DEFAULT 0 |
| candidate_tp2_before_stop | DOUBLE DEFAULT 0 |
| control_expectancy_r | DOUBLE DEFAULT 0 |
| candidate_expectancy_r | DOUBLE DEFAULT 0 |
| control_profit_factor | DOUBLE DEFAULT 0 |
| candidate_profit_factor | DOUBLE DEFAULT 0 |
| control_max_drawdown_r | DOUBLE DEFAULT 0 |
| candidate_max_drawdown_r | DOUBLE DEFAULT 0 |
| control_sample_size | INT DEFAULT 0 |
| candidate_sample_size | INT DEFAULT 0 |
| control_robustness | DOUBLE DEFAULT 0 |
| candidate_robustness | DOUBLE DEFAULT 0 |
| candidate_superior | BOOLEAN DEFAULT FALSE |
| superiority_reasons | JSON |
| decision_status | VARCHAR(32) DEFAULT 'NEEDS_MORE_DATA' |
| decision_notes | TEXT |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |

---

## 24. Asset Collector — Tabelas e Colunas

**SQL:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260616_001_create_asset_collector_runtime.sql`

### 24.1 `technical_engine_candle_events`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| event_id | VARCHAR(160) NOT NULL |
| schema_version | VARCHAR(16) NOT NULL DEFAULT '1.0' |
| event_type | VARCHAR(40) NOT NULL DEFAULT 'CANDLE_CLOSED' |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| mt5_symbol | VARCHAR(64) NULL |
| market | VARCHAR(16) NOT NULL DEFAULT 'B3' |
| timeframe | VARCHAR(16) NOT NULL |
| candle_time | DATETIME(6) NOT NULL |
| closed_at | DATETIME(6) NOT NULL |
| payload | JSON NOT NULL |
| status | ENUM('PENDING','PROCESSING','COMPLETED','FAILED','DEAD') NOT NULL DEFAULT 'PENDING' |
| attempts | INT NOT NULL DEFAULT 0 |
| available_at | DATETIME(6) NOT NULL |
| claimed_at | DATETIME(6) NULL |
| claimed_by | VARCHAR(128) NULL |
| processed_at | DATETIME(6) NULL |
| last_error_code | VARCHAR(80) NULL |
| last_error_message | TEXT NULL |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| updated_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) |

### 24.2 `technical_engine_asset_worker_heartbeats`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| worker_id | VARCHAR(128) NOT NULL |
| asset_id | BIGINT NOT NULL |
| symbol | VARCHAR(32) NOT NULL |
| pid | INT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'INIT' |
| last_cycle_started_at | DATETIME(6) NULL |
| last_cycle_finished_at | DATETIME(6) NULL |
| last_candle_times | JSON NULL |
| last_error | JSON NULL |
| metrics | JSON NULL |
| heartbeat_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| updated_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) |

### 24.3 `technical_engine_event_processor_heartbeats`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| processor_id | VARCHAR(128) NOT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'RUNNING' |
| processed_total | INT NOT NULL DEFAULT 0 |
| failed_total | INT NOT NULL DEFAULT 0 |
| dead_total | INT NOT NULL DEFAULT 0 |
| last_event_at | DATETIME(6) NULL |
| metrics | JSON NULL |
| heartbeat_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| updated_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) |

---

## 25. Evidence Bundles — Tabelas e Colunas

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/opportunity_evidence/`

### 25.1 `technical_engine_opportunity_evidence_bundles_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| evidence_bundle_id | VARCHAR(80) NOT NULL |
| opportunity_id | VARCHAR(80) NOT NULL |
| schema_version | VARCHAR(16) NOT NULL DEFAULT '1.0' |
| symbol | VARCHAR(32) NOT NULL |
| asset_id | BIGINT NOT NULL DEFAULT 0 |
| detected_at | DATETIME(6) NOT NULL |
| envelope_id | VARCHAR(80) NULL |
| operational_plan_id | VARCHAR(80) NULL |
| technical_truth_hash | VARCHAR(100) NULL |
| bundle_hash | VARCHAR(100) NULL |
| bundle_json | JSON NOT NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'EVIDENCE_READY' |
| chart_status | VARCHAR(32) NOT NULL DEFAULT 'CHART_PENDING' |
| sync_status | VARCHAR(32) NOT NULL DEFAULT 'SYNC_PENDING' |
| supersedes_bundle_id | VARCHAR(80) NULL |
| rebuild_reason | TEXT NULL |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| updated_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) |

### 25.2 `technical_engine_opportunity_evidence_items_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| item_id | VARCHAR(80) NOT NULL |
| evidence_bundle_id | VARCHAR(80) NOT NULL |
| category | VARCHAR(32) NOT NULL |
| subtype | VARCHAR(64) NOT NULL |
| source_ref | VARCHAR(128) NULL |
| selection_reason | TEXT NULL |
| payload_json | JSON NOT NULL |
| sort_order | INT NOT NULL DEFAULT 0 |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |

### 25.3 `technical_engine_opportunity_evidence_outbox_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| outbox_id | VARCHAR(80) NOT NULL |
| evidence_bundle_id | VARCHAR(80) NOT NULL |
| event_type | VARCHAR(64) NOT NULL |
| payload_hash | VARCHAR(100) NULL |
| status | VARCHAR(32) NOT NULL DEFAULT 'PENDING' |
| attempts | INT NOT NULL DEFAULT 0 |
| available_at | DATETIME(6) NOT NULL |
| last_error | TEXT NULL |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |
| sent_at | DATETIME(6) NULL |

---

## 26. Lifecycle & Outcomes — Tabelas e Colunas

**SQL:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260616_003_create_lifecycle_tables.sql`

### 26.1 `technical_engine_opportunity_lifecycle_events_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| lifecycle_event_id | VARCHAR(80) NOT NULL |
| opportunity_id | VARCHAR(80) NOT NULL |
| event_type | VARCHAR(32) NOT NULL |
| previous_state | VARCHAR(32) NULL |
| new_state | VARCHAR(32) NOT NULL |
| event_time | DATETIME(6) NULL |
| market_price | DECIMAL(18,6) NULL |
| candle_time | VARCHAR(40) NULL |
| timeframe | VARCHAR(8) DEFAULT 'M1' |
| source | VARCHAR(64) DEFAULT 'LIFECYCLE_MONITOR' |
| reason_code | VARCHAR(80) NULL |
| payload | JSON NULL |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |

### 26.2 `technical_engine_opportunity_outcomes_shadow`

| Coluna | Tipo |
|--------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| outcome_id | VARCHAR(80) NOT NULL |
| opportunity_id | VARCHAR(80) NOT NULL |
| final_state | VARCHAR(32) NOT NULL |
| entry_touched | TINYINT(1) DEFAULT 0 |
| entry_confirmed | TINYINT(1) DEFAULT 0 |
| tp1_reached | TINYINT(1) DEFAULT 0 |
| tp2_reached | TINYINT(1) DEFAULT 0 |
| tp3_reached | TINYINT(1) DEFAULT 0 |
| stop_reached | TINYINT(1) DEFAULT 0 |
| invalidated | TINYINT(1) DEFAULT 0 |
| expired | TINYINT(1) DEFAULT 0 |
| max_favorable_excursion_r | DECIMAL(10,4) DEFAULT 0 |
| max_adverse_excursion_r | DECIMAL(10,4) DEFAULT 0 |
| realized_outcome_r | DECIMAL(10,4) DEFAULT 0 |
| bars_to_entry | INT DEFAULT 0 |
| bars_to_resolution | INT DEFAULT 0 |
| ambiguous_bar | TINYINT(1) DEFAULT 0 |
| resolution_policy | VARCHAR(40) DEFAULT 'STOP_FIRST_CONSERVATIVE' |
| resolved_at | DATETIME(6) NULL |
| created_at | DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) |

---

## 27. Zone Memory (File-based)

**Diretório:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/zone_memory/`
**Persistência:** Arquivos JSONL + JSON em `runtime/zone_memory_shadow/`

| Arquivo | Conteúdo |
|---------|----------|
| `zone_memory_samples.jsonl` | Objetos ZoneMemorySampleV2 (line-delimited) |
| `zone_memory_profile.json` | Objetos AssetTimeframeZoneMemoryProfile |

---

## 28. Scripts de Recálculo — Indicadores e Zonas (Apenas Engines Oficiais)

> **Critério:** Apenas scripts que usam `technical_engine.smc_engine_v2`, `technical_engine.elliott`, `technical_engine.wyckoff`, `technical_engine.confluence`, `technical_engine.study_gateway`, `technical_engine.opportunity_scanner` — as engines oficiais V2.

### 28.1 `tools/full_backfill_v2.py` — Backfill SMC V2 (3 meses, todos ativos)

**Caminho:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/full_backfill_v2.py`

**Engine oficial:** `technical_engine.smc_engine_v2.pipeline.run_smc_engine_v2_local()`

**Descrição:** TRUNCATE das 10 tabelas shadow + recálculo SMC V2 para 3 meses de histórico.

**Fluxo:**
1. TRUNCATE todas as tabelas `smc_v2_*_shadow`
2. Para cada ativo/timeframe, carrega ~3 meses de candles
3. Roda `run_smc_engine_v2_local()` e persiste

**Limites de candles por tipo:**

| Tipo | 2min | 5min | 15min | 4h | 1d |
|------|------|------|-------|----|----|
| B3 | 20.000 | 8.000 | 2.600 | 200 | 100 |
| Forex | 50.000 | 20.000 | 6.500 | 500 | 100 |
| Crypto | 65.000 | 26.000 | 9.000 | 600 | 100 |

**Timeframes:** `2min, 5min, 15min, 4h, 1d` (exclui 1min)

**Uso:**
```bash
python3 tools/full_backfill_v2.py
```

---

### 28.2 `tools/recalculate_smc_v2_winfut.py` — Recalculo SMC V2 WINFUT (dataset expandido)

**Caminho:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/recalculate_smc_v2_winfut.py`

**Engine oficial:** `technical_engine.smc_engine_v2.pipeline.run_smc_engine_v2_local()`

**Descrição:** Recalcula SMC V2 para WINFUT com dataset expandido (FASE 6 — 44 meses).

**Tabelas afetadas:** FVG, OB, BOS/CHOCH, Liquidity, Swings

**Limites expandidos:**

| TF | Limite |
|----|--------|
| 5min | 200.000 |
| 15min | 100.000 |
| 4h | 10.000 |
| 1d | 10.000 |

**Uso:**
```bash
python3 tools/recalculate_smc_v2_winfut.py
```

---

### 28.3 `tools/reset_and_recalculate.py` — Reset + Recalculo V2 3 meses + Sync

**Caminho:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/reset_and_recalculate.py`

**Engine oficial:** `technical_engine.smc_engine_v2.pipeline.run_smc_engine_v2_local()`

**Descrição:** Limpa Hostinger + local + roda pipeline SMC V2 para 90 dias + sync total.

**Fluxo:**
1. Limpa Hostinger (sync_zones, sync_studies)
2. Limpa local (smc_zones, V2 shadow tables, analysis_history)
3. Roda pipeline SMC V2 para todos ativos × timeframes × 90 dias
4. Sync tudo para Hostinger

**Uso:**
```bash
python3 tools/reset_and_recalculate.py
```

---

### 28.4 `tools/backfill_smc_zones_winfut.py` — Backfill SMC Zones WINFUT (histórico completo)

**Caminho:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/backfill_smc_zones_winfut.py`

**Engine oficial:** `technical_engine.smc_engine_v2.pipeline.run_smc_engine_v2_local()`

**Descrição:** Backfill SMC V2 zones para WINFUT — histórico completo, um run por TF.

**Limites de candles:**

| TF | Limite |
|----|--------|
| 1min | 1.000.000 |
| 2min | 500.000 |
| 5min | 200.000 |
| 15min | 60.000 |
| 60min | 20.000 |
| 4h | 6.000 |
| 1d | 2.000 |

**Uso:**
```bash
python3 tools/backfill_smc_zones_winfut.py
```

---

### 28.5 `tools/backfill_elliott_wyckoff_winfut.py` — Backfill Elliott + Wyckoff WINFUT

**Caminho:** `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/backfill_elliott_wyckoff_winfut.py`

**Engine oficial:** `technical_engine.elliott`, `technical_engine.wyckoff` (via `infra.sync_v2.run_ew_pipeline_and_persist()`)

**Descrição:** Backfill Elliott + Wyckoff para WINFUT em todos os timeframes.

**Timeframes:** `5min, 15min, 2min, 60min, 4h, 1d`

**Uso:**
```bash
python3 tools/backfill_elliott_wyckoff_winfut.py
```

---

### 28.6 Causal Rebuild (V1/V2/V3)

**Scripts:**
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/rebuild_winfut_causal_v1.py`
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/rebuild_winfut_causal_v2.py`
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/rebuild_winfut_causal_v3.py`

**Engine oficial:** `technical_engine.smc_engine_v2.pipeline.run_smc_engine_v2_local()`

**Migrations:**
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260626_causal_rebuild_v1_schema.sql`
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260627_causal_rebuild_v2_schema.py`
- `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/20260627_causal_rebuild_v3_schema.py`

**Descrição:** Rebuild causal com versionamento, checkpoints, e lifecycle events.

**Tabelas V1:** `calculation_runs`, `active_runs`, `structure_events`, `indicator_values`, `rebuild_artifacts`

**Tabelas V2:** `rebuild_v2_parent_runs`, `rebuild_v2_timeframe_runs`, `rebuild_v2_checkpoints`, `rebuild_v2_structures`, `rebuild_v2_events`

---

### 28.7 Scripts Auxiliares (Engines Oficiais)

| Script | Caminho | Engine Oficial |
|--------|---------|----------------|
| `recalculate_ob_v2_winfut.py` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/recalculate_ob_v2_winfut.py` | `technical_engine.smc_engine_v2` |
| `verify_phase6_smc_recalc.py` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/verify_phase6_smc_recalc.py` | `technical_engine.signal_candidate_v1` |
| `run_winfut_multi_tf_backfill_shadow.py` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/run_winfut_multi_tf_backfill_shadow.py` | `technical_engine.smc_engine_v2` |
| `retry_backfill_forex.py` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/retry_backfill_forex.py` | `technical_engine.smc_engine_v2` |
| `backfill_winfut_historical.py` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/backfill_winfut_historical.py` | `technical_engine.smc_engine_v2` |

---

### 28.8 Scripts NÃO incluídos (usam código LEGADO)

> **Estes scripts NÃO usam as engines oficiais V2.** Foram removidos do relatório principal.

| Script | Motivo |
|--------|--------|
| `rebuild_all.py` | Usa `wyckoff_engine` (legado V1) para Wyckoff. SMC V2 é oficial, mas Wyckoff não. |
| `rebuild_technical_analysis_from_candles.py` | Usa `smc.zone_engine`, `smc.canonical.builder`, `wyckoff_engine` — todos legado V1. |
| `rebuild_winfut.py` | Apenas reordena candles + limpa tabelas. Não recalcula com engine oficial. |
| `nuclear_reset.py` | Usa `wyckoff_engine` (legado V1). |

---

### 28.9 Resumo: Qual Script Usar (Apenas Engines Oficiais)

| Objetivo | Script Recomendado | Engine Oficial |
|----------|-------------------|----------------|
| **Backfill SMC V2 completo (3 meses, todos ativos)** | `tools/full_backfill_v2.py` | `smc_engine_v2.pipeline` |
| **Recalcular SMC V2 WINFUT (dataset expandido)** | `tools/recalculate_smc_v2_winfut.py` | `smc_engine_v2.pipeline` |
| **Reset completo + recalculo + sync** | `tools/reset_and_recalculate.py` | `smc_engine_v2.pipeline` |
| **Backfill SMC zones WINFUT (histórico completo)** | `tools/backfill_smc_zones_winfut.py` | `smc_engine_v2.pipeline` |
| **Backfill Elliott + Wyckoff WINFUT** | `tools/backfill_elliott_wyckoff_winfut.py` | `elliott` + `wyckoff` (via `sync_v2`) |
| **Rebuild causal com versionamento** | `tools/rebuild_winfut_causal_v3.py` | `smc_engine_v2.pipeline` |
| **Recalcular indicadores (EMA/RSI/ATR)** | Nenhum script dedicado | Cálculo inline em `rebuild_all.py` (Fase 3) |

> **Nota sobre indicadores (EMA/RSI/ATR):** Não existe um script dedicado que use engine oficial para recalcular indicadores. O `rebuild_all.py` recalcula inline (código direto, sem engine), mas usa `wyckoff_engine` legado na Fase 5. Para recalcular apenas indicadores, usar `rebuild_all.py` Fase 3 ou criar script dedicado.

---

## 29. Guardrails

| Regra | Descrição |
|-------|-----------|
| `shadow_only=True` | Nunca escrever em tabelas oficiais |
| `llm_decision_used=False` | LLM é redatora, nunca motor |
| `smc_recomputed=False` | SMC consumido por run_id, nunca recalculado |
| `deterministico=True` | Mesmo input = mesmo output (SHA-256) |
| `anti_lookahead=True` | exclude_last_open_candle, available_at < study_time |

---

*Relatório gerado a partir de `docs_geral/ARQUITETURA_OFICIAL.md` e código fonte.*
*Total: 85 tabelas shadow + 1 file store (Zone Memory)*
