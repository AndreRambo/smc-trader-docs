# Relatório Completo — FASE 10 Config Candidata Versionada
# 2026-06-26

## WINFUT_CANDIDATE_V1

**Status:** ✅ CONCLUÍDA
**Gate:** PRONTO_COM_CONFIG_CANDIDATA_VERSIONADA_WINFUT_V1

---

## Config Criada

```
config/data_driven_winfut/candidates/WINFUT_CANDIDATE_V1.yaml
config/data_driven_winfut/candidates/WINFUT_CANDIDATE_V1.lock.json
config/data_driven_winfut/candidates/WINFUT_CANDIDATE_V1.manifest.json
```

---

## Política Primária

**CANDIDATE_1** — EMA condicional 0.80R/1.25R

| Quando | Target R |
|--------|----------|
| EMA ALIGNED | 1.25R |
| EMA NOT_ALIGNED/NEUTRAL/MISSING | 0.80R |

---

## 5 Políticas Mapeadas

| Policy | Regra | TP1 TRAIN | TP1 VAL | Role |
|--------|-------|-----------|---------|------|
| CANDIDATE_1 | EMA→1.25R, Default→0.80R | 81.8% | 83.0% | DEFAULT_CONTEXTUAL |
| CANDIDATE_2 | All 1.00R | 83.4% | 84.7% | CONSERVATIVE |
| CANDIDATE_3 | BUY 1.25R, SELL 0.80R | 82.1% | 83.5% | DIRECTIONAL |
| CANDIDATE_4 | EMA→1.00R, Default→0.70R | 84.2% | 85.7% | HIGH_TP1 |
| CANDIDATE_5 | All 0.80R | 85.0% | 85.7% | CONSERVATIVE_R |

---

## Modelos

| Modelo | Status | Brier Skill |
|--------|--------|-------------|
| Activation | BASE_RATE_ONLY (84.8%) | N/A |
| TP1 (Logistic L2) | CALIBRATED | 0.30 |

---

## Score Schema

```
tp1_score = round(100 * calibrated_p_tp1)
  0-49   = BAIXA
  50-64  = MODERADA
  65-79  = ALTA
  80-100 = MUITO_ALTA

activation_score = null (UNAVAILABLE)
```

---

## Guardrails

| Flag | Valor |
|------|-------|
| shadow_only | true |
| apply_automatically | false |
| can_promote_trade | false |
| official_tables_writable | false |
| smc_recompute_allowed | false |
| app_py_modification_allowed | false |
| llm_decision_used | false |

---

## Hashes

| Artefato | Hash |
|----------|------|
| Config YAML | 1a51e94f791f1a62... |
| Feature Store | de143394c722368f... |
| Lock | Criado |
| Manifest | Criado |

---

## Próximo: FASE 11 — Backtest Baseline vs Candidata

Gate: LIBERAR_FASE_11_BACKTEST_BASELINE_VS_CANDIDATA
