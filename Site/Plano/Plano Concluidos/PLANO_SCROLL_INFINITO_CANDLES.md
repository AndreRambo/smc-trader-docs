# Plano Executivo — Correção do Carregamento Dinâmico de Candles

## 1. Objetivo

Corrigir o gráfico baseado em TradingView Lightweight Charts para que ele:

1. carregue um lote inicial de candles sem reposicionar o gráfico repetidamente;
2. atualize o candle aberto e adicione candles novos sem usar `setData()` no polling;
3. carregue histórico automaticamente quando o usuário fizer pan para a esquerda;
4. preserve exatamente a posição visual ao inserir candles antigos;
5. permita zoom e pan nativos sem limites artificiais;
6. continue recebendo dados live enquanto o usuário analisa o histórico;
7. mostre um botão **Voltar ao vivo** somente quando o usuário estiver afastado do candle atual;
8. não gere chamadas duplicadas, candles duplicados, saltos, flicker ou vazamentos de listeners;
9. funcione corretamente ao trocar ativo ou timeframe.

---

## 2. Escopo

### Arquivos principais

| Arquivo | Responsabilidade após a correção |
|---|---|
| `useRealMarketData.ts` | Buscar lote inicial, fazer polling incremental, carregar histórico e controlar estados de rede |
| `CandlestickChart.tsx` | Criar o chart, aplicar `setData()` inicial, executar `update()` live, controlar pan, zoom e faixa visível |
| Arquivo de tipos existente ou novo | Definir tipos normalizados para candle, resposta da API e estados |
| Cliente de API existente | Expor chamadas separadas para dados iniciais, dados recentes e histórico anterior |

### Fora do escopo

- redesenhar o visual geral do dashboard;
- alterar regras SMC, Elliott ou Wyckoff;
- criar WebSocket nesta fase;
- remover candles antigos da memória;
- impor zoom mínimo ou máximo artificial;
- atualizar a versão do Lightweight Charts sem necessidade comprovada.

---

## 3. Diagnóstico provável do problema atual

Antes de alterar o código, auditar e registrar:

1. onde `setData()` é chamado;
2. se ele é executado a cada polling ou mudança de estado React;
3. se o chart ou a série são recriados quando `candles` muda;
4. se existe `fitContent()`, `resetTimeScale()`, `scrollToRealTime()` ou `setVisibleLogicalRange()` executado após cada atualização;
5. se o handler de pan usa apenas `range.from < 50`;
6. se existem múltiplos `setInterval`;
7. se React Strict Mode está duplicando intervalos ou subscriptions por falta de cleanup;
8. se candles chegam fora de ordem ou duplicados;
9. se o timestamp está em milissegundos quando o gráfico espera segundos;
10. se a troca de ativo/timeframe mantém respostas antigas ainda em andamento;
11. se overlays são recalculados com `setData()` e acabam alterando a escala temporal;
12. se o zoom limit atual chama repetidamente `setVisibleLogicalRange()` e entra em conflito com o gesto do usuário.

### Entrega da auditoria

Criar um pequeno bloco de comentários ou relatório técnico contendo:

- chamadas atuais de `setData()`;
- chamadas atuais de `update()`;
- criação e destruição do chart;
- criação e destruição do polling;
- criação e destruição do listener de faixa visível;
- comportamento observado antes da correção.

---

## 4. Regras obrigatórias de implementação

### 4.1 Uso de `setData()`

`setData()` pode ser usado somente nestes casos:

1. carregamento inicial;
2. troca de ativo;
3. troca de timeframe;
4. inserção de lote histórico anterior;
5. ressincronização completa após erro comprovado de consistência.

Não usar `setData()`:

- a cada polling;
- a cada renderização React;
- para atualizar apenas o último candle;
- para adicionar um único candle novo;
- dentro de um efeito que dependa do array completo de candles live.

### 4.2 Uso de `update()`

Usar `series.update(candle)` para:

- substituir o candle aberto quando o timestamp for igual ao último candle;
- adicionar um candle novo quando o timestamp for maior;
- adicionar mais de um candle perdido, em ordem crescente, quando o polling retornar um pequeno intervalo.

Não chamar `update()` com timestamp menor que o último candle, salvo uma política explícita de correção histórica.

### 4.3 Ordenação e unicidade

Todo lote recebido deve ser:

1. validado;
2. normalizado;
3. ordenado por `time` crescente;
4. deduplicado por `time`;
5. rejeitado se contiver OHLC inválido.

Chave canônica:

```ts
const candleKey = Number(candle.time);
```

Regras mínimas de OHLC:

```text
high >= open
high >= close
low <= open
low <= close
high >= low
todos os valores são finitos
```

### 4.4 Fonte de verdade

Manter uma fonte de verdade imperativa para os dados já aplicados ao gráfico:

```ts
const candlesRef = useRef<CandlestickData[]>([]);
const lastCandleRef = useRef<CandlestickData | null>(null);
```

Evitar depender do fechamento antigo de um state React dentro de intervalos e callbacks.

---

## 5. Contrato recomendado do hook `useRealMarketData.ts`

O hook deve expor, no mínimo:

```ts
interface UseRealMarketDataResult {
  initialCandles: CandlestickData[];
  latestUpdates: CandlestickData[];
  loadOlder: (before: number) => Promise<CandlestickData[]>;
  hasMoreHistory: boolean;
  isInitialLoading: boolean;
  isPolling: boolean;
  isLoadingOlder: boolean;
  error: Error | null;
  retryInitial: () => Promise<void>;
}
```

### Observação

`latestUpdates` pode conter mais de um candle porque:

- o navegador pode ter ficado suspenso;
- uma chamada pode falhar;
- pode haver atraso de rede;
- mais de um candle pode ter fechado desde o último polling.

Não assumir que cada polling retorna exatamente um candle.

---

## 6. Fase 1 — Normalização dos candles

Criar uma função única e reutilizável:

```ts
normalizeCandles(rawCandles): CandlestickData[]
```

Ela deve:

1. converter o timestamp para Unix em segundos;
2. impedir mistura entre segundos e milissegundos;
3. converter OHLC para `number`;
4. descartar valores `NaN`, `Infinity` ou incompletos;
5. ordenar por timestamp crescente;
6. deduplicar por timestamp, mantendo a versão mais recente recebida;
7. validar OHLC;
8. retornar array imutável.

### Regra de timestamp

Se a API retorna epoch em milissegundos:

```ts
time = Math.floor(timestampMs / 1000);
```

Se já retorna segundos, não dividir novamente.

Centralizar essa decisão em apenas um lugar.

---

## 7. Fase 2 — Carregamento inicial

### Comportamento

Ao abrir o gráfico ou trocar ativo/timeframe:

1. cancelar requests anteriores;
2. limpar erros anteriores;
3. definir `isInitialLoading = true`;
4. buscar entre 300 e 1.000 candles, conforme o endpoint existente;
5. normalizar o lote;
6. aplicar `series.setData(initialCandles)` uma única vez;
7. preencher `candlesRef` e `lastCandleRef`;
8. mostrar inicialmente os últimos 100 a 150 candles;
9. iniciar o polling somente depois da carga inicial válida.

### Faixa inicial

Não usar `fitContent()` como comportamento final, pois ele mostra todo o histórico carregado.

Aplicar uma faixa lógica inicial:

```ts
const visibleCount = 120;
const total = initialCandles.length;

timeScale.setVisibleLogicalRange({
  from: Math.max(0, total - visibleCount),
  to: total - 1 + rightOffsetBars,
});
```

Executar isso somente:

- na primeira carga;
- na troca de ativo;
- na troca de timeframe.

Não executar após polling nem após pan.

---

## 8. Fase 3 — Polling incremental

### Intervalo

Usar 10 segundos inicialmente:

```text
POLL_INTERVAL_MS = 10_000
```

### Não usar `setInterval` ingênuo

Um `setInterval(loadData, 10000)` pode iniciar nova chamada antes da anterior terminar.

Preferir uma destas estratégias:

1. `setTimeout` recursivo depois que a chamada termina;
2. `setInterval` com trava `pollInFlightRef`;
3. biblioteca de query com deduplicação configurada.

### Fluxo recomendado

```text
aguardar 10s
→ verificar se componente continua montado
→ verificar se não existe polling em andamento
→ buscar candles recentes
→ normalizar
→ comparar com o último timestamp local
→ emitir somente atualizações necessárias
→ agendar próxima execução
```

### Consulta recomendada

Se a API permitir:

```text
GET /candles?asset=WINFUT&timeframe=M5&after=<last_timestamp>
```

Se não permitir, buscar os últimos 3 a 10 candles:

```text
GET /candles?asset=WINFUT&timeframe=M5&limit=5
```

Evitar baixar 500 candles a cada 10 segundos.

### Algoritmo de atualização

Para cada candle recebido, em ordem crescente:

```text
time < último time local
    ignorar, salvo política explícita de correção histórica

time == último time local
    substituir no candlesRef
    executar series.update(candle)

time > último time local
    adicionar ao candlesRef
    atualizar lastCandleRef
    executar series.update(candle)
```

### Erro de polling

Em erro:

- manter o gráfico e os dados atuais;
- não limpar a série;
- registrar o erro;
- continuar tentando;
- aplicar backoff simples após erros consecutivos, por exemplo 10s, 20s, 30s, máximo 60s;
- voltar para 10s após sucesso.

---

## 9. Fase 4 — Integração live em `CandlestickChart.tsx`

O chart e a série devem ser criados uma vez por montagem, não a cada mudança de candles.

### Refs obrigatórias

```ts
const chartRef = useRef<IChartApi | null>(null);
const seriesRef = useRef<ISeriesApi<"Candlestick"> | null>(null);
const candlesRef = useRef<CandlestickData[]>([]);
const isLoadingOlderRef = useRef(false);
const hasMoreHistoryRef = useRef(true);
const isDisposedRef = useRef(false);
```

### Efeito de criação

O efeito que cria o chart deve depender somente de elementos realmente estruturais.

No cleanup:

1. remover listener de resize;
2. remover subscription da faixa lógica;
3. cancelar requests ligados ao chart;
4. marcar `isDisposedRef`;
5. chamar `chart.remove()`;
6. zerar refs.

### Efeito de atualização live

O efeito que recebe `latestUpdates` deve:

1. não chamar `setData()`;
2. ordenar e deduplicar o pequeno lote;
3. aplicar cada item com `series.update()`;
4. atualizar `candlesRef`;
5. não chamar `fitContent()`;
6. não chamar `scrollToRealTime()` automaticamente;
7. não modificar faixa visível manualmente.

---

## 10. Fase 5 — Configuração correta de pan e zoom

Configurar:

```ts
timeScale: {
  timeVisible: true,
  secondsVisible: false,
  shiftVisibleRangeOnNewBar: true,
  rightOffset: 5,
}
```

Manter pan e zoom nativos:

```ts
handleScroll: {
  mouseWheel: true,
  pressedMouseMove: true,
  horzTouchDrag: true,
  vertTouchDrag: true,
},
handleScale: {
  mouseWheel: true,
  pinch: true,
  axisPressedMouseMove: true,
},
```

### Remover completamente

- efeito de zoom limit;
- clamp manual de `range.from` e `range.to`;
- chamadas recorrentes a `setVisibleLogicalRange()`;
- qualquer lógica que reverta o zoom do usuário;
- qualquer `fitContent()` executado após a inicialização;
- `fixLeftEdge: true` enquanto houver histórico para carregar.

### Observação

`shiftVisibleRangeOnNewBar: true` deve acompanhar candles novos apenas quando o último candle estiver visível. Quando o usuário estiver no histórico, a posição não deve ser arrastada para a direita.

---

## 11. Fase 6 — Infinite scroll para a esquerda

### Não usar

```ts
if (range.from < 50) {
  loadOlder();
}
```

Esse teste é frágil porque os índices lógicos:

- podem ser negativos;
- mudam quando dados antigos são inseridos;
- não representam diretamente a quantidade de candles disponíveis antes da tela.

### Usar

```ts
const barsInfo = series.barsInLogicalRange(range);

if (
  barsInfo !== null &&
  barsInfo.barsBefore < HISTORY_PREFETCH_THRESHOLD
) {
  void requestOlderCandles();
}
```

Configuração inicial:

```text
HISTORY_PREFETCH_THRESHOLD = 50
HISTORY_PAGE_SIZE = 500
```

### Guardas obrigatórias

Não carregar histórico quando:

- `range === null`;
- não existir série;
- não houver candles;
- `isLoadingOlderRef.current === true`;
- `hasMoreHistoryRef.current === false`;
- ativo/timeframe tiver mudado;
- componente tiver sido desmontado.

### Timestamp de paginação

Usar o candle mais antigo atualmente carregado:

```ts
const oldestTime = Number(candlesRef.current[0].time);
const older = await loadOlder(oldestTime);
```

O backend deve retornar apenas candles com:

```text
time < oldestTime
```

Nunca incluir repetidamente o candle de borda; mesmo assim, o frontend deve deduplicar.

---

## 12. Fase 7 — Preservação da posição durante prepend

Esse é um requisito crítico para eliminar o “salto” do gráfico.

### Fluxo

Antes de inserir candles antigos:

```ts
const previousRange = chart.timeScale().getVisibleLogicalRange();
```

Depois:

1. normalizar o lote;
2. remover candles já existentes;
3. contar quantos candles realmente novos foram adicionados;
4. criar `merged = [...olderUnique, ...current]`;
5. ordenar e deduplicar;
6. executar `series.setData(merged)`;
7. atualizar `candlesRef`;
8. restaurar a faixa deslocada pela quantidade adicionada.

```ts
if (previousRange && addedCount > 0) {
  chart.timeScale().setVisibleLogicalRange({
    from: previousRange.from + addedCount,
    to: previousRange.to + addedCount,
  });
}
```

### Resultado esperado

O candle que estava sob o cursor ou no centro da tela deve permanecer aproximadamente no mesmo ponto visual depois do carregamento histórico.

### Cuidados

- não chamar `fitContent()` depois do prepend;
- não chamar `scrollToRealTime()` depois do prepend;
- não executar a restauração se o ativo/timeframe mudou durante o request;
- usar um identificador de geração da consulta para descartar respostas obsoletas.

---

## 13. Fase 8 — Estado “ao vivo” e botão Voltar ao vivo

Criar state:

```ts
const [isAtRealtime, setIsAtRealtime] = useState(true);
```

Atualizar durante `subscribeVisibleLogicalRangeChange`.

### Detecção recomendada

Usar `barsInLogicalRange(range)`:

```ts
const barsInfo = series.barsInLogicalRange(range);
const atRealtime =
  barsInfo !== null &&
  barsInfo.barsAfter <= REALTIME_TOLERANCE;
```

Configuração inicial:

```text
REALTIME_TOLERANCE = 1
```

Validar empiricamente o comportamento com `rightOffset`.

### Botão

Mostrar somente quando:

```ts
!isAtRealtime
```

Ação:

```ts
chart.timeScale().scrollToRealTime();
```

Após o clique:

- não recriar a série;
- não recarregar dados;
- não chamar `fitContent()`;
- permitir que o próprio evento da faixa atualize `isAtRealtime`.

### Informação opcional

Mostrar indicador discreto:

```text
AO VIVO
HISTÓRICO
RECONECTANDO
```

---

## 14. Fase 9 — Concorrência e respostas obsoletas

### Problemas que devem ser evitados

1. usuário troca de WINFUT M5 para WDOFUT M2;
2. request antigo de WINFUT termina depois;
3. resposta antiga é aplicada no gráfico novo.

### Solução

Usar:

- `AbortController`;
- chave de consulta `asset + timeframe`;
- contador `requestGenerationRef`;
- validação antes de aplicar qualquer resposta.

Exemplo conceitual:

```ts
const generation = ++requestGenerationRef.current;

const result = await fetchCandles(...);

if (
  generation !== requestGenerationRef.current ||
  activeKey !== `${asset}:${timeframe}`
) {
  return;
}
```

### Troca de ativo/timeframe

Ao trocar:

1. abortar initial load anterior;
2. abortar loadOlder anterior;
3. interromper polling anterior;
4. limpar `candlesRef`;
5. redefinir `hasMoreHistory`;
6. ocultar botão de histórico;
7. carregar o novo lote;
8. iniciar polling novo após sucesso.

---

## 15. Fase 10 — Cleanup obrigatório

No unmount ou troca de identidade:

- cancelar polling;
- limpar `setTimeout` ou `setInterval`;
- abortar fetch;
- cancelar subscription com a mesma função usada no subscribe;
- remover `ResizeObserver`;
- remover listeners do DOM;
- chamar `chart.remove()`;
- impedir callbacks tardios de acessar chart destruído.

### React Strict Mode

A implementação deve funcionar mesmo quando o ambiente de desenvolvimento monta, desmonta e monta novamente o componente.

Critério:

- apenas um polling ativo;
- apenas um listener de pan;
- apenas um chart no container;
- nenhuma atualização após `chart.remove()`.

---

## 16. Fase 11 — Overlays técnicos

Se o gráfico possui SMC, Elliott, Wyckoff, volume ou indicadores:

1. não recriar todas as séries a cada candle;
2. atualizar somente o overlay que mudou;
3. manter os dados ordenados por tempo;
4. evitar que um overlay com timestamps futuros crie espaço inesperado;
5. evitar que um overlay vazio provoque `fitContent()`;
6. sincronizar prepend histórico dos overlays somente quando necessário;
7. não deixar a atualização de overlay reposicionar a escala temporal.

Se um overlay depende do conjunto inteiro, recalcular fora do caminho crítico do candle live e aplicar sem resetar a faixa visível.

---

## 17. Fase 12 — Estados de interface

Implementar estados distintos:

### Carregamento inicial

Exibir skeleton ou spinner central.

### Carregamento histórico

Exibir indicador pequeno no canto esquerdo superior:

```text
Carregando histórico…
```

Não bloquear pan e zoom.

### Erro inicial

Exibir erro e botão **Tentar novamente**.

### Erro no polling

Manter o gráfico e mostrar status discreto:

```text
Reconectando…
```

### Fim do histórico

Definir:

```ts
hasMoreHistory = false
```

quando:

- API retornar lote vazio;
- API retornar flag `has_more: false`;
- quantidade retornada for menor que a página e o contrato confirmar fim;
- backend informar explicitamente o primeiro candle disponível.

Não repetir chamadas ao atingir o fim.

---

## 18. API recomendada

### Inicial

```http
GET /api/candles?asset=WINFUT&timeframe=M5&limit=500
```

Resposta:

```json
{
  "items": [],
  "has_more": true,
  "oldest_time": 1710000000,
  "newest_time": 1710010000
}
```

### Histórico

```http
GET /api/candles/history?asset=WINFUT&timeframe=M5&before=1710000000&limit=500
```

Regras:

- retornar ordem crescente;
- retornar apenas `time < before`;
- não repetir candles;
- informar `has_more`;
- usar paginação estável.

### Atualização recente

```http
GET /api/candles/recent?asset=WINFUT&timeframe=M5&after=1710010000
```

ou, temporariamente:

```http
GET /api/candles?asset=WINFUT&timeframe=M5&limit=5
```

---

## 19. Logs temporários de diagnóstico

Adicionar logs apenas em desenvolvimento:

```text
[chart:init] asset timeframe count
[chart:live:update] time
[chart:live:new] time
[chart:history:request] before
[chart:history:received] count
[chart:history:applied] addedCount total
[chart:range] from to barsBefore barsAfter
[chart:realtime] true/false
[chart:dispose]
```

Não imprimir cada render React.

Remover ou proteger logs detalhados antes da produção.

---

## 20. Testes unitários

### Normalização

- converte milissegundos para segundos;
- mantém segundos;
- ordena;
- deduplica;
- descarta OHLC inválido;
- mantém a versão mais recente de timestamp duplicado.

### Merge histórico

- adiciona candles anteriores;
- não duplica candle de borda;
- retorna `addedCount` correto;
- mantém ordem crescente.

### Live update

- mesmo timestamp substitui último candle;
- timestamp maior adiciona candle;
- timestamp menor é ignorado;
- múltiplos candles novos são aplicados em sequência.

### Paginação

- não chama com `isLoadingOlder`;
- não chama com `hasMoreHistory = false`;
- usa o timestamp do candle mais antigo;
- descarta resposta de ativo antigo.

---

## 21. Testes de integração e manuais

### Cenário A — Inicialização

1. abrir gráfico;
2. confirmar um único request inicial;
3. confirmar um único `setData()`;
4. confirmar 100 a 150 candles visíveis;
5. confirmar ausência de flicker.

### Cenário B — Candle aberto

1. aguardar polling;
2. receber mesmo timestamp;
3. confirmar alteração do último candle;
4. confirmar que pan e zoom não mudam;
5. confirmar ausência de `setData()`.

### Cenário C — Candle novo no modo ao vivo

1. manter último candle visível;
2. receber timestamp maior;
3. confirmar novo candle;
4. confirmar deslocamento natural para a direita.

### Cenário D — Candle novo no histórico

1. fazer pan para a esquerda;
2. confirmar botão **Voltar ao vivo**;
3. receber candle novo;
4. confirmar que o gráfico não volta automaticamente;
5. clicar no botão;
6. confirmar retorno animado ao candle atual.

### Cenário E — Infinite scroll

1. arrastar para a esquerda;
2. confirmar request quando `barsBefore < 50`;
3. confirmar uma única chamada;
4. confirmar prepend;
5. confirmar que a tela não salta;
6. repetir até o fim do histórico.

### Cenário F — Zoom

1. zoom in;
2. zoom out;
3. pan durante zoom;
4. confirmar que nenhum efeito reverte o gesto;
5. confirmar ausência de limite artificial.

### Cenário G — Rede lenta

1. simular request maior que 10 segundos;
2. confirmar que não há polling sobreposto;
3. confirmar que o gráfico permanece utilizável.

### Cenário H — Troca rápida

1. trocar ativo/timeframe várias vezes;
2. confirmar cancelamento de requests antigos;
3. confirmar que nenhum dado antigo aparece no novo gráfico.

### Cenário I — React Strict Mode

1. executar em desenvolvimento;
2. confirmar um único chart;
3. confirmar um único polling;
4. confirmar um único listener.

---

## 22. Critérios de aceite

A correção só será considerada concluída quando:

- [ ] `setData()` não for chamado no polling;
- [ ] live usar `series.update()`;
- [ ] candle aberto for substituído sem duplicação;
- [ ] candle novo for adicionado em ordem;
- [ ] pan para a esquerda carregar histórico;
- [ ] detecção usar `barsInLogicalRange().barsBefore`;
- [ ] posição visual for preservada durante prepend;
- [ ] não houver chamadas históricas paralelas;
- [ ] não houver polling paralelo;
- [ ] botão **Voltar ao vivo** funcionar;
- [ ] candles live não puxarem o usuário quando ele estiver no histórico;
- [ ] zoom limit artificial tiver sido removido;
- [ ] `fitContent()` não for executado após atualizações;
- [ ] subscriptions e timers forem removidos no cleanup;
- [ ] troca de ativo/timeframe não misturar respostas;
- [ ] não houver candles duplicados;
- [ ] não houver flicker;
- [ ] não houver erro no console;
- [ ] testes unitários e manuais passarem.

---

## 23. Ordem recomendada de execução

1. criar branch específica;
2. registrar comportamento atual;
3. remover zoom limit;
4. centralizar normalização e deduplicação;
5. corrigir criação e cleanup do chart;
6. separar carga inicial de atualização live;
7. trocar polling de `setData()` para `update()`;
8. implementar trava de polling;
9. implementar `barsInLogicalRange()`;
10. implementar `loadOlder(before)`;
11. preservar range no prepend;
12. implementar `isAtRealtime`;
13. adicionar botão **Voltar ao vivo**;
14. tratar troca de ativo/timeframe;
15. auditar overlays;
16. executar testes;
17. remover logs temporários;
18. documentar resultado.

---

## 24. Restrições para a IA executora

A IA que implementar este plano deve:

1. primeiro ler os arquivos completos envolvidos;
2. identificar a versão instalada de `lightweight-charts`;
3. não misturar API da versão 4 com a versão 5;
4. não atualizar dependências sem necessidade;
5. não reescrever o componente inteiro sem justificar;
6. preservar estilos e funcionalidades existentes;
7. fazer alterações pequenas e verificáveis;
8. compilar após cada etapa relevante;
9. executar lint e testes disponíveis;
10. apresentar diff/resumo por arquivo;
11. não declarar sucesso sem executar a verificação;
12. registrar qualquer limitação encontrada na API existente.

---

## 25. Resultado final esperado

O gráfico deverá operar desta forma:

```text
ABERTURA
  → carrega lote inicial
  → mostra últimos candles
  → inicia polling

POLLING
  → mesmo timestamp: atualiza candle aberto
  → timestamp maior: adiciona candle novo
  → não usa setData
  → não interfere no pan/zoom

PAN PARA ESQUERDA
  → calcula barsBefore
  → busca lote anterior
  → deduplica
  → aplica setData apenas para prepend
  → restaura posição visual

USUÁRIO NO HISTÓRICO
  → dados live continuam chegando
  → tela não é puxada para o presente
  → botão Voltar ao vivo aparece

VOLTAR AO VIVO
  → scrollToRealTime()
  → último candle volta a ficar visível
  → acompanhamento automático é retomado
```