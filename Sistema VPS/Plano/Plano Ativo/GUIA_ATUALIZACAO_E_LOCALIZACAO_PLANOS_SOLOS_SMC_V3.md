# GUIA DE ATUALIZAÇÃO E ORGANIZAÇÃO DOS 8 PLANOS INDIVIDUAIS — SMC ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Documento superior:** `PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt`  
**Autoridades de estado atual:** `ARQUITETURA_OFICIAL.md` e `RELATORIO_ENGINES_INDICADORES_ZONAS.md`  
**Objetivo:** definir quais planos individuais precisam ser atualizados, onde armazená-los e onde cada implementação deve ser realizada.

---

# 1. DECISÃO

Os oito planos individuais devem ser atualizados antes da execução.

Eles não devem ser reescritos do zero. Cada um deve receber uma revisão de integração para:

- apontar exclusivamente para `smc_engine_v3`;
- reconhecer a infraestrutura incremental já existente;
- consumir os contratos definidos pelo plano-mestre;
- remover ownerships duplicados;
- usar os gates globais;
- separar cutover de pacote e cutover semântico;
- adotar persistência V3 separada;
- respeitar os documentos oficiais como autoridade de nível zero.

---

# 2. ORGANIZAÇÃO DOS DOCUMENTOS

## 2.1. Pasta ativa recomendada

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/
└── docs_geral/
    └── Sistema VPS/
        └── Plano/
            └── Plano Ativo/
                └── SMC_ENGINE_V3/
                    ├── 00_PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt
                    ├── 01_PLANO_OPERACIONAL_CORRECAO_SESSIONS_ENGINE_V3.md
                    ├── 02_PLANO_OPERACIONAL_CORRECAO_SWING_ENGINE_V3.md
                    ├── 03_PLANO_OPERACIONAL_CORRECAO_STRUCTURE_ENGINE_V3.md
                    ├── 04_PLANO_OPERACIONAL_CORRECAO_PREVIOUS_HIGH_LOW_ENGINE_V3.md
                    ├── 05_PLANO_OPERACIONAL_CORRECAO_RETRACEMENT_PRICING_ENGINE_V3.md
                    ├── 06_PLANO_OPERACIONAL_CORRECAO_LIQUIDITY_ENGINE_V3.md
                    ├── 07_PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V3.md
                    ├── 08_PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V3.md
                    ├── INDEX.md
                    ├── CONTRACT_TRACEABILITY_MATRIX.md
                    └── CHANGELOG_PLANOS_SMC_V3.md
```

## 2.2. Pasta de versões antigas

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/
└── docs_geral/
    └── Sistema VPS/
        └── Plano/
            └── Arquivados/
                └── SMC_ENGINE_V3_PRE_MASTER_V2_2026-06-30/
```

Mover para o arquivo:

- versões anteriores dos oito planos;
- planos com nomes contendo `(1)` ou `(2)`;
- versões anteriores do master;
- rascunhos;
- relatórios substituídos.

Na pasta ativa deve existir apenas uma versão canônica de cada documento.

---

# 3. CABEÇALHO PADRÃO PARA TODOS OS PLANOS INDIVIDUAIS

Adicionar ao início de cada plano:

```text
Versão do plano: 2.0 — alinhado ao Plano-Mestre V3
Autoridade de estado atual:
- ARQUITETURA_OFICIAL.md
- RELATORIO_ENGINES_INDICADORES_ZONAS.md

Documento de orquestração obrigatório:
- 00_PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt

Diretório ativo:
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3

Diretório legado:
- /home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2
- backup congelado, fora do runtime

Modo inicial:
- BASELINE_COMPAT
- shadow_only=True
- can_promote_trade=False
- apply_automatically=False
- llm_decision_used=False
```

---

# 4. SEÇÕES OBRIGATÓRIAS A ADICIONAR EM CADA PLANO

Cada plano deve conter:

1. **Autoridades documentais e precedência**
2. **Caminhos reais dos arquivos**
3. **Estado atual herdado da cópia V3**
4. **Componentes incrementais existentes que devem ser reutilizados**
5. **Contratos consumidos**
6. **Contratos produzidos**
7. **Ownership do domínio**
8. **Gate global de entrada**
9. **Gate global de saída**
10. **Persistência V3 e read models**
11. **Integração live/replay/backtest**
12. **Impacto em Study Gateway, Scanner, Evidence Bundle, API e dashboard**
13. **Compatibilidade temporária BASELINE_COMPAT**
14. **Proibição de importar o backup V2**
15. **Relatório final local e atualização do relatório global**

---

# 5. LOCALIZAÇÃO DO CÓDIGO

Não criar uma segunda arquitetura paralela sem ADR.

A cópia V3 já contém a estrutura batch e incremental. As correções devem evoluir os arquivos existentes.

## 5.1. Arquivos batch/compatibilidade

```text
technical_engine/smc_engine_v3/
├── pipeline.py
├── config.py
├── models.py
├── persistence.py
├── fvg.py
├── order_blocks.py
├── structure.py
├── liquidity.py
├── bpr.py
├── swings.py
├── sessions.py
├── retracements.py
└── previous_high_low.py
```

Esses arquivos podem permanecer como:

- fachada batch;
- compatibilidade temporária;
- comparação de baseline;
- serialização para consumidores antigos.

Não devem ser a única implementação causal final.

## 5.2. Implementação incremental canônica

```text
technical_engine/smc_engine_v3/
└── incremental/
    ├── engine.py
    ├── components/
    │   ├── swing.py
    │   ├── bos_choch.py
    │   ├── sessions.py
    │   ├── previous_high_low.py
    │   ├── retracements.py
    │   ├── liquidity.py
    │   ├── fvg.py
    │   ├── bpr.py
    │   └── ob.py
    ├── adapters/
    ├── persistence/
    ├── shadow_runtime.py
    └── opportunity/
```

Regra:

- lógica causal principal em `incremental/components/`;
- orquestração em `incremental/engine.py`;
- Live/Replay/Batch/PersistedReplay em `incremental/adapters/`;
- repositories, checkpoint, schema e escrita atômica em `incremental/persistence/`;
- integração com Opportunity Scanner em `incremental/opportunity/`.

## 5.3. Contratos compartilhados

Criar ou reconciliar em:

```text
technical_engine/smc_engine_v3/
└── contracts/
    ├── temporal.py
    ├── identity.py
    ├── enums.py
    ├── guardrails.py
    ├── session_contracts.py
    ├── period_contracts.py
    ├── swing_contracts.py
    ├── structure_contracts.py
    ├── retracement_contracts.py
    ├── liquidity_contracts.py
    ├── fvg_contracts.py
    ├── order_block_contracts.py
    ├── association_contracts.py
    └── schema_registry.py
```

Antes de criar novos contratos, auditar:

- `StructureEmission`;
- `StructureEventEmission`;
- `CandleEnvelope`;
- contratos e modelos já existentes no incremental;
- contratos do `live_replay_v4`.

Não duplicar tipos existentes. Estender, adaptar ou versionar.

## 5.4. Persistência

Código:

```text
technical_engine/smc_engine_v3/incremental/persistence/
```

Migrations oficiais do banco:

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations/
```

Nomes sugeridos:

```text
202607xx_create_smc_v3_incremental_schema.sql
202607xx_create_technical_engine_smc_v3_shadow_tables.sql
```

Não reutilizar tabelas V2 para semântica V3.

## 5.5. Testes

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/
└── tests/
    └── smc_engine_v3/
        ├── contracts/
        ├── components/
        ├── persistence/
        ├── adapters/
        ├── cross_engine/
        ├── replay/
        └── cutover/
```

Manter os testes antigos copiados quando úteis, mas renomear imports para V3.

## 5.6. Ferramentas de execução

```text
tools/
├── r5a_mtf_replay_v3.py
├── full_backfill_v3.py
├── recalculate_smc_v3_winfut.py
├── compare_smc_v2_baseline_vs_v3.py
├── validate_smc_v3_contracts.py
├── validate_smc_v3_mysql_staging.py
└── cutover_smc_v3.py
```

Não alterar os scripts V2 existentes antes de possuir equivalentes V3 validados.

## 5.7. Integração e sync

```text
infra/
├── sync_v3.py
└── smc_v3_runtime.py
```

A atualização de `run_b3.py`, `run_forex.py`, FastAPI, dashboard e Hostinger deve ocorrer apenas nas fases de integração/cutover.

---

# 6. ALTERAÇÕES ESPECÍFICAS POR PLANO

## 6.1. Sessions

Atualizar para:

- produzir `SessionInstanceV3`, `PeriodInstanceV3` e `TradingPeriodSummaryV3`;
- usar `incremental/components/sessions.py`;
- ser fonte única de trading date, período concluído, DST e calendário;
- Gate de saída G1.

Remover:

- qualquer ownership de Previous Period sobre períodos concluídos;
- arquitetura paralela fora da engine incremental sem ADR.

## 6.2. Swing

Atualizar dependência:

- camadas 1–3 são autônomas;
- Structure não é dependência de detecção geométrica;
- Structure produz apenas a projeção protected/weak.

Usar:

- `incremental/components/swing.py`;
- `CanonicalSwingContractV1`;
- `EqualLevelClusterV3`;
- Gate G2.

Substituir tabelas locais isoladas por persistência unificada V3 ou read models aprovados.

## 6.3. Structure

Atualizar para:

- consumir `CanonicalSwingContractV1`;
- produzir `StructureEventV3`, `StructureLevelV3`, `StructureLegV3` e `SwingSmcRoleProjectionV3`;
- usar `incremental/components/bos_choch.py`;
- Gate G3.

Não modificar diretamente o registro canônico do swing para gravar protected/weak.

## 6.4. Previous High/Low

Atualizar para:

- consumir `TradingPeriodSummaryV3` da Sessions;
- não criar seu próprio `CompletedPeriodV3` autoritativo;
- usar `incremental/components/previous_high_low.py`;
- produzir PreviousPeriodLevel/Event;
- Gate G4.

## 6.5. Retracement/Pricing

Atualizar para:

- ownership exclusivo de `DealingRangeV3`;
- consumir `StructureLegV3`;
- usar `incremental/components/retracements.py`;
- preservar a correção `FIBONACCI_ANCHOR`;
- não criar segundo range dentro de Liquidity;
- Gate G5.

## 6.6. Liquidity

Atualizar para:

- consumir EqualLevelCluster do Swing;
- renomear `EqualLevelLiquidityDetector` para promoter/adapter;
- consumir protected/weak da Structure;
- consumir PreviousPeriodLevel;
- consumir DealingRange para ERL/IRL;
- usar `incremental/components/liquidity.py`;
- Gate G6.

## 6.7. FVG

Renomear o plano ativo para:

```text
PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V3.md
```

Atualizar para:

- usar `fvg.py` e `incremental/components/fvg.py`;
- preservar detector geométrico de três candles;
- incorporar ciclo causal;
- incorporar IFVG e BPR;
- reconhecer `bpr.py` e `incremental/components/bpr.py`;
- consumir StructureLeg/Liquidity/DealingRange apenas na classificação contextual;
- Gates G7 e G7B.

## 6.8. Order Block

Renomear o plano ativo para:

```text
PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V3.md
```

Atualizar para:

- usar `order_blocks.py` e `incremental/components/ob.py`;
- preservar `ob_subtype`:
  - NORMAL;
  - REJECTION;
  - STACKED;
- não confundir STACKED com breaker block clássico;
- consumir StructureEvent, StructureLeg, Liquidity e FVG como evidência;
- Gate G8.

---

# 7. MODELO DE EXECUÇÃO DE CADA PLANO

Para cada engine:

1. abrir a Arquitetura Oficial;
2. abrir o Relatório de Engines/Tabelas;
3. abrir o plano-mestre;
4. abrir o plano individual;
5. confirmar gate de entrada;
6. localizar os arquivos reais na cópia V3;
7. rodar baseline;
8. criar fixtures;
9. corrigir primeiro o componente incremental;
10. manter ou adaptar a fachada batch;
11. criar/alterar contratos somente após auditoria;
12. adicionar persistência V3;
13. executar testes unitários;
14. executar contract tests;
15. executar batch/replay/live parity;
16. gerar relatório local;
17. atualizar matriz de rastreabilidade;
18. aprovar gate de saída.

---

# 8. CONTROLE DE VERSÕES DOS PLANOS

Cada plano ativo deve conter:

```text
document_version: 2.0
master_version: 2.0
architecture_snapshot_date: 2026-06-30
status: ACTIVE
supersedes: versão 1.0
```

Adicionar changelog no final:

```text
## Changelog 2.0
- caminho alterado para smc_engine_v3;
- alinhamento ao incremental unified;
- contratos reconciliados;
- persistência V3 separada;
- gates globais adicionados;
- integração oficial adicionada;
- V2 movida para backup e removida do runtime;
- referências aos documentos oficiais adicionadas.
```

---

# 9. NÃO FAZER

- não deixar duas versões ativas do mesmo plano;
- não manter arquivos com `(1)` ou `(2)` na pasta ativa;
- não colocar planos dentro de `technical_engine/smc_engine_v3`;
- não criar nova árvore de código paralela sem ADR;
- não criar contratos duplicados;
- não gravar semântica V3 em tabelas V2;
- não mover V2 antes do baseline e auditoria de imports;
- não ativar V3 canônica apenas porque o import foi trocado;
- não atualizar a Arquitetura Oficial como “cutover concluído” antes de o Gate G10 passar.

---

# 10. RESULTADO ESPERADO

Ao final:

- existirão 9 documentos ativos e coerentes;
- os planos individuais estarão subordinados ao master;
- os documentos oficiais continuarão como autoridade de estado atual;
- o código será corrigido somente em `smc_engine_v3`;
- a infraestrutura incremental existente será reutilizada;
- cada engine terá ownership e contratos claros;
- não haverá duplicidade de períodos, dealing ranges ou EQH/EQL;
- o runtime V3 poderá evoluir em shadow sem quebrar Study Gateway, Scanner, Evidence Bundle, API ou dashboard.