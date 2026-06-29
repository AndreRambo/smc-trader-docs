# PLANO DE VALIDAÇÃO FINAL PÓS-V4 — OVERLAYS SMC, DENSIDADE E LEGIBILIDADE

**Projeto:** Maximus Trade  
**Data:** 26/06/2026  
**Base:** resumo consolidado das correções V4  
**Foco:** validação funcional, visual e de desempenho dos overlays SMC  
**Status atual:** `IMPLEMENTAÇÃO_V4_CONCLUÍDA / BUILD_PASS / VALIDAÇÃO_FINAL_PENDENTE`  
**Gate de produção:** `NO_GO_ATÉ_PASSAR_MATRIZ_VISUAL_E_FUNCIONAL`

---

# 1. Objetivo

Validar que a implementação V4:

1. limpa o gráfico sem remover informação técnica válida;
2. aplica corretamente TTL por timeframe;
3. limita PDH/PDL conforme o timeframe;
4. seleciona os overlays mais relevantes;
5. agrupa zonas sobrepostas sem perder semântica;
6. evita colisões entre labels de todos os tipos;
7. altera a densidade suavemente conforme o zoom;
8. preserva pan, zoom, live, prepend e Replay;
9. não interfere no autoscale;
10. mantém desempenho aceitável em todos os timeframes;
11. apresenta comportamento determinístico;
12. está pronta para promoção à produção.

---

# 2. Estado informado da implementação

## `normalizerUtils.ts`

Implementado:

- TTL baseado em barras por timeframe;
- `NormalizeOptions.timeframe`;
- M2: 600 barras;
- M5: 500 barras;
- M15: 350 barras;
- H1: 360 barras;
- H4: 360 barras;
- D1: 500 barras;
- lifecycle técnico preservado;
- mitigadas terminam em `mitigatedAt`;
- estruturas usam endpoints explícitos.

## `pdhPdlNormalizer.ts`

Implementado:

```text
D1 = 0
H1/H4 = 5
M5/M15 = 6
M2 = 2
```

## `smcVisibility.ts`

Implementado:

- budgets por tipo e timeframe;
- ranking por relevância;
- proximidade por ATR;
- recência;
- prioridade por tipo;
- clustering;
- hysteresis;
- pipeline:

```text
ranking → clustering → budget → rendering
```

## `smcRenderUtils.ts`

Implementado:

- gaps horizontais e verticais;
- detecção de colisão;
- `findAlternativeY()`;
- seis offsets alternativos;
- `drawLabel()` compartilhado pelos nove renderers.

## `useSmcPerType.ts`

Implementado:

- recebe timeframe;
- propaga timeframe aos normalizers;
- logs de produção removidos.

## `CandlestickChart.tsx`

Implementado:

- transmite timeframe a toda a camada SMC.

## Renderers

Implementado:

- limites reduzidos;
- PDH/PDL cortado ao intervalo temporal;
- limpeza de variáveis não utilizadas;
- borda direita removida das zonas ativas;
- opacidade hierárquica.

---

# 3. Estado executivo

Com base no resumo, os itens arquiteturais principais foram atendidos.

```text
LIFECYCLE: IMPLEMENTADO
TTL_POR_TIMEFRAME: IMPLEMENTADO
PDH_PDL_POR_TIMEFRAME: IMPLEMENTADO
BUDGETS_POR_TIPO: IMPLEMENTADO
RANKING: IMPLEMENTADO
CLUSTERING: IMPLEMENTADO
ZOOM_HYSTERESIS: IMPLEMENTADO
LABEL_COLLISION: IMPLEMENTADO
BORDA_DIREITA: IMPLEMENTADO
BUILD: PASS
```

Ainda precisam de prova:

```text
SEMÂNTICA_DO_TTL_EM_BARRAS
CLUSTERING_SEM_PERDA_DE_TIPO
COLISÃO_GLOBAL_ENTRE_RENDERERS
AUTOSCALE
VIEWPORT_CLIPPING
PERFORMANCE
PERSISTÊNCIA_DOS_PRESETS
VALIDAÇÃO_VISUAL_D1_A_M2
```

---

# 4. Risco P0 — interpretação incorreta de “TTL em barras”

## Problema

Há duas formas de implementar TTL por barras:

### Correta

```text
createdBarIndex + maxAgeBars
```

ou localização do candle limite na série real.

### Potencialmente incorreta

```text
createdAt + maxAgeBars × timeframeSeconds
```

A segunda opção calcula tempo corrido, não quantidade real de barras.

Em mercados com:

- fim de semana;
- feriados;
- intervalos sem negociação;
- sessão limitada;
- gaps de coleta;

os resultados divergem.

## Exemplo

Para H1:

```text
360 × 1 hora = 15 dias corridos
```

Mas 360 candles H1 de uma sessão B3 podem representar muito mais que 15 dias corridos.

## Correção/validação obrigatória

Confirmar a implementação de `resolveTimeRange()`.

Preferência:

```ts
resolveExpirationByCandleIndex(
  createdAt,
  candles,
  maxAgeBars,
)
```

Fallback aceitável apenas quando não houver candles suficientes:

```text
timeframeSeconds × maxAgeBars
```

O fallback deve ser registrado em debug.

---

# 5. Risco P0 — lifecycle deve prevalecer sobre TTL

A ordem correta é:

```text
mitigatedAt
→ invalidatedAt
→ engineExpiresAt
→ fallback visual por barras
```

Não permitir que o TTL:

- prolongue uma zona já mitigada;
- substitua `invalidatedAt`;
- mantenha estrutura após sweep;
- termine antes de um endpoint técnico explícito, salvo política visual declarada.

## Testes

- zona mitigada antes do TTL;
- zona invalidada antes do TTL;
- zona ativa até o TTL;
- zona com `expiresAt` do engine;
- zona sem endpoint técnico.

---

# 6. Risco P0 — clustering pode esconder confluências

## Problema

OB, FVG e BPR sobrepostos podem representar confluência, e não duplicação.

Não agrupar indiscriminadamente zonas de tipos diferentes.

## Política recomendada

### Agrupamento direto

Permitido entre:

```text
OB + OB
FVG + FVG
BPR + BPR
LIQ + LIQ
```

### Confluência composta

Entre tipos diferentes:

```text
OB + FVG
OB + BPR
FVG + BPR
```

manter uma entidade composta:

```ts
interface ZoneCluster {
  dominantZone: RenderableZone
  members: RenderableZone[]
  types: SmcType[]
  confluenceCount: number
}
```

## Visual

```text
OB + FVG ×2
```

Não reduzir tudo para apenas “OB”.

---

# 7. Risco P0 — LabelPlacer precisa ser global por frame

## Problema

Se cada renderer criar sua própria instância de `LabelPlacer`, colisões serão evitadas apenas dentro do mesmo tipo.

Exemplo:

```text
OB não colide com outro OB
mas pode colidir com FVG, BOS, CHOCH ou PDH
```

## Implementação correta

Criar uma única instância por ciclo de render:

```ts
const labelPlacer = new LabelPlacer(viewport)
```

Passar a mesma instância para todos os renderers.

Limpar somente no início do frame:

```ts
labelPlacer.reset()
```

## Gate

Testar colisão entre:

- OB e FVG;
- BOS e CHOCH;
- PDH e preço atual;
- LIQ e OB;
- labels de tipos distintos no mesmo preço.

---

# 8. Risco P1 — deslocamento alternativo pode distorcer o significado

`findAlternativeY()` reposiciona labels.

O label não deve se afastar tanto do nível original que pareça apontar para outra zona.

## Regras

- deslocamento máximo: 12–16 px;
- se exceder, ocultar;
- opcionalmente desenhar leader line;
- manter label dentro da viewport;
- nunca atravessar o preço atual.

---

# 9. Risco P1 — ATR inválido no ranking e clustering

Confirmar comportamento quando:

- ATR é zero;
- ATR é `NaN`;
- ATR ainda não está disponível;
- existem poucos candles;
- timeframe acaba de mudar.

## Fallback

```ts
effectiveAtr =
  validAtr ??
  medianRange ??
  Math.max(price * 0.001, tickSize * 20)
```

Nunca permitir:

```text
score = NaN
distance = Infinity não tratado
clusterThreshold = 0
```

---

# 10. Risco P1 — hysteresis precisa manter estado

Hysteresis não funciona corretamente se `getDetailLevel()` for totalmente stateless.

A função deve receber o nível anterior:

```ts
getDetailLevel(
  barsVisible,
  previousLevel,
)
```

## Transições

```text
DETAIL → STANDARD somente acima de 135
STANDARD → DETAIL somente abaixo de 110

STANDARD → OVERVIEW somente acima de 520
OVERVIEW → STANDARD somente abaixo de 470
```

## Reset

O nível anterior deve ser resetado ao mudar:

- ativo;
- timeframe;
- Replay/live;
- nova instância do chart.

---

# 11. Risco P1 — pipeline pode recomputar em excesso

Ranking e clustering podem ser custosos.

Se executados em todo movimento de mouse ou todo redraw:

- FPS cai;
- pan fica pesado;
- labels piscam;
- CPU aumenta.

## Estratégia

Recalcular seleção somente quando mudar:

- conjunto de zonas;
- timeframe;
- preço de referência relevante;
- detail level;
- faixa visível em quantidade significativa;
- toggles/preset.

Não recalcular em cada crosshair move.

## Cache

Chave sugerida:

```text
queryKey
zoneRevision
timeframe
detailLevel
visibleRangeBucket
priceBucket
toggleHash
```

---

# 12. Autoscale — ainda precisa ser confirmado

O resumo não menciona correção explícita de autoscale.

## Teste obrigatório

Injetar uma zona:

```text
50% acima do preço atual
```

Resultado esperado:

- escala dos candles não muda;
- overlay distante é filtrado ou desenhado sem autoscale;
- zoom automático não comprime candles.

---

# 13. Viewport clipping — ainda precisa ser confirmado

PDH/PDL foi cortado ao intervalo temporal da zona, mas todas as primitives devem ser cortadas à viewport.

## Aplicar a:

- OB;
- FVG;
- BPR;
- BOS;
- CHOCH;
- LIQ;
- PDH/PDL;
- Session;
- Swing.

## Gate

Nenhum renderer deve construir paths para milhares de barras fora da tela.

---

# 14. Presets

O resumo informa que presets são atendidos por `SmcToggleState`.

Confirmar que existem ações explícitas:

```text
LIMPO
OPERACIONAL
CONTEXTO
COMPLETO
```

## Definições

### Limpo

- EMA 20;
- EMA 200;
- OB ativo;
- FVG ativo;
- BOS/CHOCH principal.

### Operacional

- OB;
- FVG;
- BPR;
- BOS;
- CHOCH;
- LIQ;
- PDH/PDL conforme timeframe.

### Contexto

- zonas HTF;
- níveis semanais/mensais;
- sem microestrutura.

### Completo

- todos os tipos;
- mitigados;
- labels adicionais.

## Persistência

Salvar preferência por usuário ou local storage.

---

# 15. Matriz visual por timeframe

# 15.1 D1

## Esperado

- PDH/PDL = 0;
- no máximo budgets D1;
- zonas macro válidas;
- labels mínimos;
- sem microestrutura intraday;
- sem linhas brancas em massa;
- sem expiração prematura de zonas D1.

## Aprovação

```text
candles legíveis
contexto macro preservado
nenhuma parede vertical
```

---

# 15.2 H4

## Esperado

- máximo 5 PDH/PDL;
- zonas válidas por até 360 barras;
- clustering correto;
- sem retângulos de anos;
- sem perda de OB/FVG macro.

---

# 15.3 H1

## Esperado

- budgets por tipo;
- labels sem colisão;
- estruturas major e internal distinguíveis;
- zonas próximas priorizadas.

---

# 15.4 M15

## Esperado

- máximo 6 PDH/PDL;
- microzonas antigas removidas;
- zoom altera detalhe suavemente;
- labels top rank.

---

# 15.5 M5

## Esperado

- máximo 6 PDH/PDL;
- clustering reduz duplicatas;
- preço atual visível;
- sem bloco denso de labels.

---

# 15.6 M2

## Esperado

- máximo 2 PDH/PDL;
- microestrutura limitada;
- labels críticos;
- detalhe por hover;
- FPS aceitável.

---

# 16. Testes automatizados

# 16.1 `normalizerUtils.ts`

- TTL por índice;
- fallback temporal;
- mitigação antes do TTL;
- invalidação antes do TTL;
- D1 500 barras;
- M2 600 barras;
- gaps de fim de semana;
- sessão sem candles;
- timezone.

# 16.2 `pdhPdlNormalizer.ts`

- D1 retorna zero;
- H1 retorna cinco;
- H4 retorna cinco;
- M15 retorna seis;
- M5 retorna seis;
- M2 retorna dois;
- ordenação por recência;
- intervalo temporal correto.

# 16.3 `smcVisibility.ts`

- budget por tipo;
- ranking estável;
- ATR inválido;
- clustering mesmo tipo;
- confluência tipos diferentes;
- hysteresis;
- reset de identidade;
- ordenação determinística;
- empate de score.

# 16.4 `smcRenderUtils.ts`

- colisão vertical;
- colisão horizontal;
- colisão entre tipos;
- alternativa Y;
- deslocamento máximo;
- viewport bounds;
- ocultação sem espaço.

# 16.5 Renderers

- zona ativa sem borda direita;
- mitigada com borda final;
- clipping;
- opacidade por status;
- label compartilhado;
- autoscale não afetado.

---

# 17. Testes manuais

1. abrir D1;
2. verificar ausência de PDH/PDL;
3. alternar H4, H1, M15, M5 e M2;
4. registrar quantidade por tipo;
5. fazer zoom próximo aos thresholds;
6. confirmar ausência de flicker;
7. pan rápido;
8. prepend histórico;
9. receber candle live;
10. mitigar zona;
11. invalidar zona;
12. alternar presets;
13. entrar em Replay;
14. voltar ao live;
15. testar fullscreen;
16. testar mobile;
17. testar zona distante;
18. medir FPS.

---

# 18. Métricas

Adicionar em desenvolvimento:

```text
timeframe
barsVisible
detailLevel
previousDetailLevel
zonesInput
zonesRanked
clustersCreated
zonesAfterBudget
labelsRequested
labelsPlaced
labelsMoved
labelsHidden
renderDurationMs
fps
```

## Metas iniciais

```text
render SMC < 8 ms em desktop
pan >= 50 FPS em desktop
pan >= 30 FPS em mobile médio
sem long task > 50 ms causada por SMC
```

---

# 19. Critérios de aceite

- [ ] TTL usa barras reais ou fallback documentado.
- [ ] Lifecycle técnico prevalece sobre TTL.
- [ ] Clustering não apaga confluências.
- [ ] LabelPlacer é global por frame.
- [ ] Labels não se sobrepõem entre tipos.
- [ ] Deslocamento de label é limitado.
- [ ] ATR inválido possui fallback.
- [ ] Hysteresis mantém estado.
- [ ] Ranking é determinístico.
- [ ] Budgets são por tipo.
- [ ] PDH/PDL seguem o timeframe.
- [ ] Autoscale não é afetado.
- [ ] Viewport clipping cobre todos os renderers.
- [ ] Presets explícitos existem.
- [ ] Preferência é persistida.
- [ ] D1 passa visualmente.
- [ ] H4 passa visualmente.
- [ ] H1 passa visualmente.
- [ ] M15 passa visualmente.
- [ ] M5 passa visualmente.
- [ ] M2 passa visualmente.
- [ ] Pan/zoom não apresenta flicker.
- [ ] Performance atende às metas.
- [ ] Build passa.
- [ ] Lint passa.
- [ ] Testes passam.
- [ ] Console sem erros.

---

# 20. Gates

```text
GATE_TTL_REAL_BARS: PENDENTE
GATE_LIFECYCLE_PRECEDENCE: PENDENTE
GATE_CLUSTER_SEMANTICS: PENDENTE
GATE_GLOBAL_LABEL_COLLISION: PENDENTE
GATE_ATR_FALLBACK: PENDENTE
GATE_HYSTERESIS_STATE: PENDENTE
GATE_AUTOSCALE: PENDENTE
GATE_VIEWPORT_CLIPPING: PENDENTE
GATE_PRESETS: PENDENTE
GATE_PERFORMANCE: PENDENTE
GATE_VISUAL_D1: PENDENTE
GATE_VISUAL_H4: PENDENTE
GATE_VISUAL_H1: PENDENTE
GATE_VISUAL_M15: PENDENTE
GATE_VISUAL_M5: PENDENTE
GATE_VISUAL_M2: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 21. Ordem recomendada

```text
1. verificar TTL em barras reais
2. confirmar precedência do lifecycle
3. validar clustering por tipo/confluência
4. tornar LabelPlacer global por frame
5. adicionar fallback ATR
6. confirmar estado da hysteresis
7. corrigir autoscale
8. completar viewport clipping
9. criar presets explícitos
10. executar testes automatizados
11. executar matriz visual
12. medir desempenho
13. reauditar código
14. promover para produção
```

---

# 22. Conclusão

A implementação V4 está significativamente mais madura e cobre os itens centrais do plano.

O trabalho restante é de validação e hardening, não de nova arquitetura.

Os dois pontos mais importantes são:

```text
TTL REALMENTE BASEADO EM BARRAS
LABEL COLLISION GLOBAL ENTRE TODOS OS RENDERERS
```

Se ambos estiverem corretos, e autoscale/performance passarem, o gráfico estará próximo do gate de produção.
