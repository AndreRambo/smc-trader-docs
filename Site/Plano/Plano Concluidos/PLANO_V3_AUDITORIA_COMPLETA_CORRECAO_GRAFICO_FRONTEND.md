# Plano V3 — Auditoria Completa e Correção do Gráfico Lightweight Charts

**Projeto auditado:** `frontend.zip`  
**Data:** 26/06/2026  
**Foco prioritário:** gráfico TradingView Lightweight Charts, live, pan, histórico, indicadores, SMC e Replay  
**Versão efetivamente instalada:** `lightweight-charts 5.2.0`  
**Status executivo:** `BUILD_PASSA_MAS_GRAFICO_NAO_APROVADO`

---

## 1. Resultado da auditoria

Foram auditados os arquivos autorais e de configuração do pacote, excluindo o conteúdo de terceiros de `node_modules` e os artefatos gerados de `dist` da revisão linha a linha.

### Verificações executadas

| Verificação | Resultado |
|---|---|
| TypeScript `tsc -b` | **PASS** |
| Vite produção | **PASS** |
| Módulos transformados | 106 |
| Bundle JS principal | **5.245,35 kB minificado** |
| Bundle JS gzip | **1.562,90 kB** |
| Source map | **12.791,81 kB** |
| ESLint em `src` | **FAIL — 123 erros e 13 avisos** |
| Testes automatizados | **AUSENTES — não existe script `test`** |
| Infinite scroll visual | **FAIL — confirmado pela captura enviada** |

O build sem erros comprova apenas que o projeto transpila e empacota. Ele não valida paginação, ordem temporal, concorrência, preservação da faixa, Replay ou comportamento visual.

---

## 2. Diagnóstico principal do defeito mostrado

A captura mostra que o usuário conseguiu deslocar a escala lógica para a esquerda até uma área vazia, mas nenhum lote anterior permaneceu aplicado ao chart.

O gatilho por `barsInLogicalRange()` existe, porém há diversos pontos capazes de impedir o histórico de aparecer sem deixar erro visível.

### Status

```text
GATILHO_DE_PAN: IMPLEMENTADO
REQUEST_HISTORICO: IMPLEMENTADO_PARCIALMENTE
PAGINACAO_ESTAVEL: NÃO
MERGE_ATOMICO: NÃO
ERRO_VISIVEL: NÃO
INDICADORES_HISTORICOS_SEGUROS: NÃO
INFINITE_SCROLL: REPROVADO
```

---

## 3. Achados P0 — bloqueiam o gráfico

### P0.1 — Paginação histórica por `offset` é inadequada para dados live

**Arquivo:** `src/hooks/useRealMarketData.ts`, região de `loadOlder()`.

A implementação atual chama:

```ts
/api/candles/{symbol}?timeframe={tf}&limit=500&offset={currentOffset}
```

Problemas:

1. o plano V2 exigia cursor `before=<oldestTime>`, mas isso não foi implementado;
2. `offsetRef.current` é definido como `500`, mesmo quando a carga inicial retorna menos dados;
3. candles novos alteram a posição relativa da coleção no servidor, fazendo o offset derivar;
4. um mesmo candle pode reaparecer ou candles podem ser pulados;
5. o hook avança sempre `+500`, mesmo se o backend retornar menos itens ou duplicatas.

**Correção obrigatória:**

```http
GET /api/candles/:symbol?timeframe=5min&before=<oldest_unix_or_iso>&limit=500
```

Contrato:

```text
cada item retornado deve ter time < before
ordem crescente
cursor estável
has_more explícito
```

---

### P0.2 — Qualquer erro histórico é convertido silenciosamente em “fim do histórico”

O `catch` atual retorna:

```ts
{ candles: [], ..., hasMore: false }
```

Consequência:

- timeout;
- HTTP 500;
- JSON inválido;
- erro de ordenação;
- falha de `setData`;

podem deixar `hasMoreHistoryRef.current = false`, impedindo novas tentativas durante toda a sessão.

**Correção:**

- erro e fim de histórico devem ser estados diferentes;
- `loadOlder()` deve lançar `HistoryLoadError`;
- somente resposta válida com `has_more:false` pode encerrar a paginação;
- a UI deve mostrar erro discreto e botão/tentativa automática.

---

### P0.3 — O HTTP status do histórico não é verificado

O código executa `resp.json()` sem:

```ts
if (!resp.ok) throw ...
```

Uma resposta de erro JSON pode ser interpretada como lote vazio e finalizar o histórico.

**Correção:** usar um helper `fetchJson` que valide status, content type e payload.

---

### P0.4 — Indicadores históricos não são ordenados nem deduplicados antes de `setData()`

Em `CandlestickChart.tsx`, candles recebem deduplicação e sort, mas EMA 20, EMA 200 e RSI usam apenas:

```ts
[...page.indicator, ...currentIndicator]
```

A API `setData()` requer dados em ordem temporal. Lotes sobrepostos podem conter timestamps duplicados ou fora de ordem.

**Efeito provável:**

- `setData()` lança exceção;
- a Promise não possui `catch`;
- o usuário vê apenas espaço vazio;
- o console pode mostrar erro não tratado.

**Correção:** criar `mergeOlderLineData()` e validar cada série antes de qualquer aplicação.

---

### P0.5 — Cadeia de `loadOlder()` não possui `catch`

Existe `.then(...).finally(...)`, mas não `.catch(...)`.

Qualquer exceção no merge ou em uma das quatro chamadas `setData()` vira rejeição não tratada.

**Correção:**

```ts
try {
  const page = await loadOlder(before, signal)
  applyHistoricalPage(page)
} catch (error) {
  setHistoryError(normalizeError(error))
} finally {
  ...
}
```

---

### P0.6 — `addedCount` não representa o número real de candles adicionados

O hook retorna:

```ts
addedCount: olderCandles.length
```

Isso ignora:

- candles duplicados na borda;
- sobreposição;
- itens inválidos descartados;
- página repetida pelo offset.

A faixa é restaurada com um deslocamento potencialmente errado.

**Correção:** calcular `addedCount` no merge do componente:

```ts
addedCount = mergedUnique.length - current.length
```

---

### P0.7 — O componente do cliente pode mostrar ativo/timeframe incorretos

`CustomerChartPage.tsx` controla seu próprio `ticker` e `selectedTf`, mas renderiza:

```tsx
<CandlestickChart />
```

O gráfico cria estado interno com:

```text
WINFUT / M5
```

Assim, painel, estudo e seletor podem representar um ativo enquanto o gráfico mostra outro.

**Correção:** tornar o chart controlável:

```ts
symbol?: string
timeframe?: Timeframe
onSymbolChange?: ...
onTimeframeChange?: ...
showInternalToolbar?: boolean
```

---

### P0.8 — Replay não atualiza depois do snapshot inicial

No Replay:

- `effectiveData` muda a cada `currentIndex`;
- o efeito inicial é bloqueado por `appliedInitialIdentityRef`;
- o efeito live retorna imediatamente;
- não existe efeito específico para avançar/recuar Replay.

Resultado provável: o gráfico permanece congelado após a primeira aplicação.

**Correção:** criar pipeline Replay próprio:

- avanço de 1: `series.update()`;
- recuo de 1: `series.pop(1)` na versão 5.2.0;
- salto: `setData()` controlado;
- não resetar pan/zoom em cada passo.

---

## 4. Achados P1 — alta prioridade

### P1.1 — Timestamps e labels podem ficar desalinhados

O candle array é normalizado e ordenado, mas os labels iniciais são criados na ordem bruta da API:

```ts
apiCandles.map(formatTimestamp)
```

No prepend, `timestampsRef` não é atualizado.

O tooltip usa índice do candle ordenado para acessar um label potencialmente de outro candle.

**Correção:** substituir array posicional por:

```ts
Map<number, string>
```

chaveado pelo timestamp canônico.

---

### P1.2 — Polling recente não normaliza o lote completo antes de aplicar

O hook percorre `recentCandles` na ordem recebida e calcula `currentLastTime` apenas uma vez.

Se o backend devolver ordem descendente ou repetição:

- updates podem ser enviados fora de ordem;
- múltiplos candles duplicados podem ser inseridos;
- `series.update()` pode falhar.

**Correção:** normalizar, ordenar e deduplicar o lote antes da comparação.

---

### P1.3 — `lwRSI` não é atualizado quando há candle novo

No bloco `hasNew`, são atualizados:

- candles;
- EMA 20;
- EMA 200;
- timestamps;

mas falta:

```ts
setLwRSI(ind.rsi)
```

---

### P1.4 — Fallback dos indicadores é acoplado apenas à EMA 20

O fallback geral só executa quando:

```ts
ema20Updates.length === 0
```

Se EMA 20 vier da API, mas EMA 200 ou RSI estiverem ausentes, estas séries ficam sem delta.

**Correção:** fallback independente por indicador.

---

### P1.5 — Indicadores calculados em página isolada são matematicamente descontínuos

`buildIndicators(older)` calcula EMA/RSI apenas com os 500 candles da página antiga.

Na borda entre páginas:

- EMA 20;
- EMA 200;
- RSI;

podem apresentar salto.

**Preferência:** backend deve retornar indicadores canônicos persistidos.

**Fallback aceitável:** pedir warmup suficiente e recortar a página visual, ou recalcular a série completa mesclada de forma consistente.

---

### P1.6 — Scheduler recursivo pode ser reativado por uma chamada antiga

O cleanup limpa o timer atual, mas um `pollRecent()` antigo em andamento pode terminar e chamar `schedulePoll()` novamente.

`mountedRef` é compartilhado entre gerações e pode voltar para `true` numa nova identidade.

**Correção:** usar `cancelled` local e `generation` dentro do efeito do scheduler.

---

### P1.7 — `loadOlder()` não possui AbortController próprio

A requisição continua após:

- troca de ativo;
- troca de timeframe;
- entrada no Replay;
- unmount.

O generation evita parte da aplicação, mas não evita custo nem conflitos de loading.

---

### P1.8 — Guard de loading retorna `hasMore:false`

Quando `loadOlder()` é chamado enquanto já carrega, ele retorna página vazia com `hasMore:false`.

Mesmo sendo apenas uma chamada concorrente, o chart pode interpretar como fim definitivo.

**Correção:** não retornar uma página falsa; rejeitar com estado `ALREADY_LOADING`, retornar `null`, ou impedir no hook por ref.

---

### P1.9 — Aplicação histórica não é transacional

O código executa:

1. candles `setData`;
2. EMA 20 `setData`;
3. EMA 200 `setData`;
4. RSI `setData`.

Se a terceira série falhar:

- candles e EMA 20 já mudaram;
- refs ainda não mudaram;
- o chart fica parcialmente atualizado.

**Correção:** validar todas as quatro coleções antes; em erro, não atualizar refs e executar ressincronização integral conhecida.

---

### P1.10 — Existem duas subscriptions de logical range

A alegação de “subscriptions consolidadas” não corresponde ao código.

Há:

1. handler de redraw/realtime durante criação do chart;
2. handler separado do infinite scroll.

**Correção:** um único callback estável com:

```text
redraw
realtime state
history prefetch
```

---

### P1.11 — O sequence existe, mas não é consumido pelo chart

`latestDelta.sequence` é produzido, porém o componente não mantém `lastAppliedSequence`.

**Correção:**

```ts
if (delta.sequence <= lastAppliedSequenceRef.current) return
```

Sequence live não deve invalidar página histórica; usar contadores separados.

---

### P1.12 — Contrato de timezone é inconsistente

Existem vários parsers:

- `useRealMarketData.normalizeTimestamp`;
- `symbolMap.tsToUtc`;
- `lib/normalizeCandles`;
- `normalizerUtils.toUnix`;
- `smcRenderUtils.toUnix`;
- helpers locais do chart.

Alguns removem `Z`; outros interpretam string sem timezone no fuso local.

**Correção:** um único `parseMarketTimestamp()` com contrato explícito, por exemplo:

```text
API sempre UTC ISO 8601 com Z
ou
API sempre America/Sao_Paulo e conversão declarada
```

---

### P1.13 — SMC aberto não acompanha o último candle

`useSmcPerType.setLastCandle()` apenas altera uma ref. O efeito normalizador depende somente de `zones`.

Assim, zonas abertas podem continuar terminando no candle antigo.

**Correção:** incluir `lastCandleTime` como estado/dependência ou derivar com `useMemo`.

---

### P1.14 — Polling global do TickerTape compete com o chart

O tape global:

- busca `/api/assets`;
- tenta até três timeframes por ativo;
- roda a cada 30 segundos;
- não aborta nem impede sobreposição;
- é montado até nas telas que não precisam dele.

**Correção:** limitar às páginas necessárias, usar cache e scheduler sem sobreposição.

---

## 5. Arquitetura alvo

### 5.1 Fonte de verdade

```text
backend
  ├── snapshot inicial
  ├── cursor histórico
  └── delta live

useRealMarketData
  ├── valida HTTP
  ├── normaliza
  ├── controla concorrência
  └── devolve dados tipados

CandlestickChart
  ├── setData inicial
  ├── update live
  ├── prepend histórico
  ├── range/realtime
  └── primitives
```

### 5.2 Contratos

```ts
interface HistoricalSeriesPage {
  candles: LWCandle[]
  ema20: LWPoint[]
  ema200: LWPoint[]
  rsi: LWPoint[]
  hasMore: boolean
  nextBefore: UTCTimestamp | null
  queryKey: string
  requestId: number
}

interface HistoryLoadState {
  status: 'idle' | 'loading' | 'error' | 'exhausted'
  error: Error | null
}
```

Remover `addedCount` do retorno do hook; ele deve ser calculado após o merge real.

---

## 6. Plano de implementação

### Fase 0 — Instrumentação e confirmação do backend

Adicionar logs somente em desenvolvimento:

```text
[history:trigger] barsBefore oldestTime
[history:request] before requestId queryKey
[history:http] status duration
[history:received] count oldest newest hasMore
[history:merge] received uniqueAdded total
[history:apply] rangeBefore rangeAfter
[history:error] stage message
```

Confirmar se o backend já suporta cursor. Se não suportar, implementar antes do frontend.

**Gate:** uma chamada manual deve retornar candles estritamente anteriores ao cursor.

---

### Fase 1 — Normalização temporal única

Criar:

```text
src/lib/marketTime.ts
src/lib/marketSeries.ts
```

Funções:

```ts
parseMarketTimestamp
normalizeCandles
normalizeLineData
mergeOlderCandles
mergeOlderLineData
applyCandleDelta
applyLineDelta
```

Eliminar implementações duplicadas.

**Gate:** testes de segundos, milissegundos, ISO com `Z`, ISO com offset e string inválida.

---

### Fase 2 — Reescrever `loadOlder()`

Nova assinatura:

```ts
loadOlder(
  before: UTCTimestamp,
  options?: { signal?: AbortSignal }
): Promise<HistoricalSeriesPage>
```

Requisitos:

- cursor explícito;
- `historyInFlightRef`;
- AbortController;
- `resp.ok`;
- payload validado;
- erro não altera `hasMore`;
- `hasMore` somente pela API;
- requestId separado do sequence live;
- página normalizada;
- nenhum merge oculto em `allCandlesRef`.

O hook deve retornar o lote; o componente decide o merge.

---

### Fase 3 — Merge histórico atômico

No chart:

1. capturar range imediatamente antes da aplicação;
2. calcular quatro merges normalizados;
3. obter `actualAddedCount`;
4. validar ordem estritamente crescente;
5. aplicar séries;
6. atualizar refs;
7. atualizar mapa de labels por timestamp;
8. restaurar range com `actualAddedCount`;
9. atualizar primitives.

Adicionar `catch` e estado de erro.

**Gate:** página sobreposta não duplica nem move o candle central.

---

### Fase 4 — Consolidar logical range handler

Manter somente uma subscription:

```ts
handleLogicalRangeChange(range) {
  requestPrimitiveRedraw()
  updateRealtime(range)
  maybeLoadOlder(range)
}
```

Usar refs para callbacks mutáveis e evitar resubscription desnecessária.

---

### Fase 5 — Hardening do live

No polling:

- normalizar lote recente;
- ordenar crescente;
- deduplicar;
- atualizar `currentLastTime` durante o loop;
- fallback independente para cada indicador;
- atualizar `lwRSI`;
- validar `sequence`;
- usar `cancelled/generation` local;
- backoff de rede;
- não executar no Replay.

---

### Fase 6 — Corrigir snapshot inicial

- validar HTTP;
- normalizar raw canônico;
- ordenar `allCandlesRef`;
- definir offset apenas se temporariamente mantido;
- usar mapa timestamp → label;
- marcar identity somente depois de `setData()` bem-sucedido;
- limpar séries auxiliares explicitamente se lote vazio;
- não misturar resposta parcial de identidade antiga.

---

### Fase 7 — Corrigir Replay

- sincronizar `currentIndex` quando a carga terminar;
- fetch por intervalo no backend;
- normalizar e ordenar;
- AbortController/generation;
- avanço: `update`;
- recuo: `pop(1)`;
- salto: `setData`;
- preservar pan/zoom;
- corrigir índices para `0..total-1`.

---

### Fase 8 — Tornar o chart controlado

Adicionar props live:

```ts
symbol?: string
timeframe?: Timeframe
defaultSymbol?: string
defaultTimeframe?: Timeframe
onSymbolChange?: ...
onTimeframeChange?: ...
showInternalToolbar?: boolean
```

Aplicar em `CustomerChartPage` para garantir que gráfico e painel usam a mesma identidade.

Remover fetches duplicados de candles em `useCustomerChart` se não forem usados.

---

### Fase 9 — Corrigir SMC

- parser temporal único;
- remover logs de normalizadores;
- recalcular zonas abertas quando último candle muda;
- validar IDs compostos;
- testar pan/zoom com budgets;
- impedir extensões artificiais quando `display_to` estiver ausente sem regra de negócio.

---

### Fase 10 — Performance e bundle

- `React.lazy()` nas rotas;
- lazy import do Plotly;
- remover rota/componente legado se não usado;
- avaliar sourcemap apenas em ambiente de diagnóstico;
- retirar TickerTape de rotas desnecessárias;
- compartilhar cache de ativos.

**Gate:** bundle inicial substancialmente menor e nenhum request duplicado ao abrir o gráfico.

---

### Fase 11 — Testes automatizados

Adicionar Vitest e Testing Library.

#### Unitários

- normalização temporal;
- OHLC;
- merge candles;
- merge indicadores;
- cursor;
- actualAddedCount;
- sequence;
- timezone;
- Replay index.

#### Hook

- load inicial;
- HTTP 500;
- histórico vazio válido;
- erro transitório;
- abort;
- troca de identidade;
- polling sem sobreposição;
- live durante history.

#### Componente

- pan dispara histórico;
- prepend preserva range;
- indicadores recebem histórico;
- botão Voltar ao vivo;
- Replay forward/back;
- Customer controlled symbol.

---

### Fase 12 — Testes manuais obrigatórios

1. abrir com 500 candles;
2. pan até `barsBefore < 50`;
3. observar request com `before`;
4. confirmar página anterior;
5. confirmar aumento real de total;
6. confirmar candle central imóvel;
7. repetir três páginas;
8. simular HTTP 500 e tentar novamente;
9. receber candle live enquanto histórico está pendente;
10. trocar ativo durante request;
11. confirmar fim de histórico verdadeiro;
12. validar tooltip no candle mais antigo;
13. validar EMA 20/200 e RSI na borda;
14. validar Replay;
15. validar página do cliente.

---

## 7. Critérios de aceite

- [ ] Histórico usa cursor, não offset.
- [ ] HTTP status é validado.
- [ ] Erro não vira fim de histórico.
- [ ] `loadOlder` pode ser abortado.
- [ ] Candles históricos são únicos e ordenados.
- [ ] EMA 20 histórica é única e ordenada.
- [ ] EMA 200 histórica é única e ordenada.
- [ ] RSI histórico é único e ordenado.
- [ ] `addedCount` é delta real.
- [ ] Range é preservado.
- [ ] Tooltip antigo tem timestamp correto.
- [ ] Um único logical-range listener.
- [ ] Polling não sobrepõe nem ressuscita.
- [ ] `lwRSI` atualiza.
- [ ] Sequence live é aplicado.
- [ ] Replay avança e recua.
- [ ] Cliente mostra o ativo selecionado.
- [ ] SMC aberto acompanha o último candle.
- [ ] Lint passa sem desativar regras.
- [ ] Testes existem e passam.
- [ ] Build passa.
- [ ] Infinite scroll passa visualmente.

---

## 8. Ordem de execução recomendada

```text
1. Backend cursor before
2. marketTime + marketSeries canônicos
3. useRealMarketData.loadOlder
4. merge/prepend atômico
5. logical range único
6. polling live
7. snapshot inicial
8. Replay
9. chart controlado / CustomerChart
10. SMC
11. performance
12. testes e validação
```

Não iniciar otimizações cosméticas antes de concluir os itens 1 a 5.

---

## 9. Auditoria arquivo por arquivo

Legenda:

- **P0:** bloqueia função central;
- **P1:** erro alto ou risco de inconsistência;
- **P2:** dívida relevante;
- **P3:** melhoria;
- **OK:** nenhum achado relevante no escopo.

| Arquivo | Prioridade | Lint | Resultado |
|---|---:|---:|---|
| `.gitignore` | **OK** | 0 | Ignora `node_modules`, `dist` e arquivos locais. O ZIP ainda trouxe artefatos gerados, mas isso não é defeito do arquivo. |
| `README.md` | **P3** | 0 | Permanece como documentação genérica/incompleta. Não descreve arquitetura do gráfico, contratos da API, Replay, paginação nem procedimento de testes. |
| `eslint.config.js` | **P2** | 0 | Configuração válida e útil; o projeto não passa nela: 123 erros e 13 avisos no `src`. Manter o gate e corrigir o código, não desativar regras. |
| `index.html` | **OK** | 0 | Sem defeito funcional do gráfico detectado. Fontes externas devem ser avaliadas sob CSP/offline, fora do gate atual. |
| `package-lock.json` | **OK** | 0 | Lockfile presente. A versão efetivamente auditada de `lightweight-charts` é 5.2.0. |
| `package.json` | **P1** | 0 | Build existe, mas não há script de testes. Plotly é dependência pesada e está importado de forma eager; o bundle principal atingiu 5,25 MB minificado. |
| `public/.well-known/assetlinks.json` | **P3** | 0 | Sem impacto no gráfico. Validar fingerprint e package name no deploy Android real. |
| `public/favicon.svg` | **OK** | 0 | Sem impacto no gráfico. |
| `public/icons.svg` | **P3** | 0 | Não foi encontrada referência no `src`; confirmar uso externo ou remover como asset órfão. |
| `src/App.tsx` | **P1** | 0 | Monta `TickerTape` globalmente em login, landing, admin e área do cliente. Isso gera polling de candles em segundo plano enquanto o gráfico também consulta a API. |
| `src/assets/hero.png` | **OK** | 0 | Asset visual; sem impacto no gráfico. |
| `src/assets/react.svg` | **P3** | 0 | Asset de template aparentemente sem uso. Remover se confirmado. |
| `src/assets/vite.svg` | **P3** | 0 | Asset de template aparentemente sem uso. Remover se confirmado. |
| `src/components/BackgroundEffects.tsx` | **P1** | 0 | `TickerTape` usa `setInterval` sem trava/abort e pode disparar várias consultas por ativo a cada 30 s. Pode competir com o polling e o histórico do gráfico. |
| `src/components/CandlestickChart.tsx` | **P0** | E27/W7 | Infinite scroll incompleto/instável: duas subscriptions de logical range; `loadOlder()` sem cursor; indicadores históricos não são ordenados/deduplicados; cadeia Promise sem `catch`; `addedCount` não é delta real; timestamps não recebem prepend; Replay não atualiza depois da carga inicial; 27 erros e 7 avisos de lint. |
| `src/components/PlotlyCandlestickChart.tsx` | **P2** | E21/W2 | Componente legado com 21 erros e 2 avisos de lint. Import eager mantém Plotly no bundle principal. Remover ou carregar a rota com `lazy()`. |
| `src/components/ReplayControls.tsx` | **P2** | 0 | Componente aparentemente não utilizado. Limites de índice usam `total` em vez de `total - 1`, criando risco de off-by-one caso volte a ser usado. |
| `src/components/ReplayDatePicker.tsx` | **P2** | 0 | Parser e valores padrão são frágeis; datas parciais podem ser normalizadas de forma inesperada. Não é a causa do pan, mas afeta Replay. |
| `src/components/admin/AdminField.tsx` | **OK** | 0 | Componente simples; sem achado funcional do gráfico. |
| `src/components/admin/AdminModal.tsx` | **OK** | 0 | Sem achado funcional do gráfico; revisar foco/teclado em evolução de acessibilidade. |
| `src/components/admin/adminStyles.ts` | **OK** | 0 | Constantes visuais; sem achado funcional. |
| `src/components/admin/adminTypes.ts` | **P3** | E1/W0 | Contém `any` e falha no lint. Tipar payloads administrativos. |
| `src/components/chart/smc/normalizers/bosNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/bprNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/chochNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/fvgNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/index.ts` | **OK** | 0 | Barrel de exports consistente; sem defeito direto. |
| `src/components/chart/smc/normalizers/liqNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/normalizerUtils.ts` | **P1** | 0 | Remove o sufixo `Z` antes de `Date.parse`, alterando semântica UTC. Usa fallback de +1 h/+1 dia para zonas sem fim, podendo desenhar extensões artificiais. |
| `src/components/chart/smc/normalizers/obNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/normalizers/pdhPdlNormalizer.ts` | **P2** | 0 | Sem erro de lint, mas herda o parser de timezone e a extensão artificial de tempo do utilitário comum. |
| `src/components/chart/smc/normalizers/sessionNormalizer.ts` | **P2** | 0 | Sem erro de lint, mas herda parser de timezone e depende de nomes de sessão por string. |
| `src/components/chart/smc/normalizers/swingNormalizer.ts` | **P2** | 0 | Normalizador funcional, mas contém `console.log` em caminho quente. Depende do parser de tempo compartilhado que remove `Z`; remover logs ou protegê-los por modo de desenvolvimento. |
| `src/components/chart/smc/primitives/BosPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/BprPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/ChochPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/FvgPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/LiquidityPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/ObPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/PdhPdlPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/SessionPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/primitives/SwingPrimitive.ts` | **P2** | 0 | Implementação válida, porém repete a mesma infraestrutura em nove arquivos e cria um novo renderer em cada chamada de `renderer()`. Considerar base genérica e medir alocações antes de otimizar. |
| `src/components/chart/smc/renderers/BosRenderer.ts` | **P2** | E2/W0 | Contadores `drawn/skipped` não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/BprRenderer.ts` | **P2** | E3/W0 | Contadores de debug não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/ChochRenderer.ts` | **P2** | E2/W0 | Contadores `drawn/skipped` não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/FvgRenderer.ts` | **P2** | E3/W0 | Contadores de debug não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/LiquidityRenderer.ts` | **P2** | E2/W0 | Contadores `drawn/skipped` não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/ObRenderer.ts` | **P2** | E3/W0 | Contadores de debug não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/PdhPdlRenderer.ts` | **P3** | 0 | Sem erro de lint; validar clipping e budgets visualmente. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/SessionRenderer.ts` | **P3** | 0 | Sem erro de lint; validar custo de preenchimento de sessões em pan/zoom. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/renderers/SwingRenderer.ts` | **P2** | E2/W0 | Contadores `drawn/skipped` não utilizados; falha no lint. Herda o parser/coordenadas compartilhados; adicionar testes visuais. |
| `src/components/chart/smc/smcNormalize.ts` | **P2** | 0 | Deduplica somente por `id`; confirmar unicidade global entre tipos/execuções. Herda problemas de timezone dos normalizadores. |
| `src/components/chart/smc/smcRenderUtils.ts` | **P1** | E1/W0 | Duplica parser que remove `Z`. Parâmetro `_width` não é usado e falha no lint. Centralizar conversão temporal. |
| `src/components/chart/smc/smcStyle.ts` | **OK** | 0 | Constantes e helpers visuais coerentes. |
| `src/components/chart/smc/smcTypes.ts` | **OK** | 0 | Tipos úteis; manter como contrato central dos primitives. |
| `src/components/chart/smc/smcVisibility.ts` | **OK** | 0 | Seleção e budgets são claros; adicionar testes de janela/limites. |
| `src/contexts/AuthContext.tsx` | **P2** | E2/W0 | Falha em regras de hooks/fast refresh e pode causar rerenders globais; sem relação direta com o pan. |
| `src/hooks/useCredits.ts` | **P2** | E2/W0 | Polling pode acumular intervalos em chamadas repetidas e não trata todos os estados terminais. Fora do núcleo do gráfico. |
| `src/hooks/useCustomerChart.ts` | **P1** | 0 | Faz consultas duplicadas em paralelo ao gráfico, mas `CandlestickChart` não usa os dados selecionados. `opportunity` nunca é preenchida, então a ação de IA permanece desabilitada. |
| `src/hooks/useRealMarketData.ts` | **P0** | E16/W3 | Causa central do histórico: paginação por `offset`, sem `before`; offset fixado em 500 e sujeito a drift; sem `resp.ok`/abort no histórico; erro retorna `hasMore:false`; `addedCount` incorreto; indicadores calculados por página isolada; polling não normaliza lote recente, não atualiza `lwRSI`, fallback parcial e scheduler pode ressuscitar após cleanup. |
| `src/hooks/useReplayData.ts` | **P1** | E14/W0 | Sem abort/generation/status HTTP; busca fixa de 2.000 candles; normalização/ordenação fraca; cálculo de `startIndex` incorreto quando `from` está após todos os dados; 14 erros de lint. |
| `src/hooks/useSmcPerType.ts` | **P1** | E1/W0 | `setLastCandle()` altera apenas ref e não recalcula zonas abertas. Logs de produção e estado derivado em efeito. As zonas podem não estender até o candle live. |
| `src/hooks/useUserAssets.ts` | **P2** | 0 | Sem erro de build; avaliar cache compartilhado para evitar novas consultas quando a página do gráfico já carrega ativos. |
| `src/index.css` | **OK** | 0 | Sem erro funcional do gráfico detectado na auditoria estática. |
| `src/lib/api.ts` | **P2** | 0 | Wrapper checa HTTP, mas não expõe `AbortSignal` de forma conveniente. Padronizar todos os fetches do gráfico por ele ou por um cliente específico. |
| `src/lib/normalizeCandles.ts` | **P1** | E1/W0 | Normalizador duplicado e permissivo: data inválida pode produzir `NaN` e volume não é validado. Consolidar com o normalizador canônico do hook. |
| `src/lib/symbolMap.ts` | **P1** | 0 | Conversão de timestamp sem contrato explícito de timezone; strings sem offset são interpretadas no fuso do navegador. Mapeamento de símbolo é assimétrico. |
| `src/main.tsx` | **OK** | 0 | Bootstrap simples; sem defeito direto detectado. |
| `src/pages/AdminAssetsPage.tsx` | **P3** | E2/W0 | Fetches administrativos sem cancelamento consistente; lint acusa `any` e atualização de estado em efeito. |
| `src/pages/AdminConfigPage.tsx` | **P2** | 0 | Sem erro estático relevante; fora do fluxo do gráfico. |
| `src/pages/AdminCreditosPage.tsx` | **P3** | E0/W1 | Aviso de dependência de hook; revisar efeito para evitar dados obsoletos. |
| `src/pages/AdminEvidence.tsx` | **P2** | 0 | Sem erro de lint; fetch sem AbortController. Fora do núcleo do gráfico. |
| `src/pages/AdminEvidenceDetail.tsx` | **P3** | E1/W0 | Uso de `any`; tipar resposta da evidência. |
| `src/pages/AdminLicencasPage.tsx` | **P3** | E1/W0 | Intervalo/fetch administrativo e atualização de estado em efeito; não afeta diretamente o chart. |
| `src/pages/AdminMarketsPage.tsx` | **P3** | E2/W0 | Uso de `any` e state em efeito; padronizar cliente HTTP. |
| `src/pages/AdminPlanosPage.tsx` | **P3** | E1/W0 | State em efeito; padronizar carregamento. |
| `src/pages/AdminProdutosPage.tsx` | **P2** | 0 | Sem erro de lint; fetch simples sem abort. |
| `src/pages/AdminSystemHealth.tsx` | **P2** | E3/W0 | `Date.now()` durante render, mutação/imutabilidade e `any`; 3 erros de lint. |
| `src/pages/AdminUsuariosPage.tsx` | **P2** | E1/W0 | State em efeito e polling/fetch; risco de sobreposição fora do gráfico. |
| `src/pages/AdminVendasPage.tsx` | **P2** | 0 | Sem erro de lint; fetch simples sem abort. |
| `src/pages/AdminVpsMonitorPage.tsx` | **P2** | E2/W0 | `Date.now()` durante render e variável não usada; 2 erros de lint. |
| `src/pages/AlertasPage.tsx` | **P3** | 0 | Sem falha de lint registrada no arquivo, mas há tipagem frouxa/dados mockados; fora do gráfico. |
| `src/pages/AssetSelectionPage.tsx` | **P2** | 0 | Sem erro estático relevante; fora do fluxo do chart. |
| `src/pages/ChartPage.tsx` | **P2** | 0 | Passa callbacks `onToggleSmc`/`onToggleOverlay` que o chart não consome. API de props está inconsistente, embora os toggles externos funcionem pelo estado controlado. |
| `src/pages/CreditsPage.tsx` | **P2** | 0 | Sem erro estático relevante; fora do fluxo do chart. |
| `src/pages/CustomerArea.tsx` | **P2** | 0 | Sem erro estático relevante; roteamento da área do cliente. |
| `src/pages/CustomerChartPage.tsx` | **P0** | 0 | O seletor do cliente controla `ticker`/`timeframe` do painel, mas renderiza `<CandlestickChart />` sem props. O gráfico mantém WINFUT/M5 interno e pode mostrar ativo diferente do painel. |
| `src/pages/CustomerHistory.tsx` | **P3** | 0 | Fetch sem AbortController; fora do núcleo do gráfico. |
| `src/pages/CustomerOppDetail.tsx` | **P2** | E6/W0 | 5 usos de `any` e state em efeito; 6 erros de lint. |
| `src/pages/Dashboard.tsx` | **P2** | 0 | Todas as páginas e Plotly são importados de forma eager. Implementar `React.lazy` por rota para reduzir bundle e tempo até o gráfico. |
| `src/pages/IndicadoresPage.tsx` | **P2** | 0 | Sem erro estático relevante; não conectado ao fluxo atual do Lightweight Charts. |
| `src/pages/Landing.tsx` | **P2** | 0 | Sem erro de lint; fetch/efeito fora do gráfico. |
| `src/pages/Login.tsx` | **P2** | 0 | Sem erro de lint; fora do gráfico. |
| `src/pages/Register.tsx` | **P2** | 0 | Sem erro estático relevante; fora do gráfico. |
| `src/pages/ReplayPage.tsx` | **P1** | E1/W0 | `handleLoad` aplica `startIndex` antigo antes da nova carga; o efeito de auto-stop chama state dentro de efeito; em conjunto com o chart atual, Replay tende a congelar após o snapshot inicial. |
| `src/pages/WatchlistPage.tsx` | **P2** | 0 | Sem erro de lint; pode compartilhar cache de ativos com o gráfico no futuro. |
| `tsconfig.app.json` | **P2** | 0 | Type-check passa, porém `strict` não está habilitado explicitamente e `skipLibCheck` está ativo. A grande quantidade de `any` reduz a proteção do compilador. |
| `tsconfig.json` | **OK** | 0 | Estrutura de projetos TypeScript válida; sem achado direto no gráfico. |
| `tsconfig.node.json` | **OK** | 0 | Sem achado direto no gráfico. |
| `vite.config.ts` | **P2** | 0 | Build gera source map de produção de 12,79 MB e não há code splitting explícito. Avaliar `sourcemap` por ambiente e rotas lazy. |

---

## 10. Gates finais

```text
GATE_BUILD: PASS
GATE_TYPECHECK: PASS
GATE_LINT: FAIL
GATE_TESTS: FAIL_AUSENTES
GATE_LIVE: PARCIAL
GATE_HISTORY: FAIL
GATE_REPLAY: FAIL_PROVÁVEL
GATE_CUSTOMER_IDENTITY: FAIL
GATE_PRODUÇÃO: NO_GO
```

### Condição para `GO`

O projeto só deve receber `GATE_PRODUÇÃO: GO` quando:

1. o cursor histórico estiver validado;
2. três páginas forem carregadas por pan sem salto;
3. erro transitório permitir retry;
4. live e history coexistirem sem perda;
5. Replay funcionar;
6. gráfico do cliente respeitar o seletor;
7. lint e testes passarem.

---

## 11. Conclusão

A implementação contém uma base correta — Lightweight Charts 5.2.0, `update()` live, detecção por `barsInLogicalRange`, refs canônicos e tentativa de preservação de faixa — mas o fluxo histórico ainda não é confiável.

A causa não é um único `if`. O defeito resulta da combinação de:

```text
offset instável
+ erro silencioso convertido em hasMore=false
+ falta de resp.ok
+ indicadores sem sort/dedupe
+ Promise sem catch
+ addedCount incorreto
+ labels não prependidos
```

A correção deve seguir o plano em fases, começando pelo contrato de cursor e pelo merge histórico atômico.
