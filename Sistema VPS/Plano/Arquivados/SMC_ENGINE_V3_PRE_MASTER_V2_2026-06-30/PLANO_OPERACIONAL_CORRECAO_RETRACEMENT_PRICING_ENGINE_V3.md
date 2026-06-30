# PLANO OPERACIONAL COMPLETO — CORREÇÃO DA RETRACEMENT & PRICING ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Módulo:** Retracements / Dealing Range / Premium–Equilibrium–Discount  
**Versão-alvo:** Retracement & Pricing Engine V3  
**Escopo:** correção matemática, temporal, estrutural, visual e operacional da engine de retrações  
**Modo de execução:** incremental, causal, anti-lookahead, auditável e shadow-only até aprovação  
**Dependências obrigatórias:** Swing Engine V3 e Structure Engine V3  
**Objetivo:** substituir a implementação retrospectiva simplificada por uma engine canônica de dealing ranges e pricing model SMC, capaz de calcular retração, posição no range, Premium/Equilibrium/Discount, lifecycle da perna, revisões de endpoint e contexto MTF sem antecipação temporal.

---

# 1. OBJETIVO GERAL

Construir uma Retracement & Pricing Engine V3 que:

- consuma swings somente após `available_index`;
- selecione o dealing range pela estrutura vigente, e não apenas pelos dois últimos pivôs;
- diferencie pivô fractal, swing canônico, swing estrutural e perna estrutural;
- elimine o deslocamento artificial de uma posição;
- separe retracement percentage de range position;
- calcule corretamente os níveis de retração;
- implemente Premium, Equilibrium e Discount como saída canônica;
- diferencie retração por pavio, corpo e fechamento;
- trate extensões, teste da origem e invalidação;
- mantenha lifecycle do range;
- suporte revisões de endpoint sem reescrever o passado;
- opere separadamente em scopes `INTERNAL` e `SWING`;
- exponha contexto MTF;
- integre OB, FVG, Liquidity e Structure por IDs estáveis;
- funcione em batch, replay e streaming com o mesmo resultado;
- permaneça shadow-only até validação completa.

---

# 2. PROBLEMAS DO ESTADO ATUAL

A implementação atual possui os seguintes problemas:

1. usa swings no `origin_index`, ignorando `available_index`;
2. troca a perna antes de o novo swing estar operacionalmente disponível;
3. usa os dois swings mais recentes, mesmo quando não representam o dealing range correto;
4. mistura confirmação fractal com validade estrutural;
5. aplica um roll final incorreto que duplica o primeiro registro e remove o último;
6. corrompe o alinhamento entre posição da lista e `candle_index`;
7. desenha níveis Fibonacci invertidos em relação ao percentual calculado;
8. mistura `retracement_from_endpoint` com `range_position`;
9. não retorna Premium/Equilibrium/Discount;
10. trata pavio intrabar como “current retracement”;
11. mistura wick, body e close retracement;
12. não trata retracement negativo;
13. não trata retracement acima de 100%;
14. não possui lifecycle do range;
15. reseta deepest retracement no momento errado;
16. usa regra arbitrária de três mudanças de direção;
17. usa campo `direction` semanticamente ambíguo;
18. não diferencia orientação da perna e orientação da retração;
19. não diferencia `INTERNAL` e `SWING`;
20. não integra timeframe superior;
21. não possui vínculo com Structure Event, Impulse Leg, protected/weak levels;
22. não trata swings superseded corretamente;
23. não registra revisão do endpoint;
24. não possui filtro mínimo de significância;
25. não valida corretamente top, bottom e range;
26. recebe timestamps, mas não os persiste;
27. não preenche `raw`;
28. `total_retracements` conta candles, não ranges;
29. overlay não possui âncoras temporais reais;
30. overlay usa leitura potencialmente obsoleta;
31. não representa zonas Premium/Equilibrium/Discount;
32. não trata sessão, data gap e rollover;
33. não possui processamento incremental;
34. não possui persistência V3 separada.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Anti-lookahead absoluto

Nenhum range pode ser criado, atualizado ou consumido antes da disponibilidade dos swings e eventos estruturais que o definem.

Campos temporais mínimos:

```text
origin_swing_available_index
endpoint_swing_available_index
range_confirmed_index
range_available_index
earliest_execution_index
```

## 3.2. Separação entre posição e retração

A engine deve calcular grandezas distintas:

```text
range_position_from_low_pct
range_position_from_high_pct
retracement_from_endpoint_pct
```

## 3.3. Dealing range estrutural

O range canônico deve vir de:

- Structure Engine V3;
- Structure Leg;
- BOS/CHOCH;
- protected/weak levels;
- active dealing range.

Não apenas do último par de pivôs.

## 3.4. Histórico imutável

Revisões de endpoint não podem apagar o range anterior.

Devem gerar revisão explícita.

## 3.5. Pricing model canônico

A saída principal deve ser:

```text
PREMIUM
EQUILIBRIUM
DISCOUNT
```

Níveis Fibonacci adicionais devem ser opcionais.

## 3.6. Shadow-only

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

A engine V3 deve contemplar:

- dealing range candidate;
- dealing range confirmed;
- active dealing range;
- revised dealing range;
- superseded dealing range;
- invalidated dealing range;
- internal dealing range;
- swing dealing range;
- higher timeframe dealing range;
- bullish impulse range;
- bearish impulse range;
- wick retracement;
- body retracement;
- close retracement;
- deepest wick retracement;
- deepest close retracement;
- range position;
- Premium;
- Equilibrium;
- Discount;
- extension beyond endpoint;
- test of origin;
- breach of origin;
- invalidation;
- endpoint revision;
- MTF pricing alignment;
- integração com OB/FVG/Liquidity.

---

# 5. FORA DO ESCOPO DESTA FASE

Não implementar nesta fase:

- entrada automática;
- stop final;
- take profit final;
- promoção de sinal;
- otimização genética;
- alteração do Risk Management;
- substituição direta da V2;
- remoção de dados históricos;
- uso de LLM para escolher ranges.

---

# 6. ARQUITETURA-ALVO

A engine deve ser dividida nos seguintes componentes.

## 6.1. `RetracementInputValidator`

Responsável por:

- validar OHLC;
- validar timestamps;
- validar swings V3;
- validar Structure Events;
- validar Structure Legs;
- validar dealing ranges;
- validar ATR e price tick;
- validar continuidade.

## 6.2. `DealingRangeResolver`

Responsável por escolher o range correto.

Fontes possíveis:

```text
STRUCTURE_LEG
BOS_IMPULSE
PROTECTED_TO_WEAK
PIVOT_TO_PIVOT
HIGHER_TIMEFRAME_RANGE
```

## 6.3. `RangeLifecycleManager`

Responsável por:

- criar;
- confirmar;
- ativar;
- revisar;
- superseder;
- invalidar;
- completar.

## 6.4. `RetracementCalculator`

Responsável por calcular:

- wick retracement;
- body retracement;
- close retracement;
- deepest values;
- range position.

## 6.5. `PricingModelClassifier`

Responsável por classificar:

```text
PREMIUM
EQUILIBRIUM
DISCOUNT
```

## 6.6. `RangeRevisionManager`

Responsável por mudanças no endpoint.

## 6.7. `RetracementEventDetector`

Responsável por eventos:

- equilibrium reached;
- deep retracement;
- origin tested;
- origin breached;
- extension;
- invalidation.

## 6.8. `RetracementPersistenceV3`

Responsável por persistência, versionamento e idempotência.

## 6.9. `RetracementOverlayAdapterV3`

Responsável por zonas e linhas temporais corretas.

## 6.10. `LegacyRetracementAdapter`

Responsável por paridade upstream.

---

# 7. CONTRATOS DE DADOS

## 7.1. Modelo `DealingRangeV3`

Campos mínimos:

```text
dealing_range_id
asset
timeframe
scope

range_type
status

impulse_direction
retracement_direction
structure_direction

origin_swing_id
endpoint_swing_id
impulse_leg_id
structure_event_id
protected_level_id
weak_level_id
parent_range_id
higher_timeframe_range_id

origin_index
origin_at
origin_available_index
origin_available_at

endpoint_origin_index
endpoint_origin_at
endpoint_available_index
endpoint_available_at

range_confirmed_index
range_confirmed_at
range_available_index
range_available_at

earliest_execution_index
earliest_execution_at

top
bottom
equilibrium

range_pts
range_ticks
range_atr
leg_bars

is_cross_session
is_rollover_affected
is_data_gap_affected
continuity_status

engine_version
config_hash
run_id
replay_id
raw
```

## 7.2. Modelo `RetracementSampleV3`

Campos mínimos:

```text
sample_id
dealing_range_id
candle_index
candle_at

wick_retracement_pct
body_retracement_pct
close_retracement_pct

range_position_from_low_pct
range_position_from_high_pct

raw_retracement_pct
normalized_retracement_pct

pricing_zone
equilibrium_distance_pts
equilibrium_distance_ticks
equilibrium_distance_atr

is_extension
is_origin_test
is_origin_breach

engine_version
config_hash
raw
```

## 7.3. Modelo `RetracementEventV3`

Campos mínimos:

```text
event_id
dealing_range_id
event_type
status

event_index
event_at
confirmed_index
available_index
earliest_execution_index

price
retracement_pct
range_position_pct
pricing_zone

previous_range_status
new_range_status

structure_event_id
ob_id
fvg_id
liquidity_id

engine_version
config_hash
raw
```

## 7.4. Modelo `RangeRevisionV3`

Campos mínimos:

```text
revision_id
dealing_range_id
previous_endpoint_swing_id
new_endpoint_swing_id
previous_top
previous_bottom
new_top
new_bottom
revision_index
revision_at
reason
status
engine_version
```

## 7.5. Modelo `RetracementEngineStateV3`

Campos mínimos:

```text
state_id
asset
timeframe
scope
last_processed_index
last_processed_at
active_range_ids
pending_range_ids
version
config_hash
```

---

# 8. ENUMS OBRIGATÓRIOS

## 8.1. Range type

```text
PIVOT_TO_PIVOT
INTERNAL_STRUCTURE_RANGE
SWING_STRUCTURE_RANGE
BOS_IMPULSE_RANGE
ACTIVE_DEALING_RANGE
HIGHER_TIMEFRAME_RANGE
```

## 8.2. Range status

```text
CANDIDATE
FORMING
CONFIRMED
ACTIVE_RETRACEMENT
REVISED
SUPERSEDED
INVALIDATED
COMPLETED
```

## 8.3. Pricing zone

```text
PREMIUM
EQUILIBRIUM
DISCOUNT
OUTSIDE_RANGE
UNKNOWN
```

## 8.4. Event type

```text
RANGE_CONFIRMED
RANGE_ACTIVATED
EQUILIBRIUM_REACHED
PREMIUM_REACHED
DISCOUNT_REACHED
DEEP_RETRACEMENT
ORIGIN_TESTED
ORIGIN_BREACHED
ENDPOINT_EXTENDED
RANGE_REVISED
RANGE_INVALIDATED
RANGE_COMPLETED
```

## 8.5. Scope

```text
INTERNAL
SWING
```

## 8.6. Direction

```text
BULLISH
BEARISH
NEUTRAL
```

## 8.7. Continuity

```text
CONTINUOUS
SESSION_BOUNDARY
DATA_GAP
ROLLOVER
UNKNOWN
```

---

# 9. FASE 0 — AUDITORIA E BASELINE

## Objetivo

Mapear dependências da engine atual.

## Tarefas

1. localizar usos de:
   - `calculate_retracements`;
   - `RetracementV2`;
   - `current_retracement_pct`;
   - `deepest_retracement_pct`;
   - `swing_top`;
   - `swing_bottom`;
   - `swing_range`;
   - `retracements_to_visual_overlays`.

2. mapear consumidores:
   - Structure;
   - OB;
   - FVG;
   - Liquidity;
   - directional bias;
   - contextual;
   - scanner;
   - dashboard;
   - backtest;
   - persistência;
   - API.

3. congelar baseline:
   - ranges detectados;
   - current retracement;
   - deepest retracement;
   - últimos overlays;
   - ativos;
   - timeframes;
   - hashes.

4. criar:

```text
AUDITORIA_DEPENDENCIAS_RETRACEMENT_ENGINE_V2.md
```

5. criar feature flags:

```text
RETRACEMENT_ENGINE_MODE=legacy|shadow_v3|v3
RETRACEMENT_V3_WRITE_ENABLED=false
RETRACEMENT_V3_SIGNAL_ENABLED=false
RETRACEMENT_V3_OVERLAY_ENABLED=false
RETRACEMENT_V3_MTF_ENABLED=false
```

## Critérios de aceite

- consumidores mapeados;
- baseline reproduzível;
- nenhuma alteração em produção;
- feature flags testadas.

---

# 10. FASE 1 — VALIDAÇÃO DE ENTRADA

## Objetivo

Impedir cálculo com dados inválidos.

## Validações obrigatórias

### OHLC

- colunas presentes;
- valores finitos;
- high/low consistentes;
- timestamps crescentes;
- ausência de duplicatas.

### Swings

- contrato Swing V3;
- IDs;
- `available_index`;
- `scope`;
- status;
- preço válido.

### Structure

- Structure Leg;
- Structure Event;
- protected/weak levels;
- state vigente.

### Configuração

- `price_tick > 0`;
- ATR disponível;
- thresholds válidos;
- ranges mínimos válidos.

## Critérios de aceite

- erro explícito;
- nenhum fallback silencioso;
- logs com índice e causa.

---

# 11. FASE 2 — REMOÇÃO DO ROLL ARTIFICIAL

## Objetivo

Eliminar a corrupção de índices causada pelo shift final.

## Tarefas

1. remover no modo canônico:

```text
results = [results[0]] + results[:-1]
```

2. preservar modo legacy separadamente;
3. garantir que:

```text
records[i].candle_index == i
```

4. adicionar testes:
   - primeiro candle;
   - último candle;
   - série curta;
   - alinhamento completo.

## Critérios de aceite

- nenhum registro duplicado;
- último candle preservado;
- índice e posição alinhados;
- overlay usa valor realmente atual.

---

# 12. FASE 3 — DISPONIBILIDADE DOS SWINGS

## Objetivo

Eliminar lookahead herdado.

## Regras

- não consumir swing no `origin_index`;
- consumir somente após `available_index`;
- endpoint não pode alterar o range antes de sua disponibilidade;
- `earliest_execution_index` deve ser respeitado.

## Tarefas

1. substituir DataFrame simplificado por Swing V3;
2. bloquear swings `PENDING`;
3. aceitar apenas status configuráveis:
   - `CANONICAL`;
   - `LOCKED`;
   - `STRUCTURALLY_CONFIRMED`.

## Critérios de aceite

- replay parcial não antecipa ranges;
- endpoint só entra após disponibilidade;
- nenhum range muda retroativamente.

---

# 13. FASE 4 — DEALING RANGE RESOLVER

## Objetivo

Selecionar o range correto.

## Prioridade de fontes

Recomendada:

```text
1. ACTIVE_DEALING_RANGE da Structure Engine
2. BOS_IMPULSE_RANGE
3. PROTECTED_TO_WEAK range
4. SWING_STRUCTURE_RANGE
5. INTERNAL_STRUCTURE_RANGE
6. PIVOT_TO_PIVOT fallback shadow
```

## Regras

- range estrutural prevalece sobre micro pivô;
- scope preservado;
- direção compatível;
- origem e endpoint rastreáveis;
- fallback marcado como baixa confiança.

## Critérios de aceite

- nenhum micro swing sobrescreve range swing;
- cada range possui source explícita;
- IDs estáveis.

---

# 14. FASE 5 — RANGE CONFIRMATION E AVAILABILITY

## Objetivo

Definir quando o range passa a existir.

## Regras

```text
range_confirmed_index =
max(origin_swing.available_index,
    endpoint_swing.available_index,
    structure_event.available_index)
```

Quando não houver Structure Event:

```text
range_confirmed_index =
max(origin_swing.available_index,
    endpoint_swing.available_index)
```

```text
range_available_index =
range_confirmed_index
```

Para execução conservadora:

```text
earliest_execution_index =
range_available_index + 1
```

## Critérios de aceite

- nenhum sample antes da disponibilidade;
- timestamps corretos;
- range não existe retroativamente.

---

# 15. FASE 6 — VALIDAÇÃO GEOMÉTRICA DO RANGE

## Objetivo

Garantir ranges válidos.

## Regras

Exigir:

```text
isfinite(top)
isfinite(bottom)
top > bottom
range_pts > 0
range_ticks >= min_range_ticks
range_atr >= min_range_atr
leg_bars >= min_leg_bars
```

## Campos

```text
range_pts
range_ticks
range_atr
leg_bars
```

## Critérios de aceite

- range negativo rejeitado;
- NaN/infinito rejeitado;
- range irrelevante não promovido.

---

# 16. FASE 7 — CÁLCULO CORRETO DE RETRACEMENT

## Objetivo

Separar métricas.

## 16.1. Bullish impulse

```text
bottom = origin
top = endpoint
```

### Wick retracement

```text
(top - low_atual) / range * 100
```

### Close retracement

```text
(top - close_atual) / range * 100
```

### Body retracement

Definir de forma explícita, por exemplo:

```text
(top - min(open, close)) / range * 100
```

## 16.2. Bearish impulse

```text
top = origin
bottom = endpoint
```

### Wick retracement

```text
(high_atual - bottom) / range * 100
```

### Close retracement

```text
(close_atual - bottom) / range * 100
```

### Body retracement

```text
(max(open, close) - bottom) / range * 100
```

## Critérios de aceite

- wick, body e close separados;
- fórmulas unitariamente testadas;
- sem arredondamento prematuro.

---

# 17. FASE 8 — RANGE POSITION

## Objetivo

Separar posição absoluta da profundidade de retração.

## Fórmulas

```text
range_position_from_low_pct =
(price - bottom) / range * 100
```

```text
range_position_from_high_pct =
(top - price) / range * 100
```

## Invariante

Dentro do range:

```text
range_position_from_low_pct
+
range_position_from_high_pct
=
100
```

## Critérios de aceite

- range position não é rotulado como retracement;
- invariantes testadas;
- pricing model usa posição no range.

---

# 18. FASE 9 — PREMIUM / EQUILIBRIUM / DISCOUNT

## Objetivo

Implementar o pricing model canônico.

## Configuração

```text
equilibrium_tolerance_pct
equilibrium_tolerance_ticks
equilibrium_tolerance_atr
```

## Classificação baseada em posição from low

```text
position < 50% - tolerance
→ DISCOUNT

position dentro da banda de 50%
→ EQUILIBRIUM

position > 50% + tolerance
→ PREMIUM
```

## Regras

- geometricamente igual para bullish e bearish;
- interpretação comercial depende do contexto;
- pricing zone não gera decisão automática.

## Critérios de aceite

- zonas consistentes;
- equilíbrio em 50%;
- tolerância configurável;
- MTF possível.

---

# 19. FASE 10 — FIBONACCI CORRETO

## Objetivo

Corrigir a inversão visual.

## Separação obrigatória

### Pricing model canônico

```text
0%
50%
100%
```

### Fibonacci opcional

```text
23.6%
38.2%
50%
61.8%
78.6%
```

## Preço de retracement bullish

```text
level_price =
top - ratio * range
```

## Preço de retracement bearish

```text
level_price =
bottom + ratio * range
```

## Critérios de aceite

- 61.8% aparece no preço correto;
- label corresponde ao cálculo;
- Fibonacci opcional desligado por padrão.

---

# 20. FASE 11 — EXTENSION, ORIGIN TEST E INVALIDATION

## Objetivo

Tratar valores fora de 0–100%.

## Estados

### Retracement < 0%

```text
ENDPOINT_EXTENSION
NEW_EXTREME
```

### Retracement = 100%

```text
ORIGIN_TESTED
```

### Retracement > 100%

```text
ORIGIN_BREACHED
RANGE_INVALIDATED
```

## Campos

```text
raw_retracement_pct
normalized_retracement_pct
is_extension
is_origin_test
is_origin_breach
```

## Regras

- não truncar silenciosamente;
- não manter range ativo após invalidação sem regra explícita;
- gerar evento.

## Critérios de aceite

- estados corretos;
- lifecycle atualizado;
- histórico preservado.

---

# 21. FASE 12 — LIFECYCLE DO RANGE

## Fluxo

```text
CANDIDATE
→ FORMING
→ CONFIRMED
→ ACTIVE_RETRACEMENT
```

Transições:

```text
ACTIVE_RETRACEMENT
├─ REVISED
├─ SUPERSEDED
├─ INVALIDATED
└─ COMPLETED
```

Eventos:

```text
EQUILIBRIUM_REACHED
PREMIUM_REACHED
DISCOUNT_REACHED
DEEP_RETRACEMENT
ORIGIN_TESTED
ORIGIN_BREACHED
```

## Critérios de aceite

- transições determinísticas;
- evento para cada mudança;
- idempotência.

---

# 22. FASE 13 — DEEPEST RETRACEMENT

## Objetivo

Corrigir o acompanhamento do máximo.

## Campos

```text
deepest_wick_retracement_pct
deepest_close_retracement_pct
deepest_body_retracement_pct

deepest_wick_index
deepest_close_index
deepest_body_index
```

## Regras

- reset somente ao confirmar novo range;
- não resetar no origin index futuro;
- revisão de endpoint deve gerar revisão histórica, não reescrita silenciosa.

## Critérios de aceite

- máximos coerentes;
- índices preservados;
- replay incremental equivalente.

---

# 23. FASE 14 — RANGE REVISION

## Objetivo

Tratar endpoint superseded.

## Exemplo

```text
low
→ high A
→ retração
→ high B maior
```

## Regras

- high A permanece histórico;
- range original recebe `REVISED` ou `SUPERSEDED`;
- high B cria nova versão;
- revisão só ocorre no `available_index` de B;
- deepest anterior não é reescrito sem trilha.

## Campos

```text
revision_id
previous_endpoint_swing_id
new_endpoint_swing_id
revision_index
revision_at
reason
```

## Critérios de aceite

- sem repaint silencioso;
- histórico de versões;
- Structure linkage preservado.

---

# 24. FASE 15 — INTERNAL, SWING E MTF

## Objetivo

Manter múltiplos pricing models simultâneos.

## Scopes

```text
INTERNAL
SWING
```

## MTF

Expor:

```text
pricing_zone_internal
pricing_zone_swing
pricing_zone_higher_timeframe
```

## Regras

- internal não substitui swing;
- MTF não mistura ranges;
- cada range possui parent/HTF IDs.

## Critérios de aceite

- múltiplos ranges coexistem;
- dashboard filtra;
- contexto MTF disponível.

---

# 25. FASE 16 — CONTEXTO COMERCIAL

## Objetivo

Expor alinhamento sem gerar ordem.

## Exemplos

### Contexto bullish

```text
DISCOUNT
→ localização favorável para estudo de compra
```

### Contexto bearish

```text
PREMIUM
→ localização favorável para estudo de venda
```

## Campos

```text
context_alignment
trade_location_quality
bias_alignment
```

## Guardrail

Não transformar em COMPRA/VENDA automaticamente.

## Critérios de aceite

- apenas classificação contextual;
- nenhuma promoção de trade.

---

# 26. FASE 17 — INTEGRAÇÃO COM OB, FVG E LIQUIDITY

## OB

Cada OB deve poder receber:

```text
dealing_range_id
pricing_zone_at_origin
pricing_zone_at_entry
```

## FVG

Cada FVG deve poder receber:

```text
dealing_range_id
pricing_zone
range_position_pct
```

## Liquidity

Cada pool deve poder receber:

```text
dealing_range_id
range_type
pricing_zone
```

## Regras

- IDs estáveis;
- causalidade;
- scope compatível;
- nenhum vínculo por proximidade temporal isolada.

---

# 27. FASE 18 — SESSÃO, DATA GAP E ROLLOVER

## Objetivo

Marcar ranges afetados por descontinuidade.

## Configuração

```text
allow_cross_session_range
allow_cross_contract_range
allow_rollover_range
require_contiguous_bars
```

## Campos

```text
is_cross_session
is_data_gap_affected
is_rollover_affected
continuity_status
```

## Regras

- rollover não pode criar dealing range canônico por padrão;
- gap deve ser marcado;
- range cross-contract bloqueado por padrão.

## Critérios de aceite

- nenhum range artificial sem flag;
- razão persistida.

---

# 28. FASE 19 — PROCESSAMENTO INCREMENTAL

## Objetivo

Usar o mesmo core em streaming e batch.

## Fluxo

```text
novo candle fechado
→ validar continuidade
→ receber swings disponíveis
→ receber Structure Legs disponíveis
→ criar/revisar ranges
→ calcular samples
→ atualizar deepest
→ classificar pricing zone
→ detectar eventos
→ persistir
→ salvar checkpoint
```

## Critérios de aceite

```text
batch == incremental
```

Incluindo:

- ranges;
- revisões;
- samples;
- eventos;
- deepest;
- status.

---

# 29. FASE 20 — PERSISTÊNCIA E VERSIONAMENTO

## Tabelas/coleções sugeridas

```text
dealing_ranges_v3
retracement_samples_v3
retracement_events_v3
range_revisions_v3
retracement_engine_state_v3
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
- escrita por feature flag;
- migração reversível.

---

# 30. FASE 21 — OVERLAYS E DASHBOARD

## Visual canônico

Desenhar:

```text
PREMIUM ZONE
EQUILIBRIUM BAND
DISCOUNT ZONE
```

## Linhas

```text
0%
50%
100%
```

Fibonacci genérico opcional.

## Âncoras temporais

```text
x0 = range origin
x1 = current candle ou invalidation/completion
```

## Tooltip mínimo

```text
dealing_range_id
range_type
scope
origin_swing_id
endpoint_swing_id
range_available_at
top
bottom
equilibrium
pricing_zone
wick_retracement
close_retracement
deepest_retracement
status
```

## Critérios de aceite

- nenhum overlay antes da disponibilidade;
- zonas corretas;
- range ativo se estende até o presente;
- V2/V3 comparáveis.

---

# 31. FASE 22 — TESTES UNITÁRIOS

## Casos mínimos

### Fórmulas

- bullish wick;
- bullish close;
- bearish wick;
- bearish close;
- body retracement;
- range position.

### Pricing

- premium;
- equilibrium;
- discount;
- tolerance band.

### Temporalidade

- swing indisponível;
- endpoint disponível tardiamente;
- range unavailable;
- earliest execution.

### Inversão Fibonacci

- bullish 61.8%;
- bearish 61.8%;
- 0/50/100.

### Lifecycle

- active;
- revised;
- superseded;
- invalidated;
- completed.

### Fora do range

- extension;
- origin test;
- origin breach.

### Scopes

- internal;
- swing;
- MTF.

### Continuidade

- sessão;
- data gap;
- rollover.

### Persistência

- replay;
- restart;
- idempotência.

---

# 32. FASE 23 — TESTES DE PROPRIEDADE

Invariantes obrigatórias:

1. `range_available_index >= endpoint_swing.available_index`;
2. nenhum sample antes da disponibilidade;
3. `top > bottom`;
4. `range_pts > 0`;
5. dentro do range:
   - `position_from_low + position_from_high = 100`;
6. bullish:
   - top = 0% retracement;
   - bottom = 100%;
7. bearish:
   - bottom = 0%;
   - top = 100%;
8. Fibonacci 61.8% deve coincidir com a fórmula de retracement;
9. range revisado não apaga versão anterior;
10. batch e incremental são equivalentes;
11. restart não duplica eventos;
12. internal não substitui swing;
13. origin breach invalida range conforme regra.

---

# 33. FASE 24 — REPLAY E BACKTEST SHADOW

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

- total de ranges;
- ranges internal/swing;
- ranges revised;
- ranges invalidated;
- distribuição Premium/Equilibrium/Discount;
- wick versus close retracement;
- deepest retracement;
- origin breach rate;
- divergência V2/V3;
- ranges antecipados pela V2;
- impacto em OB;
- impacto em FVG;
- impacto em Liquidity;
- impacto no directional bias.

## Critérios de aceite

- zero promoção de trade;
- relatório por ativo/timeframe;
- divergências explicadas;
- replay determinístico.

---

# 34. FASE 25 — MIGRAÇÃO CONTROLADA

## Etapa 1

```text
RETRACEMENT_ENGINE_MODE=legacy
```

## Etapa 2

```text
RETRACEMENT_ENGINE_MODE=shadow_v3
```

- V2 e V3 simultâneas;
- persistência separada;
- sem influência em sinais.

## Etapa 3

- overlay V3 opcional;
- operação segue V2.

## Etapa 4

- OB/FVG/Liquidity shadow consomem dealing range V3.

## Etapa 5

Promoção futura somente após:

- testes;
- replay;
- backtest;
- revisão humana;
- aprovação arquitetural.

---

# 35. ROLLBACK

Criar:

```text
ROLLBACK_RETRACEMENT_ENGINE_V3.md
```

O rollback deve permitir:

1. voltar para legacy;
2. interromper escrita V3;
3. preservar dados V3;
4. restaurar overlay V2;
5. não apagar histórico;
6. preservar schema antigo.

---

# 36. OBSERVABILIDADE

## Logs mínimos

```text
retracement.range_candidate_created
retracement.range_confirmed
retracement.range_available
retracement.range_revised
retracement.range_superseded
retracement.sample_calculated
retracement.equilibrium_reached
retracement.deep_retracement
retracement.origin_tested
retracement.origin_breached
retracement.range_invalidated
retracement.lookahead_blocked
retracement.rollover_blocked
retracement.replay_divergence
```

## Métricas mínimas

- ranges active;
- ranges revised;
- ranges invalidated;
- pricing zone distribution;
- wick/close deepest;
- origin breaches;
- bloqueios por lookahead;
- divergência V2/V3;
- tempo de processamento;
- duplicatas evitadas.

---

# 37. CONFIGURAÇÃO

Criar perfil por ativo/timeframe:

```text
price_tick
atr_period
atr_method

min_range_ticks
min_range_atr
min_leg_bars
min_impulse_score

equilibrium_tolerance_pct
equilibrium_tolerance_ticks
equilibrium_tolerance_atr

earliest_execution_offset

allow_cross_session_range
allow_cross_contract_range
allow_rollover_range
require_contiguous_bars

enable_internal_range
enable_swing_range
enable_mtf_range
enable_generic_fibonacci

accepted_swing_statuses
range_source_priority
```

Regras:

- nada hardcoded;
- defaults documentados;
- hash persistido;
- fallback explícito.

---

# 38. ARQUIVOS ESPERADOS

Estrutura sugerida:

```text
technical_engine/retracements/
  __init__.py
  retracement_models_v3.py
  retracement_config_v3.py
  retracement_input_validator.py
  dealing_range_resolver.py
  range_lifecycle_manager.py
  retracement_calculator.py
  pricing_model_classifier.py
  range_revision_manager.py
  retracement_event_detector.py
  retracement_engine_v3.py
  retracement_persistence_v3.py
  retracement_overlays_v3.py
  legacy_retracement_adapter.py
```

Testes:

```text
tests/retracements_v3/
  test_input_validation.py
  test_range_resolver.py
  test_range_availability.py
  test_bullish_retracement.py
  test_bearish_retracement.py
  test_range_position.py
  test_pricing_model.py
  test_fibonacci_levels.py
  test_extension.py
  test_origin_test.py
  test_origin_breach.py
  test_range_revision.py
  test_internal_scope.py
  test_swing_scope.py
  test_mtf.py
  test_session_gap_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_anti_lookahead.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/RETRACEMENT_ENGINE_V3.md
docs/migrations/RETRACEMENT_ENGINE_V3_MIGRATION.md
docs/operations/ROLLBACK_RETRACEMENT_ENGINE_V3.md
docs/reports/RELATORIO_FINAL_RETRACEMENT_ENGINE_V3.md
```

---

# 39. CRITÉRIOS DE ACEITE GERAIS

A implementação só pode ser considerada concluída quando:

1. nenhum swing é usado antes de `available_index`;
2. o roll artificial foi removido;
3. os registros estão alinhados ao candle;
4. dealing range vem da estrutura;
5. retracement e range position estão separados;
6. Fibonacci está matematicamente correto;
7. Premium/Equilibrium/Discount funciona;
8. wick/body/close estão separados;
9. extensão e origin breach são tratados;
10. range lifecycle funciona;
11. range revision preserva histórico;
12. internal e swing coexistem;
13. MTF está disponível;
14. sessão/gap/rollover são tratados;
15. batch e incremental são equivalentes;
16. restart é idempotente;
17. overlays não antecipam;
18. V2 permanece disponível;
19. rollback funciona;
20. testes passam;
21. relatório final foi entregue;
22. nenhuma promoção de trade ocorreu.

---

# 40. DEFINITION OF DONE

A Retracement & Pricing Engine V3 estará pronta quando:

- código compilar;
- testes passarem;
- cobertura do core for no mínimo 90%;
- testes anti-lookahead estiverem verdes;
- batch e streaming forem equivalentes;
- V2 estiver preservada;
- V3 estiver persistida separadamente;
- dashboard comparativo estiver funcional;
- OB/FVG/Liquidity consumirem V3 apenas em shadow;
- rollback estiver documentado e testado;
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
- range confirmado somente após endpoint disponível;
- sample bloqueado antes da disponibilidade;
- replay parcial;
- igualdade batch/incremental.

## 41.6. Comparativo V2/V3

| Métrica | V2 | V3 | Diferença |
|---|---:|---:|---:|

## 41.7. Impacto downstream

- Structure;
- OB;
- FVG;
- Liquidity;
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
RETRACEMENT_V3_COMPLETED_SHADOW
RETRACEMENT_V3_COMPLETED_WITH_LIMITATIONS
RETRACEMENT_V3_BLOCKED
RETRACEMENT_V3_FAILED
```

---

# 42. ORDEM DE EXECUÇÃO RECOMENDADA

Executar nesta ordem:

1. auditoria;
2. baseline;
3. feature flags;
4. validação de entrada;
5. remoção do roll;
6. integração com Swing V3;
7. dealing range resolver;
8. confirmação e disponibilidade;
9. validação geométrica;
10. cálculo de retracement;
11. range position;
12. Premium/Equilibrium/Discount;
13. Fibonacci correto;
14. extension/origin test/origin breach;
15. lifecycle;
16. deepest retracement;
17. range revision;
18. internal/swing/MTF;
19. contexto comercial;
20. integração OB/FVG/Liquidity;
21. sessão/gap/rollover;
22. processamento incremental;
23. persistência;
24. overlays;
25. testes;
26. replay shadow;
27. relatório final.

Não integrar downstream antes de os testes de disponibilidade, fórmulas e anti-lookahead estarem verdes.

---

# 43. REGRAS PARA A IA DE CÓDIGO

1. Não usar swings no origin index.
2. Não manter o roll artificial.
3. Não misturar retracement com range position.
4. Não desenhar Fibonacci invertido.
5. Não usar dois pivôs recentes como verdade estrutural sem resolver o range.
6. Não promover range antes da disponibilidade.
7. Não apagar ranges revisados.
8. Não tratar wick como close.
9. Não truncar valores fora de 0–100 sem evento.
10. Não hardcodar WINFUT.
11. Não substituir V2 diretamente.
12. Não promover trade.
13. Não criar fallback silencioso.
14. Não declarar concluído sem replay.
15. Não omitir timestamps.
16. Em ambiguidade, preservar o range e marcar `UNKNOWN` ou `CANDIDATE`.
17. Toda decisão deve ser explicável por campos persistidos.
18. Toda alteração de contrato deve possuir migração.

---

# 44. RESULTADO ESPERADO

Ao final, a Retracement & Pricing Engine V3 deverá responder com precisão:

- qual é o dealing range ativo;
- qual é a origem;
- qual é o endpoint;
- quando o range foi confirmado;
- quando ficou disponível;
- qual é a direção do impulso;
- qual é a direção da retração;
- qual é o scope;
- qual é o range top/bottom;
- onde está o equilíbrio;
- se o preço está em Premium, Equilibrium ou Discount;
- qual é a retração por pavio;
- qual é a retração por fechamento;
- qual é a retração por corpo;
- qual foi a retração mais profunda;
- se houve extensão;
- se houve teste da origem;
- se houve ruptura da origem;
- se o range foi revisado;
- se foi invalidado;
- qual Structure Event o criou;
- quais OBs, FVGs e pools de liquidez estão relacionados;
- se o range foi afetado por sessão, gap ou rollover;
- qual é o contexto MTF.

A engine final deve ser causal, incremental, imutável, explicável e segura para servir como fonte canônica de pricing do SMC Trader System 7.0.
