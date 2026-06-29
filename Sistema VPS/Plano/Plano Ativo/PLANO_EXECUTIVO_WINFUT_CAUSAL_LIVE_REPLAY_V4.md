# PLANO EXECUTIVO DEFINITIVO — WINFUT CAUSAL LIVE-REPLAY V4

**Projeto:** SMC Trader System 7.0  
**Branch:** `feature/winfut-causal-live-replay-v4`  
**Versão:** 1.0  
**Data-base:** 27/06/2026  
**Status:** pronto para execução por fases

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
10. validar em replay histórico;
11. validar em shadow live;
12. somente depois considerar live real controlado.

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

## 3.9 Ambiente de testes

Os contratos V4 criados compilaram:

```text
PY_COMPILE=PASS
```

Os testes não executaram porque o ambiente não possui `pytest`:

```text
No module named pytest
```

Isso é uma falha de dependência do ambiente, não uma falha comprovada dos contratos.

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

---

# 10. Indicadores canônicos

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

## FASE 0 — Ambiente e repositório

Entregas:

```text
pytest executando
manifests atualizados
arquivos de auditoria classificados
bridge web desativado em modo V4
relatório de ambiente
```

Gate:

```text
ENVIRONMENT_READY = PASS
```

## FASE 1 — Contratos e CandleClock

Entregas:

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

Gate:

```text
CONTRACTS_PASS
NO_LOCAL_INDEX_IDENTITY
NO_FUTURE_FIELDS
```

## FASE 2 — Schema V4

Gate:

```text
SCHEMA_ISOLATED
LEGACY_TABLES_UNCHANGED
CONSTRAINTS_PASS
```

## FASE 3 — Persistência transacional

Gate:

```text
PARTIAL_CHUNK_ROWS = 0
RESUME_DETERMINISTIC
CONFLICT_ABORTS_RUN
```

## FASE 4 — Indicadores

Gate:

```text
INDICATOR_PREFIX_INVARIANCE
INDICATOR_CHUNK_INVARIANCE
INDICATOR_AVAILABLE_AT
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

# 24. Primeira sequência operacional

```text
1. corrigir ambiente de testes;
2. executar contratos já criados;
3. corrigir eventuais falhas;
4. criar CandleClock;
5. criar testes temporais;
6. criar schema V4;
7. criar persistência transacional;
8. implementar indicadores;
9. implementar SMC causal;
10. criar samples;
11. executar rebuild;
12. executar replay/backtest.
```

O erro atual:

```text
No module named pytest
```

será resolvido de forma controlada após inspeção dos manifests do projeto.

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
