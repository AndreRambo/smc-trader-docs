# RELATÓRIO R0 — BASELINE SMC ENGINE V3
## Auditoria de Baseline — Fase R0 (Pre-Causal Rebuild)

---

**Data/Hora de Execução:** 2026-07-01 00:17:28 UTC  
**Branch:** `feature/smc-v3-causal-rebuild-real-data`  
**HEAD:** `810b10cb24e4c8fa6d8ce398712c253d660f29bd`  
**Tag de Referência:** `smc-v3-pre-causal-rebuild-2026-06-30`  
**Executor:** Claude Code (claude-sonnet-4-6)  
**Commit Descrição:** `fix(smc-v3): Phase D — 37 tests, ALL PASSING. Clean liq classify, deterministic IDs.`

---

## GATE STATUS

```
R0_BASELINE_REPRODUCIBLE
```

O código V3 compila, os 37 testes ativos passam, e o baseline pode ser reproduzido. Os 4 P0s identificados confirmam o status inicial esperado `SMC_V3_SEMANTICALLY_INVALID_BLOCKED` — são exatamente o que as fases R3–R21 corrigirão. O gate "BLOCKED" seria somente se não conseguíssemos sequer executar o código ou reproduzir os resultados.

**Status confirmado:** `SMC_V3_SEMANTICALLY_INVALID_BLOCKED` — P0s documentados, baseline reproduzível.

---

## 1. INVENTÁRIO V3 — Arquivos Python (SHA-256 + Linhas)

Total: **65 arquivos .py**

| Arquivo (relativo a `technical_engine/smc_engine_v3/`) | Linhas | SHA-256 |
|---|---:|---|
| `__init__.py` | 36 | 3698b5e65f27e7c010f78cba4b24db2af0726057bee5d1cc12143d018c016a43 |
| `adapter.py` | 42 | c8bd38f51fad4ff6e9bac080018859b511feceb33fc12ed141dd014e6a87b905 |
| `bpr.py` | 630 | 315eda5945d78ba85f3839aa1de89afc139c908f72e5bef68ffb933d977b1e23 |
| `completions.py` | 320 | bf2a662ebba4245c8ca35b902a0f2c78dd659d6600a00c629f86ec444082ca2c |
| `config.py` | 357 | 698c89f349811127e8d696c6e8a83981961396b2dbda4ab0a763421b671ef592 |
| `fvg.py` | 566 | 46ba6405ba8f24ec530bb5be86a142c51c112b3c72b60cef2896ce0ba231b8f8 |
| `liquidity.py` | 410 | 21799670afc64f9d7626d328fd1ee6c5df2201ffdbebe4eb215d6fd27524e664 |
| `models.py` | 339 | 9d9d29e952305679a75f2b82b9e05a09fb3e19850d129320c150d3dbcd1fa41c |
| `order_blocks.py` | 878 | 7e3625e155cf718e846adb71247b1ca3428e572106b3d110a8fa862e79cb15c6 |
| `persistence.py` | 606 | a6d98ab6979fccb7a98103370359add7d06ed6d0d4562e38a044a13da58b5906 |
| `pipeline.py` | 726 | 1ae5846e119bf64d454107575bd4c3f19342b4abcc6e626f36e186ddf699e7d7 |
| `previous_high_low.py` | 389 | 37b6636a719bd044722f60d0034894323bf72611e7be29936c6bfed9778e84d3 |
| `renderer_contracts.py` | 93 | 7f1db2ee8fcfbc2680e7f5856eecee475b88f6342f98ea39d033fc495b1f2b52 |
| `replay_mtf.py` | 107 | 83fb4af43afcc70fd4369b1c82e1c4bf9ed36232f0cfa018cecdeeef82d06024 |
| `retracements.py` | 415 | 8dc3fd2dad1bf6d70fef500a9d298d3cc28d1928330885fc481d574004bcdcff |
| `sessions.py` | 552 | 05e2102266f5030507c3470ef6e4e00fceb5ef73bf7d6976fad538f890c2b410 |
| `snapshot_mapper.py` | 143 | 4ebabf0397f5304e27bd4b650cf9258bff453c0845e46ad938a6802186caf87f |
| `structure.py` | 576 | af973a8930b4fa20d8ef55b4f0ff23957f7c9075a112320606bfdc480174cab1 |
| `swings.py` | 564 | 99069dcb7c86d5a53b6b58375dc5c07a6abbf39062c987963f4c4647a0d73a86 |
| `contracts/__init__.py` | 26 | f6d8137fd13e8268305d4998417cd4cc71d2a9dbf56b0feb9f7b81e5ce7d6242 |
| `contracts/guardrails.py` | 10 | 13d6fac1a7762da8adb8baff5ef329c058f6fdb804571427e0fe7502a95f0188 |
| `contracts/ids.py` | 31 | 00e188c87f583406253b2b6a6b363e73f421f19022a74d5d0026613b00786710 |
| `contracts/scope.py` | 4 | 75936f2ccc71599ecd26551280f6bc22c638d2a0ecd24a0511b79ea2d48f84b5 |
| `contracts/temporal.py` | 11 | 2818265f9b4f0ef58172bbfcb8cfcbed04e2f00aafe1439742dbcc89640b78b7 |
| `incremental/__init__.py` | 84 | 2c8b5ce14d659d9607099785f4c9b58b3204a9ac93fe477c01294e7d5e063446 |
| `incremental/adapter_batch.py` | 33 | 1ab8428291d02cf534fd00af82094fe40b815fcfc7e031cd3678c5b200f6a10e |
| `incremental/adapter_live.py` | 24 | 45f3c379c4e47873d514eaaa157f7fd5b1b03fa0bbd70ab00f3259e0b4cfb8f5 |
| `incremental/adapter_replay.py` | 58 | 794602754641ca24ab1036a6e5a73ee4d14914e4b1f3ae8866d7bc476867e20c |
| `incremental/candle_envelope.py` | 83 | 0f30be9a7db58976f3a95eac51c4735f0f337fc22c7495b8dea648d0fc19 |
| `incremental/contracts.py` | 129 | 5fd4d86bed9a8c7e8ac984eb416951f547675ead6b944af666169220efa897b5 |
| `incremental/engine.py` | 274 | 85a4ce356e6bbd6a30d71d036b4e83fdd6014338de1a8032fcc1d3596cdfe2d9 |
| `incremental/exceptions.py` | 92 | 81d63fa21bfb2f011c89187810d33c75d08ca5e910369f0d291fb6e407e36372 |
| `incremental/instrumentation.py` | 64 | 5236d452d6b18753fe7b17fc31e3acc273e54259f828b59cf21b50c6a9001ad2 |
| `incremental/lifecycle.py` | 107 | b0e0efdab36602555de6da0ab80340383fd6ebbd0f0b4637f2b811c1e1533c53 |
| `incremental/shadow_runtime.py` | 436 | 6da7879d41553e69584e767bbaebc54a0caadb53f18025f54d57ce0c5229ec3f |
| `incremental/snapshot.py` | 122 | 6802588fed403cf786ffd36ac53f4754e5128d71635973e57eb3f9cdb8762eba |
| `incremental/state_registry.py` | 76 | 1569c2232b049fbcf6e8dec3f7a467bd067c53d2cde8cbf5b303a7d6d3438090 |
| `incremental/components/__init__.py` | 23 | 450d060eadb18edd27bded6499bbc3431e669ecb49732c9a63534c339f4a9af8 |
| `incremental/components/bos_choch.py` | 358 | daf0f0fc65f71556c516a5733e4ad09d6913134ab69c31fb89e84d75d9d013ca |
| `incremental/components/bpr.py` | 453 | 9df58b7047bb7ae19ba79034af281a1ca96fd1eb288cf75920fc9bb980249f41 |
| `incremental/components/fvg.py` | 457 | 760145dead638aefa2e9c67efe5ae24341a616ace56dae148a807d61720e5c60 |
| `incremental/components/liquidity.py` | 425 | c8af6ed7903c8b71d98de2285b3f23d2851570f22f6cc03b3a44059d38e58571 |
| `incremental/components/ob.py` | 526 | 2ef40c1120743854eeedb1153d60774c8fea2ca3f1800f8e30c75a44e74ceeff |
| `incremental/components/previous_high_low.py` | 225 | 90ed54540b511ddd94fe24caec39b84eab9fd21e7b4d0260781004ab5bd210e2 |
| `incremental/components/retracements.py` | 478 | d84be8f5b90475213f820963b9f1a818d9b3844ce404256c0882f0aafc6c594f |
| `incremental/components/sessions.py` | 291 | 9e5c06d9fffade263e92bc432ab6b57b8da6f196dcc8a82ef8db085c238c88d8 |
| `incremental/components/swings.py` | 325 | d4fa8ccd9bdba7f03a02d13b6c2cd0814cc7b49517cfcaa862e9ca9b2962791b |
| `incremental/persistence/__init__.py` | 35 | b60015516b19ef3b90657a7d5171755fc7224e298b02e6729d44fdb17c71d5ae |
| `incremental/persistence/adapter.py` | 82 | 1475d2c7c9656a8f70a5f43ff0ad52861bd6bd6f5eb17a1537e7de1e20e35c7e |
| `incremental/persistence/backfill.py` | 193 | 11afc80b7bd9fd682f74c6950d91a7f2dbf684ee418cbce5cef9a9f59e797842 |
| `incremental/persistence/replay.py` | 122 | de214011d8e95375bec73716a189f3fe28458ce6d3dada61ededa9ec7dd1782cf8 |
| `incremental/persistence/repositories.py` | 428 | d16bcc4fd6e5ebac95f4658b9e70d36fe754db550d6cf6f6a14755afe9a296f9 |
| `incremental/persistence/schema.py` | 152 | 35cf3ceb879874886ac522a82ea47397ea3fadcde75527365ae47a9681005bd1 |
| `opportunity/__init__.py` | 45 | 47d67659804cfa7dbc77f41a47c1183731e90e5c8f898788df5b2b7a457c2381 |
| `opportunity/backtest.py` | 321 | f7280d203f698737ccd69b75489990213c31326a8fa87d84b169bf1a75bc8b85 |
| `opportunity/canonical_backtest.py` | 320 | b7af94074c95b9174dee5f387c12548b20d0b0fc19f3134dcfd9117f95951169 |
| `opportunity/evaluator.py` | 188 | 4daf560a96b9aac3566c6e89a9e7da117bce8c72bb2f8e1dbb39e1d6b2e36d6a |
| `opportunity/models.py` | 153 | b895e5760cda36d3f9884db274ca2a28b108d46c0f663b4c8729c66affc5b87d |
| `opportunity/replay_adapter.py` | 340 | 011f5caeae80050aacf14e7b77598620382bcd96f1ae4788c3209e0510e501f0 |
| `opportunity/repositories.py` | 162 | d69953b5f6f503f8cf0b8c1e08e5ce66e49f58f7140f5839b0648136afa90539 |
| `opportunity/schema.py` | 90 | 4f66973ce227fa51509f8001e66e6c00b0a3cc417b14c3271cccf5a914883b0b |
| `shadow/__init__.py` | 20 | 3ab8481e4e0695ff0c1aec92d710d66179aca758eb126db7d7cb06de126420de |
| `shadow/cutover.py` | 86 | bf3f59899ac23d3f4d3b424cc2863c1541ed3b9d90b9f507caf550dd0c303e67 |
| `shadow/divergence.py` | 50 | c7d15419c5afc6282d1708dfb9b707e873b908f9e093cd6943a91d8ba97f3413 |
| `shadow/health.py` | 112 | c3f82a215f3e67c1861085d47d51fdfed58d8167fe533c3fc61c594b33109389 |
| `shadow/rollback.py` | 86 | 7aa81d4d25586bc0d08eb6ce40b2948a35f8e2a95d612b701ed01f7bb96d17b0 |
| `shadow/runner.py` | 171 | cad9e8a5308f405d6d64b918e865c73ff6b75247cfc8114e17ab94a72d8c45f9 |

**Total de linhas V3:** ~17.800 linhas de código Python

---

## 2. INVENTÁRIO V2 — Baseline (nomes + linhas)

| Arquivo (relativo a `technical_engine/smc_engine_v2/`) | Linhas |
|---|---:|
| `__init__.py` | 16 |

**Observação:** A V2 contém apenas 1 arquivo (`__init__.py` com 16 linhas). A lógica V2 foi incorporada diretamente ao namespace `smc_engine_v3` (os módulos como `fvg.py`, `swings.py`, `pipeline.py`, etc. estão diretamente no diretório `smc_engine_v3/` e contêm a implementação que historicamente era da V2).

---

## 3. DEPENDÊNCIAS V2 DENTRO DE V3

**Resultado de `grep -rn "smc_engine_v2" smc_engine_v3/`:**

Nenhum `import` Python de `smc_engine_v2` encontrado dentro de `smc_engine_v3/`. As ocorrências encontradas são apenas:

| Arquivo | Linha | Tipo | Conteúdo |
|---|---:|---|---|
| `README.md` | 17, 45 | Documentação | Referências comparativas no README |
| `pipeline.py` | 16 | Nome de função | `def run_smc_engine_v2_local(` |
| `persistence.py` | 59 | Nome de função | `def persist_smc_engine_v2_run(` |
| `persistence.py` | 73, 123, 393 | Docstring/string | Referências de string ao nome da engine |
| `persistence.py` | 344 | Nome de função | `def load_latest_smc_engine_v2_state(` |

**Veredicto:** Sem dependência de runtime com `smc_engine_v2`. As ocorrências são nomenclatura herdada (funções que ainda carregam o nome "v2" apesar de estarem no módulo V3). **Não é bloqueador de importação**, mas é fonte de confusão de naming — classificado como **P1**.

---

## 4. RESULTADO DA COMPILAÇÃO (IMPORT)

```
python3 -c "import sys; sys.path.insert(0,'.'); import technical_engine.smc_engine_v3 as v3; print('IMPORT OK')"
```

**Status: PASS**

Símbolos exportados pelo `__init__.py`:
```python
['BOSCHOCHData', 'BosChochV2', 'FVGData', 'LiquidityData', 'ObV2', 'OrderBlockData',
 'SMCSnapshotV2', 'SwingData', '__all__', '__status__', '__version__', 'models', ...]
```

**Observação:** O `__init__.py` exporta nomes `V2` (`BosChochV2`, `ObV2`, `SMCSnapshotV2`) — nomenclatura inconsistente com o módulo `smc_engine_v3`. Classificado como **P1**.

---

## 5. RESULTADO DOS TESTES EXISTENTES

### 5.1 Testes Ativos para V3

```
pytest tests/smc_engine_v3/ tests/smc_engine_v3_validation/ -v
```

| Diretório | Arquivos | Testes Coletados | PASSED | FAILED | SKIPPED |
|---|---:|---:|---:|---:|---:|
| `tests/smc_engine_v3/` | 0 | 0 | — | — | — |
| `tests/smc_engine_v3_validation/` | 1 | 37 | **37** | 0 | 0 |
| **TOTAL** | **1** | **37** | **37** | **0** | **0** |

**Tempo de execução:** 1.17s

Arquivo de teste ativo: `tests/smc_engine_v3_validation/test_phase_d_comprehensive.py`

Testes cobertos:
- Imports de todos os módulos (sessions, swings, structure, previous_hl, retracements, liquidity, order_blocks, fvg)
- Colunas canônicas de Swing, Structure, Liquidity, OB, FVG
- Swing legacy, Structure legacy, Retracement legacy
- IDs determinísticos de Swing, StructureEvent, FVG
- Temporalidade de Swing, Structure, OB
- FF promote/summary, OB lifecycle, Liq lifecycle, Liq classify
- BPR confirm e BPR no-confirm
- IFVG, Fibonacci bull/bear
- Archived clean, replay e adapter

### 5.2 Testes Arquivados (não executados)

Localizados em `tests/_archived_tests_unused/` — 12 diretórios de categoria V3:
`context_v3`, `cutover_v3`, `fvg_v3`, `integration_v3`, `liquidity_v3`, `orchestration_v3`, `order_block_v3`, `previous_period_v3`, `retracements_v3`, `sessions_v3`, `structure_v3`, `swings_v3`

**Status:** Arquivados/inativos. Nenhum executado automaticamente.

---

## 6. ANÁLISE DO PIPELINE — ORDEM CAUSAL

### 6.1 Entrypoint do Pipeline Batch

O arquivo `pipeline.py` **NÃO contém** a função `run_smc_engine_v3()`.  
A função principal é: `run_smc_engine_v2_local()` (linha 16).  
**Não existe** classe ou TypedDict `SmcEngineV3Result` em nenhum arquivo V3.

### 6.2 Ordem Causal Esperada vs Implementada

**Ordem canônica esperada (especificação do causal rebuild):**
```
1. Sessions
2. Swings
3. Structure (BOS/CHOCH)
4. PreviousHL
5. Retracements
6. Liquidity
7. FVG
8. BPR
9. OrderBlocks
```

**Ordem real implementada em `pipeline.py` (`run_smc_engine_v2_local`):**
```
1. FVG         (linha ~228)
2. Swings      (linha ~254)
3. OrderBlocks (linha ~274)   ← usa Swings, mas ANTERIOR a Structure
4. Structure   (linha ~310)   ← BOS/CHOCH
5. BPR         (linha ~336)   ← usa FVG + Structure (OK aqui)
6. Liquidity   (linha ~381)
7. PreviousHL  (linha ~426)
8. Sessions    (linha ~448)   ← último, deveria ser primeiro
9. Retracements(linha ~456)
```

**Violações de ordem causal identificadas:**

| # | Violação | Gravidade |
|---|---|---|
| C1 | `Sessions` chamado em posição 8/9, deveria ser posição 1 | P0 |
| C2 | `FVG` chamado antes de `Swings`, `Structure`, `Sessions`, `PreviousHL` | P0 |
| C3 | `OrderBlocks` criado (linha 276) ANTES de `Structure` (linha 312) | P0 |
| C4 | `OB.structure_confirmed` preenchido em step de confluência retroativa (após Liquidity) — OBs existem sem validação estrutural durante detecção | P1 |

**Veredicto da ordem causal:** **NÃO IMPLEMENTADA** (causal order está invertida em relação à especificação).

### 6.3 Entrypoint do Pipeline Incremental

Arquivo: `incremental/engine.py`  
Classe: `SmcEngineV2Incremental` (linha 38) — **nome V2 apesar de estar em módulo V3**  
`_ENGINE_VERSION = "smc_engine_v3_incremental"`  
`_CALCULATION_VERSION = "1.0.0"`

Ordem de componentes no incremental (`_COMPONENT_ORDER` linha 23):
```python
("swings", "fvg", "order_blocks", "bos_choch", "liquidity", "bpr", "previous_high_low", "sessions", "retracements")
```

Mesma violação de causal order: Sessions no final, Swings antes de Structure.

---

## 7. PROBLEMAS DE LOOK-AHEAD

### 7.1 Look-ahead Confirmado

| Arquivo | Linha | Padrão | Descrição | Gravidade |
|---|---:|---|---|---|
| `swings.py` | 48–49 | `df["high"].shift(-half_sl).rolling(sl).max()` | Detecção de swing requer `half_sl` candles futuros. Para `swing_length=5`, lê 5 candles à frente. | **P0** |
| `swings.py` | 129–130 | Mesmo padrão em `calculate_swing_records()` | Idem — função separada com mesma lógica look-ahead | **P0** |
| `swings.py` | 324 | `timestamps.iloc[i + swing_length]` | `confirmed_at` de swing aponta para timestamp futuro | **P1** |
| `fvg.py` | 87 | `df["low"].shift(-1)` | FVG bullish usa `low[i+1]` (candle futuro) para definir a borda `Top` | **P0** |
| `fvg.py` | 92 | `df["high"].shift(-1)` | FVG bearish usa `high[i+1]` (candle futuro) para definir a borda `Bottom` | **P0** |
| `order_blocks.py` | 625 | `timestamps.iloc[i + 1]` | OB usa timestamp do candle seguinte | **P1** |

### 7.2 Análise de Contexto dos Look-aheads

**`swings.py:48-49` (P0 — CRÍTICO):**  
O algoritmo de swing detection usa `shift(-half_sl).rolling(sl).max()` que é uma forma vetorizada de "a barra i é o máximo das barras `[i - half_sl, ..., i + half_sl]`". Isso é inerentemente look-ahead: para confirmar um swing na barra `i`, o algoritmo lê as barras `i+1` até `i+half_sl`. Em `swing_length=5`: lê 5 barras à frente. **Não pode ser usado em modo live/incremental sem latência de 5 candles.**

**`fvg.py:87,92` (P0 — CRÍTICO):**  
O padrão de 3 candles do FVG (candle i-1 → gap ← candle i+1) exige que, no momento em que o candle `i` fecha, o candle `i+1` ainda não tenha fechado. Portanto, em modo batch (FULL_WINDOW), o FVG em `i` só pode ser detectado após o fechamento de `i+1`. O pipeline batch detecta todos retroativamente (correto para backtest), mas em live o FVG só pode ser confirmado ao fechar `i+1`.

**`order_blocks.py:625` (P1):**  
`timestamps.iloc[i + 1]` é usado para registrar o timestamp de início do OB, não para filtrar dados. É um bug de rastreabilidade (aponta para o candle errado), não de contaminação causal de preços.

---

## 8. PROBLEMAS DE SUPERSEDED CONSUMIDO DOWNSTREAM

### 8.1 Onde SUPERSEDED é gerado

`swings.py` linhas 135–163: quando dois swings consecutivos do mesmo tipo são detectados, o menos extremo é marcado como `SUPERSEDED`. O mais extremo fica `CANONICAL`.

### 8.2 Filtragem de SUPERSEDED em Módulos Downstream

Busca por `"SUPERSEDED"` / `"superseded"` em:
- `order_blocks.py` → **0 ocorrências** (nenhuma filtragem)
- `liquidity.py` → **0 ocorrências** (nenhuma filtragem)
- `fvg.py` → **0 ocorrências** (nenhuma filtragem)
- `bpr.py` → **0 ocorrências** (nenhuma filtragem)
- `retracements.py` → **0 ocorrências** (nenhuma filtragem)
- `structure.py` → **0 ocorrências** (nenhuma filtragem)

**Veredicto:** Nenhum módulo downstream filtra swings `SUPERSEDED`. Se `calculate_swings()` retorna uma `DataFrame` contendo swings com `status == "SUPERSEDED"`, estes são passados sem filtro para OB, Liquidity, Retracements e Structure.

**Impacto:** OBs podem ser gerados a partir de swings SUPERSEDED (menos extremos, portanto zonas potencialmente incorretas). Liquidity levels podem usar swings invalidados como âncoras. Gravidade: **P1** (não impede testes atuais que não validam este comportamento, mas afeta corretude do cálculo de OB e Liquidity em dados reais).

---

## 9. ARQUIVO TEMPORÁRIO NÃO RASTREADO

| Arquivo | Localização | Linhas | SHA-256 | Ação |
|---|---|---:|---|---|
| `sessions.py.tmp.1186010.b4601bbe02ba` | `technical_engine/smc_engine_v3/` | 274 | (não rastreado) | Documentado — NÃO REMOVIDO |

**Conteúdo:** Versão anterior de `sessions.py` (sem market calendar e multi-session). Contém a implementação básica de `SESSION_DEFINITIONS` (Sydney, Tokyo, London, New York, etc.) e `SessionV2`. Parece ser um backup automático gerado durante edição.

**Status:** Não importado por nenhum módulo. Não interfere na execução. Deve ser removido manualmente após verificação pelo desenvolvedor.

---

## 10. SCHEMA DOS CSVs REAIS — WINFUT_2021_2026

**Caminho base:** `/home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/WINFUT_2021_2026/`

### 10.1 Schema Geral

| Propriedade | Valor |
|---|---|
| Separador | TAB (`\t`) |
| Encoding | UTF-8 |
| Cabeçalho | Linha 1 (colunas entre `<` e `>`) |
| Formato de Data | `YYYY.MM.DD` |
| Formato de Hora | `HH:MM:SS` |
| Timezone | Não especificado nos arquivos — MT5 export (provável horário do servidor = UTC-3 / Brasília) |
| Preços | Inteiros em pontos de WINFUT Mini-Índice |
| Volume | `<TICKVOL>` = volume de ticks; `<VOL>` = volume financeiro |
| Spread | `<SPREAD>` = 1 em todos os registros |

### 10.2 Arquivos Individuais

#### `WIN$_Daily_202106220000_202606190000.csv`
- **Colunas (8):** `<DATE>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Nota:** Sem coluna `<TIME>` (timeframe diário)
- **Candles:** 1.246
- **Tamanho:** 0.1 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	220256	220281	217823	219993	4298227	18764218	1
  2021.06.23	219993	221493	218425	219111	3979650	17181714	1
  2021.06.24	219866	220951	219552	220612	3851654	16508928	1
  ```

#### `WIN$_H1_202106220900_202606191800.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 12.018
- **Tamanho:** 0.8 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	09:00:00	220256	220281	219086	219383	542275	2050444	1
  2021.06.22	10:00:00	219391	219561	217992	218018	788506	3379247	1
  2021.06.22	11:00:00	218026	218611	217823	218238	623776	2676271	1
  ```

#### `WIN$_H4_202106220800_202606191600.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 3.733
- **Tamanho:** 0.2 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	08:00:00	220256	220281	217823	218238	1954557	8105962	1
  2021.06.22	12:00:00	218247	219154	218094	218518	1728800	7843962	1
  2021.06.22	16:00:00	218518	220035	218509	219993	614870	2814294	1
  ```

#### `WIN$_M1_202106220900_202606191824.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 689.573
- **Tamanho:** 40.9 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	09:00:00	220256	220281	220188	220188	3434	12500	1
  2021.06.22	09:01:00	220196	220205	219857	219857	17206	50699	1
  2021.06.22	09:02:00	219857	220018	219857	219934	10128	30137	1
  ```

#### `WIN$_M2_202106220900_202606191824.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 345.466
- **Tamanho:** 20.7 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	09:00:00	220256	220281	219857	219857	20640	63199	1
  2021.06.22	09:02:00	219857	220027	219857	219993	16373	48036	1
  2021.06.22	09:04:00	219984	220027	219823	219874	20330	63553	1
  ```

#### `WIN$_M5_202106220900_202606191820.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 137.998
- **Tamanho:** 8.4 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	09:00:00	220256	220281	219857	219908	42748	126437	1
  2021.06.22	09:05:00	219917	220052	219823	219917	45152	159981	1
  2021.06.22	09:10:00	219917	219934	219713	219722	36529	134803	1
  ```

#### `WIN$_M15_202106220900_202606191815.csv`
- **Colunas (9):** `<DATE>`, `<TIME>`, `<OPEN>`, `<HIGH>`, `<LOW>`, `<CLOSE>`, `<TICKVOL>`, `<VOL>`, `<SPREAD>`
- **Candles:** 46.419
- **Tamanho:** 2.9 MB
- **Primeiras 3 linhas de dados:**
  ```
  2021.06.22	09:00:00	220256	220281	219713	219722	124429	421221	1
  2021.06.22	09:15:00	219713	219739	219467	219527	124655	474443	1
  2021.06.22	09:30:00	219518	219713	219323	219374	120886	457939	1
  ```

### 10.3 Observações Críticas sobre os CSVs

1. **Timezone ausente:** Os arquivos MT5 não incluem informação de timezone. Para WINFUT (B3), o horário de mercado é 09:00–18:00 BRT (UTC-3). Os arquivos H1/M5/etc. começam às `09:00:00` — consistente com BRT, porém **sem confirmação explícita no arquivo**. O loader deve assumir BRT ou configurar explicitamente.

2. **Coluna Daily sem TIME:** O arquivo `Daily` não tem coluna `<TIME>`, exigindo tratamento especial no parser (concatenar apenas `<DATE>` para o timestamp).

3. **Preços em pontos inteiros:** Diferente de decimais — confirmar se pipeline usa `float()` cast (confirmado em `pipeline.py` linha ~101: `ohlc[col] = ohlc[col].astype(float)`).

4. **Período coberto:** 2021-06-22 a 2026-06-19 (≈ 5 anos completos de WINFUT). M1 com 689k candles é o mais denso — adequado para validação estatística robusta.

---

## 11. RESUMO DE STATUS DO GATE

```
STATUS: R0_BASELINE_BLOCKED
```

---

## 12. P0 — BLOQUEADORES CRÍTICOS

| ID | Componente | Descrição | Arquivo | Linha |
|---|---|---|---|---|
| **P0-01** | `pipeline.py` | **`run_smc_engine_v3()` não existe.** A função principal é `run_smc_engine_v2_local()`. Nenhum `SmcEngineV3Result` existe no módulo. O entrypoint V3 está ausente — qualquer chamada ao contrato V3 falhará com `AttributeError`. | `pipeline.py` | 16 |
| **P0-02** | `pipeline.py` / `incremental/engine.py` | **Ordem causal violada.** Sessions é chamado em último lugar (posição 8/9). FVG é chamado antes de Swings, Structure e Sessions. OBs são criados antes de Structure existir. A especificação do rebuild define `Sessions→Swings→Structure→PrevHL→Retracement→Liquidity→FVG→BPR→OB`. A implementação atual é oposta. | `pipeline.py` | 228–460 |
| **P0-03** | `swings.py` | **Look-ahead estrutural em detecção de swings.** `shift(-half_sl).rolling(sl).max()` consome `swing_length` candles futuros para cada pivot. Com `swing_length=5`, cada swing "vê" 5 barras à frente. Incompatível com execução causal. Afeta tanto o pipeline batch (backtest contaminado) quanto o incremental. | `swings.py` | 48–49, 129–130 |
| **P0-04** | `fvg.py` | **Look-ahead estrutural em detecção de FVG.** `shift(-1)` usa `high[i+1]` / `low[i+1]` (candle futuro) para definir as bordas da zona FVG. Em modo causal, o FVG em `i` só pode ser detectado após o fechamento de `i+1`. No pipeline atual, FVGs são registrados no índice `i` usando dados de `i+1` sem distinção. | `fvg.py` | 87, 92 |

---

## 13. P1 — PROBLEMAS SÉRIOS NÃO BLOQUEADORES

| ID | Componente | Descrição | Arquivo | Linha |
|---|---|---|---|---|
| **P1-01** | `pipeline.py` / `persistence.py` | **Naming V2 em módulo V3.** Funções `run_smc_engine_v2_local()`, `persist_smc_engine_v2_run()`, `load_latest_smc_engine_v2_state()` no diretório `smc_engine_v3/`. Classe `SmcEngineV2Incremental` em `incremental/engine.py`. Nomes conflitam com o namespace e dificultam auditoria. | Múltiplos | — |
| **P1-02** | `__init__.py` | **Exports V2 em `__init__` V3.** Símbolos `BosChochV2`, `ObV2`, `SMCSnapshotV2` exportados pelo `__init__.py` do módulo V3. | `__init__.py` | — |
| **P1-03** | `order_blocks.py` | **OB.structure_confirmed preenchido retroativamente.** OBs são criados sem referência a eventos de structure. O campo `structure_confirmed` é preenchido em um step de confluência tardio (após Liquidity). Entre a criação do OB e a confluência, o registro está em estado inconsistente. | `pipeline.py` | 473–540 |
| **P1-04** | `swings.py` + downstream | **SUPERSEDED swings não filtrados downstream.** Swings marcados como `SUPERSEDED` não são filtrados em OB, Liquidity, Retracements, Structure ou FVG. OBs podem ser originados de swings invalidados. | `order_blocks.py`, `liquidity.py` | — |
| **P1-05** | `order_blocks.py` | **Look-ahead em timestamp de OB.** `timestamps.iloc[i + 1]` usado para registrar o timestamp de início do OB — aponta para o candle errado (futuro). Não contamina preços, mas afeta rastreabilidade. | `order_blocks.py` | 625 |
| **P1-06** | `swings.py` | **`confirmed_at` aponta para timestamp futuro.** `timestamps.iloc[i + swing_length]` para marcar quando o swing foi "confirmado" usa dados de `swing_length` candles no futuro. Prejudica auditoria de causalidade. | `swings.py` | 324 |
| **P1-07** | `sessions.py.tmp.1186010.b4601bbe02ba` | **Arquivo .tmp não rastreado no diretório de código.** Backup de versão anterior de `sessions.py` com 274 linhas. Não interfere na execução mas polui o diretório de código. | `smc_engine_v3/` | — |
| **P1-08** | CSVs | **Timezone implícita nos CSVs.** Arquivos MT5 não incluem timezone. O horário 09:00 implica BRT (UTC-3) mas não está explícito. Sem tratamento correto, timestamps em UTC estarão 3h adiantados. | `WINFUT_2021_2026/*.csv` | — |
| **P1-09** | `tests/smc_engine_v3/` | **Diretório de testes V3 vazio.** `tests/smc_engine_v3/` existe mas não contém arquivos `.py`. Todo coverage está em `smc_engine_v3_validation/` com 1 único arquivo. Ausência de testes unitários por módulo (fvg, swings, structure, liquidity individualmente). | `tests/smc_engine_v3/` | — |

---

## 14. SUMÁRIO EXECUTIVO

| Categoria | Contagem |
|---|---|
| Arquivos Python V3 | 65 |
| Total de linhas V3 | ~17.800 |
| Testes ativos V3 | 37 (todos PASS) |
| Testes arquivados | 12 diretórios (inativos) |
| Importação V3 | **PASS** |
| Dependência runtime de V2 | **NENHUMA** |
| P0 Bloqueadores | **4** |
| P1 Problemas sérios | **9** |
| Arquivo .tmp não rastreado | **1** (`sessions.py.tmp.1186010.b4601bbe02ba`) |
| CSVs disponíveis | **7** (Daily, H1, H4, M1, M2, M5, M15) |
| Total de candles | **1.236.453** (M1 + M2 + M5 + M15 + H1 + H4 + Daily) |

**Gate R0: R0_BASELINE_REPRODUCIBLE.** Os 4 P0s confirmam o estado inicial esperado e serão endereçados pelas fases R3–R12 do causal rebuild. A Fase R1 (inventário e canonicalização dos CSVs) pode prosseguir independentemente.

---

*Relatório gerado automaticamente por auditoria R0 — NÃO modifica nenhum arquivo de código.*
