# Relatório de Alterações — Sessão 2026-06-20

## Resumo
Refatoração completa do `/admin/replay` para replicar o grafico principal, melhorias gerais no site (75+ issues auditadas), e reescrita das páginas admin de gestão de usuários, licenças e planos.

---

## 1. Replay Refatorado (Profit Pro Style)

### Arquivos modificados
| Arquivo | Mudança |
|---------|---------|
| `frontend/src/hooks/useReplayData.ts` | Adicionado fetch de Elliott, Wyckoff, Study. Retorna `startIndex` para contexto antes do start |
| `frontend/src/components/CandlestickChart.tsx` | Modo replay integrado (props opcionais). Toolbar unificada com date picker, play controls, toggles |
| `frontend/src/pages/ReplayPage.tsx` | Layout idêntico ao grafico (chart + sidebar). Playback com useEffect + useRef cleanup |
| `frontend/src/components/ReplayControls.tsx` | Limpeza de imports/variáveis não usadas |

### Arquivos criados
| Arquivo | Descrição |
|---------|-----------|
| `frontend/src/components/ReplayDatePicker.tsx` | Date picker customizado DD/MM/AAAA HH:MM com auto-advance |

### Funcionalidades
- Date picker customizado (substitui `datetime-local` nativo)
- Contexto antes do start: gráfico mostra dados anteriores à data selecionada
- Elliott Wave overlay + Wyckoff overlay no replay
- SMC toggles (FVG, OB, BPR, BOS, CHOCH, LIQ, SWING)
- Crosshair tooltip com OHLC + EMA
- IA Panel + Watchlist na sidebar
- Playback: play/pause, seek, speed (1x-20x), step forward/back

---

## 2. Auditoria Geral do Site (75+ issues)

### Prioridade 1 — Bugs Críticos (8 fixes)
| Fix | Arquivo | Descrição |
|-----|---------|-----------|
| EnforcePlanLimits | `backend/.../EnforcePlanLimits.php` | `$user->activeLicense()` → `$user->active_license` |
| CORS middleware | `backend/.../Cors.php` | `env()` → `config()` + retorno OPTIONS preflight |
| Login redirect | `frontend/.../Login.tsx` | `window.location.href` → `useNavigate()` |
| Register redirect | `frontend/.../Register.tsx` | `window.location.href` → `useNavigate()` |
| ReplayPage side effect | `frontend/.../ReplayPage.tsx` | Movido para `useEffect` |
| ReplayPage memory leak | `frontend/.../ReplayPage.tsx` | `useState` → `useRef` + cleanup no unmount |
| Symbol mapping mismatch | `frontend/src/lib/symbolMap.ts` | Shared utility criado, 3 hooks unificados |
| ReplayPage interval | `frontend/.../ReplayPage.tsx` | `setInterval` refatorado com `useRef` |

### Prioridade 2 — Bugs Menores (5 fixes)
| Fix | Arquivo | Descrição |
|-----|---------|-----------|
| Dashboard isActive | `Dashboard.tsx` | Match exato para root `/admin` |
| AdminUsuariosPage shadow | `AdminUsuariosPage.tsx` | `setInterval` → `billingInterval` |
| AdminEvidenceDetail JSON | `AdminEvidenceDetail.tsx` | `JSON.parse` com try-catch |
| Landing preço duplicado | `Landing.tsx` | Preço anual duplicado removido |
| FcmTestController path | `FcmTestController.php` | `credentials_path` removido da resposta |

### Prioridade 3 — Melhorias de Código
| Ação | Detalhes |
|------|----------|
| Dead code removido (~900 linhas) | `useMarketData.ts`, `useMarketWebSocket.ts`, `SmcSeriesPrimitive.ts`, `SmcPaneView.ts`, `SmcPaneRenderer.ts`, `smcLabelCollision.ts`, `normalizers/` (6 arquivos) |
| Console.logs removidos (18) | 7 renderers, `useReplayData.ts`, `useSmcPerType.ts`, `smcNormalize.ts` |
| Shared utility | `src/lib/symbolMap.ts` — apiSymbol, apiTimeframe, tsToUtc |

---

## 3. Gestão Admin — Usuários, Licenças e Planos

### Arquivos criados
| Arquivo | Descrição |
|---------|-----------|
| `frontend/src/components/admin/adminTypes.ts` | Interfaces: UserItem, PlanItem, LicenseItem |
| `frontend/src/components/admin/adminStyles.ts` | Estilos compartilhados: inputS, btnPri, btnSec, btnRed, btnGreen, badge() |
| `frontend/src/components/admin/AdminModal.tsx` | Modal reutilizável com ESC close + backdrop |
| `frontend/src/components/admin/AdminField.tsx` | AdminField, AdminInput, AdminSelect, AdminTextarea |

### Backend — AdminController.php
- `users()`: retorna `active_license` (plan_name, status, expires_at) + `credit_balance`
- `storeUser()`: criar via admin (não via /auth/register)
- `toggleUserActive()`: PUT toggle is_active
- `destroyUser()`: excluir com proteção contra admin
- `plans()`: retorna `license_count`, `features`, `price_semiannual`
- `storePlan()`, `updatePlan()`, `destroyPlan()`: CRUD completo
- `licenses()`: retorna user id/email, plan id, interval, guarantee_expires_at
- `updateLicense()`: suporta reativar (status=active)

### Backend — routes/api.php
- Novas rotas: `POST /users`, `PUT /users/{id}/toggle-active`, `DELETE /users/{id}`
- Novas rotas: `POST /plans`, `PUT /plans/{id}`, `DELETE /plans/{id}`
- Removido duplicate `apiResource('plans')`

### AdminUsuariosPage.tsx (reescrito)
- **Coluna "Plano"**: nome do plano + badge de status + data de expiração
- **Coluna "Créditos"**: saldo atual
- **Coluna "Criado"**: data de cadastro
- **Coluna "2FA"**: status ativo/off
- **Toggle ativo/inativo**: clique no badge alterna status
- **Busca**: filtro por nome/email
- **Exclusão**: modal de confirmação
- **Shared components**: AdminModal, AdminField, adminStyles

### AdminPlanosPage.tsx (reescrito)
- **CRUD completo**: criar, editar, excluir planos
- **Coluna "Licenças"**: quantos usuários ativos
- **Coluna "Slug"**, "Mensal", "Anual", "Ativos", "Ordem", "Status"
- **Modal de criação/edição**: nome, slug, preços, ativos, ordem
- **Proteção**: não exclui plano com licenças ativas

### AdminLicencasPage.tsx (reescrito)
- **Busca**: filtro por chave, usuário ou email
- **Coluna "Usuário"**: nome + email
- **Coluna "Ciclo"**: mensal/semestral/anual
- **Botão "Estender"**: modal com date picker (não prompt())
- **Botão "Suspender"**: muda status para suspended
- **Botão "Reativar"**: para licenças suspensas/revogadas
- **Modal de criação**: seleção de usuário + plano + ciclo

---

## 4. Métricas Finais

| Métrica | Valor |
|---------|-------|
| Frontend arquivos | 32 componentes, 3 hooks, 2 shared utilities |
| Frontend dead code removido | ~900 linhas |
| Console.logs removidos | 18 |
| Bugs críticos corrigidos | 8 |
| Bugs menores corrigidos | 5 |
| Backend novos endpoints | 6 (storeUser, toggleActive, destroyUser, storePlan, updatePlan, destroyPlan) |
| Shared admin components | 4 (AdminModal, AdminField, adminStyles, adminTypes) |
| TypeScript | 0 erros |
| Build | OK (15.5s) |
