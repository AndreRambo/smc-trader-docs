# PLANO EXECUTIVO — COMPARAÇÃO DEFINITIVA E OTIMIZAÇÃO ROBUSTA DOS SISTEMAS DE SINAIS WINFUT

**Projeto:** SMC Trader System 7.0  
**Versão do plano:** 1.0  
**Ativo inicial:** WINFUT  
**Data-base:** 17 de junho de 2026  
**Modo:** Pesquisa quantitativa, shadow-only  
**Objetivo:** Determinar com segurança qual sistema é superior — `CONTROL_A` ou `CANDIDATE_B` — e, somente após essa decisão, criar uma versão melhorada do vencedor com o maior Profit Factor robusto possível, sem overfitting, lookahead ou alteração dos sistemas congelados.

---

# 0. PRINCÍPIO CENTRAL

Este plano não busca apenas o maior Profit Factor observado em um backtest.

O objetivo correto é:

```text
maximizar o Profit Factor robusto fora da amostra
sujeito a:
- expectancy positiva;
- drawdown controlado;
- quantidade mínima de trades;
- estabilidade entre janelas;
- custos realistas;
- execução reproduzível;
- ausência de lookahead;
- ausência de concentração em um único mês ou setup.
```

Um Profit Factor alto com poucos trades, drawdown excessivo ou desempenho concentrado em uma única janela não será considerado superior.

---

# 1. PROBLEMAS QUE ESTE PLANO DEVE RESOLVER

Os relatórios atuais apresentam avanços importantes, porém existem inconsistências metodológicas que impedem uma decisão definitiva.

## 1.1 Resultado v2 versus v3

Relatórios intermediários registram:

```text
CANDIDATE_B v2
Holdout expectancy: -0,187R
Profit Factor: 0,73
```

Relatórios consolidados posteriores registram:

```text
CANDIDATE_B v3
Holdout expectancy: +0,251R
Profit Factor: 1,97
```

A política de parciais 50/25/25 foi incorporada posteriormente. Cada versão precisa ser identificada por:

```text
strategy_version
run_id
code_commit
config_hash
dataset_hash
execution_model_version
partial_policy_version
```

## 1.2 Holdout contaminado

A versão v3 foi criada após a observação de resultados que incluíam o período chamado de holdout. Portanto, esse período não pode mais ser usado como holdout final limpo da v3.

## 1.3 CONTROL_A não foi simulado exatamente como CANDIDATE_B

A expectancy do `CONTROL_A` foi estimada a partir de métricas agregadas, enquanto o candidato foi executado trade a trade.

Ambos devem ser executados no mesmo simulador.

## 1.4 Métricas com denominadores diferentes

Separar obrigatoriamente:

```text
TP1_BEFORE_STOP_ALL_ENTRIES_RATE
TP1_AFTER_SURVIVAL_CONDITIONAL_RATE
```

A métrica principal deve considerar todas as entradas válidas.

## 1.5 Dataset potencialmente inconsistente

O relatório apresenta aproximadamente 10.056 candles D1 em pouco mais de quatro anos, quantidade incompatível com o número normal de pregões.

Também há mistura entre:

```text
WIN$N contínuo para D1/H4
WINM26 para timeframes intraday
```

Essa composição precisa ser auditada quanto a:

- duplicidades;
- timeframe;
- rollover;
- ajuste de preços;
- diferença de escala;
- asset_id;
- timezone.

## 1.6 Sinais sobrepostos

O sistema gera dezenas de sinais por dia. É necessário controlar:

- duplicidade na mesma zona;
- sinais durante trade aberto;
- exposição simultânea;
- sinais opostos;
- limite de posição;
- capital disponível.

## 1.7 S4 Breaker sem validação real

O setup foi implementado, mas não gerou sinais reais. Deve permanecer fora da otimização principal até ser validado.

---

# 2. SISTEMAS E VERSÕES

## 2.1 CONTROL_A

```text
Opportunity Scanner atual
Status: congelado
Alterações permitidas: nenhuma
```

## 2.2 CANDIDATE_B_V2

```text
Stop M15
Execução M1
Sem política final de parciais v3
Status: congelado para auditoria histórica
```

## 2.3 CANDIDATE_B_V3

```text
Stop M15
Execução M1
Parciais 50/25/25
Status: congelar como baseline candidato atual
```

## 2.4 CANDIDATE_C

Será criado somente depois da comparação definitiva entre A e B.

```text
CANDIDATE_C = versão experimental melhorada do vencedor
```

O sistema vencedor original permanecerá congelado.

---

# 3. GUARDRAILS INVIOLÁVEIS

```text
shadow_only=True
research_only=True
can_promote_trade=False
apply_automatically=False
production_signal_emission=False
llm_decision_used=False
anti_lookahead=True
deterministico=True
control_a_modified=False
candidate_b_modified=False
smc_engine_v2_modified=False
```

É proibido:

- alterar o `CONTROL_A`;
- alterar o `CANDIDATE_B_V3` após o freeze;
- alterar o SMC Engine V2;
- calibrar usando o holdout final;
- remover períodos negativos;
- escolher somente os melhores trades;
- ignorar custos;
- aproximar stop apenas para elevar PF;
- aproximar TP1 apenas para elevar win rate;
- permitir múltiplas posições impossíveis;
- usar dados posteriores ao timestamp de decisão;
- declarar superioridade com base apenas em PF bruto;
- promover para live automaticamente.

---

# 4. ARQUITETURA-ALVO

```text
                CANONICAL WINFUT DATASET V2
                           │
                           ▼
                UNIFIED BACKTEST ENGINE V2
                           │
             ┌─────────────┴─────────────┐
             ▼                           ▼
         CONTROL_A                 CANDIDATE_B_V3
             │                           │
             └─────────────┬─────────────┘
                           ▼
               DEFINITIVE A/B COMPARATOR
                           │
                           ▼
                  WINNER FREEZE V1
                           │
                           ▼
                   CANDIDATE_C LAB
                           │
                           ▼
              NESTED WALK-FORWARD SEARCH
                           │
                           ▼
               UNTOUCHED FINAL HOLDOUT
                           │
                           ▼
                 FORWARD SHADOW 60–90
                           │
                           ▼
                    FINAL GO/NO-GO
```

---

# 5. OBJETIVO DE OTIMIZAÇÃO

## 5.1 Métrica principal

Criar:

```text
ROBUST_PROFIT_FACTOR_LCB_95
```

Definição:

```text
limite inferior do intervalo de confiança bootstrap de 95%
do Profit Factor fora da amostra
```

A otimização deve priorizar o limite inferior, e não apenas o PF pontual.

## 5.2 Métricas obrigatórias

```text
PROFIT_FACTOR
PROFIT_FACTOR_LCB_95
EXPECTANCY_R
EXPECTANCY_LCB_95
MAX_DRAWDOWN_R
CALMAR_R
TP1_BEFORE_STOP_ALL_ENTRIES_RATE
TP2_BEFORE_STOP_ALL_ENTRIES_RATE
TP3_BEFORE_STOP_ALL_ENTRIES_RATE
STOP_BEFORE_TP1_RATE
FILL_RATE
EXPIRATION_RATE
AVERAGE_R
MEDIAN_R
MAE_R
MFE_R
TRADE_COUNT
WIN_MONTH_RATIO
WORST_MONTH_R
BEST_MONTH_CONTRIBUTION
MAX_CONSECUTIVE_LOSSES
MAX_CONCURRENT_POSITIONS
```

## 5.3 Restrições mínimas

Uma configuração candidata só pode participar da decisão se:

```text
trades_total >= 300
trades_por_janela >= 50
profit_factor_lcb_95 > 1.00
expectancy_lcb_95 >= 0
max_drawdown_r <= limite configurado
positive_windows_ratio >= 0.70
best_month_profit_contribution <= 0.35
single_setup_profit_contribution <= 0.50
ambiguous_bar_rate <= limite
```

Para etapas preliminares, configurações com amostra menor podem ser marcadas como:

```text
LOW_SAMPLE
```

mas não podem vencer a seleção final.

## 5.4 Função de ranking

Criar:

```python
robust_score = (
    0.35 * normalized_pf_lcb_95
    + 0.20 * normalized_expectancy_lcb_95
    + 0.15 * normalized_stability
    + 0.10 * normalized_positive_month_ratio
    + 0.10 * normalized_sample_quality
    - 0.10 * normalized_drawdown
)
```

Também gerar fronteira de Pareto entre:

```text
Profit Factor
Expectancy
Drawdown
Quantidade de trades
Estabilidade
```

---

# 6. ESTRUTURA DE DIRETÓRIOS

Criar:

```text
technical_engine/
└── signal_research_v2/
    ├── __init__.py
    ├── enums.py
    ├── models.py
    ├── run_manifest.py
    ├── dataset_auditor.py
    ├── canonical_dataset.py
    ├── continuous_contract.py
    ├── unified_execution_engine.py
    ├── position_portfolio.py
    ├── cost_model.py
    ├── partial_exit_model.py
    ├── signal_matcher.py
    ├── bootstrap_metrics.py
    ├── statistical_tests.py
    ├── ab_comparator.py
    ├── optimization_space.py
    ├── nested_walk_forward.py
    ├── pareto.py
    ├── reports.py
    └── persistence.py
```

Criar laboratório:

```text
technical_engine/
└── signal_candidate_c_v1/
    ├── __init__.py
    ├── config.py
    ├── entry_policy.py
    ├── stop_policy.py
    ├── target_policy.py
    ├── filter_policy.py
    ├── partial_policy.py
    ├── dedup_policy.py
    ├── builder.py
    └── errors.py
```

Ferramentas:

```text
tools/
├── audit_winfut_dataset_v2.py
├── build_winfut_canonical_dataset_v2.py
├── run_control_a_unified_backtest.py
├── run_candidate_b_v3_unified_backtest.py
├── compare_signal_systems_definitive.py
├── optimize_winner_candidate_c.py
├── run_nested_walk_forward_candidate_c.py
├── run_final_holdout_candidate_c.py
├── start_forward_shadow_candidate_c.py
└── audit_forward_shadow_candidate_c.py
```

---

# 7. TABELAS SHADOW

Criar migrations para:

```text
technical_engine_signal_research_runs_shadow
technical_engine_signal_research_datasets_shadow
technical_engine_signal_research_trades_shadow
technical_engine_signal_research_metrics_shadow
technical_engine_signal_research_windows_shadow
technical_engine_signal_research_comparisons_shadow
technical_engine_signal_research_bootstrap_shadow
technical_engine_signal_candidate_c_configs_shadow
technical_engine_signal_candidate_c_trials_shadow
technical_engine_signal_candidate_c_forward_shadow
```

Campos obrigatórios do run:

```text
run_id
system_alias
strategy_version
code_commit
config_hash
dataset_hash
dataset_version
execution_model_version
cost_model_version
partial_policy_version
portfolio_policy_version
start_time
end_time
status
created_at
```

---

# 8. FASE 0 — FREEZE E MANIFESTOS

## Objetivo

Congelar todas as versões utilizadas.

## Entregas

Criar manifests imutáveis:

```text
CONTROL_A_FREEZE_V1.json
CANDIDATE_B_V2_FREEZE.json
CANDIDATE_B_V3_FREEZE.json
```

Cada manifest deve incluir:

- commit;
- arquivos;
- hashes;
- configuração;
- costs;
- parciais;
- stops;
- targets;
- timeframes;
- expiração;
- sessão;
- branch;
- data;
- guardrails.

## Critérios

- nenhum sistema original alterado;
- manifests hasháveis;
- working tree registrado;
- inconsistências documentais listadas.

## Relatório

```text
docs_geral/Relatorios/SignalResearchV2/RESULTADO_FASE_0_FREEZE.md
```

---

# 9. FASE 1 — AUDITORIA E DATASET CANÔNICO WINFUT V2

## 9.1 Objetivo

Criar uma base temporal consistente para todos os sistemas.

## 9.2 Auditoria por timeframe

Executar:

```sql
SELECT
    asset_id,
    symbol,
    timeframe,
    COUNT(*) rows_count,
    COUNT(DISTINCT candle_time) distinct_times,
    MIN(candle_time),
    MAX(candle_time)
FROM market_candles
WHERE symbol IN ('WINFUT','WIN$N','WINM26')
GROUP BY asset_id, symbol, timeframe;
```

Verificar:

- duplicidade;
- gaps;
- candles fora de ordem;
- timeframe real;
- timezone;
- open/high/low/close inválidos;
- volume;
- intervalos medianos;
- dias sem negociação;
- feriados;
- sessão.

## 9.3 D1 anômalo

Investigar especificamente os aproximadamente 10.056 registros D1.

Bloquear o estudo se o timeframe estiver incorreto.

## 9.4 Contrato contínuo

Definir política oficial:

```text
RAW_CONTRACT
BACK_ADJUSTED_CONTINUOUS
RATIO_ADJUSTED_CONTINUOUS
```

Para intraday histórico, usar contratos negociados em seus respectivos vencimentos ou série contínua auditada.

Não usar um único contrato atual para meses em que não era líquido sem comprovação.

## 9.5 Rollover

Criar calendário de rollover:

```text
contract
start_date
end_date
roll_date
adjustment
volume_rule
```

## 9.6 Dataset mínimo

Meta:

```text
mínimo aceitável: 12 meses intraday
preferível: 24 meses
ideal: 36 meses
```

Se o histórico confiável disponível permanecer em 6,3 meses:

```text
STATUS = INSUFFICIENT_FOR_FINAL_OPTIMIZATION
```

Ainda poderá haver estudo exploratório, mas não decisão definitiva.

## 9.7 Hash

Gerar:

```text
CANONICAL_WINFUT_DATASET_V2
dataset_hash
schema_hash
row_counts
periods
quality_report
```

## Relatório

```text
RESULTADO_FASE_1_DATASET_CANONICO.md
```

---

# 10. FASE 2 — UNIFIED BACKTEST ENGINE V2

## 10.1 Objetivo

Executar A, B e C sob as mesmas regras.

## 10.2 Execução

### Ordens LIMIT

```text
fill se low <= entry <= high
```

Com regra para gaps:

- gap atravessando a entrada;
- preço executável;
- slippage;
- ordem não preenchida quando aplicável.

### Ordens MARKET

```text
next_open + slippage direcional
```

## 10.3 Candle ambíguo

Usar M1.

Se stop e alvo ocorrerem no mesmo M1:

```text
STOP_FIRST_CONSERVATIVE
```

Se dados de tick estiverem disponíveis, permitir auditoria adicional, sem alterar o baseline.

## 10.4 Custos

Configuração WINFUT versionada:

- spread;
- slippage;
- corretagem;
- emolumentos;
- imposto operacional quando aplicável;
- valor por ponto;
- número de contratos.

## 10.5 Parciais

Implementar genericamente:

```text
NO_PARTIALS
50_25_25
60_30_10
40_30_30
CUSTOM
```

Custos devem ser aplicados em cada saída.

## 10.6 Portfólio e concorrência

Configurações obrigatórias:

```text
max_open_positions_per_symbol = 1
allow_opposite_signal_while_open = false
allow_pyramiding = false
capital_released_only_after_close = true
```

Também gerar relatório alternativo isolado por sinal, mas a decisão principal deve usar o modelo de portfólio executável.

## 10.7 Deduplicação

Definir:

```text
same_zone_id
same_setup_id
same_direction
cooldown_bars
```

## Testes

Cobrir no mínimo:

- fill limit;
- gap;
- market;
- slippage;
- custos;
- parcial;
- stop após TP1;
- TP1 e stop no mesmo candle;
- rollover;
- sessão;
- posição aberta;
- sinal oposto;
- duplicidade;
- determinismo.

## Relatório

```text
RESULTADO_FASE_2_UNIFIED_ENGINE.md
```

---

# 11. FASE 3 — REBACKTEST DEFINITIVO DE CONTROL_A E CANDIDATE_B_V3

## 11.1 Objetivo

Produzir comparação justa.

## 11.2 Regras

Ambos usarão:

- dataset canônico;
- execution engine v2;
- costs iguais;
- portfolio policy igual;
- sessão igual;
- STOP_FIRST igual;
- parciais documentadas.

Criar duas comparações:

### Comparação de lógica original

```text
CONTROL_A com sua gestão original
CANDIDATE_B_V3 com sua gestão original
```

### Comparação de sinal puro

Aplicar a mesma política de saída a ambos:

```text
50_25_25
```

Isso separa:

```text
qualidade do sinal
de
qualidade da gestão
```

## 11.3 Signal matching

Criar correspondência por:

```text
time tolerance
direction
asset
setup context
entry distance
```

Categorias:

```text
MATCHED
ONLY_A
ONLY_B
DIRECTION_CONFLICT
ENTRY_DIFFERENT
```

## 11.4 Saída

Calcular métricas:

- globais;
- por mês;
- por sessão;
- por setup;
- por direção;
- por regime;
- por volatilidade;
- por horário;
- matched signals;
- unmatched signals.

## Relatório

```text
RESULTADO_FASE_3_AB_DEFINITIVO.md
```

---

# 12. FASE 4 — DECISÃO DO VENCEDOR BASELINE

## 12.1 Critérios

Um sistema vence somente se:

```text
PF_LCB_95 superior
expectancy_LCB_95 não inferior
drawdown aceitável
amostra suficiente
estabilidade superior
sem concentração excessiva
```

## 12.2 Testes estatísticos

Aplicar:

- bootstrap por blocos temporais;
- intervalo de confiança de PF;
- intervalo de confiança da expectancy;
- comparação pareada nos matched signals;
- teste de sensibilidade a custos;
- Monte Carlo da sequência de trades.

## 12.3 Status

```text
CONTROL_A_WINS
CANDIDATE_B_WINS
STATISTICAL_TIE
INSUFFICIENT_DATA
```

Se empate:

- não escolher arbitrariamente;
- levar ambos ao forward shadow.

## Relatório

```text
DECISAO_BASELINE_CONTROL_A_VS_CANDIDATE_B.md
```

---

# 13. FASE 5 — DESENHO DO CANDIDATE_C

## 13.1 Objetivo

Melhorar o vencedor sem alterá-lo.

## 13.2 Hipóteses permitidas

### Entrada

- confirmação M2;
- fallback `MARKET_AFTER_TRIGGER`;
- zone edge versus midpoint;
- reteste;
- expiração dinâmica;
- horário;
- sessão;
- distância até zona;
- direção HTF.

### Stop

- M15 primary;
- M15 + sweep;
- M15 + ATR buffer;
- H4 fallback;
- stop cap;
- rejeição de stop excessivamente largo;
- anchor quality.

### Alvos

- TP1 em liquidez interna;
- TP2 em liquidez externa;
- TP3 somente com HTF alignment;
- target barrier;
- dynamic target buffer;
- ausência de TP3 contra tendência.

### Gestão

- parciais;
- break-even somente após estrutura;
- trailing após TP1;
- saída por invalidação;
- time stop;
- session close.

### Filtros

- regime;
- volatilidade;
- sessão;
- horário;
- notícias apenas se fonte disponível;
- setup quality;
- MTF confluence;
- distância ATR;
- spread.

### Deduplicação

- um sinal por zona;
- cooldown;
- um trade por ativo;
- bloquear sinais repetidos.

## 13.3 S4 Breaker

Manter desativado na otimização principal.

Criar estudo separado:

```text
S4_BREAKER_RESEARCH
```

---

# 14. FASE 6 — OTIMIZAÇÃO NESTED WALK-FORWARD

## 14.1 Objetivo

Otimizar sem contaminar teste externo.

## 14.2 Estrutura

Usar nested walk-forward:

```text
Outer train
  └── Inner train/validation para seleção
Outer test
```

Exemplo, se houver 24 meses:

```text
Outer train: 12 meses
Outer test: 3 meses
Step: 3 meses
Inner folds: 3
```

## 14.3 Busca

Preferir:

```text
random search determinístico
ou
Bayesian optimization com seed fixo
```

Evitar grid combinatória completa sem controle de múltiplos testes.

## 14.4 Correção de multiple testing

Registrar:

- número de configurações;
- performance de todas;
- seleção;
- White's Reality Check ou alternativa;
- Deflated Sharpe Ratio, se aplicável;
- Probability of Backtest Overfitting, quando possível.

## 14.5 Early rejection

Descartar rapidamente quando:

```text
PF < 1
expectancy < 0
trades insuficientes
drawdown excessivo
apenas uma janela positiva
```

## 14.6 Resultado

Selecionar:

```text
CANDIDATE_C_CHAMPION
CANDIDATE_C_RUNNER_UP_1
CANDIDATE_C_RUNNER_UP_2
```

Não escolher somente um, para reduzir risco de seleção.

## Relatório

```text
RESULTADO_FASE_6_NESTED_WALK_FORWARD.md
```

---

# 15. FASE 7 — STRESS TESTS

Executar nos três finalistas.

## 15.1 Custos

```text
base
+25%
+50%
+100%
```

## 15.2 Slippage

```text
5
10
15
20 pontos
```

## 15.3 Entrada

- atraso de 1 candle M1;
- atraso de 2 candles M1;
- pior preço dentro de faixa plausível.

## 15.4 Remoção de melhores trades

Remover:

```text
top 1%
top 5%
```

## 15.5 Bootstrap e Monte Carlo

Calcular:

- distribuição PF;
- drawdown esperado;
- pior percentil;
- probability PF > 1;
- probability expectancy > 0.

## 15.6 Regimes

Separar:

- alta volatilidade;
- baixa volatilidade;
- tendência;
- range;
- abertura;
- meio do dia;
- fechamento.

## Critério

O campeão deve continuar aceitável sob stress razoável.

## Relatório

```text
RESULTADO_FASE_7_STRESS_TESTS.md
```

---

# 16. FASE 8 — FINAL HOLDOUT INTOCADO

## 16.1 Regra

O holdout final deve começar depois do freeze completo da versão C.

Nenhum dado dele pode ser analisado durante a otimização.

## 16.2 Duração

Preferível:

```text
mínimo 3 meses
ideal 6 meses
```

Se a data atual não permitir:

```text
status = WAITING_FOR_FINAL_HOLDOUT
```

Não simular conclusão.

## 16.3 Execução

Rodar uma única vez.

Comparar:

```text
CONTROL_A
CANDIDATE_B_V3
CANDIDATE_C_CHAMPION
```

## Critério

Candidate C só supera o baseline se:

```text
PF_LCB_95 maior
expectancy positiva
drawdown aceitável
amostra suficiente
```

## Relatório

```text
RESULTADO_FASE_8_FINAL_HOLDOUT.md
```

---

# 17. FASE 9 — FORWARD SHADOW

## 17.1 Duração

```text
mínimo 60 pregões
ideal 90 pregões
ou três vencimentos
```

## 17.2 Sistemas paralelos

```text
CONTROL_A
CANDIDATE_B_V3
CANDIDATE_C_CHAMPION
```

## 17.3 Regras

- sinais em tempo real;
- sem recalibração;
- sem envio ao cliente;
- sem ordem real;
- persistência de todos os eventos;
- entrada disponível;
- fill simulado;
- custos;
- stop;
- targets;
- parciais;
- expirados.

## 17.4 Relatórios

Diário, semanal e final.

## Relatório final

```text
RESULTADO_FASE_9_FORWARD_SHADOW.md
```

---

# 18. FASE 10 — DECISÃO FINAL

Status possíveis:

```text
CONTROL_A_REMAINS_BEST
CANDIDATE_B_REMAINS_BEST
CANDIDATE_C_OUTPERFORMS
STATISTICAL_TIE
NEEDS_MORE_DATA
```

Nenhum status live será permitido nesta fase.

Próximo estado máximo:

```text
READY_FOR_PRODUCT_SHADOW_INTEGRATION
```

---

# 19. CRITÉRIOS PARA MAXIMIZAR PF SEM DESTRUIR O SISTEMA

O Profit Factor poderá ser elevado por:

1. remover setups persistentemente negativos;
2. evitar horários ruins;
3. bloquear regimes inadequados;
4. exigir confirmação M2 quando melhora PF fora da amostra;
5. reduzir sinais duplicados;
6. melhorar fill com fallback controlado;
7. ajustar expiração;
8. usar stops estruturais adequados;
9. limitar stops excessivos;
10. remover TP3 quando não há HTF alignment;
11. melhorar parciais;
12. aplicar break-even somente com evidência estrutural;
13. evitar zonas com barreiras próximas;
14. exigir R:R mínimo realista;
15. reduzir custos e slippage operacional quando possível.

Não poderá ser elevado por:

- eliminar losses do relatório;
- reduzir stop sem invalidação;
- aproximar alvo artificialmente;
- escolher período favorável;
- usar lookahead;
- repetir otimização no holdout;
- remover meses negativos;
- selecionar poucos trades excepcionalmente bons.

---

# 20. TESTES AUTOMATIZADOS

Criar:

```text
tests/test_signal_research_v2/
tests/test_signal_candidate_c_v1/
```

Cobertura mínima:

- dataset;
- rollover;
- D1;
- timezone;
- duplicate candles;
- fill;
- gap;
- costs;
- partial exits;
- overlap;
- cooldown;
- opposite signal;
- bootstrap;
- PF;
- drawdown;
- matched signals;
- nested walk-forward;
- holdout isolation;
- deterministic optimization;
- manifests;
- guards contra alteração A/B.

Meta:

```text
mínimo 120 testes novos
```

---

# 21. RELATÓRIOS OBRIGATÓRIOS

Criar:

```text
docs_geral/Relatorios/SignalResearchV2/
```

Arquivos:

```text
RESULTADO_FASE_0_FREEZE.md
RESULTADO_FASE_1_DATASET_CANONICO.md
RESULTADO_FASE_2_UNIFIED_ENGINE.md
RESULTADO_FASE_3_AB_DEFINITIVO.md
DECISAO_BASELINE_CONTROL_A_VS_CANDIDATE_B.md
RESULTADO_FASE_5_DESENHO_CANDIDATE_C.md
RESULTADO_FASE_6_NESTED_WALK_FORWARD.md
RESULTADO_FASE_7_STRESS_TESTS.md
RESULTADO_FASE_8_FINAL_HOLDOUT.md
RESULTADO_FASE_9_FORWARD_SHADOW.md
DECISAO_FINAL_SIGNAL_RESEARCH_V2.md
```

---

# 22. CRITÉRIOS DE ACEITE

O plano só pode ser considerado concluído quando:

- A e B congelados;
- dataset auditado;
- D1 validado;
- rollover validado;
- mesmo simulador aplicado a A e B;
- parciais comparadas de forma justa;
- overlap controlado;
- custos iguais;
- comparação estatística;
- vencedor baseline definido ou empate reconhecido;
- Candidate C criado separadamente;
- nested walk-forward executado;
- stress tests executados;
- holdout realmente intocado;
- forward shadow real concluído;
- decisão final documentada.

---

# 23. ROLLBACK

Como toda a pesquisa é isolada:

```text
SIGNAL_RESEARCH_V2_ENABLED=false
CANDIDATE_C_ENABLED=false
```

Rollback:

- parar runners;
- preservar dados;
- preservar tabelas;
- não alterar A;
- não alterar B;
- não alterar produção;
- registrar motivo.

---

# 24. CONCLUSÃO

A primeira pergunta deste plano é:

```text
CONTROL_A ou CANDIDATE_B é realmente melhor
quando ambos são executados sob as mesmas regras?
```

Somente após essa resposta será feita a segunda pergunta:

```text
Como melhorar o vencedor para aumentar o Profit Factor robusto
sem sacrificar estabilidade, amostra e controle de risco?
```

O melhor sistema será aquele que apresentar o maior:

```text
ROBUST_PROFIT_FACTOR_LCB_95
```

com expectancy positiva, drawdown aceitável, estabilidade temporal e confirmação em holdout e forward shadow.
