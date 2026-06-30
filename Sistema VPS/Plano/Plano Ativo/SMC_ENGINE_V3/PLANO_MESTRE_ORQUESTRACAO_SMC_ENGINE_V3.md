# PLANO MESTRE DE ORQUESTRAÇÃO — SMC ENGINE V3

**Projeto:** SMC Trader System 7.0
**Diretório-alvo:** `technical_engine/smc_engine_v3/` (cópia de `smc_engine_v2`; a v2 NÃO será alterada)
**Escopo:** orquestrar a aplicação dos 8 planos operacionais de correção (Sessions, Swing, Structure, Previous High/Low, Liquidity, Retracement, Order Block, FVG) como um sistema único, coerente e executável.
**Modo:** incremental, causal, anti-lookahead, idempotente, `shadow_only` até validação completa.
**Natureza deste documento:** este é o **guia de integração**. Ele NÃO substitui os 8 planos individuais — ele fixa (a) a ordem global de implementação, (b) o contrato canônico compartilhado, (c) o mapa de tradução de campos entre engines e (d) a resolução dos 3 conflitos cross-engine que nenhum plano individual cobre.

> **Para a IA de código:** leia este plano-mestre ANTES de qualquer plano individual. Sempre que um plano individual divergir deste documento em nomes de campo temporais, fonte de verdade de EQH/EQL, ou ordem de implementação, **este documento prevalece**. Não implemente nada fora da ordem definida na Seção 2. Não invente campos não listados na Seção 3. Todo trabalho é em `smc_engine_v3/`, nunca em `smc_engine_v2/`.

---

## 1. MAPA DE DEPENDÊNCIAS (validado contra os 8 planos)

```
SESSIONS V3 ──────────────► (raiz; não depende de nenhuma engine SMC)
   │ emite: session instances, trading_date, period instances, evento COMPLETED, membership
   │
   ├──► PREVIOUS HIGH/LOW V3   (depende de Sessions)
   │       emite: PDH/PDL/PWH/PWL/PMH/PML + available_index
   │
SWING V3 ⇄ STRUCTURE V3  (par faseado — circularidade resolvida por camadas, ver Seção 4)
   │  Swing emite (cam.1-3): RawPivotV3, CanonicalSwingV3, EqualLevelClusterV3
   │  Structure emite: StructureEventV3 (BOS/CHOCH), protected/weak levels, trend_state
   │  Swing cam.4 (protected/weak SMC role) é PREENCHIDA pela Structure
   │
   └──► consumidores de Swing+Structure:
          ├── LIQUIDITY V3      (Swing + Structure + PreviousHL)
          ├── RETRACEMENT V3    (Swing + Structure)
          ├── ORDER BLOCK V2    (Swing + Structure + Liquidity)
          └── FVG V2            (Swing + Structure + Liquidity)
```

Dependências declaradas nos headers dos planos, todas confirmadas como coerentes:
- Liquidity V3 → Swing V3 + Structure V3 (+ PreviousHL para session/range liquidity)
- Retracement V3 → Swing V3 + Structure V3
- Previous High/Low V3 → Sessions V3
- Swing V3 → Structure V3 (apenas camada 4; ver Seção 4)
- Structure V3 → Swing V3 (camadas 1-3)
- Order Block V2 / FVG V2 → Structure (structure_event_id, impulse_leg_id, protected/weak), Swing (origin_swing_id), Liquidity (sweep/pool refs)

---

## 2. ORDEM GLOBAL DE IMPLEMENTAÇÃO (OBRIGATÓRIA)

A ordem abaixo é derivada do grafo de dependências por ordenação topológica. **Não pule etapas. Não comece uma engine antes de a anterior ter passado em seus critérios de aceite E exportado seu contrato congelado** (ver Seção 6, "Definition of Done por estágio").

### ESTÁGIO 0 — Fundação compartilhada (PRÉ-REQUISITO DE TUDO)
Antes de QUALQUER engine, crie o módulo de contratos comuns:
- `smc_engine_v3/contracts/temporal.py` — mixin/base com os campos temporais canônicos da Seção 3.1.
- `smc_engine_v3/contracts/guardrails.py` — bloco shadow comum da Seção 3.2.
- `smc_engine_v3/contracts/ids.py` — geração determinística de IDs (SHA-256) da Seção 3.3.
- `smc_engine_v3/contracts/scope.py` — enum `INTERNAL` / `SWING`.
Critério de aceite: importável, testado, congelado. Nenhuma engine redefine esses campos localmente — todas herdam daqui.

### ESTÁGIO 1 — SESSIONS V3
Raiz da cadeia. Não depende de nenhuma engine SMC.
Aplicar: `PLANO_OPERACIONAL_CORRECAO_SESSIONS_ENGINE_V3.md`.
Saída congelada: `SessionInstanceV3`, `PeriodInstanceV3`, `SessionCandleMembershipV3`, evento `COMPLETED`, `session_id`, `trading_date`.

### ESTÁGIO 2 — SWING V3 (camadas 1-3) + STRUCTURE V3 (par faseado)
Implementar juntos, na sub-ordem da Seção 4. Swing produz pivôs/swings canônicos; Structure consome e devolve protected/weak para a camada 4 do Swing.
Aplicar: `PLANO_OPERACIONAL_CORRECAO_SWING_ENGINE_V3.md` + `PLANO_OPERACIONAL_CORRECAO_STRUCTURE_ENGINE_V3.md`.
Saída congelada: `CanonicalSwingV3`, `EqualLevelClusterV3`, `StructureEventV3`, `StructureStateV3`, protected/weak levels.

### ESTÁGIO 3 — PREVIOUS HIGH/LOW V3
Depende só de Sessions (Estágio 1). Pode rodar em paralelo ao Estágio 2 se houver banda — mas só inicia após Sessions congelado.
Aplicar: `PLANO_OPERACIONAL_CORRECAO_PREVIOUS_HIGH_LOW_ENGINE_V3.md`.
Saída congelada: `PreviousPeriodLevelV3` (PDH/PDL/PWH/PWL/PMH/PML) + available_index.

### ESTÁGIO 4 — LIQUIDITY V3
Depende de Swing+Structure (Estágio 2) e PreviousHL (Estágio 3).
Aplicar: `PLANO_OPERACIONAL_CORRECAO_LIQUIDITY_ENGINE_V3.md`.
Atenção ao conflito EQH/EQL (Seção 5.3).
Saída congelada: `LiquidityPoolV3`, `LiquiditySweepV3`, ERL/IRL, inducement.

### ESTÁGIO 5 — RETRACEMENT V3
Depende de Swing+Structure (Estágio 2). Independente de Liquidity; pode rodar em paralelo ao Estágio 4.
Aplicar: `PLANO_OPERACIONAL_CORRECAO_RETRACEMENT_PRICING_ENGINE_V3_1_.md`.
Saída congelada: `DealingRangeV3`, Premium/Equilibrium/Discount, `range_position`.

### ESTÁGIO 6 — ORDER BLOCK V2 e FVG V2 (consumidores finais)
Dependem de Structure, Swing e Liquidity. Implementar por último.
Aplicar: `PLANO_OPERACIONAL_CORRECAO_ORDER_BLOCK_ENGINE_V2.md` + `PLANO_OPERACIONAL_CORRECAO_FVG_ENGINE_V2.md`.
Saída congelada: `OrderBlockV3`, `FairValueGapV3`, IFVG/BPR derivados.

### Resumo da ordem
```
0. Contratos comuns
1. Sessions
2. Swing(1-3) ⇄ Structure        ── núcleo estrutural
3. Previous High/Low             ── (paralelizável com 2, após Sessions)
4. Liquidity                     ┐
5. Retracement                   ┘ (paralelizáveis entre si, após 2/3)
6. Order Block + FVG             ── consumidores finais
```

---

## 3. CONTRATO CANÔNICO COMPARTILHADO (fonte única de verdade)

Todos os planos repetem estes conceitos com nomes ligeiramente diferentes. Esta seção fixa o **vocabulário oficial**. Toda engine v3 herda destes nomes. Onde um plano individual usar nome diferente, aplicar a tradução da Seção 4.

### 3.1. Campos temporais canônicos (TODA entidade temporal os possui)
```text
origin_index           # índice do candle onde a entidade nasce
origin_at              # timestamp correspondente
confirmed_index        # índice onde a entidade é confirmada (fractal/estrutural)
confirmed_at
available_index        # PRIMEIRO índice em que a entidade pode ser consumida SEM lookahead
available_at
earliest_execution_index   # available_index + 1 (primeiro instante operável)
earliest_execution_at
```
**Invariante global (vale para todas as engines):**
`origin_index <= confirmed_index <= available_index < earliest_execution_index`

**Regra anti-lookahead absoluta:** nenhuma engine pode consumir uma entidade de outra engine antes de `available_index`. Nenhum atributo calculado para o instante T pode usar dados de T+1 em diante.

> NOTA DE NOMENCLATURA: o plano do Swing usa `pivot_confirmed_index`. O nome canônico é `confirmed_index`. O plano do Structure já usa `confirmed_index`. Padronizar em `confirmed_index` em toda a v3 (ver Seção 4.1).

### 3.2. Bloco de guardrails (TODA engine carrega, herdado de contracts/guardrails.py)
```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
production_truth_replaced = false
llm_decision_used = false
```
Nenhuma engine v3 escreve em banco de produção. Nenhuma promove trade. Nenhuma decisão nasce só de score estatístico.

### 3.3. Identidade e versionamento (TODA entidade)
```text
<entity>_id        # SHA-256 determinístico dos campos lógicos (NÃO inclui payload mutável)
engine_version     # versão da engine que produziu
config_hash        # hash da configuração ativa (parâmetros que afetam detecção)
scope              # INTERNAL | SWING
asset
timeframe
```
**Regra de idempotência:** mesmo input + mesmo `config_hash` → mesmo `<entity>_id` e mesmo conteúdo. Reprocessar com candles futuros NÃO pode alterar campos históricos já emitidos (origem, confirmação, limites, score inicial).

### 3.4. Equivalência batch = replay = streaming
Toda engine deve produzir resultado idêntico nos três modos. Critério de aceite por engine: hash de estado determinístico, comparado run-vs-run.

---

## 4. RESOLUÇÃO DO CONFLITO #1 — CIRCULARIDADE SWING ⇄ STRUCTURE

Os dois planos se declaram mutuamente dependentes. **Não é um ciclo real — é um pipeline faseado por camadas.** Resolução oficial:

### 4.1. Camadas do Swing e quem preenche cada uma
```text
Camada 1 — Raw Pivot         → Swing (sozinho)
Camada 2 — Canonical Swing   → Swing (sozinho)
Camada 3 — Structural class. → Swing (sozinho): HH/HL/LH/LL/EQH/EQL
Camada 4 — Papel SMC         → STRUCTURE preenche: PROTECTED/WEAK high/low
```
O Swing produz e congela as camadas 1-3 **sem nenhuma dependência de Structure**. A camada 4 (`is_protected`, role protected/weak) nasce VAZIA no Swing e é preenchida pela Structure, que devolve a classificação.

### 4.2. Sub-ordem de implementação do par (dentro do Estágio 2)
```
2a. Swing camadas 1-3 (RawPivotV3, CanonicalSwingV3, EqualLevelClusterV3) — congelar
2b. Structure consome CanonicalSwingV3 via SwingAvailabilityAdapter → produz StructureEventV3 + protected/weak
2c. Swing camada 4 recebe protected/weak da Structure (campo is_protected / role)
2d. Teste de integração do par: nenhum BOS usa pivô futuro; nenhum protected/weak antes de available_index
```

### 4.3. Mapa de tradução de campos Swing → Structure (CRÍTICO)
O `SwingAvailabilityAdapter` (Estágio 2b) DEVE fazer este mapeamento explícito. Os planos usam nomes divergentes para os mesmos conceitos:

| Conceito              | Swing produz (`CanonicalSwingV3`) | Structure espera (`SwingPointV3`) | Campo canônico oficial |
|-----------------------|-----------------------------------|-----------------------------------|------------------------|
| ID do swing           | `swing_id`                        | `swing_id`                        | `swing_id` ✓ (igual)   |
| índice de origem      | `origin_index`                    | `origin_index`                    | `origin_index` ✓       |
| índice de confirmação | `pivot_confirmed_index`           | `confirmed_index`                 | **`confirmed_index`**  |
| disponibilidade       | `available_index`                 | `available_index`                 | `available_index` ✓    |
| tipo high/low         | `high_low`                        | `high_low`                        | `high_low` ✓ (1/-1)    |
| natureza do swing     | `swing_type`                      | `method`                          | manter ambos; adapter mapeia `swing_type`→`method` |
| classificação estrut. | `structural_classification`       | (não recebe; deriva)              | `structural_classification` (Swing é fonte) |
| força                 | `prominence_atr`                  | `strength`                        | adapter mapeia `prominence_atr`→`strength` |
| escopo                | `scope`                           | `scope`                           | `scope` ✓              |
| cluster de iguais     | `equal_cluster_id`                | (via EQH/EQL)                     | `equal_cluster_id` (ver Seção 5.3) |

**Ação para a IA:** implementar `SwingAvailabilityAdapter` com este mapa exato. Não assumir que os nomes coincidem. Validar que toda chave esperada pela Structure existe após o adapter.

---

## 5. RESOLUÇÃO DOS DEMAIS CONFLITOS CROSS-ENGINE

### 5.1. Conflito #2 — Ausência de schema temporal compartilhado
**Problema:** cada plano redefine `available_index`, `available_at`, `engine_version`, `config_hash`, `scope` localmente.
**Resolução:** o ESTÁGIO 0 cria `contracts/temporal.py`, `contracts/ids.py`, `contracts/scope.py`, `contracts/guardrails.py`. Toda dataclass de entidade v3 herda/compõe a partir desses módulos. Proibido redeclarar esses campos com semântica divergente. Os nomes da Seção 3 são os únicos válidos.

### 5.2. Conflito #3 — EQH/EQL com dupla origem (Swing vs Liquidity)
**Problema:** o Swing produz `EqualLevelClusterV3` (detecção geométrica de equal highs/lows). A Liquidity também lista EQH/EQL como tipo de pool. Risco: duas detecções concorrentes.
**Resolução oficial — separação de responsabilidade:**
- **SWING é a fonte de verdade da DETECÇÃO geométrica.** Produz `EqualLevelClusterV3` (cluster de pivôs com preços equivalentes dentro da tolerância por tick). Esse cluster tem `cluster_id` estável.
- **LIQUIDITY NÃO redetecta.** Consome `EqualLevelClusterV3` por `cluster_id` e o **promove** a `LiquidityPoolV3` do tipo EQH/EQL, anexando semântica de liquidez (BSL/SSL, estado do pool, sweep, reclaim).
- Regra: `LiquidityPoolV3(type=EQH).source_cluster_id` aponta para o `EqualLevelClusterV3.cluster_id` do Swing. Liquidity nunca cria EQH/EQL sem um cluster de origem do Swing.

Mesma lógica para protected/weak: **Structure detecta/classifica** protected/weak levels; **Liquidity promove** a "protected/weak liquidity" referenciando o `level_id` da Structure. Liquidity não reclassifica protected/weak por conta própria.

### 5.3. Regra geral de "fonte única de verdade"
Para qualquer entidade que apareça em mais de um plano:
```
DETECÇÃO/CLASSIFICAÇÃO   →  pertence à engine mais UPSTREAM (mais perto da raiz)
PROMOÇÃO/ENRIQUECIMENTO  →  engine downstream referencia por ID, nunca redetecta
```
Tabela de fontes únicas:

| Entidade                  | Fonte (detecta)        | Consumidores (referenciam por ID)              |
|---------------------------|------------------------|------------------------------------------------|
| Raw pivot / Canonical swing | Swing                | Structure, Liquidity, Retracement, OB, FVG     |
| EQH/EQL cluster           | Swing                  | Liquidity (promove a pool), Structure          |
| BOS/CHOCH (structure event) | Structure            | OB, FVG, Liquidity, Retracement                |
| protected/weak levels     | Structure              | Swing(cam.4), Liquidity, OB                     |
| trend_state               | Structure              | todos (contexto)                               |
| session/period instances  | Sessions               | PreviousHL, Liquidity, contextual              |
| PDH/PDL/PWH/PWL            | Previous High/Low      | Liquidity (promove a pool), contextual         |
| liquidity pool/sweep      | Liquidity              | OB, FVG, Retracement, Opportunity Scanner      |
| dealing range / PED       | Retracement            | OB, FVG, contextual                            |
| order block               | Order Block            | Opportunity Scanner                            |
| FVG / IFVG / BPR          | FVG                    | Opportunity Scanner                            |

---

## 6. DEFINITION OF DONE POR ESTÁGIO (gate de avanço)

Uma engine só libera o próximo estágio quando TODOS abaixo passam:

```text
[ ] Critérios de aceite do plano individual: PASS
[ ] Contrato de saída CONGELADO e documentado (nomes de campo finais)
[ ] Herda contracts/ comuns (temporal, ids, scope, guardrails) — sem redefinição local
[ ] Invariante temporal validado: origin <= confirmed <= available < earliest_execution
[ ] Anti-lookahead: teste que prova que nenhum consumidor lê antes de available_index
[ ] Idempotência: mesmo input + config_hash → mesmo id e mesmo conteúdo
[ ] Equivalência batch = replay = streaming (hash de estado run-vs-run)
[ ] shadow_only = true; zero escrita em produção; zero promoção de trade
[ ] Mapa de tradução de campos para consumidores diretos documentado
[ ] Zero regressão na suíte v2 (a v3 é cópia isolada; v2 intocada)
```

Gate de integração entre estágios (não só por engine):
```text
[ ] Consumidor downstream recebe exatamente os campos que o upstream congelou
    (validar contra a tabela de tradução; falha = adapter ausente)
[ ] Nenhuma entidade é redetectada por uma engine downstream (Seção 5.3)
```

---

## 7. RISCOS E COMO MITIGAR

1. **Implementar fora de ordem.** Mitigação: gate de "contrato congelado" no fim de cada estágio. FVG/OB (Estágio 6) só começam quem Structure/Swing/Liquidity estão congelados.
2. **Divergência de nomes silenciosa.** Mitigação: adapters explícitos (Seção 4.3) + teste que valida presença de toda chave esperada após o adapter.
3. **Dupla detecção (EQH/EQL, protected/weak).** Mitigação: regra de fonte única (Seção 5.3); downstream referencia por ID.
4. **Lookahead reintroduzido na integração.** Mitigação: o invariante temporal é validado tanto DENTRO de cada engine quanto NO PONTO DE CONSUMO entre engines.
5. **v2 alterada por engano.** Mitigação: todo trabalho em `smc_engine_v3/`. Teste de regressão da v2 deve continuar verde e idêntico; se mudar, algo vazou para a v2.

---

## 8. CHECKLIST DE ARRANQUE (para a IA de código, antes de tocar em qualquer plano)

```text
[ ] Confirmar que smc_engine_v3/ é cópia de smc_engine_v2/ e que v2 não será tocada
[ ] Criar contracts/ (Estágio 0) e congelar
[ ] Só então iniciar Sessions (Estágio 1)
[ ] Seguir a ordem da Seção 2 sem pular
[ ] A cada fim de estágio, rodar o Definition of Done (Seção 6)
[ ] Manter shadow_only em todas as engines até validação final do sistema completo
[ ] NÃO promover, NÃO escrever em produção, NÃO fazer cutover sem autorização explícita
```

---

## 9. O QUE ESTE PLANO NÃO FAZ

- Não reescreve os 8 planos individuais; eles continuam sendo a especificação detalhada de cada engine.
- Não autoriza cutover nem substituição da v2 em produção.
- Não define a integração com o Opportunity Scanner / Elliott / Wyckoff (essas engines consomem a saída da v3, mas sua adaptação é trabalho posterior, após a v3 estar congelada e validada em shadow).
- Não cobre a proposta de Volume Profile (POC/VA) — aquilo é uma camada de confluência futura e opcional, fora do escopo desta correção estrutural.

================================================================================
FIM DO PLANO MESTRE DE ORQUESTRAÇÃO — SMC ENGINE V3
================================================================================
