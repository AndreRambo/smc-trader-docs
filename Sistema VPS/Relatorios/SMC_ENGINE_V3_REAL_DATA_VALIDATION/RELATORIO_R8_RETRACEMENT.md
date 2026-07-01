# RELATÓRIO R8 — RETRACEMENT / DEALING RANGE
## DealingRangeV3 (Premium/Equilibrium/Discount) + Segundo Bug de Aliasing Corrigido

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/retracements.py`

---

## 1. Segundo Bug de Aliasing Encontrado (mesma classe do R6)

Antes de implementar o DealingRangeV3, foi feita uma auditoria sistemática do padrão de bug encontrado em R6 (`to_state_dict()` com cópia rasa de dicts mutados in-place) em **todos** os 9 componentes do motor incremental. `RetracementsComponent` tinha o mesmo defeito: `_check_level_touches()` muta `lvl["touched"] = True` in-place, mas `to_state_dict()` fazia `"active_levels": list(self._active_levels)` (cópia rasa).

**Reprodução:** um nível Fibonacci recém-emitido com `touched=False` foi capturado num snapshot; ao processar mais candles que tocavam esse nível na mesma instância viva, o snapshot original passou a mostrar `touched=True` e `snap.state_hash == compute_state_hash(snap)` retornou `False` — confirmando corrupção silenciosa do snapshot já emitido.

**Correção:** mesma solução do R6 — cópia profunda de um nível (`dict(x)` por item) em `to_state_dict()`/`restore_from_state_dict()` para `confirmed_highs`, `confirmed_lows`, `anchor`, `opposite` e `active_levels`.

**Auditoria de todos os componentes (resultado):** apenas `bos_choch.py` (R6) e `retracements.py` (R8) tinham este padrão. `fvg.py`, `bpr.py`, `liquidity.py`, `ob.py` usam objetos dataclass com método `.to_dict()` que sempre constrói um dict novo a partir dos atributos atuais — seguros por design. `sessions.py` (R4) e `previous_high_low.py` (R7) já foram escritos com cópia profunda desde suas respectivas fases. **Nenhum outro componente tem este defeito.**

## 2. DealingRangeV3 Implementado

Requisito central do R8: classificar o range entre o anchor Fibonacci e o swing oposto em zonas Premium/Equilibrium/Discount.

- **DEALING_RANGE** — nova estrutura emitida junto com os níveis Fibonacci (`top`, `bottom`, `equilibrium` = ponto médio) sempre que anchor + oposto estão disponíveis
- **ZONA (PREMIUM/DISCOUNT/EQUILIBRIUM)** — rastreada por candle via evento `ZONE_CHANGED`, comparando o fechamento contra o equilíbrio (banda de tolerância de 5% do range em torno do meio conta como `EQUILIBRIUM`); emitido **somente na transição** (não a cada candle), evitando spam
- Reset explícito de `_dealing_range` sempre que o anchor muda (evita vazamento de range antigo entre diferentes pernas de tendência)

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
FIBONACCI_ANCHOR: 772   FIBO_LEVEL: 3.700   DEALING_RANGE: 740
eventos: AVAILABLE=3.700  TOUCHED=2.032  ZONE_CHANGED=1.412  ANCHOR_CHANGED=771
errors: []
```

**Consistência:** 740 DEALING_RANGE muito próximo de 772 FIBONACCI_ANCHOR (nem todo anchor tem um oposto confirmado imediatamente, daí a diferença) — proporção plausível. 1.412 transições de zona ao longo de 5 anos é consistente com o preço cruzando o equilíbrio de cada range dezenas de vezes por perna de tendência.

## 4. Prefix Invariance

Confirmado: `structure_id`s do run de 150 candles são idênticos ao subconjunto correspondente de um run de 300 candles — nenhuma retroatividade no cálculo do range ou das zonas.

## 5. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (233.8s)
```

## 6. Escopo Não Coberto Nesta Fase

- **Consumo formal de `StructureLegV3`** (P1 do R6, ainda não implementado) — `RetracementsComponent` continua rastreando seus próprios swings internamente (mesmo padrão usado em todos os outros componentes desde R3), não consumindo a perna estrutural oficial do `SwingComponent`/Structure. Mantém-se como P1 aberto, agora acumulando três fases (R6, R7 parcialmente resolvido, R8).
- **origin breach / supersession** de níveis Fibonacci quando o preço rompe o anchor original — não implementado.

---

## 7. GATE

```
R8_RETRACEMENT_PASS
```

**Justificativa:**
- DealingRangeV3 com Premium/Equilibrium/Discount implementado e validado com dados reais
- Segundo bug crítico de aliasing (mesma classe do R6) encontrado via auditoria proativa e corrigido antes de causar impacto em produção/replay
- Auditoria sistemática de todos os 9 componentes confirma que não há mais instâncias desse padrão de bug no motor incremental
- Prefix invariance confirmada
- 2.103 testes de regressão, 0 falhas

**P1 aberto (acumulado de R6):** consumo formal de `StructureLegV3` pelas engines downstream continua pendente — cada componente ainda recalcula swings internamente.

**Próxima fase:** R9 — Liquidity (bloqueada por P0 de R5: `EqualLevelClusterV3` ainda não implementado — este é o foco necessário de R9).
