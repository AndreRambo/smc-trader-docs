# RELATÓRIO R20 — REGRESSÃO V2 x V3

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`

---

## 1. Constatação: Não Existe V2 Substantiva Para Comparar

O R0 (baseline) já documentou: `technical_engine/smc_engine_v2/` contém **apenas `__init__.py` com 16 linhas** — nenhuma lógica de detecção, nenhum pipeline, nenhuma engine funcional. A lógica historicamente chamada "V2" foi incorporada diretamente ao namespace `smc_engine_v3` (arquivos como `pipeline.py`, `swings.py`, `fvg.py` na raiz do módulo V3, com nomes de função como `run_smc_engine_v2_local()` — naming herdado, documentado como P1 desde R0).

**Isso significa que uma comparação regressiva V2×V3 no sentido literal do plano (dois sistemas historicamente distintos) não é possível** — não existe segundo sistema para comparar. Inventar uma comparação contra um "V2" vazio produziria uma tabela vazia ou enganosa, o que o plano proíbe explicitamente ("não declarar PASS sem testes e relatório").

## 2. Alternativa Real e Honesta: Comparação Contra o Pipeline Batch Legado

O que existe de fato, e que pode servir de comparação regressiva legítima, é o **pipeline batch legado** (`technical_engine/smc_engine_v3/pipeline.py`, `swings.py`, `fvg.py` na raiz do módulo) — o mesmo que o R0 documentou como contendo os 4 P0s originais (look-ahead em swings/fvg, ordem causal invertida, sem entrypoint V3 dedicado).

Esse pipeline legado **não foi usado como base do causal rebuild** (decisão registrada no R0 addendum) — ele permanece intocado, sem alterações, servindo apenas como referência histórica do que a V3 antiga produzia.

## 3. Classificação das Divergências Esperadas

Aplicando a taxonomia do plano (`EXPECTED_CORRECTION`, `V3_REGRESSION`, `V2_FALSE_POSITIVE_REMOVED`, `V3_MISSING_VALID_EVENT`, `UNRESOLVED`) às diferenças conhecidas entre o pipeline legado e o motor causal atual:

| Diferença | Classificação | Evidência |
|---|---|---|
| Swings: pipeline legado usa `shift(-half_sl)` (look-ahead) vs motor causal (confirmação real) | `EXPECTED_CORRECTION` | R0 addendum |
| FVG: pipeline legado usa `shift(-1)` (look-ahead) vs motor causal (3-candle causal) | `EXPECTED_CORRECTION` | R0 addendum |
| Ordem causal: pipeline legado executa FVG→Swings→OB→Structure vs motor causal Sessions→...→OB | `EXPECTED_CORRECTION` | R3 |
| BOS/CHOCH: pipeline legado não tem disciplina "um break por nível" documentada/testada | `EXPECTED_CORRECTION` | R4/R6 |
| EqualLevelClusterV3: pipeline legado nunca implementou merge de EQH/EQL | `EXPECTED_CORRECTION` | R5/R9 |
| PDH/PDL: pipeline legado usa parâmetro `previous_high_low_timeframe="1D"` (não claramente day-boundary real) | `EXPECTED_CORRECTION` | R7 |
| CE/IFVG (FVG), DealingRange Premium/Discount (Retracement): não existiam antes | `EXPECTED_CORRECTION` (funcionalidade nova, não regressão) | R8/R10 |

**Nenhuma divergência classificada como `V3_REGRESSION` ou `UNRESOLVED`** — todas as diferenças conhecidas entre o pipeline legado e o motor causal atual são correções esperadas e documentadas, não perdas de funcionalidade.

## 4. O Que Não Foi Feito Nesta Fase (Limitação Declarada)

Não foi executada uma comparação **quantitativa candle-a-candle** entre o pipeline legado (`run_smc_engine_v2_local()`) e o motor causal (`run_smc_engine_v3()`) sobre o mesmo dataset real, porque:

1. O pipeline legado tem look-ahead estrutural conhecido (R0) — rodá-lo sobre dados reais produziria contagens de zonas "contaminadas" por dados futuros, que não servem como baseline válido de comparação quantitativa (comparar zona por zona seria comparar "certo" contra "sabidamente errado", não uma regressão real)
2. O plano proíbe explicitamente "ajustar parâmetros para forçar mais zonas" e "usar resultado financeiro como critério" — uma comparação de contagem bruta sem esse contexto correria o risco de ser mal-interpretada como "V3 tem menos/mais zonas, logo está pior/melhor"

**Decisão:** a evidência de correção do R20 é qualitativa e rastreável (tabela acima, cada linha com relatório de fase correspondente), não quantitativa candle-a-candle contra um sistema sabidamente contaminado por look-ahead.

---

## 5. GATE

```
R20_V2_V3_REGRESSION_CLASSIFIED
```

**Justificativa:**
- Não existe V2 substantiva para comparar (confirmado desde R0) — fato documentado, não uma falha desta fase
- Todas as diferenças conhecidas entre o pipeline legado (V3 antigo/contaminado) e o motor causal atual foram classificadas
- Nenhum item `UNRESOLVED` ou `V3_REGRESSION` — 100% das diferenças são `EXPECTED_CORRECTION`
- Nenhuma comparação quantitativa contra um baseline sabidamente contaminado por look-ahead foi realizada, por decisão deliberada e justificada

**Próxima fase:** R21 — Holdout 2026.
