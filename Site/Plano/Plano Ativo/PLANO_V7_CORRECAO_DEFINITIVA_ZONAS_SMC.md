# PLANO V7 — CORREÇÃO DEFINITIVA DAS ZONAS SMC

**Projeto:** Maximus Trade  
**Data da auditoria:** 26/06/2026  
**Fonte auditada:** `frontend(2).zip`  
**Imagem analisada:** gráfico WINFUT M2 após navegação histórica  
**Biblioteca:** TradingView Lightweight Charts 5.2.0  
**Build:** `PASS`  
**Lint do escopo do gráfico:** `FAIL — 63 erros e 13 avisos`  
**Status:** `V6_PARCIALMENTE_IMPLEMENTADO / ZONAS_REPROVADAS / PRODUÇÃO_NO_GO`

---

# 1. Objetivo

Corrigir definitivamente a camada SMC para que:

1. nenhuma zona seja removida por limite de quantidade;
2. todas as zonas válidas e carregadas permaneçam disponíveis;
3. todas as zonas que cruzem a viewport sejam desenhadas;
4. zoom e pan nunca alterem o conjunto armazenado;
5. timestamps sem correspondência exata com candles não sejam jogados na borda direita;
6. zonas não virem pequenos traços verticais;
7. IDs iguais de tabelas diferentes não eliminem zonas;
8. zonas ativas acompanhem o candle atual;
9. histórico de candles e histórico de zonas permaneçam sincronizados;
10. presets e estados mitigados funcionem de forma explícita;
11. o limite de zoom opere apenas sobre candles visíveis;
12. o resultado seja reversível e determinístico.

---

# 2. Resultado executivo da auditoria

O V6 não foi concluído integralmente.

## Implementado

- `minBarSpacing`;
- `chartDisplayConfig`;
- `SmcViewportController` simplificado;
- clipping básico por viewport;
- `clampX()` estrito;
- configuração declarando `limitZoneCount: false`.

## Não implementado ou ainda incorreto

```text
maxItems ainda limita todos os renderers
PDH/PDL ainda possuem limite por timeframe
zoom continua chamando primitive.setData()
lastCandleTime não chega aos normalizers
dedupe usa somente id numérico
xFromTimeClamp envia timestamps sem candle para a borda direita
clipItemTimeToViewport existe, mas não é usado
histórico não carrega zonas antigas
showMitigated é ignorado
flags V6 não são consumidas
```

---

# 3. Bugs confirmados no código

# P0.1 — Todos os nove renderers ainda limitam zonas

**Arquivo:** `src/components/chart/smc/renderers/*Renderer.ts`

Todos contêm uma variação de:

```ts
.slice(0, this.options.maxItems)
```

`DEFAULT_TYPE_OPTIONS.maxItems` continua definido como:

```ts
maxItems: 24
```

Arquivos afetados:

```text
BosRenderer.ts
BprRenderer.ts
ChochRenderer.ts
FvgRenderer.ts
LiquidityRenderer.ts
ObRenderer.ts
PdhPdlRenderer.ts
SessionRenderer.ts
SwingRenderer.ts
```

## Consequência

- somente os primeiros 24 itens de cada tipo são considerados;
- a ordem depende da resposta da API;
- zonas recentes podem desaparecer;
- zonas antigas podem permanecer;
- mudar o zoom não recupera itens cortados;
- a regra “todas as zonas” não está funcionando.

## Correção

Remover completamente os `slice()` da geometria.

```ts
const items = this.items.filter(...)
```

Remover `maxItems` de:

```text
SmcTypeOptions
DEFAULT_TYPE_OPTIONS
setOptions
renderers
```

---

# P0.2 — PDH/PDL continuam limitados e D1 continua desligado

**Arquivo:** `pdhPdlNormalizer.ts`

Ainda existe:

```ts
return items.slice(0, maxCount)
```

Além disso:

```text
D1 = 0
H1/H4 = 5
M5/M15 = 6
M2 = 2
```

Isso contradiz a decisão V6 de não limitar por quantidade.

## Correção

O normalizer deve retornar todos os níveis válidos:

```ts
return items.sort(
  (a, b) =>
    Number(a.fromTime) - Number(b.fromTime),
)
```

O preset pode ligar ou desligar PDH/PDL, mas não truncar a quantidade.

O lifecycle de 24 horas já limita naturalmente quais níveis cruzam uma viewport específica.

---

# P0.3 — Timestamps internos são enviados incorretamente para a borda direita

**Arquivo:** `smcRenderUtils.ts`

Fluxo atual:

```ts
timeToCoordinate(time)
```

Se retornar `null`, `xFromTimeClamp()` faz:

```text
time < visibleFrom
  → borda esquerda

qualquer outro caso
  → borda direita
```

## Problema

`timeToCoordinate()` pode retornar `null` quando o timestamp:

- não coincide exatamente com um candle;
- está entre dois candles;
- cai em gap de sessão;
- está deslocado por timezone;
- representa mitigação/evento sem candle exato.

Mesmo que o timestamp esteja no meio da viewport, ele é transformado na borda direita.

## Evidência visual

Os pequenos segmentos verticais alinhados à direita do gráfico são compatíveis com:

```text
x1 ou x2 nulo
→ fallback para borda direita
→ zona com largura mínima de 1 px
→ traço vertical
```

## Correção

Criar um resolvedor semântico:

```ts
export function xFromTimeNearest(
  chart: IChartApi,
  time: UTCTimestamp,
): number | null {
  const timeScale = chart.timeScale()

  const direct =
    timeScale.timeToCoordinate(time as Time)

  if (direct !== null) {
    return Number(direct)
  }

  const logicalIndex =
    timeScale.timeToIndex(time as Time, true)

  if (logicalIndex === null) {
    return null
  }

  const coordinate =
    timeScale.logicalToCoordinate(logicalIndex)

  return coordinate === null
    ? null
    : Number(coordinate)
}
```

## Regra

Não mapear automaticamente qualquer `null` para a borda direita.

Primeiro:

1. cortar o intervalo à viewport;
2. procurar coordenada direta;
3. procurar índice lógico mais próximo;
4. se ainda não resolver, não desenhar e registrar.

---

# P0.4 — `clipItemTimeToViewport()` foi criado, mas não é usado

A função existe apenas em `smcRenderUtils.ts`.

Nenhum renderer a chama.

Hoje os renderers:

1. verificam overlap manualmente;
2. usam tempos originais;
3. chamam `xFromTimeClamp()`.

## Correção

Todos os renderers devem usar:

```ts
const clipped =
  clipItemTimeToViewport(
    item.fromTime,
    item.toTime,
    visibleFrom,
    visibleTo,
  )

if (!clipped) {
  continue
}

const x1 = xFromTimeNearest(
  chart,
  clipped.from,
)

const x2 = xFromTimeNearest(
  chart,
  clipped.to,
)

if (x1 === null || x2 === null) {
  continue
}
```

Eliminar `xFromTimeClamp()` do pipeline normal.

---

# P0.5 — Zonas com duração nula viram barras verticais

FVG, OB e BPR fazem:

```ts
const width =
  Math.max(Math.abs(x2 - x1), 1)
```

Se:

```text
fromTime === toTime
```

ou ambos mapearem para o mesmo candle, o renderer cria um retângulo de 1 px.

Zonas mitigadas ainda desenham borda direita, resultando em um traço vertical.

## Correção

No normalizer:

```ts
if (
  item.kind === 'zone' &&
  Number(toTime) <= Number(fromTime)
) {
  return null
}
```

Ou, se a regra do engine garantir que é uma zona válida, corrigir o endpoint no backend.

Não inventar largura visual de 1 px para uma zona temporal inválida.

## Métrica

Registrar:

```text
invalidZeroDurationZoneCount
```

---

# P0.6 — Dedupe global por `id` descarta tipos diferentes

**Arquivos:**

```text
useSmcPerType.ts
smcNormalize.ts
```

Código atual:

```ts
const seen = new Set<number>()

if (seen.has(zone.id)) {
  return false
}
```

## Problema

FVG, OB, BOS, CHOCH e outras entidades podem vir de tabelas diferentes e possuir o mesmo ID numérico.

Exemplo:

```text
FVG id=12
OB id=12
BOS id=12
```

Somente o primeiro permanece.

Isso remove zonas antes mesmo dos normalizers.

## Correção

Criar identidade composta:

```ts
function getZoneIdentity(
  zone: ApiZone,
): string {
  const type = String(
    zone.zone_type ??
    zone.type ??
    'UNKNOWN'
  ).toUpperCase()

  return [
    type,
    zone.id,
    zone.timeframe,
    zone.created_at_candle ?? '',
    zone.display_from ?? '',
  ].join(':')
}
```

## Melhor solução de backend

A API deve retornar:

```json
{
  "source_key": "smc_v2_fvg:123",
  "source_type": "FVG",
  "source_id": 123,
  "run_id": "..."
}
```

O frontend deve usar `source_key`.

---

# P0.7 — Zonas ativas não recebem o candle atual

**Arquivo:** `useSmcPerType.ts`

O hook contém:

```ts
const lastCandleRef = useRef(null)

const setLastCandle = (...) => {
  lastCandleRef.current = ts
}
```

Mas `setLastCandle()` não é chamado em nenhum local do projeto.

Além disso, mudar uma ref não executaria novamente o efeito.

## Consequência

Para zonas sem `display_to`, o normalizer não recebe o candle atual e utiliza fallback:

```text
fromTime + 1 hora
```

Isso gera:

- zonas terminando cedo;
- barras verticais;
- zonas curtas demais;
- diferenças entre timeframe e live.

## Correção

Alterar a assinatura:

```ts
useSmcPerType(
  zones,
  timeframe,
  lastCandleTime,
)
```

Usar `useMemo`:

```ts
const data = useMemo(
  () => normalizeAll(
    zones,
    {
      timeframe,
      lastCandleTime,
    },
  ),
  [
    zones,
    timeframe,
    lastCandleTime,
  ],
)
```

Remover:

```text
lastCandleRef
setLastCandle
state derivado
effect de normalização
```

No chart:

```ts
const lastCandleTime =
  candleDataRef.current.at(-1)?.time ??
  effectiveData.lwCandles.at(-1)?.time ??
  null
```

---

# P0.8 — O zoom continua reaplicando todos os dados SMC

**Arquivo:** `CandlestickChart.tsx`

No handler:

```ts
handleLogicalRangeChange()
  → updateSmcPrimitives()
```

`updateSmcPrimitives()`:

```text
cria novo array
controller.setItems()
incrementa revision
gera snapshot
primitive.setData() em 9 primitives
```

Isso acontece em cada evento de pan/zoom.

## Consequência

- reconstrução desnecessária;
- múltiplos redraws;
- flicker;
- estado visual transitório;
- alto custo;
- risco de ordem diferente entre frames;
- dificuldade para tornar o zoom reversível.

## Correção

O zoom deve apenas:

```ts
for (const primitive of primitives) {
  primitive.requestRedraw()
}
```

`setData()` deve ocorrer somente quando:

- zonas mudam;
- lifecycle muda;
- último candle relevante muda;
- ativo muda;
- timeframe muda;
- histórico de zonas é mesclado;
- Replay muda o snapshot.

---

# P0.9 — A API carrega somente um lote de zonas

**Arquivo:** `useRealMarketData.ts`

Carga atual:

```http
GET /api/zones/{ticker}
?timeframe=...
&limit=1500
```

O infinite scroll carrega candles anteriores, mas não carrega zonas do mesmo período.

## Consequência

- candles históricos aparecem;
- zonas antigas correspondentes não aparecem;
- “todas as zonas” significa apenas as últimas 1.500 da carga inicial;
- gráfico fica inconsistente ao navegar no passado.

## Correção de backend

Criar paginação temporal de zonas:

```http
GET /api/zones/{ticker}
?timeframe=2min
&from=<timestamp>
&to=<timestamp>
&include_mitigated=1
&include_structure=1
```

ou cursor:

```http
GET /api/zones/{ticker}
?timeframe=2min
&before=<oldest_zone_time>
&limit=1000
```

## Correção de frontend

`HistoricalSeriesPage` deve incluir:

```ts
zones: ApiZone[]
```

No prepend:

```text
merge candles
merge indicadores
merge zonas por source_key
normalizar
setData das primitives
restaurar range
```

---

# P1.1 — `showMitigated` é ignorado

`SmcTypeOptions.showMitigated` existe, mas os renderers não o usam.

FVG, OB e BPR desenham mitigadas mesmo quando:

```ts
showMitigated: false
```

## Correção

```ts
const items =
  this.items.filter(item =>
    this.options.showMitigated ||
    item.status !== 'mitigated'
  )
```

Isso é controle de estado, não limite de quantidade.

---

# P1.2 — Flags V6 não possuem efeito

Configuração:

```ts
limitZoneCount: false
renderAllIntersectingZones: true
clusterGeometry: false
```

Não é consumida por nenhum arquivo.

## Correção

Ou:

1. integrar as flags de forma real; ou
2. remover as flags e codificar uma única política oficial.

Evitar configurações decorativas.

---

# P1.3 — `SmcTemporalIndex` está sem uso e possui busca incorreta

O índice é ordenado por:

```text
fromTime
```

Mas a busca binária compara:

```text
toTime
```

`toTime` não é monotônico na lista ordenada por `fromTime`.

Isso pode pular zonas longas.

## Correção

Opções:

### Simples

Não usar índice enquanto a quantidade for administrável. Filtrar todas as zonas por overlap.

### Correta

Implementar interval tree ou:

- lista ordenada por `fromTime`;
- buscar primeiro `fromTime <= visibleTo`;
- filtrar candidatos por `toTime >= visibleFrom`.

Não executar binary search em `toTime` numa lista ordenada por `fromTime`.

---

# P1.4 — Timestamp SMC não usa o parser canônico

`normalizerUtils.ts` e `smcRenderUtils.ts` removem `Z`:

```ts
.replace(/Z$/, '')
```

Isso altera a semântica UTC.

## Consequência

- zonas podem deslocar algumas horas;
- timestamp deixa de coincidir com candles;
- `timeToCoordinate()` retorna `null`;
- item vai para a borda;
- label aparece em candle errado.

## Correção

Usar apenas:

```ts
parseMarketTimestamp()
```

Remover todos os parsers duplicados.

## Contrato

Definir explicitamente se strings sem offset são:

```text
UTC
ou
America/Sao_Paulo
```

Não depender do timezone do navegador.

---

# P1.5 — O limite de zoom V6 ainda não está completo

Foi implementado:

```text
minBarSpacing
```

Mas não foi implementado o guard secundário de logical range.

## Consequência

Mudanças programáticas, prepend e resize ainda podem ultrapassar o máximo desejado.

## Correção

Implementar `enforceMaxVisibleBars()` com proteção anti-loop e sem disparar histórico durante correção.

---

# 4. Arquitetura final recomendada

```text
API zones
  → source_key estável
  → merge sem colisão
  → normalização com lastCandleTime
  → todas as zonas armazenadas por tipo
  → primitive.setData somente quando dados mudam

zoom/pan
  → limite de candles
  → primitive.requestRedraw
  → renderer consulta viewport
  → clip temporal
  → resolve coordenada direta ou índice mais próximo
  → desenha todas as zonas intersectantes
```

---

# 5. Alterações por arquivo

## `smcTypes.ts`

- remover `maxItems`;
- manter:
  - visible;
  - showMitigated;
  - showLabels;
- adicionar `sourceKey` em `RenderableSmcItem`;
- adicionar métricas opcionais de validação.

## `smcRenderUtils.ts`

- remover `xFromTimeClamp`;
- implementar `xFromTimeNearest`;
- usar `clipItemTimeToViewport`;
- mover `findAlternativeY` para dentro da classe;
- unificar timestamp com `parseMarketTimestamp`.

## Nove renderers

- remover todos os `slice()`;
- aplicar `showMitigated`;
- usar clipping real;
- usar coordenada nearest;
- rejeitar zona com duração nula;
- manter geometria independente de labels.

## `useSmcPerType.ts`

- remover state/effect derivado;
- remover dedupe por ID simples;
- receber `lastCandleTime`;
- usar `useMemo`;
- retornar arrays determinísticos.

## `smcNormalize.ts`

- usar identidade composta;
- remover `dedupeById`;
- compartilhar helper `getZoneIdentity`.

## `CandlestickChart.tsx`

- não chamar `setData()` de zones no zoom;
- range handler chama apenas redraw;
- aplicar dados em efeito próprio;
- passar `lastCandleTime`;
- implementar guard do zoom;
- sincronizar zonas históricas.

## `useRealMarketData.ts`

- adicionar source key;
- paginar zonas;
- mesclar zonas históricas;
- atualizar zonas live quando necessário;
- remover limite fixo como única fonte.

## `pdhPdlNormalizer.ts`

- remover limite de quantidade;
- manter lifecycle temporal;
- deixar presets controlarem visibilidade.

## Backend

- retornar `source_key`;
- adicionar `run_id`;
- permitir consulta de zonas por intervalo/cursor;
- garantir timestamps no mesmo timezone dos candles.

---

# 6. Ordem de execução

```text
1. remover maxItems e slice
2. corrigir dedupe por source_key
3. passar lastCandleTime corretamente
4. corrigir xFromTimeNearest
5. usar clipping real
6. rejeitar zonas de duração nula
7. separar setData de redraw
8. aplicar showMitigated
9. unificar timestamps
10. carregar zonas históricas
11. completar limite do zoom
12. testar todos os timeframes
```

---

# 7. Testes automatizados

## Identidade

- IDs iguais em tipos diferentes;
- IDs iguais em runs diferentes;
- source key única;
- nenhuma zona perdida.

## Coordenadas

- timestamp exato;
- timestamp entre candles;
- timestamp em gap de sessão;
- timestamp antes da viewport;
- timestamp depois da viewport;
- timestamp UTC com Z;
- timestamp sem offset.

## Zonas

- todas as 100 zonas preservadas;
- todas as 100 zonas entregues ao renderer;
- somente as intersectantes desenhadas;
- zero-duration rejeitada;
- mitigada respeita toggle;
- ativa acompanha o último candle.

## Zoom

- zoom não chama `setData`;
- zoom chama redraw;
- voltar ao mesmo range desenha os mesmos IDs;
- máximo de candles respeitado;
- correção não dispara histórico.

## Histórico

- candles e zonas são carregados juntos;
- merge não duplica;
- zonas antigas aparecem no pan;
- troca de timeframe descarta resposta antiga.

---

# 8. Teste manual obrigatório

## M2

1. abrir o gráfico;
2. registrar quantidade total por tipo;
3. aproximar;
4. afastar;
5. voltar ao range original;
6. confirmar mesmos IDs;
7. navegar para esquerda;
8. carregar histórico;
9. confirmar novas zonas históricas;
10. confirmar ausência de traços verticais na borda.

## Repetir

```text
M5
M15
H1
H4
D1
```

---

# 9. Métricas temporárias

```text
zonesApiCount
zonesAfterDedupe
zonesNormalized
zonesByType
zonesSentToPrimitive
zonesIntersectingViewport
zonesDrawn
zonesSkippedOutOfViewport
zonesSkippedInvalidDuration
coordinateDirectHits
coordinateNearestHits
coordinateFailures
primitiveSetDataCount
primitiveRedrawCount
```

---

# 10. Critérios de aceite

- [ ] Nenhum renderer possui `slice(maxItems)`.
- [ ] `maxItems` foi removido do contrato.
- [ ] PDH/PDL não são truncados por quantidade.
- [ ] IDs iguais entre tipos não removem zonas.
- [ ] Source key é estável.
- [ ] Último candle chega ao normalizer.
- [ ] Zona ativa acompanha o presente.
- [ ] Timestamp interno não vai para a borda direita.
- [ ] Não existem traços verticais artificiais.
- [ ] Zona de duração nula é rejeitada.
- [ ] Zoom não chama `primitive.setData()`.
- [ ] Todas as zonas intersectantes são desenhadas.
- [ ] Zonas externas permanecem armazenadas.
- [ ] `showMitigated` funciona.
- [ ] Timestamps usam parser único.
- [ ] Histórico carrega zonas e candles.
- [ ] Limite máximo de candles funciona.
- [ ] M2 passa.
- [ ] M5 passa.
- [ ] M15 passa.
- [ ] H1 passa.
- [ ] H4 passa.
- [ ] D1 passa.
- [ ] Build passa.
- [ ] Lint do gráfico passa.
- [ ] Testes passam.
- [ ] Console permanece limpo.

---

# 11. Gates

```text
GATE_REMOVE_MAX_ITEMS: PENDENTE
GATE_COMPOSITE_ZONE_KEY: PENDENTE
GATE_LAST_CANDLE_NORMALIZATION: PENDENTE
GATE_TIME_TO_X_NEAREST: PENDENTE
GATE_ZERO_DURATION: PENDENTE
GATE_DATA_UPDATE_SEPARATED_FROM_REDRAW: PENDENTE
GATE_HISTORICAL_ZONES: PENDENTE
GATE_TIMEZONE_CANONICAL: PENDENTE
GATE_ZOOM_LIMIT: PENDENTE
GATE_VISUAL_ALL_TIMEFRAMES: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 12. Conclusão

O bug ainda existe porque a política oficial declarada no V6 não corresponde ao código executado.

Os quatro defeitos mais importantes são:

```text
1. renderers ainda cortam em 24 itens
2. IDs numéricos eliminam zonas de outros tipos
3. timestamps sem candle exato são enviados à borda direita
4. zonas ativas não recebem lastCandleTime
```

A correção desses quatro pontos deve preceder qualquer nova alteração visual.
