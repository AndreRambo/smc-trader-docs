# Plano Executivo Auditado — Correção do Carregamento Dinâmico de Candles

**Projeto:** gráfico React com TradingView Lightweight Charts  
**Documento:** versão 2, revisada após auditoria do código-fonte recebido  
**Data da auditoria:** 26/06/2026  
**Fonte auditada:** `src.zip`  
**Arquivo principal analisado:** `CandlestickChart.tsx`

---

# 1. Objetivo

Corrigir e estabilizar o gráfico para que ele:

1. carregue o snapshot inicial uma única vez;
2. atualize o candle aberto com `series.update()`;
3. adicione candles novos sem reconstruir a série;
4. atualize EMA 20, EMA 200 e RSI junto com os candles;
5. carregue histórico ao fazer pan para a esquerda;
6. aplique efetivamente os candles históricos ao gráfico;
7. preserve a posição visual quando candles antigos forem inseridos;
8. continue recebendo live enquanto o usuário analisa o histórico;
9. não volte automaticamente ao presente durante análise histórica;
10. permita retornar ao presente pelo botão **Voltar ao vivo**;
11. não produza candles duplicados, séries desalinhadas, flicker ou saltos;
12. não misture respostas ao trocar ativo, timeframe ou modo;
13. funcione corretamente em React Strict Mode;
14. mantenha o modo Replay isolado do fluxo live.

---

# 2. Escopo efetivamente auditado

O arquivo compactado contém 44 arquivos, incluindo:

- `CandlestickChart.tsx`;
- `PlotlyCandlestickChart.tsx`;
- `BackgroundEffects.tsx`;
- controles de Replay;
- normalizadores SMC;
- primitives e renderers SMC.

## 2.1 Arquivos ausentes da auditoria

Os seguintes arquivos, citados pelo componente, não foram incluídos no pacote:

- `hooks/useRealMarketData.ts`;
- `hooks/useSmcPerType.ts`;
- cliente HTTP da API;
- tipos das respostas da API;
- `package.json`;
- lockfile;
- configuração TypeScript;
- testes;
- backend dos endpoints de candles.

Consequentemente, não foi possível confirmar diretamente:

- como o polling de 10 segundos está implementado;
- se existe sobreposição de requests;
- como `loadOlder()` busca, ordena e mescla histórico;
- se há `AbortController`;
- se respostas antigas são descartadas;
- qual é a versão exata instalada do `lightweight-charts`;
- se o hook deduplica candles;
- se o hook retorna lotes históricos ou apenas modifica arrays internos;
- se o build completo passa.

## 2.2 Verificação sintática

Foi executada uma transpilação sintática dos arquivos `.ts` e `.tsx` fornecidos com TypeScript 5.8.3.

**Resultado:** nenhum erro sintático nos arquivos enviados.

Isso não substitui:

- type-check completo;
- resolução de imports;
- build Vite;
- lint;
- testes;
- execução no navegador.

---

# 3. Resultado executivo da auditoria

## 3.1 Estado geral

**Status:** `IMPLEMENTAÇÃO_PARCIAL_COM_DEFEITOS_CRÍTICOS`

O código já implementou corretamente parte do plano anterior, mas o carregamento histórico ainda não está completo e existem riscos de dessincronização entre:

- os candles exibidos;
- os arrays mantidos pelo hook;
- as referências internas do componente;
- EMA 20;
- EMA 200;
- RSI;
- Replay;
- overlays.

## 3.2 Matriz de conformidade

| Requisito | Estado | Evidência no código | Diagnóstico |
|---|---|---|---|
| `shiftVisibleRangeOnNewBar: true` | Concluído | `CandlestickChart.tsx`, linha aproximada 605 | Correto |
| Pan nativo | Concluído | linhas 606–611 | Correto |
| Zoom nativo | Concluído | linhas 612–616 | Correto |
| Remoção de zoom limit | Concluído no arquivo enviado | não há clamp ou efeito de zoom limit | Manter assim |
| `setData()` na carga inicial | Parcial | linhas 692–729 | Há risco de corrida no reset |
| `update()` para candles live | Parcial | linhas 737–757 | Sem normalização, ordenação ou deduplicação |
| `barsInLogicalRange()` | Concluído | linhas 760–793 | A detecção existe |
| Chamar `loadOlder()` | Parcial | linhas 784–787 | Chama, mas não aplica explicitamente o resultado |
| Aplicar prepend histórico | Ausente | nenhuma rotina de merge + `setData()` | Defeito crítico |
| Preservar posição no prepend | Ausente | nenhuma captura/restauração de range | Defeito crítico |
| Atualizar EMA 20 live | Ausente | EMA recebe apenas `setData()` inicial | Série pode congelar |
| Atualizar EMA 200 live | Ausente | EMA recebe apenas `setData()` inicial | Série pode congelar |
| Atualizar RSI live | Ausente | RSI recebe apenas `setData()` inicial | Série pode congelar |
| Atualizar indicadores no histórico | Ausente | sem prepend das séries auxiliares | Séries ficam incompletas |
| Botão Voltar ao vivo | Concluído | linhas 822–826 e 899–905 | Correto |
| Detectar modo histórico | Parcial | linhas 796–819 | Deve ser validado com `rightOffset: 5` |
| Indicador Carregando histórico | Concluído | linhas 931–935 | Correto |
| Cleanup do chart | Parcial | linhas 673–688 | Bom, mas incompleto |
| Cancelamento de polling | Não auditável | hook ausente | Bloqueado |
| Abort de requests | Não auditável | hook ausente | Bloqueado |
| Descartar respostas antigas | Não auditável | hook ausente | Bloqueado |
| React Strict Mode | Não auditável completamente | hook ausente | Exige teste |
| Separação Live/Replay | Defeituosa | hook live é chamado mesmo no Replay | Corrigir |
| Replay sem `setData()` a cada passo | Ausente | condição `|| replayMode` | Pode causar flicker |
| Testes automatizados | Ausente do pacote | nenhum teste enviado | Criar |

---

# 4. Achados críticos

# 4.1 P0 — O histórico é solicitado, mas não é aplicado explicitamente ao gráfico

O handler de pan executa:

```ts
void effectiveData.loadOlder().finally(() => {
  isLoadingOlderRef.current = false
})
```

Porém, no componente auditado, não existe uma rotina que:

1. receba o lote histórico;
2. normalize o lote;
3. deduplique;
4. faça prepend;
5. chame `setData()` com o conjunto mesclado;
6. atualize EMA 20, EMA 200 e RSI;
7. preserve a faixa visível.

O efeito de dados iniciais está bloqueado após a primeira carga por:

```ts
if (!initialDataSetRef.current || replayMode)
```

No modo live, depois que `initialDataSetRef.current` passa para `true`, uma alteração em `effectiveData.lwCandles` causada por `loadOlder()` não é reaplicada com `setData()`.

## Consequência provável

O hook pode até carregar os candles anteriores e aumentar seu array interno, mas a série principal pode continuar com os dados antigos.

Além disso, o componente pode atualizar:

```ts
lwCandlesRef.current = effectiveData.lwCandles
```

sem que os mesmos dados tenham sido aplicados à série.

Isso cria duas fontes de verdade divergentes:

```text
lwCandlesRef / hook
    contém histórico novo

candleSeries
    não contém o mesmo histórico
```

Essa divergência pode produzir:

- tooltip incorreto;
- índices de tempo incorretos;
- overlays desalinhados;
- `barsInLogicalRange()` baseado em dados diferentes dos refs;
- comportamento imprevisível no próximo update live.

## Correção obrigatória

`loadOlder()` deve retornar um resultado explícito e o componente deve aplicar o prepend.

---

# 4.2 P0 — A posição visual não é preservada durante prepend

Não existe captura de:

```ts
chart.timeScale().getVisibleLogicalRange()
```

antes de substituir os dados históricos.

Também não existe restauração com deslocamento por `addedCount`.

## Consequência

Ao inserir candles antes do primeiro candle existente, os índices lógicos dos candles que já estavam no gráfico mudam.

Sem compensação, a tela pode:

- saltar para candles mais antigos;
- deslocar o candle sob o cursor;
- mudar o centro visual;
- causar sensação de que o pan está quebrado.

## Correção obrigatória

No momento de aplicar o histórico:

```ts
const rangeAtApply = chart.timeScale().getVisibleLogicalRange()

series.setData(mergedCandles)

if (rangeAtApply && addedCount > 0) {
  chart.timeScale().setVisibleLogicalRange({
    from: rangeAtApply.from + addedCount,
    to: rangeAtApply.to + addedCount,
  })
}
```

A faixa deve ser capturada **imediatamente antes de aplicar o lote**, não no início do request, pois o usuário pode continuar navegando enquanto a rede responde.

---

# 4.3 P1 — EMA 20, EMA 200 e RSI não são atualizados em live

Na carga inicial:

```ts
ema20SeriesRef.current.setData(...)
ema200SeriesRef.current.setData(...)
rsiSeriesRef.current.setData(...)
```

No efeito live, apenas a série de candles recebe:

```ts
candleSeriesRef.current.update(candle)
```

Não existe:

```ts
ema20SeriesRef.current.update(...)
ema200SeriesRef.current.update(...)
rsiSeriesRef.current.update(...)
```

## Consequência

Após a abertura do gráfico:

- candles continuam atualizando;
- EMA 20 pode ficar congelada;
- EMA 200 pode ficar congelada;
- RSI pode ficar congelado;
- cruzamentos e valores apresentados podem não corresponder ao candle atual.

## Correção obrigatória

O hook deve expor deltas de todas as séries ou o componente deve localizar, por timestamp, os pontos correspondentes nos arrays atualizados.

Contrato recomendado:

```ts
interface MarketSeriesDelta {
  candles: CandlestickData[]
  ema20: LineData[]
  ema200: LineData[]
  rsi: LineData[]
}
```

Cada lote deve ser ordenado e aplicado com `update()`.

---

# 4.4 P1 — Os indicadores também não recebem histórico anterior

Quando histórico for carregado, não basta atualizar candles.

O prepend deve atualizar em conjunto:

- candles;
- EMA 20;
- EMA 200;
- RSI.

Caso contrário, o usuário verá candles históricos sem indicadores correspondentes.

## Regra

A posição da escala temporal deve ser restaurada somente depois de todas as séries receberem seus dados completos.

---

# 4.5 P1 — O reset de `initialDataSetRef` possui risco de corrida

A ordem atual é:

1. efeito de criação do chart por `[symbol, timeframe]`;
2. efeito de aplicação inicial;
3. efeito separado que redefine `initialDataSetRef.current = false`.

Ao trocar ativo ou timeframe, o ref ainda pode estar `true` quando o efeito de aplicação inicial rodar.

Na prática, um novo fetch normalmente causa outra renderização, mas o comportamento depende da identidade e do timing dos arrays do hook.

## Correção obrigatória

Remover o booleano genérico como única identificação.

Usar chave explícita:

```ts
const dataIdentity = `${replayMode ? 'replay' : 'live'}:${symbol}:${timeframe}`
const appliedInitialIdentityRef = useRef<string | null>(null)
```

No efeito de criação do chart:

```ts
appliedInitialIdentityRef.current = null
```

Na carga inicial live:

```ts
if (appliedInitialIdentityRef.current === dataIdentity) return

applyInitialSnapshot()
appliedInitialIdentityRef.current = dataIdentity
```

---

# 4.6 P1 — Não há validação de candles antes de `update()`

O live executa `update()` diretamente para cada item recebido.

Não há garantia local de:

- ordem crescente;
- unicidade;
- timestamp válido;
- OHLC válido;
- timestamp maior ou igual ao último aplicado.

O erro é capturado por `try/catch`, mas depois os refs são atualizados com o array do hook mesmo que `update()` tenha falhado.

## Consequência

Pode ocorrer dessincronização entre refs e série.

## Correção obrigatória

Antes de aplicar:

1. normalizar;
2. ordenar;
3. deduplicar;
4. comparar com o último timestamp aplicado;
5. atualizar os refs somente depois do sucesso.

---

# 4.7 P1 — O modo Replay usa `setData()` em cada avanço

A condição atual é:

```ts
if (!initialDataSetRef.current || replayMode)
```

Como `replayMode` é sempre verdadeiro durante Replay, cada alteração de `currentIndex` pode:

- executar `setData()` para candles;
- executar `setData()` para EMA 20;
- executar `setData()` para EMA 200;
- executar `setData()` para RSI;
- redefinir a faixa para os últimos 120 candles.

## Consequências

- maior custo por passo;
- possível flicker;
- pan manual perdido;
- zoom manual perdido;
- reposicionamento contínuo;
- pior desempenho no play automático.

## Correção obrigatória

Separar os fluxos:

### Replay — avanço de um candle

Usar:

```ts
candleSeries.update(nextCandle)
ema20Series.update(nextEma20)
ema200Series.update(nextEma200)
rsiSeries.update(nextRsi)
```

### Replay — voltar um candle

Na versão atual do Lightweight Charts, pode ser utilizado `series.pop(1)` quando disponível na versão instalada.

Antes de usar, confirmar a versão exata do pacote.

### Replay — salto, troca de período ou recarga

Usar `setData()` somente nestes casos.

### Faixa visível

Não chamar `setVisibleLogicalRange()` a cada passo de Replay.

---

# 4.8 P1 — O hook live continua ativo durante Replay

O componente sempre executa:

```ts
const liveData = useRealMarketData(liveSymbol, liveTimeframe)
```

mesmo quando `replayMode === true`.

Hooks não podem ser chamados condicionalmente, portanto a solução deve ser arquitetural.

## Consequências

Durante Replay podem continuar ocorrendo:

- polling live;
- requests;
- atualizações de estado;
- processamento de zonas;
- erros live;
- consumo de rede;
- rerenders desnecessários.

## Soluções permitidas

### Opção recomendada

Adicionar ao hook:

```ts
useRealMarketData(symbol, timeframe, {
  enabled: !replayMode,
})
```

Quando `enabled` for falso, o hook deve:

- não iniciar carga;
- não iniciar polling;
- abortar requests existentes;
- não emitir atualizações.

### Opção alternativa

Separar em componentes:

```text
LiveCandlestickChart
ReplayCandlestickChart
SharedChartRenderer
```

Essa opção é mais limpa, mas exige refatoração maior.

---

# 4.9 P1 — O status do toolbar usa `liveData` no Replay

O status atual consulta diretamente:

```ts
liveData.isInitialLoading
liveData.error
```

mesmo quando o gráfico está em Replay.

## Correção

Usar `effectiveData` ou uma função específica por modo:

```ts
const statusSource = replayMode
  ? replayStatus
  : liveStatus
```

No Replay, usar estados como:

```text
REPLAY
CARREGANDO REPLAY
PAUSADO
REPRODUZINDO
```

---

# 4.10 P1 — Handler global de erro está instável

O efeito de debug substitui:

```ts
window.onerror
```

e depende do objeto inteiro `effectiveData`.

Isso faz o handler ser reinstalado frequentemente.

Além disso, o trecho:

```ts
prev.apply(window, arguments as any)
```

está dentro de arrow functions e pode capturar um `arguments` que não representa os argumentos reais do evento.

## Riscos

- interferência no error handler global do aplicativo;
- chamada incorreta do handler anterior;
- churn em cada atualização;
- logs com contexto inconsistente.

## Correção recomendada

Usar:

```ts
window.addEventListener('error', handler)
```

com efeito montado uma vez.

Guardar o contexto mutável em:

```ts
const debugContextRef = useRef(...)
```

No cleanup:

```ts
window.removeEventListener('error', handler)
```

Remover completamente esse debug depois que o erro `Value is null` for resolvido.

---

# 4.11 P2 — Existem múltiplas subscriptions da faixa lógica

Atualmente existem subscriptions separadas para:

1. redraw;
2. infinite scroll;
3. detecção de realtime.

Isso não é necessariamente incorreto, mas durante pan contínuo gera múltiplos callbacks.

## Melhoria recomendada

Consolidar a lógica de faixa em um único callback estável:

```ts
function handleVisibleLogicalRangeChange(range: LogicalRange | null) {
  schedulePrimitiveRedraw()
  updateRealtimeState(range)
  maybeLoadOlder(range)
}
```

Manter throttling visual por `requestAnimationFrame`.

---

# 4.12 P2 — Crosshair handler não é removido explicitamente

A criação usa callback inline em:

```ts
chart.subscribeCrosshairMove(...)
```

No cleanup não existe chamada explícita de unsubscribe para esse callback.

`chart.remove()` tende a eliminar o chart, mas o padrão correto é:

```ts
const handleCrosshairMove = (...) => { ... }

chart.subscribeCrosshairMove(handleCrosshairMove)

return () => {
  chart.unsubscribeCrosshairMove(handleCrosshairMove)
}
```

---

# 4.13 P2 — Validação inicial é insuficiente

Na carga inicial, o código apenas filtra:

```ts
c.time > 0
```

Não valida:

- duplicidade;
- ordem;
- `NaN`;
- `Infinity`;
- OHLC inconsistente;
- milissegundos versus segundos;
- pontos de indicador duplicados.

Criar normalizadores canônicos.

---

# 4.14 P2 — A conformidade de atribuição deve ser verificada

O chart está configurado com:

```ts
attributionLogo: false
```

Caso a aplicação seja pública, verificar se a atribuição exigida pelo Lightweight Charts está presente em outro local visível do produto.

Não alterar essa configuração sem antes verificar a política visual e jurídica do projeto.

---

# 5. Arquitetura corrigida

# 5.1 Separação de responsabilidades

## `useRealMarketData.ts`

Responsável por:

- snapshot inicial;
- polling;
- requests;
- `AbortController`;
- normalização;
- ordenação;
- deduplicação;
- estado de conexão;
- estado de erro;
- paginação;
- `hasMoreHistory`;
- impedir requests paralelos;
- ignorar respostas obsoletas;
- expor deltas live;
- retornar lotes históricos explícitos.

## `CandlestickChart.tsx`

Responsável por:

- criar e destruir o chart;
- criar e destruir séries;
- aplicar snapshot inicial;
- aplicar deltas live;
- aplicar prepend histórico;
- preservar faixa;
- controlar pan;
- controlar zoom;
- controlar botão **Voltar ao vivo**;
- atualizar refs coerentemente;
- atualizar overlays.

## Normalizadores

Responsáveis por:

- timestamp;
- OHLC;
- dados de linha;
- merge;
- deduplicação;
- validação.

---

# 5.2 Contratos recomendados

```ts
interface NormalizedCandle {
  time: UTCTimestamp
  open: number
  high: number
  low: number
  close: number
}

interface NormalizedLinePoint {
  time: UTCTimestamp
  value: number
}

interface MarketSeriesDelta {
  candles: NormalizedCandle[]
  ema20: NormalizedLinePoint[]
  ema200: NormalizedLinePoint[]
  rsi: NormalizedLinePoint[]
  sequence: number
  queryKey: string
}

interface HistoricalSeriesPage {
  candles: NormalizedCandle[]
  ema20: NormalizedLinePoint[]
  ema200: NormalizedLinePoint[]
  rsi: NormalizedLinePoint[]
  hasMore: boolean
  before: UTCTimestamp
  queryKey: string
}

interface UseRealMarketDataResult {
  initialCandles: NormalizedCandle[]
  initialEMA20: NormalizedLinePoint[]
  initialEMA200: NormalizedLinePoint[]
  initialRSI: NormalizedLinePoint[]

  latestDelta: MarketSeriesDelta | null

  loadOlder: (
    before: UTCTimestamp,
    signal?: AbortSignal,
  ) => Promise<HistoricalSeriesPage>

  hasMoreHistory: boolean
  isInitialLoading: boolean
  isPolling: boolean
  isLoadingOlder: boolean
  connected: boolean
  error: Error | null
}
```

## Regra importante

`loadOlder()` deve retornar **somente o lote anterior**, não um snapshot completo possivelmente desatualizado.

O componente mescla esse lote com seus refs atuais. Assim, um candle live recebido durante o request histórico não é apagado quando a resposta histórica chega.

---

# 6. Refs canônicos do chart

Substituir refs parciais por refs tipados:

```ts
const candleDataRef = useRef<NormalizedCandle[]>([])
const ema20DataRef = useRef<NormalizedLinePoint[]>([])
const ema200DataRef = useRef<NormalizedLinePoint[]>([])
const rsiDataRef = useRef<NormalizedLinePoint[]>([])

const lastCandleTimeRef = useRef<UTCTimestamp | null>(null)
const activeQueryKeyRef = useRef('')
const chartGenerationRef = useRef(0)
const isDisposedRef = useRef(false)
const isLoadingOlderRef = useRef(false)
```

## Regra de fonte de verdade

Os refs do chart devem representar exatamente o que foi aplicado às séries.

Nunca atualizar o ref com um array que ainda não foi aplicado ao chart.

---

# 7. Normalização obrigatória

# 7.1 Candles

Criar:

```ts
normalizeCandles(raw): NormalizedCandle[]
```

Deve:

1. converter timestamp para Unix em segundos;
2. rejeitar timestamp inválido;
3. converter OHLC para `number`;
4. rejeitar `NaN` e `Infinity`;
5. validar OHLC;
6. ordenar por `time`;
7. deduplicar por `time`;
8. manter a versão mais recente em duplicatas.

## Validação OHLC

```text
high >= open
high >= close
low <= open
low <= close
high >= low
```

# 7.2 Pontos de linha

Criar:

```ts
normalizeLineData(raw): NormalizedLinePoint[]
```

Deve:

- validar `time`;
- validar `value`;
- ordenar;
- deduplicar.

# 7.3 Timestamp

Centralizar:

```ts
normalizeTimestamp(value): UTCTimestamp | null
```

Não espalhar heurísticas de segundos/milissegundos pelo componente.

---

# 8. Plano de execução revisado

# Fase 0 — Completar a auditoria bloqueada

Adicionar ao pacote de auditoria:

- `useRealMarketData.ts`;
- `useSmcPerType.ts`;
- cliente de API;
- `package.json`;
- lockfile;
- `tsconfig`;
- arquivos de testes;
- contrato do backend.

Confirmar:

- versão exata de `lightweight-charts`;
- assinatura real de `loadOlder`;
- forma real de `latestUpdates`;
- polling;
- cancelamento;
- deduplicação;
- comportamento do backend.

**Gate:** não alterar a assinatura do hook sem ler sua implementação completa.

---

# Fase 1 — Criar normalizadores e testes

Criar funções puras:

```text
normalizeTimestamp
normalizeCandles
normalizeLineData
mergeOlderCandles
mergeOlderLineData
applyLiveCandleDelta
applyLiveLineDelta
```

## Testes

- segundos;
- milissegundos;
- timestamp inválido;
- ordem invertida;
- duplicatas;
- candle inválido;
- lote vazio;
- merge histórico;
- update do último candle;
- adição de candle novo;
- rejeição de candle antigo.

---

# Fase 2 — Corrigir identidade da carga inicial

Remover dependência exclusiva de:

```ts
initialDataSetRef.current
```

Criar chave:

```ts
const queryKey = `${replayMode ? 'replay' : 'live'}:${symbol}:${timeframe}`
```

No início da criação do chart:

```ts
chartGenerationRef.current += 1
activeQueryKeyRef.current = queryKey
appliedInitialIdentityRef.current = null
```

Aplicar snapshot apenas quando:

```ts
appliedInitialIdentityRef.current !== queryKey
```

Depois de sucesso:

```ts
appliedInitialIdentityRef.current = queryKey
```

---

# Fase 3 — Corrigir snapshot inicial

Fluxo:

1. aguardar `isInitialLoading === false`;
2. normalizar candles;
3. normalizar EMA 20;
4. normalizar EMA 200;
5. normalizar RSI;
6. aplicar todas as séries;
7. atualizar refs somente após sucesso;
8. construir mapa de tempo;
9. aplicar faixa inicial uma vez;
10. atualizar overlays.

## Regra de faixa inicial

```ts
const visibleCount = 120
const rightOffsetBars = 5
```

Executar apenas:

- primeira carga;
- troca de ativo;
- troca de timeframe;
- nova carga de Replay.

Não executar:

- polling;
- prepend;
- atualização de indicador;
- pan;
- zoom;
- passo normal do Replay.

---

# Fase 4 — Corrigir updates live

Para cada `latestDelta`:

1. confirmar `queryKey`;
2. descartar resposta obsoleta;
3. normalizar;
4. ordenar;
5. deduplicar;
6. aplicar candles;
7. aplicar EMA 20;
8. aplicar EMA 200;
9. aplicar RSI;
10. atualizar refs após cada aplicação;
11. reconstruir somente o trecho necessário do mapa de tempo;
12. atualizar overlays sem resetar a escala.

## Algoritmo de candle

```text
time < lastTime
    ignorar ou tratar como correção histórica explícita

time == lastTime
    series.update(candle)
    substituir último item do ref

time > lastTime
    series.update(candle)
    adicionar ao ref
```

## Proibição

Não executar `setData()` no polling.

---

# Fase 5 — Corrigir `loadOlder()`

A assinatura deve ser explícita:

```ts
loadOlder(oldestTime, signal)
```

Não depender apenas de estado interno oculto.

## Handler

```ts
async function requestOlderCandles() {
  if (isLoadingOlderRef.current) return
  if (!hasMoreHistoryRef.current) return
  if (candleDataRef.current.length === 0) return

  const chart = chartRef.current
  if (!chart) return

  isLoadingOlderRef.current = true

  const requestQueryKey = activeQueryKeyRef.current
  const requestGeneration = chartGenerationRef.current
  const oldestTime = candleDataRef.current[0].time

  try {
    const page = await loadOlder(oldestTime)

    if (isDisposedRef.current) return
    if (requestQueryKey !== activeQueryKeyRef.current) return
    if (requestGeneration !== chartGenerationRef.current) return

    applyHistoricalPage(page)
  } finally {
    isLoadingOlderRef.current = false
  }
}
```

---

# Fase 6 — Aplicar prepend e preservar a posição

## 6.1 Capturar faixa no momento da aplicação

```ts
const rangeAtApply =
  chart.timeScale().getVisibleLogicalRange()
```

## 6.2 Mesclar com os refs atuais

```ts
const candleMerge = mergeOlderCandles(
  page.candles,
  candleDataRef.current,
)

const ema20Merge = mergeOlderLineData(
  page.ema20,
  ema20DataRef.current,
)

const ema200Merge = mergeOlderLineData(
  page.ema200,
  ema200DataRef.current,
)

const rsiMerge = mergeOlderLineData(
  page.rsi,
  rsiDataRef.current,
)
```

## 6.3 Aplicar todas as séries

```ts
candleSeries.setData(candleMerge.data)
ema20Series.setData(ema20Merge.data)
ema200Series.setData(ema200Merge.data)
rsiSeries.setData(rsiMerge.data)
```

## 6.4 Atualizar refs

Somente depois de todas as operações terem sucesso.

## 6.5 Restaurar faixa

Usar a quantidade de candles efetivamente adicionados:

```ts
if (rangeAtApply && candleMerge.addedCount > 0) {
  chart.timeScale().setVisibleLogicalRange({
    from: rangeAtApply.from + candleMerge.addedCount,
    to: rangeAtApply.to + candleMerge.addedCount,
  })
}
```

## 6.6 Atualizar auxiliares

- mapa timestamp → índice;
- `timestampsRef`;
- SMC;
- Elliott;
- Wyckoff;
- tooltip;
- `hasMoreHistory`.

## Proibições

Não chamar:

```ts
fitContent()
resetTimeScale()
scrollToRealTime()
```

durante prepend.

---

# Fase 7 — Corrigir infinite scroll

Manter `barsInLogicalRange()`.

Usar:

```ts
const barsInfo = series.barsInLogicalRange(range)

if (
  barsInfo !== null &&
  barsInfo.barsBefore < HISTORY_PREFETCH_THRESHOLD
) {
  void requestOlderCandles()
}
```

## Guardas

- range não nulo;
- chart existente;
- série existente;
- candles existentes;
- não estar no Replay;
- não estar carregando;
- hook não estar carregando;
- `hasMoreHistory`;
- query atual;
- chart não descartado.

## Configuração

```text
HISTORY_PREFETCH_THRESHOLD = 50
HISTORY_PAGE_SIZE = 500
```

---

# Fase 8 — Consolidar subscriptions

Criar uma função estável:

```ts
const handleLogicalRangeChange = (
  range: LogicalRange | null,
) => {
  scheduleRedraw()
  updateRealtimeState(range)
  maybeRequestHistory(range)
}
```

Inscrever uma vez por instância do chart.

No cleanup, remover usando a mesma referência.

---

# Fase 9 — Validar detecção de realtime

A lógica atual usa:

```ts
barsInfo.barsAfter <= REALTIME_TOLERANCE
```

Ela pode funcionar, mas deve ser testada com:

```ts
rightOffset: 5
```

## Cenários obrigatórios

1. último candle visível com espaço à direita;
2. último candle parcialmente visível;
3. usuário a um candle do fim;
4. usuário no histórico;
5. novo candle enquanto no histórico;
6. clique em Voltar ao vivo.

## Alternativa

Avaliar `timeScale.scrollPosition()` após confirmar o comportamento na versão instalada.

Não trocar a implementação sem teste visual.

---

# Fase 10 — Corrigir Replay

Separar os efeitos live dos efeitos Replay.

## Replay forward

- `update()` para novo candle;
- `update()` para indicadores;
- não resetar range.

## Replay backward

- usar `pop(1)` se suportado pela versão instalada;
- caso contrário, `setData()` apenas ao voltar ou saltar;
- preservar a faixa quando aplicável.

## Replay jump

`setData()` permitido em:

- carregar período;
- ir ao início;
- ir ao fim;
- salto grande;
- trocar símbolo;
- trocar timeframe.

## Hook live

Adicionar `enabled: !replayMode`.

---

# Fase 11 — Corrigir debug e cleanup

## Cleanup obrigatório

- unsubscribe logical range;
- unsubscribe visible time range;
- unsubscribe size;
- unsubscribe data changed;
- unsubscribe crosshair;
- cancelar `requestAnimationFrame`;
- desconectar `ResizeObserver`;
- abortar requests;
- interromper polling;
- detach primitives;
- remover chart;
- zerar refs;
- marcar `isDisposedRef.current = true`.

## Debug global

Substituir `window.onerror` por listener estável ou remover.

---

# Fase 12 — Polling

Esta fase depende da auditoria do hook.

## Requisitos

- intervalo base de 10 segundos;
- nenhuma chamada sobreposta;
- `AbortController`;
- backoff em erro;
- reset do backoff em sucesso;
- request recente pequeno;
- query key;
- sequence/revision;
- descarte de resposta antiga;
- cleanup em unmount;
- cleanup na troca de ativo/timeframe;
- disabled no Replay.

## Estratégia preferida

`setTimeout` recursivo:

```text
esperar
→ buscar
→ processar
→ finalizar
→ agendar próximo
```

Evitar `setInterval` sem trava.

---

# Fase 13 — Overlays

Ao aplicar histórico:

- atualizar último candle conhecido;
- recalcular normalizadores necessários;
- não reposicionar time scale;
- não executar `fitContent()`;
- não reconstruir primitives sem necessidade;
- usar `requestAnimationFrame` para redraw.

Verificar se os overlays usam exatamente os mesmos timestamps normalizados das séries.

---

# Fase 14 — Testes automatizados

## Normalização

- timestamp;
- OHLC;
- linha;
- ordem;
- deduplicação.

## Live

- candle aberto;
- novo candle;
- múltiplos candles;
- candle antigo;
- indicador correspondente;
- falha parcial.

## Histórico

- prepend;
- borda duplicada;
- `addedCount`;
- lote vazio;
- fim do histórico;
- live recebido durante request histórico;
- resposta de query antiga.

## Replay

- forward;
- backward;
- jump;
- pan preservado;
- hook live disabled.

## Cleanup

- Strict Mode;
- unmount;
- troca rápida;
- request tardio;
- timer tardio.

---

# 9. Testes manuais obrigatórios

# Cenário A — Abertura

1. abrir gráfico;
2. confirmar um request inicial;
3. confirmar um `setData()` por série;
4. confirmar últimos 120 candles visíveis;
5. confirmar EMA e RSI;
6. confirmar ausência de flicker.

# Cenário B — Candle aberto

1. receber mesmo timestamp;
2. confirmar candle atualizado;
3. confirmar EMA/RSI atualizados;
4. confirmar faixa inalterada;
5. confirmar ausência de `setData()`.

# Cenário C — Novo candle no presente

1. permanecer em realtime;
2. receber timestamp maior;
3. confirmar novo candle;
4. confirmar deslocamento natural;
5. confirmar indicadores.

# Cenário D — Novo candle durante histórico

1. fazer pan para a esquerda;
2. receber candle novo;
3. confirmar que a tela não volta;
4. confirmar botão Voltar ao vivo;
5. clicar;
6. confirmar retorno.

# Cenário E — Histórico

1. aproximar do início;
2. confirmar uma chamada;
3. receber lote;
4. confirmar prepend;
5. confirmar que o candle central permanece no mesmo lugar;
6. confirmar EMA e RSI históricos;
7. repetir até o fim.

# Cenário F — Rede lenta

1. request histórico demorar;
2. continuar fazendo pan;
3. receber live;
4. aplicar histórico;
5. confirmar que live não foi perdido;
6. confirmar ausência de request duplicado.

# Cenário G — Troca rápida

1. WINFUT M5;
2. WDOFUT M2;
3. outro ativo;
4. confirmar que respostas anteriores são ignoradas.

# Cenário H — Replay

1. iniciar Replay;
2. confirmar ausência de polling live;
3. avançar;
4. voltar;
5. fazer pan;
6. confirmar ausência de reset a cada passo.

# Cenário I — Strict Mode

1. executar desenvolvimento;
2. confirmar um chart;
3. confirmar um polling;
4. confirmar uma subscription;
5. confirmar cleanup.

---

# 10. Logs temporários

```text
[chart:create] queryKey generation
[chart:initial:apply] candles ema20 ema200 rsi
[chart:live:candle:update] time
[chart:live:candle:new] time
[chart:live:indicator] type time
[chart:history:request] before queryKey generation
[chart:history:received] counts
[chart:history:merge] addedCount total
[chart:history:range-before] from to
[chart:history:range-after] from to
[chart:history:discarded] reason
[chart:realtime] true false
[chart:dispose] queryKey generation
```

Logs detalhados devem existir apenas em desenvolvimento.

---

# 11. Critérios de aceite revisados

A correção só será considerada concluída quando:

- [ ] `setData()` não for usado no polling;
- [ ] candles live usarem `update()`;
- [ ] EMA 20 live usar `update()`;
- [ ] EMA 200 live usar `update()`;
- [ ] RSI live usar `update()`;
- [ ] lotes live forem normalizados;
- [ ] candles duplicados forem eliminados;
- [ ] updates antigos forem ignorados;
- [ ] `loadOlder()` receber `before`;
- [ ] histórico retornar lote explícito;
- [ ] histórico for aplicado ao candleSeries;
- [ ] histórico for aplicado às EMAs;
- [ ] histórico for aplicado ao RSI;
- [ ] posição visual for preservada;
- [ ] live não for apagado por resposta histórica;
- [ ] não houver requests históricos paralelos;
- [ ] não houver polling paralelo;
- [ ] botão Voltar ao vivo funcionar;
- [ ] realtime não puxar usuário no histórico;
- [ ] zoom e pan não forem revertidos;
- [ ] Replay não executar polling live;
- [ ] Replay não executar `setData()` a cada passo normal;
- [ ] troca de ativo/timeframe não misturar respostas;
- [ ] refs e séries permanecerem coerentes;
- [ ] tooltip permanecer alinhado;
- [ ] overlays permanecerem alinhados;
- [ ] handler global de erro não interferir no app;
- [ ] cleanup completo funcionar;
- [ ] Strict Mode passar;
- [ ] type-check passar;
- [ ] build passar;
- [ ] lint passar;
- [ ] testes passarem;
- [ ] não houver erro no console.

---

# 12. Ordem final recomendada

1. incluir os arquivos ausentes na auditoria;
2. confirmar versão do Lightweight Charts;
3. auditar `useRealMarketData`;
4. criar normalizadores;
5. criar testes de merge;
6. corrigir chave de carga inicial;
7. corrigir live de candles;
8. corrigir live de indicadores;
9. alterar contrato de `loadOlder`;
10. aplicar prepend;
11. preservar faixa;
12. consolidar subscriptions;
13. validar realtime;
14. desabilitar live no Replay;
15. otimizar passos de Replay;
16. corrigir debug global;
17. completar cleanup;
18. executar type-check;
19. executar build;
20. executar testes;
21. executar testes manuais;
22. remover logs temporários;
23. gerar relatório final.

---

# 13. Restrições para a IA executora

A IA deve:

1. ler integralmente o hook antes de modificá-lo;
2. não assumir a assinatura de `loadOlder`;
3. não misturar API v4 e v5;
4. confirmar o pacote instalado;
5. não atualizar dependências sem necessidade;
6. preservar primitives SMC;
7. preservar Elliott;
8. preservar Wyckoff;
9. preservar tooltips;
10. preservar Replay;
11. fazer mudanças pequenas;
12. compilar após cada etapa;
13. executar testes;
14. informar arquivos alterados;
15. apresentar riscos restantes;
16. não declarar sucesso apenas porque o código compila;
17. validar visualmente pan, zoom, live e prepend.

---

# 14. Resultado esperado

```text
ABERTURA
  → snapshot inicial normalizado
  → setData em candles + indicadores
  → últimos 120 candles visíveis
  → inicia polling

LIVE
  → mesmo timestamp: update candle aberto
  → timestamp maior: update adiciona candle
  → indicadores atualizam junto
  → sem setData
  → sem alterar pan/zoom

PAN PARA ESQUERDA
  → barsInLogicalRange detecta limite
  → loadOlder(before)
  → retorna lote anterior
  → merge com refs atuais
  → setData em todas as séries
  → restaura range por addedCount
  → sem salto

DURANTE REQUEST HISTÓRICO
  → live pode continuar
  → resposta histórica não apaga live
  → query antiga é descartada

USUÁRIO NO HISTÓRICO
  → live continua
  → tela permanece
  → botão Voltar ao vivo aparece

REPLAY
  → polling live desabilitado
  → forward usa update
  → backward usa pop ou ressincronização controlada
  → pan não é resetado em cada passo

TROCA DE ATIVO/TIMEFRAME
  → aborta requests anteriores
  → incrementa generation
  → limpa refs
  → aplica novo snapshot
  → ignora respostas antigas
```

---

# 15. Conclusão da auditoria

O código enviado não está no estado inicial descrito no plano anterior: várias melhorias já foram implementadas.

Entretanto, o núcleo do infinite scroll ainda está incompleto. A existência de `barsInLogicalRange()` e da chamada `loadOlder()` não significa que o histórico esteja corretamente integrado.

Os pontos prioritários são:

1. aplicar explicitamente o lote histórico;
2. preservar a faixa;
3. impedir divergência entre refs e série;
4. atualizar EMA 20, EMA 200 e RSI;
5. corrigir a identidade da carga inicial;
6. separar Replay do live;
7. auditar o hook ausente.

Até que esses itens sejam concluídos, o gráfico deve ser considerado:

```text
LIVE_BÁSICO_IMPLEMENTADO
INFINITE_SCROLL_INCOMPLETO
INDICADORES_LIVE_INCOMPLETOS
REPLAY_COM_RISCO_DE_FLICKER
AUDITORIA_DO_POLLING_BLOQUEADA
```
