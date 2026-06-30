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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

**Status:** `PENDING_UPDATE`

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

# Próxima versão planejada — 2.1

A versão 2.1 deverá ser registrada depois que:

- os oito planos individuais forem atualizados;
- todos os nomes ativos estiverem normalizados;
- `INDEX.md` refletir os caminhos reais;
- a matriz de contratos tiver schemas implementados;
- os statuses `PENDING_UPDATE` forem substituídos;
- a Fase M-1A validar a cópia V3;
- os relatórios de baseline estiverem disponíveis.

Entrada esperada:

```text
## 2026-XX-XX — Versão documental 2.1
- oito planos revisados;
- schemas reconciliados;
- contract tests implementados;
- Gate G0 aprovado ou bloqueios documentados.
```

---

# Template para novas entradas

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
