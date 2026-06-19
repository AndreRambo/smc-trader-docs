# VISÃO DE PRODUTO, ENTREGAS E EXPECTATIVAS — SMC TRADER SYSTEM 7.0

**Data:** 16 de Junho de 2026
**Versão:** 1.0
**Baseado em:** Análise direta dos 3 projetos + RELATORIO_GERAL + PLANO_EXECUTIVO + ARQUITETURA_OFICIAL

---

## 1. Resumo Executivo

### O Que É o SMC Trader System 7.0

O SMC Trader System 7.0 é uma **plataforma de análise técnica de mercado financeiro** que coleta dados de ativos do mercado (B3 e Forex), processa análise técnica avançada baseada em Smart Money Concepts (SMC), e entrega ao usuário final oportunidades de mercado identificadas de forma determinística — via dashboard web e aplicativo Android com alertas push.

É uma plataforma de **apoio à decisão de trading**, não um robô de ordens automáticas. O sistema identifica, analisa e alerta; a decisão de operar é sempre do trader.

### A Proposta Central

Substituir a análise técnica manual e subjetiva por um motor determinístico que:

1. Coleta dados diretamente do MetaTrader 5 (MT5) para 11 ativos em múltiplos timeframes
2. Aplica metodologia SMC (Smart Money Concepts) com Elliott Wave e Wyckoff
3. Detecta automaticamente oportunidades que atendem a critérios técnicos específicos
4. Entrega o contexto completo da oportunidade: entrada, stop, alvos, confluências, ciclo de mercado
5. Notifica o usuário em tempo real via push notification no celular

### O Problema Que Resolve

**Para o trader:**
- Análise técnica manual é demorada, subjetiva e propensa a viés emocional
- Acompanhar 11 ativos em múltiplos timeframes simultaneamente é inviável
- Perder uma oportunidade porque não estava olhando o gráfico no momento certo
- Não ter um contexto técnico estruturado antes de entrar em uma operação

**Para o operador/dono do sistema:**
- Entregar análise técnica de qualidade de forma escalável para múltiplos usuários
- Monetizar conhecimento de análise SMC através de uma plataforma com planos comerciais
- Ter visibilidade total do funcionamento do sistema (saúde, sync, alertas, usuários)

### Quem É o Usuário Final

**Trader pessoa física** que:
- Opera minicontratos (WINFUT, WDOFUT), ações (PETR4, VALE3, ITUB3) ou Forex (XAUUSDm, BTCUSDm, ETHUSDm, EURUSDm, USDJPYm)
- Tem conhecimento básico de análise técnica
- Quer ser alertado quando uma oportunidade técnica está se formando
- Não quer ficar horas na frente de gráficos, mas quer acompanhar o mercado
- Toma a decisão final de entrar ou não na operação

### Resultado Esperado Para o Usuário

- Receber alertas push quando uma oportunidade SMC for detectada
- Ver a oportunidade com contexto completo: entrada, stop, alvos, tipo de setup, confluências
- Acompanhar o histórico de oportunidades detectadas
- Ter um canal de monitoramento passivo do mercado sem precisar estar no gráfico

### Resultado Esperado Para o Administrador/Dono

- Plataforma rodando 24/7 coletando e processando dados sem intervenção manual
- Painel administrativo com controle de usuários, planos, licenças e vendas
- Visibilidade do sync entre VPS e site
- Sistema gerando receita recorrente via assinaturas (Free, Starter, Pro, Enterprise)

---

## 2. O Que o Sistema Faz

O sistema funciona como um pipeline de ponta a ponta, desde a coleta de dados brutos de mercado até a notificação no celular do usuário:

### Etapa 1 — Coleta de Dados de Mercado

**Encontrado nos arquivos:** `run_b3.py`, `run_forex.py`, `infra/mt5_core.py`, pontes RPyC nas portas 11000/11001

O MetaTrader 5 (MT5), rodando em ambiente Wine no Linux da VPS, conecta-se às plataformas de dados de mercado B3 e Forex. Dois robôs Python (`run_b3.py` e `run_forex.py`) se comunicam com o MT5 via pontes RPyC e coletam candles (velas OHLC) em múltiplos timeframes:

- **Ativos B3:** WINFUT, WDOFUT, PETR4, VALE3, ITUB3
- **Ativos Forex:** XAUUSDm, BTCUSDm, ETHUSDm, EURUSDm, USDJPYm
- **Timeframes:** M1, M2, M5, M15, H1, H4, D1

Os candles coletados são gravados na tabela `market_candles` do banco MySQL local da VPS.

### Etapa 2 — Processamento Técnico Local (SMC Engine V2)

**Encontrado nos arquivos:** `technical_engine/smc_engine_v2/pipeline.py`, 10 módulos especializados

Quando novos candles são detectados, o sistema dispara o pipeline SMC Engine V2 — um motor de análise técnica determinístico com 10 passos. O motor identifica:

- **FVG (Fair Value Gaps):** Desequilíbrios de preço de 3 candles
- **OB (Order Blocks):** Zonas de interesse institucional
- **BOS/CHOCH (Structure):** Quebras de estrutura e mudanças de caráter
- **Liquidity:** Clusters de liquidez baseados em ATR
- **BPR (Balanced Price Ranges):** Sobreposição de FVGs bull+bear
- **Swings:** Máximas e mínimas estruturais
- **Sessions:** Londres, B3, NY, Ásia
- **PDH/PDL:** Máxima e mínima do dia anterior

Tudo é gravado em 10 tabelas shadow no MySQL. O motor é 100% determinístico: o mesmo input gera sempre o mesmo output (verificável via SHA-256). Nenhuma IA participa do cálculo.

### Etapa 3 — Análise de Contexto (Elliott Wave + Wyckoff)

**Encontrado nos arquivos:** `technical_engine/elliott/`, `technical_engine/wyckoff/`, `technical_engine/contextual_market_profile/`

Em paralelo ao SMC, outros motores analisam o contexto macro do mercado:

- **Elliott Wave:** 14 pivots, 9 wave legs, identificação de tendência e fase (impulso, correção)
- **Wyckoff:** 4 fases (acumulação, markup, distribuição, markdown), 8 eventos (SPRING, UT, SOS, SOW, etc.)
- **Contextual Market Profile:** Volatilidade, regime de mercado, sessão ativa, viés HTF (Higher Timeframe)

Esses motores fornecem o contexto que amplia ou bloqueia oportunidades detectadas pelo SMC.

### Etapa 4 — Geração do Estudo Canônico (Study Gateway)

**Encontrado nos arquivos:** `technical_engine/study_gateway/`, `models_v2.py`, `confluence_v2.py`

O Study Gateway integra os resultados do SMC Engine V2, Elliott e Wyckoff em uma análise unificada chamada `TechnicalTruthEnvelopeV2`:

- **Confluence V2:** Fusão de 6 fontes técnicas com pesos calibrados por timeframe (H4/M15/M5)
- **Risk Management V2:** Calcula entrada estrutural, stop, TP1/TP2/TP3 e R:R (risco/retorno)
- **Hit Rates V2:** Walk-forward simulator que calcula taxa histórica de acerto por setup
- **Professional Study Renderer:** Gera narrativa técnica em markdown (LLM atua apenas como redatora, não como motor de cálculo)

O estudo canônico é o documento técnico completo que justifica ou refuta uma oportunidade.

### Etapa 5 — Scanner de Oportunidades

**Encontrado nos arquivos:** `technical_engine/opportunity_scanner/scanner.py`, `evaluator.py`

O Opportunity Scanner avalia continuamente os estudos canônicos gerados e aplica 10+ gates (filtros) determinísticos para identificar oportunidades válidas:

- **Freshness:** A zona ainda está ativa e recente?
- **Proximity:** O preço está próximo o suficiente para entrar?
- **HTF Alignment:** O timeframe maior confirma a direção?
- **Pullback:** A estrutura de pullback está correta?
- **Health:** O scanner está saudável?
- **Dedup:** Já foi alertado nos últimos 15 minutos para este setup?

Quando todos os gates passam, é gerado um `OpportunitySignalV1` — o sinal de oportunidade.

### Etapa 6 — Sincronização com o Site (HMAC Sync)

**Encontrado nos arquivos:** `infra/sync_v2.py` (1051 linhas), `infra/database.py` (6881 linhas)

O Sistema Local envia os dados processados para o MaximusTrader via HTTPS POST autenticado com HMAC-SHA256:

- Candles + indicadores técnicos (EMA, RSI, ATR) → `/api/sync/candles`
- Zonas SMC (FVG, OB, BOS, Liquidity, BPR) → `/api/sync/zones`
- Elliott Waves → `/api/sync/elliott`
- Wyckoff phases + events → `/api/sync/wyckoff`
- Estudos canônicos → `/api/sync/study`
- Oportunidades do scanner → `/api/scanner/alerts`

Autenticação via 5 headers: `X-API-Key`, `X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature`.

### Etapa 7 — Recebimento e Persistência no Site

**Encontrado nos arquivos:** `MaximusTrader/backend/app/Http/Controllers/Api/SyncController.php`, 17 migrations, tabelas `sync_*`

O Laravel recebe os dados do Sistema Local, valida a assinatura HMAC e persiste nas tabelas do banco Hostinger:

- `sync_candles`, `sync_zones`, `sync_studies`, `sync_elliott_waves`, `sync_wyckoff_phases`, `sync_wyckoff_events`
- `scanner_alerts`, `opportunities`

### Etapa 8 — Exibição no Dashboard e Gráficos

**Encontrado nos arquivos:** `MaximusTrader/frontend/src/components/CandlestickChart.tsx`, `components/chart/smc/` (8 módulos)

O frontend React exibe os dados recebidos em gráficos interativos com overlay SMC:

- Gráfico de candles com lightweight-charts v5
- Sobreposição de zonas SMC (FVG, OB, BOS, Liquidity, BPR)
- Elliott waves e Wyckoff phases
- Watchlist multi-ativo
- Replay histórico

### Etapa 9 — Envio de Alerta Push (FCM)

**Encontrado nos arquivos:** `MaximusTrader/backend/app/Services/FirebasePushService.php`, `Jobs/SendOpportunityPushNotification.php`

Quando o scanner detecta uma oportunidade e o Laravel recebe o sinal, é disparado um Job que:

1. Verifica as preferências de cada usuário (ativos habilitados, quiet hours, rate limit)
2. Filtra usuários que atendem às preferências
3. Envia push notification via Firebase Cloud Messaging (FCM) HTTP v1

### Etapa 10 — Usuário Acompanha no App

**Encontrado nos arquivos:** `AppAndroid/MaximusTrader/composeApp/src/`

O usuário recebe a notificação push no celular Android, clica no alerta, e o app abre diretamente na tela de detalhe da oportunidade via deep link (`maximus://opportunity/{id}`), mostrando:

- Símbolo, direção (BUY/SELL), tipo de setup
- Entrada, stop, TP1/TP2/TP3
- Preço atual, distância em pontos/ATR, ETA
- Contexto técnico da oportunidade
- Disclaimer de risco

---

## 3. O Que o Sistema Entrega

### 3.1 Entregas Técnicas

**Encontrado nos arquivos** (confirmado nas estruturas dos 3 projetos):

| Entrega | Descrição | Onde Persiste |
|---------|-----------|---------------|
| Candles OHLC | 11 ativos × 7 timeframes, coletados do MT5 | MySQL VPS (`market_candles`) → MySQL Hostinger (`sync_candles`) |
| EMA/RSI/ATR | Indicadores técnicos calculados por candle | Junto com os candles no sync |
| Zonas FVG | Fair Value Gaps ativos por ativo/timeframe | Shadow tables VPS → `sync_zones` Hostinger |
| Order Blocks | Zonas de interesse institucional com quality score | Shadow tables VPS → `sync_zones` Hostinger |
| BOS/CHOCH | Quebras e mudanças de estrutura | Shadow tables VPS → `sync_zones` Hostinger |
| Liquidity | Clusters de liquidez (ATR-based) | Shadow tables VPS → `sync_zones` Hostinger |
| BPR | Balanced Price Ranges | Shadow tables VPS → `sync_zones` Hostinger |
| Elliott Waves | 9 wave legs, trend, fase | Shadow tables VPS → `sync_elliott_waves` Hostinger |
| Wyckoff Phases | 4 fases + 8 eventos (SPRING, UT, SOS, SOW...) | Shadow tables VPS → `sync_wyckoff_phases/events` Hostinger |
| Estudo Canônico | TechnicalTruthEnvelopeV2 com SHA-256 | Shadow tables VPS → `sync_studies` Hostinger |
| Opportunity Signal | OpportunitySignalV1 com entrada/stop/TP/R:R | MySQL VPS scanner → `scanner_alerts`/`opportunities` Hostinger |
| Heartbeat | Status de saúde do sistema (pendente implementação) | — |
| Logs estruturados | JSON de cada operação de sync (pendente) | — |
| Dashboards locais | FastAPI :8008 e Dash/Plotly :8050 | Local na VPS |

### 3.2 Entregas Para o Usuário Final

| Entrega | Descrição | Canal |
|---------|-----------|-------|
| Alerta de oportunidade | Push notification com símbolo, direção e tipo de setup | App Android (FCM) |
| Detalhe da oportunidade | Entrada, stop, TP1/TP2/TP3, preço atual, ETA, approach velocity | App Android (tela de detalhe) |
| Contexto técnico | Tipo de setup SMC, ciclo Elliott/Wyckoff, confluências | App Android (detalhe) |
| Lista de oportunidades ativas | Todas as oportunidades em aberto | App Android (lista) |
| Histórico de oportunidades | Oportunidades encerradas com resultado | App Android (histórico — pendente) |
| Dashboard resumo | Status do mercado, oportunidades ativas, alertas recentes | App Android (dashboard — pendente) |
| Gráficos com overlays SMC | Visualização completa das zonas no gráfico | MaximusTrader Web |
| Watchlist | Acompanhamento multi-ativo em tempo real | MaximusTrader Web |
| Replay histórico | Revisar operações passadas nos gráficos | MaximusTrader Web (plano Pro+) |
| Disclaimer de risco | Linguagem clara de que é análise, não garantia | App e Site |

### 3.3 Entregas Para o Administrador

| Entrega | Descrição | Canal |
|---------|-----------|-------|
| Controle de usuários | CRUD completo, roles, status | MaximusTrader Web (admin) |
| Gestão de planos | 4 planos (Free, Starter, Pro, Enterprise) com limites por plano | MaximusTrader Web (admin) |
| Gestão de licenças | Associar usuário/plano, ativar/desativar | MaximusTrader Web (admin) |
| Gestão de produtos | Produtos vendáveis associados a planos | MaximusTrader Web (admin) |
| Dashboard de vendas | Receita, assinaturas, histórico de compras | MaximusTrader Web (admin) |
| Painel de saúde do sync | Status do sync VPS→Site, último heartbeat (pendente) | MaximusTrader Web (admin) |
| Logs de alertas | Histórico de push notifications enviadas com status | MaximusTrader Web / `push_logs` |
| Webhooks de pagamento | Integração com 7 provedores: Hotmart, Kiwify, Stripe, MercadoPago, PayPal | Backend automático |
| Logs de auditoria | `audit_logs`, `access_logs`, `webhook_logs` | Backend MySQL |

---

## 4. O Que o Sistema NÃO Deve Fazer

### 4.1 Limites de Produto

| O Que Não Deve Fazer | Por Quê |
|----------------------|---------|
| Executar ordens automaticamente no broker | O sistema está em modo `shadow_only=True` — guardrail ativo; a ativação exige validação completa do pipeline e decisão explícita do dono do sistema |
| Prometer lucro ou garantir resultado | Viola regulação de mercado e é eticamente errado; o sistema entrega análise técnica, não previsão de preço |
| Apresentar "hit rate" como "probabilidade garantida" | O guardrail `probabilidade_proibida=True` está ativo; usa-se "taxa histórica de acerto", nunca "probabilidade" |
| Recalcular SMC no App Android | O app é canal de exibição; recalcular no cliente criaria divergências com o motor determinístico da VPS e consumiria bateria |
| Recalcular SMC no Frontend React | O frontend exibe dados recebidos da API; duplicar a lógica cria inconsistências e acoplamento indesejado |
| Depender do app para gerar sinais | Se o app falhar, os sinais devem continuar sendo gerados; a cadeia começa na VPS, não no app |
| Misturar motor de cálculo com painel visual | O Sistema Local calcula; o MaximusTrader exibe; o App notifica — esses papéis são intencionais e não devem ser misturados |
| Ativar push FCM para usuários reais sem validação | O pipeline ainda não foi validado end-to-end; ativar prematuramente pode gerar alertas incorretos e prejudicar usuários |
| Usar LLM como motor de cálculo | A LLM é redatora do estudo narrativo; o motor é 100% determinístico em Python; a IA nunca decide entrada, stop ou alvo |
| Alterar o pipeline SMC V2 sem processo de promoção | O SMC Engine V2 está `STABLE_FROZEN_V2`; mudanças exigem o processo formal: proposta → backtest → forward → promoção |
| Aplicar configuração automaticamente | O guardrail `apply_automatically=False` está ativo; toda promoção de config exige validação humana explícita |

---

## 5. Usuários e Perfis

### 5.1 Usuário Final / Trader

**O que ele vê:**
- No celular: alertas push com símbolo, direção e tipo de setup
- No app: lista de oportunidades ativas com entrada/stop/alvos, detalhe técnico, histórico
- No site (se acessar): gráfico com zonas SMC, watchlist, replay

**O que ele espera:**
- Ser alertado quando uma oportunidade real estiver se formando
- Receber contexto suficiente para tomar sua própria decisão
- Não perder oportunidades por não estar olhando o gráfico
- Interface simples e rápida; o app não deve ser complexo

**Que decisões ele toma:**
- Entrar ou não na operação
- Qual tamanho de posição usar (sizing)
- Se o contexto atual faz sentido com sua leitura de mercado

**Informações que precisa receber:**
- Símbolo e direção (BUY/SELL)
- Preço de entrada, stop e alvos (TP1/TP2/TP3)
- Distância atual do preço à zona (em pontos e em ATR)
- Tipo de setup e confluências principais
- ETA estimado (quanto tempo falta para o preço chegar à zona)

**Experiência ideal:**
1. Celular vibra com notificação do Maximus Trade Signals
2. Abre o app pelo deep link
3. Vê a oportunidade formatada de forma clara
4. Em 30 segundos entende o setup, avalia e decide

### 5.2 Administrador

**O que administra:**
- Usuários, planos, licenças e vendas via painel web
- Configuração de indicadores disponíveis por plano
- Webhooks de pagamento dos provedores

**O que monitora:**
- Saúde do sync (VPS → Site): está funcionando? Último heartbeat?
- Alertas gerados: quantos push foram enviados hoje? Algum erro?
- Usuários ativos, conversões de plano, receita

**O que configura:**
- Limites por plano (ativos, alertas, timeframes, replay, IA)
- Preços e periodicidade dos planos
- Provedores de pagamento ativos

**Indicadores de saúde que precisa acompanhar:**
- Último sync bem-sucedido por ativo
- Status dos serviços systemd (robôs, scanner, sync watcher)
- Taxa de sucesso de push notifications
- Erros de autenticação HMAC (pode indicar ataque ou desalinhamento de chaves)

### 5.3 Sistema Local / Operador Técnico

**Quem cuida da VPS:**
- Tecnicamente, o dono do sistema (ou quem ele delegar)
- Responsável por reiniciar serviços, monitorar logs, atualizar o código

**O que precisa monitorar:**
- Status dos serviços systemd (19 serviços: MT5 bridges, robôs B3/Forex, scanner, notifier)
- Conexão dos bridges RPyC com o MT5 (portas 11000/11001)
- Espaço em disco (logs, banco MySQL)
- Saúde do banco MySQL local

**Quais falhas precisam ser visíveis:**
- MT5 offline ou bridge RPyC desconectado (sem dados → sem alertas)
- Scanner parado (oportunidades não detectadas)
- Sync falhando (dados não chegam ao site)
- Banco MySQL com problemas (dados não persistidos)
- VPS sem espaço em disco

**Ações manuais que podem existir:**
- Reiniciar serviços systemd individualmente
- Rodar backfill manual de dados históricos
- Promover configuração de calibração após validação
- Disparar sync manual via CLI (`tools/sync_to_web.py`)

---

## 6. Papel de Cada Projeto

### 6.1 Sistema Local — O Cérebro Técnico

**Encontrado nos arquivos:** `SMC_Trader_System 7.0/` — 414 arquivos de teste, 2522+ testes passando, 19 serviços systemd

```
Responsabilidade principal:
  Motor de coleta, processamento e geração de sinais técnicos de mercado.
  É o único componente que calcula análise técnica SMC.
  Alimenta todo o resto do sistema.

Entrada:
  - Dados de mercado brutos (candles OHLC) via MT5/RPyC
  - 11 ativos × 7 timeframes em tempo real

Processamento:
  - SMC Engine V2 (FVG, OB, BOS/CHOCH, Liquidity, BPR, Swings)
  - Elliott Wave (14 pivots, 9 wave legs, trend, fase)
  - Wyckoff (4 fases, 8 eventos)
  - Confluence V2 (6 fontes com pesos + MTF fusion)
  - Risk Management V2 (entrada, stop, TP1-3, R:R)
  - Opportunity Scanner (10+ gates determinísticos)

Saída:
  - Candles + indicadores → /api/sync/candles
  - Zonas SMC → /api/sync/zones
  - Elliott → /api/sync/elliott
  - Wyckoff → /api/sync/wyckoff
  - Estudos canônicos → /api/sync/study
  - Oportunidades → /api/scanner/alerts

Consumidores:
  - MaximusTrader (via HMAC HTTPS POST)
  - Dashboards locais FastAPI :8008 (uso interno/debug)

Não é responsabilidade:
  - Exibir dados para o usuário final
  - Gerenciar usuários, planos ou licenças
  - Enviar push notifications (apenas sinaliza ao MaximusTrader)
  - Autenticar usuários
  - Armazenar preferências de usuário
  - Recalcular dados já enviados ao site
```

### 6.2 MaximusTrader — A Plataforma Central

**Encontrado nos arquivos:** `MaximusTrader/backend/` (Laravel 12, 43 endpoints, 24 models) + `MaximusTrader/frontend/` (React 19, 15 páginas)

```
Responsabilidade principal:
  Hub central do sistema. Recebe dados do Sistema Local,
  persiste, expõe via API, exibe no dashboard web,
  gerencia usuários/planos/licenças e envia push notifications.

Entrada:
  - Dados técnicos do Sistema Local (via HMAC POST)
  - Tokens FCM dos dispositivos Android (via API mobile)
  - Preferências dos usuários (via API mobile e web)
  - Webhooks de pagamento (Hotmart, Kiwify, Stripe, MercadoPago, PayPal)
  - Ações do administrador (via dashboard admin)

Processamento:
  - Validação HMAC (VerifySyncHmac.php)
  - Controle de planos e limites (EnforcePlanLimits.php)
  - Filtragem de push por preferências do usuário
  - Job de envio FCM (SendOpportunityPushNotification.php)
  - Autenticação (Sanctum + 2FA TOTP)
  - Controle de roles (Spatie Permission)

Saída:
  - API REST para App Android (/api/mobile/*)
  - SPA React para usuários web (/admin/*)
  - Push notifications FCM para dispositivos Android
  - Webhooks de confirmação para provedores de pagamento

Consumidores:
  - App Android (via REST + Sanctum)
  - Usuários web (via SPA React)
  - Administradores (via SPA React admin)
  - Provedores de pagamento (via webhooks)

Não é responsabilidade:
  - Calcular ou recalcular zonas SMC, Elliott ou Wyckoff
  - Coletar dados do MT5
  - Executar o scanner de oportunidades
  - Decidir quais oportunidades são válidas (isso é do Sistema Local)
  - Armazenar dados RAW de mercado além do necessário para exibição
```

### 6.3 AppAndroid — O Canal Mobile do Usuário

**Encontrado nos arquivos:** `AppAndroid/MaximusTrader/composeApp/src/` — Kotlin 2.1.0, KMP + Compose Multiplatform, Firebase FCM

```
Responsabilidade principal:
  Interface mobile do usuário final.
  Canal de alertas push e consulta de oportunidades.
  Deve ser simples, rápido e focado no essencial.

Entrada:
  - Push notifications via Firebase FCM
  - Dados de oportunidades via API REST (/api/mobile/*)
  - Preferências do usuário (armazenadas no MaximusTrader)

Processamento:
  - Autenticação (login, 2FA, token refresh)
  - Renderização de listas e detalhes
  - Gerenciamento de preferências locais (DataStore)
  - Roteamento de deep links

Saída:
  - Token FCM enviado ao MaximusTrader para registro
  - Preferências de notificação enviadas ao MaximusTrader
  - Ações do usuário (logout, alterar preferências)

Consumidores:
  - Usuário final (trader) via interface Android

Não é responsabilidade:
  - Calcular nenhuma análise técnica (SMC, Elliott, Wyckoff)
  - Tomar decisões sobre quais oportunidades alertar (isso é do Sistema Local + MaximusTrader)
  - Armazenar dados de mercado localmente (busca da API sob demanda)
  - Gerenciar usuários, planos ou licenças (isso é do MaximusTrader)
  - Acessar diretamente o banco de dados do Sistema Local
  - Funcionar como produto principal sem o backend (requer conexão com MaximusTrader)
```

---

## 7. Fluxo Ideal do Produto

### 7.1 Jornada do Trader

1. Trader instala o app e faz login com email/senha + 2FA
2. App registra o token FCM no MaximusTrader
3. Trader configura preferências: ativos que quer monitorar, horários de silêncio
4. Sistema Local está coletando e processando dados 24/7 em background
5. Scanner detecta oportunidade em WINFUT M5 (zona FVG com HTF alinhado)
6. Sistema Local envia sinal ao MaximusTrader via HMAC POST
7. MaximusTrader verifica preferências do trader: ele tem WINFUT habilitado? Está dentro do horário permitido?
8. Se sim: MaximusTrader dispara push FCM
9. Celular do trader vibra: "🎯 WINFUT — BUY — Zona FVG ativa"
10. Trader clica na notificação → app abre diretamente na tela de detalhe
11. Trader vê: entrada 124.800, stop 124.600, TP1 125.100, TP2 125.400
12. Vê: distância atual 15 pts, ETA estimado 8 minutos, confluências: Elliott Impulso W3, Wyckoff Markup
13. Decide entrar ou não (decisão exclusivamente do trader)
14. Consulta histórico depois para acompanhar o resultado da oportunidade
15. Repete o ciclo para os próximos alertas

### 7.2 Jornada do Administrador

1. Administrador acessa `maximustrade.com.br/admin/dashboard`
2. Verifica painel de saúde do sistema: sync OK, último heartbeat há 45 segundos
3. Confere últimos alertas gerados: 3 hoje, 2 enviados com sucesso, 1 falhou (token FCM expirado)
4. Verifica usuários: 12 ativos, 3 novos esta semana
5. Confere planos: 5 Free, 4 Starter, 2 Pro, 1 Enterprise
6. Verifica vendas: R$ 289,70 de receita recorrente este mês
7. Abre logs de webhook: 1 novo pagamento Hotmart confirmado → ativa licença Pro para usuário
8. Configura novo indicador disponível para plano Pro
9. Verifica espaço em disco e logs de erro (saúde do sistema)

### 7.3 Jornada Técnica dos Dados

```
1. MT5 (Wine/Linux VPS) gera novo candle M5 de WINFUT
          ↓
2. run_b3.py coleta via RPyC (porta 11000), INSERT em market_candles
          ↓
3. TRIGGER 4 detecta novo candle → dispara SMC Engine V2 pipeline
   ├─ FVG detector identifica novo gap de 3 candles
   ├─ Order Block detector marca possível zona institucional
   ├─ Structure detector confirma BOS (Break of Structure)
   └─ Persistence: salva em smc_v2_shadow_fvg, smc_v2_shadow_ob, etc.
          ↓
4. Elliott Wave Engine atualiza contexto (W3 impulso confirmado em H1)
          ↓
5. Wyckoff Engine confirma fase Markup
          ↓
6. Study Gateway gera TechnicalTruthEnvelopeV2
   ├─ Confluence V2: score 0.78 (acima do threshold)
   ├─ Risk Management: entrada 124.800, stop 124.600, TP1 125.100, R:R 1.5
   └─ Study Renderer: narrativa técnica gerada (LLM como redatora)
          ↓
7. Opportunity Scanner avalia (scan_once())
   ├─ Gate freshness: PASSOU (zona < 6 velas)
   ├─ Gate proximity: PASSOU (preço a 12 pts da zona)
   ├─ Gate HTF alignment: PASSOU (H1 + H4 alinhados BUY)
   ├─ Gate dedup: PASSOU (último alerta desta zona > 15 min)
   └─ OpportunitySignalV1 gerado
          ↓
8. HMAC POST → maximustrade.com.br/api/scanner/alerts
   Headers: X-API-Key, X-Client-Id, X-Timestamp, X-Nonce, X-Signature
          ↓
9. Laravel ScannerAlertController recebe → valida HMAC → persiste scanner_alert
          ↓
10. Job SendOpportunityPushNotification disparado na fila
    ├─ Busca usuários com WINFUT habilitado
    ├─ Filtra por quiet hours e rate limit
    └─ Envia FCM via FirebasePushService (HTTP v1 + OAuth JWT)
          ↓
11. Firebase entrega push para dispositivos Android
          ↓
12. MaximusFirebaseMessagingService no app cria notificação local
    com PendingIntent para maximus://opportunity/{id}
          ↓
13. Trader clica → MainActivity captura deep link → NavHost redireciona
    para OpportunityDetailScreen com o id da oportunidade
          ↓
14. OpportunityDetailViewModel busca GET /api/mobile/opportunities/{id}
          ↓
15. Tela exibe: entrada, stop, alvos, preço atual, distância, ETA, contexto
```

---

## 8. Expectativas Por Projeto

| Projeto | Expectativa Correta | O que precisa estar pronto | O que pode ficar para depois | O que não deve fazer |
|---------|---------------------|---------------------------|------------------------------|----------------------|
| **Sistema Local** | Motor 24/7, determinístico, shadow-only. Processa dados, gera sinais, sincroniza com o site. Não é produto visual. | Sync event-driven automático; heartbeat e retry; scanner rodando continuamente; backfill completo de dados históricos | Pipeline de promoção para sinais ao vivo (depende de validação); iOS (não aplicável ao Sistema Local) | Expor dados diretamente para usuário final sem passar pelo MaximusTrader; alterar guardrails sem validação |
| **MaximusTrader Backend** | Hub central que recebe, persiste, expõe e notifica. API estável é contrato com o app. | 43 endpoints ativos e testados (já existem); validação HMAC robusta (já existe); jobs de push funcionando; controle de planos e limites | Painel de saúde do sync (pendente); health endpoint do sistema (pendente); API docs OpenAPI/Swagger | Recalcular SMC ou outras análises técnicas; tomar decisões de sinal; processar dados de mercado brutos |
| **MaximusTrader Frontend** | Dashboard web para análise visual e administração. Não é o produto principal do usuário — o app é. | Gráfico unificado com lightweight-charts; overlays SMC funcionando; painel admin funcional; Error Boundary | Testes E2E; iOS; internacionalização; features de replay avançado | Recalcular zonas SMC no navegador; duplicar lógica de scoring; processar dados localmente sem API |
| **AppAndroid** | Canal mobile de alertas e consulta de oportunidades. Interface simples, focada, sem análise própria. | Login + 2FA; push FCM; lista e detalhe de oportunidades; preferências básicas (já existem) | Dashboard completo (pendente); histórico (pendente); conta/perfil (pendente); preferências avançadas; iOS; testes | Calcular SMC; acessar banco de dados direto; funcionar offline para dados de mercado; ser app de trading completo |
| **Infraestrutura** | VPS rodando 24/7 com serviços systemd, MySQL saudável, sync automatizado | Backups automáticos; logrotate; monitoramento de serviços; restart automático em falha | Deploy CI/CD automatizado; hardening de secrets (vault) | Dependência única de MT5 Wine (risco que deve ser gerenciado, não eliminado agora) |
| **Testes** | Manter 2522+ testes passando no Sistema Local; adicionar cobertura ao frontend e app | Testes Python: manter baseline; frontend: mínimo 10 testes iniciais; app: mínimo 10 testes unitários | Testes E2E automatizados; Playwright/Cypress para web; Instrumented tests para Android | Testar pipeline SMC V2 fora do processo formal de promoção |
| **Deploy** | Deploy faseado, com backup, rollback definido, fora do horário de mercado B3 | Checklist pré-deploy documentado; procedimento de rollback por componente | CI/CD automatizado (GitHub Actions); blue-green deploy; distribuição automática do APK | Deploy durante horário de mercado B3 (10h-17h BRT); ativar can_promote_trade sem validação explícita |

---

## 9. Critérios de Produto Pronto

### 9.1 MVP Interno (Uso Controlado Pelo Dono)

- [ ] Sistema Local rodando 24/7 na VPS com auto-restart
- [ ] Coleta de dados de pelo menos 3 ativos principais (WINFUT, WDOFUT, XAUUSDm)
- [ ] SMC Engine V2 processando e persistindo em shadow tables
- [ ] Scanner detectando oportunidades (mesmo que com threshold conservador)
- [ ] Sync automático VPS → MaximusTrader funcionando
- [ ] Gráfico web exibindo candles e zonas SMC do último dia
- [ ] Push notification chegando no app de pelo menos 1 dispositivo
- [ ] Login no app funcionando com 2FA
- [ ] Lista de oportunidades ativas visível no app
- [ ] Detalhe da oportunidade com entrada/stop/alvos visível

### 9.2 Beta Fechado (5-20 Usuários Reais Convidados)

- [ ] Todos os itens do MVP Interno
- [ ] Sync funcionando continuamente por 7 dias sem intervenção manual
- [ ] Heartbeat visível no painel admin (saber se sistema está online)
- [ ] Histórico de oportunidades no app implementado
- [ ] Preferências de ativos funcionando (filtrar alertas por ativo)
- [ ] Quiet hours funcionando (não acordar usuário de madrugada)
- [ ] Linguagem de risco nos alertas validada (não sugerir garantia de lucro)
- [ ] Suporte a todos os 11 ativos configurados
- [ ] Backup automático configurado
- [ ] Runbook de operação documentado
- [ ] Procedimento de rollback testado
- [ ] Painel admin com controle de usuários e planos funcional

### 9.3 Produção (Comercialmente Disponível)

- [ ] Todos os itens do Beta Fechado
- [ ] Testes integrados E2E passando (fluxo MT5 → App)
- [ ] Telas de Dashboard, Histórico e Conta no app implementadas
- [ ] Preferências avançadas no app (quiet hours, por radar state, max pushes/hora)
- [ ] Todos os 4 planos comerciais configurados (Free, Starter, Pro, Enterprise)
- [ ] Pelo menos 2 provedores de pagamento testados em produção
- [ ] Cloudflare WAF ativo e configurado
- [ ] Monitoramento externo ativo (UptimeRobot ou similar)
- [ ] Logs estruturados e rotativos configurados
- [ ] Cobertura de testes mínima: Python (manter 2522+), Frontend (>10 testes), App (>10 testes)
- [ ] OpenAPI/Swagger documentando os 43 endpoints
- [ ] Política de privacidade e disclaimer de risco publicados no site
- [ ] App publicado na Google Play Store ou distribuído via APK assinado

---

## 10. Riscos de Expectativa Errada

| Risco de Expectativa | Impacto | Como Alinhar Corretamente |
|----------------------|---------|---------------------------|
| Achar que o app precisa calcular sinais próprios | App consumindo bateria, cache desatualizado, divergência com o motor real; complexidade desnecessária | App é canal de exibição. 100% dos cálculos ficam no Sistema Local da VPS. O app só busca dados da API e exibe |
| Achar que o frontend deve recalcular SMC no navegador | Inconsistência com dados da VPS, bug difícil de rastrear, duplicação de lógica | Frontend exibe zonas recebidas da API. A engine SMC V2 só roda no Sistema Local |
| Achar que o sistema está pronto para produção porque o motor local tem 2522+ testes | Os testes cobrem o cálculo técnico, não o pipeline completo MT5→VPS→Site→App | MVP Interno ainda requer sync automático, heartbeat, push FCM validado e app com telas essenciais completas |
| Achar que alerta é recomendação garantida de trade | Dano de reputação, problema legal, perda de confiança do usuário | O sistema identifica oportunidades técnicas, não garante resultado. Disclaimer de risco é obrigatório em cada alerta |
| Achar que sync manual é suficiente para operação 24/7 | Dados atrasados no site, usuários sem alertas, gráficos desatualizados | Sync precisa ser event-driven e automático. Manual é apenas para debug e backfill |
| Achar que gráfico bonito significa dado confiável | Exibir dados incorretos com visual bonito é pior do que dado feio correto | Confiabilidade do pipeline de sync deve ser validada antes de otimizar o visual |
| Achar que push funcionando significa pipeline validado | Push pode estar chegando com dados incorretos ou desatualizados | Pipeline validado significa: dado correto → calculado certo → sincronizado sem perda → alerta com informação precisa |
| Achar que 3 bibliotecas de gráfico são equivalentes | Manutenção cara, bugs visuais difíceis de rastrear, inconsistência de UX | lightweight-charts é a escolha definitiva; ApexCharts deve ser removido; Plotly depreciado |
| Achar que Shadow Only significa "não funciona" | Shadow Only é um guardrail de segurança, não uma limitação de funcionamento | O sistema funciona e gera sinais. Shadow Only significa que não executa ordens reais — é intencional e correto |
| Achar que LLM melhora a precisão dos sinais | LLM como motor de análise é não-determinístico e inauditável | LLM é apenas redatora do texto narrativo do estudo. O cálculo é sempre Python determinístico com SHA-256 |
| Achar que iOS pode vir antes de Android estar completo | iOS exige porta completa da arquitetura; começar antes de validar Android dobra o trabalho | Completar Android MVP antes de iniciar iOS. AppAndroid ainda tem 3 telas vazias e 0 testes |
| Achar que o administrador consegue monitorar sem painel de saúde | Falha silenciosa do sync; alertas parando sem que ninguém saiba | Heartbeat e painel de saúde são necessidades funcionais antes do beta, não features opcionais |

---

## 11. Decisões Que Precisam Ser Tomadas

| Decisão | Opções | Recomendação Técnica | Impacto |
|---------|--------|----------------------|---------|
| **O sistema vai vender sinais, scanner ou plataforma educacional?** | (A) Sinais prontos para operar, (B) Scanner de oportunidades com contexto, (C) Plataforma educacional de análise SMC | (B) Scanner com contexto — alinhado ao que já está implementado; evita conflito com regulação de vendas de sinais | Define linguagem do produto, clausulas legais, posicionamento de mercado e treinamento do usuário |
| **O app será apenas alertas ou dashboard completo?** | (A) App minimalista: só alertas e detalhe, (B) App completo com dashboard, histórico, conta, gráficos | (A) para MVP; (B) como evolução — o que está implementado hoje já é próximo de (A) | Define escopo das Fases 4 e 5; afeta tempo até beta |
| **O site ou o app é o produto principal?** | (A) Site é o principal, app é complemento, (B) App é o principal, site é admin, (C) Equivalentes | (A) Site para análise técnica profunda; app para alertas em movimento — papéis complementares diferentes | Define onde investir esforço de UX; o app sendo canal de alertas é mais direto e menos concorrente com TradingView |
| **Qual biblioteca de gráfico é definitiva?** | (A) lightweight-charts (já escolhida), (B) Plotly, (C) TradingView Advanced Charts | (A) lightweight-charts — SMC overlay engine de 8 módulos já foi construído para ela | Impacta Fase 4 (consolidação); 0 dúvida técnica, decisão já praticamente tomada |
| **Qual banco é fonte da verdade?** | (A) MySQL VPS é a fonte, site é espelho, (B) MySQL Hostinger é a fonte, VPS sincroniza de lá | (A) MySQL VPS é a fonte — a VPS calcula e o site recebe; inverter criaria acoplamento e latência | Define arquitetura de sync; atualmente implementado como (A) |
| **Quando sinais ao vivo poderão ser liberados?** | (A) Após MVP Interno validado, (B) Após Beta Fechado validado, (C) Apenas após 3 meses de Beta | (B) Após Beta Fechado com monitoramento intenso — não antes; guardrail `can_promote_trade=False` deve ser mantido até então | Define expectativa de timeline; sinais ao vivo requerem pipeline E2E validado |
| **Quais ativos entram no MVP?** | (A) Apenas WINFUT, (B) WINFUT + XAUUSDm + BTCUSDm, (C) Todos os 11 ativos | (B) 3 ativos para MVP — reduz risco, foca validação; expandir para 11 no beta | Define escopo técnico do MVP; mais ativos = mais dados a validar no pipeline |
| **Quais timeframes entram no MVP?** | (A) Apenas M5, (B) M5 + M15 + H1, (C) Todos os timeframes | (B) M5 + M15 + H1 para MVP — cobre os timeframes operacionais mais relevantes | Afeta volume de dados sincronizados, performance do scanner e quantidade de alertas |
| **O app precisa funcionar offline?** | (A) Sim, cache local de oportunidades, (B) Não, sempre online | (B) Não para MVP; cache de oportunidades pode ser adicionado depois | Define complexidade do app; KMP + DataStore permite cache, mas não é bloqueante para MVP |
| **Qual linguagem usar nos alertas para evitar promessa de lucro?** | (A) "Oportunidade de COMPRA identificada", (B) "Zona de interesse SMC detectada em WINFUT", (C) "Sinal de entrada WINFUT BUY" | (B) Mais conservadora e tecnicamente precisa; evita conotação de recomendação | Define compliance legal; textos precisam de revisão jurídica antes do lançamento público |
| **Quais métricas definem que o sistema está saudável?** | Ver abaixo | Ver abaixo | Define critérios para o painel de saúde |

**Métricas de saúde recomendadas (Inferência técnica):**

| Métrica | Verde (OK) | Amarelo (Warning) | Vermelho (Critical) |
|---------|-----------|-------------------|---------------------|
| Último sync bem-sucedido | < 5 min | 5-15 min | > 15 min |
| Último candle de WINFUT | < 2 min (M1) | 2-10 min | > 10 min |
| Status dos robôs B3/Forex | Ambos running | 1 parado | Ambos parados |
| Status do scanner | Running | Parado < 5 min | Parado > 5 min |
| Push FCM últimas 24h | Sucesso > 95% | Sucesso 80-95% | Sucesso < 80% |
| Erros HMAC nas últimas 24h | 0 | 1-5 | > 5 |

---

## 12. Mapa de Entregas Por Fase

### Fase A — Alinhamento do Produto (Esta Fase — 1-2 dias)

- [x] Documento de visão de produto (este arquivo)
- [ ] Respostas do dono do produto às perguntas da Seção 13
- [ ] Decisões alinhadas da tabela da Seção 11
- [ ] Definição da linguagem comercial dos alertas
- [ ] Definição de quais ativos e timeframes entram no MVP

### Fase B — Confiabilidade Técnica (Fases 0-3 do Plano Executivo)

- [ ] Baseline técnico documentado e backups realizados (já em andamento)
- [ ] Sync event-driven automático (sync watcher)
- [ ] Retry com backoff exponencial em falhas de rede
- [ ] Heartbeat de sync a cada 60s
- [ ] Painel de saúde no admin
- [ ] Backups automáticos MySQL (VPS e Hostinger)
- [ ] Logs estruturados JSON

### Fase C — Experiência Web (Fase 4 do Plano Executivo)

- [ ] Unificação em lightweight-charts (remoção do ApexCharts)
- [ ] Deprecação do Plotly como fallback
- [ ] Gráfico com todos os overlays SMC funcionando com dados reais
- [ ] Error Boundary global
- [ ] Testes frontend (mínimo 10)
- [ ] Painel de saúde do sistema visível no admin

### Fase D — Experiência Mobile (Fase 5 do Plano Executivo)

- [ ] Dashboard no app (cards resumo, scanner health)
- [ ] Histórico de oportunidades no app
- [ ] Tela de Conta/Perfil no app
- [ ] Preferências avançadas (quiet hours, por radar state, max pushes)
- [ ] DTOs/Mappers/UseCases tipados
- [ ] Testes unitários no app (mínimo 10)

### Fase E — Validação Ponta a Ponta (Fase 6 do Plano Executivo)

- [ ] Teste E2E: MT5 → Sistema Local → MaximusTrader → App
- [ ] Cenários de falha testados (rede offline, HMAC inválido, payload duplicado)
- [ ] Performance com 1500+ zonas validada
- [ ] FCM testado em modo dry-run e em dispositivo real
- [ ] Resultados documentados em RESULTADOS_TESTES_INTEGRADOS.md

### Fase F — Beta Controlado (5-20 usuários reais)

- [ ] Todos os itens do MVP Interno completos
- [ ] Sistema rodando 7 dias sem intervenção
- [ ] Monitoramento intenso: logs, alertas, erros
- [ ] Coleta de feedback dos usuários beta
- [ ] Ajuste da linguagem e UX com base no feedback
- [ ] Validação dos critérios de saúde definidos

### Fase G — Produção Assistida (Deploy Controlado — Fase 7 do Plano Executivo)

- [ ] Todos os itens do Beta Fechado
- [ ] Checklist pré-deploy completo
- [ ] Deploy faseado: Backend → Frontend → VPS
- [ ] 24h sem incidentes
- [ ] Runbook de operação publicado
- [ ] App na Google Play Store ou APK assinado disponível
- [ ] Todos os planos comerciais ativos com pelo menos 1 provedor de pagamento

---

## 13. Perguntas Que o Dono do Produto Deve Responder

Estas perguntas devem ser respondidas **antes das Fases B e C** para evitar retrabalho.

### Produto

1. O sistema será vendido como **scanner de oportunidades SMC** (recomendado), como **sinais de trading** ou como **plataforma educacional**?
2. O alerta deve dizer "**oportunidade técnica identificada**" ou "**sinal de entrada**"? (Impacto legal e de posicionamento)
3. Qual é a proposta de valor principal para o usuário free? (Para converter para pago)
4. O **site ou o app** deve ser o produto principal para o usuário final? (Define onde colocar mais esforço de UX)
5. O nome "Maximus Trade Signals" está alinhado com o posicionamento final do produto?

### Trading

6. Quais **3 ativos** devem entrar no MVP do beta? (Recomendação: WINFUT, XAUUSDm, BTCUSDm)
7. Quais **timeframes** devem gerar alertas para usuário final? (Recomendação: M5 + M15 para entradas, H1 como contexto)
8. Qual é o **threshold mínimo de confluência** para gerar um alerta público? (Scanner já tem gates — definir quão conservador ser)
9. O que bloqueia um alerta? (Volatilidade extrema? Sessão fora do horário? Ativo em circuit breaker?)
10. O sistema deve gerar alertas fora do horário de mercado B3? (Forex opera 24/5)

### Operação

11. **Quem monitora a VPS** no dia a dia? (O próprio dono? Um DevOps terceirizado?)
12. **Quem recebe alerta de falha** do sistema? (Email? WhatsApp? Telegram?)
13. Quanto tempo de **atraso no sync** é aceitável antes de alertar o operador? (Recomendação: 5 min warning, 15 min critical)
14. O que fazer se o **MT5 cair** por mais de 1 hora? (Alertar usuários? Suspender alertas? Colocar modo manutenção?)
15. Qual é o **SLA de disponibilidade** esperado? (99%? 95%?)

### Comercial

16. Os planos atuais (Free R$0 / Starter R$29,90 / Pro R$59,90 / Enterprise R$99,90) são os preços finais?
17. Quais recursos entram no **plano gratuito** sem exigir cartão?
18. Há intenção de oferecer **período de trial** para os planos pagos?
19. Qual é o modelo de monetização preferido: **mensal** (fluxo mais constante) ou **anual** (melhor LTV)?
20. Existem planos de **parceria ou afiliados** que devem estar no sistema?

### Técnico

21. A decisão de usar **lightweight-charts** como única biblioteca de gráfico está confirmada?
22. O **MySQL VPS** continuará sendo a fonte da verdade (dados calculados na VPS e enviados ao site)?
23. Quais **endpoints são contratos estáveis** que o app Android não pode ver quebrar? (Auth + Mobile são críticos)
24. O app precisa funcionar **offline** para mostrar pelo menos as últimas oportunidades? (Impacto de complexidade)
25. Existe plano de suporte para **iOS** e em qual horizonte de tempo?

---

## 14. Conclusão

### O Que o Sistema É

O SMC Trader System 7.0 é uma **plataforma técnica de scanner de oportunidades de mercado** baseada em Smart Money Concepts (SMC), Elliott Wave e Wyckoff. É composta por um motor de cálculo Python rodando 24/7 em VPS Linux, uma plataforma web Laravel+React para visualização e administração, e um app Android para alertas mobile.

**Não é:** um robô de ordens, uma IA que decide trades, uma promessa de lucro, ou uma plataforma de copy trading.

### O Que Ele Entrega

- **Para o trader:** Alertas push quando uma oportunidade técnica SMC é detectada, com contexto completo (entrada, stop, alvos, confluências) e interface mobile simples para acompanhar
- **Para o administrador:** Controle total de usuários, planos, licenças, receita e saúde do sistema
- **Para o operador técnico:** Sistema determinístico, auditável, com guardrails de segurança, backfill histórico e dashboards locais de debug

### Responsabilidade de Cada Projeto

| Projeto | Uma Frase |
|---------|-----------|
| **Sistema Local** | Calcula tudo, não exibe nada. |
| **MaximusTrader** | Recebe tudo, organiza, exibe e notifica. |
| **AppAndroid** | Recebe alertas, exibe contexto, não calcula nada. |

### Próxima Etapa Recomendada

**Antes de continuar qualquer desenvolvimento técnico:**

1. O dono do produto responde as 25 perguntas da Seção 13
2. As 11 decisões da Seção 11 são alinhadas (especialmente: linguagem do produto, ativos do MVP, quando sinais ao vivo)
3. Com decisões alinhadas, executa-se a **Fase B — Confiabilidade Técnica** (sync automático, heartbeat, retry, backups)

### Faz Sentido Continuar Para o Baseline Técnico?

**Sim, com uma condição:** o baseline técnico (Fase 0 do Plano Executivo) pode e deve continuar em paralelo — ele não requer decisões de produto, apenas documenta o estado atual. O que **não deve** continuar sem alinhamento é:

- Definir a linguagem dos alertas (decisão de produto/jurídico)
- Implementar o painel de saúde sem saber quais métricas importam (decisão operacional)
- Expandir o app sem saber se o foco é alertas simples ou dashboard completo (decisão de produto)
- Preparar a estrutura de planos sem confirmar os preços e features de cada plano (decisão comercial)

O sistema técnico está avançado. O que pode travar o produto é a falta de clareza sobre **o que ele é e para quem serve** — e isso precisa ser respondido agora, antes da Fase B.

---

*Documento gerado em 16 de Junho de 2026 com base na análise direta dos arquivos dos 3 projetos, do RELATORIO_GERAL_STATUS e do PLANO_EXECUTIVO_PROXIMAS_FASES.*

*Inferências técnicas estão marcadas como "Inferência técnica". Dados confirmados nos arquivos estão marcados como "Encontrado nos arquivos".*
