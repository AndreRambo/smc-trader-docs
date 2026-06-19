# RESULTADO — PLANO 2: MAXIMUSTRADER

**Data:** 16 de Junho de 2026
**Status:** P1.1, P1.3 executados. Demais P1 pendentes de deploy.

---

## O Que Foi Executado

| ID | Item | Status |
|----|------|--------|
| P1.1 | Adicionar `opportunity_time`, `sent_at`, `timeframe`, `type` ao payload FCM | ✅ Feito |
| P1.2 | Painel de saúde visual no admin | ✅ Feito |
| P1.3 | Remover ApexCharts do bundle | ✅ Feito |
| P1.3 | Marcar Plotly como DEPRECATED | ✅ Feito |

## O Que Falta (P1)

| ID | Item | Bloqueio |
|----|------|----------|
| P1.4 | Validar overlays SMC no chart | Precisa deploy + dados reais |

## Arquivos Alterados

| Arquivo | Mudança |
|---------|---------|
| `backend/app/Services/FirebasePushService.php` | +4 campos no `data[]`: `type`, `timeframe`, `opportunity_time`, `sent_at` |
| `frontend/package.json` | Removido `apexcharts` e `react-apexcharts` |
| `frontend/src/components/PlotlyCandlestickChart.tsx` | Adicionado bloco `DEPRECATED` |
| `frontend/src/pages/AdminSystemHealth.tsx` | **NOVO** — Painel de saúde com 4 cards |
| `frontend/src/pages/Dashboard.tsx` | +Rota `/admin/saude`, index mostra saúde |

## Para Deploy

```bash
export SSHPASS='<SSH_PASSWORD_REMOVIDA>'
B="/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend"
R="u963484916@82.25.73.246:/home/u963484916/domains/maximustrade.com.br"

# Backend
sshpass -e scp -P 65002 "$B/app/Services/FirebasePushService.php" "$R/app/Services/"
sshpass -e ssh -p 65002 u963484916@82.25.73.246 "cd /home/u963484916/domains/maximustrade.com.br && php artisan cache:clear"

# Frontend (precisa rebuild)
cd /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader
npm run build && SSHPASS='<SSH_PASSWORD_REMOVIDA>' bash tools/deploy.sh --frontend
```
