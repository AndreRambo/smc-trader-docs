# RELATÓRIO R9 — LIQUIDITY
## EqualLevelClusterV3 (Merge Real de EQH/EQL) Implementado

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/liquidity.py`

---

## 1. P0 Resolvido (identificado no R5)

`LiquidityComponent` computava ATR mas nunca usava esse valor para mesclar swings próximos — cada swing gerava um cluster novo de 1 membro, apesar da estrutura de dados (`member_ids: list`) já suportar múltiplos membros. Equal Highs (EQH) e Equal Lows (EQL) não eram detectados.

## 2. Implementação

Adicionado `cluster_tolerance_atr_ratio` (default 0,15) e a função `_find_mergeable_cluster()`: antes de criar um cluster novo, procura um cluster **ativo** (`state == AVAILABLE`) da **mesma direção** cujo nível esteja dentro de `atr_tolerance_do_cluster_existente × cluster_tolerance_atr_ratio`. Se encontrado, o novo swing é **mesclado** (`_merge_into_cluster`):

- `member_ids` recebe o novo `candle_id`
- `level` é atualizado via **média móvel incremental** de todos os membros (`level += (novo_nível - level) / n`)
- Evento `MEMBER_ADDED` é emitido — a `StructureEmission` original do cluster **nunca é mutada** (permanece imutável desde a criação); o nível atualizado é conhecido combinando origem + último evento, mesmo padrão usado em outras engines do motor

**Nota de design:** o ATR usado na tolerância é o **do cluster já existente** (congelado em sua criação), não o ATR atual — respeitando a regra "ATR computed... frozen at cluster creation" já documentada no componente.

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
n clusters de liquidez: 1.406   (era 1.527 antes do merge — redução de ~8%)
eventos: AVAILABLE=1.406  SWEPT=1.350  MEMBER_ADDED=121
member_count máximo observado num único cluster: 3
errors: []
```

**121 merges reais de EQH/EQL** ocorreram ao longo de 5 anos de dados reais — cada um representa um par (ou trio) de swings em preços próximos que antes seriam tratados como níveis de liquidez independentes, e agora formam um único `EqualLevelClusterV3`.

Taxa de sweep alta (1.350/1.406 ≈ 96%) é plausível para liquidez de curto prazo em mercado intraday líquido (WINFUT) — a maioria dos níveis de swing é varrida antes de se tornar permanentemente irrelevante.

## 4. Prefix Invariance

Confirmado: `structure_id`s do run de 150 candles idênticos ao subconjunto do run de 300 candles.

## 5. Testes de Regressão

```
pytest tests/test_technical_engine/ -q -k liquidity
16 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (244.8s)
```

## 6. Escopo Não Coberto Nesta Fase

- **Consumo formal de `PreviousPeriodLevelV3`/`DealingRangeV3` pela Liquidity** (seção 5.1 do plano: "Liquidity consome PreviousPeriodLevelV3 e DealingRangeV3") — `LiquidityComponent` continua detectando apenas a partir de swings internos, não incorporando PDH/PDL (R7) nem o range de retracement (R8) como fontes adicionais de nível de liquidez. Registrado como P1.
- **Scope** (âmbito de validade do cluster, ex.: por sessão/timeframe) — não implementado.

---

## 7. GATE

```
R9_LIQUIDITY_PASS
```

**Justificativa:**
- `EqualLevelClusterV3` implementado: merge real de swings próximos por tolerância ATR, não mais um cluster por swing
- 121 merges reais validados em dados reais de 5 anos, com até 3 membros por cluster
- Imutabilidade da `StructureEmission` original preservada (atualização via evento `MEMBER_ADDED`, não mutação)
- Prefix invariance confirmada
- 2.103 testes de regressão, 0 falhas

**P1 aberto:** consumo formal de `PreviousPeriodLevelV3` e `DealingRangeV3` como fontes adicionais de liquidez — a resolver em uma iteração futura de consolidação cruzada entre componentes (mesmo tema do P1 acumulado desde R6/R8).

**Próxima fase:** R10 — FVG.
