# PLANO V4 — CORREÇÃO VISUAL, CICLO DE VIDA E DENSIDADE DOS OVERLAYS DO GRÁFICO

**Projeto:** Maximus Trade  
**Data:** 26/06/2026  
**Base de análise:** capturas dos timeframes D1, H4, H1, M15, M5 e M2  
**Foco:** Lightweight Charts, overlays SMC, PDH/PDL, zonas, labels, autoscale, visibilidade por timeframe e legibilidade  
**Status atual:** `CANDLES_OK_PARCIAL / OVERLAYS_REPROVADOS / PRODUÇÃO_NO_GO`

---

# 1. Objetivo

Corrigir a apresentação visual do gráfico para que:

1. os candles permaneçam legíveis em todos os timeframes;
2. overlays antigos não atravessem indefinidamente o gráfico;
3. PDH/PDL não gerem centenas de linhas simultâneas;
4. zonas mitigadas, invalidadas ou irrelevantes sejam filtradas;
5. labels não se sobreponham;
6. a quantidade de elementos seja adaptada ao zoom e timeframe;
7. D1, H4, H1, M15, M5 e M2 usem políticas visuais diferentes;
8. o gráfico preserve informação técnica sem virar “poluição visual”;
9. overlays não deformem a escala de preço;
10. renderização permaneça estável durante pan, zoom, live e prepend histórico;
11. o comportamento possa ser testado de forma determinística;
12. o usuário possa alternar entre visão limpa, operacional e completa.

---

# 2. Diagnóstico executivo

Os prints mostram que os candles e indicadores principais estão presentes, porém a camada SMC está exibindo dados demais e sem política adequada de ciclo de vida.

## Estado observado

```text
D1:
  PDH/PDL extremamente densos
  centenas de linhas horizontais
  labels repetidos
  candles parcialmente encobertos

H4:
  zonas antigas estendidas por anos
  grandes retângulos cinza sem relevância operacional atual
  bordas verticais longas

H1:
  acúmulo severo de linhas e zonas
  muitas regiões históricas continuam até o candle atual
  labels sobrepostos próximos ao preço atual

M15:
  excesso de zonas horizontais em múltiplas faixas
  estruturas antigas permanecem abertas visualmente
  leitura do preço fica comprometida

M5:
  alta densidade no trecho recente
  labels FVG/OB/BOS colidem
  muitas zonas com cores próximas e pouca hierarquia

M2:
  maior concentração local
  labels sobrepostos
  FVG, OB, BOS e CHOCH disputam o mesmo espaço
  retângulos e linhas encobrem os candles
```

## Conclusão

O problema principal não é mais apenas carregamento de candles. É uma combinação de:

```text
zonas sem expiração visual
+ lifecycle incompleto
+ PDH/PDL históricos persistentes
+ ausência de budgets por timeframe
+ ausência de filtros por zoom
+ labels sem declutter
+ right edge desenhado para todas as zonas
+ cores e espessuras com pouca hierarquia
+ falta de presets
```

---

# 3. Achados por prioridade

# 3.1 P0 — PDH/PDL renderizados como histórico permanente

No D1 e H1 existem dezenas ou centenas de linhas brancas tracejadas, muitas com labels PDH/PDL.

Esse comportamento sugere que cada nível histórico:

- permanece ativo indefinidamente;
- é estendido até o último candle;
- não é limitado por sessão;
- não possui orçamento máximo;
- não é filtrado por timeframe.

## Correção obrigatória

PDH/PDL devem seguir uma política temporal.

### Intraday M2/M5/M15

Mostrar por padrão:

```text
PDH/PDL do dia anterior
PDH/PDL dos últimos 3 pregões, no máximo
```

Opcional em modo completo:

```text
últimos 5 pregões
```

### H1/H4

Mostrar:

```text
últimos 5 a 10 níveis diários relevantes
```

ou substituir por:

```text
previous week high/low
previous month high/low
```

### D1

PDH/PDL devem ficar:

```text
desativados por padrão
```

No D1, níveis diários anteriores geram ruído excessivo. Priorizar:

```text
PWH/PWL
PMH/PML
máximas/mínimas anuais relevantes
```

## Regra de segmento

Um PDH/PDL não deve começar no início do gráfico e atravessar toda a série.

Deve ser desenhado entre:

```text
session_start_atual
→ session_end_atual
```

ou:

```text
data de criação
→ fim da sessão de validade
```

---

# 3.2 P0 — Zonas antigas permanecem abertas até o candle atual

Nas imagens H4, H1, M15, M5 e M2, muitas zonas antigas terminam na mesma coordenada à direita.

Isso indica provável fallback semelhante a:

```text
displayTo = lastCandleTime
```

para qualquer zona sem `display_to`.

## Consequência

- zonas de meses ou anos atrás ficam visualmente ativas;
- todas criam borda direita no mesmo ponto;
- forma-se uma “parede vertical”;
- preço atual fica encoberto;
- zonas invalidadas continuam aparecendo.

## Correção obrigatória

Cada zona deve possuir lifecycle explícito:

```ts
type ZoneLifecycle =
  | 'ACTIVE'
  | 'PARTIALLY_MITIGATED'
  | 'MITIGATED'
  | 'INVALIDATED'
  | 'EXPIRED'
```

E tempos:

```ts
createdAt
firstTouchAt
mitigatedAt
invalidatedAt
expiresAt
displayFrom
displayTo
```

## Regra visual

```text
ACTIVE
  pode estender até o último candle

PARTIALLY_MITIGATED
  pode estender, porém com opacidade menor

MITIGATED
  termina em mitigatedAt

INVALIDATED
  termina em invalidatedAt

EXPIRED
  termina em expiresAt e fica oculto por padrão
```

Não usar `lastCandleTime` indiscriminadamente.

---

# 3.3 P0 — Ausência de orçamento visual por tipo e timeframe

O gráfico renderiza muitos elementos simultaneamente.

Criar limites rígidos por tipo.

## Budget recomendado inicial

| Timeframe | OB | FVG | BOS/CHOCH | Liquidez | PDH/PDL | Sessões |
|---|---:|---:|---:|---:|---:|---:|
| D1 | 8 | 8 | 12 | 6 | 0 | 0 |
| H4 | 12 | 12 | 16 | 8 | 8 | 0 |
| H1 | 16 | 16 | 20 | 10 | 10 | 0 |
| M15 | 20 | 20 | 24 | 12 | 6 | 3 |
| M5 | 24 | 24 | 28 | 14 | 6 | 3 |
| M2 | 20 | 20 | 24 | 12 | 4 | 2 |

Os valores devem ser configuráveis.

## Seleção

Não escolher apenas os últimos por data.

Rankear por:

```text
status ativo
proximidade do preço
força
confluência
timeframe
recência
número de toques
tamanho normalizado por ATR
```

---

# 3.4 P0 — Labels colidem e encobrem candles

Nas imagens M2, M5, M15 e H1, labels OB, FVG, BOS, CHOCH e PDH ocupam a mesma região.

## Correção obrigatória

Criar `LabelCollisionManager`.

### Funções

```ts
canPlaceLabel(rect): boolean
reserveLabel(rect): void
findAlternativeY(...)
hideLowerPriority(...)
```

### Prioridade

```text
1. preço atual
2. CHOCH
3. BOS
4. OB ativo
5. FVG ativo
6. liquidez
7. PDH/PDL
8. zonas mitigadas
```

### Regras

- um label por zona;
- esconder label se largura visual da zona for muito pequena;
- esconder label em zoom muito aberto;
- limitar labels por faixa vertical;
- não desenhar label se colidir com preço atual;
- não repetir PDH/PDL a cada linha;
- permitir tooltip no hover para dados ocultos.

---

# 3.5 P0 — Overlays sem política por zoom

Quando o gráfico está muito afastado, labels e zonas continuam com o mesmo nível de detalhe.

## Correção

Definir nível de detalhe por `barsVisible`.

```ts
type ChartDetailLevel =
  | 'OVERVIEW'
  | 'STANDARD'
  | 'DETAIL'
```

### OVERVIEW

```text
mais de 500 barras visíveis
```

Mostrar:

- candles;
- EMA;
- poucas zonas macro;
- BOS/CHOCH principais;
- sem labels menores;
- sem FVG pequenos;
- sem PDH diários.

### STANDARD

```text
120 a 500 barras
```

Mostrar:

- zonas ativas;
- labels principais;
- liquidez relevante;
- budgets médios.

### DETAIL

```text
menos de 120 barras
```

Mostrar:

- detalhes intraday;
- labels completos;
- FVG menores;
- estruturas locais.

---

# 3.6 P1 — Bordas verticais à direita acumuladas

Muitas zonas desenham uma borda vertical no mesmo `displayTo`.

## Correção

Para zonas ativas:

```text
não desenhar borda direita
```

Usar apenas:

- borda superior;
- borda inferior;
- preenchimento;
- fade para a direita.

Desenhar borda direita somente em:

```text
MITIGATED
INVALIDATED
EXPIRED
```

Isso elimina a parede vertical no último candle.

---

# 3.7 P1 — Escala de preço visualmente comprometida

Algumas zonas muito distantes do preço atual permanecem visíveis, aumentando a faixa vertical e reduzindo o tamanho útil dos candles.

## Correção

Os primitives não devem influenciar o autoscale, salvo explicitamente.

Aplicar filtro de distância:

```text
distância máxima por ATR
ou
percentual do preço atual
```

Recomendação inicial:

| Timeframe | Distância máxima |
|---|---:|
| D1 | 30 ATR |
| H4 | 20 ATR |
| H1 | 15 ATR |
| M15 | 12 ATR |
| M5 | 10 ATR |
| M2 | 8 ATR |

Zonas fora da faixa:

- não entram no renderer principal;
- podem aparecer em painel “zonas distantes”;
- podem ser mostradas sob demanda.

---

# 3.8 P1 — Zonas excessivamente largas no tempo

Retângulos começam muito antes da área relevante e permanecem até o presente.

## Correção

Clip temporal:

```text
desenhar apenas interseção com visibleLogicalRange
```

Mesmo que a zona tenha vida longa, o renderer deve pintar apenas o trecho visível.

Não construir paths fora da viewport.

---

# 3.9 P1 — Cores não comunicam prioridade suficiente

Há muitas linhas verdes, vermelhas, ciano, cinza e amarelas com espessuras semelhantes.

## Hierarquia recomendada

### Ativo e relevante

```text
opacidade 0.24 a 0.32
borda 1.0 a 1.5 px
label visível
```

### Parcialmente mitigado

```text
opacidade 0.12 a 0.18
borda 1 px
label opcional
```

### Mitigado

```text
opacidade 0.04 a 0.08
borda pontilhada
sem label por padrão
```

### Inválido/expirado

```text
oculto por padrão
```

---

# 3.10 P1 — Todos os tipos parecem ativos por padrão

A barra lateral mostra diversos tipos SMC simultaneamente.

Criar presets.

## Preset Limpo

```text
EMA20
EMA200
OB ativo
FVG ativo
BOS/CHOCH principal
preço atual
```

## Preset Operacional

```text
OB
FVG
BOS
CHOCH
liquidez
PDH/PDL limitados
```

## Preset Completo

```text
todos os tipos
inclui mitigados
labels adicionais
```

## Preset Contexto

```text
zonas de timeframe superior
níveis semanais/mensais
sem microestrutura
```

O padrão inicial deve ser `Limpo` ou `Operacional`, não `Completo`.

---

# 4. Diagnóstico por timeframe

# 4.1 D1

## Problemas observados

- PDH/PDL dominam o gráfico;
- linhas brancas se acumulam em larga faixa de preço;
- labels PDH se repetem;
- candles ficam parcialmente ocultos;
- excesso de detalhe diário em visão macro.

## Política correta

```text
PDH/PDL: OFF
PWH/PWL: ON
PMH/PML: ON
OB ativos: máximo 8
FVG ativos: máximo 8
BOS/CHOCH: apenas major structure
labels: somente zonas mais próximas
```

---

# 4.2 H4

## Problemas observados

- grandes zonas cinza antigas;
- algumas começam anos antes e terminam no presente;
- baixa relevância operacional de parte delas;
- retângulos ocupam grandes áreas vazias.

## Política correta

```text
mostrar somente zonas ativas/relevantes
máximo 12 OB + 12 FVG
ocultar mitigadas por padrão
limitar distância em ATR
sem sessões intraday
PDH/PDL limitados
```

---

# 4.3 H1

## Problemas observados

- alta densidade de zonas no preço atual;
- muitas linhas horizontais brancas;
- labels PDH e estruturas se sobrepõem;
- histórico antigo continua visualmente ativo.

## Política correta

```text
budget médio
PDH/PDL últimos 5 a 10
major + internal structure diferenciados
labels por prioridade
zonas mitigadas ocultas
```

---

# 4.4 M15

## Problemas observados

- várias camadas de zonas de diferentes origens;
- linhas longas ocupam quase toda a largura;
- estrutura passada continua no presente;
- candles recentes perdem destaque.

## Política correta

```text
zonas ativas próximas ao preço
últimos 3 pregões para níveis diários
expiração de microzonas
labels adaptativos
máximo 20 por tipo principal
```

---

# 4.5 M5

## Problemas observados

- concentração de FVG/OB/BOS perto do preço;
- labels colidem;
- múltiplas zonas muito semelhantes;
- pouca distinção entre principal e secundária.

## Política correta

```text
cluster por faixa de preço
escolher zona dominante por cluster
agrupar zonas sobrepostas
labels apenas para top rank
```

---

# 4.6 M2

## Problemas observados

- maior nível de poluição local;
- microzonas demais;
- labels FVG, CHOCH e OB simultâneos;
- retângulos encobrem a ação de preço;
- estrutura de baixa qualidade recebe o mesmo peso visual.

## Política correta

```text
janela temporal curta
máximo 2 pregões visíveis para microestrutura
filtro mínimo por tamanho/ATR
filtro mínimo por score
cluster agressivo
labels somente em hover ou top rank
```

---

# 5. Arquitetura recomendada

```text
SMC API / snapshots
        │
        ▼
ZoneLifecycleNormalizer
        │
        ▼
ZoneRanker
        │
        ▼
TimeframeVisibilityPolicy
        │
        ▼
ViewportFilter
        │
        ▼
ClusterAndDeduplicate
        │
        ▼
LabelCollisionManager
        │
        ▼
Primitive Renderer
```

---

# 6. Novos módulos

Criar:

```text
src/components/chart/smc/policy/
├── timeframePolicy.ts
├── overlayBudgets.ts
├── lifecyclePolicy.ts
├── zoomDetailPolicy.ts
└── overlayPresets.ts

src/components/chart/smc/ranking/
├── zoneRanker.ts
├── zoneDistance.ts
└── zoneClusterer.ts

src/components/chart/smc/layout/
├── LabelCollisionManager.ts
├── labelPriority.ts
└── viewportClip.ts

src/components/chart/smc/debug/
├── SmcDebugPanel.tsx
└── rendererMetrics.ts
```

---

# 7. Contratos recomendados

```ts
interface RenderableZone {
  id: string
  type: SmcType
  timeframe: string

  priceTop: number
  priceBottom: number

  createdAt: number
  displayFrom: number
  displayTo: number | null

  status:
    | 'ACTIVE'
    | 'PARTIALLY_MITIGATED'
    | 'MITIGATED'
    | 'INVALIDATED'
    | 'EXPIRED'

  strength: number
  confluence: number
  touchCount: number
  rankScore: number

  label: string
  sourceTimeframe?: string
}
```

---

# 8. Ranking

Exemplo de score:

```ts
score =
  statusWeight +
  proximityWeight +
  strengthWeight +
  confluenceWeight +
  recencyWeight -
  touchPenalty -
  agePenalty
```

## Status weight

```text
ACTIVE: +100
PARTIALLY_MITIGATED: +60
MITIGATED: +10
INVALIDATED: -100
EXPIRED: -200
```

## Proximidade

Baseada em ATR:

```text
0 a 1 ATR: alta prioridade
1 a 3 ATR: média
3 a 8 ATR: baixa
acima do limite: ocultar
```

---

# 9. Clustering

Zonas sobrepostas ou muito próximas devem ser agrupadas.

## Regra inicial

Considerar mesmo cluster quando:

```text
overlap vertical >= 60%
ou
distância entre midpoints <= 0.25 ATR
```

## Seleção

- manter a zona de maior score;
- opcionalmente mostrar contador:

```text
OB ×3
FVG ×2
```

- revelar detalhes no hover.

---

# 10. Labels

## Estratégia por detalhe

### Overview

- sem labels de FVG/OB menores;
- apenas major BOS/CHOCH;
- labels semanais/mensais.

### Standard

- labels das zonas top rank;
- máximo de 12 labels simultâneos.

### Detail

- máximo de 24 labels;
- hover para detalhes;
- collision manager ativo.

## Distância mínima

```text
4 px vertical
6 px horizontal
```

## Fallback

Se não houver espaço:

```text
não desenhar label
```

Nunca sobrepor texto indiscriminadamente.

---

# 11. Renderização de zonas

# 11.1 Ativas

- sem borda direita;
- fill suave;
- bordas superior/inferior;
- extensão até último candle.

# 11.2 Mitigadas

- terminar no momento de mitigação;
- opacidade baixa;
- sem label por padrão.

# 11.3 Invalidadas

- terminar na invalidação;
- ocultas por padrão.

# 11.4 Expiradas

- não renderizar.

---

# 12. Renderização de BOS/CHOCH

- segmento limitado ao evento;
- não estender indefinidamente;
- label perto da quebra;
- diferenciar major e internal;
- major com espessura maior;
- internal com opacidade menor;
- ocultar internal em overview.

---

# 13. Renderização de liquidez

- não mostrar todos os níveis históricos;
- agrupar EQH/EQL próximos;
- eliminar níveis já tomados;
- terminar no sweep;
- labels somente no primeiro ponto visível;
- limitar por distância do preço.

---

# 14. PDH/PDL, PWH/PWL e PMH/PML

Criar tipos distintos.

```ts
type ReferenceLevelType =
  | 'PDH'
  | 'PDL'
  | 'PWH'
  | 'PWL'
  | 'PMH'
  | 'PML'
```

Cada nível deve possuir:

```text
validFrom
validTo
takenAt
status
```

Após ser tomado:

```text
terminar no takenAt
ou
mostrar como histórico somente no modo completo
```

---

# 15. Autoscale

Garantir que:

- primitives não alterem autoscale;
- zonas distantes não expandam preço;
- RSI continue isolado em sua escala;
- indicadores não criem whitespace indevido;
- preço atual permaneça centralizável.

Adicionar testes com uma zona extrema, por exemplo 50% distante do preço.

---

# 16. Debug panel

Criar painel de desenvolvimento com:

```text
barsVisible
detailLevel
zonesReceived
zonesAfterLifecycle
zonesAfterDistance
zonesAfterCluster
zonesRendered
labelsRequested
labelsRendered
labelsHidden
renderDurationMs
```

Filtros:

- tipo;
- timeframe;
- status;
- score;
- distância ATR.

---

# 17. Fases de implementação

# Fase 0 — Instrumentação

1. registrar quantidade por tipo;
2. registrar status;
3. medir render time;
4. medir labels;
5. confirmar lifecycle vindo da API;
6. confirmar `display_to`.

**Gate:** relatório por timeframe.

---

# Fase 1 — Lifecycle

1. implementar estados;
2. terminar zonas mitigadas;
3. terminar invalidadas;
4. expirar antigas;
5. remover fallback global até último candle.

**Gate:** nenhuma zona inválida atravessa o presente.

---

# Fase 2 — PDH/PDL

1. segmentar por sessão;
2. limitar quantidade;
3. desabilitar no D1;
4. introduzir níveis semanais/mensais;
5. terminar quando tomado.

**Gate:** D1 sem faixa de centenas de linhas brancas.

---

# Fase 3 — Policies por timeframe

1. budgets;
2. tipos permitidos;
3. distância ATR;
4. janela temporal;
5. defaults.

**Gate:** cada timeframe possui densidade distinta.

---

# Fase 4 — Ranking e clustering

1. calcular score;
2. cluster vertical;
3. deduplicar;
4. selecionar dominante;
5. mostrar contador opcional.

**Gate:** redução significativa sem perda das zonas principais.

---

# Fase 5 — Labels

1. collision manager;
2. prioridades;
3. limite;
4. hover;
5. níveis de detalhe.

**Gate:** nenhum label sobreposto em cenários de teste.

---

# Fase 6 — Zoom adaptativo

1. calcular `barsVisible`;
2. definir detail level;
3. ajustar budgets;
4. ocultar labels;
5. reduzir microestrutura.

**Gate:** overview legível e detail informativo.

---

# Fase 7 — Renderer visual

1. remover borda direita de zonas ativas;
2. aplicar opacidades por status;
3. diferenciar major/internal;
4. clip viewport;
5. cores e espessuras.

**Gate:** sem parede vertical no último candle.

---

# Fase 8 — Presets

1. Limpo;
2. Operacional;
3. Completo;
4. Contexto;
5. persistência da preferência.

**Gate:** usuário troca preset sem recarregar chart.

---

# Fase 9 — Autoscale e performance

1. excluir overlays do autoscale;
2. filtrar distância;
3. medir FPS;
4. reduzir allocations;
5. cache por viewport.

**Gate:** pan/zoom fluido.

---

# Fase 10 — Testes por timeframe

Executar D1, H4, H1, M15, M5 e M2.

---

# 18. Testes visuais obrigatórios

## D1

- PDH/PDL desligados;
- no máximo 8 OB e 8 FVG;
- candles legíveis;
- sem parede branca.

## H4

- nenhuma zona antiga irrelevante até o presente;
- no máximo 12 por tipo principal;
- escala preservada.

## H1

- PDH/PDL limitados;
- labels sem colisão;
- zonas próximas priorizadas.

## M15

- microzonas expiradas;
- apenas últimos pregões relevantes;
- candles visíveis.

## M5

- clustering;
- labels top rank;
- sem sobreposição local.

## M2

- janela curta;
- microestrutura filtrada;
- detalhe disponível em hover;
- FPS aceitável.

---

# 19. Testes funcionais

- trocar timeframe;
- pan;
- zoom;
- prepend;
- live;
- Replay;
- ativar/desativar tipo;
- trocar preset;
- resize;
- mobile;
- fullscreen;
- carregar histórico;
- último candle novo;
- zona mitigada live;
- zona invalidada live.

---

# 20. Critérios de aceite

- [ ] D1 não mostra PDH/PDL históricos em massa.
- [ ] Zonas mitigadas terminam na mitigação.
- [ ] Zonas invalidadas não atravessam o presente.
- [ ] Zonas ativas não desenham borda direita.
- [ ] Budgets são aplicados por timeframe.
- [ ] Zoom altera nível de detalhe.
- [ ] Labels não se sobrepõem.
- [ ] Candles permanecem legíveis.
- [ ] Preço atual não é coberto por múltiplos labels.
- [ ] Overlays não alteram autoscale.
- [ ] Clustering reduz zonas repetidas.
- [ ] Preset Limpo é utilizável.
- [ ] Preset Operacional mantém informação essencial.
- [ ] Preset Completo continua disponível.
- [ ] Pan e zoom permanecem fluidos.
- [ ] Nenhum erro no console.
- [ ] Build passa.
- [ ] Lint passa.
- [ ] Testes passam.

---

# 21. Ordem recomendada

```text
1. Lifecycle
2. PDH/PDL
3. Policies por timeframe
4. Budgets
5. Ranking
6. Clustering
7. Labels
8. Zoom adaptativo
9. Renderer visual
10. Presets
11. Autoscale
12. Performance
13. Testes
```

Não começar por alterações cosméticas de cores antes de corrigir lifecycle e budgets.

---

# 22. Gates

```text
GATE_LIFECYCLE
GATE_REFERENCE_LEVELS
GATE_TIMEFRAME_POLICY
GATE_BUDGETS
GATE_LABELS
GATE_ZOOM_DETAIL
GATE_AUTOSCALE
GATE_PERFORMANCE
GATE_VISUAL_D1
GATE_VISUAL_H4
GATE_VISUAL_H1
GATE_VISUAL_M15
GATE_VISUAL_M5
GATE_VISUAL_M2
GATE_PRODUCTION
```

---

# 23. Estado atual revisado

```text
CANDLES:
  aparentemente carregados em todos os timeframes

EMA/RSI:
  visualmente presentes

HISTÓRICO:
  melhorado, mas continuidade deve continuar sendo testada

SMC:
  funcionalmente presente
  visualmente não aprovado

PDH/PDL:
  reprovado

LABELS:
  reprovado

DENSIDADE:
  reprovada

LIFECYCLE:
  provável implementação incompleta

PRODUÇÃO:
  NO_GO
```

---

# 24. Conclusão

As capturas comprovam que o próximo problema prioritário não é mais apenas o carregamento de candles.

O gráfico precisa de uma camada de decisão visual entre os dados SMC e os renderers:

```text
dados brutos
→ lifecycle
→ ranking
→ policy por timeframe
→ budget
→ clustering
→ collision manager
→ renderer
```

Sem essa camada, qualquer aumento no histórico continuará aumentando a poluição visual.

O objetivo da V4 é tornar o gráfico tecnicamente rico, mas operacionalmente legível.
