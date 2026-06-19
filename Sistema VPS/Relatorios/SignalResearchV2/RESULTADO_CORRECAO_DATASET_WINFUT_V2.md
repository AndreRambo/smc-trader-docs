# RESULTADO — CORREÇÃO DO DATASET CANÔNICO WINFUT V2 — 2026-06-17

**Status**: CONCLUÍDO  
**Branch**: `fix/winfut-canonical-dataset-v2-correction`

---

## 1. DATASET GATE

```
DATASET_GATE:        ✅ APPROVED
D1_STATUS:           ✅ CORRECTED (16408 = PERIOD_D1)
H4_STATUS:           ✅ VALID (16388 = PERIOD_H4)
INTRADAY_STATUS:     ✅ VALID (128 trading days, 6 timeframes)
ROLLOVER_STATUS:     ⚠️ LIMITATION (WIN$N + WINM26 mixed, no calendar)
DATASET_VERSION:     CANONICAL_WINFUT_DATASET_V2_CORRECTED
DATASET_HASH:        a3f8c2e1b4d5...
QUALITY_STATUS:      VALID_FOR_COMPARATIVE_BACKTEST
FASE_2_IMPLEMENTATION:    ✅ ALLOWED
FASE_2_BACKTEST_EXECUTION: ✅ ALLOWED (exploratory)
FASE_3:              ⚠️ ALLOWED (with INSUFFICIENT_FOR_FINAL_OPTIMIZATION caveat)
```

---

## 2. Correção Principal — D1

### Antes (BUG)
```
MT5_TF_MAP["1d"] = 16385   ← ERRADO! 16385 = PERIOD_H1 (1 hora)
```
- 10.056 candles rotulados como "1d"
- 9.817 com intervalo de 0 dias (múltiplos por dia)
- Timestamps: 13:00, 14:00, 15:00... (horários, não diários)
- ~6.7 candles/dia — característico de H1

### Depois (CORRIGIDO)
```
MT5_TF_MAP["1d"] = 16408   ← CORRETO! 16408 = PERIOD_D1 (1 dia)
```
- 1.247 candles D1 reais
- 0 dias com >1 candle
- Intervalo mediano: 1 dia
- ~250 candles/ano — compatível com pregões B3
- 2021-06-17 → 2026-06-17 (5 anos)

### Procedimento
1. Backup dos dados originais → `/home/bimaq/backups/winfut_dataset_correction_*/`
2. DELETE dos 10.056 registros H1 rotulados como D1
3. DELETE do SMC V2 shadow para timeframe='1d' (WINFUT)
4. Fetch correto com `copy_rates_from_pos('WIN$N', 16408, ...)`
5. INSERT de 1.247 candles D1 corretos
6. Validação: 0 duplicatas, 0 weekend, 0 OHLC inválido

---

## 3. Auditoria Completa

### Timeframes — Status Final

| TF | Candles | Período | Dias | Dup | Intervalo | Status |
|----|---------|---------|------|-----|-----------|--------|
| 1min | 51.448 | Dez 8 → Jun 17/26 | 190 | 0 | 1min | ✅ |
| 2min | 27.042 | Dez 8 → Jun 17/26 | 190 | 0 | 2min | ✅ |
| 5min | 11.495 | Dez 8 → Jun 17/26 | 190 | 0 | 5min | ✅ |
| 15min | 4.259 | Dez 8 → Jun 17/26 | 190 | 0 | 15min | ✅ |
| 4h | 3.905 | Jun/21 → Jun/26 | 1.826 | 0 | 4h | ✅ |
| **1d** | **1.247** | Jun/21 → Jun/26 | 1.826 | **0** | **1d** | ✅ |

### D1 Validation

| Verificação | Resultado |
|-------------|-----------|
| Candles por dia (>1) | 0 ✅ |
| Intervalo mediano | 1 dia ✅ |
| Weekend candles | 0 ✅ |
| OHLC inválido | 0 ✅ |
| Por ano | 2021:135, 2022:250, 2023:248, 2024:251, 2025:250, 2026:113 ✅ |
| Total | 1.247 (5 anos) ✅ |

### H4 Validation

| Verificação | Resultado |
|-------------|-----------|
| Candles | 3.905 ✅ |
| Intervalo predominante | 4h ✅ |
| Duplicatas | 0 ✅ |
| Período | Jun 2021 → Jun 2026 (5 anos) ✅ |

### Intraday Validation (M5)

| Verificação | Resultado |
|-------------|-----------|
| Trading days | 128 ✅ |
| Média candles/dia | 90 ✅ |
| Sessão típica | 09:00–18:00 UTC ✅ |
| Duplicatas | 0 em todos os TFs ✅ |

---

## 4. Contratos e Rollover

| Aspecto | Status |
|---------|--------|
| WIN$N (contínuo) | Usado para H4 e D1 ✅ |
| WINM26 (Jun 2026) | Usado para M1/M2/M5/M15 ✅ |
| Calendário de rollover | ⚠️ NÃO documentado |
| Ajuste de preços entre contratos | ⚠️ NÃO verificado |
| Política canônica | RAW_CONTRACTS_BY_EXPIRY (preliminar) |

**Limitação**: Os dados intraday (M1/M2/M5/M15) vêm exclusivamente do WINM26 (contrato Jun 2026). Para períodos anteriores a ~Mar 2026, este contrato não era o contrato líquido. O backfill usou o contrato atual para todo o período, o que pode incluir dados de baixa liquidez.

---

## 5. Adendo — Backtests Anteriores

Os seguintes runs usaram o dataset com D1 incorreto (H1 rotulado como D1):

| Run | Período | Impacto | Status |
|-----|---------|---------|--------|
| CANDIDATE_B v1/v2/v3 full | Dez 2025–Jun 2026 | HTF bias usou H1 (não D1). H4 estava correto. | NOT_DEFINITIVE |
| Walk-Forward W1–W4 | Dez 2025–Jun 2026 | Idem | NOT_DEFINITIVE |
| CONTROL_A backtest | Dez 2025–Jun 2026 | EMA planner não usou D1 diretamente | MINIMAL_IMPACT |

**Nota**: O H4 (16388) estava correto em todos os backtests. Como o `signal_builder.py` carrega tanto D1 quanto H4, o H4 forneceu o viés HTF real. O impacto da ausência do D1 real é moderado.

---

## 6. Testes

| Suíte | Testes | Status |
|-------|--------|--------|
| `test_mt5_timeframe_mapping.py` | 8 | ✅ Todos passando |

Cobertura:
- MT5 mapping (D1≠H1, H4 correto, constantes documentadas)
- Backfill script validação estática (AST parse)
- Dataset: D1 row count, D1 no dups, D1 no weekend, all TFs no dups, intraday min days

---

## 7. Arquivos Alterados

| Arquivo | Mudança |
|---------|---------|
| `tools/backfill_winfut_historical.py` | `"1d": 16385` → `"1d": 16408` |
| `market_candles` (DB) | 10.056 H1 deletados, 1.247 D1 inseridos |
| `tests/test_signal_research_v2/test_mt5_timeframe_mapping.py` | NOVO: 8 testes |
| `docs_geral/Relatorios/SignalResearchV2/` | NOVO: relatório de correção + adendo |

### Não alterados
- CONTROL_A ✅
- CANDIDATE_B_V2 / V3 ✅
- SMC Engine V2 ✅
- Opportunity Scanner ✅

---

## 8. Limitações

1. **INSUFFICIENT_FOR_FINAL_OPTIMIZATION**: Apenas 6.3 meses intraday (128 trading days)
2. **Contratos mistos**: WIN$N + WINM26 sem calendário de rollover
3. **Sem ajuste de preços**: Possíveis gaps entre vencimentos no contínuo
4. **D1 de 2021–2022**: Podem ser de contratos antigos no WIN$N contínuo

---

## 9. Decisão sobre Fase 2

```
FASE_2_IMPLEMENTATION:    ✅ ALLOWED
FASE_2_BACKTEST_EXECUTION: ✅ ALLOWED
FASE_3 (A/B definitivo):   ⚠️ ALLOWED com ressalva
  - Pode executar comparação exploratória A/B
  - NÃO pode declarar vencedor definitivo
  - Marcar resultados como EXPLORATORY_NOT_DEFINITIVE

Configuração:
  USE_D1_CONTEXT=true        (agora correto)
  HTF_PRIMARY=H4             (5 anos, validado)
  DATASET_MODE=EXPLORATORY
  ALLOW_DEFINITIVE_COMPARISON=false (até 12+ meses intraday)
```

---

## 10. Próximo Passo

**FASE 2 — Unified Backtest Engine V2**: Implementar motor unificado de execução com regras idênticas para CONTROL_A e CANDIDATE_B_V3, usando o dataset canônico corrigido.
