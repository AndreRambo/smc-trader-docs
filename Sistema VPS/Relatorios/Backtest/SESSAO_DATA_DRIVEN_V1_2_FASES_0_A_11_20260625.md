# Relatório Completo — Sessão Data-Driven WINFUT V1.2
# Fases 0–11.1 | 2026-06-25/26

---

## Resumo: 12 Fases Concluídas em ~10 horas

| Fase | Status | Gate |
|------|--------|------|
| FASE 0 | ✅ DONE | PRONTO_PARA_AUDITORIA |
| FASE 1 | ✅ DONE | PRONTO_COM_DATASET_CANONICAL |
| FASE 2 | ✅ DONE_REEXECUTED | CORRIGIDO_BUG_EXPECTANCY_R |
| FASE 3 | ✅ REVALIDATED | PRONTO_COM_TEMPORAL_SPLIT |
| FASE 4 | ✅ DONE_AUDITED | PRONTO_COM_FEATURE_STORE |
| FASE 5 | ✅ DONE_AUDITED | PRONTO_COM_ANALISE_ZONAS |
| FASE 6 | ✅ DONE_AUDITED | PRONTO_COM_GATILHOS |
| FASE 7 | ✅ DONE | PRONTO_COM_COMBINACOES |
| FASE 8 | ✅ DONE | PRONTO_COM_TP1_POR_CONTEXTO |
| FASE 9 | ✅ DONE_AUDITED | PRONTO_COM_PROBABILIDADE_E_SCORE |
| FASE 10 | ✅ DONE_HARDENED | PRONTO_COM_CONFIG_CANDIDATA |
| FASE 11 | ✅ DONE_AUDITED | PRONTO_COM_BACKTEST_BASELINE_VS_CANDIDATA |

---

## Backtest Final (OB M5, 5945 zones)

| Policy | TP1% | Net E(R) | PF | Status |
|--------|------|----------|-----|--------|
| **CANDIDATE_1 (EMA 0.80/1.25R)** | **82.4%** | **+0.699R** | **4.98** | **PRIMARY** |
| CANDIDATE_2 (All 1.00R) | 83.9% | +0.679R | 5.24 | SHADOW |

---

## Bugs Corrigidos Nesta Sessão

1. Bearish realized_r (V1.1.1)
2. Simpson's Paradox EMA
3. Activation model 100% trivial (905 negativos)
4. EMA alignment not computed in backtest (CANDIDATE_1 = CANDIDATE_5 bug)
5. Schema JSON missing
6. Model references incomplete
7. Timestamps not UTC
8. Lock hashes truncated

---

## Próximo: FASE 12 — Walk-Forward e Robustez
