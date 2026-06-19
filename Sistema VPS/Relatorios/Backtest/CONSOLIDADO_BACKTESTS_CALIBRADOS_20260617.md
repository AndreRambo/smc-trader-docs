# Relatório Consolidado — Backtests & Calibração Multi-Ativo — 2026-06-17

**Branch**: `fix/roadmap-closeout-e2e-soak-v1`  
**Plano de referência**: [`PLANO_BACKTEST_CALIBRACAO_MULTI_ATIVO_SMC_TRADER_7_0.md`](../../Planos/PLANO_BACKTEST_CALIBRACAO_MULTI_ATIVO_SMC_TRADER_7_0.md)

---

## ✅ Fase A — Verificação do Pipeline (CONCLUÍDA)

Sessão anterior. Todos os 11 ativos com coleta ativa, eventos COMPLETED nas últimas 24h, dispatch OK.

---

## ✅ Fase C — Implementação `get_asset_config()` (CONCLUÍDA)

### Arquivos modificados

| Arquivo | Mudança |
|---------|---------|
| `technical_engine/smc_engine_v2/config.py` | Adicionado `get_asset_config(symbol)` — 9 perfis calibrados + fallback |
| `services/candle_event_processor/dispatcher.py` | 5 call sites agora: `config=get_asset_config(symbol)` |
| `tools/run_opportunity_scanner_backtest.py` | Fix: tp1 no signal dict + None guards no compute_outcome |

### Perfis calibrados

| Perfil | OB Thresholds | Sessão (UTC) | BPR max |
|--------|---------------|--------------|---------|
| WINFUT_M5 | 100 / 250 / 500 pts | 13-21 (B3) | 150.0 |
| WDOFUT_M5 | 5 / 15 / 30 pts | 13-21 (B3) | 30.0 |
| PETR4/VALE3/ITUB3_M5 | 0.10 / 0.35 / 0.80 R$ | 13-21 (B3) | 0.80 |
| XAUUSDM_M5 | 2.0 / 6.0 / 15.0 USD | 8-17 (London+NY) | 8.0 |
| XAGUSDM_M5 | 0.03 / 0.10 / 0.25 USD | 8-17 (London+NY) | 0.25 |
| EURUSDM_M5 | 0.0005 / 0.0015 / 0.004 | 7-16 (London) | 0.003 |
| USDJPYM_M5 | 0.05 / 0.15 / 0.35 | 0-9 (Tokyo) | 0.30 |
| BTCUSDM_M5 | 200 / 600 / 1500 | 0-24 (24/7) | 700.0 |
| ETHUSDM_M5 | 5.0 / 15.0 / 40.0 | 0-24 (24/7) | 35.0 |

### Testes

- **SMC Engine V2**: 164 passed, 2 skipped
- **Technical Engine + Scanner**: 1915 passed, 0 failures
- **Total**: 2079+ passando, sem regressões

---

## 📊 Backtests Executados (6 meses, Dez 2025 – Jun 2026)

### S26 — XAUUSDm Calibrado

| Métrica | Valor |
|---------|-------|
| Candles M5 / M1 | 36.786 / 72.203 |
| Sinais | 2.158 |
| Entry hit | **80.9%** |
| TP1 hit | **51.7%** |
| Win rate (entered) | **63.9%** |
| ALTISTA / BAIXISTA | 47.5% / 52.5% |

**Comparativo S21**: ALTISTA imbalance caiu de 87.3% → 47.5% (correção de período 2024 bull vs 2025-2026).  
**Melhoria esperada em produção**: Session scoring London UTC corrigido (score 83 vs 56 anterior).

### S27 — WDOFUT Calibrado

| Métrica | Valor |
|---------|-------|
| Candles M5 / M1 | 5.586 / 27.927 |
| Sinais | 6.068 |
| Entry hit | **68.1%** |
| TP1 hit | **38.4%** |
| Win rate (entered) | **56.4%** |
| ALTISTA / BAIXISTA | 43.0% / 57.0% |

**Nota**: 6.068 sinais em 6 meses = ~33/dia. Volume alto — candidato a cooldown (Fase G).

### S28 — EURUSDm Calibrado

| Métrica | Valor |
|---------|-------|
| Candles M5 / M1 | 36.831 / 72.190 |
| Sinais | 2.124 |
| Entry hit | **69.2%** |
| TP1 hit | **28.2%** |
| Win rate (entered) | **40.8%** |
| ALTISTA / BAIXISTA | 55.4% / 44.6% |

**Nota**: TP1 28.2% — baixo. O backtest usa planner EMA-sintético, não SMC V2 completo.
A calibração real (session London 7-16 UTC, thresholds em pips) opera no pipeline LIVE.

---

## 🔄 Pipeline Live — Impacto da Calibração

O dispatcher.py (`smc-candle-event-processor`) agora envia `config=get_asset_config(symbol)` para
todas as execuções do SMC V2 (M5, M15, H4, D1, M2). Isso corrige:

1. **Session scoring** — OBs do Gold (8h UTC) agora recebem score PREGAO=83, não FORA=56
2. **OB size thresholds** — Distinção real entre micro/pequeno/médio/grande por instrumento
3. **BPR max_size** — Filtro de qualidade corrigido (Gold BPR > $8 = descartado)

O impacto completo será visível após restart do serviço e monitoramento via shadow tables.

---

## ⏳ Pendente

### Fase G — Filtros de Qualidade (não implementado)
- Cooldown entre sinais do mesmo ativo (15 min)
- min_confidence MEDIA
- Deduplicação temporal (0.5×ATR window)

### Fase F — Demais backtests
- PETR4 (asset_id=10) — tentou rodar mas sem output (possível falta de dados M1 suficientes)
- VALE3 (asset_id=11) — idem
- BTCUSDm (asset_id=4) — não rodou
- Outros: XAGUSDm, USDJPYm, ETHUSDm, ITUB3 — não rodaram

**Prioridade**: Fase G (filtros) antes de mais backtests, pois o número elevado de sinais
(WDOFUT: 33/dia, EURUSDm: 12/dia) precisa de cooldown e dedup para ser útil em produção.

---

## 📁 Artefatos

| Caminho | Conteúdo |
|---------|----------|
| `storage/replay/opportunity_scanner/S26_XAUUSDM_CALIBRADO/` | Backtest Gold |
| `storage/replay/opportunity_scanner/S27_WDOFUT_CALIBRADO/` | Backtest Mini-Dólar |
| `storage/replay/opportunity_scanner/S28_EURUSDM_CALIBRADO/` | Backtest EUR/USD |
| `docs_geral/Planos/PLANO_BACKTEST_CALIBRACAO_MULTI_ATIVO_SMC_TRADER_7_0.md` | Plano completo |
| `docs_geral/Relatorios/Backtest/S26_XAUUSDM_calibrado_20260617.md` | Relatório S26 |
