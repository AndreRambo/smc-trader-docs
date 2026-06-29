# PLANO V5 — CORREÇÃO DEFINITIVA DOS BUGS DE ZOOM, VIEWPORT E OVERLAYS SMC

**Projeto:** Maximus Trade  
**Data da auditoria:** 26/06/2026  
**Fonte auditada:** `frontend(1).zip`  
**Versão do Lightweight Charts:** `5.2.0`  
**Foco exclusivo:** bugs que surgem ao afastar o zoom e permanecem após aproximar novamente  
**Status do build auditado:** `PASS`  
**Status do lint auditado:** `FAIL — 144 erros e 19 avisos`  
**Gate de produção:** `NO_GO`

---

# 1. Objetivo

Corrigir o gráfico para que:

1. afastar o zoom não introduza zonas ou linhas artificiais;
2. aproximar novamente restaure exatamente a visualização correspondente à viewport;
3. itens fora da viewport não sejam comprimidos nas bordas;
4. budgets por timeframe e zoom sejam realmente aplicados;
5. labels não reapareçam acumulados depois do zoom;
6. histórico carregado continue disponível, mas somente os itens relevantes sejam renderizados;
7. pan, zoom, prepend e live não deixem estado visual residual;
8. a seleção de SMC seja determinística e reversível;
9. o usuário possa alternar entre zoom aberto e fechado sem precisar recarregar a página;
10. todas as correções sejam cobertas por testes automatizados e visuais.

---

# 2. Resumo executivo da auditoria

O bug foi confirmado como um problema de **pipeline de viewport**, e não do Lightweight Charts em si.

O código possui:

- `getDetailLevel()`;
- budgets por timeframe;
- ranking;
- clustering;
- `selectVisible()`;
- `crossesWindow()`;
- `LabelPlacer.reset()`.

Porém, essas funções principais **não são usadas pelo gráfico nem pelos renderers**.

## Fluxo atual real

```text
zonas normalizadas
→ setData(rawItems) em cada primitive
→ renderer usa slice(maxItems)
→ xFromTimeClamp() comprime tempos fora da viewport nas bordas
→ draw
```

## Fluxo planejado, mas não conectado

```text
zonas normalizadas
→ visible range
→ detail level
→ ranking
→ clustering
→ budget por tipo
→ label budget
→ renderer
```

O segundo fluxo existe em `smcVisibility.ts`, mas está morto.

---

# 3. Causa principal confirmada

# P0.1 — `selectVisible()` nunca é chamado

**Arquivo:** `src/components/chart/smc/smcVisibility.ts`

A função foi implementada com:

- filtro por janela;
- hysteresis;
- budgets;
- ranking;
- clustering;
- detalhe por zoom.

Entretanto, a busca no projeto confirma:

```text
selectVisible() é apenas declarado
não existe nenhuma chamada em src/
```

Consequência:

- o zoom não modifica a seleção de overlays;
- os budgets por timeframe não chegam às primitives;
- o ranking não determina o que é exibido;
- o clustering não reduz zonas;
- `OVERVIEW`, `STANDARD` e `DETAIL` não afetam o gráfico;
- todo o comportamento V4 de visibilidade está inativo.

---

# P0.2 — Itens fora da viewport são comprimidos nas bordas

**Arquivo:** `src/components/chart/smc/smcRenderUtils.ts`

`xFromTimeClamp()` faz:

```text
tempo anterior à viewport → coordenada da borda esquerda
tempo posterior à viewport → coordenada da borda direita
```

Isso só seria seguro depois de confirmar que a zona cruza parcialmente a viewport.

Hoje, os renderers não executam `crossesWindow()` antes do clamp.

## Resultado

Uma zona completamente fora da viewport pode virar:

```text
x1 = borda esquerda
x2 = borda esquerda
```

ou:

```text
x1 = borda direita
x2 = borda direita
```

Depois de afastar o zoom e carregar/expor mais histórico, esses itens continuam nas primitives. Quando o zoom volta, eles ficam comprimidos nas extremidades e aparecem como:

- linhas verticais;
- pequenos traços empilhados;
- retângulos colapsados;
- zonas presas à borda;
- “sujeira” que parece permanente.

Esse comportamento é visível nos prints, especialmente próximo à borda direita.

---

# P0.3 — Os renderers recebem dados brutos e nunca são reselecionados no zoom

**Arquivo:** `src/components/CandlestickChart.tsx`

`updateSmcPrimitives()` chama:

```ts
primitive.setData(smcPerType.tipo)
```

Os arrays são normalizados, mas não filtrados pela viewport.

No handler de logical range, o código apenas:

```text
requestRedraw()
scheduleOverlay()
```

Não existe:

```text
recalcular detail level
selecionar visible items
reaplicar budgets
atualizar primitive data
```

Assim, o zoom apenas redesenha o mesmo conjunto bruto.

---

# P0.4 — Existem duas subscriptions de logical range

**Arquivo:** `src/components/CandlestickChart.tsx`

Subscription 1:

```text
linhas aproximadas 286–299
redraw + realtime
```

Subscription 2:

```text
linhas aproximadas 455–537
infinite scroll
```

O resumo anterior afirmava que a subscription havia sido consolidada, mas o código atual ainda possui duas.

## Riscos

- ordem de execução não controlada;
- redraw antes/depois do prepend;
- mais chamadas por gesto;
- dificuldade para coordenar detail level;
- possibilidade de carregar histórico enquanto o pipeline visual usa estado anterior;
- manutenção complexa.

---

# P0.5 — Budgets por timeframe não chegam aos renderers

`getBudgetsForTimeframe()` define limites por tipo, porém:

- `setOptions({ maxItems })` não é chamado por timeframe;
- primitives usam `DEFAULT_TYPE_OPTIONS.maxItems = 24`;
- renderers executam `.slice(0, this.options.maxItems)`;
- `maxLabels` do timeframe não é aplicado.

Consequência:

```text
D1, H4, H1, M15, M5 e M2
podem usar o mesmo maxItems real de 24
```

Os budgets declarados em `smcVisibility.ts` não controlam a renderização.

---

# P0.6 — `LabelPlacer` não é compartilhado globalmente

Todos os nove renderers criam instâncias próprias:

```ts
const placer = new LabelPlacer(...)
```

Exemplos:

```text
FvgRenderer
ObRenderer
BprRenderer
BosRenderer
ChochRenderer
LiquidityRenderer
PdhPdlRenderer
SessionRenderer
SwingRenderer
```

Consequência:

- OB evita colisão apenas com OB;
- FVG evita colisão apenas com FVG;
- BOS evita colisão apenas com BOS;
- OB pode colidir com FVG, BOS, CHOCH e LIQ;
- `reset()` não resolve, porque cada renderer cria nova instância em cada draw.

O `LabelPlacer.reset()` adicionado anteriormente não é chamado e não possui efeito prático.

---

# P0.7 — `findAlternativeY()` foi implementado, mas nunca é usado

`drawLabel()` executa:

```ts
if (!placer.canPlace(box)) return false
```

Ele não chama:

```ts
placer.findAlternativeY(...)
```

Portanto, a lógica de seis offsets alternativos está morta.

---

# 4. Outros erros confirmados

# P1.1 — `clusterZones()` mantém o item de prioridade errada

Em `priorityFor()`:

```text
OB = 0
FVG = 1
...
mitigado = 9
```

Menor número significa maior prioridade.

Mas `clusterZones()` ordena:

```ts
.sort((a, b) => b.priority - a.priority)
```

Isso coloca o maior número primeiro.

Depois mantém:

```ts
cluster[0]
```

Resultado: quando o clustering for conectado, pode manter o item menos importante ou mitigado e descartar o mais relevante.

## Correção

```ts
.sort((a, b) =>
  computeRankScore(b, currentPrice) -
  computeRankScore(a, currentPrice)
)
```

Ou preservar diretamente a lista `ranked` já ordenada e não reordenar por `priority`.

---

# P1.2 — Budget combinado é aplicado duas vezes

Em `applyTypeBudgets()`:

```text
BOS e CHOCH compartilham bosChoch
PDH e PDL compartilham pdhPdl
```

Mas o loop executa o mesmo limite para cada tipo.

Exemplo:

```text
bosChoch = 20
→ até 20 BOS
→ mais até 20 CHOCH
→ total possível = 40
```

O mesmo ocorre com PDH/PDL.

## Correção

Juntar os tipos, ordenar pelo score e aplicar um único `slice()` ao grupo.

---

# P1.3 — Detail level altera quase apenas estruturas

Em `selectVisible()`:

```text
OVERVIEW → structures.slice(0, 6)
DETAIL → nenhuma alteração
```

Os budgets de OB, FVG, BPR e LIQ não são reduzidos no overview.

Mesmo depois de conectar `selectVisible`, o zoom aberto ainda poderá exibir zonas demais.

## Correção

Definir multiplicadores:

| Nível | Multiplicador |
|---|---:|
| OVERVIEW | 0,25 |
| STANDARD | 0,60 |
| DETAIL | 1,00 |

E política de labels:

| Nível | Labels |
|---|---|
| OVERVIEW | somente BOS/CHOCH major |
| STANDARD | labels top rank |
| DETAIL | labels completos dentro do budget |

---

# P1.4 — Hysteresis possui estado global de módulo

```ts
let lastDetailLevel = 'STANDARD'
```

Esse estado é compartilhado por:

- todas as instâncias do chart;
- área do cliente;
- Replay;
- possíveis gráficos simultâneos.

## Correção

Mover para uma instância:

```ts
class SmcViewportController {
  private detailLevel: ChartDetailLevel
}
```

ou manter em ref dentro de `CandlestickChart`.

---

# P1.5 — `setLastCandle()` não recalcula os normalizers

`useSmcPerType` guarda o último candle em ref:

```ts
lastCandleRef.current = ts
```

O efeito de normalização depende somente de:

```text
zones
timeframe
```

Mudar a ref não dispara recálculo.

Em `updateSmcPrimitives()`:

```text
setLastCandle(lastCandle)
setData(smcPerType...)
```

O segundo comando ainda usa os arrays normalizados com o candle anterior.

## Correção

Remover state/effect desnecessário e usar `useMemo` com:

```text
zones
timeframe
lastCandleTime
```

Ou normalizar imperativamente no controller.

---

# P1.6 — `PdhPdlRenderer` ainda possui fallback de largura total

Se as coordenadas temporais não forem resolvidas:

```ts
x1 = 0
x2 = width
```

Isso recria linhas atravessando todo o gráfico.

## Correção

Se o intervalo não cruza a viewport ou as coordenadas não puderem ser calculadas:

```ts
continue
```

Não usar full-width fallback.

---

# P1.7 — `clampX()` permite coordenadas fora do canvas

```ts
Math.max(-100, Math.min(width + 100, x))
```

Embora exista clip do canvas, isso:

- aumenta áreas desenhadas;
- dificulta testes;
- permite artefatos nas bordas;
- não representa clipping real.

## Correção

Depois de validar overlap:

```ts
Math.max(0, Math.min(width, x))
```

---

# P1.8 — Histórico pode ser carregado apenas pelo zoom-out

`barsBefore < threshold` pode ser atingido por:

- pan para a esquerda;
- zoom-out;
- resize.

Isso pode carregar várias páginas enquanto o usuário apenas afasta o zoom.

Não é necessariamente errado, mas precisa ser controlado.

## Correção

Adicionar:

- debounce;
- cooldown;
- requestId;
- limite de uma página por gesto;
- reavaliação após apply;
- opção de prefetch, sem cascata automática.

---

# P1.9 — Erros de hooks e closures

O lint encontrou 45 erros e 11 avisos em `CandlestickChart.tsx`, incluindo diversas dependências ausentes em efeitos.

Isso aumenta o risco de:

- callbacks usando refs/props antigos;
- listeners com closures obsoletas;
- comportamento diferente após troca de timeframe;
- redraw com dados antigos.

---

# 5. Correção arquitetural recomendada

# 5.1 Criar `SmcViewportController`

Novo arquivo:

```text
src/components/chart/smc/SmcViewportController.ts
```

Responsabilidades:

```text
armazenar todos os itens normalizados
armazenar timeframe
armazenar preço atual
armazenar detail level por instância
receber visible logical/time range
selecionar itens visíveis
aplicar ranking
aplicar clustering
aplicar budgets
gerar snapshot por tipo
evitar updates idênticos
```

Interface:

```ts
interface SmcViewportSnapshot {
  detailLevel: ChartDetailLevel
  maxLabels: number

  fvg: RenderableSmcItem[]
  ob: RenderableSmcItem[]
  bpr: RenderableSmcItem[]
  bos: RenderableSmcItem[]
  choch: RenderableSmcItem[]
  liquidity: RenderableSmcItem[]
  swing: RenderableSmcItem[]
  pdhPdl: RenderableSmcItem[]
  session: RenderableSmcItem[]

  signature: string
}
```

---

# 5.2 Um pipeline único

```text
raw API zones
→ normalize once
→ store all normalized items
→ visible range changes
→ selectVisible
→ split by type
→ update primitives only if signature changed
```

O zoom não deve renormalizar dados brutos.

O zoom deve apenas reselecionar o subconjunto visível.

---

# 5.3 Um único handler de viewport

Substituir as duas subscriptions por uma:

```ts
function handleLogicalRangeChange(range) {
  updateRealtimeState(range)
  scheduleSmcViewportRefresh(range)
  maybeLoadHistory(range)
  scheduleLegacyOverlay()
}
```

Usar `requestAnimationFrame` para agrupar múltiplos eventos do mesmo gesto.

---

# 6. Hotfix obrigatório — impedir artefatos nas bordas

Antes da refatoração completa, aplicar imediatamente em todos os renderers:

```ts
const visible = chart.timeScale().getVisibleRange()
if (!visible) return

const vf = Number(visible.from)
const vt = Number(visible.to)

if (item.toTime < vf || item.fromTime > vt) {
  continue
}
```

Somente depois calcular:

```ts
xFromTimeClamp(...)
```

## Regra

```text
item completamente fora → não desenhar
item parcialmente dentro → cortar à borda
item completamente dentro → desenhar normal
```

---

# 7. Substituir `xFromTimeClamp()` por clipping explícito

Criar:

```ts
interface ClippedTimeRange {
  from: UTCTimestamp
  to: UTCTimestamp
}

function clipItemTimeToViewport(
  itemFrom,
  itemTo,
  visibleFrom,
  visibleTo,
): ClippedTimeRange | null
```

Implementação:

```ts
if (itemTo < visibleFrom) return null
if (itemFrom > visibleTo) return null

return {
  from: Math.max(itemFrom, visibleFrom),
  to: Math.min(itemTo, visibleTo),
}
```

Depois converter apenas os tempos cortados.

Remover o comportamento de comprimir qualquer tempo externo na borda sem verificar overlap.

---

# 8. Seleção por viewport

No refresh:

```ts
const logicalRange =
  chart.timeScale().getVisibleLogicalRange()

const timeRange =
  chart.timeScale().getVisibleRange()

const barsVisible =
  logicalRange
    ? Math.max(0, logicalRange.to - logicalRange.from)
    : undefined

const currentPrice =
  candleDataRef.current.at(-1)?.close ?? null
```

Chamar:

```ts
selectVisible(
  allNormalizedItems,
  rendererOptions,
  visibleFrom,
  visibleTo,
  barsVisible,
  currentPrice,
  timeframe,
)
```

---

# 9. Corrigir `selectVisible()`

## 9.1 Ordenação

Não reordenar clusters por `priority` invertida.

Usar score explícito:

```ts
interface RankedItem {
  item: RenderableSmcItem
  score: number
}
```

## 9.2 Budgets agrupados

```ts
takeGroup(['BOS', 'CHOCH'], budgets.bosChoch)
takeGroup(['PDH', 'PDL'], budgets.pdhPdl)
```

## 9.3 Detail multipliers

```ts
const multiplier = {
  OVERVIEW: 0.25,
  STANDARD: 0.60,
  DETAIL: 1.00,
}[detailLevel]
```

## 9.4 Overview

Ocultar:

- swings;
- sessions;
- FVG pequenos;
- labels de zonas;
- estruturas internas de baixa prioridade.

## 9.5 Determinismo

Desempate:

```text
score DESC
fromTime DESC
id ASC
```

A mesma viewport deve produzir a mesma seleção.

---

# 10. Labels

# 10.1 Solução definitiva

Criar uma única primitive composta:

```text
SmcCompositePrimitive
SmcCompositeRenderer
```

Ela deve desenhar todos os tipos em ordem controlada:

```text
sessions
zonas mitigadas
zonas ativas
liquidez
estruturas
labels
```

Somente uma primitive composta consegue compartilhar de forma confiável:

```text
LabelPlacer
label budget
z-order
frame lifecycle
```

## 10.2 Migração

Manter as nove primitives durante a transição atrás de feature flag:

```ts
VITE_SMC_COMPOSITE_RENDERER=true
```

Depois remover as antigas.

## 10.3 Uso do alternative Y

`drawLabel()` deve:

```ts
if (placer.canPlace(box)) {
  draw
} else {
  placer.findAlternativeY(...)
}
```

Se não encontrar posição:

```text
ocultar label
```

---

# 11. Evitar estado residual após zoom

Criar assinatura de viewport:

```text
timeframe
detailLevel
visibleFromBucket
visibleToBucket
currentPriceBucket
zonesRevision
presetHash
```

Quando a assinatura mudar:

1. gerar novo snapshot;
2. substituir integralmente os arrays selecionados;
3. não concatenar com seleção anterior;
4. solicitar redraw;
5. limpar estado de labels do frame.

Nunca acumular resultado de seleção anterior.

---

# 12. Proteção contra histórico acionado por zoom

Adicionar estado:

```ts
lastHistoryLoadAt
historyCooldownMs
lastRequestedBefore
```

Regras:

- uma requisição por vez;
- não repetir o mesmo cursor;
- cooldown de 300–500 ms;
- após prepend, aguardar próximo gesto;
- não carregar em loop dentro do mesmo frame;
- registrar origem do gatilho quando possível.

---

# 13. Fases de execução

# Fase 0 — Instrumentação

Adicionar logs temporários:

```text
[smc:viewport]
[smc:detail-change]
[smc:selection]
[smc:edge-skip]
[smc:primitive-update]
[history:trigger]
```

Campos:

```text
timeframe
barsVisible
detailLevel
visibleFrom
visibleTo
rawCount
visibleCount
selectedCount
signature
```

---

# Fase 1 — Hotfix de overlap

1. adicionar filtro de overlap em todos os renderers;
2. remover full-width fallback de PDH/PDL;
3. tornar clamp estrito;
4. testar zoom out/in.

**Gate:** nenhum item completamente externo aparece na borda.

---

# Fase 2 — Conectar `selectVisible()`

1. criar ref com itens normalizados completos;
2. calcular viewport;
3. chamar `selectVisible`;
4. dividir por tipo;
5. setData nas primitives;
6. aplicar somente quando signature mudar.

**Gate:** budgets e detail level alteram a quantidade real.

---

# Fase 3 — Handler único

1. remover segunda subscription;
2. incorporar infinite scroll;
3. usar RAF;
4. limpar no unmount.

**Gate:** um callback por mudança lógica.

---

# Fase 4 — Corrigir seleção

1. score;
2. clustering;
3. budgets agrupados;
4. detail multipliers;
5. determinismo.

**Gate:** seleção igual para viewport igual.

---

# Fase 5 — Corrigir `useSmcPerType`

1. substituir state/effect por `useMemo`;
2. incluir `lastCandleTime`;
3. remover atualização por ref sem recomputação;
4. evitar render extra.

**Gate:** zona ativa acompanha o último candle sem depender de nova resposta de zonas.

---

# Fase 6 — Composite renderer

1. criar primitive única;
2. compartilhar LabelPlacer;
3. usar alternative Y;
4. controlar z-order;
5. remover instâncias locais.

**Gate:** labels de tipos diferentes não colidem.

---

# Fase 7 — Performance

1. cache por signature;
2. não recalcular no crosshair;
3. medir draw;
4. evitar novos arrays desnecessários;
5. limitar updates por RAF.

---

# 14. Testes automatizados

# 14.1 Viewport clipping

- item totalmente à esquerda;
- item totalmente à direita;
- item cruzando esquerda;
- item cruzando direita;
- item dentro;
- item instantâneo;
- range nulo.

# 14.2 Zoom reversível

```text
DETAIL
→ OVERVIEW
→ DETAIL
```

A seleção final deve ser idêntica à seleção DETAIL inicial para a mesma viewport.

# 14.3 Clustering

- prioridade correta;
- mesmo tipo;
- tipos diferentes;
- empate;
- mitigado versus ativo.

# 14.4 Budget combinado

- BOS + CHOCH não excedem limite total;
- PDH + PDL não excedem limite total.

# 14.5 Labels

- colisão entre tipos;
- alternative Y;
- limite global;
- reset por frame.

# 14.6 History trigger

- zoom-out não causa loop;
- pan dispara uma página;
- mesmo cursor não repete;
- prepend não aciona cascata.

---

# 15. Teste manual principal

Executar em M5:

1. abrir no zoom normal;
2. capturar screenshot;
3. afastar até overview;
4. capturar screenshot;
5. aproximar exatamente à região inicial;
6. capturar screenshot;
7. comparar seleção, labels e zonas;
8. repetir cinco vezes;
9. confirmar ausência de itens na borda;
10. repetir em M15 e H1.

## Aceite

A imagem final deve ser visualmente equivalente à inicial, considerando apenas novos candles live.

---

# 16. Critérios de aceite

- [ ] `selectVisible()` é chamado.
- [ ] `crossesWindow()` ou clipping equivalente é aplicado.
- [ ] Item fora da viewport não é desenhado.
- [ ] Nenhum renderer usa full-width fallback.
- [ ] Budgets por timeframe são efetivos.
- [ ] Budgets por zoom são efetivos.
- [ ] `maxLabels` é efetivo.
- [ ] Existe uma única subscription lógica.
- [ ] Zoom out/in é reversível.
- [ ] Não há estado visual residual.
- [ ] Clustering mantém a maior relevância.
- [ ] BOS/CHOCH respeitam limite combinado.
- [ ] PDH/PDL respeitam limite combinado.
- [ ] LabelPlacer é global por frame.
- [ ] `findAlternativeY()` é usado.
- [ ] `setLastCandle()` causa recomputação real.
- [ ] Infinite scroll não entra em cascata.
- [ ] Pan e zoom permanecem fluidos.
- [ ] Build passa.
- [ ] Lint dos arquivos do gráfico passa.
- [ ] Testes passam.
- [ ] Console não contém erros.

---

# 17. Gates

```text
GATE_OVERLAP_FILTER: PENDENTE
GATE_VIEWPORT_CLIPPING: PENDENTE
GATE_SELECT_VISIBLE_CONNECTED: PENDENTE
GATE_SINGLE_RANGE_HANDLER: PENDENTE
GATE_BUDGETS_EFFECTIVE: PENDENTE
GATE_DETAIL_REVERSIBLE: PENDENTE
GATE_CLUSTER_PRIORITY: PENDENTE
GATE_GLOBAL_LABELS: PENDENTE
GATE_HISTORY_NO_CASCADE: PENDENTE
GATE_ZOOM_M5: PENDENTE
GATE_ZOOM_M15: PENDENTE
GATE_ZOOM_H1: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 18. Ordem recomendada

```text
1. filtro de overlap
2. remover full-width fallback
3. conectar selectVisible
4. consolidar handler
5. corrigir clustering/budgets
6. corrigir useSmcPerType
7. composite renderer
8. testes de reversibilidade
9. performance
10. reauditoria
```

---

# 19. Conclusão

O bug observado não ocorre porque o Lightweight Charts “não volta o zoom”.

Ele ocorre porque a camada SMC:

```text
mantém itens brutos
+ não aplica a política de viewport
+ comprime itens externos nas bordas
+ não limpa/reseleciona ao mudar o zoom
```

A correção prioritária é:

```text
não desenhar itens fora da viewport
e conectar selectVisible() ao handler de zoom
```

Depois disso, aproximar novamente deverá restaurar uma visualização limpa e equivalente à inicial.
