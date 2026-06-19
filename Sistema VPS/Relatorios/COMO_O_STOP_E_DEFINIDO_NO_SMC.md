# COMO O STOP E OS ALVOS SÃO DEFINIDOS NO SMC

**Projeto:** SMC Trader System 7.0  
**Tema:** Definição estrutural de Stop Loss, Take Profits e gestão de saída em Smart Money Concepts  
**Fontes consultadas:**  
- *Smart Money Concepts — Marcos Leitão*  
- *SMC Bible — DexterrFX*  

---

# 1. PRINCÍPIO CENTRAL

No SMC, o stop não deve ser definido apenas por uma quantidade fixa de pontos, pips ou percentual.

O stop deve ficar no nível em que a hipótese técnica da operação deixa de ser válida.

```text
Stop SMC = nível estrutural de invalidação + margem de segurança
```

O nível de invalidação pode ser:

- extremo de um Order Block;
- swing protegido;
- fundo ou topo da varredura de liquidez;
- swing que originou o MSS/CHoCH;
- extremo do POI;
- nível estrutural cuja perda invalida a direção esperada.

O buffer serve para evitar que spread, slippage, ruído normal ou uma pequena varredura retirem a posição antes da invalidação estrutural real.

---

# 2. REGRA PRINCIPAL

## 2.1 Operação de compra

O stop deve ficar abaixo do ponto que precisa continuar protegido para o cenário altista permanecer válido.

Possíveis âncoras:

- fundo da varredura de liquidez;
- protected low;
- swing low que originou o deslocamento;
- extremidade inferior do Order Block;
- fundo do POI;
- swing que produziu o MSS/CHoCH altista.

```text
Compra
   │
   ├── entrada no POI, OB ou FVG
   │
   └── stop abaixo do nível que invalida a reação altista
```

## 2.2 Operação de venda

O stop deve ficar acima do ponto que precisa continuar protegido para o cenário baixista permanecer válido.

Possíveis âncoras:

- topo da varredura de liquidez;
- protected high;
- swing high que originou o deslocamento;
- extremidade superior do Order Block;
- topo do POI;
- swing que produziu o MSS/CHoCH baixista.

```text
Venda
   │
   ├── entrada no POI, OB ou FVG
   │
   └── stop acima do nível que invalida a reação baixista
```

---

# 3. STOP POR MODELO DE ENTRADA

## 3.1 Entrada em Order Block

### Bullish Order Block

```text
stop = abaixo do extremo inferior do OB + buffer
```

### Bearish Order Block

```text
stop = acima do extremo superior do OB + buffer
```

Entretanto, a extremidade do OB só deve ser usada se ela for realmente o nível de invalidação.

Quando existe uma varredura de liquidez além do OB, o stop pode precisar ficar além do extremo da varredura.

### Compra

```python
stop_anchor = min(
    order_block_bottom,
    relevant_sweep_low,
    protected_swing_low,
)
```

### Venda

```python
stop_anchor = max(
    order_block_top,
    relevant_sweep_high,
    protected_swing_high,
)
```

Somente devem participar os níveis que fazem parte da tese concreta daquela oportunidade.

---

## 3.2 Entrada após sweep ou stop hunt

### Compra após varredura de fundo

```text
stop abaixo do ponto mais baixo do stop hunt
```

### Venda após varredura de topo

```text
stop acima do ponto mais alto do stop hunt
```

A margem além do sweep não deve ser fixa para todos os mercados.

Um buffer de dez pips, por exemplo, não pode ser aplicado universalmente porque:

- WINFUT trabalha em pontos;
- Forex trabalha em pips;
- ouro possui outra volatilidade;
- Bitcoin possui outra volatilidade;
- M2, M5 e H4 exigem escalas diferentes;
- spread e slippage variam por ativo e horário.

---

## 3.3 Entrada após MSS ou CHoCH

Fluxo típico:

```text
POI HTF
→ sweep
→ displacement
→ MSS/CHoCH no timeframe de entrada
→ retração para FVG ou OB
```

### Compra

```text
stop abaixo do swing low que originou o MSS altista
```

### Venda

```text
stop acima do swing high que originou o MSS baixista
```

Esse modelo pode produzir um stop menor, mas o swing utilizado deve realmente invalidar a confirmação.

Não se deve usar um pequeno pivot interno apenas para aumentar artificialmente o R:R.

É necessário distinguir:

- estrutura swing;
- estrutura interna;
- microestrutura de entrada.

---

## 3.4 Entrada em FVG

O FVG representa desequilíbrio, mas sua borda não é obrigatoriamente o nível de invalidação.

Regra recomendada:

```text
entrada no FVG
stop no swing, OB ou origem estrutural que sustenta o FVG
```

| Situação | Stop recomendado |
|---|---|
| Entrada agressiva no FVG | além do candle de origem ou extremo estrutural |
| Entrada após MSS | além do swing que originou o MSS |
| FVG dentro de OB | além da extremidade distal do OB |
| FVG após sweep | além do extremo da varredura |

Colocar o stop apenas alguns ticks fora do FVG pode deixar o stop sem significado estrutural.

---

## 3.5 Entrada em Breaker Block

### Bullish Breaker

```text
abaixo da extremidade inferior do breaker
ou abaixo do swing que confirmou a reversão
```

### Bearish Breaker

```text
acima da extremidade superior do breaker
ou acima do swing que confirmou a reversão
```

A perda completa do breaker e da estrutura que deveria ser protegida invalida o cenário.

---

## 3.6 Entrada de continuação

### Tendência altista

```text
stop abaixo do protected low
```

### Tendência baixista

```text
stop acima do protected high
```

O protected low ou protected high não é qualquer pivot.

Um fundo torna-se estruturalmente relevante quando o movimento que partiu dele rompe um topo importante.

Um topo torna-se estruturalmente relevante quando o movimento que partiu dele rompe um fundo importante.

---

# 4. STOP ESTRUTURAL E BUFFER

Após selecionar a âncora estrutural, deve-se adicionar uma margem.

## 4.1 Componentes do buffer

```text
spread
+ tick mínimo
+ slippage esperado
+ volatilidade
+ margem contra pequenas varreduras
```

## 4.2 Fórmula recomendada

```python
buffer = max(
    min_stop_ticks * tick_size,
    spread_points + expected_slippage_points,
    atr * stop_buffer_atr,
)
```

### Compra

```python
stop = structural_anchor - buffer
```

### Venda

```python
stop = structural_anchor + buffer
```

---

# 5. USO CORRETO DO ATR

O ATR não deveria escolher sozinho o local do stop.

## Uso correto

```text
1. A estrutura define a âncora.
2. O ATR define ou ajuda a definir o buffer.
3. O ATR verifica se o stop ficou excessivamente largo.
```

## Uso inadequado

```text
stop = entrada - 1 ATR
```

sem considerar:

- OB;
- swing;
- sweep;
- protected high/low;
- POI;
- estrutura;
- invalidação.

Nesse caso, o stop pode ficar:

- no meio do OB;
- dentro da liquidez;
- antes do protected low;
- no meio da estrutura;
- longe demais sem justificativa.

---

# 6. QUANDO NÃO ENTRAR

Depois de calcular o stop estrutural, ele pode ficar excessivamente distante.

Exemplo:

```text
entrada = 128.500
stop estrutural = 127.900
risco = 600 pontos
```

A solução correta não é aproximar o stop para um nível sem validade estrutural.

As alternativas corretas são:

```text
não operar
ou
esperar confirmação em timeframe menor
ou
reduzir o tamanho da posição
```

Regra recomendada:

```python
stop = structural_invalidation + volatility_buffer

if stop_distance_atr > max_stop_atr:
    block("STOP_TOO_WIDE")
```

---

# 7. STOP E DIMENSIONAMENTO DA POSIÇÃO

O stop deve ser calculado antes do tamanho da posição.

```python
risk_points = abs(entry - stop)

contracts = floor(
    account_capital * risk_percent
    / (risk_points * point_value)
)
```

É incorreto aproximar o stop apenas para permitir mais contratos.

```text
Errado:
“Quero operar 5 contratos, então vou diminuir o stop.”

Correto:
“O stop técnico exige esta distância; o tamanho da posição deve se adaptar.”
```

---

# 8. BREAKEVEN E GESTÃO POSTERIOR

O breakeven não deve ser movido apenas porque o preço avançou alguns pontos.

A proteção deve ocorrer após confirmação estrutural.

Possíveis critérios:

```text
rompimento de swing relevante
novo BOS
afastamento mínimo em R
mitigação concluída
entrada confirmada
```

Modelo possível:

```text
PRO_TREND:
mover para breakeven após confirmação estrutural e afastamento mínimo em R.

COUNTER_TREND:
proteger mais rapidamente, pois o cenário possui menor expectativa estrutural.
```

Essas regras devem ser backtestadas por ativo e timeframe.

---

# 9. REGRA DETERMINÍSTICA RECOMENDADA

## 9.1 Compra

```python
candidates = [
    relevant_poi_bottom,
    protected_swing_low,
    liquidity_sweep_low,
    confirmation_swing_low,
]

stop_anchor = select_true_invalidation_level(candidates)

buffer = max(
    asset_min_ticks * tick_size,
    current_spread + expected_slippage,
    atr * configured_buffer_atr,
)

stop = stop_anchor - buffer
```

## 9.2 Venda

```python
candidates = [
    relevant_poi_top,
    protected_swing_high,
    liquidity_sweep_high,
    confirmation_swing_high,
]

stop_anchor = select_true_invalidation_level(candidates)

buffer = max(
    asset_min_ticks * tick_size,
    current_spread + expected_slippage,
    atr * configured_buffer_atr,
)

stop = stop_anchor + buffer
```

---

# 10. VALIDAÇÕES OBRIGATÓRIAS

```python
if stop_is_on_wrong_side_of_entry:
    block("INVALID_STOP_GEOMETRY")

if stop_distance_atr > max_stop_atr:
    block("STOP_TOO_WIDE")

if rr_tp1 < min_rr_tp1:
    block("RR_TP1_TOO_LOW")

if position_size < 1:
    block("CAPITAL_INCOMPATIBLE")

if stop_anchor_is_unconfirmed_micro_swing:
    block("WEAK_INVALIDATION_ANCHOR")
```

Validações adicionais recomendadas:

```python
if stop_anchor_is_inside_entry_zone:
    block("STOP_INSIDE_ENTRY_ZONE")

if stop_buffer_is_below_spread:
    block("BUFFER_BELOW_SPREAD")

if protected_swing_is_stale:
    block("STALE_STOP_ANCHOR")

if structural_anchor_missing:
    block("NO_VALID_STOP_ANCHOR")
```

---

# 11. TIPOS DE STOP RECOMENDADOS NO SISTEMA

Criar enum:

```python
class StopAnchorType(str, Enum):
    ORDER_BLOCK_EXTREME = "ORDER_BLOCK_EXTREME"
    LIQUIDITY_SWEEP = "LIQUIDITY_SWEEP"
    PROTECTED_SWING = "PROTECTED_SWING"
    CONFIRMATION_SWING = "CONFIRMATION_SWING"
    BREAKER_EXTREME = "BREAKER_EXTREME"
    POI_EXTREME = "POI_EXTREME"
    STRUCTURAL_FALLBACK = "STRUCTURAL_FALLBACK"
```

Criar enum do método:

```python
class StopMethod(str, Enum):
    STRUCTURAL = "STRUCTURAL"
    STRUCTURAL_WITH_ATR_BUFFER = "STRUCTURAL_WITH_ATR_BUFFER"
    ZONE_FALLBACK_WITH_ATR_BUFFER = "ZONE_FALLBACK_WITH_ATR_BUFFER"
```

---

# 12. CAMPOS RECOMENDADOS NO OPERATIONALPLANV2

```json
{
  "stop": 128100,
  "stop_method": "STRUCTURAL_WITH_ATR_BUFFER",
  "stop_anchor_type": "LIQUIDITY_SWEEP",
  "stop_anchor_price": 128140,
  "buffer_points": 40,
  "buffer_atr": 0.18,
  "spread_points": 5,
  "slippage_points": 5,
  "stop_distance_points": 400,
  "stop_distance_atr": 1.82,
  "invalidation_rule": "Fechamento abaixo do fundo da varredura invalida o cenário altista",
  "fallback_used": false,
  "fallback_reason": null
}
```

---

# 13. EXPLICAÇÃO PARA O CLIENTE

O aplicativo não deve mostrar somente:

```text
Stop: 128.100
```

Deve mostrar também a justificativa:

> **Stop abaixo do fundo da varredura de liquidez que originou o deslocamento altista, acrescido de uma margem de volatilidade. A perda desse nível invalida o cenário.**

Outros exemplos:

### Order Block

> Stop abaixo da extremidade do Order Block que originou o deslocamento e a quebra estrutural.

### Protected swing

> Stop abaixo do fundo protegido que sustenta a continuidade da estrutura altista.

### Confirmação LTF

> Stop abaixo do swing que gerou a mudança estrutural no timeframe de entrada.

---

# 14. ORDEM DE PRIORIDADE RECOMENDADA

A seleção da âncora não deve ser apenas uma ordem fixa. Ela deve depender da tese.

Ordem conceitual sugerida:

```text
1. Nível que explicitamente invalida a tese.
2. Extremo da varredura, quando o setup depende de sweep.
3. Protected swing, quando o setup depende de continuidade.
4. Swing de confirmação, quando a entrada é refinada em LTF.
5. Extremo do OB/POI, quando ele representa a origem válida.
6. Fallback estrutural da zona.
```

---

# 15. EXEMPLOS

## 15.1 Compra após sweep

```text
Entrada: 128.500
Sweep low: 128.120
ATR M5: 220 pontos
Spread + slippage: 10 pontos
Buffer ATR configurado: 0,15
Buffer ATR: 33 pontos

Buffer final:
max(10, 33) = 33 pontos

Stop:
128.120 - 33 = 128.087
```

Arredondando ao tick válido do ativo:

```text
Stop final = 128.085 ou 128.080
```

## 15.2 Venda em bearish OB

```text
Entrada: 2.350,00
Topo do OB: 2.357,50
ATR M5: 6,00
Spread + slippage: 0,50
Buffer ATR: 0,20 × 6,00 = 1,20

Buffer final:
max(0,50, 1,20) = 1,20

Stop:
2.357,50 + 1,20 = 2.358,70
```

## 15.3 Stop excessivamente largo

```text
Entrada: 128.500
Stop estrutural: 127.900
ATR: 250 pontos

Distância:
600 pontos

stop_distance_atr:
600 / 250 = 2,40 ATR
```

Se:

```text
max_stop_atr = 2,00
```

Resultado:

```text
BLOQUEADO — STOP_TOO_WIDE
```

---

# 16. RECOMENDAÇÃO PARA O SMC TRADER SYSTEM 7.0

A base atual está conceitualmente correta:

```text
stop estrutural preferido
+ buffer ATR
+ fallback pela zona
```

Melhorias recomendadas:

1. priorizar explicitamente o extremo do sweep quando a tese depender de varredura;
2. diferenciar o tipo de âncora;
3. persistir separadamente âncora e buffer;
4. registrar o motivo da invalidação;
5. bloquear stops excessivos;
6. bloquear anchors fracos;
7. não reduzir artificialmente o stop para melhorar R:R;
8. gerar explicação para o aplicativo;
9. backtestar parâmetros por ativo e timeframe;
10. manter o cálculo determinístico e sem LLM decisora.

---

# 17. CONCLUSÃO DA PARTE DE STOP

A regra central é:

> **O stop deve ficar no ponto em que o mercado prova que a leitura SMC estava errada, e não no ponto em que o prejuízo parece mais confortável.**

A estrutura define a invalidação.

O spread, o slippage, o tick e o ATR definem a margem de segurança.

O gerenciamento de risco define o tamanho da posição.

Se a distância estrutural resultar em risco excessivo ou R:R inadequado, a oportunidade deve ser bloqueada, refinada em timeframe menor ou descartada.

---

# PARTE II — COMO OS ALVOS SÃO DEFINIDOS NO SMC

# 18. PRINCÍPIO CENTRAL DOS ALVOS

No SMC, o alvo não deve ser escolhido apenas por uma quantidade fixa de pontos ou por um múltiplo arbitrário de risco.

O alvo deve representar um local lógico para onde o preço possui razão estrutural para se deslocar.

```text
Alvo SMC = draw on liquidity ou estrutura/POI oposto relevante
```

Em termos práticos, os alvos normalmente são encontrados em:

- weak highs e weak lows;
- Buy-Side Liquidity e Sell-Side Liquidity;
- Equal Highs e Equal Lows;
- swing highs e swing lows relevantes;
- liquidez interna e externa;
- máxima ou mínima anterior;
- máxima ou mínima da sessão;
- máxima ou mínima diária, semanal ou mensal;
- estrutura M15 ou H4;
- zona oposta de supply/demand;
- Order Block oposto ainda não mitigado;
- FVG/BPR oposto relevante;
- extremo de range;
- nível institucional ou projeção técnica secundária.

A regra central é:

> **O stop marca onde a tese fica errada; o alvo marca onde existe liquidez, estrutura ou oferta/demanda capaz de receber o preço.**

---

# 19. O QUE OS LIVROS INDICAM

## 19.1 Weak points como alvos

O livro *Smart Money Concepts*, nas páginas 19 a 21, apresenta os weak points como alvos naturais:

- em estrutura altista, o weak high tende a ser o objetivo;
- em estrutura baixista, o weak low tende a ser o objetivo;
- strong points produzem deslocamentos que atacam weak points;
- weak points são possíveis alvos porque fazem parte da formação estrutural.

Isso produz a regra:

```text
ALTISTA:
strong/protected low → objetivo no weak high

BAIXISTA:
strong/protected high → objetivo no weak low
```

## 19.2 Estrutura M15 e H4

O *SMC Bible*, nas páginas 85 a 87, separa a gestão pró-tendência da gestão contra a tendência.

Para operação a favor da tendência, o modelo apresentado é:

```text
TP1 = estrutura M15
TP2 = estrutura H4
```

O segundo alvo só é coerente quando M15 e H4 estão alinhados na mesma direção.

Em um exemplo posterior, o livro utiliza:

```text
TP1 = weak low ou demand M15
TP2 = demand H4
```

A lógica vale inversamente para uma compra:

```text
TP1 = weak high ou supply M15
TP2 = supply/liquidez H4
```

## 19.3 Alvo contra a tendência

O material em português mostra que operações contra a tendência devem buscar alvos mais curtos, normalmente no fim da retração ou na primeira estrutura relevante.

```text
Contra tendência:
alvo curto dentro da estrutura

Pró-tendência:
alvos mais longos na direção do fluxo dominante
```

## 19.4 Fibonacci como projeção secundária

O livro *Smart Money Concepts* também apresenta extensões de Fibonacci, como:

```text
-0.272
-0.618
-0.5
-1
-2
```

Esses níveis podem complementar a projeção, mas não devem substituir:

- liquidez;
- estrutura;
- weak points;
- POIs opostos;
- contexto multi-timeframe.

Fibonacci deve ser tratado como confirmação ou fallback secundário, nunca como única razão do alvo.

---

# 20. HIERARQUIA DOS ALVOS

A seleção recomendada deve respeitar a seguinte hierarquia conceitual.

## 20.1 Primeiro nível — liquidez interna ou estrutura próxima

Usado normalmente como TP1:

- weak high/low próximo;
- swing interno;
- EQH/EQL interno;
- BSL/SSL interna;
- estrutura M15;
- primeiro POI oposto;
- primeira zona que pode reagir contra a operação.

Objetivo:

```text
realizar parte do ganho antes da primeira barreira relevante
```

## 20.2 Segundo nível — liquidez externa ou estrutura operacional

Usado normalmente como TP2:

- swing externo;
- máxima/mínima do range;
- estrutura M15 principal;
- estrutura H4 próxima;
- Order Block oposto;
- supply/demand oposta;
- PDH/PDL;
- máxima/mínima de sessão relevante.

Objetivo:

```text
capturar o deslocamento estrutural principal
```

## 20.3 Terceiro nível — objetivo HTF

Usado normalmente como TP3:

- estrutura H4;
- liquidez externa H4;
- máxima/mínima diária;
- máxima/mínima semanal;
- POI HTF;
- extremo do range maior;
- próximo objetivo D1, quando houver alinhamento.

Objetivo:

```text
manter uma parcela residual para o movimento de maior extensão
```

TP3 não deve ser criado apenas porque o modelo possui três campos. Se não existir um terceiro objetivo válido, o plano deve ter somente TP1 e TP2.

---

# 21. ALVOS DE COMPRA E VENDA

## 21.1 Compra

O preço-alvo deve estar acima da entrada.

Possíveis candidatos:

```python
bullish_target_candidates = [
    nearest_weak_high,
    unswept_buy_side_liquidity,
    equal_highs,
    previous_session_high,
    previous_day_high,
    opposing_supply,
    opposing_bearish_order_block,
    m15_structure_high,
    h4_structure_high,
    external_range_high,
]
```

## 21.2 Venda

O preço-alvo deve estar abaixo da entrada.

Possíveis candidatos:

```python
bearish_target_candidates = [
    nearest_weak_low,
    unswept_sell_side_liquidity,
    equal_lows,
    previous_session_low,
    previous_day_low,
    opposing_demand,
    opposing_bullish_order_block,
    m15_structure_low,
    h4_structure_low,
    external_range_low,
]
```

---

# 22. ALVOS POR MODELO DE ENTRADA

## 22.1 Entrada em Order Block

### Compra em bullish OB

```text
TP1 = weak high ou liquidez interna mais próxima
TP2 = BSL externa ou estrutura M15
TP3 = supply/estrutura H4, quando alinhada
```

### Venda em bearish OB

```text
TP1 = weak low ou liquidez interna mais próxima
TP2 = SSL externa ou estrutura M15
TP3 = demand/estrutura H4, quando alinhada
```

## 22.2 Entrada após sweep

O objetivo natural costuma ser o lado oposto da liquidez ou a estrutura que o sweep pretende atacar.

### Compra após sweep de SSL

```text
TP1 = liquidez interna acima
TP2 = weak high ou BSL externa
TP3 = estrutura HTF acima
```

### Venda após sweep de BSL

```text
TP1 = liquidez interna abaixo
TP2 = weak low ou SSL externa
TP3 = estrutura HTF abaixo
```

## 22.3 Entrada após MSS/CHoCH

```text
TP1 = primeiro swing da nova direção
TP2 = liquidez externa ou estrutura operacional
TP3 = objetivo HTF, somente após alinhamento
```

O CHoCH isolado não garante reversão completa. Em uma reversão inicial, o alvo deve ser mais conservador até que exista confirmação adicional.

## 22.4 Entrada em FVG

O FVG define a região de entrada, não necessariamente o destino.

```text
TP = liquidez, estrutura ou POI oposto
```

Não usar automaticamente a outra borda do FVG como alvo final.

## 22.5 Entrada em Breaker

```text
TP1 = primeira liquidez da nova direção
TP2 = weak swing externo
TP3 = POI ou estrutura HTF oposta
```

## 22.6 Continuação de tendência

### Compra pró-tendência

```text
TP1 = weak high/M15
TP2 = BSL externa/H4
TP3 = PDH, máxima semanal ou próximo POI HTF
```

### Venda pró-tendência

```text
TP1 = weak low/M15
TP2 = SSL externa/H4
TP3 = PDL, mínima semanal ou próximo POI HTF
```

---

# 23. PRÓ-TENDÊNCIA E CONTRA-TENDÊNCIA

## 23.1 Pró-tendência

Quando D1/H4, M15 e M5 estão alinhados:

- é aceitável buscar estrutura mais distante;
- TP1 pode usar a estrutura M15;
- TP2 pode usar a estrutura H4;
- TP3 pode buscar liquidez externa HTF;
- uma parcela pode ser mantida para extensão.

```text
D1/H4 alinhados
+ M15 alinhado
+ setup M5 válido
→ alvos progressivamente mais longos
```

## 23.2 Contra-tendência

Quando a operação está contra o viés HTF:

- alvo deve ser mais curto;
- priorizar liquidez interna;
- priorizar o fim provável da retração;
- reduzir ou eliminar TP3;
- não presumir rompimento da estrutura H4;
- proteger a posição mais cedo.

```text
Contra HTF
→ TP1 na primeira estrutura
→ TP2 opcional no fim da retração
→ TP3 normalmente ausente
```

Se o sistema estiver configurado para bloquear contra-tendência, nenhum plano deve ser criado.

---

# 24. LIQUIDEZ INTERNA E EXTERNA

## 24.1 Liquidez interna

Exemplos:

- pequenos swing highs/lows dentro do range;
- EQH/EQL internos;
- subestrutura;
- níveis criados durante a retração;
- inducements.

Uso:

```text
TP1 ou parcial
```

## 24.2 Liquidez externa

Exemplos:

- máxima/mínima do range;
- swing high/low principal;
- PDH/PDL;
- máxima/mínima semanal;
- BSL/SSL externa;
- estrutura H4.

Uso:

```text
TP2 ou TP3
```

---

# 25. ZONAS OPOSTAS COMO BARREIRAS E ALVOS

Uma zona oposta não é apenas alvo. Ela também pode bloquear o caminho até um alvo mais distante.

Exemplo de compra:

```text
entrada bullish
→ bearish OB não mitigado no caminho
→ BSL acima desse OB
```

Nesse caso:

- o bearish OB pode ser TP1;
- a BSL pode ser TP2 somente se houver espaço e probabilidade de rompimento;
- não se deve ignorar a zona intermediária apenas para obter R:R maior.

Regra:

```python
if opposing_zone_between_entry_and_target:
    target_path_has_barrier = True
```

A oportunidade pode ser:

- mantida com parcial antes da barreira;
- rebaixada;
- bloqueada por R:R insuficiente.

---

# 26. R:R NÃO DEVE CRIAR O ALVO

O Risk/Reward serve para validar a geometria da oportunidade.

```text
A estrutura cria o alvo.
O stop define 1R.
O R:R mede se vale a pena operar.
```

Uso correto:

```python
rr = abs(target_price - entry_price) / abs(entry_price - stop_price)
```

Uso inadequado:

```text
“TP1 será 1,5R apenas porque 1,5R foi configurado.”
```

Um alvo puramente em R não possui necessariamente razão SMC.

## 26.1 Exceção: alvo de gestão

Um limite em R pode ser usado para executar parcial antes do alvo estrutural.

Nesse caso, separar:

```text
structural_target_price
execution_target_price
```

Exemplo:

```text
Alvo estrutural: weak high em 1,80R
Alvo de execução da parcial: 1,50R
```

O sistema deve registrar que o preço de execução foi limitado por gestão, e não tratá-lo como um alvo SMC estrutural.

---

# 27. BUFFER DE EXECUÇÃO DO ALVO

O take profit pode ser posicionado pouco antes do nível de liquidez para aumentar a chance de execução.

## 27.1 Compra

```python
tp_execution = target_anchor_price - target_buffer
```

## 27.2 Venda

```python
tp_execution = target_anchor_price + target_buffer
```

## 27.3 Buffer recomendado

```python
target_buffer = max(
    min_target_ticks * tick_size,
    expected_execution_slippage,
    atr * target_buffer_atr,
)
```

O spread deve ser tratado conforme o modelo de execução do ativo e da corretora.

Não aplicar o mesmo cálculo de spread indistintamente a compra e venda.

---

# 28. PARCIAIS

O sistema atual utiliza como referência:

```text
TP1 = 50%
TP2 = 25%
TP3 = 25%
```

Essa distribuição é adequada como configuração inicial, mas deve ser calibrável por ativo, timeframe e perfil.

O *SMC Bible* menciona parciais de aproximadamente 30% a 40% como preferência do autor, mas isso não constitui regra universal.

## 28.1 Configuração sugerida

```python
partial_tp1 = 0.50
partial_tp2 = 0.25
partial_tp3 = 0.25
```

Perfis possíveis:

```text
CONSERVADOR:
60% / 30% / 10%

BALANCEADO:
50% / 25% / 25%

TENDENCIAL:
30% / 30% / 40%
```

Qualquer perfil deve ser validado por backtest.

---

# 29. QUANDO NÃO CRIAR ALVOS

Bloquear ou reduzir o plano quando:

- não existe alvo estrutural à frente;
- TP1 está do lado errado da entrada;
- liquidez já foi varrida;
- zona-alvo já foi mitigada e não possui relevância;
- primeira barreira produz R:R abaixo do mínimo;
- target está excessivamente próximo;
- target depende de dado futuro;
- target contradiz o HTF;
- target exige atravessar múltiplas zonas opostas;
- não existe monotonicidade entre TP1, TP2 e TP3.

Validações:

```python
if direction == "ALTISTA" and not (entry < tp1 < tp2 < tp3):
    block("INVALID_TARGET_MONOTONICITY")

if direction == "BAIXISTA" and not (entry > tp1 > tp2 > tp3):
    block("INVALID_TARGET_MONOTONICITY")

if rr_tp1 < min_rr_tp1:
    block("RR_TP1_TOO_LOW")

if target_is_already_swept:
    reject_target("TARGET_ALREADY_SWEPT")

if target_available_at > evaluation_time:
    reject_target("TARGET_LOOKAHEAD")
```

Quando houver somente um ou dois alvos válidos, não forçar um terceiro alvo artificial.

---

# 30. ALGORITMO DETERMINÍSTICO RECOMENDADO

## 30.1 Coleta de candidatos

```python
candidates = collect_target_candidates(
    unswept_liquidity=True,
    weak_swings=True,
    opposing_unmitigated_zones=True,
    structural_events=True,
    session_levels=True,
    previous_high_low=True,
    htf_structures=True,
)
```

## 30.2 Filtros

```python
candidates = [
    c for c in candidates
    if c.available_at <= evaluation_time
    and c.is_ahead_of_entry(direction)
    and not c.is_swept
    and c.is_relevant
]
```

## 30.3 Barreiras

```python
for candidate in candidates:
    candidate.barriers = find_opposing_zones_between(
        entry_price,
        candidate.price,
        direction,
    )
```

## 30.4 Ranking

Critérios sugeridos:

```text
1. validade temporal;
2. direção correta;
3. liquidez não varrida;
4. relevância estrutural;
5. timeframe;
6. qualidade;
7. ausência de barreiras;
8. distância;
9. R:R;
10. alinhamento HTF.
```

## 30.5 Seleção

### Pró-tendência

```python
tp1 = nearest_valid_internal_or_m15_target
tp2 = next_external_or_h4_target
tp3 = next_htf_target_if_aligned
```

### Contra-tendência

```python
tp1 = nearest_internal_structure
tp2 = retracement_end_or_m15_opposing_zone
tp3 = None
```

## 30.6 Caps em R

A configuração atual reportada usa caps aproximados:

```text
TP1: 1,5R
TP2: 2,5R
TP3: 4,0R
```

Recomendação:

- manter como limites de execução/gestão;
- não usar como geradores primários do alvo;
- persistir o alvo estrutural original;
- registrar quando o cap modificou o preço executável.

---

# 31. TIPOS DE ALVO RECOMENDADOS

```python
class TargetAnchorType(str, Enum):
    WEAK_HIGH = "WEAK_HIGH"
    WEAK_LOW = "WEAK_LOW"
    BUY_SIDE_LIQUIDITY = "BUY_SIDE_LIQUIDITY"
    SELL_SIDE_LIQUIDITY = "SELL_SIDE_LIQUIDITY"
    EQUAL_HIGHS = "EQUAL_HIGHS"
    EQUAL_LOWS = "EQUAL_LOWS"
    STRUCTURAL_SWING = "STRUCTURAL_SWING"
    M15_STRUCTURE = "M15_STRUCTURE"
    H4_STRUCTURE = "H4_STRUCTURE"
    PREVIOUS_DAY_HIGH = "PREVIOUS_DAY_HIGH"
    PREVIOUS_DAY_LOW = "PREVIOUS_DAY_LOW"
    PREVIOUS_WEEK_HIGH = "PREVIOUS_WEEK_HIGH"
    PREVIOUS_WEEK_LOW = "PREVIOUS_WEEK_LOW"
    SESSION_HIGH = "SESSION_HIGH"
    SESSION_LOW = "SESSION_LOW"
    OPPOSING_ORDER_BLOCK = "OPPOSING_ORDER_BLOCK"
    OPPOSING_SUPPLY_DEMAND = "OPPOSING_SUPPLY_DEMAND"
    OPPOSING_FVG = "OPPOSING_FVG"
    OPPOSING_BPR = "OPPOSING_BPR"
    RANGE_EXTREME = "RANGE_EXTREME"
    FIBONACCI_EXTENSION = "FIBONACCI_EXTENSION"
    R_MANAGEMENT_CAP = "R_MANAGEMENT_CAP"
```

Método:

```python
class TargetMethod(str, Enum):
    STRUCTURAL = "STRUCTURAL"
    LIQUIDITY = "LIQUIDITY"
    OPPOSING_ZONE = "OPPOSING_ZONE"
    MTF_STRUCTURE = "MTF_STRUCTURE"
    STRUCTURE_OR_R_CAP = "STRUCTURE_OR_R_CAP"
    FIBONACCI_FALLBACK = "FIBONACCI_FALLBACK"
```

---

# 32. CAMPOS RECOMENDADOS NO OPERATIONALPLANV2

Cada alvo deveria registrar:

```json
{
  "target_id": "tp1",
  "target_price": 129100,
  "structural_target_price": 129120,
  "execution_target_price": 129100,
  "target_method": "LIQUIDITY",
  "target_anchor_type": "WEAK_HIGH",
  "target_timeframe": "M15",
  "target_source_ref": "swing-123",
  "target_buffer_points": 20,
  "distance_points": 600,
  "distance_atr": 2.4,
  "rr": 1.5,
  "partial_percent": 0.50,
  "barriers_count": 0,
  "htf_aligned": true,
  "fallback_used": false,
  "management_cap_applied": false,
  "reason": "Topo fraco M15 com liquidez não varrida"
}
```

Campos adicionais no setup:

```json
{
  "target_count": 3,
  "target_profile": "BALANCEADO",
  "target_selection_version": "TARGET_SELECTION_V1",
  "target_path_clear": true,
  "target_invalidation_policy": "ORIGINAL_TARGETS_IMMUTABLE",
  "tp1": {},
  "tp2": {},
  "tp3": {}
}
```

---

# 33. EXPLICAÇÃO PARA O CLIENTE

O aplicativo não deve mostrar apenas:

```text
TP1: 129.100
TP2: 129.500
TP3: 130.100
```

Deve explicar:

### TP1

> **Primeiro alvo na região de topo fraco do M15, onde existe liquidez compradora ainda não varrida.**

### TP2

> **Segundo alvo na liquidez externa formada por topos iguais e estrutura operacional anterior.**

### TP3

> **Terceiro alvo na estrutura H4, mantido porque H4, M15 e M5 estão alinhados na mesma direção.**

Contra-tendência:

> **O alvo foi limitado à primeira estrutura interna porque a oportunidade ocorre contra o viés do timeframe maior.**

---

# 34. EXEMPLOS NUMÉRICOS

## 34.1 Compra pró-tendência

```text
Entrada: 128.500
Stop: 128.100
R: 400 pontos
```

Candidatos:

```text
Weak high M15: 129.120
EQH/BSL: 129.520
Estrutura H4: 130.120
```

Buffer de alvo:

```text
20 pontos
```

Execução:

```text
TP1 = 129.100
RR TP1 = (129.100 - 128.500) / 400 = 1,50R

TP2 = 129.500
RR TP2 = (129.500 - 128.500) / 400 = 2,50R

TP3 = 130.100
RR TP3 = (130.100 - 128.500) / 400 = 4,00R
```

Distribuição:

```text
TP1: 50%
TP2: 25%
TP3: 25%
```

## 34.2 Venda contra a tendência

```text
Entrada: 2.350,00
Stop: 2.356,00
R: 6,00
```

Candidatos:

```text
Liquidez interna: 2.341,00
Demand M15: 2.334,00
Estrutura H4: 2.310,00
```

Como a operação é contra o HTF:

```text
TP1 = 2.341,00
TP2 = 2.334,00
TP3 = ausente
```

Não usar 2.310,00 apenas porque produz R:R elevado.

## 34.3 Primeira barreira invalida a operação

```text
Entrada: 128.500
Stop: 128.100
R: 400
Primeira supply oposta: 128.850
```

```text
RR da primeira barreira:
350 / 400 = 0,875R
```

Se:

```text
min_rr_tp1 = 1,20
```

Resultado:

```text
BLOQUEADO — RR_TP1_TOO_LOW
```

Não ignorar a supply e escolher uma liquidez mais distante artificialmente.

---

# 35. TARGETS ORIGINAIS E GESTÃO POSTERIOR

Os alvos originais devem ser congelados no:

```text
OpportunityEvidenceBundleV1
```

Lifecycle posterior pode registrar:

- parcial executada;
- alvo atingido;
- trailing ativado;
- saída manual;
- breakeven;
- encerramento antecipado;
- target revisado por nova política.

Mas nunca deve sobrescrever silenciosamente os alvos originais.

Criar eventos:

```text
TP1_REACHED
TP2_REACHED
TP3_REACHED
PARTIAL_EXECUTED
TARGET_CANCELLED
TARGET_REVISED
```

Se houver revisão:

```text
original_target_price
revised_target_price
revision_reason
revision_time
policy_version
```

---

# 36. RECOMENDAÇÃO PARA O SISTEMA ATUAL

O sistema atual reporta:

```text
Universo:
zonas opostas não mitigadas
+ liquidez não varrida
+ eventos estruturais

Parciais:
50% / 25% / 25%

Caps:
1,5R / 2,5R / 4,0R
```

Essa base é boa.

Melhorias recomendadas:

1. priorizar weak highs/lows explicitamente;
2. diferenciar liquidez interna e externa;
3. incluir timeframe do alvo;
4. incluir source reference;
5. detectar zonas opostas entre entrada e alvo;
6. separar alvo estrutural de preço executável;
7. usar R caps como gestão, não como origem;
8. permitir menos de três alvos;
9. reduzir alvos contra a tendência;
10. exigir alinhamento H4/M15 para TP HTF;
11. persistir buffer de execução;
12. congelar alvos no Evidence Bundle;
13. mostrar a justificativa no app;
14. calibrar parciais e caps por ativo/timeframe;
15. medir taxa histórica de alcance por tipo de alvo.

---

# 37. TAXA HISTÓRICA DE ALCANCE

Para cada tipo de alvo, medir:

```text
taxa de alcance antes do stop
tempo médio até o alvo
MAE antes do alvo
MFE
amostra
regime
ativo
timeframe
pró-tendência ou contra-tendência
```

Exemplo:

```json
{
  "target_anchor_type": "WEAK_HIGH",
  "symbol": "WINFUT",
  "timeframe": "M5",
  "sample_size": 184,
  "reach_rate_before_stop": 0.61,
  "median_bars_to_target": 14,
  "expectancy_r": 0.42
}
```

Se:

```text
sample_size < 30
```

Mostrar:

```text
AMOSTRA_BAIXA
```

Não chamar taxa histórica de probabilidade futura.

---

# 38. CONCLUSÃO GERAL

## Stop

> **O stop fica onde o mercado prova que a leitura SMC estava errada.**

## Alvo

> **O alvo fica onde existe liquidez, estrutura ou uma zona oposta que o preço possui razão técnica para buscar.**

## R:R

> **O R:R não cria a geometria; ele valida se a geometria oferece retorno compatível com o risco.**

## Gestão

> **Parciais, caps em R e breakeven administram a operação, mas não devem apagar a razão estrutural original.**

A sequência correta é:

```text
1. Definir a entrada.
2. Definir a invalidação estrutural.
3. Calcular o stop e 1R.
4. Mapear liquidez, estruturas e zonas à frente.
5. Selecionar TP1, TP2 e TP3 válidos.
6. Verificar barreiras.
7. Calcular R:R.
8. Bloquear a operação se TP1 não compensar o risco.
9. Definir parciais.
10. Congelar entrada, stop e alvos no Evidence Bundle.
```
