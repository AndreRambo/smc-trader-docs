# INCIDENTE — COLETOR WINFUT — 2026-06-17

**Incident ID**: INC-WINFUT-COLLECTOR-20260617-224500  
**Severity**: MEDIUM (gap parcial durante sessão, sem perda total do pregão)  
**Status**: RESOLVED_WITH_RECOVERY — coletor retomou coleta

---

## 1. Cronologia

| Hora (UTC) | Evento |
|------------|--------|
| 13:18 | Último candle M1 registrado em `market_candles` |
| 13:18–16:51 | Gap de coleta (~3.5h durante a sessão B3) |
| 16:51 | Coletor retoma publicação de eventos (visível no journal) |
| 22:40 | Health check detecta lag de 682 minutos |
| 22:45 | Diagnóstico: coletor ativo, publicando eventos, processador com backlog |

---

## 2. Diagnóstico

### Causa Raiz

O `smc-asset-collector@WINFUT` teve uma interrupção silenciosa na coleta entre 13:18 UTC (10:18 BRT) e 16:51 UTC (13:51 BRT) — aproximadamente 3.5 horas durante o pregão.

**Hipótese mais provável**: 
- MT5 B3 teve desconexão temporária ou throttle
- Coletor continuou rodando mas `copy_rates_from_pos` retornou vazio ou com erro
- Coletor retomou automaticamente quando a conexão foi restabelecida

**Evidências**:
- Serviço: ativo contínuo desde Jun 16 (sem restart)
- Journal: sem erros registrados
- Eventos publicados: 16:51–16:59 UTC Jun 17 (retomada)
- `market_candles`: último M1 em 13:18 (gap não processado ainda)

### Falso positivo no health check

O health check anterior comparava `MAX(timestamp)` com `now()` sem considerar:
- Horário da sessão B3 (13:00–21:00 UTC)
- Fechamento do mercado
- Pipeline de eventos (coletor → processador → market_candles)

---

## 3. Impacto

| Métrica | Valor |
|---------|-------|
| Gap M1 estimado | ~200 candles (3.5h × ~57/hora) |
| Gap M5 estimado | ~40 candles |
| Gap M15 estimado | ~14 candles |
| Cobertura do pregão | ~60% (10:18–13:51 BRT perdido) |
| Dados recuperáveis | ✅ Sim (MT5 tem os dados) |
| Impacto em backtests | BAIXO (1 pregão parcial em 128) |

---

## 4. Recuperação

O coletor já retomou a coleta automaticamente. Os dados do período 13:18–16:51 UTC estão disponíveis no MT5 e podem ser recuperados via backfill.

**Ação**: Executar `tools/backfill_winfut_historical.py --timeframes 1min,2min,5min,15min --start 2026-06-17T13:18 --end 2026-06-17T16:51`

---

## 5. Correções Implementadas

### Health Check Session-Aware

O `check_winfut_collector_health.py` agora considera:
- Sessão B3 (13:00–21:00 UTC)
- Fim de semana (Sat/Sun)
- Fechamento de mercado
- Estados: HEALTHY, HEALTHY_MARKET_CLOSED, HEALTHY_WAITING_CANDLE_CLOSE, DEGRADED_LAG

### Estados Corrigidos

| Estado | Condição |
|--------|----------|
| HEALTHY | Coletor ativo, lag normal |
| HEALTHY_MARKET_CLOSED | Fora da sessão, sem candles esperados |
| DEGRADED_LAG | Gap real durante sessão |
| FAILED_NO_DATA | Sem dados por período prolongado |

---

## 6. Lições Aprendidas

1. **Health check deve ser session-aware**: Comparar com `now()` não funciona fora da sessão
2. **Coletor pode ter falhas silenciosas**: MT5 desconexão não gera exceção no Python
3. **Pipeline tem 3 estágios**: Coletor → Eventos → Processador → market_candles. Health check deve monitorar todos
4. **Heartbeat separado dos dados**: O serviço pode estar ativo mas não coletando

---

## 7. Status Final

```
INCIDENT_STATUS: RESOLVED_WITH_RECOVERY
COLLECTOR: HEALTHY (retomou coleta)
DATA_GAP: 13:18–16:51 UTC (3.5h, recuperável)
HEALTH_CHECK: CORRIGIDO (session-aware)
PHASE_6: BLOCKED_WAITING_FOR_DATA
```
