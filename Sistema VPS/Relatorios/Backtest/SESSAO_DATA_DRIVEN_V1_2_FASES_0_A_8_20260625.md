# Relatório Completo — Sessão Data-Driven WINFUT V1.2
# Fases 0–8 | 2026-06-25/26

---

## Resumo Executivo

Sessão de ~8 horas que executou as **8 primeiras fases** do plano Data-Driven WINFUT V1.2, com múltiplos patches de correção e auditorias complementares.

---

## Fases Concluídas (8/15)

| Fase | Status | Gate | Descricao |
|------|--------|------|-----------|
| FASE 0 | ✅ DONE | PRONTO_PARA_AUDITORIA | Inventário de dados |
| FASE 1 | ✅ DONE | PRONTO_COM_DATASET_CANONICAL | Auditoria e canonicalização |
| FASE 2 | ✅ DONE_REEXECUTED | CORRIGIDO_BUG_EXPECTANCY_R | Contrato de outcomes (bearish bug fix) |
| FASE 3 | ✅ REVALIDATED | PRONTO_COM_TEMPORAL_SPLIT | Split temporal (RECENT_HOLDOUT) |
| FASE 4 | ✅ DONE_AUDITED | PRONTO_COM_FEATURE_STORE | Feature Store causal (56 features) |
| FASE 5 | ✅ DONE_AUDITED | PRONTO_COM_ANALISE_ZONAS | Análise individual de zonas |
| FASE 6 | ✅ DONE_AUDITED | PRONTO_COM_GATILHOS | Análise de gatilhos |
| FASE 7 | ✅ DONE | PRONTO_COM_COMBINACOES | Combinações SMC+Elliott+Wyckoff |
| FASE 8 | ✅ DONE | PRONTO_COM_TP1_POR_CONTEXTO | Pesquisa TP1 por contexto |
| FASE 9 | ⏳ | PENDENTE | Probabilidade e Score |

---

## Bugs Corrigidos

### Bug 1: Bearish realized_r (PATCH V1.1.1)
- **Problema:** trades BEARISH tinham realized_r negativo quando deveriam ser positivos
- **Causa:** `realized_r = (tp1 - entry) / risk` em vez de `(entry - tp1) / risk` para bearish
- **Impacto:** OB 5min net expectancy subiu de 0.004R para 0.790R
- **Status:** CORRIGIDO

### Bug 2: Efeito EMA (FASE 6.1)
- **Problema:** FASE 5.1 reportou +13.1pp, FASE 6 reportou +1.8pp
- **Causa:** Populações diferentes (Feature Store vs DB raw)
- **Status:** RECONCILIADO — efeito real é +13.1pp (Feature Store)

### Bug 3: Simpson's Paradox (FASE 5.1)
- **Problema:** Agregação OB+FVG mascara efeito EMA (+0.8pp vs +13.1pp em OB)
- **Status:** DETECTADO — toda análise deve ser estratificada por zone_type

### Bug 4: Ledger keys duplicadas
- **Problema:** JSON com chaves "5", "6", "7" duplicadas
- **Status:** CORRIGIDO

---

## Dados do Banco

### Velas (market_candles)
| TF | Velas | Período |
|----|-------|---------|
| 1min | 689.573 | 2021-06 → 2026-06 |
| 2min | 345.466 | 2021-06 → 2026-06 |
| 5min | 137.998 | 2021-06 → 2026-06 |
| 15min | 46.419 | 2021-06 → 2026-06 |
| 60min | 12.018 | 2021-06 → 2026-06 |
| 4h | 3.733 | 2021-06 → 2026-06 |
| 1d | 1.246 | 2021-06 → 2026-06 |

### Zonas SMC V2
| Tipo | Total |
|------|-------|
| FVG | 103.775 |
| Order Blocks | 24.934 |
| BOS/CHOCH | 24.829 |
| Liquidity | 14.768 |
| Swings | 62.713 |

---

## Resultados por Fase

### FASE 0 — Inventário
- 1.236.453 velas auditadas
- 0 duplicatas, 0 OHLC inválido, 0 indicadores faltando
- Elliott/Wyckoff backfill executado (6 rows por TF)

### FASE 1 — Canonicalização
- 682 duplicatas exatas detectadas (0.4% do total)
- 6 potenciais rollovers (>2% gap diário)
- available_at completo (0 nulls)

### FASE 2 — Contrato de Outcomes
**Resultados corrigidos (V1.1.1):**

| Zona | TF | TP1% | Net E(R) | PF |
|------|-----|------|----------|-----|
| **OB 4h** | 75.0% | **+0.949R** | — |
| **OB 15min** | 79.3% | **+0.810R** | — |
| **OB 5min** | 82.2% | **+0.790R** | 5.40 |
| **OB 2min** | 84.4% | **+0.766R** | — |
| FVG 5min | 32.8% | +0.191R | 1.29 |

### FASE 3 — Split Temporal

| Split | Período | Purge | Embargo |
|-------|---------|-------|---------|
| DISCOVERY | 2021-06 → 2022-12 | 0d | 30d |
| TRAIN | 2023-01 → 2024-06 | 30d | 30d |
| VALIDATION | 2024-07 → 2025-06 | 30d | 60d |
| TEST_FINAL | 2025-08 → 2026-01 | 60d | 90d |
| RECENT_HOLDOUT | 2026-04 → 2026-06 | 90d | 0d |

### FASE 4 — Feature Store
- 56 features em 11 categorias
- 22.851 snapshots (4.584 OB + FVG)
- 0 lookahead violations
- 0 label leakage
- 0 duplicatas

### FASE 5 — Análise Individual de Zonas

**Baseline OB 5min:**
- TP1: 82.1% (CI: 80.9-83.3%)
- Net E(R): +0.784R
- PF: 5.40
- Trades/dia: ~4

**BUY vs SELL:** Equilibrado (82.0% vs 82.3%)

**EMA:** +13.1pp TP1 (87.0% aligned vs 73.9% not aligned)

**Simpson's Paradox detectado:** Agregação OB+FVG mascara efeito EMA

**Estabilidade temporal:** Range 3.3pp em 5 anos, todos os anos positivos

### FASE 6 — Análise de Gatilhos

| Gatilho | Δ TP1 (pp) | Δ E(R) | Classificação |
|---------|------------|--------|---------------|
| close_aligned | +20.0 | -0.13 | INSUFFICIENT_CONTRAST (n=88) |
| rejection_any | +2.6 | -0.17 | NO_INCREMENTAL_VALUE |
| ema_aligned | +1.8 | -0.10 | PROMISING_AS_SCORE |
| displacement | -2.1 | +0.13 | PROMISING_ECONOMIC |
| volume_high | **-5.8** | +0.02 | REJECTED |

### FASE 7 — Combinações

**Achado crítico:** Elliott e Wyckoff são 100% NEUTRO — snapshot único, sem discriminância para OB M5.

**Baseline OB 5min (Feature Store):**

| Split | TP1% | Net E(R) | PF |
|-------|------|----------|-----|
| ALL | 81.8% | +0.779R | 5.32 |
| TRAIN | 81.1% | +0.751R | 5.04 |
| **VALIDATION** | **83.4%** | **+0.875R** | **6.29** |

### FASE 8 — Pesquisa TP1 por Contexto

**Grade Fixa em R (OB M5):**

| Target R | TP1% | Net E(R) | PF |
|----------|------|----------|-----|
| 0.70R | 86.3% | +0.467R | 4.41 |
| 0.80R | 85.5% | +0.539R | 4.72 |
| 0.90R | 84.5% | +0.606R | 4.92 |
| **1.00R** | **83.9%** | **+0.679R** | **5.24** |
| 1.10R | 82.5% | +0.733R | 5.20 |
| 1.25R | 80.7% | +0.816R | 5.24 |
| 1.50R | 76.7% | +0.918R | 4.95 |

**EMA Context (1.00R):**
- EMA aligned: **88.3% TP1**, PF=7.58
- EMA not aligned: 77.1% TP1, PF=3.38

**Política vencedora: EMA condicional 0.80R/1.25R**
- TP1: 82.5% (acima de 80%)
- Target R médio: 1.07R (acima de 0.70R)
- Net E(R): +0.720R (melhor)
- PF: 5.13

---

## 5 Candidatos para FASE 9

| # | Policy | TP1% | Target | E(R) | PF | Papel |
|---|--------|------|--------|------|-----|-------|
| 1 | EMA cond 0.80R/1.25R | 82.5% | 1.07R | +0.720R | 5.13 | DEFAULT_CONTEXTUAL |
| 2 | All 1.00R | 83.9% | 1.00R | +0.679R | 5.24 | CONSERVATIVE |
| 3 | BUY 1.25R / SELL 0.80R | 82.8% | 1.03R | +0.671R | 4.90 | DIRECTIONAL |
| 4 | EMA cond 0.70R/1.00R | 84.9% | 0.88R | +0.604R | 4.99 | HIGH_TP1 |
| 5 | All 0.80R | 85.5% | 0.80R | +0.539R | 4.72 | CONSERVATIVE_R |

---

## Arquivos Criados

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

### Relatórios (12)
- `docs/validacoes/DATA_AUDIT_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_2_1_AUDITORIA_OUTCOME_CONTRACT_WINFUT_V1.md`
- `docs/validacoes/PATCH_V1_2_METAS_FUNIL_TRADE_TP1_WINFUT_20260625.md`
- `docs/validacoes/FASE_4_FEATURE_STORE_CAUSAL_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_4_1_AUDITORIA_FINAL_FEATURE_STORE_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_5_1_AUDITORIA_COMPLEMENTAR_ANALISE_ZONAS_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_6_1_AUDITORIA_COMPLEMENTAR_GATILHOS_WINFUT_V1_20260625.md`
- `docs/validacoes/FASE_7_COMBINACOES_SMC_ELLIOTT_WYCKOFF_WINFUT_V1_20260626.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/ZONE_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/TRIGGER_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/COMBINATION_ANALYSIS_WINFUT_V1.md`
- `docs_geral/Sistema VPS/Relatorios/Backtest/TARGET_RESEARCH_WINFUT_V1.md`

### Feature Store
- `runtime/data_driven_winfut/feature_store_v1/` — 5 splits, 56 features

### Planos
- Plano V1.2 atualizado + backups

---

## Guardrails Preservados

| Guardrail | Status |
|-----------|--------|
| shadow_only | ✅ true |
| official_tables_modified | ✅ false |
| smc_recomputed | ✅ false |
| app_py_modified | ✅ false |
| llm_decision_used | ✅ false |

---

## Próximo: FASE 9 — Probabilidade e Score

**Gate:** LIBERAR_FASE_9_PROBABILIDADE_E_SCORE

A FASE 9 deve:
1. Criar score determinístico baseado nos 5 candidatos
2. Modelar P(trade activated | signal qualified)
3. Modelar P(TP1 before stop | trade activated)
4. Calibrar probabilidade
