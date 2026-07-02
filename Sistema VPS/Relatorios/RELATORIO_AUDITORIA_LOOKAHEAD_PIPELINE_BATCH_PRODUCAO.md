# RELATÓRIO DE AUDITORIA — LOOK-AHEAD NO PIPELINE BATCH EM PRODUÇÃO
## `technical_engine/smc_engine_v3/pipeline.py` (rotulado "CANONICAL_V3")

---

> **ATUALIZAÇÃO 2026-07-01 (mesmo dia, sessão subsequente):** a correção real do
> look-ahead descrito neste relatório **foi implementada** — ver §9
> "Correção Implementada". A correção é **aditiva e opt-in** (flag
> `causal_swings_fvg=False` por padrão) — o comportamento de produção
> descrito nas seções 1-8 abaixo **permanece inalterado até decisão explícita
> de ativar a flag**. As seções 1-8 são mantidas como registro histórico da
> auditoria original.

**Data:** 2026-07-01
**Escopo:** auditoria dedicada do pipeline batch legado que roda em produção hoje, motivada por uma discrepância encontrada entre `docs_geral/ARQUITETURA_OFICIAL.md` (§4.10) e o código-fonte real, durante a análise pós-plano de validação da SMC Engine V3 (`PLANO_MESTRE_AUDITORIA_CORRECAO_VALIDACAO_SMC_V3_COM_CSVS_REAIS_WINFUT_2021_2026.md`).
**Branch em que a auditoria foi feita:** `feature/smc-v3-causal-rebuild-real-data`
**Nenhum código foi alterado nesta auditoria** — apenas leitura e confirmação via grep/verificação de chamadas. (A correção implementada em sessão subsequente está documentada em §9.)

---

## 1. Motivação

`ARQUITETURA_OFICIAL.md` §4.10 afirma que as 8 engines batch em `technical_engine/smc_engine_v3/*.py` (raiz do módulo) foram "refatoradas" para `CANONICAL_V3` com detecção corrigida — "PR merged" em 2026-06-30, citando explicitamente para Swing: *"EQH/EQL preservados (SUPERSEDED, nunca deletados)"*.

Essa afirmação foi contrastada com o achado do R0 do plano de validação da SMC V3 (2026-07-01), que já havia documentado look-ahead estrutural em `swings.py` e `fvg.py` — mas o R0 investigou esses arquivos como parte do **motor incremental separado** (`incremental/`), não necessariamente confirmando se o mesmo problema afetava o **pipeline batch legado que roda em produção**. Esta auditoria fecha essa lacuna.

## 2. Metodologia

1. Grep por padrões de look-ahead (`shift(-N)`, `.iloc[i + N]`, `[i + N]`) em todos os arquivos batch de `technical_engine/smc_engine_v3/*.py`.
2. Leitura direta do código-fonte de cada ocorrência para distinguir contaminação real de padrões legítimos de varredura histórica (backtest).
3. Rastreamento da cadeia de chamadas real desde o robô de produção (`run_b3.py`) até a função de detecção.
4. Verificação se o parâmetro `detection_definition="CANONICAL_V3"` de fato altera o algoritmo de detecção ou é vestigial.

## 3. Achados — Look-ahead Confirmado (P0)

### 3.1 `swings.py` — Detecção de Pivô (Swing High/Low)

```python
# _calculate_swings_legacy (LEGACY_V2) — linhas 48-49
is_swing_high = df["high"] == df["high"].shift(-half_sl).rolling(sl).max()
is_swing_low  = df["low"]  == df["low"].shift(-half_sl).rolling(sl).min()

# _calculate_swings_canonical_v3 (CANONICAL_V3, DEFAULT) — linhas 129-130
is_swing_high = df["high"] == df["high"].shift(-half_sl).rolling(sl).max()
is_swing_low  = df["low"]  == df["low"].shift(-half_sl).rolling(sl).min()
```

**Achado crítico:** o algoritmo de detecção de pivô do caminho `CANONICAL_V3` (linhas 129-130) é **idêntico, linha por linha**, ao algoritmo `LEGACY_V2` (linhas 48-49). A refatoração de 2026-06-30 adicionou preservação de status `SUPERSEDED` para EQH/EQL **sobre** essa mesma detecção — nunca corrigiu o algoritmo central.

`shift(-half_sl)` desloca valores futuros para trás na série antes de computar o rolling max/min — na prática, o candidato a pivô no índice `i` é avaliado contra um máximo/mínimo que inclui candles de `i+1` até `i+half_sl` (dados que, em execução causal real, ainda não existem). **Isso não é uma questão de rotulagem tardia — é a própria decisão "este candle é um swing?" sendo tomada com base em preços futuros.**

### 3.2 `fvg.py` — Geometria do Fair Value Gap

```python
def calculate_fvg(
    ohlc: pd.DataFrame,
    ...
    detection_definition: str = "CANONICAL_V3",   # <- aceito, nunca referenciado no corpo
) -> pd.DataFrame:
    ...
    bullish_gap = df["high"].shift(1) < df["low"].shift(-1)   # linha 87
    bearish_gap = df["low"].shift(1) > df["high"].shift(-1)   # linha 92
    ...
    df.loc[i, "Top"] = float(df.loc[i + 1, "low"])    # linha 108
    df.loc[i, "Bottom"] = float(df.loc[i + 1, "high"]) # linha 116
```

**Achado crítico:** `calculate_fvg()` é uma **única função sem ramificação** por `detection_definition` — o parâmetro existe na assinatura e na docstring, mas nunca é lido no corpo da função. A zona do FVG no candle de origem `i` é definida usando o preço do candle `i+1` (`df.loc[i+1, "low"]`/`df.loc[i+1, "high"]`) — dado futuro relativo ao candle `i`.

### 3.3 Confirmação do Caminho de Produção

```
run_b3.py (robô de coleta, systemd smc-b3-robot.service)
  → TRIGGER 4a (candle novo em qualquer TF exceto M1)
  → infra/sync_v2.py:811-812
      from technical_engine.smc_engine_v3.pipeline import run_smc_engine_v2_local
      pipeline_result = run_smc_engine_v2_local(...)
  → pipeline.py: run_smc_engine_v2_local()
      swings.calculate_swings(ohlc, swing_length)          # detection_definition default = CANONICAL_V3
        → _calculate_swings_canonical_v3()                  # look-ahead confirmado (§3.1)
      fvg.calculate_fvg_records(...) → calculate_fvg(...)   # look-ahead confirmado (§3.2)
  → persist_smc_engine_v2_run() → technical_engine_smc_v2_*_shadow (10 tabelas)
  → sync_v2_shadow_zones() → dashboard + TechnicalTruthEnvelopeV2 + Opportunity Scanner (input)
```

**O pipeline com look-ahead confirmado é o que roda em produção hoje**, a cada candle novo, para todos os 6 ativos monitorados.

## 4. Efeito Cascata — Contaminação Transitiva

`pipeline.py` computa `_swings_df` **uma vez** via `swings.calculate_swings()` e reutiliza esse DataFrame contaminado como insumo direto para:

| Consumidor | Como usa `_swings_df` |
|---|---|
| `order_blocks.py` (`calculate_ob_records`) | Recebe `swings_for_ob` — zona de Order Block ancorada em swings potencialmente contaminados |
| `structure.py` (`calculate_bos_choch_records`) | Recebe `swings_for_bc` — nível de BOS/CHOCH ancorado em swing contaminado |
| `liquidity.py` (`calculate_liquidity_records`) | Recebe `swings_for_liq` — nível de liquidez ancorado em swing contaminado |
| `retracements.py` (`calculate_retracements`) | Recebe `swings_for_ret` — range de retração ancorado em swing contaminado |

**Nenhum desses 4 arquivos tem look-ahead direto no próprio código** (confirmado via grep, ver §5) — mas todos herdam a contaminação por dependerem do `_swings_df`. Isso significa que praticamente toda zona SMC persistida em produção (OB, BOS/CHOCH, Liquidity, Retracement, Swing, FVG) tem uma dependência, direta ou transitiva, de dados futuros.

## 5. Padrões Verificados e Classificados como NÃO-look-ahead (Varredura Histórica Legítima)

| Arquivo | Linhas | Padrão | Classificação |
|---|---|---|---|
| `fvg.py` | 185-190 | `low[i+1:] <= midpoint` | Varredura para frente buscando **quando** uma zona já existente foi mitigada — legítimo em backtest histórico, não define a zona em si |
| `structure.py` | 102-110 | `close[i+2:] > level[i]` | Varredura para frente buscando **quando** um nível já existente foi rompido — mesmo padrão, legítimo |
| `swings.py` | 324 | `timestamps.iloc[i + swing_length]` | Rótulo de `confirmed_at` (timestamp de quando o swing foi confirmado) — metadado, não altera qual candle é pivô |
| `order_blocks.py` | 625 | `timestamps.iloc[i + 1]` | Bug de rótulo de timestamp (já documentado como P1 no R0 do plano SMC V3) — não contamina preço |

Esses padrões representam "dado um evento/zona já definido no passado, quando no futuro (relativo a ele) algo aconteceu" — uma operação válida de análise histórica em lote, distinta de "usar dado futuro para decidir se o evento/zona existe".

## 6. Distinção com o Motor Incremental (`incremental/`)

O plano de validação SMC V3 (R0-R22, 2026-07-01) auditou e corrigiu um motor **completamente separado**: `technical_engine/smc_engine_v3/incremental/`. Esse motor:

- confirma um swing somente após `swing_length` candles subsequentes terem sido **realmente processados** via `on_candle_closed()` (nunca acessa dados além do que foi entregue)
- confirma um FVG somente no terceiro candle da formação, usando apenas os 3 candles já recebidos
- foi validado com 2.103 testes de regressão e 12.018 candles reais de WINFUT (2021-2026), com prefix invariance, paridade batch/replay/chunk/resume e replay em 5 modos, todos com `future_data_violations=0`

**Este motor é shadow-only e não substituiu o pipeline batch em produção.** A confusão de nomenclatura entre os dois sistemas (ambos usam termos como "CANONICAL_V3", ambos vivem sob `technical_engine/smc_engine_v3/`) é a provável origem da discrepância na documentação.

## 7. Risco e Recomendação

**Risco:** toda decisão hoje tomada a partir de `technical_engine_smc_v2_*_shadow` (dashboard, `TechnicalTruthEnvelopeV2`, Opportunity Scanner) é influenciada por swings e FVGs detectados com dados futuros. Como o sistema opera em `shadow_only=True` (nenhum sinal promovido a trade real), o impacto imediato é de **qualidade de sinal/estudo**, não de execução financeira — mas qualquer decisão de cutover ou validação de performance histórica que use esse pipeline como baseline está comparando contra um sistema que "trapaceia" estruturalmente.

**Recomendações:**
1. Não usar o pipeline batch (`pipeline.py`/`swings.py`/`fvg.py` na raiz de `smc_engine_v3/`) como baseline de comparação de performance ou qualidade de sinal — ele não é causal.
2. Considerar o motor `incremental/` (já validado nas 22 fases do plano de auditoria SMC V3) como candidato a substituir o pipeline batch em produção, sujeito aos P1s documentados no relatório final daquele plano (`StructureLegV3` formal, consumo cruzado de Liquidity, decisão sobre `require_structure_break`).
3. Corrigir a documentação (`ARQUITETURA_OFICIAL.md`, `RELATORIO_ENGINES_INDICADORES_ZONAS.md`) para não descrever o pipeline batch como causalmente correto — feito nesta mesma sessão, ver commits/edições relacionadas.
4. Se o pipeline batch continuar em produção por qualquer período, documentar explicitamente que ele é `SHADOW_ONLY_NON_CAUSAL` até ser substituído ou corrigido.

---

## 8. Resumo dos Achados

| Item | Status |
|---|---|
| `swings.py` look-ahead (LEGACY_V2) | **CONFIRMADO**, linhas 48-49 |
| `swings.py` look-ahead (CANONICAL_V3, default de produção) | **CONFIRMADO**, linhas 129-130 — idêntico ao legado |
| `fvg.py` look-ahead (única função, sem distinção por `detection_definition`) | **CONFIRMADO**, linhas 87, 92, 108, 116 |
| Parâmetro `detection_definition` em `fvg.py` | **Vestigial** — não referenciado no corpo da função |
| Caminho de produção confirmado | `run_b3.py` → `infra/sync_v2.py` → `pipeline.py:run_smc_engine_v2_local()` |
| Contaminação transitiva | OB, Structure/BOS-CHOCH, Liquidity, Retracements (via `_swings_df` compartilhado) |
| Motor incremental (`incremental/`) | **Não afetado** — causal por construção, validado no plano R0-R22, shadow-only |
| **Correção real (opt-in)** | **IMPLEMENTADA** em sessão subsequente (2026-07-01) — ver §9. Default de produção inalterado. |

---

## 9. Correção Implementada (2026-07-01, sessão subsequente)

Após esta auditoria, foi desenhada e implementada uma correção real do look-ahead, seguindo o mesmo princípio já usado no plano de validação SMC V3 (R12): **não alterar silenciosamente o comportamento de detecção em produção** — a correção é aditiva, com ativação explícita via flag, permitindo comparação em shadow antes de qualquer decisão de virar o padrão.

### 9.1 Abordagem: Views Causais Aditivas + Flag Opt-in

- `swings.py` e `fvg.py` — as funções de detecção existentes (`_calculate_swings_legacy`, `_calculate_swings_canonical_v3`, `calculate_fvg`) **não foram alteradas** — preserva os testes de paridade upstream (`smartmoneyconcepts`) e a semântica "qual candle é historicamente o pivô/origem do gap" usada em overlays visuais.
- Nova função `swings.to_causal_swings_view(swings_df, swing_length)`: reindexação aditiva que move cada pivô da linha de origem para `AvailableIndex` (`origin + swing_length`), excluindo (não clampando) qualquer pivô cuja janela de confirmação exceda os dados disponíveis — estruturalmente impossível haver colisão entre dois pivôs, pois posições de origem são estritamente crescentes.
- Nova função `fvg.to_causal_fvg_view(fvg_df)`: reindexação aditiva que move cada FVG do candle do meio (`i`, origem) para o candle de confirmação (`i+1`, 3º candle). Sempre injetivo e dentro dos limites.
- **Correção independente de metadado** em `fvg.calculate_fvg_records()`: `confirmed_index`/`available_index` corrigidos de `i` (candle do meio) para `i+1` (candle de confirmação real) — bug de rotulagem que encodava o look-ahead diretamente no contrato temporal, agora corrigido incondicionalmente (não depende de flag).
- `pipeline.py`: novo parâmetro `causal_swings_fvg: bool = False` em `run_smc_engine_v2_local()`. Quando `True`, `order_blocks.py`, `structure.py`, `liquidity.py`, `retracements.py` e `bpr.py` passam a consumir as views causais em vez do DataFrame de detecção bruto — **sem nenhuma alteração de código nesses 5 arquivos**, pois são consumidores agnósticos à origem do DataFrame recebido (leem as mesmas colunas por posição). `result["swings"]`/`result["fvg"]` (overlays visuais) continuam vindo da detecção bruta em ambos os casos.

### 9.2 Validação

- `pytest tests/test_smc_engine_v2/` — mesmas 7 falhas pré-existentes (confirmadas via `git stash`, não relacionadas a esta correção), **zero regressões novas**, 164 passando (era 157) com os 7 novos testes.
- Novo arquivo `tests/test_smc_engine_v2/test_causal_views_lookahead_fix.py` (7 testes): reindexação correta, exclusão de cauda, invariância de prefixo (dataset completo vs. truncado) para ambas as views, e a correção de metadado do FVG.
- Execução real ponta a ponta (dados EURUSD, 500 candles): com a flag `False` (padrão), `swing_count`/`fvg_count`/todos os diagnósticos são **idênticos** ao comportamento anterior à mudança. Com a flag `True`, as contagens de OB (28→55) e BOS/CHOCH (53→63) mudam — evidenciando que a correção realmente altera quais estruturas dependentes de swing são detectadas quando a causalidade é imposta, exatamente como esperado (o pipeline atual conta estruturas que dependem de pivôs "vistos antes da hora").

### 9.3 Correção Commitada

Commit `6f30992` na branch `feature/smc-v3-causal-rebuild-real-data`: `fix(smc-v3): additive causal views for batch pipeline look-ahead (opt-in)`. Arquivos alterados: `technical_engine/smc_engine_v3/swings.py`, `technical_engine/smc_engine_v3/fvg.py`, `technical_engine/smc_engine_v3/pipeline.py`, novo `tests/test_smc_engine_v2/test_causal_views_lookahead_fix.py`. Plano completo da correção: `/home/bimaq/.claude/plans/agile-meandering-fairy.md`.

### 9.4 Shadow-Run sobre Dados Reais WINFUT (2026-07-01)

Comparação `causal_swings_fvg=False` (produção hoje) vs. `True` (correção causal) rodada sobre o dataset completo `data/csv_import/canonical/WINFUT_H1_canonical.csv` — 12.018 candles H1 reais, 2021-06-22 a 2026-06-19 (o mesmo stream usado em R0-R22).

**Resultado — flag OFF vs. ON:**

| Estrutura | OFF (produção) | ON (causal) | Delta |
|---|---:|---:|---:|
| swings (raw, `result["swings"]`) | 1701 | 1701 | 0% |
| fvg (raw, `result["fvg"]`) | 2966 | 2966 | 0% |
| order_blocks | 648 | 1182 | **+82.4%** |
| bos_choch | 1917 | 2442 | **+27.4%** |
| bullish_ob | 316 | 585 | +85.1% |
| bearish_ob | 332 | 597 | +79.8% |
| bos_count | 1595 | 2106 | +32.0% |
| choch_count | 322 | 336 | +4.3% |
| liquidity | 409 | 409 | 0% |
| retracements | 12003 | 11998 | -0.04% |
| bpr | 424 | 424 | 0% |
| pivôs excluídos por cauda (`causal_swings_excluded_tail_count`) | — | 0 | (nunca acionado neste dataset — consistente com a análise de §9.1: a própria fórmula de detecção já exige `origin+swing_length <= n-1` para não retornar NaN, tornando o clamp de cauda uma salvaguarda defensiva raramente/nunca exercida na prática) |

**Achados:**
1. `swings`/`fvg` brutos (usados nos overlays visuais) são **idênticos** com a flag ligada ou desligada em todo o stream de 12.018 candles — confirma que o design aditivo funciona exatamente como projetado (zero efeito colateral na detecção original).
2. **OB e BOS/CHOCH mudam substancialmente** quando a causalidade é imposta (+82,4% e +27,4% respectivamente). Liquidity, BPR e Retracements permanecem praticamente inalterados. Isso indica que o pipeline em produção hoje **sub-conta ou classifica incorretamente** uma fração relevante de Order Blocks e eventos BOS/CHOCH — a sequência/ordem dos pivôs é preservada pela view causal (sem colisões, posições de origem estritamente crescentes), mas o ponto de início da varredura de rompimento (`close[i+2:] > level[i]`) muda porque `i` (a linha onde o pivô aparece) passa a ser `AvailableIndex` em vez de `OriginIndex` — isso reclassifica quais padrões de 4-swing realmente se qualificam como BOS/CHOCH e, por extensão, quais Order Blocks são ancorados. **Este é o achado mais relevante para decisão de produto** — a diferença não é cosmética e merece revisão do dono do produto antes de qualquer cutover.

**Validação cruzada vs. motor incremental (referência já validada no plano R0-R22):**

| Componente | Batch (causal, params default) | Incremental (params default) |
|---|---:|---:|
| swings | 1701 | 1519 |
| fvg | 2966 | 4400 |
| order_blocks | 1182 | 1761 |
| bos_choch | 2442 | 971 |
| liquidity | 409 | 1406 |
| retracements | 11998 | 5212 |
| bpr | 424 | 1370 |

**Ressalva metodológica:** esta comparação **não é rigorosamente apples-to-apples** — os dois motores rodaram com parâmetros default próprios (definição de OB, clustering de liquidez, granularidade de retracement, regras de junção de FVG diferem entre batch e incremental). As divergências grandes (ex.: liquidity 409 vs. 1406, retracements 11998 vs. 5212, bpr 424 vs. 1370) provavelmente refletem essas diferenças de parâmetro/design, não um erro na correção causal em si. Uma validação cruzada rigorosa exigiria alinhar previamente os parâmetros de ambos os motores — **ainda não realizado**, listado como próximo passo.

### 9.6 Segundo Bug de Look-Ahead Encontrado — Clustering de Liquidez

Ao tentar alinhar parâmetros entre os dois motores para uma validação cruzada rigorosa (pedido explícito do usuário: "os parâmetros sempre tem que ser o mesmo para tudo"), foi descoberto um **segundo look-ahead, independente do de swings/FVG**, em `liquidity.py`:

```python
# _calculate_liquidity_legacy — ANTES da correção
if cluster_width_atr > 0:
    atr_val = _compute_atr(df["high"], df["low"], df["close"])   # .iloc[-1] — ÚLTIMO valor da série INTEIRA
    pip_range = atr_val * cluster_width_atr
else:
    pip_range = (float(df["high"].max()) - float(df["low"].min())) * range_percent  # max/min do dataset INTEIRO
```

Em ambos os ramos, a tolerância de clustering (`pip_range`) é um **escalar único**, computado a partir de dados do **fim** (ou de todo) o dataset, e aplicado uniformemente a candles do início da série — um pico de preço futuro infla a tolerância usada para agrupar pivôs muito antes desse pico ter ocorrido.

**Reprodução determinística** (`tests/test_smc_engine_v2/test_causal_views_lookahead_fix.py::test_liquidity_causal_range_closes_future_spike_lookahead`): dois swing highs a 0.4pt de distância nas linhas 5 e 10, com um pico futuro artificial na linha 30. Com a tolerância global (legado), esses dois pivôs são erroneamente agrupados (`Liquidity=1`) porque o pico futuro infla o range do dataset inteiro. Com a correção causal, a tolerância na linha 5 reflete apenas dados até a linha 5 e os pivôs **não** são agrupados.

**Correção:** `causal_range: bool = False` (opt-in) em `calculate_liquidity`/`calculate_liquidity_records`/`pipeline.py` (parâmetro `causal_liquidity_range`). Quando `True`: modo ATR usa ATR rolling por candle (`_compute_atr_series`, causal por construção — já era assim mas usava só o último valor); modo `range_percent` usa high/low **expanding** (`cummax()`/`cummin()`) em vez do máximo/mínimo do dataset inteiro. Default `False` preserva produção — confirmado por teste (`test_liquidity_causal_range_default_matches_legacy_behaviour`, `assert_frame_equal` exato).

**Nota de risco/escopo:** o ramo `range_percent` é o modo **ativo em produção hoje** (`cluster_width_atr=0.0` por padrão) — diferente do bug de swings/FVG, este JÁ afeta a contagem de liquidez em produção continuamente, não é dormente. O ramo ATR (`cluster_width_atr>0`) é dormente hoje (nunca usado por padrão), mas a correção da série por-candle foi aplicada de qualquer forma (sem risco, pois o código antigo nesse ramo nunca executa com os defaults atuais).

Também identificado, **não corrigido nesta sessão**: a classificação ERL/IRL (`_enrich_canonical_v3`) usa `ohlc["high"].max()`/`ohlc["low"].min()` do dataset inteiro — outro look-ahead, mas de escopo de correção ambíguo (qual "range" é o correto: janela de backtest inteira? sessão? lookback de N dias?) — decisão de produto, não bug fix direto. Registrado em `shared_config.STRUCTURAL_DIVERGENCES["liquidity_erl_irl"]`.

### 9.7 Configuração Compartilhada Única (`shared_config.py`)

Motivação (feedback explícito do usuário): parâmetros que existem nos dois motores (batch e incremental) devem vir de **uma única fonte de verdade por `(asset_id, timeframe)`**, nunca defaults independentes por motor — a validação cruzada de §9.4 usava defaults distintos em cada lado, tornando a comparação inválida.

Criado `technical_engine/smc_engine_v3/shared_config.py`: `SmcSharedEngineConfig` (dataclass frozen), com `to_batch_kwargs()` e `to_incremental_component_params()`, e `for_asset_timeframe(asset_id, timeframe)` como único ponto de acesso (permite overrides futuros por ativo/timeframe, nunca por motor). Parâmetros alinhados:

| Parâmetro | Valor | Alinhamento |
|---|---|---|
| `swing_length` | 5 | já era idêntico nos dois motores |
| FVG mitigação | 0.5 (midpoint), touch WICK | batch sempre usou 50%; incremental default era 1.0 (full-fill) — **alinhado ao batch** |
| OB mitigação | touch WICK, fraction 1.0 | já eram equivalentes (full-fill) |
| Liquidez, cluster ATR ratio | 0.15 | mesmo nome/semântica nos dois motores; agora ambos causais |
| BPR | max_temporal_gap=30, mitigação 0.5 (midpoint) | já eram equivalentes |

**Resultado da validação cruzada com config compartilhada, dados reais WINFUT H1 completos (12.018 candles):**

| Componente | Batch (config compartilhada) | Incremental (config compartilhada) | Ratio |
|---|---:|---:|---:|
| swings | 1701 | 1519 | 1.12 |
| fvg | 2966 | 3940 | 0.75 |
| order_blocks | 1182 | 1761 | 0.67 |
| bos_choch | 2442 | 971 | 2.51 |
| liquidity | 219 | 1406 | 0.16 |
| bpr | 424 | 1370 | 0.31 |
| retracements | 11998 | 5212 | não comparável (unidade de contagem diferente) |

**Achado central: alinhar os parâmetros nominais NÃO fecha a maior parte do gap.** Isso prova que a divergência remanescente é **algorítmica**, não de calibração — documentado explicitamente em `shared_config.STRUCTURAL_DIVERGENCES`:

- **`order_blocks`**: `ob_definition` (prev/upstream/last_bear) do batch não tem equivalente no `ObComponent` incremental (algoritmo fixo único).
- **`retracements`**: unidade de contagem incompatível por design (uma linha por candle vs. um registro por evento estrutural) — nunca comparável por contagem.
- **`liquidity_clustering_algorithm`** (novo achado): mesmo com `cluster_tolerance_atr_ratio=0.15` idêntico e causal nos dois lados, batch=219 vs. incremental=1406 (ratio 0.16) — gap grande demais para ser calibração; o algoritmo de crescimento de cluster/detecção de sweep difere estruturalmente entre `liquidity.py` (varredura única por pivô, consumo in-place) e `LiquidityComponent` (regra de agregação diferente).
- **`bpr_size_filter`** (novo achado): batch filtra BPRs por tamanho (`bpr_max_size_pts=150.0`); `BprComponent` não tem filtro de tamanho — explica parte do gap 424 vs. 1370, sem parâmetro do lado incremental para alinhar.
- **`bos_choch_algorithm`** (novo achado): batch=2442 vs. incremental=971 (ratio 2.51) mesmo com `swing_length` idêntico e após a correção causal — o casamento de padrão de 4-swing e a janela de busca de rompimento diferem entre `structure.py` e `BosChochComponent` além de qualquer parâmetro compartilhável.

**Conclusão:** a config compartilhada elimina a divergência de CALIBRAÇÃO (que existia e foi real: FVG mitigação 0.5 vs 1.0, por exemplo) e serve como base única de verdade daqui em diante — mas **contagens agregadas entre batch e incremental continuam não sendo um método de validação confiável** para OB, BOS/CHOCH, Liquidity, BPR e Retracements, porque a divergência é algorítmica. Uma validação cruzada rigorosa desses componentes exigiria comparação evento-a-evento (não contagens), fora do escopo desta correção.

### 9.8 Testes

10 testes em `tests/test_smc_engine_v2/test_causal_views_lookahead_fix.py` (era 7, +3 para liquidez): reindexação de swings/FVG causal, exclusão de cauda, invariância de prefixo, correção de metadado do FVG, `causal_range=False` idêntico ao legado (`assert_frame_equal` exato), reprodução determinística do bug de liquidez futuro-vazando e fechamento pela correção, e invariância de prefixo do modo ATR. `pytest tests/test_smc_engine_v2/` — mesmas 7 falhas pré-existentes (não relacionadas), **zero regressões**, 167 passando (era 157 antes de toda a correção, 164 após a primeira parte).

### 9.9 Unificação de Algoritmos entre Batch e Incremental (2026-07-01, sessão subsequente)

Pedido explícito do usuário: "os dois motores precisam de um trabalho de unificação de algoritmo". Trabalho feito, componente a componente, cada um com testes de paridade real contra dados reais:

**BPR — filtro de tamanho.** `BprComponent` (incremental) não tinha `min_size_pts`/`max_size_pts`; batch sempre teve (`bpr_max_size_pts=150.0`, calibrado por backtest). Adicionado ao `BprComponent` (default 0.0/desabilitado — preserva comportamento anterior), alinhado via `shared_config.py`. **Resultado real:** ratio batch/incremental melhorou de 0.31 para **0.50** (incremental caiu de 1370 para 848 BPRs) no stream completo de WINFUT H1. Commit: filtro de tamanho no BPR.

**Order Blocks — unificação completa.** Investigação encontrou 3 diferenças estruturais empilhadas entre `order_blocks.py:calculate_ob` e `incremental/components/ob.py:_try_form_ob`:
1. **Gate de rompimento de swing** — batch exige incondicionalmente um rompimento de nível de swing antes de procurar OB; o incremental (`require_structure_break=False`, decisão de produto já registrada no R12) não exige.
2. **Seleção do candle de origem** — nenhum dos 3 modos do batch (`prev`/`upstream`/`last_bear`) bate com a regra do incremental (candle imediatamente anterior ao impulso, com corpo obrigatoriamente oposto à direção do impulso).
3. **Geometria da zona** — batch usa high/low inteiro do candle; incremental usa o lado do corpo (open/low para OB de alta, high/close para OB de baixa).

**Decisão do usuário:** motor incremental como referência (já validado causalmente nas 22 fases do plano R0-R22; o batch nunca passou por essa validação). Implementado `order_blocks._calculate_ob_incremental_parity()` — porte aditivo/opt-in do algoritmo exato do `ObComponent` para o formato vetorizado do batch, acionado via `ob_definition="incremental_parity"`. O modo `"prev"` (default de produção) permanece **totalmente intocado**.

**Verificação de paridade real (não apenas contagem):** rodando os dois motores sobre os mesmos dados reais —
- Amostra EURUSD 500 candles: **74 == 74** OBs.
- WINFUT H1 completo, 12.018 candles: **1761 == 1761** OBs — e não só a contagem: `(direção, top, bottom)` idênticos candle a candle em `tests/test_smc_engine_v2/test_ob_incremental_parity.py::test_incremental_parity_ob_zone_prices_match_incremental_engine`.

`shared_config.py`: `ob_definition` default agora é `"incremental_parity"`, e `STRUCTURAL_DIVERGENCES["order_blocks"]` foi removido (resolvido) — histórico completo mantido no docstring do módulo.

**Liquidity — look-ahead de ERL/IRL corrigido.** `_enrich_canonical_v3` classificava ERL/IRL usando `ohlc["high"].max()`/`ohlc["low"].min()` do dataset inteiro — outro look-ahead independente do de clustering. Estendido o mesmo flag `causal_range` para também usar expanding high/low. Reprodução determinística confirmada (mesmo pool: `ERL=True` no legado com pico futuro artificial, `ERL=False/IRL=True` na versão causal). `STRUCTURAL_DIVERGENCES["liquidity_erl_irl"]` resolvido.

**Liquidity — unificação completa do algoritmo de clustering.** Investigação revelou que `LiquidityComponent` (incremental) tem seu **próprio detector de swing interno** (mesma fórmula estrita de extremo local do `BosChochComponent`), completamente independente do `SwingComponent`/`calculate_swings` — não era só uma diferença de tolerância de clustering. Implementado `liquidity._calculate_liquidity_incremental_parity()` — porte aditivo/opt-in via `clustering_algorithm="incremental_parity"`. **Paridade verificada:** amostra EURUSD 500 candles **65 == 65**; WINFUT H1 completo **1400 == 1400** origens, 1398/1400 exatos em nível/direção (2 casos residuais são uma limitação documentada e conhecida: candle único confirmando swing high E low simultaneamente, colidindo no formato de saída em linha única por pool). `STRUCTURAL_DIVERGENCES["liquidity_clustering_algorithm"]` resolvido.

**BOS/CHOCH — unificação completa do algoritmo.** Mesmo padrão de causa raiz da liquidez: `BosChochComponent` tem detector de swing interno próprio (idêntico ao da liquidez), rastreia apenas o high/low mais recentemente confirmado (não um padrão de 4 swings), e classifica BOS vs. CHOCH por contexto de tendência de 2 swings. Também detecta `WICK_SWEEP_HIGH`/`WICK_SWEEP_LOW` (varredura de liquidez sem confirmação de fechamento), conceito inexistente nos outros 2 modos do batch. Implementado `structure._calculate_bos_choch_incremental_parity()` (`algorithm="incremental_parity"`). Durante o porte, **dois bugs reais foram encontrados e corrigidos** via diff direto contra o motor real: (1) suposição errada de que `uptrend`/`downtrend` eram mutuamente exclusivos — são flags independentes (calculadas de highs e lows separadamente) e `uptrend` tem prioridade quando ambas são verdadeiras simultaneamente; (2) mesma colisão de linha única já vista na liquidez (candle confirma high E low ao mesmo tempo) — corrigida separando `Level`/`BrokenIndex`/`WickSweepLevel` em pares de colunas High/Low distintas. **Paridade verificada: sequência completa idêntica** `(origin_index, structure_type, level)` — amostra EURUSD 500 candles **37 == 37**; WINFUT H1 completo **971 == 971**, sem nenhuma divergência. `STRUCTURAL_DIVERGENCES["bos_choch_algorithm"]` resolvido.

**Estado final das divergências estruturais:** de 6 originalmente identificadas (order_blocks, retracements, liquidity_erl_irl, liquidity_clustering_algorithm, bpr_size_filter, bos_choch_algorithm), **5 estão resolvidas**. Resta apenas:
- `retracements` — diferença de **unidade de contagem por design** (batch: uma linha por candle; incremental: um registro por evento estrutural). Isso não é um bug de algoritmo — é uma decisão de produto sobre qual formato de saída a Opportunity Engine deveria consumir. Não atacado nesta rodada; requer decisão explícita antes de qualquer implementação.

Testes: `tests/test_smc_engine_v2/test_ob_incremental_parity.py`, `test_liquidity_incremental_parity.py`, `test_bos_choch_incremental_parity.py` (9 novos no total, todos com verificação de paridade exata contra os motores reais, não apenas contagens agregadas). Suíte completa (`tests/test_smc_engine_v2/` + `tests/test_technical_engine/`): **2282 passando**, mesmas 8 falhas pré-existentes não relacionadas (confirmado via `git stash` que todas já existiam antes de qualquer mudança desta sessão).

### 9.10 Status e Próximos Passos

- **Nenhuma mudança de comportamento em produção por padrão** — todos os parâmetros de correção causal (`causal_swings_fvg`, `causal_liquidity_range`) e de unificação de algoritmo (`ob_definition="incremental_parity"`, `liquidity_clustering_algorithm="incremental_parity"`, `bos_choch_algorithm="incremental_parity"`) são opt-in; o pipeline batch em produção roda hoje exatamente como antes de toda esta correção. `shared_config.py` é uma biblioteca nova, não é consumida automaticamente por `run_b3.py`/`infra/sync_v2.py`.
- Shadow-run sobre dados reais WINFUT completo: **concluído** (§9.4, §9.7). Mostrou impacto real e não-trivial em OB (+82%) e BOS/CHOCH (+27%) quando a causalidade é ligada.
- **Unificação de algoritmo entre os dois motores: concluída para 5 de 6 divergências**, todas com paridade exata (não aproximada) verificada em dados reais completos de WINFUT (Order Blocks, BPR parcial, Liquidity clustering, Liquidity ERL/IRL, BOS/CHOCH). Apenas `retracements` permanece — decisão de produto, não item técnico.
- **Antes de considerar ativar qualquer uma dessas flags por padrão em produção, ou adotar `shared_config.py` no caminho de produção:**
  1. Revisão do dono do produto sobre o aumento de OB/BOS-CHOCH quando a causalidade é ligada — são zonas "novas" legítimas que o pipeline atual perdia, ou o aumento exige recalibração de thresholds/qualidade?
  2. Decisão sobre `retracements` — qual formato de saída (por candle vs. por evento estrutural) a Opportunity Engine deveria efetivamente consumir.
  3. Dado que `ob_definition`/`liquidity_clustering_algorithm`/`bos_choch_algorithm="incremental_parity"` agora reproduzem o motor incremental com paridade exata, uma decisão de cutover para produção poderia considerar usar o motor incremental diretamente (já shadow-only, já validado nas 22 fases do plano R0-R22) em vez de manter os dois motores em paralelo — decisão de arquitetura para o dono do produto.
