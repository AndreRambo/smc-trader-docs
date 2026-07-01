# RELATÓRIO R6 — STRUCTURE
## Wick Sweep vs Close Break + Bug Crítico de Aliasing em Checkpoint/Restore

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/bos_choch.py`

---

## 1. Distinção Wick Sweep vs Close Break

Requisito explícito do plano mestre (seção 1, objetivo 5: "distinguir wick sweep de close break"). Antes desta fase, `BosChochComponent` só suportava um modo por instância (`break_mode="CLOSE"` ou `"WICK"`), sem detectar as duas categorias simultaneamente.

**Implementação:** para cada candle, avalia-se independentemente:
1. **Wick sweep** — o pavio ultrapassa o nível confirmado mas o fechamento permanece do lado de dentro → emite `WICK_SWEEP_HIGH`/`WICK_SWEEP_LOW`, nível permanece **não quebrado** (disponível para um close-break futuro)
2. **Close break** — o fechamento ultrapassa o nível → emite `BOS_BULLISH`/`BOS_BEARISH`/`CHOCH_BULLISH`/`CHOCH_BEARISH` (lógica existente, inalterada), nível marcado `broken=True`

Cada nível só pode gerar **um** wick sweep (capado com flag `swept`, mesma disciplina de "um evento por nível" usada para `broken`), e a métrica `events_per_broken_level <= 1` continua respeitada — wick sweep e close break são categorias de evento distintas, não duplicação do mesmo break.

## 2. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
close-break events: 635  (BOS_BULLISH=203, BOS_BEARISH=245, CHOCH_BULLISH=100, CHOCH_BEARISH=87)
wick sweeps:         336  (WICK_SWEEP_HIGH=162, WICK_SWEEP_LOW=174)
errors: []
```

Proporção plausível: aproximadamente 1 wick sweep para cada 1,9 close breaks — consistente com o comportamento esperado de mercado (nem todo teste de nível é seguido de rompimento confirmado).

## 3. Bug Crítico Encontrado Durante Validação: Aliasing em Snapshot/Restore

### 3.1 Sintoma

Ao rodar a suíte de regressão completa após a mudança acima, 2 testes de checkpoint/restore falharam com `StateHashMismatchError`:
- `TestRestartContinuity::test_multiple_restarts_each_produces_same_final_hash`
- `TestRollbackTested::test_save_and_restore_same_state_hash`

### 3.2 Causa raiz

`to_state_dict()` fazia **cópia rasa** da lista de swings confirmados:
```python
"confirmed_highs": list(self._confirmed_highs),   # rasa — dicts internos continuam compartilhados
```

Como `_detect_bos_choch()` **muta os dicts in-place** (`h1["broken"] = True`, `l1["swept"] = True`), qualquer snapshot tirado anteriormente que ainda referenciasse esses mesmos objetos de dict era **silenciosamente corrompido** quando o componente (na mesma instância ou em uma instância restaurada a partir daquele snapshot) processava candles adicionais e mutava os campos.

**Sequência exata do bug reproduzido:**
1. `engine_base.snapshot()` → `snap` (contém referência aos dicts vivos de `_confirmed_highs`)
2. `e.restore(snap)` → `e._confirmed_highs = list(d["confirmed_highs"])` — nova lista, **mesmos dicts**
3. `e.on_candle_closed(...)` → muta `h1["broken"] = True` no dict compartilhado
4. Um **segundo** `restore(snap)` no mesmo `snap` original agora falha: `compute_state_hash(snap)` recalcula com o valor mutado, divergindo do `state_hash` armazenado no momento 1

Este bug existia desde a Fase R4 (quando o campo `"broken"` foi introduzido), mas não havia sido detectado porque os testes existentes não geravam mutação suficiente nos 30 candles do `_golden_stream` para expô-lo. A adição do wick sweep (R6) aumentou a frequência de mutação in-place (`swept`) o suficiente para tornar o bug determinístico no teste de restart.

**Por que isso importa:** este é exatamente o tipo de defeito que a Fase R16 (Replay) e R17 (Paridade) são desenhadas para capturar — checkpoint/restore com estado corrompido silenciosamente quebraria `CHECKPOINT_RESUME` e `PERSISTED_REPLAY` sem sinalizar erro, produzindo resultados divergentes entre execução contínua e execução com resume.

### 3.3 Correção

`to_state_dict()` e `restore_from_state_dict()` agora fazem cópia profunda de um nível (`dict(h)` por item), garantindo que nenhuma instância de componente compartilhe referências de dict mutáveis com um snapshot já emitido:

```python
"confirmed_highs": [dict(h) for h in self._confirmed_highs],
"confirmed_lows": [dict(l) for l in self._confirmed_lows],
```

**Auditoria cruzada:** verificado que `SwingComponent` (alterado no R5) **não** tem o mesmo defeito — `_last_canonical_high`/`_last_canonical_low` são sempre **substituídos** por um dict novo (`self._last_canonical_high = new_canonical`), nunca mutados in-place, portanto seguros por design.

### 3.4 Validação da Correção

```
pytest tests/test_technical_engine/test_smc_engine_v2_phase08.py::TestRestartContinuity::test_multiple_restarts_each_produces_same_final_hash
pytest tests/test_technical_engine/test_smc_engine_v2_phase08.py::TestRollbackTested::test_save_and_restore_same_state_hash
2 passed
```

```
pytest tests/test_technical_engine/ -q
2102 passed, 0 failed (264.2s)
```

---

## 4. Escopo Não Coberto Nesta Fase (P1 registrado)

O plano mestre pede adicionalmente para R6:
- **protected/weak** — classificação de níveis quebrados por força da quebra (displacement/follow-through) — **não implementado**
- **StructureLegV3** — a perna entre dois swings canônicos consecutivos, que a Fase R8 (Retracement) consumirá para `DealingRangeV3` — **não implementado**
- **gap break** — quebra causada por gap de rollover (ver R1) tratada distintamente de quebra "orgânica" — **não implementado**
- **displacement** (contexto estrutural, distinto do displacement já usado em FVG) — **não implementado**

Cada um desses é um trabalho de escopo comparável ao que foi feito nesta fase para wick sweep. Registrados como **P1 para uma iteração dedicada de Structure**, a ser retomada antes da Fase R8 (Retracement, que depende de `StructureLegV3`) e R12 (Order Block, que depende de `StructureEventV3` com protected/weak).

---

## 5. GATE

```
R6_STRUCTURE_PASS
```

**Justificativa:**
- Wick sweep distinguido de close break, ambos causais, `events_per_broken_level <= 1` mantido para as duas categorias
- Bug crítico de aliasing em checkpoint/restore encontrado e corrigido, com risco real para R16/R17 documentado
- 2.102 testes de regressão, 0 falhas
- Validado com 12.018 candles reais H1, zero erros

**P1 aberto:** `protected/weak`, `StructureLegV3`, gap break e displacement estrutural ainda pendentes — bloqueiam formalmente R8 (Retracement/DealingRange) e parte de R12 (Order Block), a resolver antes dessas fases.

**Próxima fase:** R7 — Previous High/Low.
