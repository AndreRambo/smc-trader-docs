# PLANO OPERACIONAL COMPLETO — CORREÇÃO DA PREVIOUS HIGH/LOW ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Módulo atual:** `previous_high_low.py`  
**Versão-alvo:** Previous Period Levels Engine V3  
**Escopo:** máximas e mínimas do período anterior, disponibilidade temporal, testes, sweeps, consumo e visualização  
**Modo de execução:** causal, incremental, anti-lookahead, auditável e `shadow_only` até aprovação  
**Dependência obrigatória:** Sessions & Market Calendar Engine V3  
**Objetivo:** substituir o resampling genérico e a lógica binária de `broken_high`/`broken_low` por uma engine canônica de níveis de períodos anteriores, alinhada ao calendário real do mercado e capaz de distinguir toque, wick sweep, close-through, gap-through, consumo e reclaim.

---

# 1. OBJETIVO GERAL

Construir uma Previous Period Levels Engine V3 que:

- determine corretamente o período de referência anterior;
- alinhe dias, semanas, meses, sessões e períodos customizados ao calendário do mercado;
- não invente timestamps quando eles estiverem ausentes;
- não utilize períodos parciais sem marcação explícita;
- torne PDH/PDL, PWH/PWL, PMH/PML e níveis customizados disponíveis apenas após o fechamento do período de referência;
- diferencie origem do nível, período de observação e evento de rompimento;
- diferencie toque, wick sweep, close-through, gap-through, consumo e reclaim;
- mantenha histórico imutável;
- gere eventos idempotentes;
- funcione igualmente em batch, replay e streaming;
- integre-se à Liquidity Engine V3 e à Sessions Engine V3;
- permaneça em shadow até validação completa.

---

# 2. DIAGNÓSTICO DO CÓDIGO ATUAL

A implementação atual apresenta os seguintes riscos:

1. cria um `DatetimeIndex` sintético de 5 minutos quando timestamps não são fornecidos;
2. utiliza `pandas.resample()` sem calendário de mercado;
3. depende dos defaults de `label`, `closed`, `origin` e `offset`;
4. não separa timezone de entrada, timezone do período e timezone de exibição;
5. pode usar como referência um primeiro bucket parcial iniciado no meio do período;
6. chama o período anterior de “second-to-last”, embora use `pos - 1`;
7. sobrecarrega `current_period_start` e `current_period_end` com dados do período de referência;
8. usa `pd.Timedelta(time_frame)`, inadequado para períodos calendáricos variáveis, como mês;
9. faz busca O(n × p) varrendo todos os períodos para cada candle;
10. possui fallback perigoso para o último período quando nenhum bucket é encontrado;
11. não valida timestamps duplicados ou desalinhados;
12. usa `index.get_loc()` sem tratar duplicatas;
13. trata qualquer wick além do nível como “broken”;
14. não diferencia wick sweep de fechamento além do nível;
15. não diferencia gap-through;
16. pode marcar high e low como rompidos no mesmo candle sem classificar ambiguidade;
17. não mede profundidade da incursão;
18. não registra o primeiro toque;
19. não registra reclaim;
20. reseta flags por período, mas não persiste eventos individuais;
21. conta candles com flag verdadeira, e não eventos únicos;
22. usa labels fixos `PDH` e `PDL` mesmo para semana, mês, H4 ou período customizado;
23. deduplica overlays somente pelo preço, colapsando períodos diferentes com o mesmo valor;
24. não define `x0` e `x1` reais para as linhas;
25. pode usar o status de quebra de um registro arbitrário ao desenhar o nível;
26. não possui IDs estáveis de período, nível e evento;
27. não trata feriados, early close, sessões overnight, leilões ou rollover;
28. não distingue período completo, parcial ou afetado por falha de dados;
29. não mantém um registro resumido e imutável de cada período concluído;
30. não possui processamento incremental canônico.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Timestamps reais são obrigatórios

É proibido gerar datas sintéticas para uso canônico.

Na ausência de timestamp:

```text
status = BLOCKED_INVALID_INPUT
```

O modo sintético só poderá existir em testes explicitamente marcados.

## 3.2. Calendário precede resampling

Períodos devem ser construídos pela Sessions & Market Calendar Engine V3.

Não usar apenas relógio civil genérico para definir:

- pregão;
- trading day;
- semana operacional;
- mês operacional;
- sessão overnight;
- early close;
- feriado.

## 3.3. Disponibilidade causal

O high/low de um período só fica disponível após o fechamento confirmado desse período.

```text
level_available_index >= reference_period_completed_index
```

## 3.4. Referência e observação são entidades distintas

A engine deve separar:

```text
reference_period
observation_period
```

Exemplo:

```text
reference_period = pregão anterior
observation_period = pregão atual
```

## 3.5. Nível e evento são entidades distintas

O nível PDH existe antes de ser tocado ou rompido.

Eventos posteriores devem ser persistidos separadamente.

## 3.6. Histórico imutável

Nenhum período, nível ou evento confirmado pode ser apagado ou reescrito silenciosamente.

## 3.7. Shadow-only

Durante implantação:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
production_truth_replaced = false
llm_decision_used = false
```

---

# 4. ESCOPO FUNCIONAL

A engine V3 deve suportar:

- Previous Session High/Low;
- Previous Trading Day High/Low;
- Previous Week High/Low;
- Previous Month High/Low;
- Previous Quarter High/Low, se habilitado;
- Previous Year High/Low, se habilitado;
- previous custom anchored period;
- previous H1/H4/custom intraday block;
- nível ativo;
- primeiro toque;
- wick sweep;
- close-through;
- gap-through;
- consumo;
- reclaim;
- expiração;
- período parcial;
- período afetado por data gap;
- período afetado por rollover;
- eventos BSL/SSL derivados;
- integração com Liquidity Engine V3.

---

# 5. FORA DO ESCOPO

Não implementar nesta fase:

- decisão automática de compra/venda;
- promoção de sinal;
- alteração de Risk Management;
- calibração definitiva de estratégia;
- remoção da implementação V2;
- cálculo de sessões sem a Sessions Engine V3;
- uso de LLM para interpretar rompimentos.

---

# 6. ARQUITETURA-ALVO

## 6.1. `PreviousPeriodInputValidator`

Valida OHLC, timestamps, calendário, timezone, períodos e configuração.

## 6.2. `PeriodInstanceProvider`

Consome instâncias de período produzidas pela Sessions Engine V3.

## 6.3. `CompletedPeriodAggregator`

Agrega OHLC e metadados apenas de períodos concluídos.

## 6.4. `PreviousPeriodLevelResolver`

Associa o período concluído anterior ao período de observação atual.

## 6.5. `PreviousPeriodEventDetector`

Detecta toque, sweep, close-through, gap-through, consumo e reclaim.

## 6.6. `PreviousPeriodStateMachine`

Gerencia lifecycle de cada nível.

## 6.7. `PreviousPeriodPersistenceV3`

Persiste períodos, níveis, eventos e estado incremental.

## 6.8. `PreviousPeriodOverlayAdapterV3`

Gera linhas e labels com âncoras temporais corretas.

## 6.9. `LegacyPreviousHighLowAdapter`

Preserva comportamento V2 apenas para paridade.

---

# 7. CONTRATOS DE DADOS

## 7.1. `CompletedPeriodV3`

```text
period_id
asset
timeframe
period_type
calendar_id
session_profile_id

period_start
period_end
completed_at

open
high
low
close
volume
bar_count

first_candle_index
last_candle_index

is_complete
is_partial_at_dataset_start
is_partial_at_dataset_end
is_cross_session
is_data_gap_affected
is_rollover_affected
continuity_status

engine_version
config_hash
raw
```

## 7.2. `PreviousPeriodLevelV3`

```text
level_id
asset
timeframe

level_kind
level_side
label

reference_period_id
observation_period_id

price
reference_period_start
reference_period_end
reference_period_completed_at

available_index
available_at
earliest_execution_index
earliest_execution_at

status

first_touch_index
first_touch_at
wick_sweep_index
wick_sweep_at
close_through_index
close_through_at
gap_through_index
gap_through_at
consumed_index
consumed_at
reclaimed_index
reclaimed_at
expired_index
expired_at

max_penetration_pts
max_penetration_ticks
max_penetration_atr

is_reference_period_complete
is_data_gap_affected
is_rollover_affected

engine_version
config_hash
run_id
replay_id
raw
```

## 7.3. `PreviousPeriodLevelEventV3`

```text
event_id
level_id
event_type
event_status

event_index
event_at
confirmed_index
available_index
earliest_execution_index

open
high
low
close
price

penetration_pts
penetration_ticks
penetration_atr

close_back_inside
close_beyond_level
same_bar_dual_break
gap_event

previous_level_status
new_level_status

structure_event_id
liquidity_event_id

engine_version
config_hash
raw
```

## 7.4. `PreviousPeriodEngineStateV3`

```text
state_id
asset
timeframe
period_type
last_processed_index
last_processed_at
current_observation_period_id
active_level_ids
version
config_hash
```

---

# 8. ENUMS OBRIGATÓRIOS

## 8.1. Period type

```text
SESSION
TRADING_DAY
WEEK
MONTH
QUARTER
YEAR
INTRADAY_BLOCK
CUSTOM
```

## 8.2. Level kind

```text
PREVIOUS_SESSION_HIGH
PREVIOUS_SESSION_LOW
PREVIOUS_DAY_HIGH
PREVIOUS_DAY_LOW
PREVIOUS_WEEK_HIGH
PREVIOUS_WEEK_LOW
PREVIOUS_MONTH_HIGH
PREVIOUS_MONTH_LOW
PREVIOUS_CUSTOM_HIGH
PREVIOUS_CUSTOM_LOW
```

## 8.3. Level side

```text
BSL
SSL
```

## 8.4. Level status

```text
UNAVAILABLE
ACTIVE
TESTED
WICK_SWEPT
CLOSE_THROUGH
GAP_THROUGH
CONSUMED
RECLAIMED
EXPIRED
INVALIDATED
AMBIGUOUS
```

## 8.5. Event type

```text
LEVEL_ACTIVATED
FIRST_TOUCH
WICK_SWEEP
CLOSE_THROUGH
GAP_THROUGH
CONSUMPTION
RECLAIM
EXPIRATION
INVALIDATION
DUAL_BREAK_AMBIGUOUS
```

## 8.6. Continuity

```text
CONTINUOUS
SESSION_BOUNDARY
DATA_GAP
ROLLOVER
PARTIAL_PERIOD
UNKNOWN
```

---

# 9. FASE 0 — AUDITORIA E BASELINE

## Tarefas

1. localizar consumidores de:
   - `calculate_previous_high_low`;
   - `PreviousHighLowV2`;
   - `previous_high`;
   - `previous_low`;
   - `broken_high`;
   - `broken_low`;
   - `period_start`;
   - `period_end`.

2. mapear:
   - Liquidity Engine;
   - Sessions;
   - dashboard;
   - scanner;
   - contextual;
   - backtest;
   - persistência;
   - APIs.

3. congelar baseline por ativo/timeframe/período.

4. criar:

```text
AUDITORIA_DEPENDENCIAS_PREVIOUS_PERIOD_LEVELS_V2.md
```

5. criar feature flags:

```text
PREVIOUS_PERIOD_ENGINE_MODE=legacy|shadow_v3|v3
PREVIOUS_PERIOD_V3_WRITE_ENABLED=false
PREVIOUS_PERIOD_V3_SIGNAL_ENABLED=false
PREVIOUS_PERIOD_V3_OVERLAY_ENABLED=false
```

## Critérios de aceite

- baseline reproduzível;
- nenhum comportamento de produção alterado;
- consumidores mapeados;
- feature flags testadas.

---

# 10. FASE 1 — VALIDAÇÃO DE ENTRADA

## Validações obrigatórias

- timestamps obrigatórios;
- `DatetimeIndex` único e crescente;
- OHLC válido;
- tamanho de OHLC igual ao de timestamps;
- timezone explícito;
- calendar profile válido;
- period type válido;
- `price_tick > 0`;
- ausência de NaN não tratado;
- contrato vigente identificável quando aplicável.

## Proibição

Remover do modo canônico:

```text
pd.date_range(start="2000-01-01", ...)
```

## Critérios de aceite

- entrada inválida gera erro explícito;
- nenhum índice sintético;
- nenhum fallback para período arbitrário.

---

# 11. FASE 2 — DEPENDÊNCIA DA SESSIONS ENGINE V3

## Objetivo

Eliminar o resampling civil genérico como verdade operacional.

## Tarefas

1. consumir `PeriodInstanceV3`;
2. obter:
   - start;
   - end;
   - timezone;
   - calendar day;
   - completion status;
   - early close;
   - holiday;
   - session ID;
3. permitir fallback de `pandas.resample()` apenas em legacy;
4. documentar ordem de inicialização.

## Critérios de aceite

- trading day B3 não depende de meia-noite genérica;
- overnight session é atribuída ao trading date correto;
- early close é respeitado.

---

# 12. FASE 3 — AGREGAÇÃO DE PERÍODOS CONCLUÍDOS

## Objetivo

Criar registros imutáveis de períodos.

## Regras

- agregar somente candles pertencentes ao `period_id`;
- marcar período incompleto;
- não usar período parcial como referência canônica por padrão;
- completar período apenas quando a Sessions Engine emitir `COMPLETED`.

## Critérios de aceite

- primeiro bucket parcial é identificado;
- último bucket aberto não é tratado como concluído;
- high/low não mudam após completion.

---

# 13. FASE 4 — RESOLUÇÃO DO PERÍODO ANTERIOR

## Regra

Para cada `observation_period_id`:

```text
reference_period =
último período completo imediatamente anterior
compatível com asset, calendar e period_type
```

## Proibições

- não usar “último índice disponível” como fallback;
- não pular períodos sem registrar motivo;
- não usar período futuro.

## Critérios de aceite

- referência determinística;
- `reference_period_id` explícito;
- lacunas de calendário tratadas.

---

# 14. FASE 5 — DISPONIBILIDADE DOS NÍVEIS

## Regra

```text
available_at =
max(reference_period.completed_at,
    observation_period.start)
```

```text
available_index =
primeiro candle elegível do período de observação
```

Para execução conservadora:

```text
earliest_execution_index =
available_index + configured_offset
```

## Critérios de aceite

- PDH/PDL não aparecem durante o dia de referência;
- níveis ficam ativos no início correto do novo período;
- replay parcial é causal.

---

# 15. FASE 6 — LABELS E IDENTIDADE

## Labels obrigatórios

```text
PSH / PSL
PDH / PDL
PWH / PWL
PMH / PML
PQH / PQL
PYH / PYL
P{CUSTOM}H / P{CUSTOM}L
```

## Regras

- label deriva de `level_kind`;
- IDs não dependem apenas do preço;
- dois períodos com mesmo preço geram níveis diferentes.

## Critérios de aceite

- overlays não colapsam níveis iguais de períodos distintos;
- labels corretos por período.

---

# 16. FASE 7 — DETECTOR DE EVENTOS

## 16.1. First touch

Candle intersecta o nível pela primeira vez.

## 16.2. Wick sweep do high

```text
high > level + sweep_buffer
close <= level
```

## 16.3. Wick sweep do low

```text
low < level - sweep_buffer
close >= level
```

## 16.4. Close-through do high

```text
close > level + close_buffer
```

## 16.5. Close-through do low

```text
close < level - close_buffer
```

## 16.6. Gap-through

Abertura ocorre além do nível sem cruzamento contínuo.

## 16.7. Dual break

Mesmo candle rompe high e low anteriores.

Classificar:

```text
DUAL_BREAK_AMBIGUOUS
```

até análise intrabar ou timeframe inferior.

## Critérios de aceite

- wick e close separados;
- gap separado;
- dual break não é resolvido arbitrariamente.

---

# 17. FASE 8 — LIFECYCLE

Fluxo:

```text
UNAVAILABLE
→ ACTIVE
→ TESTED
```

A partir de `TESTED` ou `ACTIVE`:

```text
WICK_SWEPT
CLOSE_THROUGH
GAP_THROUGH
CONSUMED
RECLAIMED
EXPIRED
INVALIDATED
AMBIGUOUS
```

## Critérios de aceite

- toda transição gera evento;
- status anterior preservado;
- reinício não duplica transições.

---

# 18. FASE 9 — PENETRAÇÃO E REAÇÃO

## Métricas

```text
penetration_pts
penetration_ticks
penetration_atr
max_penetration
close_distance_from_level
reaction_mfe
reaction_mae
bars_to_reaction
```

## Critérios de aceite

- sweep mínimo e rompimento forte distinguíveis;
- métricas persistidas;
- cálculo causal.

---

# 19. FASE 10 — RELAÇÃO COM LIQUIDITY ENGINE V3

## Mapeamento

```text
Previous High → BSL
Previous Low  → SSL
```

## Campos

```text
liquidity_pool_id
liquidity_event_id
source_level_id
```

## Regras

- Previous Period Engine cria a fonte;
- Liquidity Engine gerencia pool/contexto;
- não duplicar eventos.

## Critérios de aceite

- IDs cruzados;
- um único evento canônico;
- side correto.

---

# 20. FASE 11 — SESSÃO, GAP, ROLLOVER E CONTRATO

## Configuração

```text
allow_partial_reference_period
allow_cross_contract_reference
allow_rollover_reference
allow_data_gap_reference
```

## Regras

- cross-contract bloqueado por padrão;
- rollover marcado;
- período com data gap marcado;
- referência parcial não promovida sem flag.

## Critérios de aceite

- WINFUT contínuo não mistura contratos silenciosamente;
- motivos de bloqueio persistidos.

---

# 21. FASE 12 — PROCESSAMENTO INCREMENTAL

Fluxo:

```text
novo candle fechado
→ identificar period_id
→ atualizar período corrente
→ receber completion event
→ congelar período
→ ativar níveis no próximo período
→ verificar eventos
→ persistir
→ checkpoint
```

## Critérios de aceite

```text
batch == incremental
```

Incluindo:

- períodos;
- níveis;
- eventos;
- status;
- timestamps.

---

# 22. FASE 13 — PERFORMANCE

## Objetivo

Eliminar busca O(n × p).

## Estratégia

- mapear candles diretamente para `period_id`;
- usar join/index lookup;
- manter período corrente em estado;
- evitar loop sobre todos os períodos para cada candle.

## Critérios de aceite

- complexidade linear ou quase linear;
- benchmark documentado;
- sem regressão funcional.

---

# 23. FASE 14 — PERSISTÊNCIA E VERSIONAMENTO

## Tabelas sugeridas

```text
completed_periods_v3
previous_period_levels_v3
previous_period_level_events_v3
previous_period_engine_state_v3
```

## Regras

- V2 preservada;
- V3 separada;
- `engine_version`;
- `config_hash`;
- `run_id`;
- `replay_id`;
- migração reversível.

---

# 24. FASE 15 — OVERLAYS

## Regras

- linha começa no início do período de observação;
- linha termina em:
  - evento terminal;
  - fim do período;
  - candle atual, se ativa;
- não deduplicar apenas por preço;
- usar `level_id`;
- label no ponto correto;
- estilo diferente por status.

## Critérios de aceite

- PDH/PDL ativos continuam visíveis;
- períodos iguais em preço não se fundem;
- x0/x1 reais;
- status correto no tooltip.

---

# 25. FASE 16 — TESTES UNITÁRIOS

Casos mínimos:

- primeiro período parcial;
- segundo período completo;
- dia normal;
- overnight;
- semana com feriado;
- mês variável;
- early close;
- PDH touch;
- PDH wick sweep;
- PDH close-through;
- PDL wick sweep;
- PDL close-through;
- gap-through;
- dual break;
- reclaim;
- rollover;
- data gap;
- timestamps duplicados;
- batch/incremental;
- restart/idempotência.

---

# 26. FASE 17 — TESTES DE PROPRIEDADE

Invariantes:

1. nível nunca disponível antes do fim do período de referência;
2. reference period é anterior ao observation period;
3. high do período é maior ou igual a todos os highs membros;
4. low do período é menor ou igual a todos os lows membros;
5. wick sweep e close-through não são o mesmo evento;
6. ID não depende só do preço;
7. período incompleto não vira referência canônica por padrão;
8. batch e incremental são equivalentes;
9. restart não duplica eventos;
10. same-price periods permanecem distintos.

---

# 27. FASE 18 — REPLAY SHADOW

Ativos mínimos:

- WINFUT;
- WDOFUT;
- Forex 24h;
- ação B3;
- ativo com sessão overnight.

Períodos mínimos:

- session;
- day;
- week;
- month;
- H4/custom.

Métricas:

- níveis criados;
- níveis bloqueados por período parcial;
- touches;
- wick sweeps;
- close-through;
- gap-through;
- dual breaks;
- divergência V2/V3;
- eventos antecipados pela V2;
- impacto na Liquidity Engine.

---

# 28. FASE 19 — MIGRAÇÃO CONTROLADA

## Etapa 1

```text
PREVIOUS_PERIOD_ENGINE_MODE=legacy
```

## Etapa 2

```text
PREVIOUS_PERIOD_ENGINE_MODE=shadow_v3
```

## Etapa 3

Overlay V3 opcional.

## Etapa 4

Liquidity V3 consome níveis V3 em shadow.

## Etapa 5

Promoção futura somente após testes, replay e aprovação.

---

# 29. ROLLBACK

Criar:

```text
ROLLBACK_PREVIOUS_PERIOD_LEVELS_V3.md
```

O rollback deve:

- voltar ao legacy;
- interromper escrita V3;
- preservar dados V3;
- restaurar overlays V2;
- não apagar histórico.

---

# 30. OBSERVABILIDADE

Logs:

```text
previous_period.period_started
previous_period.period_completed
previous_period.partial_period_blocked
previous_period.level_activated
previous_period.level_touched
previous_period.wick_sweep
previous_period.close_through
previous_period.gap_through
previous_period.reclaimed
previous_period.expired
previous_period.rollover_blocked
previous_period.lookahead_blocked
previous_period.replay_divergence
```

Métricas:

- períodos concluídos;
- períodos parciais;
- níveis ativos;
- sweeps;
- close-through;
- gaps;
- reclaims;
- bloqueios;
- divergência V2/V3;
- tempo de processamento.

---

# 31. CONFIGURAÇÃO

```text
calendar_id
period_type
period_anchor
period_timezone
price_tick
atr_period
atr_method

sweep_buffer_ticks
sweep_buffer_atr
close_through_buffer_ticks
close_through_buffer_atr

earliest_execution_offset

allow_partial_reference_period
allow_cross_contract_reference
allow_rollover_reference
allow_data_gap_reference

require_contiguous_bars
```

Nada deve ser hardcoded por ativo.

---

# 32. ARQUIVOS ESPERADOS

```text
technical_engine/previous_period/
  __init__.py
  previous_period_models_v3.py
  previous_period_config_v3.py
  previous_period_input_validator.py
  completed_period_aggregator.py
  previous_period_level_resolver.py
  previous_period_event_detector.py
  previous_period_state_machine.py
  previous_period_engine_v3.py
  previous_period_persistence_v3.py
  previous_period_overlays_v3.py
  legacy_previous_high_low_adapter.py
```

Testes:

```text
tests/previous_period_v3/
  test_input_validation.py
  test_period_aggregation.py
  test_reference_resolution.py
  test_level_availability.py
  test_touch.py
  test_wick_sweep.py
  test_close_through.py
  test_gap_through.py
  test_dual_break.py
  test_reclaim.py
  test_partial_period.py
  test_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_anti_lookahead.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/PREVIOUS_PERIOD_LEVELS_V3.md
docs/migrations/PREVIOUS_PERIOD_LEVELS_V3_MIGRATION.md
docs/operations/ROLLBACK_PREVIOUS_PERIOD_LEVELS_V3.md
docs/reports/RELATORIO_FINAL_PREVIOUS_PERIOD_LEVELS_V3.md
```

---

# 33. CRITÉRIOS DE ACEITE GERAIS

1. nenhum timestamp sintético no modo canônico;
2. calendário operacional usado;
3. referência anterior correta;
4. período parcial marcado;
5. disponibilidade causal;
6. wick e close separados;
7. gap separado;
8. dual break ambíguo preservado;
9. IDs estáveis;
10. overlays por período;
11. labels corretos;
12. batch e incremental equivalentes;
13. restart idempotente;
14. V2 preservada;
15. rollback testado;
16. nenhuma promoção de trade;
17. relatório final entregue.

---

# 34. DEFINITION OF DONE

A engine estará pronta quando:

- código compilar;
- cobertura do core for no mínimo 90%;
- testes anti-lookahead passarem;
- períodos forem calendar-aware;
- batch e streaming forem equivalentes;
- V2 permanecer disponível;
- V3 estiver persistida separadamente;
- overlays comparativos funcionarem;
- integração com Liquidity V3 ocorrer apenas em shadow;
- rollback estiver documentado;
- relatório final estiver concluído.

---

# 35. RELATÓRIO FINAL OBRIGATÓRIO

Deve conter:

- resumo executivo;
- arquivos alterados;
- contratos e enums;
- migrations;
- testes;
- prova de anti-lookahead;
- comparativo V2/V3;
- impacto em Sessions e Liquidity;
- guardrails;
- rollback;
- riscos remanescentes.

Status final permitido:

```text
PREVIOUS_PERIOD_V3_COMPLETED_SHADOW
PREVIOUS_PERIOD_V3_COMPLETED_WITH_LIMITATIONS
PREVIOUS_PERIOD_V3_BLOCKED
PREVIOUS_PERIOD_V3_FAILED
```

---

# 36. ORDEM DE EXECUÇÃO

1. auditoria;
2. baseline;
3. feature flags;
4. Sessions Engine V3 pronta;
5. validação de entrada;
6. períodos concluídos;
7. resolução da referência;
8. disponibilidade;
9. labels e IDs;
10. eventos;
11. lifecycle;
12. penetração/reação;
13. integração Liquidity;
14. rollover/gaps;
15. incremental;
16. persistência;
17. overlays;
18. testes;
19. replay;
20. relatório final.

---

# 37. REGRAS PARA A IA DE CÓDIGO

1. Não inventar timestamps.
2. Não usar `resample()` genérico como verdade canônica.
3. Não usar período parcial sem marcação.
4. Não antecipar PDH/PDL.
5. Não misturar wick sweep com close-through.
6. Não deduplicar por preço.
7. Não usar labels PDH/PDL para todos os períodos.
8. Não apagar eventos.
9. Não hardcodar calendário.
10. Não promover sinais.
11. Não substituir V2 diretamente.
12. Não criar fallback silencioso.
13. Em ambiguidade, preservar e marcar `AMBIGUOUS`.
14. Toda decisão deve ser rastreável por IDs.
15. Toda mudança de contrato deve possuir migração.

---

# 38. RESULTADO ESPERADO

Ao final, a engine deverá responder:

- qual período foi usado como referência;
- se o período estava completo;
- quando o nível ficou disponível;
- qual é o PDH/PDL, PWH/PWL ou PMH/PML;
- se houve toque;
- se houve wick sweep;
- se houve close-through;
- se houve gap-through;
- se o nível foi consumido;
- se houve reclaim;
- qual pool de liquidez foi associado;
- se houve data gap ou rollover;
- qual é o estado atual do nível.

A engine final deve ser causal, calendar-aware, incremental, imutável e explicável.
