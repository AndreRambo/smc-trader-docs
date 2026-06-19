# BASELINE — PÓS-ROADMAP FECHAMENTO E2E

**Data:** 2026-06-16 21:01 CEST
**Plano:** `PLANO_EXECUTIVO_FECHAMENTO_E2E_SOAK_BETA_SMC_TRADER_7_0.md`
**Branch:** `fix/roadmap-closeout-e2e-soak-v1`

---

## 1. Estado dos Repositórios

| Projeto | Branch | Commit | Status |
|---------|--------|--------|--------|
| Sistema Local | fix/roadmap-closeout-e2e-soak-v1 | ab35fa3 | Clean |
| MaximusTrader | main | 676d427 | Clean |
| App Android | main | 5dbc281 | Clean |

## 2. Ambiente

| Item | Valor |
|------|-------|
| Hostname | vmi3284842 |
| User | bimaq |
| OS | Ubuntu 24.04, kernel 6.8.0-111 |
| Disco | 193 GB, 46% usado, 105 GB livre |
| Memória | 11 Gi, 4.6 Gi disponível |
| Python | 3.12.3 |
| MySQL | 8.0.46 |
| PHP | 8.3.6 |
| Composer | 2.7.1 |
| Node | v24.15.0 |
| Java | OpenJDK 17.0.19 |

## 3. Serviços

| Serviço | Status |
|---------|--------|
| smc-asset-collector@WINFUT | running |
| smc-asset-collector@WDOFUT | running |
| smc-asset-collector@PETR4 | running |
| smc-asset-collector@VALE3 | running |
| smc-asset-collector@ITUB3 | running |
| smc-asset-collector@XAUUSDm | running |
| smc-asset-collector@BTCUSDm | running |
| smc-asset-collector@EURUSDm | running |
| smc-asset-collector@USDJPYm | running |
| smc-asset-collector@XAGUSDm | running |
| smc-asset-collector@ETHUSDm | running |
| smc-candle-event-processor | running |
| smc-mt5linux-b3 | running |
| smc-mt5linux-fx | running |
| smc-mt5-b3-terminal | running |
| smc-mt5-fx-terminal | running |
| smc-xvfb | running |

**11/11 coletores + processor + 4 bridges = 16 serviços ativos**

Legado: TODO desativado (smc-opportunity-scanner, smc-b3-robot, smc-forex-robot, smc-analysis-worker).

## 4. Banco de Dados

| Métrica | Valor |
|---------|-------|
| Eventos PENDING | 238 |
| Eventos PROCESSING | 3 |
| Eventos COMPLETED | 2,253 |
| Eventos FAILED | 16 |
| Bundles | 0 |
| Lifecycle events | 0 |
| SMC V2 FVGs persistidos | 609,549 |
| SMC V2 OBs persistidos | 129,423 |
| SMC V2 BOS/CHOCH | 132,571 |

**Total eventos: 2,510 | Taxa falha: 0.64% (16/2510)**

## 5. Testes

| Suite | Resultado |
|-------|-----------|
| Python (8 suítes) | 851 passed, 1 failed (pré-existente), 3 skipped |
| Laravel routes | 11 endpoints mobile/evidence |
| Laravel migrations | 19 ran |
| Frontend React | Build OK (10.26s) |
| Android APK | Pendente (Fase 5) |

## 6. Lacunas confirmadas

1. 16 eventos FAILED — requer classificação (Fase 1)
2. Scanner `LatestPriceRef` — corrigido no commit `ab35fa3`
3. 238 eventos PENDING represados — processor precisa consumir
4. Bundle = 0 — pipeline integrado mas sem oportunidade detectada
5. Site não deployado — pendente (Fase 4)
6. Android físico não testado — pendente (Fase 5)
7. Soak test não iniciado — pendente (Fase 7)

## 7. Erros conhecidos

| Erro | Classificação | Status |
|------|--------------|--------|
| test_hit_rates_replay_cli | PREEXISTENTE (tabela vazia) | Não bloqueia |
| LatestPriceRef.status | CORRIGIDO (ab35fa3) | ✅ |
| 16 FAILED events | A CLASSIFICAR (Fase 1) | 🔴 |

---

**Status Fase 0:** CONCLUIDO
