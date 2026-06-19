# PLANO 3 — APP ANDROID: EXECUÇÃO

**Data:** 16 de Junho de 2026
**Baseado em:** Plano 3 do dono do produto + Relatório Geral + Baseline Técnico
**Status:** Execução P1 iniciada

---

## 0. Diagnóstico Rápido

| Seção do Plano | Já Implementado? | Status |
|---------------|-----------------|--------|
| 1. Papel principal (canal mobile) | ✅ Arquitetura correta | OK |
| 2. App simples, foco em alerta | ✅ Fluxo implementado | OK |
| 3. Notificação com data/hora | ❌ Não exibe | Gap crítico |
| 4. Payload FCM esperado | ⚠️ Backend envia, app não parseia | Gap |
| 5. Lista de oportunidades | ⚠️ Funcional, falta info de tempo | Gap |
| 6. Detalhe da oportunidade | ⚠️ Funcional, falta disclaimer e tempos | Gap |
| 7. IA no app | ✅ Não implementado (correto) | OK |
| 8. Preferências | ⚠️ Básico OK, avançado não | Gap |
| 9. Histórico | ❌ Diretório vazio | Gap |
| 10. Prioridades | P1 parcial | Ver abaixo |

---

## 1. Papel Principal — Conformidade

### O Que o App Deve Fazer (Plano) vs. Implementado

| Deve Fazer | Status | Arquivo |
|-----------|--------|---------|
| Receber notificações | ✅ | `MaximusFirebaseMessagingService.kt` |
| Mostrar oportunidades | ✅ | `OpportunityListScreen.kt` |
| Mostrar data/hora da notificação | ❌ | Não implementado |
| Abrir detalhes via deep link | ✅ | `AndroidManifest.xml` + `MainActivity.kt` |
| Mostrar contexto técnico resumido | ⚠️ | Parcial — sem disclaimer |
| Preferências básicas | ✅ | `PreferencesScreen.kt` |
| Ser rápido e simples | ✅ | KMP + Compose, Clean Architecture |

### O Que o App NÃO Deve Fazer — Todos Confirmados

| Não-Deve | Status |
|----------|--------|
| Calcular SMC/Elliott/Wyckoff | ✅ Não faz |
| Acessar banco direto | ✅ Usa API REST |
| Executar ordens | ✅ Não faz |
| Substituir o site para análises gráficas | ✅ App é complementar |

---

## 2. Gap Crítico: Data/Hora na Notificação

### Situação Atual

**Backend (FirebasePushService.php):** ✅ Já envia `opportunity_time`, `sent_at`, `timeframe`, `type` no payload FCM (corrigido P1.1).

**App (MaximusFirebaseMessagingService.kt):** ❌ O `onMessageReceived` recebe `message.data` (Map<String,String>) mas NÃO extrai os novos campos. Só extrai `title`, `body` e `deep_link`.

**App (OpportunityDto):** ❌ O modelo tem `created_at`, `updated_at` mas NÃO tem `sent_at`, `opportunity_time`, `type`.

**App (OpportunityDetailScreen):** ❌ Não exibe "Detectado em" nem "Notificado em".

### Correções Necessárias

**1. Criar `FcmOpportunityPayload` (data class):**

```kotlin
// NOVO: core/notifications/FcmOpportunityPayload.kt
data class FcmOpportunityPayload(
    val type: String?,
    val alertId: String?,
    val opportunityId: Long?,
    val symbol: String?,
    val direction: String?,
    val proximity: String?,
    val timeframe: String?,
    val opportunityTime: String?,
    val sentAt: String?,
    val title: String?,
    val body: String?,
    val deepLink: String?
) {
    companion object {
        fun fromMap(data: Map<String, String>): FcmOpportunityPayload {
            return FcmOpportunityPayload(
                type = data["type"],
                alertId = data["alert_id"],
                opportunityId = data["opportunity_id"]?.toLongOrNull(),
                symbol = data["symbol"],
                direction = data["direction"],
                proximity = data["proximity"],
                timeframe = data["timeframe"],
                opportunityTime = data["opportunity_time"],
                sentAt = data["sent_at"],
                title = data["title"],
                body = data["body"] ?: data["message"],
                deepLink = data["deep_link"]
            )
        }
    }
}
```

**2. Atualizar `OpportunityDto` (adicionar campos):**

```kotlin
// Adicionar ao OpportunityDto:
val opportunity_time: String? = null,   // da notificação FCM
val sent_at: String? = null,            // da notificação FCM  
val type: String? = null,               // "opportunity_alert"
val timeframe: String? = null,          // do payload
```

**3. Atualizar tela de detalhe (exibir tempos + disclaimer):**

```kotlin
// Bloco de tempo (antes do plano técnico):
if (opp.opportunity_time != null) {
    Text("Detectado em: ${formatDateTime(opp.opportunity_time)}")
}
if (opp.sent_at != null) {
    Text("Notificado em: ${formatDateTime(opp.sent_at)}")
}
if (opp.timeframe != null) {
    Text("Timeframe: ${opp.timeframe}")
}

// Disclaimer fixo (sempre visível):
Card(colors = CardDefaults.cardColors(containerColor = MaximusColors.BgWarning)) {
    Text(
        "Este alerta representa uma oportunidade técnica identificada " +
        "pelo scanner. Não é recomendação de compra ou venda. " +
        "A decisão de operar é exclusivamente sua.",
        style = MaterialTheme.typography.bodySmall,
        color = MaximusColors.TextMuted
    )
}
```

---

## 3. Itens Executados Agora (P1)

### P1.1 — Criar FcmOpportunityPayload

### P1.2 — Atualizar OpportunityDto com campos de tempo

### P1.3 — Atualizar tela de detalhe com tempos + disclaimer

### P1.4 — Atualizar AndroidNotificationService para passar dados FCM

---

## 4. Pendências Por Prioridade

### Prioridade 1 (MVP)

| # | Ação | Arquivos |
|---|------|----------|
| P1.1 | Criar `FcmOpportunityPayload` parser | Novo arquivo em `core/notifications/` |
| P1.2 | Adicionar `opportunity_time`, `sent_at`, `timeframe` ao `OpportunityDto` | `domain/model/OpportunityModels.kt` |
| P1.3 | Exibir data/hora na tela de detalhe | `features/opportunities/OpportunityDetailScreen.kt` |
| P1.4 | Exibir disclaimer fixo na tela de detalhe | `OpportunityDetailScreen.kt` |
| P1.5 | Passar dados FCM para a tela de detalhe | `firebase/AndroidNotificationService.kt` |

### Prioridade 2 (Beta)

| # | Ação | Arquivos |
|---|------|----------|
| P2.1 | Melhorar cards da lista com tempos e status | `features/opportunities/OpportunityCard.kt` |
| P2.2 | Exibir data/hora na lista | `OpportunityListScreen.kt` |
| P2.3 | Adicionar filtros (ativo, direção, status) | `OpportunityListViewModel.kt` |
| P2.4 | Implementar tela de Histórico | `features/history/` (vazio) |

### Prioridade 3 (Produção)

| # | Ação | Arquivos |
|---|------|----------|
| P3.1 | Implementar tela de Dashboard | `features/dashboard/` (vazio) |
| P3.2 | Implementar tela de Conta/Perfil | `features/account/` (vazio) |
| P3.3 | Preferências avançadas (quiet hours, radar states) | `features/preferences/` |
| P3.4 | Preencher DTOs, Mappers, UseCases | `data/dto/`, `data/mapper/`, `domain/usecase/` (vazios) |

---

## 5. Checklist

- [ ] App exibe "Detectado em" e "Notificado em" no detalhe
- [ ] Disclaimer de risco visível em todas as telas de oportunidade
- [ ] FCM payload parseia corretamente os novos campos
- [ ] Deep link funciona com os novos dados
- [ ] Histórico implementado
- [ ] Dashboard implementado
- [ ] Testes unitários (mínimo 10)

---

*Documento gerado em 16 de Junho de 2026.*
