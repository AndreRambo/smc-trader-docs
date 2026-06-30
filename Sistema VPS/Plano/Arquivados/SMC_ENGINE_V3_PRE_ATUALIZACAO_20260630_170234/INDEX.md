# ÍNDICE EXECUTIVO — PLANOS SMC ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Pacote documental:** Orquestração das 8 Engines SMC V3  
**Versão:** 2.0  
**Estado:** `ACTIVE`  
**Autoridades de estado atual:**

- `ARQUITETURA_OFICIAL.md`
- `RELATORIO_ENGINES_INDICADORES_ZONAS.md`

**Documento superior de orquestração:**

- `00_PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt`

---

## 1. Objetivo deste índice

Este arquivo informa:

- qual documento deve ser aberto em cada fase;
- qual engine produz e consome cada contrato;
- qual gate global deve estar aprovado;
- qual relatório deve ser entregue;
- qual diretório de código pode ser alterado;
- qual diretório legado não participa do runtime.

Este índice não substitui o plano-mestre nem os oito planos individuais.

---

## 2. Diretórios oficiais

### Módulo ativo

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3
```

Este é o único módulo autorizado para:

- desenvolvimento;
- correções;
- testes;
- replay;
- persistência V3;
- runtime V3;
- cutover futuro.

### Backup legado

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2
```

Regras:

- backup congelado;
- fora do runtime;
- fora do `PYTHONPATH`;
- não importar código diretamente;
- não criar dependências operacionais;
- usar apenas como referência manual, baseline e auditoria.

---

## 3. Documentos ativos

| Ordem | Documento | Autoridade principal |
|---:|---|---|
| 00 | `PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt` | Ordem global, contratos, ownership, gates, integração e cutover |
| 01 | `PLANO_OPERACIONAL_CORRECAO_SESSIONS_ENGINE_V3.md` | Calendário, sessões, DST, trading date e períodos concluídos |
| 02 | `PLANO_OPERACIONAL_CORRECAO_SWING_ENGINE_V3.md` | Raw pivots, canonical swings, supersession e EQH/EQL geométrico |
| 03 | `PLANO_OPERACIONAL_CORRECAO_STRUCTURE_ENGINE_V3.md` | BOS, CHOCH, trend state, StructureLeg e protected/weak |
| 04 | `PLANO_OPERACIONAL_CORRECAO_PREVIOUS_HIGH_LOW_ENGINE_V3.md` | PDH/PDL, PWH/PWL, PMH/PML e eventos dos níveis anteriores |
| 05 | `PLANO_OPERACIONAL_CORRECAO_RETRACEMENT_PRICING_ENGINE_V3.md` | DealingRange, retracement e Premium/Equilibrium/Discount |
| 06 | `PLANO_OPERACIONAL_CORRECAO_LIQUIDITY_ENGINE_V3.md` | BSL/SSL, pools, sweeps, ERL/IRL, reclaim e lifecycle |
| 07 | `PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V3.md` | FVG, IFVG, BPR, displacement e lifecycle |
| 08 | `PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V3.md` | Order Blocks, origem do impulso, refinement, freshness e lifecycle |

Documentos auxiliares:

| Documento | Finalidade |
|---|---|
| `INDEX.md` | Navegação e ordem de leitura |
| `CONTRACT_TRACEABILITY_MATRIX.md` | Rastreabilidade produtor → contrato → consumidor |
| `CHANGELOG_PLANOS_SMC_V3.md` | Histórico de versões e mudanças dos planos |

---

## 4. Ordem global de execução

```text
M-1A  Sincronizar e validar a cópia smc_engine_v3
M-1B  Migrar imports e runtime para o pacote V3 em BASELINE_COMPAT
M-1C  Mover V2 para backups e eliminar dependência de runtime

M0    Reconciliar contratos existentes e congelar schemas
M1    Sessions & Market Calendar V3
M2    Swing Core V3 — camadas 1 a 3
M3    Structure V3 + projeção protected/weak
M4    Previous Period Levels V3
M5    Retracement & Pricing V3
M6    Liquidity V3
M7    FVG V3
M7B   IFVG/BPR
M8    Order Block V3
M9    Context Association e read models
M10   Replay integrado, soak, dashboard, sync e cutover semântico
```

---

## 5. Gates globais

| Gate | Nome | Documento principal | Condição resumida |
|---|---|---|---|
| G-1 | V3 Package Ready | Plano-mestre | V3 importável, baseline preservado e V2 fora do runtime |
| G0 | Contract Freeze | Plano-mestre + matriz de contratos | Ownership, schemas, IDs e adapters reconciliados |
| G1 | Sessions Ready | Plano Sessions | Calendário, DST, períodos e batch/stream parity |
| G2 | Swing Core Ready | Plano Swing | Raw/canonical, availability, supersession e equal clusters |
| G3 | Structure Ready | Plano Structure | BOS/CHOCH, StructureLeg, levels, state e role projection |
| G4 | Previous Period Ready | Plano Previous High/Low | Níveis anteriores causais e eventos separados |
| G5 | Dealing Range Ready | Plano Retracement | Range estrutural, pricing model e revisions |
| G6 | Liquidity Ready | Plano Liquidity | Pools, promoter EQH/EQL, ERL/IRL e lifecycle |
| G7 | FVG Ready | Plano FVG | Geometria, displacement, lifecycle e contexto causal |
| G7B | IFVG/BPR Ready | Plano FVG | IFVG/BPR derivados e sem dependência circular |
| G8 | Order Block Ready | Plano Order Block | Origem, StructureLeg, liquidity evidence e lifecycle |
| G9 | Integration Ready | Plano-mestre | Contratos cross-engine, APIs, dashboard e persistência |
| G10 | Semantic Cutover Ready | Plano-mestre | Replay MTF, soak, rollback e aprovação humana |

---

## 6. Documento a abrir por wave

### Wave -1 — Migração de pacote

Abrir:

1. `ARQUITETURA_OFICIAL.md`
2. `RELATORIO_ENGINES_INDICADORES_ZONAS.md`
3. plano-mestre
4. este índice

Entregável:

```text
RELATORIO_MIGRACAO_SMC_ENGINE_V2_PARA_V3.txt
```

### Wave 0 — Contratos

Abrir:

1. plano-mestre;
2. `CONTRACT_TRACEABILITY_MATRIX.md`;
3. planos Swing, Structure, Sessions, Previous Period, Retracement e Liquidity.

Entregável:

```text
RELATORIO_FASE_M0_CONTRACT_FREEZE.txt
```

### Wave 1 — Sessions

Abrir:

- plano-mestre;
- plano Sessions;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_SESSIONS_ENGINE_V3.md
```

### Wave 2 — Swing

Abrir:

- plano-mestre;
- plano Swing;
- plano Structure somente para o contrato de integração;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_SWING_ENGINE_V3.md
```

### Wave 3 — Structure

Abrir:

- plano-mestre;
- plano Structure;
- plano Swing;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_STRUCTURE_ENGINE_V3.md
```

### Wave 4 — Previous Period e Retracement

Abrir:

- plano Sessions;
- plano Previous High/Low;
- plano Swing;
- plano Structure;
- plano Retracement;
- matriz de contratos.

Entregáveis:

```text
RELATORIO_FINAL_PREVIOUS_PERIOD_LEVELS_V3.md
RELATORIO_FINAL_RETRACEMENT_ENGINE_V3.md
```

### Wave 5 — Liquidity

Abrir:

- planos Swing, Structure, Previous Period e Retracement;
- plano Liquidity;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_LIQUIDITY_ENGINE_V3.md
```

### Wave 6 — FVG, IFVG e BPR

Abrir:

- planos Structure, Retracement e Liquidity;
- plano FVG;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_FVG_ENGINE_V3.md
```

### Wave 7 — Order Block

Abrir:

- planos Swing, Structure, Liquidity e FVG;
- plano Order Block;
- matriz de contratos.

Entregável:

```text
RELATORIO_FINAL_ORDER_BLOCK_ENGINE_V3.md
```

### Wave 8 — Integração e cutover

Abrir:

- plano-mestre;
- oito relatórios finais;
- matriz de contratos;
- changelog;
- documentos oficiais.

Entregável:

```text
RELATORIO_FINAL_ORQUESTRACAO_8_ENGINES_SMC_V3.txt
```

---

## 7. Modos de execução

### `BASELINE_COMPAT`

- pacote ativo já é `smc_engine_v3`;
- comportamento ainda reproduz o baseline V2;
- consumidores atuais continuam funcionando;
- não significa cutover semântico.

### `V3_INCREMENTAL_SHADOW`

- contratos e semântica V3;
- storage V3 separado;
- comparativo com baseline;
- sem influência em sinais oficiais.

### `V3_CANONICAL`

- somente após G10;
- requer replay, soak, rollback e aprovação humana;
- fora do escopo de ativação automática dos planos.

---

## 8. Guardrails globais

```text
shadow_only=True
can_promote_trade=False
apply_automatically=False
llm_decision_used=False
production_truth_replaced=False
smc_recomputed_in_frontend=False
anti_lookahead=True
deterministico=True
ZERO_SHADOW_ORDERS=True
```

---

## 9. Regra de encerramento de cada fase

Nenhuma fase será aceita se o relatório não informar:

- versão do plano-mestre;
- versão do plano individual;
- arquivos alterados;
- contratos consumidos;
- contratos produzidos;
- gate de entrada;
- gate de saída;
- testes executados;
- prova anti-lookahead;
- batch/replay/live parity;
- impacto downstream;
- resultado dos guardrails;
- rollback aplicável.
