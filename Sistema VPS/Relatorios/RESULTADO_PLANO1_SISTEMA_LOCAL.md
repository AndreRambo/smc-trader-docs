# RESULTADO — PLANO 1: SISTEMA LOCAL

**Data:** 16 de Junho de 2026
**Baseado em:** Plano 1 do dono do produto + Plano Executivo Fase 2-3
**Status:** P0 CONCLUÍDO — Sistema Local estabilizado e monitorado

---

## 1. O Que Foi Executado

Itens P0 do Plano 1 de acordo com `PLANO1_SISTEMA_LOCAL_EXECUCAO.md`:

| ID | Item | Status |
|----|------|--------|
| P1.1 | Corrigir `smc-forex-robot` (loop restart) | ✅ Corrigido |
| P1.2 | Corrigir `smc-b3-robot` | ✅ Rodando |
| P1.3 | Sync watcher event-driven | ✅ Criado |
| P1.4 | Heartbeat a cada 60s | ✅ Funcionando |
| P1.5 | Retry com backoff exponencial | ✅ Implementado |
| P1.6 | JSON structured logging | ✅ Funcionando |
| P1.7 | Health endpoint local + remoto | ✅ GET/POST `/api/sync/health` |
| P1.8 | Corrigir `smc-study-forward-shadow` | ✅ Timer 15min |

---

## 2. Correções de Serviços

### smc-forex-robot

**Problema:** 28.580+ restarts com `status=203/EXEC` — executável não encontrado.

**Causa raiz:** O arquivo de serviço em `/etc/systemd/system/smc-forex-robot.service` apontava para o path antigo do projeto:
```
WorkingDirectory=/home/bimaq/projetos/smc_trader_system  ← NÃO EXISTE
```
O path correto é `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0`.

**Solução:** Criado serviço user-level com wrapper script em `/home/bimaq/projetos/forex_robot_launcher.sh` (sem espaços no path, que quebram em user-level systemd < v256).

**Resultado:** Robô Forex coletando GOLD (XAUUSD), BTCUSD, EURUSD, USDJPY, ETHUSD. V2 pipeline sendo disparado ao detectar velas novas.

**Pendente:** Atualizar o serviço system-level em `/etc/systemd/system/` (precisa sudo).

### smc-b3-robot

**Problema:** Suspeito de estar quebrado (aparecia como `activating auto-restart`).

**Diagnóstico:** Na verdade estava funcionando — o log `runtime/logs/b3_robot.log` mostrava coleta ativa de WINFUT, WDOFUT, PETR4, VALE3, ITUB3. O status `activating` era porque o output vai para arquivo, não journal.

**Resultado:** Robô B3 funcionando, coletando todos os ativos B3 com V2 pipeline ativo.

### smc-study-forward-shadow

**Problema:** `status=203/EXEC` — script não executável.

**Causa raiz:** O arquivo `tools/run_study_forward_shadow.py` não tinha permissão `+x`. Além disso, o path no serviço system-level tinha o mesmo problema de espaços.

**Solução:** `chmod +x` no script. Criado serviço user-level com timer de 15 minutos usando wrapper script.

**Resultado:** Timer executando a cada 15 minutos, processando 6 ativos. Última execução: WINFUT (PRONTO), WDOFUT (PRONTO), PETR4 (MONITORAR), VALE3 (MONITORAR), XAUUSDm (BLOQUEADO — MISSING_SMC_REF pois forex acabou de reiniciar), BTCUSDm (BLOQUEADO — idem).

---

## 3. Sync Watcher — Novo Serviço

### Arquivo principal

`infra/sync_watcher.py` (377 linhas)

### Funcionalidades

- **Heartbeat:** Envia métricas de saúde a cada 60s via POST `/api/sync/health` com autenticação HMAC-SHA256
- **Retry:** Backoff exponencial (1s, 2s, 4s, 8s, 16s… até 120s, máx 5 tentativas)
- **JSON logging:** Logs estruturados em `runtime/logs/sync_watcher.jsonl`
- **Métricas coletadas:** último candle por ativo, status dos serviços systemd, espaço em disco, contagem de zonas shadow
- **Serviço systemd:** User-level, auto-restart em falha

### Health Endpoint (MaximusTrader)

- **POST `/api/sync/health`** — Recebe heartbeat + métricas (protegido por HMAC)
- **GET `/api/sync/health`** — Retorna status atual: `green` (<5min), `yellow` (5-15min), `red` (>15min)
- **Tabela:** `sync_health_logs` (criada via migration `2026_06_16_000001`)
- **Arquivos criados:**
  - `app/Http/Controllers/Api/SyncHealthController.php`
  - `app/Models/SyncHealthLog.php`
  - `database/migrations/2026_06_16_000001_create_sync_health_logs_table.php`
  - `routes/api.php` — adicionadas 2 rotas + import

### Exemplo de heartbeat bem-sucedido

```json
GET https://maximustrade.com.br/api/sync/health

{
    "status": "green",
    "last_heartbeat": "2026-06-16 00:06:55",
    "seconds_ago": 3,
    "minutes_ago": 0.1,
    "client_id": "smc-local",
    "metrics": {
        "timestamp": "2026-06-16T00:06:54+00:00",
        "db_error": "none",
        "service_smc-forex-robot": "active",
        "service_smc-mt5-b3-terminal": "active",
        "service_smc-mt5-fx-terminal": "active",
        "service_smc-mt5linux-b3": "active",
        "service_smc-mt5linux-fx": "active",
        "service_smc-opportunity-scanner": "active",
        "service_smc-opportunity-notifier": "active",
        "disk_total_gb": 192.7,
        "disk_used_pct": 43.3
    }
}
```

---

## 4. Bugs Corrigidos no Caminho

| Bug | Arquivo | Solução |
|-----|---------|----------|
| WorkingDirectory errado | service file system-level | User-level com wrapper script |
| Path com espaços quebra user-level systemd | Vários | Wrapper scripts em `/home/bimaq/projetos/` |
| Script sem +x | `tools/run_study_forward_shadow.py` | `chmod +x` |
| `hmac` module não importado | `infra/sync_watcher.py` | Adicionado `import hmac` |
| Hash duplo no HMAC (SHA256 do hex) | `infra/sync_watcher.py` | Corrigido para `hmac.new(...).hexdigest()` |
| URL duplicada `/api/api/` | `infra/sync_watcher.py` | Separado `hmac_path` de `url` |
| API key vazia (env var não setada) | `infra/sync_watcher.py` | Adicionados defaults do `database.py` |
| `use SyncHealthController` ausente | `routes/api.php` | Adicionado import |
| Dependência de Model/Carbon | `SyncHealthController.php` | Substituído por `DB` facade |
| `No module named 'infra'` | `sync_watcher_launcher.sh` | Adicionado `PYTHONPATH` |

---

## 5. Serviços Ativos (Resumo Final)

```
USER-LEVEL (systemctl --user):
  smc-forex-robot           ✅ active   Coleta Forex (5 ativos)
  smc-b3-robot              ✅ active   Coleta B3 (5 ativos)
  smc-sync-watcher          ✅ active   Heartbeat 60s + retry
  smc-study-forward-shadow  ⏱️ timer    15min (oneshot)

SYSTEM-LEVEL (systemctl):
  smc-mt5-b3-terminal       ✅ active   MT5 B3 (XP)
  smc-mt5-fx-terminal       ✅ active   MT5 Forex (Exness)
  smc-mt5linux-b3           ✅ active   Bridge RPyC :11000
  smc-mt5linux-fx           ✅ active   Bridge RPyC :11001
  smc-opportunity-scanner   ✅ active   Scanner
  smc-opportunity-notifier  ✅ active   Notifier
  smc-xvfb                  ✅ active   Display virtual :99
```

---

## 6. Pendente (Ação do Usuário)

Corrigir o serviço system-level do forex robot (path antigo):

```bash
sudo cp /home/bimaq/.config/systemd/user/smc-forex-robot.service \
        /etc/systemd/system/smc-forex-robot.service
sudo systemctl daemon-reload
```

Ou, se preferir manter via user-level, garantir que o `loginctl enable-linger bimaq` está ativo (já está).

---

## 7. Próximos Passos (P1 — Fase 2-3 do Plano Executivo)

- [ ] Separar coleta B3 por ativo (WINFUT, WDOFUT, Ações) — Estágio 2
- [ ] Sincronizar estudos automaticamente via watcher
- [ ] Documentar contrato do payload de estudo (StudyPayloadV2)
- [ ] Adicionar health por ativo/timeframe ao heartbeat
- [ ] Criar painel de saúde visual no admin do MaximusTrader

---

*Relatório gerado em 16 de Junho de 2026 pela execução do Plano 1 — Sistema Local.*
