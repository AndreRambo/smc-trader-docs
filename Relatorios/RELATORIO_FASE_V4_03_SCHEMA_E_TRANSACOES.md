# RELATORIO FASE V4_03 - SCHEMA E TRANSACOES

## 1. Resumo executivo
A V4 foi estendida com um schema aplicavel real, validação de schema via `information_schema` e probe transacional real usando MySQL local. O apply controlado criou 18 tabelas `winfut_lr_v4_*`, a validação estrutural passou e o probe transacional confirmou rollback em tabelas V4 reais e commit em tabela temporaria de prova.

## 2. Branch e commit inicial
- Branch: `feature/winfut-causal-live-replay-v4`
- Commit inicial da fase: `e09370584b88ed64369fe46fdcd3a0aaf4e62b51`

## 3. Arquivos criados
- `technical_engine/live_replay_v4/schema.py`

## 4. Arquivos alterados
- `migrations/20260627_live_replay_v4_schema.py`
- `technical_engine/live_replay_v4/cli.py`
- `technical_engine/live_replay_v4/persistence/connection.py`
- `tests/live_replay_v4/test_static_schema_and_persistence.py`

## 5. Decisoes tecnicas
- O schema declarativo foi movido para `technical_engine/live_replay_v4/schema.py`, mantendo a migration como wrapper sem side effects.
- O `build_db_config()` passou a ler `.env` local explicitamente para tornar a CLI V4 operavel sem depender de variaveis exportadas no shell.
- A validacao estrutural consulta `information_schema` para verificar existencia das tabelas, FKs e ausencia de colunas nullable em `UNIQUE KEY`.
- O probe transacional usa duas estrategias: rollback em tabelas V4 reais e commit em tabela temporaria de prova.

## 6. Limitacao de isolamento
- O usuario `smc_user` nao possui permissao para `CREATE DATABASE`.
- O ambiente de teste isolado por database separado nao foi possivel.
- A validacao real foi executada com isolamento por namespace `winfut_lr_v4_*` dentro de `smc_trader_2_db`, sem tocar em tabelas legadas.

## 7. Testes e comandos executados
- `python3 -m pytest tests/live_replay_v4 -q`
- `python3 -m py_compile $(rg --files technical_engine/live_replay_v4 tests/live_replay_v4 migrations/20260627_live_replay_v4_schema.py)`
- `python3 -m compileall technical_engine/live_replay_v4 tests/live_replay_v4`
- `python3 -m technical_engine.live_replay_v4.cli schema-apply`
- `python3 -m technical_engine.live_replay_v4.cli schema-validate`
- `python3 -m technical_engine.live_replay_v4.cli transaction-probe`

## 8. Resultados
- Suite `tests/live_replay_v4`: 51 testes aprovados, 0 falhas.
- `schema-apply`: PASS, 18 tabelas aplicadas.
- `schema-validate`: PASS, sem tabelas faltantes, sem FKs invalidas, sem colunas nullable em unique.
- `transaction-probe`: PASS, rollback real sem residuos e commit de prova confirmado.

## 9. Hashes dos relatorios JSON
- `schema_apply_report.json`: `dfde5729260280d0ac873f5442904f8f7b5c01c6ba7b6497efd73d9456ea5dc3`
- `schema_validate_report.json`: `e5a0c0568ae5013e4d5fb77a5d63451c640f767fa5533d3a2937c1c0d9f44631`
- `transaction_probe_report.json`: `2a00d0f070ebc5591f0a66a563a2f3f6d32d42bf77fa40bc89e654bfcb76a04e`

## 10. Confirmacoes operacionais
- Nenhuma tabela legada foi escrita.
- Nenhum collector, worker, scanner, Opportunity Engine ou SMC V2 foi alterado.
- Os quatro arquivos legados nao rastreados permaneceram com os mesmos hashes.
- Nenhum run foi ativado.

## 11. Riscos pendentes
- O isolamento por database separado continua bloqueado por permissao do MySQL.
- O schema V4 foi aplicado no banco principal do projeto, mas somente em namespace proprio `winfut_lr_v4_*`.
- A proxima fase deve consolidar repositories e primeiros writes controlados de dados V4 usando esse schema ja aplicado.

## 12. Commit final
- Commit final da fase: `a79bff9ecd655e524d11897d2a3f5ff56f8ed30e` (`feat(live-replay-v4): apply schema and validate transactions`).
