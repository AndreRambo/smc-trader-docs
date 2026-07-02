# Auditoria: ARQUITETURA_OFICIAL.md vs. Sistema Real (2026-07-02)

## Contexto

Revisão completa do arquivo `docs_geral/ARQUITETURA_OFICIAL.md` (1810 linhas), cruzando cada seção com o estado real do sistema — confirmado via leitura de código, `systemctl status`/`is-active`/`is-enabled` ao vivo na VPS, e o trabalho realizado nesta sessão (Fase S — harness de simulação causal, e o cutover "motor incremental como principal").

Objetivo: servir de base para o usuário decidir os próximos passos de correção do documento oficial.

---

## 1. Achados — DESATUALIZADO / ERRADO

### 1.1 Mecanismo de coleta descrito não é o que roda hoje (§2, §3.1, §4.8, §9, §10)

O documento descreve `run_b3.py`/`run_forex.py` (loop 60s, 6 timeframes por ativo) via `smc-b3-robot.service`/`smc-forex-robot.service` como os coletores em produção (§3.1 "Coleta → Persistencia", §4.8 tabela de infraestrutura, §9 métricas "Robôs de coleta | 2").

**Confirmado ao vivo (`systemctl`):**
- `smc-b3-robot.service` — **não está nem instalado** (`systemctl is-enabled` → `not-found`).
- `smc-forex-robot.service` — instalado mas **inativo** (`is-active` → `inactive`, `is-enabled` → `disabled`).
- O coletor que **realmente está ativo agora** é `smc-asset-collector@WINFUT.service` (`is-active` → `active`), parte de uma arquitetura de serviços mais nova sob `services/asset_collector/` (`cli.py`, `worker.py`, `candle_fetcher.py`, `mt5_gateway.py`, `closed_candle_detector.py`, `event_publisher.py`, `persistence.py`) — **nunca mencionada em nenhuma parte do documento**.
- Os terminais MT5 realmente ativos são `smc-mt5-b3-terminal.service` e `smc-mt5-fx-terminal.service` (ambos `active`/`enabled`), não os nomes usados no documento.

### 1.2 `technical_engine/smc_engine_v2/` descrito como módulo vivo — na verdade foi congelado em backup

§4.1 lista `smc_engine_v2/pipeline.py`, `fvg.py`, `order_blocks.py`, `structure.py`, `liquidity.py`, `bpr.py`, `swings.py`, `sessions.py`, `retracements.py`, `previous_high_low.py`, `persistence.py`, `config.py` como módulos ativos, com status "`STABLE_FROZEN_V2` — 164 testes". §10 repete: `technical_engine/smc_engine_v2/ → STABLE_FROZEN_V2 (164 testes)`.

**Realidade:** `technical_engine/smc_engine_v2/__init__.py` hoje contém apenas:
```python
raise ImportError(
    "smc_engine_v2 foi movido para backup (Fase M-1C). "
    "Use technical_engine.smc_engine_v3. "
    "Backup: /home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2"
)
```
O pacote foi deliberadamente movido para backup na Fase M-1C. Os módulos reais e ativos vivem em `technical_engine/smc_engine_v3/` — que o próprio documento já descreve corretamente em §4.10/§4.11. Há uma **contradição interna**: §4.1 descreve uma estrutura morta como se fosse a fonte canônica, enquanto §4.10 (mais recente, atualizado 2026-07-01) já trata `smc_engine_v3/` como o pipeline real em produção.

Nenhum arquivo no repositório importa de `technical_engine.smc_engine_v2.*` (confirmado via grep) — reforça que é código morto/stub.

### 1.3 Dispatcher de produção real não é mencionado

`services/candle_event_processor/dispatcher.py` — o módulo que **realmente** chama `run_smc_engine_v2_local()` (via `pipeline.py`) em produção hoje, disparado por um outbox MySQL (`technical_engine_candle_events`, claim transacional `SELECT...FOR UPDATE SKIP LOCKED` em `services/candle_event_processor/repository.py`) — **não aparece em lugar nenhum do documento**. O documento descreve uma chamada inline dentro do loop de `run_b3.py` (`TRIGGER 4 → run_v2_pipeline_and_sync`), que não é o caminho que está de fato implantado (`run_b3.py` não está nem rodando, conforme 1.1).

Achado adicional relevante já documentado nesta sessão: o `TIMEFRAME_DISPATCH_MAP` do dispatcher real **não inclui H1** (gap pré-existente) — diferente do que §3.1 descreve (`V2_TIMEFRAMES = {M2, M5, M15, H1, H4, D1}`, que parece se referir à lista do `run_b3.py`, não do dispatcher real).

### 1.4 Estado operacional atual não reflete a realidade

§9 ("Métricas") afirma "Serviços systemd | 11 ativos (4 scanner/notifier/forex + 4 MT5 + 2 bridge + 1 vps-monitor) + 2 robôs coleta".

**Estado real confirmado (`systemctl is-active`):**

| Serviço | Estado real |
|---|---|
| `smc-mt5-b3-terminal.service` | ativo |
| `smc-mt5-fx-terminal.service` | ativo |
| `smc-asset-collector@WINFUT.service` | ativo |
| `smc-b3-robot.service` | não instalado |
| `smc-forex-robot.service` | inativo |
| `smc-candle-event-processor.service` | inativo (pausado deliberadamente nesta sessão) |
| `smc-study-forward-shadow.timer` | inativo (apesar de "enabled") |
| `smc-opportunity-scanner.service` | inativo |
| `smc-opportunity-notifier.service` | inativo |
| `smc-sync-watcher.service` | não instalado |

Ou seja: candles novos **estão** entrando em `market_candles` via `smc-asset-collector@WINFUT`, mas **nada os processa** downstream — nem o pipeline batch antigo, nem qualquer engine de estudo/oportunidade. Isso é uma decisão operacional consciente do usuário nesta sessão, mas o documento não reflete esse estado nem o motivo.

### 1.5 Trabalho desta sessão totalmente ausente do documento

- **Fase S** (`tools/fase_s_simulacao/`): harness de simulação MTF causal e acelerado, motor incremental candle a candle, Elliott/Wyckoff causal, e cadeia real de decisão de oportunidade — validado sobre 3 meses reais de WINFUT (6 timeframes), zero erros em todas as sub-fases (S1-S4).
- **Cutover "motor incremental como principal"** (`technical_engine/smc_engine_v3/incremental/live/`): `mysql_repositories.py` (persistência nativa MySQL do schema `smc_v2_structures`/`smc_v2_structure_events`/`smc_v2_checkpoints`/`smc_v2_active_stream_versions`/`smc_v2_engine_runs`/`smc_v2_reconciliation` — schema que existia como arquivo de migração mas nunca tinha sido aplicado, aplicado nesta sessão), `cutover_store.py` (roteamento batch↔incremental persistido), `runner.py` (`IncrementalCandleRunner`: cold-start/warm-restart via checkpoint, dual-write legado, disparo da cadeia real de oportunidade no fechamento de M5) — validado de ponta a ponta contra MySQL real e 61.662 candles reais recarregados em `market_candles`.
- Documentado em `docs_geral/Sistema VPS/Plano/Plano Ativo/PLANO_MIGRACAO_MOTOR_CAUSAL_INCREMENTAL_PRODUCAO_UNIFICADA.md` (Seções 9 e 10), mas nunca propagado para o `ARQUITETURA_OFICIAL.md`.

---

## 2. O que está CORRETO e atualizado

§4.10 (SMC Engine V3 — Batch Canônico) e §4.11 (SMC Engine V3 Incremental Unified) estão **precisos e alinhados** com o que esta sessão confirmou — foram claramente atualizados em 2026-07-01, incluindo os avisos corretos sobre look-ahead estrutural no batch (`swings.py`/`fvg.py`), a correção opt-in (`causal_swings_fvg`), e o status real do motor incremental (validado, shadow-only, ainda não em produção — o que era verdade até esta sessão começar o cutover).

Não há evidência de problemas nas seções sobre: Site MaximusTrader (Laravel/React), App Android (Kotlin Multiplatform), Live-Replay V4, hierarquia MTF, guardrails, ou o histórico de decisões arquiteturais (§11) — nenhuma dessas áreas foi tocada ou investigada a fundo nesta sessão, então não há confirmação nem contradição encontrada.

---

## 3. Seções que precisam de correção (mapeamento)

| Seção | Problema | Ação sugerida |
|---|---|---|
| §2 (Camadas do Sistema, diagrama) | Não menciona `services/asset_collector/` nem `services/candle_event_processor/` | Adicionar caminho real de coleta/dispatch |
| §3.1 (Coleta → Persistência) | Descreve `run_b3.py`/`run_forex.py` como mecanismo ativo | Substituir pelo fluxo real: asset_collector → outbox → candle_event_processor |
| §4.1 (SMC Engine V2) | Descreve pacote morto como "STABLE_FROZEN_V2" ativo | Marcar como histórico/congelado em backup, remover da lista de módulos ativos |
| §4.8 (Infraestrutura) | `run_b3.py`/`run_forex.py` como componentes principais | Atualizar com os serviços reais e seus nomes systemd corretos |
| §4.10/§4.11 | — | Já corretas; adicionar nota sobre o cutover em andamento (esta sessão) |
| §9 (Métricas) | Contagem de serviços/robôs desatualizada | Atualizar com estado real (ativo/inativo) confirmado por `systemctl` |
| §10 (Estrutura de Diretórios) | `smc_engine_v2/` como módulo ativo | Corrigir para refletir que é stub morto; `smc_engine_v3/` é o real |
| Nova seção ou extensão de §4.11 | Ausência total do cutover desta sessão | Adicionar `incremental/live/` (mysql_repositories, cutover_store, runner) e Fase S |

---

## 4. Próximos passos (decisão do usuário)

Duas abordagens possíveis, a definir:
1. **Patch cirúrgico** — corrigir apenas as seções listadas acima, preservando o resto do documento intacto (recomendado, dado o tamanho do arquivo e que a maior parte permanece válida).
2. **Revisão mais ampla** — reestruturar o documento para refletir a arquitetura de serviços atual como eixo central, não apenas corrigir trechos pontuais.
