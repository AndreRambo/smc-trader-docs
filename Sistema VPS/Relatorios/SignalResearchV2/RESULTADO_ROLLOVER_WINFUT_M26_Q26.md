# RESULTADO ROLLOVER — WINM26 → WINQ26 — 2026-06-18

**Status**: ROLLOVER_OBSERVED (primeiro rollover documentado)

---

## 1. EVIDÊNCIAS

```
OLD_CONTRACT:        WINM26
NEW_CONTRACT:        WINQ26 (Agosto 2026)
OBSERVED_AT:         2026-06-18 01:20 UTC
EXPIRATION_DATE:     2026-06-17 (quarta-feira próxima ao dia 15)

WINM26 last M5:      2026-06-17 18:55 UTC
WINQ26 last M5:      2026-06-17 20:30 UTC

Also available:      WINV26, WINZ26 (back months)
```

---

## 2. AÇÃO

- Configuração do coletor atualizada: `mt5_symbol: WINM26` → `WINQ26`
- Coletor precisa ser reiniciado para usar novo contrato

---

## 3. GATE

```
ROLLOVERS_OBSERVED:          1 ✅
ROLLOVER_STATUS:             VALIDATED (primeiro rollover observado e documentado)
```

---

## 4. GATES FASE 6

```
✅ data_quality
✅ portfolio
✅ matching
✅ bootstrap
✅ intraday_months (44 meses)
✅ rollover (WINM26→WINQ26)
❌ control_a_adapter (PARTIAL_VALIDATED)

6/7 APROVADOS
```
