# RELATÓRIO GERAL — STATUS DO SMC TRADER SYSTEM 7.0

**Data:** 15 de Junho de 2026
**Versão do Relatório:** 1.0
**Responsável:** Equipe de Engenharia — Análise por Exploração de Arquivos

---

## 1. Visão Geral do Ecossistema

O SMC Trader System 7.0 é composto por 3 partes interdependentes:

| Parte | Caminho | Papel |
|-------|---------|-------|
| **AppAndroid** | `AppAndroid/MaximusTrader/` | Aplicativo Kotlin Multiplatform para Android que exibe sinais de trading (oportunidades) e recebe notificações push via Firebase FCM |
| **MaximusTrader** | `MaximusTrader/` | Plataforma web (Laravel + React) hospedada em `maximustrade.com.br` — dashboard, gráficos, API REST, autenticação, planos comerciais e portal de sinais |
| **Sistema Local** | `SMC_Trader_System 7.0/` | Motor de cálculo em Python executando 24/7 em VPS Linux — coleta de dados MT5, engine SMC, estudo canônico, scanner de oportunidades, sincronização com o site |

### Fluxo Geral de Dados

```text
┌─────────────────────────────────────────────────────────────────┐
│  MT5 Terminals (Wine) — B3 + Forex                              │
│  Coleta: M1, M2, M5, M15, H1, H4, D1 × 11 ativos               │
└────────────────────────┬────────────────────────────────────────┘
                         │ RPyC Bridges (ports 11000/11001)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  SISTEMA LOCAL DE CÁLCULO (VPS Linux, Python 3.11+)             │
│                                                                  │
│  run_b3.py / run_forex.py                                       │
│    → market_candles (MySQL: smc_trader_2_db)                    │
│    → TRIGGER 4: SMC Engine V2 pipeline                          │
│      ├─ FVG, OB, BOS/CHOCH, Liquidity, BPR, Swings              │
│      ├─ Elliott Wave + Wyckoff + Volume Profile                  │
│      └─ Persistência: 10 shadow tables (MySQL)                  │
│    → Study Gateway (Confluence V2, Risk Management)              │
│    → Opportunity Scanner (10+ deterministic gates)              │
│    → HMAC Sync → Site                                           │
│                                                                  │
│  Dashboards: FastAPI :8008 + Dash/Plotly :8050                   │
└────────────────────────┬────────────────────────────────────────┘
                         │ HTTPS POST (HMAC-SHA256)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  MAXIMUSTRADER (Hostinger — maximustrade.com.br)                 │
│                                                                  │
│  Laravel 12 API:                                                 │
│    ├─ /api/sync/* ← recebe candles, zonas, Elliott, Wyckoff     │
│    ├─ /api/scanner/* ← recebe oportunidades + FCM push          │
│    ├─ /api/auth/* ← autenticação, 2FA                            │
│    ├─ /api/mobile/* ← API para o app Android                    │
│    ├─ /api/market/* ← dados de mercado (leitura)                 │
│    └─ /api/admin/* ← painel administrativo                       │
│                                                                  │
│  React SPA (Vite + TypeScript + Tailwind):                       │
│    ├─ Dashboard, Gráficos (lightweight-charts + Plotly)         │
│    ├─ Watchlist, Replay, Indicadores, Alertas                    │
│    └─ Admin: Planos, Usuários, Licenças, Vendas                  │
└────────────────────────┬────────────────────────────────────────┘
                         │ REST API (Sanctum Token)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  APP ANDROID (Kotlin Multiplatform + Compose Multiplatform)      │
│                                                                  │
│  Features: Login, 2FA, Oportunidades, Preferências              │
│  Push: Firebase FCM                                              │
│  Deep Links: maximus://opportunity/{id}                          │
└─────────────────────────────────────────────────────────────────┘
```

### Comunicação entre as Partes

1. **Sistema Local → MaximusTrader:** Sincronização via HTTPS POST com autenticação HMAC-SHA256 (`X-API-Key` + `Client-Id` + `Timestamp` + `Nonce` + `Signature`). Endpoints: `/api/sync/*` e `/api/scanner/*`.

2. **MaximusTrader → AppAndroid:** API REST com autenticação Laravel Sanctum (tokens `mt_*`). Endpoints: `/api/mobile/*`. Push notifications via Firebase FCM (HTTP v1 + OAuth JWT).

3. **MaximusTrader → Usuário Web:** SPA React servida pelo mesmo domínio, com autenticação Sanctum SPA-based.

---

## 2. Parte 1 — AppAndroid

### 2.1 Estrutura Encontrada

```
AppAndroid/MaximusTrader/
├── build.gradle.kts                   # Root build (AGP 8.13, Kotlin 2.1.0)
├── settings.gradle.kts                # Inclui module :composeApp
├── gradle.properties
├── gradle/libs.versions.toml          # Catálogo de versões
├── build-local.bat                    # Script build Windows
├── screen1.png                        # Screenshot do app
├── Plano/
│   └── plano_app_kotlin_multiplatform_maximus_trade_signals.md
├── composeApp/
│   ├── build.gradle.kts               # Dependências KMP
│   ├── google-services.json           # Firebase: maximus-trade-signals
│   └── src/
│       ├── androidMain/kotlin/.../    # 6 arquivos Android-specific
│       │   ├── MainActivity.kt
│       │   ├── MainApplication.kt
│       │   ├── firebase/
│       │   │   ├── AndroidNotificationService.kt
│       │   │   └── MaximusFirebaseMessagingService.kt
│       │   └── storage/
│       │       ├── AndroidDataStoreProvider.kt
│       │       └── AndroidSecureTokenStorage.kt
│       ├── commonMain/kotlin/.../     # 31 arquivos shared
│       │   ├── App.kt                 # NavHost raiz
│       │   ├── core/
│       │   │   ├── api/ApiClient.kt        # Ktor HTTP client
│       │   │   ├── api/AppConfig.kt        # URL base
│       │   │   ├── design/MaximusColors.kt
│       │   │   ├── design/MaximusTheme.kt  # Material3 dark
│       │   │   ├── di/Modules.kt           # Koin DI
│       │   │   ├── notifications/NotificationService.kt
│       │   │   └── storage/DataStoreProvider.kt, TokenStorage.kt
│       │   ├── data/repository/       # 4 implementações
│       │   │   ├── AuthRepositoryImpl.kt
│       │   │   ├── DeviceRepositoryImpl.kt
│       │   │   ├── OpportunityRepositoryImpl.kt
│       │   │   └── PreferencesRepositoryImpl.kt
│       │   ├── domain/
│       │   │   ├── model/             # AuthModels, OpportunityModels, PreferenceModels
│       │   │   └── repository/        # 4 interfaces
│       │   └── features/
│       │       ├── auth/              # Login, ForgotPassword (Screens + ViewModels)
│       │       ├── opportunities/     # List, Detail, Card (Screens + ViewModels)
│       │       ├── preferences/       # PreferencesScreen + ViewModel
│       │       ├── dashboard/         # (vazio)
│       │       ├── history/           # (vazio)
│       │       └── account/           # (vazio)
│       └── main/keepRules/rules.keep  # ProGuard placeholder
```

### 2.2 Tecnologia Identificada

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Linguagem | Kotlin | 2.1.0 |
| Multiplataforma | Kotlin Multiplatform (KMP) | — |
| UI | Compose Multiplatform + Material 3 | 1.7.3 |
| Navegação | Navigation Compose (JetBrains fork) | 2.8.0-alpha10 |
| HTTP | Ktor Client | 3.0.3 |
| Serialização | kotlinx.serialization.json | 1.7.3 |
| Async | Coroutines + Flow | 1.10.1 |
| DI | Koin | 4.0.0 |
| Storage | DataStore Preferences | 1.1.1 |
| Secure Storage | EncryptedSharedPreferences | 1.1.0-alpha06 |
| Push | Firebase Cloud Messaging | BOM 33.9.0 |
| Crash | Firebase Crashlytics | BOM 33.9.0 |
| Analytics | Firebase Analytics | BOM 33.9.0 |
| Build | Gradle Kotlin DSL | 8.13 |
| Android | API 24+ (minSdk 24, targetSdk 35) | — |
| Arquitetura | Clean Architecture + MVVM | — |

### 2.3 Funcionalidades Já Existentes

**Totalmente implementadas:**

1. **Splash Screen** — Auto-login via `AuthRepository.isLoggedIn()`, redireciona para login ou oportunidades
2. **Login** — Email/senha com estados de loading, erro, sucesso; suporte a 2FA (`LoginResult.Requires2FA`)
3. **Forgot Password** — Formulário de email, envia reset via `POST /auth/forgot-password`
4. **Lista de Oportunidades Ativas** — Cards com símbolo, direção, radar state, entry/stop/tp1, ETA
5. **Detalhe da Oportunidade** — Entry, stop, tp1/tp2/tp3, preço atual, ETA, distância em pts/ATR, approach velocity, lifecycle, mensagem, disclaimer de risco
6. **Navegação via Deep Link** — `maximus://opportunity/{id}` configurado no AndroidManifest
7. **Firebase Cloud Messaging** — Token refresh, recebimento de mensagens, registro no backend via `POST /mobile/devices`
8. **Notificações Push** — Canal "Alertas de Oportunidades", notificações com PendingIntent deep link, single-top activity
9. **Preferências** — Toggle para push notifications e ativos WINFUT on/off, salvo via DataStore
10. **Secure Token Storage** — AES256-GCM via EncryptedSharedPreferences
11. **Device Registration** — Envia token FCM para `POST /mobile/devices`, persiste device ID retornado
12. **Logout** — `POST /auth/logout`, limpa token e device ID
13. **Error Handling** — Todos os estados de erro com botões "Tentar novamente"
14. **Dark Theme** — Custom dark color scheme com fundo "deep void", brand colors roxas, cores de buy/sell/alert

**Parcialmente implementadas / Scaffold vazio:**

1. **Dashboard** — Diretório `features/dashboard/` existe sem arquivos
2. **History** — Diretório `features/history/` existe sem arquivos (interface `getHistory()` existe no repositório)
3. **Account** — Diretório `features/account/` existe sem arquivos
4. **Deep Link Handler** — Diretórios `core/deeplink/` e `deeplink/` vazios
5. **DTO/Mapper/Remote** — Diretórios `data/dto/`, `data/mapper/`, `data/remote/`, `domain/usecase/` vazios
6. **Sonar/Scanner States** — Model define `sonarEnabled` mas UI só expõe `pushEnabled` e `winfutEnabled`

**Não implementado (conforme plano):**

- Tela de histórico de oportunidades
- Dashboard com cards resumo, ticker tape, scanner health
- Account/profile screen
- Preferências por radar state (PREPARAR, ENTRADA_PROXIMA, NA_ZONA)
- Quiet hours e max pushes por hora (campos existem no model, sem UI)
- Sound/vibration settings
- `FcmOpportunityPayload` parser
- Testes unitários
- iOS (`iosMain` source set)

### 2.4 Integrações Encontradas

| Integração | Status | Detalhes |
|-----------|--------|----------|
| API MaximusTrader | Configurada | `AppConfig.kt`: `BASE_URL = "https://maximustrade.com.br/api/"` |
| Firebase | Configurada | `google-services.json` presente, projeto `maximus-trade-signals` |
| FCM Push | Implementada | Token → backend, recebimento, notificação local com deep link |
| Deep Links | Configurada | `maximus://` scheme no AndroidManifest |
| Crashlytics | Configurada | Plugin no build.gradle.kts |
| Analytics | Configurada | Firebase Analytics declarado como dependência |

### 2.5 Status Atual

**Classificação: PARCIALMENTE IMPLEMENTADO (MVP CORE FUNCIONAL)**

**Justificativa:** O app possui funcionalidades essenciais completas: autenticação (login, 2FA, forgot password), push notifications FCM, lista de oportunidades com detalhes, deep link navigation e preferências básicas. Clean Architecture implementada com MVVM, DI, e multiplataforma. Entretanto, 3 telas principais estão vazias (dashboard, histórico, conta), preferências avançadas não têm UI, não há testes unitários, e a plataforma iOS está apenas planejada. O app está em estágio de MVP — funcionalidades core prontas, mas incompleto para release.

### 2.6 Pendências

1. Implementar tela de Dashboard
2. Implementar tela de Histórico de Oportunidades
3. Implementar tela de Conta/Perfil
4. Implementar preferências avançadas (quiet hours, radar states, sound)
5. Implementar `FcmOpportunityPayload` parser
6. Preencher DTOs, Mappers e UseCases (atualmente vazios)
7. Criar testes unitários
8. Criar `iosMain` source set para suporte iOS
9. Testar em dispositivo real
10. Configurar CI/CD para build Android

### 2.7 Próximos Passos Recomendados

1. **Implementar Dashboard** — Cards de resumo: oportunidades ativas, alertas, saúde do scanner
2. **Implementar Histórico** — Lista paginada com filtros por ativo/direção/status/data
3. **Implementar Account** — Perfil, alterar senha, gerenciar dispositivos, logout
4. **Preferências Avançadas** — UI para quiet hours, max pushes/hora, per-radar-state toggles
5. **DTOs e Mappers** — Tipar corretamente os payloads da API
6. **Testes** — Unit tests para ViewModels e Repositories
7. **iOS** — Configurar `iosMain`, adaptar Firebase para iOS
8. **CI/CD** — GitHub Actions ou similar para build + distribuição

---

## 3. Parte 2 — MaximusTrader

### 3.1 Estrutura Encontrada

```
MaximusTrader/
├── backend/                              # Laravel 12
│   ├── app/
│   │   ├── Console/Commands/             # FcmTest, RegisterTestDevice
│   │   ├── Http/
│   │   │   ├── Controllers/Api/          # 14 controllers
│   │   │   │   ├── AuthController.php    # Login, 2FA, forgot/reset password
│   │   │   │   ├── AdminController.php   # Dashboard, users, plans, sales
│   │   │   │   ├── AlertController.php   # CRUD alertas
│   │   │   │   ├── IndicatorController.php
│   │   │   │   ├── PlanController.php
│   │   │   │   ├── MarketDataController.php  # Assets, candles, zones, study, state
│   │   │   │   ├── SyncController.php    # HMAC sync bridge
│   │   │   │   ├── ScannerAlertController.php
│   │   │   │   ├── WebhookController.php # Pagamentos
│   │   │   │   ├── FcmTestController.php
│   │   │   │   ├── MobileOpportunityController.php
│   │   │   │   ├── MobileDeviceController.php
│   │   │   │   └── MobilePreferenceController.php
│   │   │   └── Middleware/               # Cors, EnforcePlanLimits, VerifySyncHmac, VerifyScannerHmac
│   │   ├── Jobs/SendOpportunityPushNotification.php
│   │   ├── Models/                       # 24 models
│   │   ├── Services/
│   │   │   ├── FirebasePushService.php   # FCM HTTP v1 + OAuth JWT
│   │   │   └── Webhooks/                 # 7 providers + Abstract + Generic
│   │   └── Providers/AppServiceProvider.php
│   ├── bootstrap/app.php, providers.php
│   ├── composer.json                     # PHP 8.2+, Laravel 12, Sanctum 4, Spatie Permission 6
│   ├── config/                           # 12 arquivos de config
│   ├── database/
│   │   ├── migrations/                   # 17 migrations
│   │   └── seeders/                      # DatabaseSeeder, PlanAndRoleSeeder
│   ├── .env, .env.example, .env.production.example
│   ├── routes/
│   │   ├── api.php                       # 183 linhas, ~43 endpoints
│   │   ├── console.php
│   │   └── web.php
│   ├── tests/                            # 7 arquivos PHP de teste
│   └── phpunit.xml
├── frontend/                             # React 19 + TypeScript 6 + Vite 8
│   ├── src/
│   │   ├── App.tsx                       # Router principal
│   │   ├── main.tsx, index.css
│   │   ├── components/
│   │   │   ├── CandlestickChart.tsx       # Gráfico principal (lightweight-charts v5)
│   │   │   ├── PlotlyCandlestickChart.tsx # Gráfico alternativo (Plotly)
│   │   │   ├── BackgroundEffects.tsx      # Efeitos visuais
│   │   │   └── chart/smc/                # 8 módulos SMC overlay engine
│   │   ├── contexts/AuthContext.tsx       # Estado de autenticação
│   │   ├── hooks/
│   │   │   ├── useRealMarketData.ts       # Hook produção (REST API)
│   │   │   ├── useMarketWebSocket.ts      # Socket.io real-time
│   │   │   └── useMarketData.ts           # Mock/legacy
│   │   ├── lib/api.ts                    # Fetch wrapper
│   │   └── pages/                        # 15 páginas
│   ├── package.json                      # React 19.2, Plotly 3.6, lightweight-charts 5.2
│   ├── vite.config.ts, tsconfig.json
│   ├── index.html
│   └── dist/                             # Build output
├── docs/
│   ├── ARQUITETURA_GRAFICO.md
│   ├── cloudflare-config.md
│   ├── legal/TEXTOS_LEGAIS_MAXIMUS_TRADE_V1.md
│   └── Planos/                           # 5 documentos de plano
├── tools/
│   └── deploy.sh                         # Deploy Hostinger (MD5 diff sync)
├── deploy.ps1                            # Deploy Windows
├── scripts/backup.sh                     # Backup MySQL criptografado
└── MAXIMUS-DESIGN-SYSTEM/                # Assets de design
```

### 3.2 Tecnologia Identificada

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Backend | PHP / Laravel | 8.2+ / 12.x |
| Auth | Laravel Sanctum | 4.0 |
| Roles | Spatie Laravel Permission | 6.25 |
| 2FA | spomky-labs/otphp (TOTP) | 11.4 |
| Database | MySQL (prod) / SQLite (dev) | — |
| Queue | Database driver | — |
| Frontend | React + TypeScript | 19.2 / 6.0 |
| Build | Vite | 8.x |
| CSS | Tailwind CSS | 4.3 |
| Routing | React Router DOM | 7.16 |
| Charts Primary | lightweight-charts | 5.2 |
| Charts Secondary | Plotly.js | 3.6 |
| Charts Legacy | ApexCharts | 5.13 |
| Realtime | Socket.io Client | 4.8.3 |
| Push | Firebase Admin SDK (HTTP v1) | — |
| Hosting | Hostinger (shared) | — |
| DNS/CDN | Cloudflare | Full SSL, WAF |

### 3.3 Funcionalidades Já Existentes

**Backend — API Completa (43 endpoints):**

| Grupo | Endpoints | Descrição |
|-------|-----------|-----------|
| Auth | 12 | Register, login, 2FA (setup/enable/disable/verify), recovery codes, forgot/reset password, me, logout, logout-all |
| Plans | 2 | List, show (público) |
| Admin | 9+ | Dashboard, users, plans CRUD, licenses, products, sales, indicators CRUD |
| Market Data | 7 | Assets, candles, zones, study, elliott, wyckoff, state (5-in-1 agregado) |
| Alerts | 5 | CRUD completo (por usuário) |
| Indicators | 1 | List |
| Sync Bridge | 6 | HMAC: candles, zones, elliott, wyckoff, study, sync geral |
| Scanner | 2 | HMAC: receive alerts, test FCM |
| Mobile | 8 | Opportunities (active, detail, history), devices (register, unregister), preferences (show, update, assets, proximities) |
| Webhooks | 1+ | Hotmart, Kiwify, Stripe, MercadoPago, PayPal + Generic (7+ provedores) |
| Health | 1 | /up |

**Backend — Database (17 migrations, 24 models):**

- Tabelas core: users, cache, jobs, personal_access_tokens, permissions (5 roles)
- Comercial: plans, licenses, subscriptions, purchases, products
- Indicadores: indicators, alerts, configurations
- Logs: webhook_logs, access_logs, audit_logs
- Scanner: scanner_alerts, opportunities
- Push/Mobile: user_devices, notification_preferences, push_logs
- Sync: sync_assets, sync_candles, sync_zones, sync_studies, sync_elliott_waves, sync_wyckoff_phases, sync_wyckoff_events

**Backend — Services:**

- `FirebasePushService.php` — FCM HTTP v1 completo: OAuth JWT, dry-run, proximity-based priority, APNs + Android config
- `SendOpportunityPushNotification.php` (Job) — Fila: verifica preferências do usuário (asset filter, proximity, quiet hours, rate limit, dedup), envia push, loga resultado. Retry 3x com backoff 30s

**Backend — Middleware:**

- `VerifySyncHmac.php` — Autenticação HMAC-SHA256 + API key + timestamp (anti-replay) para sync VPS→Site
- `VerifyScannerHmac.php` — Bearer token + HMAC-SHA256 + idempotency key para scanner alerts
- `EnforcePlanLimits.php` — Verifica licença ativa, limites do plano (alerts, timeframes, assets, replay, IA)
- `Cors.php` — CORS para origin do frontend

**Frontend — Páginas (15):**

| Página | Rota | Status |
|--------|------|--------|
| Landing | `/` | Pública — landing page com pricing table |
| Login | `/login` | Login com fluxo 2FA |
| Register | `/register` | Registro de usuário |
| Dashboard | `/admin/dashboard` | Shell admin com sidebar |
| Chart | `/admin/grafico` | Gráfico principal (lightweight-charts + SMC overlay) |
| Watchlist | `/admin/watchlist` | Tabela multi-ativo |
| Replay | `/admin/replay` | Replay histórico |
| Alertas | `/admin/alertas` | Gestão de alertas |
| Indicadores | `/admin/indicadores` | Configuração de indicadores |
| Admin Planos | `/admin/planos` | CRUD planos |
| Admin Usuários | `/admin/usuarios` | Gestão de usuários |
| Admin Licenças | `/admin/licencas` | Gestão de licenças |
| Admin Vendas | `/admin/vendas` | Dashboard de vendas |
| Admin Produtos | `/admin/produtos` | Gestão de produtos |
| Admin Config | `/admin/config` | Configurações do sistema |

**Frontend — SMC Chart Engine (8 módulos):**

Módulos em `components/chart/smc/`:
- `smcTypes.ts` — Tipos de zonas
- `smcStyle.ts` — Estilos de zonas (cores, opacidades, dash patterns)
- `smcNormalize.ts` — Normalização de zonas vindas da API
- `smcVisibility.ts` — Culling de visibilidade
- `smcLabelCollision.ts` — Evitar sobreposição de labels
- `SmcPaneRenderer.ts` — Canvas renderer para zonas SMC
- `SmcPaneView.ts` — Pane view
- `SmcSeriesPrimitive.ts` — Series primitive nativa (lightweight-charts Canvas API)

**Plano Comercial (4 planos):**

| Plano | Mensal | Anual | Ativos | Alertas | Timeframes | Features |
|-------|--------|------|--------|---------|------------|----------|
| Free | R$ 0 | R$ 0 | 1 | 1 | M5 | Básico |
| Starter | R$ 29,90 | R$ 299 | 3 | 5 | M2, M5 | Real-time |
| Pro | R$ 59,90 | R$ 599 | 6 | 20 | M2, M5, M15, H1 | IA + Replay |
| Enterprise | R$ 99,90 | R$ 999 | 11 | 50 | +H4 | Completo |

### 3.4 Documentos Encontrados

| Documento | Caminho | Resumo |
|-----------|---------|--------|
| ARQUITETURA_GRAFICO.md | `docs/ARQUITETURA_GRAFICO.md` | Documento completo da arquitetura do gráfico: camadas lightweight-charts, renderização SMC, pipeline de dados das tabelas shadow → sync → API → React chart. Inclui queries SQL, transformações e diagramas. |
| cloudflare-config.md | `docs/cloudflare-config.md` | Configuração Cloudflare: DNS, SPF/DKIM/DMARC, WAF (rate limits, firewall, security headers, cache rules, SSL/TLS), bot fight mode. |
| TEXTOS_LEGAIS | `docs/legal/` | Política de privacidade, termos de uso, disclaimer de risco |
| plano_correcao_zonas_smc_overlay.md | `docs/Planos/` | Plano de correção do overlay de zonas SMC |
| plano_maximus_smc_layer_engine_lightweight_charts.md | `docs/Planos/` | Plano da engine de renderização SMC no lightweight-charts |
| plano-migracao-plotly-v2.md | `docs/Planos/` | Plano de migração do Plotly para lightweight-charts |
| PLANO_SITE_MAXIMUS_TRADE_V1.md | `docs/Planos/` | Plano geral do site |
| plano_smc_series_primitive_renderer.md | `docs/Planos/` | Plano do renderer nativo Canvas (series primitive) |
| plano_app_kotlin_multiplatform | `AppAndroid/.../Plano/` | Plano completo do app Android (duplicado) |

### 3.5 Status Atual

**Classificação: FUNCIONAL COM BUGS — EM MIGRAÇÃO**

**Justificativa:** O backend Laravel está robusto: 43 endpoints, 24 models, 17 migrations, autenticação completa (Sanctum + 2FA + Spatie roles), sync bridge HMAC funcional, push notifications FCM implementadas, webhooks de pagamento para 7+ provedores. O frontend React possui 15 páginas mas a migração do sistema de gráficos ainda está em andamento — coexistem implementações em lightweight-charts, Plotly e ApexCharts. O plano de correção do eixo Plotly (`warm-weaving-mountain.md`) foi identificado como necessário e parece já implementado no código atual. A engine SMC overlay (8 módulos) está implementada para lightweight-charts. A cobertura de testes frontend é zero. O deploy está maduro com scripts bash e PowerShell, MD5 diff sync eficiente, e backup MySQL criptografado.

**Pontos fortes:**

- Backend Laravel bem estruturado e completo
- Sync bridge HMAC funcional e bem desenhado
- Sistema de planos comerciais implementado
- FCM push completo (service, job, preferences, dedup, rate limit)
- Deploy robusto e testado
- Cloudflare configurado (WAF, SSL, cache)

**Pontos fracos:**

- Frontend com 3 bibliotecas de gráfico — duplicação e inconsistência
- 0 testes frontend
- Bugs visuais nos gráficos (eixos Plotly corrigidos no código atual, lightweight-charts overlay em evolução)
- Páginas admin parcialmente implementadas
- Sem testes end-to-end
- Frontend não tem estrutura de error boundary ou tratamento global de erros

### 3.6 Problemas e Riscos Encontrados

| # | Problema | Severidade | Detalhe |
|---|----------|-----------|---------|
| 1 | Múltiplas bibliotecas de gráfico | Média | lightweight-charts, Plotly e ApexCharts coexistem. Duplicação de lógica e manutenção. |
| 2 | Zero testes frontend | Alta | 37 arquivos TSX/TS sem cobertura de testes |
| 3 | Bugs visuais em gráficos | Média | Plano `warm-weaving-mountain.md` identificou 10 bugs de eixo no Plotly (corrigidos no código atual) |
| 4 | SMC overlay em evolução | Baixa | 8 módulos implementados, mas série primitive Canvas ainda em maturação |
| 5 | Falta de tratamento global de erros | Média | Frontend sem ErrorBoundary ou tratamento unificado |
| 6 | Duplicação de lógica de indicadores | Média | EMA/RSI calculados tanto no backend quanto no frontend |
| 7 | Deploy manual | Baixa | Sem CI/CD automatizado para testes e deploy |
| 8 | Backups não automatizados na VPS | Média | Script de backup existe mas execução depende de cron manual |
| 9 | Configurações hardcoded | Baixa | URLs e secrets parcialmente hardcoded no deploy.sh |

### 3.7 Próximos Passos Recomendados

1. **Consolidar gráficos** — Remover ApexCharts legado, padronizar lightweight-charts como principal, remover Plotly se redundante
2. **Corrigir bugs visuais** — Aplicar correções do plano `warm-weaving-mountain.md` se ainda pendentes
3. **Testes frontend** — Implementar Jest + React Testing Library para componentes e hooks
4. **Error Boundary** — Implementar tratamento global de erros no React
5. **CI/CD** — GitHub Actions: lint, typecheck, build, deploy automático
6. **E2E Tests** — Cypress ou Playwright para fluxos críticos (login, gráfico, alertas)
7. **API Documentation** — OpenAPI/Swagger para documentação dos 43 endpoints
8. **Monitoramento** — Health checks automáticos para API e sync bridge
9. **Otimização de performance** — Code splitting, lazy loading de páginas admin
10. **Internacionalização** — Preparar i18n para suporte multi-idioma

---

## 4. Parte 3 — Sistema Local de Cálculo

### 4.1 Estrutura Encontrada

```
SMC_Trader_System 7.0/
├── Executáveis principais (root)
│   ├── run_b3.py                 # Robô B3 (WINFUT, WDOFUT, PETR4, VALE3, ITUB3)
│   ├── run_forex.py              # Robô Forex (XAUUSDm, BTCUSDm, ETHUSDm, EURUSDm, USDJPYm)
│   ├── mt5_connection.py         # Ponte RPyC dual-port para MT5
│   ├── start.sh, start_bridges.sh, start_tunnel.sh
│   ├── diagnostic.sh, cleanup_vps.sh, install_mt5.sh
│   ├── requirements.txt          # 15 dependências Python
│   ├── .env                      # Variáveis de ambiente ativas
│   ├── .env.example              # Template
│   └── settings.json             # Configurações runtime
│
├── technical_engine/             ★ Motor principal — 34 módulos especializados
│   ├── smc_engine_v2/            # SMC Engine V2 (FROZEN — 164 tests)
│   │   ├── pipeline.py           # Orquestrador 10 steps
│   │   ├── fvg.py, order_blocks.py, structure.py
│   │   ├── liquidity.py, bpr.py, swings.py
│   │   ├── sessions.py, retracements.py, previous_high_low.py
│   │   ├── persistence.py        # 10 shadow tables
│   │   ├── config.py, models.py, adapter.py
│   │   ├── snapshot_mapper.py, renderer_contracts.py
│   ├── study_gateway/            # Study Gateway (PRONTO — 123 tests)
│   │   ├── models_v2.py          # TechnicalTruthEnvelopeV2 (SHA-256)
│   │   ├── smc_v2_adapter.py     # SMC → canonical envelope
│   │   ├── confluence_v2.py      # 6 fontes com pesos + MTF fusion
│   │   ├── context_states.py     # Elliott/Wyckoff helper
│   │   ├── forward_runner.py     # Forward shadow gateway (6L)
│   │   ├── risk_management_v2.py # OperationalPlanV2
│   │   ├── hit_rates_v2.py       # Walk-forward simulator
│   │   ├── professional_study_renderer.py
│   │   ├── operational_plan_persistence.py
│   │   └── operational_status.py
│   ├── opportunity_scanner/      # Scanner (ATIVO — 306 tests)
│   │   ├── scanner.py            # scan_once() orquestrador
│   │   ├── evaluator.py          # 10+ deterministic gates
│   │   ├── loader.py, signal_builder.py, dedup.py
│   │   ├── notifier.py, http_post_notifier.py (HMAC → Laravel)
│   │   ├── persistence.py        # 3 shadow tables
│   │   ├── config.py, models.py
│   │   └── ab_shadow_compare.py, benchmark.py, calibration_review.py
│   ├── elliott/                  # Elliott Wave Engine
│   ├── wyckoff/                  # Wyckoff Engine
│   ├── elliott_wyckoff_shadow/   # EW persistence to DB
│   ├── contextual_market_profile/ # Volatility, session, regime, HTF bias
│   ├── volume_profile/           # Volume profile engine
│   ├── confluence/               # Confluence evidences
│   ├── backtest/                 # Technical backtest engine
│   ├── smc/                      # SMC core (legacy V1)
│   ├── study_pipeline_shadow/*/  # Pipeline shadow (6 sub-módulos)
│   ├── zone_tracking/            # Zone tracker
│   ├── zone_memory/              # Zone memory builder V2
│   ├── memory_ranking/           # Memory-based ranking
│   ├── memory_calibration/       # Memory-based calibration
│   ├── study_consumers/          # Payload consumers (8 routes)
│   ├── study_scheduler/          # Study scheduler
│   ├── worker_shadow/            # Shadow worker
│   ├── persistence/              # Shadow persistence helpers
│   ├── shadow_database/          # Shadow DB persistence
│   ├── visual_shadow/            # Visual comparison shadow
│   ├── visual_study_overlay/     # HTML/Plotly overlay
│   ├── strictness_calibration/   # SMC strictness
│   ├── contextual_calibration/   # Contextual calibration V2
│   └── config_*/                 # Config pipeline (proposal, backtest, forward, promotion)
│
├── infra/                        # Infraestrutura
│   ├── database.py               # 6881 linhas — MySQL persistence
│   ├── sync_v2.py                # 1051 linhas — Sync VPS → Site (HMAC)
│   ├── mt5_core.py               # Processamento dados MT5
│   ├── indicators.py             # EMA, RSI, ATR
│   ├── config_manager.py, robot_singleton.py, robot_health.py
│   ├── asset_profiles.py
│   ├── llm_provider.py, llm_healthcheck.py
│   ├── openrouter_dual_key.py    # Dual API key failover
│   └── openrouter_model_discovery.py
│
├── dashboard_shadow/             # Dashboards
│   ├── backend/                  # FastAPI :8008 (+ WebSocket)
│   ├── frontend/                 # React/Vite/Tailwind + lightweight-charts
│   └── dash_app/                 # Dash/Plotly :8050
│
├── tests/                        # 414 arquivos de teste Python
│   ├── test_smc_engine_v2/       # 164 tests
│   ├── test_study_gateway/       # 123 tests
│   ├── test_opportunity_scanner/ # 306 tests
│   ├── test_technical_engine/    # Maior suite (Fases 0-6I)
│   ├── test_dashboard_shadow/
│   ├── test_dashboard_backend/
│   ├── test_study_pipeline_shadow/
│   ├── test_smc_reference/
│   └── ...                       # Diversas outras suites
│
├── tools/                        # 111 scripts Python + 2 shell
│   ├── sync_to_web.py            # CLI sync
│   ├── sync_v2_engine.py         # V2 engine sync
│   ├── sync_multi_layer.py       # Multi-layer sync
│   ├── sync_all_timeframes.py    # Sync todos TFs
│   ├── full_backfill_v2.py       # Backfill completo V2
│   ├── deploy_s28.py             # Deploy FCM test
│   └── ...                       # ~100 outros
│
├── systemd/                      # 10 serviços systemd
├── deploy/systemd/               # +9 serviços (+ 1 timer)
├── config/                       # Arquivos de configuração
├── database/                     # SQL migrations (shadow tables)
├── migrations/                   # Python migrations
├── docs/                         # ~60+ documentos
│   ├── app_android/              # 5 docs (API contract, migrations, E2E, FCM setup, release checklist)
│   ├── ativos/                   # Docs por módulo ativo
│   ├── contratos/                # Contratos técnicos (5 docs)
│   ├── auditorias-fase-0-5/      # ~15 auditorias históricas
│   ├── guias/                    # 6 guias (instalação, MT5Linux, Gemini, etc.)
│   └── historico/                # Documentação legada
│
├── scripts/                      # Scripts operacionais (12)
│   ├── operational/              # sync batches
│   ├── start_dashboard_shadow.sh
│   ├── stop_dashboard_shadow.sh
│   └── health_check.sh
│
├── backups/                      # Código e dados
├── storage/                      # Storage ativo
├── runtime/                      # Outputs, logs, snapshots
├── log/                          # Logs
└── venv/                         # Virtual environment
```

### 4.2 Tecnologia Identificada

| Camada | Tecnologia |
|--------|-----------|
| Linguagem | Python 3.11+ |
| Database | MySQL 8.0+ (`smc_trader_2_db`) |
| Data Source | MetaTrader 5 (Wine) → RPyC bridges (ports 11000/11001) |
| Dashboards | FastAPI + React/Vite (:8008), Dash/Plotly (:8050), Streamlit (legado) |
| LLM | OpenRouter (cloud, primário) + Ollama (local, fallback) |
| Sistema Operacional | Ubuntu Linux (VPS) |
| Gerenciamento | 19 systemd services |
| Testes | pytest, 2522+ passando (414 arquivos) |

### 4.3 Módulos Principais Encontrados

**SMC Engine V2** (`technical_engine/smc_engine_v2/`) — **STATUS: STABLE_FROZEN_V2 (164 testes)**

Motor de análise técnica SMC. Pipeline de 10 passos com shared swings_df. Detecta:
- **FVG** (Fair Value Gaps): 3-candle imbalance, mitigation 50%, vetorizado
- **OB** (Order Blocks): prev+wick, quality scoring configurável
- **BOS/CHOCH** (Structure): 4-swing pattern, close_break, 62% continuation
- **Liquidity**: ATR-based cluster, swept detection
- **BPR** (Balanced Price Ranges): overlap FVG bull+bear, dedup >60%
- **Swings**: rolling window, sem forced alternation
- **Sessions**: London, B3, NY, Asia
- **Retracements**: percentuais de retração
- **PDH/PDL**: Previous Day High/Low
- **Persistence**: 10 shadow tables MySQL, load/save, latest_candle_time auto

**Study Gateway** (`technical_engine/study_gateway/`) — **STATUS: PRONTO (123 testes)**

- `TechnicalTruthEnvelopeV2` com SHA-256 deterministic
- `StudyPayloadTechnicalTruthV2` com sanity gates e blockers
- Confluence V2: 6 fontes com pesos + MTF weighted fusion (H4/M15/M5)
- Risk Management V2: OperationalPlanV2 com entry/stop/TP/R:R + 3 camadas MTF
- Hit Rates V2: Walk-forward simulator + tabulation + expectancy_R
- Forward Runner: shadow gateway 6L (ONCE/LOOP), idempotency, alarms
- Professional Study Renderer: template narrativo markdown + coluna hit rate histórica
- Operational Plan Persistence: save/expire shadow, heartbeat NOW()

**Opportunity Scanner** (`technical_engine/opportunity_scanner/`) — **STATUS: ATIVO (306 testes)**

- `scan_once()` orquestrador
- 10+ deterministic gates: freshness, proximity, HTF alignment, pullback, health
- Dedup 15min key (symbol+direction+proximity+price)
- HMAC-signed POST para Laravel (S24)
- A/B shadow compare, benchmark, calibration review
- Suporte a 6 ativos (WINFUT, WDOFUT, PETR4, VALE3, XAUUSDm, BTCUSDm)
- Config profiles, plan lifecycle, early entry radar

**Elliott Wave** (`technical_engine/elliott/`) — 14 pivots, 9 wave legs, trend/stage/pattern, 4 sanity rules

**Wyckoff** (`technical_engine/wyckoff/`) — 4 fases, 8 eventos (SPRING/UT/SOS/SOW), Effort/Result, 3 sanity rules

**Infra** (`infra/`):

- `database.py` (6881 linhas): Toda persistência MySQL — market_candles, analysis_history, 10 shadow tables, sync functions
- `sync_v2.py` (1051 linhas): TRIGGER 4 V2 pipeline, Elliott/Wyckoff shadow, HMAC sync
- `mt5_core.py`: Processamento MT5, fetch, INSERT
- `indicators.py`: EMA, RSI, ATR
- `config_manager.py`: Config runtime
- `robot_singleton.py`, `robot_health.py`: Gerenciamento de robôs

**Dashboard Shadow** (`dashboard_shadow/`):

- Backend FastAPI (:8008): REST + WebSocket
- Frontend React/Vite: Dashboard, Chart, Watchlist, Alerts, Indicators, Replay, Admin
- Dash/Plotly (:8050): Dashboard alternativo multi-layer

**Pipeline de Configuração** (6 módulos):

- `config_proposal/` → Proposta de configuração
- `config_backtest_validation/` → Validação em backtest
- `config_forward_validation/` → Validação forward
- `config_promotion/` → Promoção a candidate
- `calibration/` → Propostas de calibração
- `strictness_calibration/`, `contextual_calibration/` → Calibração fina

### 4.4 Documentos Encontrados

| Documento | Resumo |
|-----------|--------|
| `ARQUITETURA_OFICIAL.md` | Documento canônico da arquitetura: guardrails (8 invariants), pipeline completo, stack, roadmap |
| `CHANGELOG.md` (2044 linhas) | Histórico cronológico completo de todas as fases e alterações |
| `DOCUMENTATION_INDEX.md` | Índice da documentação |
| `MEMORIA_OFICIAL.md` | Memória oficial do sistema |
| `README.md` | Visão geral (v6.0 era) |
| `docs/ESTUDO_CANONICO_V2.md` | Especificação do estudo canônico V2 |
| `docs/FASE_4_HIT_RATES.md` | Documentação dos hit rates (Fase 4) |
| `docs/FOREX_ROBOT_DEPLOY.md` | Guia de deploy do robô Forex |
| `docs/contratos/` | 5 contratos técnicos (Truth V2, EW Sanity, Config Forward, Config Promotion, Memory Ranking) |
| `docs/ativos/` | Documentação de módulos ativos (SMC V2, Dashboard, Study Pipeline, Contextual, Prune) |
| `docs/guias/` | 6 guias (Code Review, Gemini, Ubuntu Install, MT5Linux Bridge, Shadow Workflow, Tutorial MT5Linux) |
| `docs/app_android/` | 5 docs (API Contract, Backend Migrations, E2E Test Plan, FCM Setup, Release Checklist) |
| `docs/historico/` | Documentação legada (arquitetura antiga, auditorias fase 0-5) |

### 4.5 Status Atual

**Classificação: FUNCIONAL COM PENDÊNCIAS — PRONTO PARA SHADOW RUN**

**Justificativa:** O sistema local é a parte mais madura do ecossistema. Possui 414 arquivos de teste com 2522+ testes passando. O SMC Engine V2 está congelado (STABLE_FROZEN_V2). O Study Gateway está pronto com 123 testes. O Opportunity Scanner está ativo com 306 testes. A infraestrutura de sincronização (HMAC) está implementada e funcional. Os robôs B3 e Forex rodam via systemd. As dashboards locais (FastAPI :8008 e Dash :8050) estão operacionais. O pipeline de configuração (proposta → backtest → forward → promoção) está implementado.

**O que falta para produção:**

- Ativação completa do pipeline de promoção de configurações
- Sinais de trading ao vivo (guardrail `can_promote_trade=False` ainda ativo)
- iOS app (planejado, não iniciado)
- Ativação final do FCM push em produção
- Finalização dos gateways de pagamento

**Guardrails ativos (invariants):**

| Guardrail | Valor | Significado |
|-----------|-------|-------------|
| `shadow_only` | True | Nunca escreve em tabelas oficiais |
| `can_promote_trade` | False | Nunca promove sinal operacional |
| `apply_automatically` | False | Nunca aplica config automaticamente |
| `llm_decision_used` | False | LLM é redactress, nunca engine |
| `smc_recomputed` | False | SMC consumido por run_id |
| `deterministic` | True | Mesmo input = mesmo output (SHA-256) |
| `anti_lookahead` | True | Exclui última vela aberta |
| `probabilidade_proibida` | True | "Hit rate histórica", nunca "probabilidade" |

### 4.6 Integração com MaximusTrader

**O que já existe:**

1. **Sync V2 Engine** (`infra/sync_v2.py` — 1051 linhas): TRIGGER 4 pipeline completo
   - Detecta novas velas no MySQL local
   - Roda SMC Engine V2 (FVG, OB, BOS/CHOCH, Liquidity, BPR, etc.)
   - Persiste em 10 shadow tables
   - Sincroniza candles, zonas, Elliott, Wyckoff via HMAC POST

2. **HMAC Auth** — Implementado em ambos os lados:
   - VPS: `infra/database.py` (`sync_to_web()`, `sync_candles_only()`, `sync_zones_only()`)
   - Site: `VerifySyncHmac.php` middleware
   - Headers: `X-API-Key`, `Client-Id`, `Timestamp`, `Nonce`, `Signature`

3. **Endpoints Sincronizados:**
   - `POST /api/sync` — Candles + indicadores
   - `POST /api/sync/candles` — Apenas candles
   - `POST /api/sync/zones` — Apenas zonas SMC
   - `POST /api/sync/elliott` — Elliott waves
   - `POST /api/sync/wyckoff` — Wyckoff phases + events
   - `POST /api/sync/study` — Estudos canônicos
   - `POST /api/scanner/alerts` — Oportunidades do scanner

4. **CLI Tools:** 7 ferramentas de sincronização
   - `sync_to_web.py`, `sync_v2_engine.py`, `sync_multi_layer.py`
   - `sync_all_timeframes.py`, `full_backfill_v2.py`
   - `send_test_alert_to_laravel.py`, `sync_cron.sh`

5. **Dados Sincronizados:** 34,072 zonas (último sync reportado)

**O que está pendente:**

1. Automação completa do sync — atualmente requer trigger manual ou cron
2. Monitoramento de saúde do sync (heartbeat, alertas de falha)
3. Retry automático com backoff exponencial em falhas de rede
4. Validação de integridade dos dados sincronizados (checksum end-to-end)
5. Logs estruturados de sincronização no site

**Fluxo ideal de sincronização:**

```text
VPS (MySQL local)
  │
  ├─ TRIGGER 4: nova vela detectada em market_candles
  │
  ├─ SMC Engine V2 pipeline executado
  │   └─ Persiste 10 shadow tables
  │
  ├─ Elliott/Wyckoff executados
  │   └─ Persiste shadow tables
  │
  ├─ Study Gateway processa
  │   └─ TechnicalTruthEnvelopeV2 gerado
  │
  ├─ Opportunity Scanner avalia
  │   └─ OpportunitySignalV1 (se gates passarem)
  │
  ├─ HMAC POST → maximustrade.com.br
  │   ├─ /api/sync/candles     (a cada vela nova)
  │   ├─ /api/sync/zones       (a cada alteração de zona)
  │   ├─ /api/sync/elliott     (a cada alteração EW)
  │   ├─ /api/sync/wyckoff     (a cada alteração Wyckoff)
  │   └─ /api/scanner/alerts   (a cada oportunidade detectada)
  │
  └─ Site processa → salva MySQL → disponibiliza via API → push FCM
```

### 4.7 Problemas e Riscos Encontrados

| # | Problema | Severidade | Detalhe |
|---|----------|-----------|---------|
| 1 | Sync não totalmente automático | Alta | TRIGGER 4 depende de execução manual ou cron — ideal seria event-driven |
| 2 | Sem monitoramento de sync | Alta | Sem heartbeat/alerta se sync falhar por >N minutos |
| 3 | Sem retry automático | Média | Falhas de rede causam perda silenciosa de dados |
| 4 | Duplicidade de dashboards | Baixa | 3 dashboards (FastAPI :8008, Dash :8050, Streamlit) com sobreposição |
| 5 | Documentação fragmentada | Média | ~100+ docs em múltiplos diretórios, alguns desatualizados |
| 6 | Módulos legados | Baixa | `technical_engine/smc/` (V1) coexiste com `smc_engine_v2/` |
| 7 | Dependência MT5 Wine | Alta | MT5 só roda em Windows/Wine — ponto único de falha |
| 8 | Sem validação end-to-end dos dados | Média | Dados podem corromper entre VPS e site sem detecção |
| 9 | Backups manuais | Média | Scripts de backup existem mas execução não automatizada |
| 10 | Secrets em arquivos | Média | `.env` e `.credentials.json` com secrets que precisam de proteção |

### 4.8 Próximos Passos Recomendados

1. **Automatizar sync** — Implementar event-driven sync (trigger no MySQL ou watcher no Python)
2. **Monitoramento de sync** — Heartbeat a cada 60s, alerta se >5min sem sync
3. **Retry com backoff** — Implementar retry exponencial (1s, 2s, 4s, 8s, 16s) em falhas de POST
4. **Validação end-to-end** — Checksum dos dados no envio e recebimento
5. **Consolidar dashboards** — Manter apenas FastAPI :8008, descomissionar Dash :8050 e Streamlit
6. **Limpeza de módulos legados** — Remover `technical_engine/smc/` V1, padronizar V2
7. **Centralizar documentação** — Consolidar ~100+ docs em índice estruturado
8. **Automatizar backups** — Systemd timer para backup diário do MySQL
9. **Hardening de secrets** — Migrar de `.env` para vault ou secrets manager
10. **Pipeline de promoção** — Avançar guardrails para permitir sinais ao vivo após validação

---

## 5. Integração entre as 3 Partes

### 5.1 Contratos de API

**Sistema Local → MaximusTrader (HMAC):**

| Endpoint | Método | Payload | Autenticação |
|----------|--------|---------|--------------|
| `/api/sync` | POST | candles[], indicators{} | HMAC-SHA256 + API Key + Timestamp + Nonce |
| `/api/sync/candles` | POST | candles[] | HMAC |
| `/api/sync/zones` | POST | zones[], replace bool | HMAC |
| `/api/sync/elliott` | POST | waves[] | HMAC |
| `/api/sync/wyckoff` | POST | ranges[], events[] | HMAC |
| `/api/sync/study` | POST | indicators, projections, zones | HMAC |
| `/api/scanner/alerts` | POST | OpportunitySignalV1 | Bearer + HMAC + Idempotency Key |

**MaximusTrader → AppAndroid (Sanctum):**

| Endpoint | Método | Auth |
|----------|--------|------|
| `/api/auth/login` | POST | Público |
| `/api/auth/verify-2fa` | POST | Público |
| `/api/auth/logout` | POST | Sanctum |
| `/api/mobile/opportunities/active` | GET | Sanctum |
| `/api/mobile/opportunities/{id}` | GET | Sanctum |
| `/api/mobile/opportunities/history` | GET | Sanctum |
| `/api/mobile/devices` | POST | Sanctum |
| `/api/mobile/devices/{id}` | DELETE | Sanctum |
| `/api/mobile/preferences` | GET/PUT | Sanctum |

### 5.2 Dados Compartilhados

| Dado | Origem | Destino | Frequência |
|------|--------|---------|------------|
| Candles (OHLC + EMA/RSI/ATR) | MT5 → VPS | MaximusTrader | A cada vela nova (M1→D1) |
| Zonas SMC (FVG, OB, BOS, Liq, BPR) | SMC Engine V2 | MaximusTrader | A cada alteração |
| Elliott Waves | Elliott Engine | MaximusTrader | A cada alteração |
| Wyckoff Phases/Events | Wyckoff Engine | MaximusTrader | A cada alteração |
| Oportunidades (sinais) | Opportunity Scanner | MaximusTrader → App | On detection |
| FCM Tokens | App Android | MaximusTrader | On app start / token refresh |
| Preferências | App Android | MaximusTrader | On change |
| Alertas (push) | MaximusTrader | App Android (FCM) | On opportunity + prefs match |

### 5.3 Pontos Não Conectados

1. **Dashboard → App push preferences**: Preferências de notificação do app não têm endpoint para configurar por radar state (model já tem campos, API e UI pendentes)
2. **Scanner health → App dashboard**: Não há endpoint expondo saúde do scanner para o app
3. **System status → Site**: Não há painel de saúde do sistema local visível no site
4. **Logs centralizados**: Logs do sistema local e do site não são agregados
5. **Métricas de sync**: Não há dashboard de métricas de sincronização (latência, volume, erros)

### 5.4 Proposta de Fluxo Final

```text
┌─────────────────────────────────────────┐
│        SISTEMA LOCAL (VPS Linux)        │
│                                         │
│  MT5 → Candles → SMC V2 Pipeline        │
│              → Elliott / Wyckoff        │
│              → Study Gateway            │
│              → Opportunity Scanner      │
│                                         │
│  Persistência: 10 shadow tables (MySQL) │
│  Dashboards: FastAPI :8008 (local)      │
│                                         │
│  Sync Engine (event-driven):            │
│    ├─ Heartbeat a cada 60s              │
│    ├─ Retry exponencial em falhas       │
│    └─ Checksum end-to-end               │
└──────────────┬──────────────────────────┘
               │ HTTPS HMAC
               ▼
┌─────────────────────────────────────────┐
│     MAXIMUSTRADER (Hostinger)           │
│                                         │
│  Laravel 12 API:                        │
│    ├─ /api/sync/* ← recebe dados        │
│    ├─ /api/market/* ← expõe leitura     │
│    ├─ /api/auth/* ← autenticação        │
│    ├─ /api/mobile/* ← API para app      │
│    └─ /api/admin/* ← administração      │
│                                         │
│  Processamento:                         │
│    ├─ Salva MySQL (sync_* tables)       │
│    ├─ Push FCM (oportunidades)          │
│    └─ Webhooks pagamento                │
│                                         │
│  React SPA:                             │
│    ├─ Gráficos (lightweight-charts)     │
│    ├─ Dashboard / Watchlist / Replay    │
│    └─ Admin (planos, usuários, vendas)  │
└──────────────┬──────────────────────────┘
               │ REST (Sanctum Token)
               ▼
┌─────────────────────────────────────────┐
│         APP ANDROID (KMP)               │
│                                         │
│  Features:                              │
│    ├─ Login / 2FA                       │
│    ├─ Oportunidades (lista + detalhe)   │
│    ├─ Dashboard (pendente)              │
│    ├─ Histórico (pendente)              │
│    ├─ Preferências (básico ok)          │
│    └─ Conta (pendente)                  │
│                                         │
│  Push: Firebase FCM                     │
│  Deep Links: maximus://opportunity/{id} │
└─────────────────────────────────────────┘
```

---

## 6. Tabela Geral de Status

| Parte | Caminho | Tecnologia | Status Atual | Principais Pendências | Prioridade |
|-------|---------|-----------|-------------|----------------------|-----------|
| **AppAndroid** | `AppAndroid/MaximusTrader/` | Kotlin 2.1.0, KMP, Compose Multiplatform 1.7.3, Ktor 3.0, Koin 4.0, Firebase BOM 33.9 | Parcialmente implementado — MVP core funcional | Dashboard, Histórico, Conta (vazios); DTOs/Mappers/UseCases vazios; 0 testes; sem iOS | **Alta** |
| **MaximusTrader** | `MaximusTrader/` | Laravel 12, PHP 8.2, React 19.2, TypeScript 6.0, Vite 8, lightweight-charts 5.2, Tailwind 4.3 | Funcional com bugs — Backend robusto, frontend em migração de gráficos | 3 bibliotecas de gráfico coexistindo; 0 testes frontend; bugs visuais; sem CI/CD; sem E2E | **Alta** |
| **Sistema Local** | `SMC_Trader_System 7.0/` | Python 3.11, MySQL 8.0, FastAPI, Dash/Plotly, MT5/RPyC | Funcional com pendências — 2522+ testes passando, engines estáveis | Sync não 100% automático; sem monitoramento; sem retry; dashboards duplicados; doc fragmentada | **Média** |

### Status Detalhado por Subsistema

| Subsistema | Testes | Cobertura | Estabilidade | Observações |
|-----------|--------|-----------|-------------|-------------|
| SMC Engine V2 | 164 | Alta | STABLE_FROZEN_V2 | 10 shadow tables, pipeline 10 steps |
| Study Gateway | 123 | Alta | PRONTO | TechnicalTruthEnvelopeV2, Confluence V2, Risk V2 |
| Opportunity Scanner | 306 | Alta | ATIVO | 10+ gates, dedup, HMAC notifier |
| Elliott Wave | Incluso em technical_engine | Média | Estável | 14 pivots, 9 legs, 4 sanity rules |
| Wyckoff | Incluso em technical_engine | Média | Estável | 4 fases, 8 eventos, 3 sanity rules |
| Dashboard Shadow | Incluso | Média | Operacional | FastAPI :8008 + Dash :8050 |
| Sync V2 | Via integration | Média | Funcional | HMAC, 1051 linhas, TRIGGER 4 |
| MaximusTrader API | 7 tests PHP | Baixa | Funcional | 43 endpoints, 24 models, 17 migrations |
| MaximusTrader Frontend | 0 tests | Zero | Bugs visuais | 37 arquivos TSX/TS |
| App Android | 0 tests | Zero | MVP funcional | 37 arquivos KT |

---

## 7. Próximos Passos Gerais

### Fase 1 — Auditoria e Organização (2-3 dias)

- [ ] Padronizar documentação — consolidar ~100+ docs em índice navegável
- [ ] Validar `.env.example` em todos os projetos
- [ ] Mapear todas as dependências e versões
- [ ] Mapear endpoints faltantes (documentar OpenAPI)
- [ ] Mapear schema completo do banco (VPS + Hostinger)
- [ ] Documentar fluxo de sincronização completo

### Fase 2 — Integração Sistema Local → MaximusTrader (3-5 dias)

- [ ] Tornar sync event-driven (TRIGGER 4 automático)
- [ ] Implementar heartbeat de sync (a cada 60s)
- [ ] Implementar retry com backoff exponencial
- [ ] Implementar checksum end-to-end
- [ ] Criar painel de saúde do sync no site
- [ ] Testar envio real de todos os tipos de dados
- [ ] Criar logs estruturados de sincronização

### Fase 3 — MaximusTrader Dashboard/API (5-7 dias)

- [ ] Consolidar gráficos — remover ApexCharts, padronizar lightweight-charts
- [ ] Corrigir bugs visuais pendentes (overlay SMC)
- [ ] Implementar testes frontend (Jest + React Testing Library)
- [ ] Implementar Error Boundary global
- [ ] Validar performance dos gráficos com dados reais
- [ ] Documentar API com OpenAPI/Swagger
- [ ] Criar painel de saúde do sistema (sync status, scanner status, uptime)

### Fase 4 — App Android (5-7 dias)

- [ ] Implementar Dashboard (cards resumo, saúde scanner, ticker tape)
- [ ] Implementar Histórico de Oportunidades (paginado, com filtros)
- [ ] Implementar Conta/Perfil
- [ ] Implementar preferências avançadas (quiet hours, radar states, max pushes)
- [ ] Preencher DTOs, Mappers, UseCases
- [ ] Criar testes unitários (ViewModels, Repositories)
- [ ] Testar fluxo E2E: login → notificação → deep link → detalhe
- [ ] Testar em dispositivo físico Android

### Fase 5 — Testes Integrados (3-4 dias)

- [ ] Testar fluxo completo: MT5 → VPS → Sync → Site → App
- [ ] Testar cenários de falha: rede offline, timeout, erro 500
- [ ] Testar sincronização atrasada (dados de 1h, 1d, 1 semana atrás)
- [ ] Testar push notifications: token refresh, múltiplos dispositivos, quiet hours
- [ ] Testar dashboard com 10+ ativos simultâneos
- [ ] Testar app em diferentes versões Android (API 24-35)

### Fase 6 — Deploy e Produção (2-3 dias)

- [ ] Configurar todos os serviços systemd com auto-restart
- [ ] Configurar logs rotativos (logrotate)
- [ ] Configurar monitoramento (UptimeRobot ou similar)
- [ ] Configurar backup automático diário (MySQL VPS + Hostinger)
- [ ] Criar runbook de operação (procedimentos de restart, recovery, rollback)
- [ ] Criar checklist de deploy para atualizações
- [ ] Validar Cloudflare (WAF, rate limits, cache, SSL)

---

## 8. Riscos Técnicos

| Risco | Severidade | Impacto | Como Resolver |
|-------|-----------|---------|---------------|
| Falta de contrato claro entre sistema local e site | **Alta** | Dados inconsistentes, perda de sincronização, bugs difíceis de rastrear | Documentar schema exato de cada endpoint, versionar API, validar com testes de contrato |
| Falta de autenticação segura entre serviços | **Alta** | Vazamento de dados, acesso não autorizado | HMAC já implementado — validar rotação de chaves, adicionar rate limiting |
| Falta de logs e rastreabilidade | **Alta** | Impossibilidade de debugar falhas em produção | Implementar structured logging (JSON), correlation IDs, centralizar logs |
| Falta de testes integrados | **Alta** | Regressões não detectadas, falhas em produção | Implementar E2E tests (Cypress/Playwright para web, instrumented tests para Android) |
| Dependência MT5 Wine | **Alta** | Ponto único de falha — sem MT5, sem dados | Monitorar saúde do MT5, backup data source, alertas de inatividade |
| Falta de validação dos dados sincronizados | **Média** | Dados corrompidos no site sem detecção | Checksum end-to-end, validação de schema no recebimento |
| Duplicidade de lógica entre local e web | **Média** | Manutenção cara, inconsistências | Centralizar cálculos no sistema local, site apenas exibe |
| Falta de testes no frontend e app | **Média** | Bugs em produção, regressões | Implementar Jest + React Testing Library (web), JUnit + Compose Testing (Android) |
| Falhas em notificações push | **Média** | Usuários não recebem alertas | Monitorar push_logs, retry em falhas FCM, fallback (in-app notifications) |
| Falhas em deploy 24/7 | **Média** | Sistema fora do ar, perda de dados | Implementar deploy com zero downtime, health checks, rollback automático |
| Documentação fragmentada | **Baixa** | Onboarding difícil, conhecimento perdido | Consolidar em índice estruturado, manter atualizado a cada fase |
| Múltiplas bibliotecas de gráfico | **Baixa** | Manutenção duplicada, bugs visuais | Consolidar em lightweight-charts, remover Plotly e ApexCharts |

---

## 9. Checklist Final

```md
- [x] App Android analisado — 37 arquivos KT, KMP + Compose, FCM integrado
- [x] MaximusTrader analisado — 43 endpoints, 24 models, 37 arquivos frontend
- [x] Sistema Local analisado — 414 testes, 34 módulos, 19 serviços systemd
- [x] Documentos internos verificados — ~100+ docs, 5 contratos, ~15 auditorias
- [x] Arquivos principais verificados — todos .py, .php, .tsx, .ts, .kt, .gradle, .json
- [x] Integrações mapeadas — HMAC VPS→Site, REST Site→App, FCM push
- [x] Pendências listadas — por parte e por severidade
- [x] Riscos listados — 12 riscos classificados
- [x] Próximos passos definidos — 6 fases, 30+ tarefas
- [x] Relatório salvo em docs_geral
```

---

## 10. Resumo Executivo

O SMC Trader System 7.0 é um ecossistema de trading algorítmico em estágio avançado de desenvolvimento, composto por 3 partes interdependentes:

1. **Sistema Local de Cálculo** (Python, VPS Linux) — A parte mais madura, com **2.522+ testes passando**, motores SMC V2, Elliott, Wyckoff, Study Gateway e Opportunity Scanner estáveis. Opera 24/7 coletando dados de 11 ativos via MT5, processando análises técnicas e sincronizando com o site. Possui guardrails rigorosos que impedem operação ao vivo (modo shadow).

2. **MaximusTrader** (Laravel + React, Hostinger) — Plataforma web com **43 endpoints de API**, sistema de planos comerciais, autenticação completa (Sanctum + 2FA), push notifications FCM, e dashboard de gráficos. O backend está robusto e bem estruturado. O frontend está em migração de bibliotecas de gráficos (3 coexistindo) e carece de testes.

3. **App Android** (Kotlin Multiplatform) — Aplicativo mobile em estágio MVP, com autenticação, lista de oportunidades, push notifications FCM e deep links funcionais. Telas de dashboard, histórico e conta ainda não implementadas. Sem testes.

**Prioridade imediata:** Consolidar a integração entre as partes (sync automático, monitoramento, testes integrados), finalizar as telas pendentes do app e unificar o sistema de gráficos do frontend.

**Risco principal:** A dependência do MT5 (Wine) como ponto único de falha para coleta de dados e a falta de monitoramento/alertas para falhas de sincronização.
