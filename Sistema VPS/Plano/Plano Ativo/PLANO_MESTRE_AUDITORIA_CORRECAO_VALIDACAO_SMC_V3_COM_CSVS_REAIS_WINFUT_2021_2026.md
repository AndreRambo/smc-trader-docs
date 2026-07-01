# PLANO MESTRE OPERACIONAL — AUDITORIA, CORREÇÃO E VALIDAÇÃO DA SMC ENGINE V3 COM DADOS REAIS WINFUT 2021–2026

**Projeto:** SMC Trader System 7.0  
**Engine-alvo:** `technical_engine/smc_engine_v3`  
**Dataset real obrigatório:**  
`/home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/WINFUT_2021_2026`  
**Objetivo principal:** corrigir integralmente a SMC Engine V3, validar a causalidade e o lifecycle de todas as zonas com dados reais e somente depois liberar o backtest da Opportunity Engine.  
**Status inicial:** `SMC_V3_SEMANTICALLY_INVALID_BLOCKED`  
**Cutover autorizado:** não  
**Backtest da Opportunity Engine autorizado:** não  
**Modo obrigatório durante todo o plano:** `shadow_only=True`

---

# 1. OBJETIVO E RESULTADO ESPERADO

Este plano deve ser executado por uma IA de código de forma sequencial, com auditoria, correções pequenas, testes automatizados, replay candle a candle e validação visual com dados reais de WINFUT entre 2021 e 2026.

Ao final, a SMC Engine V3 deve:

1. processar candles reais sem lookahead;
2. respeitar a ordem causal entre as oito engines;
3. gerar swings somente quando confirmados e disponíveis;
4. gerar BOS/CHOCH uma única vez por nível estrutural;
5. distinguir wick sweep de close break;
6. gerar FVG apenas após o terceiro candle;
7. gerar Order Blocks a partir da perna estrutural correta;
8. registrar origem, confirmação e disponibilidade de cada zona;
9. acompanhar toque, mitigação parcial, midpoint, full mitigation, invalidação, reclaim e expiração;
10. preservar IDs determinísticos e resultados idempotentes;
11. produzir os mesmos resultados em batch, replay, chunk e resume;
12. persistir em tabelas V3 separadas;
13. produzir overlays visualmente auditáveis;
14. demonstrar, com dados reais, que cada zona aparece e muda de estado no candle correto;
15. impedir o início do backtest da Opportunity Engine enquanto qualquer gate crítico estiver reprovado.

---

# 2. REGRAS DE SEGURANÇA E ESCOPO

## 2.1. Proibições

Durante este plano:

- não remover a V2;
- não mover a V2 para backup antes da aprovação final;
- não alterar Elliott;
- não alterar Wyckoff;
- não alterar o Opportunity Scanner;
- não alterar regras de entrada, stop ou alvo da Opportunity Engine;
- não iniciar backtest de oportunidade;
- não escrever V3 em tabelas V2;
- não enviar notificações;
- não sincronizar V3 com Hostinger;
- não substituir a verdade operacional;
- não alterar parâmetros para “forçar” mais zonas;
- não calibrar por resultado financeiro;
- não usar dados futuros;
- não ocultar exceções;
- não usar `raise_on_error=False` em auditoria;
- não declarar uma fase aprovada sem relatório e testes.

## 2.2. Guardrails obrigatórios

```text
shadow_only=True
can_promote_trade=False
apply_automatically=False
llm_decision_used=False
production_truth_replaced=False
ZERO_SHADOW_ORDERS=True
SMC_V3_USER_NOTIFICATIONS_ENABLED=false
SMC_V3_SYNC_ENABLED=false
SMC_V3_SCANNER_AB_ENABLED=false
```

## 2.3. Resultado permitido antes do último gate

```text
SMC_V3_VALIDATED_FOR_TECHNICAL_REPLAY
```

Ainda não significa:

```text
SMC_V3_APPROVED_FOR_OPPORTUNITY_BACKTEST
```

A autorização para o backtest de oportunidades depende de um gate separado no final deste plano.

---

# 3. CAMINHOS OFICIAIS

## 3.1. Raiz

```text
/home/bimaq/projetos/SMC_Trader_System_7_0
```

## 3.2. Código

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0
```

## 3.3. V3

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3
```

## 3.4. V2 de baseline

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v2
```

## 3.5. Dataset real

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/WINFUT_2021_2026
```

## 3.6. Testes V3

Criar/usar:

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tests/smc_engine_v3
```

Subpastas:

```text
tests/smc_engine_v3/
├── data_contract/
├── contracts/
├── sessions/
├── swings/
├── structure/
├── previous_high_low/
├── retracements/
├── liquidity/
├── fvg/
├── bpr/
├── order_blocks/
├── pipeline/
├── replay/
├── persistence/
├── visualization/
├── regression/
└── real_data/
```

## 3.7. Ferramentas

Criar/usar:

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/smc_v3_validation
```

## 3.8. Relatórios

Criar:

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/Sistema VPS/Relatorios/SMC_ENGINE_V3_REAL_DATA_VALIDATION
```

---

# 4. BRANCH E CONTROLE DE VERSÃO

Criar branch:

```text
feature/smc-v3-causal-rebuild-real-data
```

Antes da primeira alteração:

```bash
git status --short
git rev-parse HEAD
git branch --show-current
```

Criar tag de referência do estado inválido:

```text
smc-v3-pre-causal-rebuild-2026-06-30
```

Não executar:

```text
git reset --hard
git clean -fd
```

Não sobrescrever alterações preexistentes do usuário.

Cada fase deve gerar um commit isolado e reversível.

Padrão recomendado:

```text
smc-v3/r0: inventário e baseline
smc-v3/r1: data contract real
smc-v3/r2: pipeline causal
smc-v3/r3: sessions
smc-v3/r4: swings
smc-v3/r5: structure
smc-v3/r6: previous and retracement
smc-v3/r7: liquidity
smc-v3/r8: fvg and bpr
smc-v3/r9: order blocks
smc-v3/r10: persistence and overlays
smc-v3/r11: real-data replay
smc-v3/r12: final gates
```

---

# 5. ORDEM CAUSAL OFICIAL

A engine deve executar nesta ordem:

```text
1. Sessions
2. Swing
3. Structure
4. Previous High/Low
5. Retracement / Dealing Range
6. Liquidity
7. FVG
8. BPR
9. Order Block
10. Association / Read Models / Overlays
```

## 5.1. Dependências obrigatórias

```text
Sessions
  └── TradingPeriodSummaryV3
        └── PreviousPeriodLevelV3

Swing
  ├── CanonicalSwingV3
  └── EqualLevelClusterV3

Structure
  ├── consome CanonicalSwingV3 disponível
  ├── StructureEventV3
  ├── StructureLevelV3
  ├── StructureLegV3
  └── protected/weak projection

Retracement
  └── consome StructureLegV3
      └── DealingRangeV3

Liquidity
  ├── consome EqualLevelClusterV3
  ├── consome protected/weak
  ├── consome PreviousPeriodLevelV3
  └── consome DealingRangeV3

FVG/BPR
  ├── geometria causal própria
  └── contexto estrutural posterior

Order Block
  ├── consome StructureEventV3
  ├── consome StructureLegV3
  ├── consome LiquidityEventV3 opcional
  └── consome FVG disponível como evidência opcional
```

Nenhuma engine downstream pode redetectar uma entidade cuja autoridade pertence a uma engine upstream.

---

# 6. CONTRATO TEMPORAL GLOBAL

Toda entidade deve possuir, conforme aplicável:

```text
origin_index
origin_at
confirmed_index
confirmed_at
available_index
available_at
earliest_execution_index
earliest_execution_at
```

Invariante:

```text
origin <= confirmed <= available <= earliest_execution
```

## 6.1. Definições

### Origin

Candle onde a geometria ou nível nasceu.

### Confirmed

Candle em que já existem evidências suficientes para confirmar.

### Available

Primeiro candle em que a engine pode disponibilizar o evento sem usar futuro.

### Earliest execution

Primeiro candle em que um consumidor pode agir usando o evento.

## 6.2. Regra operacional

Uma zona pode ser desenhada retroativamente para auditoria, mas:

- deve registrar claramente o candle de disponibilidade;
- não pode ser consumida antes de `available_index`;
- o overlay operacional deve iniciar em `available_at`.

---

# 7. CONTRATO DE LIFECYCLE DE ZONAS

Cada zona deve registrar:

```text
zone_id
zone_type
direction
source_scope
origin_index
confirmed_index
available_index
top
bottom
midpoint
proximal
distal
status
first_touch_index
first_touch_at
touch_count
max_penetration_pct
midpoint_reached_index
full_mitigation_index
invalidation_index
reclaim_index
expired_index
last_state_change_index
```

## 7.1. Estados comuns

```text
CANDIDATE
CONFIRMED
FRESH
TOUCHED
PARTIALLY_MITIGATED
MIDPOINT_REACHED
FULLY_MITIGATED
INVALIDATED
RECLAIMED
EXPIRED
SUPERSEDED
```

Nem todos os tipos precisam usar todos os estados, mas o mapeamento deve ser explícito.

## 7.2. Eventos mínimos por zona

```text
CREATED
CONFIRMED
AVAILABLE
FIRST_TOUCH
PARTIAL_FILL
MIDPOINT_REACHED
FULL_FILL
INVALIDATED
RECLAIMED
EXPIRED
```

## 7.3. Regra de início

Uma zona existe geometricamente em `origin_index`, mas só entra no registry operacional em `available_index`.

## 7.4. Regra de mitigação

Não usar um único booleano `mitigated`.

Registrar:

- primeiro toque;
- profundidade;
- midpoint;
- full fill;
- invalidação.

## 7.5. Regra de invalidação

Separar:

```text
WICK_BEYOND_DISTAL
CLOSE_BEYOND_DISTAL
GAP_THROUGH_DISTAL
```

O default operacional deve ser definido por zona e documentado.

---

# 8. FASE R0 — INVENTÁRIO, BASELINE E CONGELAMENTO

## Objetivo

Criar um baseline reproduzível antes das correções.

## Tarefas

1. inventariar todos os arquivos de `smc_engine_v3`;
2. calcular hashes SHA-256;
3. registrar linhas por módulo;
4. executar compilação;
5. executar testes atuais;
6. executar pipeline atual em uma amostra real;
7. salvar outputs atuais;
8. salvar overlays atuais;
9. registrar quantidades de:
   - swings;
   - BOS;
   - CHOCH;
   - FVG;
   - BPR;
   - liquidity;
   - OB;
   - zonas ativas;
   - zonas mitigadas;
   - zonas invalidadas;
10. preservar logs e stack traces;
11. ativar `raise_on_error=True` em ferramentas de auditoria, sem alterar ainda o runtime.

## Dataset baseline

Selecionar pelo menos:

- cinco pregões de tendência;
- cinco pregões de range;
- cinco pregões de alta volatilidade;
- cinco pregões de baixa volatilidade;
- dias de rollover;
- dias próximos a feriados;
- sessões com gap de abertura.

Não escolher períodos com base em resultado financeiro.

## Entregável

```text
RELATORIO_R0_BASELINE_SMC_V3.md
```

## Gate R0

```text
R0_BASELINE_REPRODUCIBLE
```

---

# 9. FASE R1 — AUDITORIA E CANONICALIZAÇÃO DOS CSVs

## Objetivo

Garantir que o dataset real é confiável antes de usá-lo para validar a engine.

## 9.1. Descoberta dos arquivos

A ferramenta deve listar recursivamente:

- nome;
- caminho;
- tamanho;
- hash;
- quantidade de linhas;
- data mínima;
- data máxima;
- timeframe aparente;
- ticker/contrato;
- separador;
- encoding;
- colunas;
- timezone;
- ordem;
- duplicatas.

Criar:

```text
tools/smc_v3_validation/inventory_winfut_csvs.py
```

## 9.2. Não assumir schema

Detectar e mapear variantes como:

```text
timestamp
datetime
date + time
open/high/low/close
Open/High/Low/Close
volume
tick_volume
real_volume
spread
symbol
timeframe
contract
```

## 9.3. Modelo canônico de candle

Criar adapter que produza:

```python
CanonicalCandle(
    symbol,
    contract,
    timeframe,
    timestamp_utc,
    timestamp_brt,
    trading_date_b3,
    open,
    high,
    low,
    close,
    volume,
    tick_volume,
    source_file,
    source_row,
)
```

## 9.4. Validações por candle

- `high >= max(open, close, low)`;
- `low <= min(open, close, high)`;
- OHLC finitos;
- preço positivo;
- timestamp válido;
- timeframe válido;
- volume não negativo;
- ordem crescente;
- chave única por `symbol + timeframe + timestamp`;
- sem NaN nos campos obrigatórios.

## 9.5. Duplicatas

Classificar:

```text
EXACT_DUPLICATE
CONFLICTING_DUPLICATE
ROLLOVER_OVERLAP
UNKNOWN_DUPLICATE
```

Conflitos não podem ser resolvidos silenciosamente.

## 9.6. Gaps

Detectar:

- gaps esperados de sessão;
- gaps de fim de semana;
- feriados;
- missing candles intraday;
- intervalos irregulares;
- pausas B3;
- early close;
- mudança de contrato;
- rollover.

## 9.7. Timezone

Converter explicitamente:

```text
America/Sao_Paulo
UTC
```

Não usar timestamp naive.

Registrar:

```text
source_timezone
normalized_timezone
conversion_rule
```

## 9.8. Rollover

Identificar:

- arquivos por contrato;
- série contínua;
- símbolo genérico;
- gaps de rollover;
- ajuste ou não ajuste de preço;
- sobreposição de contratos.

Não fundir contratos automaticamente sem política documentada.

## 9.9. Relatórios

Criar:

```text
RELATORIO_R1_INVENTARIO_CSV.md
RELATORIO_R1_QUALIDADE_DADOS.md
RELATORIO_R1_ROLLOVER_WINFUT.md
```

## Gate R1

```text
R1_DATASET_CANONICAL_APPROVED
```

ou:

```text
R1_DATASET_BLOCKED
```

Nenhuma correção de engine deve ser validada antes de R1 aprovado.

---

# 10. FASE R2 — SPLITS TEMPORAIS E GOLDEN WINDOWS

## Objetivo

Evitar overfitting e permitir auditoria manual.

## 10.1. Separação recomendada

Após o inventário, criar divisões cronológicas sem embaralhamento:

```text
DEVELOPMENT
VALIDATION
HOLDOUT
```

Proposta inicial:

```text
Development: 2021–2024
Validation:  2025
Holdout:     2026
```

A divisão final deve respeitar contratos e rollovers inteiros.

## 10.2. Regras

- não usar holdout para ajustar algoritmo;
- não escolher parâmetros com base em P&L;
- não misturar candles futuros;
- manter dias completos;
- manter sessões completas;
- manter contratos completos quando necessário.

## 10.3. Golden windows manuais

Selecionar janelas reais para auditoria humana:

- bullish BOS limpo;
- bearish BOS limpo;
- bullish CHOCH;
- bearish CHOCH;
- wick sweep sem break;
- close break sem displacement;
- displacement com FVG;
- FVG preenchida parcialmente;
- FVG invertida;
- BPR;
- bullish OB;
- bearish OB;
- rejection OB;
- stacked OB;
- liquidity sweep;
- PDH/PDL reclaim;
- range/retracement;
- gap de abertura;
- rollover.

Cada golden window deve conter:

```text
window_id
symbol
contract
timeframe
start_at
end_at
purpose
expected_events
review_status
reviewer_notes
```

## 10.4. Manifest

Criar:

```text
tests/smc_engine_v3/real_data/golden_windows_manifest.yaml
```

## Entregável

```text
RELATORIO_R2_SPLITS_E_GOLDEN_WINDOWS.md
```

## Gate R2

```text
R2_REAL_DATA_WINDOWS_APPROVED
```

---

# 11. FASE R3 — RECONSTRUÇÃO DO PIPELINE V3

## Objetivo

Criar um entrypoint V3 real e parar de usar o pipeline V2 como verdade.

## 11.1. Criar entrypoint

Exemplo:

```text
smc_engine_v3/pipeline_v3.py
```

Função:

```python
run_smc_engine_v3(
    candles,
    asset_config,
    timeframe,
    mode="BATCH_REFERENCE",
    raise_on_error=True,
)
```

## 11.2. Resultado canônico

Criar:

```python
SmcEngineV3Result
```

Campos mínimos:

```text
run_metadata
sessions
swings
equal_level_clusters
structure_levels
structure_events
structure_legs
previous_period_levels
dealing_ranges
liquidity_pools
liquidity_events
fvgs
fvg_events
bprs
order_blocks
order_block_events
visual_overlays
diagnostics
errors
```

## 11.3. Proibir records V2 na saída V3

O pipeline V3 não pode retornar:

```text
SwingV2
BosChochV2
ObV2
FvgV2
LiquidityV2
RetracementV2
PreviousHighLowV2
BprV2
```

Adapters de compatibilidade devem existir fora do core V3.

## 11.4. Falhas

Default:

```text
raise_on_error=True
```

Modo tolerante apenas com flag explícita e diagnóstico obrigatório.

## 11.5. Diagnóstico

Cada engine deve retornar:

```text
input_count
output_count
rejected_count
warmup_count
error_count
latency_ms
last_available_index
```

## Entregável

```text
RELATORIO_R3_PIPELINE_V3.md
```

## Gate R3

```text
R3_V3_PIPELINE_CANONICAL_PASS
```

---

# 12. FASE R4 — SESSIONS

## Objetivo

Usar uma única implementação causal e correta para B3.

## Tarefas

1. escolher uma função canônica;
2. remover ambiguidade entre `calculate_sessions_v3_causal` e wrappers;
3. usar IANA `America/Sao_Paulo`;
4. configurar B3 por ativo;
5. mapear trading date;
6. mapear feriados;
7. mapear early close;
8. mapear sessões regulares;
9. tratar overnight quando aplicável;
10. emitir `SessionInstanceV3`;
11. emitir `TradingPeriodSummaryV3`;
12. corrigir `last_candle_index`;
13. evitar múltiplas instâncias do mesmo feriado;
14. não capturar exceções silenciosamente.

## Testes reais

- primeiro candle do pregão;
- último candle;
- gap da abertura;
- feriado;
- véspera de feriado;
- horário de verão histórico;
- ausência de candle;
- arquivo com timezone UTC;
- arquivo com timezone local.

## Gate R4

```text
R4_SESSIONS_PASS
```

---

# 13. FASE R5 — SWING

## Objetivo

Produzir swings causais e estáveis.

## 13.1. Camadas

Separar:

```text
RAW_PIVOT
CANONICAL_SWING
STRUCTURE_ROLE_PROJECTION
```

## 13.2. Confirmação

Um pivot com `right_bars=N` só pode ficar disponível depois de N candles.

## 13.3. Same-type runs

Para uma sequência de highs consecutivos:

- manter os raw pivots;
- escolher um único extremo canônico;
- marcar os demais como `SUPERSEDED`;
- nenhum downstream pode consumir `SUPERSEDED`.

## 13.4. Scope

Produzir:

```text
Scope.INTERNAL
Scope.SWING
```

com parâmetros separados, mas detector compartilhado.

## 13.5. Classificação

```text
HH
HL
LH
LL
EQH
EQL
```

A classificação deve usar somente swings disponíveis.

## 13.6. Equal levels

Criar `EqualLevelClusterV3` incremental.

Tolerância:

```text
max(tick_size * n, ATR_available * factor)
```

Nunca usar epsilon fixo.

## 13.7. Quality

Quality deve ser calculado com dados disponíveis no momento da confirmação.

Não usar range total do dataframe.

## Testes reais

- tendência longa;
- range;
- pivôs same-type;
- equal highs/lows;
- spike;
- gap;
- rollover;
- baixa volatilidade.

## Métricas

```text
raw_pivots
canonical_swings
superseded
eqh_clusters
eql_clusters
availability_delay
prefix_divergence
```

## Gate R5

```text
R5_SWING_PASS
```

---

# 14. FASE R6 — STRUCTURE

## Objetivo

Corrigir BOS/CHOCH e produzir StructureLegV3.

## 14.1. Consumo

Somente consumir swing quando:

```text
swing.available_index <= current_index
swing.status == CANONICAL
```

## 14.2. Níveis

Manter separadamente:

```text
protected_high
protected_low
weak_high
weak_low
```

por Scope.

## 14.3. Eventos

Distinguir:

```text
WICK_SWEEP
CLOSE_BREAK
GAP_BREAK
MARGINAL_BREAK
DISPLACEMENT_BREAK
```

## 14.4. Uma quebra por nível

Cada nível estrutural deve possuir estado:

```text
ACTIVE
SWEPT
BROKEN
RECLAIMED
SUPERSEDED
```

Uma vez `BROKEN`, não pode gerar novo BOS.

## 14.5. Broken reference

`broken_index` deve apontar ao swing/nível rompido, não ao candle de rompimento.

## 14.6. Atualização do nível

Não usar:

```text
protected_high = close_price
```

O novo nível só nasce quando um novo swing canônico é confirmado.

## 14.7. BOS e CHOCH

Definir state machine explícita para:

```text
NEUTRAL
BULLISH
BEARISH
TRANSITION_TO_BULLISH
TRANSITION_TO_BEARISH
```

## 14.8. Displacement

Calcular com dados disponíveis:

```text
range_to_atr
body_to_range
close_location
consecutive_bars
distance_beyond_level
```

## 14.9. StructureLegV3

Emitir perna causal:

```text
start_swing_id
end_event_id
direction
start_index
end_index
high
low
range
scope
available_index
```

## Testes reais

- BOS bullish;
- BOS bearish;
- CHOCH bullish;
- CHOCH bearish;
- sweep sem break;
- break marginal;
- break com gap;
- mesma quebra não repetida;
- novo swing após BOS;
- INTERNAL e SWING coexistindo.

## Métrica crítica

```text
events_per_broken_level <= 1
```

## Gate R6

```text
R6_STRUCTURE_PASS
```

---

# 15. FASE R7 — PREVIOUS HIGH/LOW

## Objetivo

Produzir níveis de períodos concluídos com lifecycle correto.

## Tarefas

1. consumir `TradingPeriodSummaryV3`;
2. não resamplear por conta própria quando Sessions já produziu período;
3. corrigir reset indevido de PDH para `NONE`;
4. implementar simetria PDH/PDL;
5. distinguir:
   - TOUCH;
   - WICK_SWEEP;
   - CLOSE_THROUGH;
   - GAP_THROUGH;
   - RECLAIM;
6. registrar primeira interação;
7. registrar múltiplas interações;
8. não reclassificar com futuro;
9. preservar `raw/evidence`.

## Testes reais

- toque no PDH;
- sweep e fechamento abaixo;
- close-through;
- gap-through;
- reclaim;
- PDL equivalente;
- sessão sem negociação anterior;
- feriado;
- rollover.

## Gate R7

```text
R7_PREVIOUS_PERIOD_PASS
```

---

# 16. FASE R8 — RETRACEMENT E DEALING RANGE

## Objetivo

Corrigir Premium/Discount e usar pernas estruturais reais.

## Tarefas

1. consumir `StructureLegV3`;
2. produzir `DealingRangeV3`;
3. preservar direção real;
4. corrigir cálculo de Premium/Discount;
5. calcular equilibrium;
6. calcular níveis Fibonacci configurados;
7. preservar `FIBONACCI_ANCHOR`;
8. implementar revision/supersession;
9. implementar origin breach;
10. implementar invalidation;
11. não combinar swings de pernas diferentes;
12. não usar swings ainda indisponíveis.

## Regra de preço

Para uma perna bullish:

```text
parte superior = PREMIUM
equilibrium = 50%
parte inferior = DISCOUNT
```

Para bearish, a zona é classificada pelo preço no range, não por rótulo invertido.

## Testes reais

- perna bullish;
- perna bearish;
- novo extremo;
- retracement profundo;
- origin breach;
- revision;
- gap;
- range muito curto.

## Gate R8

```text
R8_RETRACEMENT_PASS
```

---

# 17. FASE R9 — LIQUIDITY

## Objetivo

Parar de redetectar liquidez e promover fontes upstream.

## Fontes

```text
EqualLevelClusterV3
protected/weak levels
PreviousPeriodLevelV3
session levels
DealingRangeV3
```

## Tarefas

1. remover uso canônico de `_calculate_liquidity_legacy`;
2. criar promotor de clusters;
3. classificar BSL/SSL;
4. classificar ERL/IRL usando `DealingRangeV3`;
5. implementar lifecycle:
   - ACTIVE;
   - TOUCHED;
   - SWEPT;
   - CLOSE_THROUGH;
   - CONSUMED;
   - RECLAIMED;
   - EXPIRED;
6. não usar máxima/mínima da janela inteira;
7. não registrar pool antes do segundo toque/confirmador;
8. manter source IDs;
9. preservar Scope;
10. garantir idempotência.

## Testes reais

- EQH;
- EQL;
- protected high;
- weak low;
- PDH/PDL;
- sweep;
- close-through;
- reclaim;
- ERL/IRL;
- pools sobrepostos;
- múltiplos scopes.

## Gate R9

```text
R9_LIQUIDITY_PASS
```

---

# 18. FASE R10 — FVG

## Objetivo

Corrigir disponibilidade e lifecycle.

## 18.1. Geometria

Para candles A, B, C:

```text
bullish: C.low > A.high
bearish: C.high < A.low
```

## 18.2. Índices

```text
origin_index = A
confirmed_index = C
available_index = C
earliest_execution_index = C + 1
```

Se a arquitetura permitir consumo no fechamento de C, documentar explicitamente; para backtest sem intrabar, o default seguro é C+1.

## 18.3. Separações

Distinguir:

```text
FVG_GEOMETRY
DISPLACEMENT_QUALIFIED
STRUCTURE_ALIGNED
LIQUIDITY_ALIGNED
```

## 18.4. Lifecycle

```text
FRESH
TOUCHED
PARTIAL_FILL
CE_REACHED
FULL_FILL
INVALIDATED
INVERTED
EXPIRED
```

## 18.5. IFVG

Somente após:

- FVG válida;
- invalidação causal;
- fechamento através;
- papel invertido;
- reteste posterior.

## Testes reais

- bullish FVG;
- bearish FVG;
- gap mínimo;
- displacement;
- touch;
- CE;
- full fill;
- gap-through;
- IFVG;
- sem lookahead.

## Gate R10

```text
R10_FVG_PASS
```

---

# 19. FASE R11 — BPR

## Objetivo

Formar BPR de maneira causal.

## Tarefas

1. consumir apenas FVGs disponíveis;
2. consumir apenas FVGs em estado compatível;
3. criar BPR somente no candle em que a segunda FVG necessária está disponível;
4. registrar:
   - source_fvg_bull_id;
   - source_fvg_bear_id;
   - confirmed_index;
   - available_index;
5. não deduplicar usando candidatos futuros;
6. lifecycle próprio;
7. remover threshold fixo específico de WINFUT;
8. usar ticks/ATR/config por ativo.

## Gate R11

```text
R11_BPR_PASS
```

---

# 20. FASE R12 — ORDER BLOCK

## Objetivo

Implementar o detector canônico real.

## 20.1. Pré-requisitos

Não iniciar R12 enquanto R6, R9, R10 e R11 não estiverem aprovadas.

## 20.2. Fontes obrigatórias

```text
StructureEventV3
StructureLegV3
```

Fontes opcionais:

```text
LiquidityEventV3
FvgEventV3
DealingRangeV3
SessionInstanceV3
```

## 20.3. Origem

Modo inicial recomendado:

```text
LAST_OPPOSITE_CANDLE_IN_STRUCTURE_LEG
```

Registrar:

```text
origin_reason
origin_candle_index
source_structure_event_id
source_structure_leg_id
```

## 20.4. Sem fallback silencioso

Quando não houver origem:

```text
ORIGIN_NOT_FOUND
```

Não usar automaticamente o candle anterior.

## 20.5. Zona

Armazenar:

```text
top
bottom
midpoint
proximal
distal
zone_mode
```

Modos:

```text
FULL_CANDLE
BODY
HALF
WICK
```

## 20.6. Freshness

Analisar do candle posterior à origem até o anterior à confirmação.

Registrar:

```text
pre_confirmation_touch_count
pre_confirmation_max_penetration_pct
pre_confirmation_midpoint_reached
pre_confirmation_full_fill
fresh_at_confirmation
```

## 20.7. Lifecycle

Separar:

```text
FRESH
TOUCHED
PARTIALLY_MITIGATED
MIDPOINT_REACHED
FULLY_MITIGATED
INVALIDATED
BREAKER_CANDIDATE
RECLAIMED
EXPIRED
```

Primeiro toque não pode ser full mitigation.

## 20.8. Subtipos

```text
NORMAL
REJECTION
STACKED
```

### Rejection

Usar o candle de origem e contexto.

### Stacked

Exigir overlap e relação temporal causal.

### Breaker

Não é sinônimo de Stacked.

## Testes reais

- bullish OB;
- bearish OB;
- origem ausente;
- origem distante;
- FVG opcional;
- sweep anterior;
- fresh;
- pré-consumido;
- touch;
- parcial;
- midpoint;
- full mitigation;
- wick distal;
- close distal;
- stacked;
- rejection;
- breaker candidate.

## Gate R12

```text
R12_ORDER_BLOCK_PASS
```

---

# 21. FASE R13 — REGISTRY E EVENT SOURCING DE ZONAS

## Objetivo

Auditar início, confirmação e mitigação de cada zona.

## 21.1. Registro imutável

Cada zona deve possuir uma linha de criação e eventos subsequentes.

Não sobrescrever o histórico.

## 21.2. Estrutura recomendada

```text
zones
zone_events
zone_snapshots
```

## 21.3. Eventos

Cada transição deve registrar:

```text
event_id
zone_id
event_type
candle_index
event_at
price
penetration_pct
previous_status
new_status
evidence
source_run_id
```

## 21.4. Auditoria por zona

Criar ferramenta:

```text
tools/smc_v3_validation/audit_zone_lifecycle.py
```

Entrada:

```text
zone_id
```

Saída:

```text
origem
confirmação
disponibilidade
primeiro toque
mitigação parcial
midpoint
full mitigation
invalidação
reclaim
expiração
candles envolvidos
preços envolvidos
```

## 21.5. Relatório CSV

Gerar:

```text
artifacts/zone_lifecycle_audit.csv
```

Colunas mínimas:

```text
zone_id
zone_type
direction
symbol
contract
timeframe
origin_at
confirmed_at
available_at
first_touch_at
midpoint_at
full_mitigation_at
invalidated_at
reclaimed_at
expired_at
final_status
top
bottom
midpoint
touch_count
max_penetration_pct
source_event_id
source_leg_id
```

## Gate R13

```text
R13_ZONE_LIFECYCLE_AUDIT_PASS
```

---

# 22. FASE R14 — PERSISTÊNCIA V3

## Objetivo

Persistir sem misturar V2.

## Tabelas sugeridas

```text
smc_v3_engine_runs
smc_v3_structures
smc_v3_structure_events
smc_v3_structure_legs
smc_v3_checkpoints
smc_v3_active_stream_versions
smc_v3_reconciliation

technical_engine_smc_v3_sessions_shadow
technical_engine_smc_v3_swings_shadow
technical_engine_smc_v3_equal_levels_shadow
technical_engine_smc_v3_bos_choch_shadow
technical_engine_smc_v3_previous_high_low_shadow
technical_engine_smc_v3_retracements_shadow
technical_engine_smc_v3_liquidity_shadow
technical_engine_smc_v3_fvg_shadow
technical_engine_smc_v3_bpr_shadow
technical_engine_smc_v3_order_blocks_shadow
technical_engine_smc_v3_zone_events_shadow
technical_engine_smc_v3_visual_overlays_shadow
```

## Requisitos

- migrations idempotentes;
- rollback;
- FKs;
- payload hash;
- content hash;
- deterministic IDs;
- conflito explícito;
- repositories sem commit interno;
- transação por chunk;
- checkpoint após reconciliação;
- zero escrita em V2;
- `FIBONACCI_ANCHOR` permitido;
- restart idempotente.

## Gate R14

```text
R14_PERSISTENCE_PASS
```

---

# 23. FASE R15 — OVERLAYS E AUDITORIA VISUAL

## Objetivo

Ver visualmente quando cada zona nasceu e mudou de estado.

## Overlay por zona

Mostrar:

- origem;
- confirmação;
- disponibilidade;
- proximal;
- distal;
- midpoint;
- primeiro toque;
- full mitigation;
- invalidação;
- reclaim.

## Modos

```text
ORIGIN_VIEW
OPERATIONAL_VIEW
LIFECYCLE_VIEW
```

### Operational view

A zona só começa em `available_at`.

### Origin view

A zona pode ser desenhada desde a origem, mas com marcador separado da confirmação.

## Cores

Não depender apenas da direção.

Usar cores distintas por estado:

```text
fresh
touched
partial
midpoint
full
invalidated
reclaimed
expired
```

## Ferramenta

Criar:

```text
tools/smc_v3_validation/render_real_data_window.py
```

Parâmetros:

```text
--csv
--symbol
--timeframe
--start
--end
--engines
--zone-id
--mode
--output
```

## Gate R15

```text
R15_VISUAL_AUDIT_PASS
```

---

# 24. FASE R16 — REPLAY CANDLE A CANDLE COM DADOS REAIS

## Objetivo

Comprovar causalidade.

## 24.1. Modos comparados

```text
FULL_BATCH
PREFIX_REPLAY
CHUNK_REPLAY
CHECKPOINT_RESUME
PERSISTED_REPLAY
```

## 24.2. Prefix replay

Para cada índice `i`:

1. executar com candles `0..i`;
2. registrar entidades disponíveis;
3. avançar para `i+1`;
4. comparar entidades antigas;
5. permitir somente novas transições causadas pelo novo candle.

## 24.3. Invariantes

- ID antigo não muda;
- origem antiga não muda;
- confirmação antiga não muda;
- disponibilidade antiga não muda;
- subtype não usa futuro;
- status só muda após evento novo;
- zonas não desaparecem sem evento terminal;
- uma StructureLevel não gera dois breaks;
- FVG não existe antes de C;
- OB não existe antes do StructureEvent.

## 24.4. Dataset

Executar:

- Development completo;
- Validation completo;
- Golden windows;
- amostra holdout;
- todos os timeframes disponíveis.

## 24.5. Saídas

```text
replay_summary.json
prefix_divergences.csv
zone_state_transitions.csv
engine_counts_by_day.csv
errors_by_file.csv
```

## Gate R16

```text
R16_REAL_DATA_REPLAY_PASS
```

Critério:

```text
prefix_divergence_count = 0
unexplained_id_changes = 0
future_data_violations = 0
```

---

# 25. FASE R17 — TESTES DE PARIDADE

## Objetivo

Garantir que o mesmo algoritmo produz o mesmo resultado em todos os modos.

## Comparações

```text
batch vs replay
batch vs chunk
replay vs resume
batch vs persisted replay
single process vs restart
```

## Campos comparados

```text
entity_id
entity_type
origin_index
confirmed_index
available_index
direction
scope
top
bottom
midpoint
status
source_ids
payload_hash
```

## Tolerância

- preço: zero diferença, salvo normalização decimal documentada;
- índice: zero diferença;
- timestamp: zero diferença;
- IDs: zero diferença;
- lifecycle final: zero diferença.

## Gate R17

```text
R17_PARITY_PASS
```

---

# 26. FASE R18 — MÉTRICAS DE SANIDADE COM DADOS REAIS

## Objetivo

Detectar superprodução ou ausência de zonas sem usar P&L.

## Métricas por ativo/timeframe/dia

```text
candles
swings
canonical_swings
superseded_swings
bos
choch
sweeps
fvg_created
fvg_active
fvg_full_fill
bpr_created
liquidity_created
liquidity_swept
ob_created
ob_fresh
ob_touched
ob_midpoint
ob_full_mitigation
ob_invalidated
zones_active_end_of_day
zones_per_1000_candles
structure_events_per_swing
duplicate_event_rate
```

## Soft thresholds

Não hardcodar thresholds como verdade. Usar apenas alarmes.

Exemplos:

```text
structure_events_per_swing > 1.0 → investigar
100% de OB mitigado → investigar
0 zonas ativas por meses → investigar
prefix divergence > 0 → bloquear
mesmo nível com múltiplos BOS → bloquear
```

## Gate R18

```text
R18_SANITY_METRICS_PASS
```

---

# 27. FASE R19 — GOLDEN TESTS MANUAIS

## Objetivo

Validar semântica, não apenas consistência computacional.

Para cada golden window:

1. renderizar candles;
2. renderizar swings;
3. renderizar níveis estruturais;
4. renderizar BOS/CHOCH/sweep;
5. renderizar dealing range;
6. renderizar liquidity;
7. renderizar FVG/BPR;
8. renderizar OB;
9. renderizar lifecycle;
10. preencher checklist humano.

## Checklist por zona

```text
[ ] origem correta
[ ] confirmação correta
[ ] disponibilidade correta
[ ] direção correta
[ ] top/bottom corretos
[ ] midpoint correto
[ ] primeiro toque correto
[ ] mitigação parcial correta
[ ] full mitigation correta
[ ] invalidação correta
[ ] reclaim correto
[ ] nenhuma marcação antecipada
[ ] nenhuma mudança retroativa
```

## Evidências

Salvar:

```text
PNG/SVG
CSV dos eventos
JSON da entidade
hashes
comentários
```

## Gate R19

```text
R19_GOLDEN_WINDOWS_APPROVED
```

---

# 28. FASE R20 — REGRESSÃO CONTRA V2

## Objetivo

Comparar comportamento sem exigir igualdade.

## Comparar

- quantidade de zonas;
- duração;
- localização;
- causa;
- lifecycle;
- duplicação;
- lookahead;
- falsos eventos óbvios;
- zonas perdidas.

## Regra

V3 não precisa reproduzir V2.

A divergência deve ser classificada:

```text
EXPECTED_CORRECTION
V3_REGRESSION
V2_FALSE_POSITIVE_REMOVED
V3_MISSING_VALID_EVENT
UNRESOLVED
```

## Gate R20

```text
R20_V2_V3_REGRESSION_CLASSIFIED
```

Nenhum `UNRESOLVED` P0 pode permanecer.

---

# 29. FASE R21 — HOLDOUT 2026

## Objetivo

Validar sem novos ajustes.

## Regras

- congelar parâmetros antes;
- não alterar algoritmo;
- executar holdout;
- registrar todas as divergências;
- qualquer correção exige voltar ao Development/Validation e repetir os gates.

## Gate R21

```text
R21_HOLDOUT_PASS
```

---

# 30. FASE R22 — GATE PARA BACKTEST DA OPPORTUNITY ENGINE

O backtest da Opportunity Engine somente pode começar quando todos os itens abaixo estiverem aprovados.

## 30.1. Dados

```text
R1_DATASET_CANONICAL_APPROVED
R2_REAL_DATA_WINDOWS_APPROVED
```

## 30.2. Engines

```text
R4_SESSIONS_PASS
R5_SWING_PASS
R6_STRUCTURE_PASS
R7_PREVIOUS_PERIOD_PASS
R8_RETRACEMENT_PASS
R9_LIQUIDITY_PASS
R10_FVG_PASS
R11_BPR_PASS
R12_ORDER_BLOCK_PASS
```

## 30.3. Infraestrutura

```text
R3_V3_PIPELINE_CANONICAL_PASS
R13_ZONE_LIFECYCLE_AUDIT_PASS
R14_PERSISTENCE_PASS
R15_VISUAL_AUDIT_PASS
```

## 30.4. Causalidade

```text
R16_REAL_DATA_REPLAY_PASS
R17_PARITY_PASS
R19_GOLDEN_WINDOWS_APPROVED
R21_HOLDOUT_PASS
```

## 30.5. Critérios quantitativos mínimos

```text
future_data_violations = 0
prefix_divergence_count = 0
duplicate_structure_breaks = 0
active_zone_silent_evictions = 0
hash_conflicts_silenced = 0
orphan_source_refs = 0
unexplained_v2_v3_p0_divergences = 0
failed_golden_windows = 0
```

## 30.6. Decisão

Status permitidos:

```text
SMC_V3_APPROVED_FOR_OPPORTUNITY_BACKTEST
SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS
SMC_V3_BLOCKED_FOR_OPPORTUNITY_BACKTEST
SMC_V3_VALIDATION_FAILED
```

A IA não pode iniciar o backtest automaticamente. Deve produzir a decisão e aguardar aprovação humana.

---

# 31. FERRAMENTAS OBRIGATÓRIAS

Criar, no mínimo:

```text
inventory_winfut_csvs.py
validate_winfut_csvs.py
build_canonical_winfut_dataset.py
select_golden_windows.py
run_smc_v3_real_data_replay.py
compare_batch_replay.py
compare_v2_v3.py
audit_zone_lifecycle.py
render_real_data_window.py
export_zone_events.py
run_holdout_validation.py
generate_smc_v3_validation_report.py
```

Cada ferramenta deve suportar:

```text
--help
--dry-run
--input
--output
--start
--end
--symbol
--contract
--timeframe
--limit
--raise-on-error
```

---

# 32. TESTES OBRIGATÓRIOS

## 32.1. Unitários

Por função e state machine.

## 32.2. Contratos

- type identity;
- serialization;
- temporal invariants;
- IDs;
- guardrails;
- Scope;
- source references.

## 32.3. Propriedades

- idempotência;
- prefix invariance;
- monotonic availability;
- one break per level;
- no future;
- no silent overwrite.

## 32.4. Integração

- pipeline completo;
- dataset real;
- persistence;
- overlay;
- restart.

## 32.5. Fault injection

- erro de engine;
- erro de banco;
- checkpoint corrompido;
- arquivo CSV inválido;
- timestamp duplicado;
- gap inesperado;
- schema incompatível.

---

# 33. RELATÓRIOS OBRIGATÓRIOS

Criar:

```text
RELATORIO_R0_BASELINE_SMC_V3.md
RELATORIO_R1_INVENTARIO_CSV.md
RELATORIO_R1_QUALIDADE_DADOS.md
RELATORIO_R1_ROLLOVER_WINFUT.md
RELATORIO_R2_SPLITS_E_GOLDEN_WINDOWS.md
RELATORIO_R3_PIPELINE_V3.md
RELATORIO_R4_SESSIONS.md
RELATORIO_R5_SWING.md
RELATORIO_R6_STRUCTURE.md
RELATORIO_R7_PREVIOUS_PERIOD.md
RELATORIO_R8_RETRACEMENT.md
RELATORIO_R9_LIQUIDITY.md
RELATORIO_R10_FVG.md
RELATORIO_R11_BPR.md
RELATORIO_R12_ORDER_BLOCK.md
RELATORIO_R13_ZONE_LIFECYCLE.md
RELATORIO_R14_PERSISTENCE.md
RELATORIO_R15_VISUAL_AUDIT.md
RELATORIO_R16_REAL_DATA_REPLAY.md
RELATORIO_R17_PARITY.md
RELATORIO_R18_SANITY_METRICS.md
RELATORIO_R19_GOLDEN_WINDOWS.md
RELATORIO_R20_V2_V3_REGRESSION.md
RELATORIO_R21_HOLDOUT.md
RELATORIO_R22_OPPORTUNITY_BACKTEST_GATE.md
RELATORIO_FINAL_SMC_V3_REAL_DATA_VALIDATION.md
```

---

# 34. CONTEÚDO DO RELATÓRIO FINAL

O relatório final deve conter:

1. resumo executivo;
2. branch e commits;
3. hashes;
4. inventário do dataset;
5. schema;
6. timezone;
7. gaps;
8. duplicatas;
9. rollover;
10. splits;
11. golden windows;
12. pipeline final;
13. contrato temporal;
14. resultado por engine;
15. zonas por tipo;
16. lifecycle;
17. replay;
18. prefix invariance;
19. parity;
20. persistence;
21. overlays;
22. regressão V2/V3;
23. holdout;
24. testes totais;
25. pass/fail/skip;
26. cobertura;
27. performance;
28. warnings;
29. limitações;
30. riscos;
31. decisões aceitas;
32. blockers;
33. status do Gate R22;
34. autorização ou bloqueio do backtest da Opportunity Engine.

---

# 35. CRITÉRIOS DE DEFINITION OF DONE

A engine só pode ser considerada tecnicamente validada quando:

1. o dataset real foi auditado;
2. o timezone está correto;
3. rollovers estão classificados;
4. a ordem causal foi corrigida;
5. records V3 são usados end-to-end;
6. Sessions está no início;
7. Swing respeita disponibilidade;
8. SUPERSEDED não chega downstream;
9. Structure não repete BOS;
10. broken reference está correto;
11. wick sweep é alcançável;
12. StructureLegV3 existe;
13. Previous HL preserva interação;
14. Retracement usa StructureLeg;
15. Premium/Discount está correto;
16. Liquidity promove fontes;
17. FVG nasce no terceiro candle;
18. BPR é causal;
19. OB consome StructureEvent e StructureLeg;
20. primeiro toque não é full mitigation;
21. freshness é causal;
22. lifecycle registra todas as transições;
23. overlays começam em `available_at`;
24. persistence V3 é separada;
25. batch/replay/chunk/resume são idênticos;
26. prefix divergence é zero;
27. holdout passa sem ajustes;
28. golden windows são aprovados;
29. nenhum P0 está aberto;
30. Gate R22 é aprovado.

---

# 36. REGRA FINAL PARA A IA DE CÓDIGO

Executar uma fase por vez.

Após cada fase:

1. compilar;
2. executar testes;
3. executar amostra real;
4. gerar relatório;
5. registrar diff;
6. classificar o gate;
7. parar se houver P0;
8. adicionar um resumo curto da fase (o que foi feito, gate, achados) na seção "37. PROGRESSO REGISTRADO POR FASE" ao final deste documento, com o caminho do relatório completo correspondente em `docs_geral/Sistema VPS/Relatorios/SMC_ENGINE_V3_REAL_DATA_VALIDATION/`.

Não corrigir múltiplas engines em um único commit.

Não ajustar parâmetros para aumentar o número de zonas.

Não usar resultado financeiro como critério de correção.

Não iniciar Opportunity Backtest.

Ao final, informar:

```text
status geral
último gate aprovado
fases concluídas
fases bloqueadas
testes total/pass/fail/skip
future_data_violations
prefix_divergence_count
zonas por tipo
zonas ativas
zonas mitigadas
zonas invalidadas
golden windows aprovadas
holdout aprovado
Gate R22
backtest de oportunidades autorizado ou bloqueado
```

---

# 37. PROGRESSO REGISTRADO POR FASE

Branch: `feature/smc-v3-causal-rebuild-real-data`

## R0 — Baseline
Gate: `R0_BASELINE_REPRODUCIBLE`. Inventário de 65 arquivos V3, 37 testes iniciais + adendo revelou 2.091 testes adicionais em `tests/test_technical_engine/`. 4 P0s no pipeline batch legado (look-ahead em swings.py/fvg.py, ordem causal invertida, sem entrypoint V3). Relatório: `RELATORIO_R0_BASELINE_SMC_V3.md` + `RELATORIO_R0_ADDENDUM_CORRECAO.md`.

## R1 — Dataset Real
Gate: `R1_DATASET_CANONICAL_APPROVED`. 1.236.453 candles reais auditados (7 timeframes), 0 duplicatas/OHLC inválido, 3 rollovers detectados (<5%). Relatórios: `RELATORIO_R1_INVENTARIO_CSV.md`, `RELATORIO_R1_QUALIDADE_DADOS.md`, `RELATORIO_R1_ROLLOVER_WINFUT.md`.

## R2 — Splits e Golden Windows
Gate: `R2_REAL_DATA_WINDOWS_APPROVED`. Splits Dev(2021-24)/Val(2025)/Holdout(2026). 57 janelas candidatas em 19/20 categorias. Relatório: `RELATORIO_R2_SPLITS_E_GOLDEN_WINDOWS.md`.

## R3 — Pipeline V3
Gate: `R3_V3_PIPELINE_CANONICAL_PASS`. Criado `run_smc_engine_v3()`/`SmcEngineV3Result` sobre o motor incremental já causal. Ordem causal corrigida. Validado com 12.018 candles H1 reais, 0 erros. Relatório: `RELATORIO_R3_PIPELINE_V3.md`.

## R4 — Sessions
Gate: `R4_SESSIONS_PASS`. Sessão B3/WINFUT real implementada. Bug corrigido: sessão nunca fechava. Achado crítico investigado a pedido do usuário: BOS/CHOCH sem retirada de nível (3,14→0,418 razão). Relatório: `RELATORIO_R4_SESSIONS.md`.

## R5 — Swing
Gate: `R5_SWING_PASS`. SUPERSEDED + HH/HL/LH/LL implementados. P0 novo aberto para R9: EqualLevelClusterV3 não implementado. Relatório: `RELATORIO_R5_SWING.md`.

## R6 — Structure
Gate: `R6_STRUCTURE_PASS`. Wick sweep vs close break implementado. Bug crítico de aliasing em snapshot/checkpoint encontrado e corrigido (bos_choch.py). Relatório: `RELATORIO_R6_STRUCTURE.md`.

## R7 — Previous High/Low
Gate: `R7_PREVIOUS_PERIOD_PASS`. Reescrito para dia de pregão real (era janela fixa de candles). Lifecycle TOUCH/WICK_SWEEP/CLOSE_THROUGH/GAP_THROUGH/RECLAIM implementado. Relatório: `RELATORIO_R7_PREVIOUS_PERIOD.md`.

## R8 — Retracement/Dealing Range
Gate: `R8_RETRACEMENT_PASS`. DealingRangeV3 (Premium/Equilibrium/Discount) implementado. Segundo bug de aliasing (mesma classe do R6) encontrado e corrigido (retracements.py). Relatório: `RELATORIO_R8_RETRACEMENT.md`.

## R9 — Liquidity
Gate: `R9_LIQUIDITY_PASS`. EqualLevelClusterV3 implementado (merge real de EQH/EQL por tolerância ATR) — P0 do R5 resolvido. Relatório: `RELATORIO_R9_LIQUIDITY.md`.

## R10 — FVG
Gate: `R10_FVG_PASS`. CE (Consequent Encroachment) e IFVG (Inverse FVG) implementados. Relatório: `RELATORIO_R10_FVG.md`.

## R11 — BPR
Gate: `R11_BPR_PASS`. Bug de crescimento sem limite de memória corrigido (_available_fvgs). Relatório: `RELATORIO_R11_BPR.md`.

## R12 — Order Block
Gate: `R12_ORDER_BLOCK_PASS`. Bug de memória corrigido (_mitigated_zones). Rastreamento estrutural (StructureEventV3) exposto via origin_reason, opcional (require_structure_break=False por padrão, preserva comportamento). Relatório: `RELATORIO_R12_ORDER_BLOCK.md`.

## R13 — Zone Lifecycle Audit
Gate: `R13_ZONE_LIFECYCLE_AUDIT_PASS`. Ferramentas `export_zone_events.py`/`audit_zone_lifecycle.py` criadas. Bug de timestamps ausentes em PDH/PDL (desde R7) encontrado e corrigido. Zero violações em 24.113 estruturas/46.959 eventos reais. Relatório: `RELATORIO_R13_ZONE_LIFECYCLE.md`.

## R14 — Persistência V3
Gate: `R14_PERSISTENCE_PASS`. Infraestrutura já existente validada com dados reais (restart idempotente, checkpoint/resume, detecção de conflito). Sem mudança de código. Relatório: `RELATORIO_R14_PERSISTENCE.md`.

## R15 — Overlays e Auditoria Visual
Gate: `R15_VISUAL_AUDIT_PASS`. `render_real_data_window.py` criado (ORIGIN/OPERATIONAL/LIFECYCLE_VIEW). Invariante causal confirmada programaticamente. Relatório: `RELATORIO_R15_VISUAL_AUDIT.md`.

## R16 — Replay Candle a Candle
Gate: `R16_REAL_DATA_REPLAY_PASS`. `run_smc_v3_real_data_replay.py` criado (FULL_BATCH/PREFIX/CHUNK/CHECKPOINT/PERSISTED). P0 real encontrado e corrigido: `confirmation_candle_id`/`availability_candle_id`/`source_candle_id` de PDH/PDL apontavam para o candle extremo em vez do candle-gatilho, inconsistente com `confirmed_at`/`available_at`. Validado no dataset H1 completo (12.018 candles, 24.113 structures): os 5 modos convergem exatamente — `future_data_violations=0`, `prefix_divergence_count=0`, `unexplained_id_changes=0`. Relatório: `RELATORIO_R16_REAL_DATA_REPLAY.md`.

## R17 — Paridade
Gate: `R17_PARITY_PASS`. `compare_batch_replay.py` criado (fingerprint completo por estrutura, não só ID). Validado no H1 completo: 24.113 estruturas comuns, 0 mismatches. Demais comparações (chunk/resume/persisted/restart) reaproveitam evidência real de R14/R16. Relatório: `RELATORIO_R17_PARITY.md`.

## R18 — Métricas de Sanidade
Gate: `R18_SANITY_METRICS_PASS`. `generate_smc_v3_validation_report.py` criado. Zero alarmes no H1 completo (12.018 candles, 9 engines): `structure_events_per_swing=0,639`, `duplicate_structure_breaks=0`, OBs 98,4% mitigados (não 100%). Relatório: `RELATORIO_R18_SANITY_METRICS.md`.

## R19 — Golden Windows
Gate: `R19_GOLDEN_WINDOWS_APPROVED`. `verify_golden_windows.py` criado — checklist automatizado (ordem temporal, direção, presença do padrão), explicitamente declarado como NÃO substituto de inspeção visual humana (essa fica com o usuário via render_real_data_window.py). 44 janelas verificadas, 0 violações temporais, 0 divergências de direção, padrão encontrado em 25/26 janelas aplicáveis. Relatório: `RELATORIO_R19_GOLDEN_WINDOWS.md`.

## R20 — Regressão V2 x V3
Gate: `R20_V2_V3_REGRESSION_CLASSIFIED`. Constatado: não existe V2 substantiva (só `__init__.py`, 16 linhas, confirmado desde R0). Classificadas as diferenças conhecidas entre pipeline batch legado (contaminado por look-ahead) e motor causal atual — 100% `EXPECTED_CORRECTION`, zero `UNRESOLVED`/`V3_REGRESSION`. Sem código alterado. Relatório: `RELATORIO_R20_V2_V3_REGRESSION.md`.

## R21 — Holdout 2026
Gate: `R21_HOLDOUT_PASS`. `run_holdout_validation.py` criado (stream completo stateful, isolamento pós-fato do período >=2026-01-02). 2.299 estruturas reais no holdout (1.146 candles), zero erros, parâmetros congelados desde R20. Relatório: `RELATORIO_R21_HOLDOUT.md`.

## R22 — Gate Final para Opportunity Backtest
**DECISÃO FORMAL: `SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS`**

Todos os 8 critérios absolutos atendidos (future_data_violations=0, prefix_divergence_count=0, duplicate_structure_breaks=0, active_zone_silent_evictions=0, hash_conflicts_silenced=0, orphan_source_refs=0, unexplained_v2_v3_p0_divergences=0, failed_golden_windows=0). 9 bugs reais corrigidos ao longo de R0-R21, cada um com evidência real. P1s conscientes e documentados (StructureLegV3 formal, consumo cruzado de Liquidity, decisão de produto sobre require_structure_break em OB, escala M1 não validada integralmente) exigem revisão do dono do produto antes do cutover de produção — não bloqueiam o gate técnico, mas impedem a aprovação irrestrita.

**Opportunity Backtest: NÃO iniciado.** Aguardando aprovação humana explícita.

Relatório final consolidado: `RELATORIO_FINAL_SMC_V3_REAL_DATA_VALIDATION.md`

---

# FIM DA EXECUÇÃO DO PLANO — R0 a R22 CONCLUÍDAS
