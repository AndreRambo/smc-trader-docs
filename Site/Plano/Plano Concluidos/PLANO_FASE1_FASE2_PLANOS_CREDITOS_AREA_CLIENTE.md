# Plano: Planos + Ativos + Créditos + Asaas + Área do Cliente (VPS ↔ Site ↔ App)

**Data:** 2026-06-18  
**Projeto:** MaximusTrader (Site + App) + SMC_Trader_System 7.0 (VPS)  
**Repositórios:** `/MaximusTrader` (site), `/AppAndroid/MaximusTrader` (app), `/SMC_Trader_System 7.0` (VPS)

> **Este documento é a fonte da verdade e é autossuficiente** — qualquer agente de IA (ou desenvolvedor) deve conseguir executá-lo lendo apenas este arquivo. Caminhos de arquivo, schemas, rotas, contratos entre VPS/Site/App e ordem de execução estão todos aqui. Ao implementar, seguir a **Ordem de Execução** ao final e marcar cada fase como concluída. Decisões já tomadas estão em "Decisões da Revisão" — não reabrir sem necessidade.

---

## Contexto

Quatro blocos de funcionalidade, mantendo **VPS, Site e App sempre conversando**:

**Fase 1 — Backend do Site** — Diferenciar o que cada plano entrega (ativos selecionáveis, análise IA, créditos, Razor SMC). Pagamento de planos via Hotmart (webhook) com **assinatura mensal/semestral/anual** e gestão de lifecycle (garantia 7 dias, cancelamento, inadimplência); créditos via Asaas (PIX/cartão). Push filtrado por plano + ativos escolhidos.

**Fase 2 — Área do Cliente (Frontend)** — Gráfico interativo (Elliott, Wyckoff, EMAs, marcações de oportunidade — sem SMC raw), painel lateral de análise IA, seleção de ativos, compra de créditos.

**Fase 3 — VPS (Python)** — Geração de análise IA sob demanda via fila/polling (a VPS só fala com o site por push/outbox, sem API inbound).

**Fase 4 — App Android** — Lista de ativos dinâmica vinda do plano do cliente (hoje hardcoded), com toggle de notificação por ativo.

---

## Decisões da Revisão (2026-06-18)

Após validar o plano contra o código real, foram tomadas as decisões abaixo e incorporadas:

1. **Análise IA = geração real na VPS (assíncrona, pode falhar).** O clique em "Gerar análise" dispara a geração de estudo/evidência no sistema SMC da VPS. O resultado chega como `OpportunityEvidenceBundle` (via `InternalEvidenceController` já existente). Se a geração falhar, o crédito é restaurado. → exige modelo `AiAnalysisRequest` para rastrear ciclo de vida + restore.
2. **Planos substituídos.** `PlansSeeder` cria `basico/intermediario/maximus_full` com `updateOrCreate` e **desativa** (`is_active=false`) os antigos `free/starter/pro/enterprise`. Fallback do `WebhookController::findPlan()` e `plan_map` de cada provider atualizados para os novos slugs.
3. **CPF e dados do cliente vêm do webhook de compra da Hotmart** (o cliente compra o plano na Hotmart). → **Nova Fase 1E** estende o `HotmartProvider` para capturar `document` (CPF), `phone`, endereço e persistir no usuário. Asaas passa a ser usado **apenas para compra de créditos** (não para venda de planos).
4. **VPS, Site e App precisam conversar** (validado nos 3 repositórios):
   - A **VPS** comunica com o site **somente por push/outbox** (não há API inbound na VPS). → a análise IA sob demanda usa **fila + polling** (Fase 3), não chamada direta site→VPS.
   - O **App Android** lista ativos **hardcoded** (`AccountType.kt`) e `accountType=FULL` fixo. → passar a consumir `GET /user/assets` (Fase 4).
   - **Tickers divergem** entre os sistemas (app `XAUUSDm`, site `GOLD (XAUUSD)`, VPS canônico). → **canonicalização obrigatória**: `config/assets.php`, `user_plan_assets.sync_asset_id` e o app devem usar o **mesmo ticker de `sync_assets.ticker`** (fonte da verdade, vinda do sync da VPS).
5. **Estudos IA são por cliente, com reaproveitamento de oportunidade**:
   - **Estudo de oportunidade**: o artefato é compartilhável (mesma entrada/stop/TPs p/ todos). 1º cliente gera na VPS; próximos que pedirem a **mesma oportunidade** **reaproveitam** o estudo pronto — gastam crédito, ganham acesso, **sem** rechamar a IA (instantâneo, mais barato).
   - **Estudo de timeframe sem oportunidade**: **individual** por cliente+timeframe; sempre gerado, sem reaproveitamento entre clientes.
   - **Visibilidade** sempre por cliente (só vê o que desbloqueou). Detalhado na Fase 1C.
6. **Assinaturas com ciclo (mensal / semestral / anual) + garantia de 7 dias** (detalhado na Fase 1E):
   - Cada plano tem **3 preços** (mensal, semestral, anual). O webhook Hotmart informa o ciclo; o site diferencia e calcula a expiração correta (`+1 mês` / `+6 meses` / `+1 ano`).
   - **Garantia 7 dias**: cancelamento/reembolso **dentro de 7 dias** (ou evento de reembolso/chargeback) → **bloqueio imediato** (licença revogada + tokens revogados).
   - **Cancelamento após a garantia** → **não renova**: licença permanece ativa **até `expires_at`**, depois expira.
   - **Falha de pagamento** (atraso/inadimplência) → **bloqueio** (licença suspensa) até regularizar.

### Correções de design aplicadas (vs plano original)

| # | Problema encontrado no código | Correção |
|---|---|---|
| 1 | `sync_assets` **não tem** campo de categoria (B3/Forex); só `ticker/name/code`, e o sync da VPS sobrescreve. | Classificador por **config** (`config/assets.php`) mapeando ticker→categoria. Não depende de coluna sobrescrita pelo sync. |
| 2 | `users` **não tem** CPF/telefone (Asaas exige `cpfCnpj` p/ PIX). | Migration adiciona `cpf`, `phone` em `users`; preenchidos pelo webhook Hotmart (Fase 1E). |
| 3 | Crédito grátis diário misturado no mesmo saldo dos pagos → grátis de um ativo gastaria em outro + acúmulo indevido. | **Separar**: saldo pago em `user_credits`; direito grátis diário via log `ai_analysis_requests` (por ativo/dia). **Remove** o command `credits:grant-daily`. |
| 4 | `CreditService::consume` sujeito a corrida (2 req simultâneas furam o saldo). | Consumo **atômico** com `DB::transaction` + `lockForUpdate`. |
| 5 | Crédito por compra sem idempotência amarrada ao webhook. | Reusar `idempotency_key` do `WebhookController`; branch Asaas concede crédito **sem** criar License. |
| 6 | `getActiveLicenseAttribute` não checa `expires_at`. | `getActivePlan()` valida `status='active'` **e** `expires_at > now()`. |
| 7 | `HotmartProvider::getAmount(float $payload)` — type hint errado, retorna sempre `0.0`. | Corrigir para `array` e extrair valor real do payload. |
| 8 | `active_license` já é `$appends` (1 query/serialização); somar `assets_locked` global pioraria N+1. | `assets_locked` calculado **só** na resposta do `UserAssetController`, não como append global. |
| 9 | Migrations novas colidiriam com `2026_06_18_000001_add_device_lock`. | Numerar a partir de `000002`. |
| 10 | Bands de Wyckoff / labels Elliott são não-triviais em `lightweight-charts`. | **Reusar** o padrão de overlay já existente em `components/chart/smc/`. |
| 11 | `SendOpportunityPushNotification` faz `UserDevice::active()->get()` (carrega **todos** os devices) e filtra em PHP. | Query parte só dos **usuários elegíveis** (plano ativo + ativo selecionado) — ver Fase 1B. |

---

## Planos de Assinatura

### Plano Básico
- Escolha de **até 2 ativos B3** + **até 2 ativos Forex**
- **Seleção bloqueada após confirmação** — ao salvar, exibe modal de aviso: "Após confirmar, os ativos não poderão ser alterados. Para trocar um ativo, entre em contato com o administrador." O cliente confirma ciente disso.
- Somente o **admin** pode trocar os ativos do cliente após o bloqueio (via painel admin)
- Recebe notificações push apenas para os ativos selecionados
- **No app Android**: a lista de ativos exibe **somente os ativos escolhidos** pelo cliente; o cliente pode ativar/desativar as notificações de cada ativo individualmente, mas não pode adicionar ou remover ativos
- **Análise IA completa disponível** — porém apenas com **créditos pagos** (sem crédito gratuito diário)
- **Sem** Operacional Razor SMC

### Plano Intermediário
- Escolha de **até 2 ativos B3** + **até 2 ativos Forex**
- Recebe notificações push para ativos selecionados
- **1 análise IA grátis por ativo por dia**
- Créditos extras compráveis (preço configurável pelo admin)
- Crédito restaurado automaticamente se a geração falhar
- **Sem** Operacional Razor SMC

### Plano Maximus Full
- **Todos os ativos** (B3 + Forex) sem limite de seleção
- Recebe notificações push para todos os ativos
- **1 análise IA grátis por ativo por dia**
- Créditos extras compráveis
- **Operacional Razor SMC** incluído

---

## Estado Atual (o que já existe)

| O que existe | Status |
|---|---|
| `Plan` model com `features` (JSON) + `max_assets` | ✅ pronto |
| `NotificationPreference` com `enabled_assets` (array) | ✅ pronto |
| `SyncStudy`, `SyncElliottWave`, `SyncWyckoffPhase/Event` | ✅ pronto |
| `Opportunity` com `entrada`, `stop`, `tp1–3` | ✅ pronto |
| `CustomerArea.tsx` com sidebar + rotas `/app/*` | ✅ pronto |
| `ChartPage.tsx` + `CandlestickChart` com overlays SMC | ✅ pronto |
| Webhook framework (11 providers) | ✅ pronto (Asaas ausente) |
| **Sistema de créditos** | ❌ ausente |
| **Seleção de ativos por usuário/plano** | ❌ ausente |
| **Asaas integration** | ❌ ausente |
| **Área do cliente: ativos, créditos, chart cliente** | ❌ ausente |

---

## Fluxo de Notificação de Oportunidades (verificado no código)

**Requisito**: a VPS encontra a oportunidade → envia ao site → o site descobre **quais clientes têm aquele ativo** → envia **só** para esses clientes (o cliente só recebe se tiver o ativo no plano).

**Fluxo-alvo (ponta a ponta):**
```
[VPS] opportunity_scanner detecta oportunidade
   │  http_post_notifier.py  (HMAC)
   ▼
POST https://maximustrade.com.br/api/scanner/alerts
   │
   ▼
[SITE] ScannerAlertController::store
   • idempotente por alert_id
   • cria/atualiza Opportunity (symbol, entrada, stop, tp1-3, proximity…)
   • dispatch SendOpportunityPushNotification(opportunity)
   │
   ▼
[SITE] Job SendOpportunityPushNotification
   • CAMADA 1 (direito do plano): seleciona apenas usuários com
       - plano ATIVO e não expirado
       - symbol presente em user_plan_assets   ← "só quem tem o ativo"
   • CAMADA 2 (preferência): enabled_assets + proximity + quiet hours + rate limit + dedup
   • envia FCM só aos devices elegíveis
   │
   ▼
[APP] recebe push só dos ativos do seu plano que mantém ligados
```

**Estado atual vs alvo:**
| Etapa | Hoje | Alvo |
|---|---|---|
| VPS → `/api/scanner/alerts` | ✅ funciona | mantém |
| Cria Opportunity + dispatch job | ✅ funciona | mantém |
| Filtro por **direito do plano** (`user_plan_assets` + plano ativo) | ❌ **não existe** | **adicionar** (Camada 1, Fase 1B) |
| Filtro por **preferência** (`enabled_assets`, proximidade, quiet hours) | ✅ funciona | mantém (Camada 2) |

> **Conclusão da verificação**: o transporte VPS→site→app já está correto e idempotente. **Falta apenas a Camada 1** (gate por plano + ativo selecionado), já especificada na Fase 1B. Sem ela, hoje um cliente poderia receber push de um ativo fora do seu plano.

---

## FASE 1 — Backend: Planos + Ativos + Créditos + Asaas

### 1A. Migrations — Planos com limites de ativos

**Migration** `2026_06_18_000002_add_plan_asset_limits.php` — adiciona em `plans`:
- `max_b3_assets` int nullable (null = ilimitado)
- `max_forex_assets` int nullable
- `free_ai_per_day` int default 0
- `price_semiannual` decimal(10,2) nullable  ← **ciclo semestral** (já existem `price_monthly`, `price_yearly`)

**Seed** `PlansSeeder.php` — usa `updateOrCreate` por `slug`, define os **3 preços por ciclo** e **desativa** os antigos:
```
basico:        max_b3=2,    max_forex=2,    free_ai=0, features={ai_analysis:true, razor_smc:false}, price_monthly/semiannual/yearly
intermediario: max_b3=2,    max_forex=2,    free_ai=1, features={ai_analysis:true, razor_smc:false}, price_monthly/semiannual/yearly
maximus_full:  max_b3=null, max_forex=null, free_ai=1, features={ai_analysis:true, razor_smc:true},  price_monthly/semiannual/yearly

// Ao final: Plan::whereIn('slug', ['free','starter','pro','enterprise'])->update(['is_active' => false]);
```
> Nota: **Básico tem `ai_analysis=true`** (análise disponível, mas só com crédito pago — `free_ai_per_day=0`).  
> Os valores exatos de preço por ciclo serão definidos pelo negócio; o seed deixa placeholders e o admin ajusta.

**Reconciliação com webhooks** (decisão #2):
- `WebhookController::findPlan()` — trocar fallback `slug='pro'` por `slug='basico'`
- `config/webhooks.php` — atualizar `plan_map` de cada provider habilitado para apontar aos novos slugs

Atualizar `Plan.php` com os novos campos em `$fillable` e `casts()`.

---

### 1B. Seleção de Ativos por Usuário

**Migration** `2026_06_18_000003_create_user_plan_assets.php`:
```sql
user_id        FK users        NOT NULL
sync_asset_id  FK sync_assets  NOT NULL
category       ENUM('b3','forex') NOT NULL
locked_at      timestamp nullable   -- preenchido ao confirmar seleção (Plano Básico)
locked_by      bigint nullable FK users  -- admin que fez eventual troca
timestamps
UNIQUE(user_id, sync_asset_id)
```

**Categorização B3/Forex** (correção #1) — `config/assets.php`:
```php
return [
    'categories' => [
        'b3'    => ['WINFUT', 'WDOFUT', 'PETR4', 'VALE3', /* ... */],
        'forex' => ['EURUSD', 'GBPUSD', 'XAUUSD', 'GOLD (XAUUSD)', /* ... */],
    ],
];
// Helper AssetCategoryResolver::categoryFor(string $ticker): ?string
```
Usado tanto na validação do limite (`store`) quanto no agrupamento da lista (`index`). Não depende de coluna em `sync_assets` (que o sync sobrescreve).

**Novo model** `UserPlanAsset` — BelongsTo(User), BelongsTo(SyncAsset)

**Novo controller** `UserAssetController`:
- `GET  /user/assets`          → lista ativos selecionados + limites do plano + flag `is_locked`
- `POST /user/assets`          → adiciona ativo (valida max_b3/max_forex; erro 423 se `assets_locked = true`)
- `DELETE /user/assets/{id}`   → remove ativo (erro 423 se `assets_locked = true`)
- `POST /user/assets/confirm`  → **confirma e bloqueia** a seleção atual (preenche `locked_at` em todos os registros do usuário; irreversível pelo próprio cliente)

**Admin endpoints** (adicionados a `AdminController` ou novo `AdminAssetController`):
- `GET  /admin/users/{id}/assets`           → lista ativos + status de bloqueio
- `POST /admin/users/{id}/assets`           → admin adiciona ativo para o cliente
- `DELETE /admin/users/{id}/assets/{assetId}` → admin remove ativo do cliente
- Ao modificar via admin: registra `locked_by = admin_id` + atualiza `locked_at`

**Regra de bloqueio** — método `assetsLocked()` (não append global):
```php
// Plano Básico: bloqueado se qualquer UserPlanAsset tiver locked_at preenchido
// Plano Intermediário/Full: nunca bloqueado (cliente pode alterar livremente)
public function assetsLocked(): bool {
    $plan = $this->getActivePlan();
    if (!$plan || $plan->slug !== 'basico') return false;
    return $this->planAssets()->whereNotNull('locked_at')->exists();
}
```
O `UserAssetController::index` inclui `is_locked` no JSON chamando este método.

**Métodos adicionados a `User.php`**:
- `planAssets(): HasMany` → UserPlanAsset
- `getActivePlan(): ?Plan` → via license `status='active'` **e** `expires_at > now()` (correção #6)
- `canAddAsset(string $category): bool` → compara count com limite do plano
- `assetsLocked(): bool` → método normal (não `$appends` global — correção #8); chamado só pelo `UserAssetController`

**Enforcement no push** (`SendOpportunityPushNotification.php`) — **duas camadas** (ver "Fluxo de Notificação" abaixo):

- **Camada 1 — Direito do plano (gate rígido):** o cliente só é elegível se tiver **plano ativo não expirado** *e* o `symbol` da oportunidade estiver em `user_plan_assets`. Se falhar, **nunca** envia.
- **Camada 2 — Preferência do cliente:** dentre os ativos a que tem direito, respeita o toggle `NotificationPreference.enabled_assets` + proximidade + quiet hours + rate limit (lógica já existente).

**Eficiência (correção #11):** trocar `UserDevice::active()->get()` (carrega todos) por uma query que já parte dos usuários elegíveis:
```php
// users com plano ativo + que selecionaram este symbol, com seus devices ativos
$devices = UserDevice::active()
    ->whereHas('user', fn($q) => $q
        ->whereHas('licenses', fn($l) => $l->where('status','active')->where('expires_at','>',now()))
        ->whereHas('planAssets', fn($a) => $a->whereHas('syncAsset', fn($s) => $s->where('ticker', $opportunity->symbol)))
    )->with('user')->get();
```
Depois aplica a Camada 2 por device. Reduz drasticamente o loop e garante o requisito "só recebe quem tem o ativo".

> **Ticker**: a comparação usa `opportunity->symbol` (canônico, vindo do scanner) contra `sync_assets.ticker`. Ver canonicalização (decisão #4).

**App Android** (`/home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid`):
- Endpoint `GET /user/assets` também é consumido pelo app mobile
- A lista de ativos no app exibe **apenas os ativos em `user_plan_assets`** do usuário (não todos os ativos disponíveis)
- Para cada ativo, o cliente pode **ativar/desativar notificações** individualmente (toggle que chama `PUT /mobile/preferences/assets`)
- O cliente **não vê** botão de adicionar/remover ativos — essa ação não existe no app
- Se `is_locked = true` (Plano Básico confirmado), o app exibe badge "Plano Básico" com info "Para trocar ativos, contate o suporte"

---

### 1C. Sistema de Créditos + Estudos IA (individuais por cliente, com reaproveitamento)

Separação de conceitos (correção #3): **saldo pago** (fungível, comprado) ≠ **direito grátis diário** (por ativo/dia, não acumula).

**Modelo de estudo (decisão #5 — propriedade/visibilidade por cliente):**

| Tipo de estudo | Chave | Geração | Reaproveitamento |
|---|---|---|---|
| **Oportunidade** (`kind=opportunity`) | `opportunity_id` (evento compartilhado) | 1ª vez gera na VPS | **Sim** — próximos clientes na **mesma oportunidade** reusam o bundle pronto: gastam crédito, ganham acesso, **sem** chamar a IA de novo (instantâneo) |
| **Timeframe sem oportunidade** (`kind=timeframe`) | `(user_id, ticker, timeframe)` | Sempre gera na VPS | **Não** — individual por cliente+timeframe |

> **Visibilidade sempre por cliente**: o cliente só enxerga estudos que ele desbloqueou (tem um `ai_analysis_request` `completed`). O *artefato* de oportunidade é fisicamente compartilhado (`OpportunityEvidenceBundle` por `opportunity_id`), mas o *acesso* é controlado pelos registros de request por usuário.

**Migration** `2026_06_18_000004_create_credit_system.php`:

```sql
-- Tabela user_credits:  saldo de créditos PAGOS
user_id   FK unique, balance int default 0, updated_at

-- Tabela credit_transactions:  livro-razão (apenas créditos pagos)
user_id                  FK
amount                   int          (positivo=ganho, negativo=consumo)
type                     ENUM('purchase','consume','restore','admin_grant')
description              string nullable
related_request_id       bigint nullable   -- FK ai_analysis_requests
created_at

-- Tabela ai_analysis_requests:  ciclo de vida + acesso por cliente + controle de grátis
user_id                  FK
kind                     ENUM('opportunity','timeframe')
opportunity_id           FK opportunities nullable   -- só p/ kind=opportunity
ticker                   string                       -- símbolo/ativo (sempre)
timeframe                string nullable              -- só p/ kind=timeframe
source                   ENUM('free','paid')          -- como foi pago
status                   ENUM('pending','processing','completed','failed')
evidence_bundle_id       bigint nullable              -- artefato do estudo (compart. p/ opportunity; próprio p/ timeframe)
reused_from_request_id   bigint nullable              -- se reaproveitou estudo existente
free_date                date nullable                -- dia do grátis (p/ unicidade); null se source=paid
claimed_at               timestamp nullable           -- lease do poller (Fase 3)
error_message            string nullable
created_at, updated_at
INDEX(user_id, ticker, created_at)        -- p/ contar grátis do dia por ativo + listar estudos do cliente
INDEX(opportunity_id, status)             -- p/ achar bundle existente reaproveitável
UNIQUE(user_id, opportunity_id)           -- 1 request por cliente por oportunidade (anti dupla-cobrança/corrida)
UNIQUE(user_id, ticker, free_date)        -- no máx. 1 grátis por ativo por dia (anti-corrida no DB)

-- Tabela credit_orders:  pedido de compra de crédito (anti-fraude de valor — ver Segurança)
user_id                  FK
provider                 string default 'asaas'
provider_payment_id      string nullable    -- payment_id retornado pelo Asaas
quantity                 int                -- créditos a conceder
expected_amount_cents    int                -- valor esperado (quantity × preço no momento da compra)
status                   ENUM('pending','paid','expired','failed') default 'pending'
created_at, updated_at
INDEX(provider_payment_id)
```

**Novos models**: `UserCredit`, `CreditTransaction`, `AiAnalysisRequest`

**Config** — preço do crédito em `Configuration` global (`user_id = null`, key=`credit_price_cents`). `Configuration` já suporta isso (campo `user_id` nullable).

**Novo service** `CreditService` (consumo **atômico** — correção #4):
```
getBalance(User): int
consumePaid(User, int $amount, string $desc, ?int $reqId): bool
    → DB::transaction + lockForUpdate; false se saldo < amount
restore(User, int $amount, string $desc, ?int $reqId): void
grant(User, int $amount, string $type, string $desc): void       // purchase/admin_grant
freeUsedTodayFor(User, string $ticker): int                       // conta ai_analysis_requests source=free hoje
```
> **Removido** o command `credits:grant-daily` — o direito grátis é calculado on-demand (sem pré-conceder saldo).

**Novo service** `AiAnalysisService` (decisão #1 + #5):

`requestOpportunity(User, Opportunity)` — tudo em **transação** (S9):
1. Valida `features.ai_analysis=true`
2. **Já tem acesso?** Se o usuário já tem request `completed`/`pending` p/ essa oportunidade → retorna (idempotente, não cobra). Violação do `UNIQUE(user_id, opportunity_id)` em corrida → tratar como "já tem acesso" (não cobra 2×).
3. Cobra: `freeUsedTodayFor(ticker) < plan.free_ai_per_day` → `source='free'` (grava `free_date`, protegido por `UNIQUE(user_id,ticker,free_date)` — S10); senão `consumePaid(1)` (atômico) → 402 se saldo 0
4. **Reaproveitamento**: existe `OpportunityEvidenceBundle` `completed` p/ essa `opportunity_id` (de qualquer cliente)?
   - **Sim** → cria `AiAnalysisRequest(status='completed', evidence_bundle_id=existente, reused_from_request_id=...)` na hora. **Sem VPS.** Instantâneo.
   - **Não** → cria `pending` + enfileira geração (Fase 3). Quando concluir, o bundle fica disponível e os próximos reaproveitam.

`requestTimeframe(User, ticker, timeframe)`:
1. Valida `features.ai_analysis=true`
2. Cobra (free/paid, idem acima)
3. **Sempre** cria `pending` + enfileira geração na VPS (individual; sem reaproveitamento entre clientes)
4. O bundle resultante pertence a esse cliente naquele timeframe

**Conclusão/falha** (via fila da Fase 3):
- `completed` → vincula `evidence_bundle_id`
- `failed` → `CreditService::restore()` **se** `source='paid'` (reaproveitamento nunca falha → nunca restaura)

**Novo controller** `AiAnalysisController`:
- `POST /user/ai-analysis` — body `{ opportunity_id }` **ou** `{ ticker, timeframe }` → 202 com `request_id` (ou 200 imediato se reaproveitado)
- `GET  /user/ai-analysis/{request_id}` → status + `evidence_bundle_id` + flag `reused`
- `GET  /user/studies`                  → lista de estudos que **este** cliente desbloqueou (requests `completed`)
- `GET  /user/credits`                   → saldo pago + histórico (`credit_transactions`)

**Novo controller** `AdminCreditController`:
- `GET  /admin/credits/config`         → preço por crédito (`Configuration` global)
- `PUT  /admin/credits/config`         → atualiza preço
- `POST /admin/users/{id}/credits`     → adiciona créditos manualmente (`grant` type=`admin_grant`)

---

### 1D. Integração Asaas — **somente compra de créditos**

> Venda de **planos** acontece na Hotmart (Fase 1E). Asaas cobre apenas a compra avulsa de créditos (PIX/cartão).

**Referência API**: https://docs.asaas.com/reference/comece-por-aqui

**Novo service** `AsaasApiClient`:
```
createOrFindCustomer(User $user): array     → POST /v3/customers (busca por CPF/email antes de criar)
createPayment(array $data): array           → POST /v3/payments
createSubscription(array $data): array      → POST /v3/subscriptions
getPixQrCode(string $paymentId): array      → GET /v3/payments/{id}/pixQrCode
getPaymentStatus(string $paymentId): array  → GET /v3/payments/{id}
```

Configuração via `.env`:
```
ASAAS_API_KEY=
ASAAS_API_URL=https://api.asaas.com   (ou sandbox: https://sandbox.asaas.com/api/v3)
ASAAS_WEBHOOK_TOKEN=
```

**Nova classe** `app/Services/Webhooks/AsaasProvider.php` (extends `AbstractProvider`):
- Validação de assinatura: header `asaas-access-token` comparado com `ASAAS_WEBHOOK_TOKEN`
- Evento relevante: `PAYMENT_RECEIVED` / `PAYMENT_CONFIRMED` → confirma compra de crédito

**Branch de crédito no `WebhookController`** (correção #5 + anti-fraude de valor):
- `resolveProvider()` — adicionar `'asaas' => new AsaasProvider(...)` no `match`
- `processEvent()` — pagamento de crédito: **não** criar License. Em vez de confiar na `description`:
  1. Busca `credit_orders` por `provider_payment_id` (criado no checkout).
  2. Valida que o **valor pago ≥ `expected_amount_cents`** (impede manipular quantidade/valor). Se divergir → loga e **não concede**.
  3. `CreditService::grant(user, order.quantity, 'purchase', ...)` + marca `order.status='paid'` + `Purchase` (metadata).
  - Idempotência: `idempotency_key` existente **+** `order.status` (só concede se ainda `pending`).

**Adicionar a `config/webhooks.php`**:
```php
'asaas' => [
    'enabled' => env('WEBHOOK_ASAAS_ENABLED', true),
    'secret'  => env('ASAAS_WEBHOOK_TOKEN', ''),
    'plan_map' => [],   // Asaas não vende planos
],
```

Adicionar `'asaas'` à lista `whereIn('provider', [...])` em `routes/api.php`.

**Novo controller** `AsaasCheckoutController`:
- `POST /checkout/credits` — cria cobrança PIX ou cartão
  - Valida `{ quantity: int (1..max), billing_type: 'PIX'|'CREDIT_CARD' }`
  - Lê o preço **do servidor** (`Configuration credit_price_cents`) — nunca do cliente
  - Cria `credit_orders(status=pending, quantity, expected_amount_cents)`
  - Cria/busca customer Asaas; cria payment; salva `provider_payment_id` na order
  - Retorna `{ payment_id, pix_code, pix_expiry, checkout_url }` conforme tipo
- `GET /checkout/credits/{payment_id}/status` — polling de status; valida que a order pertence ao usuário autenticado
- Throttle (ex.: `throttle:10,1`) para evitar criação abusiva de cobranças

---

### 1E. Webhook Hotmart — Dados do Cliente + Ciclo de Assinatura + Lifecycle

**Contexto**: o cliente compra o plano na Hotmart; o webhook traz nome, email, **CPF**, telefone, endereço, **ciclo de cobrança** (mensal/semestral/anual) e eventos de reembolso/cancelamento/inadimplência. Hoje o `HotmartProvider` extrai só email/name/transaction/plan, tem bug em `getAmount`, e o `WebhookController` renova licença com período **hardcoded**.

Ref: https://developers.hotmart.com/docs/pt-BR/2.0.0/webhook/purchase-webhook/

#### 1E.1 — Dados do cliente

**Migration** `2026_06_18_000005_add_customer_fields_to_users.php` — adiciona em `users`:
- `cpf` string nullable (somente dígitos), `phone` string nullable, `address` json nullable

**`HotmartProvider`** — adicionar extração (e corrigir `getAmount`):
```php
getAmount(array $payload): float              // corrigir type + ler data.purchase.price.value
getCustomerDocument(array $payload): ?string   // data.buyer.document
getCustomerPhone(array $payload): ?string      // data.buyer.checkout_phone / phone
getCustomerAddress(array $payload): ?array      // data.buyer.address.*
```

**`WebhookController::processEvent`** — ao criar/achar o `User`, preencher/atualizar `cpf`, `phone`, `address` (sem sobrescrever com null). Adicionar ao `$fillable` de `User`.

#### 1E.2 — Ciclo de assinatura (mensal / semestral / anual)

**Migration** `2026_06_18_000006_add_billing_cycle_to_subscriptions.php` — em `subscriptions`:
- alterar `interval` para aceitar `monthly | semiannual | annual` (string; doc do enum)

**Mapa de oferta → (plano, ciclo)** em `config/webhooks.php` (hotmart `offer_map`): cada oferta da Hotmart (9 = 3 planos × 3 ciclos) mapeia para `['slug' => ..., 'cycle' => 'monthly|semiannual|annual']`.

**`HotmartProvider::getBillingCycle(payload): string`** — resolve o ciclo via `offer_map` (offer code) ou periodicidade do payload. Default seguro: `monthly`.

**Helper de período** (ex.: `BillingCycle::expiresAt(string $cycle): Carbon`):
```
monthly    → now()->addMonth()
semiannual → now()->addMonths(6)
annual     → now()->addYear()
```

**`WebhookController::createOrRenewLicense`** — substituir `addMonth()/addYear()` hardcoded por `BillingCycle::expiresAt($cycle)`. `Subscription.interval` recebe o ciclo real; `current_period_end` = mesma data.

#### 1E.3 — Lifecycle: garantia 7 dias, cancelamento, inadimplência

**Config** `config/commercial.php`: `'guarantee_days' => 7`.

**Estados de `License.status`**: `active` → `suspended` (bloqueio temporário) → `revoked` (bloqueio definitivo) / `expired` (fim natural).

Estender o `AbstractProvider` (impl. no `HotmartProvider`):
```php
isCancellation(array $payload): bool      // SUBSCRIPTION_CANCELLATION
isPaymentFailure(array $payload): bool    // PURCHASE_DELAYED / status unpaid/past_due
```

**`WebhookController` — roteamento de eventos (ordem importa):**

| Evento Hotmart | Condição | Ação no site |
|---|---|---|
| `PURCHASE_REFUNDED` / `PURCHASE_CHARGEBACK` | reembolso/chargeback | **Bloqueio imediato**: `license.status='revoked'`, `user->tokens()->delete()`, purchase=`refunded/chargeback`, subscription=`cancelled` |
| `SUBSCRIPTION_CANCELLATION` | **dentro** de 7 dias (`now()-activated_at ≤ guarantee_days`) | **Bloqueio imediato** (mesmo do reembolso) |
| `SUBSCRIPTION_CANCELLATION` | **após** 7 dias | **Não renova**: `subscription.status='cancelled'`+`cancelled_at`; **licença permanece `active` até `expires_at`** |
| `PURCHASE_DELAYED` / inadimplência | falha de pagamento | **Bloqueio**: `license.status='suspended'`, `subscription.status='past_due'` (regulariza → volta a `active` no próximo `PURCHASE_APPROVED`) |
| `PURCHASE_APPROVED` / `PURCHASE_COMPLETE` | aprovação/renovação | cria/renova licença com `BillingCycle::expiresAt(cycle)`; `subscription.status='active'` |

> "Bloqueio" = licença não-`active`/expirada ⇒ `getActivePlan()` retorna null ⇒ **Camada 1 do push corta notificações** e a área do cliente mostra "sem plano ativo". No bloqueio **imediato** também se revogam os tokens Sanctum para encerrar a sessão do app na hora.

**Expiração natural** — command `licenses:expire-check` (agendado diário em `routes/console.php`/`Kernel`): marca `expired` as licenças `active` com `expires_at < now()` sem renovação. (Como `getActivePlan()` já valida `expires_at`, o acesso já para sozinho; o command é p/ consistência de status e relatórios.)

> Com tudo isso, quando o cliente comprar créditos no Asaas, o CPF já estará no perfil. Se faltar (compra fora da Hotmart), o checkout pede o CPF e salva.

---

## FASE 2 — Frontend: Área do Cliente

### 2A. Seleção de Ativos — `/app/ativos`

**Nova página** `AssetSelectionPage.tsx`:
- Divide `sync_assets` em dois grupos: **B3** e **Forex** (tabs ou seções)
- Checkbox toggle por ativo
- Badge de limite: "2 de 2 B3 selecionados" (básico/intermediário) | "Ilimitado" (full)
- Ao tentar exceder limite → toast de erro com mensagem de upsell

**Fluxo de bloqueio (Plano Básico)**:

Se `is_locked = false` (ainda não confirmado):
- Página normal com checkboxes ativos
- Botão **[Confirmar seleção]** visível após selecionar pelo menos 1 ativo
- Ao clicar em Confirmar → exibe modal de aviso:
  ```
  ┌──────────────────────────────────────────────┐
  │ ⚠️  Atenção — Esta ação é irreversível       │
  │──────────────────────────────────────────────│
  │ Após confirmar, os ativos selecionados não   │
  │ poderão ser alterados por você.              │
  │                                              │
  │ Para trocar um ativo no futuro, será         │
  │ necessário entrar em contato com o           │
  │ administrador, que avaliará a solicitação.   │
  │                                              │
  │ Ativos selecionados:                         │
  │   B3:   WINFUT, PETR4                        │
  │   Forex: EURUSD, XAUUSD                      │
  │                                              │
  │ [Cancelar]           [Confirmar e Bloquear]  │
  └──────────────────────────────────────────────┘
  ```
- Ao confirmar → `POST /user/assets/confirm` → ativos bloqueados

Se `is_locked = true` (já confirmado, Plano Básico):
- Lista de ativos **somente leitura** (sem checkboxes ou botão de adicionar)
- Banner laranja no topo:
  ```
  🔒 Ativos bloqueados — Plano Básico
  Para solicitar a troca de um ativo, entre em contato com o administrador.
  ```

**Intermediário/Full**: sem bloqueio, alterações livres a qualquer momento.

Requests: `GET /user/assets`, `POST /user/assets`, `DELETE /user/assets/{id}`, `POST /user/assets/confirm`  
Hook: `useUserAssets.ts`

Adicionar nav item **"Meus Ativos"** na sidebar de `CustomerArea.tsx`.

---

### 2B. Gráfico do Cliente — `/app/grafico`

**Nova página** `CustomerChartPage.tsx` (baseada em `ChartPage.tsx` mas sem SMC):

**Overlays VISÍVEIS para o cliente:**
| Overlay | Fonte de dados |
|---|---|
| Velas OHLCV | `GET /candles/{ticker}` |
| EMA 20 + EMA 200 | `SyncCandle.ema20`, `.ema200` |
| Elliott Waves | `GET /elliott/{ticker}` — rótulos W1–W5/WA–WC |
| Wyckoff Phases | `GET /wyckoff/{ticker}` — faixas sombreadas + rótulo |
| Wyckoff Events | `GET /wyckoff/{ticker}` — ícones SPRING, SOS, SOW, UPTHRUST |
| Marcações de oportunidade | Linhas horizontais: verde=entrada, vermelho=stop, amarelo=TPs |

**Overlays OCULTOS (não renderizar para cliente):**
- SMC Zones (FVG, OB, BOS/CHOCH, liquidez) — exclusivo para admin/interno

**Painel lateral direito** `StudyPanel.tsx` (largura 280px):
```
┌──────────────────────────────┐
│ Seletor ativo + timeframe    │
├──────────────────────────────┤
│ ANÁLISE IA                   │
│ Bias:        ALTISTA ↑       │
│ Confluência: 82%             │
│ Wyckoff:     ACUMULACAO      │
│ Readiness:   PRONTO          │
│ Regime:      BULL            │
│ Volatilidade: BAIXA          │
├──────────────────────────────┤
│ [Gerar nova análise]         │
│ Créditos: 3 disponíveis      │
├──────────────────────────────┤
│ ÚLTIMA OPORTUNIDADE          │
│ WINFUT  ↑  IMINENTE          │
│ Entrada: 136.800             │
│ Stop:    135.400             │
│ TP1:     138.200             │
│ TP2:     139.800             │
│ TP3:     141.500             │
└──────────────────────────────┘
```

**Hook** `useCustomerChart.ts`:
- Busca candles, elliott, wyckoff via `GET /candles|elliott|wyckoff/{ticker}`
- Busca study via `GET /study/{ticker}`
- Busca última oportunidade ativa via `GET /mobile/opportunities/active?symbol={ticker}&limit=1`

**Botão "Gerar análise" (assíncrono)** no `StudyPanel`:
- Há oportunidade ativa no ativo → `POST /user/ai-analysis {opportunity_id}`; senão → `POST /user/ai-analysis {ticker, timeframe}` (estudo do timeframe atual)
- Se a resposta vier **200 `reused`** (estudo de oportunidade já existia) → mostra o estudo **na hora** com selo "Estudo reaproveitado"
- Se vier **202 `pending`** → estado "Gerando…" + polling em `GET /user/ai-analysis/{request_id}` até `completed` (mostra estudo) ou `failed` (mensagem + crédito restaurado se pago)
- Mostra saldo de créditos e, se 402, CTA "Comprar créditos"
- Aba/lista **"Meus Estudos"** (`GET /user/studies`) — só os estudos que **este** cliente desbloqueou

**Componente** `CustomerChartOverlays.tsx` — **reusar o padrão de overlay já existente** em `components/chart/smc/` (correção #10), que já desenha zonas/áreas sobre `lightweight-charts`:
- EMAs → `LineSeries` (já há suporte a EMA20/200 no `CandlestickChart`)
- Elliott wave labels → markers/`setMarkers`
- Wyckoff phase bands → mesma técnica de área sombreada usada nas SMC zones (reaproveitar helper)
- Trade levels (entrada/stop/TPs) → `createPriceLine` com cores e rótulo de preço

> O `CandlestickChart` aceita props para ligar/desligar camadas — adicionar flag para **omitir SMC** e ligar Elliott/Wyckoff/trade-levels na versão cliente.

---

### 2C. Dashboard do Cliente — `/app/dashboard`

Atualizar o dashboard principal de `CustomerArea.tsx` com 4 cards informativos:

```
┌────────────────────────┐  ┌──────────────────┐
│  Plano Atual           │  │  Créditos        │
│  Intermediário ✓       │  │  Saldo: 5        │
│  Ciclo: Semestral      │  │  [Comprar]       │
│  Renova/expira: 18/12  │  └──────────────────┘
│  Status: Ativo         │  ┌──────────────────┐
│  [Ver planos]          │  │  Última Opport.  │
└────────────────────────┘  │  WINFUT ↑        │
┌────────────────────────┐  │  EN: 136.800     │
│  Meus Ativos           │  └──────────────────┘
│  WINFUT, EURUSD        │
│  [Gerenciar]           │
└────────────────────────┘
```

**Card "Plano Atual"** reflete o estado da assinatura (vindo de `License` + `Subscription`):
- **Ciclo**: mensal / semestral / anual (`subscription.interval`)
- **Renova/expira em**: `license.expires_at`
- **Status**:
  - `Ativo` — renovação automática on
  - `Ativo (cancelado — não renova)` — cancelado após garantia; acesso até `expires_at`
  - `Pagamento pendente` — licença `suspended` (inadimplência) → CTA "Regularizar na Hotmart"
  - `Bloqueado/Expirado` — sem acesso → CTA "Assinar"
- Dados expostos via `GET /me` (incluir `plan_slug`, `cycle`, `expires_at`, `subscription_status`, `assets_locked`).

---

### 2D. Compra de Créditos — `/app/creditos`

**Nova página** `CreditsPage.tsx`:

```
┌─────────────────────────────────────────────┐
│ ● Saldo atual: 3 créditos                   │
├─────────────────────────────────────────────┤
│ Comprar créditos                            │
│ Preço: R$ 2,90 / crédito (configurável)    │
│                                             │
│  [5 cred — R$ 14,50]                       │
│  [10 cred — R$ 29,00]  ← mais popular      │
│  [20 cred — R$ 58,00]                      │
│  Personalizado: [____]                      │
│                                             │
│  Forma de pagamento:                        │
│  ⊙ PIX   ○ Cartão de crédito              │
│                                             │
│  [Gerar cobrança]                           │
├─────────────────────────────────────────────┤
│  ┌─ PIX gerado (após confirmar) ──────────┐ │
│  │  [QR Code aqui]                        │ │
│  │  Vence em: 29:45                       │ │
│  │  [Copiar código PIX]                   │ │
│  │  Aguardando confirmação...  ●           │ │
│  └────────────────────────────────────────┘ │
├─────────────────────────────────────────────┤
│  Histórico de créditos (pagos)              │
│  +10  Compra PIX        18/06/2026 15:32   │
│   -1  Análise WINFUT    17/06/2026 09:14   │
│   +1  Restauro (falha)  17/06/2026 09:15   │
└─────────────────────────────────────────────┘
```
> O histórico mostra apenas o razão de créditos **pagos** (`credit_transactions`). Análises **grátis** não aparecem aqui (não tocam o saldo); aparecem só como uso em `ai_analysis_requests`.

**Hook** `useCredits.ts`:
- `GET /user/credits` → saldo pago + histórico
- `POST /checkout/credits` → gera cobrança
- `GET /checkout/credits/{id}/status` → polling a cada 5s
- Ao confirmar pagamento → atualiza saldo automaticamente

---

## FASE 3 — VPS (Python): Análise IA sob demanda via fila/polling

> **Por quê**: a VPS (`SMC_Trader_System 7.0`) só fala com o site por **push/outbox** — não expõe API inbound. Logo, o site não chama a VPS diretamente. A análise sob demanda usa uma **fila no site** que a VPS **drena por polling**, reusando o HMAC já existente (`infra/database._send_sync_request`) e o builder de evidência já existente (`technical_engine/opportunity_evidence/builder.py`).

### 3A. Site — endpoints internos da fila (HMAC, reusa `VerifyScannerHmac`)

Em `routes/api.php`, grupo `internal` (já protegido por HMAC):
- `GET  /internal/analysis-queue`            → lista `ai_analysis_requests` com `status=pending` (limit N)
- `POST /internal/analysis-queue/{id}/claim` → marca como `processing` (evita corrida entre polls)
- `POST /internal/analysis-queue/{id}/result`→ `{ status: completed|failed, evidence_bundle_id?, error? }`
  - `completed` → vincula bundle + marca request `completed`
  - `failed` → marca `failed` + dispara `CreditService::restore()` se `source=paid`

Novo controller `InternalAnalysisQueueController` (ou métodos no `InternalEvidenceController` existente).

### 3B. VPS — worker de polling

**Novo módulo** `technical_engine/opportunity_evidence/analysis_poller.py` + entrada em `start_bridges.sh` / `systemd`:
1. A cada X s, `GET /api/internal/analysis-queue` (HMAC) → pega pendências (só requests `pending` — **estudos reaproveitados não entram na fila**, já saem `completed` no site)
2. `POST .../claim` para travar o item
3. Gera o `OpportunityEvidenceBundleV1` com o **builder existente**:
   - `kind=opportunity` → estudo da oportunidade (`opportunity_id`)
   - `kind=timeframe` → estudo do `ticker`+`timeframe` (individual)
4. **Publica o bundle no site** pelo caminho de evidência já existente (`/api/internal/opportunity-evidence`, `/artifacts`, `/complete`)
5. `POST .../result` com `completed` + `evidence_bundle_id` (ou `failed` + `error`)

> Como o reaproveitamento de oportunidade resolve no próprio site (sem VPS), a fila só recebe a **1ª** solicitação de cada oportunidade + **todas** as de timeframe.

> **A confirmar**: se o push de evidência VPS→site (`POST /api/internal/opportunity-evidence*`) já está implementado no lado Python (há outbox `EvidenceOutbox` + tabela `..._evidence_outbox_shadow`). Se ainda não houver o "drenador" HTTP, ele entra aqui como parte da 3B.

### 3C. Contrato de tickers (canonicalização)

A `opportunity_id` referencia `opportunities.symbol` (vindo do scanner da VPS). Garantir que `config/assets.php` (site) classifique exatamente esses símbolos canônicos. Documentar a lista canônica única usada por VPS + site + app.

---

## FASE 4 — App Android: lista de ativos dinâmica do plano

> **Por quê**: hoje `AccountType.kt` tem ativos **hardcoded** e `accountType=FULL` fixo (com TODO no próprio código). O cliente deve ver **apenas os ativos do seu plano** e ligar/desligar notificação de cada um.

### 4A. Camada de dados

- **Novo** `data/remote/UserAssetRemoteDataSource.kt` → `GET /user/assets` (mesma base Sanctum do app)
- **Novo** `domain/model/UserAssetModels.kt` → `data class UserAsset(ticker, name, category)`, `PlanAssetsInfo(assets, isLocked, planSlug, limits)`
- **Novo** `domain/repository/UserAssetRepository.kt` + `data/repository/UserAssetRepositoryImpl.kt`
- Registrar no Koin (`core/di/Modules.kt`)

### 4B. UI / ViewModel

- `PreferencesViewModel.kt`: substituir `accountType.allowedAssets` (hardcoded) por carregamento via `UserAssetRepository`. Os toggles de notificação continuam gravando em `enabled_assets` (`PUT /mobile/preferences/assets`) — só que **a lista exibida** passa a ser a dos ativos do plano.
- `PreferencesScreen.kt`: renderizar a lista vinda do backend; se `isLocked` (Básico), exibir nota "Para trocar ativos, contate o suporte". Sem botão de adicionar/remover.
- `AccountType.kt`: **deprecar/remover** as listas hardcoded; manter só o necessário (ou remover de vez) e tirar o default hardcoded de `enabledAssets` em `PreferenceModels.kt`.

### 4C. Plano no `/me` (opcional, recomendado)

Incluir `plan_slug` + `assets_locked` na resposta de `GET /me` (site) para o app exibir o plano sem chamada extra. Caso contrário, o app deriva de `GET /user/assets`.

---

## Arquivos a criar/modificar

### Backend (`/MaximusTrader/backend/`)

| Arquivo | Operação |
|---|---|
| `database/migrations/2026_06_18_000002_add_plan_asset_limits.php` | NOVA |
| `database/migrations/2026_06_18_000003_create_user_plan_assets.php` | NOVA |
| `database/migrations/2026_06_18_000004_create_credit_system.php` | NOVA (`user_credits`, `credit_transactions`, `ai_analysis_requests` c/ uniques, `credit_orders`) |
| `database/migrations/2026_06_18_000005_add_customer_fields_to_users.php` | NOVA (`cpf`, `phone`, `address`) |
| `database/migrations/2026_06_18_000006_add_billing_cycle_to_subscriptions.php` | NOVA (`interval`: monthly/semiannual/annual) |
| `database/seeders/PlansSeeder.php` | NOVO (`updateOrCreate` + 3 preços/ciclo + desativa antigos) |
| `config/commercial.php` | NOVO (`guarantee_days=7`) |
| `app/Support/BillingCycle.php` | NOVO (helper ciclo → `expiresAt`) |
| `app/Console/Commands/ExpireLicenses.php` | NOVO (`licenses:expire-check`, agendado diário) |
| `app/Console/Commands/ReapStaleAnalysis.php` | NOVO (`ai-analysis:reap-stale`, ~5 min — falha requests presos + restaura crédito) |
| `app/Models/Subscription.php` | doc do `interval` (monthly/semiannual/annual) |
| `config/assets.php` | NOVO (mapa B3/Forex) + `AssetCategoryResolver` |
| `app/Models/UserPlanAsset.php` | NOVO |
| `app/Models/UserCredit.php` | NOVO |
| `app/Models/CreditTransaction.php` | NOVO |
| `app/Models/AiAnalysisRequest.php` | NOVO |
| `app/Models/CreditOrder.php` | NOVO (pedido de compra de crédito — anti-fraude de valor) |
| `app/Models/Plan.php` | + `max_b3_assets`, `max_forex_assets`, `free_ai_per_day`, `price_semiannual` |
| `app/Models/User.php` | + `planAssets()`, `credit()`, `getActivePlan()` (c/ expiry), `canAddAsset()`, `assetsLocked()`, `cpf/phone/address` no fillable |
| `app/Services/CreditService.php` | NOVO (consumo atômico) |
| `app/Services/AiAnalysisService.php` | NOVO (geração VPS + restore) |
| `app/Services/AsaasApiClient.php` | NOVO |
| `app/Services/Webhooks/AsaasProvider.php` | NOVO |
| `app/Services/Webhooks/HotmartProvider.php` | + `getAmount` (fix) + document/phone/address + `getBillingCycle` + `isCancellation` + `isPaymentFailure` |
| `app/Services/Webhooks/AbstractProvider.php` | + assinaturas `getBillingCycle`/`isCancellation`/`isPaymentFailure` (defaults no GenericProvider) |
| `app/Http/Controllers/Api/WebhookController.php` | + branch Asaas (crédito, sem License) + dados cliente + **roteamento lifecycle** (garantia 7d, cancelamento, inadimplência) + `createOrRenewLicense` por ciclo |
| `app/Http/Controllers/Api/UserAssetController.php` | NOVO (inclui `confirm` + admin endpoints) |
| `app/Http/Controllers/Api/AiAnalysisController.php` | NOVO |
| `app/Http/Controllers/Api/AsaasCheckoutController.php` | NOVO |
| `app/Http/Controllers/Api/AdminCreditController.php` | NOVO |
| `app/Jobs/RequestVpsAnalysis.php` | NOVO (enfileira request → fila drenada pela VPS) |
| `app/Jobs/SendOpportunityPushNotification.php` | + filtro por `user_plan_assets` + plano ativo |
| `app/Http/Controllers/Api/InternalAnalysisQueueController.php` | NOVO (fila p/ VPS: list/claim/result) |
| `app/Http/Controllers/Api/AuthController.php` | + `/me`: `plan_slug`, `cycle`, `expires_at`, `subscription_status`, `assets_locked` (Fase 4C + dashboard 2C) |
| `config/webhooks.php` | + entrada Asaas + `plan_map` dos novos slugs + `offer_map` Hotmart (9 ofertas → plano+ciclo) |
| `routes/api.php` | + rotas user/assets, user/credits, user/ai-analysis, checkout, admin, internal/analysis-queue; + `asaas` no whereIn |

### VPS (`/SMC_Trader_System 7.0/`)

| Arquivo | Operação |
|---|---|
| `technical_engine/opportunity_evidence/analysis_poller.py` | NOVO (drena fila do site, gera bundle, devolve resultado) |
| `start_bridges.sh` / `systemd/` | + iniciar o poller como serviço |
| (push de evidência VPS→site) | confirmar/wirar drenador HTTP do `EvidenceOutbox` se ainda não existir |

### App Android (`/AppAndroid/MaximusTrader/`)

| Arquivo | Operação |
|---|---|
| `data/remote/UserAssetRemoteDataSource.kt` | NOVO (`GET /user/assets`) |
| `domain/model/UserAssetModels.kt` | NOVO |
| `domain/repository/UserAssetRepository.kt` | NOVO |
| `data/repository/UserAssetRepositoryImpl.kt` | NOVO |
| `core/di/Modules.kt` | + registrar UserAssetRepository |
| `features/preferences/PreferencesViewModel.kt` | lista de ativos vem do backend (não do enum) |
| `features/preferences/PreferencesScreen.kt` | renderiza lista dinâmica + nota de bloqueio |
| `domain/model/AccountType.kt` | deprecar/remover listas hardcoded |
| `domain/model/PreferenceModels.kt` | remover default hardcoded de `enabledAssets` |

### Frontend (`/MaximusTrader/frontend/src/`)

| Arquivo | Operação |
|---|---|
| `pages/AssetSelectionPage.tsx` | NOVO |
| `pages/CustomerChartPage.tsx` | NOVO |
| `pages/CreditsPage.tsx` | NOVO |
| `components/chart/CustomerChartOverlays.tsx` | NOVO |
| `components/StudyPanel.tsx` | NOVO |
| `hooks/useCredits.ts` | NOVO |
| `hooks/useUserAssets.ts` | NOVO |
| `hooks/useCustomerChart.ts` | NOVO |
| `pages/CustomerArea.tsx` | + sidebar (Meus Ativos, Gráfico, Créditos) + dashboard cards |
| `lib/api.ts` | + chamadas para novos endpoints |
| `App.tsx` | + rotas `/app/ativos`, `/app/grafico`, `/app/creditos` |

---

## Rotas novas a adicionar

### Backend (`routes/api.php`)

```php
// Ativos selecionados pelo usuário
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('/assets',             [UserAssetController::class, 'index']);
    Route::post('/assets',            [UserAssetController::class, 'store']);
    Route::delete('/assets/{id}',     [UserAssetController::class, 'destroy']);
    Route::post('/assets/confirm',    [UserAssetController::class, 'confirm']);   // bloqueia seleção (Plano Básico)

    Route::get('/credits',                   [AiAnalysisController::class, 'balance']);
    Route::post('/ai-analysis',              [AiAnalysisController::class, 'generate']);   // body {opportunity_id} | {ticker,timeframe} → 202 (ou 200 se reaproveitado)
    Route::get('/ai-analysis/{requestId}',   [AiAnalysisController::class, 'status']);     // polling do request
    Route::get('/studies',                   [AiAnalysisController::class, 'studies']);    // estudos desbloqueados por ESTE cliente
});

// Checkout Asaas
Route::middleware('auth:sanctum')->prefix('checkout')->group(function () {
    Route::post('/credits',                           [AsaasCheckoutController::class, 'createCreditPayment']);
    Route::get('/credits/{payment_id}/status',        [AsaasCheckoutController::class, 'status']);
});

// Admin — créditos + gestão de ativos de clientes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/credits/config',                      [AdminCreditController::class, 'config']);
    Route::put('/credits/config',                      [AdminCreditController::class, 'updateConfig']);
    Route::post('/users/{id}/credits',                 [AdminCreditController::class, 'grantCredits']);

    Route::get('/users/{id}/assets',                   [UserAssetController::class, 'adminIndex']);
    Route::post('/users/{id}/assets',                  [UserAssetController::class, 'adminStore']);
    Route::delete('/users/{id}/assets/{assetId}',      [UserAssetController::class, 'adminDestroy']);
});

// Fila de análise IA — drenada pela VPS (HMAC, dentro do grupo internal existente)
Route::middleware([\App\Http\Middleware\VerifyScannerHmac::class])->prefix('internal')->group(function () {
    Route::get('/analysis-queue',                 [InternalAnalysisQueueController::class, 'pending']);
    Route::post('/analysis-queue/{id}/claim',     [InternalAnalysisQueueController::class, 'claim']);
    Route::post('/analysis-queue/{id}/result',    [InternalAnalysisQueueController::class, 'result']);
});
```

---

## Segurança e Robustez (revisão geral — obrigatório implementar)

### Autorização / IDOR
- **S1.** `GET /user/ai-analysis/{requestId}`, `GET /user/studies` e o acesso ao `evidence_bundle` **devem** filtrar por `user_id = auth()->id()`. Um cliente nunca pode ler estudo/evidência de outro (visibilidade é por cliente). Retornar 404 (não 403) p/ não vazar existência.
- **S2.** `DELETE /user/assets/{id}` e `confirm` validam que a linha pertence ao usuário autenticado.
- **S3.** Endpoints admin (`role:admin`) que mexem em crédito/ativos → registrar em `AuditLog` (quem, quando, antes/depois).

### Dinheiro / créditos (anti-fraude)
- **S4.** Preço do crédito é **sempre** lido do servidor (`Configuration`), nunca do payload do cliente.
- **S5.** Concessão de crédito por compra valida `valor pago ≥ expected_amount_cents` da `credit_orders` (não confia em `description`). Ver Fase 1D.
- **S6.** Idempotência de concessão: `idempotency_key` (webhook) **+** `credit_orders.status` (só concede se `pending`). Reenvio não duplica.
- **S7.** Consumo de crédito **atômico** (`lockForUpdate`); nunca deixar saldo negativo.
- **S8.** Restauro de crédito é **idempotente** e só ocorre na transição `pending|processing → failed` de um request `source=paid` (guardas de estado evitam restaurar 2×).

### Concorrência / corrida
- **S9.** `UNIQUE(user_id, opportunity_id)` em `ai_analysis_requests` evita dupla-cobrança em cliques simultâneos na mesma oportunidade; o `request()` roda em transação e trata violação de unicidade como "já tem acesso".
- **S10.** `UNIQUE(user_id, ticker, free_date)` garante **no máx. 1 grátis/ativo/dia** mesmo com requisições concorrentes. "Dia" = fuso `America/Sao_Paulo` (definir em config).

### Webhooks (Hotmart/Asaas)
- **S11.** Comparações de assinatura/token **timing-safe** (`hash_equals`). Em produção, **falhar fechado** se segredo ausente (hoje o Hotmart libera fora de produção — manter, mas garantir bloqueio em prod).
- **S12.** Eventos podem chegar **fora de ordem / atrasados**: guardar transições de licença. Um `PURCHASE_APPROVED` atrasado **não** deve reativar licença `revoked` por reembolso (comparar timestamp do evento vs `activated_at`/última transição).
- **S13.** Escrita de License+Subscription+Purchase do webhook dentro de **uma transação** (evita estado parcial).
- **S14.** Payloads de webhook já são redatados (`sanitizePayload`) — garantir que CPF/cartão **nunca** vão para log; **nunca** logar `ASAAS_API_KEY`.

### Fila de análise (Fase 3)
- **S15.** `POST /internal/analysis-queue/{id}/result` **idempotente**: só age se o request estiver `pending|processing`; ignora se já `completed|failed`.
- **S16.** **Reaper de stale** — command `ai-analysis:reap-stale` (agendado, ex. a cada 5 min): requests `processing`/`pending` há mais de N min (lease via `claimed_at`) → marca `failed` + restaura crédito pago. **Evita travar o dinheiro do cliente** se o poller cair.
- **S17.** `claim` usa `claimed_at` como lease; item só é re-claimável após expirar o lease.

### Rate limiting / validação de entrada
- **S18.** Throttle em `POST /user/ai-analysis` (ex. `throttle:30,1`) e `POST /checkout/credits` (`throttle:10,1`).
- **S19.** Validar `ticker` contra `sync_assets` e `timeframe` contra allowlist; `quantity` inteiro positivo com teto. `category` derivada no servidor (não confiar no cliente).
- **S20.** `POST /user/assets` valida limite **no servidor** por categoria (B3/Forex) — o front é só conveniência.

### LGPD / dados pessoais
- **S21.** `cpf`, `phone`, `address` em `$hidden` no `User` (não vazar em `/me` nem em respostas de listagem). Se precisar exibir, **mascarar** (ex. `***.***.**9-00`).
- **S22.** Acesso a PII restrito ao próprio cliente e a admin; documentar retenção/finalidade (compra/cobrança).

### Compatibilidade / não quebrar o que existe
- **S23.** Reconciliar `EnforcePlanLimits` (middleware existente) e qualquer uso de `Plan.max_assets`/`features` antigos com os novos slugs/planos — rodar a suíte de testes do backend após a troca de planos.
- **S24.** Manter `active_license` funcionando (já é `$appends`); novas queries usam eager loading p/ evitar N+1 no push e no `/me`.

### Operação / segredos
- **S25.** Variáveis novas no `.env` (produção): `ASAAS_API_KEY`, `ASAAS_API_URL`, `ASAAS_WEBHOOK_TOKEN`, `WEBHOOK_HOTMART_SECRET/TOKEN`, `offer_map` configurado. Checklist antes do deploy.
- **S26.** Agendar no scheduler: `licenses:expire-check` (diário) e `ai-analysis:reap-stale` (5 min).

---

## Verificação

1. `php artisan migrate` — sem erros, **5** novas migrations aplicadas (000002–000006)
2. `php artisan db:seed --class=PlansSeeder` — 3 planos novos ativos (c/ 3 preços por ciclo), antigos `is_active=false`
3. `npm run build` — sem erros TypeScript
4. Webhook Hotmart simulado — lifecycle completo:
   - `PURCHASE_APPROVED` **mensal** → License `expires_at = +1 mês`, subscription `interval=monthly`; usuário com `cpf/phone/address`
   - `PURCHASE_APPROVED` **semestral/anual** → `expires_at = +6 meses / +1 ano` (ciclo correto)
   - `PURCHASE_REFUNDED` → License `revoked` **na hora** + tokens revogados (cliente bloqueado)
   - `SUBSCRIPTION_CANCELLATION` **dentro de 7 dias** → bloqueio imediato
   - `SUBSCRIPTION_CANCELLATION` **após 7 dias** → subscription `cancelled`, License segue `active` até `expires_at` (sem renovar)
   - `PURCHASE_DELAYED`/inadimplência → License `suspended` (bloqueado); novo `PURCHASE_APPROVED` → volta `active`
   - `licenses:expire-check` → licença vencida vira `expired`
5. Fluxo manual completo:
   - Login com Plano Básico → `/app/ativos` → seleciona 2 B3 + 2 Forex → tentar 3º → erro de limite
   - Clicar [Confirmar seleção] → modal de aviso exibido → confirmar → ativos bloqueados → banner laranja aparece
   - Tentar adicionar ativo após bloqueio → erro 423 "ativos bloqueados"
   - Admin → `POST /admin/users/{id}/assets` → consegue trocar ativo bloqueado
   - App Android → lista de ativos exibe apenas os 4 ativos selecionados → toggle de notificação por ativo funciona
   - `/app/grafico` → EMAs + Elliott + Wyckoff visíveis, **SMC zones ausentes**
   - **Intermediário**: 1ª "Gerar análise" do dia p/ ativo → grátis (`source=free`); 2ª no mesmo ativo → consome crédito pago
   - **Básico** sem crédito → "Gerar análise" → 402 + botão "Comprar créditos"
   - **Estudo de oportunidade (1º cliente)** → request `pending` → VPS dreni a fila (`GET /internal/analysis-queue` → `claim` → gera bundle → `result completed`) → request `completed` com `evidence_bundle_id`
   - **Reaproveitamento (2º cliente, mesma oportunidade)** → resposta **200 `reused`** na hora, crédito do 2º cliente consumido, **sem** nova entrada na fila da VPS; cada cliente só vê via `GET /user/studies`
   - **Estudo de timeframe sem oportunidade** → sempre `pending` → gera na VPS → individual do cliente (outro cliente no mesmo timeframe gera o seu próprio)
   - Simular falha de geração (VPS `result failed`) → request `failed` → crédito pago **restaurado** (free e reaproveitado não restauram)
   - **VPS**: poller roda como serviço, autentica via HMAC, drena 1 request e devolve resultado ponta-a-ponta
   - **App Android**: após login, lista de ativos vem de `GET /user/assets` (não mais hardcoded); Básico bloqueado mostra nota de suporte; tickers batem com `sync_assets`
   - `/app/creditos` → gerar cobrança PIX (usa CPF do perfil) → QR code exibido → polling ativo
   - Webhook Asaas `PAYMENT_RECEIVED` (description `credits:10`) → saldo +10, **sem** criar License; reenvio do mesmo evento → idempotente (não duplica)
   - Admin → `POST /admin/users/{id}/credits` → créditos adicionados manualmente
   - Push de oportunidade → chega **apenas** para usuários que selecionaram o ativo e têm plano ativo não expirado
6. Verificação de **segurança/robustez**:
   - Cliente A tenta `GET /user/ai-analysis/{id}` de um request do cliente B → **404** (S1)
   - Dois `POST /user/ai-analysis {opportunity_id}` simultâneos do mesmo cliente → 1 cobrança só (UNIQUE, S9)
   - Duas análises grátis simultâneas no mesmo ativo/dia → só 1 grátis (UNIQUE free_date, S10)
   - Webhook Asaas com valor **menor** que o esperado → **não** concede crédito (S5); reenvio idêntico → não duplica (S6)
   - Matar o poller no meio → `ai-analysis:reap-stale` marca `failed` e **restaura** o crédito pago (S16)
   - `result` reenviado pela VPS para request já `completed` → ignorado (S15)
   - `/me` e listagens **não** retornam CPF em claro (S21)
   - `PURCHASE_APPROVED` atrasado após `PURCHASE_REFUNDED` → licença **continua** revogada (S12)
   - Suíte de testes do backend passa após troca de planos (S23)

---

## Ordem de Execução

```
━━━ FASE 1 — Backend ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  1A → Migrations 000002 (plan limits)
       → PlansSeeder (substitui planos) + Plan.php
       → findPlan fallback + plan_map (reconciliação)

  1B → Migration 000003 (user_plan_assets)
       → config/assets.php + AssetCategoryResolver
       → UserPlanAsset model + UserAssetController (+confirm +admin)
       → User.php (planAssets, getActivePlan c/ expiry, canAddAsset, assetsLocked)
       → SendOpportunityPushNotification (filtro plan assets + plano ativo)

  1C → Migration 000004 (user_credits, credit_transactions, ai_analysis_requests c/ uniques, credit_orders)
       → UserCredit + CreditTransaction + AiAnalysisRequest + CreditOrder models
       → CreditService (atômico) + AiAnalysisService (transação + uniques S9/S10) + RequestVpsAnalysis job
       → AiAnalysisController (IDOR S1) + AdminCreditController (audit S3)
       → ReapStaleAnalysis command (S16)

  1D → AsaasApiClient + AsaasProvider + AsaasCheckoutController
       → WebhookController branch crédito + config/webhooks.php + routes

  1E → Migration 000005 (cpf/phone/address) + 000006 (interval ciclo)
       → config/commercial.php + BillingCycle helper + ExpireLicenses command
       → HotmartProvider (fix getAmount + dados cliente + getBillingCycle + isCancellation + isPaymentFailure)
       → WebhookController (dados cliente + ciclo + lifecycle: garantia 7d / cancelamento / inadimplência)
       → config/webhooks.php offer_map (9 ofertas → plano+ciclo)

  → Deploy backend + php artisan migrate + db:seed --class=PlansSeeder
  → agendar licenses:expire-check (diário) + ai-analysis:reap-stale (~5min)
  → conferir .env (S25): ASAAS_*, WEBHOOK_HOTMART_*, offer_map
  → rodar suíte de testes backend (S23)

━━━ FASE 2 — Frontend ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  2A → useUserAssets.ts + AssetSelectionPage.tsx
       → CustomerArea.tsx (sidebar + nav)

  2B → useCustomerChart.ts + CustomerChartOverlays.tsx
       → CustomerChartPage.tsx (sem SMC, com Elliott/Wyckoff/EMAs/TPs)

  2C → Dashboard do cliente — 4 cards
       → StudyPanel.tsx

  2D → useCredits.ts + CreditsPage.tsx (PIX + cartão + polling)

  → npm run build + deploy frontend

━━━ FASE 3 — VPS (Python) ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  3A → Site: InternalAnalysisQueueController + rotas internal (HMAC)
  3B → VPS: analysis_poller.py (drena fila, gera bundle, devolve result)
       → wirar push de evidência VPS→site se ainda não existir
       → registrar serviço (start_bridges.sh / systemd)
  3C → Canonicalizar lista de tickers (VPS = site = app)

  → deploy VPS (poller) + validar E2E análise sob demanda

━━━ FASE 4 — App Android ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  4A → UserAssetRemoteDataSource + model + repository + Koin
  4B → PreferencesViewModel/Screen consomem GET /user/assets
       → deprecar AccountType hardcoded + default de enabledAssets
  4C → /me retorna plan_slug + assets_locked (site) [opcional]

  → ./gradlew assembleDebug + validar lista dinâmica
```

> **Dependência entre fases**: a Fase 2D (StudyPanel "Gerar análise") só fica 100% funcional após a **Fase 3** (VPS drenando a fila). Até lá, o request fica `pending`. A Fase 4 (Android) depende da **Fase 1B** (`/user/assets`).
