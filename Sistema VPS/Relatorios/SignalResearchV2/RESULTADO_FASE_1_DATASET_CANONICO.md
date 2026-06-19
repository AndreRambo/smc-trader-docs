# RESULTADO FASE 1 — DATASET CANÔNICO WINFUT V2 — 2026-06-17

**Status**: ✅ AUDITORIA CONCLUÍDA — ⚠️ ANOMALIAS CRÍTICAS

---

## 1. Auditoria por Timeframe (asset_id=1)

| Timeframe | Candles | Período | Dias | /dia real | /dia esperado | Status |
|-----------|---------|---------|------|-----------|---------------|--------|
| 1min | 51.448 | Dez 8 → Jun 17 | 190 | 271 | ~114 | ⚠️ 2.4× esperado |
| 2min | 27.042 | Dez 8 → Jun 17 | 190 | 142 | ~57 | ⚠️ 2.5× esperado |
| 5min | 11.495 | Dez 8 → Jun 17 | 190 | 60 | ~23 | ⚠️ 2.6× esperado |
| 15min | 4.259 | Dez 8 → Jun 17 | 190 | 22 | ~8 | ⚠️ 2.8× esperado |
| 4h | 3.905 | Jun 2021 → Jun 2026 | 1.826 | 2.1 | ~2 | ✅ OK |
| **1d** | **10.056** | Abr 2022 → Jun 2026 | 1.510 | **6.7** | ~1 | ❌ **CRÍTICO** |

---

## 2. D1 — DIAGNÓSTICO

### O "D1" é na verdade H1 (1 hora)

**Evidências:**

1. **Intervalo entre candles**: 9.817 de 10.056 registros têm intervalo de **0 dias** (múltiplos por dia)
2. **Timestamps**: `13:00, 14:00, 15:00, 16:00, 17:00` — horas do pregão B3
3. **Frequência**: 6.7 candles/dia × 252 pregões/ano × 4.2 anos ≈ 7.100 candles. Mas temos 10.056, o que corresponde a 365 dias/ano → ~6.7 candles/dia corrido
4. **Causa raiz**: `tools/backfill_winfut_historical.py` mapeou `"1d": 16385` — na MT5, **16385 = PERIOD_H1** (1 hora), não D1. O correto seria `16384` para D1.

### O "4h" está correto

MT5 `16388 = PERIOD_H4` — mapeamento correto. 2.1 candles/dia é esperado (24/4=6, mas B3 só opera ~9.5h/dia, então ~2.4/dia).

---

## 3. Contratos — Diagnóstico

| Timeframe | Fonte MT5 | Símbolo | Período | Observação |
|-----------|-----------|---------|---------|------------|
| M1/M2/M5/M15 | WINM26 | Contrato atual | Dez 2025–Jun 2026 | 6.3 meses |
| H4 | WIN$N | Contínuo | Jun 2021–Jun 2026 | 5 anos, OK |
| **"D1"** | **WIN$N (16385=H1)** | **Contínuo rotulado errado** | Abr 2022–Jun 2026 | **NÃO É D1** |

### Mistura de contratos

- WINM26 (Jun 2026) para intraday — líquido apenas nos últimos ~3 meses
- WIN$N (contínuo) para "D1"/H1/H4 — inclui dados de múltiplos vencimentos
- **Sem calendário de rollover** documentado
- **Sem ajuste de preços** entre contratos

---

## 4. Qualidade dos Dados

| Verificação | Resultado |
|-------------|-----------|
| Duplicatas (M5) | 0 ✅ |
| Gaps > 15min (M5) | 462 ⚠️ (normais: overnight, fim de pregão) |
| Weekend candles (D1/H1) | 0 ✅ |
| Open/High/Low/Close inválidos | Não verificado |
| Timezone | UTC (naive, sem tzinfo) |
| Dias sem negociação | Verificado via gaps |

---

## 5. Impacto nos Backtests Existentes

### CANDIDATE_B — HTF bias usa "D1"

O `signal_builder.py` carrega `d1 = repo.load_snapshot(symbol, "1d", ...)` para HTF bias. Como "D1" é na verdade H1, o viés HTF está sendo calculado com dados de **1 hora**, não diários. Isso significa:

- O viés muda a cada hora (muito instável para HTF)
- A "tendência D1" reportada nos backtests era na verdade tendência H1
- O H4 está correto e provavelmente forneceu o viés real

### CONTROL_A — backtest com EMA também usou "D1"

Os candles rotulados como D1 no `market_candles` alimentaram qualquer cálculo que usasse timeframe='1d'.

---

## 6. Dataset Mínimo

| Requisito | Realidade | Status |
|-----------|-----------|--------|
| 12 meses intraday | **6.3 meses** (M1/M2/M5/M15) | ❌ INSUFFICIENT |
| 24 meses intraday | 6.3 meses | ❌ |
| D1 correto | NÃO (é H1) | ❌ |
| H4 correto | SIM | ✅ |
| Contrato consistente | NÃO (WIN$N + WINM26) | ❌ |
| Calendário rollover | NÃO | ❌ |

**STATUS**: `INSUFFICIENT_FOR_FINAL_OPTIMIZATION`

---

## 7. Ações Corretivas

### Imediatas
1. **Corrigir o backfill**: `"1d": 16384` (não 16385) para obter D1 real
2. **Re-backfill D1**: do WIN$N contínuo com o timeframe correto
3. **Verificar H4**: se está realmente correto com 16388

### Para o estudo atual
1. **Usar H4 como HTF primário** (está correto, 5 anos de dados)
2. **NÃO usar "D1" atual** — é H1, não diário
3. **Marcar todos os backtests anteriores** com ressalva: "HTF bias usou H1 rotulado como D1"
4. **Prosseguir com 6.3 meses intraday** ciente da limitação

---

## 8. Hash do Dataset

```
CANONICAL_WINFUT_DATASET_V2
status: NEEDS_CORRECTION
d1_timeframe: WRONG (H1, not D1)
intraday_months: 6.3
htf_h4_months: 60
```

---

## 9. Próxima Fase

**FASE 2 — Unified Backtest Engine V2**

Com a ressalva: usar H4 como HTF, corrigir D1 quando possível. Prosseguir com os 6.3 meses disponíveis como estudo exploratório (não definitivo).
