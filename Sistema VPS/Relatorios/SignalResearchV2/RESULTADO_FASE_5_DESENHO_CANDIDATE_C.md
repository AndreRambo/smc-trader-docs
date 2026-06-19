# RESULTADO FASE 5 — DESENHO DO CANDIDATE C — 2026-06-17

**Status**: ✅ CONCLUÍDO

---

## 1. Objetivo

Criar CANDIDATE_C como versão melhorada do CANDIDATE_B_V3 (vencedor baseline), sem alterar o original.

---

## 2. Melhorias Implementadas

| Melhoria | Problema que resolve | Impacto esperado |
|----------|---------------------|-----------------|
| **SessionFilter** | Sinais fora do pregão | -15% sinais, +qualidade |
| **CooldownTracker** | Sinais consecutivos redundantes | -20% ruído |
| **DedupFilter** | Sinais repetidos na mesma zona | -10% duplicatas |
| **MTFConfluenceFilter** | TP3 sem alinhamento HTF | TP3 mais confiável |
| **Dynamic expiry** | Expiração fixa ineficiente | +5% fill rate |
| **MARKET fallback** | 45% expiração LIMIT | +15% fill rate |
| **Break-even estrutural** | Stop prematuro após TP1 | -10% stop-out |

---

## 3. Arquivos

```
technical_engine/signal_candidate_c_v1/
├── __init__.py       — alias CANDIDATE_C
├── config.py         — CandidateCConfigV1
├── builder.py        — CandidateCBuilder (wrapper do B_V3)
└── filter_policy.py  — Session, Cooldown, Dedup, MTF filters
```

---

## 4. Arquitetura

```
CANDIDATE_B_V3 (intacto)
      │
      ▼
CandidateCBuilder
      │
      ├── SessionFilter → rejeita fora do pregão
      ├── CooldownTracker → bloqueia < 5 barras
      ├── DedupFilter → bloqueia zona repetida
      ├── MTFConfluenceFilter → drop TP3 se HTF não alinhado
      ├── Dynamic expiry → ajusta por ATR
      ├── MARKET fallback → tag para execução
      └── Break-even → tag para gestão
      │
      ▼
CANDIDATE_C signals
```

---

## 5. Configuração Default

| Parâmetro | Valor |
|-----------|-------|
| entry_type | ZONE_EDGE |
| market_fallback | True (após 3 barras M5) |
| m2_confirmation | True |
| stop_buffer_atr | 0.15 |
| max_stop_atr | 2.5 |
| session_only | True (13-21 UTC) |
| cooldown_bars_m5 | 5 |
| dedup_zone_tolerance | 30% |
| dynamic_expiry | True |
| breakeven_after_tp1 | True |
| require_htf_for_tp3 | True |
| enabled_setups | S1, S2, S3, S5 |
| s4_enabled | False |

---

## 6. Próximas Fases

| Fase | Status |
|------|--------|
| 6 — Nested Walk-Forward | ⏳ Bloqueado (6.3 meses insuficiente para nested WF) |
| 7 — Stress Tests | ⏳ Bloqueado (requer holdout limpo) |
| 8 — Holdout Final | ⏳ Bloqueado (requer 3-6 meses de novos dados) |
| 9 — Forward Shadow | ⏳ Bloqueado (requer 60-90 pregões futuros) |
| 10 — Decisão Final | ⏳ Bloqueado |
