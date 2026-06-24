# Plano de Melhorias - MaximusTrader

## Visão Geral

O MaximusTrader é uma plataforma SaaS para traders brasileiros, combinando análise de mercado (SMC/Elliott/Wyckoff), detecção de oportunidades com push notifications, análise por IA e sistema de assinatura. A arquitetura é híbrida: um VPS Python faz a análise pesada e sincroniza dados com o backend Laravel na Hostinger, que serve para o frontend React.

**Stack:** Laravel 12 + PHP 8.2 (backend) | React 19 + TypeScript + Vite 8 + Tailwind CSS (frontend)

---

## FASE 1: Segurança (CRÍTICO)

### 1.1 Rotacionar APP_KEY exposta
- **Problema:** `backend/.env` foi commitado com `APP_KEY` real no git
- **Ação:** Gerar nova APP_KEY, rotacionar, e remover o arquivo do histórico git (BFG Repo Cleaner ou git filter-branch)
- **Arquivo:** `backend/.env`

### 1.2 Credenciais hardcoded no deploy
- **Problema:** `tools/deploy.sh` contém IP do VPS (`82.25.73.246`), porta (`65002`) e username em plaintext
- **Ação:** Parametrizar via variáveis de ambiente ou arquivo `.env.deploy` (excluído do git)
- **Arquivo:** `tools/deploy.sh`

### 1.3 Guards de role no frontend
- **Problema:** `ProtectedRoute` só verifica autenticação, não verificação de role admin. Usuários customer veem a sidebar admin mas recebem 403
- **Ação:** Adicionar verificação `user.roles?.some(r => r.name === 'admin')` nas rotas `/admin/*`
- **Arquivo:** `frontend/src/components/ProtectedRoute.tsx` (ou similar)

---

## FASE 2: Performance (ALTO)

### 2.1 Queries N+1 no endpoint de assets
- **Problema:** `MarketDataController::assets()` executa 3 queries por asset (3N+1 total)
- **Ação:** Usar eager loading ou query agregada com subqueries
- **Arquivo:** `backend/app/Http/Controllers/Api/MarketDataController.php:43-66`

### 2.2 Fetch duplicado de zones
- **Problema:** `useRealMarketData` e `useSmcPerType` buscam `/api/zones/{ticker}` duas vezes no modo live
- **Ação:** Compartilhar dados de zones via contexto ou consolidar em um único hook
- **Arquivos:** `frontend/src/hooks/useRealMarketData.ts`, `frontend/src/hooks/useSmcPerType.ts`

### 2.3 Cache de dados de mercado
- **Problema:** Todas as requisições de candles/zones batem direto no MySQL, sem cache
- **Ação:** Implementar cache Redis para dados de mercado (TTL de 30s-1min para candles recentes)
- **Arquivo:** `backend/app/Http/Controllers/Api/MarketDataController.php`

### 2.4 Ordenação de zones em PHP
- **Problema:** `SmcZoneService` faz 9 queries separadas e ordena com `usort` em PHP
- **Ação:** Usar SQL `CASE WHEN` para ordenação por prioridade, ou consolidar em query única
- **Arquivo:** `backend/app/Services/SmcZoneService.php:60`

### 2.5 Paginação ineficiente
- **Problema:** Endpoint de candles usa `skip($offset)` que é ineficiente para offsets grandes
- **Ação:** Usar cursor-based pagination (WHERE timestamp < :last_timestamp)
- **Arquivo:** `backend/app/Http/Controllers/Api/MarketDataController.php:165`

---

## FASE 3: Código e Arquitetura (MÉDIO)

### 3.1 Extrair lógica duplicada do SyncController
- **Problema:** Lógica de salvar candles está duplicada entre `sync()` e `candles()`
- **Ação:** Extrair para um método compartilhado ou service class
- **Arquivo:** `backend/app/Http/Controllers/Api/SyncController.php`

### 3.2 Decompor controllers gigantes
- **Problema:** `MarketDataController` (702 linhas), `AdminController`, `WebhookController` são muito grandes
- **Ação:** Separar em controllers menores por responsabilidade (ex: CandlesController, ZonesController)
- **Arquivo:** `backend/app/Http/Controllers/Api/MarketDataController.php`

### 3.3 Refatorar SmcZoneService
- **Problema:** 574 linhas com 9 métodos de transformação quase idênticos (um por tabela SMC)
- **Ação:** Criar classe base ou trait com método genérico de transformação, cada tabela define apenas seu mapping
- **Arquivo:** `backend/app/Services/SmcZoneService.php`

### 3.4 Remover path legado de zones
- **Problema:** Feature flag `smc.use_new_tables` mantém dois caminhos de dados completos
- **Ação:** Documentar plano de migração e definir data de remoção do path antigo
- **Arquivo:** `backend/app/Http/Controllers/Api/MarketDataController.php:222-258`

### 3.5 Consolidar tipos TypeScript duplicados
- **Problema:** `StudyData` interface definida em múltiplos arquivos
- **Ação:** Criar `frontend/src/types/market.ts` com tipos compartilhados
- **Arquivos:** `useRealMarketData.ts`, `ChartPage.tsx`

### 3.6 Componente Sidebar compartilhado
- **Problema:** `Dashboard.tsx` (admin) e `CustomerArea.tsx` (customer) implementam sidebars quase idênticas
- **Ação:** Extrair para componente `Sidebar` reutilizável
- **Arquivos:** `frontend/src/pages/Dashboard.tsx`, `frontend/src/pages/CustomerArea.tsx`

### 3.7 Form Requests para validação
- **Problema:** Validação inline nos controllers, sem reutilização
- **Ação:** Criar Form Request classes para endpoints principais (Sync, Scanner, Auth)
- **Arquivos:** `backend/app/Http/Controllers/Api/*.php`

---

## FASE 4: Testes e Qualidade (ALTO)

### 4.1 Cobertura de testes backend
- **Problema:** ~5-8% de cobertura. Zero testes para Sync, Auth, MarketData, Webhook, Admin, Services
- **Ação:** Criar testes para fluxos críticos:
  - AuthController (registro, login, 2FA, recuperação de senha)
  - SyncController (sync de candles, zones, HMAC validation)
  - MarketDataController (todos os endpoints públicos)
  - WebhookController (pelo menos 2 providers: Hotmart e Asaas)
  - CreditService (consume, restore, race conditions)
  - SmcZoneService (transformação de dados)
- **Meta:** 50%+ cobertura de controllers

### 4.2 Factories incompletas
- **Problema:** Apenas `UserFactory` existe. 33 models sem factories
- **Ação:** Criar factories para: Opportunity, Plan, License, SyncAsset, SyncCandle, SyncZone, UserCredit, ScannerAlert
- **Arquivo:** `backend/database/factories/`

### 4.3 Testes frontend
- **Problema:** Zero testes frontend. Nenhum framework configurado
- **Ação:** Configurar Vitest + React Testing Library, testar:
  - Auth flow (login, registro, 2FA)
  - Hook useRealMarketData
  - Componente CandlestickChart (rendering básico)
- **Arquivo:** `frontend/package.json` (adicionar script de teste)

### 4.4 Static Analysis
- **Problema:** Sem PHPStan/Psalm para detecção de erros de tipo
- **Ação:** Instalar PHPStan nível 5+, configurar no CI
- **Arquivo:** `backend/composer.json`

### 4.5 Formatação de código
- **Problema:** Laravel Pint instalado mas não configurado. Sem pre-commit hooks
- **Ação:** Criar `pint.json`, adicionar script composer, configurar Husky + lint-staged
- **Arquivo:** `backend/pint.json`, `frontend/.husky/`

---

## FASE 5: Documentação (MÉDIO)

### 5.1 README do projeto
- **Problema:** Sem README raiz. READMEs de backend/frontend são boilerplate padrão
- **Ação:** Criar `README.md` raiz com: visão geral, arquitetura, setup, deploy, links para docs
- **Arquivo:** `README.md` (novo)

### 5.2 Documentação de API
- **Problema:** 50+ endpoints sem documentação externa
- **Ação:** Gerar OpenAPI/Swagger ou criar Postman collection
- **Arquivo:** `docs/api/` (novo)

### 5.3 Substituir READMEs boilerplate
- **Problema:** `backend/README.md` e `frontend/README.md` são textos genéricos do framework
- **Ação:** Reescrever com informações específicas do projeto (setup, estrutura, convenções)
- **Arquivos:** `backend/README.md`, `frontend/README.md`

---

## FASE 6: CI/CD e Deploy (MÉDIO)

### 6.1 Pipeline de CI
- **Problema:** Sem GitHub Actions ou qualquer CI configurado
- **Ação:** Criar workflow: lint (Pint + ESLint) → test (PHPUnit) → build (npm)
- **Arquivo:** `.github/workflows/ci.yml` (novo)

### 6.2 Ambiente de staging
- **Problema:** Deploy vai direto para produção, sem staging
- **Ação:** Configurar ambiente de staging com deploy automático da branch `develop`
- **Arquivo:** `deploy.sh` (adicionar target staging)

### 6.3 Rollback
- **Problema:** Sem capacidade de rollback no deploy
- **Ação:** Implementar versionamento de releases e script de rollback
- **Arquivo:** `tools/deploy.sh`

---

## FASE 7: Monetização e UX (BAIXO)

### 7.1 Console.log em produção
- **Problema:** Todos os normalizers SMC logam no console em cada render
- **Ação:** Remover ou usar variável de ambiente `VITE_DEBUG_SMC` para habilitar
- **Arquivos:** `frontend/src/components/chart/smc/normalizers/*.ts`

### 7.2 Remover dependência morta
- **Problema:** `socket.io-client` instalado mas não usado
- **Ação:** Remover do `package.json`
- **Arquivo:** `frontend/package.json`

### 7.3 Limpeza de tokens FCM
- **Problema:** Tokens inválidos nunca são removidos da tabela `user_devices`
- **Ação:** Marcar tokens como inativos após 3 falhas consecutivas, limpar periodicamente
- **Arquivo:** `backend/app/Services/FirebasePushService.php`

### 7.4 Rate limiting em endpoints públicos
- **Problema:** `/api/assets`, `/api/candles/*`, `/api/zones/*` não têm rate limiting
- **Ação:** Adicionar middleware throttle (ex: 60 req/min)
- **Arquivo:** `backend/routes/api.php`

### 7.5 Idioma consistente
- **Problema:** Mensagens de erro misturam português e inglês
- **Ação:** Padronizar em português (público-alvo brasileiro) ou inglês
- **Arquivos:** `backend/app/Http/Controllers/Api/*.php`

---

## Ordem de Implementação Sugerida

| Fase | Prioridade | Esforço | Impacto |
|------|------------|---------|---------|
| 1 - Segurança | CRÍTICO | Baixo | Protege dados e usuários |
| 2 - Performance | ALTO | Médio | Melhora experiência do usuário |
| 4 - Testes | ALTO | Alto | Previne regressões |
| 3 - Código | MÉDIO | Alto | Facilita manutenção |
| 5 - Documentação | MÉDIO | Baixo | Facilita onboarding |
| 6 - CI/CD | MÉDIO | Médio | Automatiza qualidade |
| 7 - UX | BAIXO | Baixo | Polimento final |

---

## Verificação

Após cada fase, verificar:
1. **Fase 1:** Confirmar que APP_KEY foi rotacionada, deploy.sh parametrizado, rotas admin protegidas
2. **Fase 2:** Medir tempo de resposta dos endpoints antes/depois (usar Laravel Telescope ou dd)
3. **Fase 3:** Rodar `php artisan test` e verificar que nada quebrou
4. **Fase 4:** Rodar `php artisan test --coverage` e verificar aumento de cobertura
5. **Fase 5:** Verificar que README é claro para novo desenvolvedor
6. **Fase 6:** Verificar que pipeline roda sem erros em push
7. **Fase 7:** Verificar que console está limpo, endpoints respondem com rate limit
