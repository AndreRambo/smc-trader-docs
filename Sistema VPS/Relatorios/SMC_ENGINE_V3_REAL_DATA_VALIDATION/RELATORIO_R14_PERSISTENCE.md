# RELATÓRIO R14 — PERSISTÊNCIA V3
## Validação da Infraestrutura Existente com Dados Reais

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Nenhum arquivo de código alterado nesta fase** — apenas validação da infraestrutura já existente (`technical_engine/smc_engine_v3/incremental/persistence/`) com dados reais e o novo `pipeline_v3.py`.

---

## 1. Descoberta: Infraestrutura Já Existente e Madura

Diferente das fases anteriores, a Fase R14 não exigiu construção do zero — `technical_engine/smc_engine_v3/incremental/persistence/` (1.012 linhas: `schema.py`, `repositories.py`, `backfill.py`, `adapter.py`, `replay.py`) já implementa **todos** os requisitos do plano:

| Requisito do plano | Implementação existente |
|---|---|
| Tabelas V3 separadas | `smc_v2_engine_runs`, `smc_v2_structures`, `smc_v2_structure_events`, `smc_v2_checkpoints`, `smc_v2_active_stream_versions`, `smc_v2_reconciliation` — nenhuma referência a `technical_engine_smc_v2_*_shadow` (confirmado via grep) |
| Migrations | `migrations/20260629_smc_v2_incremental_schema.sql` + `schema.py` (SQLite-compatível, idempotente) |
| Rollback | `drop_schema()` |
| FKs | Presentes em `structures`→`engine_runs`, `structure_events`→`structures`/`engine_runs` |
| IDs determinísticos | `structure_id`/`event_id` são hashes SHA-256; `run_id` derivado deterministicamente de `(asset_id, timeframe, parameter_hash, first_candle_id, last_candle_id)` |
| Hashes / conflicts explícitos | `PersistenceConflictError` levantado quando `structure_id`/`event_id` já existe com payload de hash diferente |
| Transaction | `BackfillRunner.write_tick_in_transaction()` usa SAVEPOINT por tick |
| Checkpoint | `CheckpointRepository` — snapshot completo do engine a cada N candles |
| Reconciliation | `ReconciliationRepository` + `count_orphan_events()` |
| Restart idempotente | Mesmo `run_id` + mesmos dados → `IDEMPOTENT_MATCH`, sem duplicar linhas |
| Zone events | `StructureEventRepository` |

**Nota sobre naming:** as tabelas usam prefixo `smc_v2_*` (naming herdado, já documentado como P1 desde o R0 addendum) — mas são fisicamente distintas de qualquer tabela `technical_engine_smc_v2_*_shadow` da V2 legada. Confirmado por busca textual: nenhuma referência cruzada.

## 2. Validação com Dados Reais (WINFUT H1 canônico)

### 2.1 Backfill + Restart Idempotente (2.000 candles reais)

```
Run 1: READY, 2000 candles, 4.175 structures, 8.105 events, 10 checkpoints, reconciliation=PASS
Run 2 (mesmo run_id, engine novo, mesmos candles): 0 structures novas, 0 events novos (tudo IDEMPOTENT_MATCH)
run_id idêntico: True
state_hash final idêntico: True
Contagem no banco após ambos os runs: 4.175 structures, 8.105 events (sem duplicação)
```

### 2.2 Checkpoint + Resume (1.000 candles reais, split em 400/600)

```
Run completo sem persistência (400+600 candles contínuos): state_hash final = X
Run parcial persistido (400 candles) + checkpoint + restore em engine novo + processa candles 401-1000: state_hash final = X
Resultado: IDÊNTICO
```

Confirma `CHECKPOINT_RESUME` — resumir de um checkpoint produz exatamente o mesmo estado final que um processamento contínuo.

### 2.3 Detecção de Conflito

```
EngineRunRepository.create(run_id='run1', parameter_hash='hash_A')  -> INSERTED
EngineRunRepository.create(run_id='run1', parameter_hash='hash_B_DIFFERENT')  -> PersistenceConflictError levantado corretamente
```

## 3. Testes de Regressão

```
pytest tests/test_technical_engine/ -q -k "persist or backfill or checkpoint or reconcil"
91 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (239.7s)
```

---

## 4. GATE

```
R14_PERSISTENCE_PASS
```

**Justificativa:**
- Toda a infraestrutura de persistência exigida pelo plano já existia e foi validada end-to-end com dados reais de WINFUT (não sintéticos)
- Restart idempotente confirmado: reprocessar os mesmos 2.000 candles reais não duplica nenhuma linha
- Checkpoint/resume confirmado: hash de estado final idêntico entre execução contínua e execução com resume
- Detecção de conflito confirmada com teste direcionado
- Tabelas V3 fisicamente separadas de qualquer tabela V2 shadow (busca textual sem ocorrências)
- 91 testes de persistência + 2.103 testes de regressão completos, 0 falhas

**Próxima fase:** R15 — Overlays e Auditoria Visual.
