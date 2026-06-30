# PLANO OPERACIONAL COMPLETO — CORREÇÃO DA LIQUIDITY ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Módulo:** Liquidity Detection / Liquidity Pools / Sweeps / ERL / IRL  
**Versão-alvo:** Liquidity Engine V3  
**Escopo:** correção arquitetural, temporal, estrutural e operacional da engine de liquidez  
**Modo de execução:** incremental, causal, anti-lookahead, auditável e shadow-only até aprovação  
**Dependências obrigatórias:** Swing Engine V3 e Structure Engine V3  
**Objetivo:** substituir o detector retrospectivo simplificado de clusters horizontais por uma engine completa de liquidez SMC, capaz de identificar BSL/SSL, EQH/EQL, níveis estruturais, ERL/IRL, sweeps, close-through, consumption, reclaim, inducement e relações MTF, sem antecipação temporal e sem quebrar o pipeline atual.

---

# 1. OBJETIVO GERAL

Construir uma Liquidity Engine V3 que:

- detecte liquidez de forma causal e incremental;
- consuma apenas swings e níveis estruturais já disponíveis;
- diferencie BSL e SSL;
- represente liquidez como zona, e não apenas como um preço médio;
- diferencie candidate, forming, confirmed, active, tested, swept, consumed e expired;
- diferencie wick sweep de close-through;
- classifique ERL e IRL;
- integre EQH/EQL, weak/protected levels, structure highs/lows, trendline liquidity e momentum shift;
- mantenha histórico imutável;
- não use ATR futuro;
- não use range global futuro;
- tenha idempotência;
- funcione em batch, replay e streaming com o mesmo resultado;
- alimente OB, FVG, Structure, Opportunity Scanner e contexto MTF;
- permaneça shadow-only até validação completa.

---

# 2. PROBLEMAS DO ESTADO ATUAL

A implementação atual possui os seguintes problemas:

1. usa o ATR final de toda a janela para clusters históricos;
2. usa o range total do DataFrame no modo legado;
3. herda lookahead da Swing Engine V2;
4. considera o cluster existente desde o primeiro membro;
5. pode registrar sweep antes da disponibilidade do segundo membro;
6. pode usar o mesmo candle para formar e varrer o cluster;
7. representa o pool por preço médio;
8. usa a tolerância de agrupamento como limiar de sweep;
9. mistura wick sweep com breakout;
10. detecta apenas clusters horizontais;
11. exige dois swings, ignorando liquidez estrutural de nível único;
12. não possui ERL/IRL;
13. usa nomenclatura ambígua “bullish/bearish liquidity”;
14. remove membros de consideração de forma destrutiva;
15. ancora o cluster somente no primeiro membro;
16. não exige distância temporal ou excursão mínima entre testes;
17. não controla idade do pool;
18. encerra visualmente a linha no último membro, mesmo quando o pool continua ativo;
19. sobrecarrega o campo `End`;
20. possui lifecycle binário;
21. não mede penetração, reação e shift;
22. calibra “swept rate” sem horizonte e sem reação;
23. usa tolerância ATR possivelmente excessiva;
24. usa ATR com warm-up incompleto;
25. não normaliza por tick;
26. não trata sessão, gap e rollover;
27. não integra estado estrutural;
28. não classifica inducement;
29. não implementa trendline liquidity;
30. não implementa momentum shift liquidity;
31. não implementa structure high/low liquidity;
32. não implementa MTF;
33. tem complexidade próxima de O(n²);
34. não valida alinhamento e integridade de entrada.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Anti-lookahead absoluto

Nenhum pool ou evento pode ser utilizado antes de seu `available_index`.

Campos temporais mínimos:

```text
first_member_origin_index
last_member_origin_index
cluster_confirmed_index
cluster_available_index
earliest_execution_index
```

## 3.2. Liquidez como zona

Toda liquidez deve possuir:

```text
price_min
price_max
inner_boundary
outer_boundary
representative_price
```

A média pode existir, mas não pode ser a única representação.

## 3.3. Separação entre fonte e evento

A fonte de liquidez é uma coisa; o que o preço faz com ela é outra.

Exemplo:

```text
source_type = EQUAL_LEVEL_CLUSTER
event_type = WICK_SWEEP
```

## 3.4. Histórico imutável

Nenhum pool, membro ou evento confirmado pode ser apagado.

Estados podem mudar, mas a trilha deve permanecer.

## 3.5. Processamento incremental

O mesmo core deve processar:

- candle fechado;
- replay;
- batch;
- restart.

## 3.6. Dependência estrutural explícita

ERL, IRL, protected/weak liquidity e sweep-and-shift dependem da Structure Engine V3.

## 3.7. Shadow-only

Durante toda a implementação:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
production_truth_replaced = false
llm_decision_used = false
```

---

# 4. ESCOPO FUNCIONAL

A Liquidity Engine V3 deve contemplar:

- BSL;
- SSL;
- EQH;
- EQL;
- Structure High liquidity;
- Structure Low liquidity;
- Protected High liquidity;
- Protected Low liquidity;
- Weak High liquidity;
- Weak Low liquidity;
- ERL;
- IRL;
- trendline liquidity;
- momentum shift liquidity;
- session liquidity;
- range extremes;
- inducement;
- candidate pool;
- forming pool;
- confirmed pool;
- active pool;
- tested pool;
- partially raided pool;
- wick sweep;
- close-through;
- consumption;
- reclaim;
- sweep-and-shift;
- expiration;
- invalidation;
- relações MTF;
- vínculo com OB, FVG e eventos estruturais.

---

# 5. FORA DO ESCOPO DESTA FASE

Não implementar nesta fase:

- promoção automática de trades;
- mudança da verdade operacional;
- calibração definitiva de entradas;
- otimização genética;
- score final de oportunidade;
- alteração do Risk Management;
- substituição da engine V2 sem shadow;
- remoção de dados históricos V2;
- inferência por LLM na decisão da liquidez.

---

# 6. ARQUITETURA-ALVO

A engine deve ser dividida em módulos independentes.

## 6.1. `LiquidityInputValidator`

Responsável por:

- validar OHLC;
- validar timestamps;
- validar swings;
- validar StructureState/StructureLevel;
- validar `price_tick`;
- validar ATR;
- validar continuidade.

## 6.2. `EqualLevelLiquidityDetector`

Detecta:

- EQH;
- EQL;
- clusters horizontais.

## 6.3. `StructureLevelLiquidityDetector`

Detecta liquidez em:

- weak high;
- weak low;
- protected high;
- protected low;
- structure high;
- structure low.

## 6.4. `RangeLiquidityClassifier`

Classifica:

- ERL;
- IRL;
- range high;
- range low;
- external targets;
- internal pools.

## 6.5. `TrendlineLiquidityDetector`

Detecta liquidez projetada em linhas de tendência.

## 6.6. `MomentumShiftLiquidityDetector`

Detecta pools inclinados ou compressivos que não são equal levels.

## 6.7. `SessionLiquidityDetector`

Detecta níveis de sessão, quando habilitado:

- high/low da sessão;
- high/low do dia anterior;
- high/low da semana;
- abertura de sessão;
- pools recorrentes.

## 6.8. `LiquidityPoolRegistry`

Responsável por:

- criar;
- atualizar;
- deduplicar;
- hierarquizar;
- vincular membros;
- manter lifecycle.

## 6.9. `LiquidityEventDetector`

Responsável por:

- test;
- partial raid;
- wick sweep;
- close-through;
- consumption;
- reclaim;
- shift.

## 6.10. `LiquidityStateMachine`

Responsável por transições de estado.

## 6.11. `LiquidityQualityScorer`

Responsável por qualidade e contexto.

## 6.12. `LiquidityPersistenceV3`

Responsável por persistência, versionamento e idempotência.

## 6.13. `LiquidityOverlayAdapterV3`

Responsável por visualização correta.

## 6.14. `LegacyLiquidityAdapter`

Responsável por manter paridade V2.

---

# 7. CONTRATOS DE DADOS

## 7.1. Modelo `LiquidityPoolV3`

Campos mínimos:

```text
liquidity_id
asset
timeframe
scope

liquidity_side
source_type
range_type
role
status

price_min
price_max
inner_boundary
outer_boundary
representative_price
mean_price

member_swing_ids
member_level_ids
member_count

first_member_origin_index
first_member_origin_at
last_member_origin_index
last_member_origin_at

cluster_confirmed_index
cluster_confirmed_at
cluster_available_index
cluster_available_at

earliest_execution_index
earliest_execution_at

first_test_index
first_test_at
partial_raid_index
partial_raid_at
wick_sweep_index
wick_sweep_at
close_through_index
close_through_at
consumed_index
consumed_at
reclaimed_index
reclaimed_at
shift_confirmed_index
shift_confirmed_at
invalidated_index
invalidated_at
expired_index
expired_at

penetration_pts
penetration_ticks
penetration_atr
max_penetration_pts
max_penetration_ticks
max_penetration_atr

structure_id
structure_event_id
protected_level_id
weak_level_id
dealing_range_id
parent_liquidity_id
higher_timeframe_liquidity_id
shift_event_id

is_cross_session
is_rollover_affected
is_data_gap_affected
continuity_status

quality_score
quality_label

engine_version
config_hash
run_id
replay_id
raw
```

## 7.2. Modelo `LiquidityEventV3`

Campos mínimos:

```text
event_id
liquidity_id
asset
timeframe
scope

event_type
liquidity_side
status

event_index
event_at
confirmed_index
available_index
earliest_execution_index

price
open
high
low
close

penetration_pts
penetration_ticks
penetration_atr

close_back_inside
close_beyond_outer_boundary
gap_through
same_bar_ambiguous

previous_pool_status
new_pool_status

structure_event_id
shift_event_id
impulse_leg_id

reaction_mfe
reaction_mae
bars_to_reaction

engine_version
config_hash
raw
```

## 7.3. Modelo `LiquidityPoolMemberV3`

Campos mínimos:

```text
membership_id
liquidity_id
member_type
member_id
origin_index
available_index
price
distance_to_cluster_pts
distance_to_cluster_ticks
distance_to_cluster_atr
joined_index
joined_at
```

## 7.4. Modelo `DealingRangeV3`

Campos mínimos:

```text
dealing_range_id
asset
timeframe
scope
range_high_level_id
range_low_level_id
range_high
range_low
equilibrium
origin_index
available_index
status
parent_structure_id
```

## 7.5. Modelo `LiquidityEngineStateV3`

Campos mínimos:

```text
state_id
asset
timeframe
scope
last_processed_index
last_processed_at
active_pool_ids
forming_pool_ids
pending_event_ids
version
config_hash
```

---

# 8. ENUMS OBRIGATÓRIOS

## 8.1. Liquidity side

```text
BSL
SSL
```

## 8.2. Source type

```text
EQUAL_LEVEL_CLUSTER
STRUCTURE_HIGH
STRUCTURE_LOW
PROTECTED_HIGH
PROTECTED_LOW
WEAK_HIGH
WEAK_LOW
TRENDLINE
MOMENTUM_SHIFT
SESSION_HIGH
SESSION_LOW
PREVIOUS_DAY_HIGH
PREVIOUS_DAY_LOW
PREVIOUS_WEEK_HIGH
PREVIOUS_WEEK_LOW
RANGE_HIGH
RANGE_LOW
OTHER
```

## 8.3. Range type

```text
ERL
IRL
UNCLASSIFIED
```

## 8.4. Role

```text
PRIMARY_TARGET
SECONDARY_TARGET
INDUCEMENT
INTERNAL_POOL
EXTERNAL_POOL
FUEL
REVERSAL_SOURCE
UNKNOWN
```

## 8.5. Pool status

```text
CANDIDATE
FORMING
CONFIRMED
ACTIVE
TESTED
PARTIALLY_RAIDED
WICK_SWEPT
CLOSE_THROUGH
CONSUMED
RECLAIMED
SHIFT_CONFIRMED
INVALIDATED
EXPIRED
AMBIGUOUS
```

## 8.6. Event type

```text
FIRST_TEST
PARTIAL_RAID
WICK_SWEEP
CLOSE_THROUGH
GAP_THROUGH
CONSUMPTION
RECLAIM
SHIFT_CONFIRMATION
EXPIRATION
INVALIDATION
SAME_BAR_AMBIGUOUS
```

## 8.7. Continuity status

```text
CONTINUOUS
SESSION_BOUNDARY
DATA_GAP
ROLLOVER
UNKNOWN
```

## 8.8. Scope

```text
INTERNAL
SWING
```

---

# 9. FASE 0 — AUDITORIA E BASELINE

## Objetivo

Mapear todas as dependências da Liquidity Engine V2.

## Tarefas

1. localizar usos de:
   - `calculate_liquidity`;
   - `calculate_liquidity_records`;
   - `LiquidityV2`;
   - `Liquidity`;
   - `Level`;
   - `End`;
   - `Swept`.

2. mapear consumidores:
   - Structure;
   - OB;
   - FVG;
   - directional bias;
   - contextual;
   - Opportunity Scanner;
   - dashboard;
   - backtest;
   - persistência;
   - API;
   - relatórios.

3. congelar baseline:
   - quantidade de pools;
   - quantidade swept;
   - distribuição ABOVE/BELOW;
   - ativos;
   - timeframes;
   - datas;
   - configuração ATR;
   - configuração range_percent;
   - hashes.

4. criar:
   - `AUDITORIA_DEPENDENCIAS_LIQUIDITY_ENGINE_V2.md`.

5. criar feature flags:

```text
LIQUIDITY_ENGINE_MODE=legacy|shadow_v3|v3
LIQUIDITY_V3_WRITE_ENABLED=false
LIQUIDITY_V3_SIGNAL_ENABLED=false
LIQUIDITY_V3_OVERLAY_ENABLED=false
LIQUIDITY_V3_ERL_IRL_ENABLED=false
LIQUIDITY_V3_TRENDLINE_ENABLED=false
LIQUIDITY_V3_MOMENTUM_SHIFT_ENABLED=false
```

## Critérios de aceite

- consumidores mapeados;
- baseline reproduzível;
- nenhuma alteração de produção;
- feature flags testadas.

---

# 10. FASE 1 — VALIDAÇÃO DE ENTRADA

## Objetivo

Impedir processamento com dados inválidos.

## Validações obrigatórias

### OHLC

- colunas presentes;
- valores finitos;
- high/low consistentes;
- timestamps crescentes;
- ausência de duplicatas;
- tamanho mínimo.

### Swings

- contrato Swing V3;
- IDs;
- `available_index`;
- `scope`;
- status;
- preço;
- direção.

### Structure

- state disponível;
- level IDs;
- dealing range;
- protected/weak levels.

### Configuração

- `price_tick > 0`;
- ATR canônico disponível;
- thresholds não negativos;
- janela e idade válidas.

## Critérios de aceite

- erros explícitos;
- nenhum fallback silencioso;
- logs com causa e índice.

---

# 11. FASE 2 — ATR CAUSAL E NORMALIZAÇÃO POR TICK

## Objetivo

Eliminar o uso de ATR futuro.

## Tarefas

1. remover `_compute_atr(...).iloc[-1]` do modo canônico;
2. consumir ATR por candle;
3. exigir `atr_ready=True`;
4. congelar ATR de referência no `cluster_available_index`;
5. normalizar preços e distâncias por tick;
6. persistir:
   - `atr_at_confirmation`;
   - `price_tick`;
   - `cluster_width_ticks`;
   - `cluster_width_atr`.

## Critérios de aceite

- cluster histórico não muda ao anexar candles futuros;
- nenhuma tolerância usa ATR final;
- warm-up respeitado;
- preços alinhados ao tick.

---

# 12. FASE 3 — EQUAL LEVEL LIQUIDITY DETECTOR

## Objetivo

Implementar EQH/EQL de forma causal.

## Regras

### BSL por equal highs

- dois ou mais swing highs disponíveis;
- dentro da tolerância;
- separados por excursão mínima;
- disponíveis antes da confirmação do cluster.

### SSL por equal lows

- lógica inversa.

## Parâmetros

```text
equal_tolerance_ticks
equal_tolerance_atr
min_members
min_bars_between_members
min_opposite_excursion_ticks
min_opposite_excursion_atr
max_cluster_width_ticks
max_cluster_width_atr
```

## Política de clustering

Recomendada:

```text
max(member_prices) - min(member_prices)
<= max_cluster_width
```

## Critérios de aceite

- membros preservados;
- cluster confirmado apenas após membro mínimo disponível;
- nenhuma remoção destrutiva;
- cluster não depende do futuro.

---

# 13. FASE 4 — DISPONIBILIDADE DO CLUSTER

## Objetivo

Definir quando o pool passa a existir operacionalmente.

## Regras

```text
cluster_confirmed_index =
máximo available_index dos membros mínimos necessários
```

```text
cluster_available_index =
cluster_confirmed_index
```

Para execução conservadora:

```text
earliest_execution_index =
cluster_available_index + 1
```

## Proibições

- cluster não pode ser consumido desde o primeiro membro;
- sweep não pode ser detectado antes de `cluster_available_index`;
- membro confirmador não pode varrer o cluster no passado.

## Critérios de aceite

- replay parcial não cria pool cedo demais;
- same-bar ambiguity marcada;
- overlays distinguem origem e disponibilidade.

---

# 14. FASE 5 — REPRESENTAÇÃO DA ZONA

## Objetivo

Substituir preço médio isolado por zona completa.

## Regras

### BSL

```text
price_min = menor high membro
price_max = maior high membro
inner_boundary = price_min
outer_boundary = price_max
representative_price = outer_boundary
```

### SSL

```text
price_min = menor low membro
price_max = maior low membro
inner_boundary = price_max
outer_boundary = price_min
representative_price = outer_boundary
```

## Campos adicionais

```text
mean_price
median_price
cluster_center
```

A média não pode ser usada como limiar de sweep.

## Critérios de aceite

- zona persistida;
- outer boundary correta;
- overlay mostra faixa;
- sweep usa outer boundary + buffer.

---

# 15. FASE 6 — MEMBER TOLERANCE E SWEEP BUFFER

## Objetivo

Separar agrupamento de remoção de liquidez.

## Configuração

```text
member_tolerance_ticks
member_tolerance_atr
sweep_buffer_ticks
sweep_buffer_atr
close_through_buffer_ticks
close_through_buffer_atr
```

## Regras

### BSL

```text
sweep_threshold =
outer_boundary + sweep_buffer
```

### SSL

```text
sweep_threshold =
outer_boundary - sweep_buffer
```

## Critérios de aceite

- cluster width não define sweep;
- buffers independentes;
- thresholds persistidos.

---

# 16. FASE 7 — LIQUIDITY EVENT DETECTOR

## Objetivo

Diferenciar eventos de interação com liquidez.

## 16.1. FIRST_TEST

Preço toca a zona sem ultrapassar o outer boundary.

## 16.2. PARTIAL_RAID

Preço penetra além da inner boundary, mas não confirma sweep completo.

## 16.3. WICK_SWEEP

### BSL

```text
high > sweep_threshold
close <= outer_boundary
```

### SSL

```text
low < sweep_threshold
close >= outer_boundary
```

## 16.4. CLOSE_THROUGH

### BSL

```text
close > outer_boundary + close_through_buffer
```

### SSL

```text
close < outer_boundary - close_through_buffer
```

## 16.5. GAP_THROUGH

Candle abre além da zona sem negociação contínua.

## 16.6. CONSUMPTION

Close-through confirmado e pool removido como alvo ativo.

## 16.7. RECLAIM

Preço fecha além e depois retorna/fecha novamente do lado original.

## 16.8. SHIFT_CONFIRMATION

Sweep seguido de CHOCH/BOS contrário conforme Structure Engine.

## Critérios de aceite

- wick sweep não vira close-through;
- gap separado;
- causalidade preservada;
- direção compatível.

---

# 17. FASE 8 — MÁQUINA DE ESTADOS

## Fluxo base

```text
CANDIDATE
→ FORMING
→ CONFIRMED
→ ACTIVE
```

## Transições possíveis

```text
ACTIVE
→ TESTED
→ PARTIALLY_RAIDED
→ WICK_SWEPT
→ SHIFT_CONFIRMED
```

ou:

```text
ACTIVE
→ CLOSE_THROUGH
→ CONSUMED
```

ou:

```text
CLOSE_THROUGH
→ RECLAIMED
```

ou:

```text
ACTIVE
→ EXPIRED
```

## Regras

- toda transição gera evento;
- status anterior preservado;
- transições inválidas geram erro;
- nenhum estado retrocede sem evento específico.

## Critérios de aceite

- lifecycle determinístico;
- histórico completo;
- idempotência.

---

# 18. FASE 9 — STRUCTURE LEVEL LIQUIDITY

## Objetivo

Detectar liquidez estrutural sem exigir dois swings iguais.

## Fontes

```text
WEAK_HIGH → BSL
WEAK_LOW → SSL
PROTECTED_HIGH → BSL
PROTECTED_LOW → SSL
STRUCTURE_HIGH → BSL
STRUCTURE_LOW → SSL
```

## Regras

- consumir apenas StructureLevelV3 disponível;
- persistir `source_level_id`;
- classificar scope;
- diferenciar alvo estrutural de equal level pool.

## Critérios de aceite

- nível único pode gerar pool;
- origem rastreável;
- não duplicar pool equivalente.

---

# 19. FASE 10 — ERL E IRL

## Objetivo

Classificar liquidez dentro e fora do dealing range.

## Dependências

- dealing range;
- protected levels;
- StructureStateV3.

## Regras conceituais

### ERL

Liquidez nos extremos externos relevantes do range.

### IRL

Liquidez dentro do range, antes do alvo externo.

## Campos

```text
dealing_range_id
range_type
range_position
distance_to_equilibrium
distance_to_external_boundary
```

## Critérios de aceite

- ERL/IRL dependem de range válido;
- sem range, marcar UNCLASSIFIED;
- não inferir por heurística isolada.

---

# 20. FASE 11 — INDUCEMENT

## Objetivo

Classificar pools que podem atuar como isca antes do alvo principal.

## Dependências

- OB;
- FVG;
- POI;
- Structure;
- dealing range.

## Regras iniciais

Um pool pode ser `INDUCEMENT` quando:

- está entre o preço e POI relevante;
- é IRL;
- não é o alvo externo principal;
- está alinhado com perna de pullback;
- existe liquidez maior além dele.

## Guardrail

Inducement deve ser inicialmente:

```text
shadow_only
confidence = LOW|MEDIUM
```

Até validação.

## Critérios de aceite

- não classificar sem POI/range;
- preservar justificativa;
- vincular alvo principal.

---

# 21. FASE 12 — TRENDLINE LIQUIDITY

## Objetivo

Detectar liquidez sobre highs ou abaixo de lows alinhados.

## Requisitos

- 2 ou 3 pivôs mínimos configuráveis;
- regressão ou linha entre membros;
- tolerância à linha;
- extensão temporal;
- slope;
- lado da liquidez.

## Campos

```text
line_anchor_1_id
line_anchor_2_id
line_anchor_3_id
slope
intercept
projection_price
projection_index
line_tolerance
```

## Eventos

- test da linha;
- wick sweep da projeção;
- close-through.

## Critérios de aceite

- detector separado do EQH/EQL;
- sem extrapolação ilimitada;
- idade máxima configurável;
- disponível apenas após âncoras confirmadas.

---

# 22. FASE 13 — MOMENTUM SHIFT LIQUIDITY

## Objetivo

Detectar liquidez em estruturas onde highs/lows não são iguais.

## Requisitos

- sequência de pivôs;
- compressão;
- mudança de inclinação;
- falha de expansão;
- contexto direcional.

## Classificação inicial

```text
MOMENTUM_SHIFT_BSL
MOMENTUM_SHIFT_SSL
```

## Guardrail

Primeira versão em shadow, sem influência operacional.

## Critérios de aceite

- regras explícitas;
- IDs dos pivôs;
- sem uso de LLM;
- replay auditável.

---

# 23. FASE 14 — SESSION LIQUIDITY

## Objetivo

Adicionar níveis de sessão quando fizer sentido para o ativo.

## Fontes opcionais

- session high;
- session low;
- previous day high;
- previous day low;
- previous week high;
- previous week low.

## Regras

- calendário por mercado;
- timezone explícito;
- feriados;
- contrato vigente;
- não misturar sessão com cluster SMC sem source_type.

## Critérios de aceite

- sessão correta;
- timestamps corretos;
- fonte explícita;
- feature flag separada.

---

# 24. FASE 15 — CONTINUIDADE, GAP E ROLLOVER

## Objetivo

Impedir eventos artificiais.

## Configuração

```text
require_contiguous_bars
allow_cross_session_cluster
allow_cross_session_sweep
allow_cross_contract_cluster
allow_rollover_sweep
```

## Regras

- gap de sessão marcado;
- data gap marcado;
- rollover marcado;
- close-through de rollover não confirma shift;
- cluster cross-contract bloqueado por padrão.

## Critérios de aceite

- nenhum evento artificial sem flag;
- razão persistida;
- rollback possível.

---

# 25. FASE 16 — QUALIDADE E SCORING

## Objetivo

Separar validade de qualidade.

## Validade

Critérios obrigatórios:

- fonte válida;
- disponibilidade temporal;
- membros válidos;
- continuidade permitida;
- zona válida;
- tick normalizado.

## Qualidade

Possíveis fatores:

```text
member_count
member_spacing
opposite_excursion
cluster_tightness
age
scope
range_type
source_type
higher_timeframe_alignment
protected_weak_context
sweep_reaction
shift_confirmation
```

## Proibições

- não usar apenas sweep rate;
- não usar tamanho de zona sozinho;
- não hardcodar WINFUT M5.

## Critérios de aceite

- score explicável;
- configuração por ativo/timeframe;
- validade independente de qualidade.

---

# 26. FASE 17 — PROCESSAMENTO INCREMENTAL

## Objetivo

Operar candle a candle.

## Fluxo

```text
novo candle fechado
→ validar continuidade
→ receber swings disponíveis
→ receber níveis estruturais disponíveis
→ atualizar pools forming
→ confirmar novos pools
→ verificar eventos nos pools ativos
→ atualizar lifecycle
→ persistir eventos
→ atualizar overlays
→ salvar checkpoint
```

## Tarefas

- estado serializável;
- idempotência;
- checkpoint;
- restart;
- replay;
- deduplicação;
- active pool registry.

## Critérios de aceite

```text
batch == incremental
```

Incluindo:

- pools;
- membros;
- eventos;
- estados;
- timestamps.

---

# 27. FASE 18 — PERSISTÊNCIA E VERSIONAMENTO

## Tabelas/coleções sugeridas

```text
liquidity_pools_v3
liquidity_pool_members_v3
liquidity_events_v3
liquidity_engine_state_v3
dealing_ranges_v3
```

## Campos operacionais

```text
engine_version
source_version
config_hash
run_id
replay_id
created_at
updated_at
```

## Regras

- V2 preservada;
- V3 separada;
- migração reversível;
- escrita por feature flag;
- índices por asset/timeframe/status.

---

# 28. FASE 19 — OVERLAYS E DASHBOARD

## Regras visuais

### Pool ativo

- faixa da zona;
- extensão até candle atual.

### Pool swept

- linha até sweep;
- marcador no evento.

### Pool consumed

- estilo distinto.

### Pool forming

- visível apenas em modo debug/shadow.

### Disponibilidade

- origem histórica;
- confirmação;
- available index;
- earliest execution.

## Filtros

```text
BSL
SSL
ERL
IRL
EQH/EQL
STRUCTURE
TRENDLINE
MOMENTUM_SHIFT
SESSION
ACTIVE
SWEPT
CONSUMED
```

## Tooltip mínimo

```text
liquidity_id
side
source_type
range_type
role
status
outer_boundary
cluster_available_at
member_count
quality_score
event_history
```

## Critérios de aceite

- pool ativo continua até o presente;
- nenhum label antecipado;
- troca de timeframe consistente;
- V2/V3 comparáveis.

---

# 29. FASE 20 — INTEGRAÇÃO COM STRUCTURE, OB E FVG

## Structure

A Liquidity Engine deve receber:

```text
StructureStateV3
StructureLevelV3
StructureEventV3
DealingRangeV3
```

## OB

OB deve poder receber:

```text
liquidity_id
sweep_event_id
inducement_liquidity_id
```

## FVG

FVG deve poder receber:

```text
liquidity_event_id
sweep_and_shift_id
```

## Regras

- direção compatível;
- scope compatível;
- causalidade;
- IDs estáveis;
- nada por proximidade temporal isolada.

## Critérios de aceite

- vínculos rastreáveis;
- nenhum lookahead;
- nenhum módulo consome pool antes de available index.

---

# 30. FASE 21 — TESTES UNITÁRIOS

## Casos mínimos

### Equal levels

- EQH exato;
- EQH por tolerância;
- EQL exato;
- EQL por tolerância;
- terceiro membro;
- membro fora da faixa.

### Temporalidade

- segundo membro indisponível;
- cluster confirmado tardiamente;
- sweep antes da disponibilidade;
- same-bar ambiguity.

### Zona

- outer boundary BSL;
- outer boundary SSL;
- média diferente do outer boundary.

### Eventos

- first test;
- partial raid;
- wick sweep;
- close-through;
- gap-through;
- consumption;
- reclaim;
- shift confirmation.

### Structure

- weak high;
- weak low;
- protected high;
- protected low;
- ERL;
- IRL.

### Continuidade

- sessão;
- data gap;
- rollover;
- cross-contract.

### Scopes

- internal;
- swing;
- MTF.

### Persistência

- replay;
- restart;
- duplicata;
- idempotência.

---

# 31. FASE 22 — TESTES DE PROPRIEDADE

Invariantes obrigatórias:

1. `cluster_available_index >= max(member.available_index)`;
2. sweep não pode ocorrer antes de `cluster_available_index`;
3. wick sweep não pode ser close-through;
4. outer boundary BSL é o maior high do cluster;
5. outer boundary SSL é o menor low do cluster;
6. pool confirmado preserva membros;
7. pool não muda com candles futuros, exceto lifecycle;
8. ATR de confirmação permanece congelado;
9. V2 e V3 coexistem;
10. batch e incremental são equivalentes;
11. restart não duplica evento;
12. ERL/IRL exige dealing range válido;
13. structure pool exige level ID;
14. same-bar ambiguity não vira sweep confirmado automaticamente.

---

# 32. FASE 23 — REPLAY E BACKTEST SHADOW

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

- total de pools;
- BSL/SSL;
- EQH/EQL;
- structure pools;
- ERL/IRL;
- trendline;
- momentum shift;
- wick sweeps;
- close-through;
- consumption;
- reclaim;
- sweep-and-shift;
- time-to-event;
- MFE;
- MAE;
- divergência V2/V3;
- eventos antecipados pela V2;
- pools alterados por ATR futuro;
- impacto em OB/FVG;
- impacto no directional bias.

## Critérios de aceite

- zero promoção de trade;
- relatório por ativo/timeframe;
- divergências explicadas;
- replay determinístico.

---

# 33. REVALIDAÇÃO DA CALIBRAÇÃO ATR

## Objetivo

Revalidar o valor 1,15 ATR.

## Não usar apenas

```text
swept_rate
```

## Métricas obrigatórias

```text
sweep_within_N_bars
time_to_sweep
wick_sweep_rate
close_through_rate
sweep_and_shift_rate
reaction_MFE
reaction_MAE
probability_of_structure_shift
pool_survival
false_cluster_rate
```

## Comparações

- 0,10 ATR;
- 0,15 ATR;
- 0,25 ATR;
- 0,50 ATR;
- 0,75 ATR;
- 1,00 ATR;
- 1,15 ATR;
- perfis por timeframe.

## Guardrail

Nenhum valor promovido sem validação externa e amostra suficiente.

---

# 34. FASE 24 — MIGRAÇÃO CONTROLADA

## Etapa 1 — Legacy

```text
LIQUIDITY_ENGINE_MODE=legacy
```

## Etapa 2 — Shadow V3

```text
LIQUIDITY_ENGINE_MODE=shadow_v3
```

- roda V2 e V3;
- persiste V3 separadamente;
- não altera sinais.

## Etapa 3 — Overlay V3

- dashboard pode exibir V3;
- operação continua V2.

## Etapa 4 — Contextual shadow

- Structure/OB/FVG shadow consomem V3.

## Etapa 5 — promoção futura

Somente após:

- testes;
- replay;
- backtest;
- revisão humana;
- aprovação arquitetural;
- aprovação dos guardrails.

---

# 35. ROLLBACK

Criar:

```text
ROLLBACK_LIQUIDITY_ENGINE_V3.md
```

O rollback deve permitir:

1. voltar para legacy;
2. interromper escrita V3;
3. preservar dados V3;
4. restaurar overlay V2;
5. não apagar histórico;
6. preservar schema V2;
7. reprocessar shadow.

---

# 36. OBSERVABILIDADE

## Logs mínimos

```text
liquidity.pool_candidate_created
liquidity.pool_member_added
liquidity.pool_confirmed
liquidity.pool_available
liquidity.pool_tested
liquidity.partial_raid
liquidity.wick_sweep
liquidity.close_through
liquidity.consumed
liquidity.reclaimed
liquidity.shift_confirmed
liquidity.expired
liquidity.lookahead_blocked
liquidity.same_bar_ambiguous
liquidity.rollover_blocked
liquidity.replay_divergence
```

## Métricas mínimas

- pools candidate;
- pools active;
- pools swept;
- pools consumed;
- pools reclaimed;
- ERL/IRL;
- divergência V2/V3;
- eventos bloqueados por lookahead;
- same-bar ambiguities;
- tempo de processamento;
- duplicatas evitadas.

---

# 37. CONFIGURAÇÃO

Criar perfil por ativo/timeframe:

```text
price_tick
atr_period
atr_method

equal_tolerance_ticks
equal_tolerance_atr
member_tolerance_ticks
member_tolerance_atr

max_cluster_width_ticks
max_cluster_width_atr
min_members
min_bars_between_members
min_opposite_excursion_ticks
min_opposite_excursion_atr

sweep_buffer_ticks
sweep_buffer_atr
close_through_buffer_ticks
close_through_buffer_atr

max_cluster_age_bars
max_member_distance_bars
max_cluster_age_sessions

require_contiguous_bars
allow_cross_session_cluster
allow_cross_session_sweep
allow_cross_contract_cluster
allow_rollover_sweep

enable_structure_liquidity
enable_erl_irl
enable_trendline_liquidity
enable_momentum_shift_liquidity
enable_session_liquidity
enable_inducement
```

Regras:

- nada hardcoded;
- defaults documentados;
- hash persistido;
- fallback explícito;
- perfis separados por scope.

---

# 38. ARQUIVOS ESPERADOS

Estrutura sugerida:

```text
technical_engine/liquidity/
  __init__.py
  liquidity_models_v3.py
  liquidity_config_v3.py
  liquidity_input_validator.py
  equal_level_liquidity_detector.py
  structure_level_liquidity_detector.py
  range_liquidity_classifier.py
  trendline_liquidity_detector.py
  momentum_shift_liquidity_detector.py
  session_liquidity_detector.py
  liquidity_pool_registry.py
  liquidity_event_detector.py
  liquidity_state_machine.py
  liquidity_quality_scorer.py
  liquidity_engine_v3.py
  liquidity_persistence_v3.py
  liquidity_overlays_v3.py
  legacy_liquidity_adapter.py
```

Testes:

```text
tests/liquidity_v3/
  test_input_validation.py
  test_equal_level_clusters.py
  test_cluster_availability.py
  test_liquidity_zones.py
  test_wick_sweep.py
  test_close_through.py
  test_gap_through.py
  test_consumption.py
  test_reclaim.py
  test_shift_confirmation.py
  test_structure_liquidity.py
  test_erl_irl.py
  test_inducement.py
  test_trendline_liquidity.py
  test_momentum_shift_liquidity.py
  test_session_liquidity.py
  test_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_anti_lookahead.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/LIQUIDITY_ENGINE_V3.md
docs/migrations/LIQUIDITY_ENGINE_V3_MIGRATION.md
docs/operations/ROLLBACK_LIQUIDITY_ENGINE_V3.md
docs/reports/RELATORIO_FINAL_LIQUIDITY_ENGINE_V3.md
```

---

# 39. CRITÉRIOS DE ACEITE GERAIS

A implementação só pode ser considerada concluída quando:

1. nenhum pool usa ATR futuro;
2. nenhum pool usa range global no modo canônico;
3. swings respeitam `available_index`;
4. cluster só existe após membros mínimos disponíveis;
5. sweep só é monitorado após disponibilidade;
6. member tolerance e sweep buffer são separados;
7. wick sweep e close-through são distintos;
8. pool é zona completa;
9. membros não são apagados;
10. lifecycle completo funciona;
11. structure liquidity existe;
12. ERL/IRL funciona com range válido;
13. session/gap/rollover são tratados;
14. batch e incremental são equivalentes;
15. restart é idempotente;
16. overlays não antecipam eventos;
17. V2 permanece disponível;
18. rollback funciona;
19. testes passam;
20. relatório final foi entregue;
21. nenhuma promoção de trade ocorreu.

---

# 40. DEFINITION OF DONE

A Liquidity Engine V3 estará pronta quando:

- código compilar;
- testes passarem;
- cobertura do core for no mínimo 90%;
- todos os testes anti-lookahead estiverem verdes;
- batch e streaming forem equivalentes;
- V2 estiver preservada;
- V3 estiver persistida separadamente;
- dashboard comparativo estiver funcional;
- Structure/OB/FVG consumirem V3 apenas em shadow;
- rollback estiver documentado;
- relatório final estiver concluído;
- nenhum sinal de produção tiver sido alterado.

---

# 41. RELATÓRIO FINAL OBRIGATÓRIO

## 41.1. Resumo executivo

- correções realizadas;
- limitações;
- riscos;
- status final.

## 41.2. Arquivos alterados

| Arquivo | Tipo | Alteração |
|---|---|---|

## 41.3. Contratos criados

- modelos;
- enums;
- tabelas;
- DTOs;
- APIs.

## 41.4. Testes

| Suíte | Total | Passou | Falhou | Skip |
|---|---:|---:|---:|---:|

## 41.5. Anti-lookahead

Demonstrar:

- swing indisponível bloqueado;
- cluster confirmado somente após membro disponível;
- sweep bloqueado antes da disponibilidade;
- ATR congelado;
- replay parcial;
- igualdade batch/incremental.

## 41.6. Comparativo V2/V3

| Métrica | V2 | V3 | Diferença |
|---|---:|---:|---:|

## 41.7. Impacto downstream

- Structure;
- OB;
- FVG;
- directional bias;
- scanner;
- dashboard.

## 41.8. Guardrails

Confirmar:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
llm_decision_used = false
production_truth_replaced = false
```

## 41.9. Rollback

- procedimento;
- comandos;
- validação.

## 41.10. Status final

Usar uma opção:

```text
LIQUIDITY_V3_COMPLETED_SHADOW
LIQUIDITY_V3_COMPLETED_WITH_LIMITATIONS
LIQUIDITY_V3_BLOCKED
LIQUIDITY_V3_FAILED
```

---

# 42. ORDEM DE EXECUÇÃO RECOMENDADA

Executar nesta ordem:

1. auditoria;
2. baseline;
3. feature flags;
4. validação de entrada;
5. ATR causal;
6. normalização por tick;
7. equal level detector;
8. cluster availability;
9. zona completa;
10. member tolerance e sweep buffer;
11. event detector;
12. state machine;
13. structure liquidity;
14. ERL/IRL;
15. inducement;
16. trendline liquidity;
17. momentum shift liquidity;
18. session liquidity;
19. gap/rollover;
20. quality scoring;
21. processamento incremental;
22. persistência;
23. overlays;
24. integração Structure/OB/FVG;
25. testes;
26. replay shadow;
27. recalibração ATR;
28. relatório final.

Não integrar OB/FVG antes de os testes de disponibilidade, eventos e anti-lookahead estarem verdes.

---

# 43. REGRAS PARA A IA DE CÓDIGO

1. Não usar ATR futuro.
2. Não usar range global no modo canônico.
3. Não consumir swing antes de `available_index`.
4. Não criar pool antes do segundo membro disponível.
5. Não detectar sweep no passado.
6. Não misturar member tolerance com sweep buffer.
7. Não usar média como único nível.
8. Não apagar membros.
9. Não misturar wick sweep com close-through.
10. Não considerar CHOCH/BOS sem Structure Engine.
11. Não classificar ERL/IRL sem dealing range.
12. Não hardcodar WINFUT.
13. Não substituir V2 diretamente.
14. Não promover trade.
15. Não criar fallback silencioso.
16. Não declarar concluído sem replay.
17. Não usar sweep rate isolado como qualidade.
18. Em ambiguidade, preservar o evento e marcar `AMBIGUOUS`.
19. Toda decisão deve ser explicável por campos persistidos.
20. Toda alteração de contrato deve ter migração.

---

# 44. RESULTADO ESPERADO

Ao final, a Liquidity Engine V3 deverá responder com precisão:

- onde está a liquidez;
- se é BSL ou SSL;
- qual é a zona real;
- qual é o outer boundary;
- qual é a fonte;
- quando o pool foi confirmado;
- quando ficou disponível;
- se é EQH/EQL, structure level, ERL, IRL, trendline ou momentum shift;
- qual é o role do pool;
- se foi testado;
- se houve partial raid;
- se houve wick sweep;
- se houve close-through;
- se foi consumido;
- se foi reclaimed;
- se houve sweep-and-shift;
- qual Structure Event está relacionado;
- qual OB/FVG está relacionado;
- se houve sessão, gap ou rollover;
- qual foi a penetração;
- qual foi a reação;
- por que o pool recebeu determinada qualidade.

A Liquidity Engine V3 deve ser causal, incremental, imutável, explicável e segura para servir como fonte canônica de liquidez do SMC Trader System 7.0.
