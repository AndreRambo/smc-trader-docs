# PLANO V6 — LIMITAR O ZOOM POR QUANTIDADE DE CANDLES SEM LIMITAR AS ZONAS SMC

**Projeto:** Maximus Trade  
**Data:** 26/06/2026  
**Objetivo principal:** limitar apenas o zoom-out do gráfico e voltar a plotar todas as zonas SMC carregadas  
**Biblioteca:** TradingView Lightweight Charts 5.2.0  
**Status inicial:** `BUILD_PASS / ESTRATÉGIA_DE_BUDGET_DE_ZONAS_REPROVADA`  
**Gate de produção:** `NO_GO`

---

# 1. Decisão arquitetural

A estratégia anterior deve ser substituída.

## Não fazer mais

```text
zoom-out
→ diminuir budget
→ remover zonas
→ clusterizar e descartar geometria
→ trocar conjunto de zonas conforme o zoom
```

Essa estratégia está causando:

- zonas desaparecendo;
- zonas diferentes ao voltar o zoom;
- cache visual residual;
- inconsistência entre timeframes;
- dificuldade para reproduzir a mesma tela;
- bugs de seleção e clustering.

## Nova estratégia

```text
carregar todas as zonas
→ manter todas em memória
→ limitar apenas o número máximo de candles visíveis
→ desenhar todas as zonas que cruzam a viewport
→ esconder somente geometria totalmente fora da viewport
```

### Regra fundamental

```text
NENHUMA ZONA É REMOVIDA POR QUANTIDADE.
```

Podem continuar existindo filtros por:

- tipo ligado/desligado pelo usuário;
- lifecycle técnico;
- ativo;
- timeframe;
- intervalo realmente carregado;
- opção explícita de mostrar mitigadas/invalidadas.

Não podem existir filtros por:

- budget;
- quantidade máxima;
- ranking;
- nível de zoom;
- score;
- clustering que descarte geometria.

---

# 2. Resultado esperado

## Zoom

- usuário pode aproximar livremente;
- usuário pode afastar até um número máximo de candles;
- depois desse limite, o zoom-out para;
- pan continua funcionando normalmente;
- infinite scroll continua carregando histórico;
- o limite não reposiciona o usuário bruscamente.

## Zonas

- todas as zonas carregadas permanecem armazenadas;
- todas as zonas que cruzam a viewport são desenhadas;
- uma zona fora da viewport não é desenhada naquele frame;
- ao fazer pan até a zona, ela aparece;
- ao voltar ao mesmo intervalo, aparecem as mesmas zonas;
- zoom não muda a composição das zonas;
- ranking pode determinar z-order e prioridade de label, mas não existência.

---

# 3. Quantidade máxima de candles visíveis

## 3.1 Não adivinhar a quantidade final

O print atual deve ser usado como referência visual, mas a quantidade exata deve ser medida pelo logical range.

Adicionar temporariamente:

```ts
const logicalRange =
  chart.timeScale().getVisibleLogicalRange()

if (logicalRange) {
  const visibleBars =
    logicalRange.to - logicalRange.from

  console.debug('[chart:visible-bars]', {
    visibleBars,
    from: logicalRange.from,
    to: logicalRange.to,
  })
}
```

O usuário deve deixar o gráfico no zoom máximo desejado e registrar `visibleBars`.

## 3.2 Valor inicial de implementação

Até a calibração visual final, usar:

```ts
const DEFAULT_MAX_VISIBLE_BARS = 220
```

Configuração recomendada por viewport:

```ts
const MAX_VISIBLE_BARS_BY_LAYOUT = {
  desktop: 220,
  tablet: 160,
  mobileLandscape: 120,
  mobilePortrait: 80,
} as const
```

## 3.3 Não variar por timeframe inicialmente

O limite visual deve depender da largura da tela, não do timeframe.

M2, M5, M15, H1, H4 e D1 podem compartilhar o mesmo número máximo de candles por layout.

Se testes futuros justificarem diferenças, criar configuração separada depois.

---

# 4. Implementação principal com `minBarSpacing`

O Lightweight Charts permite definir o espaçamento mínimo em pixels entre barras.

Quanto maior o `minBarSpacing`, menor a quantidade máxima de candles simultaneamente visíveis.

## 4.1 Cálculo dinâmico

Criar:

```text
src/components/chart/zoom/chartZoomPolicy.ts
```

```ts
export interface ZoomPolicyInput {
  containerWidth: number
  maxVisibleBars: number
  priceScaleReservePx?: number
  minAllowedSpacingPx?: number
  maxAllowedSpacingPx?: number
}

export function calculateMinBarSpacing({
  containerWidth,
  maxVisibleBars,
  priceScaleReservePx = 84,
  minAllowedSpacingPx = 1.5,
  maxAllowedSpacingPx = 16,
}: ZoomPolicyInput): number {
  const usableWidth =
    Math.max(100, containerWidth - priceScaleReservePx)

  const spacing = usableWidth / maxVisibleBars

  return Math.max(
    minAllowedSpacingPx,
    Math.min(maxAllowedSpacingPx, spacing),
  )
}
```

## 4.2 Aplicar ao chart

Na criação:

```ts
const maxVisibleBars =
  resolveMaxVisibleBars(container)

const minBarSpacing =
  calculateMinBarSpacing({
    containerWidth: container.clientWidth,
    maxVisibleBars,
  })

const chart = createChart(container, {
  timeScale: {
    timeVisible: true,
    secondsVisible: false,
    shiftVisibleRangeOnNewBar: true,
    rightOffset: 5,

    minBarSpacing,
    maxBarSpacing: 40,
  },
})
```

## 4.3 Atualizar no resize

No `ResizeObserver`:

```ts
const width = entry.contentRect.width

const maxVisibleBars =
  resolveMaxVisibleBarsFromWidth(width)

const minBarSpacing =
  calculateMinBarSpacing({
    containerWidth: width,
    maxVisibleBars,
  })

chart.timeScale().applyOptions({
  minBarSpacing,
})
```

## Regras

- não chamar `fitContent()`;
- não resetar o zoom no resize;
- não chamar range inicial novamente;
- apenas atualizar `minBarSpacing`;
- se a tela encolher, permitir que o próprio time scale ajuste o limite.

---

# 5. Guard exato de logical range

`minBarSpacing` será a proteção principal.

Adicionar um guard secundário para:

- mudanças programáticas;
- restauração de range;
- Replay;
- prepend histórico;
- comportamentos de resize;
- diferenças de cálculo da área útil.

## 5.1 Ref de proteção

```ts
const enforcingZoomLimitRef = useRef(false)
```

## 5.2 Função

```ts
function enforceMaxVisibleBars(
  range: LogicalRange | null,
): LogicalRange | null {
  if (!range) return null

  const maxBars =
    maxVisibleBarsRef.current

  const span = range.to - range.from

  if (span <= maxBars + 1) {
    return null
  }

  const barsInfo =
    candleSeriesRef.current
      ?.barsInLogicalRange(range)

  const isAtRealtime =
    barsInfo !== null &&
    barsInfo !== undefined &&
    barsInfo.barsAfter <= 1

  if (isAtRealtime) {
    return {
      from: range.to - maxBars,
      to: range.to,
    }
  }

  const center =
    (range.from + range.to) / 2

  return {
    from: center - maxBars / 2,
    to: center + maxBars / 2,
  }
}
```

## 5.3 Aplicação sem loop

```ts
const corrected =
  enforceMaxVisibleBars(range)

if (
  corrected &&
  !enforcingZoomLimitRef.current
) {
  enforcingZoomLimitRef.current = true

  chart.timeScale()
    .setVisibleLogicalRange(corrected)

  requestAnimationFrame(() => {
    enforcingZoomLimitRef.current = false
  })

  return
}
```

## Regra

O guard:

- corrige somente quando o limite foi ultrapassado;
- não roda a cada pequeno zoom;
- não interfere no pan;
- preserva o candle atual quando em realtime;
- preserva o centro quando no histórico.

---

# 6. Simplificar a arquitetura das zonas

## 6.1 Remover seleção por quantidade

Desativar ou remover do caminho de renderização:

```text
budgets por tipo
detail multipliers
maxItems
maxActiveZones
maxStructures
slice por quantidade
cluster que substitui zonas
seleção de top N
```

## Arquivos afetados

```text
src/components/chart/smc/SmcViewportController.ts
src/components/chart/smc/smcVisibility.ts
src/components/chart/smc/smcTypes.ts
src/components/chart/smc/primitives/*
src/components/chart/smc/renderers/*
src/components/CandlestickChart.tsx
```

---

# 6.2 Novo papel do `SmcViewportController`

O controller deixa de decidir quais zonas “merecem” existir.

Novo papel:

```text
armazenar todas as zonas normalizadas
controlar revision
separar por tipo
consultar interseção com viewport
gerar assinatura de dados
evitar normalização duplicada
```

Nova interface:

```ts
interface AllSmcZonesSnapshot {
  revision: number

  fvg: RenderableSmcItem[]
  ob: RenderableSmcItem[]
  bpr: RenderableSmcItem[]
  bos: RenderableSmcItem[]
  choch: RenderableSmcItem[]
  liquidity: RenderableSmcItem[]
  swing: RenderableSmcItem[]
  pdhPdl: RenderableSmcItem[]
  session: RenderableSmcItem[]
}
```

## Proibição

O controller não pode usar:

```text
slice
budget
top N
detail multiplier
cluster para descartar item
```

---

# 7. Todas as zonas devem permanecer nas primitives

Atualizar as primitives somente quando os dados SMC mudarem:

```text
nova resposta da API
prepend histórico
novo snapshot SMC
mudança de toggle
mudança de lifecycle/status
troca de ativo/timeframe
```

Não executar `primitive.setData()` somente porque houve zoom.

## Fluxo

```text
dados SMC mudaram
→ normalize
→ separar por tipo
→ primitive.setData(allItemsOfType)

zoom/pan
→ primitive.requestUpdate()
→ renderer filtra apenas overlap
→ não substituir arrays
```

Esse fluxo elimina estado residual de seleção após zoom.

---

# 8. Viewport clipping continua obrigatório

“Plotar todas as zonas” significa:

```text
todas as zonas carregadas e válidas
aparecem quando seu intervalo cruza a viewport
```

Não significa desenhar pixels fora da tela.

Cada renderer deve manter:

```ts
const visible =
  getVisibleTimeRange(timeScale)

if (!visible) {
  return
}

const clipped =
  clipItemTimeToViewport(
    item.fromTime,
    item.toTime,
    visible.from,
    visible.to,
  )

if (!clipped) {
  continue
}
```

## Resultado

- zona fora da tela: armazenada, não desenhada;
- zona parcialmente visível: cortada na borda;
- zona visível: desenhada completa;
- ao fazer pan: aparece automaticamente.

---

# 9. Remover limites dentro dos renderers

Procurar e remover:

```ts
items.slice(0, maxItems)
items.slice(-maxItems)
labelCount >= maxLabels && break
selected.slice(...)
```

Para geometria:

```ts
for (const item of items) {
  // renderizar se cruza viewport
}
```

## Exceção: labels

A geometria de todas as zonas deve aparecer.

Labels podem continuar sujeitos a:

- collision manager;
- viewport;
- espaço disponível;
- prioridade;
- hover.

Ocultar um label não pode ocultar a zona.

---

# 10. Clustering sem remover geometria

Desativar clustering como filtro de zonas.

Se for mantido, usar apenas para:

- texto de confluência;
- tooltip;
- z-order;
- estilo de borda;
- badge `×2`, `×3`.

Exemplo:

```text
OB + FVG ×2
```

Mas todas as geometrias originais permanecem desenhadas.

## Proibição

```text
cluster → escolher dominantZone → descartar members
```

---

# 11. Ranking sem remover zonas

Ranking pode continuar sendo usado para:

- ordem de desenho;
- prioridade de label;
- opacidade;
- tooltip;
- destaque no hover.

Não pode ser usado para:

- `slice`;
- budget;
- remoção;
- esconder geometria.

---

# 12. Lifecycle

Manter as correções já implementadas:

```text
mitigada termina em mitigatedAt
invalidada termina em invalidatedAt
ativa usa endpoint/fallback técnico
PDH/PDL respeita intervalo temporal
```

## Presets

Presets continuam controlando:

- tipos visíveis;
- estados visíveis;
- mitigadas;
- swings;
- sessões.

Presets não podem controlar quantidade máxima.

---

# 13. Configuração proposta

Criar:

```text
src/components/chart/config/chartDisplayConfig.ts
```

```ts
export const CHART_DISPLAY_CONFIG = {
  initialVisibleBars: 120,

  maxVisibleBars: {
    desktop: 220,
    tablet: 160,
    mobileLandscape: 120,
    mobilePortrait: 80,
  },

  rightOffsetBars: 5,
  maxBarSpacing: 40,

  smc: {
    limitZoneCount: false,
    renderAllIntersectingZones: true,
    clusterGeometry: false,
    collisionManagedLabels: true,
  },
} as const
```

---

# 14. Feature flag para rollback

Criar:

```env
VITE_CHART_LIMIT_VISIBLE_BARS=true
VITE_SMC_LIMIT_ZONE_COUNT=false
VITE_SMC_RENDER_ALL_ZONES=true
```

No código:

```ts
const limitZoneCount =
  import.meta.env.VITE_SMC_LIMIT_ZONE_COUNT === 'true'
```

## Objetivo

Permitir rollback sem reverter commits.

---

# 15. Infinite scroll

O limite de zoom não pode impedir o pan para a esquerda.

Manter:

```text
barsBefore
loadOlder(before)
```

## Proteções

- uma requisição por vez;
- cursor único;
- sem cascata;
- cooldown pós-prepend;
- abort na troca de identidade;
- não carregar histórico apenas porque o guard corrigiu o zoom.

## Origem do evento

Adicionar ref:

```ts
type RangeChangeCause =
  | 'user-pan'
  | 'user-zoom'
  | 'zoom-limit-correction'
  | 'history-prepend'
  | 'programmatic'
```

Enquanto a correção de zoom estiver ativa:

```text
não disparar loadOlder
```

---

# 16. Handler único de logical range

Ordem:

```ts
function handleLogicalRangeChange(
  range: LogicalRange | null,
) {
  if (!range) return

  updateRealtimeState(range)

  const corrected =
    enforceMaxVisibleBars(range)

  if (corrected) {
    applyCorrection(corrected)
    return
  }

  requestSmcRedraw()

  if (
    !enforcingZoomLimitRef.current &&
    !suppressHistoryRef.current
  ) {
    maybeLoadOlder(range)
  }
}
```

## Importante

O zoom não deve:

- chamar `setData()` de zonas;
- recalcular top N;
- substituir arrays;
- alterar lifecycle;
- mudar presets.

---

# 17. Estado inicial do chart

Após snapshot inicial:

```ts
const total =
  candleDataRef.current.length

const initialVisible =
  Math.min(
    CHART_DISPLAY_CONFIG.initialVisibleBars,
    total,
  )

chart.timeScale().setVisibleLogicalRange({
  from: total - initialVisible,
  to: total - 1 + 5,
})
```

Executar apenas:

- carga inicial;
- troca de ativo;
- troca de timeframe;
- entrada em Replay.

Não executar após:

- zoom;
- pan;
- live;
- prepend.

---

# 18. Responsividade

# 18.1 Desktop

```text
máximo inicial: 220 candles
```

# 18.2 Tablet

```text
máximo inicial: 160 candles
```

# 18.3 Mobile paisagem

```text
máximo inicial: 120 candles
```

# 18.4 Mobile retrato

```text
máximo inicial: 80 candles
```

Os valores devem ser calibrados visualmente após a implementação.

---

# 19. Performance sem remover zonas

Se existirem muitas zonas carregadas, otimizar sem esconder dados.

## Permitido

- ordenar por `fromTime`;
- busca binária por viewport;
- índice temporal;
- cache de coordenadas por frame;
- `requestAnimationFrame`;
- não redesenhar se chart não mudou;
- clip de canvas;
- memoização;
- estruturas reutilizáveis;
- evitar alocações.

## Não permitido

- top N;
- budget;
- truncar arrays;
- apagar zonas antigas válidas;
- clusterizar descartando members.

---

# 20. Índice temporal opcional

Criar:

```text
src/components/chart/smc/SmcTemporalIndex.ts
```

Interface:

```ts
class SmcTemporalIndex {
  setItems(items: RenderableSmcItem[]): void

  queryIntersecting(
    visibleFrom: number,
    visibleTo: number,
  ): RenderableSmcItem[]
}
```

O índice retorna todas as zonas que cruzam a viewport.

Não limita quantidade.

---

# 21. Labels

Todas as zonas são desenhadas.

Labels seguem regras separadas:

```text
um label pode ser ocultado
a zona não pode ser ocultada junto
```

Manter:

- LabelPlacer global;
- colisão;
- alternative Y;
- tooltip no hover;
- prioridade;
- limite somente de texto simultâneo, se necessário.

## Se o usuário exigir todos os labels

Criar opção:

```text
Mostrar todos os labels
```

Desligada por padrão por legibilidade.

---

# 22. Arquivos a alterar

## Prioridade P0

```text
src/components/CandlestickChart.tsx
src/components/chart/smc/SmcViewportController.ts
src/components/chart/smc/smcVisibility.ts
src/components/chart/smc/smcTypes.ts
src/components/chart/smc/smcRenderUtils.ts
src/components/chart/smc/primitives/*
src/components/chart/smc/renderers/*
```

## Novos arquivos

```text
src/components/chart/zoom/chartZoomPolicy.ts
src/components/chart/config/chartDisplayConfig.ts
src/components/chart/smc/SmcTemporalIndex.ts
```

## Configuração

```text
.env.example
```

---

# 23. Remoções obrigatórias

Remover do caminho principal:

```text
getBudgetsForTimeframe()
DETAIL_MULTIPLIERS para geometria
applyTypeBudgets()
slice por maxItems
maxActiveZones como corte
maxStructures como corte
cluster que descarta zonas
select top N
setData de primitive por zoom
```

Essas funções podem permanecer temporariamente atrás da feature flag, mas não devem ser usadas no modo novo.

---

# 24. Testes automatizados

# 24.1 Zoom policy

- desktop;
- tablet;
- mobile;
- resize;
- limite exato;
- realtime anchor;
- historical center;
- guard sem loop.

# 24.2 Zonas

- 1 zona;
- 100 zonas;
- 1.000 zonas;
- todas preservadas no store;
- todas as intersectantes retornadas;
- nenhuma removida por count;
- pan revela zona;
- zoom não muda IDs armazenados.

# 24.3 Renderers

- sem `slice`;
- fora da viewport não desenha;
- dentro desenha;
- parcial corta;
- label oculto não remove geometria.

# 24.4 Reversibilidade

```text
zoom A
→ máximo zoom-out
→ zoom A
```

Comparar:

```text
IDs das zonas armazenadas
IDs das zonas intersectantes
geometria desenhada
```

# 24.5 Infinite scroll

- pan carrega;
- correção de zoom não carrega;
- mesmo cursor não repete;
- prepend preserva range;
- todas as novas zonas entram no store.

---

# 25. Testes manuais

## M2

1. abrir no zoom normal;
2. confirmar todas as zonas;
3. afastar até o limite;
4. confirmar que zoom para;
5. pan para esquerda;
6. confirmar histórico;
7. aproximar;
8. confirmar mesmas zonas.

## M5, M15, H1, H4 e D1

Repetir o mesmo procedimento.

## Teste de carga

1. carregar várias páginas históricas;
2. confirmar aumento do total de zonas;
3. navegar entre períodos;
4. confirmar que zonas aparecem quando entram na viewport;
5. confirmar ausência de desaparecimento por budget.

---

# 26. Métricas de debug

```text
maxVisibleBars
actualVisibleBars
minBarSpacing
rangeCorrectionCount
allZonesCount
intersectingZonesCount
drawnZonesCount
hiddenLabelsCount
historyRequestCount
rendererDurationMs
fps
```

---

# 27. Critérios de aceite

- [ ] Zoom-out para no limite definido.
- [ ] Pan continua funcionando.
- [ ] Infinite scroll continua funcionando.
- [ ] Correção de zoom não dispara histórico.
- [ ] Nenhuma zona é removida por quantidade.
- [ ] Nenhuma zona é removida por detail level.
- [ ] Nenhuma zona é removida por clustering.
- [ ] Nenhuma zona é removida por ranking.
- [ ] Todas as zonas intersectantes são desenhadas.
- [ ] Zonas fora da viewport permanecem armazenadas.
- [ ] Pan revela zonas armazenadas.
- [ ] Zoom não chama `primitive.setData()`.
- [ ] Não existem `slice(maxItems)` nos renderers.
- [ ] Labels são independentes da geometria.
- [ ] Voltar ao mesmo zoom mostra as mesmas zonas.
- [ ] M2 passa.
- [ ] M5 passa.
- [ ] M15 passa.
- [ ] H1 passa.
- [ ] H4 passa.
- [ ] D1 passa.
- [ ] Build passa.
- [ ] Lint dos arquivos alterados passa.
- [ ] Testes passam.
- [ ] Console permanece limpo.

---

# 28. Gates

```text
GATE_MAX_VISIBLE_BARS: PENDENTE
GATE_ZOOM_LIMIT_NO_LOOP: PENDENTE
GATE_PAN_UNRESTRICTED: PENDENTE
GATE_HISTORY_UNRESTRICTED: PENDENTE
GATE_ALL_ZONES_STORED: PENDENTE
GATE_ALL_INTERSECTING_RENDERED: PENDENTE
GATE_NO_ZONE_BUDGET: PENDENTE
GATE_ZOOM_REVERSIBLE: PENDENTE
GATE_PERFORMANCE: PENDENTE
GATE_VISUAL_M2: PENDENTE
GATE_VISUAL_M5: PENDENTE
GATE_VISUAL_M15: PENDENTE
GATE_VISUAL_H1: PENDENTE
GATE_VISUAL_H4: PENDENTE
GATE_VISUAL_D1: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 29. Ordem de execução

```text
1. medir quantidade desejada de candles
2. criar chartZoomPolicy
3. aplicar minBarSpacing
4. adicionar guard lógico
5. remover budgets de zonas
6. remover slice dos renderers
7. simplificar SmcViewportController
8. manter viewport clipping
9. separar labels de geometria
10. proteger infinite scroll
11. executar testes de reversibilidade
12. executar testes de performance
13. validar todos os timeframes
```

---

# 30. Restrições para a IA executora

A IA deve:

1. ler todos os arquivos atuais antes de alterar;
2. preservar lifecycle;
3. preservar toggles;
4. preservar presets;
5. preservar clipping;
6. não reintroduzir zonas fora da viewport;
7. não usar `fitContent()` como correção;
8. não chamar `setVisibleLogicalRange()` em loop;
9. não limitar zonas por quantidade;
10. não limitar zonas por score;
11. não descartar members de clusters;
12. não confundir label oculto com zona oculta;
13. executar build após cada fase;
14. executar lint;
15. criar testes;
16. informar métricas antes/depois;
17. gerar relatório final.

---

# 31. Resultado final esperado

```text
ZOOM
  → aproxima livremente
  → afasta até MAX_VISIBLE_BARS
  → para de forma suave

PAN
  → continua livre
  → carrega histórico

ZONAS
  → todas armazenadas
  → todas as intersectantes desenhadas
  → nenhuma removida por budget
  → nenhuma removida pelo zoom

LABELS
  → gerenciados separadamente
  → podem ser ocultados por colisão
  → geometria permanece

REVERSIBILIDADE
  → zoom A
  → zoom-out
  → zoom A
  → mesmas zonas
```

---

# 32. Conclusão

A correção deve trocar o controle:

```text
DE:
limitar zonas conforme o zoom

PARA:
limitar candles visíveis no zoom
e manter todas as zonas
```

Essa arquitetura é mais simples, determinística e compatível com a necessidade operacional informada.
