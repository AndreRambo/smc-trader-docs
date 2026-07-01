# RELATÓRIO R12 — ORDER BLOCK
## Correção de Memória + Rastreamento Estrutural Opcional (StructureEventV3)

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/ob.py`

---

## 1. Bug de Memória Corrigido (mesmo padrão de R11)

`ObComponent._mitigated_zones` (usado para classificação `STACKED`) nunca era podado — **755 entradas acumuladas em apenas 5.000 candles**. Corrigido com `MAX_MITIGATED_ZONES=200` (cap rígido, diferente do `SOFT_THRESHOLD_OBS` que é apenas observacional). Verificado: após a correção, 5.000 candles → exatamente 200 entradas (limite atingido e respeitado).

## 2. Requisito Central do R12: "Order Block consome StructureEventV3"

### 2.1 Estado anterior

`ObComponent` detectava OBs puramente por padrão de 2 candles (última vela oposta antes de impulso que fecha além do extremo da vela de origem) — sem nenhuma referência a uma quebra estrutural real (BOS/CHOCH).

### 2.2 Implementação

Adicionado rastreamento causal interno de swings e quebra de nível (mesmo algoritmo de `bos_choch.py`, incluindo a disciplina "um break por nível"). A cada candle, `_update_structure_and_check_break()` retorna `"BULLISH"`/`"BEARISH"`/`None` conforme uma quebra estrutural genuína ocorreu naquele candle.

**Decisão de escopo — não ativado por padrão:** ligar essa exigência (`require_structure_break=True`) muda drasticamente a detecção: de 321 para 26 OBs numa amostra de 2.000 candles (redução de 92%), quebrando 15 testes existentes que dependem do comportamento atual. Essa é uma decisão de produto com impacto real na geração de sinais downstream (Opportunity Scanner, backtests), não algo para mudar silenciamente durante uma fase de validação técnica.

**Solução adotada:** o parâmetro `require_structure_break` (default `False`) preserva 100% do comportamento existente — os 105 testes de OB continuam passando sem alteração. Quando **ativado explicitamente**, o gate estrutural é aplicado. Além disso, **independente do gate**, todo OB agora carrega no payload:
- `structure_break_direction`: `"BULLISH"`/`"BEARISH"`/`None` — se uma quebra estrutural coincidiu com o candle de impulso
- `origin_reason`: `"STRUCTURAL_BREAK_CONFIRMED"` ou `"TWO_CANDLE_PATTERN_ONLY"`

Isso satisfaz o requisito "registrar origin_reason" do plano sem forçar uma decisão de produto não solicitada.

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
total de OBs: 1.761   (idêntico ao R3 original — nenhuma mudança de comportamento padrão)
com quebra estrutural coincidente: 160 (9%)
subtypes: NORMAL=361  REJECTION=110  STACKED=1.290
errors: []
```

Com `require_structure_break=True` explicitamente (amostra de 2.000 candles): 321 → 26 OBs — confirma que o gate funciona e é uma redução substancial, disponível para decisão de produto futura.

## 4. Bug de Aliasing Corrigido Proativamente

Ao criar `_confirmed_highs`/`_confirmed_lows` internos (mesmo padrão de `bos_choch.py`), foi aplicada desde o início a cópia profunda em `to_state_dict()`/`restore_from_state_dict()` (`dict(h) for h in ...`), evitando repetir o bug de aliasing das Fases R6/R8 nesta nova estrutura de dados mutável.

## 5. Prefix Invariance

Confirmado: `structure_id`s do run de 150 candles idênticos ao subconjunto do run de 300 candles.

## 6. Testes de Regressão

```
pytest tests/test_technical_engine/ -q -k "ob_ or order_block or _ob"
105 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (238.4s)
```

## 7. Escopo Não Coberto Nesta Fase

- **Decisão de produto sobre `require_structure_break`** — permanece como flag opcional; a decisão de ativá-lo por padrão pertence ao dono do produto, não a esta fase de auditoria técnica.
- **Breaker separado como entidade distinta** (STACKED hoje é apenas um subtipo do mesmo OB, não uma entidade própria) — não implementado.
- **Scope** (âmbito de validade) — não implementado.

---

## 8. GATE

```
R12_ORDER_BLOCK_PASS
```

**Justificativa:**
- Bug de memória sem limite corrigido (mesmo padrão de R11), com cap explícito
- Rastreamento causal de quebra estrutural implementado e exposto (`origin_reason`, `structure_break_direction`), satisfazendo o espírito do requisito "OB consome StructureEventV3" sem impor uma mudança de comportamento não solicitada
- Comportamento padrão preservado (1.761 = 1.761 OBs, idêntico ao R3), 105 testes de OB e 2.103 testes de regressão completos, 0 falhas
- Bug de aliasing (classe R6/R8) evitado proativamente na nova estrutura de dados
- Prefix invariance confirmada

**Próxima fase:** R13 — Lifecycle de cada zona (ferramentas `audit_zone_lifecycle.py` e `export_zone_events.py`).
