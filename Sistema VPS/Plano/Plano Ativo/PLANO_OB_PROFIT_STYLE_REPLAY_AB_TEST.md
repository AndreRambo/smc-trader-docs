# PLANO EXECUTIVO — OB PROFIT-STYLE EM PARALELO + REPLAY A/B

**Projeto:** SMC Trader System 7.0  
**Escopo:** manter a engine atual de Order Blocks, criar um modo paralelo `PROFIT_STYLE`, replicar os cálculos do indicador Profit enviado, rodar replay comparativo e só promover o modo vencedor.  
**Data:** 2026-07-02  
**Status:** Plano técnico completo para implementação, validação e cutover controlado.  
**Regra principal:** nada substitui a engine atual até o replay provar que o novo modo é melhor.

---

## 1. Objetivo

Criar dentro do `smc_engine` um modo adicional de detecção/classificação/mitigação de Order Blocks inspirado no indicador Profit:

```text
OBDetectionProfile.SYSTEM_DEFAULT
OBDetectionProfile.PROFIT_STYLE
```

A engine atual continua existindo e funcionando como baseline. O novo modo roda em paralelo, grava resultados separados e permite comparar:

```text
Engine atual do sistema
vs
Engine OB Profit-style
```

O objetivo não é “marcar igual visualmente” apenas. O objetivo é descobrir qual modo gera melhores **oportunidades operacionais DayTrade**, com métricas de replay, risco e resultado.

---

## 2. Decisão Arquitetural

### 2.1 Não trocar a engine atual diretamente

Não alterar o comportamento padrão atual:

```text
order_block_profile = "SYSTEM_DEFAULT"
```

Criar um modo novo:

```text
order_block_profile = "PROFIT_STYLE"
```

O modo novo deve ser executado somente quando ativado por:

```text
config
CLI
replay
feature flag
ambiente shadow
```

### 2.2 Modo novo deve ser shadow-only

Nenhuma oportunidade real deve ser promovida automaticamente por esse modo durante a fase de teste.

Campos obrigatórios:

```text
shadow_only = true
can_promote_trade = false
ob_detection_profile = "PROFIT_STYLE"
```

### 2.3 Comparação justa

O replay A/B deve mudar apenas a lógica de OB.

Devem ser mantidos iguais entre os dois modos:

```text
ativo
timeframe
candles
spread/slippage
risk management
scanner
janela de horário
entrada
stop
take profit
dedup
regras de avaliação
```

---

## 3. Hipótese de Trabalho

A hipótese a validar é:

> O modelo OB Profit-style deve gerar menos zonas fracas, marcar zonas mais operacionais, melhorar a qualidade dos alertas e reduzir ruído no DayTrade.

Mas isso não deve ser assumido como verdade antes do teste.

Possíveis resultados:

| Resultado | Interpretação |
|---|---|
| Profit-style melhora expectancy e reduz ruído | Promover para padrão operacional |
| Profit-style reduz zonas, mas perde oportunidades boas | Manter como filtro opcional |
| Profit-style piora resultado | Manter engine atual |
| Profit-style melhora só em WIN 5m | Ativar por perfil de ativo/timeframe |
| Profit-style melhora visual, mas não estatística | Usar só no dashboard, não no scanner |

---

## 4. Arquitetura Recomendada

### 4.1 Estrutura de arquivos

```text
technical_engine/
└── smc_engine/
    ├── incremental/
    │   └── components/
    │       ├── ob.py                         # engine atual
    │       ├── ob_profit_style.py            # novo modo paralelo
    │       └── ob_common.py                  # contratos compartilhados
    │
    ├── batch/
    │   ├── order_blocks.py                   # engine batch atual
    │   └── order_blocks_profit_style.py      # detector Profit-style batch/replay
    │
    ├── config/
    │   └── ob_profiles.py                    # parâmetros por ativo/timeframe
    │
    └── replay/
        └── compare_ob_profiles.py            # replay A/B
```

### 4.2 Não duplicar tudo sem necessidade

Criar componentes compartilhados:

```text
OBZone
OBCandidate
OBProfileConfig
OBLifecycleEvent
OBReplayStats
```

O modo Profit-style deve emitir o mesmo contrato final da engine atual, para que Study Gateway, Scanner e Dashboard não precisem ser reescritos.

---

## 5. Modelo de Dados Recomendado

### 5.1 Campos mínimos da zona OB

```text
structure_id
run_id
asset_id
symbol
timeframe
ob_detection_profile
direction
zone_type
ob_subtype
status
origin_index
origin_at
confirmation_index
confirmed_at
available_at
top
bottom
midpoint
size
mitigated_at
invalidated_at
display_from
display_to
source_rule
raw_json
payload_hash
shadow_only
can_promote_trade
```

### 5.2 Valores para `ob_detection_profile`

```text
SYSTEM_DEFAULT
PROFIT_STYLE
PROFIT_STYLE_STRICT
PROFIT_STYLE_NO_FALLBACK
```

### 5.3 Valores para `ob_subtype`

O indicador usa tipos numéricos. No sistema, gravar texto legível:

| Tipo no indicador | Texto curto | Texto completo | Valor no sistema |
|---:|---|---|---|
| 1 | OB | Order Block | `NORMAL` |
| 2 | OB2 | Mitigation OB | `MITIGATION_OB` |
| 3 | RB | Rejection Block | `REJECTION` |
| 4 | EX | Extreme OB | `EXTREME` |
| 5 | DC | Decisional OB | `DECISIONAL` |
| 6 | BB | Breaker Block | `BREAKER` |

### 5.4 Estados de ciclo de vida

```text
AVAILABLE
TOUCHED
MITIGATED
INVALIDATED
EXPIRED
```

O indicador trabalha visualmente com `ativo`, `mitigado`, `encerrado` e `expirado`. O sistema deve formalizar isso.

---

## 6. Parâmetros do Indicador a Replicar

### 6.1 Inputs principais

```text
Exigir_Imbalance_OB = true
Exigir_OB_Extremo_Local = true
Marcar_OB_2_0 = true
Mostrar_Texto_Mitigado = true
Perido = 5
ImbalanceVelas = 10
DiasRetroativos = 10
Mitigacao = 1
EqLookback = 20
EqToleranceTicks = 2
MinTouchesFlip = 2
```

### 6.2 Parâmetros de mitigação

```text
Mitigar_OB_Normal_Centro = true
Mitigar_OB2_AoTocar = false
Mitigar_Rejection_AoTocar = false
Evitar_Mitigar_No_Candle_Criacao = true
Manter_OB_Apos_Mitigacao = false
```

### 6.3 Parâmetros de Rejection Block

```text
PavioMinRejectionPerc = 0.50
CorpoMaxRejectionPerc = 0.45
```

### 6.4 Perfil automático por ativo/timeframe

O indicador aplica perfis por ativo e timeframe. O sistema deve replicar isso como `OBProfileConfig`.

Ativos:

```text
WIN / WINFUT
WDO / WDOFUT
Ações
Forex majors
XAUUSD / GOLD
XAGUSD / SILVER
USTEC / NAS100 / US100
US500 / SPX500
Outro
NOVO1 / NOVO2 / NOVO3
```

Timeframes:

```text
TF_2M_CODE       = 2 minutos ou menor
TF_5M_CODE       = até 5 minutos
TF_15M_CODE      = até 15 minutos
TF_MAIOR_15_CODE = acima de 15 minutos
```

### 6.5 Perfil WIN recomendado inicialmente

Como o foco atual é DayTrade em WIN, iniciar com o perfil WIN:

```text
WIN_USAR_V5_FILTRO = true
WIN_FALLBACK_OB = true
WIN_USAR_PD = false
WIN_USAR_HTF = false
WIN_USAR_INDUCEMENT = false
WIN_CLASS_EX_DC = false
WIN_CLASS_BREAKER = true
WIN_PAVIO_RB = 0.50
WIN_CORPO_RB = 0.45
WIN_MITIGAR_OB_CENTRO = true
WIN_MITIGAR_OB2_TOQUE = false
WIN_MITIGAR_RB_TOQUE = false
WIN_MANTER_OB_MITIGADO = false
WIN_TEXTO_MITIGADO = true

WIN_2M_EXIGIR_OB2 = true
WIN_5M_EXIGIR_OB2 = true
WIN_15M_EXIGIR_OB2 = false
WIN_HTF_EXIGIR_OB2 = false
```

---

## 7. Cálculo Profit-style — Visão Geral

O indicador faz a detecção em camadas:

```text
1. Detecta níveis base de suporte/resistência por fractal/período.
2. Detecta rompimento de estrutura: BreakUp / BreakDown.
3. Define Trend.
4. Detecta imbalance: FVG ou displacement.
5. Quando há virada de Trend com rompimento e imbalance, procura o candle do OB.
6. Calcula a zona do OB como corpo total da vela candidata: high/low.
7. Classifica em OB, OB2, RB, EX, DC ou BB.
8. Salva a zona.
9. Gerencia mitigação, invalidação, expiração e texto.
```

No sistema, isso deve ser implementado de forma causal:

```text
o candle atual só pode usar dados até ele mesmo
nenhuma zona pode aparecer antes do candle de confirmação
available_at deve ser igual ao candle de confirmação
```

---

## 8. Cálculo dos Níveis Base

### 8.1 Parâmetros

```text
Perido = 5
p = floor(Perido / 2)
```

### 8.2 Cálculo

Equivalência do indicador:

```text
dh = Summation(Sign(High - High[1]), p)
dl = Summation(Sign(Low - Low[1]), p)

vLow = Lowest(Low, Perido)
vHigh = Highest(High, Perido)

cHigh = vHigh <= vHigh[1]
cLow = vLow >= vLow[1]

if cHigh and not cHigh[1]:
    vFractals = 1
elif cLow and not cLow[1]:
    vFractals = -1
else:
    vFractals = 0
```

### 8.3 Interpretação

```text
vFractals = 1  → novo nível de resistência/base supply
vFractals = -1 → novo nível de suporte/base demand
```

### 8.4 Cálculo da zona bruta a partir do fractal

Para suporte/demand:

```text
j = LowestBar(Low, Perido)
newOBSup = Low[j] + abs(High[j] - Low[j]) / 2
newOBSupSize = abs(High[j] - Low[j]) / 2
```

Para resistência/supply:

```text
j = HighestBar(High, Perido)
newOBRes = Low[j] + abs(High[j] - Low[j]) / 2
newOBResSize = abs(High[j] - Low[j]) / 2
```

No sistema, gravar:

```text
midpoint = newOB*
size = newOB*Size
top = midpoint + size
bottom = midpoint - size
```

---

## 9. Cálculo de BreakUp, BreakDown e Trend

### 9.1 Regras

```text
BreakUp = Close[1] <= last_resistance and Close >= last_resistance
BreakDown = Close[1] >= last_support and Close <= last_support
```

### 9.2 Trend

```text
if BreakUp:
    Trend = 1

if BreakDown:
    Trend = -1

if Trend == 0:
    Trend = previous Trend
```

### 9.3 Interpretação SMC

```text
Trend -1 → 1 + BreakUp     = rompimento para cima / BoS alta
Trend 1 → -1 + BreakDown   = rompimento para baixo / BoS baixa
```

### 9.4 Condição principal para criar OB bearish

```text
previous Trend = 1
current Trend = -1
BreakDown = true
Exigir_Imbalance_OB = false ou hasBearImbalance = true
```

### 9.5 Condição principal para criar OB bullish

```text
previous Trend = -1
current Trend = 1
BreakUp = true
Exigir_Imbalance_OB = false ou hasBullImbalance = true
```

---

## 10. Cálculo de Imbalance

O indicador considera imbalance de duas formas:

```text
FVG clássico de 3 candles
Displacement por candle de corpo forte
```

### 10.1 FVG clássico atual

Bullish:

```text
Low > High[2]
```

Bearish:

```text
High < Low[2]
```

Quando detecta FVG clássico no candle atual:

```text
hasBullImbalance = true
idxBuscaBull = 2

hasBearImbalance = true
idxBuscaBear = 2
```

### 10.2 Displacement atual

```text
range = High - Low
corpo = abs(Close - Open)
```

Bullish displacement:

```text
Close > Open
corpo >= range * 0.55
Close > High[1]
```

Bearish displacement:

```text
Close < Open
corpo >= range * 0.55
Close < Low[1]
```

Quando detecta displacement atual:

```text
idxBuscaBull = 1
idxBuscaBear = 1
```

### 10.3 Imbalance em candles recentes

O indicador procura até `ImbalanceVelas = 10`.

Regra geral para offset `k`:

Bullish FVG recente:

```text
Low[k] > High[k+2]
```

Bearish FVG recente:

```text
High[k] < Low[k+2]
```

Bullish displacement recente:

```text
Close[k] > Open[k]
abs(Close[k] - Open[k]) >= (High[k] - Low[k]) * 0.55
Close[k] > High[k+1]
```

Bearish displacement recente:

```text
Close[k] < Open[k]
abs(Close[k] - Open[k]) >= (High[k] - Low[k]) * 0.55
Close[k] < Low[k+1]
```

### 10.4 Uso do `idxBuscaBull` e `idxBuscaBear`

O índice indica a partir de qual candle buscar o OB.

Objetivo operacional:

```text
evitar pegar o candle oposto mais perto do BoS/pullback
e preferir o candle realmente anterior ao deslocamento/FVG
```

No sistema, gravar no `raw_json`:

```json
{
  "imbalance_type": "FVG" ou "DISPLACEMENT",
  "imbalance_offset": 2,
  "idx_busca": 2
}
```

---

## 11. Busca do Candle do OB Bearish

### 11.1 Regra SMC operacional

OB bearish é:

```text
último candle de alta antes da perna forte de baixa
```

### 11.2 Condição geral

Para cada offset `k` de 1 até `ImbalanceVelas`:

```text
Close[k] > Open[k]                    # candle de alta
Close[k-1] < Open[k-1]                # candle seguinte já é bearish
Close[k-1] < Low[k]                   # candle seguinte rompe a mínima do candle OB
k >= idxBuscaBear                     # respeita origem do imbalance
```

No indicador, a comparação aparece como:

```text
1 >= idxBuscaBear
2 >= idxBuscaBear
3 >= idxBuscaBear
...
```

No sistema, normalizar como:

```python
if offset >= idx_busca_bear:
    avaliar candidato
```

### 11.3 Filtro de extremo local

Quando `Exigir_OB_Extremo_Local = true`, o candle bearish deve estar em extremo superior local:

Para offset `k`, exigir:

```text
High[k] >= High[k-1]
High[k] >= High[k+1]
High[k] >= High[k+2]
```

A forma exata varia nos offsets por causa do indexamento NTSL, mas a intenção é:

```text
o candle candidato precisa estar no topo local
não pode ser candle de pullback no meio da consolidação
```

### 11.4 Cálculo da zona bearish

```text
midpoint = Low[k] + abs(High[k] - Low[k]) / 2
size = abs(High[k] - Low[k]) / 2
top = High[k]
bottom = Low[k]
```

Se `size < tick`:

```text
size = tick
top = midpoint + tick
bottom = midpoint - tick
```

### 11.5 Fallback fractal

Se nenhum candle SMC exato for encontrado:

```text
if Permitir_Fallback_Fractal_OB and newOBRes > 0 and newOBResSize > 0:
    foundBearOB = true
```

Esse fallback é importante para replicar o indicador. Porém, no sistema ele deve ser marcado:

```text
source_rule = "FALLBACK_FRACTAL"
operational_quality_penalty = true
```

Para o scanner, esse tipo pode ser usado com menor score.

---

## 12. Busca do Candle do OB Bullish

### 12.1 Regra SMC operacional

OB bullish é:

```text
último candle de baixa antes da perna forte de alta
```

### 12.2 Condição geral

Para cada offset `k` de 1 até `ImbalanceVelas`:

```text
Close[k] < Open[k]                    # candle de baixa
Close[k-1] > Open[k-1]                # candle seguinte já é bullish
Close[k-1] > High[k]                  # candle seguinte rompe a máxima do candle OB
k >= idxBuscaBull                     # respeita origem do imbalance
```

### 12.3 Filtro de extremo local

Quando `Exigir_OB_Extremo_Local = true`, o candle bullish deve estar em extremo inferior local:

```text
Low[k] <= Low[k-1]
Low[k] <= Low[k+1]
Low[k] <= Low[k+2]
```

Intenção:

```text
o candle candidato precisa estar no fundo local
não pode ser candle perdido no meio da consolidação
```

### 12.4 Cálculo da zona bullish

```text
midpoint = Low[k] + abs(High[k] - Low[k]) / 2
size = abs(High[k] - Low[k]) / 2
top = High[k]
bottom = Low[k]
```

Se `size < tick`:

```text
size = tick
top = midpoint + tick
bottom = midpoint - tick
```

### 12.5 Fallback fractal

```text
if not foundBullOB and Permitir_Fallback_Fractal_OB and newOBSup > 0 and newOBSupSize > 0:
    foundBullOB = true
```

Também deve ser marcado como:

```text
source_rule = "FALLBACK_FRACTAL"
```

---

## 13. Filtros Avançados V5

O indicador possui filtros avançados, mas normalmente eles só bloqueiam se:

```text
Usar_V5_Como_Filtro = true
```

### 13.1 Premium / Discount

```text
pdHigh = Highest(High, PDLookback)
pdLow = Lowest(Low, PDLookback)
pdMid = (pdHigh + pdLow) / 2
```

Bearish OB válido preferencialmente acima do `pdMid`:

```text
if cfgUsarPremiumDiscount and newOBRes < pdMid:
    bloquear
```

Bullish OB válido preferencialmente abaixo do `pdMid`:

```text
if cfgUsarPremiumDiscount and newOBSup > pdMid:
    bloquear
```

### 13.2 HTF bias aproximado

```text
htfHigh = Highest(High, HTFLookback)
htfLow = Lowest(Low, HTFLookback)
htfMid = (htfHigh + htfLow) / 2
```

```text
htfBullOK = Close >= htfMid
htfBearOK = Close <= htfMid
```

### 13.3 Inducement

Bullish inducement:

```text
Low[1] < Low[2] and Close[1] > Low[2]
ou
Low[2] < Low[3] and Close[2] > Low[3]
ou
Low[3] < Low[4] and Close[3] > Low[4]
```

Bearish inducement:

```text
High[1] > High[2] and Close[1] < High[2]
ou
High[2] > High[3] and Close[2] < High[3]
ou
High[3] > High[4] and Close[3] < High[4]
```

### 13.4 Implementação no sistema

No modo `PROFIT_STYLE`, os filtros devem existir, mas inicialmente respeitar os defaults do indicador:

```text
Usar_Premium_Discount = false
Usar_HTF_Bias = false
Usar_Inducement = false
```

No replay, testar também variações:

```text
PROFIT_STYLE_DEFAULT
PROFIT_STYLE_PD_ON
PROFIT_STYLE_HTF_ON
PROFIT_STYLE_INDUCEMENT_ON
PROFIT_STYLE_STRICT_ALL
```

---

## 14. Equal High, Equal Low e Flip

O indicador calcula contexto por liquidez e flip, mas tem fallback para continuar desenhando o OB se o contexto não for encontrado.

### 14.1 Bearish: Equal High acima do OB

```text
for k1 in 1..EqLookback:
    for k2 in k1+1..EqLookback:
        if abs(High[k1] - High[k2]) <= EqToleranceTicks * tick:
            eqHigh = média dos dois highs
```

Contexto válido:

```text
eqHigh > 0 and eqHigh > nivelOB
```

### 14.2 Bullish: Equal Low abaixo do OB

```text
for k1 in 1..EqLookback:
    for k2 in k1+1..EqLookback:
        if abs(Low[k1] - Low[k2]) <= EqToleranceTicks * tick:
            eqLow = média dos dois lows
```

Contexto válido:

```text
eqLow > 0 and eqLow < nivelOB
```

### 14.3 Flip / linha batida

```text
touches = candles cujo High/Low atravessam nivelOB ± EqToleranceTicks*tick
above = existe Close acima de nivelOB
below = existe Close abaixo de nivelOB
```

Contexto válido:

```text
touches >= MinTouchesFlip
ou
touches >= MinTouchesFlip and above and below
```

### 14.4 Diferença entre desenho e operação

No indicador:

```text
se contexto não for válido, ainda desenha o OB por fallback
```

No sistema, separar:

```text
draw_valid = true
operational_context_valid = contextoValido
```

Recomendação:

```text
OB pode aparecer no gráfico
mas só vira OB_OPERACIONAL se tiver contexto ou score suficiente
```

---

## 15. Classificação OB / OB2 / RB / EX / DC / BB

### 15.1 Rejection Block bearish

Condição:

```text
corpo = abs(Close[1] - Open[1])
range = High[1] - Low[1]
precoCorpoMax = max(Open[1], Close[1])
precoCorpoMin = min(Open[1], Close[1])
pavioSup = High[1] - precoCorpoMax
pavioInf = precoCorpoMin - Low[1]
```

Bearish RB:

```text
corpo <= range * cfgCorpoMaxRejectionPerc
pavioSup >= range * cfgPavioMinRejectionPerc
pavioSup > pavioInf
```

### 15.2 Rejection Block bullish

Bullish RB:

```text
corpo <= range * cfgCorpoMaxRejectionPerc
pavioInf >= range * cfgPavioMinRejectionPerc
pavioInf > pavioSup
```

### 15.3 Observação importante

O indicador usa `Close[1]`, `Open[1]`, `High[1]`, `Low[1]` para classificar RB.

Para replicação exata:

```text
PROFIT_STYLE_PARITY → usar [1] igual ao indicador
```

Para evolução futura:

```text
PROFIT_STYLE_ORIGIN_RB → classificar RB no candle de origem do OB
```

No primeiro momento, implementar `PARITY` para bater com o Profit.

### 15.4 OB2 / Mitigation OB

OB2 é detectado quando o novo OB está próximo de um OB anterior da mesma direção já encerrado/mitigado.

Bearish:

```text
ob_anterior = OBRes anterior com TimeEnd > 0

abs(ob_anterior.midpoint - novo_ob.midpoint)
<= ob_anterior.size + novo_ob.size + tick * 2
```

Se `cfgExigirReacaoOB2 = false`, proximidade basta.

Se `cfgExigirReacaoOB2 = true`, exigir reação:

```text
High >= fundoOB
Low <= topoOB
Close < ob_anterior.midpoint
```

Bullish:

```text
ob_anterior = OBSup anterior com TimeEnd > 0

abs(ob_anterior.midpoint - novo_ob.midpoint)
<= ob_anterior.size + novo_ob.size + tick * 2
```

Se exigir reação:

```text
High >= fundoOB
Low <= topoOB
Close > ob_anterior.midpoint
```

### 15.5 Breaker Block

Bearish breaker:

```text
nova supply próxima de demand anterior encerrada/mitigada
```

Condição:

```text
abs(previous_demand.midpoint - new_supply.midpoint)
<= previous_demand.size + new_supply.size + tick * 2
```

Bullish breaker:

```text
nova demand próxima de supply anterior encerrada/mitigada
```

Condição:

```text
abs(previous_supply.midpoint - new_demand.midpoint)
<= previous_supply.size + new_demand.size + tick * 2
```

### 15.6 Extreme / Decisional

Só aplicar quando:

```text
cfgClassificarExtremeDecisional = true
```

Bearish:

```text
if isRejectionBear and newOBRes >= pdMid:
    EXTREME
else:
    DECISIONAL
```

Bullish:

```text
if isRejectionBull and newOBSup <= pdMid:
    EXTREME
else:
    DECISIONAL
```

### 15.7 Prioridade operacional de classificação

A prioridade do indicador deve ser replicada:

```text
1. RB
2. OB2
3. BB
4. EX
5. DC
6. OB normal
```

Pseudo-código:

```python
if is_rejection:
    subtype = "REJECTION"
elif is_ob2:
    subtype = "MITIGATION_OB"
elif is_breaker:
    subtype = "BREAKER"
elif is_extreme:
    subtype = "EXTREME"
elif is_decisional:
    subtype = "DECISIONAL"
else:
    subtype = "NORMAL"
```

---

## 16. Mitigação e Invalidação

### 16.1 Conceitos

No indicador:

```text
Mitigou = marca OB-M / OB2-M / RB-M
Invalidou = encerra a zona no extremo oposto
Por padrão, zona mitigada para de projetar para frente
```

### 16.2 Demand / bullish OB

```text
topoOB = midpoint + size
fundoOB = midpoint - size
```

Mitigação padrão `Mitigacao = 1`:

| Tipo | Condição padrão |
|---|---|
| OB normal | `Low < midpoint`, se `cfgMitigarOBNormalCentro=true`; senão `Low < topoOB` |
| OB2 | `Low < topoOB` se `cfgMitigarOB2AoTocar=true`; senão `Low < midpoint` |
| RB | `Low < topoOB` se `cfgMitigarRejectionAoTocar=true`; senão `Low < midpoint` |
| Outros | `Low < midpoint` |

Invalidação com `Mitigacao = 1`:

```text
Close < fundoOB
```

Mitigação/Invalidação com `Mitigacao = 2`:

```text
mitigação igual acima
invalidação = Low < fundoOB
```

### 16.3 Supply / bearish OB

```text
topoOB = midpoint + size
fundoOB = midpoint - size
```

Mitigação padrão `Mitigacao = 1`:

| Tipo | Condição padrão |
|---|---|
| OB normal | `High > midpoint`, se `cfgMitigarOBNormalCentro=true`; senão `High > fundoOB` |
| OB2 | `High > fundoOB` se `cfgMitigarOB2AoTocar=true`; senão `High > midpoint` |
| RB | `High > fundoOB` se `cfgMitigarRejectionAoTocar=true`; senão `High > midpoint` |
| Outros | `High > midpoint` |

Invalidação com `Mitigacao = 1`:

```text
Close > topoOB
```

Mitigação/Invalidação com `Mitigacao = 2`:

```text
mitigação igual acima
invalidação = High > topoOB
```

### 16.4 Evitar mitigação no candle de criação

O indicador evita que a zona nasça e seja mitigada no mesmo candle:

```text
Evitar_Mitigar_No_Candle_Criacao = true
```

No sistema:

```python
if event_index == origin_creation_index and avoid_same_candle_mitigation:
    ignore mitigation/invalidation for this candle
```

### 16.5 Texto e histórico

Quando mitigado:

```text
OB  → OB-M
OB2 → OB2-M
RB  → RB-M
BB  → BB-M
EX  → EX-M
DC  → DC-M
```

Se `Manter_OB_Apos_Mitigacao = false`:

```text
display_to = candle de mitigação
```

Se `true`:

```text
zona continua ativa até invalidar
```

---

## 17. Separação entre OB Detectado, OB Visual e OB Operacional

Criar três níveis:

```text
OB_DETECTED
OB_VISUAL
OB_OPERATIONAL
```

### 17.1 OB_DETECTED

Qualquer OB que passou pela lógica básica.

### 17.2 OB_VISUAL

OB que deve aparecer no gráfico:

```text
passou contexto ou fallback visual
não expirou por DiasRetroativos
tem preço válido
```

### 17.3 OB_OPERATIONAL

OB que pode alimentar scanner:

Regras mínimas recomendadas:

```text
1. source_rule != FALLBACK_FRACTAL ou score compensatório alto
2. has_imbalance = true
3. BreakUp/BreakDown verdadeiro
4. origem em extremo local
5. não mitigado
6. não invalidado
7. não expirado
8. R:R potencial mínimo
9. distância até preço operacional
10. contexto MTF neutro ou favorável
```

O indicador pode desenhar OB mesmo sem EqH/EqL/flip. O scanner não deve necessariamente operar todos eles.

---

## 18. Fase 0 — Inventário e Baseline

### 18.1 Objetivo

Registrar como a engine atual se comporta antes de implementar o modo Profit-style.

### 18.2 Ações

- [ ] Rodar replay com engine atual.
- [ ] Salvar resultados em banco/tabela separada.
- [ ] Exportar zonas atuais para CSV/JSON.
- [ ] Gerar relatório de quantidade de OBs.
- [ ] Gerar relatório de oportunidades derivadas de OB.
- [ ] Salvar screenshots de comparação.

### 18.3 Métricas baseline

```text
total_ob_detected
total_ob_visual
total_ob_operational
total_ob_touched
total_ob_mitigated
total_ob_invalidated
total_alerts_from_ob
tp1_hits
tp2_hits
tp3_hits
stop_hits
expectancy_R
profit_factor
max_drawdown_R
alerts_per_day
false_alert_rate
avg_time_to_touch
avg_time_to_mitigation
```

### 18.4 Entregável

```text
Sistema VPS/Relatorios/OB_BASELINE_SYSTEM_DEFAULT_WINFUT_M5.md
```

---

## 19. Fase 1 — Contratos e Configuração

### 19.1 Criar enum

```python
class OBDetectionProfile(str, Enum):
    SYSTEM_DEFAULT = "SYSTEM_DEFAULT"
    PROFIT_STYLE = "PROFIT_STYLE"
    PROFIT_STYLE_STRICT = "PROFIT_STYLE_STRICT"
    PROFIT_STYLE_NO_FALLBACK = "PROFIT_STYLE_NO_FALLBACK"
```

### 19.2 Criar config

```python
@dataclass
class OBProfitStyleConfig:
    periodo: int = 5
    imbalance_velas: int = 10
    dias_retroativos: int = 10
    exigir_imbalance_ob: bool = True
    exigir_ob_extremo_local: bool = True
    marcar_ob_2_0: bool = True
    mitigacao: int = 1
    eq_lookback: int = 20
    eq_tolerance_ticks: int = 2
    min_touches_flip: int = 2
    mitigar_ob_normal_centro: bool = True
    mitigar_ob2_ao_tocar: bool = False
    mitigar_rejection_ao_tocar: bool = False
    evitar_mitigar_no_candle_criacao: bool = True
    manter_ob_apos_mitigacao: bool = False
    pavio_min_rejection_perc: float = 0.50
    corpo_max_rejection_perc: float = 0.45
    permitir_fallback_fractal_ob: bool = True
```

### 19.3 Criar registry por ativo/timeframe

```text
technical_engine/smc_engine/config/ob_profiles.py
```

---

## 20. Fase 2 — Implementar Detector Profit-style

### 20.1 Arquivo novo

```text
technical_engine/smc_engine/incremental/components/ob_profit_style.py
```

### 20.2 Responsabilidades

- [ ] Manter buffers de suporte/resistência.
- [ ] Detectar BreakUp/BreakDown.
- [ ] Detectar imbalance.
- [ ] Buscar candle OB bearish.
- [ ] Buscar candle OB bullish.
- [ ] Aplicar fallback fractal.
- [ ] Classificar RB/OB2/BB/EX/DC/OB.
- [ ] Atualizar mitigação e invalidação.
- [ ] Emitir eventos lifecycle.
- [ ] Emitir zonas compatíveis com contrato atual.

### 20.3 Eventos gerados

```text
OB_AVAILABLE
OB_TOUCHED
OB_MITIGATED
OB_INVALIDATED
OB_EXPIRED
```

### 20.4 Campos no payload

```json
{
  "ob_detection_profile": "PROFIT_STYLE",
  "profit_style": {
    "periodo": 5,
    "imbalance_velas": 10,
    "exigir_imbalance_ob": true,
    "exigir_ob_extremo_local": true,
    "source_rule": "SMC_CANDLE" ,
    "fallback_used": false,
    "idx_busca": 2,
    "imbalance_type": "FVG",
    "trend_before": 1,
    "trend_after": -1,
    "break_type": "BREAKDOWN",
    "eq_context_valid": true,
    "is_ob2": false,
    "is_rejection": false,
    "is_breaker": true
  }
}
```

---

## 21. Fase 3 — Modo Dual no Pipeline

### 21.1 Configuração

Adicionar:

```text
SMC_OB_PROFILE=SYSTEM_DEFAULT
SMC_OB_PROFILE=PROFIT_STYLE
SMC_OB_PROFILE=DUAL_COMPARE
```

### 21.2 DUAL_COMPARE

Quando `DUAL_COMPARE` estiver ativo:

```text
processar OB atual
processar OB Profit-style
persistir ambos
não misturar estruturas
não deixar scanner usar Profit-style em produção
```

### 21.3 Chave de separação

Toda zona precisa carregar:

```text
ob_detection_profile
```

Exemplo:

```text
WINFUT|M5|2026-06-17T17:10|OB|SYSTEM_DEFAULT
WINFUT|M5|2026-06-17T17:10|OB|PROFIT_STYLE
```

---

## 22. Fase 4 — Persistência

### 22.1 Sem DDL inicial, se possível

Preferir gravar novos campos em:

```text
payload_json
raw_json
```

Para reduzir risco.

### 22.2 Campos recomendados se houver DDL

Adicionar nas tabelas oficiais de OB:

```sql
ALTER TABLE technical_engine_smc_order_blocks
ADD COLUMN ob_detection_profile VARCHAR(32) NOT NULL DEFAULT 'SYSTEM_DEFAULT',
ADD COLUMN ob_subtype VARCHAR(32) NULL,
ADD COLUMN source_rule VARCHAR(64) NULL,
ADD COLUMN lifecycle_status VARCHAR(32) NULL,
ADD COLUMN operational_flag VARCHAR(32) NULL;
```

### 22.3 Índices

```sql
CREATE INDEX idx_smc_ob_profile_symbol_tf
ON technical_engine_smc_order_blocks (ob_detection_profile, symbol, timeframe, created_at);

CREATE INDEX idx_smc_ob_operational
ON technical_engine_smc_order_blocks (operational_flag, lifecycle_status, symbol, timeframe);
```

---

## 23. Fase 5 — Replay Comparativo

### 23.1 Criar CLI

```text
tools/run_ob_profile_ab_replay.py
```

### 23.2 Exemplo de uso

```bash
python tools/run_ob_profile_ab_replay.py \
  --symbol WINFUT \
  --timeframe M5 \
  --from 2026-04-01 \
  --to 2026-07-01 \
  --profiles SYSTEM_DEFAULT,PROFIT_STYLE \
  --scanner same \
  --risk same \
  --output markdown,json,csv
```

### 23.3 Garantias do replay

- [ ] Mesmo dataset.
- [ ] Mesmo horário operacional.
- [ ] Mesmo spread/slippage.
- [ ] Mesmo Risk Management.
- [ ] Mesmo Opportunity Scanner.
- [ ] Mesma regra de dedup.
- [ ] Somente OB muda.

### 23.4 Saídas

```text
Sistema VPS/Relatorios/OB_PROFILE_AB_REPLAY_WINFUT_M5.md
runtime/replay/ob_profile_ab/winfut_m5_system_default.json
runtime/replay/ob_profile_ab/winfut_m5_profit_style.json
runtime/replay/ob_profile_ab/winfut_m5_comparison.csv
```

---

## 24. Métricas de Comparação

### 24.1 Métricas de zonas

```text
zones_created
zones_per_day
zones_per_session
zones_by_subtype
zones_with_imbalance
zones_from_fallback
zones_mitigated
zones_invalidated
zones_expired
avg_zone_size_ticks
median_zone_size_ticks
overlapping_zones_count
```

### 24.2 Métricas operacionais

```text
signals_created
alerts_created
alerts_per_day
deduped_alerts
entry_reached
tp1_hit
tp2_hit
tp3_hit
stop_hit
expectancy_R
profit_factor
win_rate
avg_R
max_drawdown_R
avg_time_to_entry
avg_time_to_tp1
avg_time_to_stop
```

### 24.3 Métricas de qualidade SMC

```text
percent_with_BOS_or_CHOCH
percent_with_imbalance
percent_with_extreme_local
percent_in_premium_discount_correct_side
percent_with_eqh_eql_or_flip
percent_with_htf_alignment
percent_not_mitigated_before_alert
percent_not_invalidated_before_alert
```

### 24.4 Métricas de ruído

```text
alerts_without_followthrough
zones_never_touched
zones_touched_but_no_reaction
zones_created_inside_range
zones_against_ema200_context
zones_too_close_to_current_price
zones_too_far_from_current_price
```

---

## 25. Fórmula de Score para Comparação

Criar score apenas para comparar perfis, não para vender promessa.

```text
profile_score =
  expectancy_R * 35
+ tp1_hit_rate * 20
+ profit_factor_norm * 15
+ smc_quality_score * 15
- false_alert_rate * 10
- alerts_per_day_penalty * 5
```

### 25.1 Score de qualidade SMC

```text
smc_quality_score =
  20 se tem BOS/CHOCH
+ 20 se tem imbalance
+ 15 se origem está em extremo local
+ 10 se tem EQH/EQL ou flip
+ 10 se está no lado certo do PD
+ 10 se HTF está favorável/neutro
+ 10 se não é fallback fractal
+ 5 se subtype é OB2/RB/BB
```

Normalizar para 0–100.

---

## 26. Critérios de Promoção

O modo Profit-style só pode virar padrão se passar pelos critérios.

### 26.1 Critérios mínimos

- [ ] Expectancy_R maior que o baseline.
- [ ] Profit factor maior ou igual ao baseline.
- [ ] Drawdown menor ou igual ao baseline.
- [ ] Falso alerta menor que o baseline.
- [ ] Zonas por dia menor ou igual ao baseline.
- [ ] TP1 hit rate maior ou igual ao baseline.
- [ ] Nenhum bug de causalidade.
- [ ] Nenhuma zona aparece antes do candle de confirmação.
- [ ] Replay reproduz visualmente o indicador em casos-chave.

### 26.2 Critérios recomendados

```text
expectancy_R >= baseline + 10%
false_alert_rate <= baseline - 15%
alerts_per_day <= baseline
zones_created <= baseline - 20%
tp1_hit_rate >= baseline
max_drawdown_R <= baseline
```

### 26.3 Promoção parcial

Se o Profit-style for melhor apenas em WIN 5m:

```text
ativar só para WINFUT M5
```

Se for melhor em WIN 2m e 5m:

```text
ativar para WINFUT TF <= 5m
```

Se for pior em WDO:

```text
manter WDO em SYSTEM_DEFAULT
```

---

## 27. Dashboard de Comparação

### 27.1 Tela nova

```text
Dashboard Shadow → OB Profile Compare
```

### 27.2 Funcionalidades

- [ ] Toggle `SYSTEM_DEFAULT`.
- [ ] Toggle `PROFIT_STYLE`.
- [ ] Mostrar zonas lado a lado.
- [ ] Filtro por subtipo: OB, OB2, RB, BB, EX, DC.
- [ ] Filtro por status: ativo, mitigado, invalidado.
- [ ] Filtro por source_rule: SMC_CANDLE, FALLBACK_FRACTAL.
- [ ] Mostrar eventos de mitigação.
- [ ] Mostrar métricas no gráfico.

### 27.3 Cores

Manter padrão do indicador:

```text
zona de alta = uma cor única
zona de baixa = uma cor única
tipo/estado fica no texto
```

---

## 28. Integração com Opportunity Scanner

### 28.1 Regra principal

O scanner não deve usar todo OB Profit-style automaticamente.

Usar apenas:

```text
operational_flag = "OB_OPERATIONAL"
```

### 28.2 Campos obrigatórios para oportunidade

```text
ob_detection_profile
ob_subtype
lifecycle_status
mitigation_status
source_rule
has_imbalance
has_structure_break
is_extreme_local
context_score
```

### 28.3 Gates novos

Adicionar gates:

```text
reject_if_ob_fallback_and_low_score
reject_if_ob_mitigated
reject_if_ob_invalidated
reject_if_ob_inside_bad_range
reject_if_ob_without_imbalance
reject_if_ob_without_break
```

### 28.4 Regra inicial recomendada

Durante replay:

```text
SYSTEM_DEFAULT usa scanner atual
PROFIT_STYLE usa scanner atual + filtro OB_OPERATIONAL
```

Depois testar também:

```text
PROFIT_STYLE_VISUAL_ONLY
PROFIT_STYLE_OPERATIONAL_STRICT
```

---

## 29. Testes Unitários Obrigatórios

### 29.1 Testes de cálculo base

- [ ] `Perido=5` gera níveis base corretos.
- [ ] `BreakUp` detecta rompimento de resistência.
- [ ] `BreakDown` detecta rompimento de suporte.
- [ ] `Trend` carrega estado anterior quando não há rompimento.

### 29.2 Testes de imbalance

- [ ] Bullish FVG atual: `Low > High[2]`.
- [ ] Bearish FVG atual: `High < Low[2]`.
- [ ] Bullish displacement: corpo >= 55% e close rompe máxima anterior.
- [ ] Bearish displacement: corpo >= 55% e close rompe mínima anterior.
- [ ] `idxBuscaBull` e `idxBuscaBear` são preenchidos corretamente.

### 29.3 Testes de OB bearish

- [ ] Encontra último candle de alta antes da queda.
- [ ] Exige extremo local quando configurado.
- [ ] Calcula top/bottom/midpoint/size corretamente.
- [ ] Usa fallback fractal quando permitido.
- [ ] Bloqueia fallback quando perfil `NO_FALLBACK`.

### 29.4 Testes de OB bullish

- [ ] Encontra último candle de baixa antes da alta.
- [ ] Exige extremo local quando configurado.
- [ ] Calcula top/bottom/midpoint/size corretamente.
- [ ] Usa fallback fractal quando permitido.

### 29.5 Testes de classificação

- [ ] RB bearish por pavio superior.
- [ ] RB bullish por pavio inferior.
- [ ] OB2 bearish por proximidade com supply anterior mitigada.
- [ ] OB2 bullish por proximidade com demand anterior mitigada.
- [ ] BB bearish por proximidade com demand anterior mitigada.
- [ ] BB bullish por proximidade com supply anterior mitigada.
- [ ] Prioridade RB > OB2 > BB > EX > DC > OB.

### 29.6 Testes de mitigação

- [ ] Demand mitiga no centro.
- [ ] Supply mitiga no centro.
- [ ] Demand invalida por fechamento abaixo do fundo em `Mitigacao=1`.
- [ ] Supply invalida por fechamento acima do topo em `Mitigacao=1`.
- [ ] Demand invalida por mínima abaixo do fundo em `Mitigacao=2`.
- [ ] Supply invalida por máxima acima do topo em `Mitigacao=2`.
- [ ] Não mitiga no candle de criação.
- [ ] Texto muda para `OB-M`, `OB2-M`, `RB-M`, `BB-M`.

---

## 30. Testes de Causalidade

Obrigatórios antes de qualquer replay comparativo.

### 30.1 Regras

- [ ] Nenhum cálculo usa candle futuro.
- [ ] `available_at` é sempre >= candle de confirmação.
- [ ] A zona não aparece antes do rompimento.
- [ ] A mitigação não acontece antes da criação.
- [ ] Reprocessar em streaming gera o mesmo resultado do replay incremental.

### 30.2 Teste streaming vs batch

Executar:

```text
candles em streaming candle-a-candle
vs
candles em janela batch causal
```

Os eventos Profit-style precisam bater.

---

## 31. Roteiro de Implementação

### Fase A — Preparação

- [ ] Criar branch `feature/ob-profit-style-ab-test`.
- [ ] Congelar baseline atual.
- [ ] Exportar amostra de candles WINFUT M5.
- [ ] Separar 10 screenshots/casos manuais do Profit para validação visual.
- [ ] Criar fixtures de candles sintéticos.

### Fase B — Contratos

- [ ] Criar `OBDetectionProfile`.
- [ ] Criar `OBProfitStyleConfig`.
- [ ] Criar `OBZone`.
- [ ] Criar `OBLifecycleEvent`.
- [ ] Criar serialização em `payload_json`.

### Fase C — Detector

- [ ] Implementar níveis base.
- [ ] Implementar BreakUp/BreakDown/Trend.
- [ ] Implementar imbalance.
- [ ] Implementar busca bearish.
- [ ] Implementar busca bullish.
- [ ] Implementar fallback.
- [ ] Implementar filtros V5.
- [ ] Implementar classificação.
- [ ] Implementar mitigação/invalidação.

### Fase D — Persistência

- [ ] Gravar `ob_detection_profile`.
- [ ] Gravar `source_rule`.
- [ ] Gravar `ob_subtype`.
- [ ] Gravar `lifecycle_status`.
- [ ] Não quebrar leitura antiga.

### Fase E — Replay

- [ ] Criar CLI A/B.
- [ ] Rodar WINFUT M5 30 dias.
- [ ] Rodar WINFUT M5 90 dias.
- [ ] Rodar WINFUT M2, se houver dados.
- [ ] Rodar WDO M5, se houver dados.
- [ ] Gerar relatório comparativo.

### Fase F — Dashboard

- [ ] Renderizar zonas dos dois perfis.
- [ ] Adicionar toggle.
- [ ] Conferir visualmente contra Profit.

### Fase G — Scanner

- [ ] Criar `OB_OPERATIONAL`.
- [ ] Testar scanner com Profit-style.
- [ ] Comparar alertas.

### Fase H — Decisão

- [ ] Revisar relatório.
- [ ] Escolher vencedor por ativo/timeframe.
- [ ] Promover ou manter como opcional.

---

## 32. Arquivos Prováveis a Alterar

### 32.1 Engine

```text
technical_engine/smc_engine/incremental/components/ob.py
technical_engine/smc_engine/incremental/components/ob_profit_style.py
technical_engine/smc_engine/incremental/engine.py
technical_engine/smc_engine/config.py
technical_engine/smc_engine/models.py
technical_engine/smc_engine/persistence.py
```

### 32.2 Replay

```text
tools/run_ob_profile_ab_replay.py
technical_engine/smc_engine/replay/compare_ob_profiles.py
technical_engine/opportunity_scanner/backtester.py
```

### 32.3 Dashboard

```text
dashboard_shadow/frontend/src/components/...
dashboard_shadow/backend/api/...
```

### 32.4 Scanner

```text
technical_engine/opportunity_scanner/loader.py
technical_engine/opportunity_scanner/evaluator.py
technical_engine/opportunity_scanner/signal_builder.py
```

### 32.5 Documentação

```text
docs/OB_PROFIT_STYLE_REPLAY_AB.md
Sistema VPS/Relatorios/OB_PROFILE_AB_REPLAY_WINFUT_M5.md
```

---

## 33. Relatório Final Esperado

Criar relatório:

```text
Sistema VPS/Relatorios/RELATORIO_FINAL_OB_PROFIT_STYLE_AB_TEST.md
```

### 33.1 Conteúdo do relatório

```text
1. Período testado
2. Ativos/timeframes
3. Configuração dos dois perfis
4. Quantidade de zonas
5. Quantidade de oportunidades
6. Resultado por R
7. TP1/TP2/TP3/Stop
8. Ruído por dia
9. Zonas mitigadas
10. Zonas invalidadas
11. Print comparativo sistema vs Profit
12. Decisão recomendada
13. Riscos
14. Próximos passos
```

---

## 34. Critério de Decisão Final

### 34.1 Promover Profit-style como padrão

Somente se:

```text
Profit-style tiver melhor expectancy_R
e menor ruído
e menor falso alerta
e não aumentar drawdown
e não quebrar causalidade
```

### 34.2 Manter engine atual

Se:

```text
Profit-style ficar mais bonito, mas piorar resultado
ou reduzir demais as oportunidades boas
```

### 34.3 Usar Profit-style só como filtro

Se:

```text
Profit-style melhorar qualidade visual
mas não tiver vantagem estatística suficiente
```

### 34.4 Usar por ativo/timeframe

Se:

```text
Profit-style for melhor no WIN M5
mas pior no WDO ou em outros timeframes
```

---

## 35. Rollback

Rollback simples:

```text
SMC_OB_PROFILE=SYSTEM_DEFAULT
```

Manter:

```text
PROFIT_STYLE desligado
dados gravados preservados para análise
scanner não lê Profit-style
dashboard pode ocultar Profit-style
```

Nada deve ser removido da engine atual durante essa fase.

---

## 36. Resultado Esperado

Ao final, o projeto terá:

```text
1. Engine atual preservada.
2. Modo OB Profit-style implementado em paralelo.
3. Cálculo inspirado no indicador Profit:
   - fractal/período
   - BreakUp/BreakDown
   - Trend
   - imbalance/FVG/displacement
   - último candle oposto antes do deslocamento
   - extremo local
   - fallback fractal
   - OB2
   - Rejection Block
   - Breaker Block
   - mitigação
   - invalidação
4. Replay A/B real.
5. Métricas objetivas.
6. Decisão segura sobre promover ou não.
```

---

## 37. Próximo Passo Imediato

Executar primeiro:

```text
Fase 0 — Inventário e Baseline
```

Depois criar apenas os contratos e config, sem alterar o comportamento atual:

```text
OBDetectionProfile
OBProfitStyleConfig
ob_detection_profile no payload
```

Só então implementar o detector Profit-style.

---

## 38. Resumo Executivo

A estratégia correta é:

```text
Manter engine atual
+
Adicionar OB Profit-style em paralelo
+
Rodar replay A/B
+
Medir oportunidade, não só desenho
+
Promover apenas se vencer estatisticamente
```

Isso protege o sistema contra uma troca baseada apenas em percepção visual e permite aproveitar o que o indicador Profit tem de mais forte: zonas mais limpas, classificação operacional e mitigação mais parecida com DayTrade real.
