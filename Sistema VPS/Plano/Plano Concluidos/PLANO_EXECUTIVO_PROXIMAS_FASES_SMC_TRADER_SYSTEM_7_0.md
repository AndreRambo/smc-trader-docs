# PLANO EXECUTIVO — PRÓXIMAS FASES DO SMC TRADER SYSTEM 7.0

**Data:** 15 de Junho de 2026
**Versão:** 1.0
**Baseado em:** `docs_geral/RELATORIO_GERAL_STATUS_SMC_TRADER_SYSTEM_7_0.md`
**Branch atual:** `smc-engine-v2-rebuild-phase0`

---

## 1. Resumo Executivo

### Estado Atual

O ecossistema SMC Trader System 7.0 possui 3 partes em estágios diferentes de maturidade:

| Parte | Maturidade | Testes | Pronto para produção? |
|-------|-----------|--------|----------------------|
| **Sistema Local** (Python/VPS) | ⭐⭐⭐⭐⭐ Alta | 2.522+ passando | Shadow run estável, sinais ao vivo bloqueados por guardrails |
| **MaximusTrader Backend** (Laravel/Hostinger) | ⭐⭐⭐⭐ Média-Alta | 7 PHP | API completa, sync bridge funcional, planos implementados |
| **MaximusTrader Frontend** (React) | ⭐⭐ Baixa | 0 | 3 bibliotecas de gráfico, bugs visuais, sem testes |
| **App Android** (Kotlin/KMP) | ⭐⭐ Baixa | 0 | MVP core funcional, 3 telas vazias, sem testes |

### Prioridade Real Agora

A **prioridade máxima** NÃO é adicionar funcionalidades novas. É:

1. **Tornar confiável o que já existe** — sync, monitoramento, logs
2. **Consolidar e limpar** — unificar gráficos, organizar documentação
3. **Cobrir com testes** — frontend e app estão com zero cobertura
4. **Só depois expandir** — novas telas no app, dashboards avançados, iOS

### Sequência Mais Segura

```
Fase 0: Backup + Baseline (1 dia)
    ↓
Fase 1: Organização da Documentação (1-2 dias)
    ↓
Fase 2: Confiabilidade do Sync (2-3 dias)
    ↓
Fase 3: Monitoramento + Heartbeat + Retry (2-3 dias)
    ↓
Fase 4: Consolidação de Gráficos (3-4 dias)
    ↓
Fase 5: App Android MVP (4-5 dias)
    ↓
Fase 6: Testes Integrados (3-4 dias)
    ↓
Fase 7: Deploy Controlado (2-3 dias)
```

**NÃO FAZER antes da Fase 3:**
- Alterar o pipeline SMC V2 (está FROZEN)
- Modificar guardrails (shadow_only, can_promote_trade)
- Ativar push FCM para usuários reais
- Alterar contratos de API sem versionar

---

## 2. Ordem Recomendada de Execução

| Ordem | Fase | Objetivo | Por que vem nessa ordem | Risco se inverter |
|-------|------|----------|------------------------|-------------------|
| 0 | Segurança, Backup e Baseline | Garantir ponto de restauração antes de qualquer alteração | Sem baseline, qualquer erro pode ser irreversível | Perder estado atual do sistema sem possibilidade de rollback |
| 1 | Organização da Documentação | Consolidar ~100+ docs dispersos em índice estruturado | Sem documentação organizada, as fases seguintes perdem eficiência | Perder tempo procurando informações; retrabalho por desconhecimento |
| 2 | Integração Sistema Local → MaximusTrader | Tornar sync automático e confiável | O sync é a espinha dorsal — se falhar, todo o resto (gráficos, alertas, app) fica sem dados | Corrigir frontend/app com dados inconsistentes; retrabalho quando sync for ajustado |
| 3 | Monitoramento, Heartbeat, Retry e Logs | Saber quando o sync falha e recuperar automaticamente | Sem monitoramento, Fase 2 é cega — não há como saber se o sync funciona | Ter sync automático que falha silenciosamente; ilusão de funcionamento |
| 4 | MaximusTrader Frontend e Gráficos | Unificar 3 bibliotecas em 1, corrigir bugs visuais | Com sync confiável (Fase 2) e monitoramento (Fase 3), os dados exibidos serão reais e rastreáveis | Gráficos mostrando dados errados ou inconsistentes; bugs visuais mascarando problemas reais |
| 5 | App Android MVP Completo | Finalizar 3 telas vazias + preferências + testes | Depende da API mobile já estável (Fase 2-3 garantiram dados) e FCM funcional | App mostrando dados stale ou quebrados; push notifications sem dados reais |
| 6 | Testes Integrados Ponta a Ponta | Validar fluxo completo MT5→VPS→Site→App | Todas as partes precisam estar individualmente estáveis primeiro | Testar integração de partes instáveis gera falsos negativos; perda de tempo |
| 7 | Deploy Controlado e Produção Assistida | Subir para produção com checklist, backup e rollback | Última fase — tudo validado antes | Deploy sem validação = incidente em produção |

---

## 3. Fase 0 — Segurança, Backup e Baseline

### 3.1 Objetivo

Criar um ponto de restauração completo antes de iniciar qualquer alteração no sistema.

### 3.2 Verificações Iniciais

```bash
# 1. Verificar branch e status do repositório
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
git branch --show-current
git status --short
git log --oneline -10

# 2. Verificar serviços systemd ativos
systemctl list-units --type=service --state=running | grep smc

# 3. Verificar espaço em disco
df -h /
df -h /home

# 4. Verificar memória disponível
free -h

# 5. Verificar conectividade com MySQL local
python3 -c "from infra.database import get_db_connection; c=get_db_connection(); print('MySQL OK'); c.close()"
```

### 3.3 Backups Obrigatórios

```bash
BACKUP_DIR="/home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/baseline_backups"
mkdir -p "$BACKUP_DIR"

# 1. Backup dos arquivos .env (sem expor conteúdo)
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/.env" "$BACKUP_DIR/dotenv_local.env.bak"
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/.env.example" "$BACKUP_DIR/dotenv_local_example.bak"
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/.env" "$BACKUP_DIR/dotenv_maximustrader.env.bak"
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/.env.example" "$BACKUP_DIR/dotenv_maximustrader_example.bak"

# 2. Backup dos serviços systemd
cp -r "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/systemd" "$BACKUP_DIR/systemd_bak/"
cp -r "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/deploy/systemd" "$BACKUP_DIR/deploy_systemd_bak/"

# 3. Backup das configurações principais
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/settings.json" "$BACKUP_DIR/settings.json.bak"
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/requirements.txt" "$BACKUP_DIR/requirements.txt.bak"

# 4. Backup dos arquivos de configuração do MaximusTrader
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/composer.json" "$BACKUP_DIR/composer.json.bak"
cp "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/package.json" "$BACKUP_DIR/package.json.frontend.bak"

# 5. Backup das migrations
cp -r "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/database/migrations" "$BACKUP_DIR/migrations_bak/"

# 6. Listar secrets (apenas existência, sem conteúdo)
find "/home/bimaq/projetos/SMC_Trader_System_7_0" -maxdepth 4 -type f \( -name ".env" -o -name ".credentials.json" -o -name "google-services.json" \) ! -path "*/venv/*" ! -path "*/node_modules/*" > "$BACKUP_DIR/secrets_locations.txt"
```

### 3.4 Rodar Testes Existentes (Baseline)

```bash
# 1. Rodar suite completa de testes Python
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
python -m pytest tests/ -x --tb=short -q 2>&1 | tee "$BACKUP_DIR/pytest_baseline_$(date +%Y%m%d).txt"

# 2. Rodar apenas testes críticos (SMC V2, Study Gateway, Scanner)
python -m pytest tests/test_smc_engine_v2/ tests/test_study_gateway/ tests/test_opportunity_scanner/ -v --tb=short 2>&1 | tee "$BACKUP_DIR/pytest_critical_baseline_$(date +%Y%m%d).txt"
```

### 3.5 Criar Baseline Técnico

Criar o arquivo:

`/home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/BASELINE_TECNICO_ANTES_DAS_PROXIMAS_FASES.md`

Conteúdo mínimo:
- Data e hora do baseline
- Branch e commit atual
- Serviços systemd ativos com status
- Contagem de testes e resultados
- Lista de arquivos críticos com hash MD5
- Tamanho do banco de dados (estimativa)
- Versões de Python, PHP, Node, MySQL
- Lista de secrets encontrados (apenas paths, sem conteúdo)
- Status de cada serviço (running/stopped/failed)

### 3.6 Critérios de Pronto da Fase 0

- [ ] Branch atual registrada
- [ ] Serviços systemd listados e com status documentado
- [ ] Backups de .env, systemd, configs, migrations realizados
- [ ] Testes baseline executados e resultados salvos
- [ ] Baseline técnico salvo em `docs_geral/BASELINE_TECNICO_ANTES_DAS_PROXIMAS_FASES.md`
- [ ] Nenhum arquivo de produção alterado

### 3.7 Riscos da Fase 0

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Backup de .env exposto acidentalmente | Baixa | Alto — vazamento de credenciais | Arquivo baseline aponta localização, não copia conteúdo na íntegra |
| Testes quebrarem durante baseline | Baixa | Médio — falsa sensação de problema | Rodar com `-x` e documentar falhas pré-existentes |

---

## 4. Fase 1 — Organização da Documentação e Backlog Técnico

### 4.1 Objetivo

Consolidar a documentação fragmentada (~100+ arquivos) em um índice navegável e criar uma matriz de backlog priorizada para guiar todas as fases seguintes.

### 4.2 Situação Atual (Verificada)

**Documentação existe em múltiplos locais:**

| Local | Quantidade | Tipo |
|-------|-----------|------|
| `SMC_Trader_System 7.0/docs/` | ~60+ | Contratos, ativos, guias, auditorias, histórico |
| `MaximusTrader/docs/` | 5 planos + 3 docs | Arquitetura gráfico, Cloudflare, Legal |
| `SMC_Trader_System 7.0/*.md` (root) | 4 | ARQUITETURA_OFICIAL, CHANGELOG, DOCUMENTATION_INDEX, MEMORIA_OFICIAL |
| `AppAndroid/.../Plano/` | 1 (duplicado) | Plano app Kotlin |
| `.opencode/skills/` | 8 | Skills documentados |

**Problemas identificados:**
- Documentação duplicada (ex: plano app Android em 2 locais)
- `README.md` desatualizado (v6.0 era)
- Índice `DOCUMENTATION_INDEX.md` existe mas pode não refletir estado atual
- Documentos legados em `docs/historico/` misturados com docs ativos

### 4.3 Tarefas da Fase 1

#### 4.3.1 Criar Índice Centralizado

Criar `docs_geral/INDICE_DOCUMENTACAO.md` com:

```markdown
# Índice Centralizado — Documentação SMC Trader System 7.0

## Documentos Canônicos (sempre atualizados)
| Documento | Caminho | Última Atualização | Status |
|-----------|---------|-------------------|--------|

## Documentos por Módulo
### SMC Engine V2
### Study Gateway
### Opportunity Scanner
...

## Documentos por Tipo
### Contratos Técnicos
### Guias de Instalação
### Planos de Execução
### Auditorias Históricas

## Documentos Obsoletos (manter para histórico)
```

#### 4.3.2 Criar Matriz de Backlog

Criar `docs_geral/BACKLOG_TECNICO_CONSOLIDADO.md` com TODAS as tarefas pendentes classificadas:

| ID | Área | Tarefa | Prioridade | Dependências | Arquivos Prováveis | Critério de Pronto |
|----|------|--------|-----------|-------------|-------------------|-------------------|
| SYS-001 | Sistema Local | Automatizar sync (TRIGGER 4 event-driven) | P0 | Nenhuma | `infra/sync_v2.py`, `infra/database.py` | Sync dispara automaticamente ao detectar nova vela |
| SYS-002 | Sistema Local | Heartbeat de sync a cada 60s | P0 | SYS-001 | `infra/sync_v2.py` (novo método) | Heartbeat visível no site |
| SYS-003 | Sistema Local | Retry com backoff exponencial | P0 | SYS-001 | `infra/database.py` | Falhas de POST recuperam automaticamente |
| MTB-001 | MaximusTrader Backend | Endpoint de health check do sync | P1 | Nenhuma | Novo controller ou rota | `GET /api/sync/health` retorna status |
| MTB-002 | MaximusTrader Backend | Tabela `sync_health_logs` | P1 | Nenhuma | Nova migration | Tabela criada e populada |
| MTF-001 | MaximusTrader Frontend | Remover ApexCharts | P1 | Nenhuma | `package.json`, páginas afetadas | ApexCharts ausente do bundle |
| MTF-002 | MaximusTrader Frontend | Remover PlotlyCandlestickChart | P1 | MTF-001 | `PlotlyCandlestickChart.tsx`, imports | Componente removido sem erros |
| MTF-003 | MaximusTrader Frontend | Testes frontend | P1 | MTF-001, MTF-002 | `*.test.tsx`, `*.test.ts` | Cobertura > 70% |
| MTF-004 | MaximusTrader Frontend | Error Boundary global | P2 | Nenhuma | Novo `ErrorBoundary.tsx` | Erros não quebram app inteiro |
| APP-001 | App Android | Tela Dashboard | P1 | Nenhuma | `features/dashboard/` | Cards resumo, saúde scanner, ticker |
| APP-002 | App Android | Tela Histórico | P1 | Nenhuma | `features/history/` | Lista paginada com filtros |
| APP-003 | App Android | Tela Conta/Perfil | P2 | Nenhuma | `features/account/` | Perfil, dispositivos, senha |
| APP-004 | App Android | Preferências avançadas | P2 | Nenhuma | `features/preferences/` | Quiet hours, radar states, max pushes |
| APP-005 | App Android | DTOs/Mappers/UseCases | P2 | Nenhuma | `data/dto/`, `data/mapper/`, `domain/usecase/` | Tipagem completa dos payloads |
| APP-006 | App Android | Testes unitários | P1 | APP-001...APP-005 | `*Test.kt` | Cobertura > 60% |
| INF-001 | Infraestrutura | Backup automático MySQL VPS | P1 | Nenhuma | Novo timer systemd | Backup diário criptografado |
| INF-002 | Infraestrutura | Logrotate configurado | P2 | Nenhuma | `/etc/logrotate.d/smc-trader` | Logs não acumulam indefinidamente |
| DOC-001 | Documentação | Consolidar docs em índice | P1 | Nenhuma | `docs_geral/INDICE_DOCUMENTACAO.md` | Índice completo e navegável |
| DOC-002 | Documentação | Atualizar README.md | P2 | DOC-001 | `README.md` | README reflete estado atual (v7.0) |
| TST-001 | Testes | E2E fluxo completo | P2 | Fases 2-5 | Novos arquivos de teste | Testes passam em todos os cenários |
| DEP-001 | Deploy | CI/CD pipeline | P2 | Fases 2-5 | `.github/workflows/` | Build + testes automáticos |

### 4.4 Critérios de Pronto da Fase 1

- [ ] Índice centralizado criado com TODOS os documentos mapeados
- [ ] Backlog técnico consolidado com todas as tarefas das 3 partes
- [ ] Cada tarefa tem ID, prioridade, dependências e critério de pronto
- [ ] Documentos obsoletos identificados e marcados
- [ ] Nenhum documento apagado (apenas classificados)

### 4.5 Riscos da Fase 1

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Backlog incompleto | Média | Médio — tarefas esquecidas | Validar contra o relatório geral e os arquivos reais |
| Passar tempo demais organizando | Média | Baixo — atraso nas fases seguintes | Limitar a 2 dias; o backlog pode ser refinado depois |

---

## 5. Fase 2 — Integração Sistema Local → MaximusTrader

### 5.1 Objetivo

Tornar a sincronização VPS → Site **automática, confiável e rastreável**.

### 5.2 Arquitetura Atual (Verificada)

**Arquivos envolvidos:**

| Local | Arquivo | Linhas | Função |
|-------|---------|--------|--------|
| VPS | `infra/sync_v2.py` | 1.051 | Pipeline V2 + builders de zona + sync |
| VPS | `infra/database.py` | 6.881 | Conexão MySQL + funções `sync_to_web()`, `_send_sync_request()` |
| VPS | `tools/sync_to_web.py` | — | CLI manual de sync |
| VPS | `tools/sync_v2_engine.py` | — | Engine V2 sync |
| VPS | `tools/sync_cron.sh` | — | Script cron |
| Site | `app/Http/Controllers/Api/SyncController.php` | 360 | 6 métodos: sync, candles, zones, elliott, wyckoff, study |
| Site | `app/Http/Middleware/VerifySyncHmac.php` | 84 | Validação HMAC: API Key + Timestamp (±5min) + Signature |
| Site | `routes/api.php` (L106-115) | — | Grupo de rotas com prefixo `sync` |

**Endpoints verificados (todos com HMAC):**

```
POST /api/sync          → SyncController@sync       (candles + indicators)
POST /api/sync/candles  → SyncController@candles     (apenas candles)
POST /api/sync/zones    → SyncController@zones       (apenas zonas SMC)
POST /api/sync/study    → SyncController@study       (estudos canônicos)
POST /api/sync/elliott  → SyncController@elliott     (Elliott waves)
POST /api/sync/wyckoff  → SyncController@wyckoff     (Wyckoff phases+events)
POST /api/scanner/alerts→ ScannerAlertController@store (oportunidades, HMAC scanner)
```

**Formato HMAC verificado (`VerifySyncHmac.php`):**
```
Headers:
  X-API-Key: <chave estática compartilhada>
  X-Client-Id: <identificador do cliente>
  X-Timestamp: <epoch seconds>
  X-Nonce: <nonce aleatório>
  X-Signature: HMAC-SHA256(method + "\n" + path + "\n" + timestamp + "\n" + nonce + "\n" + SHA256(body))
```

**Serviços systemd verificados (ativos):**
- `smc-b3-robot.service`
- `smc-forex-robot.service`
- `smc-mt5-b3-terminal.service`
- `smc-mt5-fx-terminal.service`
- `smc-mt5linux-b3.service`
- `smc-mt5linux-fx.service`
- `smc-opportunity-scanner.service`
- `smc-opportunity-notifier.service`
- `smc-xvfb.service`

### 5.3 O Que Já Funciona

- ✅ HMAC autenticação implementada em ambos os lados
- ✅ 6 endpoints de sync ativos e testados
- ✅ 34.072 zonas já sincronizadas com sucesso
- ✅ TRIGGER 4 implementado (detecta velas novas)
- ✅ SyncController persiste candles, zonas, Elliott, Wyckoff, estudos
- ✅ Zonas usam `replace=true/false` para evitar duplicação
- ✅ Scanner alerts têm idempotency key

### 5.4 O Que Precisa Ser Feito

#### 5.4.1 Tornar Sync Event-Driven (SYS-001)

**Problema:** Atualmente o sync é disparado manualmente ou via cron. O TRIGGER 4 detecta velas novas mas não dispara o sync automaticamente.

**Abordagem proposta (watcher Python):**

Criar `infra/sync_watcher.py`:

```python
"""
infra/sync_watcher.py — Watcher que monitora market_candles e dispara sync.

Loop:
  1. Consulta MAX(latest_candle_time) por ativo na tabela local
  2. Compara com last_synced_time (trackeado em memória ou shadow table)
  3. Se novo candle detectado → dispara sync_v2 pipeline
  4. Aguarda intervalo configurável (ex: 5s para M1, 30s para M5+)
  5. Registra heartbeat no site
"""
```

**Alternativa (MySQL trigger → Python callback):**
- Criar trigger no MySQL que insere em tabela `sync_queue`
- Watcher Python faz polling na `sync_queue`

#### 5.4.2 Validar Integridade dos Dados (SYS-004)

**Problema:** Não há validação se os dados chegaram íntegros no site.

**Abordagem proposta:**

```python
# No VPS, antes de enviar:
import hashlib, json
payload_str = json.dumps(payload, sort_keys=True)
checksum = hashlib.sha256(payload_str.encode()).hexdigest()

# Enviar checksum como header adicional:
# X-Content-Sha256: <checksum>

# No site (SyncController), validar:
# $receivedHash = hash('sha256', json_encode($request->all()));
# $expectedHash = $request->header('X-Content-Sha256');
# if (!hash_equals($expectedHash, $receivedHash)) { return 422; }
```

#### 5.4.3 Adicionar Logs Estruturados (SYS-005)

**Arquivo a criar:** `infra/sync_logger.py`

```python
"""
Log JSON estruturado para cada operação de sync:
{
  "timestamp": "2026-06-15T14:30:00-03:00",
  "correlation_id": "uuid",
  "operation": "sync_zones",
  "ticker": "WINFUT",
  "timeframe": "M5",
  "records_count": 42,
  "run_id": "abc123",
  "duration_ms": 1234,
  "status": "success" | "failed" | "retry",
  "error": null,
  "retry_count": 0
}
"""
```

#### 5.4.4 Testar Sincronização Real

**Script de validação a criar:** `tools/validate_sync_e2e.py`

```python
"""
Valida o fluxo completo de sync:
1. Verifica último candle local (MySQL VPS)
2. Verifica último candle remoto (GET /api/candles/WINFUT?limit=1)
3. Compara timestamps e valores
4. Reporta diferenças
"""
```

### 5.5 Arquivos a Modificar

| Arquivo | Tipo de Alteração | Risco |
|---------|------------------|-------|
| `infra/sync_watcher.py` | NOVO — watcher event-driven | Baixo — não altera pipeline existente |
| `infra/sync_logger.py` | NOVO — logging estruturado | Nenhum — apenas adiciona logs |
| `infra/sync_v2.py` | MODIFICAR — adicionar checksum SHA-256 | Baixo — adição de header |
| `infra/database.py` | MODIFICAR — adicionar retry lógica | Médio — altera funções de envio |
| `backend/app/Http/Controllers/Api/SyncController.php` | MODIFICAR — validar checksum | Baixo — validação adicional |
| `backend/routes/api.php` | MODIFICAR — adicionar GET /sync/health | Baixo — nova rota |
| `deploy/systemd/smc-sync-watcher.service` | NOVO — serviço systemd | Baixo — novo serviço |

### 5.6 Arquivos que NÃO Podem Ser Alterados

- `infra/sync_v2.py` — pipeline de zonas (builders `_build_fvg_zones`, `_build_ob_zones`, etc.)
- `backend/app/Http/Middleware/VerifySyncHmac.php` — a menos que seja para adicionar checksum
- `backend/app/Models/SyncZone.php` e demais models Sync*
- `.env` de ambos os lados

### 5.7 Critérios de Pronto da Fase 2

- [ ] Sync watcher rodando como serviço systemd
- [ ] Sync disparado automaticamente ao detectar vela nova
- [ ] Checksum SHA-256 validado no recebimento
- [ ] Logs JSON estruturados gerados em cada operação
- [ ] Teste E2E de sync executado com sucesso (1 candle + 1 zona + 1 Elliott + 1 Wyckoff)
- [ ] Nenhum dado duplicado após 3 execuções consecutivas
- [ ] Falha de rede simulada e recuperada (retry)

### 5.8 Riscos da Fase 2

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Sync watcher sobrecarregar MySQL | Baixa | Médio — lentidão | Polling com intervalo mínimo de 5s; query com LIMIT |
| Duplicação de dados durante retry | Média | Alto — dados incorretos no site | Idempotency key + replace=false apenas na primeira batch |
| API Key exposta em logs | Baixa | Alto — acesso não autorizado | Sanitizar headers sensíveis no logger |
| Quebra de compatibilidade com site | Baixa | Alto — sync para de funcionar | Testar em endpoint de staging antes (se existir) ou dry-run |

---

## 6. Fase 3 — Monitoramento, Heartbeat, Retry e Logs

### 6.1 Objetivo

Saber, em tempo real, se o sistema está funcionando, e recuperar automaticamente de falhas.

### 6.2 O Que Monitorar

| Métrica | Fonte | Frequência | Como expor |
|---------|-------|-----------|-----------|
| Último candle sincronizado (por ativo) | MySQL VPS | A cada sync | Campo `last_synced_at` |
| Último sync bem-sucedido (timestamp) | Sync watcher | A cada sync | Endpoint health |
| Último erro de sync | Sync logger | On error | Endpoint health |
| Quantidade de registros enviados (último sync) | Sync watcher | A cada sync | Endpoint health |
| Status do MT5 (B3 + Forex) | `robot_health.py` | A cada 60s | Endpoint health |
| Status do scanner | scanner systemd | A cada scan | Endpoint health |
| Status do FCM (último push) | Laravel | A cada push | Endpoint health |
| Uptime dos robôs | systemd | A cada 60s | Endpoint health |

### 6.3 Arquitetura Proposta

```text
┌─────────────────────────────────────────────┐
│               VPS (Sistema Local)            │
│                                              │
│  sync_watcher.py (novo)                      │
│  ├─ Loop: detecta velas → dispara sync       │
│  ├─ Heartbeat: POST /api/sync/health a cada  │
│  │   60s com métricas atualizadas            │
│  └─ Retry: fila local de falhas, backoff     │
│                                              │
│  health_collector.py (novo)                  │
│  ├─ Coleta status MT5, scanner, robôs        │
│  └─ Expõe via endpoint local :8008/health    │
└──────────────────┬──────────────────────────┘
                   │ POST /api/sync/health (HMAC)
                   ▼
┌─────────────────────────────────────────────┐
│        MaximusTrader (Hostinger)             │
│                                              │
│  SyncHealthController (novo)                 │
│  ├─ POST /api/sync/health ← recebe métricas  │
│  ├─ GET /api/sync/health  ← expõe status     │
│  └─ Salva em sync_health_logs                │
│                                              │
│  Painel de Saúde (React)                     │
│  ├─ Card: Status Sync (online/offline)       │
│  ├─ Card: Último candle por ativo            │
│  ├─ Card: Status MT5/Scanner                 │
│  └─ Gráfico: Latência de sync (24h)          │
└─────────────────────────────────────────────┘
```

### 6.4 Novos Arquivos

| Arquivo | Propósito |
|---------|----------|
| `infra/sync_watcher.py` | Watcher event-driven + heartbeat + retry |
| `infra/health_collector.py` | Coletor de métricas do sistema local |
| `infra/sync_logger.py` | Logger JSON estruturado |
| `deploy/systemd/smc-sync-watcher.service` | Serviço systemd para o watcher |
| `backend/app/Http/Controllers/Api/SyncHealthController.php` | Controller de health |
| `backend/database/migrations/2026_06_15_000001_create_sync_health_logs_table.php` | Tabela de health |
| `backend/app/Models/SyncHealthLog.php` | Model |
| `frontend/src/pages/AdminSystemHealth.tsx` | Painel de saúde (ou integrado ao Dashboard) |

### 6.5 Retry com Backoff Exponencial

```python
# Pseudocódigo do retry (em infra/sync_watcher.py)
import time
from typing import Callable

def retry_with_backoff(
    fn: Callable,
    max_retries: int = 5,
    base_delay: float = 1.0,
    max_delay: float = 60.0,
) -> dict:
    """Executa fn() com retry e backoff exponencial."""
    for attempt in range(max_retries + 1):
        result = fn()
        if result["ok"]:
            return result
        if attempt < max_retries:
            delay = min(base_delay * (2 ** attempt), max_delay)
            log_warning(f"Sync falhou (tentativa {attempt+1}/{max_retries}), "
                        f"retry em {delay:.1f}s", result.get("msg"))
            time.sleep(delay)
    log_error(f"Sync esgotou após {max_retries} tentativas")
    return {"ok": False, "msg": "MAX_RETRIES_EXCEEDED"}
```

### 6.6 Alertas

**Nível 1 — Warning (sync atrasado > 5 min):**
- Log warning no VPS
- Incrementa contador no health endpoint
- Visível no painel de saúde (amarelo)

**Nível 2 — Critical (sync parado > 15 min):**
- Log error no VPS
- Health endpoint retorna `status: "degraded"`
- Painel de saúde mostra vermelho
- (Futuro) Enviar email/alerta para admin

**Nível 3 — Emergency (MT5 offline > 5 min):**
- Health endpoint retorna `status: "down"`
- (Futuro) Notificação push para admin

### 6.7 Critérios de Pronto da Fase 3

- [ ] Sync watcher rodando como systemd service com auto-restart
- [ ] Heartbeat POST a cada 60s para `/api/sync/health`
- [ ] Retry com backoff exponencial implementado e testado
- [ ] Tabela `sync_health_logs` criada e populando
- [ ] Endpoint `GET /api/sync/health` retornando métricas atualizadas
- [ ] Painel de saúde visível no admin do MaximusTrader
- [ ] Simulação de falha de rede: retry funciona, health mostra degraded
- [ ] Simulação de recuperação: health volta ao normal automaticamente

### 6.8 Riscos da Fase 3

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Falso positivo nos alertas | Alta | Baixo — ruído | Janela de tolerância de 5 min antes de alertar |
| Sobrecarga no site com heartbeats | Baixa | Médio — latência | Heartbeat é POST leve (~1KB), a cada 60s |
| Tabela de health logs crescer indefinidamente | Média | Baixo — disco | LIMIT ou TTL de 30 dias nos logs |

---

## 7. Fase 4 — MaximusTrader Frontend e Gráficos

### 7.1 Objetivo

Unificar as 3 bibliotecas de gráfico em 1, corrigir bugs visuais e implementar cobertura de testes.

### 7.2 Situação Atual (Verificada)

**3 bibliotecas coexistindo:**

| Biblioteca | Arquivo Principal | Tamanho | Status |
|-----------|-------------------|---------|--------|
| lightweight-charts v5.2 | `CandlestickChart.tsx` (28KB) | Principal | Ativa, SMC overlay implementado (8 módulos) |
| Plotly.js v3.6 | `PlotlyCandlestickChart.tsx` (12KB) | Secundária | Bugs de eixo corrigidos (plano warm-weaving-mountain) |
| ApexCharts v5.13 | — (importado via package.json) | Legada | Sem componente principal identificado |

**SMC Overlay Engine (8 módulos em `components/chart/smc/`):**

| Arquivo | Função |
|---------|--------|
| `smcTypes.ts` | Tipos TypeScript para zonas |
| `smcStyle.ts` | Cores, opacidades, dash patterns |
| `smcNormalize.ts` | Normalização de zonas da API |
| `smcVisibility.ts` | Culling de zonas fora da viewport |
| `smcLabelCollision.ts` | Evitar sobreposição de labels |
| `SmcPaneRenderer.ts` | Canvas renderer |
| `SmcPaneView.ts` | Pane view |
| `SmcSeriesPrimitive.ts` | Series primitive nativa lightweight-charts |

**Hooks de dados:**

| Hook | Fonte | Status |
|------|-------|--------|
| `useRealMarketData.ts` | REST API (`/api/candles`, `/api/zones`, etc.) | Produção |
| `useMarketWebSocket.ts` | Socket.io | Real-time |
| `useMarketData.ts` | Mock | Legado |

### 7.3 Recomendação Técnica

**Manter lightweight-charts como única biblioteca de gráfico.**

Justificativas:
1. SMC overlay engine (8 módulos) foi construído especificamente para lightweight-charts
2. É a biblioteca mais performática para dados financeiros (Canvas nativo, WebGL)
3. Menor bundle size que Plotly e ApexCharts
4. Suporte nativo a candlesticks, indicadores, e custom series primitives
5. Plotly e ApexCharts são redundantes — tudo que fazem pode ser feito com lightweight-charts + sobreposições

**Plano de ação:**

1. **Remover ApexCharts** — Remover de `package.json`, verificar se alguma página além do gráfico usa (provavelmente não)
2. **Manter PlotlyCandlestickChart.tsx temporariamente** — como fallback, escondido atrás de feature flag, até lightweight-charts estar 100% validado
3. **Consolidar em CandlestickChart.tsx** — Garantir que suporta todos os overlays (SMC, Elliott, Wyckoff, RSI, MACD, Volume)
4. **Remover Plotly** — Após validação com 1.500+ zonas, remover Plotly e seu componente

### 7.4 Tarefas Detalhadas

#### 7.4.1 Remover ApexCharts (MTF-001)

```bash
cd /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend
# 1. Verificar onde ApexCharts é usado
grep -r "apexcharts\|react-apexcharts\|ApexCharts" src/ --include="*.tsx" --include="*.ts"

# 2. Remover dependências
npm uninstall apexcharts react-apexcharts

# 3. Build de verificação
npm run build
```

#### 7.4.2 Validar CandlestickChart com Dados Reais (MTF-003)

Criar `frontend/src/__tests__/CandlestickChart.test.tsx`:

```typescript
// Testes mínimos:
// 1. Renderiza sem zonas (estado vazio)
// 2. Renderiza com 1 zona FVG
// 3. Renderiza com 100 zonas (performance)
// 4. Renderiza com 1.500 zonas (stress test)
// 5. Alterna entre timeframes sem flicker
// 6. Alterna entre ativos mantendo estado
// 7. Elliott waves renderizam
// 8. Wyckoff phases renderizam
// 9. Zonas fora da viewport não renderizam (culling)
// 10. Labels não sobrepõem (collision)
```

#### 7.4.3 Implementar Error Boundary (MTF-004)

Criar `frontend/src/components/ErrorBoundary.tsx`:

```tsx
// Error boundary que:
// - Captura erros no gráfico
// - Mostra fallback UI (mensagem + botão reload)
// - Loga erro no console
// - Não quebra o resto da aplicação
```

#### 7.4.4 Setup de Testes Frontend

```bash
cd /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend

# Instalar Jest + React Testing Library
npm install --save-dev jest @testing-library/react @testing-library/jest-dom jest-environment-jsdom

# Configurar jest.config.ts
# Adicionar script: "test": "jest"
```

### 7.5 Arquivos a Modificar

| Arquivo | Ação | Risco |
|---------|------|-------|
| `package.json` | Remover apexcharts, react-apexcharts; adicionar jest, testing-library | Baixo |
| `CandlestickChart.tsx` | Refatorar para consolidar overlays, corrigir bugs | Médio |
| `PlotlyCandlestickChart.tsx` | Manter como fallback (não alterar lógica) | Nenhum |
| `hooks/useRealMarketData.ts` | Verificar se retorna dados corretos para lightweight-charts | Baixo |
| `components/chart/smc/*` | Corrigir bugs de renderização SMC (se houver) | Médio |
| `components/ErrorBoundary.tsx` | NOVO | Nenhum |
| `src/__tests__/*` | NOVOS — testes | Nenhum |

### 7.6 Arquivos que NÃO Podem Ser Alterados

- `backend/` — nada relacionado a gráficos no backend
- `smcTypes.ts` — formato de zonas
- `api.ts` — cliente HTTP

### 7.7 Critérios de Pronto da Fase 4

- [ ] ApexCharts removido do `package.json` e do bundle
- [ ] `npm run build` passa sem erros
- [ ] `npm run lint` passa sem erros
- [ ] CandlestickChart renderiza SMC + Elliott + Wyckoff sem erros
- [ ] Teste com 1.500 zonas: sem flicker, sem crash, scroll suave
- [ ] ErrorBoundary implementado e testado
- [ ] Testes frontend criados (mínimo 10 testes)
- [ ] `npx jest` passa

### 7.8 Riscos da Fase 4

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Quebrar gráfico principal | Média | Alto — site fica sem gráfico | Feature flag para Plotly fallback; deploy em horário de baixo uso |
| Performance com muitas zonas | Média | Médio — lentidão | Culling já implementado; testar com 1.500 zonas antes do deploy |
| Regressão visual em produção | Média | Médio — usuários veem erros | Screenshot comparison antes/depois |

---

## 8. Fase 5 — App Android MVP Completo

### 8.1 Objetivo

Finalizar as 3 telas vazias, implementar preferências avançadas, preencher DTOs/Mappers/UseCases e criar testes unitários.

### 8.2 Situação Atual (Verificada)

**Telas implementadas (14 funcionalidades):**
- ✅ Login com 2FA
- ✅ Forgot Password
- ✅ Lista de Oportunidades Ativas (com cards)
- ✅ Detalhe da Oportunidade
- ✅ Preferências básicas (push toggle, WINFUT toggle)
- ✅ Push notifications (FCM)
- ✅ Deep links
- ✅ Logout
- ✅ Dark theme
- ✅ Secure token storage

**Telas/features vazias (verificadas como diretórios vazios):**

| Diretório | O que deveria ter |
|-----------|-------------------|
| `features/dashboard/` | Cards resumo, saúde scanner, ticker tape |
| `features/history/` | Lista paginada de oportunidades encerradas |
| `features/account/` | Perfil, alterar senha, gerenciar dispositivos |
| `data/dto/` | Data Transfer Objects tipados |
| `data/mapper/` | Mapeadores API → Domain |
| `data/remote/` | API remote data sources |
| `domain/usecase/` | Casos de uso |
| `core/deeplink/` | Deep link handler |
| `core/auth/` | Auth utilities |
| `core/utils/` | Utilities |

### 8.3 Ordem de Implementação

```
1. DTOs/Mappers/UseCases (APP-005)
   ↓ (tipagem necessária para as telas)
2. Dashboard (APP-001)
   ↓
3. Histórico (APP-002)
   ↓
4. Preferências Avançadas (APP-004)
   ↓
5. Conta/Perfil (APP-003)
   ↓
6. Testes Unitários (APP-006)
```

### 8.4 Tarefas por Feature

#### 8.4.1 DTOs/Mappers/UseCases (APP-005)

Criar arquivos nos diretórios vazios:

```
data/dto/
  ├── AuthDto.kt          # LoginRequest, LoginResponse, 2FAVerifyRequest
  ├── OpportunityDto.kt   # OpportunityResponse, OpportunityListResponse
  ├── DeviceDto.kt        # DeviceRegisterRequest, DeviceResponse
  └── PreferenceDto.kt    # PreferenceUpdateRequest, PreferenceResponse

data/mapper/
  ├── AuthMapper.kt       # DTO → Domain model
  ├── OpportunityMapper.kt
  └── PreferenceMapper.kt

data/remote/
  ├── AuthRemoteDataSource.kt
  ├── OpportunityRemoteDataSource.kt
  └── PreferenceRemoteDataSource.kt

domain/usecase/
  ├── LoginUseCase.kt
  ├── GetActiveOpportunitiesUseCase.kt
  ├── GetOpportunityDetailUseCase.kt
  ├── GetOpportunityHistoryUseCase.kt
  ├── UpdatePreferencesUseCase.kt
  └── RegisterDeviceUseCase.kt
```

#### 8.4.2 Dashboard (APP-001)

Componentes:
- `DashboardScreen.kt` — Scaffold com top bar "Maximus Trade Signals"
- `ScannerHealthCard.kt` — Status do scanner (online/offline), última scan, oportunidades ativas
- `ActiveOpportunitiesSummary.kt` — Cards agregados: total ativas, por direção (BUY/SELL), por ativo
- `RecentAlertsCard.kt` — Últimas 3 oportunidades detectadas

API necessária: `GET /api/mobile/dashboard` (novo endpoint a ser criado no backend se necessário, ou compor a partir de `/api/mobile/opportunities/active`)

#### 8.4.3 Histórico (APP-002)

Componentes:
- `HistoryScreen.kt` — LazyColumn paginada
- `HistoryFilterBar.kt` — Filtros: ativo, direção, status, período
- `HistoryCard.kt` — Card resumido: símbolo, direção, entry, resultado, data

API: `GET /api/mobile/opportunities/history` (já existe no backend)

#### 8.4.4 Preferências Avançadas (APP-004)

Campos a adicionar na `PreferencesScreen.kt`:
- `quietHoursStart` / `quietHoursEnd` — TimePicker
- `maxPushesPerHour` — Slider (1-20)
- `radarStateToggles` — Toggle para cada estado: PREPARAR, ENTRADA_PROXIMA, NA_ZONA, MITIGADA
- `assetToggles` — Expandir além de WINFUT para todos os 6 ativos

API: `PUT /api/mobile/preferences` e `PUT /api/mobile/preferences/proximities` (já existem)

#### 8.4.5 Conta/Perfil (APP-003)

Componentes:
- `AccountScreen.kt` — Perfil do usuário
- `ChangePasswordSection.kt` — Alterar senha
- `DeviceManagementSection.kt` — Lista de dispositivos, remover dispositivo
- `LogoutSection.kt` — Logout com confirmação

### 8.5 Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `data/dto/*.kt` | NOVOS — 4 arquivos |
| `data/mapper/*.kt` | NOVOS — 3 arquivos |
| `data/remote/*.kt` | NOVOS — 3 arquivos |
| `domain/usecase/*.kt` | NOVOS — 6 arquivos |
| `features/dashboard/*.kt` | NOVOS — 2-3 arquivos |
| `features/history/*.kt` | NOVOS — 2-3 arquivos |
| `features/account/*.kt` | NOVOS — 2-3 arquivos |
| `features/preferences/PreferencesScreen.kt` | MODIFICAR — expandir |
| `features/preferences/PreferencesViewModel.kt` | MODIFICAR — expandir |
| `App.kt` | MODIFICAR — adicionar novas rotas |
| `core/di/Modules.kt` | MODIFICAR — adicionar novos usecases |

### 8.6 Testes (APP-006)

```kotlin
// Estrutura de testes:
composeApp/src/commonTest/kotlin/br/com/maximustrade/signals/
  ├── features/
  │   ├── auth/LoginViewModelTest.kt
  │   ├── opportunities/OpportunityListViewModelTest.kt
  │   ├── opportunities/OpportunityDetailViewModelTest.kt
  │   └── preferences/PreferencesViewModelTest.kt
  ├── domain/usecase/
  │   ├── LoginUseCaseTest.kt
  │   └── GetActiveOpportunitiesUseCaseTest.kt
  └── data/repository/
      ├── AuthRepositoryImplTest.kt
      └── OpportunityRepositoryImplTest.kt
```

### 8.7 Critérios de Pronto da Fase 5

- [ ] Dashboard exibindo cards resumo com dados reais da API
- [ ] Histórico exibindo lista paginada com filtros funcionais
- [ ] Conta/Perfil exibindo informações do usuário
- [ ] Preferências avançadas salvando e recuperando corretamente
- [ ] DTOs, Mappers e UseCases preenchidos e tipados
- [ ] Testes unitários criados (mínimo 10 testes)
- [ ] App compila sem erros (`./gradlew assembleDebug`)
- [ ] Teste em dispositivo real: login → receber push → abrir deep link → ver detalhe

### 8.8 Riscos da Fase 5

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Endpoint mobile não retorna dados suficientes para Dashboard | Média | Médio — dashboard incompleto | Criar endpoint específico `/api/mobile/dashboard` se necessário |
| Quebra de compatibilidade ao adicionar usecases | Baixa | Médio — app não compila | Compilar após cada feature; testes de regressão |
| FCM token inválido em produção | Média | Baixo — push não chega | Já tratado com retry e log no backend |

---

## 9. Fase 6 — Testes Integrados Ponta a Ponta

### 9.1 Objetivo

Validar o fluxo completo de dados desde a coleta MT5 até a notificação no app Android.

### 9.2 Fluxo a Testar

```text
[MT5] → [RPyC Bridge] → [run_b3.py/run_forex.py] → [MySQL VPS]
  → [TRIGGER 4: SMC V2 Pipeline] → [Shadow Tables]
  → [Sync Watcher] → [HMAC POST] → [Laravel SyncController]
  → [MySQL Hostinger] → [MarketDataController] → [React Chart]
  → [Opportunity Scanner] → [ScannerAlertController]
  → [SendOpportunityPushNotification Job] → [FCM]
  → [App Android: Push Notification] → [Deep Link] → [Detail Screen]
```

### 9.3 Cenários de Teste

#### 9.3.1 Cenário Normal (Happy Path)

| # | Passo | Validação |
|---|-------|----------|
| 1 | Nova vela M5 de WINFUT inserida no MySQL VPS | Registro visível em `market_candles` |
| 2 | TRIGGER 4 detecta e dispara SMC V2 pipeline | Novas zonas em `smc_v2_shadow_fvg`, `smc_v2_shadow_ob`, etc. |
| 3 | Sync watcher detecta e envia POST /api/sync/zones | Resposta 200, zonas salvas em `sync_zones` |
| 4 | Frontend recebe zonas via GET /api/zones/WINFUT | Zonas renderizadas no gráfico |
| 5 | Scanner detecta oportunidade e envia POST /api/scanner/alerts | Resposta 200, registro em `scanner_alerts` |
| 6 | Job push dispara e envia FCM | Push log `success` em `push_logs` |
| 7 | App Android recebe push e abre deep link | Tela de detalhe exibe a oportunidade correta |

#### 9.3.2 Cenários de Falha

| Cenário | Simulação | Comportamento Esperado |
|---------|-----------|----------------------|
| Falha de rede (VPS → Site) | Derrubar interface de rede por 2 min | Retry com backoff; health mostra degraded; recuperação automática |
| HMAC inválido | Enviar assinatura errada | 401; logged; não corrompe dados |
| Payload duplicado | Enviar mesmo payload 2x | Idempotency key detecta; segunda request retorna 200 sem duplicar |
| Timestamp expirado | Enviar com timestamp de 10 min atrás | 401 "Timestamp too old" |
| FCM token inválido | Usar token revogado | Push log com error; não quebra fila |
| Sincronização atrasada | Simular sync de dados de 1h atrás | Dados inseridos com timestamp correto; não sobrescrevem dados mais recentes |
| Zona grande (1500+ zonas) | Enviar batch de 1500 zonas | Processamento < 30s; sem timeout; frontend renderiza sem crash |

### 9.4 Script de Teste Automatizado

Criar `tests/integration/test_e2e_sync_flow.py`:

```python
"""
Teste E2E automatizado do fluxo de sync.

Pré-requisitos:
  - MySQL VPS acessível
  - MaximusTrader acessível (maximustrade.com.br)
  - HMAC credentials configuradas
  - Pelo menos 1 candle recente em market_candles

Passos:
  1. Insere candle de teste no MySQL VPS
  2. Dispara sync manualmente
  3. Verifica se candle chegou no site
  4. Verifica se zonas foram geradas
  5. Verifica se Elliott/Wyckoff foram gerados
  6. Verifica se health endpoint responde
  7. Limpa dados de teste
"""
```

### 9.5 Critérios de Pronto da Fase 6

- [ ] Todos os 7 cenários do happy path passam
- [ ] Todos os 7 cenários de falha passam
- [ ] Testes documentados em `docs_geral/RESULTADOS_TESTES_INTEGRADOS.md`
- [ ] Nenhum dado de produção corrompido durante testes
- [ ] Performance dentro do esperado (sync < 5s, render < 2s com 1500 zonas)

### 9.6 Riscos da Fase 6

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Teste E2E contaminar banco de produção | Média | Alto — dados inválidos | Usar ticker de teste (não WINFUT real); cleanup explícito |
| FCM teste enviar push para usuário real | Baixa | Médio — confusão | Usar dry-run mode do FirebasePushService |

---

## 10. Fase 7 — Deploy Controlado e Produção Assistida

### 10.1 Objetivo

Realizar deploy faseado com validação, backup e capacidade de rollback.

### 10.2 Checklist Pré-Deploy

```bash
# 1. Todos os testes passando
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
python -m pytest tests/ -x --tb=short -q 2>&1 | tail -5

cd "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend"
npx tsc --noEmit && npm run build

# 2. Lint sem erros
npm run lint

# 3. Serviços systemd ativos
systemctl list-units --type=service --state=running | grep smc

# 4. Espaço em disco > 20%
df -h / | tail -1 | awk '{print $5}'

# 5. MySQL VPS respondendo
python3 -c "from infra.database import get_db_connection; c=get_db_connection(); print(c.execute('SELECT 1').fetchone()); c.close()"

# 6. Site respondendo
curl -s -o /dev/null -w "%{http_code}" https://maximustrade.com.br/api/up

# 7. Branch correta
git branch --show-current
```

### 10.3 Sequência de Deploy

```
1. Deploy Backend (Laravel)
   ├─ Backup do banco Hostinger
   ├─ Rodar migrations pendentes
   ├─ SCP arquivos PHP alterados
   ├─ Limpar cache Laravel
   └─ Validar: GET /api/up → 200

2. Deploy Frontend (React)
   ├─ npm run build
   ├─ SCP index.html + assets
   ├─ Remover assets antigos
   └─ Validar: abrir /admin/grafico → sem erros no console

3. Deploy VPS (Python)
   ├─ Backup do banco VPS
   ├─ git pull (branch correta)
   ├─ Reiniciar serviços systemd afetados
   ├─ Verificar logs por 5 min
   └─ Validar: health endpoint verde

4. Validação Pós-Deploy
   ├─ Sync funcionando (último heartbeat < 2 min)
   ├─ Gráficos renderizando (todos os overlays)
   ├─ FCM push funcional (teste dry-run)
   ├─ App Android recebendo dados
   └─ Métricas de performance estáveis
```

### 10.4 Procedimento de Rollback

```bash
# Backend rollback
cd /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader
# Restaurar backup do banco (se migrations novas)
# Reverter arquivos PHP para versão anterior (git checkout <commit>)

# Frontend rollback
# Restaurar dist/ anterior do backup
# SCP para Hostinger

# VPS rollback
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
git checkout <commit_anterior>
systemctl restart smc-sync-watcher smc-opportunity-scanner
```

### 10.5 Verificações Pós-Deploy (Primeiras 24h)

| Hora | Verificação | Como |
|------|-----------|------|
| +5 min | Sync funcionando | Health endpoint |
| +15 min | Candles sincronizados | Último candle por ativo |
| +30 min | Zonas renderizando | Gráfico com overlays |
| +1 h | FCM funcional | Test push dry-run |
| +6 h | Logs sem erros | Verificar sync_health_logs |
| +24 h | Performance estável | Métricas de latência e volume |

### 10.6 Serviços Systemd a Gerenciar

**Já existentes (NÃO alterar sem necessidade):**

| Serviço | Ação no Deploy |
|---------|---------------|
| `smc-b3-robot.service` | Não reiniciar (24/7) |
| `smc-forex-robot.service` | Não reiniciar (24/7) |
| `smc-mt5-b3-terminal.service` | Não reiniciar |
| `smc-mt5-fx-terminal.service` | Não reiniciar |
| `smc-mt5linux-b3.service` | Não reiniciar |
| `smc-mt5linux-fx.service` | Não reiniciar |
| `smc-opportunity-scanner.service` | Reiniciar após deploy |
| `smc-opportunity-notifier.service` | Reiniciar após deploy |
| `smc-xvfb.service` | Não reiniciar |

**Novos (criados nas Fases 2-3):**

| Serviço | Ação no Deploy |
|---------|---------------|
| `smc-sync-watcher.service` | Reiniciar após deploy |
| `smc-health-collector.service` | Reiniciar após deploy |

### 10.7 Critérios de Pronto da Fase 7

- [ ] Checklist pré-deploy completo e aprovado
- [ ] Backups realizados antes do deploy
- [ ] Deploy executado na ordem: Backend → Frontend → VPS
- [ ] Validação pós-deploy aprovada (todos os checks verdes)
- [ ] Rollback testado (simulação de falha e reversão)
- [ ] 24h de operação sem incidentes
- [ ] Runbook de operação documentado

### 10.8 Riscos da Fase 7

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| Migration quebrar banco de produção | Baixa | Crítico — site fora do ar | Backup imediatamente antes; testar migration em dev primeiro |
| Reiniciar serviço crítico derrubar coleta MT5 | Média | Alto — perda de dados | NÃO reiniciar robôs B3/Forex durante deploy |
| Deploy em horário de pico | Baixa | Médio — usuários afetados | Deploy em janela de baixa liquidez (fora de horário B3) |
| Frontend quebrado em produção | Média | Médio — usuários veem tela branca | Validar build localmente; testar em aba anônima após deploy |

---

## 11. Prompts Separados Para Execução de Cada Fase

### 11.1 Prompt — Fase 0: Baseline e Backup

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Criar um baseline técnico completo e backups antes de iniciar qualquer alteração no sistema.

## Caminhos principais
- Sistema Local: /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0
- MaximusTrader: /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader
- AppAndroid: /home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid

## O que fazer

1. Verificar branch git atual e status do repositório
2. Listar todos os serviços systemd com status (running/stopped/failed)
3. Fazer backup dos arquivos .env (apenas cópia segura, sem expor conteúdo no terminal)
4. Fazer backup das configurações systemd (systemd/ e deploy/systemd/)
5. Fazer backup das migrations do Laravel
6. Fazer backup do settings.json e requirements.txt
7. Rodar suite de testes completa e salvar resultados
8. Criar arquivo de baseline em:
   /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/BASELINE_TECNICO_ANTES_DAS_PROXIMAS_FASES.md

## O baseline deve conter
- Data/hora, branch, commit hash
- Serviços systemd com status
- Contagem de testes e resultados
- Arquivos críticos com hash MD5
- Versões de Python, PHP, Node, MySQL (se detectável)
- Localização dos arquivos .env (sem conteúdo)

## O que NÃO fazer
- NÃO expor secrets no terminal ou no baseline
- NÃO alterar nenhum arquivo de produção
- NÃO reiniciar serviços
- NÃO rodar migrations

## Critérios de pronto
- [ ] Branch e commit registrados
- [ ] Serviços systemd documentados
- [ ] Backups realizados no diretório docs_geral/baseline_backups/
- [ ] Testes baseline executados e resultados salvos
- [ ] Baseline técnico salvo
- [ ] Nenhum arquivo de produção alterado
```

### 11.2 Prompt — Fase 1: Backlog Técnico

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Organizar a documentação fragmentada e criar um backlog técnico consolidado.

## Caminhos principais
- Sistema Local: /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0
- MaximusTrader: /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader
- AppAndroid: /home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid
- Relatório Geral: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RELATORIO_GERAL_STATUS_SMC_TRADER_SYSTEM_7_0.md

## O que fazer

1. Mapear TODOS os arquivos .md e .txt nas 3 partes do projeto
2. Classificar cada documento como: canônico, ativo, legado, obsoleto
3. Criar índice centralizado em docs_geral/INDICE_DOCUMENTACAO.md
4. Criar backlog técnico consolidado em docs_geral/BACKLOG_TECNICO_CONSOLIDADO.md

## O backlog deve conter (mínimo 30 tarefas)
- ID único (SYS-xxx, MTB-xxx, MTF-xxx, APP-xxx, INF-xxx, DOC-xxx, TST-xxx, DEP-xxx)
- Área (Sistema Local, MaximusTrader Backend, MaximusTrader Frontend, App, Infra, Testes, Deploy, Docs)
- Descrição da tarefa
- Prioridade (P0=bloqueante, P1=alta, P2=média, P3=baixa)
- Dependências (IDs de outras tarefas)
- Arquivos prováveis a serem alterados
- Critério de pronto

## O que NÃO fazer
- NÃO apagar nenhum documento
- NÃO alterar código
- NÃO modificar arquivos de configuração

## Critérios de pronto
- [ ] Índice com TODOS os documentos mapeados e classificados
- [ ] Backlog com no mínimo 30 tarefas classificadas
- [ ] Tarefas P0 e P1 identificadas como prioridade para Fase 2
- [ ] Documentos obsoletos marcados como tal (não apagados)
```

### 11.3 Prompt — Fase 2: Sync Sistema Local → MaximusTrader

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Tornar a sincronização VPS → Site automática, confiável e rastreável.

## Arquivos principais (LEIA antes de alterar)

### Sistema Local
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/infra/sync_v2.py (1051 linhas)
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/infra/database.py (6881 linhas)
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/sync_to_web.py
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tools/sync_v2_engine.py

### MaximusTrader
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/app/Http/Controllers/Api/SyncController.php
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/app/Http/Middleware/VerifySyncHmac.php
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/routes/api.php

## O que fazer

1. Criar infra/sync_watcher.py — loop que detecta velas novas e dispara sync
2. Adicionar checksum SHA-256 nos payloads enviados (header X-Content-Sha256)
3. Adicionar validação de checksum no SyncController
4. Implementar retry com backoff exponencial (1s, 2s, 4s, 8s, 16s — max 5 tentativas)
5. Criar sistema de log JSON estruturado (infra/sync_logger.py)
6. Criar serviço systemd smc-sync-watcher.service
7. Testar com 1 candle real: enviar → verificar recebimento → verificar sem duplicação

## O que NÃO alterar
- NÃO alterar a lógica de build de zonas em sync_v2.py (funções _build_*)
- NÃO alterar o middleware VerifySyncHmac.php (exceto para adicionar checksum)
- NÃO alterar models Sync* (SyncZone, SyncCandle, etc.)
- NÃO alterar .env de nenhum lado
- NÃO expor API keys ou secrets

## Testes obrigatórios
- python -m pytest tests/ -x --tb=short -q (todos devem continuar passando)
- Enviar 1 candle e verificar se chegou no site
- Enviar mesmo payload 2x e verificar que não duplicou
- Simular falha de rede e verificar retry

## Critérios de pronto
- [ ] Sync watcher rodando como systemd service
- [ ] Sync disparado automaticamente ao detectar vela nova
- [ ] Checksum validado no recebimento
- [ ] Retry funcionando (testado com falha simulada)
- [ ] Logs JSON gerados
- [ ] Nenhum dado duplicado
- [ ] Todos os testes existentes passando

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADO_FASE2_SYNC.md
```

### 11.4 Prompt — Fase 3: Monitoramento e Retry

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Implementar monitoramento, heartbeat, retry com backoff e painel de saúde.

## Arquivos principais
- /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/infra/sync_watcher.py (criado na Fase 2)
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/routes/api.php
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/database/migrations/

## O que fazer

1. Criar endpoint POST /api/sync/health (recebe métricas do VPS, autenticado com HMAC)
2. Criar endpoint GET /api/sync/health (público/admin, retorna status atual)
3. Criar migration sync_health_logs (timestamp, ticker, metric, value, status)
4. Criar SyncHealthController e SyncHealthLog model
5. No sync_watcher.py: adicionar heartbeat a cada 60s enviando métricas
6. Criar health_collector.py: coleta status MT5, scanner, robôs, uptime
7. Criar painel de saúde no frontend (AdminSystemHealth.tsx ou integrado ao Dashboard)
8. Implementar 3 níveis de alerta: warning (5min), critical (15min), emergency (MT5 offline)

## Métricas mínimas a expor
- Último sync bem-sucedido (timestamp)
- Último erro de sync (timestamp + mensagem)
- Status dos robôs B3 e Forex (online/offline)
- Status do scanner (online/offline)
- Último candle sincronizado por ativo
- Quantidade de registros no último sync
- Latência do último sync (ms)

## O que NÃO fazer
- NÃO expor secrets nas métricas
- NÃO sobrecarregar o site com heartbeats pesados

## Testes obrigatórios
- python -m pytest tests/ -x --tb=short -q
- Heartbeat enviado com sucesso (verificar no banco do site)
- Endpoint GET /api/sync/health retornando JSON válido
- Simular sync parado por 6 min e verificar health.status = "degraded"

## Critérios de pronto
- [ ] Heartbeat funcionando (verificar sync_health_logs)
- [ ] GET /api/sync/health retornando métricas atualizadas
- [ ] Painel de saúde visível no admin
- [ ] Alertas de warning/critical funcionando
- [ ] Retry testado com falha simulada

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADO_FASE3_MONITORAMENTO.md
```

### 11.5 Prompt — Fase 4: Gráficos e Overlay SMC

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Consolidar as 3 bibliotecas de gráfico em lightweight-charts, corrigir bugs visuais e implementar testes.

## Arquivos principais (LEIA antes de alterar)

### Gráficos
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/components/CandlestickChart.tsx (28KB)
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/components/PlotlyCandlestickChart.tsx (12KB)
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/components/BackgroundEffects.tsx
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/components/chart/smc/* (8 módulos)

### Hooks
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/hooks/useRealMarketData.ts
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/hooks/useMarketWebSocket.ts

### Páginas
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/src/pages/ChartPage.tsx

### Config
- /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/frontend/package.json

## O que fazer

1. Remover ApexCharts do package.json (npm uninstall apexcharts react-apexcharts)
2. Verificar se alguma página importa ApexCharts e remover import
3. Manter PlotlyCandlestickChart.tsx como fallback (adicionar comentário "DEPRECATED")
4. Validar CandlestickChart com dados reais: candles, zonas SMC, Elliott, Wyckoff, RSI
5. Testar performance com 1.500 zonas (culling + collision)
6. Implementar ErrorBoundary global
7. Criar setup de testes frontend (Jest + React Testing Library)
8. Criar 10 testes mínimos para CandlestickChart e SMC overlay

## O que NÃO fazer
- NÃO remover PlotlyCandlestickChart.tsx (manter como fallback)
- NÃO alterar a API de dados (useRealMarketData)
- NÃO alterar os tipos SMC (smcTypes.ts)

## Testes obrigatórios
- npx tsc --noEmit (sem erros)
- npm run build (sem erros)
- npm run lint (sem erros)
- npx jest (todos passando)
- Abrir /admin/grafico com 3 ativos diferentes e verificar overlays

## Critérios de pronto
- [ ] ApexCharts removido
- [ ] Build e lint passando
- [ ] CandlestickChart funcional com todos os overlays
- [ ] 10+ testes frontend passando
- [ ] ErrorBoundary implementado
- [ ] Performance aceitável com 1.500 zonas

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADO_FASE4_GRAFICOS.md
```

### 11.6 Prompt — Fase 5: App Android MVP

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Finalizar o MVP do App Android: implementar 3 telas vazias, DTOs/Mappers/UseCases, preferências avançadas e testes.

## Caminho principal
/home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid/MaximusTrader/composeApp/src/

## Diretórios vazios a preencher (VERIFICADOS)
- commonMain/kotlin/.../features/dashboard/
- commonMain/kotlin/.../features/history/
- commonMain/kotlin/.../features/account/
- commonMain/kotlin/.../data/dto/
- commonMain/kotlin/.../data/mapper/
- commonMain/kotlin/.../data/remote/
- commonMain/kotlin/.../domain/usecase/
- commonMain/kotlin/.../core/deeplink/
- commonMain/kotlin/.../core/auth/
- commonMain/kotlin/.../core/utils/

## Ordem de implementação
1. DTOs/Mappers/UseCases (tipagem necessária para as telas)
2. Dashboard (cards resumo, saúde scanner, ticker tape)
3. Histórico (lista paginada com filtros)
4. Conta/Perfil (dados do usuário, dispositivos, logout)
5. Preferências avançadas (quiet hours, radar states, max pushes)
6. Testes unitários (mínimo 10)

## APIs disponíveis (já implementadas no backend)
- GET /api/mobile/opportunities/active
- GET /api/mobile/opportunities/history
- GET /api/mobile/opportunities/{id}
- GET/PUT /api/mobile/preferences
- PUT /api/mobile/preferences/assets
- PUT /api/mobile/preferences/proximities
- POST /api/mobile/devices
- DELETE /api/mobile/devices/{id}

## O que NÃO fazer
- NÃO alterar a estrutura de navegação existente (App.kt — apenas adicionar rotas)
- NÃO alterar ApiClient.kt ou AppConfig.kt
- NÃO alterar os models de domínio existentes (AuthModels, OpportunityModels, PreferenceModels)
- NÃO remover nenhuma tela existente

## Testes obrigatórios
- ./gradlew assembleDebug (sem erros)
- Testes unitários passando
- Verificar navegação entre todas as telas

## Critérios de pronto
- [ ] Dashboard exibindo cards com dados reais
- [ ] Histórico com lista paginada e filtros
- [ ] Conta/Perfil funcional
- [ ] Preferências avançadas salvando e recuperando
- [ ] DTOs/Mappers/UseCases implementados
- [ ] 10+ testes unitários passando

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADO_FASE5_APP_ANDROID.md
```

### 11.7 Prompt — Fase 6: Testes Integrados

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Validar o fluxo completo de dados: MT5 → VPS → Sync → Site → App Android → Push Notification.

## Fluxo a testar
[MT5] → [RPyC Bridge] → [run_b3.py] → [MySQL VPS] → [SMC V2 Pipeline] → [Sync Watcher]
→ [HMAC POST] → [Laravel SyncController] → [MySQL Hostinger] → [MarketDataController]
→ [React Chart] → [Scanner] → [FCM Push] → [App Android]

## O que fazer

1. Criar script de teste E2E em tests/integration/test_e2e_sync_flow.py
2. Testar happy path: candle → zona → sync → gráfico → scanner → push
3. Testar cenários de falha:
   - Rede offline (sync retry)
   - HMAC inválido (401)
   - Payload duplicado (idempotency)
   - Timestamp expirado (401)
   - FCM token inválido (logged, não quebra)
   - Sincronização atrasada (dados corretos)
   - 1500+ zonas (performance)
4. Documentar resultados em docs_geral/RESULTADOS_TESTES_INTEGRADOS.md

## O que NÃO fazer
- NÃO usar dados de produção reais (usar ticker de teste)
- NÃO enviar push para dispositivos reais (usar dry-run)
- NÃO alterar configurações de produção para os testes

## Critérios de pronto
- [ ] 7 cenários happy path passando
- [ ] 7 cenários de falha passando
- [ ] Performance dentro do esperado
- [ ] Resultados documentados
- [ ] Nenhum dado de produção afetado

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADOS_TESTES_INTEGRADOS.md
```

### 11.8 Prompt — Fase 7: Deploy Controlado

```
Você é uma IA de engenharia de software atuando no servidor Linux do projeto SMC Trader System 7.0.

## Objetivo
Realizar deploy faseado com validação, backup e rollback.

## Caminhos
- Sistema Local: /home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0
- MaximusTrader: /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader
- Deploy script: /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/tools/deploy.sh
- Backup script: /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/scripts/backup.sh

## Sequência de deploy
1. Pré-deploy: rodar checklist completo (testes, lint, build, saúde dos serviços)
2. Backup: banco Hostinger + banco VPS + arquivos alterados
3. Deploy Backend: migrations → SCP PHP → cache clear → validar /api/up
4. Deploy Frontend: npm run build → SCP dist/ → validar gráficos
5. Deploy VPS: git pull → reiniciar sync watcher + scanner → validar health
6. Pós-deploy: monitorar por 24h

## O que NÃO fazer
- NÃO reiniciar robôs B3/Forex (smc-b3-robot, smc-forex-robot)
- NÃO reiniciar MT5 terminals ou bridges
- NÃO fazer deploy durante horário de mercado B3 (10h-17h BRT)
- NÃO ativar modo produção (can_promote_trade) sem autorização explícita

## Rollback
Documentar procedimento exato de rollback para cada parte (backend, frontend, VPS)

## Critérios de pronto
- [ ] Checklist pré-deploy aprovado
- [ ] Backups realizados
- [ ] Deploy executado na ordem correta
- [ ] Validação pós-deploy aprovada
- [ ] 24h sem incidentes
- [ ] Runbook operacional documentado

## Relatório
Salvar resultado em: /home/bimaq/projetos/SMC_Trader_System_7_0/docs_geral/RESULTADO_FASE7_DEPLOY.md
```

---

## 12. Divergências Encontradas

Durante a análise para este plano executivo, as seguintes divergências foram encontradas entre o relatório geral e a verificação atual dos arquivos:

| # | Divergência | Relatório | Verificação Atual | Ação |
|---|-----------|-----------|-------------------|------|
| 1 | Pasta do App Android | Chamada de `AppAndroid/MaximusTrader/` | Confirmado — o projeto está em `AppAndroid/MaximusTrader/` com `composeApp/` como módulo principal | OK, consistente |
| 2 | Plano `warm-weaving-mountain.md` sobre correção de eixos Plotly | Afirma que bugs foram corrigidos | Confirmado — o código atual do `PlotlyCandlestickChart.tsx` NÃO contém mais os patterns com domains errados (`domain: [0.21, 0.38]` no xaxis2, etc.) | OK, correção já aplicada |
| 3 | Serviços systemd ativos | 19 serviços listados | 9 ativos confirmados via `systemctl` (nem todos os 19 estão instalados como serviços ativos) | OK — alguns são templates/deploy, nem todos precisam estar ativos |
| 4 | Documentação duplicada | Plano app Android em 2 locais | Confirmado — `Plano/plano_app_*.md` e `gradle/plano_app_*.md` são idênticos | Pendente consolidar na Fase 1 |
| 5 | `README.md` | Mencionado como "v6.0 era" | Confirmado — o README na raiz do sistema local referencia versão 6.0 | Pendente atualizar na Fase 1 |
| 6 | 10 diretórios vazios no App Android | Listados como "scaffold vazio" | Confirmado — todos os 10 diretórios estão vazios (sem arquivos .kt) | Pendente preencher na Fase 5 |

---

## 13. Resumo Final

### Linha do Tempo Estimada

```
Semana 1: Fase 0 + Fase 1 + Fase 2 (Baseline + Documentação + Sync)
Semana 2: Fase 3 (Monitoramento)
Semana 3: Fase 4 (Gráficos)
Semana 4: Fase 5 (App Android)
Semana 5: Fase 6 (Testes Integrados)
Semana 6: Fase 7 (Deploy Controlado)
```

**Total estimado:** 4-6 semanas de trabalho focado.

### Principais Decisões Técnicas

1. **Sync event-driven** — Watcher Python com polling no MySQL (não MySQL trigger) — mais simples e controlável
2. **lightweight-charts como única biblioteca** — Remover ApexCharts, depreciar Plotly
3. **HMAC mantido** — Já funciona, apenas adicionar checksum e retry
4. **Health endpoint único** — `GET /api/sync/health` como fonte de verdade para monitoramento
5. **App Android: completar antes de expandir** — Finalizar MVP antes de iOS ou features avançadas

### O Que NÃO Fazer Em Nenhuma Fase

- ❌ NÃO remover guardrails (`shadow_only`, `can_promote_trade`, `apply_automatically`)
- ❌ NÃO alterar pipeline SMC V2 (está FROZEN)
- ❌ NÃO expor secrets (.env, API keys, credentials)
- ❌ NÃO fazer deploy sem backup
- ❌ NÃO reiniciar robôs B3/Forex durante horário de mercado
- ❌ NÃO ativar push FCM para usuários reais sem validação completa
- ❌ NÃO apagar documentação legada (apenas classificar como obsoleta)
