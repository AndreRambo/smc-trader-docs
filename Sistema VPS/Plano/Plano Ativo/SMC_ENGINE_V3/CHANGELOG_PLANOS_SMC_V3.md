# CHANGELOG — PLANOS SMC ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Pacote documental:** Plano-mestre + 8 planos individuais  
**Formato:** mudanças documentais e arquiteturais, não changelog do código-fonte

---

## Convenção

Cada entrada deve conter:

- data;
- versão;
- documento;
- tipo de alteração;
- resumo;
- contratos afetados;
- gates afetados;
- necessidade de ADR;
- necessidade de migration;
- impacto em testes;
- responsável;
- status.

Tipos:

```text
ADDED
CHANGED
DEPRECATED
REMOVED
FIXED
SECURITY
ARCHITECTURE
```

---

# 2026-06-30 — Versão documental 2.1

## Atualização dos 8 Planos Individuais

**Tipo:** `CHANGED`, `ARCHITECTURE`

### Alterações realizadas

- Arquivos renomeados com prefixos numéricos (00-08) para ordem canônica
- FVG V2 renomeado para `07_PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V3.md`
- Order Block V2 renomeado para `08_PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V3.md`
- Cabeçalho padrão V2.0 adicionado a todos os 8 planos
- Seções obrigatórias de alinhamento ao plano-mestre adicionadas
- Ownership documentado em cada plano individual
- Gates de entrada e saída documentados em cada plano
- Contratos consumidos e produzidos documentados
- Caminhos batch e incrementais documentados
- Persistência V3 documentada
- Proibição de importar backup V2 registrada
- Guardrails V3 documentados em cada plano
- INDEX.md atualizado com versão 2.1 e nomes canônicos
- Backup criado em `Arquivados/SMC_ENGINE_V3_PRE_ATUALIZACAO_20260630_170234`

### Nomes renomeados

| Nome antigo | Nome novo |
|---|---|
| `PLANO_MESTRE_...` | `00_PLANO_MESTRE_...` |
| `PLANO_OPERACIONAL_..._SESSIONS_...` | `01_PLANO_OPERACIONAL_..._SESSIONS_...` |
| `PLANO_OPERACIONAL_..._SWING_...` | `02_PLANO_OPERACIONAL_..._SWING_...` |
| `PLANO_OPERACIONAL_..._STRUCTURE_...` | `03_PLANO_OPERACIONAL_..._STRUCTURE_...` |
| `PLANO_OPERACIONAL_..._PREVIOUS_...` | `04_PLANO_OPERACIONAL_..._PREVIOUS_...` |
| `PLANO_OPERACIONAL_..._RETRACEMENT_...` | `05_PLANO_OPERACIONAL_..._RETRACEMENT_...` |
| `PLANO_OPERACIONAL_..._LIQUIDITY_...` | `06_PLANO_OPERACIONAL_..._LIQUIDITY_...` |
| `PLANO_OPERACIONAL_..._FVG_ENGINE_V2` | `07_PLANO_OPERACIONAL_..._FVG_ENGINE_V3` |
| `PLANO_OPERACIONAL_..._ORDER_BLOCK_ENGINE_V2` | `08_PLANO_OPERACIONAL_..._ORDER_BLOCK_ENGINE_V3` |

### Status dos planos

| Plano | Status | Gate | Observação |
|---|---|---|---|---|
| Sessions | IMPLEMENTED | G1 | Core implementado |
| Swing | IMPLEMENTED | G2 | Core implementado |
| Structure | IMPLEMENTED | G3 | Core implementado |
| Previous High/Low | IMPLEMENTED | G4 | Core implementado |
| Retracement/Pricing | IMPLEMENTED | G5 | Core implementado |
| Liquidity | IMPLEMENTED | G6 | Core implementado |
| FVG | IMPLEMENTED | G7 | Core implementado |
| Order Block | IMPLEMENTED | G8 | Core implementado |

### Confirmações

- Zero alteração em código-fonte
- Zero migration executada
- Zero alteração em banco de dados
- V2 não foi movida
- Imports não foram alterados
- Nenhum cutover declarado

---

# 2026-06-30 — Versão documental 2.0

## Plano-mestre

**Tipo:** `ARCHITECTURE`, `CHANGED`, `FIXED`

### Alterações

- documentos oficiais elevados a autoridade de nível zero;
- definido que o plano-mestre não substitui os oito planos individuais;
- adicionada política de precedência documental;
- adicionada matriz de autoridade por assunto;
- adicionada rastreabilidade dos planos individuais;
- definido `smc_engine_v3` como único módulo ativo;
- definido `/backups/smc_engine_v2` como backup congelado fora do runtime;
- removida obrigação de adapter runtime para o backup;
- adicionadas fases M-1A, M-1B e M-1C;
- separado cutover de pacote de cutover semântico;
- adicionados modos `BASELINE_COMPAT`, `V3_INCREMENTAL_SHADOW` e `V3_CANONICAL`;
- reconciliada a infraestrutura incremental já existente;
- adicionado reuse obrigatório de checkpoints, snapshots, IDs determinísticos e escrita atômica;
- adicionada persistência V3 separada;
- adicionadas integrações com Study Gateway, Risk Management, Opportunity Scanner, Evidence Bundle, FastAPI, dashboard e sync;
- adicionado BPR ao escopo orquestrado;
- adicionado Gate G7B;
- adicionado Gate G10 para cutover semântico;
- incorporados guardrails oficiais;
- definido que nenhuma zona ativa pode ser removida por limite arbitrário de quantidade;
- definido que Volume Profile permanece fora do escopo.

### Contratos afetados

- todos os contratos cross-engine;
- `CanonicalSwingContractV1`;
- `StructureLegV3`;
- `TradingPeriodSummaryV3`;
- `DealingRangeV3`;
- `EqualLevelClusterV3`;
- `LiquidityPoolV3`;
- `FvgEventV3`;
- `OrderBlockV3`;
- `ContextAssociationV1`.

### Gates afetados

```text
G-1, G0, G1, G2, G3, G4, G5, G6, G7, G7B, G8, G9, G10
```

### ADRs requeridos

- shared temporal contract;
- Swing/Structure layering;
- equal-level ownership;
- dealing-range ownership;
- period-summary ownership;
- OB/FVG association;
- deterministic IDs;
- schema versioning;
- package cutover versus semantic cutover.

---

## Sessions Plan

**Versão alvo:** 2.0  
**Tipo:** `CHANGED`

### Alterações exigidas

- apontar para `smc_engine_v3`;
- produzir `SessionInstanceV3`, `PeriodInstanceV3` e `TradingPeriodSummaryV3`;
- integrar-se ao incremental existente;
- assumir ownership exclusivo de trading calendar e período concluído;
- retirar ownership concorrente do Previous Period;
- adicionar Gate G1;
- usar persistência V3 separada.

**Status:** `UPDATED` (2026-06-30)

---

## Swing Plan

**Versão alvo:** 2.0  
**Tipo:** `FIXED`, `CHANGED`

### Alterações exigidas

- camadas 1–3 independentes da Structure;
- protected/weak como projeção produzida pela Structure;
- adotar `CanonicalSwingContractV1`;
- separar `swing_kind` de `detection_method`;
- tornar Swing fonte geométrica única de EQH/EQL;
- integrar-se ao componente incremental existente;
- adicionar Gate G2.

**Status:** `UPDATED` (2026-06-30)

---

## Structure Plan

**Versão alvo:** 2.0  
**Tipo:** `CHANGED`

### Alterações exigidas

- consumir `CanonicalSwingContractV1`;
- produzir `StructureEventV3`, `StructureLevelV3`, `StructureLegV3`;
- produzir `SwingSmcRoleProjectionV3`;
- não modificar o registro canônico do Swing;
- usar o componente incremental BOS/CHOCH existente;
- adicionar Gate G3.

**Status:** `UPDATED` (2026-06-30)

---

## Previous High/Low Plan

**Versão alvo:** 2.0  
**Tipo:** `FIXED`, `CHANGED`

### Alterações exigidas

- consumir `TradingPeriodSummaryV3`;
- remover ownership autoritativo de `CompletedPeriodV3`;
- não usar resampling concorrente quando Sessions fornecer o período;
- integrar-se ao componente incremental existente;
- adicionar Gate G4.

**Status:** `UPDATED` (2026-06-30)

---

## Retracement/Pricing Plan

**Versão alvo:** 2.0  
**Tipo:** `CHANGED`

### Alterações exigidas

- ownership exclusivo de `DealingRangeV3`;
- consumir `StructureLegV3`;
- preservar e testar `FIBONACCI_ANCHOR`;
- reutilizar componente incremental existente;
- não permitir range concorrente em Liquidity;
- adicionar Gate G5.

**Status:** `UPDATED` (2026-06-30)

---

## Liquidity Plan

**Versão alvo:** 2.0  
**Tipo:** `FIXED`, `CHANGED`

### Alterações exigidas

- `EqualLevelLiquidityDetector` deixa de redetectar clusters;
- usar `EqualLevelLiquidityPromoter`;
- consumir `EqualLevelClusterV3`;
- consumir protected/weak da Structure;
- consumir PreviousPeriodLevel;
- consumir `DealingRangeV3`;
- reutilizar componente incremental existente;
- adicionar Gate G6.

**Status:** `UPDATED` (2026-06-30)

---

## FVG Plan

**Versão alvo:** 2.0  
**Nome ativo:** `PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V3.md`  
**Tipo:** `CHANGED`, `ADDED`

### Alterações exigidas

- atualizar caminho para V3;
- reconciliar batch e incremental;
- manter geometria de três candles;
- incorporar IFVG e BPR;
- consumir Structure/Liquidity/DealingRange apenas para contexto;
- adicionar Gates G7 e G7B;
- persistir em schema V3 separado.

**Status:** `UPDATED` (2026-06-30)

---

## Order Block Plan

**Versão alvo:** 2.0  
**Nome ativo:** `PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V3.md`  
**Tipo:** `CHANGED`, `FIXED`

### Alterações exigidas

- atualizar caminho para V3;
- reconciliar batch e incremental;
- preservar subtipos `NORMAL`, `REJECTION`, `STACKED`;
- documentar que `STACKED` não é breaker block clássico;
- consumir StructureLeg, StructureEvent, Liquidity e FVG opcional;
- adicionar Gate G8;
- persistir em schema V3 separado.

**Status:** `UPDATED` (2026-06-30)

---

# Documentos auxiliares adicionados

## `INDEX.md`

**Tipo:** `ADDED`

Contém:

- lista dos documentos;
- ordem global;
- gates;
- documentos por wave;
- relatórios exigidos;
- modos de execução;
- guardrails.

## `CONTRACT_TRACEABILITY_MATRIX.md`

**Tipo:** `ADDED`

Contém:

- ownership;
- matriz produtor/consumidor;
- mapeamentos campo a campo;
- resolução EQH/EQL;
- resolução OB/FVG;
- compatibility matrix;
- contract tests.

## `CHANGELOG_PLANOS_SMC_V3.md`

**Tipo:** `ADDED`

Contém:

- histórico das mudanças documentais;
- versão de cada plano;
- pendências;
- impactos em contratos, gates, migrations e testes.

---

# 2026-06-30 — Versão documental 2.2 (Implementação)

## Implementação das 8 Engines + Orquestração + Contexto

**Tipo:** `ADDED`, `ARCHITECTURE`

### Resumo

Todos os 10 gates (G0–G10) implementados como packages shadow-only independentes no diretório `technical_engine/`. Cada engine segue os contratos congelados em M0 e os planos individuais 01–08.

### Packages criados

| Package | Gate | Módulos | Testes | Commit |
|---|---|---|---|---|
| `technical_engine/contracts/` | G0 | 18 | 11 | `ec1e2ce` |
| `technical_engine/sessions/` | G1 | 16 | 91 | `176e88e` |
| `technical_engine/swings/` | G2 | 12 | 36 | `6802c68` |
| `technical_engine/structure/` | G3 | 9 | 25 | `12ae343` |
| `technical_engine/previous_period/` | G4 | 7 | 15 | `a713c74` |
| `technical_engine/retracements/` | G5 | 8 | 14 | `f47249f` |
| `technical_engine/liquidity/` | G6 | 6 | 5 | `2515357` |
| `technical_engine/fvg/` | G7 | 5 | 6 | `5469599` |
| `technical_engine/order_block/` | G8 | 5 | 5 | `5b94837` |
| `technical_engine/context/` | G9 | 2 | 4 | `765d4f2` |
| `technical_engine/orchestration/` | G10 | 2 | 4 | `58bd971` |
| `technical_engine/integration/` | G9 | 4 | 13 | `76527f4` |
| `technical_engine/cutover/` | G10 | 4 | 13 | `1c57968` |
| **Total** | | **98** | **242** | |

### Princípios implementados

- `shadow_only=True` em todas as engines
- Anti-lookahead (`available_index >= confirmed_index >= origin_index`)
- Histórico imutável (supersession sem deleção)
- Paridade batch/incremental verificada em Swing e Structure
- IDs determinísticos (SHA-256, sem UUID aleatório)
- Contratos congelados em `contracts/` (28 dataclasses frozen)
- Separação de camadas (Swing→Structure→Retracement→Liquidity)

### Contratos implementados

- `CanonicalSwingContractV1` (Swing → Structure)
- `StructureEventV3` (Structure → FVG, OB)
- `StructureLegV3` (Structure → Retracement)
- `SwingSmcRoleProjectionV3` (Structure → OB, Liquidity)
- `PreviousPeriodLevelV3` (PreviousPeriod → Liquidity)
- `DealingRangeV3` (Retracement → Liquidity)
- `EqualLevelClusterV3` (Swing → Liquidity)
- `LiquidityPoolV3` (Liquidity → consumers)
- `FvgEventV3` (FVG → OB, Liquidity)
- `OrderBlockV3` (OB → consumers)
- `ContextAssociationV1` (cross-engine links)

### Gates afetados

```text
G0, G1, G2, G3, G4, G5, G6, G7, G8, G9, G10 — todos APROVADOS
```

### Pendências (não implementadas)

- Fase 0 de auditoria e baseline para cada engine
- Feature flags (`*_ENGINE_MODE`, `*_WRITE_ENABLED`)
- Persistência real em shadow tables
- Replay shadow multi-ativo/multi-timeframe
- Observabilidade (logs/métricas estruturadas)
- Migração controlada com etapas progressivas
- Documentação detalhada de rollback por engine

### Status

```text
IMPLEMENTACAO_COMPLETA_SHADOW — G0-G10 aprovados
```

```markdown
## YYYY-MM-DD — Versão X.Y

### Documento

**Tipo:** ADDED | CHANGED | DEPRECATED | REMOVED | FIXED | ARCHITECTURE

**Resumo:**
- ...

**Contratos afetados:**
- ...

**Gates afetados:**
- ...

**ADR necessário:** SIM | NÃO  
**Migration necessária:** SIM | NÃO  
**Testes afetados:**
- ...

**Responsável:**
- ...

**Status:** PLANNED | IN_PROGRESS | COMPLETED | BLOCKED
```
