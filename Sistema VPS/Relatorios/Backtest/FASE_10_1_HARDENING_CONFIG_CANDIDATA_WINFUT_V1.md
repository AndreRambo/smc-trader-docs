# FASE 10.1 — Auditoria Final e Hardening da Config Candidata

**Data:** 2026-06-26
**Status:** ✅ PRONTO_COM_CONFIG_CANDIDATA_VERSIONADA_WINFUT_V1_AUDITADA
**Gate:** LIBERAR_FASE_11_BACKTEST_BASELINE_VS_CANDIDATA

---

## Bloqueadores Corrigidos

| # | Bloqueador | Correção |
|---|------------|----------|
| 1 | Schema JSON ausente | ✅ Criado `WINFUT_CANDIDATE_V1.schema.json` |
| 2 | Modelo TP1 sem referência completa | ✅ `score_available=false`, campos obrigatórios adicionados |
| 3 | Stop não estruturado | ✅ Regras tipadas BUY/SELL com fallback |
| 4 | Timestamps não-UTC | ✅ Normalizados para `YYYY-MM-DDTHH:MM:SSZ` |
| 5 | Hashes truncados (16 chars) | ✅ SHA-256 completo (64 chars) em lock e manifest |
| 6 | CANDIDATE_3 sem fallback | ✅ Fallback para DIRECTION_UNKNOWN |
| 7 | Research targets incompletos | ✅ Todos os gates V1.2 adicionados |
| 8 | Schema JSON ausente no manifest | ✅ Adicionado ao manifest |

---

## Config WINFUT_CANDIDATE_V1 (Hardened)

### Activation Model
- Status: `BASE_RATE_ONLY`
- Rate: 84.8% (5040/5945)
- Score: **UNAVAILABLE** (não calibrado)

### TP1 Model
- Status: `BASE_RATE_ONLY`
- Model/Calibrator: **não persistidos** → `score_available=false`
- Brier skill: 0.30 (vs baseline)

### 5 Policies Congeladas

| # | Policy | Regras | Fallback |
|---|--------|--------|----------|
| 1 | EMA 0.80/1.25R | ALIGNED→1.25, DEFAULT→0.80 | BLOCK_SAMPLE |
| 2 | All 1.00R | ALWAYS→1.00 | — |
| 3 | BUY 1.25/SELL 0.80R | BUY→1.25, SELL→0.80, MISSING→0.80 | DEFAULT_TO_SELL |
| 4 | EMA 0.70/1.00R | ALIGNED→1.00, DEFAULT→0.70 | BLOCK_SAMPLE |
| 5 | All 0.80R | ALWAYS→0.80 | — |

### Guardrails
7 flags, todos congelados como `const` no schema.

---

## Artefatos

| Arquivo | Hash SHA-256 |
|---------|-------------|
| YAML | `97da555134116508...` |
| Schema | `ee9a52b6f3d710a2...` |
| Feature Store | `de143394c722368f...` |

---

## Próximo: FASE 11 — Backtest Baseline vs Candidata

Gate: `LIBERAR_FASE_11_BACKTEST_BASELINE_VS_CANDIDATA` ✅
