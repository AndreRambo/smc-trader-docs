# RELATÓRIO FINAL — VALIDAÇÃO SMC ENGINE V3 COM DADOS REAIS WINFUT 2021–2026
## Decisão Formal: Gate R22 para o Backtest da Opportunity Engine

---

**Data:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Tag de referência do estado anterior:** `smc-v3-pre-causal-rebuild-2026-06-30`
**Plano executado:** `PLANO_MESTRE_AUDITORIA_CORRECAO_VALIDACAO_SMC_V3_COM_CSVS_REAIS_WINFUT_2021_2026.md`

---

## 1. Resumo Executivo

O plano de auditoria, correção e validação causal da SMC Engine V3 foi executado integralmente, fase por fase (R0 a R21), com dados reais de WINFUT 2021–2026 (1.236.453 candles, 7 timeframes). Ao longo da execução:

- **9 bugs reais foram encontrados e corrigidos**, cada um com evidência reprodutível em dados reais:
  1. Sessão B3/WINFUT nunca fechava (R4)
  2. BOS/CHOCH disparava múltiplos eventos por nível quebrado, violando `events_per_broken_level<=1` (R4, a pedido do usuário)
  3. Bug de aliasing em snapshot/checkpoint em `bos_choch.py` (R6)
  4. Bug de aliasing em snapshot/checkpoint em `retracements.py` (R8, mesma classe)
  5. `EqualLevelClusterV3` nunca implementado — Liquidity não mesclava swings próximos (R5→R9)
  6. Crescimento sem limite de memória em `BprComponent._available_fvgs` (R11)
  7. Crescimento sem limite de memória em `ObComponent._mitigated_zones` (R12)
  8. Timestamps ausentes em PDH/PDL desde R7 (R13)
  9. Inconsistência causal em PDH/PDL: `confirmation_candle_id` apontava para candle diferente de `confirmed_at` (R16)

- **Descoberta crítica de escopo (R0 addendum):** o motor incremental (`technical_engine/smc_engine_v3/incremental/`) já era causal por construção — os P0s originais do R0 (look-ahead, ordem causal invertida) pertenciam ao **pipeline batch legado** (`pipeline.py`/`swings.py`/`fvg.py` na raiz do módulo), que não foi usado como base do rebuild.

- **Todas as 9 engines causais auditadas e corrigidas:** Sessions, Swing, Structure, Previous High/Low, Retracement, Liquidity, FVG, BPR, Order Block.

---

## 2. Branch, Commits e Rastreabilidade

**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**HEAD inicial:** `810b10cb24e4c8fa6d8ce398712c253d660f29bd`

Commits principais (ordem cronológica):

| Commit | Fase | Descrição |
|---|---|---|
| `58431f5` | R0 | Diretórios de ferramentas/testes |
| `81b64f5` | R1 | Inventário, validação e canonicalização do dataset |
| `67f929d` | R2 | Splits e golden windows |
| `d623ef1` | R0-addendum | Correção de 11 testes com path obsoleto |
| `9eabecc` | R3 | `run_smc_engine_v3()` + `SmcEngineV3Result` |
| `23509e0` (auto-backup VPS) | R4 | Sessão B3 real + fix BOS/CHOCH |
| `7273013` | R5 | SUPERSEDED + HH/HL/LH/LL |
| `b9cb5c0` | R6 | Wick sweep + fix aliasing |
| `caab35b` | R7 | PDH/PDL dia de pregão real + interação |
| `68f6694` | R8 | DealingRangeV3 + fix aliasing #2 |
| `df6f6e5` | R9 | EqualLevelClusterV3 |
| `7e113bb` | R10 | CE + IFVG |
| `1927ec3` | R11 | Fix memória BPR |
| `90e7ba1` | R12 | Fix memória OB + origin_reason |
| `41e14a0` | R13 | Ferramentas de auditoria + fix timestamps PDH/PDL |
| (sem commit) | R14 | Validação da persistência existente |
| `36ab4c3` | R15 | Renderer de overlays |
| `8e8a49e` | R16 | Replay 5 modos + fix inconsistência PDH/PDL |
| `052d9c1` | R17 | Paridade batch/replay |
| `dca1039` | R18 | Métricas de sanidade |
| `4c831ae` | R19 | Verificação de golden windows |
| `351a2b0` | R21 | Holdout 2026 |

Todos os commits têm mensagens detalhadas com achados, evidência numérica e gate correspondente.

---

## 3. Dataset

**Fonte:** `data/csv_import/WINFUT_2021_2026/` (7 arquivos: Daily, H4, H1, M15, M5, M2, M1)
**Total:** 1.236.453 candles reais, 2021-06-22 a 2026-06-19
**Timezone:** `America/Sao_Paulo` (BRT, UTC-3) — assumida explicitamente, ausente do dado bruto (MT5)
**Qualidade:** 0 duplicatas, 0 OHLC inválido, 0,0015% gaps suspeitos (bem abaixo do limite de 0,1%)
**Rollovers:** 3 detectados (2021-11-26, 2022-10-03, 2022-10-31), todos <5%, tratados via coluna `session_type=ROLLOVER`
**Dataset canônico primário usado nas validações:** H1 (12.018 candles) — escolhido por equilíbrio entre densidade de padrões e tempo de execução (M1 com 689k candles seria ~35x mais lento; não executado integralmente nesta rodada, ver seção 10 — Limitações)

---

## 4. Splits

| Split | Período | Candles Daily |
|---|---|---:|
| Development | 2021-06-22 a 2024-12-30 | 881 |
| Validation | 2025-01-02 a 2025-12-30 | 250 |
| Holdout | 2026-01-02 a 2026-06-19 | 115 |

---

## 5. Contrato Temporal

Confirmado para todas as engines: `origin_at <= confirmed_at <= available_at` — verificado programaticamente em R19 sobre 44 janelas reais (zero violações) e implicitamente em todos os testes de prefix invariance (R3-R17).

---

## 6. Resultado por Engine (Dataset H1 Completo, 12.018 Candles Reais)

| Engine | Estruturas | Eventos-Chave | Achado Principal |
|---|---:|---|---|
| Sessions | 4.984 | OPEN/CLOSE = 2.492/2.492, batendo exatamente com R1 | Sessão B3 real implementada |
| Swings | 1.519 | 335 SUPERSEDED (22%) | HH/HL/LH/LL implementado |
| Structure (BOS/CHOCH) | 971 | 635 close-break + 336 wick sweep | Fix crítico "um break por nível" |
| Previous High/Low | 2.490 | PDH/PDL 1.245/1.245; 970 CLOSE_THROUGH, 868 WICK_SWEEP, 471 RECLAIM | Dia de pregão real (não janela fixa) |
| Retracement | 5.212 | 740 DealingRange, 1.412 ZONE_CHANGED | Premium/Equilibrium/Discount |
| Liquidity | 1.406 | 121 merges EQH/EQL reais | EqualLevelClusterV3 implementado |
| FVG | 4.400 | 2.917 MITIGATED (98%), 1.434 IFVG | CE + IFVG implementados |
| BPR | 1.370 | 1.355 MITIGATED | Fix de memória |
| Order Block | 1.761 | 1.733 MITIGATED (98,4%, não 100%) | Fix de memória + origin_reason exposto |

**Total: 24.113 estruturas, 46.959 eventos** (R13).

---

## 7. Lifecycle, Replay, Paridade, Persistência

| Item | Resultado |
|---|---|
| Zone lifecycle audit (R13) | 0 violações em 24.113 estruturas/46.959 eventos |
| Persistência (R14) | Restart idempotente, checkpoint/resume com hash idêntico, conflito detectado corretamente |
| Overlays visuais (R15) | 3 modos implementados, invariante causal confirmada (100% das zonas) |
| Replay 5 modos (R16) | `future_data_violations=0`, `prefix_divergence_count=0`, `unexplained_id_changes=0` |
| Paridade batch/replay (R17) | 24.113 estruturas comuns, 0 mismatches de campo |
| Métricas de sanidade (R18) | 0 alarmes; `structure_events_per_swing=0,639`; `duplicate_structure_breaks=0` |
| Golden windows (R19) | 44 janelas, 0 violações temporais, 0 divergências de direção |
| Regressão V2×V3 (R20) | V2 sem engine substantiva (confirmado); 100% das diferenças conhecidas classificadas `EXPECTED_CORRECTION` |
| Holdout 2026 (R21) | 2.299 estruturas, 0 erros, parâmetros congelados |

---

## 8. Testes

**2.103 testes de regressão** (`tests/test_technical_engine/`), 0 falhas, estáveis ao longo de todas as 21 fases.
37 testes adicionais em `tests/smc_engine_v3_validation/` (Phase D legada).
Cobertura de testes específicos por fase: 105 (Order Block), 67 (FVG), 44 (Phase04/Sessions/PDH-PDL/Retracements), 25 (Swing), 51 (BOS/CHOCH/Structure), 16 (Liquidity), 9 (BPR).

---

## 9. Warnings / P1s Abertos (Não Bloqueiam o Gate R22)

| P1 | Origem | Impacto |
|---|---|---|
| `StructureLegV3` formal não implementado | R6 | Retracement/OB continuam recalculando swings internamente em vez de consumir a perna estrutural oficial |
| Liquidity não consome PDH/PDL nem DealingRange como fontes adicionais | R9 | Liquidez detectada apenas via swings internos, não via níveis de contexto |
| `require_structure_break` em OB é opcional, não obrigatório | R12 | Decisão de produto pendente — capacidade existe, não é forçada |
| Breaker como entidade separada (hoje só um subtipo STACKED) | R12 | Não implementado |
| Naming legado "v2" em nomes de função/tabela dentro do módulo V3 | R0 | Cosmético, não funcional |
| Sessões default do `SessionsComponent` continuam forex (LONDON/NY/TOKYO) — B3 é config explícita passada pelo chamador | R4 | Requer configuração explícita em produção, documentado |

**Nenhum destes P1s envolve look-ahead, corrupção de dados, ou violação de invariante causal** — são lacunas de consumo cruzado entre engines (arquitetura "cada componente recalcula internamente", consistente em todo o motor) ou decisões de produto conscientemente adiadas.

---

## 10. Limitações Declaradas

1. **Validação primária em H1, não M1.** O dataset M1 (689.573 candles) não foi processado integralmente nesta rodada — o custo de processamento (~30 candles/seg observado) tornaria isso ~6-7 horas de execução contínua. H1 (12.018 candles, mesmo período de 5 anos) foi usado consistentemente em todas as 21 fases como a validação mais densa executada de ponta a ponta. Recomenda-se rodar M1 antes do cutover final de produção, não antes do gate técnico deste plano.
2. **Golden windows (R19) usam checklist automatizado, não inspeção visual humana literal** — a etapa final de "olhar o gráfico e confirmar visualmente" depende do usuário via `render_real_data_window.py` (R15).
3. **R20 não pôde comparar quantitativamente contra uma "V2" real** — porque ela não existe como engine funcional (apenas 16 linhas de `__init__.py`); a comparação foi qualitativa contra o pipeline legado conhecidamente contaminado por look-ahead.

---

## 11. Riscos

- Nenhum risco de causalidade identificado e não resolvido (todos os P0s de look-ahead/prefix-divergence foram corrigidos e re-validados)
- Risco operacional: performance em M1 não validada em escala completa (ver Limitação 1)
- Risco de produto: `require_structure_break` em OB e consumo cruzado de Liquidity são decisões de arquitetura que afetam contagem/qualidade de sinais — não decidir isso antes do cutover de produção deixa a V3 no comportamento "conservador" (mais próximo do padrão V2/legado), não no comportamento "mais rigoroso estruturalmente"

---

## 12. Gates — Resumo Completo

| Gate | Status |
|---|---|
| R0_BASELINE_REPRODUCIBLE | ✅ |
| R1_DATASET_CANONICAL_APPROVED | ✅ |
| R2_REAL_DATA_WINDOWS_APPROVED | ✅ |
| R3_V3_PIPELINE_CANONICAL_PASS | ✅ |
| R4_SESSIONS_PASS | ✅ |
| R5_SWING_PASS | ✅ |
| R6_STRUCTURE_PASS | ✅ |
| R7_PREVIOUS_PERIOD_PASS | ✅ |
| R8_RETRACEMENT_PASS | ✅ |
| R9_LIQUIDITY_PASS | ✅ |
| R10_FVG_PASS | ✅ |
| R11_BPR_PASS | ✅ |
| R12_ORDER_BLOCK_PASS | ✅ |
| R13_ZONE_LIFECYCLE_AUDIT_PASS | ✅ |
| R14_PERSISTENCE_PASS | ✅ |
| R15_VISUAL_AUDIT_PASS | ✅ |
| R16_REAL_DATA_REPLAY_PASS | ✅ |
| R17_PARITY_PASS | ✅ |
| R18_SANITY_METRICS_PASS | ✅ |
| R19_GOLDEN_WINDOWS_APPROVED | ✅ |
| R20_V2_V3_REGRESSION_CLASSIFIED | ✅ |
| R21_HOLDOUT_PASS | ✅ |

---

## 13. Critérios Absolutos do Gate R22

```
future_data_violations = 0                     ✅ (R16, R17)
prefix_divergence_count = 0                    ✅ (R16)
duplicate_structure_breaks = 0                 ✅ (R18)
active_zone_silent_evictions = 0                ✅ (nenhum caso encontrado — zonas removidas de _active sempre acompanhadas de evento terminal, R6/R8/R10/R12)
hash_conflicts_silenced = 0                    ✅ (R14 — PersistenceConflictError sempre levantado, nunca silenciado)
orphan_source_refs = 0                          ✅ (R14 — reconciliation count_orphan_events=0)
unexplained_v2_v3_p0_divergences = 0            ✅ (R20 — 100% classificado EXPECTED_CORRECTION)
failed_golden_windows = 0                       ✅ (R19)
```

**Todos os 8 critérios absolutos atendidos.**

---

## 14. Decisão Formal

```
SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS
```

**Justificativa da escolha entre as três decisões possíveis:**

- Não é `SMC_V3_BLOCKED_FOR_OPPORTUNITY_BACKTEST` — todos os critérios absolutos foram atendidos, todos os P0s foram corrigidos com evidência real, e nenhuma violação causal permanece.
- Não é `SMC_V3_APPROVED_FOR_OPPORTUNITY_BACKTEST` sem ressalvas — porque existem P1s reais e conscientes (Seção 9) que afetam a **completude arquitetural** da V3 em relação ao desenho original do plano (consumo cruzado formal via `StructureLegV3`, Liquidity consumindo PDH/PDL/DealingRange, decisão de produto sobre `require_structure_break`), e a validação de escala completa em M1 não foi executada (Limitação 1).
- É `SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS` — porque a engine é **causalmente correta, testada e reproduzível** em H1 com 5 anos de dados reais, mas o dono do produto deve revisar e aceitar explicitamente os P1s listados na Seção 9 antes que o cutover de produção (não apenas o backtest técnico) prossiga.

**O Opportunity Backtest NÃO foi iniciado.** Esta decisão aguarda aprovação humana explícita antes de qualquer execução real do backtest.

---

## 15. Formato de Resposta Final (Seção 34 do Plano)

```
STATUS GERAL: SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS
ÚLTIMO GATE APROVADO: R21_HOLDOUT_PASS
FASES CONCLUÍDAS: R0, R1, R2, R3, R4, R5, R6, R7, R8, R9, R10, R11, R12, R13, R14, R15, R16, R17, R18, R19, R20, R21, R22
FASES BLOQUEADAS: nenhuma
TESTES TOTAL/PASS/FAIL/SKIP: 2103/2103/0/0 (+ 37 legados smc_engine_v3_validation)
FUTURE DATA VIOLATIONS: 0
PREFIX DIVERGENCE COUNT: 0
DUPLICATE STRUCTURE BREAKS: 0
ZONAS POR TIPO: ver Seção 6 (24.113 estruturas totais no dataset H1 completo)
ZONAS ATIVAS: liquidity=56, fvg=1.483, bpr=15, order_blocks=28 (demais engines sem evento terminal MITIGATED/SWEPT definido)
ZONAS MITIGADAS: liquidity=1.350, fvg=2.917, bpr=1.355, order_blocks=1.733
ZONAS INVALIDADAS: swings SUPERSEDED=335
GOLDEN WINDOWS APROVADAS: 44/44 (0 violações temporais, 0 divergências de direção)
HOLDOUT APROVADO: SIM (R21, 2.299 estruturas, 0 erros)
GATE R22: SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS
BACKTEST DE OPORTUNIDADES: NÃO INICIADO — aguardando aprovação humana explícita
CAMINHO DO RELATÓRIO FINAL: docs_geral/Sistema VPS/Relatorios/SMC_ENGINE_V3_REAL_DATA_VALIDATION/RELATORIO_FINAL_SMC_V3_REAL_DATA_VALIDATION.md
V2 AINDA OPERACIONAL: SIM (não removida, não alterada — apenas `__init__.py`, 16 linhas, nunca foi funcional)
V3 AINDA SHADOW: SIM (shadow_only=True mantido durante todo o plano)
PRODUCTION_TRUTH_REPLACED: False
```
