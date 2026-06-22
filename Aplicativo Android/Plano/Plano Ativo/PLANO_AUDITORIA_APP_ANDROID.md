# Plano: Auditoria e Melhorias do App Android (MaximusTrader)

## Visão Geral
App KMP (Kotlin Multiplatform) + Compose — atualmente só alvo Android. Arquitetura clean com DI (Koin), repositories, ViewModels, DataStore. Código geralmente bem organizado, mas com vários bugs, código morto, e gaps de UX.

---

## 🐛 Bugs Críticos

### B1. AccountScreen nunca mostra dados reais do usuário
**Arquivo:** `AccountViewModel.kt:41-45`
```kotlin
_userName = "Usuário",  // hardcoded
userEmail = "",          // vazio
```
O `UserDto` retornado no login tem name/email, mas nunca é persistido. A tela de Conta mostra sempre "Usuário" e email vazio.

**Fix:** Salvar `UserDto` no `TokenStorage` no login. No `AccountViewModel`, ler do storage e preencher nome/email reais. Adicionar `saveUser(user: UserDto)` e `getUser(): UserDto?` ao `TokenStorage`.

### B2. Foreground service não faz nada
**Arquivo:** `MaximusForegroundService.kt`
O serviço cria uma notificação persistente mas não tem nenhuma lógica de polling/monitoramento. É apenas uma notificação estática "Monitorando oportunidades" sem monitorar nada.

**Fix:** Duas opções — (a) remover o foreground service e confiar no FCM + Widget, ou (b) implementar polling real com WorkManager periódico (ex: a cada 5min buscar oportunidades ativas). Opção (a) é mais simples e eficiente.

### B3. `fmtInt()` perde decimais para Forex
**Arquivo:** `OpportunityCard.kt:141`
```kotlin
internal fun Double?.fmtInt(): String = this?.toLong()?.toString() ?: "--"
```
Para XAUUSD (2350.50), mostra "2350". Para BTCUSD, perde decimais. Forex precisa de formatação com decimais.

**Fix:** Criar `fmtPrice()` que detecta se o ativo é Forex (XAUUSD, BTCUSD, etc.) e formata com 2 casas decimais quando aplicável. Ou simplificar: mostrar com 2 casas sempre (2350.50, 123456.00).

### B4. 401 sem redirect para login
**Arquivo:** `ApiClient.kt`
Quando o token expira, recebe 401 mas trata como exception genérica. Usuário fica preso com tela de erro.

**Fix:** Adicionar interceptor no Ktor que detecta 401 → invalida token → navega para login via `DeepLinkEventBus` ou callback.

---

## 🗑️ Código Morto (remover)

### D1. `GetHistoryUseCase.kt`
Não registrado no DI, não usado em lugar nenhum. `HistoryViewModel` chama repository direto.

### D2. `DeviceDto.kt` (DeviceRegisterRequest / DeviceResponse)
Nunca usado. `DeviceRepositoryImpl` tem seus próprios DTOs internos (`DeviceRegistrationRequest`).

### D3. `OpportunityMapper.kt`
`fromMap()` nunca chamado. FCM usa `FcmOpportunityPayload` que é independente.

### D4. `OpportunityRemoteDataSource.kt`
Injetado no DI mas nunca referenciado. `OpportunityRepositoryImpl` faz chamadas API diretamente.

### D5. `PreferenceRemoteDataSource.kt`
Verificar se é realmente usado — `PreferencesRepositoryImpl` o recebe como nullable e injeta.

---

## 🎨 Melhorias de UX

### U1. Pull-to-refresh nas listas
`OpportunityListScreen` e `HistoryScreen` não têm pull-to-refresh. Usuário precisa pressionar "Tentar novamente" nos erros.

**Fix:** Envolver `LazyColumn` com `PullToRefreshBox` (Material3).

### U2. Refresh automático periódico
A tela de oportunidades carrega uma vez. Para um app de sinais, deveria atualizar periodicamente (ex: a cada 60s) ou ter botão manual de refresh no TopAppBar.

### U3. Formatação de preços para Forex
(B3 acima) — Preços de FOREX precisam de decimais.

### U4. Empty states com ícone/illustration
Telas de "nenhuma oportunidade" mostram só texto. Adicionar ícone ilustrativo.

---

## 🔧 Melhorias de Código

### C1. `formatDisplayTime` duplicado
Existe em `OpportunityCard.kt:144` e `DateTimeUtils.kt:12`. Unificar usando `DateTimeUtils.formatDisplayTime()`.

### C2. `DirectionPill` / `PillBadge` duplicado
`HistoryScreen.kt` tem `DirectionPill`, `OpportunityCard.kt` tem `PillBadge` — mesma coisa. Extrair para componente compartilhado.

### C3. Widget usa URL hardcoded
**Arquivo:** `WidgetRefreshWorker.kt:38`
```kotlin
"https://maximustrade.com.br/api/mobile/opportunities/active"
```
Deveria usar `AppConfig.API_BASE_URL`.

### C4. Widget cria HttpClient duplicado
`WidgetRefreshWorker` cria seu próprio `HttpClient` com config igual ao `ApiClient`. Poderia reutilizar.

### C5. Rate limiter não é thread-safe
**Arquivo:** `MaximusFirebaseMessagingService.kt:29`
`notificationTimestamps = mutableListOf<Long>()` — pode ter concurrent modification.

**Fix:** Usar `CopyOnWriteArrayList` ou `Mutex` do coroutines.

### C6. `Object` vs `data object` inconsistente
Alguns sealed class members usam `Object` (ex: `OpportunityDetailUiState.Loading`), outros `data object` (ex: `LoginUiState.Idle`). Padronizar para `data object`.

### C7. Wildcard imports
`LoginScreen.kt`, `HistoryScreen.kt` etc usam `.*`. Limpar para imports explícitos.

---

## 📋 Implementação Sugerida

### Fase 1 — Bugs críticos + código morto
1. B1: Salvar UserDto no login, AccountScreen mostra dados reais
2. B3: Criar `fmtPrice()` com suporte a decimais para Forex
3. B4: Interceptor 401 → redirect login
4. D1-D4: Remover código morto

### Fase 2 — Melhorias UX
5. U1: Pull-to-refresh nas listas
6. U2: Auto-refresh periódico (60s) nas oportunidades
7. U3: Empty states com ícones

### Fase 3 — Limpeza de código
8. C1: Unificar formatDisplayTime
9. C2: Extrair PillBadge compartilhado
10. C3: Widget usar AppConfig.API_BASE_URL
11. C5: Thread-safe rate limiter
12. C6: Padronizar data object

---

## Verificação
- `./gradlew assembleDebug` — build limpo
- Testar login → AccountScreen mostra nome/email reais
- Testar 401 → redireciona para login
- Testar preços Forex com decimais (XAUUSD: 2350.50, não 2350)
- Verificar pull-to-refresh nas listas
- Verificar widget com URL correta
