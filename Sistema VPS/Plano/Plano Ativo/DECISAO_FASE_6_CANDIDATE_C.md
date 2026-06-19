# DECISÃO FASE 6 — CANDIDATE_C — 2026-06-18

**Status**: PHASE_6_FRAMEWORK_READY — PENDING_EXECUTION

---

## 1. CONTEXTO

O nested walk-forward completo (200 trials × 8 outer folds = 1.600 backtests) requer aproximadamente 22 horas de computação contínua. Não foi possível completar na sessão atual.

O framework está implementado e testado. A execução pode ser retomada.

---

## 2. BASELINE DE REFERÊNCIA (CANDIDATE_B_V3)

Resultados existentes do walk-forward de 6.3 meses (4 janelas):

| Janela | Período | E[R] | PF | TP1 cond. |
|--------|---------|------|-----|-----------|
| W1 | Dez–Mar | +0.261 | 1.73 | 96.5% |
| W2 | Mar–Abr | +0.449 | 2.87 | 100% |
| W3 | Abr–Mai | +0.275 | 1.79 | 96.6% |
| W4 | Mai–Jun | +0.251 | 1.97 | 91.6% |

**Média: +0.309R ±0.094R, PF 2.09, todas as janelas positivas**

---

## 3. SEARCH SPACE CONGELADO

```
stop_buffer_atr:     [0.10, 0.15, 0.20, 0.25]
max_stop_atr:        [2.0, 2.5, 3.0]
expiry_candles_m5:   [6, 9, 12]
session_only:        [true, false]
require_htf_for_tp3: [true, false]
breakeven_after_tp1: [true, false]
cooldown_bars_m5:    [3, 5, 8]

Fixos: M2=false, S4=false, M5 execution, STOP_FIRST
Total: 4×3×3×2×2×2×3 = 864 combos. Random search: 200 trials.
```

---

## 4. PRÓXIMOS PASSOS

1. Executar `python tools/run_phase6_nested_wf.py` em sessão longa
2. 200 trials, 8 outer folds, 44 meses de dados
3. Selecionar Champion + 2 Runner-ups
4. Gerar relatório final

---

## 5. STATUS

```
PHASE_6_STATUS:             FRAMEWORK_READY
BASELINE_REFERENCE:         CANDIDATE_B_V3 (+0.309R, PF 2.09)
SEARCH_SPACE:               CONGELADO (7 params, 864 combos)
EXECUTION:                  PENDING (requer ~22h computação)
```
