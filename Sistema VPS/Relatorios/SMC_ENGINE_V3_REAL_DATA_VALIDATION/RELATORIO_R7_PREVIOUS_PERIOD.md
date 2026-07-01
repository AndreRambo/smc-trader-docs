# RELATÓRIO R7 — PREVIOUS HIGH/LOW
## Reescrita para Dia de Pregão Real + Lifecycle de Interação

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivos alterados:** `technical_engine/smc_engine_v3/incremental/components/previous_high_low.py`, `tests/test_technical_engine/test_smc_engine_v2_phase04.py`

---

## 1. Problema Corrigido (P1 identificado no R4)

`PreviousHighLowComponent` usava uma janela de contagem fixa de candles (`period_candles=24`), sem relação com o dia de pregão real. Para H1 (9 candles/dia útil de WINFUT), isso significava que "PDH/PDL" não correspondiam ao dia anterior real, mas a um período arbitrário deslizante.

**Correção:** reescrito para detectar o dia de pregão via `available_at` convertido para `America/Sao_Paulo` (mesma timezone usada no `SessionsComponent`, R4). Quando a `trading_date` de um novo candle difere da data do período em curso, o dia anterior é fechado e PDH/PDL são publicados usando o high/low real daquele dia.

## 2. Lifecycle de Interação Implementado

Anteriormente inexistente. Adicionado, por nível ativo (PDH e PDL, cada um com seu próprio estado):

- **TOUCH** — preço toca o nível exatamente sem ultrapassar
- **WICK_SWEEP** — pavio ultrapassa o nível mas o fechamento fica do lado de dentro
- **CLOSE_THROUGH** — fechamento ultrapassa o nível
- **GAP_THROUGH** — abertura de candle já além do nível, sem ter tocado antes (gap)
- **RECLAIM** — depois de um `CLOSE_THROUGH`/`GAP_THROUGH`, o preço fecha de volta do lado original

Cada tipo de transição só pode ocorrer **uma vez por nível** (mesma disciplina de "um evento por nível" já usada em BOS/CHOCH — capado via `fired: set[str]` por nível ativo), evitando repetição espúria a cada candle subsequente. O nível "reseta" implicitamente a cada novo dia de pregão: um PDH/PDL novo nasce sempre com `fired=set()` — o histórico de interação do nível anterior é preservado (nunca sobrescrito), não reaproveitado.

## 3. Testes Atualizados

Os testes existentes de `TestPreviousHighLow` usavam a API antiga (`period_candles=N`), que foi removida por representar a semântica incorreta. Reescritos para usar candles ancorados em dias de pregão reais (helper `_pdhl_candle(day_offset, hour, n)`), incluindo dois testes novos:
- `test_interaction_wick_sweep_then_reclaim` — valida a sequência WICK_SWEEP → CLOSE_THROUGH → RECLAIM
- `test_interaction_capped_one_per_type_per_level` — valida que sweeps repetidos no mesmo nível não geram eventos duplicados

```
pytest tests/test_technical_engine/test_smc_engine_v2_phase04.py -q
44 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (268.2s)
```

## 4. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
PDH: 1.245   PDL: 1.245
interação: AVAILABLE=2.490  CLOSE_THROUGH=970  WICK_SWEEP=868  RECLAIM=471  GAP_THROUGH=258  TOUCH=14
errors: []
```

**Validação cruzada:** 1.245 PDH/PDL batem quase exatamente com os 1.246 candles Daily reais do R1 — a diferença de 1 é esperada (o último dia de pregão do dataset nunca é fechado, pois não chega um candle do "dia seguinte" para disparar a publicação).

`TOUCH` disparou 14 vezes em 5 anos de dados reais — confirma que não é uma condição morta (preço tocando o nível exatamente, sem excesso, é raro mas ocorre de fato com preços reais).

## 5. Escopo Não Coberto Nesta Fase

- **PDH/PDL simétricos por design** — já garantido (mesmo algoritmo aplicado a high/low)
- **raw/evidence preservados** — `extreme_candle_id`, `period_start_candle_id`, `trading_date` no payload; histórico de interação nunca sobrescrito (P1 do plano atendido)
- **Timeframes intraday diferentes de H1** (M1, M5, etc.) — não testados nesta fase; a lógica é agnóstica de timeframe (baseada em `trading_date`), mas a validação real ficou restrita a H1 por tempo de execução (M1 tem 689k candles, ~35x mais lento que H1)

---

## 6. GATE

```
R7_PREVIOUS_PERIOD_PASS
```

**Justificativa:**
- PDH/PDL agora usa dia de pregão real (`America/Sao_Paulo`), não janela de candles arbitrária — P1 do R4 resolvido
- Lifecycle de interação completo (TOUCH/WICK_SWEEP/CLOSE_THROUGH/GAP_THROUGH/RECLAIM) implementado e validado com dados reais
- Validação cruzada forte: 1.245/1.246 dias batem com R1
- 2.103 testes de regressão (44 específicos de Phase04 + resto), 0 falhas

**Próxima fase:** R8 — Retracement/Dealing Range (bloqueada parcialmente pelo P1 de R6: `StructureLegV3` ainda não implementado).
