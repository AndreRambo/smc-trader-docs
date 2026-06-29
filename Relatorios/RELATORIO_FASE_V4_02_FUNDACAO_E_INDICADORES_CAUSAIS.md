# RELATORIO FASE V4_02 - FUNDACAO E INDICADORES CAUSAIS

## 1. Resumo executivo
A fundacao do namespace `technical_engine/live_replay_v4` foi endurecida sem aplicar migration, sem escrever no banco e sem executar rebuild. O CandleClock tornou-se a fonte temporal canonica, os indicadores minimos causais foram implementados de forma incremental/batch/serializavel e a fase foi validada com testes sinteticos, invariancia por prefixo/chunk/resume e checagens estaticas de schema/persistencia.

## 2. Branch e commit inicial
- Branch: `feature/winfut-causal-live-replay-v4`
- Commit inicial da fase: `0a95d8dcfbf05a5208c123303e47bb59f20e1d26`

## 3. Arquivos criados
- `technical_engine/live_replay_v4/validation.py`
- `technical_engine/live_replay_v4/indicators/__init__.py`
- `technical_engine/live_replay_v4/indicators/contracts.py`
- `technical_engine/live_replay_v4/indicators/registry.py`
- `technical_engine/live_replay_v4/indicators/engine.py`
- `technical_engine/live_replay_v4/indicators/true_range.py`
- `technical_engine/live_replay_v4/indicators/range_metrics.py`
- `technical_engine/live_replay_v4/indicators/ema.py`
- `technical_engine/live_replay_v4/indicators/rsi.py`
- `technical_engine/live_replay_v4/indicators/atr.py`
- `technical_engine/live_replay_v4/indicators/volatility.py`
- `tests/live_replay_v4/helpers.py`
- `tests/live_replay_v4/test_hashing.py`
- `tests/live_replay_v4/test_identity.py`
- `tests/live_replay_v4/test_indicator_contracts.py`
- `tests/live_replay_v4/test_true_range.py`
- `tests/live_replay_v4/test_ema.py`
- `tests/live_replay_v4/test_rsi.py`
- `tests/live_replay_v4/test_atr.py`
- `tests/live_replay_v4/test_volatility.py`
- `tests/live_replay_v4/test_indicator_prefix_invariance.py`
- `tests/live_replay_v4/test_indicator_chunk_invariance.py`
- `tests/live_replay_v4/test_indicator_resume.py`
- `tests/live_replay_v4/test_no_future_access.py`
- `tests/live_replay_v4/test_import_side_effects.py`
- `tests/live_replay_v4/test_static_schema_and_persistence.py`
- `tests/live_replay_v4/test_cli_reports.py`

## 4. Arquivos alterados
- `requirements.txt`
- `migrations/20260627_live_replay_v4_schema.py`
- `technical_engine/live_replay_v4/__init__.py`
- `technical_engine/live_replay_v4/contracts.py`
- `technical_engine/live_replay_v4/candle_clock.py`
- `technical_engine/live_replay_v4/hashing.py`
- `technical_engine/live_replay_v4/identity.py`
- `technical_engine/live_replay_v4/state.py`
- `technical_engine/live_replay_v4/exceptions.py`
- `technical_engine/live_replay_v4/config.py`
- `technical_engine/live_replay_v4/cli.py`
- `technical_engine/live_replay_v4/persistence/connection.py`
- `technical_engine/live_replay_v4/persistence/repositories.py`
- `technical_engine/live_replay_v4/persistence/mappings.py`
- `technical_engine/live_replay_v4/persistence/reconciliation.py`
- `technical_engine/live_replay_v4/persistence/transactions.py`
- `tests/live_replay_v4/test_candle_clock.py`
- `tests/live_replay_v4/test_contracts.py`
- `tests/live_replay_v4/test_persistence_contracts.py`
- `tests/live_replay_v4/test_schema_plan.py`

## 5. Decisoes de formula
- `RANGE = high - low`.
- `TRUE_RANGE` usa `max(high-low, abs(high-prev_close), abs(low-prev_close))`, com primeiro candle fechado usando `high-low`.
- `EMA20/EMA200` usam seed por SMA dos primeiros `P` closes e atualizacao exponencial classica.
- `RSI14` usa Wilder, primeira leitura madura apos 15 closes.
- `ATR14` usa Wilder, primeira leitura madura no 14o candle fechado.
- `VOLATILITY_BUCKET` adotou opcao B: thresholds fixos e configuraveis sobre `ATR14 / close`, versionados no `parameter_hash` e marcados como provisórios ate calibracao.

## 6. Politica temporal
- `market_candles.timestamp` continua semantica de abertura.
- O fechamento historico e `available_at` sao sempre derivados do proximo candle real.
- O ultimo candle fica `PENDING_SOURCE_CLOSE` e nao entra em indicadores finais, estruturas ou decisoes.
- Mistura de datetime aware/naive falha explicitamente com `AmbiguousTimestampError`.

## 7. Politica de warm-up
- Indicadores imaturos emitem `is_warm=false` e valor nao utilizavel.
- `EMA20`: maduro no 20o candle fechado.
- `EMA200`: maduro no 200o candle fechado.
- `RSI14`: maduro apos 15 closes.
- `ATR14`: maduro no 14o candle fechado.
- `VOLATILITY_BUCKET`: maduro junto com o `ATR14` interno.

## 8. Politica de volatility bucket
- Config padrao provisoria: `low=0.0008`, `normal=0.0016`, `high=0.0024`, acima disso `EXTREME`.
- `production_calibrated=false` por padrao.
- Thresholds entram no `parameter_hash` e ficam explicitamente documentados como provisórios.

## 9. Testes executados
- `python3 -m pytest tests/live_replay_v4 -q`
- `python3 -m technical_engine.live_replay_v4.cli environment`
- `python3 -m technical_engine.live_replay_v4.cli preflight`
- `python3 -m technical_engine.live_replay_v4.cli schema-plan`
- `python3 -m technical_engine.live_replay_v4.cli foundation-validate`
- `python3 -m technical_engine.live_replay_v4.cli indicator-selftest`
- `python3 -m py_compile $(rg --files technical_engine/live_replay_v4 tests/live_replay_v4 migrations/20260627_live_replay_v4_schema.py)`
- `python3 -m compileall technical_engine/live_replay_v4 tests/live_replay_v4`

## 10. Quantidade total de testes
- Suite `tests/live_replay_v4`: 51 testes aprovados, 0 falhas.
- `foundation-validate`: 6 checagens PASS.
- `indicator-selftest`: 4 checagens PASS.

## 11. Gates
| Gate | Status | Evidencia |
|---|---|---|
| CONTRACTS / FOUNDATION | PASS | `51 passed`, `foundation_validation.json` |
| CANDLE CLOCK | PASS | `test_candle_clock.py`, `foundation_validation.json` |
| HASHING / IDENTITY | PASS | `test_hashing.py`, `test_identity.py` |
| INDICATORS | PASS | `test_true_range.py`, `test_ema.py`, `test_rsi.py`, `test_atr.py`, `test_volatility.py` |
| PREFIX INVARIANCE | PASS | `test_indicator_prefix_invariance.py`, `indicator_validation.json` |
| CHUNK INVARIANCE | PASS | `test_indicator_chunk_invariance.py`, `indicator_validation.json` |
| RESUME INVARIANCE | PASS | `test_indicator_resume.py`, `indicator_validation.json` |
| NO FUTURE | PASS | `test_no_future_access.py`, `foundation_validation.json` |
| IMPORT SIDE EFFECTS | PASS | `test_import_side_effects.py`, `foundation_validation.json` |
| SCHEMA STATIC | PASS | `test_schema_plan.py`, `test_static_schema_and_persistence.py` |

## 12. Hashes dos relatorios JSON
- `environment.json`: `7513179245e8102c486ce54e97d04dd36d0cebae29890f4cc7c77167c2b5d6cd`
- `preflight.json`: `38fda56f2d2687950abfff9539b8b3aca751d56cb8cb0277074fc7177a27f183`
- `schema_plan.json`: `0d66f91569704c0c34e6aa1ffc090315011a771d81a8359eb67991eac06140fd`
- `foundation_validation.json`: `4d608bd07587f2a6661316917460dd5dd16d6b89dba8476aa41d22089471879a`
- `indicator_validation.json`: `a112095382796bdd74ed0e522e66383494ee5ed6c84f8738d2bc9ee745a06862`

## 13. Confirmacoes operacionais
- Nenhuma migration foi aplicada.
- O banco nao recebeu escrita nesta fase.
- Nenhum rebuild/backtest/start/worker/full/freeze/activate foi executado.
- `technical_engine/smc_engine_v2` permaneceu intacto.
- Os quatro arquivos legados nao rastreados foram preservados com os mesmos hashes.

## 14. Commit final
- Commit final da fase: `e09370584b88ed64369fe46fdcd3a0aaf4e62b51` (`feat(live-replay-v4): harden foundation and add causal indicators`).

## 15. Riscos pendentes
- O schema V4 continua apenas declarativo; validacao transacional real ficou para a fase seguinte.
- Os thresholds de `VOLATILITY_BUCKET` sao provisórios e ainda nao calibrados para uso produtivo.
- A persistencia V4 esta alinhada estaticamente, mas sem integration test real em MySQL por regra desta fase.

## 16. Proximo passo recomendado
FASE V4_03 - aplicacao controlada do schema V4 em ambiente isolado de teste e validacao transacional real.
