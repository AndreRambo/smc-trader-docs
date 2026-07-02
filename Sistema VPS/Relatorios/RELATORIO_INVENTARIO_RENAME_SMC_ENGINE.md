# Relatório de Inventário — Rename SMC Engine (Fase 0)

**Data:** 2026-07-02
**Referência:** `PLANO_PADRONIZACAO_SMC_ENGINE_TABELAS_SMC.md` §7

Levantamento somente-leitura (nenhum código alterado) das referências aos nomes versionados que o plano propõe padronizar.

## Contagem de arquivos `.py` afetados (excluindo `backups/`, `__pycache__`)

| Padrão | Arquivos |
|---|---|
| `smc_engine_v2` | 88 |
| `smc_engine_v3` | 126 |
| `run_smc_engine_v2_local` | 35 |
| `technical_engine_smc_v2_` (tabelas legadas) | 49 |
| `smc_v2_` (tabelas nativas incremental + legadas, ambíguo) | 84 |

## Referências ao endpoint `smc-engine-v2`

- `dashboard_shadow/backend/app/api/smc_engine_v2_ob.py`
- `dashboard_shadow/backend/app/api/smc_engine_v2_swings.py`
- `dashboard_shadow/backend/app/api/smc_engine_v2_fvg.py`
- `dashboard_shadow/backend/app/api/smc_engine_v2_state.py`
- `dashboard_shadow/dash_app/app/api_client.py`
- `dashboard_shadow/backend/start_backend.sh`
- `tools/compare_smc_engine_v2_local_vs_reference.py`
- `tools/audit_runtime_engine_imports.py`
- Testes: `test_dash_smc_engine_v2_fvg_source.py`, `test_smc_v2_regression_after_prune.py`, `test_dash_smc_engine_v2_local_api_routing.py`, `test_smc_engine_v2_fvg_endpoint.py`, `test_multilayer_smc_regression.py`

## Observação crítica

O padrão `smc_v2_` é **ambíguo**: cobre tanto as 9 tabelas legadas do batch (`technical_engine_smc_v2_*_shadow`) quanto o schema **nativo** do motor incremental aplicado nesta sessão (`smc_v2_structures`, `smc_v2_structure_events`, `smc_v2_engine_runs`, `smc_v2_checkpoints`, `smc_v2_active_stream_versions`, `smc_v2_reconciliation` — ver `ARQUITETURA_OFICIAL.md` §4.11-A). Qualquer rename em massa precisa distinguir os dois grupos antes de tocar em SQL.

## Escala do trabalho

Volume total (~380 arquivos únicos somando os padrões, com sobreposição) confirma que este é um plano de execução multi-fase real, não uma tarefa de uma sessão. Fases 1-11 do plano (criar `smc_engine/`, migrar imports, views de compatibilidade SQL, endpoints, Laravel sync, docs, testes, cutover, rollback) permanecem não iniciadas.

## Próximo passo

Conforme §23 do plano: só iniciar a criação do namespace oficial `technical_engine/smc_engine/` (Fase 1) após este inventário ser revisado.
