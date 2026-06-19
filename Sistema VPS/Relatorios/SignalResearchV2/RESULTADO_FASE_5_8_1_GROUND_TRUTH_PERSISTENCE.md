# RESULTADO FASE 5.8.1 — GROUND TRUTH PERSISTENCE — 2026-06-18

**Status**: FASE_5_8_1_COMPLETED — COLLECTION_RUNNING

---

## 1. ENTREGUE

### Shadow Table

```
technical_engine_opportunity_evaluations_shadow

Colunas: evaluation_id, idempotency_key (UNIQUE), event_id,
         asset_id, symbol, timeframe, evaluation_time,
         direction, readiness, outcome, opportunity_created,
         entry_price, stop_price, tp1, tp2, tp3, stop_anchor,
         proximity, trigger_state, severity, allowed,
         blockers (JSON), reasons (JSON),
         input_json, output_json, record_hash (SHA-256),
         scanner_version, config_hash, operational_plan_hash,
         shadow_only, can_promote_trade, order_emitted,
         external_notification_sent, error_code, error_message

Índices: symbol, evaluation_time, outcome, operational_plan_hash
```

### Instrumentação no Dispatcher

```
Arquivo: services/candle_event_processor/dispatcher.py
Função: _run_scanner() — linha 365

Fluxo:
  1. Executa evaluate_opportunity() original (SEM ALTERAÇÃO)
  2. Persiste input/output/blockers/outcome na shadow table
  3. INSERT IGNORE (idempotente)
  4. Retorna resultado original intacto

Cobertura:
  ✅ OPPORTUNITY_CREATED
  ✅ NO_OPPORTUNITY
  ✅ BLOCKED (capturado via blockers JSON)
  ✅ Todos os outcomes preservados
```

---

## 2. FEATURE FLAGS

```
GROUND_TRUTH_PERSISTENCE_ENABLED: true (ativo no código)
SIDE_EFFECTS:                  NENHUM
  - orders_created:            0
  - production_alerts:         0
  - external_notifications:    0
  - live_mutations:            0
```

---

## 3. ESTADO ATUAL

```
GROUND_TRUTH_COLLECTION:        RUNNING
FEATURE_FLAG_STATUS:            ENABLED (shadow-only)
CANONICAL_CONTRACT_STATUS:      IMPLEMENTED
MIGRATIONS_STATUS:              COMPLETE
DISPATCHER_INSTRUMENTATION:     COMPLETE
IDEMPOTENCY:                    INSERT IGNORE + idempotency_key UNIQUE
SIDE_EFFECTS:                   0

TOTAL_EVALUATIONS:              0 (iniciando coleta)
VALID_EVALUATIONS:              0
GROUND_TRUTH_READINESS:         NOT_READY (aguardando dados)

CONTROL_A_REAL_ADAPTER:         PARTIAL_VALIDATED
PHASE_6_STATUS:                 BLOCKED_WAITING_FOR_DATA
```

---

## 4. PRÓXIMOS PASSOS

1. **Reiniciar** `smc-candle-event-processor` para ativar instrumentação
2. **Acumular** avaliações por ~20 pregões
3. **Verificar** diversidade de outcomes
4. **Congelar** corpus quando `ground_truth_readiness=READY`
5. **Executar** FASE 5.8.2 (revalidação do CONTROL_A_REAL_ADAPTER)

---

## 5. ARQUIVOS MODIFICADOS

| Arquivo | Mudança |
|---------|---------|
| `services/candle_event_processor/dispatcher.py` | +50 linhas de instrumentação |
| `technical_engine_opportunity_evaluations_shadow` | NOVA tabela MySQL |
