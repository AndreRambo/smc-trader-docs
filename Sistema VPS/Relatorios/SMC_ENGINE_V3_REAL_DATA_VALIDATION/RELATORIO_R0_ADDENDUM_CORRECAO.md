# ADENDO AO RELATÓRIO R0 — CORREÇÃO DE ESCOPO
## Descoberta Durante a Fase R3: Cobertura de Testes Subestimada

---

**Data/Hora:** 2026-07-01
**Motivo:** Ao iniciar a Fase R3 (Pipeline V3), a leitura detalhada de `technical_engine/smc_engine_v3/incremental/` revelou que o inventário original do R0 (`RELATORIO_R0_BASELINE_SMC_V3.md`, seção 5) estava **incompleto**. Este adendo corrige o registro, conforme exigido pela regra do plano mestre: "não ocultar exceções" e "não declarar PASS sem testes".

---

## 1. O Que o R0 Original Errou

O R0 executou `pytest tests/smc_engine_v3/ tests/smc_engine_v3_validation/` e reportou **37 testes** como sendo toda a cobertura V3 disponível. Isso **não incluiu** o diretório `tests/test_technical_engine/`, que contém **241 arquivos de teste** relacionados ao `smc_engine_v3/incremental/`.

## 2. Resultado Real

```
pytest tests/test_technical_engine/ -q
```

**Resultado: 2.091 PASSED, 11 FAILED, em 246,55s.**

Os 11 testes que falham são **exclusivamente falhas de path obsoleto** — testes de "static safety" que fazem `open()` direto em `technical_engine/smc_engine_v2/incremental/components/*.py`, um caminho que não existe mais desde que a lógica foi consolidada em `technical_engine/smc_engine_v3/incremental/`. Não são falhas de lógica causal ou de corretude.

## 3. Arquitetura Real Descoberta

Existe um **motor incremental causal já implementado e testado** em `technical_engine/smc_engine_v3/incremental/`, distinto do pipeline batch legado (`pipeline.py`, `swings.py`, `fvg.py` na raiz do módulo, que SÃO os arquivos com look-ahead documentados no R0 original):

- `incremental/engine.py` — `SmcEngineV2Incremental`: processa candle-a-candle via `on_candle_closed()`, com verificação de sequência monotônica, detecção de gap, idempotência de duplicatas, hash de estado determinístico.
- `incremental/components/swings.py` — `SwingComponent`: confirma um pivô **somente depois** que `swing_length` candles subsequentes já foram processados via `process(candle)` — nunca acessa dados futuros não recebidos. **Causal por construção.**
- `incremental/components/fvg.py` — `FvgComponent`: formação de 3 candles confirmada em C3, `first_eligible_candle_id` = candle após C3. **Causal por construção.**
- `incremental/components/ob.py`, `bos_choch.py`, `liquidity.py`, `bpr.py`, `previous_high_low.py`, `sessions.py`, `retracements.py` — mesma filosofia, cada um com lifecycle (AVAILABLE → TOUCHED → PARTIALLY_FILLED → MITIGATED), IDs determinísticos via SHA-256, e snapshot/restore serializável.
- `incremental/shadow_runtime.py` — já monta os 9 componentes num engine único (`_build_engine()`), com persistência SQLite, checkpoint/restore e feature flag `SMC_V2_INCREMENTAL_SHADOW`.
- `incremental/adapter_batch.py` — `BatchAdapter`: itera uma lista de `CandleEnvelope` chamando `on_candle_closed()` sequencialmente — infraestrutura pronta para um driver batch real.

## 4. Reavaliação dos P0s do R0 Original

| P0 Original | Status Revisado |
|---|---|
| P0-01 (sem `run_smc_engine_v3()`) | **Ainda válido** — não existe um driver batch que use `incremental/` sobre CSVs reais. Este é o trabalho real da Fase R3: criar o driver, não reescrever a detecção. |
| P0-02 (ordem causal invertida) | **Parcialmente válido** — aplica-se ao `pipeline.py` legado. O `incremental/engine.py` tem `_COMPONENT_ORDER` com Sessions por último (mesmo defeito), mas cada componente causal internamente rastreia seus próprios swings/estado — não depende estritamente da ordem de execução no mesmo tick para a maioria dos casos. **Exceção confirmada:** `ObComponent` não consome `StructureEventV3` do `BosChochComponent` (cada um roda detecção própria e independente) — isto é uma divergência real do contrato do plano mestre (seção 5.1: "Order Block consome StructureEventV3"), a ser tratada nas Fases R6/R12. |
| P0-03 (look-ahead em `swings.py`) | **Válido apenas para o pipeline BATCH legado** (`smc_engine_v3/swings.py`, função vetorizada). O `incremental/components/swings.py` **não tem este problema** — é causal por construção. |
| P0-04 (look-ahead em `fvg.py`) | **Válido apenas para o pipeline BATCH legado** (`smc_engine_v3/fvg.py`). O `incremental/components/fvg.py` **não tem este problema**. |

## 5. Decisão de Escopo para R3+

Em vez de reescrever os algoritmos de detecção do zero (que já existem, corretos e testados em `incremental/`), a Fase R3 vai:

1. Criar `pipeline_v3.py` com `run_smc_engine_v3()` como **driver batch real** sobre `incremental/engine.py` + `BatchAdapter`, alimentado pelos CSVs canônicos reais de R1.
2. Corrigir `_COMPONENT_ORDER` para refletir a ordem causal oficial do plano (Sessions primeiro).
3. Documentar como P1 aberto (a resolver em R6/R12) a falta de consumo cruzado `OB ← StructureEventV3`.
4. O pipeline batch legado (`pipeline.py`/`swings.py`/`fvg.py` na raiz) **não será usado como base do V3** — permanece como está, sem alteração, servindo apenas de referência histórica (nunca foi removido, per regra de não deletar V2).
5. Corrigir os 11 testes com path obsoleto (mudança mecânica de string, sem risco).

## 6. Testes Totais V3 Confirmados (Corrigido)

| Fonte | Testes | PASS | FAIL |
|---|---:|---:|---:|
| `tests/smc_engine_v3_validation/` | 37 | 37 | 0 |
| `tests/test_technical_engine/` | 2.102 | 2.091 | 11 (path obsoleto, não-lógico) |
| **TOTAL** | **2.139** | **2.128** | **11** |

---

## 7. Impacto no Gate R0

O gate `R0_BASELINE_REPRODUCIBLE` permanece válido — o baseline É reproduzível, e agora com evidência muito mais forte (2.128 testes passando, não 37). O status inicial do plano `SMC_V3_SEMANTICALLY_INVALID_BLOCKED` refere-se especificamente ao **pipeline batch legado usado como se fosse a V3 canônica** — não ao motor incremental causal, que está em estado significativamente mais avançado do que o R0 original registrou.

**Este adendo não substitui o RELATORIO_R0_BASELINE_SMC_V3.md — complementa e corrige.**
