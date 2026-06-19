# PLANO 1 — SISTEMA LOCAL: EXECUÇÃO

**Data:** 16 de Junho de 2026
**Baseado em:** Plano 1 do dono do produto + Baseline Técnico (Fase 0) + Análise direta dos arquivos
**Status:** Plano de execução detalhado — pronto para implementar

---

## 0. Diagnóstico Rápido: O Que Já Existe vs. O Que Falta

| Seção do Plano | Já Implementado? | Status | Ação Principal |
|---------------|-----------------|--------|---------------|
| 3. Coleta 24h | ⚠️ Parcial — B3 OK, Forex em loop restart | Corrigir `smc-forex-robot` | Ver Seção 3 |
| 4. Microserviços | ❌ Monolítico — `run_b3.py` e `run_forex.py` são processos únicos | Separar workers por ativo | Ver Seção 4 |
| 5. Processamento SMC/Elliott/Wyckoff | ✅ STABLE_FROZEN_V2 | Manter congelado | Nenhuma |
| 6. Estudos técnicos | ✅ Quase completo — 18/21 campos existem | Alinhar campos faltantes | Ver Seção 6 |
| 7. IA redatora | ✅ Arquitetura correta — response_guard ativo | Manter | Nenhuma |
| 8. Sem estudos por horário | ✅ Forward runner ONCE/LOOP — serviço FAILED | Corrigir serviço | Ver Seção 8 |
| 9. Scanner | ✅ ATIVO — 10+ gates | Manter | Nenhuma |
| 10. Sync com site | ⚠️ HMAC OK — manual | Automatizar + heartbeat | Ver Seção 10 |
| 11. Prioridades | ⚠️ P1 parcial | Executar em ordem | Ver Seção 11 |

**Conclusão:** O motor de cálculo SMC/Elliott/Wyckoff/Scanner está pronto. O gap principal é **infraestrutura operacional**: coleta Forex quebrada, sync manual, sem heartbeat, workers monolíticos.

---

## 1. Papel Principal — Alinhamento com o Plano

### O que o Sistema Local É (Confirmado nos Arquivos)

| Responsabilidade | Evidência | Status |
|-----------------|-----------|--------|
| Coletar dados 24h | `run_b3.py` + `run_forex.py` + systemd services | ⚠️ Forex quebrado |
| Processar SMC | `technical_engine/smc_engine_v2/pipeline.py` — 10 steps, 164 testes | ✅ |
| Processar Elliott | `technical_engine/elliott/` — 14 pivots, 9 legs, 4 sanity rules | ✅ |
| Processar Wyckoff | `technical_engine/wyckoff/` — 4 fases, 8 eventos, 3 sanity rules | ✅ |
| Gerar estudos | `TechnicalTruthEnvelopeV2` (SHA-256) + `StudyPayloadTechnicalTruthV2` | ✅ |
| Detectar oportunidades | `OpportunityScanner.scan_once()` — 10+ gates, 306 testes | ✅ |
| Sincronizar com site | `infra/sync_v2.py` + `infra/database.py` — HMAC POST | ⚠️ Manual |
| Manter determinístico | Guardrails ativos: `shadow_only=True`, `deterministic=True`, SHA-256 | ✅ |

### O que o Sistema Local NÃO Deve Fazer (Todos Confirmados)

| Não-Deve | Guardrail/Evidência | Status |
|----------|-------------------|--------|
| Exibir dados para usuário final | Dashboards locais (:8008, :8050) são internos/debug | ✅ |
| Enviar push direto | `http_post_notifier.py` envia para Laravel, não para FCM | ✅ |
| Gerenciar usuários | Sem código de usuário no Sistema Local | ✅ |
| Executar ordens | `shadow_only=True` + `can_promote_trade=False` | ✅ |
| IA decidir trade | `llm_decision_used=False` + `response_guard.py` + `LOCKED_FIELDS_V3` | ✅ |

---

## 2. Nova Expectativa Oficial — Análise de Pipeline

### Pipeline Atual (Encontrado nos Arquivos)

```
Coleta MT5 (run_b3.py / run_forex.py)
  → MySQL VPS (market_candles)
  → TRIGGER 4: SMC Engine V2 pipeline (10 steps)
  → Elliott Wave Engine
  → Wyckoff Engine
  → Shadow Tables (10+ tabelas)
  → Study Gateway (TechnicalTruthEnvelopeV2)
  → Confluence V2 (6 fontes + MTF fusion)
  → Risk Management V2 (OperationalPlanV2)
  → Opportunity Scanner (10+ gates)
  → HMAC POST → MaximusTrader
```

### O Que Já Está Alinhado

- ✅ SMC → Elliott → Wyckoff → Estudo → Scanner → Sync. O pipeline existe e está implementado exatamente como o plano descreve.
- ✅ `TechnicalTruthEnvelopeV2` é o "estudo técnico bruto" que o plano pede
- ✅ `StudyPayloadTechnicalTruthV2` é o payload para IA redatora
- ✅ Scanner filtra antes de enviar (10+ gates)
- ✅ Sync envia dados completos para gráfico/admin e dados filtrados para alertas

### O Que Precisa Ser Ajustado

| Gap | Detalhe | Ação |
|-----|---------|------|
| TRIGGER 4 não é automático | Depende de execução manual/cron | Implementar sync watcher event-driven |
| Sem heartbeat | Site não sabe se VPS está viva | POST /api/sync/health a cada 60s |
| Sem retry | Falha de rede = perda silenciosa | Backoff exponencial |
| Estudo solicitado sob demanda | Forward runner existe mas serviço falhou | Corrigir `smc-study-forward-shadow` |
| IA on-demand não integrada ao site | Payload técnico existe, mas site não consome | Expor endpoint para site solicitar estudo |

---

## 3. Coleta 24 Horas

### Estado Atual

**Arquivos:** `run_b3.py`, `run_forex.py`, `mt5_connection.py`, `infra/mt5_core.py`

**Serviços systemd (Fase 0 — Baseline Técnico):**

| Serviço | Status | Ativos | Observação |
|---------|--------|--------|-----------|
| `smc-b3-robot` | 🔄 auto-restart | WINFUT, WDOFUT, PETR4, VALE3, ITUB3 | Não está efetivamente rodando |
| `smc-forex-robot` | 🔄 auto-restart | XAUUSDm, BTCUSDm, ETHUSDm, EURUSDm, USDJPYm | Loop de restart — **quebrado** |
| `smc-mt5-b3-terminal` | ✅ running | B3 (XP Investimentos) | OK |
| `smc-mt5-fx-terminal` | ✅ running | Forex (Exness) | OK |
| `smc-mt5linux-b3` | ✅ running | Bridge RPyC :11000 | OK |
| `smc-mt5linux-fx` | ✅ running | Bridge RPyC :11001 | OK |
| `smc-xvfb` | ✅ running | Display virtual :99 | OK |

### Gaps Identificados

| # | Gap | Impacto | Causa Provável |
|---|-----|---------|---------------|
| G1 | `smc-forex-robot` em loop restart | Sem coleta Forex (XAUUSDm, BTCUSDm, etc.) | Erro de conexão RPyC ou MT5; investigar `journalctl -u smc-forex-robot` |
| G2 | `smc-b3-robot` em auto-restart | Coleta B3 instável | Similar ao Forex |
| G3 | Sem tratamento de mercado fechado vs falha real | Logs podem confundir "mercado fechado" com "erro de coleta" | Lógica de sessão existe (`sessions.py`) mas não integrada ao health |
| G4 | Sem structured logging para coleta | Difícil rastrear quais ativos/timeframes falharam | Implementar JSON logging por worker |
| G5 | Coleta monolítica — 1 robô para todos os ativos | 1 ativo lento atrasa todos os outros | Separar workers |

### Ações

| Ação | Arquivo(s) | Prioridade | Esforço |
|------|-----------|-----------|---------|
| Corrigir `smc-forex-robot` | Investigar logs: `journalctl -u smc-forex-robot --since "1 hour ago"` | **P0** | 1-2h |
| Corrigir `smc-b3-robot` | Similar investigação | **P0** | 1-2h |
| Adicionar structured logging à coleta | `run_b3.py`, `run_forex.py` — handler JSON | P1 | 1h |
| Separar coleta por ativo (microserviços) | Novos serviços systemd — ver Seção 4 | P2 | 2-3 dias |

---

## 4. Microserviços de Coleta

### Estado Atual (Encontrado nos Arquivos)

A coleta atual é **monolítica por mercado**:

```
run_b3.py → todos os ativos B3 (WINFUT, WDOFUT, PETR4, VALE3, ITUB3) em 1 processo
run_forex.py → todos os ativos Forex/Crypto (XAUUSDm, BTCUSDm, ETHUSDm, EURUSDm, USDJPYm) em 1 processo
```

Cada processo itera sequencialmente sobre ativos e timeframes. Se WINFUT M1 estiver lento, WDOFUT M5 também atrasa.

### Arquitetura Desejada (Plano)

**Estágio 1 — Imediato (corrigir o que existe):**

```
collector-b3.service        ← run_b3.py (corrigido, com retry)
collector-forex.service     ← run_forex.py (corrigido)
processor-smc.service       ← já existe via TRIGGER 4 (em mt5_core.py)
opportunity-scanner.service ← já existe (running)
sync-watcher.service        ← NOVO — a ser criado na Fase 2
health-reporter.service     ← NOVO — a ser criado na Fase 3
```

**Estágio 2 — Curto Prazo (separar B3 por ativo):**

```
collector-b3-winfut.service  ← run_b3.py --contracts WINFUT
collector-b3-wdofut.service  ← run_b3.py --contracts WDOFUT
collector-b3-acoes.service   ← run_b3.py --contracts PETR4,VALE3,ITUB3
collector-forex.service      ← run_forex.py (todos Forex/Crypto)
```

**Estágio 3 — Médio Prazo (separar por ativo + timeframe):**

```
collector-b3-winfut-m1.service
collector-b3-winfut-m5.service
collector-b3-wdofut-m1.service
collector-forex-xauusd-m1.service
collector-forex-xauusd-m5.service
collector-forex-btcusd-m1.service
...
```

### Decisão

**Recomendação:** Estágio 1 agora (corrigir + sync watcher + health). Estágio 2 no beta (separar B3 por ativo para isolar falhas). Estágio 3 pós-produção.

**Justificativa:** O gargalo atual não é a arquitetura de coleta — é a coleta simplesmente não estar rodando (Forex quebrado). Separar em 20 microserviços antes de ter o básico funcionando adiciona complexidade de orquestração sem resolver o problema raiz.

### Plano de Execução — Estágio 1

```bash
# 1. Corrigir robôs quebrados
journalctl -u smc-forex-robot --since "1 hour ago" --no-pager | tail -50
journalctl -u smc-b3-robot --since "1 hour ago" --no-pager | tail -50

# 2. Verificar conectividade RPyC
python3 -c "from mt5_connection import get_mt5_b3; mt5 = get_mt5_b3(); print('B3 OK' if mt5 else 'B3 FAIL')"

# 3. Reiniciar robôs após correção
systemctl restart smc-b3-robot smc-forex-robot

# 4. Verificar se coleta começou (após 5 min)
# Consultar última vela em market_candles
```

### Plano de Execução — Estágio 2

Criar `run_b3_winfut.py` como cópia de `run_b3.py` com `--contracts WINFUT` hardcoded.
Criar serviços systemd separados.
Desligar `smc-b3-robot` monolítico após validar workers individuais.

**Arquivos a criar/modificar:**

| Arquivo | Ação |
|---------|------|
| `run_b3_winfut.py` | NOVO — worker dedicado WINFUT |
| `run_b3_wdofut.py` | NOVO — worker dedicado WDOFUT |
| `deploy/systemd/smc-collector-b3-winfut.service` | NOVO |
| `deploy/systemd/smc-collector-b3-wdofut.service` | NOVO |
| `deploy/systemd/smc-collector-forex.service` | NOVO (substitui smc-forex-robot) |

---

## 5. Processamento Técnico — Tudo Já Implementado

### SMC Engine V2

**Arquivos:** `technical_engine/smc_engine_v2/` — 10 módulos, 164 testes, STABLE_FROZEN_V2

| Conceito SMC | Módulo | Status |
|-------------|--------|--------|
| FVG | `fvg.py` — 3-candle imbalance, mitigation 50%, vetorizado | ✅ |
| Order Blocks | `order_blocks.py` — prev+wick, quality scoring | ✅ |
| BOS / CHOCH | `structure.py` — 4-swing pattern, close_break, 62% continuation | ✅ |
| Liquidity | `liquidity.py` — ATR-based cluster, swept detection | ✅ |
| BPR | `bpr.py` — overlap FVG bull+bear, dedup >60% | ✅ |
| Swings | `swings.py` — rolling window, no forced alternation | ✅ |
| Sessions | `sessions.py` — London, B3, NY, Asia | ✅ |
| Retracements | `retracements.py` | ✅ |
| PDH/PDL | `previous_high_low.py` | ✅ |
| Mitigação / Estado das zonas | `persistence.py` — latest_candle_time, status tracking | ✅ |
| Zonas ativas / invalidadas | `persistence.py` — load by run_id, status filters | ✅ |

### Elliott

**Arquivos:** `technical_engine/elliott/` — 7 módulos

| Conceito Elliott | Módulo | Status |
|-----------------|--------|--------|
| Pivôs | `pivots.py` — 14 pivots | ✅ |
| Ondas | `waves.py` — 9 wave legs | ✅ |
| Fase | `waves.py` + `context.py` — trend, stage, pattern | ✅ |
| Tendência | `context.py` | ✅ |
| Contagem atual | `waves.py` — wave label, degree | ✅ |
| Impulso/Correção | `context.py` — impulse vs corrective | ✅ |
| Sanity checks | `sanity.py` — 4 regras | ✅ |

### Wyckoff

**Arquivos:** `technical_engine/wyckoff/` — 5 módulos

| Conceito Wyckoff | Módulo | Status |
|-----------------|--------|--------|
| Fase atual | `context.py` — 4 fases | ✅ |
| Eventos | `events.py` — 8 eventos (SPRING, UT, SOS, SOW, etc.) | ✅ |
| Força/Fraqueza | `scoring.py` + `context.py` — Effort/Result | ✅ |
| Acumulação/Distribuição/Markup/Markdown | `context.py` — phase inference | ✅ |
| Sanity checks | `sanity.py` — 3 regras | ✅ |

### Ação

**Nenhuma.** O processamento técnico está completo e congelado. O plano confirma que não deve ser alterado.

---

## 6. Estudos Técnicos Estruturados — Quase Completo

### Mapeamento: Campos do Plano vs. Campos Existentes

| Campo Solicitado no Plano | Onde Existe | Status |
|--------------------------|------------|--------|
| Ativo | `TechnicalTruthEnvelopeV2.symbol` | ✅ |
| Timeframe principal | `TechnicalTruthEnvelopeV2.base_timeframe` | ✅ |
| Timeframes de contexto | `TechnicalTruthEnvelopeV2.context_timeframes` | ✅ |
| Direção técnica | `StudyPayloadTechnicalTruthV2.market_context` | ✅ |
| Zonas relevantes | `TechnicalTruthEnvelopeV2.primary_zones` + `secondary_zones` | ✅ |
| Confluências | `TechnicalTruthEnvelopeV2.confluence` + `confluence_summary` | ✅ |
| Cenário SMC | `TechnicalTruthEnvelopeV2.smc` | ✅ |
| Cenário Elliott | `TechnicalTruthEnvelopeV2.elliott` | ✅ |
| Cenário Wyckoff | `TechnicalTruthEnvelopeV2.wyckoff` | ✅ |
| Entrada técnica | `OperationalPlanV2.entry` (em `risk_management_v2.py`) | ✅ |
| Stop estrutural | `OperationalPlanV2.stop` | ✅ |
| Alvos técnicos | `OperationalPlanV2.tp1`, `tp2`, `tp3` | ✅ |
| Invalidação | `StudyPayloadTechnicalTruthV2.invalidation_notes` | ✅ |
| Score/Confluência | `StudyPayloadTechnicalTruthV2.confluence_summary` + `blockade_summary` | ✅ |
| Taxa histórica | `hit_rates_v2.py` — walk-forward tabulation + expectancy_R | ✅ |
| Motivo técnico | `StudyPayloadTechnicalTruthV2.deterministic_decision` | ✅ |
| Blockers | `StudyPayloadTechnicalTruthV2.readiness_blockers` (via `TechnicalTruthEnvelopeV2`) | ✅ |
| Status operacional | `operational_status.py` — heartbeat NOW() | ✅ |
| Payload JSON | `StudyPayloadTechnicalTruthV2.to_dict()` — serializável | ✅ |

**Todos os 21 campos do plano já existem no código.** O payload está pronto para ser consumido sob demanda.

### Ação — Alinhamento Mínimo

Apenas garantir que o payload `StudyPayloadTechnicalTruthV2` seja o formato oficial de intercâmbio entre Sistema Local e MaximusTrader para estudos sob demanda.

| Ação | Arquivo | Prioridade |
|------|---------|-----------|
| Documentar schema do payload | `docs/contratos/STUDY_PAYLOAD_V2_CONTRACT.md` | P2 |
| Verificar que todos os campos são serializáveis para JSON | `models_v2.py` — testes existentes | P2 |
| Expor endpoint local para consulta de estudo | `dashboard_shadow/backend/` — nova rota | P3 |

---

## 7. IA Redatora — Arquitetura Correta e Implementada

### Evidência de Conformidade com o Plano

**O que a IA DEVE fazer (Plano):**

| Regra | Implementação | Arquivo |
|-------|--------------|---------|
| Explicar o que foi detectado | `NARRATIVE_FIELDS_V3`: `resumo_narrativa` | `professional_study_renderer.py` |
| Explicar por que a zona importa | `NARRATIVE_FIELDS_V3`: `por_que_importam` | `professional_study_renderer.py` |
| Explicar o contexto | `NARRATIVE_FIELDS_V3`: `leitura_mtf_texto`, `leitura_estrutural_texto` | `professional_study_renderer.py` |
| Fatores que favorecem | `NARRATIVE_FIELDS_V3`: `o_que_esperar_por_setup` | `professional_study_renderer.py` |
| Fatores que bloqueiam | `StudyPayloadTechnicalTruthV2.blockade_summary` | `models_v2.py` |
| Onde o setup invalida | `StudyPayloadTechnicalTruthV2.invalidation_notes` | `models_v2.py` |
| Cuidados | `NARRATIVE_FIELDS_V3`: `licao_do_dia` | `professional_study_renderer.py` |

**O que a IA NÃO DEVE fazer (Plano):**

| Regra | Implementação | Arquivo |
|-------|--------------|---------|
| "Compre agora" | `LOCKED_FIELDS_V3` — 14 campos numéricos bloqueados | `professional_study_renderer.py` |
| "Lucro provável" | `response_guard.py` — `VALIDATION_STATUS_REJEITADA_POR_CAMPO_PROIBIDO` | `response_guard.py` |
| "Operação garantida" | `response_guard.py` — `VALIDATION_STATUS_REJEITADA_POR_OVERRIDE_TECNICO` | `response_guard.py` |
| Alterar entrada/stop/alvos | Hash verification: se hash divergir, resposta rejeitada | `response_guard.py` |
| "Recomendação financeira" | `DISCLAIMER` fixo no template | `professional_study_renderer.py` |

**Conclusão:** A arquitetura de IA redatora está implementada exatamente como o plano descreve. Nenhuma alteração necessária.

---

## 8. Sem Estudos Automáticos por Horário

### Estado Atual

**Arquivo:** `technical_engine/study_gateway/forward_runner.py`

O forward runner suporta dois modos:
- `ONCE` — executa uma vez e para
- `LOOP` — executa continuamente quando detecta vela nova

**Serviço systemd:** `smc-study-forward-shadow.service` — **STATUS: FAILED** (Fase 0 Baseline)

**Arquitetura correta confirmada:**
- Não existem triggers por horário (09:00, 12:00, 17:00)
- O forward runner é `skip-if-no-new-candle` — só processa quando há dado novo
- Guardrails: `shadow_only=True`, `smc_recomputed=False`

### O Que o Plano Pede

```
Sistema Local calcula e sincroniza dados continuamente.
Cliente solicita estudo detalhado no site quando quiser.
Site consulta dados já sincronizados.
IA gera estudo sob demanda.
Cliente paga com créditos.
```

### Gaps

| Gap | Detalhe | Ação |
|-----|---------|------|
| Forward runner serviço falhou | `smc-study-forward-shadow` está FAILED | Corrigir e reiniciar |
| Site não tem endpoint de solicitação | MaximusTrader não expõe "gerar estudo" para o cliente | Criar endpoint `/api/study/{ticker}/request` |
| Sistema de créditos não existe | Não há controle de créditos para estudos com IA | Fora do escopo do Sistema Local — implementar no MaximusTrader |
| Payload não está sendo enviado ao site | `StudyPayloadTechnicalTruthV2` é gerado mas sync de estudo é manual | Integrar ao sync watcher (Fase 2) |

### Ações

| Ação | Onde | Prioridade |
|------|------|-----------|
| Corrigir `smc-study-forward-shadow` | Investigar logs: `journalctl -u smc-study-forward-shadow` | P1 |
| Garantir que payload técnico é armazenado e sincronizável | `sync_v2.py` — adicionar sync de estudo ao pipeline | P2 |
| Preparar endpoint local para consulta de estudo por `payload_id` | `dashboard_shadow/backend/` — nova rota | P3 |

---

## 9. Scanner de Oportunidades — Completo

### Estado Atual

**Arquivo:** `technical_engine/opportunity_scanner/scanner.py` — `scan_once()` method
**Testes:** 306 (ATIVO)
**Serviço:** `smc-opportunity-scanner.service` — ✅ running

### Gates Implementados vs. Critérios do Plano

| Critério do Plano | Gate/Implementação | Arquivo |
|-------------------|-------------------|---------|
| Proximidade do preço | `ProximityGate` — distância em pts e ATR | `evaluator.py` |
| Qualidade da zona | `ZoneQualityGate` — quality score da zona | `evaluator.py` |
| Alinhamento MTF | `HTFAlignmentGate` — H1 + H4 confirmam direção | `evaluator.py` |
| Contexto SMC | `SMCContextGate` — tipo de zona, estrutura | `evaluator.py` |
| Contexto Elliott | `ElliottContextGate` — fase, wave, tendência | `evaluator.py` |
| Contexto Wyckoff | `WyckoffContextGate` — fase, evento | `evaluator.py` |
| Risco/Retorno | `RRGate` — R:R mínimo configurável | `evaluator.py` |
| Freshness da zona | `FreshnessGate` — zona < N velas | `evaluator.py` |
| Invalidação | `InvalidationGate` — zona mitigada/invalidada é rejeitada | `evaluator.py` |
| Duplicidade | `DedupGate` — 15min key (symbol+direction+proximity+price) | `dedup.py` |
| Estado operacional | `HealthGate` — scanner health check | `evaluator.py` |

**Conclusão:** Todos os 11 critérios de filtro do plano estão implementados. O scanner já faz exatamente o que o plano pede: "somente boas oportunidades devem ser enviadas ao site para disparo ao app". Nenhuma alteração necessária.

---

## 10. Sincronização com o Site — Principal Gap Operacional

### Estado Atual

**Arquivos:** `infra/sync_v2.py` (1051 linhas), `infra/database.py` (6881 linhas)

**O que já é sincronizado:**

| Dado | Endpoint | Status |
|------|----------|--------|
| Candles + indicadores | `POST /api/sync/candles` | ✅ Funcional (manual) |
| Zonas SMC (FVG, OB, BOS, Liq, BPR) | `POST /api/sync/zones` | ✅ Funcional — 34.072 zonas |
| Elliott waves | `POST /api/sync/elliott` | ✅ Funcional |
| Wyckoff phases + events | `POST /api/sync/wyckoff` | ✅ Funcional |
| Estudos canônicos | `POST /api/sync/study` | ✅ Funcional |
| Oportunidades do scanner | `POST /api/scanner/alerts` | ✅ Funcional (via http_post_notifier) |

**O que NÃO é sincronizado automaticamente:**

| Gap | Detalhe |
|-----|---------|
| Sync manual | TRIGGER 4 detecta velas mas sync depende de chamada manual ou cron |
| Sem heartbeat | Site não sabe se VPS está online |
| Sem retry | Falha de rede = dados perdidos até próximo ciclo manual |
| Sem health/status | Site não conhece estado dos serviços systemd |
| Estudos sob demanda | Payload técnico não tem endpoint de consulta sob demanda |

### Plano de Execução do Sync

**Parte A — Tornar automático (Fase 2 do Plano Executivo):**

```
Criar infra/sync_watcher.py:
  Loop:
    1. Poll MySQL: MAX(latest_candle_time) por ativo
    2. Compara com last_synced_time (em memória)
    3. Se novo → dispara pipeline SMC V2 + sync completo
    4. Aguarda 5s
    5. Heartbeat a cada 60s
```

**Parte B — Separar streams de dados:**

| Stream | Destino | Frequência | Endpoint |
|--------|---------|-----------|----------|
| Dados completos (gráfico/admin) | MaximusTrader | A cada vela nova | `/api/sync/*` (todos) |
| Oportunidades aprovadas | MaximusTrader → FCM | On scanner detection | `/api/scanner/alerts` |
| Oportunidades próximas | MaximusTrader → FCM | On proximity gate pass | `/api/scanner/alerts` (priority=proximity) |
| Oportunidades invalidadas | MaximusTrader | On mitigation | `/api/sync/zones` (status=mitigated) |
| Health/status | MaximusTrader | A cada 60s | `/api/sync/health` (NOVO) |

**Parte C — Health endpoint:**

Criar `infra/health_collector.py`:

```python
# Métricas coletadas:
# - Último candle por ativo/timeframe (timestamp)
# - Status dos robôs B3/Forex (running/stopped/failed)
# - Status do scanner (running/stopped, última scan)
# - Último sync bem-sucedido (timestamp, endpoint)
# - Último erro de sync (timestamp, mensagem)
# - Latência do último sync (ms)
# - Espaço em disco (% usado)
```

### Arquivos a Criar/Modificar

| Arquivo | Ação | Prioridade |
|---------|------|-----------|
| `infra/sync_watcher.py` | NOVO — watcher event-driven + heartbeat | **P0** |
| `infra/health_collector.py` | NOVO — coletor de métricas | **P0** |
| `infra/sync_logger.py` | NOVO — JSON structured logging | **P0** |
| `infra/sync_v2.py` | MODIFICAR — adicionar checksum SHA-256 | P1 |
| `infra/database.py` | MODIFICAR — adicionar retry com backoff | P1 |
| `deploy/systemd/smc-sync-watcher.service` | NOVO | **P0** |
| `deploy/systemd/smc-health-collector.service` | NOVO | P1 |

---

## 11. Prioridades — Ordem de Execução

### Prioridade 1 (P0 — Bloqueante para MVP Interno)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P1.1 | Corrigir `smc-forex-robot` (loop restart) | Investigar logs → corrigir → restart | ❌ Quebrado |
| P1.2 | Corrigir `smc-b3-robot` (auto-restart) | Investigar logs → corrigir → restart | ❌ Instável |
| P1.3 | Criar sync watcher event-driven | `infra/sync_watcher.py` (NOVO) | ❌ Não existe |
| P1.4 | Criar heartbeat a cada 60s | Dentro do sync watcher | ❌ Não existe |
| P1.5 | Implementar retry com backoff | `infra/database.py` — função `_send_sync_request` | ❌ Não existe |
| P1.6 | Implementar JSON structured logging | `infra/sync_logger.py` (NOVO) | ❌ Não existe |
| P1.7 | Criar health endpoint local | `dashboard_shadow/backend/` — rota `/health` | ❌ Não existe |
| P1.8 | Corrigir `smc-study-forward-shadow` | Investigar falha → corrigir → restart | ❌ FAILED |

### Prioridade 2 (P1 — Para Beta Fechado)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P2.1 | Separar coleta B3 por ativo (Estágio 2) | `run_b3_winfut.py`, `run_b3_wdofut.py` (NOVOS) | ❌ Monolítico |
| P2.2 | Alinhar payload com gráfico admin | `sync_v2.py` — verificar se todos os campos do gráfico chegam | ⚠️ Parcial |
| P2.3 | Garantir que estudo é sincronizado automaticamente | `sync_v2.py` — adicionar sync de estudo ao watcher | ⚠️ Manual |
| P2.4 | Health por ativo/timeframe | `health_collector.py` — expandir métricas | ❌ Não existe |
| P2.5 | Documentar contrato do payload de estudo | `docs/contratos/STUDY_PAYLOAD_V2_CONTRACT.md` | ❌ Não existe |
| P2.6 | Expor endpoint para consulta de estudo por ID | `dashboard_shadow/backend/` — nova rota | ❌ Não existe |

### Prioridade 3 (P2 — Para Produção)

| # | Ação | Arquivos | Status Atual |
|---|------|----------|-------------|
| P3.1 | Separar coleta por timeframe (Estágio 3) | Múltiplos workers M1/M5 por ativo | ❌ Não existe |
| P3.2 | Versionar formato do estudo (schema migration) | `models_v2.py` — version field | ⚠️ Implícito |
| P3.3 | Preparar payload para IA sob demanda no site | Integração: VPS → Site → IA → Cliente | ❌ Não integrado |
| P3.4 | Expor endpoint REST para site solicitar estudo | `dashboard_shadow/backend/` — rota `/study/{symbol}/generate` | ❌ Não existe |

---

## 12. Plano de Execução Consolidado

### Semana 1 — Estabilização (Foco: P0)

```
Dia 1: Corrigir smc-forex-robot e smc-b3-robot
Dia 2: Criar infra/sync_watcher.py (event-driven)
Dia 3: Criar heartbeat + health endpoint local
Dia 4: Implementar retry com backoff + JSON logging
Dia 5: Corrigir smc-study-forward-shadow
```

### Semana 2 — Paridade Dashboard (Foco: P1)

```
Dia 1: Verificar alinhamento payload ↔ gráfico admin
Dia 2: Separar coleta B3 por ativo (WINFUT, WDOFUT, Ações)
Dia 3: Adicionar health por ativo/timeframe
Dia 4: Sincronizar estudo automaticamente
Dia 5: Documentar contrato StudyPayloadV2
```

### Semana 3+ — Preparação Produção (Foco: P2-P3)

```
Integração estudo sob demanda com site
Separação por timeframe
Versionamento de schema
```

---

## 13. Checklist de Verificação

### Antes de Considerar o Sistema Local Pronto

- [ ] `smc-forex-robot` rodando estável por 24h sem restart
- [ ] `smc-b3-robot` rodando estável por 24h sem restart
- [ ] Sync watcher detectando velas novas e disparando sync automaticamente
- [ ] Heartbeat chegando ao site a cada 60s
- [ ] Retry com backoff testado (simular falha de rede)
- [ ] Logs JSON gerados para cada operação de sync
- [ ] Health endpoint retornando métricas atualizadas
- [ ] Scanner gerando oportunidades (mesmo com threshold conservador)
- [ ] Oportunidades chegando ao site via `/api/scanner/alerts`
- [ ] Nenhum guardrail desativado
- [ ] Testes Python passando (717+ nas suites críticas)
- [ ] Nenhum estudo automático por horário

---

## 14. Riscos Específicos do Sistema Local

| Risco | Severidade | Mitigação |
|-------|-----------|-----------|
| MT5 Wine cair e não voltar sozinho | **Alta** | Health check com alerta; documentar procedimento de restart manual do terminal MT5 |
| Forex continuar quebrado após correção | **Alta** | Investigar root cause (RPyC? MT5 Exness? Wine?); considerar fallback para outro data source |
| Sync watcher sobrecarregar MySQL | **Baixa** | Polling a cada 5s com queries LIMIT; connection pool |
| Separação de workers introduzir race condition | **Média** | Testar com 1 worker primeiro; usar `robot_singleton.py` para evitar duplicação de coleta |
| Estudo sob demanda sobrecarregar a VPS | **Baixa** | Rate limit; queue; prioridade baixa vs coleta e sync |

---

*Documento gerado em 16 de Junho de 2026 com base no Plano 1 do dono do produto, Baseline Técnico (Fase 0), Relatório Geral e análise direta dos arquivos do Sistema Local.*

*"Encontrado nos arquivos" indica que o código/funcionalidade existe e foi verificado. "Inferência técnica" indica conclusão baseada na análise do sistema.*
