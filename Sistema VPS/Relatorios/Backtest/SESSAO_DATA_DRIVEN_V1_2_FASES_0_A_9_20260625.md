# Relatório Completo — Sessão Data-Driven WINFUT V1.2
# Fases 0–9.1 | 2026-06-25/26

---

## Resumo Executivo

Sessão de ~9 horas que executou as **10 primeiras fases** (0-9.1) do plano Data-Driven WINFUT V1.2, com múltiplos patches de correção e auditorias.

---

## Fases Concluídas (10/15)

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
| FASE 10 | ⏳ | PENDENTE |

---

## Bugs Corrigidos

1. **Bearish realized_r** (V1.1.1): sinal invertido → OB 5min E(R) 0.004R → 0.790R
2. **Simpson's Paradox EMA**: agregação OB+FVG mascara efeito real
3. **Activation model 100%**: Feature Store tem 905 negativos (não apenas touched)
4. **Ledger keys duplicadas**: corrigido

---

## Resultados Principais (OB 5min)

### Baseline
- TP1: 81.8% (VALIDATION: 83.4%)
- Net E(R): +0.779R (VALIDATION: +0.875R)
- PF: 5.32

### Melhor Política
- **CANDIDATE_1**: EMA condicional 0.80R/1.25R
- TP1: 82.5% | Target R: 1.07R | E(R): +0.720R | PF: 5.13

### Grade Fixa R
- 0.80R: TP1=85.5%, E(R)=+0.539R (melhor TP1)
- 1.00R: TP1=83.9%, E(R)=+0.679R (default)
- 1.25R: TP1=80.7%, E(R)=+0.816R (melhor expectancy)

### EMA Context
- Aligned: 88.3% TP1, PF=7.58
- Not aligned: 77.1% TP1, PF=3.38

### Probability Score
- TP1 model: Logistic L2, Brier=0.097 (skill score=0.30 vs baseline)
- 5 policies avaliadas e mapeadas
- Score: tp1_score = round(100 * calibrated_p_tp1)
- Activation: IDENTIFICABLE (905 negativos), mas não treinado

---

## Arquivos Criados (esta sessão)

### Scripts (11)
- `tools/audit_winfut_research_data.py`
- `tools/backfill_elliott_wyckoff_winfut.py`
- `tools/canonicalize_winfut_data.py`
- `technical_engine/data_driven_winfut/outcome_labeler.py`
- `technical_engine/data_driven_winfut/temporal_split.py`
- `technical_engine/data_driven_winfut/feature_store/` (4 arquivos)
- `technical_engine/data_driven_winfut/zone_analysis/zone_analyzer.py`
- `technical_engine/data_driven_winfut/trigger_analysis/trigger_analyzer.py`
- `technical_engine/data_driven_winfut/combination_analysis/combination_analyzer.py`
- `technical_engine/data_driven_winfut/target_research/target_research_analyzer.py`
- `technical_engine/data_driven_winfut/probability_score/probability_score_analyzer.py`

### Relatórios (14)
- `docs/validacoes/DATA_AUDIT_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_2_1_AUDITORIA_OUTCOME_CONTRACT_WINFUT_V1.md`
- `docs/validacoes/PATCH_V1_2_METAS_FUNIL_TRADE_TP1_WINFUT_20260625.md`
- `docs/validacoes/FASE_4_FEATURE_STORE_CAUSAL_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_4_1_AUDITORIA_FINAL_FEATURE_STORE_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_5_1_AUDITORIA_COMPLEMENTAR_ANALISE_ZONAS_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_6_1_AUDITORIA_COMPLEMENTAR_GATILHOS_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_7_COMBINACOES_SMC_ELLIOTT_WYCKOFF_WINFUT_V1_20260626.md`
- `docs/validacoes/FASE_9_1_AUDITORIA_PROBABILIDADE_SCORE_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/ZONE_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/TRIGGER_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/COMBINATION_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/TARGET_RESEARCH_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/PROBABILITY_SCORE_WINFUT_V1.md`

---

## Próximo: FASE 10 — Config Candidata Versionada

Gate: LIBERAR_FASE_10_CONFIG_CANDIDATA_VERSIONADA
