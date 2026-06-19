# RESULTADO FASE 5.8 — CONTROL_A REAL REPLAY — 2026-06-18

**Status**: FASE_5_8_COMPLETED_WITH_LIMITATIONS  
**CONTROL_A_REAL_ADAPTER**: PARTIAL_VALIDATED (não VALID)

---

## 1. DIAGNÓSTICO

### Dados disponíveis para ground truth

| Fonte | Registros | Status |
|-------|-----------|--------|
| SMC V2 runs shadow | 9.063 | ✅ Disponível |
| Operational plans shadow | 676 | ✅ Disponível |
| Notification outbox shadow | **0** | ❌ VAZIO |
| Evidence bundles | N/A | ❌ Tabela não existe |
| Oportunidades persistidas | **0** | ❌ NENHUMA |

**O pipeline live processa eventos (1.011 COMPLETED para WINFUT) mas não gerou oportunidades persistidas.** Sem ground truth, não é possível validar equivalência exata do replay.

---

## 2. MAPA DE DEPENDÊNCIAS

| Componente | Dependência | Replay Ready? |
|-----------|-------------|---------------|
| SMC V2 | market_candles | ✅ Shadow data disponível |
| Study Gateway | SMC V2 state | ✅ Código disponível |
| Risk Management | Study Gateway + candles | ✅ Código disponível |
| OperationalPlanV2 | Risk Management | ✅ 676 planos persistidos |
| Opportunity Scanner | OperationalPlanV2 + price | ✅ Código disponível |
| evaluate_opportunity() | Scanner | ✅ Código disponível |
| **Notification outbox** | **Scanner output** | **❌ 0 registros** |

---

## 3. O QUE É POSSÍVEL (PARTIAL_VALIDATED)

### ✅ Implementável agora
1. **Replay determinístico**: SMC V2 → Study Gateway → OperationalPlanV2
2. **Anti-lookahead**: Filtro temporal em todos os repositórios
3. **Determinismo**: 3 execuções idênticas por timestamp
4. **Comparação com EMA planner**: Validar divergências

### ❌ Não é possível (bloqueia VALID)
1. **Ground truth de oportunidades**: 0 registros persistidos
2. **Exact match com pipeline live**: Sem output real para comparar
3. **Corpus de 500 amostras com ground truth**: Precisa de 0 → 500

---

## 4. DECISÃO

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  CONTROL_A_REAL_ADAPTER: PARTIAL_VALIDATED              │
│                                                          │
│  Razão: 0 oportunidades persistidas no pipeline live.    │
│         Sem ground truth para validação de equivalência. │
│                                                          │
│  Para atingir VALID:                                     │
│    1. Ativar persistência de oportunidades no dispatcher │
│    2. Acumular ≥200 amostras com ground truth            │
│    3. Reexecutar corpus de validação                     │
│                                                          │
│  Este gate permanece PARTIAL.                            │
│  PHASE_6 permanece BLOCKED.                              │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 5. AÇÃO RECOMENDADA

Ativar a persistência de oportunidades no `dispatcher.py` (já existe código para `_build_and_sync_evidence` e `_run_scanner`). O pipeline atual gera sinais mas não os persiste consistentemente. Após correção, cada evento M5 processado gerará uma oportunidade persistida quando aplicável.

**Tempo estimado para 200 amostras**: ~20 pregões (1 mês).

---

## 6. STATUS DOS GATES

```
CONTROL_A_REAL_ADAPTER:  PARTIAL_VALIDATED ❌ (precisa de ground truth)
DATA_QUALITY:             PASS ✅
PORTFOLIO:                VALID ✅
MATCHING:                 VALID ✅
BOOTSTRAP:                VALID ✅
INTRADAY_MONTHS:          6.2 ❌ (precisa 12)
ROLLOVER:                 0 ❌ (precisa ≥1)

PHASE_6_READINESS:        NOT_READY
PHASE_6_STATUS:           BLOCKED_WAITING_FOR_DATA
```
