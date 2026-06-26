# PLANO_DATA_DRIVEN_APRENDIZADO_WINFUT_SMC_ELLIOTT_WYCKOFF_SINAIS_ENTRADAS_TP1_V1

**Projeto:** SMC Trader System 7.0 / `technical_engine`  
**Ativo inicial:** WINFUT (`asset_id=1`)  
**Versão:** V1.2 — metas do funil, conversão trade → TP1 e frequência  
**Data:** 2026-06-25  
**Status:** `PLANO_V1_2_ATUALIZADO`  
**Backup V1.1:** `PLANO_DATA_DRIVEN_PRE_V1_2_20260625_202300.md`  
**Plano arquitetural de referência:** `docs/planos/PLANO_OPERACIONAL_SMC_ENGINE_V2_REBUILD_LOCAL_OFICIAL_V3.md`  
**Dashboard oficial:** `http://127.0.0.1:8050/`

---

## 1. Objetivo executivo

Construir uma linha de pesquisa e validação data-driven específica para o comportamento do WINFUT, usando evidências provenientes de:

```text
SMC Engine V2 Local persisted shadow
Elliott computed_runtime_shadow/persisted shadow
Wyckoff computed_runtime_shadow/persisted shadow
Contextual Market Profile Shadow
market_candles canonical
indicadores disponíveis no momento da decisão
```

O sistema deverá:

```text
1. detectar muitas oportunidades potenciais ao longo do dia;
2. separar oportunidade de entrada real;
3. aprender quais zonas, gatilhos e combinações funcionam melhor no WINFUT;
4. descobrir qual TP1 é mais adequado por contexto;
5. transformar os achados em parâmetros versionados;
6. executar backtest temporal, walk-forward e testes de robustez;
7. validar os parâmetros em live shadow;
8. permanecer shadow-only até aprovação formal de uma fase futura.
```

Meta de pesquisa:

```text
Aumentar a quantidade de oportunidades úteis por dia,
mantendo entradas seletivas,
e tentar alcançar trade_to_tp1_rate >= 80%
em trades realmente ativados,
sem sacrificar expectancy, payoff, profit factor e controle de drawdown.
```

A meta de 80% é uma meta avançada de pesquisa, não uma afirmação antecipada de resultado.

---

## 2. Problema que este plano resolve

O walk-forward anterior, mesmo com 400 trials, não encontrou configuração com:

```text
>= 2 trades/dia
PF > 2
```

Melhor resultado informado:

```text
1,9 trades/dia
PF = 1,19
```

Isso indica que continuar alterando parâmetros sem compreender o comportamento real do ativo pode apenas ampliar overfitting.

A nova abordagem será:

```text
DADOS REAIS
  ↓
COMPORTAMENTO DAS ZONAS
  ↓
GATILHOS
  ↓
COMBINAÇÕES SMC + ELLIOTT + WYCKOFF
  ↓
MELHOR TP1 POR CONTEXTO
  ↓
PARÂMETROS CANDIDATOS
  ↓
BACKTEST TEMPORAL
  ↓
WALK-FORWARD
  ↓
LIVE SHADOW
```

---

## 3. Uso correto da análise preliminar já existente

Existe o relatório:

```text
ANALISE_ZONAS_WINFUT.md
```

Ele deve ser tratado como:

```text
EVIDÊNCIA EXPLORATÓRIA PRELIMINAR
```

e não como base suficiente para gerar parâmetros finais.

### 3.1 Achados preliminares úteis

Os resultados preliminares sugerem:

```text
Order Blocks M2:
  TP1 Hit aproximado = 70,3%
  respeito aproximado = 80,6%

Order Blocks M5:
  TP1 Hit aproximado = 67,4%
  respeito aproximado = 78,6%

Order Blocks M15:
  TP1 Hit aproximado = 62,8%

FVGs isoladas:
  TP1 Hit geralmente entre 25% e 31%

BOS:
  continuação informada entre 88% e 94%

CHOCH:
  reversão informada entre 67% e 76%

Volume e sessão:
  aparentam melhorar diversos grupos, especialmente FVG/OB intraday
```

Esses dados indicam que:

```text
OB pode ser uma base promissora de oportunidade;
BOS/CHOCH podem ser melhores como contexto/gatilho do que como zona;
FVG isolada provavelmente precisa de confluência;
volume e sessão podem ser filtros relevantes.
```

### 3.2 Problemas identificados no relatório preliminar

O relatório também revelou falhas que devem ser corrigidas antes de qualquer conclusão:

```text
BOS possui zero toques/respeito no bloco de zonas, mas possui continuação em outra seção;
Liquidity possui milhares de registros, porém reversão e continuação estão em zero;
D1 e H4 aparecem avaliados por "sessão ativa", o que não é semanticamente comparável ao intraday;
TP1 baseado em próximo swing pode usar swing ainda não confirmado;
volume foi comparado com média global, não necessariamente média causal/rolling;
não há auditoria de duplicatas entre runs;
não há separação train/validation/test;
não há Elliott/Wyckoff nas combinações;
não há intervalos de confiança;
não há modelagem de custos/slippage;
não há política explícita para candle que toca TP e stop na mesma barra;
não há auditoria do rollover do WINFUT;
```

Consequência:

```text
Nenhum percentual preliminar pode ser usado diretamente como regra de produção ou parâmetro final.
```

---

## 4. Resultado funcional desejado

O pipeline final deverá separar claramente:

```text
ZONA DETECTADA
    ↓
SINAL DE OBSERVAÇÃO
    ↓
OPORTUNIDADE MONITORADA
    ↓
OPORTUNIDADE QUALIFICADA
    ↓
TRADE READY
    ↓
ORDEM SIMULADA PREENCHIDA
    ↓
TP1 / STOP / EXPIRADO / NÃO PREENCHIDO
```

Estados públicos shadow:

```text
OBSERVAR
MONITORAR
QUALIFICADA
TRADE_READY
BLOQUEADA
EXPIRADA
```

Outputs operacionais existentes:

```text
ALTISTA / BAIXISTA / NEUTRO
COMPRA / VENDA / AGUARDAR
PRONTO / MONITORAR / BLOQUEADO
```

Regra:

```text
Mais sinais por dia deve significar mais oportunidades acompanhadas,
não obrigatoriamente mais entradas ruins.
```

---

## 5. Métricas principais

### 5.1 Funil de oportunidades

```text
zones_detected
signals_observe
opportunities_monitored
opportunities_qualified
trade_ready_count
orders_filled
trades_triggered
tp1_hits
stops
expired
no_fill
```

Conversões obrigatórias:

```text
zone_to_opportunity_rate
opportunity_to_qualified_rate
qualified_to_trade_ready_rate
trade_ready_to_fill_rate
trade_to_tp1_rate
opportunity_to_tp1_rate
```

### 5.2 Métricas financeiras e de risco

```text
expectancy_r
net_expectancy_r
profit_factor_gross
profit_factor_net
average_win_r
average_loss_r
payoff_ratio
maximum_drawdown_r
maximum_drawdown_currency
recovery_factor
maximum_losing_streak
trades_per_day
active_days
days_without_trade
negative_day_rate
MFE
MAE
time_to_tp1
time_to_stop
```

### 5.3 Métricas de estabilidade

```text
resultado por ano
resultado por mês
resultado por sessão
resultado por regime
resultado por volatilidade
resultado long vs short
resultado por timeframe
resultado por tipo de zona
resultado por gatilho
resultado por combinação
dependência dos 5 melhores dias
dependência dos 10 melhores trades
```

---

## 6. Metas de pesquisa

As metas abaixo são gates de pesquisa e não promessas.

### 6.1 Frequência

```text
Oportunidades observáveis:
  derivadas dos dados, sem limite artificial inicial

Oportunidades qualificadas:
  alvo exploratório de 2 a 8 por pregão

Trades ativados:
  alvo exploratório de 1 a 4 por pregão

Não reduzir filtros apenas para atingir frequência.
```

### 6.2 Qualidade

Meta avançada:

```text
trade_to_tp1_rate >= 80%
```

Mas somente será considerada válida se simultaneamente:

```text
TP1 médio >= 0,70R
net_expectancy_r > 0
profit_factor_net > 1
lookahead_violations = 0
resultado não concentrado em poucos dias
resultado estável em múltiplos períodos
```

Meta stretch:

```text
profit_factor_net > 2
```

Gate mínimo para continuar pesquisa:

```text
profit_factor_net > 1
net_expectancy_r > 0
drawdown controlado
robustez OOS aceitável
```

---

## 7. Invariantes e bloqueios absolutos

Este plano é uma trilha isolada:

```text
DATA_DRIVEN_WINFUT_RESEARCH_SHADOW
```

Não fazer:

```text
Não alterar app.py.
Não alterar SMC Engine V2 aprovada.
Não recalcular SMC com regras diferentes.
Não modificar Elliott/Wyckoff oficiais.
Não escrever em analysis_history.
Não escrever em smc_snapshots oficial.
Não escrever em smc_zones oficial.
Não promover parâmetros automaticamente.
Não gerar sinal oficial.
Não enviar ordem real.
Não executar trade real.
Não usar LLM como motor estatístico, classificador, score ou decisão.
Não usar candles futuros.
Não usar zonas antes de available_at.
Não usar swings/targets ainda não confirmados.
Não escolher parâmetros no mesmo período usado como teste final.
Não chamar hit rate de assertividade operacional sem qualificação metodológica.
Não usar PF bruto sem custos como métrica final.
```

Permitido:

```text
Ler tabelas shadow aprovadas.
Criar dataset canonical de pesquisa.
Criar tabelas shadow de pesquisa.
Criar modelos estatísticos interpretáveis.
Criar parâmetros candidate shadow.
Executar backtest e live shadow.
Gerar relatórios, endpoints e painéis shadow.
```

---

## 8. Arquitetura proposta

Criar módulo:

```text
technical_engine/data_driven_winfut/
```

Estrutura:

```text
technical_engine/data_driven_winfut/
  __init__.py
  models.py
  enums.py
  config.py
  contracts.py
  dataset_manifest.py
  candle_canonicalizer.py
  zone_canonicalizer.py
  rollover_policy.py
  temporal_split.py
  anti_lookahead.py
  opportunity_builder.py
  outcome_labeler.py
  feature_builder.py
  smc_feature_adapter.py
  elliott_feature_adapter.py
  wyckoff_feature_adapter.py
  contextual_feature_adapter.py
  indicator_feature_adapter.py
  trigger_detector.py
  combination_miner.py
  statistical_analysis.py
  probability_calibrator.py
  tp1_research.py
  parameter_generator.py
  evidence_backtest.py
  walk_forward.py
  robustness.py
  live_shadow.py
  persistence.py
  validators.py
  report_builder.py
```

Tools:

```text
tools/audit_winfut_research_data.py
tools/build_winfut_research_dataset.py
tools/label_winfut_zone_outcomes.py
tools/analyze_winfut_zone_behaviour.py
tools/analyze_winfut_triggers.py
tools/analyze_winfut_combinations.py
tools/analyze_winfut_tp1_candidates.py
tools/generate_winfut_candidate_parameters.py
tools/run_winfut_evidence_backtest.py
tools/run_winfut_walk_forward.py
tools/run_winfut_robustness_suite.py
tools/run_winfut_live_shadow.py
```

---

# FASE 0 — GOVERNANÇA, CHECKPOINT E INVENTÁRIO

## Objetivo

Preparar a trilha de pesquisa sem interferir no runtime oficial.

## Entregas

```text
branch/checkpoint
git status/hash
inventário das fontes
inventário de tabelas
inventário de versões/config_hash
manifest de arquivos a criar
```

Criar:

```text
runtime/data_driven_winfut/<run_id>/source_inventory.json
runtime/data_driven_winfut/<run_id>/research_scope.md
runtime/data_driven_winfut/<run_id>/table_write_manifest.json
```

## Gate

```text
PRONTO_PARA_AUDITORIA_DATA_DRIVEN_WINFUT
```

---

# FASE 1 — AUDITORIA E CANONICALIZAÇÃO DOS DADOS

## Objetivo

Garantir que candles, zonas e evidências sejam únicas, temporais e reproduzíveis.

## 1.1 Auditoria de candles

Validar por timeframe:

```text
count
min(timestamp)
max(timestamp)
missing candles
duplicated timestamps
OHLC inválido
volume ausente
indicadores nulos
timezone
candle aberto
session calendar
```

Timeframes iniciais:

```text
M2
M5
M15
H1
H4
D1
```

## 1.2 Auditoria de zonas SMC V2

Para cada tabela:

```text
raw_rows
unique_rows
exact_duplicates
semantic_duplicates
engine_version
config_hash
run_id
available_at coverage
invalid timestamps
invalid price ranges
mitigation state
```

Chave canônica sugerida:

```text
asset_id
symbol
timeframe
engine_version
config_hash
zone_type
direction
origin_at
confirmed_at
available_at
price_low
price_high
```

## 1.3 Auditoria Elliott/Wyckoff

Validar:

```text
run_id
available_at
source_mode
fake_data_used=False
event/wave/range identity
duplicate events
temporal validity
```

## 1.4 Auditoria dos 854 trades históricos

Identificar:

```text
strategy_version
parameter_version
period
cost model
execution model
lookahead status
duplicate trades
in_sample/out_of_sample
outcome definition
```

Uso inicial:

```text
benchmark e sanity check
```

Não usar como truth label sem auditoria aprovada.

## 1.5 Rollover WINFUT

Criar política explícita:

```text
contract_symbol
rollover_date
continuous_series_method
price_adjustment_method
gap handling
zones crossing rollover
indicator reset/carry policy
exclusion window
```

Saídas:

```text
docs/validacoes/DATA_AUDIT_WINFUT_V1_YYYYMMDD.md
docs/validacoes/dados_data_audit_winfut_v1_YYYYMMDD.json
runtime/data_driven_winfut/<run_id>/canonical_dataset_manifest.json
runtime/data_driven_winfut/<run_id>/zone_dedup_report.json
runtime/data_driven_winfut/<run_id>/rollover_report.json
runtime/data_driven_winfut/<run_id>/anti_lookahead_audit.json
```

## Gate

```text
PRONTO_COM_DATASET_CANONICAL_WINFUT_V1
```

Bloqueios:

```text
BLOQUEADO_DUPLICIDADE_NAO_RESOLVIDA
BLOQUEADO_ROLLOVER_INDEFINIDO
BLOQUEADO_AVAILABLE_AT_INCOMPLETO
BLOQUEADO_CANDLE_DATA_INTEGRITY
```

---

# FASE 2 — CONTRATO FORMAL DE EVENTOS, OPORTUNIDADES E OUTCOMES

## Objetivo

Definir matematicamente o que é zona tocada, reação, gatilho, trade, TP1 e stop.

Criar:

```text
docs/contratos/WINFUT_ZONE_OPPORTUNITY_TRADE_OUTCOME_CONTRACT_V1.md
```

## 2.1 Estados de zona/oportunidade

```text
DETECTED
AVAILABLE
APPROACHING
TOUCHED
MONITORING
QUALIFIED
TRADE_READY
INVALIDATED
EXPIRED
```

## 2.2 Estados de execução

```text
NO_TRIGGER
TRIGGERED
NO_FILL
FILLED
TP1_BEFORE_STOP
STOP_BEFORE_TP1
AMBIGUOUS_SAME_BAR
EXPIRED_AFTER_FILL
CANCELLED_BY_CONTEXT
```

## 2.3 First touch e retests

Separar:

```text
touch_number=1
touch_number=2
touch_number>=3
```

Nunca misturar primeiro toque e reteste sem feature explícita.

## 2.4 Reação de zona

Definir reação usando:

```text
minimum_reaction_atr
minimum_reaction_r
reaction_horizon_bars
MFE
MAE
```

## 2.5 Execução intrabar

Se TP1 e stop ocorrerem no mesmo candle:

```text
usar timeframe menor, se disponível e temporalmente consistente;
senão marcar AMBIGUOUS_SAME_BAR;
cenário conservador opcional: STOP_FIRST;
nunca escolher TP_FIRST automaticamente.
```

## 2.6 Expiry

Definir por contexto:

```text
zone_expiry_bars
opportunity_expiry_bars
order_expiry_bars
trade_max_holding_bars
```

## Gate

```text
PRONTO_COM_OUTCOME_CONTRACT_WINFUT_V1
```

---

# FASE 3 — SPLIT TEMPORAL E DATASET DE PESQUISA

## Objetivo

Separar construção de hipótese, treinamento, validação e teste final.

Nunca usar split aleatório.

Criar:

```text
technical_engine/data_driven_winfut/temporal_split.py
```

Splits:

```text
DISCOVERY
TRAIN
VALIDATION
TEST_FINAL
FORWARD_SHADOW
```

O script deve calcular cortes com base na cobertura real e nos rollovers.

Regras:

```text
purge entre janelas
embargo entre janelas
nenhum trade atravessando split
nenhuma zona/label usando candles do split seguinte
TEST_FINAL não pode ser usado para ajustar parâmetros
FORWARD_SHADOW contém somente dados posteriores ao freeze
```

Saída:

```text
runtime/data_driven_winfut/<run_id>/temporal_split_manifest.json
```

## Gate

```text
PRONTO_COM_TEMPORAL_SPLIT_WINFUT_V1
```

---

# FASE 4 — FEATURE STORE CAUSAL

## Objetivo

Criar features disponíveis no instante da decisão.

## 4.1 Features SMC

```text
zone_type
zone_timeframe
direction
zone_width_points
zone_width_atr
zone_age_bars
touch_number
mitigation_state_before_touch
distance_to_midpoint_atr
displacement_strength
bos_present
choch_present
liquidity_sweep_present
premium_discount_position
internal_external_liquidity
overlapping_fvg_ob
htf_zone_alignment
```

## 4.2 Features Elliott

```text
wave_state
wave_direction
wave_degree
impulse_or_correction
possible_wave_3
possible_wave_5_exhaustion
abc_completion
distance_to_wave_invalidation
elliott_confidence
```

Usar apenas waves:

```text
available_at <= decision_at
```

## 4.3 Features Wyckoff

```text
range_state
phase
event
spring
upthrust
SOS
SOW
LPS
LPSY
test
shakeout
distance_to_range_edge
wyckoff_confidence
```

## 4.4 Indicadores

EMA:

```text
price_vs_ema20
price_vs_ema200
ema20_slope
ema200_slope
ema20_ema200_alignment
distance_ema20_atr
distance_ema200_atr
recent_cross
pullback_to_ema
```

RSI:

```text
rsi_value
rsi_slope
cross_50
exit_overbought
exit_oversold
divergence_flag
direction_alignment
```

Volume:

```text
volume_zscore_rolling
touch_candle_volume_zscore
trigger_candle_volume_zscore
displacement_volume_zscore
pullback_volume_contraction
trigger_volume_expansion
ob_volume_percentile
```

ATR/volatilidade:

```text
atr
atr_percentile
volatility_state
zone_width_atr
stop_distance_atr
target_distance_atr
```

Sessão/contexto:

```text
session_id
minutes_from_open
market_regime
htf_bias
news_context
day_of_week
rollover_proximity
```

## 4.5 Persistência

Criar tabela:

```text
technical_engine_winfut_feature_snapshots_shadow
```

Cada snapshot deve conter:

```text
sample_id
decision_at
feature_schema_version
features_json
input_refs_json
available_at_max
lookahead_ok
config_hash
```

## Gate

```text
PRONTO_COM_CAUSAL_FEATURE_STORE_WINFUT_V1
```

---

# FASE 5 — ANÁLISE INDIVIDUAL DAS ZONAS

## Objetivo

Medir comportamento individual de cada zona antes de combinar fatores.

Analisar:

```text
FVG
Order Block
BOS retest
CHOCH retest
Liquidity
Swing reaction
Previous High/Low
overlapping zones
```

Por:

```text
timeframe
direction
first touch/retest
session
regime
volatility
year
```

Métricas:

```text
touch_rate
reaction_rate
TP1 rate por target candidate
MFE
MAE
time_to_reaction
time_to_tp1
sample_size
confidence_interval
```

Requisitos estatísticos:

```text
n < 30:
  DESCRIPTIVE_ONLY

30 <= n < 100:
  LOW_SUPPORT

100 <= n < 500:
  PRELIMINARY

n >= 500:
  STRONGER_SUPPORT
```

Aplicar:

```text
Wilson confidence interval ou Beta-Binomial credible interval
bootstrap por dia/sessão
não tratar zonas correlacionadas como independentes
```

Saída:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_ZONAS_WINFUT_V2.md
runtime/data_driven_winfut/<run_id>/zone_statistics.json
```

## Gate

```text
PRONTO_COM_ZONE_BEHAVIOUR_ANALYSIS_WINFUT_V2
```

---

# FASE 6 — ANÁLISE DE GATILHOS

## Objetivo

Descobrir quais gatilhos transformam oportunidade em trade com maior probabilidade de TP1.

Gatilhos a testar:

```text
micro BOS M2
micro CHOCH M2
liquidity sweep + reclaim
rejection candle
engulfing/displacement candle
close beyond trigger level
volume expansion
RSI cross 50
EMA pullback/reclaim
FVG M2 após toque em zona M5/M15
Wyckoff Test/LPS/LPSY
Elliott corrective completion
```

Cada gatilho deve ser comparado com:

```text
opportunity without trigger
same zone/context with trigger
```

Métricas:

```text
trigger_frequency
trigger_to_fill_rate
trade_to_tp1_rate
stop_rate
MFE
MAE
expectancy_r
delay_bars
opportunities_lost
bad_trades_avoided
```

Saída:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_GATILHOS_WINFUT.md
runtime/data_driven_winfut/<run_id>/trigger_statistics.json
```

## Gate

```text
PRONTO_COM_TRIGGER_ANALYSIS_WINFUT_V1
```

---

# FASE 7 — ANÁLISE DE COMBINAÇÕES SMC + ELLIOTT + WYCKOFF

## Objetivo

Identificar combinações com suporte estatístico e maior conversão para TP1.

Combinações iniciais:

```text
OB M5 + BOS M2
OB M5 + liquidity sweep + CHOCH M2
OB M5 + volume expansion
OB M5 + M15/H1 alinhado
FVG M5 + BOS M2
FVG M5 dentro de OB M15
FVG + OB overlap
BOS retest + EMA200 alinhada
Liquidity sweep + reclaim
Wyckoff LPS + FVG bullish + Elliott impulse
Wyckoff LPSY + OB bearish + Elliott correction complete
Elliott ABC completion + CHOCH M2
SMC zone + Wyckoff test + volume contraction/expansion
```

Não criar explosão combinatória irrestrita.

Regras:

```text
max_combination_depth inicial = 3
minimum_support absoluto
minimum_support por sessão/regime
false discovery rate control
cross-period stability
```

Métricas por combinação:

```text
occurrences
opportunities
trade_ready
filled_trades
tp1_hits
stops
expired
trade_to_tp1_rate
expectancy_r
PF net
lift_vs_zone_baseline
confidence_interval
```

Persistir:

```text
technical_engine_winfut_combination_stats_shadow
```

Saída:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_COMBINACOES_SMC_ELLIOTT_WYCKOFF_WINFUT.md
runtime/data_driven_winfut/<run_id>/combination_statistics.json
```

## Gate

```text
PRONTO_COM_COMBINATION_ANALYSIS_WINFUT_V1
```

---

# FASE 8 — PESQUISA DO MELHOR TP1

## Objetivo

Descobrir TP1 apropriado por contexto, evitando TP excessivamente curto apenas para elevar hit rate.

Candidatos:

```text
0.50R
0.70R
0.75R
1.00R
1.25R
1.50R
nearest_internal_liquidity
nearest_external_liquidity
previous_confirmed_swing
previous_high_low
opposite_zone_near_edge
opposite_zone_midpoint
ATR adaptive
session range target
```

Regras:

```text
target deve estar disponível antes da entrada;
swing precisa estar confirmado;
zona oposta precisa ter available_at <= entry_decision_at;
não usar estrutura criada depois da entrada;
custos precisam ser considerados.
```

Métricas por TP1:

```text
hit_rate
stop_rate
ambiguous_rate
expectancy_r
net_expectancy_r
profit_factor_net
average_holding_time
MAE_before_tp1
MFE_after_tp1
continuation_to_tp2_rate
drawdown
```

Função de seleção não pode maximizar somente hit rate.

Score de TP1 sugerido:

```text
TP1_UTILITY =
  calibrated_hit_probability
  × net_expectancy_factor
  × stability_factor
  × execution_feasibility
```

Gate mínimo de um TP1 candidate:

```text
minimum_tp1_r >= 0,70R
net_expectancy_r > 0
sample_size suficiente
stable em validation
```

Persistir:

```text
technical_engine_winfut_tp1_research_shadow
```

Saída:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_TP1_WINFUT.md
runtime/data_driven_winfut/<run_id>/tp1_candidates.json
```

## Gate

```text
PRONTO_COM_TP1_RESEARCH_WINFUT_V1
```

---

# FASE 9 — PROBABILIDADE E SCORE DE OPORTUNIDADE/TRADE

## Objetivo

Transformar evidências em probabilidade calibrada e parâmetros reproduzíveis.

## 9.1 Primeiro baseline determinístico

Criar score transparente usando estatísticas TRAIN, sem pesos arbitrários.

Exemplo de grupos:

```text
zone_quality_score
context_score
trigger_score
tp1_feasibility_score
risk_score
```

## 9.2 Modelo interpretável opcional

Somente após baseline:

```text
Logistic Regression regularizada
```

Target principal:

```text
TP1_BEFORE_STOP
```

Modelos separados:

```text
P(opportunity becomes trade)
P(TP1 before stop | trade triggered)
```

Features somente causais.

Requisitos:

```text
time-series cross-validation
regularização
class balance
probability calibration
Brier score
reliability curve
feature importance/coefficient report
no test_final tuning
```

Modelos complexos só podem ser avaliados em fase posterior e precisam superar o modelo interpretável fora da amostra.

## 9.3 Thresholds dinâmicos

O sistema deve permitir:

```text
mais sinais OBSERVAR/MONITORAR;
threshold maior para TRADE_READY;
threshold adaptado por sessão/regime;
nunca alterar automaticamente em produção.
```

Persistir:

```text
technical_engine_winfut_probability_models_shadow
technical_engine_winfut_score_configs_shadow
```

## Gate

```text
PRONTO_COM_PROBABILITY_SCORING_WINFUT_V1
```

---

# FASE 10 — GERAÇÃO DOS PARÂMETROS CANDIDATOS

## Objetivo

Converter achados em configuração versionada.

Criar:

```text
configs/winfut/data_driven_candidate_v1.yaml
```

Schema esperado:

```yaml
symbol: WINFUT
asset_id: 1
base_timeframe: M5
trigger_timeframe: M2
context_timeframes: [M15, H1, H4, D1]

opportunity_generation:
  allowed_zone_types: []
  include_bos_retest: true
  include_choch_retest: true
  include_liquidity_sweep: true
  include_wyckoff_events: true
  first_touch_policy: SEPARATE
  max_zone_age_bars: 0

qualification:
  minimum_zone_probability: 0.0
  minimum_context_probability: 0.0
  minimum_combined_probability: 0.0

trade_activation:
  minimum_tp1_probability: 0.0
  required_trigger_types: []
  require_risk_target_valid: true
  require_anti_lookahead_ok: true

entry:
  mode: MIDPOINT_LIMIT|TRIGGER_CLOSE|RETEST_LIMIT
  expiry_bars: 0
  slippage_ticks: 0

stop:
  mode: ZONE_EDGE_ATR_BUFFER
  atr_buffer: 0.0
  maximum_stop_risk_points: 0

tp1:
  mode: ADAPTIVE_BY_CONTEXT
  minimum_r: 0.70
  candidates: []

risk:
  max_simultaneous_trades: 1
  max_trades_per_session: 0

guardrails:
  shadow_only: true
  apply_automatically: false
  can_promote_trade: false
```

Criar manifest:

```text
candidate_id
feature_schema_version
dataset_manifest_hash
train_period
validation_period
parameters_hash
created_at
```

## Gate

```text
PRONTO_COM_WINFUT_CANDIDATE_PARAMETERS_V1
```

---

# FASE 11 — BACKTEST BASELINE VS CANDIDATE

## Objetivo

Reproduzir o pipeline completo.

Fluxo:

```text
zona disponível
sinal OBSERVAR
oportunidade MONITORAR
qualificação
gatilho
TRADE_READY
ordem
fill/no-fill
TP1/stop/expired
```

Baselines obrigatórios:

```text
OB midpoint sem filtro
FVG midpoint sem filtro
zona + EMA200
zona + volume
zona + sessão
SMC-only
SMC + trigger
SMC + Elliott + Wyckoff candidate
```

Execução realista:

```text
custos
emolumentos
spread
slippage
gap
partial/no fill
same-bar ambiguity
session close
rollover exclusion
```

Saídas:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/BACKTEST_DATA_DRIVEN_WINFUT_V1.md
runtime/data_driven_winfut/<run_id>/backtest_summary.json
runtime/data_driven_winfut/<run_id>/trades.parquet
runtime/data_driven_winfut/<run_id>/opportunities.parquet
```

Gate:

```text
PRONTO_COM_BACKTEST_DATA_DRIVEN_WINFUT_V1
```

ou:

```text
KEEP_RESEARCH
NO_GO
```

---

# FASE 12 — WALK-FORWARD E ROBUSTEZ

## Objetivo

Validar estabilidade temporal sem procurar milhares de combinações no período de teste.

Walk-forward:

```text
rolling train
validation
OOS
purge
embargo
parameters frozen per fold
```

Robustez:

```text
custos normais
custos 2x
slippage +1 tick
slippage +2 ticks
entrada atrasada 1 candle
ATR buffer ±10%
expiry ±20%
remoção dos 5 melhores dias
remoção dos 5 piores dias
bootstrap por dia
Monte Carlo de ordem de trades
```

Critérios de avanço para live shadow:

```text
lookahead_violations = 0
net_expectancy_r > 0 em maioria dos folds
PF net > 1 em maioria dos folds
trade_to_tp1 estável
drawdown dentro do limite
sem dependência excessiva dos melhores dias
```

Meta de 80%:

```text
Só marcar TARGET_80_REACHED se:
trade_to_tp1_rate >= 80% OOS
TP1 médio >= 0,70R
sample_size >= 500 trades
múltiplos regimes/sessões
intervalo inferior de confiança aceitável
```

Caso contrário:

```text
TARGET_80_NOT_YET_VALIDATED
```

Saída:

```text
docs_geral/Sistema VPS/Relatorios/Backtest/WALK_FORWARD_DATA_DRIVEN_WINFUT_V1.md
runtime/data_driven_winfut/<run_id>/walk_forward_summary.json
runtime/data_driven_winfut/<run_id>/robustness_summary.json
```

## Gate

```text
PRONTO_PARA_LIVE_SHADOW_WINFUT_V1
```

---

# FASE 13 — LIVE SHADOW

## Objetivo

Validar em dados novos sem execução real.

Pipeline live:

```text
candle fechado
zonas persisted atualizadas
opportunities geradas
qualificação
gatilho
trade shadow
TP1/stop/outcome
```

Persistir:

```text
technical_engine_winfut_live_opportunities_shadow
technical_engine_winfut_live_trade_candidates_shadow
technical_engine_winfut_live_trades_shadow
technical_engine_winfut_live_outcomes_shadow
```

Campos mínimos:

```text
candidate_config_id
opportunity_id
trade_id
created_at
available_at
entry
stop
tp1
tp1_type
predicted_tp1_probability
status
outcome
outcome_at
MFE
MAE
guardrails
```

Período mínimo sugerido:

```text
>= 40 pregões
e
>= 100 trades ativados shadow
```

A meta de 80% precisa ser medida separadamente:

```text
historical OOS
walk-forward OOS
live shadow
```

Nunca combinar as três amostras sem identificação.

Dashboard:

```text
Data-Driven WINFUT Live Shadow
```

Mostrar:

```text
opportunities/day
qualified/day
trade_ready/day
trades/day
trade_to_tp1_rate
expected vs realized
TP1 distribution
PF net simulated
drawdown
session/regime breakdown
```

## Gate

```text
PRONTO_COM_LIVE_SHADOW_WINFUT_VALIDADO
```

ou:

```text
KEEP_RESEARCH_LIVE_SHADOW
NO_GO_LIVE_SHADOW
```

---

# FASE 14 — MEMÓRIA DO COMPORTAMENTO DO ATIVO

## Objetivo

Registrar comportamento validado por contexto sem autoajuste.

Chave de memória:

```text
asset_id
symbol
base_timeframe
trigger_timeframe
session
market_regime
volatility_state
direction
zone_type
trigger_type
combination_id
tp1_type
```

Métricas:

```text
sample_count
trade_count
tp1_hits
stop_hits
trade_to_tp1_rate
expectancy_r
PF net
MFE
MAE
confidence_interval
last_updated_at
```

Persistir:

```text
technical_engine_winfut_behavior_memory_shadow
```

Regras:

```text
não autoaplicar;
não promover automaticamente;
gerar proposal;
exigir review gate;
versionar por dataset/config/model.
```

## Gate

```text
PRONTO_COM_WINFUT_BEHAVIOR_MEMORY_SHADOW_V1
```

---

## 9. Tabelas shadow propostas

```text
technical_engine_winfut_research_runs_shadow
technical_engine_winfut_canonical_zones_shadow
technical_engine_winfut_opportunity_labels_shadow
technical_engine_winfut_feature_snapshots_shadow
technical_engine_winfut_zone_stats_shadow
technical_engine_winfut_trigger_stats_shadow
technical_engine_winfut_combination_stats_shadow
technical_engine_winfut_tp1_research_shadow
technical_engine_winfut_probability_models_shadow
technical_engine_winfut_score_configs_shadow
technical_engine_winfut_backtest_runs_shadow
technical_engine_winfut_backtest_trades_shadow
technical_engine_winfut_walk_forward_runs_shadow
technical_engine_winfut_live_opportunities_shadow
technical_engine_winfut_live_trade_candidates_shadow
technical_engine_winfut_live_trades_shadow
technical_engine_winfut_live_outcomes_shadow
technical_engine_winfut_behavior_memory_shadow
```

Regra:

```text
CREATE TABLE IF NOT EXISTS
sem DROP
sem tabela oficial
migrations idempotentes
```

---

## 10. Endpoints shadow propostos

```text
GET /api/data-driven-winfut/health
GET /api/data-driven-winfut/data-audit
GET /api/data-driven-winfut/zone-analysis
GET /api/data-driven-winfut/trigger-analysis
GET /api/data-driven-winfut/combination-analysis
GET /api/data-driven-winfut/tp1-analysis
GET /api/data-driven-winfut/candidate-config
GET /api/data-driven-winfut/backtest-summary
GET /api/data-driven-winfut/walk-forward-summary
GET /api/data-driven-winfut/live-shadow
GET /api/data-driven-winfut/behavior-memory
```

---

## 11. Testes obrigatórios

Criar suites:

```text
tests/test_data_driven_winfut/test_canonicalization.py
tests/test_data_driven_winfut/test_zone_deduplication.py
tests/test_data_driven_winfut/test_rollover_policy.py
tests/test_data_driven_winfut/test_temporal_split.py
tests/test_data_driven_winfut/test_anti_lookahead.py
tests/test_data_driven_winfut/test_outcome_contract.py
tests/test_data_driven_winfut/test_feature_availability.py
tests/test_data_driven_winfut/test_trigger_detector.py
tests/test_data_driven_winfut/test_combination_miner.py
tests/test_data_driven_winfut/test_tp1_research.py
tests/test_data_driven_winfut/test_probability_calibration.py
tests/test_data_driven_winfut/test_parameter_generator.py
tests/test_data_driven_winfut/test_evidence_backtest.py
tests/test_data_driven_winfut/test_walk_forward.py
tests/test_data_driven_winfut/test_robustness.py
tests/test_data_driven_winfut/test_live_shadow.py
tests/test_data_driven_winfut/test_no_official_writes.py
tests/test_data_driven_winfut/test_no_smc_recompute.py
tests/test_data_driven_winfut/test_app_py_unchanged.py
```

Testes obrigatórios:

```text
duplicatas removidas corretamente;
available_at respeitado;
nenhum target futuro usado;
split temporal sem contaminação;
rollover respeitado;
same-bar ambiguity tratada;
features causais;
custos aplicados;
baseline não modificado;
SMC não recalculada;
live shadow não executa ordem;
tabelas oficiais intactas.
```

---

## 12. Validação estatística

Obrigatório reportar:

```text
sample_size
confidence_interval
support_level
bootstrap_by_day
stability_by_period
stability_by_session
stability_by_regime
multiple_comparison_control
```

Regras:

```text
não escolher combinação apenas pelo maior hit rate;
não aceitar combinação com suporte baixo;
não otimizar no TEST_FINAL;
não alterar configuração durante FORWARD_SHADOW;
não omitir resultados negativos.
```

---

## 13. Critérios de aprovação

### Aprovação da pesquisa exploratória

```text
dataset canonical aprovado
lookahead=0
labels formalizados
resultados reproduzíveis
```

### Aprovação do candidate backtest

```text
net_expectancy_r > 0 OOS
PF net > 1 OOS
drawdown aceitável
resultado estável
mais de uma sessão/regime
```

### Meta avançada de TP1

```text
trade_to_tp1_rate >= 80%
TP1 médio >= 0,70R
>= 500 trades OOS válidos
intervalo de confiança reportado
live shadow confirma direção do resultado
```

### Proibido concluir

```text
"80% de assertividade" baseado apenas em treino;
"PF>2 validado" sem custos;
"estratégia pronta" sem TEST_FINAL;
"live validado" sem dados novos;
```

---

## 14. Documentação obrigatória

Atualizar:

```text
MEMORIA_OFICIAL.md
docs/memoria.md
ARQUITETURA_OFICIAL.md
DOCUMENTATION_INDEX.md
CHANGELOG.md
AGENTS.md
docs/planos/PLANO_OPERACIONAL_SMC_ENGINE_V2_REBUILD_LOCAL_OFICIAL_V3.md
```

Criar:

```text
docs/planos/PLANO_DATA_DRIVEN_APRENDIZADO_WINFUT_SMC_ELLIOTT_WYCKOFF_SINAIS_ENTRADAS_TP1_V1.md
docs/contratos/WINFUT_ZONE_OPPORTUNITY_TRADE_OUTCOME_CONTRACT_V1.md
docs/contratos/WINFUT_DATA_DRIVEN_FEATURE_SCHEMA_V1.md
docs/contratos/WINFUT_TP1_RESEARCH_CONTRACT_V1.md
docs/contratos/WINFUT_LIVE_SHADOW_VALIDATION_V1.md
```

Cada fase cria:

```text
docs/validacoes/<FASE>_YYYYMMDD.md
docs/validacoes/dados_<FASE>_YYYYMMDD.json
runtime/data_driven_winfut/<run_id>/summary.json
```

---

## 15. Ordem de execução obrigatória

```text
1. FASE 0 — Governança e inventário
2. FASE 1 — Auditoria/canonicalização
3. FASE 2 — Contrato de outcomes
4. FASE 3 — Split temporal
5. FASE 4 — Feature store causal
6. FASE 5 — Análise individual de zonas
7. FASE 6 — Análise de gatilhos
8. FASE 7 — Combinações SMC + Elliott + Wyckoff
9. FASE 8 — Pesquisa do melhor TP1
10. FASE 9 — Probabilidade/score
11. FASE 10 — Parâmetros candidatos
12. FASE 11 — Backtest
13. FASE 12 — Walk-forward/robustez
14. FASE 13 — Live shadow
15. FASE 14 — Memória do comportamento
```

Não criar `run_winfut_evidence_backtest.py` antes de:

```text
dataset canonical;
outcome contract;
split temporal;
feature availability audit.
```

---

## 16. Status finais possíveis

```text
PRONTO_COM_DATASET_CANONICAL_WINFUT_V1
PRONTO_COM_ZONE_BEHAVIOUR_ANALYSIS_WINFUT_V2
PRONTO_COM_TRIGGER_ANALYSIS_WINFUT_V1
PRONTO_COM_COMBINATION_ANALYSIS_WINFUT_V1
PRONTO_COM_TP1_RESEARCH_WINFUT_V1
PRONTO_COM_WINFUT_CANDIDATE_PARAMETERS_V1
PRONTO_COM_BACKTEST_DATA_DRIVEN_WINFUT_V1
PRONTO_PARA_LIVE_SHADOW_WINFUT_V1
PRONTO_COM_LIVE_SHADOW_WINFUT_VALIDADO
PRONTO_COM_WINFUT_BEHAVIOR_MEMORY_SHADOW_V1
KEEP_RESEARCH
NO_GO
```

Bloqueios:

```text
BLOQUEADO_DUPLICIDADE_NAO_RESOLVIDA
BLOQUEADO_ROLLOVER_INDEFINIDO
BLOQUEADO_AVAILABLE_AT_INCOMPLETO
BLOQUEADO_ANTI_LOOKAHEAD
BLOQUEADO_TARGET_FUTURO
BLOQUEADO_SPLIT_CONTAMINADO
BLOQUEADO_OFFICIAL_TABLE_WRITE
BLOQUEADO_SMC_RECOMPUTE
BLOQUEADO_EXECUCAO_REAL
```

---

## 17. Próximo passo imediato

Criar o primeiro prompt operacional:

```text
PROMPT_DD_01 — FASE 0 + FASE 1:
AUDITORIA, DEDUPLICAÇÃO, ROLLOVER E DATASET CANONICAL WINFUT
```

Esse prompt deve:

```text
auditar os números do relatório preliminar;
descobrir por que Liquidity ficou 0%;
reconciliar BOS/CHOCH entre as duas metodologias;
remover duplicatas entre runs;
validar available_at;
auditar rollovers;
congelar o dataset canonical;
não executar backtest ainda.
```

---

## 18. Decisão final do plano

```text
O relatório ANALISE_ZONAS_WINFUT.md ajuda a indicar hipóteses,
principalmente o potencial dos Order Blocks M2/M5,
o uso de BOS/CHOCH como gatilho/contexto
e o possível ganho de volume/sessão.

Porém, o relatório contém inconsistências suficientes para impedir
a geração imediata de parâmetros ou um novo backtest.

O plano correto é primeiro transformar os dados em dataset canonical,
definir labels causais, analisar zonas, gatilhos e combinações,
descobrir o melhor TP1 por contexto,
gerar parâmetros versionados,
validar em backtest temporal e walk-forward,
e somente depois testar em live shadow.
```
