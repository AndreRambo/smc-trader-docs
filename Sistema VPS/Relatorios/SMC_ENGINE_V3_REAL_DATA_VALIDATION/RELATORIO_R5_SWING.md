# RELATÓRIO R5 — SWING
## Status SUPERSEDED, HH/HL/LH/LL e Auditoria de Equal-Level Clustering

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo alterado:** `technical_engine/smc_engine_v3/incremental/components/swings.py`

---

## 1. Estado Anterior (auditoria)

O `SwingComponent` já era causal por construção (R0 addendum): confirma um pivô somente após `swing_length` candles subsequentes serem processados, buffer limitado, `available_at` correto. Faltavam, em relação ao plano mestre:

- **status SUPERSEDED** — todo swing confirmado era emitido como definitivo, sem mecanismo de invalidação quando um swing mais extremo do mesmo tipo aparecia sem um swing oposto entre eles
- **HH/HL/LH/LL** — não calculado
- **EqualLevelClusterV3** — auditado nesta fase: `LiquidityComponent` computa ATR mas **não mescla** swings próximos em um cluster real (cada swing forma um cluster novo de 1 membro; `member_ids` nunca cresce). Não é uma feature implementada, apesar do nome sugerir clustering.

## 2. Implementação: SUPERSEDED

Regra aplicada: quando dois swings confirmados **consecutivos do mesmo tipo** (dois highs seguidos, ou dois lows seguidos, sem um swing do tipo oposto confirmado entre eles) ocorrem, apenas o **mais extremo** é `CANONICAL`; o menos extremo é `SUPERSEDED`.

Dois casos possíveis, ambos tratados:
1. O novo swing é **menos extremo** → nasce diretamente com `status=SUPERSEDED` no payload (imutável desde a criação, sem evento de transição, pois nunca foi `CANONICAL`)
2. O novo swing é **mais extremo** → nasce `CANONICAL`, e o swing anterior (que já havia sido emitido como `CANONICAL`) recebe um **evento** `SUPERSEDED` retroativo — sem alterar o registro original (regra do plano: "immutable, emitted once, never modified" — o campo `payload.status` do struct original nunca muda; a mudança de estado é só via evento separado)

## 3. Implementação: HH/HL/LH/LL

Cada swing recebe `payload.hh_hl_label` comparando com o último swing `CANONICAL` do mesmo tipo:
- Swing high: `HH` (higher high) se mais extremo que o high canônico anterior, senão `LH` (lower high)
- Swing low: `HL` (higher low) se mais extremo (mais alto) que o low canônico anterior, senão `LL` (lower low)
- Primeiro swing de cada tipo: `INITIAL`

## 4. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
n swings: 1.519
status na criação: CANONICAL=1.365  SUPERSEDED=154
labels: HH=363  LH=403  HL=361  LL=390  INITIAL=2
eventos de transição SUPERSEDED (retroativos): 181
errors: []
```

**Verificação:** 154 (nascidos SUPERSEDED) + 181 (transição retroativa) = 335 swings SUPERSEDED no total ao longo do dataset — de 1.519, aproximadamente 22% dos swings confirmados acabam superados por um pivô mais extremo do mesmo tipo antes que ocorra uma reversão, o que é consistente com o comportamento esperado de um mercado com muitos micro-pivôs dentro de tendências maiores.

## 5. Verificação de Prefix Invariance

Testado com prefixo de 150 candles dentro de um run de 300: o conjunto `(structure_id, status_na_criação)` dos 19 swings confirmados nos primeiros 150 candles é **idêntico** entre o run isolado de 150 e o subconjunto correspondente do run de 300. Confirma que o `payload.status` de cada swing é determinado apenas por dados já vistos no momento da emissão — nenhuma retroatividade contamina o registro original; apenas eventos futuros (separados) podem mudar o status observável.

## 6. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2102 passed, 0 failed (251.9s)
```

## 7. Gap Confirmado e Não Resolvido Nesta Fase: EqualLevelClusterV3

`LiquidityComponent._form_cluster()` calcula ATR (`_current_atr`) mas usa esse valor apenas como metadado (`atr_tolerance` no payload) — **não há lógica que procure um cluster existente dentro da tolerância ATR e mescle o novo swing nele**. Cada swing gera sempre um `struct_id` novo e único (hash inclui `swing_candle_id` e `level`), e `member_ids` é sempre uma lista de 1 elemento na criação.

Isso significa que **Equal Highs (EQH) e Equal Lows (EQL) não são detectados** — dois swings em preços muito próximos (dentro de tolerância de tick/ATR) são tratados como níveis de liquidez completamente independentes, quando deveriam ser agrupados em um único `EqualLevelClusterV3` com múltiplos membros.

**Decisão de escopo:** este é um trabalho de tamanho comparável ao que foi feito para SUPERSEDED nesta fase (busca de cluster próximo existente, lógica de merge, atualização de `level` como média móvel dos membros, lifecycle de SWEPT por cluster) e pertence tematicamente à Fase R9 (Liquidity), que já está formalmente definida no plano para consumir `EqualLevelClusterV3`. Implementá-lo corretamente agora, fora de ordem e sem os testes dedicados de R9, arriscaria uma correção rasa. **Registrado como P0 aberto para R9.**

---

## 8. GATE

```
R5_SWING_PASS
```

**Justificativa:**
- Raw pivots, canonical swings, availability: já corretos (herdados, causais)
- SUPERSEDED implementado e validado com dados reais (335/1.519 = 22% dos swings superados, comportamento plausível)
- HH/HL/LH/LL implementado e populado corretamente
- Prefix invariance confirmada (imutabilidade do registro original)
- 2.102 testes de regressão, 0 falhas
- Downstream (OB, BOS/CHOCH, Liquidity) ainda não filtra/consome SUPERSEDED formalmente — como já documentado no R3, cada componente hoje rastreia seu próprio estado interno em vez de consumir a saída do `SwingComponent`. Esse consumo cruzado formal é o assunto das Fases R6/R9/R12, não deste relatório.

**P0 aberto para R9:** `EqualLevelClusterV3` (agrupamento real de EQH/EQL por tolerância de tick/ATR) não está implementado — `LiquidityComponent` calcula ATR mas não o usa para mesclar swings próximos.

**Próxima fase:** R6 — Structure (consolidar BOS/CHOCH para consumir swings canônicos do `SwingComponent`, tratar `protected/weak`, `StructureLegV3`).
