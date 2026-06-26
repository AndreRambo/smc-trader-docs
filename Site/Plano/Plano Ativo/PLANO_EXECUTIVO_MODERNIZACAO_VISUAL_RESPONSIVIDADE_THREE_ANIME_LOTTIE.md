# PLANO EXECUTIVO — MODERNIZAÇÃO VISUAL, RESPONSIVIDADE E EXPERIÊNCIA MOBILE

**Projeto:** Frontend Maximus Trade / Plataforma de Trading  
**Base auditada:** `frontend.zip`  
**Data:** 26/06/2026  
**Documento:** Plano de implementação para outra IA executar  
**Escopo:** UI/UX, responsividade, mobile, design system, landing page, app shell, gráfico, Anime.js, Lottie, Three.js, acessibilidade e performance  
**Status inicial:** `PRONTO_PARA_EXECUÇÃO_COM_GATES`

---

# 1. Objetivo

Modernizar o frontend existente para que a plataforma:

1. funcione corretamente em desktop, tablet e smartphone;
2. ofereça uma experiência de trading focada, limpa e rápida;
3. tenha uma landing page mais moderna, visualmente forte e orientada à conversão;
4. use Three.js apenas onde agrega valor institucional e de marketing;
5. use Anime.js para microinterações e transições de interface;
6. use Lottie para estados de carregamento, onboarding e feedback;
7. mantenha o Lightweight Charts como núcleo exclusivo do gráfico financeiro;
8. preserve desempenho, acessibilidade e legibilidade;
9. respeite `prefers-reduced-motion`;
10. reduza efeitos globais excessivos;
11. diminua o bundle inicial e o custo de renderização;
12. evite introduzir regressões no gráfico, Replay, SMC, IA e área administrativa.

---

# 2. Princípios obrigatórios

## 2.1 Separar marketing de plataforma

A landing page pode ser expressiva, animada e imersiva.

A plataforma de trading deve ser:

- precisa;
- sóbria;
- legível;
- rápida;
- com baixa distração;
- centrada no gráfico e nas decisões.

## 2.2 Não usar Three.js atrás do gráfico

Three.js não deve ser renderizado:

- atrás do Lightweight Charts;
- dentro da área operacional;
- nas páginas administrativas;
- durante Replay;
- em todas as rotas.

## 2.3 Não animar candles com Anime.js

Candles, indicadores e overlays continuam sob controle do Lightweight Charts.

Anime.js deve animar apenas:

- painéis;
- drawers;
- bottom sheets;
- tabs;
- badges;
- toolbars;
- estados visuais;
- transições de página.

## 2.4 Lottie somente para estados relevantes

Lottie deve ser usado em:

- onboarding;
- IA analisando;
- carregamento;
- sucesso;
- vazio;
- erro recuperável;
- conexão;
- alertas.

Não usar Lottie em:

- todos os botões;
- backgrounds permanentes;
- cards comuns;
- loops decorativos contínuos.

## 2.5 Mobile-first

Toda nova interface deve ser desenhada primeiro para:

```text
360 × 800
390 × 844
412 × 915
```

Depois adaptar para:

```text
768 × 1024
1024 × 768
1366 × 768
1440 × 900
1920 × 1080
```

---

# 3. Diagnóstico visual atual

## 3.1 Problemas principais

1. efeitos globais competem com o conteúdo;
2. landing e plataforma usam linguagem visual muito semelhante;
3. sidebar e painéis não estão preparados para telas pequenas;
4. gráfico não possui toolbar mobile própria;
5. tooltip do chart não está otimizado para toque;
6. painel de IA lateral ocupa espaço excessivo;
7. navegação não possui app shell mobile;
8. tamanhos de toque são pequenos;
9. algumas fontes no gráfico são pequenas;
10. há excesso de violeta, glow e transparência;
11. o hero da landing perde conteúdo visual no mobile;
12. Plotly e rotas pesadas entram no bundle inicial;
13. TickerTape global gera custo visual e de rede desnecessário;
14. não existe uma política clara de movimento;
15. não há sistema explícito de reduced motion.

---

# 4. Arquitetura visual alvo

```text
Frontend
├── MarketingShell
│   ├── Landing
│   ├── Login
│   ├── Register
│   └── Three.js / Anime.js / Lottie
│
├── TradingShell
│   ├── DesktopSidebar
│   ├── MobileTopBar
│   ├── MobileBottomNav
│   ├── ChartWorkspace
│   ├── AnalysisPanel
│   └── BottomSheets
│
└── AdminShell
    ├── Sidebar administrativa
    ├── Header simples
    └── Sem efeitos pesados
```

---

# 5. Estrutura de arquivos recomendada

Criar ou reorganizar:

```text
src/
├── app/
│   ├── routes/
│   ├── shells/
│   │   ├── MarketingShell.tsx
│   │   ├── TradingShell.tsx
│   │   ├── AdminShell.tsx
│   │   └── MobileAppShell.tsx
│   └── providers/
│
├── components/
│   ├── layout/
│   │   ├── DesktopSidebar.tsx
│   │   ├── MobileTopBar.tsx
│   │   ├── MobileBottomNav.tsx
│   │   ├── ResponsiveDrawer.tsx
│   │   ├── BottomSheet.tsx
│   │   ├── FocusMode.tsx
│   │   └── SafeArea.tsx
│   │
│   ├── chart/
│   │   ├── ChartToolbarDesktop.tsx
│   │   ├── ChartToolbarMobile.tsx
│   │   ├── ChartMobileHud.tsx
│   │   ├── IndicatorSheet.tsx
│   │   ├── StructureSheet.tsx
│   │   ├── ReplaySheet.tsx
│   │   ├── AnalysisBottomSheet.tsx
│   │   └── ChartFullscreenButton.tsx
│   │
│   ├── motion/
│   │   ├── MotionProvider.tsx
│   │   ├── AnimatedNumber.tsx
│   │   ├── FadeIn.tsx
│   │   ├── StaggerList.tsx
│   │   ├── RouteTransition.tsx
│   │   └── ReducedMotionFallback.tsx
│   │
│   ├── lottie/
│   │   ├── LottieState.tsx
│   │   ├── AiProcessingAnimation.tsx
│   │   ├── EmptyStateAnimation.tsx
│   │   ├── SuccessAnimation.tsx
│   │   └── ConnectionAnimation.tsx
│   │
│   ├── three/
│   │   ├── HeroScene.tsx
│   │   ├── HeroSceneCanvas.tsx
│   │   ├── AdaptiveThreeQuality.ts
│   │   └── HeroSceneFallback.tsx
│   │
│   └── ui/
│       ├── Button.tsx
│       ├── Card.tsx
│       ├── Badge.tsx
│       ├── Sheet.tsx
│       ├── Tabs.tsx
│       ├── Tooltip.tsx
│       └── Skeleton.tsx
│
├── design-system/
│   ├── tokens.css
│   ├── colors.css
│   ├── typography.css
│   ├── spacing.css
│   ├── motion.css
│   └── breakpoints.ts
│
├── hooks/
│   ├── useBreakpoint.ts
│   ├── useReducedMotion.ts
│   ├── useOrientation.ts
│   ├── useFullscreen.ts
│   ├── useSafeArea.ts
│   └── useAdaptivePerformance.ts
│
└── assets/
    ├── lottie/
    ├── three/
    └── screenshots/
```

---

# 6. Design system

# 6.1 Cores

Criar tokens semânticos:

```css
:root {
  --bg-0: #08090d;
  --bg-1: #0d0f16;
  --surface-1: #121520;
  --surface-2: #181c29;
  --surface-3: #202536;

  --border-subtle: rgba(255, 255, 255, 0.08);
  --border-active: rgba(139, 92, 246, 0.45);

  --text-primary: #f6f7fb;
  --text-secondary: #a8afc3;
  --text-muted: #727b91;

  --accent: #8b5cf6;
  --accent-soft: rgba(139, 92, 246, 0.15);

  --info: #22d3ee;
  --success: #22c55e;
  --danger: #ef4444;
  --warning: #f59e0b;

  --market-buy: #22c55e;
  --market-sell: #ef4444;
}
```

## Regras

- violeta: ação primária e seleção;
- ciano: informação;
- verde: compra, sucesso e alta;
- vermelho: venda, erro e baixa;
- âmbar: atenção;
- neutros: estrutura principal.

Não usar violeta para todas as bordas.

---

# 6.2 Superfícies

```text
Surface 0
  fundo principal

Surface 1
  barras e painéis

Surface 2
  cards e drawers

Surface 3
  hover, seleção e estado ativo
```

## Regras

- remover glow permanente de cards comuns;
- glow forte apenas em CTA principal;
- evitar múltiplas sombras;
- usar bordas discretas;
- limitar blur de backdrop em mobile.

---

# 6.3 Tipografia

## Marketing

- títulos display;
- tamanhos responsivos com `clamp`;
- uso controlado de gradiente;
- parágrafos largos e bem espaçados.

## Plataforma

- fonte de UI simples;
- fonte mono para valores;
- evitar fonte display em filtros e tabelas;
- preços e percentuais com tabular numbers.

Exemplo:

```css
.price {
  font-variant-numeric: tabular-nums;
}
```

---

# 6.4 Espaçamento

Criar escala:

```css
--space-1: 4px;
--space-2: 8px;
--space-3: 12px;
--space-4: 16px;
--space-5: 20px;
--space-6: 24px;
--space-8: 32px;
--space-10: 40px;
--space-12: 48px;
```

---

# 6.5 Bordas e raios

```css
--radius-sm: 8px;
--radius-md: 12px;
--radius-lg: 16px;
--radius-xl: 24px;
```

Evitar raios excessivos em toolbars e tabelas.

---

# 7. App shell responsivo

# 7.1 Desktop

```text
┌───────────────────────────────────────────────────────────┐
│ Topbar / ativo / preço / conexão / ações                 │
├──────────┬─────────────────────────────────────┬──────────┤
│ Sidebar  │                                     │ IA /     │
│          │              GRÁFICO                │ Setup    │
│          │                                     │          │
├──────────┴─────────────────────────────────────┴──────────┤
│ Status / notificações / contexto                        │
└───────────────────────────────────────────────────────────┘
```

## Requisitos

- sidebar recolhível;
- painel direito redimensionável;
- modo foco;
- toolbar compacta;
- chart usa área restante;
- sem scroll vertical no workspace principal.

---

# 7.2 Tablet

```text
┌─────────────────────────────────┐
│ Topbar                          │
├────────┬────────────────────────┤
│ Nav    │ Gráfico                │
│ mini   │                        │
├────────┴────────────────────────┤
│ Painel IA como drawer           │
└─────────────────────────────────┘
```

---

# 7.3 Mobile retrato

```text
┌────────────────────────────┐
│ WINFUT  186.095    ● LIVE │
├────────────────────────────┤
│                            │
│          GRÁFICO           │
│                            │
├────────────────────────────┤
│ SMC  Indic.  IA  Mais      │
├────────────────────────────┤
│ Mercado Gráfico Alertas Eu │
└────────────────────────────┘
```

## Requisitos

- topbar fixa;
- bottom nav fixa;
- safe-area;
- chart em `100dvh`;
- painel IA em bottom sheet;
- toolbar do chart separada;
- sem sidebar lateral.

---

# 7.4 Mobile paisagem

```text
┌─────────────────────────────────────┬────┐
│                                     │SMC │
│               GRÁFICO               │EMA │
│                                     │RSI │
└─────────────────────────────────────┴────┘
```

## Requisitos

- bottom nav oculta;
- topbar reduzida;
- chart em tela cheia;
- toolbar vertical;
- modo foco automático opcional.

---

# 8. Gráfico mobile

# 8.1 Toolbar desktop

Manter:

- ativo;
- timeframe;
- indicadores;
- SMC;
- Replay;
- foco;
- fullscreen;
- conexão.

---

# 8.2 Toolbar mobile

Mostrar apenas:

```text
[Ativo] [TF] [Indicadores] [Mais]
```

Abrir configurações em sheets.

---

# 8.3 IndicatorSheet

Controles:

- EMA 20;
- EMA 200;
- RSI;
- volume;
- espessura;
- visibilidade;
- reset.

---

# 8.4 StructureSheet

Controles:

- OB;
- FVG;
- BOS;
- CHOCH;
- liquidez;
- PDH/PDL;
- sessões;
- Wyckoff;
- Elliott;
- reset de overlays.

---

# 8.5 ReplaySheet

Controles:

- data;
- hora;
- velocidade;
- play;
- pause;
- avançar;
- voltar;
- ir ao início;
- sair do Replay.

---

# 8.6 Tooltip mobile

Em touch:

- não seguir o dedo;
- mostrar HUD fixo;
- evitar cobrir candles;
- permitir fechamento.

Formato:

```text
15/06 14:25
O 186.067  H 186.195  L 186.052  C 186.093
EMA20 186.011  EMA200 186.684
```

---

# 8.7 Áreas de toque

Mínimo:

```text
44 × 44 px
```

Aplicar em:

- botões;
- tabs;
- ícones;
- timeframe;
- seleção de ativo;
- fechamento de painel.

---

# 8.8 Modo foco

Ao ativar:

- ocultar TickerTape;
- ocultar navegação;
- ocultar painel IA;
- maximizar chart;
- reduzir background;
- manter apenas toolbar essencial.

---

# 9. Painel IA e oportunidades

# 9.1 Desktop

- painel lateral redimensionável;
- recolhível;
- tabs:
  - análise;
  - setup;
  - risco;
  - contexto;
  - histórico.

---

# 9.2 Mobile

Converter para bottom sheet:

```text
recolhido
meia altura
quase tela cheia
```

## Interações

- arrastar;
- botão expandir;
- swipe para fechar;
- snap points;
- animação com Anime.js;
- bloquear body scroll quando expandido.

---

# 10. Landing page moderna

# 10.1 Estrutura

```text
1. Hero
2. Demonstração real
3. Como funciona
4. SMC + Wyckoff + Elliott
5. Inteligência Artificial
6. Replay
7. Alertas mobile
8. Segurança e precisão
9. Planos
10. CTA final
```

---

# 10.2 Hero

## Conteúdo

Título:

```text
Leia o mercado antes de tomar a decisão.
```

Subtítulo:

```text
SMC, Wyckoff, Elliott, contexto multi-timeframe
e inteligência artificial em uma única plataforma.
```

CTAs:

```text
Experimentar plataforma
Ver demonstração
```

## Visual

Desktop:

- cena Three.js;
- mockup do gráfico;
- cards de sinal;
- movimento sutil.

Mobile:

- mockup estático ou animação simplificada;
- cena 3D desativada em hardware fraco;
- CTA acima da dobra.

---

# 10.3 Demonstração do produto

Criar seção com:

- screenshot real;
- hotspots;
- abas SMC / IA / Replay;
- transições Anime.js;
- vídeo curto opcional;
- fallback estático.

---

# 10.4 Prova de valor

Mostrar:

- multi-timeframe;
- estrutura;
- risco;
- evidências;
- alertas;
- Replay;
- visão mobile.

Evitar números ou promessas não comprovadas.

---

# 11. Three.js

# 11.1 Uso permitido

- hero da landing;
- login opcional;
- seção institucional;
- visualização conceitual de mercado;
- mapa abstrato de oportunidades.

---

# 11.2 HeroScene

Conceito:

```text
Market Pulse
├── candles abstratos
├── linhas de liquidez
├── pontos conectados
├── ondas de fluxo
└── câmera lenta
```

---

# 11.3 Qualidade adaptativa

Criar níveis:

```ts
type ThreeQuality = 'off' | 'low' | 'medium' | 'high'
```

Critérios:

- mobile;
- DPR;
- memória;
- FPS;
- reduced motion;
- visibilidade da aba.

## Regras

```text
high
  desktop potente

medium
  desktop comum

low
  tablet / mobile potente

off
  reduced motion / hardware fraco
```

---

# 11.4 Performance

- lazy import;
- carregar apenas quando hero estiver perto da viewport;
- pausar quando aba perde foco;
- limitar pixel ratio;
- remover listeners no cleanup;
- não usar sombras pesadas;
- evitar pós-processamento caro;
- fallback estático.

---

# 12. Anime.js

# 12.1 Usos

- entrada do hero;
- stagger de cards;
- tabs;
- drawers;
- bottom sheets;
- badges;
- contador numérico;
- transição de rota;
- feedback de sucesso;
- expansão de painel.

---

# 12.2 Não usar em

- candles;
- crosshair;
- linhas do chart;
- overlays SMC;
- loop contínuo de botões;
- todas as transições ao mesmo tempo.

---

# 12.3 Motion tokens

```css
--motion-instant: 100ms;
--motion-fast: 160ms;
--motion-normal: 240ms;
--motion-slow: 420ms;
```

---

# 12.4 Curvas

```css
--ease-standard: cubic-bezier(0.2, 0, 0, 1);
--ease-enter: cubic-bezier(0, 0, 0.2, 1);
--ease-exit: cubic-bezier(0.4, 0, 1, 1);
```

---

# 13. Lottie

# 13.1 Componentes

Criar componente genérico:

```ts
interface LottieStateProps {
  animationData: object
  loop?: boolean
  autoplay?: boolean
  size?: number
  label?: string
}
```

---

# 13.2 Estados

- IA analisando;
- conectando;
- sem oportunidades;
- sucesso;
- erro recuperável;
- onboarding;
- compra concluída;
- alerta criado.

---

# 13.3 Carregamento

- importar dinamicamente;
- não colocar animações grandes no bundle inicial;
- permitir fallback SVG;
- respeitar reduced motion.

---

# 14. Reduced motion

Criar hook:

```ts
useReducedMotion()
```

Regras:

- Three.js off;
- Anime.js com duração mínima;
- Lottie estático ou primeira frame;
- scroll reveal desativado;
- sem parallax;
- sem bounce.

CSS:

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    animation-duration: 1ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 1ms !important;
  }
}
```

---

# 15. Acessibilidade

## Obrigatório

- foco visível;
- navegação por teclado;
- contraste;
- labels;
- `aria-expanded`;
- `aria-controls`;
- `aria-live`;
- trap de foco em modal/sheet;
- Escape fecha painel;
- botões com nome acessível;
- não depender apenas de cor;
- mensagens de erro associadas;
- suporte a zoom 200%.

---

# 16. Performance

# 16.1 Rotas lazy

Trocar imports eager por:

```ts
const ChartPage = lazy(() => import(...))
```

Aplicar em:

- admin;
- Replay;
- Plotly legado;
- landing pesada;
- Three.js;
- páginas secundárias.

---

# 16.2 Plotly

- remover se não necessário;
- ou lazy load;
- não incluir no bundle inicial.

---

# 16.3 TickerTape

- não montar globalmente;
- exibir apenas em rotas necessárias;
- compartilhar cache de ativos;
- pausar em aba invisível;
- evitar polling sobreposto.

---

# 16.4 Three.js e Lottie

- chunks separados;
- preload seletivo;
- fallback estático;
- observar viewport;
- descarregar quando sair.

---

# 16.5 Metas

```text
bundle inicial gzip < 500 kB
LCP < 2,5 s
CLS < 0,1
INP < 200 ms
FPS chart >= 50 em desktop
FPS chart >= 30 em mobile médio
```

As metas devem ser medidas, não presumidas.

---

# 17. Fases de implementação

# Fase 0 — Baseline

1. criar branch;
2. registrar screenshots;
3. medir bundle;
4. medir Lighthouse;
5. medir FPS;
6. mapear breakpoints;
7. registrar fluxos críticos.

**Gate:** baseline documentado.

---

# Fase 1 — Design system

1. criar tokens;
2. cores;
3. superfícies;
4. tipografia;
5. spacing;
6. radius;
7. buttons;
8. cards;
9. badges;
10. skeletons;
11. estados.

**Gate:** componentes básicos renderizados em Storybook ou página de catálogo.

---

# Fase 2 — Shell responsivo

1. MarketingShell;
2. TradingShell;
3. AdminShell;
4. MobileTopBar;
5. MobileBottomNav;
6. DesktopSidebar;
7. safe areas;
8. `100dvh`;
9. orientação;
10. fullscreen.

**Gate:** navegação funcional em desktop, tablet e mobile.

---

# Fase 3 — Gráfico mobile

1. toolbar mobile;
2. tooltip HUD;
3. sheets;
4. modo foco;
5. paisagem;
6. touch targets;
7. fullscreen;
8. bottom sheet IA.

**Gate:** chart utilizável em 360 px sem overflow.

---

# Fase 4 — Área do cliente

1. aplicar shell;
2. sincronizar ativo/timeframe;
3. painel IA responsivo;
4. cards;
5. estados;
6. ações mobile.

**Gate:** ativo selecionado corresponde ao gráfico.

---

# Fase 5 — Landing

1. nova estrutura;
2. hero;
3. demo;
4. prova visual;
5. CTA;
6. planos;
7. mobile;
8. SEO;
9. imagens responsivas.

**Gate:** landing clara sem Three.js.

---

# Fase 6 — Anime.js

1. MotionProvider;
2. route transitions;
3. hero reveal;
4. tabs;
5. bottom sheets;
6. cards;
7. badges;
8. numbers.

**Gate:** nenhuma animação interfere no chart.

---

# Fase 7 — Lottie

1. componente genérico;
2. IA;
3. vazio;
4. sucesso;
5. conexão;
6. onboarding;
7. reduced motion.

**Gate:** Lottie carregado sob demanda.

---

# Fase 8 — Three.js

1. HeroScene;
2. qualidade adaptativa;
3. fallback;
4. lazy;
5. observer;
6. cleanup;
7. pause;
8. mobile low/off.

**Gate:** chart e páginas internas não carregam Three.js.

---

# Fase 9 — Admin

1. reduzir efeitos;
2. padronizar cards;
3. tabelas responsivas;
4. filtros mobile;
5. drawers;
6. estados;
7. acessibilidade.

**Gate:** admin funcional em tablet.

---

# Fase 10 — Performance

1. route lazy;
2. split Plotly;
3. split Three;
4. split Lottie;
5. cache;
6. imagens;
7. fontes;
8. sourcemaps;
9. TickerTape;
10. profiling.

**Gate:** metas medidas.

---

# Fase 11 — Acessibilidade

1. teclado;
2. foco;
3. contraste;
4. screen reader;
5. reduced motion;
6. zoom;
7. modais;
8. sheets.

**Gate:** sem bloqueios críticos em auditoria.

---

# Fase 12 — Testes

## Visual

- desktop;
- tablet;
- mobile;
- paisagem;
- light/dark se existir;
- reduced motion.

## Funcional

- navegação;
- chart;
- Replay;
- IA;
- sheets;
- fullscreen;
- ativo/timeframe;
- login;
- admin.

## Performance

- Lighthouse;
- bundle;
- FPS;
- CPU throttling;
- rede lenta.

---

# 18. Arquivos atuais a alterar

## Prioridade P0

```text
src/App.tsx
src/index.css
src/pages/ChartPage.tsx
src/pages/CustomerChartPage.tsx
src/pages/ReplayPage.tsx
src/components/CandlestickChart.tsx
src/components/BackgroundEffects.tsx
```

## Prioridade P1

```text
src/pages/Landing.tsx
src/pages/CustomerArea.tsx
src/components/ReplayControls.tsx
src/components/ReplayDatePicker.tsx
src/hooks/useCustomerChart.ts
src/hooks/useReplayData.ts
src/lib/api.ts
vite.config.ts
package.json
```

## Prioridade P2

```text
src/pages/admin/*
src/components/admin/*
src/pages/Login.tsx
src/pages/Register.tsx
```

---

# 19. Restrições para a IA executora

A IA deve:

1. ler cada arquivo antes de alterar;
2. preservar lógica do chart;
3. não modificar o pipeline financeiro sem necessidade;
4. não misturar Three.js com o chart;
5. não adicionar animação global pesada;
6. não importar bibliotecas pesadas no entrypoint;
7. não usar `any`;
8. não desativar lint;
9. não remover acessibilidade;
10. não declarar sucesso sem teste;
11. gerar build após cada fase;
12. registrar bundle antes/depois;
13. apresentar diff por arquivo;
14. manter fallback;
15. respeitar reduced motion;
16. validar Android e iPhone;
17. manter SSR/SEO compatível quando aplicável;
18. não usar assets sem licença;
19. não prometer resultados financeiros;
20. preservar a identidade visual existente, refinando-a.

---

# 20. Critérios de aceite

## Responsividade

- [ ] 360 px sem scroll horizontal;
- [ ] chart ocupa viewport útil;
- [ ] bottom nav respeita safe area;
- [ ] painel IA vira bottom sheet;
- [ ] tooltip não fica sob o dedo;
- [ ] landscape funcional;
- [ ] touch targets >= 44 px.

## Visual

- [ ] landing e plataforma diferenciadas;
- [ ] glow reduzido;
- [ ] cores semânticas;
- [ ] cards com hierarquia;
- [ ] tipografia consistente;
- [ ] ícones uniformes.

## Anime.js

- [ ] animações sob demanda;
- [ ] sem candles animados;
- [ ] sem regressão de FPS;
- [ ] reduced motion.

## Lottie

- [ ] lazy load;
- [ ] fallback;
- [ ] uso apenas em estados;
- [ ] sem loops excessivos.

## Three.js

- [ ] apenas marketing;
- [ ] qualidade adaptativa;
- [ ] fallback estático;
- [ ] pausado fora da viewport;
- [ ] não carregado em chart/admin.

## Performance

- [ ] rotas lazy;
- [ ] Plotly fora do bundle inicial;
- [ ] Three separado;
- [ ] Lottie separado;
- [ ] TickerTape limitado;
- [ ] metas medidas.

## Acessibilidade

- [ ] teclado;
- [ ] foco;
- [ ] contraste;
- [ ] reduced motion;
- [ ] aria;
- [ ] zoom 200%.

---

# 21. Ordem final recomendada

```text
1. Baseline
2. Design system
3. Shell responsivo
4. Gráfico mobile
5. Área do cliente
6. Landing sem efeitos
7. Anime.js
8. Lottie
9. Three.js
10. Admin
11. Performance
12. Acessibilidade
13. Testes finais
```

Three.js deve ser a última camada visual, não a primeira.

---

# 22. Gates

```text
GATE_DESIGN_SYSTEM
GATE_RESPONSIVE_SHELL
GATE_MOBILE_CHART
GATE_CUSTOMER_AREA
GATE_LANDING
GATE_ANIME
GATE_LOTTIE
GATE_THREE
GATE_PERFORMANCE
GATE_ACCESSIBILITY
GATE_PRODUCTION
```

Cada gate deve possuir:

- build;
- lint;
- testes;
- screenshots;
- checklist;
- riscos restantes.

---

# 23. Entregáveis finais

1. frontend responsivo;
2. design system documentado;
3. app shell desktop/mobile;
4. gráfico mobile;
5. bottom sheets;
6. modo foco;
7. landing moderna;
8. Anime.js integrado;
9. Lottie integrado;
10. Three.js adaptativo;
11. bundle otimizado;
12. testes;
13. relatório final;
14. screenshots;
15. métricas antes/depois.

---

# 24. Resultado esperado

```text
LANDING
  → visual forte
  → Three.js adaptativo
  → mockup real
  → CTA claro
  → mobile completo

PLATAFORMA
  → limpa
  → rápida
  → responsiva
  → chart prioritário
  → baixa distração

MOBILE
  → app shell
  → bottom nav
  → toolbar compacta
  → IA em bottom sheet
  → landscape

MOVIMENTO
  → Anime.js em microinterações
  → Lottie em estados
  → Three.js apenas marketing
  → reduced motion

PERFORMANCE
  → lazy loading
  → chunks separados
  → bundle menor
  → FPS preservado
```

---

# 25. Conclusão

A modernização deve priorizar:

```text
RESPONSIVIDADE
→ USABILIDADE DO GRÁFICO
→ DESIGN SYSTEM
→ LANDING
→ MICROINTERAÇÕES
→ LOTTIE
→ THREE.JS
```

O objetivo não é adicionar o máximo de efeitos, mas criar uma plataforma:

- moderna;
- profissional;
- rápida;
- visualmente consistente;
- acessível;
- compatível com dispositivos móveis;
- preparada para evolução.
