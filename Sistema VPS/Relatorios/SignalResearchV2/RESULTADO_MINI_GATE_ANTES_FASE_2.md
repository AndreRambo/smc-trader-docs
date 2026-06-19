# RESULTADO — MINI-GATE ANTES DA FASE 2 — 2026-06-17

**Status**: MINI_GATE_APPROVED_WITH_ROLLOVER_LIMITATION

---

## 1. MINI-GATE FINAL

```
MINI_GATE:                ✅ APPROVED_WITH_ROLLOVER_LIMITATION
SMC_V2_D1_STATUS:         ✅ RECALCULATED (224 FVGs, 55 OBs, 57 BOS, 135 Swings, 39 Liq)
ANTI_LOOKAHEAD_STATUS:    ✅ VALID (ref_index within candle bounds)
TIMEZONE_STATUS:          ✅ CONFIRMED (UTC naive storage, B3 13-21 UTC)
SESSION_STATUS:           ✅ VERSIONED (B3 10:00-18:00 BRT)

FASE_2_IMPLEMENTATION:    GO
FASE_2_BACKTEST_EXECUTION: GO
FASE_3_EXPLORATORY_AB:    GO
DEFINITIVE_OPTIMIZATION:  NO_GO (6.3 months intraday)

ROLLOVER_STATUS:          UNRESOLVED_EXPLORATORY
```

---

## 2. SMC V2 D1 Recalculation

| Estado | Antes | Depois |
|--------|-------|--------|
| FVGs | 0 | 224 |
| Order Blocks | 0 | 55 |
| BOS/CHOCH | 0 | 57 |
| Liquidity | 0 | 39 |
| Swings | 0 | 135 |

**Run ID**: `recalc-D1-CORRECTED-20260617T193046-913508e6`  
**Candles processados**: 1.247 (2021-06-17 → 2026-06-17)

---

## 3. Hashes Completos

| Hash | Valor (SHA-256, 64 chars) |
|------|---------------------------|
| `dataset_hash` | `db1f599078d290b2d2eaafd51238b013dbb4ff243ca87447d06b986d3de6ee39` |
| `d1_candles_hash` | `794a5db53190e338c659c784b2cc30b34ffc3948ba821d0cec25e766899c779c` |
| `smc_v2_d1_state_hash` | `795b5b20b2abf413adf78ad81a045391367a458e018b1b9d7f136ef52d117f08` |
| `manifest_hash` | `3157ce0fbca405694543aae029ebda823451c5f5bdf313977e94ab57757c1c0b` |
| `code_commit` | `c1b7c27` |

---

## 4. Timezone

| Aspecto | Valor |
|---------|-------|
| Source (MT5) | UTC |
| Storage (MySQL) | UTC (naive datetime) |
| Market (B3) | America/Sao_Paulo |
| Sessão B3 (BRT) | 10:00–18:00 |
| Sessão B3 (UTC) | 13:00–21:00 |
| DST | Não (abolido em 2019) |
| Dados observados | 09:00–20:20 UTC (MT5 inclui pre/post market) |

**Conclusão**: Dados em UTC naive, sessão B3 13:00-21:00 UTC. Correto para o engine config (`tz_offset_hours=-3, pregao_start=10, pregao_end=18`).

---

## 5. Anti-Lookahead

Testado via `ref_index` bounds: FVGs ref_index 6→1237, candles 0→1246. Nenhum SMC object referencia candle inexistente ou futuro. O `SMCStateRepository.load_snapshot()` filtra por `ref_index <= max_ref` baseado no candle time, garantindo anti-lookahead.

---

## 6. Smoke Tests

| Teste | Resultado |
|-------|-----------|
| D1 states not empty | ✅ Todas as 5 tabelas com dados |
| Signal builder 5 days | ✅ Funcional, D1+H4+M5 carregados |
| D1 candles 1.247 | ✅ Período 2021-2026 |
| H4 candles 3.905 | ✅ Período 2021-2026 |
| Duplicatas | ✅ 0 em todos os TFs |

---

## 7. Configuração para FASE 2

```
USE_D1_CONTEXT=true           (D1 corrigido e validado)
HTF_PRIMARY=H4                (5 anos, validado)
DATASET_MODE=EXPLORATORY
ALLOW_DEFINITIVE_COMPARISON=false
ALLOW_CANDIDATE_C_OPTIMIZATION=false
ROLLOVER=UNRESOLVED_EXPLORATORY
```

---

## 8. Limitações Mantidas

1. **6.3 meses intraday** — INSUFFICIENT_FOR_FINAL_OPTIMIZATION
2. **Rollover não resolvido** — WIN$N contínuo + WINM26 contrato atual
3. **Sem calendário de vencimentos** — rollover não documentado
4. **Resultados anteriores**: NOT_DEFINITIVE (usavam D1 falso)

---

## 9. Datasets

| Versão | Status |
|--------|--------|
| CANONICAL_WINFUT_DATASET_V2_CORRECTED | ✅ Ativo, revision 2 |
| Manifest | `CANONICAL_WINFUT_DATASET_V2_CORRECTED.json` |

---

## 10. Próxima Fase

**FASE 2 — Unified Backtest Engine V2**: Implementar motor unificado com regras idênticas para CONTROL_A e CANDIDATE_B_V3.
