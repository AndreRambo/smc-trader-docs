---
document_version: 2.0
master_version: 2.0
architecture_snapshot_date: 2026-06-30
status: ACTIVE
supersedes: 1.0
projeto: SMC Trader System 7.0
---

# AUTORIDADES DOCUMENTAIS E PRECEDÊNCIA

| Autoridade | Nível | Uso |
|---|---|---|
| `ARQUITETURA_OFICIAL.md` | NÍVEL 0 | Estado atual do sistema |
| `RELATORIO_ENGINES_INDICADORES_ZONAS.md` | NÍVEL 0 | Engines, tabelas e zonas |
| `00_PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt` | NÍVEL 1 | Ordem global, contratos, ownership, gates |
| `CONTRACT_TRACEABILITY_MATRIX.md` | NÍVEL 2 | Rastreabilidade de contratos |
| Este plano individual | NÍVEL 3 | Algoritmo e implementação específica |

---

# CAMINHOS OFICIAIS

| Recurso | Caminho |
|---|---|
| Diretório ativo | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3` |
| Diretório incremental | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3/incremental` |
| Diretório legado (backup) | `/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2` |
| Persistência V3 | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3/incremental/persistence` |
| Migrations oficiais | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations` |
| Testes | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tests/smc_engine_v3/` |

---

# REGRA DE BACKUP E RUNTIME

- **backup congelado:** `/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2` — fora do runtime
- **proibido importar código do backup** — toda implementação ocorre na cópia V3
- **modo inicial:** `BASELINE_COMPAT`
- **guardrails:**
  - `shadow_only=True`
  - `can_promote_trade=False`
  - `apply_automatically=False`
  - `llm_decision_used=False`
  - `production_truth_replaced=False`

---


# PLANO OPERACIONAL — CORREÇÃO DA SWING ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Módulo:** Swing Detection / Pivot Detection  
**Escopo:** correção arquitetural, temporal, estrutural e operacional da engine de swings  
**Modo de execução:** incremental, anti-lookahead, auditável e shadow-only até aprovação  
**Versão-alvo:** Swing Engine V3  
**Dependência principal:** Structure Engine V3  
**Objetivo:** substituir o detector retrospectivo simplificado por uma engine canônica de pivôs e swings, com disponibilidade temporal explícita, histórico imutável, suporte a estrutura interna e swing, preservação de EQH/EQL e integração causal com BOS/CHOCH, OB, FVG e liquidez.

---

# 1. OBJETIVO GERAL

Construir uma Swing Engine V3 que:

- detecte pivôs locais de forma determinística;
- separe pivô fractal de swing estrutural;
- elimine lookahead downstream;
- preserve todos os pivôs confirmados, inclusive os posteriormente substituídos;
- mantenha histórico de revisões;
- diferencie estrutura `INTERNAL` e `SWING`;
- preserve equal highs e equal lows;
- tenha semântica temporal explícita;
- funcione em batch e streaming com o mesmo resultado;
- possa alimentar a Structure Engine V3 sem ambiguidade;
- permaneça em modo shadow até validação completa.

---

# 2. PROBLEMAS DO ESTADO ATUAL

A implementação atual possui os seguintes problemas:

1. usa janela retrospectiva com candles futuros;
2. retorna apenas `HighLow` e `Level` no DataFrame principal;
3. não propaga `available_index` aos consumidores;
4. possui documentação incompatível com o comportamento real;
5. usa janela efetivamente assimétrica;
6. mistura confirmação fractal com confirmação estrutural;
7. apaga pivôs anteriores quando surge pivô mais extremo do mesmo tipo;
8. destrói EQH/EQL;
9. não trata candle simultaneamente swing high e swing low;
10. não diferencia raw pivot, canonical swing e structural swing;
11. não diferencia `INTERNAL` e `SWING`;
12. não qualifica amplitude, prominence, ATR, ticks ou retracement;
13. pode atravessar sessão, gap de dados e rollover;
14. usa fallback incorreto quando não há candles de confirmação suficientes;
15. não possui processamento incremental real;
16. não preserva histórico de supersessão;
17. overlays não mostram atraso de confirmação;
18. batch final pode divergir do que existiria em tempo real;
19. a engine downstream pode consumir pivôs antes de eles estarem disponíveis.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Anti-lookahead absoluto

Nenhum pivô poderá ser utilizado antes de `available_index`.

Para cada pivô ou swing, devem existir:

```text
origin_index
pivot_confirmed_index
available_index
earliest_execution_index
```

## 3.2. Separação de camadas

A arquitetura deve separar:

```text
RAW_PIVOT
CANONICAL_SWING
STRUCTURAL_SWING
SMC_ROLE
```

## 3.3. Histórico imutável

Pivôs confirmados não podem ser apagados.

Quando substituídos:

```text
status = SUPERSEDED
superseded_by = novo_swing_id
superseded_at = timestamp
```

## 3.4. Processamento incremental

O mesmo core deve operar:

- candle a candle;
- em replay;
- em batch histórico;
- após restart.

## 3.5. Paridade separada da verdade canônica

Devem existir dois modos:

```text
LEGACY_UPSTREAM_EXACT
CANONICAL_V3
```

O modo upstream serve somente para comparação.

## 3.6. Shadow-only

Durante implementação e validação:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
production_truth_replaced = false
```

---

# 4. CAMADAS FUNCIONAIS DA NOVA ENGINE

## 4.1. Camada 1 — Raw Pivot

Detecta extremos locais usando janela esquerda/direita.

Tipos:

```text
RAW_PIVOT_HIGH
RAW_PIVOT_LOW
RAW_PIVOT_BOTH
RAW_PIVOT_AMBIGUOUS
```

## 4.2. Camada 2 — Canonical Swing

Resolve pivôs consecutivos e escolhe o extremo corrente da perna.

Tipos:

```text
CANONICAL_HIGH
CANONICAL_LOW
```

Estados:

```text
PENDING
FRACTAL_CONFIRMED
CANONICAL
SUPERSEDED
LOCKED
```

## 4.3. Camada 3 — Structural Swing

Classifica o swing em relação à sequência estrutural:

```text
HH
HL
LH
LL
EQH
EQL
```

## 4.4. Camada 4 — Papel SMC

Promovido pela Structure Engine V3:

```text
PROTECTED_HIGH
PROTECTED_LOW
WEAK_HIGH
WEAK_LOW
UNCLASSIFIED_HIGH
UNCLASSIFIED_LOW
```

---

# 5. ARQUITETURA-ALVO

Criar os seguintes componentes.

## 5.1. `SwingInputValidator`

Responsável por:

- validar OHLC;
- validar timestamps;
- validar continuidade;
- validar `price_tick`;
- validar parâmetros;
- identificar gaps e rollover.

## 5.2. `RawPivotDetector`

Responsável pela detecção geométrica dos pivôs.

## 5.3. `CanonicalSwingResolver`

Responsável por alternância, substituição e travamento da perna.

## 5.4. `EqualLevelClusterer`

Responsável por EQH/EQL.

## 5.5. `SwingScopeEngine`

Responsável por executar configurações distintas para:

```text
INTERNAL
SWING
```

## 5.6. `SwingStateStore`

Responsável por:

- estado incremental;
- checkpoints;
- persistência;
- idempotência.

## 5.7. `SwingQualityScorer`

Responsável por:

- amplitude;
- prominence;
- ATR;
- ticks;
- retracement;
- duração da perna.

## 5.8. `SwingOverlayAdapter`

Responsável pela visualização temporal correta.

## 5.9. `LegacySwingAdapter`

Responsável pelo modo upstream.

---

# 6. CONTRATOS DE DADOS

## 6.1. Modelo `RawPivotV3`

Campos mínimos:

```text
pivot_id
asset
timeframe
scope

pivot_type
high_low
price

origin_index
origin_at

left_bars
right_bars

pivot_confirmed_index
pivot_confirmed_at

available_index
available_at

earliest_execution_index
earliest_execution_at

status
is_candidate
is_fractal_confirmed

is_cross_session
is_rollover_affected
is_data_gap_affected
continuity_status

engine_version
config_hash
raw
```

## 6.2. Modelo `CanonicalSwingV3`

Campos mínimos:

```text
swing_id
pivot_id
asset
timeframe
scope

high_low
swing_type
price

origin_index
origin_at

pivot_confirmed_index
pivot_confirmed_at
available_index
available_at
earliest_execution_index
earliest_execution_at

status
is_canonical
is_locked
is_structurally_confirmed
is_protected

superseded_by
superseded_index
superseded_at

previous_opposite_swing_id
next_opposite_swing_id
parent_swing_id
parent_leg_id

distance_from_previous_pts
distance_from_previous_ticks
distance_from_previous_atr
leg_bars
prominence_atr
retracement_ratio

equal_cluster_id
is_equal_cluster

is_cross_session
is_rollover_affected
is_data_gap_affected

engine_version
config_hash
raw
```

## 6.3. Modelo `SwingRevisionV3`

Campos mínimos:

```text
revision_id
swing_id
revision_type
previous_status
new_status
trigger_swing_id
trigger_index
trigger_at
reason
engine_version
```

## 6.4. Modelo `EqualLevelClusterV3`

Campos mínimos:

```text
cluster_id
asset
timeframe
scope
cluster_type
member_swing_ids
price_min
price_max
price_mean
first_touch_index
last_touch_index
touch_count
available_index
available_at
status
```

## 6.5. Modelo `SwingEngineStateV3`

Campos mínimos:

```text
state_id
asset
timeframe
scope
last_processed_index
last_processed_at
pending_candidates
last_canonical_high_id
last_canonical_low_id
current_leg_direction
current_leg_id
version
config_hash
```

---

# 7. ENUMS OBRIGATÓRIOS

## 7.1. Scope

```text
INTERNAL
SWING
```

## 7.2. Pivot type

```text
RAW_PIVOT_HIGH
RAW_PIVOT_LOW
RAW_PIVOT_BOTH
RAW_PIVOT_AMBIGUOUS
```

## 7.3. Swing type

```text
CANONICAL_HIGH
CANONICAL_LOW
```

## 7.4. Structural classification

```text
HH
HL
LH
LL
EQH
EQL
UNCLASSIFIED
```

## 7.5. Status

```text
PENDING
FRACTAL_CONFIRMED
CANONICAL
SUPERSEDED
LOCKED
STRUCTURALLY_CONFIRMED
PROTECTED
INVALIDATED
REJECTED
```

## 7.6. Continuity

```text
CONTINUOUS
SESSION_BOUNDARY
DATA_GAP
ROLLOVER
UNKNOWN
```

## 7.7. Revision type

```text
PROMOTED
SUPERSEDED
LOCKED
STRUCTURALLY_CONFIRMED
PROTECTED
INVALIDATED
REJECTED
```

---

# 8. FASE 0 — AUDITORIA E BASELINE

## Objetivo

Mapear o uso atual da Swing Engine antes de alterar contratos.

## Tarefas

1. localizar todos os usos de:
   - `calculate_swings`;
   - `calculate_swing_records`;
   - `SwingV2`;
   - `HighLow`;
   - `Level`;
   - `confirmed_index`;
   - `available_index`.

2. mapear consumidores:
   - Structure Engine;
   - OB Engine;
   - FVG Engine;
   - liquidity;
   - Elliott;
   - Wyckoff;
   - contextual;
   - directional bias;
   - opportunity scanner;
   - dashboard;
   - backtests;
   - persistência.

3. gerar:
   - `AUDITORIA_DEPENDENCIAS_SWING_ENGINE_V2.md`.

4. congelar baseline:
   - quantidade de swings;
   - distribuição high/low;
   - intervalos;
   - ativos;
   - timeframes;
   - fixtures;
   - hashes.

5. criar feature flags:

```text
SWING_ENGINE_MODE=legacy|shadow_v3|v3
SWING_V3_WRITE_ENABLED=false
SWING_V3_SIGNAL_ENABLED=false
SWING_V3_OVERLAY_ENABLED=false
```

## Critérios de aceite

- consumidores mapeados;
- baseline reproduzível;
- nenhuma mudança em produção;
- feature flags funcionando.

---

# 9. FASE 1 — VALIDAÇÃO DE ENTRADA

## Objetivo

Garantir que a engine não processe dados inválidos.

## Validações obrigatórias

- colunas OHLC presentes;
- valores finitos;
- `high >= max(open, close, low)`;
- `low <= min(open, close, high)`;
- timestamps crescentes;
- ausência de duplicatas;
- OHLC e timestamps com mesmo tamanho;
- `left_bars >= 1`;
- `right_bars >= 1`;
- `price_tick > 0`;
- quantidade mínima de candles;
- NaN em janela explicitamente tratado.

## Critérios de aceite

- entradas inválidas geram erro claro;
- nenhum fallback silencioso;
- logs informam candle e causa.

---

# 10. FASE 2 — PARÂMETROS EXPLÍCITOS DE JANELA

## Objetivo

Eliminar a ambiguidade de `swing_length`.

## Tarefas

1. substituir internamente por:

```text
left_bars
right_bars
```

2. definir janela canônica:

```text
[origin-left_bars ... origin ... origin+right_bars]
```

3. manter compatibilidade:

```text
legacy_swing_length
```

4. documentar tradução do modo upstream;
5. impedir janela assimétrica no modo canônico;
6. criar testes de borda.

## Critérios de aceite

- semântica inequívoca;
- janela simétrica quando configurada;
- perfil legado preservado.

---

# 11. FASE 3 — RAW PIVOT DETECTOR

## Objetivo

Detectar pivôs fractais sem misturá-los com swings estruturais.

## Regras

### Pivot High

```text
high[origin] >= todos os highs à esquerda
high[origin] >= todos os highs à direita
```

### Pivot Low

```text
low[origin] <= todos os lows à esquerda
low[origin] <= todos os lows à direita
```

## Política de igualdade

Configurar:

```text
strict
leftmost
rightmost
cluster
```

Padrão recomendado:

```text
cluster
```

## Candle simultaneamente high e low

Não escolher automaticamente.

Classificar como:

```text
RAW_PIVOT_BOTH
```

ou:

```text
RAW_PIVOT_AMBIGUOUS
```

## Critérios de aceite

- pivô só confirmado após `right_bars`;
- sem fallback para origem;
- BOTH preservado;
- igualdade tratada explicitamente.

---

# 12. FASE 4 — TEMPORALIDADE E DISPONIBILIDADE

## Objetivo

Eliminar lookahead downstream.

## Regras

```text
origin_index = candle extremo
pivot_confirmed_index = origin_index + right_bars
available_index = pivot_confirmed_index
```

Para execução conservadora:

```text
earliest_execution_index = available_index + 1
```

## Timestamps

Separar:

```text
origin_at
confirming_bar_open_at
pivot_confirmed_at
available_at
earliest_execution_at
```

## Critérios de aceite

- nenhum pivô consumido antes da disponibilidade;
- timestamp usa fechamento, não abertura, quando aplicável;
- consumidores antigos bloqueados ou adaptados.

---

# 13. FASE 5 — CANONICAL SWING RESOLVER

## Objetivo

Resolver pivôs consecutivos sem destruir o histórico.

## Regras

### Dois highs consecutivos

- o mais alto torna-se canônico;
- o anterior vira `SUPERSEDED`;
- manter revisão.

### Dois lows consecutivos

- o mais baixo torna-se canônico;
- o anterior vira `SUPERSEDED`;
- manter revisão.

### Pivô oposto

- encerra a perna anterior;
- pode travar o swing anterior;
- inicia nova perna.

## Proibições

- não apagar pivô;
- não reescrever histórico;
- não antecipar supersessão;
- não aplicar supersessão antes do novo pivô estar disponível.

## Critérios de aceite

- histórico imutável;
- revisão auditável;
- resultado incremental reproduzível.

---

# 14. FASE 6 — LOCK DA PERNA

## Objetivo

Diferenciar swing provisório de swing travado.

## Regras

Um canonical high pode permanecer revisável até surgir low oposto válido.

Quando low oposto é confirmado:

```text
canonical high → LOCKED
```

O inverso vale para low.

## Campos

```text
locked_index
locked_at
locked_by_swing_id
```

## Critérios de aceite

- swing revisável e locked são distintos;
- estrutura downstream pode escolher somente locked swings;
- lock não usa futuro além da disponibilidade do pivô oposto.

---

# 15. FASE 7 — EQH E EQL

## Objetivo

Preservar equal highs e equal lows.

## Tolerância

```text
equal_tolerance_ticks
equal_tolerance_atr
```

## Regras

- highs dentro da tolerância formam `EQH`;
- lows dentro da tolerância formam `EQL`;
- todos os membros são preservados;
- cluster possui faixa e nível médio;
- cluster alimenta liquidez;
- ZigZag pode escolher representante sem apagar membros.

## Critérios de aceite

- nenhum EQH/EQL destruído;
- cluster auditável;
- sweep posterior detectável.

---

# 16. FASE 8 — CLASSIFICAÇÃO HH/HL/LH/LL

## Objetivo

Adicionar classificação estrutural sem promover papéis SMC ainda.

## Regras

Comparar swings canônicos locked do mesmo tipo.

### High

```text
acima da tolerância → HH
abaixo da tolerância → LH
dentro da tolerância → EQH
```

### Low

```text
acima da tolerância → HL
abaixo da tolerância → LL
dentro da tolerância → EQL
```

## Critérios de aceite

- classificação determinística;
- tolerância configurável;
- sem inferir protected/weak nesta fase.

---

# 17. FASE 9 — INTERNAL E SWING SCOPES

## Objetivo

Separar escalas estruturais.

## Perfis

### INTERNAL

- janela menor;
- menor amplitude mínima;
- maior sensibilidade.

### SWING

- janela maior;
- maior amplitude mínima;
- maior prominence;
- menor ruído.

## Regras

- pipelines independentes;
- estados independentes;
- parent-child opcional;
- internal não substitui swing;
- cada evento carrega `scope`.

## Critérios de aceite

- complex pullback preservado;
- internal e swing coexistem;
- dashboard filtra por scope.

---

# 18. FASE 10 — FILTROS DE QUALIDADE

## Objetivo

Evitar micro swings irrelevantes.

## Métricas

```text
distance_pts
distance_ticks
distance_atr
leg_bars
prominence_atr
retracement_ratio
slope
velocity
```

## Configuração

```text
min_swing_distance_ticks
min_swing_distance_atr
min_leg_bars
min_prominence_atr
min_retracement_ratio
```

## Regras

- pivô geométrico pode existir;
- canonical swing pode ser rejeitado por qualidade;
- raw pivot nunca é apagado;
- score deve ser explicável.

## Critérios de aceite

- ruído reduzido;
- parâmetros por ativo/timeframe;
- nada hardcoded para WINFUT.

---

# 19. FASE 11 — SESSÃO, GAP E ROLLOVER

## Objetivo

Marcar pivôs afetados por descontinuidade.

## Tarefas

1. detectar:
   - sessão;
   - candle ausente;
   - gap;
   - rollover;
   - mudança de contrato.

2. marcar:

```text
is_cross_session
is_data_gap_affected
is_rollover_affected
continuity_status
```

3. configuração:

```text
allow_cross_session_pivot
allow_rollover_pivot
require_contiguous_bars
```

## Critérios de aceite

- pivôs afetados não são tratados como normais sem marcação;
- nenhuma rejeição silenciosa;
- perfis configuráveis.

---

# 20. FASE 12 — PROCESSAMENTO INCREMENTAL

## Objetivo

Usar o mesmo core em streaming e batch.

## Fluxo

```text
novo candle fechado
→ validar candle
→ atualizar candidatos pendentes
→ confirmar candidatos cujo right window terminou
→ registrar raw pivots
→ atualizar canonical swings
→ aplicar supersessão
→ travar perna quando aplicável
→ atualizar clusters EQH/EQL
→ persistir checkpoint
```

## Tarefas

- estado serializável;
- checkpoint;
- restart seguro;
- idempotência;
- replay;
- proteção contra duplicatas.

## Critérios de aceite

```text
resultado batch == resultado incremental
```

Incluindo:

- pivôs;
- revisões;
- supersessões;
- locks;
- clusters.

---

# 21. FASE 13 — PERSISTÊNCIA E VERSIONAMENTO

## Objetivo

Persistir V3 sem substituir V2.

## Tabelas/coleções esperadas

```text
swing_raw_pivots_v3
swing_canonical_v3
swing_revisions_v3
swing_equal_clusters_v3
swing_engine_state_v3
```

## Campos operacionais

```text
engine_version
config_hash
run_id
replay_id
source_version
created_at
updated_at
```

## Critérios de aceite

- V2 preservada;
- migração reversível;
- rollback testado;
- escrita por feature flag.

---

# 22. FASE 14 — INTEGRAÇÃO COM STRUCTURE ENGINE V3

## Objetivo

Entregar contrato definitivo para estrutura.

## A Structure Engine deve receber

```text
swing_id
origin_index
pivot_confirmed_index
available_index
earliest_execution_index
scope
status
price
high_low
structural_classification
equal_cluster_id
```

## Regras

- apenas swings elegíveis;
- apenas após disponibilidade;
- somente `CANONICAL` ou `LOCKED`, conforme configuração;
- raw pivot não pode gerar BOS diretamente;
- protected/weak são definidos pela Structure Engine.

## Critérios de aceite

- nenhum BOS usa pivô futuro;
- nenhum OB usa pivô bruto indevidamente;
- causalidade preservada.

---

# 23. FASE 15 — INTEGRAÇÃO COM OB, FVG E LIQUIDEZ

## Order Blocks

OB deve receber:

```text
origin_swing_id
broken_swing_id
impulse_leg_id
structure_event_id
```

## FVG

FVG deve receber:

```text
origin_swing_id
impulse_leg_id
structure_event_id
```

## Liquidez

Liquidez deve receber:

```text
EQH cluster
EQL cluster
weak high
weak low
sweep candidates
```

## Critérios de aceite

- nenhuma integração por índice solto;
- IDs estáveis;
- direção e scope compatíveis.

---

# 24. FASE 16 — OVERLAYS E DASHBOARD

## Objetivo

Mostrar origem e disponibilidade sem antecipação.

## Modos

### Histórico

- marcador na origem;
- linha de swing completa;
- status atual.

### Operacional

- marcador de origem;
- marcador de confirmação;
- `available_index`;
- supersessão;
- lock;
- cluster EQH/EQL.

## Tooltip mínimo

```text
swing_id
scope
status
origin_at
available_at
price
classification
distance_atr
prominence_atr
superseded_by
equal_cluster_id
```

## Critérios de aceite

- visual não antecipa disponibilidade;
- troca de timeframe não perde labels;
- internal/swing distinguíveis.

---

# 25. FASE 17 — TESTES UNITÁRIOS

## Casos obrigatórios

### Janela

- left/right simétricos;
- janela mínima;
- bordas;
- série curta;
- NaN.

### Temporalidade

- pivô confirmado somente após right bars;
- pending no fim da série;
- earliest execution;
- timestamp de fechamento.

### Consecutivos

- high seguido de high maior;
- high seguido de high menor;
- low seguido de low menor;
- low seguido de low maior;
- supersessão.

### Igualdade

- EQH exato;
- EQH por tolerância;
- EQL exato;
- EQL por tolerância.

### Ambiguidade

- candle high e low simultâneo;
- outside bar;
- flat market.

### Scopes

- internal;
- swing;
- complex pullback.

### Continuidade

- gap de sessão;
- data gap;
- rollover.

### Persistência

- restart;
- replay;
- idempotência.

---

# 26. FASE 18 — TESTES DE PROPRIEDADE

Invariantes obrigatórias:

1. `available_index >= pivot_confirmed_index >= origin_index`;
2. pivô pending não pode ser consumido;
3. supersessão só ocorre após disponibilidade do novo pivô;
4. pivô superseded permanece persistido;
5. EQH/EQL preservam todos os membros;
6. raw pivot nunca recebe papel protected diretamente;
7. internal não substitui swing;
8. batch e incremental são equivalentes;
9. restart não duplica eventos;
10. nenhum pivot confirmado aparece nos últimos `right_bars` sem dados suficientes.

---

# 27. FASE 19 — REPLAY SHADOW

## Ativos mínimos

- WINFUT;
- WDOFUT;
- um Forex;
- um ativo com gaps;
- um ativo de baixa liquidez.

## Timeframes mínimos

- M1;
- M2;
- M5;
- M15;
- H4;
- D1.

## Métricas

- total raw pivots;
- total canonical swings;
- total superseded;
- total locked;
- total EQH/EQL;
- diferença V2/V3;
- pivôs antecipados na V2;
- pivôs removidos pela V2;
- divergência batch/stream;
- impacto em BOS/CHOCH;
- impacto em OB;
- impacto em FVG;
- impacto em liquidez.

## Critérios de aceite

- relatório por ativo/timeframe;
- zero promoção de trade;
- divergências explicadas;
- replay determinístico.

---

# 28. FASE 20 — MIGRAÇÃO CONTROLADA

## Etapa 1

```text
SWING_ENGINE_MODE=legacy
```

## Etapa 2

```text
SWING_ENGINE_MODE=shadow_v3
```

- V2 e V3 simultâneas;
- persistência separada;
- dashboard comparativo.

## Etapa 3

- Structure Engine V3 consome Swing V3 em shadow;
- sinais continuam bloqueados.

## Etapa 4

- OB/FVG shadow consomem IDs V3;
- validação conjunta.

## Etapa 5

Promoção futura somente após:

- testes;
- replay;
- backtest;
- revisão humana;
- aprovação arquitetural.

---

# 29. ROLLBACK

Criar:

```text
ROLLBACK_SWING_ENGINE_V3.md
```

O rollback deve permitir:

1. voltar para V2;
2. interromper escrita V3;
3. manter dados V3;
4. restaurar overlay legado;
5. não apagar histórico;
6. preservar schema antigo.

---

# 30. OBSERVABILIDADE

Logs mínimos:

```text
swing.candidate_created
swing.pivot_confirmed
swing.available
swing.promoted_canonical
swing.superseded
swing.locked
swing.equal_cluster_created
swing.equal_cluster_updated
swing.rejected_quality
swing.lookahead_blocked
swing.rollover_flagged
swing.replay_divergence
```

Métricas mínimas:

- candidatos pendentes;
- pivôs confirmados;
- swings canônicos;
- supersessões;
- locks;
- clusters EQH/EQL;
- bloqueios por lookahead;
- divergência V2/V3;
- tempo por candle;
- duplicatas evitadas.

---

# 31. CONFIGURAÇÃO

Criar perfil por ativo/timeframe:

```text
left_bars
right_bars
price_tick

equal_tolerance_ticks
equal_tolerance_atr

min_swing_distance_ticks
min_swing_distance_atr
min_leg_bars
min_prominence_atr
min_retracement_ratio

allow_cross_session_pivot
allow_rollover_pivot
require_contiguous_bars

earliest_execution_offset
tie_policy
both_pivot_policy

internal_profile
swing_profile
```

Regras:

- nada hardcoded;
- hash persistido;
- fallback explícito;
- defaults documentados.

---

# 32. ARQUIVOS ESPERADOS

Estrutura sugerida:

```text
technical_engine/swings/
  __init__.py
  swing_models_v3.py
  swing_config_v3.py
  swing_input_validator.py
  raw_pivot_detector.py
  canonical_swing_resolver.py
  equal_level_clusterer.py
  swing_quality_scorer.py
  swing_scope_engine.py
  swing_state_store.py
  swing_engine_v3.py
  swing_overlays_v3.py
  legacy_swing_adapter.py
```

Testes:

```text
tests/swings_v3/
  test_input_validation.py
  test_raw_pivots.py
  test_pivot_availability.py
  test_canonical_resolver.py
  test_supersession.py
  test_locking.py
  test_eqh_eql.py
  test_both_pivot.py
  test_internal_scope.py
  test_swing_scope.py
  test_quality_filters.py
  test_session_gap_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_anti_lookahead.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/SWING_ENGINE_V3.md
docs/migrations/SWING_ENGINE_V3_MIGRATION.md
docs/operations/ROLLBACK_SWING_ENGINE_V3.md
docs/reports/RELATORIO_FINAL_SWING_ENGINE_V3.md
```

---

# 33. CRITÉRIOS DE ACEITE GERAIS

A implementação só pode ser considerada concluída quando:

1. nenhum consumidor usa pivô antes de `available_index`;
2. não existe fallback de confirmação na origem;
3. raw pivot e structural swing estão separados;
4. superseded não é apagado;
5. EQH/EQL são preservados;
6. candle BOTH é tratado explicitamente;
7. internal e swing são independentes;
8. batch e incremental produzem o mesmo resultado;
9. restart é idempotente;
10. overlays mostram origem e confirmação;
11. V2 permanece disponível;
12. feature flags funcionam;
13. rollback foi testado;
14. testes anti-lookahead passam;
15. relatório final foi entregue;
16. nenhuma promoção de trade ocorreu.

---

# 34. DEFINITION OF DONE

A Swing Engine V3 estará pronta quando:

- código compilar;
- testes passarem;
- cobertura do core for no mínimo 90%;
- invariantes anti-lookahead estiverem verdes;
- batch e streaming forem equivalentes;
- V2 estiver preservada;
- V3 estiver persistida separadamente;
- dashboard comparativo estiver funcional;
- Structure Engine V3 consumir o contrato correto em shadow;
- rollback estiver documentado e testado;
- relatório final estiver concluído.

---

# 35. RELATÓRIO FINAL OBRIGATÓRIO

## 35.1. Resumo executivo

- correções realizadas;
- limitações;
- riscos;
- status final.

## 35.2. Arquivos alterados

| Arquivo | Tipo | Alteração |
|---|---|---|

## 35.3. Contratos criados

- modelos;
- enums;
- tabelas;
- DTOs;
- APIs.

## 35.4. Testes

| Suíte | Total | Passou | Falhou | Skip |
|---|---:|---:|---:|---:|

## 35.5. Anti-lookahead

Demonstrar:

- pivô pending;
- confirmação após right bars;
- disponibilidade correta;
- replay parcial;
- igualdade batch/incremental.

## 35.6. Comparativo V2/V3

| Métrica | V2 | V3 | Diferença |
|---|---:|---:|---:|

## 35.7. Impacto downstream

- Structure;
- OB;
- FVG;
- liquidez;
- Elliott;
- Wyckoff;
- scanner;
- dashboard.

## 35.8. Guardrails

Confirmar:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
production_truth_replaced = false
```

## 35.9. Rollback

- procedimento;
- comandos;
- validação.

## 35.10. Status final

Usar uma opção:

```text
SWING_V3_COMPLETED_SHADOW
SWING_V3_COMPLETED_WITH_LIMITATIONS
SWING_V3_BLOCKED
SWING_V3_FAILED
```

---

# 36. ORDEM DE EXECUÇÃO RECOMENDADA

Executar nesta ordem:

1. auditoria;
2. baseline;
3. feature flags;
4. validação de entrada;
5. parâmetros left/right;
6. raw pivot detector;
7. temporalidade;
8. canonical resolver;
9. supersessão;
10. lock da perna;
11. EQH/EQL;
12. HH/HL/LH/LL;
13. internal/swing;
14. filtros de qualidade;
15. sessão/gap/rollover;
16. processamento incremental;
17. persistência;
18. overlays;
19. integração Structure V3;
20. integração OB/FVG/liquidez;
21. testes;
22. replay shadow;
23. relatório final.

Não integrar Structure Engine V3 antes de os testes de disponibilidade e anti-lookahead estarem verdes.

---

# 37. REGRAS PARA A IA DE CÓDIGO

1. Não apagar pivôs históricos.
2. Não usar candles futuros fora da confirmação explícita.
3. Não expor pivô antes de `available_index`.
4. Não misturar raw pivot com swing estrutural.
5. Não destruir EQH/EQL.
6. Não escolher arbitrariamente high em candle BOTH.
7. Não hardcodar WINFUT.
8. Não substituir V2 diretamente.
9. Não alterar sinais de produção.
10. Não criar fallback silencioso.
11. Não declarar concluído sem replay e testes.
12. Não usar somente o resultado batch como prova.
13. Não omitir histórico de revisões.
14. Não integrar OB/FVG antes do contrato definitivo.
15. Em caso de ambiguidade, preservar o evento e marcar como `AMBIGUOUS`.

---

# 38. RESULTADO ESPERADO

Ao final, a engine deverá responder com precisão:

- onde o pivô ocorreu;
- quando foi confirmado;
- quando ficou disponível;
- quando poderia ser usado por uma estratégia;
- se é raw pivot, canonical swing ou structural swing;
- se foi superseded;
- se está locked;
- se pertence à estrutura internal ou swing;
- se é HH, HL, LH, LL, EQH ou EQL;
- qual perna originou;
- qual cluster pertence;
- se foi afetado por sessão, gap ou rollover;
- qual Structure Event, OB, FVG ou evento de liquidez está relacionado.

A Swing Engine V3 deve ser causal, incremental, imutável, explicável e segura para servir como base canônica de toda a arquitetura SMC.

---
# SEÇÕES ESPECÍFICAS — SWING ENGINE V3

## Ownership do Domínio (Confirmado)

Swing é dona exclusiva de:
- `RawPivotV3`
- `CanonicalSwingV3`
- `SwingRevisionV3`
- `EqualLevelClusterV3`
- `SwingEngineStateV3`

**Regra:** Swing é a única autoridade geométrica de EQH/EQL. Structure é dona de protected/weak via `SwingSmcRoleProjectionV3`.

## Contratos Produzidos

| Contrato | Consumidor | Gate |
|---|---|---|
| `CanonicalSwingContractV1` | Structure | G2 |
| `EqualLevelClusterV3` | Liquidity | G2 |

## Contratos Consumidos

| Contrato | Produtor |
|---|---|
| Candles, Sessions/continuity metadata, ATR, price tick | Infraestrutura |

## Gate de Entrada

G-1 (V3 Package Ready)

## Gate de Saída

**G2 — Swing Core Ready:** raw/canonical, available_index, supersession sem apagar, EqualLevelCluster, internal/swing, batch/stream parity.

## Dependências

- **Obrigatórias:** candles, Sessions/continuity metadata, ATR, price tick
- **Não depende de Structure** — camadas 1-3 são independentes

## Regra de Separação

- `swing_kind` separado de `detection_method`
- Structure produz apenas a projeção `SwingSmcRoleProjectionV3`
- Swing não modifica registro canônico ao receber protected/weak

## Caminhos Batch

- `smc_engine_v3/swings.py`

## Caminhos Incrementais

- `incremental/components/swing.py`

## Proibição

- Não destruir EQH/EQL
- Não apagar pivôs históricos
- Não misturar `swing_type` com `detection_method`
