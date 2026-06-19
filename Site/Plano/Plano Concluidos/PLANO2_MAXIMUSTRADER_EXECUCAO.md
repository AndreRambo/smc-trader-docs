# PLANO 2 — MAXIMUSTRADER: EXECUÇÃO

**Data:** 16 de Junho de 2026
**Baseado em:** Plano 2 do dono do produto + Relatório Geral + Baseline Técnico + Análise direta dos arquivos
**Status:** Plano de execução detalhado — pronto para implementar

---

## 0. Diagnóstico Rápido: O Que Já Existe vs. O Que Falta

| Seção do Plano | Já Implementado? | Status | Ação Principal |
|---------------|-----------------|--------|---------------|
| 1. Papel principal (hub central) | ✅ Backend robusto, 43 endpoints | OK | Nenhuma estrutural |
| 2. Área administrativa + cliente | ⚠️ Admin funcional, área cliente inexistente | Criar área do cliente | Ver Seção 2 |
| 3. Gráfico espelhar Dashboard Shadow | ✅ `/api/state/{ticker}` agrega tudo | OK — verificar overlays | Ver Seção 3 |
| 4. IA sob demanda | ❌ Não existe | Planejar arquitetura | Ver Seção 4 |
| 5. Sem estudos automáticos | ✅ Não existem triggers por horário | OK | Nenhuma |
| 6. Créditos | ❌ Não existe | Planejar tabelas | Ver Seção 6 |
| 7. Tipos de estudo | ❌ Não implementado | Planejar contratos | Ver Seção 7 |
| 8. Fluxo de alerta p/ App | ⚠️ Funcional, falta data/hora no payload | Adicionar campos | Ver Seção 8 |
| 9. Data/hora nas notificações | ❌ Payload FCM não inclui `opportunity_time`/`sent_at` | Adicionar ao buildPayload | Ver Seção 9 |
| 10. Prioridades | ⚠️ P1 quase pronto, P2 iniciado (health), P3-P4 pendentes | Executar em ordem | Ver Seção 10 |

**Conclusão:** O backend está muito completo. Os gaps principais são: (1) incluir data/hora no payload FCM, (2) criar painel de saúde visual no admin, (3) consolidar gráficos, (4) planejar arquitetura de IA/estudos sob demanda, (5) criar área do cliente separada do admin.

---

## 1. Papel Principal — Alinhamento com o Plano

### O Que Já Está Implementado (Encontrado nos Arquivos)

| Responsabilidade | Evidência | Status |
|-----------------|-----------|--------|
| Receber dados do Sistema Local | 6 endpoints HMAC em `SyncController.php` + `ScannerAlertController.php` | ✅ |
| Salvar no banco web | 17 migrations, tabelas `sync_*`, `scanner_alerts`, `opportunities` | ✅ |
| Exibir gráficos e dashboards | `CandlestickChart.tsx` (508 linhas) + 8 módulos SMC overlay | ✅ |
| Administrar usuários/planos/permissoes | `AuthController`, `AdminController`, Spatie Permission, 24 models | ✅ |
| Enviar alertas p/ AppAndroid | `SendOpportunityPushNotification.php` (Job) + `FirebasePushService.php` (FCM HTTP v1) | ⚠️ Payload incompleto |
| Ponte motor técnico → cliente | API `/api/market/*` + `/api/mobile/*` + Webhooks de pagamento | ✅ |

### O Que O MaximusTrader NÃO Deve Fazer (Todos Confirmados)

| Não-Deve | Evidência | Status |
|----------|-----------|--------|
| Recalcular SMC/Elliott/Wyckoff | `MarketDataController` apenas lê tabelas `sync_*` — nunca calcula | ✅ |
| Decidir oportunidade | Scanner roda no Sistema Local; MaximusTrader só persiste e notifica | ✅ |
| Gerar sinal por conta própria | Sem código de análise técnica no backend | ✅ |
| Acessar MT5 diretamente | Sem conexão com MT5 no MaximusTrader | ✅ |
| Duplicar motor técnico | Lógica de cálculo 100% no Sistema Local | ✅ |

---

## 2. Área Administrativa vs. Área do Cliente

### Estado Atual

**Área Administrativa (existente):**

| Página | Rota | Status |
|--------|------|--------|
| Dashboard admin | `/admin/dashboard` | ✅ Funcional |
| Gráfico | `/admin/grafico` | ⚠️ 2 bibliotecas coexistindo |
| Watchlist | `/admin/watchlist` | ✅ Funcional |
| Replay | `/admin/replay` | ✅ Funcional |
| Alertas | `/admin/alertas` | ✅ Funcional |
| Indicadores | `/admin/indicadores` | ✅ Funcional |
| Planos | `/admin/planos` | ✅ CRUD funcional |
| Usuários | `/admin/usuarios` | ✅ CRUD funcional |
| Licenças | `/admin/licencas` | ✅ CRUD funcional |
| Vendas | `/admin/vendas` | ✅ Funcional |
| Produtos | `/admin/produtos` | ✅ CRUD funcional |
| Config | `/admin/config` | ✅ Funcional |
| **Saúde do sistema** | — | ❌ Não existe painel |

**Área do Cliente:**

| Funcionalidade | Status |
|---------------|--------|
| Consultar oportunidades | ✅ Via `/api/mobile/opportunities/active` (API) |
| Ver histórico | ✅ Endpoint existe (`/api/mobile/opportunities/history`), app consome |
| Abrir detalhes | ✅ Endpoint existe (`/api/mobile/opportunities/{id}`) |
| Área logada no site para trader | ❌ Não existe — site é só admin |
| Solicitar análise com IA | ❌ Não existe |
| Consumir créditos | ❌ Não existe |
| Ver estudos anteriores | ❌ Não existe |

### Gaps

| # | Gap | Prioridade |
|---|-----|-----------|
| G1 | Não existe painel de saúde visual no admin | P1 |
| G2 | Não existe área do cliente no site (hoje só app) | P3 |
| G3 | Dashboard admin não mostra métricas de sync/scanner/FCM | P1 |

### Ações

| Ação | Descrição | Prioridade |
|------|-----------|-----------|
| Criar painel de saúde | Cards no Dashboard admin: sync status, scanner status, FCM status, último heartbeat | P1 |
| Adicionar métricas ao Dashboard | Expandir `Dashboard.tsx` com dados do `GET /api/sync/health` | P1 |
| Planejar área do cliente | Seção "Meus Estudos" / "Minhas Oportunidades" — implementar após módulo de IA | P3 |

---

## 3. Gráfico Admin Deve Espelhar o Dashboard Local Shadow

### Estado Atual

**Endpoint `/api/state/{ticker}` (MarketDataController@state):**

Já agrega todos os dados em uma única chamada:

| Campo | Inclui | Status |
|-------|--------|--------|
| `candles` | OHLC + EMA20 + EMA200 + RSI + ATR | ✅ |
| `zones` | FVG, OB, BOS, CHOCH, Liquidity, SWING, SESSION, RETRACEMENT, PDH, PDL, BPR | ✅ |
| `elliott` | Waves com label, direção, tempos, preços | ✅ |
| `wyckoff` | Phases + Events | ✅ |
| `study` | Indicadores do estudo canônico | ✅ |
| `source` | `"SMC_ENGINE_V2_SYNCED"` — rastreável | ✅ |

**Fonte:** `"source": "SMC_ENGINE_V2_SYNCED"` — indica que os dados vieram do V2 pipeline sync.

**Zonas incluem:**
- `zone_type` — tipo (FVG, OB, BOS, etc.)
- `type` — classificação técnica
- `price_top` / `price_bottom` — coordenadas de preço
- `timeframe` — M5, M15, etc.
- `status` — active, mitigated
- `display_from` / `display_to` — coordenadas temporais
- `created_at_candle` — candle de origem
- `mitigated_at` — quando foi mitigada
- `payload` — metadados completos (JSON)

### O Que o Dashboard Local Shadow Mostra (VPS — FastAPI :8008)

O Dashboard Local Shadow mostra:
- Candles OHLC
- FVG (retângulos semi-transparentes verdes/vermelhos)
- Order Blocks (retângulos com qualidade score)
- BOS/CHOCH (linhas estruturais)
- Liquidity (níveis)
- BPR (zonas de balanço)
- Swings (máximas/mínimas)
- Sessões (London/NY/Asia/B3)
- Retracements
- PDH/PDL
- Elliott waves (linhas de tendência)
- Wyckoff fases e eventos

### O Que Já Chega ao MaximusTrader

Todos esses dados chegam via sync. O `MarketDataController@state` retorna TODOS eles. O gráfico `CandlestickChart.tsx` + módulos SMC overlay renderiza TODOS eles.

### Gaps de Renderização

| Overlay | Chega na API? | Renderiza no Chart? | Status |
|---------|--------------|-------------------|--------|
| FVG | ✅ zones com `type=FVG` | ✅ `SmcPaneRenderer` | OK |
| Order Blocks | ✅ zones com `type=ORDER_BLOCK` | ✅ `SmcPaneRenderer` | OK |
| BOS/CHOCH | ✅ zones com `type=BOS/CHOCH` | ✅ Linhas estruturais | OK |
| Liquidity | ✅ zones com `type=LIQUIDITY` | ⚠️ Verificar | Testar |
| BPR | ✅ zones com `type=BPR` | ⚠️ Verificar | Testar |
| Swings | ✅ zones com `type=SWING` | ⚠️ Verificar | Testar |
| Sessões | ✅ zones com `type=SESSION` | ⚠️ Verificar | Testar |
| PDH/PDL | ✅ zones com `type=PDH/PDL` | ⚠️ Verificar | Testar |
| Elliott | ✅ `/api/elliott/{ticker}` | ✅ `CandlestickChart.tsx` | OK |
| Wyckoff | ✅ `/api/wyckoff/{ticker}` | ✅ `CandlestickChart.tsx` | OK |
| Estudos | ✅ `/api/study/{ticker}` | ❌ Não renderiza | Gap |
| Scanner state | ❌ Não exposto na API pública | ❌ | Gap |

### Ações

| Ação | Arquivo(s) | Prioridade |
|------|-----------|-----------|
| Validar renderização de Liquidity, BPR, Swings, Sessions, PDH/PDL no chart | `CandlestickChart.tsx`, `smcNormalize.ts` | P1 |
| Adicionar endpoint de scanner state para o admin | Novo método em `MarketDataController` ou `AdminController` | P2 |
| Consolidar em lightweight-charts (remover ApexCharts, depreciar Plotly) | `package.json`, `CandlestickChart.tsx`, `PlotlyCandlestickChart.tsx` | P1 |

---

## 4. Módulo Futuro de Estudos com IA — Planejamento

### Fluxo Desejado (Plano)

```
Cliente logado → Escolhe ativo/timeframe → "Gerar estudo detalhado"
→ Verifica créditos → Busca dados técnicos sincronizados
→ Monta prompt seguro → IA gera estudo → Debita crédito
→ Salva no histórico do cliente
```

### O Que Já Existe no Sistema Local Que Alimenta Isso

| Componente | Arquivo | O Que Provê |
|-----------|---------|------------|
| StudyPayloadTechnicalTruthV2 | `study_gateway/models_v2.py` | Payload técnico completo para IA redigir |
| ProfessionalStudyRenderer | `study_gateway/professional_study_renderer.py` | Template markdown com campos narrativos e locked |
| ResponseGuard | `study_gateway/response_guard.py` | Valida resposta da IA contra verdade técnica |
| PromptBuilder | `study_gateway/prompt_builder.py` | Constrói prompt seguro para LLM |
| OpenRouterClient | `study_gateway/openrouter_client.py` | Cliente HTTP para LLM |

### O Que Precisa Ser Criado no MaximusTrader

| Componente | Descrição | Prioridade |
|-----------|-----------|-----------|
| `ai_credit_wallets` (migration) | Saldo de créditos por usuário | P4 |
| `ai_credit_transactions` (migration) | Histórico de consumo | P4 |
| `ai_study_requests` (migration) | Solicitações de estudo | P4 |
| `ai_study_results` (migration) | Resultados dos estudos | P4 |
| `StudyController` | Endpoint para solicitar estudo, verificar créditos, retornar resultado | P4 |
| `app/Services/AiStudyService.php` | Orquestrador: busca dados → monta prompt → chama LLM → salva resultado | P4 |
| Página "Meus Estudos" (frontend) | Área do cliente: lista de estudos, solicitar novo | P4 |
| Página "Estudo Detalhado" (frontend) | Visualização de um estudo gerado | P4 |

### O Que NÃO Fazer Agora

- ❌ Implementar regra final de precificação de créditos
- ❌ Integrar com gateway de pagamento para compra de créditos
- ❌ Gerar estudos automaticamente por horário
- ❌ Criar UI de estudos antes de ter o backend de IA funcional

### Ação Imediata

Criar as migrations das 4 tabelas (`ai_credit_wallets`, `ai_credit_transactions`, `ai_study_requests`, `ai_study_results`) como placeholder arquitetural. Sem lógica de negócio ainda — apenas estrutura.

---

## 5. Sem Estudos Automáticos Por Horário — Conformidade

### Estado Atual

**Verificado:** NÃO existem comandos agendados no Laravel (`routes/console.php`) que disparem estudos. NÃO existem triggers no `SyncController` que gerem estudos. O Sistema Local também não gera estudos automáticos (forward runner é ONCE/LOOP baseado em vela nova, não em horário).

**Conclusão:** ✅ O sistema já respeita esta regra. Nenhuma alteração necessária.

---

## 6. Sistema de Créditos — Planejamento Arquitetural

### Tabelas Propostas (Plano)

```
ai_credit_wallets        — saldo por usuário
ai_credit_transactions   — histórico de consumo
ai_study_requests        — solicitações
ai_study_results         — resultados
ai_study_templates       — templates de estudo
```

### Migration Placeholder (Criar Agora)

```php
// ai_credit_wallets
Schema::create('ai_credit_wallets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('balance')->default(0);
    $table->integer('lifetime_earned')->default(0);
    $table->integer('lifetime_spent')->default(0);
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});

// ai_study_requests
Schema::create('ai_study_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('symbol');
    $table->string('timeframe', 16);
    $table->string('study_type'); // opportunity, asset, timeframe, mtf
    $table->unsignedInteger('opportunity_id')->nullable();
    $table->unsignedInteger('credit_cost')->default(0);
    $table->string('status', 32)->default('pending'); // pending, processing, completed, failed
    $table->string('technical_truth_hash')->nullable();
    $table->timestamps();
});
```

### Ação

| Ação | Prioridade |
|------|-----------|
| Criar migration com as 4 tabelas (placeholder, sem lógica de negócio) | P4 |
| Não implementar controllers ou regras de crédito agora | — |

---

## 7. Tipos Futuros de Estudo — Contratos

### Definições Técnicas

| Tipo de Estudo | Dados Necessários | Custo Estimado (Créditos) | Prioridade |
|---------------|-------------------|--------------------------|-----------|
| **Oportunidade** | OpportunitySignalV1 + StudyPayloadTechnicalTruthV2 do scanner | 1 crédito | P3 |
| **Por Ativo** | Último StudyPayloadTechnicalTruthV2 do ativo | 2 créditos | P4 |
| **Por Timeframe** | Payload filtrado por timeframe específico | 1 crédito | P4 |
| **Multi-Timeframe** | MTF fusion H4/M15/M5 do ConfluenceV2 | 3 créditos | P4 |

### Contrato de API (Provisório)

```
POST /api/studies/request
  Body: { symbol, timeframe, study_type, opportunity_id? }
  Auth: Sanctum
  Response: { study_id, status: "queued", estimated_seconds: 30 }

GET /api/studies/{id}
  Auth: Sanctum
  Response: { study_id, status, result_markdown, requested_at, completed_at }

GET /api/studies/history
  Auth: Sanctum
  Response: { data: [...], total, page }
```

---

## 8. Fluxo de Alerta Para o AppAndroid

### Estado Atual

**Pipeline existente (Encontrado nos Arquivos):**

```
OpportunityScanner (VPS)
  → http_post_notifier.py → POST /api/scanner/alerts (HMAC)
  → ScannerAlertController@store → persiste scanner_alert + opportunity
  → Dispatch SendOpportunityPushNotification Job
  → Job verifica: preferências, quiet hours, rate limit, dedup
  → FirebasePushService::sendToDevice() → FCM HTTP v1
  → App Android recebe → MaximusFirebaseMessagingService → notificação local
```

**Filtros já implementados no Job:**

| Filtro | Implementação | Arquivo |
|--------|--------------|---------|
| Usuário ativo | `$user->is_active` | `SendOpportunityPushNotification.php:46` |
| Push enabled | `$prefs->push_enabled` | Linha 54 |
| Asset filter | `$prefs->shouldPushForAsset($symbol)` | Linha 61 |
| Proximity filter | `$prefs->shouldPushForProximity($proximity)` | Linha 68 |
| Quiet hours | `$prefs->isInQuietHours()` | Linha 75 |
| Max pushes/hour | `$prefs->hasExceededMaxPushes($count)` | Linha 87 |
| Dedup | `PushLog::byAlert($alert_id)->where('user_id', ...)` | Linha 94 |

### O Que Falta: Data/Hora no Payload FCM

**Payload atual (FirebasePushService.php:76-108):**

```php
'data' => [
    'alert_id' => ...,
    'opportunity_id' => ...,
    'symbol' => ...,
    'direction' => ...,
    'proximity' => ...,
    'deep_link' => ...,
],
```

**O que o plano pede:**

```json
{
  "opportunity_time": "2026-06-16T10:35:00-03:00",
  "sent_at": "2026-06-16T10:36:12-03:00"
}
```

### Ação

Adicionar `opportunity_time` e `sent_at` ao payload FCM no método `buildPayload()` do `FirebasePushService.php`.

---

## 9. Data e Hora nas Notificações — Correção do Payload

### Gaps

| Campo | Existe no Payload FCM? | Existe no Model `Opportunity`? | Ação |
|-------|----------------------|-------------------------------|------|
| `opportunity_time` | ❌ Não | ✅ `created_at` | Adicionar ao `buildPayload()` |
| `sent_at` | ❌ Não | — (momento do envio) | Adicionar `now()` ao `buildPayload()` |
| `timeframe` | ❌ Não | ✅ `timeframe` | Adicionar ao `buildPayload()` |
| `type` (tipo de alerta) | ❌ Não | — | Adicionar string fixa `"opportunity_alert"` |

### Correção

**Arquivo:** `backend/app/Services/FirebasePushService.php`

Adicionar ao array `data` do `buildPayload()`:

```php
'data' => [
    'type' => 'opportunity_alert',                          // NOVO
    'alert_id' => $opportunity->alert_id,
    'opportunity_id' => (string) $opportunity->id,
    'symbol' => $opportunity->symbol,
    'direction' => $opportunity->direction ?? '',
    'proximity' => $opportunity->proximity ?? '',
    'timeframe' => $opportunity->timeframe ?? 'M5',         // NOVO
    'opportunity_time' => $opportunity->created_at?->format('Y-m-d\TH:i:sP'),  // NOVO
    'sent_at' => now()->format('Y-m-d\TH:i:sP'),            // NOVO
    'deep_link' => 'maximustrade://opportunity/' . $opportunity->id,
],
```

### Ação

| Ação | Arquivo | Prioridade |
|------|---------|-----------|
| Adicionar 4 campos ao payload FCM | `FirebasePushService.php` | P1 |

---

## 10. Prioridades — Ordem de Execução

### Prioridade 1 (P1 — Bloqueante para MVP)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P1.1 | Incluir `opportunity_time`/`sent_at`/`timeframe`/`type` no payload FCM | `FirebasePushService.php` | ❌ Faltam 4 campos |
| P1.2 | Criar painel de saúde visual no admin | `Dashboard.tsx` — adicionar cards | ❌ Não existe |
| P1.3 | Consolidar lightweight-charts (remover ApexCharts) | `package.json`, imports | ⚠️ ApexCharts ainda presente |
| P1.4 | Validar renderização de todos os overlays SMC | `CandlestickChart.tsx` + módulos `smc/` | ⚠️ Parcial |
| P1.5 | Manter API HMAC estável — adicionar checksum SHA-256 | `VerifySyncHmac.php`, `sync_v2.py` | ✅ Já existe |

### Prioridade 2 (P2 — Beta Fechado)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P2.1 | Expor scanner state no admin (último scan, sinais gerados) | Novo endpoint em `AdminController` ou `MarketDataController` | ❌ Não existe |
| P2.2 | Expandir painel de saúde: status por ativo/timeframe | `Dashboard.tsx` + `GET /api/sync/health` já retorna métricas | ⚠️ Backend pronto, frontend não |
| P2.3 | Adicionar filtro de plano ao health (admin vê tudo, cliente vê seus ativos) | `EnforcePlanLimits.php` | ✅ Já existe |
| P2.4 | Melhorar logs de push (rastrear envio, falha, device) | `PushLog` model + painel admin | ⚠️ Log existe, painel não |

### Prioridade 3 (P3 — Produção)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P3.1 | Criar área do cliente no site (oportunidades, histórico) | Novas páginas em `frontend/src/pages/` | ❌ Não existe |
| P3.2 | Página de detalhe de oportunidade no site | Nova página | ❌ Só existe no app |
| P3.3 | Filtrar dados por plano na API | `EnforcePlanLimits` middleware | ✅ Já existe |

### Prioridade 4 (P4 — Futuro)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P4.1 | Criar migrations placeholder para créditos e estudos com IA | Novas migrations | ❌ Não existe |
| P4.2 | Criar `StudyController` (endpoints de solicitação) | Novo controller | ❌ Não existe |
| P4.3 | Criar `AiStudyService` (orquestrador) | Novo service | ❌ Não existe |
| P4.4 | Criar páginas de estudos no frontend | Novas páginas | ❌ Não existe |
| P4.5 | Integrar com LLM (via OpenRouter) | Novo service ou reuso do `study_gateway/openrouter_client.py` | ❌ Não existe |

---

## 11. Plano de Execução Consolidado

### Dia 1-2 — Payload FCM + Painel de Saúde (P1)

```
1. Adicionar opportunity_time, sent_at, timeframe, type ao buildPayload()
2. Criar AdminSystemHealth.tsx (cards: sync, heartbeat, scanner, FCM)
3. Integrar ao Dashboard.tsx existente
4. Testar push com payload completo
```

### Dia 3-4 — Consolidação de Gráficos (P1)

```
1. Remover ApexCharts do package.json
2. Depreciar PlotlyCandlestickChart.tsx (manter 30 dias como fallback)
3. Validar todos os overlays SMC com dados reais (WINFUT, 34k zonas)
4. Testar performance com 1500+ zonas
```

### Dia 5 — Scanner State no Admin (P2)

```
1. Criar endpoint GET /api/admin/scanner-state
2. Exibir no Dashboard admin: última scan, sinais ativos, sinais hoje
```

### Semana Seguinte — IA e Créditos (P4)

```
1. Criar 4 migrations placeholder
2. Desenhar contratos de API para estudos
3. NÃO implementar regras de negócio ainda
```

---

## 12. Arquivos a Modificar/Criar

| Arquivo | Ação | Prioridade |
|---------|------|-----------|
| `backend/app/Services/FirebasePushService.php` | MODIFICAR — adicionar 4 campos ao data[] | P1 |
| `frontend/src/pages/Dashboard.tsx` | MODIFICAR — adicionar cards de saúde | P1 |
| `frontend/src/pages/AdminSystemHealth.tsx` | NOVO — painel de saúde | P1 |
| `frontend/package.json` | MODIFICAR — remover ApexCharts | P1 |
| `frontend/src/components/PlotlyCandlestickChart.tsx` | MODIFICAR — adicionar comentário DEPRECATED | P1 |
| `frontend/src/components/CandlestickChart.tsx` | MODIFICAR — validar overlays | P1 |
| `backend/app/Http/Controllers/Api/AdminController.php` | MODIFICAR — adicionar scanner-state | P2 |
| `backend/database/migrations/2026_06_16_000002_create_ai_study_tables.php` | NOVO — placeholder IA | P4 |
| `backend/app/Http/Controllers/Api/StudyController.php` | NOVO — placeholder | P4 |

---

## 13. Checklist de Verificação

### Antes de Considerar o MaximusTrader Pronto

- [ ] Payload FCM inclui `opportunity_time`, `sent_at`, `timeframe`, `type`
- [ ] Painel de saúde visível no admin (sync, scanner, FCM, heartbeat)
- [ ] ApexCharts removido do bundle
- [ ] Gráfico renderiza todos os overlays SMC sem erros
- [ ] Elliott e Wyckoff visíveis no gráfico
- [ ] API `/api/state/{ticker}` retorna todos os dados necessários
- [ ] Health endpoint retornando `green`
- [ ] Push log rastreável
- [ ] Nenhum endpoint recalcula SMC/Elliott/Wyckoff
- [ ] Migrations de IA criadas como placeholder

---

*Documento gerado em 16 de Junho de 2026 com base no Plano 2 do dono do produto e análise direta dos arquivos do MaximusTrader.*
