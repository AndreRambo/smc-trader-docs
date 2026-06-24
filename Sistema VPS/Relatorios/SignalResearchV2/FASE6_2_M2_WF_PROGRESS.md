# FASE 6.2 — Nested Walk-Forward M2 — 🏆 CONCLUÍDO

**Início:** 2026-06-24 02:53 | **Fim:** 2026-06-24 14:13
**Duração:** 11h20m | **Workers:** 4 × 50 trials | **Execução:** M2

---

## Resultado Final: 200/200 ✅ — 0 rejeições

---

## 🏆 CHAMPION (Finalize): TRIAL_0044

| Métrica | Valor |
|---------|-------|
| PF médio | 3.45 |
| PF LCB 95% | 2.823 |
| E(R) média | 0.504R |
| E(R) LCB 95% | 0.435R |

---

## 🔄 Champion Confirmation rodando: TRIAL_0051

Motivo: TRIAL_0044 muito seletivo (0.9 trades/dia). TRIAL_0051 equilibra PF=3.33 com 1.2 trades/dia.

**Parâmetros:** stop=0.25, max_stop=2.0, expiry=6, session=True, htf_tp3=True, breakeven=True, cooldown=3

---

## Top 12 — Melhor equilíbrio qualidade + quantidade

| # | Trial | PF | E(R) | Trades | T/dia | Entry% | TP1% | TP2% | TP3% |
|---|-------|-----|------|--------|-------|--------|------|------|------|
| 1 | TRIAL_0044 | 3.45 | 0.504 | 406 | 0.9 | 52.6 | 100 | 48.1 | 44.0 |
| 2 | TRIAL_0159 | 3.36 | 0.480 | 469 | 1.0 | 52.2 | 100 | 43.3 | 40.2 |
| **3** | **TRIAL_0051** | **3.33** | **0.486** | **550** | **1.2** | **53.8** | **100** | **45.0** | **42.5** 🔄 |
| 4 | TRIAL_0034 | 3.17 | 0.489 | 506 | 1.1 | 51.5 | 100 | 53.3 | 50.2 |
| 5 | TRIAL_0117 | 3.14 | 0.478 | 564 | 1.2 | 53.8 | 100 | 47.6 | 45.1 |
| 6 | TRIAL_0055 | 3.03 | 0.412 | 469 | 1.0 | 52.2 | 100 | 31.2 | 31.2 |
| 7 | TRIAL_0045 | 2.94 | 0.457 | 612 | 1.3 | 58.3 | 100 | 47.8 | 45.3 |
| 8 | TRIAL_0031 | 2.94 | 0.457 | 612 | 1.3 | 58.3 | 100 | 47.8 | 45.3 |
| 9 | TRIAL_0165 | 2.94 | 0.456 | 470 | 1.0 | 56.2 | 100 | 53.9 | 49.8 |
| 10 | TRIAL_0146 | 2.92 | 0.457 | 662 | 1.4 | 61.3 | 100 | 45.6 | 43.1 |
| 11 | TRIAL_0058 | 2.86 | 0.406 | 490 | 1.1 | 51.5 | 100 | 29.4 | 29.4 |
| 12 | TRIAL_0079 | 2.83 | 0.406 | 564 | 1.2 | 53.8 | 100 | 30.8 | 30.8 |

---

## Comparação final

| Run | Breakeven | Trailing | Melhor PF | Melhor E(R) |
|-----|-----------|----------|-----------|-------------|
| Run #9 (TRIAL_0028) | ❌ | ❌ | 1.36 | 0.119 |
| **FASE 6.2** | ✅ | ✅ | **3.45** | **0.504** |

---

## Arquivos

- CSV completo: `Sistema VPS/Relatorios/Backtest/FASE6_2_WINFUT_200_TRIALS.csv`
- Metodologia: `Sistema VPS/Relatorios/Backtest/METODOLOGIA_NESTED_WALK_FORWARD.md`
- Resultado WF: `SignalResearchV2/RESULTADO_FASE_6_NESTED_WALK_FORWARD.md`
