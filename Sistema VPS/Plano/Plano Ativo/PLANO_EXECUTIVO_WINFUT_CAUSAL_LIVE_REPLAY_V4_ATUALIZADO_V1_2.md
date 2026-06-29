# PLANO EXECUTIVO DEFINITIVO — WINFUT CAUSAL LIVE-REPLAY V4

**Projeto:** SMC Trader System 7.0  
**Branch:** `feature/winfut-causal-live-replay-v4`  
**Versão:** 1.2  
**Data-base:** 27/06/2026  
**Status:** Fases V4_02 e V4_03 concluídas; próxima fase consolida repositories, chunks/checkpoints, persistência controlada dos indicadores e RSI-Heikin Ashi

---

# 1. Objetivo final

Reconstruir integralmente o histórico do WINFUT nos timeframes:

```text
D1
H4
H1
M15
M5
M2
```

A solução deverá:

1. recalcular todos os candles históricos;
2. recalcular indicadores técnicos;
3. recalcular todas as estruturas SMC necessárias;
4. preencher campos causais, identidades, hashes e lifecycle;
5. simular o mercado candle a candle, como em live;
6. executar o mesmo Opportunity Engine usado em produção;
7. registrar oportunidades `PRONTO`, `MONITORAR` e `BLOQUEADO`;
8. simular entradas, stops, alvos, custos e encerramentos;
9. medir a qualidade dos sinais;
10. testar, por A/B controlado, se o RSI-Heikin Ashi melhora a reação em zonas, o timing e a qualidade dos gatilhos;
11. validar em replay histórico;
12. validar em shadow live;
13. somente depois considerar live real controlado.

A pergunta central é:

> O Opportunity Engine encontra, no instante correto e sem acessar o futuro, oportunidades com qualidade suficiente para operação real?

---

# 2. Decisões arquiteturais

## 2.1 O legado não será fonte causal

Os seguintes componentes ficam disponíveis apenas para auditoria, comparação numérica ou regressão visual:

```text
technical_engine/data_driven_winfut/causal_rebuild_v1.py
tools/rebuild_winfut_causal_v3.py
tools/rebuild_winfut_live_replay_dataset.py
rebuild_m2_chunk.py
rebuild_m2_4q.py
rebuild_m2_simple.py
rebuild_m2_split.py
tabelas V1/V2/V3
runs oficiais FULL_WINDOW
visual overlays legados
```

Eles não poderão ser usados como:

- dataset canônico;
- baseline causal;
- fonte de identidades;
- fonte de `available_at`;
- fonte de lifecycle;
- fonte de ativação;
- base do backtest live-like definitivo.

## 2.2 O SMC Engine V2 legado será preservado inicialmente

A implementação causal será criada em namespace isolado:

```text
technical_engine/live_replay_v4/
```

O SMC Engine V2 atual não será alterado diretamente nas primeiras fases.

Motivos:

- preservar estabilidade do sistema atual;
- evitar regressões nos workers, site e scanner;
- permitir comparação V2 × V4;
- corrigir causalidade sem misturar persistência legada;
- promover a V4 apenas após aprovação dos gates.

## 2.3 Nenhum rebuild integral antes dos gates

Não iniciar rebuild histórico antes de concluir:

```text
contratos
CandleClock
schema V4
persistência transacional
indicadores canônicos
componentes SMC causais
testes sintéticos
testes de prefixo
testes de chunk e resume
sample D1
sample multi-timeframe
reconciliação
```

---

# 3. Evidências já suficientes

A auditoria forense está encerrada para fins de decisão arquitetural.

## 3.1 Runs legados

```text
26 de 26 runs V1 = não canônicos
```

Problemas comprovados:

- runs parciais;
- contadores declarados divergentes do banco;
- linhas órfãs;
- payloads conflitantes;
- ausência de identidades lógicas;
- ausência de hashes;
- persistência parcial;
- implementações diferentes com o mesmo prefixo;
- FULL_WINDOW tratado como causal;
- `sample` processando histórico completo;
- `READY` sem validação suficiente.

## 3.2 Índices locais tratados como globais

Consequências:

- colisão de identidade;
- candle IDs errados;
- timestamps errados;
- supressão de estruturas legítimas;
- dependência do tamanho do batch;
- resultados não reproduzíveis.

## 3.3 Persistência não transacional

A conexão compartilhada usa:

```text
autocommit=True
```

Consequências:

- inserts confirmados isoladamente;
- rollback ineficaz após commit automático;
- chunks incompletos;
- checkpoint divergente;
- retries inseguros.

## 3.4 Indicadores

Problemas identificados:

- disponibilidade associada à abertura do candle;
- ausência de contrato canônico de fechamento;
- warm-up inconsistente;
- múltiplas implementações para o mesmo indicador;
- cálculo full-window;
- hashes incompletos;
- ausência de estado incremental determinístico.

## 3.5 Swings

O swing nasce no pivô, mas só pode ser publicado depois da confirmação.

Contrato necessário:

```text
origin_index = pivô
confirmation_source_index = origin_index + swing_length
available_at = fechamento causal da confirmação
```

## 3.6 BOS/CHOCH

Foi comprovado que o rompimento pode ocorrer antes de todos os swings constituintes estarem confirmados.

Contrato:

```text
confirmation_source_index =
max(
    broken_index,
    confirmação do último swing necessário
)
```

## 3.7 Liquidity

O algoritmo atual usa ATR do prefixo completo e altera pools históricos retroativamente.

Consequências:

- falha de prefix invariance;
- formação retroativa;
- nível alterado por candles futuros;
- membros e tolerâncias não congelados.

## 3.8 Order Blocks

No D1:

```text
56 registros
56 disponíveis um candle antes
56 desenhados antes de available_at
30 contendo mitigação futura na linha inicial
```

A formação numérica foi reproduzida, mas o modelo temporal foi reprovado.

## 3.9 Ambiente, fundação e indicadores — estado executado

A Fase V4_02 foi concluída.

Resultados confirmados:

```text
pytest = 8.2.2
Python = 3.12.3
suite tests/live_replay_v4 = 51 passed, 0 failed
foundation-validate = PASS
indicator-selftest = PASS
environment = PASS
preflight = PASS
schema-plan = PASS
```

Componentes já implementados e testados:

```text
CandleClock
hashing
identity
state
exceptions
config
CLI
persistência V4 com autocommit=False
TRUE_RANGE
RANGE
EMA20
EMA200
RSI14
ATR14
VOLATILITY_BUCKET
```

Gates aprovados:

```text
CONTRACTS / FOUNDATION
CANDLE CLOCK
HASHING / IDENTITY
INDICATORS
PREFIX INVARIANCE
CHUNK INVARIANCE
RESUME INVARIANCE
NO FUTURE
IMPORT SIDE EFFECTS
SCHEMA STATIC
```

Commit da Fase V4_02:

```text
e09370584b88ed64369fe46fdcd3a0aaf4e62b51
feat(live-replay-v4): harden foundation and add causal indicators
```

O `VOLATILITY_BUCKET` permanece com thresholds provisórios e:

```text
production_calibrated = false
```

## 3.10 Schema e transações — estado executado

A Fase V4_03 foi concluída.

Resultados:

```text
18 tabelas winfut_lr_v4_* aplicadas
schema-apply = PASS
schema-validate = PASS
transaction-probe = PASS
suite tests/live_replay_v4 = 51 passed, 0 failed
```

O schema foi aplicado dentro de:

```text
smc_trader_2_db
```

com isolamento por namespace:

```text
winfut_lr_v4_*
```

Não foi possível criar database separado porque:

```text
smc_user não possui CREATE DATABASE
```

O probe confirmou:

```text
rollback real sem resíduos em tabelas V4
commit de prova confirmado
autocommit=False
```

Commit da Fase V4_03:

```text
a79bff9ecd655e524d11897d2a3f5ff56f8ed30e
feat(live-replay-v4): apply schema and validate transactions
```

Limite do que já foi comprovado:

```text
schema físico e transação básica = validados
repositories completos = ainda pendentes
chunk atômico completo = ainda pendente
checkpoint/resume físico = ainda pendente
primeira persistência real de indicadores V4 = ainda pendente
```

---

# 4. Princípios obrigatórios da V4

## 4.1 Causalidade

Em qualquer instante simulado:

```text
available_at <= simulation_time
```

Nenhum consumidor poderá acessar:

- candle ainda aberto;
- indicador não fechado;
- estrutura não confirmada;
- mitigação futura;
- sweep futuro;
- invalidação futura;
- resultado futuro da oportunidade.

## 4.2 Determinismo

Mesmos candles, parâmetros, versões, timezone e ordem de eventos deverão produzir exatamente os mesmos:

```text
logical IDs
payload hashes
event hashes
state hashes
content hashes
contagens
oportunidades
trades
resultados
```

## 4.3 Identidade por candle real

É proibido usar como identidade persistida:

```text
DataFrame index
ref_index local
posição da janela
posição do chunk
datetime.now()
contador de processo
```

## 4.4 Lifecycle append-only

A estrutura inicial contém somente o conhecimento disponível na criação.

Eventos futuros serão separados:

```text
CREATED
AVAILABLE
TOUCHED
MEMBER_ADDED
LEVEL_UPDATED
MITIGATED
SWEPT
BROKEN
INVALIDATED
EXPIRED
```

## 4.5 Transação por chunk

Um chunk somente poderá ser confirmado se todos os seus elementos forem válidos:

```text
indicadores
estruturas
eventos
oportunidades
trades/outcomes
reconciliação
hashes
checkpoint
```

## 4.6 Sem falha silenciosa

É proibido:

```python
except Exception:
    continue
```

Todo erro deverá:

- interromper o chunk;
- causar rollback;
- ser registrado;
- impedir `READY`;
- impedir ativação.

---

# 5. Arquitetura-alvo

```text
technical_engine/live_replay_v4/
├── __init__.py
├── contracts.py
├── config.py
├── candle_clock.py
├── identity.py
├── hashing.py
├── state.py
├── exceptions.py
├── indicators/
│   ├── contracts.py
│   ├── incremental.py
│   ├── ema.py
│   ├── rsi.py
│   ├── atr.py
│   ├── range_metrics.py
│   └── registry.py
├── smc/
│   ├── swings.py
│   ├── fvg.py
│   ├── order_blocks.py
│   ├── structure.py
│   ├── liquidity.py
│   ├── bpr.py
│   ├── previous_high_low.py
│   ├── sessions.py
│   ├── retracements.py
│   └── registry.py
├── persistence/
│   ├── connection.py
│   ├── repositories.py
│   ├── transactions.py
│   ├── mappings.py
│   └── reconciliation.py
├── replay/
│   ├── event_queue.py
│   ├── timeframe_state.py
│   ├── mtf_state.py
│   ├── orchestrator.py
│   ├── checkpoints.py
│   └── resume.py
├── opportunities/
│   ├── adapter.py
│   ├── snapshot.py
│   ├── decision_log.py
│   ├── execution.py
│   └── outcomes.py
├── validation/
│   ├── prefix.py
│   ├── determinism.py
│   ├── reconciliation.py
│   ├── no_future.py
│   └── gates.py
└── cli.py
```

Ferramenta operacional:

```text
tools/winfut_live_replay_v4.py
```

Testes:

```text
tests/live_replay_v4/
```

Migration:

```text
migrations/20260627_live_replay_v4_schema.py
```

---

# 6. Contrato temporal dos candles

## 6.1 Semântica atual

```text
market_candles.timestamp = abertura do candle
```

## 6.2 Fechamento histórico

Enquanto não houver coluna canônica de fechamento:

```text
source_open_at = timestamp[i]
source_close_at = timestamp[i + 1]
available_at = timestamp[i + 1]
```

Não somar minutos fixos. Usar o próximo candle real para preservar:

- gaps;
- finais de semana;
- feriados;
- pausas de sessão;
- indisponibilidade de dados.

## 6.3 Último candle

Sem sucessor:

```text
source_close_at = NULL
available_at = NULL
status = PENDING_SOURCE_CLOSE
```

Esse candle não poderá:

- alimentar indicador final;
- confirmar estrutura;
- gerar decisão final;
- participar do replay histórico fechado.

## 6.4 CandleClock

Criar um único componente responsável por:

- `open_at`;
- `close_at`;
- `available_at`;
- candle anterior;
- candle seguinte;
- gaps;
- sessão;
- timezone;
- ordenação global.

Nenhum componente poderá inferir fechamento por conta própria.

---

# 7. Identidade, hashes e versões

## 7.1 Estruturas

O `logical_structure_id` deverá incluir:

```text
asset_id
timeframe
structure_type
direction
origin_candle_id
confirmation_candle_id
parameter_hash
engine_version
calculation_version
schema_version
```

Quando necessário:

```text
anchor_candle_id
breakout_candle_id
member_set_hash
parent_structure_id
```

## 7.2 Eventos

```text
run_id
logical_structure_id
event_type
sequence
source_candle_id
reason_code
event_at
```

## 7.3 Indicadores

```text
timeframe_run_id
indicator_name
source_candle_id
parameter_hash
engine_version
calculation_version
```

## 7.4 Oportunidades

```text
asset_id
direction
setup_type
decision_candle_id
evidence_hash
parameter_hash
engine_version
calculation_version
```

## 7.5 Hashes obrigatórios

```text
parameter_hash
input_hash
state_hash
payload_hash
event_hash
chunk_content_hash
timeframe_content_hash
run_content_hash
```

Mesmo ID e mesmo hash:

```text
retry idempotente
```

Mesmo ID e hash diferente:

```text
CONFLICT
ROLLBACK
RUN FAILED
```

---

# 8. Schema V4 isolado

## 8.1 Tabelas

```text
winfut_lr_v4_parent_runs
winfut_lr_v4_timeframe_runs
winfut_lr_v4_status_history
winfut_lr_v4_chunks
winfut_lr_v4_checkpoints
winfut_lr_v4_indicators
winfut_lr_v4_structures
winfut_lr_v4_structure_events
winfut_lr_v4_opportunities
winfut_lr_v4_opportunity_evidence
winfut_lr_v4_trade_simulations
winfut_lr_v4_trade_events
winfut_lr_v4_outcomes
winfut_lr_v4_reconciliation
winfut_lr_v4_validations
winfut_lr_v4_errors
winfut_lr_v4_artifacts
winfut_lr_v4_active_runs
```

## 8.2 Parent run

Campos mínimos:

```text
run_id
symbol
asset_id
status
schema_version
engine_version
calculation_version
parameter_hash
source_dataset_hash
started_at
finished_at
created_at
created_by
git_commit
python_version
config_json
content_hash
```

## 8.3 Timeframe run

```text
timeframe_run_id
parent_run_id
timeframe
database_timeframe
status
first_candle_id
last_candle_id
candle_count
processed_candle_count
indicator_count
structure_count
event_count
opportunity_count
trade_count
input_hash
state_hash
content_hash
started_at
finished_at
```

## 8.4 Indicadores

```text
timeframe_run_id
asset_id
timeframe
indicator_name
source_candle_id
source_close_candle_id
source_open_at
source_close_at
available_at
numeric_value
text_value
is_warm
warmup_required
engine_version
calculation_version
parameter_hash
payload_hash
created_at
```

## 8.5 Estruturas

```text
timeframe_run_id
logical_structure_id
structure_type
direction
origin_candle_id
confirmation_candle_id
availability_candle_id
anchor_candle_id
breakout_candle_id
origin_at
confirmed_at
available_at
state_at_creation
top_price
bottom_price
level_price
midpoint_price
parameter_hash
payload_hash
payload_json
created_at
```

Campos futuros como `mitigated_at` não pertencem à linha inicial.

## 8.6 Eventos

```text
timeframe_run_id
logical_structure_id
event_identity_hash
event_type
sequence
source_candle_id
event_at
state_before
state_after
reason_code
payload_hash
payload_json
created_at
```

## 8.7 Oportunidades

```text
parent_run_id
opportunity_id
symbol
direction
setup_type
decision_time
decision_candle_id
entry_timeframe
status
score
entry_price
stop_price
target_1
target_2
target_3
risk_points
reward_risk_1
reward_risk_2
reward_risk_3
parameter_hash
evidence_hash
decision_payload_hash
decision_payload_json
created_at
```

## 8.8 Constraints

Obrigatório:

- foreign keys para runs;
- identidades `NOT NULL`;
- unique keys reais;
- sem nullable em unique identity;
- check constraints para sequência e estados;
- índice por `available_at`;
- índice por `source_candle_id`;
- índice por `logical_structure_id`;
- índice por `decision_time`;
- uma ativação por símbolo/timeframe;
- nenhuma FK para tabela legada.


## 8.9 Estado físico do schema após V4_03

O schema deixou de ser apenas um plano declarativo.

Estado atual:

```text
schema aplicado = sim
quantidade de tabelas = 18
namespace = winfut_lr_v4_*
database físico = smc_trader_2_db
schema-validate = PASS
```

O isolamento atual é por namespace, não por database separado.

Essa limitação é aceita temporariamente porque:

- nenhuma tabela legada foi alterada;
- as tabelas V4 possuem prefixo exclusivo;
- a validação por `information_schema` passou;
- os testes transacionais não deixaram resíduos;
- nenhum run foi ativado.

Regras adicionais a partir deste ponto:

- não reaplicar cegamente a migration;
- alterações futuras devem ser aditivas e versionadas;
- qualquer diff de schema deve gerar plano antes do apply;
- operações destrutivas continuam proibidas;
- nenhum repository poderá acessar tabela fora de `winfut_lr_v4_*`, exceto leitura explícita de `market_candles`.

---

# 9. Persistência e transações

## 9.1 Conexão dedicada

Criar:

```text
technical_engine/live_replay_v4/persistence/connection.py
```

Obrigatório:

```python
autocommit=False
```

## 9.2 Unidade atômica

```text
BEGIN
  persistir indicadores
  persistir estruturas
  persistir eventos
  persistir oportunidades
  persistir trades/outcomes
  persistir reconciliação
  validar hashes
  persistir checkpoint
COMMIT
```

Em erro:

```text
ROLLBACK
registrar erro em transação separada
status FAILED ou PAUSED
```

## 9.3 Persistidores sem commit interno

Nenhum repository poderá executar `commit()` ou `rollback()`.

## 9.4 Retry idempotente

Retry deverá:

1. carregar checkpoint confirmado;
2. restaurar estado;
3. reprocessar chunk;
4. comparar IDs e hashes;
5. aceitar igualdade exata;
6. abortar em conflito.

## 9.5 Fault injection

Criar falhas simuladas depois de:

```text
indicadores
FVG
Order Blocks
BOS/CHOCH
Liquidity
oportunidades
reconciliação
checkpoint
```

Em todos os casos, não poderão existir linhas parciais do chunk.


## 9.6 Divisão da persistência em duas etapas

### FASE 3A — concluída

```text
conexão autocommit=False
schema físico aplicado
validação por information_schema
rollback real
commit de prova
```

### FASE 3B — pendente e prioritária

Implementar e validar:

```text
ParentRunRepository
TimeframeRunRepository
StatusHistoryRepository
ChunkRepository
CheckpointRepository
IndicatorRepository
ReconciliationRepository
ValidationRepository
ErrorRepository
ArtifactRepository
```

A Fase 3B deverá comprovar em tabelas V4 reais:

```text
transação de chunk completo
bulk insert
idempotência
conflito de hash
rollback por fault injection
checkpoint atômico
resume físico
reconciliação residual zero
zero escrita legada
```

O primeiro conteúdo persistido será limitado a indicadores V4. Nenhuma estrutura SMC será persistida antes da aprovação desse gate.

---

# 10. Indicadores canônicos

## 10.0 Estado atual da fase

### Núcleo concluído na V4_02

```text
TRUE_RANGE
RANGE
EMA20
EMA200
RSI14
ATR14
VOLATILITY_BUCKET
```

Esses indicadores passaram:

```text
incremental × batch
prefix invariance
chunk invariance
resume invariance
no-future
```

### Pendente por atualização posterior do plano

```text
RSI_HEIKIN_ASHI_V1
```

O RSI-Heikin Ashi foi adicionado ao plano depois da conclusão da V4_02. Portanto, ainda precisa ser implementado, testado e integrado ao registry antes do primeiro sample persistido que pretenda representar o conjunto completo de indicadores experimentais.


## 10.1 Indicadores mínimos

```text
EMA20
EMA200
RSI14
ATR14
TRUE_RANGE
RANGE
VOLATILITY_BUCKET
```

Adicionar somente quando usados pelo Opportunity Engine:

```text
volume moving average
relative volume
session VWAP
ADX
momentum
```

## 10.2 Fonte única

Cada indicador terá:

```text
nome
versão
parâmetros
warm-up
schema de estado
incremental update
batch reference
numeric tolerance
```

## 10.3 Incremental × batch

```text
incremental output == batch prefix output
```

## 10.4 Warm-up

Durante warm-up:

```text
is_warm = false
value = NULL ou valor explicitamente não utilizável
```

O Opportunity Engine não poderá consumir indicador não maduro.

## 10.5 Testes

- série constante;
- tendência linear;
- gaps;
- NaN proibido;
- warm-up;
- prefix invariance;
- chunk invariance;
- resume invariance;
- comparação com referência.


## 10.6 Indicador experimental RSI-Heikin Ashi

Será implementado um indicador causal e versionado inspirado no código Profit analisado, com o nome canônico:

```text
RSI_HEIKIN_ASHI_V1
```

A finalidade inicial não é substituir SMC, Elliott ou Wyckoff. O indicador será testado como:

```text
CONTEXT_MODIFIER
TRIGGER_SCORE
ZONE_REACTION_CONFIRMATION
```

Ele não poderá funcionar inicialmente como bloqueio rígido universal de operações.

### 10.6.1 Pipeline matemático canônico

Para cada candle fechado:

1. suavizar `Open`, `High`, `Low` e `Close` com EMA de período 3;
2. calcular RSI de Wilder com período 14 separadamente para cada fonte suavizada;
3. formar um candle Heikin Ashi no espaço do RSI;
4. calcular EMA de período 14 sobre o OHLC4 do candle RSI-HA.

Fontes suavizadas:

```text
oSrc = EMA3(Open)
hSrc = EMA3(High)
lSrc = EMA3(Low)
cSrc = EMA3(Close)
```

RSI por fonte:

```text
rsiOpen
rsiHigh
rsiLow
rsiClose
```

Heikin Ashi do RSI:

```text
haCloseRSI =
(rsiOpen + rsiHigh + rsiLow + rsiClose) / 4

haOpenRSI inicial =
(rsiOpen + rsiClose) / 2

haOpenRSI seguinte =
(haOpenRSI anterior + haCloseRSI anterior) / 2

haHighRSI =
max(rsiHigh, haOpenRSI, haCloseRSI)

haLowRSI =
min(rsiLow, haOpenRSI, haCloseRSI)
```

Linha de sinal:

```text
baseMA =
(haOpenRSI + haHighRSI + haLowRSI + haCloseRSI) / 4

signalEMA = EMA14(baseMA)
```

### 10.6.2 Tratamento correto do RSI

A V4 deverá corrigir a ambiguidade do indicador original:

```text
average_gain = 0 e average_loss = 0 → RSI = 50
average_loss = 0 e average_gain > 0 → RSI = 100
average_gain = 0 e average_loss > 0 → RSI = 0
```

Não será permitido retornar RSI 100 para uma série totalmente estável.

### 10.6.3 Warm-up

O indicador somente será utilizável quando toda a cadeia estiver madura:

```text
EMA3 das quatro fontes
RSI14 das quatro fontes
Heikin Ashi do RSI
EMA14 da linha de sinal
```

A implementação deverá calcular e persistir o `warmup_required` da cadeia. Com seed SMA canônico, a primeira linha de sinal madura deverá ser validada por teste e não presumida silenciosamente.

Enquanto não estiver maduro:

```text
is_warm = false
trigger_eligible = false
```

### 10.6.4 Emissões obrigatórias

Persistir, no mínimo:

```text
RSI_HA_OPEN
RSI_HA_HIGH
RSI_HA_LOW
RSI_HA_CLOSE
RSI_HA_SIGNAL_EMA14
RSI_HA_DIRECTION
RSI_HA_BODY
RSI_HA_RANGE
RSI_HA_BODY_RATIO
RSI_HA_UPPER_WICK
RSI_HA_LOWER_WICK
RSI_HA_SIGNAL_SLOPE
RSI_HA_DISTANCE_TO_SIGNAL
RSI_HA_ZONE
RSI_HA_CROSS_EVENT
```

Estados derivados:

```text
BULLISH
BEARISH
NEUTRAL
ABOVE_50
BELOW_50
OVERSOLD
OVERBOUGHT
```

Eventos derivados:

```text
BULLISH_FLIP
BEARISH_FLIP
CROSS_ABOVE_SIGNAL
CROSS_BELOW_SIGNAL
RECOVER_30
LOSE_30
CROSS_ABOVE_50
CROSS_BELOW_50
CROSS_ABOVE_70
CROSS_BELOW_70
```

Todos os eventos deverão ser derivados somente de candles fechados.

### 10.6.5 Causalidade

Para o candle fonte `i`:

```text
source_close_at = CandleClock.close_at(i)
available_at = CandleClock.available_at(i)
```

É proibido:

- usar o valor intrabar final como se já fosse conhecido;
- recalcular eventos anteriores com candles futuros;
- antecipar cruzamentos;
- usar quantis full-window;
- usar resultado da oportunidade na feature.

### 10.6.6 Timeframes

O indicador será calculado e armazenado nos seis timeframes:

```text
D1
H4
H1
M15
M5
M2
```

Uso experimental inicial:

```text
D1/H4/H1 = contexto e pesquisa, sem gate rígido
M15 = modificador estrutural opcional
M5 = confirmação do setup na zona
M2 = gatilho principal de timing
```

### 10.6.7 Bloco de exaustão excluído da V1

O bloco original baseado em:

```text
AccAgressSaldo
AgressionVolBalance
MA_speed
Topo_Fundo
```

não será incorporado ao `RSI_HEIKIN_ASHI_V1`.

Motivos:

- expressões algébricas cancelam `accAgr` e `agVolBal`;
- há risco de divisão por zero;
- OHLCV não reproduz fielmente agressão;
- faltam dados históricos de negócio/tick compatíveis;
- a regra de topo/fundo pode repetir marcações.

Esse bloco somente poderá voltar como estudo independente após:

```text
dados de agressão auditados
fórmula redesenhada
contrato causal
testes específicos
A/B separado
```

### 10.6.8 Paridade com Profit

Criar um conjunto pequeno de candles e exportar os valores do Profit para comparação numérica quando possível.

A paridade deverá verificar:

```text
EMA3 das fontes
Wilder RSI
candle RSI-HA
EMA14 de sinal
eventos de cruzamento
```

Diferenças de inicialização deverão ser documentadas. A fórmula V4 será a fonte canônica do replay após congelamento de versão.


---

# 11. Correções dos componentes SMC

## 11.1 Swings

Contrato:

```text
origin_candle_id = pivô
confirmation_source_candle_id = origin + swing_length
availability_candle_id = candle seguinte ao fechamento da confirmação
```

Requisitos:

- não publicar antes da confirmação;
- identidade sem índice local;
- imutabilidade após confirmação;
- teste de prefixo em todos os timeframes;
- teste em borda de chunk.

## 11.2 FVG

A FVG só existe após fechamento do terceiro candle necessário.

Lifecycle:

```text
CREATED
AVAILABLE
TOUCHED
PARTIALLY_FILLED
MITIGATED
INVALIDATED
```

Requisitos:

- preenchimento futuro fora da linha inicial;
- gaps de sessão parametrizados;
- visualização em `available_at`;
- formação em fronteira de chunk.

## 11.3 Order Blocks

Substituir:

```text
confirmed_index = ob_origin_index + 1
available_index = ob_origin_index + 1
display_from = origin_at
mitigated_index dentro da estrutura inicial
```

por:

```text
formation_source =
max(
    breakout_candle,
    swing_confirmation_candle
)

confirmed_at = fechamento de formation_source
available_at = primeiro instante após esse fechamento
display_from = available_at
```

Identidade:

```text
origin_candle_id
breakout_candle_id
swing_candle_id
direction
ob_definition
mitigation_mode
parameter_hash
versions
```

Lifecycle:

```text
CREATED
AVAILABLE
TOUCHED
MITIGATED
INVALIDATED
EXPIRED
```

Requisitos:

- múltiplas estruturas por candle;
- sem sobrescrita por arrays;
- volume do rompimento só após fechamento;
- scoring de sessão versionado;
- nenhum campo futuro no payload inicial.

## 11.4 BOS/CHOCH

Criar state machine incremental de swings confirmados.

Disponibilidade:

```text
source =
max(
    candle do rompimento,
    confirmação causal dos swings constituintes
)
```

Identidade:

```text
broken_level_swing_id
breakout_candle_id
event_type
direction
constituent_state_hash
```

Requisitos:

- não reclassificar retroativamente;
- guardar constituent swing IDs;
- guardar estado anterior;
- emitir um evento por rompimento;
- prefix invariance.

## 11.5 Liquidity

Redesenho obrigatório.

Quando um membro entra:

```text
ATR usado = ATR disponível naquele instante
tolerância = congelada no evento
```

Lifecycle:

```text
CREATED
MEMBER_ADDED
LEVEL_UPDATED
AVAILABLE
SWEPT
INVALIDATED
EXPIRED
```

Requisitos:

- atualização append-only;
- histórico do nível;
- ATR e threshold no evento;
- identidade ancorada no primeiro membro;
- prefix invariance;
- chunk invariance;
- ordem determinística.

## 11.6 BPR

Derivar somente de FVGs disponíveis.

```text
available_at = max(available_at das FVGs constituintes)
```

Guardar IDs das FVGs pais.

## 11.7 Previous High/Low

Somente períodos concluídos:

```text
previous day
previous week
previous session
```

Requisitos:

- timezone explícito;
- calendário de sessão;
- feriados;
- vínculo aos candles extremos;
- nunca usar o período atual como previous.

## 11.8 Sessions

Requisitos:

- configuração por ativo;
- `ZoneInfo`;
- sessão regular;
- fechamento;
- virada de data;
- feriados;
- nenhum UTC fixo espalhado pelo código.

## 11.9 Retracements

Requisitos:

- anchors confirmados;
- sem reposicionamento retroativo;
- parent swing IDs;
- mudança de anchor como novo evento;
- parâmetros versionados.

---

# 12. Replay live-like

## 12.1 Fila global

Criar fila cronológica de eventos de fechamento/disponibilidade dos seis timeframes.

## 12.2 Ordem em timestamps iguais

1. registrar fechamento de todos os candles;
2. atualizar indicadores;
3. atualizar estruturas;
4. atualizar contexto MTF;
5. executar Opportunity Engine uma vez;
6. persistir decisão e evidências;
7. atualizar trades abertos;
8. confirmar chunk.

## 12.3 Snapshot visível

Criar:

```text
ReplayMarketSnapshot
```

Campos:

```text
simulation_time
latest closed candle por timeframe
indicadores disponíveis
estruturas disponíveis
lifecycle conhecido
contexto MTF
sessão
posição/trade aberto
risk state
```

## 12.4 Guard de futuro

Todo repository usado no replay exigirá:

```text
available_at <= simulation_time
```

---

# 13. Integração com o Opportunity Engine

## 13.1 Mesmo caminho de produção

Não criar scanner simplificado para backtest.

Criar adapter do engine real para `ReplayMarketSnapshot`.

## 13.2 Guardrails

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
llm_decision_used = false
```

## 13.3 Registrar todas as decisões

Persistir:

```text
PRONTO
MONITORAR
BLOQUEADO
```

Também registrar:

- motivos de bloqueio;
- regras não atendidas;
- score parcial;
- confluências;
- ausência de dados;
- warm-up;
- contexto MTF;
- evidence bundle.

## 13.4 Deduplicação

Definir:

- cooldown;
- continuidade;
- promoção `MONITORAR → PRONTO`;
- invalidação;
- nova oportunidade;
- prevenção de sinal repetido.

## 13.5 Imutabilidade

A decisão original nunca será reescrita após conhecer o resultado.


## 13.6 Experimento RSI-Heikin Ashi nas zonas e nos gatilhos

O RSI-Heikin Ashi será integrado por adapter, sem modificar retroativamente a decisão do Opportunity Engine atual.

### 13.6.1 Hipóteses a testar

```text
H1: melhora a confirmação de reação em OB/FVG/BPR.
H2: reduz entradas antes da reação real da zona.
H3: reduz sinais contra momentum de curto prazo.
H4: melhora o timing no M2 depois de setup M5.
H5: reduz MAE sem sacrificar excessivamente a taxa de acionamento.
H6: diferencia zonas que apenas foram tocadas de zonas que efetivamente reagiram.
```

### 13.6.2 Zonas e eventos SMC analisados

Associar as features do indicador a:

```text
ORDER_BLOCK_AVAILABLE
ORDER_BLOCK_TOUCHED
FVG_AVAILABLE
FVG_TOUCHED
FVG_PARTIALLY_FILLED
BPR_AVAILABLE
LIQUIDITY_SWEPT
BOS
CHOCH
PREVIOUS_HIGH_LOW_TOUCH
RETRACEMENT_LEVEL_TOUCH
```

Para cada evento de zona, registrar uma janela causal:

```text
estado no instante do toque
primeiro flip posterior
primeiro cruzamento da linha de sinal
primeira recuperação/perda de 30, 50 ou 70
latência em candles
distância do preço à zona
estrutura disponível no instante
```

Nenhuma janela poderá usar candles futuros para decidir o sinal; candles posteriores são usados somente para avaliar o resultado.

### 13.6.3 Variantes congeladas

Executar as três variantes no mesmo replay, com os mesmos candles, custos e regras de execução:

```text
CONTROL_A
Opportunity Engine atual, sem RSI-HA.

CANDIDATE_B
Engine atual +
direção RSI-HA alinhada +
posição relativa à EMA de sinal.

CANDIDATE_C
Engine atual +
direção +
cruzamento da EMA +
inclinação da EMA +
qualidade do corpo/pavios +
evento de 30/50/70 contextualizado à zona.
```

Não alterar simultaneamente:

- stop;
- alvos;
- custos;
- regras SMC;
- cooldown;
- filtros de sessão;
- sizing.

Assim, a diferença observada será atribuível ao conjunto RSI-HA.

### 13.6.4 Uso inicial como score

Exemplo inicial, totalmente configurável e congelado por versão:

```text
+10 direção RSI-HA alinhada
+10 fechamento RSI-HA do lado correto da EMA
+5 inclinação da EMA alinhada
+5 cruzamento recente da EMA
+5 recuperação/perda da linha 50
+5 reversão confirmada após 30/70
+5 body_ratio mínimo
-5 pavio contrário dominante
```

Esses pesos são hipóteses experimentais, não regras aprovadas. Eles deverão entrar em:

```text
parameter_hash
candidate_version
decision_payload
```

### 13.6.5 Regras de gatilho candidatas

Compra em zona:

```text
setup SMC válido no M5
zona disponível e tocada
bias MTF permitido
RSI-HA M2 faz BULLISH_FLIP
haCloseRSI cruza ou permanece acima da signalEMA
signalEMA slope >= limite configurado
evento RECOVER_30 ou CROSS_ABOVE_50, conforme setup
```

Venda em zona:

```text
setup SMC válido no M5
zona disponível e tocada
bias MTF permitido
RSI-HA M2 faz BEARISH_FLIP
haCloseRSI cruza ou permanece abaixo da signalEMA
signalEMA slope <= limite configurado
evento CROSS_BELOW_70 ou CROSS_BELOW_50, conforme setup
```

O replay deverá também registrar oportunidades que seriam aprovadas pelo controle e bloqueadas pelos candidatos, e vice-versa.

### 13.6.6 Estados de decisão

O indicador poderá inicialmente:

```text
aumentar score
reduzir score
promover MONITORAR para PRONTO
manter MONITORAR
registrar motivo de bloqueio experimental
```

Não poderá, antes da validação:

```text
alterar ordem real
promover automaticamente configuração
alterar risco
alterar stop
alterar alvo
```

### 13.6.7 Snapshot e evidências

O `OpportunityEvidenceBundleV4` deverá guardar:

```text
rsi_ha timeframe
valores OHLC
signalEMA
signal slope
direction
body ratio
wicks
zone state
cross event
warm-up
source candle IDs
available_at
candidate version
score contribution
decision reason codes
```


---

# 14. Simulação de execução

## 14.1 Configuração explícita

```text
entrada market/limit/stop
gatilho
preço de execução
slippage
spread
custos
latência
parciais
stop
alvos
breakeven
trailing
expiração
fim de sessão
gap
```

## 14.2 Ordem intrabar

Sem ticks, quando stop e alvo forem tocados no mesmo candle, usar política configurável:

```text
CONSERVATIVE
OPTIMISTIC
LOWER_TIMEFRAME_RESOLUTION
AMBIGUOUS_EXCLUDED
```

## 14.3 Outcomes

```text
triggered
not_triggered
expired
cancelled
stopped
target_1
target_2
target_3
session_exit
ambiguous
```

## 14.4 Métricas por trade

```text
pontos
R
custos
MFE
MAE
tempo até entrada
tempo em operação
tempo até alvo
drawdown intratrade
```

---

# 15. Métricas do Opportunity Engine

## 15.1 Gerais

```text
quantidade de oportunidades
oportunidades por dia
PRONTO/MONITORAR/BLOQUEADO
taxa de acionamento
taxa de acerto
expectancy em R
profit factor
payoff
drawdown
recovery factor
sequência de perdas
```

## 15.2 Segmentação

```text
setup
direção
timeframe
regime
sessão
horário
dia da semana
score
quality label
volatility bucket
SMC
Elliott
Wyckoff
contexto D1/H4
```

## 15.3 Qualidade do scanner

```text
sinais duplicados
sinais atrasados
sinais invalidados antes da entrada
bloqueados que venceriam
aprovados de baixa qualidade
uso de dado não maduro
divergência replay × shadow
```

## 15.4 Controle

Manter:

```text
CONTROL_A
CANDIDATE_B
CANDIDATE_C
```

Nenhum candidato será promovido automaticamente.


## 15.5 Métricas específicas do RSI-Heikin Ashi

Comparar CONTROL_A, CANDIDATE_B e CANDIDATE_C por:

```text
expectancy em R
profit factor
drawdown
taxa de acerto
payoff
taxa de acionamento
MFE
MAE
tempo até entrada
latência após toque na zona
sinais precoces evitados
sinais válidos bloqueados
sinais tardios
oportunidades por dia
```

Segmentar por:

```text
tipo de zona
tipo de setup
direção
M2/M5
sessão
horário
regime de volatilidade
bias D1/H4
evento RSI-HA
zona 30/50/70
body ratio
signal slope
```

Relatórios obrigatórios:

```text
rsi_ha_feature_report.json
rsi_ha_zone_reaction_report.json
rsi_ha_ab_test_report.json
rsi_ha_out_of_sample_report.json
```

### 15.5.1 Critérios de análise

Não aprovar o indicador apenas porque aumenta a taxa de acerto.

A análise deverá considerar conjuntamente:

```text
expectancy
profit factor
drawdown
frequência
estabilidade por período
estabilidade por setup
bootstrap
out-of-sample
custos
```

### 15.5.2 Promoção

A promoção de qualquer candidato exige:

```text
sample mínimo definido antes do teste
resultado positivo fora da amostra
nenhuma violação causal
nenhuma piora de risco além do limite configurado
ganho reproduzível em mais de um período/regime
shadow live consistente
aprovação humana
```

Se não houver melhoria robusta:

```text
RSI_HA = FEATURE_INFORMATIVA
sem promoção para gate operacional
```


---

# 16. Rollover e suficiência do dataset

O histórico atual possui limitação conhecida de rollover.

O replay poderá validar:

- causalidade;
- integração;
- funcionamento;
- qualidade inicial dos sinais.

Promoção definitiva deverá exigir:

```text
mínimo de 12 meses intraday
pelo menos um rollover validado
continuidade de preços auditada
custos confirmados
sessões confirmadas
```

---

# 17. Testes obrigatórios

## 17.1 Ambiente

1. localizar `pyproject.toml`, `requirements*.txt`, `setup.cfg` e lockfiles;
2. identificar padrão oficial de dependências;
3. adicionar `pytest` como dependência de desenvolvimento;
4. não instalar globalmente;
5. registrar versão;
6. executar no `.venv` correto.

## 17.2 Contratos

- timeframe bidirecional;
- hashes determinísticos;
- IDs sem índice local;
- timestamps ordenados;
- rejeição de lifecycle futuro;
- reconciliação sem residual;
- payload sem NaN/Infinity.

## 17.3 Sintéticos

Criar casos mínimos para:

```text
Swing
FVG
Order Block
BOS
CHOCH
Liquidity
Sweep
BPR
Previous High/Low
Session
Retracement
```

## 17.4 Prefix invariance

```text
resultado no prefixo N
==
recorte causal do resultado completo em N
```

## 17.5 Chunk invariance

Comparar:

```text
chunk 50
chunk 100
chunk 500
chunk 2000
sem chunk
```

## 17.6 Resume invariance

Interromper e retomar em múltiplos pontos. O hash final deverá ser igual ao run contínuo.

## 17.7 Fault injection

Falha depois de cada componente deverá deixar:

```text
zero linhas parciais
checkpoint anterior intacto
erro registrado
run não READY
```

## 17.8 No-future

Provar que:

- indicador futuro não aparece;
- estrutura futura não aparece;
- lifecycle futuro não aparece;
- higher timeframe aberto não aparece;
- outcome não altera decisão histórica.

## 17.9 Determinismo

Executar o mesmo sample duas vezes e comparar:

```text
IDs
hashes
contagens
eventos
oportunidades
trades
métricas
```


## 17.10 Testes específicos do RSI-Heikin Ashi

Criar testes para:

```text
série constante → RSI neutro 50
tendência crescente
tendência decrescente
alternância de ganhos/perdas
EMA3 das quatro fontes
RSI Wilder das quatro fontes
seed do haOpenRSI
continuidade recursiva do haOpenRSI
haHighRSI e haLowRSI
EMA14 da linha de sinal
warm-up integral
cruzamentos
linhas 30/50/70
body ratio
pavios
slope
serialização e resume
```

Gates:

```text
RSI_HA_BATCH_INCREMENTAL_EQUIVALENCE = PASS
RSI_HA_PREFIX_INVARIANCE = PASS
RSI_HA_CHUNK_INVARIANCE = PASS
RSI_HA_RESUME_INVARIANCE = PASS
RSI_HA_NO_FUTURE = PASS
RSI_HA_HASH_DETERMINISM = PASS
```

Também criar teste de integração sintético:

```text
toque em OB/FVG
→ flip RSI-HA posterior
→ cruzamento da signalEMA
→ Opportunity Engine recebe a feature somente em available_at
```


---

# 18. CLI V4

Comandos planejados:

```text
environment
schema-plan
schema-apply
preflight
sample
rebuild
resume
status
validate
compare
report
freeze
activate
deactivate
replay
backtest
shadow-compare
```

`sample` deverá exigir:

```text
--timeframe
--max-candles
--dry-run ou --persist
```

Defaults:

```text
offline
dry-run
no-activate
rollback-on-error
```

---

# 19. Fases de execução

## FASE 0 — Ambiente e repositório — CONCLUÍDA

Entregas confirmadas:

```text
pytest 8.2.2 no venv
requirements.txt atualizado
environment.json
preflight.json
arquivos legados preservados
imports V4 sem side effects
```

Gate:

```text
ENVIRONMENT_READY = PASS
```

## FASE 1 — Contratos e CandleClock — CONCLUÍDA

Entregas implementadas e testadas:

```text
contracts.py
candle_clock.py
identity.py
hashing.py
state.py
exceptions.py
test_contracts.py
test_candle_clock.py
```

Gates:

```text
CONTRACTS_PASS = PASS
NO_LOCAL_INDEX_IDENTITY = PASS
NO_FUTURE_FIELDS = PASS
CANDLE_CLOCK = PASS
```

## FASE 2 — Schema V4 — CONCLUÍDA COM ISOLAMENTO POR NAMESPACE

Resultados:

```text
18 tabelas aplicadas
schema-validate = PASS
legacy tables unchanged = PASS
namespace winfut_lr_v4_* = PASS
```

Gates:

```text
SCHEMA_ISOLATED_BY_NAMESPACE = PASS
LEGACY_TABLES_UNCHANGED = PASS
CONSTRAINTS_PASS = PASS
```

Ressalva:

```text
database separado = não disponível por falta de CREATE DATABASE
```

## FASE 3 — Persistência transacional — PARCIAL

### FASE 3A concluída

```text
autocommit=False
schema apply
schema validate
transaction probe
rollback sem resíduos
```

### FASE 3B pendente

```text
repositories reais
chunk atômico completo
checkpoint físico
resume físico
bulk insert
idempotência
hash conflict
fault injection
```

Gates ainda necessários:

```text
PARTIAL_CHUNK_ROWS = 0
RESUME_DETERMINISTIC = PASS
CONFLICT_ABORTS_RUN = PASS
RECONCILIATION_RESIDUAL_ZERO = PASS
```

## FASE 4 — Indicadores — NÚCLEO CONCLUÍDO, RSI-HA PENDENTE

### FASE 4A concluída

```text
TRUE_RANGE
RANGE
EMA20
EMA200
RSI14
ATR14
VOLATILITY_BUCKET
```

Gates aprovados:

```text
INDICATOR_PREFIX_INVARIANCE = PASS
INDICATOR_CHUNK_INVARIANCE = PASS
INDICATOR_RESUME_INVARIANCE = PASS
INDICATOR_AVAILABLE_AT = PASS
```

### FASE 4B pendente

```text
RSI_HEIKIN_ASHI_V1
paridade incremental/batch
prefix/chunk/resume
no-future
registry
persistência genérica
```

## FASE 5 — SMC causal

Ordem:

```text
Swings
FVG
Order Blocks
BOS/CHOCH
Liquidity
BPR
Previous High/Low
Sessions
Retracements
```

Gate:

```text
SMC_ALL_COMPONENTS_CAUSAL = PASS
```

## FASE 6 — Golden sample D1

Sample pequeno e explícito.

Gate:

```text
D1_GOLDEN_SAMPLE = PASS
```

## FASE 7 — Sample multi-timeframe

Gate:

```text
MTF_REPLAY_SAMPLE = PASS
```

## FASE 8 — Rebuild integral

Ordem:

```text
D1
H4
H1
M15
M5
M2
```

Gate por timeframe:

```text
INPUT_COUNT_MATCH
PROCESSED_COUNT_MATCH
RECONCILIATION_RESIDUAL_ZERO
ERROR_COUNT_ZERO
PREFIX_TEST_PASS
DETERMINISM_PASS
CONTENT_HASH_PRESENT
```

Gate final:

```text
FULL_HISTORY_REBUILD_READY = PASS
```

## FASE 9 — Backtest live-like

Gate:

```text
NO_FUTURE_ACCESS
NO_DECISION_MUTATION
REPLAY_REPRODUCIBLE
```


## FASE 9A — Experimento RSI-Heikin Ashi em replay

Executar, sem alterar o dataset causal base:

```text
CONTROL_A
CANDIDATE_B
CANDIDATE_C
```

Escopo inicial:

```text
M5 = zona/setup
M2 = gatilho
M15 = contexto opcional
D1/H4/H1 = features coletadas para análise
```

Entregas:

```text
features causais RSI-HA
evidence bundle
decisões paralelas
relatório de reação por zona
relatório A/B
métricas por candidato
```

Gates:

```text
RSI_HA_FEATURE_CAUSAL = PASS
RSI_HA_AB_REPLAY_REPRODUCIBLE = PASS
RSI_HA_DECISIONS_IMMUTABLE = PASS
RSI_HA_NO_AUTOMATIC_PROMOTION = PASS
```

O término dessa fase não implica que o indicador melhorou o sistema. Ele apenas determina, com evidência, se houve melhoria.


## FASE 10 — Análise e calibração

Regras:

- separar treino, validação e teste temporal;
- congelar candidatos;
- não otimizar no mesmo período de aprovação;
- bootstrap;
- análise por regime;
- comparação CONTROL_A/B/C.

## FASE 11 — Shadow live

Gate:

```text
SHADOW_STABILITY
REPLAY_LIVE_EQUIVALENCE
```

## FASE 12 — Live controlado

Somente após aprovação explícita.

Guardrails:

```text
capital reduzido
limite por trade
limite diário
máximo de operações
máximo de exposição
kill switch
shadow paralelo
sem alteração automática de parâmetros
```

---

# 20. Gates de ativação

`READY` exige:

```text
all timeframe runs READY
zero errors
zero reconciliation residual
all hashes present
all deterministic comparisons pass
all no-future tests pass
all checkpoint tests pass
all counts match
```

`FROZEN` exige:

```text
READY
config congelada
git commit registrado
artifacts completos
```

`ACTIVE` exige:

```text
FROZEN
aprovação explícita
active pointer transacional
nenhum active incompatível
```

---

# 21. Relatórios obrigatórios

```text
environment.json
preflight.json
input_inventory.json
parameters.json
timeframe_report_<TF>.json
reconciliation_<TF>.json
determinism_<TF>.json
prefix_validation_<TF>.json
chunk_validation_<TF>.json
resume_validation_<TF>.json
opportunity_report.json
trade_report.json
metrics_report.json
rsi_ha_feature_report.json
rsi_ha_zone_reaction_report.json
rsi_ha_ab_test_report.json
rsi_ha_out_of_sample_report.json
final_gate.json
```

Todos deverão incluir:

```text
run_id
git commit
schema version
engine version
calculation version
parameter hash
generated_at
```

---

# 22. Critérios de aceite final

## Dataset

- seis timeframes completos;
- nenhuma truncagem;
- candles reconciliados;
- indicadores causais;
- RSI-Heikin Ashi causal e versionado, testado como feature experimental;
- SMC causal;
- lifecycle append-only;
- hashes determinísticos;
- zero órfãos;
- zero conflitos;
- zero residual.

## Replay

- candle a candle;
- sem futuro;
- MTF sincronizado;
- Opportunity Engine real;
- decisão imutável;
- resume determinístico.

## Estratégia

- métricas completas;
- comparação CONTROL_A/CANDIDATE_B/CANDIDATE_C;
- análise do RSI-HA por zona e gatilho;
- custos incluídos;
- intrabar tratado;
- out-of-sample;
- análise por regime;
- shadow live;
- rollover explicitado.

## Produção

- kill switch;
- limites;
- logs;
- alertas;
- rollback;
- operação controlada.

---

# 23. Operações proibidas até liberação

Não executar:

```text
causal_rebuild_v1.py
runners M2 legados
rebuild_winfut_causal_v3.py sample/start/worker/full/freeze
rebuild_winfut_live_replay_dataset.py start/worker/freeze
migration V3 destrutiva
TRUNCATE
DELETE de legado
ativação
backtest definitivo
operação real
```

Também é proibido:

- corrigir diretamente tabelas legadas;
- reaproveitar IDs legados;
- preencher campos causais por inferência sem prova;
- marcar run parcial como READY;
- usar `INSERT IGNORE` para ocultar conflito;
- engolir exceções;
- alterar parâmetros durante o run.

---

# 24. Sequência operacional atual após V4_03

As etapas de ambiente, contratos, CandleClock, indicadores básicos, schema apply e probe transacional já foram concluídas.

Próxima sequência:

```text
1. auditar o schema físico e mappings existentes;
2. consolidar repositories V4;
3. implementar transação atômica de chunk;
4. implementar checkpoint e resume físicos;
5. implementar RSI_HEIKIN_ASHI_V1;
6. integrar todos os indicadores ao registry genérico;
7. executar repository-selftest com rollback;
8. executar fault injection em tabelas V4;
9. executar dry-run D1 limitado;
10. persistir um sample D1 somente de indicadores, com limite explícito;
11. validar contagens, hashes, idempotência e reconciliação;
12. invalidar o run de integração e impedir ativação;
13. somente depois iniciar a implementação SMC causal.
```

Limite desta próxima fase:

```text
sem rebuild integral
sem SMC causal ainda
sem Opportunity Engine
sem backtest
sem freeze
sem active run
```

---

# 25. Definição de concluído

O projeto V4 será considerado tecnicamente concluído quando:

```text
histórico integral reconstruído
D1/H4/H1/M15/M5/M2 aprovados
dataset causal congelado
replay live-like reproduzível
Opportunity Engine avaliado
métricas e relatórios completos
shadow live aprovado
gates de risco aprovados
```


## 25.1 Resultado esperado do experimento RSI-Heikin Ashi

O plano não assume previamente que o indicador melhorará a estratégia.

O resultado final deverá classificar a feature como uma das opções:

```text
REJECTED
INFORMATIVE_ONLY
SCORE_MODIFIER_APPROVED
TRIGGER_CONFIRMATION_APPROVED
SHADOW_ONLY
```

A classificação deverá ser fundamentada em replay causal, out-of-sample e shadow live.


A entrada em mercado real exige uma decisão separada baseada em:

```text
qualidade estatística
risco
estabilidade operacional
dados suficientes
rollover
shadow live
aprovação humana
```

---

# 26. Registro da atualização 1.1

Esta versão adiciona o estudo causal do indicador `RSI_HEIKIN_ASHI_V1` para verificar se ele melhora:

```text
reação em Order Blocks
reação em FVGs
reação em BPRs
gatilhos após sweep de liquidez
timing M2 após setup M5
qualidade de promoção MONITORAR → PRONTO
```

Também estabelece:

- fórmula canônica;
- correção de divisão por zero do RSI;
- warm-up integral;
- features persistidas;
- exclusão do bloco de exaustão não reproduzível;
- testes causais;
- evidence bundle;
- variantes CONTROL_A/B/C;
- métricas por zona;
- out-of-sample;
- proibição de promoção automática.

A inclusão do indicador é experimental. Nenhuma regra operacional será promovida sem evidência estatística e validação shadow.

---

# 27. Registro da atualização 1.2

A versão 1.2 sincroniza o plano com os relatórios executados das Fases V4_02 e V4_03.

Atualizações principais:

```text
pytest instalado e validado
51 testes V4 aprovados
CandleClock concluído
indicadores básicos concluídos
18 tabelas V4 aplicadas
schema validado por information_schema
transaction probe aprovado
rollback real sem resíduos
commit V4_02 registrado
commit V4_03 registrado
```

Também corrige estados desatualizados do plano:

- remove o bloqueio antigo de `pytest`;
- registra que o schema já foi aplicado;
- registra isolamento por namespace, não por database separado;
- divide persistência em FASE 3A concluída e FASE 3B pendente;
- divide indicadores em núcleo concluído e RSI-Heikin Ashi pendente;
- define como próxima etapa repositories, chunk/checkpoint, RSI-HA e primeiro sample persistido somente de indicadores.

Risco operacional mantido:

```text
schema V4 reside no banco principal do projeto
isolamento depende do namespace winfut_lr_v4_*
```

Mitigação obrigatória:

```text
repositories com allowlist de tabelas
zero SQL dinâmico não validado
zero escrita fora do namespace
testes de rollback
fault injection
run de integração nunca ativo
```
