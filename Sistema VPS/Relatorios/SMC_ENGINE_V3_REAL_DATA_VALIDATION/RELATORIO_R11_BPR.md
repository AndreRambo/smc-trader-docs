# RELATÓRIO R11 — BPR
## Correção de Crescimento Sem Limite em `_available_fvgs`

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/bpr.py`

---

## 1. Estado Anterior (auditoria)

`BprComponent` já atendia à maior parte dos requisitos do R11: consome apenas FVGs disponíveis (rastreadas internamente, mesmo padrão dos demais componentes), forma BPR no candle correto, `source IDs` (`bullish_fvg_id`/`bearish_fvg_id`) no payload, `available_at` causal, sem deduplicação com dados futuros, sem limite fixo de preço específico do WINFUT (usa fração de overlap, não valor absoluto).

## 2. Bug de Performance/Memória Encontrado

`self._available_fvgs` (lista de FVGs rastreadas internamente para checagem de overlap) **nunca era podada** — todo FVG detectado permanecia na lista para sempre, mesmo depois de ultrapassar `max_temporal_gap` (o limite de candles usado para decidir se duas FVGs podem formar um BPR).

**Medição real:** após processar apenas 5.000 candles reais de WINFUT H1, `_available_fvgs` continha **1.257 entradas acumuladas**. Para o dataset M1 completo (689.573 candles), a extrapolação linear sugere dezenas de milhares de entradas nunca liberadas — um problema real de memória e degradação de performance O(n²) na checagem de overlap (`for other in opposite: ...`) durante a Fase R16 (replay completo).

## 3. Correção

Após adicionar cada novo FVG detectado, a lista é podada removendo entradas cujo `sequence_ordinal` esteja além de `max_temporal_gap` candles atrás do candle atual — exatamente o mesmo critério já usado em `_try_detect_fvg_and_bpr` para decidir se um par pode formar BPR (`if gap > self.max_temporal_gap: continue`). A poda é matematicamente equivalente: uma entrada podada nunca mais poderia formar um BPR de qualquer forma, então **remover não altera nenhum resultado observável**.

**Resultado da correção:** 5.000 candles → 8 entradas ativas (antes: 1.257).

## 4. Validação: Poda Não Altera Detecção

```
Antes da correção (R3, dataset completo): bpr_count = 1.370
Depois da correção (R11, dataset completo): bpr_count = 1.370  (idêntico)
```

**Resultado com Dados Reais (12.018 candles H1, 2021–2026):**

```
BPR_BULLISH=668  BPR_BEARISH=702   (total 1.370 — idêntico ao R3 antes da correção)
eventos: AVAILABLE=1.370  MITIGATED=1.355
errors: []
```

Confirma que a poda é uma otimização pura de memória, sem efeito colateral no resultado.

## 5. Testes de Regressão

```
pytest tests/test_technical_engine/ -q -k bpr
9 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (258.4s)
```

## 6. Escopo Não Coberto Nesta Fase

- **Lifecycle mais granular** (TOUCHED como estado intermediário antes de MITIGATED) — hoje o BPR só tem `AVAILABLE → MITIGATED`, sem distinguir "tocou a zona" de "cruzou o ponto médio". Funcionalmente correto, mas menos granular que o lifecycle do FVG (que tem TOUCHED/PARTIALLY_FILLED/CE). Não bloqueante, registrado como observação para eventual refinamento futuro.

---

## 7. GATE

```
R11_BPR_PASS
```

**Justificativa:**
- BPR consome apenas FVGs disponíveis, com `source_ids` corretos, sem lookahead
- Bug real de crescimento sem limite de memória encontrado e corrigido antes de impactar a Fase R16 (replay em escala)
- Correção matematicamente equivalente ao comportamento anterior (1.370 = 1.370, confirmado com dados reais completos)
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R12 — Order Block.
