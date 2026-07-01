# RELATÓRIO R10 — FVG
## CE (Consequent Encroachment) e IFVG (Inverse FVG) Implementados

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/fvg.py`

---

## 1. Estado Anterior

`FvgComponent` já era totalmente causal (confirmado no R0 addendum): geometria de 3 candles confirmada em C3, `available_at = C3.available_at`, lifecycle `AVAILABLE → TOUCHED → PARTIALLY_FILLED → MITIGATED`, um evento por transição, estado serializável via dataclass `_ActiveFvg` (seguro contra o bug de aliasing de R6/R8 — `.to_dict()` sempre constrói um dict novo a partir dos atributos correntes).

Faltavam dois requisitos explícitos do plano mestre: **CE** e **IFVG**.

## 2. CE (Consequent Encroachment)

Marco clássico do SMC: quando o preço atinge o ponto médio (50%) do gap, distinto do threshold genérico `PARTIALLY_FILLED` (que usa `mitigation_fraction`, configurável). Implementado como evento `CE_REACHED`, disparado no máximo uma vez por FVG (guardado por `ce_reached: bool`), independente do estado atual (`AVAILABLE`, `TOUCHED` ou `PARTIALLY_FILLED`).

## 3. IFVG (Inverse FVG)

Quando um FVG é totalmente mitigado (`fill_fraction >= mitigation_fraction`) **e** o candle que causou a mitigação **fecha além da borda oposta** do gap (não apenas a atinge), a zona inverte de polaridade — um FVG bullish mitigado com fechamento abaixo do `bottom_price` original forma um `IFVG_BEARISH`, e vice-versa. Avaliado apenas com o candle que já causou a mitigação (nenhum lookahead).

`origin_fvg_struct_id` no payload preserva a referência ao FVG original — a IFVG é uma estrutura nova e distinta, não uma reescrita do FVG original (que permanece imutável).

## 4. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
FVG_BEARISH=1.478  FVG_BULLISH=1.488   (total FVG = 2.966, idêntico ao R3)
IFVG_BEARISH=733   IFVG_BULLISH=701    (total IFVG = 1.434)

eventos: AVAILABLE=4.400  TOUCHED=2.943  CE_REACHED=2.937  MITIGATED=2.917  PARTIALLY_FILLED=1.420
errors: []
```

**Consistência:** 2.917 MITIGATED de 2.966 FVGs totais (98%) — quase todo FVG eventualmente é totalmente preenchido, plausível para um mercado intraday líquido de 5 anos. Das mitigações, **1.434 (49%)** viram IFVG — quase metade dos FVGs mitigados tem fechamento através da borda oposta, não apenas um toque no ponto próximo.

## 5. Prefix Invariance

Confirmado: `structure_id`s do run de 150 candles idênticos ao subconjunto correspondente do run de 300 candles.

## 6. Testes de Regressão

```
pytest tests/test_technical_engine/ -q -k fvg
67 passed
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (238.1s)
```

## 7. Escopo Não Coberto Nesta Fase

- **Separação formal de geometry/displacement/contexto** — o pipeline batch legado (`pipeline.py`) tem `tag_displacement_fvgs()`, mas o `FvgComponent` incremental não marca displacement; isso é feito indiretamente no `BprComponent` (que reimplementa a detecção internamente). Registrado como P1 — mesmo tema de consolidação cruzada acumulado desde R6/R8/R9.

---

## 8. GATE

```
R10_FVG_PASS
```

**Justificativa:**
- Geometria de 3 candles causal, confirmada em C3 — já validado desde R0
- CE (Consequent Encroachment) implementado, um evento por FVG
- IFVG implementado com verificação real de fechamento além da borda oposta, sem lookahead
- 1.434 IFVGs reais formados em 5 anos de dados, proporção plausível (49% das mitigações)
- Prefix invariance confirmada
- 2.103 testes de regressão, 0 falhas

**P1 aberto:** displacement tagging formal no componente incremental (hoje só existe no pipeline legado e informalmente no BPR).

**Próxima fase:** R11 — BPR.
