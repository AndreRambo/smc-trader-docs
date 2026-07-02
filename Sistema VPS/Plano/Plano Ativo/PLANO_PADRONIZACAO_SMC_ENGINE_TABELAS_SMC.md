# PLANO DE PADRONIZAÇÃO DO SMC ENGINE E TABELAS SMC

**Projeto:** SMC Trader System 7.0  
**Objetivo:** substituir nomes versionados como `smc_engine_v3`, `smc_engine_v2` e tabelas `*_smc_v2_*` por nomes oficiais estáveis, mantendo versionamento em metadados.  
**Data:** 2026-07-02  
**Status:** Plano executivo para migração segura

---

## 0. Registro de Progresso

### Fase 0 — CONCLUÍDA (2026-07-02): Inventário
Relatório: `Sistema VPS/Relatorios/RELATORIO_INVENTARIO_RENAME_SMC_ENGINE.md`. ~380 referências mapeadas em 5 padrões. Achado: `smc_v2_` é ambíguo (cobre tabelas legadas do batch E o schema nativo do motor incremental) — tratado separadamente na Fase 3.

### Fase "renomear pasta" — CONCLUÍDA (2026-07-02, fora da ordem original do plano, a pedido do usuário)
`technical_engine/smc_engine_v3/` → `technical_engine/smc_engine/` (rename direto, sem camada de adapter). 126 arquivos `.py` atualizados via sed (`smc_engine_v3` → `smc_engine`), incluindo o dispatcher real (`services/candle_event_processor/dispatcher.py`), `infra/sync_v2.py`, endpoints do dashboard. Validado: imports funcionam, suite de testes com zero regressão (1860 passando, mesmas 7 falhas pré-existentes). `technical_engine/smc_engine_v2/` (pacote morto, §4.1 do ARQUITETURA_OFICIAL.md) não foi tocado — continua como stub congelado.

### Fase 3 (Opção A — views de compatibilidade) — CONCLUÍDA (2026-07-02)
16 views criadas no MySQL real (`CREATE OR REPLACE VIEW ... AS SELECT * FROM ...`), tabelas físicas antigas preservadas sem alteração:
- 10 views batch/legado (`technical_engine_smc_v2_*_shadow` → `technical_engine_smc_*`, tabela 5.1 do plano)
- 6 views do motor incremental (`smc_v2_*` → `technical_engine_smc_*`, tabela 5.2 do plano)

Validado: contagem de linhas idêntica entre view e tabela física original (ex: `technical_engine_smc_fvg`=11070=`technical_engine_smc_v2_fvg_shadow`; `technical_engine_smc_structures`=8364=`smc_v2_structures`).

### Fase 4 — CONCLUÍDA (2026-07-02): persistence layer grava nos nomes oficiais
`technical_engine/smc_engine/persistence.py` (`persist_smc_engine_v2_run`) atualizado — as 10 tabelas legadas referenciadas por nome literal trocadas de `technical_engine_smc_v2_*_shadow` para `technical_engine_smc_*` (que gravam através da view na tabela física antiga, confirmado via write-through). Smoke test end-to-end: `persist_smc_engine_v2_run()` grava via nome novo → dado aparece na tabela física antiga → legível de volta pelo nome novo. Suite `tests/test_smc_engine_v2/`: 195 passando, mesmas 7 falhas pré-existentes, zero regressão.

**Escopo não coberto nesta fase** (deliberado, dado o volume — ~49 arquivos referenciam `technical_engine_smc_v2_*` fora de `persistence.py`): a maioria são scripts de pesquisa/auditoria em `tools/` e `technical_engine/data_driven_winfut/` (offline, não pipeline ao vivo) e `infra/sync_v2.py` (só leitura, dormente — confirmado nesta sessão que não é chamado pelo dispatcher real). Nenhum desses foi tocado — continuam funcionando exatamente como antes, lendo as tabelas físicas antigas diretamente (que permanecem intactas e não vão a lugar nenhum). O schema nativo do motor incremental (`smc_v2_structures` etc., usado por `technical_engine/smc_engine/incremental/live/`) também não foi migrado nesta fase — mysql_repositories.py continua escrevendo nos nomes `smc_v2_*` originais.

### Fase 4b — CONCLUÍDA (2026-07-02): schema nativo do incremental também migrado
`technical_engine/smc_engine/incremental/live/mysql_repositories.py` — as 6 tabelas nativas trocadas de `smc_v2_*` para `technical_engine_smc_*` (via `sed` com `\b` word-boundary, preservando a referência ao nome do arquivo de migração `20260629_smc_v2_incremental_schema.sql` em comentário, que não é nome de tabela). Smoke test: `EngineRunRepositoryMySQL.create()` grava pelo nome novo → aparece na tabela física antiga (`smc_v2_engine_runs`) → legível pelo nome novo. `tests/test_smc_engine_v2/test_mysql_repositories_live.py` + `test_cutover_store_live.py`: 9/9 passando.

**Pendente:** Fase 5+ (unificar batch/incremental sob mesmo namespace, endpoints REST, Laravel sync, docs, testes formais, cutover Opção B — tornar nomes novos físicos e nomes antigos virarem view reversa).

### Fase 8 — CONCLUÍDA parcialmente (2026-07-02): documentação
`ARQUITETURA_OFICIAL.md` atualizado: `smc_engine_v3` → `smc_engine` em todo o documento (títulos §4.10/§4.11, diagramas), §6.2 reescrita para listar tabela física antiga + nome oficial novo (view) lado a lado, §4.11-A atualizada refletindo a escrita nos nomes novos.

### Extensão do padrão para Live-Replay V4 — CONCLUÍDA (2026-07-02, a pedido do usuário)
Mesmo tratamento aplicado a `technical_engine/live_replay_v4/` → `technical_engine/live_replay/`:
- Rename direto da pasta + `tests/live_replay_v4/` → `tests/live_replay/` (necessário — os testes importam `tests.live_replay_v4.helpers` como pacote Python).
- 60 arquivos `.py` atualizados (`live_replay_v4` → `live_replay`).
- 18 views de compatibilidade criadas no MySQL (`winfut_lr_v4_*` → `winfut_lr_*`), mesmo padrão Opção A (tabela física antiga intacta).
- `technical_engine/live_replay/persistence/allowlist.py` (`V4_TABLE_ALLOWLIST`) e demais módulos de persistência atualizados para os nomes novos.
- **Bug encontrado e corrigido**: o `sed` global inicialmente mangled a referência ao arquivo de migração real `migrations/20260627_live_replay_v4_schema.py` (não renomeado) em 3 lugares — corrigido de volta.
- Validado: `tests/live_replay/`: 187/187 passando. Imports de `technical_engine.live_replay`, `technical_engine.smc_engine`, `services.candle_event_processor.dispatcher`, `infra.sync_v2` confirmados OK.

**Nota:** este trabalho no Live-Replay V4 não estava no escopo original deste plano (que cobre só o SMC Engine) — foi uma extensão do mesmo padrão a pedido do usuário, registrada aqui por falta de plano próprio.

---

## 1. Decisão Arquitetural

Padronizar o motor SMC com um nome único e estável:

```text
technical_engine/smc_engine/
```

Em vez de manter múltiplos diretórios versionados:

```text
technical_engine/smc_engine_v2/
technical_engine/smc_engine_v3/
technical_engine/smc_engine_v4/
```

Também padronizar as tabelas SMC removendo o sufixo de versão do nome físico:

```text
technical_engine_smc_*
```

Em vez de:

```text
technical_engine_smc_v2_*
smc_v2_*
```

A versão do motor, schema e modo de cálculo deve ficar em campos internos, não no nome do pacote ou da tabela.

---

## 2. Motivo da Mudança

Hoje existe confusão estrutural no projeto:

- O código ativo do SMC está em `technical_engine/smc_engine_v3/`.
- O antigo `technical_engine/smc_engine_v2/` não representa mais o motor real.
- As tabelas `technical_engine_smc_v2_*_shadow` mantêm nome legado, mesmo sendo alimentadas pelo pipeline atual.
- O motor incremental usa tabelas `smc_v2_*`, que confundem com o batch legado.
- Endpoints, adapters, relatórios e scripts ainda usam nomes mistos como `v2`, `v3`, `shadow`, `canonical`, `incremental`.

A mudança proposta elimina a confusão de nomenclatura e cria um contrato oficial estável para o restante do sistema.

---

## 3. Princípios da Migração

1. **Não versionar nomes principais**
   - Diretório oficial: `smc_engine/`
   - Tabelas oficiais: `technical_engine_smc_*`

2. **Versionar por metadados**
   - `engine_version`
   - `schema_version`
   - `calculation_mode`
   - `detection_definition`
   - `causal_mode`
   - `shadow_only`
   - `can_promote_trade`

3. **Não quebrar código existente de uma vez**
   - Criar camada de compatibilidade.
   - Migrar imports gradualmente.
   - Usar views ou aliases SQL temporários.
   - Remover nomes antigos somente após validação completa.

4. **Manter o guardrail shadow-only**
   - Remover `_shadow` do nome não significa liberar trade real.
   - O bloqueio operacional deve continuar em campo/configuração:
     - `shadow_only = 1`
     - `can_promote_trade = 0`

5. **Preservar rastreabilidade**
   - Toda execução deve manter `run_id`, hashes, versão do motor, versão do schema e modo de cálculo.

---

## 4. Padrão Final de Diretórios

### 4.1 Estrutura Recomendada

```text
technical_engine/
└── smc_engine/
    ├── __init__.py
    ├── pipeline.py
    ├── config.py
    ├── contracts.py
    ├── persistence.py
    │
    ├── batch/
    │   ├── __init__.py
    │   ├── fvg.py
    │   ├── order_blocks.py
    │   ├── structure.py
    │   ├── liquidity.py
    │   ├── bpr.py
    │   ├── swings.py
    │   ├── sessions.py
    │   ├── retracements.py
    │   └── previous_high_low.py
    │
    ├── incremental/
    │   ├── __init__.py
    │   ├── engine.py
    │   ├── components/
    │   ├── live/
    │   └── persistence/
    │
    ├── adapters/
    │   ├── __init__.py
    │   ├── legacy_v2_adapter.py
    │   ├── legacy_v3_adapter.py
    │   ├── study_gateway_adapter.py
    │   └── dashboard_adapter.py
    │
    └── tests/
```

### 4.2 Contrato Oficial

O restante do sistema deve importar somente de:

```python
from technical_engine.smc_engine.pipeline import run_smc_engine
```

E não mais diretamente de:

```python
from technical_engine.smc_engine_v3.pipeline import run_smc_engine_v2_local
```

---

## 5. Padrão Final de Tabelas

### 5.1 Renomeação Batch / Legado

| Nome atual | Nome novo recomendado |
|---|---|
| `technical_engine_smc_v2_runs_shadow` | `technical_engine_smc_runs` |
| `technical_engine_smc_v2_fvg_shadow` | `technical_engine_smc_fvg` |
| `technical_engine_smc_v2_order_blocks_shadow` | `technical_engine_smc_order_blocks` |
| `technical_engine_smc_v2_bos_choch_shadow` | `technical_engine_smc_bos_choch` |
| `technical_engine_smc_v2_liquidity_shadow` | `technical_engine_smc_liquidity` |
| `technical_engine_smc_v2_swings_shadow` | `technical_engine_smc_swings` |
| `technical_engine_smc_v2_sessions_shadow` | `technical_engine_smc_sessions` |
| `technical_engine_smc_v2_retracements_shadow` | `technical_engine_smc_retracements` |
| `technical_engine_smc_v2_previous_high_low_shadow` | `technical_engine_smc_previous_high_low` |
| `technical_engine_smc_v2_visual_overlays_shadow` | `technical_engine_smc_visual_overlays` |

### 5.2 Renomeação Incremental

| Nome atual | Nome novo recomendado |
|---|---|
| `smc_v2_engine_runs` | `technical_engine_smc_engine_runs` |
| `smc_v2_structures` | `technical_engine_smc_structures` |
| `smc_v2_structure_events` | `technical_engine_smc_structure_events` |
| `smc_v2_checkpoints` | `technical_engine_smc_checkpoints` |
| `smc_v2_active_stream_versions` | `technical_engine_smc_active_stream_versions` |
| `smc_v2_reconciliation` | `technical_engine_smc_reconciliation` |

---

## 6. Campos Obrigatórios de Versionamento

Todas as tabelas principais do motor SMC devem conter, quando aplicável:

```text
run_id
asset_id
symbol
timeframe
engine_version
schema_version
source_engine
calculation_mode
detection_definition
causal_mode
shadow_only
can_promote_trade
input_hash
output_hash
payload_hash
created_at
```

### 6.1 Valores Recomendados

```text
engine_version = "3.0.0" ou "incremental-1.0.0"
schema_version = "2026_07_smc_unified"
source_engine = "SMC_ENGINE"
calculation_mode = "BATCH" | "INCREMENTAL" | "LIVE" | "REPLAY"
detection_definition = "CANONICAL" | "LEGACY" | "CAUSAL"
causal_mode = 0 | 1
shadow_only = 1
can_promote_trade = 0
```

---

## 7. Fase 0 — Inventário Antes da Migração

### 7.1 Objetivo

Mapear todos os pontos que usam os nomes antigos antes de alterar qualquer código.

### 7.2 Checklist

- [ ] Buscar referências a `smc_engine_v2`.
- [ ] Buscar referências a `smc_engine_v3`.
- [ ] Buscar referências a `run_smc_engine_v2_local`.
- [ ] Buscar referências a `technical_engine_smc_v2_`.
- [ ] Buscar referências a `smc_v2_`.
- [ ] Buscar endpoints com `smc-engine-v2`.
- [ ] Buscar scripts de replay/backfill que escrevem ou leem tabelas antigas.
- [ ] Buscar queries SQL hardcoded.
- [ ] Buscar migrations antigas.
- [ ] Buscar referências no FastAPI.
- [ ] Buscar referências no Dashboard Shadow.
- [ ] Buscar referências no Laravel sync.
- [ ] Buscar referências no Opportunity Scanner.
- [ ] Buscar referências no Study Gateway.
- [ ] Buscar referências em relatórios e docs.

### 7.3 Comandos Sugeridos

```bash
grep -R "smc_engine_v2" -n .
grep -R "smc_engine_v3" -n .
grep -R "run_smc_engine_v2_local" -n .
grep -R "technical_engine_smc_v2_" -n .
grep -R "smc_v2_" -n .
grep -R "smc-engine-v2" -n .
```

### 7.4 Entregável

Criar relatório:

```text
Sistema VPS/Relatorios/RELATORIO_INVENTARIO_RENAME_SMC_ENGINE.md
```

---

## 8. Fase 1 — Criar Pacote Oficial `smc_engine/`

### 8.1 Objetivo

Criar a pasta oficial sem mover a lógica real de imediato.

### 8.2 Ação

Criar:

```text
technical_engine/smc_engine/
```

com arquivos mínimos:

```text
__init__.py
pipeline.py
config.py
contracts.py
persistence.py
```

### 8.3 Adapter Inicial

```python
# technical_engine/smc_engine/pipeline.py

from technical_engine.smc_engine_v3.pipeline import run_smc_engine_v2_local as _legacy_run

def run_smc_engine(*args, **kwargs):
    return _legacy_run(*args, **kwargs)
```

### 8.4 Regra

Nesta fase, o sistema continua usando o código real antigo por baixo, mas passa a ter uma entrada oficial nova.

### 8.5 Testes

- [ ] Importar `run_smc_engine`.
- [ ] Executar um run batch pequeno.
- [ ] Confirmar que o output é idêntico ao motor anterior.
- [ ] Confirmar que nenhum comportamento foi alterado.

---

## 9. Fase 2 — Migrar Imports Gradualmente

### 9.1 Objetivo

Trocar imports antigos pelo contrato oficial.

### 9.2 Antes

```python
from technical_engine.smc_engine_v3.pipeline import run_smc_engine_v2_local
```

### 9.3 Depois

```python
from technical_engine.smc_engine.pipeline import run_smc_engine
```

### 9.4 Prioridade de Migração

1. Testes.
2. Scripts de replay.
3. Study Gateway.
4. Opportunity Scanner.
5. Candle Event Processor.
6. Dashboard Shadow.
7. FastAPI.
8. Sync Laravel.
9. Scripts manuais em `tools/`.
10. Documentação.

### 9.5 Checklist

- [ ] Nenhum novo código deve importar `smc_engine_v2`.
- [ ] Nenhum novo código deve importar `smc_engine_v3`.
- [ ] Toda chamada nova deve usar `technical_engine.smc_engine`.
- [ ] Os adapters antigos devem continuar disponíveis durante a transição.

---

## 10. Fase 3 — Criar Nomes Novos de Tabelas com Compatibilidade

### 10.1 Estratégia Recomendada

Não renomear fisicamente tudo no primeiro passo. Primeiro criar **views de compatibilidade**.

Existem duas opções.

---

### Opção A — Tabelas antigas continuam físicas; nomes novos viram views

Útil para migração inicial sem risco.

```sql
CREATE OR REPLACE VIEW technical_engine_smc_fvg AS
SELECT * FROM technical_engine_smc_v2_fvg_shadow;

CREATE OR REPLACE VIEW technical_engine_smc_order_blocks AS
SELECT * FROM technical_engine_smc_v2_order_blocks_shadow;

CREATE OR REPLACE VIEW technical_engine_smc_runs AS
SELECT * FROM technical_engine_smc_v2_runs_shadow;
```

Vantagem:

- Baixo risco.
- Não altera escrita atual.
- Permite código novo ler nomes limpos.

Desvantagem:

- O legado continua sendo a origem física.

---

### Opção B — Tabelas novas são físicas; nomes antigos viram views

Útil na fase final.

```sql
RENAME TABLE technical_engine_smc_v2_fvg_shadow TO technical_engine_smc_fvg;

CREATE OR REPLACE VIEW technical_engine_smc_v2_fvg_shadow AS
SELECT * FROM technical_engine_smc_fvg;
```

Vantagem:

- Nome limpo vira oficial.
- Código antigo ainda funciona temporariamente.

Desvantagem:

- Exige validação mais cuidadosa de escrita, FK, índices e permissões.

---

## 11. Fase 4 — Migrar Persistence Layer

### 11.1 Objetivo

Fazer o motor gravar nos nomes oficiais.

### 11.2 Antes

```text
technical_engine_smc_v2_fvg_shadow
technical_engine_smc_v2_order_blocks_shadow
technical_engine_smc_v2_runs_shadow
```

### 11.3 Depois

```text
technical_engine_smc_fvg
technical_engine_smc_order_blocks
technical_engine_smc_runs
```

### 11.4 Regras

- [ ] Não usar strings hardcoded espalhadas.
- [ ] Centralizar nomes em um registry.
- [ ] Permitir modo compatibilidade via configuração.
- [ ] Adicionar `schema_version`.
- [ ] Adicionar `causal_mode` quando aplicável.
- [ ] Preservar `shadow_only`.
- [ ] Preservar `payload_hash`.

### 11.5 Exemplo de Registry

```python
SMC_TABLES = {
    "runs": "technical_engine_smc_runs",
    "fvg": "technical_engine_smc_fvg",
    "order_blocks": "technical_engine_smc_order_blocks",
    "bos_choch": "technical_engine_smc_bos_choch",
    "liquidity": "technical_engine_smc_liquidity",
    "swings": "technical_engine_smc_swings",
    "sessions": "technical_engine_smc_sessions",
    "retracements": "technical_engine_smc_retracements",
    "previous_high_low": "technical_engine_smc_previous_high_low",
    "visual_overlays": "technical_engine_smc_visual_overlays",
}
```

---

## 12. Fase 5 — Unificar Batch e Incremental Sob o Mesmo Namespace

### 12.1 Objetivo

Deixar batch e incremental como modos do mesmo motor oficial.

### 12.2 Estrutura

```text
technical_engine/smc_engine/
├── batch/
└── incremental/
```

### 12.3 Entrada Oficial

```python
def run_smc_engine(
    candles,
    symbol,
    timeframe,
    mode="BATCH",
    causal_mode=True,
    calculation_mode="REPLAY",
    **kwargs
):
    ...
```

### 12.4 Modos

| Modo | Descrição |
|---|---|
| `BATCH` | Processa janela de candles |
| `INCREMENTAL` | Processa candle a candle |
| `LIVE` | Fluxo de produção ao vivo |
| `REPLAY` | Fluxo de validação com dados históricos |
| `BACKFILL` | Reprocessamento histórico |

---

## 13. Fase 6 — Atualizar Endpoints e APIs Internas

### 13.1 Objetivo

Remover `v2` dos nomes públicos internos.

### 13.2 Antes

```text
/api/smc-engine-v2/state
```

### 13.3 Depois

```text
/api/smc-engine/state
```

### 13.4 Compatibilidade Temporária

Manter o endpoint antigo chamando o novo:

```text
/api/smc-engine-v2/state → /api/smc-engine/state
```

### 13.5 Checklist

- [ ] Atualizar FastAPI.
- [ ] Atualizar dashboard local.
- [ ] Atualizar frontend React.
- [ ] Atualizar documentação.
- [ ] Manter endpoint antigo com aviso de deprecated.
- [ ] Registrar data prevista para remoção.

---

## 14. Fase 7 — Atualizar Laravel Sync

### 14.1 Objetivo

Evitar colisão entre tabelas antigas `smc_v2_*` no Hostinger e o novo schema oficial do motor incremental.

### 14.2 Padrão Recomendado no Laravel

```text
sync_smc_runs
sync_smc_fvg
sync_smc_order_blocks
sync_smc_bos_choch
sync_smc_liquidity
sync_smc_swings
sync_smc_structures
sync_smc_structure_events
sync_smc_engine_runs
```

### 14.3 Checklist

- [ ] Mapear payload atual enviado pela VPS.
- [ ] Criar migrations novas.
- [ ] Criar camada de ingestão compatível.
- [ ] Manter leitura antiga temporariamente.
- [ ] Atualizar endpoints `/api/sync/*`.
- [ ] Atualizar HMAC sem mudar regra de segurança.
- [ ] Validar push scanner após mudança.

---

## 15. Fase 8 — Atualizar Documentação

### 15.1 Arquivos a Atualizar

- [ ] `ARQUITETURA_OFICIAL.md`
- [ ] `RELATORIO_ENGINES_INDICADORES_ZONAS_TABELAS.md`
- [ ] Documentação do Study Gateway.
- [ ] Documentação do Opportunity Scanner.
- [ ] Documentação de deploy.
- [ ] Runbook de replay.
- [ ] Runbook de cutover.
- [ ] Documentação de tabelas.
- [ ] Documentação Laravel sync.
- [ ] Documentação app Android, se mencionar nomes antigos.

### 15.2 Glossário Oficial

Adicionar seção:

```text
SMC Engine = nome oficial do motor.
Batch = modo de cálculo por janela.
Incremental = modo causal candle a candle.
Legacy V2 = nome histórico, deprecated.
Legacy V3 = nome histórico, deprecated.
technical_engine_smc_* = tabelas oficiais.
shadow_only = guardrail operacional, não sufixo obrigatório de tabela.
```

---

## 16. Fase 9 — Testes Obrigatórios

### 16.1 Testes de Import

- [ ] `from technical_engine.smc_engine.pipeline import run_smc_engine`
- [ ] Nenhum erro em imports antigos durante compatibilidade.
- [ ] Nenhum novo import direto de `smc_engine_v3`.

### 16.2 Testes de Output

- [ ] Rodar batch com dataset pequeno.
- [ ] Comparar output antigo vs novo.
- [ ] Validar FVG.
- [ ] Validar OB.
- [ ] Validar BOS/CHOCH.
- [ ] Validar Liquidez.
- [ ] Validar Swings.
- [ ] Validar Visual Overlays.

### 16.3 Testes de Persistência

- [ ] Gravação em tabelas novas.
- [ ] Leitura por views antigas.
- [ ] Leitura por nomes novos.
- [ ] Índices preservados.
- [ ] `run_id` preservado.
- [ ] `payload_hash` preservado.
- [ ] `shadow_only=1`.
- [ ] `can_promote_trade=0`.

### 16.4 Testes do Fluxo Completo

- [ ] CSV local → SMC Engine.
- [ ] SMC Engine → Study Gateway.
- [ ] Study Gateway → OperationalPlanV2.
- [ ] OperationalPlanV2 → Opportunity Scanner.
- [ ] Scanner → Laravel HMAC.
- [ ] Laravel → opportunities.
- [ ] Laravel → FCM.
- [ ] App Android recebe alerta.

### 16.5 Testes de Regressão

- [ ] Testes unitários.
- [ ] Testes de integração.
- [ ] Testes de replay.
- [ ] Testes de dashboard.
- [ ] Testes de sync.
- [ ] Testes de rollback.

---

## 17. Fase 10 — Cutover Oficial

### 17.1 Pré-condições

- [ ] Todos os testes passando.
- [ ] Tabelas novas populadas.
- [ ] Views antigas funcionando.
- [ ] Dashboard lendo nomes novos.
- [ ] Scanner lendo nomes novos.
- [ ] Study Gateway lendo nomes novos.
- [ ] Laravel sync validado.
- [ ] Backup do banco realizado.
- [ ] Relatório de equivalência gerado.

### 17.2 Ação de Cutover

- [ ] Definir `SMC_ENGINE_NAMESPACE=technical_engine.smc_engine`.
- [ ] Definir `SMC_TABLE_NAMESPACE=technical_engine_smc`.
- [ ] Ativar persistence oficial em tabelas novas.
- [ ] Marcar nomes antigos como deprecated.
- [ ] Atualizar documentação oficial.
- [ ] Rodar replay completo de validação.

### 17.3 Pós-Cutover

- [ ] Monitorar erros.
- [ ] Validar contagem de estruturas.
- [ ] Validar contagem de oportunidades.
- [ ] Validar alertas no Laravel.
- [ ] Validar app Android.
- [ ] Validar dashboards.
- [ ] Validar logs de auditoria.

---

## 18. Fase 11 — Remoção Gradual do Legado

### 18.1 O que manter temporariamente

- [ ] Views antigas.
- [ ] Adapters antigos.
- [ ] Endpoints antigos com aviso de deprecated.
- [ ] Relatórios antigos preservados como histórico.

### 18.2 O que remover após estabilização

- [ ] Imports diretos de `smc_engine_v2`.
- [ ] Imports diretos de `smc_engine_v3`.
- [ ] Chamadas a `run_smc_engine_v2_local`.
- [ ] Queries diretas para `technical_engine_smc_v2_*`.
- [ ] Queries diretas para `smc_v2_*`.
- [ ] Documentação que indique `v2` ou `v3` como motor oficial.

### 18.3 Critério para Remoção

Só remover legado depois de:

- [ ] Pelo menos um ciclo completo de replay validado.
- [ ] Nenhum erro no dashboard.
- [ ] Nenhum erro no scanner.
- [ ] Nenhum erro no sync Laravel.
- [ ] Nenhum erro no app/push.
- [ ] Backup validado.
- [ ] Plano de rollback testado.

---

## 19. Plano de Rollback

### 19.1 Rollback de Código

Manter os imports antigos disponíveis:

```python
from technical_engine.smc_engine_v3.pipeline import run_smc_engine_v2_local
```

Se necessário, reverter configuração:

```text
SMC_ENGINE_NAMESPACE=technical_engine.smc_engine_v3
```

### 19.2 Rollback de Banco

Se a Opção A for usada inicialmente, não há renomeação física crítica.

Se a Opção B for usada, manter views reversas:

```sql
CREATE OR REPLACE VIEW technical_engine_smc_v2_fvg_shadow AS
SELECT * FROM technical_engine_smc_fvg;
```

### 19.3 Rollback de API

Manter endpoint antigo:

```text
/api/smc-engine-v2/state
```

chamando internamente o novo:

```text
/api/smc-engine/state
```

### 19.4 Critérios para Acionar Rollback

- Falha de persistência.
- Divergência relevante entre output antigo e novo.
- Dashboard sem zonas.
- Scanner sem oportunidades.
- Laravel rejeitando payload HMAC.
- App sem receber alertas.
- Erro de import em serviço crítico.

---

## 20. Critérios de Aceite

A migração será considerada concluída quando:

- [ ] O pacote oficial `technical_engine/smc_engine/` existir e for usado pelo sistema.
- [ ] Nenhum código novo importar diretamente `smc_engine_v2` ou `smc_engine_v3`.
- [ ] As tabelas oficiais `technical_engine_smc_*` existirem.
- [ ] Os nomes antigos funcionarem apenas por compatibilidade temporária.
- [ ] O motor batch e o motor incremental estiverem sob o mesmo namespace.
- [ ] O versionamento estiver em colunas, não em nomes.
- [ ] O guardrail `shadow_only` continuar ativo.
- [ ] `can_promote_trade=0` continuar garantido.
- [ ] O replay CSV funcionar.
- [ ] O Study Gateway funcionar.
- [ ] O OperationalPlanV2 funcionar.
- [ ] O Opportunity Scanner funcionar.
- [ ] O sync Laravel funcionar.
- [ ] O app Android receber alerta normalmente.
- [ ] A documentação oficial estiver atualizada.
- [ ] O relatório final da migração for gerado.

---

## 21. Resultado Esperado

Após a migração, o projeto deixará de ter confusão de nomes como:

```text
smc_engine_v2
smc_engine_v3
technical_engine_smc_v2_*_shadow
smc_v2_*
```

E passará a ter um padrão profissional e estável:

```text
technical_engine/smc_engine/
technical_engine_smc_*
```

Com versionamento controlado por metadados:

```text
engine_version
schema_version
calculation_mode
detection_definition
causal_mode
shadow_only
can_promote_trade
```

---

## 22. Observação Importante

Esta mudança deve ser feita antes de uma nova fase forte de produção ao vivo, porque:

- A coleta ao vivo está pausada.
- O sistema roda atualmente sobre CSV local.
- O risco operacional é menor agora.
- O motor incremental causal ainda está em fase de cutover.
- A padronização reduz risco futuro de erro humano, import errado e query em tabela errada.

---

## 23. Próximo Passo Recomendado

Executar primeiro a **Fase 0 — Inventário**, sem alterar código:

```bash
grep -R "smc_engine_v2" -n .
grep -R "smc_engine_v3" -n .
grep -R "run_smc_engine_v2_local" -n .
grep -R "technical_engine_smc_v2_" -n .
grep -R "smc_v2_" -n .
grep -R "smc-engine-v2" -n .
```

Depois gerar o relatório:

```text
Sistema VPS/Relatorios/RELATORIO_INVENTARIO_RENAME_SMC_ENGINE.md
```

Somente após esse relatório iniciar a criação do namespace oficial:

```text
technical_engine/smc_engine/
```
