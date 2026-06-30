# CONTRACT TRACEABILITY MATRIX — SMC ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Versão da matriz:** 2.0  
**Documento superior:** `PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt`  
**Objetivo:** registrar ownership, produção, consumo, campos críticos, gates e regras de compatibilidade dos contratos cross-engine.

---

## 1. Regras gerais

1. Cada conceito possui um único produtor autoritativo.
2. Consumidores não podem recriar o contrato recebido.
3. Entidades derivadas respeitam:

```text
derived.available_index >= max(dependency.available_index)
```

4. Todos os timestamps persistidos devem ser timezone-aware e normalizados em UTC.
5. Índices só podem ser comparados dentro do mesmo `stream_id`.
6. Mudança breaking exige nova major version, migration e adapter.
7. IDs canônicos devem ser determinísticos.
8. Associações opcionais não participam da identidade primária.
9. Nenhuma engine pode importar código do backup `smc_engine_v2`.
10. A persistência V3 não pode reutilizar tabelas V2 para resultados de semântica V3.

---

## 2. Ownership principal

| Domínio | Produtor autoritativo | Contratos principais |
|---|---|---|
| Calendário e sessões | Sessions V3 | `MarketCalendarDayV3`, `SessionInstanceV3`, `PeriodInstanceV3`, `TradingPeriodSummaryV3` |
| Pivôs e swings | Swing V3 | `RawPivotV3`, `CanonicalSwingV3`, `SwingRevisionV3`, `EqualLevelClusterV3` |
| Estrutura | Structure V3 | `StructureLevelV3`, `StructureStateV3`, `StructureEventV3`, `StructureLegV3`, `SwingSmcRoleProjectionV3` |
| Níveis anteriores | Previous Period V3 | `PreviousPeriodLevelV3`, `PreviousPeriodLevelEventV3` |
| Dealing range e pricing | Retracement V3 | `DealingRangeV3`, `RetracementSampleV3`, `RetracementEventV3`, `RangeRevisionV3` |
| Liquidez | Liquidity V3 | `LiquidityPoolV3`, `LiquidityPoolMemberV3`, `LiquidityEventV3` |
| FVG/IFVG/BPR | FVG V3 | `FvgEventV3`, `FvgLifecycleEventV3`, `BprProjectionV3` |
| Order Block | Order Block V3 | `OrderBlockV3`, `OrderBlockLifecycleEventV3`, `OrderBlockRefinementV3` |
| Associações | Context Association | `ContextAssociationV1`, `EntityRelationshipV1` |

---

## 3. Matriz produtor → consumidor

| # | Produtor | Contrato | Consumidor | Finalidade | Gate mínimo |
|---:|---|---|---|---|---|
| 1 | Sessions | `SessionInstanceV3` | Previous Period | Sessão atual/anterior e trading date | G1 |
| 2 | Sessions | `TradingPeriodSummaryV3` | Previous Period | High/low de período concluído | G1 |
| 3 | Sessions | `SessionInstanceV3` | Liquidity | Session high/low como fonte | G1 |
| 4 | Swing | `CanonicalSwingContractV1` | Structure | BOS/CHOCH e levels | G2 |
| 5 | Structure | `SwingSmcRoleProjectionV3` | Read models, OB, Liquidity | Protected/weak sem alterar Swing | G3 |
| 6 | Swing | `EqualLevelClusterV3` | Liquidity | Promoção EQH/EQL para pool | G2 |
| 7 | Structure | `StructureLegV3` | Retracement | Dealing range estrutural | G3 |
| 8 | Structure | `StructureEventV3` | FVG | Classificação displacement/contextual | G3 |
| 9 | Structure | `StructureEventV3` | Order Block | Confirmação estrutural do OB | G3 |
| 10 | Previous Period | `PreviousPeriodLevelV3` | Liquidity | PDH/PDL/PWH/PWL como pool | G4 |
| 11 | Retracement | `DealingRangeV3` | Liquidity | ERL/IRL e posição no range | G5 |
| 12 | Retracement | `DealingRangeV3` | FVG | Premium/Discount do FVG | G5 |
| 13 | Retracement | `DealingRangeV3` | Order Block | Premium/Discount do OB | G5 |
| 14 | Liquidity | `LiquidityEventV3` | FVG | Sweep anterior ao displacement | G6 |
| 15 | Liquidity | `LiquidityEventV3` | Order Block | Sweep/inducement como evidência | G6 |
| 16 | FVG | `FvgEventV3` | Order Block | Evidência opcional de imbalance | G7 |
| 17 | FVG | `FvgEventV3` | BPR | Overlap bull/bear | G7 |
| 18 | OB/FVG/Ranges/Liquidity | IDs canônicos | Association Service | Enriquecimento bidirecional | G8 |
| 19 | Todas | Read models V3 | Study Gateway | Technical truth shadow | G9 |
| 20 | Todas | Read models V3 | Evidence Bundle/Dashboard/Sync | Exposição e rastreabilidade | G9 |

---

## 4. Mapeamento Swing → Structure

### Contrato público

```text
CanonicalSwingContractV1
```

| Campo interno Swing | Campo canônico | Campo usado por Structure | Regra |
|---|---|---|---|
| `swing_id` | `swing_id` | `swing_id` | Obrigatório |
| `pivot_id` | `source_pivot_id` | `source_pivot_id` | Referência |
| `asset` | `asset_id` | `asset_id` | Identidade |
| `timeframe` | `timeframe` | `timeframe` | Obrigatório |
| `scope` | `scope` | `scope` | `INTERNAL` ou `SWING` |
| `high_low` | `high_low` | `high_low` | `1` high, `-1` low |
| `swing_type` | `swing_kind` | `swing_kind` | Não mapear para method |
| método interno | `detection_method` | `detection_method` | Conceito distinto |
| HH/HL/LH/LL/EQH/EQL | `structural_classification` | `structural_classification` | Geometria |
| `pivot_confirmed_index` | `confirmed_index` | `confirmed_index` | Nome público |
| `pivot_confirmed_at` | `confirmed_at` | `confirmed_at` | UTC |
| `available_index` | `available_index` | `available_index` | Anti-lookahead |
| `available_at` | `available_at` | `available_at` | UTC |
| `origin_index` | `origin_index` | `origin_index` | Origem geométrica |
| `origin_at` | `origin_at` | `origin_at` | UTC |
| `Level` legado | `price` | `price` | `Level` proibido em novo schema |
| `status` | `status` | `is_valid` derivado | Sem alteração silenciosa |
| `engine_version` | `engine_version` | `source_version` | Auditoria |
| `config_hash` | `config_hash` | `config_hash` | Reprodutibilidade |

---

## 5. EQH/EQL — ownership resolvido

### Fonte geométrica

**Swing V3** é a única engine autorizada a decidir:

- membros do cluster;
- EQH ou EQL;
- tolerância de igualdade;
- `price_min`;
- `price_max`;
- `price_mean`;
- `cluster_id`;
- `available_index`.

### Promoção operacional

**Liquidity V3** recebe `EqualLevelClusterV3` e define:

- `BSL` para EQH;
- `SSL` para EQL;
- boundaries do pool;
- role;
- ERL/IRL;
- sweep buffer;
- lifecycle;
- wick sweep;
- close-through;
- reclaim;
- consumption.

| EqualLevelCluster | LiquidityPool |
|---|---|
| `cluster_id` | `source_cluster_id` |
| `EQH` | `BSL` |
| `EQL` | `SSL` |
| `member_swing_ids` | `member_swing_ids` |
| `price_min` | `price_min` |
| `price_max` | `price_max` |
| `price_mean` | `mean_price` |
| `available_index` | `source_available_index` |
| `scope` | `scope` |

Regra:

```text
um cluster_id → no máximo um pool primário por scope e detector_version
```

---

## 6. Sessions → Previous Period

| Sessions | Previous Period | Regra |
|---|---|---|
| `period_instance_id` | `reference_period_id` | Identidade |
| `period_type` | `period_type` | DAY/WEEK/MONTH/etc. |
| `scheduled_start` | `reference_period_start` | UTC |
| `scheduled_end` | `reference_period_end` | UTC |
| `completed_at` | `reference_period_completed_at` | Obrigatório |
| `high` | HIGH level price | Não reagregar |
| `low` | LOW level price | Não reagregar |
| `available_index` | `reference_period_available_index` | Causal |
| `is_complete` | `is_reference_period_complete` | Parcial bloqueado por padrão |
| `continuity_status` | `continuity_status` | Propagar |
| `is_rollover_affected` | `is_rollover_affected` | Propagar |

Previous Period não pode criar outro período concluído autoritativo.

---

## 7. Structure → Retracement

| StructureLegV3 | DealingRangeV3 |
|---|---|
| `leg_id` | `impulse_leg_id` |
| `origin_swing_id` | `origin_swing_id` |
| `endpoint_swing_id` | `endpoint_swing_id` |
| `direction` | `impulse_direction` |
| `scope` | `scope` |
| `structure_event_id` | `structure_event_id` |
| `available_index` | `structural_leg_available_index` |
| `high` | `top` ou extremo |
| `low` | `bottom` ou extremo |

Invariante:

```text
DealingRange.available_index
>= max(origin swing, endpoint swing, StructureLeg, StructureEvent availability)
```

Ownership de `DealingRangeV3`: Retracement/Pricing.

---

## 8. Retracement → Liquidity

| DealingRangeV3 | Liquidity |
|---|---|
| `dealing_range_id` | `dealing_range_id` |
| `top` | `range_high` |
| `bottom` | `range_low` |
| `equilibrium` | `equilibrium` |
| `origin_swing_id` | `range_origin_swing_id` |
| `endpoint_swing_id` | `range_endpoint_swing_id` |
| `range_available_index` | `source_available_index` |
| `status` | `source_range_status` |
| `scope` | `scope` |

Liquidity usa o range para ERL/IRL; não pode recalculá-lo.

---

## 9. FVG ↔ Order Block

Não existe dependência circular de criação.

### Etapa 1 — FVG

FVG é criado sem `source_ob_id` obrigatório.

### Etapa 2 — Order Block

OB pode consumir FVG já disponível como evidência.

### Etapa 3 — Association Service

Cria relação:

```text
relationship_type = ORIGINATES_FROM_OB
from_entity_id = fvg_id
to_entity_id = order_block_id
```

A associação:

- não altera `fvg_id`;
- não altera `order_block_id`;
- não muda disponibilidade histórica;
- não reclassifica silenciosamente score passado.

---

## 10. BPR

Produtor base: FVG V3.

Entrada mínima:

- FVG bullish confirmado;
- FVG bearish confirmado;
- overlap geométrico;
- disponibilidade causal dos dois.

Saída:

```text
BprProjectionV3
```

Campos mínimos:

- `bpr_id`;
- `bullish_fvg_id`;
- `bearish_fvg_id`;
- `top`;
- `bottom`;
- `midpoint`;
- `confirmed_index`;
- `available_index`;
- `status`;
- `quality`;
- `engine_version`;
- `config_hash`.

Gate: G7B.

---

## 11. Campos compartilhados obrigatórios

Todas as entidades persistidas devem considerar, quando aplicável:

```text
entity_id
schema_name
schema_version
engine_name
engine_version
asset_id
symbol
timeframe
scope
stream_id

origin_index
origin_at
confirmed_index
confirmed_at
available_index
available_at
earliest_execution_index
earliest_execution_at

config_hash
source_data_version
run_id
replay_id
created_at
updated_at

continuity_status
is_cross_session
is_data_gap_affected
is_rollover_affected

shadow_only
can_promote_trade
apply_automatically
llm_decision_used
is_lookahead_safe

raw
evidence
```

---

## 12. Matriz de compatibilidade

| Producer | Schema produzido | Consumer | Versão mínima | Adapter | Estado |
|---|---|---|---|---|---|
| Sessions V3 | `session-instance/3.x` | Previous Period V3 | 3.0 | Não | PLANNED |
| Sessions V3 | `period-summary/3.x` | Previous Period V3 | 3.0 | Não | PLANNED |
| Swing V3 | `canonical-swing/1.x` | Structure V3 | 1.0 | `SwingAvailabilityAdapterV1` | REQUIRED |
| Swing V3 | `equal-level-cluster/3.x` | Liquidity V3 | 3.0 | `EqualLevelLiquidityPromoter` | REQUIRED |
| Structure V3 | `structure-leg/3.x` | Retracement V3 | 3.0 | Não | REQUIRED |
| Previous Period V3 | `previous-period-level/3.x` | Liquidity V3 | 3.0 | Promoter | REQUIRED |
| Retracement V3 | `dealing-range/3.x` | Liquidity V3 | 3.0 | Não | REQUIRED |
| Structure V3 | `structure-event/3.x` | FVG V3 | 3.0 | Não | REQUIRED |
| Structure V3 | `structure-event/3.x` | Order Block V3 | 3.0 | Não | REQUIRED |
| Liquidity V3 | `liquidity-event/3.x` | FVG/OB V3 | 3.0 | Não | REQUIRED |
| FVG V3 | `fvg-event/3.x` | Order Block V3 | 3.0 | Não | OPTIONAL EVIDENCE |

Estados permitidos:

```text
PLANNED
IMPLEMENTED
APPROVED_SHADOW
DEPRECATED
BLOCKED
```

---

## 13. Contract tests obrigatórios

Para cada linha da matriz:

- schema válido;
- campos obrigatórios;
- enum compatível;
- timestamps UTC e timezone-aware;
- `available_index` causal;
- ID determinístico;
- foreign IDs resolvíveis;
- consumidor tolera campos opcionais adicionais;
- campos desconhecidos seguem política de versão;
- batch/replay/live produzem contrato equivalente;
- restart não duplica entidade/evento.

---

## 14. Controle de alterações

Toda mudança desta matriz deve atualizar:

1. plano-mestre;
2. plano individual do produtor;
3. plano individual do consumidor;
4. schema registry;
5. compatibility matrix;
6. contract tests;
7. changelog;
8. ADR quando houver alteração de ownership ou semântica.
