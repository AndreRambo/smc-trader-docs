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


# PLANO OPERACIONAL COMPLETO — CORREÇÃO DA SESSIONS & MARKET CALENDAR ENGINE V3

**Projeto:** SMC Trader System 7.0  
**Módulo atual:** `sessions.py`  
**Versão-alvo:** Sessions & Market Calendar Engine V3  
**Escopo:** sessões, kill zones, calendário de mercado, timezones, DST, fases de negociação, highs/lows e instâncias de período  
**Modo de execução:** causal, incremental, calendar-aware, auditável e `shadow_only` até aprovação  
**Objetivo:** substituir a lógica baseada apenas em horários fixos por uma engine profissional de sessões e calendário, capaz de lidar com timezone de entrada, timezone da sessão, DST, sessões overnight, feriados, early close, leilões, gaps de dados e múltiplas sessões sobrepostas.

---

# 1. OBJETIVO GERAL

Construir uma Sessions & Market Calendar Engine V3 que:

- separe timezone da fonte, timezone da sessão e timezone de exibição;
- utilize IANA timezones;
- trate DST corretamente;
- represente sessões como instâncias com IDs estáveis;
- use intervalos temporais semanticamente definidos;
- trate candles por horário de abertura, fechamento ou overlap;
- suporte sessões overnight;
- suporte feriados e early closes;
- suporte fases de mercado;
- identifique sessões parciais e dados ausentes;
- preserve highs/lows finais após o encerramento;
- gere resumos imutáveis de sessão;
- exponha membership por candle;
- suporte múltiplas sessões e kill zones simultâneas;
- alimente Previous Period, Liquidity e contextual;
- funcione em batch, replay e streaming com resultados idênticos;
- permaneça shadow-only até validação.

---

# 2. DIAGNÓSTICO DO CÓDIGO ATUAL

A implementação atual apresenta os seguintes problemas:

1. cria timestamps sintéticos de 5 minutos quando não há índice temporal;
2. usa um único parâmetro `time_zone` para funções diferentes;
3. confunde timezone dos candles com timezone da sessão;
4. as definições built-in são declaradas em UTC, mas são comparadas no timezone escolhido pelo usuário;
5. Londres, Nova York e Sydney usam horários UTC fixos e não acompanham DST local;
6. B3 é declarada em horário local dentro do mesmo dicionário de sessões UTC;
7. não existe `session_timezone` por definição;
8. não existe calendário de feriados;
9. não existe early close;
10. não existem fases como pre-open, continuous, auction e after-market;
11. o intervalo usa início e fim inclusivos;
12. candles exatamente no horário final podem ser incluídos indevidamente;
13. não há semântica para timestamp de abertura versus fechamento do candle;
14. não há política para candle que cruza a borda da sessão;
15. sessões overnight não possuem `trading_date` explícito;
16. dataset iniciado no meio da sessão faz o primeiro candle parecer o início real;
17. dataset encerrado no meio da sessão não marca sessão parcial;
18. `session_start` recebe o primeiro candle observado, não necessariamente o início programado;
19. `session_end` nunca é preenchido;
20. highs/lows são zerados ao sair da sessão e não há resumo persistido da sessão concluída;
21. não há `session_id`;
22. não há status de sessão;
23. não há detecção de data gap;
24. não há detecção de rollover;
25. não há suporte nativo a sessões sobrepostas;
26. uma chamada processa apenas uma definição;
27. não há validação robusta de `HH:MM`;
28. não há tratamento de horários ambíguos ou inexistentes em DST;
29. `index.get_loc()` pode falhar ou retornar slice com timestamps duplicados;
30. overlays usam o timestamp do último candle como fim, não o fim real do candle ou da sessão;
31. o agrupamento visual pode dividir ou unir sessões incorretamente;
32. uso de `or 0` e `or inf` em highs/lows é semanticamente frágil;
33. `annotations` é sempre vazio;
34. não existe separação entre running high/low e final high/low;
35. não existe open/close/volume/range da sessão;
36. não há processamento incremental persistente;
37. não há vínculo com trading day, week ou month operacional.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Timestamps reais e timezone explícito

No modo canônico, são obrigatórios:

```text
source_timezone
session_timezone
display_timezone
```

É proibido inventar datas.

## 3.2. IANA timezones

Usar identificadores como:

```text
America/Sao_Paulo
Europe/London
America/New_York
Asia/Tokyo
Australia/Sydney
UTC
```

Não usar offsets fixos como substituto de timezone.

## 3.3. Calendário de mercado

Sessões de exchange devem depender de calendário versionado, não apenas de horário fixo.

## 3.4. Intervalos half-open

Padrão recomendado:

```text
[start, end)
```

Isso evita dupla contagem na borda.

## 3.5. Semântica do candle explícita

Configurar:

```text
timestamp_represents = BAR_OPEN | BAR_CLOSE
membership_policy = OPEN_TIME | CLOSE_TIME | ANY_OVERLAP | FULLY_CONTAINED
```

## 3.6. Sessão como entidade

Cada ocorrência deve possuir `session_instance_id`.

## 3.7. Histórico imutável

Sessões concluídas e seus resumos não podem ser apagados.

## 3.8. Shadow-only

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

- exchange sessions;
- custom sessions;
- kill zones;
- sessões overnight;
- trading date;
- calendar date;
- timezone conversion;
- DST;
- feriados;
- early close;
- pre-open;
- opening auction;
- continuous trading;
- intraday break;
- closing auction;
- after-market;
- overlap de sessões;
- session running high/low;
- final session high/low;
- session open/close;
- session range;
- volume;
- session completion;
- partial session;
- data gap;
- rollover;
- session summaries;
- candle membership;
- trading day/week/month instances;
- integração com Previous Period e Liquidity.

---

# 5. FORA DO ESCOPO

Não implementar nesta fase:

- estratégia por kill zone;
- geração automática de entrada;
- promoção de trade;
- substituição imediata da V2;
- calendário obtido por LLM;
- inferência silenciosa de timezone;
- download automático de calendário sem versionamento.

---

# 6. ARQUITETURA-ALVO

## 6.1. `MarketCalendarRegistry`

Registra calendários e versões.

## 6.2. `SessionDefinitionRegistry`

Registra definições de sessões e kill zones.

## 6.3. `TimezoneNormalizer`

Converte source → session → display.

## 6.4. `SessionScheduleResolver`

Gera instâncias reais para cada data operacional.

## 6.5. `SessionMembershipResolver`

Associa candles a uma ou mais sessões.

## 6.6. `SessionAggregator`

Calcula running e final OHLCV/range.

## 6.7. `SessionLifecycleManager`

Gerencia scheduled, active, completed, partial e invalidated.

## 6.8. `TradingPeriodProvider`

Produz trading day, week, month e custom period instances.

## 6.9. `SessionPersistenceV3`

Persiste definições, instâncias, membership, eventos e estado.

## 6.10. `SessionOverlayAdapterV3`

Gera regiões e linhas corretas.

## 6.11. `LegacySessionsAdapter`

Preserva a versão atual para paridade.

---

# 7. CONTRATOS DE DADOS

## 7.1. `SessionDefinitionV3`

```text
session_definition_id
name
session_type
market
calendar_id

session_timezone
start_local_time
end_local_time
overnight

phase_type
parent_session_definition_id

valid_from
valid_to
version
source
is_active

metadata
```

## 7.2. `MarketCalendarDayV3`

```text
calendar_day_id
calendar_id
trading_date
calendar_date

is_trading_day
is_holiday
holiday_name
is_early_close

scheduled_open
scheduled_close

pre_open_start
auction_start
continuous_start
continuous_end
closing_auction_start
after_market_end

timezone
version
source
```

## 7.3. `SessionInstanceV3`

```text
session_instance_id
session_definition_id
calendar_id
asset
market

trading_date
calendar_date

scheduled_start
scheduled_end
actual_first_candle_at
actual_last_candle_at

status

open
high
low
close
volume
range_pts
range_ticks
bar_count

first_candle_index
last_candle_index

completed_at
available_index
available_at

is_partial_at_dataset_start
is_partial_at_dataset_end
is_data_gap_affected
is_rollover_affected
continuity_status

engine_version
config_hash
run_id
replay_id
raw
```

## 7.4. `SessionCandleMembershipV3`

```text
membership_id
session_instance_id
candle_index
candle_open_at
candle_close_at

active
overlap_start
overlap_end
overlap_duration_seconds
membership_policy

running_high
running_low
running_open
running_close
running_volume

engine_version
```

## 7.5. `SessionEventV3`

```text
event_id
session_instance_id
event_type
event_index
event_at
previous_status
new_status
reason
engine_version
raw
```

## 7.6. `SessionEngineStateV3`

```text
state_id
asset
timeframe
last_processed_index
last_processed_at
active_session_instance_ids
calendar_version
config_hash
```

---

# 8. ENUMS OBRIGATÓRIOS

## 8.1. Session type

```text
EXCHANGE
GLOBAL_MARKET
KILL_ZONE
CUSTOM
PHASE
```

## 8.2. Phase type

```text
PRE_OPEN
OPENING_AUCTION
CONTINUOUS
INTRADAY_BREAK
CLOSING_AUCTION
AFTER_MARKET
NONE
```

## 8.3. Status

```text
SCHEDULED
ACTIVE
COMPLETED
PARTIAL
DATA_GAP_AFFECTED
ROLLOVER_AFFECTED
INVALIDATED
CANCELLED
```

## 8.4. Event type

```text
SESSION_SCHEDULED
SESSION_STARTED
SESSION_COMPLETED
SESSION_MARKED_PARTIAL
DATA_GAP_DETECTED
ROLLOVER_DETECTED
EARLY_CLOSE_APPLIED
SESSION_INVALIDATED
```

## 8.5. Timestamp semantics

```text
BAR_OPEN
BAR_CLOSE
```

## 8.6. Membership policy

```text
OPEN_TIME
CLOSE_TIME
ANY_OVERLAP
FULLY_CONTAINED
```

## 8.7. Continuity

```text
CONTINUOUS
DATA_GAP
ROLLOVER
PARTIAL_START
PARTIAL_END
UNKNOWN
```

---

# 9. FASE 0 — AUDITORIA E BASELINE

## Tarefas

1. localizar consumidores de:
   - `calculate_sessions`;
   - `SessionV2`;
   - `active`;
   - `session_high`;
   - `session_low`;
   - `session_start`;
   - `session_end`.

2. mapear:
   - Previous High/Low;
   - Liquidity;
   - contextual;
   - dashboard;
   - scanner;
   - coletores;
   - backtest;
   - persistência;
   - API.

3. congelar baseline por sessão/ativo/timeframe.

4. criar:

```text
AUDITORIA_DEPENDENCIAS_SESSIONS_ENGINE_V2.md
```

5. criar feature flags:

```text
SESSIONS_ENGINE_MODE=legacy|shadow_v3|v3
SESSIONS_V3_WRITE_ENABLED=false
SESSIONS_V3_OVERLAY_ENABLED=false
MARKET_CALENDAR_V3_ENABLED=false
```

## Critérios de aceite

- consumidores mapeados;
- baseline reproduzível;
- produção inalterada;
- feature flags testadas.

---

# 10. FASE 1 — VALIDAÇÃO DE ENTRADA

## Validações

- timestamps obrigatórios;
- índice único e crescente;
- timezone da fonte obrigatório;
- OHLC válido;
- timeframe/bar duration conhecido;
- calendar ID válido;
- session definition válida;
- HH:MM validado;
- start/end coerentes;
- política de membership configurada;
- contrato/asset identificável.

## Proibição

Remover do modo canônico:

```text
pd.date_range(start="2000-01-01", ...)
```

## Critérios de aceite

- nenhum timestamp sintético;
- nenhum timezone inferido silenciosamente;
- erros explícitos.

---

# 11. FASE 2 — SEPARAÇÃO DE TIMEZONES

## Campos obrigatórios

```text
source_timezone
session_timezone
display_timezone
```

## Regras

1. interpretar timestamp no source timezone;
2. converter para session timezone;
3. resolver membership;
4. converter para display timezone apenas na apresentação.

## Critérios de aceite

- sessão London correta com input UTC ou BRT;
- sessão B3 correta com input UTC;
- nenhuma definição muda de significado ao trocar display timezone.

---

# 12. FASE 3 — DST

## Objetivo

Corrigir sessões sazonais.

## Tarefas

- usar timezone IANA;
- resolver transições DST;
- testar horário ambíguo;
- testar horário inexistente;
- versionar política;
- não usar UTC fixo para London/New York/Sydney quando a definição for local.

## Critérios de aceite

- horários locais permanecem estáveis;
- UTC varia quando necessário;
- testes nas datas de transição passam.

---

# 13. FASE 4 — MARKET CALENDAR

## Objetivo

Modelar trading days reais.

## Tarefas

- criar calendar registry;
- carregar feriados;
- carregar early closes;
- carregar dias sem pregão;
- suportar versão do calendário;
- criar adapter por mercado;
- permitir calendário estático versionado em testes.

## Guardrail

Calendário externo deve ser cacheado e versionado. A engine não pode depender de resposta remota em tempo real para reproduzir backtest.

## Critérios de aceite

- feriados não geram sessão normal;
- early close altera scheduled end;
- replay usa mesma versão histórica.

---

# 14. FASE 5 — DEFINIÇÕES DE SESSÃO

## Regras

Cada definição deve possuir:

```text
session_timezone
start_local_time
end_local_time
overnight
valid_from
valid_to
calendar_id
```

## Built-ins

Migrar definições atuais para perfis versionados.

Não misturar B3 local com sessões UTC no mesmo contrato sem timezone individual.

## Critérios de aceite

- cada sessão tem timezone próprio;
- definições são versionadas;
- alteração futura não reescreve histórico.

---

# 15. FASE 6 — INSTÂNCIAS DE SESSÃO

## Objetivo

Gerar uma ocorrência por trading date.

## Regras

- overnight recebe trading date explícito;
- scheduled start/end vêm do calendário;
- `session_instance_id` determinístico;
- dataset iniciado no meio da sessão marca partial start;
- dataset terminado antes do fim marca partial end.

## Critérios de aceite

- sessão overnight não é dividida incorretamente à meia-noite;
- partial sessions são identificadas;
- session start real não é confundido com primeiro candle observado.

---

# 16. FASE 7 — SEMÂNTICA DE BARRAS

## Configuração

```text
timestamp_represents
bar_duration
membership_policy
```

## Políticas

### OPEN_TIME

Candle pertence à sessão se seu open está dentro.

### CLOSE_TIME

Usa close timestamp.

### ANY_OVERLAP

Qualquer interseção temporal.

### FULLY_CONTAINED

Candle inteiro deve estar dentro.

## Padrão recomendado

Para candles intraday alinhados à abertura:

```text
timestamp_represents = BAR_OPEN
membership_policy = OPEN_TIME
session_interval = [start, end)
```

## Critérios de aceite

- candle na borda final não é contado duas vezes;
- candle que cruza boundary segue política explícita.

---

# 17. FASE 8 — MEMBERSHIP MÚLTIPLO

## Objetivo

Permitir sobreposição.

Exemplo:

- London session;
- London open kill zone;
- custom volatility window.

Um candle pode pertencer a múltiplas sessões.

## Critérios de aceite

- memberships independentes;
- nenhum campo único `active` limita o candle;
- APIs retornam lista de session IDs.

---

# 18. FASE 9 — AGREGAÇÃO CAUSAL

## Running fields

```text
running_open
running_high
running_low
running_close
running_volume
running_range
```

## Final fields

Congelados no completion:

```text
open
high
low
close
volume
range
bar_count
```

## Critérios de aceite

- running high/low evoluem causalmente;
- final high/low não mudam depois da conclusão;
- saída fora da sessão não apaga resumo.

---

# 19. FASE 10 — LIFECYCLE

Fluxo:

```text
SCHEDULED
→ ACTIVE
→ COMPLETED
```

Possíveis desvios:

```text
PARTIAL
DATA_GAP_AFFECTED
ROLLOVER_AFFECTED
INVALIDATED
CANCELLED
```

## Eventos

Toda transição gera `SessionEventV3`.

## Critérios de aceite

- `session_end` preenchido;
- completion explícito;
- restart idempotente.

---

# 20. FASE 11 — FASES DE MERCADO

## Objetivo

Modelar segmentos internos da sessão.

Fases possíveis:

```text
PRE_OPEN
OPENING_AUCTION
CONTINUOUS
INTRADAY_BREAK
CLOSING_AUCTION
AFTER_MARKET
```

## Regras

- fase é sub-sessão;
- fase possui parent session;
- high/low podem ser calculados por fase;
- calendário define exceções.

## Critérios de aceite

- leilão não é confundido com contínuo;
- Previous Period pode escolher qual fase compõe o pregão.

---

# 21. FASE 12 — DATA GAP E CONTINUIDADE

## Objetivo

Detectar sessões incompletas por falha de dados.

## Regras

- comparar timestamps esperados;
- considerar breaks programados;
- ignorar períodos oficialmente fechados;
- marcar missing bars;
- não completar sessão como íntegra se gap crítico existir.

## Campos

```text
expected_bar_count
actual_bar_count
missing_bar_count
data_completeness_pct
```

## Critérios de aceite

- gaps reais detectados;
- lunch break programado não vira data gap;
- qualidade da sessão disponível.

---

# 22. FASE 13 — ROLLOVER E CONTRATO

## Objetivo

Marcar mudança de contrato em futuros.

## Regras

- session instance carrega contract ID;
- não misturar OHLC de contratos diferentes sem política;
- rollover session marcada;
- downstream decide se pode usar.

## Critérios de aceite

- sessão cross-contract não é silenciosa;
- Previous Period recebe flag.

---

# 23. FASE 14 — TRADING PERIOD PROVIDER

## Objetivo

Produzir instâncias reutilizáveis para:

- trading day;
- week;
- month;
- custom block.

## Regras

- semana e mês baseados no trading calendar;
- overnight associado ao trading date;
- IDs estáveis;
- completion explícito.

## Critérios de aceite

- Previous Period Engine não precisa resample genérico;
- períodos são compartilhados entre módulos.

---

# 24. FASE 15 — PROCESSAMENTO INCREMENTAL

Fluxo:

```text
novo candle fechado
→ normalizar timezone
→ resolver calendar day
→ resolver session instances
→ criar memberships
→ atualizar running aggregates
→ detectar completion
→ congelar summary
→ persistir
→ checkpoint
```

## Critérios de aceite

```text
batch == incremental
```

Incluindo:

- memberships;
- session instances;
- highs/lows;
- status;
- summaries.

---

# 25. FASE 16 — PERSISTÊNCIA E VERSIONAMENTO

## Tabelas sugeridas

```text
market_calendars_v3
market_calendar_days_v3
session_definitions_v3
session_instances_v3
session_candle_memberships_v3
session_events_v3
session_engine_state_v3
```

## Regras

- V2 preservada;
- V3 separada;
- calendar version persistida;
- config hash;
- run/replay IDs;
- migração reversível.

---

# 26. FASE 17 — OVERLAYS

## Regiões

- x0 = scheduled/actual start conforme modo;
- x1 = scheduled/actual end;
- y0/y1 = final ou running range;
- status visual por complete/partial/gap.

## Linhas

- session high;
- session low;
- open;
- opcionalmente midpoint.

## Tooltip

```text
session_instance_id
session_name
trading_date
timezone
scheduled_start
scheduled_end
actual_start
actual_end
status
high
low
range
bar_count
completeness
```

## Critérios de aceite

- overnight desenhada como uma instância;
- fim correto;
- partial claramente indicada;
- sem uso de `or 0`/`inf`.

---

# 27. FASE 18 — INTEGRAÇÃO COM PREVIOUS PERIOD

A Sessions Engine deve fornecer:

```text
PeriodInstanceV3
CompletedPeriodV3
trading_date
completed_at
calendar_version
```

## Critérios de aceite

- PDH/PDL usa trading day real;
- PWH/PWL usa semana operacional;
- nenhum módulo recria calendário próprio.

---

# 28. FASE 19 — INTEGRAÇÃO COM LIQUIDITY

Fontes possíveis:

```text
SESSION_HIGH
SESSION_LOW
PREVIOUS_SESSION_HIGH
PREVIOUS_SESSION_LOW
```

## Regras

- Sessions cria níveis;
- Liquidity cria pools/eventos;
- IDs cruzados;
- sem duplicação.

---

# 29. FASE 20 — TESTES UNITÁRIOS

Casos obrigatórios:

- sessão normal;
- overnight;
- dataset inicia no meio;
- dataset termina no meio;
- candle no start;
- candle no end;
- candle que cruza boundary;
- BAR_OPEN;
- BAR_CLOSE;
- ANY_OVERLAP;
- DST start;
- DST end;
- horário ambíguo;
- feriado;
- early close;
- pre-open;
- auction;
- intraday break;
- múltiplas sessões;
- data gap;
- rollover;
- timestamps duplicados;
- batch/incremental;
- restart/idempotência.

---

# 30. FASE 21 — TESTES DE PROPRIEDADE

Invariantes:

1. scheduled start < scheduled end;
2. membership respeita política;
3. candle não entra pela borda final em intervalo half-open;
4. running high nunca diminui;
5. running low nunca aumenta;
6. final high/low congelam após completion;
7. completed session possui end;
8. partial session é marcada;
9. overnight possui um único trading date;
10. batch e incremental são equivalentes;
11. restart não duplica sessão;
12. display timezone não altera membership.

---

# 31. FASE 22 — REPLAY SHADOW

Mercados mínimos:

- B3;
- Forex;
- London;
- New York;
- Tokyo;
- sessão overnight.

Timeframes:

- M1;
- M2;
- M5;
- M15;
- H4.

Métricas:

- sessões detectadas;
- partial sessions;
- DST divergences;
- early closes;
- memberships;
- high/low divergência V2/V3;
- dados faltantes;
- impacto em Previous Period;
- impacto em Liquidity.

---

# 32. FASE 23 — MIGRAÇÃO CONTROLADA

## Etapa 1

```text
SESSIONS_ENGINE_MODE=legacy
```

## Etapa 2

```text
SESSIONS_ENGINE_MODE=shadow_v3
```

## Etapa 3

Overlay V3 opcional.

## Etapa 4

Previous Period V3 consome calendário/sessões V3 em shadow.

## Etapa 5

Promoção futura após testes, replay e revisão.

---

# 33. ROLLBACK

Criar:

```text
ROLLBACK_SESSIONS_ENGINE_V3.md
```

Deve permitir:

- voltar ao legacy;
- interromper escrita V3;
- preservar dados;
- restaurar overlay;
- selecionar calendar version anterior.

---

# 34. OBSERVABILIDADE

Logs:

```text
sessions.calendar_loaded
sessions.session_scheduled
sessions.session_started
sessions.session_completed
sessions.session_partial
sessions.data_gap_detected
sessions.rollover_detected
sessions.early_close_applied
sessions.dst_transition_handled
sessions.membership_created
sessions.lookahead_blocked
sessions.replay_divergence
```

Métricas:

- active sessions;
- completed sessions;
- partial sessions;
- data completeness;
- missing bars;
- DST adjustments;
- memberships;
- divergência V2/V3;
- tempo de processamento.

---

# 35. CONFIGURAÇÃO

```text
calendar_id
calendar_version
source_timezone
display_timezone

timestamp_represents
bar_duration
membership_policy

session_definitions
phase_definitions

require_contiguous_bars
allow_partial_session
allow_cross_contract_session

calendar_cache_path
calendar_source
```

Cada session definition deve conter seu próprio `session_timezone`.

---

# 36. ARQUIVOS ESPERADOS

```text
technical_engine/sessions/
  __init__.py
  session_models_v3.py
  session_config_v3.py
  session_input_validator.py
  market_calendar_registry.py
  session_definition_registry.py
  timezone_normalizer.py
  session_schedule_resolver.py
  session_membership_resolver.py
  session_aggregator.py
  session_lifecycle_manager.py
  trading_period_provider.py
  session_engine_v3.py
  session_persistence_v3.py
  session_overlays_v3.py
  legacy_sessions_adapter.py
```

Testes:

```text
tests/sessions_v3/
  test_input_validation.py
  test_timezone_conversion.py
  test_dst.py
  test_normal_session.py
  test_overnight_session.py
  test_boundaries.py
  test_membership_policies.py
  test_partial_sessions.py
  test_holidays.py
  test_early_close.py
  test_market_phases.py
  test_overlapping_sessions.py
  test_data_gaps.py
  test_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/SESSIONS_MARKET_CALENDAR_ENGINE_V3.md
docs/migrations/SESSIONS_ENGINE_V3_MIGRATION.md
docs/operations/ROLLBACK_SESSIONS_ENGINE_V3.md
docs/reports/RELATORIO_FINAL_SESSIONS_ENGINE_V3.md
```

---

# 37. CRITÉRIOS DE ACEITE GERAIS

1. nenhum timestamp sintético;
2. source/session/display timezones separados;
3. DST correto;
4. calendário versionado;
5. feriado e early close tratados;
6. intervalos half-open;
7. semântica de candle explícita;
8. overnight com trading date;
9. partial sessions marcadas;
10. session end preenchido;
11. summaries persistidos;
12. múltiplas sessões suportadas;
13. data gaps detectados;
14. rollover marcado;
15. batch e incremental equivalentes;
16. restart idempotente;
17. V2 preservada;
18. rollback testado;
19. nenhuma promoção de trade;
20. relatório final entregue.

---

# 38. DEFINITION OF DONE

A engine estará pronta quando:

- código compilar;
- cobertura do core for no mínimo 90%;
- testes de timezone/DST passarem;
- testes de boundary passarem;
- batch e streaming forem equivalentes;
- calendar version estiver persistida;
- sessões concluídas forem imutáveis;
- Previous Period V3 consumir a nova engine em shadow;
- overlays comparativos funcionarem;
- rollback estiver documentado;
- relatório final estiver concluído.

---

# 39. RELATÓRIO FINAL OBRIGATÓRIO

Deve conter:

- resumo executivo;
- calendários e versões;
- definições migradas;
- arquivos alterados;
- contratos/enums;
- migrations;
- testes;
- casos DST;
- casos early close;
- comparativo V2/V3;
- impacto em Previous Period e Liquidity;
- guardrails;
- rollback;
- riscos remanescentes.

Status final permitido:

```text
SESSIONS_V3_COMPLETED_SHADOW
SESSIONS_V3_COMPLETED_WITH_LIMITATIONS
SESSIONS_V3_BLOCKED
SESSIONS_V3_FAILED
```

---

# 40. ORDEM DE EXECUÇÃO

1. auditoria;
2. baseline;
3. feature flags;
4. validação;
5. separação de timezones;
6. DST;
7. calendar registry;
8. session definitions;
9. session instances;
10. semântica de barras;
11. membership múltiplo;
12. agregação;
13. lifecycle;
14. fases;
15. gaps;
16. rollover;
17. trading period provider;
18. incremental;
19. persistência;
20. overlays;
21. integração Previous Period;
22. integração Liquidity;
23. testes;
24. replay;
25. relatório final.

---

# 41. REGRAS PARA A IA DE CÓDIGO

1. Não inventar timestamps.
2. Não usar um único timezone para tudo.
3. Não usar offsets UTC fixos para sessões locais sazonais.
4. Não usar intervalo inclusivo dos dois lados.
5. Não assumir que timestamp é sempre bar open.
6. Não tratar primeiro candle observado como início real sem marcar partial.
7. Não zerar resumo ao sair da sessão.
8. Não deixar `session_end=None` em sessão concluída.
9. Não hardcodar feriados.
10. Não misturar calendários sem versionamento.
11. Não substituir V2 diretamente.
12. Não promover sinais.
13. Não criar fallback silencioso.
14. Em ambiguidade DST, aplicar política documentada e registrar.
15. Toda sessão deve ter ID estável.
16. Toda mudança de contrato deve possuir migração.

---

# 42. RESULTADO ESPERADO

Ao final, a engine deverá responder:

- qual sessão está ativa;
- em qual timezone ela é definida;
- qual trading date pertence;
- qual é o scheduled start/end;
- qual foi o actual first/last candle;
- se a sessão está completa ou parcial;
- qual é o running high/low;
- qual é o final high/low;
- qual é o open/close/volume/range;
- se houve data gap;
- se houve rollover;
- qual fase do mercado está ativa;
- quais sessões/kill zones sobrepõem o candle;
- quais períodos de trading day/week/month foram concluídos;
- quando os dados ficaram disponíveis para Previous Period e Liquidity.

A engine final deve ser calendar-aware, timezone-safe, DST-safe, incremental, imutável e explicável.

---
# SEÇÕES ESPECÍFICAS — SESSIONS ENGINE V3

## Ownership do Domínio (Confirmado)

Sessions é dona exclusiva de:
- `MarketCalendarDayV3`
- `SessionDefinitionV3`
- `SessionInstanceV3`
- `SessionCandleMembershipV3`
- `PeriodInstanceV3`
- `PeriodCompletedEventV3`
- `TradingPeriodSummaryV3`

**Regra:** Sessions é a única autoridade sobre calendário, trading date, sessão, DST e período concluído. Nenhuma outra engine pode criar `CompletedPeriodV3` ou `TradingPeriodSummaryV3` concorrente.

## Contratos Produzidos

| Contrato | Consumidor | Gate |
|---|---|---|
| `SessionInstanceV3` | Previous Period, Liquidity | G1 |
| `PeriodInstanceV3` | Previous Period | G1 |
| `TradingPeriodSummaryV3` | Previous Period | G1 |

## Contratos Consumidos

| Contrato | Produtor |
|---|---|
| Feed temporal, calendário, configuração de mercado | Infraestrutura |

## Gate de Entrada

G-1 (V3 Package Ready) — módulo V3 importável, baseline preservado.

## Gate de Saída

**G1 — Sessions Ready:** timezone/DST, calendar version, PeriodInstance, completion event, batch/stream parity, partial/gap/rollover flags.

## Dependências

- **Obrigatórias:** feed temporal, calendário de mercado, configuração de ativo
- **Não depende** de Swing, Structure, ou qualquer outra engine SMC

## Componentes Incrementais

- `incremental/components/sessions.py`
- `incremental/persistence/`

## Caminhos Batch

- `smc_engine_v3/sessions.py`

## Persistência V3

Tabelas sugeridas: `session_definitions_v3`, `session_instances_v3`, `session_candle_memberships_v3`, `session_events_v3`, `session_engine_state_v3`

## Proibição

- Previous Period não pode criar período concorrente
- Nenhuma re-criação de calendário por outra engine
