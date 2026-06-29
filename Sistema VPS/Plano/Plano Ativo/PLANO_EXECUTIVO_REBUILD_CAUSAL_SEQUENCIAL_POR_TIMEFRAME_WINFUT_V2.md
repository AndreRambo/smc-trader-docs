# PLANO EXECUTIVO — REBUILD CAUSAL SEQUENCIAL POR TIMEFRAME WINFUT V2

## Projeto

`/home/bimaq/projetos/SMC_Trader_System_7_0`

## Objetivo

Recalcular, de forma causal, determinística, versionada e auditável, todos os dados técnicos do WINFUT, um timeframe por vez:

`D1 → H4 → H1 → M15 → M5 → M2`

Cada timeframe só poderá avançar após sample validation, full run, validação, segunda execução reprodutível e gate próprio.

Este plano substitui as tentativas anteriores baseadas em scripts diferentes, chunks independentes, `INSERT IGNORE`, IDs por `ref_index` local, agregação por `run_id LIKE`, status `READY` com erros e validações causais incompletas.

## Escopo técnico

Recalcular, quando suportado pela engine oficial:

- indicadores por candle;
- FVG;
- swings;
- BOS;
- CHOCH;
- Order Blocks;
- liquidez;
- previous high/low;
- sessions;
- retracements;
- premium/discount/equilibrium;
- Breaker/BPR/Rejection Block somente se já possuírem contrato oficial;
- Elliott histórico causal;
- Wyckoff histórico causal.

Não criar detectores novos apenas para preencher tabelas.


# 1. Regras absolutas

1. Um único runner parametrizado: `tools/rebuild_winfut_causal_v2.py`.
2. Não recalcular dois timeframes simultaneamente.
3. Não usar `INSERT IGNORE`.
4. Não usar `except: pass`.
5. Não usar `ref_index` local como identidade global.
6. Não aceitar run parcial.
7. Não marcar `READY` quando houver qualquer erro.
8. Não validar vários runs juntos por `LIKE`.
9. Não usar chunks independentes com overlap arbitrário.
10. Não alterar `app.py`.
11. Não escrever em tabelas oficiais.
12. Não abrir outcomes do `RECENT_HOLDOUT`.
13. Não iniciar replay nem live shadow.
14. Não ativar nenhum run até todos os timeframes passarem.


# 2. Fase 0 — Parada controlada do processo atual

Descobrir processos:

```bash
ps -ef | grep -E "causal_rebuild|rebuild_winfut|smc_engine_v2" | grep -v grep
systemctl --type=service --state=running | grep -Ei "smc|rebuild|analysis"
```

Registrar PID, comando, run_id, timeframe, início, último checkpoint e log.

Encerrar primeiro com `SIGTERM`. Usar `SIGKILL` somente se necessário.

Confirmar:

- nenhum rebuild em execução;
- nenhuma conexão escrevendo nos runs V1;
- contagens estáveis por 60 segundos.

Gate: `REBUILD_V1_STOPPED_AND_FROZEN`.


# 3. Fase 1 — Quarentena dos runs V1

Listar cada run individualmente. Não agregar por prefixo.

Marcar os runs experimentais como `FAILED` e adicionar em `metadata_json`:

```json
{
  "quarantined": true,
  "quarantine_code": "EXPERIMENTAL_INVALID_FOR_REPLAY",
  "reasons": [
    "migration_not_reproducible",
    "partial_timeframes",
    "insert_ignore",
    "local_ref_index_identity",
    "chunk_boundary_not_validated",
    "tests_not_completed",
    "reproducibility_not_proven"
  ]
}
```

Não apagar dados. Não ativar runs V1.

Gerar `runtime/data_driven_winfut/causal_rebuild_v2/quarantine_v1_inventory.json`.

Gate: `EXPERIMENTAL_V1_RUNS_QUARANTINED`.


# 4. Fase 2 — Backup, schema dump e rollback

Antes de qualquer nova alteração:

- backup lógico das tabelas shadow alteradas;
- dump do schema;
- contagens por tabela/run/timeframe;
- hashes dos dumps;
- procedimento de rollback;
- confirmação de que o run anterior continua preservado.

Gate: `BACKUP_AND_ROLLBACK_READY`.


# 5. Fase 3 — Migration V2 reproduzível

Criar somente:

`migrations/20260627_causal_rebuild_v2_schema.py`

Comandos obrigatórios:

```bash
python migrations/20260627_causal_rebuild_v2_schema.py check
python migrations/20260627_causal_rebuild_v2_schema.py apply
python migrations/20260627_causal_rebuild_v2_schema.py validate
python migrations/20260627_causal_rebuild_v2_schema.py rollback
```

Regras:

- usar `information_schema`;
- nenhuma execução Python inline;
- qualquer erro inesperado bloqueia e não imprime sucesso;
- testar apply/validate/rollback/reapply em schema descartável;
- migration deve reproduzir exatamente o schema final.

Gate: `MIGRATION_V2_APPLY_VALIDATE_ROLLBACK_PASS`.


# 6. Fase 4 — Parent run, child runs e checkpoints

Criar tabelas versionadas para:

## Parent run

Campos mínimos:

- `parent_run_id`;
- asset/symbol;
- engine_version;
- calculation_version;
- parameter_hash;
- data_hash;
- status;
- started_at/finished_at;
- error_code/error_message;
- metadata_json.

## Child run por timeframe

- `parent_run_id`;
- `timeframe_run_id`;
- timeframe;
- first/last candle;
- candle_count;
- indicators/structures/events written;
- content_hash;
- status;
- errors.

Unique: `(parent_run_id, timeframe)`.

## Checkpoints

- timeframe_run_id;
- sequence;
- last candle ID/time;
- serialized engine state hash;
- artifact path;
- rows written.

Status obrigatório:

```python
if errors:
    status = "FAILED"
elif validation_pending:
    status = "VALIDATING"
else:
    status = "READY"
```

Gate: `RUN_PARTITION_CHECKPOINT_SCHEMA_READY`.


# 7. Fase 5 — Identidade global das estruturas

Proibido: `f"ob_M2_{ref_index}"`.

Criar `logical_structure_id` SHA-256 a partir de:

- asset_id;
- timeframe;
- structure_type;
- origin_candle_id;
- confirmation_candle_id;
- direction;
- top/bottom normalizados;
- engine_version;
- parameter_hash.

Adicionar `global_ref_index`, `origin_candle_id`, `confirmation_candle_id` e `logical_structure_id`.

Unique: `(run_id, logical_structure_id)`.

Evento único por `(run_id, logical_structure_id, event_type, event_at, source_candle_id)`.

Gate: `GLOBAL_STRUCTURE_IDENTITY_VALIDATED`.


# 8. Fase 6 — Persistência sem erros silenciosos

Substituir `INSERT IGNORE` por persistência explícita.

Cada tentativa deve terminar em:

- INSERTED;
- EXPECTED_DUPLICATE;
- UNEXPECTED_DUPLICATE;
- REJECTED_VALIDATION;
- FAILED.

Duplicata só é esperada quando identidade e payload hash são iguais.

Mesma identidade com payload diferente = `IDENTITY_COLLISION`, bloqueando o run.

Registrar attempted/inserted/duplicates/rejected/failed.

Gate: `NO_SILENT_INSERT_FAILURES`.


# 9. Fase 7 — Prova de causalidade da engine

Antes do full rebuild, executar `prefix invariance`.

Para pelo menos 50 timestamps por timeframe, comparar:

- engine com dados até T;
- engine full-window filtrada por `available_at <= T`.

Comparar IDs, preços, direção, origin_at, confirmed_at, available_at e lifecycle até T.

Se houver diferença inexplicada: `BLOQUEADO_PREFIX_INVARIANCE`.

Caminhos permitidos:

## A. Full-window validado

Permitido se prefix invariance passar e todo o timeframe couber em uma única execução.

## B. Engine incremental/stateful

Obrigatório se full-window não for equivalente ou não couber.

Estado mínimo:

- rolling candle buffer;
- swings confirmados;
- últimos níveis estruturais;
- FVG/OB/liquidez ativos;
- previous highs/lows;
- session/retracement state;
- lifecycle aberto;
- último candle processado;
- sequences.

Gate: `SMC_ENGINE_CAUSAL_EQUIVALENCE_PASS`.


# 10. Fase 8 — Chunking somente stateful

É proibido:

- chunks independentes;
- overlap fixo como substituto de estado;
- IDs locais.

Permitido:

```text
chunk N recebe final_state do chunk N-1
```

Testar:

- origem no chunk N e confirmação no N+1;
- confirmação no N e mitigação no N+2;
- invalidação posterior;
- resume de checkpoint;
- hash chunked igual ao single-pass em amostras.

Gate: `STATEFUL_CHUNKING_EQUIVALENCE_PASS`.


# 11. Fase 9 — Indicadores causais

Preencher `technical_engine_indicator_values_shadow`.

Inventariar todos os indicadores consumidos por SMC, Opportunity Scanner, Candidate, Elliott e Wyckoff.

Mínimos:

- EMA200;
- ATR oficial;
- true range;
- range;
- volatility bucket;
- session id;
- volume/tick-volume features utilizadas.

Cada linha deve conter candle_id, timeframe, versão, parâmetros, valor, source_close_time, available_at, run_id e parameter_hash.

Validar com cálculo prefix-only.

Gate: `CAUSAL_INDICATORS_FILLED_AND_VALIDATED`.


# 12. Fase 10 — Estruturas e lifecycle

Núcleo obrigatório SMC V2:

- FVG;
- Swings;
- Order Blocks;
- BOS;
- CHOCH;
- Liquidity;
- Previous High/Low;
- Sessions;
- Retracements.

Condicionais somente se oficiais:

- Breaker;
- BPR;
- Rejection Block;
- Premium/Discount/Equilibrium;
- Dealing Range.

Eventos aplicáveis:

- ORIGINATED;
- CONFIRMED;
- AVAILABLE;
- FIRST_TOUCH;
- TOUCHED;
- PARTIALLY_MITIGATED;
- MITIGATED;
- INVALIDATED;
- EXPIRED;
- SWEPT.

Elliott/Wyckoff:

- recalcular somente se a engine for causal;
- caso contrário, `DISABLED_PENDING_CAUSAL_ENGINE`;
- não bloquear o núcleo SMC, mas registrar ressalva.


# 13. Fase 11 — Golden dataset por timeframe

Antes de cada full run, executar sample cobrindo:

- alta;
- baixa;
- lateralização;
- alta/baixa volatilidade;
- rollover;
- fronteiras de sessão.

Amostra mínima:

- 20 FVG;
- 20 swing highs;
- 20 swing lows;
- 20 BOS;
- 20 CHOCH;
- 20 OB bullish;
- 20 OB bearish;
- 20 liquidity pools;
- 20 transições de lifecycle.

Validar source candles, IDs, timestamps, preços e lifecycle.

Gate por timeframe: `<TF>_GOLDEN_SAMPLE_PASS`.


# 14. Fase 12 — Execução sequencial

Fluxo idêntico para cada timeframe:

1. preflight;
2. sample;
3. sample validation;
4. full run A;
5. validate run A;
6. full run B;
7. compare A/B;
8. freeze run A;
9. gate;
10. avançar.

Ordem e gates:

- `D1_CAUSAL_REBUILD_V2_PASS`;
- `H4_CAUSAL_REBUILD_V2_PASS`;
- `H1_CAUSAL_REBUILD_V2_PASS`;
- `M15_CAUSAL_REBUILD_V2_PASS`;
- `M5_CAUSAL_REBUILD_V2_PASS`;
- `M2_CAUSAL_REBUILD_V2_PASS`.

Exemplo:

```bash
python tools/rebuild_winfut_causal_v2.py preflight --timeframe D1
python tools/rebuild_winfut_causal_v2.py sample --timeframe D1
python tools/rebuild_winfut_causal_v2.py full --timeframe D1 --label A
python tools/rebuild_winfut_causal_v2.py validate --run-id <RUN_A>
python tools/rebuild_winfut_causal_v2.py full --timeframe D1 --label B
python tools/rebuild_winfut_causal_v2.py compare --run-a <RUN_A> --run-b <RUN_B>
python tools/rebuild_winfut_causal_v2.py freeze --run-id <RUN_A>
```

M2 só começa após todos os outros gates e exige stateful processing/checkpoint/resume comprovados.


# 15. Fase 13 — Validação exclusiva por child run

Nunca validar por `run_id LIKE`.

Para cada timeframe_run_id:

## Integridade

- status READY;
- errors = 0;
- failed inserts = 0;
- unexpected duplicates = 0;
- identity collisions = 0.

## Causalidade

- origin_at <= confirmed_at <= available_at;
- source/confirmation candle existem;
- available_at <= último candle fornecido à engine;
- first_touch_at >= available_at;
- mitigated/invalidated/expired_at >= available_at.

## Reconciliação

```text
attempted = inserted + expected_duplicates + rejected + failed
```

Residual = 0.

## Lifecycle

Toda estrutura disponível deve ter `STRUCTURE_AVAILABLE`.

Gate: `<TF>_VALIDATION_COMPLETE`.


# 16. Fase 14 — Reprodutibilidade

Executar full run A e B com mesmas entradas/config/engine.

Comparar:

- conjunto de logical_structure_id;
- payload hashes;
- event hashes;
- indicator hashes;
- contagens;
- distribuições de timestamps.

Excluir IDs técnicos, created_at e run_id do content hash.

Obrigatório: `content_hash_A == content_hash_B`.

Gate: `<TF>_REPRODUCIBLE`.


# 17. Fase 15 — Testes obrigatórios

## Migration

- apply;
- validate;
- rollback;
- reapply;
- fail-closed.

## Status

- erro nunca vira READY;
- run parcial nunca vira READY;
- child failed bloqueia parent.

## Identidade

- sem ref_index local;
- duplicate idêntico esperado;
- colisão de payload bloqueia.

## Chunk/state

- state restore;
- origem/confirm/mitigação atravessando fronteiras;
- chunked == single-pass.

## Causalidade

- prefix invariance;
- sem future slice;
- sem candle aberto;
- available_at correto;
- first touch não retroativo.

## Indicadores

- EMA200 prefix-only;
- ATR prefix-only;
- available_at por candle.

Executar:

```bash
pytest -q tests/test_smc_engine_v2
pytest -q tests/test_study_pipeline_shadow
pytest -q tests/test_data_driven_winfut
pytest -q tests/test_causal_rebuild_v2
```

Gate: `ALL_REBUILD_V2_TESTS_PASS`.


# 18. Fase 16 — Reconciliação global

Comparar dataset V2 com o anterior.

Classificar diferenças:

- EXPECTED_CAUSALITY_FIX;
- EXPECTED_VERSION_CHANGE;
- EXPECTED_IDENTITY_FIX;
- OLD_RUN_PARTIAL;
- OLD_RUN_DUPLICATE;
- NEW_ENGINE_BUG;
- UNEXPLAINED.

Obrigatório: `unexplained critical differences = 0`.

Gerar `reconciliation_global_v2.parquet` e `reconciliation_summary_v2.json`.


# 19. Fase 17 — Ativação atômica global

Pré-condições:

- seis timeframes PASS;
- testes PASS;
- reprodutibilidade PASS;
- reconciliação PASS;
- hashes PASS.

Fluxo:

```text
parent V2 READY
-> transaction
-> parent anterior SUPERSEDED
-> parent V2 ACTIVE
-> active_run_id por timeframe atualizado
-> commit
```

Em falha: rollback e run anterior preservado.

Gate: `WINFUT_CAUSAL_REBUILD_V2_ACTIVE`.


# 20. Relatórios

Um relatório por timeframe:

- `REBUILD_CAUSAL_V2_WINFUT_D1.md`
- `REBUILD_CAUSAL_V2_WINFUT_H4.md`
- `REBUILD_CAUSAL_V2_WINFUT_H1.md`
- `REBUILD_CAUSAL_V2_WINFUT_M15.md`
- `REBUILD_CAUSAL_V2_WINFUT_M5.md`
- `REBUILD_CAUSAL_V2_WINFUT_M2.md`

Relatório final:

`RELATORIO_FINAL_REBUILD_CAUSAL_SEQUENCIAL_WINFUT_V2.md`

Cada relatório deve conter run IDs, manifests, hashes, modo de execução, checkpoints, indicadores, estruturas, lifecycle, causalidade, erros, duplicatas, reconciliação, testes, reprodutibilidade e gate.


# 21. Gate final

Critérios:

- V1 parado e em quarentena;
- backup pronto;
- migration V2 reproduzível;
- parent/child runs;
- identidade global;
- zero INSERT IGNORE;
- zero erro silencioso;
- prefix invariance;
- stateful chunking quando necessário;
- indicadores preenchidos;
- todas as estruturas oficiais;
- seis timeframes aprovados;
- zero violações causais;
- zero colisões;
- zero diferenças críticas inexplicadas;
- testes passando;
- segunda execução idêntica;
- ativação atômica;
- run anterior preservado.

Gate:

`PRONTO_COM_REBUILD_CAUSAL_SEQUENCIAL_WINFUT_V2`

Somente após esse gate atualizar o plano de replay live-like.
