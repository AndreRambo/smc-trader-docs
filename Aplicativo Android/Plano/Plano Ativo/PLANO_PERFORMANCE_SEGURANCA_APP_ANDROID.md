# Auditoria de Desempenho e Segurança — App Android

**Status:** Ativo | **Data:** 2026-06-22 | **Escopo:** Performance + Security

---

## Segurança

### S1. Rede sem certificate pinning
**Risco:** MITM (Man-in-the-Middle) — atacante pode interceptar tráfego HTTPS em redes comprometidas.
**Arquivo:** `ApiClient.kt`
**Fix:** Adicionar `ktor-network-tls` com certificate pinning para `maximustrade.com.br`. Alternativa mais simples: `networkSecurityConfig` no AndroidManifest com `certificatePins`.

### S2. Password fica em memória após login
**Risco:** Se o device for comprometido (root), password pode ser extraído da memória do processo.
**Arquivo:** `LoginScreen.kt:33` — `var password by remember { mutableStateOf("") }`
**Fix:** Usar `password = ""` no `onLoginSuccess` callback, ou usar `rememberSaveable` com `Saver` que limpa. No mínimo, garantir que password não sobrevive à navegação.

### S3. Token sem expiração automática
**Risco:** Token stale continua sendo enviado; 401 tratado como erro genérico → user preso.
**Arquivo:** `ApiClient.kt` — `cachedToken` nunca expira.
**Fix:** Adicionar interceptor Ktor que detecta 401 → `invalidateToken()` + navegar para login.

### S4. `AuthDto.kt` é dead code com modelos duplicados
**Risco:** Confusão — dois `LoginRequest`, dois `UserDto`. Se alguém importar o errado, quebra.
**Arquivo:** `data/dto/AuthDto.kt` — nunca importado em nenhum lugar.
**Fix:** Remover completamente.

### S5. Rate limiter não é thread-safe
**Risco:** Concurrent modification em `notificationTimestamps` quando múltiplas FCM chegam simultaneamente.
**Arquivo:** `MaximusFirebaseMessagingService.kt:29` — `mutableListOf<Long>()`
**Fix:** Usar `CopyOnWriteArrayList<Long>()` ou `Mutex`.

### S6. Widget usa URL hardcoded
**Risco:** Se `AppConfig.API_BASE_URL` mudar, widget continua apontando para URL antiga.
**Arquivo:** `WidgetRefreshWorker.kt:38`
**Fix:** Usar `AppConfig.API_BASE_URL + "mobile/opportunities/active"`.

### S7. `networkSecurityConfig` ausente
**Risco:** Android permite cleartext traffic por default em targets < 28. Mesmo com target 35, defense-in-depth.
**Arquivo:** `AndroidManifest.xml`
**Fix:** Criar `res/xml/network_security_config.xml` com `cleartextTrafficPermitted=false` e `certificatePins`.

### S8. Foreground service sem notificação customizada
**Risco:** Ícone genérico `android.R.drawable.ic_dialog_info` — poco profissional, pode confundir user.
**Arquivo:** `MaximusForegroundService.kt:42`
**Fix:** Criar ícone customizado ou usar o launcher icon.

---

## Desempenho

### P1. Chart image decoded sem cache
**Problema:** `ChartCard` decodifica base64 → bitmap em `LaunchedEffect`. Se a tela recompor, re-decode.
**Arquivo:** `OpportunityDetailScreen.kt:297-304`
**Fix:** Usar `remember(chart.thumbnail_base64)` para cachear o bitmap. Ou melhor: usar Coil para async load com disco cache.

### P2. BitmapFactory sem limite de tamanho
**Problema:** `BitmapFactory.decodeByteArray` sem `inSampleSize` — imagem gigante causa OOM.
**Arquivo:** `ImageDecoder.android.kt:9`
**Fix:** Adicionar `BitmapFactory.Options` com `inJustDecodeBounds=true` primeiro, depois calcular `inSampleSize` baseado no tamanho alvo (ex: 1080x1350 para mobile).

### P3. History sem paginação
**Problema:** `getHistory()` busca TODAS as oportunidades de uma vez. Para user ativo, pode ser 1000+.
**Arquivo:** `OpportunityRepositoryImpl.kt:47-55`
**Fix:** Adicionar paginação (page/per_page) e load-more no LazyColumn.

### P4. Dashboard re-fetch a cada navegação
**Problema:** `DashboardViewModel.loadDashboard()` roda no `init{}`. Cada volta ao dashboard re-busca.
**Arquivo:** `DashboardViewModel.kt:27`
**Fix:** Usar cache em memória (ex: 30s TTL) ou `stateIn` com `SharingStarted.WhileSubscribed`.

### P5. PreferencesRepository cache inicial null
**Problema:** `_cachedPrefs` inicia como `null`. `getPreferences()` usa `filterNotNull()` — perde a primeira emissão se sync não completou.
**Arquivo:** `PreferencesRepositoryImpl.kt:31-34`
**Fix:** Inicializar com `UserPreferences()` default em vez de null.

### P6. OpportunityDetailScreen usa verticalScroll
**Problema:** Lista longa de evidências pode causar jank com `verticalScroll` + muitos `DetailSection`.
**Arquivo:** `OpportunityDetailScreen.kt:122`
**Fix:** Converter para `LazyColumn` se evidências > 10 itens.

### P7. Koin ViewModels sem scoping adequado
**Problema:** `koinViewModel<DashboardViewModel>()` dentro do composable cria nova instância por recomposição.
**Arquivo:** `DashboardScreen.kt:35` (e todas as telas)
**Fix:** Usar `koinViewModel` com `viewModelStoreOwner` do NavBackStackEntry para compartilhar instâncias.

### P8. Sem biblioteca de imagem (Coil)
**Problema:** Decode manual de base64 sem caching, sem placeholder, sem error handling visual.
**Arquivo:** `ImageDecoder.kt` + `OpportunityDetailScreen.kt`
**Fix:** Adicionar Coil Compose (`io.coil-kt:coil-compose`) para async image loading com disco cache.

### P9. Widget RefreshWorker cria HttpClient próprio
**Problema:** Duplica config do ApiClient. Se uma mudar, a outra fica desatualizada.
**Arquivo:** `WidgetRefreshWorker.kt:94-106`
**Fix:** Extrair factory de HttpClient compartilhada, ou aceitar a duplicação (widget roda em processo separado, então compartilhar é difícil).

---

## Priorização

### Fase 1 — Segurança crítica
1. S4: Remover AuthDto.kt (dead code)
2. S5: Thread-safe rate limiter
3. S6: Widget usar AppConfig
4. S3: Interceptor 401

### Fase 2 — Desempenho alto impacto
5. P1: Cache de bitmap no ChartCard
6. P2: BitmapFactory com inSampleSize
7. P5: PreferencesRepository cache default
8. P8: Adicionar Coil (substitui decode manual)

### Fase 3 — Desempenho médio
9. P4: Dashboard cache 30s
10. P7: Koin ViewModel scoping
11. P3: History paginação

### Fase 4 — Segurança hardening
12. S1: Certificate pinning
13. S7: Network security config
14. S2: Password memory cleanup

---

## Verificação
- `./gradlew assembleDebug` — build limpo
- Login → password não permanece em state após navegação
- 401 → redireciona para login
- Widget → usa URL de AppConfig
- Chart image → decode com size limit, sem OOM
- History → paginação funcional
- Preferences → cache inicial não é null
