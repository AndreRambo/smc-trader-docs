# PLANO EXECUTIVO — NOVO SISTEMA DE SINAIS SMC SHADOW E BACKTEST COMPARATIVO WINFUT

**Projeto:** SMC Trader System 7.0  
**Versão:** 1.0  
**Escopo inicial:** WINFUT  
**Modo:** Shadow-only / candidato experimental  
**Documento-base:** `COMO_O_STOP_E_DEFINIDO_NO_SMC.md`  
**Objetivo:** Criar um novo sistema de sinais SMC, isolado do Opportunity Scanner atual, capaz de gerar entradas, stops e alvos estruturais determinísticos e comparar sua assertividade com o sistema atual por meio de backtest walk-forward auditável.

---

# 0. PRINCÍPIO GERAL

O Opportunity Scanner atual não será alterado.

Ele será tratado como:

```text
SISTEMA A — CONTROLE
```

O novo sistema será implementado separadamente como:

```text
SISTEMA B — CANDIDATO
```

A comparação será feita sobre:

- mesmos candles;
- mesmos períodos;
- mesmos custos;
- mesmos critérios de execução;
- mesmos horários;
- mesmas regras anti-lookahead;
- mesmo ativo;
- mesmos timeframes disponíveis.

O objetivo não é produzir um sistema que “nunca pegue stop”, pois isso não é realisticamente garantível.

O objetivo correto é:

```text
maximizar a frequência de alcance dos alvos antes do stop
sem reduzir artificialmente o stop
sem colocar alvos excessivamente próximos
sem usar dados futuros
sem overfitting
sem alterar o motor atual
```

---

# 1. OBJETIVO DO NOVO SISTEMA

O novo motor deverá:

1. consumir dados técnicos já calculados;
2. identificar setups de entrada;
3. selecionar uma entrada executável;
4. selecionar o nível estrutural de invalidação;
5. aplicar buffer de segurança;
6. selecionar TP1, TP2 e TP3 estruturais;
7. calcular R:R;
8. bloquear geometrias ruins;
9. persistir o sinal candidato;
10. executar backtest;
11. comparar com o scanner atual;
12. produzir recomendação técnica sem aplicar automaticamente.

---

# 2. REGRAS INVIOLÁVEIS

```text
shadow_only=True
candidate_only=True
can_promote_trade=False
apply_automatically=False
llm_decision_used=False
anti_lookahead=True
deterministico=True
current_scanner_modified=False
current_scanner_reconfigured=False
production_signal_emission=False
```

É proibido:

- alterar o Opportunity Scanner atual;
- alterar os parâmetros atuais do scanner;
- alterar o SMC Engine V2;
- usar LLM para decidir entrada;
- usar LLM para calcular stop;
- usar LLM para escolher alvo;
- usar candle aberto;
- olhar dados futuros;
- calibrar no período de teste final;
- eliminar trades perdedores da amostra;
- aproximar stop para melhorar R:R;
- aproximar alvo para inflar win rate;
- declarar “sem stop”;
- promover automaticamente o candidato.

---

# 3. NOMENCLATURA

## 3.1 Sistema atual

```text
Opportunity Scanner atual
Alias de comparação: CONTROL_A
```

## 3.2 Novo sistema

Nome recomendado:

```text
SMC Signal Candidate V1
```

Diretório:

```text
technical_engine/signal_candidate_v1/
```

Alias de comparação:

```text
CANDIDATE_B
```

## 3.3 Contratos

```text
SignalCandidateConfigV1
SignalSetupCandidateV1
SignalEntryPlanV1
SignalStopPlanV1
SignalTargetPlanV1
SignalCandidateV1
SignalBacktestTradeV1
SignalBacktestRunV1
SignalComparisonReportV1
```

---

# 4. ESCOPO DE MERCADO

## 4.1 Ativo

```text
WINFUT
```

Resolver por `asset_id`, não depender apenas de ticker textual.

## 4.2 Timeframes

Arquitetura recomendada:

```text
D1/H4 = contexto e viés
M15   = estrutura operacional
M5    = setup base
M2    = gatilho
M1    = simulação de execução e acompanhamento
```

## 4.3 Fonte de dados

```text
market_candles
```

Somente candles fechados.

## 4.4 Período

Usar o maior período confiável disponível.

Meta recomendada:

```text
mínimo: 12 meses
ideal: 24 meses
```

Se houver menos de 12 meses confiáveis, registrar limitação.

---

# 5. DEFINIÇÃO DE ASSERTIVIDADE

“Mais assertivo” não será medido apenas por win rate.

O sistema deve ser comparado por um conjunto de métricas.

## 5.1 Métrica primária

```text
TP1_BEFORE_STOP_RATE
```

Definição:

```text
trades que atingiram TP1 antes do stop
dividido por
trades executados
```

## 5.2 Métricas secundárias

```text
TP2_BEFORE_STOP_RATE
TP3_BEFORE_STOP_RATE
STOP_BEFORE_TP1_RATE
NO_ENTRY_RATE
EXPIRED_BEFORE_ENTRY_RATE
EXPECTANCY_R
PROFIT_FACTOR
MAX_DRAWDOWN_R
MEDIAN_MAE_R
MEDIAN_MFE_R
AVERAGE_R
MEDIAN_BARS_TO_ENTRY
MEDIAN_BARS_TO_TP1
MEDIAN_BARS_TO_RESOLUTION
AMBIGUOUS_BAR_RATE
SAMPLE_SIZE
```

## 5.3 Métrica de qualidade composta

Criar:

```text
ROBUSTNESS_SCORE_V1
```

Sugestão:

```python
robustness_score = (
    0.30 * normalized_expectancy_r
    + 0.20 * normalized_tp1_before_stop
    + 0.15 * normalized_tp2_before_stop
    + 0.10 * normalized_profit_factor
    + 0.10 * normalized_sample_size
    + 0.10 * normalized_stability_across_windows
    - 0.05 * normalized_max_drawdown
)
```

O score não deve substituir as métricas brutas.

---

# 6. ARQUITETURA-ALVO

```text
market_candles
      │
      ▼
SMC V2 persisted
Elliott persisted
Wyckoff persisted
Contextual persisted
TechnicalTruthEnvelopeV2
      │
      ├──────────────────────────────┐
      │                              │
      ▼                              ▼
CONTROL_A                      CANDIDATE_B
Scanner atual                  SMC Signal Candidate V1
      │                              │
      └──────────────┬───────────────┘
                     ▼
            Backtest Comparator
                     │
                     ▼
          SignalComparisonReportV1
```

---

# 7. ESTRUTURA DE DIRETÓRIOS

Criar:

```text
technical_engine/
└── signal_candidate_v1/
    ├── __init__.py
    ├── enums.py
    ├── models.py
    ├── config.py
    ├── setup_detector.py
    ├── entry_selector.py
    ├── stop_selector.py
    ├── target_selector.py
    ├── geometry_validator.py
    ├── signal_builder.py
    ├── persistence.py
    ├── repositories.py
    ├── serializer.py
    ├── hashing.py
    └── errors.py
```

Backtest:

```text
technical_engine/
└── signal_backtest_v1/
    ├── __init__.py
    ├── models.py
    ├── dataset_builder.py
    ├── event_simulator.py
    ├── execution_model.py
    ├── cost_model.py
    ├── ambiguity_policy.py
    ├── walk_forward.py
    ├── metrics.py
    ├── comparator.py
    ├── reports.py
    ├── persistence.py
    └── cli.py
```

Ferramentas:

```text
tools/
├── run_winfrut_signal_candidate_backtest.py
├── compare_control_a_vs_candidate_b.py
├── audit_signal_candidate_trades.py
└── render_signal_backtest_examples.py
```

---

# 8. SETUPS DO CANDIDATO B

O sistema deve começar com um conjunto controlado de setups.

## 8.1 Setup S1 — Sweep + MSS/CHoCH + Reteste

Fluxo:

```text
liquidity sweep
→ displacement
→ MSS/CHoCH
→ retorno a FVG/OB
→ entrada
```

## 8.2 Setup S2 — Order Block de continuação

Fluxo:

```text
viés HTF
→ BOS na direção
→ OB validado
→ mitigação
→ entrada
```

## 8.3 Setup S3 — FVG com estrutura

Fluxo:

```text
viés HTF
→ displacement
→ FVG
→ confirmação estrutural
→ mitigação
→ entrada
```

## 8.4 Setup S4 — Breaker Block

Fluxo:

```text
OB falha
→ estrutura muda
→ breaker confirmado
→ reteste
→ entrada
```

## 8.5 Setup S5 — Continuação em protected swing

Fluxo:

```text
estrutura alinhada
→ protected low/high
→ retração
→ confirmação LTF
→ entrada
```

No primeiro ciclo, não incluir outros setups.

---

# 9. ENTRADAS

## 9.1 Tipos de entrada a testar

```text
ZONE_EDGE
ZONE_MIDPOINT
FVG_50_PERCENT
CONFIRMATION_CLOSE
MSS_RETEST
MARKET_AFTER_TRIGGER
```

## 9.2 Entrada de compra

Candidatos:

```python
buy_entry_candidates = [
    bullish_ob_proximal,
    bullish_ob_midpoint,
    bullish_fvg_midpoint,
    breaker_retest_price,
    confirmation_retest_price,
]
```

## 9.3 Entrada de venda

Candidatos:

```python
sell_entry_candidates = [
    bearish_ob_proximal,
    bearish_ob_midpoint,
    bearish_fvg_midpoint,
    breaker_retest_price,
    confirmation_retest_price,
]
```

## 9.4 Regras

- entrada deve estar disponível no momento;
- zona deve estar válida;
- zona não pode estar completamente mitigada;
- preço deve tocar a entrada após o sinal;
- sinal pode expirar;
- não preencher ordem retroativamente;
- não assumir fill se houve gap além do preço limite sem regra específica;
- considerar slippage.

---

# 10. STOPS

Usar as regras de `COMO_O_STOP_E_DEFINIDO_NO_SMC.md`.

## 10.1 Âncoras permitidas

```text
LIQUIDITY_SWEEP
PROTECTED_SWING
CONFIRMATION_SWING
ORDER_BLOCK_EXTREME
BREAKER_EXTREME
POI_EXTREME
STRUCTURAL_FALLBACK
```

## 10.2 Buffer

```python
stop_buffer = max(
    min_stop_ticks * tick_size,
    spread_points + slippage_points,
    atr * stop_buffer_atr,
)
```

## 10.3 Compra

```python
stop = stop_anchor - stop_buffer
```

## 10.4 Venda

```python
stop = stop_anchor + stop_buffer
```

## 10.5 Bloqueios

```text
INVALID_STOP_GEOMETRY
STOP_INSIDE_ENTRY_ZONE
STOP_TOO_WIDE
WEAK_INVALIDATION_ANCHOR
NO_VALID_STOP_ANCHOR
BUFFER_BELOW_MINIMUM
```

---

# 11. ALVOS

Usar as regras de `COMO_O_STOP_E_DEFINIDO_NO_SMC.md`.

## 11.1 Candidatos

```text
weak high/low
BSL/SSL
EQH/EQL
M15 structure
H4 structure
PDH/PDL
session high/low
opposing OB
opposing supply/demand
range extreme
```

## 11.2 Seleção

### Pró-tendência

```text
TP1 = liquidez interna ou estrutura M15
TP2 = liquidez externa ou estrutura operacional
TP3 = estrutura H4 ou objetivo HTF
```

### Contra-tendência

```text
TP1 = primeira estrutura interna
TP2 = fim provável da retração
TP3 = ausente
```

## 11.3 Barreiras

Detectar zonas opostas entre entrada e alvo.

## 11.4 R:R

```python
rr = abs(target - entry) / abs(entry - stop)
```

## 11.5 Bloqueios

```text
RR_TP1_TOO_LOW
TARGET_ALREADY_SWEPT
TARGET_LOOKAHEAD
INVALID_TARGET_MONOTONICITY
NO_VALID_TARGET
TARGET_PATH_BLOCKED
```

---

# 12. GRID DE CONFIGURAÇÕES

A grade deve ser pré-registrada antes do backtest final.

## 12.1 Entrada

```text
ZONE_EDGE
ZONE_MIDPOINT
FVG_50_PERCENT
MSS_RETEST
CONFIRMATION_CLOSE
```

## 12.2 Stop anchor

```text
LIQUIDITY_SWEEP
PROTECTED_SWING
CONFIRMATION_SWING
ORDER_BLOCK_EXTREME
AUTO_STRUCTURAL
```

## 12.3 Stop buffer ATR

```text
0.10
0.15
0.20
0.25
0.30
```

## 12.4 Máximo stop ATR

```text
1.0
1.5
2.0
2.5
```

## 12.5 TP1 mínimo

```text
1.0R
1.2R
1.5R
```

## 12.6 Expiração

```text
3 candles M5
6 candles M5
12 candles M5
```

## 12.7 Confirmação

```text
NONE
M2_MSS
M2_CLOSE_CONFIRMATION
M1_TRIGGER
```

Não testar combinações logicamente incompatíveis.

---

# 13. CONTROLE DE OVERFITTING

## 13.1 Divisão temporal

Nunca usar split aleatório.

Usar walk-forward.

Exemplo:

```text
Treino: 6 meses
Validação: 2 meses
Teste: 2 meses
Passo: 2 meses
```

## 13.2 Holdout final

Separar o período mais recente como:

```text
FINAL_HOLDOUT
```

Esse período não pode ser usado para calibrar.

## 13.3 Regras

- parâmetros escolhidos apenas em treino/validação;
- holdout executado uma única vez;
- mínimo de trades por janela;
- descartar configuração instável;
- medir variância entre janelas;
- não escolher apenas maior win rate;
- registrar todas as configurações testadas;
- não remover meses ruins.

## 13.4 Amostra mínima

```text
mínimo por configuração: 100 trades
ideal: 300+
```

Se abaixo:

```text
LOW_SAMPLE
```

---

# 14. SIMULADOR DE EXECUÇÃO

## 14.1 Resolução

Preferência:

```text
M1 para execução
```

Quando não houver M1:

```text
usar menor timeframe disponível
```

## 14.2 Ordem limite

Fill somente se:

```text
low <= entry <= high
```

## 14.3 Ordem a mercado

Preço:

```text
next_open + slippage
```

## 14.4 Candle ambíguo

Se stop e alvo forem tocados no mesmo candle:

```text
STOP_FIRST_CONSERVATIVE
```

Persistir:

```text
ambiguous_bar=True
resolution_policy=STOP_FIRST_CONSERVATIVE
```

## 14.5 Custos

Incluir:

- corretagem;
- emolumentos;
- spread;
- slippage;
- valor por ponto;
- custo por contrato.

Configuração versionada.

## 14.6 Horário

Respeitar sessão B3.

Não simular entrada fora da janela configurada.

---

# 15. COMPARAÇÃO CONTROL_A VS CANDIDATE_B

## 15.1 Mesmo universo

Os dois sistemas devem usar:

- mesmo período;
- mesmo horário;
- mesmos custos;
- mesmo modelo de fill;
- mesma política ambígua;
- mesmo ativo;
- mesmos candles.

## 15.2 Níveis de comparação

### Nível 1 — sinais brutos

```text
quantidade de sinais
distribuição por direção
distribuição por horário
```

### Nível 2 — execução

```text
sinais que viraram entrada
tempo até entrada
slippage
expiração
```

### Nível 3 — resultado

```text
TP1 antes do stop
TP2 antes do stop
TP3 antes do stop
expectancy R
drawdown
profit factor
```

### Nível 4 — robustez

```text
por mês
por sessão
por regime
por volatilidade
por setup
por direção
por janela walk-forward
```

## 15.3 Interseção

Medir:

```text
sinais apenas A
sinais apenas B
sinais coincidentes
sinais com mesma direção
sinais com entrada semelhante
sinais com outcome divergente
```

---

# 16. TABELAS SHADOW

Criar migration:

```text
technical_engine_signal_candidate_runs_shadow
technical_engine_signal_candidates_shadow
technical_engine_signal_candidate_entries_shadow
technical_engine_signal_candidate_stops_shadow
technical_engine_signal_candidate_targets_shadow
technical_engine_signal_backtest_runs_shadow
technical_engine_signal_backtest_trades_shadow
technical_engine_signal_backtest_metrics_shadow
technical_engine_signal_comparisons_shadow
```

## 16.1 Run

Campos:

```text
run_id
config_hash
dataset_hash
engine_version
symbol
period_start
period_end
status
created_at
```

## 16.2 Trade

Campos:

```text
trade_id
run_id
signal_id
setup_type
direction
signal_time
entry_time
entry_price
stop_price
tp1
tp2
tp3
result
realized_r
mae_r
mfe_r
bars_to_entry
bars_to_resolution
ambiguous_bar
costs
payload_json
```

---

# 17. CLI

## 17.1 Backtest candidato

```bash
python tools/run_winfrut_signal_candidate_backtest.py \
  --symbol WINFUT \
  --start YYYY-MM-DD \
  --end YYYY-MM-DD \
  --config config/signal_candidate_v1.yaml \
  --walk-forward \
  --persist \
  --report
```

## 17.2 Comparação

```bash
python tools/compare_control_a_vs_candidate_b.py \
  --control-run-id <id> \
  --candidate-run-id <id> \
  --report
```

## 17.3 Auditoria visual

```bash
python tools/render_signal_backtest_examples.py \
  --run-id <id> \
  --sample wins=20,losses=20,ambiguous=10
```

---

# 18. FASES DE EXECUÇÃO

# FASE 0 — BASELINE E CONGELAMENTO DO CONTROLE

Objetivo:

- registrar scanner atual;
- registrar config;
- registrar commit;
- registrar dataset;
- garantir que não será alterado.

Entregas:

```text
BASELINE_CONTROL_A_WINFUT.md
control_config_hash
control_code_hash
dataset_hash
```

Critério:

- scanner atual intacto;
- baseline reproduzível.

---

# FASE 1 — CONTRATOS E PERSISTÊNCIA

Implementar:

- models;
- enums;
- config;
- serializers;
- hashes;
- migrations shadow.

Critério:

- contratos versionados;
- migrations aplicadas em teste;
- testes passando.

---

# FASE 2 — MOTOR DE SINAIS CANDIDATO

Implementar:

- setup detector;
- entry selector;
- stop selector;
- target selector;
- geometry validator;
- signal builder.

Critério:

- sinais determinísticos;
- sem lookahead;
- nenhum efeito no scanner atual.

---

# FASE 3 — SIMULADOR DE BACKTEST

Implementar:

- dataset builder;
- event simulator;
- execution model;
- costs;
- ambiguity policy;
- trade lifecycle.

Critério:

- resultados reproduzíveis;
- STOP_FIRST;
- custos aplicados;
- testes sintéticos.

---

# FASE 4 — BACKTEST EXPLORATÓRIO

Objetivo:

- validar funcionamento;
- eliminar configurações inválidas;
- não escolher vencedor final.

Executar em período de desenvolvimento.

Critério:

- bugs corrigidos;
- grade congelada;
- relatório exploratório.

---

# FASE 5 — WALK-FORWARD

Executar:

- treino;
- validação;
- múltiplas janelas;
- estabilidade.

Critério:

- configurações robustas;
- amostra suficiente;
- sem uso do holdout.

---

# FASE 6 — HOLDOUT FINAL

Executar uma única vez.

Critério:

- resultado real fora da amostra;
- sem recalibração posterior.

---

# FASE 7 — COMPARAÇÃO A/B

Comparar CONTROL_A e CANDIDATE_B.

Critério:

- mesmas condições;
- relatório por métricas;
- significância e amostra.

---

# FASE 8 — AUDITORIA VISUAL

Revisar:

- melhores trades;
- piores trades;
- stops;
- targets;
- ambiguous bars;
- falsos positivos;
- oportunidades perdidas.

Critério:

- exemplos renderizados;
- relatório de causas.

---

# FASE 9 — DECISÃO

Status possíveis:

```text
REJECTED
NEEDS_MORE_DATA
PROMISING_SHADOW
OUTPERFORMS_CONTROL_IN_HOLDOUT
READY_FOR_FORWARD_SHADOW
```

Nunca usar:

```text
READY_FOR_LIVE
```

---

# 19. TESTES

Criar:

```text
tests/test_signal_candidate_v1/
tests/test_signal_backtest_v1/
```

Casos obrigatórios:

- entrada sem lookahead;
- stop correto compra;
- stop correto venda;
- alvo correto compra;
- alvo correto venda;
- target barrier;
- stop too wide;
- RR insuficiente;
- ordem não preenchida;
- expiração;
- gap;
- slippage;
- custos;
- ambiguous bar;
- STOP_FIRST;
- parciais;
- monotonicidade;
- mesma entrada gera mesmo hash;
- replay determinístico;
- controle não alterado.

---

# 20. RELATÓRIOS

Criar:

```text
docs_geral/Relatorios/SignalCandidate/
BASELINE_CONTROL_A_WINFUT.md
RESULTADO_FASE_1_CONTRATOS.md
RESULTADO_FASE_2_MOTOR_CANDIDATO.md
RESULTADO_FASE_3_SIMULADOR.md
RESULTADO_FASE_4_EXPLORATORIO.md
RESULTADO_FASE_5_WALK_FORWARD.md
RESULTADO_FASE_6_HOLDOUT.md
RESULTADO_FASE_7_COMPARACAO_AB.md
RESULTADO_FASE_8_AUDITORIA_VISUAL.md
DECISAO_FINAL_SIGNAL_CANDIDATE_V1.md
```

---

# 21. CRITÉRIOS DE ACEITE

O projeto só estará concluído quando:

- scanner atual não foi alterado;
- candidato está isolado;
- WINFUT apenas;
- dataset versionado;
- parâmetros pré-registrados;
- backtest sem lookahead;
- custos incluídos;
- STOP_FIRST aplicado;
- walk-forward executado;
- holdout executado;
- comparação A/B feita;
- amostra reportada;
- drawdown reportado;
- exemplos visuais gerados;
- decisão final documentada.

---

# 22. CRITÉRIO DE SUPERIORIDADE

CANDIDATE_B será considerado superior somente se:

```text
1. expectancy_R no holdout > CONTROL_A
2. TP1_BEFORE_STOP_RATE não inferior
3. max drawdown não significativamente pior
4. profit factor superior
5. estabilidade em múltiplas janelas
6. amostra suficiente
7. custos incluídos
8. resultado não depende de uma única janela
```

Não basta:

```text
maior win rate
```

---

# 23. ROLLBACK

Como o sistema é isolado:

```text
SIGNAL_CANDIDATE_V1_ENABLED=false
```

Rollback:

- parar runner candidato;
- preservar tabelas;
- preservar resultados;
- não alterar scanner atual;
- não apagar dados;
- registrar motivo.

---

# 24. CONCLUSÃO

O novo sistema será uma linha experimental independente.

A pergunta final não será:

```text
“Qual sistema quase não toma stop?”
```

A pergunta correta será:

```text
“Qual sistema entrega melhor expectativa, maior estabilidade e melhor relação entre alvo alcançado e stop, fora da amostra, sob as mesmas condições de execução?”
```

O candidato só poderá avançar para forward shadow após comprovar desempenho no holdout e robustez em walk-forward.
