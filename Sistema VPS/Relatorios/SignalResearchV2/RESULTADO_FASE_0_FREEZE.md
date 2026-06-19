# RESULTADO FASE 0 — FREEZE E MANIFESTOS — 2026-06-17

**Status**: ✅ CONCLUÍDO

---

## 1. Documentos — SHA-256

| Documento | Hash |
|-----------|------|
| PLANO_EXECUTIVO_COMPARACAO_OTIMIZACAO_ROBUSTA_WINFUT.md | `f656e6212fab45155c5f4e84972723ed248c40414df54c79db4e84d31384b5c2` |
| COMO_O_STOP_E_DEFINIDO_NO_SMC.md | `932f9b2cb0342fc186ae61b4dba305c3920413c2e40ee1dca1b46061264eca86` |
| PLANO_SIGNAL_CANDIDATE (anterior) | `162a93bdfddfd13a45037588ca958c126d04a639278ae6f88730810dfffb2d3e` |

---

## 2. Branch, Commit

| Campo | Valor |
|-------|-------|
| Branch | `fix/roadmap-closeout-e2e-soak-v1` |
| Commit | `27b17a6ba39662573a5a0a1cf14e26e887e4feda` |
| Remote | `github-backup` |

---

## 3. Sistemas Congelados

| Sistema | Manifest | Status |
|---------|----------|--------|
| CONTROL_A | `CONTROL_A_FREEZE_V1.json` | ✅ Congelado |
| CANDIDATE_B_V2 | `CANDIDATE_B_V2_FREEZE.json` | ✅ Congelado (histórico) |
| CANDIDATE_B_V3 | `CANDIDATE_B_V3_FREEZE.json` | ✅ Congelado (baseline) |

---

## 4. Resumo das Regras

### Entradas (6 tipos)
ZONE_EDGE, ZONE_MIDPOINT, FVG_50_PERCENT, MSS_RETEST, CONFIRMATION_CLOSE, MARKET_AFTER_TRIGGER

### Stops (prioridade M15)
PROTECTED_SWING_M15 → LIQUIDITY_SWEEP → PROTECTED_SWING_M5 → CONFIRMATION_SWING → OB_EXTREME  
Buffer = max(tick×min_ticks, spread+slippage, ATR×buffer_atr)

### Alvos (hierarquia estrutural)
- Pró-tendência: TP1=liquidez interna/M15, TP2=liquidez externa/H4, TP3=HTF
- Contra-tendência: TP1=primeira estrutura, TP2=fim retração, TP3=ausente

### Backtest
Walk-forward temporal, STOP_FIRST_CONSERVATIVE, M1 execução, parciais 50/25/25, custos reais

### Fases (10)
0=Freeze, 1=Dataset, 2=Unified Engine, 3=AB Definitivo, 4=Decisão Baseline, 5=Candidate C, 6=Nested WF, 7=Stress, 8=Holdout, 9=Forward Shadow, 10=Decisão Final

---

## 5. Inconsistências Identificadas

### 5.1 Holdout contaminado (CRÍTICO)
A v3 (parciais 50/25/25) foi criada **após** observar resultados do período chamado "holdout" (Mai–Jun 2026). O holdout da v3 NÃO é limpo. O plano exige um **novo holdout** posterior ao freeze da v3 para qualquer conclusão definitiva.

### 5.2 CONTROL_A não simulado trade-a-trade (CRÍTICO)
A expectancy do CONTROL_A (~-0.295R) foi **estimada** a partir de taxas agregadas (TP1% × 0.6R + Stop% × -1.0R). O CANDIDATE_B foi simulado trade a trade com parciais, M1 execução, e custos. A comparação não é justa.

### 5.3 Denominadores diferentes (MÉDIO)
- TP1 condicional (survived stop): CANDIDATE_B 95.6% vs CONTROL_A 88.6%
- TP1 all entries: CANDIDATE_B 68.8% vs CONTROL_A 64.9%
A métrica primária do plano é `TP1_BEFORE_STOP_ALL_ENTRIES_RATE`, não a condicional.

### 5.4 D1 anômalo (CRÍTICO)
10.056 candles D1 em ~4.2 anos = ~2.394 candles/ano. O esperado para D1 (dias corridos) é ~365/ano, ou ~252/ano (pregões B3). 10.056 / 4.2 = 2.394/ano — isso sugere que os candles D1 NÃO são diários. Possível: dados de 4H ou outro timeframe incorretamente rotulado como D1.

### 5.5 Contratos mistos (MÉDIO)
- WIN$N contínuo para D1/H4
- WINM26 contrato atual para M1/M2/M5/M15
Essa mistura precisa de auditoria de ajuste de preços no rollover.

### 5.6 S4 Breaker sem sinais (BAIXO)
Setup implementado mas nunca gerou sinais reais. Deve ficar fora da otimização.

### 5.7 Sinais sobrepostos não controlados (MÉDIO)
Sem cooldown, sem dedup, sem max_open_positions=1, sem bloqueio de sinais opostos.

### 5.8 Dataset insuficiente para decisão definitiva (CRÍTICO)
6.3 meses de dados intraday. O plano exige mínimo 12 meses. Status: `INSUFFICIENT_FOR_FINAL_OPTIMIZATION`.

---

## 6. Riscos

| Risco | Severidade | Mitigação |
|-------|------------|-----------|
| Decisão com dados insuficientes | CRÍTICO | Marcar INSUFFICIENT_DATA, prosseguir como exploratório |
| Holdout contaminado | CRÍTICO | Definir novo holdout futuro |
| D1 incorreto falseia HTF bias | CRÍTICO | Auditar na FASE 1 |
| Overfitting por nested WF com poucos dados | ALTO | Restringir espaço de busca |
| Comparação injusta A/B | ALTO | Unificar motor de execução |

---

## 7. Arquivos Afetados

### Criar (FASE 0)
- `technical_engine/signal_research_v2/__init__.py`
- `technical_engine/signal_research_v2/CONTROL_A_FREEZE_V1.json`
- `technical_engine/signal_research_v2/CANDIDATE_B_V2_FREEZE.json`
- `technical_engine/signal_research_v2/CANDIDATE_B_V3_FREEZE.json`

### Não alterar
- `technical_engine/opportunity_scanner/` (CONTROL_A)
- `technical_engine/smc_engine_v2/` (STABLE_FROZEN_V2)
- `technical_engine/signal_candidate_v1/` (CANDIDATE_B)
- `technical_engine/signal_backtest_v1/` (CANDIDATE_B backtest)

---

## 8. Próxima Fase

**FASE 1 — Auditoria e Dataset Canônico WINFUT V2**

Objetivo: Auditar D1, verificar timeframes, resolver mistura de contratos, criar dataset canônico versionado.
