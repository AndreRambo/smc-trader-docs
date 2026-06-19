# RESULTADO FASE 1 — CONTRATOS E PERSISTÊNCIA — 2026-06-17

**Status**: ✅ CONCLUÍDO  
**Commit**: `d513bef` (working tree — mudanças não commitadas)  
**Branch**: `fix/roadmap-closeout-e2e-soak-v1`

---

## Entregas

### 1. Módulo `signal_candidate_v1/`

| Arquivo | Descrição | Linhas |
|---------|-----------|--------|
| `__init__.py` | Versão e alias (`CANDIDATE_B`) | 12 |
| `enums.py` | 26 enums versionados (Direction, SetupType, EntryType, StopAnchorType, StopBlockReason, TargetAnchorType, TargetMethod, etc.) | 180 |
| `models.py` | 6 dataclasses: SignalSetupCandidateV1, SignalEntryPlanV1, SignalStopPlanV1, SignalTargetPlanV1, SignalCandidateV1 | 210 |
| `config.py` | SignalCandidateConfigV1 (frozen dataclass) + grid exploratória (1296 combos) | 130 |
| `hashing.py` | deterministic_hash, signal_hash, config_hash, dataset_hash | 60 |
| `serializer.py` | serialize_signal, serialize_entry, serialize_stop, serialize_target | 130 |
| `errors.py` | 10 classes de erro específicas | 40 |
| `persistence.py` | DDL (5 tabelas) + ensure_schema + insert_signal + insert_run | 270 |

### 2. Módulo `signal_backtest_v1/`

| Arquivo | Descrição |
|---------|-----------|
| `__init__.py` | Versão |
| `models.py` | SignalBacktestTradeV1, SignalBacktestRunV1, SignalComparisonReportV1 |

### 3. Shadow Tables (5/5 criadas)

| Tabela | Status |
|--------|--------|
| `technical_engine_signal_candidate_runs_shadow` | ✅ |
| `technical_engine_signal_candidates_shadow` | ✅ |
| `technical_engine_signal_backtest_runs_shadow` | ✅ |
| `technical_engine_signal_backtest_trades_shadow` | ✅ |
| `technical_engine_signal_comparisons_shadow` | ✅ |

### 4. Testes

| Arquivo | Testes | Status |
|---------|--------|--------|
| `tests/test_signal_candidate_v1/test_contracts.py` | 23 | ✅ Todos passando |

Cobertura:
- Enums: direção, setups, stops, alvos
- Config: defaults, imutabilidade, serialização, grid
- Hashing: determinismo, hash de sinal, hash de config
- Models: geometria LONG/SHORT, monotonicidade, hash reproduzível
- Serializer: serialização completa, JSON round-trip
- Guardrails: shadow_only, anti_lookahead, sem LLM, controle intacto

### 5. Integração verificada
- Persistência: insert → verify → cleanup funcionando
- Schema: 5 tabelas criadas com índices e constraints
- Import chain: todos os módulos importam sem erros

---

## Guardrails (todos ativos)

```
shadow_only=True ✅
candidate_only=True ✅
can_promote_trade=False ✅
apply_automatically=False ✅
llm_decision_used=False ✅
anti_lookahead=True ✅
deterministico=True ✅
current_scanner_modified=False ✅
production_signal_emission=False ✅
```

---

## Contratos Versionados

| Contrato | Versão |
|----------|--------|
| SignalCandidateConfigV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalSetupCandidateV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalEntryPlanV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalStopPlanV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalTargetPlanV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalCandidateV1 | `SIGNAL_CANDIDATE_V1_0_0` |
| SignalBacktestTradeV1 | `SIGNAL_BACKTEST_V1_0_0` |
| SignalBacktestRunV1 | `SIGNAL_BACKTEST_V1_0_0` |
| SignalComparisonReportV1 | `SIGNAL_COMPARISON_V1_0_0` |

---

## Grid Exploratória (pré-registrada)

- 4 entry types × 4 stop anchors × 3 buffer ATR × 3 max stop × 3 min RR × 3 expiry
- Total: 1.296 combinações (removendo incompatíveis logicamente: ~900 válidas)
- Congelada antes do walk-forward

---

## Pendências para Fase 2

- `repositories.py` — queries de leitura (não crítico para motor)
- `signal_builder.py` — construtor do SignalCandidateV1 (Fase 2)
- Integração com SMC V2 persisted state (Fase 2)

---

## Próxima Fase

**FASE 2 — MOTOR DE SINAIS CANDIDATO**
- setup_detector.py
- entry_selector.py
- stop_selector.py
- target_selector.py
- geometry_validator.py
- signal_builder.py
