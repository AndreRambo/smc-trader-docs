# PLANO — Migração do Cálculo de Zonas/Indicadores para o Motor Causal Incremental (Candle a Candle) em Toda a Stack

---

**Criado:** 2026-07-01
**Branch de origem do trabalho preparatório:** `feature/smc-v3-causal-rebuild-real-data`
**Motivação direta (usuário):** *"no final quero que as zonas, indicadores seja calculado candle a candle em vez de janela, tipo o incremental... nossos coletores, backtest live, engine de oportunidades devem respeitar e gerar os mesmos comportamentos."*

---

## 1. Contexto e Motivação

O sistema hoje tem **dois motores SMC completos e paralelos**:

1. **Batch (`technical_engine/smc_engine_v3/pipeline.py` + `swings.py`/`fvg.py`/`structure.py`/`liquidity.py`/`order_blocks.py`/etc. na raiz do pacote)** — calcula tudo de uma vez sobre uma janela/DataFrame inteiro. **É o que roda em produção hoje**, confirmado em dois pontos: `infra/sync_v2.py` (robô B3, `run_b3.py`) e `services/candle_event_processor/dispatcher.py` (dispatcher genérico por timeframe e por símbolo — chamaria também qualquer ativo Forex futuro).
2. **Incremental (`technical_engine/smc_engine_v3/incremental/`)** — calcula candle a candle, causal por construção (nunca vê dados além do candle mais recente processado). **Shadow-only** — confirmado via grep, `on_candle_closed`/`SmcEngineV2Incremental` não é chamado em nenhum lugar de `infra/` ou `services/` hoje.

Isso significa: o comportamento que valida em backtest (se o backtest usar o motor causal/incremental) **não é o mesmo** que roda ao vivo (batch) — dois algoritmos diferentes, calibrações diferentes, e (como a auditoria de 2026-07-01 provou) contagens de zonas diferentes.

**Decisão do usuário:** unificar tudo em torno do motor incremental — coletores (B3 e futuro Forex), backtest/replay, e Engine de Oportunidades devem consumir a **mesma** lógica candle a candle, para que o que é validado em backtest seja **literalmente o mesmo código e comportamento** que roda ao vivo.

### 1.1 O que já foi validado (não precisa ser refeito)

- **Motor incremental em si:** validado nas 22 fases do plano `PLANO_MESTRE_AUDITORIA_CORRECAO_VALIDACAO_SMC_V3_COM_CSVS_REAIS_WINFUT_2021_2026.md` — 2.103 testes de regressão, 12.018 candles reais de WINFUT (2021-2026), prefix invariance, paridade batch/replay/chunk/resume, replay em 5 modos, `future_data_violations=0`. Decisão formal: `SMC_V3_APPROVED_WITH_ACCEPTED_LIMITATIONS`.
- **Persistência com checkpoint/resume:** `technical_engine/smc_engine_v3/incremental/persistence/` (`adapter.py`, `backfill.py`, `replay.py`, `repositories.py`, `schema.py`) já existe e foi validada com dados reais (restart idempotente, hash idêntico, detecção de conflito) — ver `ARQUITETURA_OFICIAL.md` §4.11.
- **Adapter de batch→incremental:** `technical_engine/smc_engine_v3/incremental/adapter_batch.py` (`BatchAdapter.process_batch`/`process_one`) já alimenta o motor incremental candle a candle a partir de uma lista de `CandleEnvelope` — é a base técnica que tanto o backfill/replay quanto um futuro coletor livewire vão reusar.
- **Paridade OB/Liquidity/BOS-CHOCH/FVG/Swings entre batch e incremental:** sessão de 2026-07-01 (commits `6f30992`, `d7a64c9`, `8606721`, `9481a3b`, `ab338ea`) — confirmou e corrigiu bugs de look-ahead no batch, e provou paridade EXATA (não aproximada) entre os dois motores em dados reais completos de WINFUT (1761==1761 OB, 1400==1400 Liquidity, 971==971 BOS/CHOCH). Isso reduz o risco de "o incremental vai produzir algo muito diferente do que os usuários já veem hoje" — na prática, quando configurado de forma equivalente, ele já produz o mesmo tipo de resultado, só que causalmente correto.

### 1.2 P1s conhecidos (já documentados no relatório final do plano R0-R22)

- `StructureLegV3` formal não implementado.
- Consumo cruzado de Liquidity (outros componentes lendo liquidez incremental) não finalizado.
- Decisão de produto pendente sobre `require_structure_break` no `ObComponent` (default hoje é `False`).
- Escala M1 (alta frequência) não validada integralmente.
- `retracements`: diferença de unidade de saída (por candle vs. por evento) — usuário já decidiu: seguir o formato do incremental (por evento).

Esses P1s **não bloqueiam** o início da migração, mas devem ser resolvidos antes do cutover final de cada consumidor que dependa deles.

### 1.3 Descoberta crítica (2026-07-02): escopo real do que alimenta a Engine de Oportunidades

Levantamento completo (agente de exploração, só investigação, nenhum arquivo alterado) sobre TUDO que a Engine de Oportunidades (`technical_engine/opportunity_scanner/`) consome hoje — não apenas zonas SMC. Muda a moldura do escopo desta migração.

**Achado arquitetural mais importante: existem DOIS caminhos de produção paralelos, ambos ativos, que não são equivalentes:**

| Serviço (systemd) | Arquivo | O que faz |
|---|---|---|
| `smc-candle-event-processor.service` | `services/candle_event_processor/dispatcher.py` | Por candle fechado: roda SMC **batch** (`run_smc_engine_v2_local`, não o incremental), monta plano **em memória**, chama o scanner **inline**, sincroniza direto pro Laravel. Elliott/Wyckoff são calculados mas **descartados** (não entram no envelope). Não grava em `operational_plans_shadow`. |
| `smc-study-forward-shadow.service` (timer, 15min) | `technical_engine/study_gateway/forward_runner.py` | Lê SMC **persisted** (não recalcula), monta Elliott+Wyckoff, **persiste** `OperationalPlanV2` em `technical_engine_operational_plans_shadow`. |
| `smc-opportunity-scanner.service` | `technical_engine/opportunity_scanner/scanner.py` | **Este é o "Engine de Oportunidades" mapeado na Fase M2.** Só enxerga o mundo através do plano gravado pelo `forward_runner` acima. |

Ou seja: a Engine de Oportunidades só vê o que o `forward_runner` grava — e o `dispatcher.py` (que roda a cada candle, caminho mais "ao vivo") tem sua PRÓPRIA lógica paralela, com SMC batch e Elliott/Wyckoff descartados, emitindo alertas por uma via diferente (direto pro Laravel). **Antes de desenhar a Fase M3/M5 completas, é preciso esclarecer com o dono do sistema qual desses dois caminhos é a fonte de verdade** — migrar só um dos dois deixaria o outro desatualizado.

**O que a Engine de Oportunidades (pacote `opportunity_scanner/`) consome DIRETAMENTE:** apenas OHLC de `market_candles` + ATR recalculado localmente, e os campos JÁ DERIVADOS do `OperationalPlanV2` (direção, entrada, stop, tps, rr, confiança, zonas). Não lê RSI/EMA/MACD/Bollinger/VWAP em nenhum ponto.

**Tabela-resumo por indicador/engine:**

| Engine/Indicador | Batch ou Incremental | Consumido HOJE pela Engine de Oportunidades? |
|---|---|---|
| SMC zonas (FVG/OB/BOS-CHOCH/Liquidity/Swings/BPR/Retracements) | Incremental já validado, mas **produção ainda usa a versão batch** (`dispatcher.py`) | Sim, via `operational_plans_shadow` |
| RSI clássico (`infra/indicators.py`) | Batch, persiste em `market_candles.rsi` | **Não** (loader do scanner ignora a coluna) |
| RSI/EMA/ATR (`services/asset_collector/`) | Causal mas recomputado do zero por candle (não O(1)) | **Não** (sem consumidor downstream) |
| RSI14 causal (`live_replay_v4/indicators/rsi.py`) | Incremental verdadeiro (O(1), snapshot/restore) | **Não** — módulo `live_replay_v4` totalmente isolado da produção |
| **"Novo RSI" / RSI-Heikin Ashi** (`live_replay_v4/indicators/rsi_heikin_ashi.py`, plano V4_04) | Causal mas NÃO O(1) (recomputa buffer inteiro a cada candle); nem está no registry padrão do V4 | **Não** |
| EMA20/EMA200 (V4) | Incremental verdadeiro (O(1)) | **Não** |
| SMA20 (viés HTF alternativo) | Batch | **Inativo por padrão** (`htf_bias_source="structure"`, não `"sma"`) |
| MACD / Bollinger / VWAP | Sem implementação de cálculo real | Não |
| **Wyckoff** | Batch, recomputado a cada ciclo de 15min (janela completa) | **Sim, indiretamente** — gate obrigatório para `readiness=PRONTO`, peso **0.25** na confluência (o maior peso) → influencia `direction`/`entrada`/`stop` do plano |
| **Elliott Wave** | Batch, recomputado a cada ciclo de 15min | **Sim, indiretamente** — mesmo mecanismo, peso **0.15** na confluência |
| Contextual Market Profile | Batch, com persistência própria | **Não** — `forward_runner` nunca passa esse dado ao envelope (parâmetro fica `None`) |
| Zone Memory Profile | Não investigado a fundo | **Não** — mesmo padrão do Contextual |

**Implicações diretas para o plano de migração:**
1. **Elliott e Wyckoff são o maior "batch residual" real** no caminho que decide o plano — pesam mais na confluência (0.25+0.15=0.40) do que se imaginava, e são recalculados do zero a cada 15 minutos, não candle a candle. Se o objetivo final é "tudo causal candle a candle", esses dois entram no escopo da migração tanto quanto as zonas SMC já feitas.
2. RSI/EMA causais (V4) já existem prontos (parte já é O(1)) mas são uma **integração nova** a construir, não uma migração — hoje não têm nenhum ponto de conexão com o `TechnicalTruthEnvelopeV2`/Opportunity Scanner.
3. Contextual Market Profile e Zone Memory Profile têm parâmetros dedicados no código mas estão **inertes** — vale confirmar com o usuário se isso é intencional ou uma lacuna esquecida antes de decidir se entram no escopo.

---

## 2. Objetivo

Fazer com que **coletores (B3 e futuro Forex), backtest/replay/live-simulation, e Engine de Oportunidades** consumam todos o **mesmo motor causal incremental**, processando candle a candle, com o **mesmo comportamento** entre ambiente de estudo/backtest e produção ao vivo. O motor batch deixa de ser o caminho de decisão de produção — passa a ser, na melhor das hipóteses, uma ferramenta de estudo/overlay visual (decisão a confirmar na Fase 6).

---

## 3. Fases

### Fase M1 — Fechar a última divergência estrutural (retracements)
- Implementar a view/formato de retracements do batch no padrão "por evento estrutural" (mesma unidade do incremental), OU expor diretamente os registros do `RetracementsComponent` como a fonte de verdade — decisão técnica dentro da fase, já autorizada pelo usuário a seguir o formato do incremental.
- Atualizar `shared_config.STRUCTURAL_DIVERGENCES` (deve ficar vazio ao final desta fase).
- Testes de paridade real (mesmo padrão das fases anteriores: comparação contra dados reais de WINFUT).

### Fase M2 — Auditoria dos consumidores atuais do shadow batch
- Mapear TODOS os consumidores de `technical_engine_smc_v2_*_shadow` (as 10 tabelas mencionadas na auditoria): dashboard (`dashboard_shadow_backend`), `TechnicalTruthEnvelopeV2`, Opportunity Scanner, qualquer relatório/ferramenta que leia essas tabelas diretamente.
- Para cada consumidor: documentar o schema/contrato que ele espera hoje (colunas, tipos, semântica de índice) vs. o que a persistência incremental (`technical_engine/smc_engine_v3/incremental/persistence/schema.py`) já produz — mapear gaps de schema/contrato antes de trocar a fonte.
- Esta fase é só levantamento — nenhuma mudança de código de produção.

### Fase M3 — Coletor B3 ao vivo → motor incremental
- Trocar, em `infra/sync_v2.py` (chamado por `run_b3.py`), a chamada de `run_smc_engine_v2_local` (batch) para `SmcEngineV2Incremental.on_candle_closed()` via o padrão já validado em `BatchAdapter`/persistência incremental — mas agora em modo **streaming ao vivo** (um candle novo por vez, não um backfill), com restart-safe state loading (usar o mecanismo de checkpoint/resume já validado).
- Rodar em **paralelo/shadow** com o batch por um período de validação (não substituir de imediato) — persistir ambos, comparar diariamente.
- Critério de saída da fase: N dias (a definir com o usuário) de paralelismo sem divergência inexplicada, sem erro de sequência/gap, sem regressão de latência.

### Fase M4 — Backtest/Replay oficial → motor incremental
- `tools/smc_v3_validation/run_holdout_validation.py` e ferramentas semelhantes já usam `run_smc_engine_v3`/`BatchAdapter` (candle a candle) — formalizar isso como a **ferramenta oficial de backtest**, substituindo qualquer fluxo de backtest que ainda dependa do pipeline batch (ex.: `tools/run_hit_rates_replay.py`, `tools/run_controlled_opportunity_e2e.py` — auditar quais desses usam batch vs. incremental).
- Objetivo concreto: um backtest rodado sobre um CSV histórico deve produzir **exatamente** as mesmas zonas/timing que o motor teria produzido ao vivo, candle a candle — sem overfitting de "olhar para trás" que o batch permite estruturalmente.

### Fase M5 — Engine de Oportunidades → consumir saída incremental
- Migrar o Opportunity Scanner/Engine para ler as estruturas/eventos emitidos pelo motor incremental (via a persistência já validada), em vez do shadow batch atual.
- Esta é a fase de maior risco de regressão de sinal — exige validação lado a lado (mesma janela de tempo, mesmo ativo) comparando oportunidades geradas pelo caminho antigo (batch) vs. novo (incremental) antes de qualquer decisão de trocar o que efetivamente gera alertas/trades reais.

### Fase M6 — Forex (quando/se a coleta existir)
- Nenhum caminho batch deve ser construído para Forex — a implementação nasce direto sobre o motor incremental, usando a mesma infraestrutura de coletor construída na Fase M3 (generalizada por ativo/timeframe via `SmcSharedEngineConfig.for_asset_timeframe`, já preparado para isso).

### Fase M7 — Decisão sobre o motor batch
- Após M3-M5 validadas e em produção real: decidir explicitamente (com o usuário) o destino do pipeline batch — aposentar completamente, ou manter apenas como ferramenta de overlay visual/estudo histórico (sem influência em decisão de trade/sinal).
- Se mantido, documentar explicitamente como `SHADOW_ONLY_NON_CAUSAL` / `STUDY_ONLY` em `ARQUITETURA_OFICIAL.md`.

### Fase M8 — Cutover final + monitoramento
- Data de corte formal, comunicada e documentada.
- Monitoramento pós-cutover (dashboards de sanidade, alarmes de `future_data_violations`, gap/duplicate detection já existentes no motor incremental).
- Plano de rollback documentado (voltar para o batch em caso de problema crítico) até que a confiança em produção esteja estabelecida.

---

## 4. Riscos e Mitigações

| Risco | Mitigação |
|---|---|
| Motor incremental nunca rodou sob carga real contínua 24/7 (só em backfill/replay controlado) | Fase M3 roda em paralelo/shadow antes de qualquer cutover — mede latência e estabilidade reais antes de decidir |
| P1s conhecidos (StructureLegV3, consumo cruzado de Liquidity, `require_structure_break`, escala M1) podem afetar consumidores específicos | Cada fase (M3-M5) deve revisitar explicitamente quais P1s afetam aquele consumidor antes do cutover daquele consumidor |
| Consumidores do shadow batch (dashboard, Opportunity Scanner) podem depender de colunas/semântica que a persistência incremental não replica 1:1 | Fase M2 (auditoria) é dedicada exatamente a isso, antes de qualquer troca de código |
| Engine de Oportunidades gera sinais reais — uma regressão aqui tem impacto direto | Fase M5 exige validação lado a lado explícita antes de qualquer decisão de trocar a fonte real |
| Migração de coletor ao vivo (M3) tem risco de gap/downtime na coleta | Rodar em paralelo (shadow) antes de desligar o batch — nunca cutover direto sem período de validação |

---

## 5. Pontos de Decisão do Usuário (não avançar sem confirmação explícita)

1. Fim da Fase M1: formato final de `retracements` (confirmar que "por evento" é aceitável para os consumidores existentes, ou se algum consumidor específico ainda precisa do formato "por candle" como view derivada).
2. Fim da Fase M2: aprovação do mapeamento de gaps de schema antes de M3 começar a escrever código.
3. Fim da Fase M3: critério objetivo de quantos dias de shadow-run sem divergência é suficiente para prosseguir.
4. Fim da Fase M5: aprovação explícita antes de qualquer sinal real passar a vir do caminho incremental.
5. Fase M7: decisão final sobre o destino do pipeline batch.

---

## 6. Fora de Escopo (por ora)

- Reescrever a UI/dashboard além do necessário para consumir o novo schema.
- Qualquer mudança de calibração/threshold de sinal motivada por "o incremental encontrou mais/menos zonas" — isso é uma decisão de produto separada, não parte da migração de arquitetura em si (mas será um efeito colateral observável a ser revisado quando ocorrer).

---

## 7. Registro de Progresso

### Fase M1 — CONCLUÍDA (2026-07-01)

Divergência de `retracements` resolvida via `retracements.calculate_retracements_incremental_parity()` — wrapper direto que roda o `RetracementsComponent` real candle a candle (não uma reimplementação vetorizada, dado que o algoritmo é uma máquina de estados anchor/opposite/dealing-range). Paridade garantida por construção, confirmada em dados reais: **223 == 223** estruturas numa amostra EURUSD de 500 candles, **5212 == 5212** no stream completo de WINFUT H1 (12.018 candles).

Wired em `pipeline.py` via `retracements_algorithm="legacy"` (default, preserva 100% o comportamento de produção atual) | `"incremental_parity"` (novo, popula `result["retracements_events"]` em vez de `result["retracements"]`, formato por evento estrutural).

`shared_config.STRUCTURAL_DIVERGENCES` agora está **vazio** — as 6 divergências originalmente identificadas entre batch e incremental (order_blocks, bpr_size_filter, liquidity_erl_irl, liquidity_clustering_algorithm, bos_choch_algorithm, retracements) estão todas resolvidas com paridade exata verificada em dados reais.

4 testes novos (`tests/test_smc_engine_v2/test_retracements_incremental_parity.py`). Suíte completa: 2286 passando, mesmas 8 falhas pré-existentes não relacionadas. Commit: `42128b1`.

**Relatório completo:** `docs_geral/Sistema VPS/Relatorios/RELATORIO_AUDITORIA_LOOKAHEAD_PIPELINE_BATCH_PRODUCAO.md` §9.9-9.10.

**Ponto de decisão pendente (item 1 da seção 5):** usuário precisa confirmar que o formato por evento é aceitável antes de prosseguir para a Fase M2.

### Fase M2 — CONCLUÍDA (2026-07-01) — Auditoria dos consumidores reais do shadow batch

Levantamento (nenhum código alterado). Achado central: **as 10 tabelas `technical_engine_smc_v2_*_shadow` não são lidas diretamente pela maioria dos consumidores** — existe uma cadeia de tradução já estabelecida.

#### 1. O loader real (peça que faltava)

`technical_engine/smc_engine_v3/persistence.py:load_latest_smc_engine_v2_state(conn, symbol, timeframe, limit=500)` é a ÚNICA função que faz `SELECT` real nas 10 tabelas e monta o dict no formato de `run_smc_engine_v2_local` (chaves: `source`, `engine_version`, `diagnostics`, `fvg`, `swings`, `order_blocks`, `bos_choch`, `liquidity`, `previous_high_low`, `sessions`, `retracements`, `candles`, `visual_overlays`). Detalhes:
- Pega o `run_id` mais recente por `(symbol, timeframe)` com alias bidirecional de timeframe (`M5`↔`5min`).
- Filtra `fvg`/`swings`/`order_blocks`/`bos_choch`/`liquidity` por `ref_index >= min_ref` (paginação em memória, não em SQL); `previous_high_low`/`sessions`/`retracements` carregam o run inteiro sem filtro.
- **Nunca lê `technical_engine_smc_v2_visual_overlays_shadow`** (a 10ª tabela) — regenera os overlays em runtime a partir dos outros 9 componentes + candles de `market_candles`. Essa tabela é escrita mas nunca lida por nenhum consumidor encontrado — candidata a não precisar de equivalente na Fase M3.

#### 2. Cadeia real até o Opportunity Scanner

```
smc_v2_*_shadow (10 tabelas)
  → load_latest_smc_engine_v2_state()           [persistence.py]
  → ForwardStudyShadowRunner._process_asset()    [study_gateway/forward_runner.py]
  → build_technical_truth_envelope_v2()          [study_gateway/smc_v2_adapter.py] → TechnicalTruthEnvelopeV2 (EM MEMÓRIA, não persistido diretamente)
  → build_operational_plan()                     [study_gateway/risk_management_v2.py]
  → save_operational_plan_shadow()                [study_gateway/operational_plan_persistence.py] → INSERT technical_engine_operational_plans_shadow
  → load_latest_plan_for_scanner()                [opportunity_scanner/loader.py] — Opportunity Scanner lê SÓ esta tabela, nunca as 10 originais
```

`build_operational_plan` consome do envelope apenas: `confluence`, `readiness`, `symbol`, `base_timeframe`, `available_at`, `technical_truth_hash`, `primary_zones`, `secondary_zones`, `structure_events`, `liquidity_levels`, `contextual`. **Não usa swings nem retracements — o envelope nem carrega essas chaves.**

#### 3. Achado crítico sobre `retracements` (valida a decisão da Fase M1)

`TechnicalTruthEnvelopeV2`/Study Gateway V2/Opportunity Scanner **não consomem retracements de forma alguma** — nenhuma menção em `smc_v2_adapter.py`, `risk_management_v2.py`, `confluence_v2.py`, `forward_runner.py`. **Nenhum consumidor de decisão/trade depende do formato de retracements.**

Os únicos consumidores reais do formato "por candle" são de **exibição**, não de decisão:
- `dashboard_shadow/backend/app/api/multilayer.py` e `smc_engine_v2_state.py` — repassam a lista crua ao frontend, sem lógica de negócio sobre os campos.
- `technical_engine/study_pipeline_shadow/builder.py` (`STUDY_PIPELINE_SHADOW_V1`, geração mais antiga, ainda ativa mas distinta do Study Gateway V2) — passthrough dos 10 primeiros registros, sem cálculo condicionado.

**Conclusão:** a mudança de formato do motor incremental (por evento) feita na Fase M1 **não quebra nenhum consumidor de decisão real** — o único impacto seria na camada de exibição (dashboard, `STUDY_PIPELINE_SHADOW_V1`), que precisaria de adaptação SE/QUANDO passar a consumir o novo formato. Isso confirma que a decisão da Fase M1 foi segura.

#### 4. Dashboard — mapa de endpoints

- `smc_engine_v2_state.py` (`GET /smc-engine-v2/state`) — chama `load_latest_smc_engine_v2_state` diretamente (ou recalcula via `pipeline.run_smc_engine_v2_local` se o candle mais recente não bate com o persisted). **Inconsistência pré-existente encontrada** (não relacionada a esta migração): no caminho persisted, `diagnostics.current_retracement_pct`/`deepest_retracement_pct` não são recalculados (só existem no caminho "computed").
- `multilayer.py`, `study_pipeline_shadow.py` — não fazem SQL direto, reusam a função Python `smc_engine_v2_state(...)`.
- `smc_engine_v2_fvg.py`/`smc_engine_v2_swings.py`/`smc_engine_v2_ob.py` — recalculam em runtime sobre `market_candles`, desacoplados do pipeline persistido (endpoints ad-hoc do dashboard, não dependem das tabelas shadow).
- `reference_smc.py` — lê tabelas `technical_engine_smc_reference_*_shadow` (conjunto DIFERENTE, marcado `REFERENCE_ONLY_NOT_OFFICIAL_ENGINE`) — fora do escopo desta migração.

#### 5. Schema da persistência incremental (o que já existe hoje)

`technical_engine/smc_engine_v3/incremental/persistence/schema.py` — 6 tabelas, desenho genérico (diferente do batch, que usa 9 tabelas físicas separadas por componente):
- `smc_v2_engine_runs`, `smc_v2_structures` (genérica, todos os tipos via `structure_type`/`payload_json`), `smc_v2_structure_events`, `smc_v2_checkpoints`, `smc_v2_active_stream_versions`, `smc_v2_reconciliation`.
- **Gap identificado para a Fase M3:** não há hoje nenhum adaptador ligando `smc_v2_structures`/`smc_v2_structure_events` ao formato que `load_latest_smc_engine_v2_state` produz — esse adaptador precisa ser construído na Fase M3, não existe ainda. Também não há equivalente incremental para `previous_high_low`/`sessions` (componentes existem no motor incremental, mas a comparação de schema exata desses dois não foi feita nesta rodada — candidato a revisão dentro de M3).

**Relatório completo (transcript do agente de exploração):** anexado ao histórico da sessão; achados replicados nesta seção como registro permanente.

**Ponto de decisão (item 2 da seção 5):** aprovar o mapeamento acima antes de M3 começar a escrever código — em particular, confirmar que construir o adaptador `smc_v2_structures`/`smc_v2_structure_events` → formato `load_latest_smc_engine_v2_state` é o caminho certo para a Fase M3 (em vez de, por exemplo, reescrever os consumidores para ler o schema incremental diretamente).

### Correção de premissa — ambiente é a VPS de produção ao vivo (2026-07-01)

Durante o início da Fase M3, foi confirmado que este ambiente **é a própria VPS de produção**: `smc-asset-collector@WINFUT.service`, `smc-mt5-b3-terminal.service` (B3/XP) e **`smc-mt5-fx-terminal.service` (Forex/Exness) já rodam ao vivo há mais de uma semana**. A Fase M6 do plano original ("Forex quando/se a coleta existir") estava desatualizada — **Forex já existe e roda em paralelo ao B3 hoje**, não é hipótese futura. Nenhuma mudança em serviço ao vivo foi feita sem confirmação explícita do usuário (regra de segurança já em vigor).

Também foi confirmado que já existe uma implementação prévia de shadow-run (R4 — `SHADOW_RUNTIME_INTEGRATED`, `technical_engine/smc_engine_v3/incremental/shadow_runtime.py`), hoje desligada (`SMC_V2_INCREMENTAL_SHADOW` não setado), escrevendo num **SQLite local isolado** (`runtime/smc_v2_incremental_shadow.db`, 33MB de um teste anterior) — abordagem que **não permite validação visual no site**, pois o site só lê as tabelas MySQL oficiais/espelhadas. Decisão do usuário: abandonar essa abordagem de arquivo isolado; a validação será feita **visualmente no site (MaximusTrader)**, mantendo o ambiente de cálculo local sem gráficos.

### Mapeamento MaximusTrader (site) — só documentação, nenhuma mudança feita lá

Cadeia confirmada (local → Hostinger → site):
```
Contabo (local): 9 tabelas technical_engine_smc_v2_*_shadow (formato "uma linha por ref_index")
  → sync_v2_shadow_tables() [infra/sync_v2.py] → POST /sync/tables/push
  → Hostinger (MaximusTrader/Laravel): smc_v2_fvg, smc_v2_order_blocks, smc_v2_bos_choch,
    smc_v2_liquidity, smc_v2_swings, smc_v2_sessions, smc_v2_retracements,
    smc_v2_previous_high_low, smc_v2_bpr, smc_v2_runs
  → SmcZoneService (backend/app/Services/SmcZoneService.php) → API /zones/{ticker}
  → Frontend (ReplayPage.tsx + CandlestickChart.tsx, lightweight-charts) renderiza
```

**Achado central — o Replay do site já existe e é funcional, mas NÃO é causal na exibição:**
- Existe hoje uma feature de Replay completa: `frontend/src/pages/ReplayPage.tsx`, `useReplayData.ts`, `ReplayControls.tsx` (play/pause/step/seek/velocidade), operando sobre `CandlestickChart.tsx` (lightweight-charts) — não precisa ser construída do zero.
- **Bug de look-ahead na própria visualização, independente do motor de cálculo:** ao avançar o replay, uma zona que só será mitigada no candle 200 já aparece **desde o candle 10 (quando nasce) com a cor final de "mitigada"** e a caixa já desenhada até o candle 200 — porque `isMitigated(z)` e `getTimeRangeForZone`/`resolveTimeRange` usam o estado FINAL vindo do batch, sem nunca clampar ao `currentIndex`/tempo atual do replay (`CandlestickChart.tsx`, `chart/smc/normalizers/*.ts`). Ou seja: o site "espia o futuro" na hora de desenhar, mesmo que o motor de cálculo por trás seja 100% causal. Esse é um segundo look-ahead, na camada de apresentação, distinto do que corrigimos no motor batch nesta sessão.
- As colunas de lifecycle (`origin_at`, `confirmed_at`, `available_at`, `mitigated_at`, `swept_at`, `broken_at`, `end_at`) **já existem no schema** das tabelas Laravel e **já trafegam até o frontend** (parte como campo de topo, parte dentro de `payload: Record<string, any>` sem tipagem forte) — não faltam dados, falta lógica de revelação sincronizada ao tempo do replay.
- Não existe um enum de estado (`DETECTED`/`CONFIRMED`/`AVAILABLE`/`MITIGATED`/`SWEPT`/`BROKEN`) por zona — o estado é sempre inferido de "qual timestamp está preenchido".
- **Atenção operacional:** o endpoint `/zones/{ticker}` só lê as tabelas `smc_v2_*` novas se a env var `SMC_USE_NEW_TABLES=true` estiver setada no Hostinger — hoje o default é `false` (lê de uma tabela unificada antiga, `sync_zones`). Precisa ser confirmado/ativado quando o motor incremental for ligado — mudança do lado do MaximusTrader, fora do escopo "só local" desta fase.

**Conclusão para o lado local (este repositório) da Fase M3:** para o replay do site eventualmente mostrar o "encontrar → verificar → marcar" corretamente, o motor incremental local precisa persistir (nas tabelas `technical_engine_smc_v2_*_shadow` já existentes, reaproveitando o schema) os MESMOS timestamps de lifecycle que essas colunas esperam — o que ele já produz nativamente (`origin_at`/`confirmed_at`/`available_at`/`mitigated_at` fazem parte do contrato `StructureEmission`/`StructureEventEmission`). O trabalho de corrigir a lógica de revelação causal no frontend (o bug de look-ahead na visualização) fica fora do escopo deste repositório — é um achado a ser tratado no projeto MaximusTrader, quando o usuário decidir.

### Fase M3.1 — CONCLUÍDA (2026-07-01): adaptador de formato (só código, zero conexão)

Criado `technical_engine/smc_engine_v3/incremental/legacy_shadow_adapter.py` — função **pura** (`convert_incremental_to_legacy_shadow_shape`), sem abrir nenhuma conexão de banco, sem tocar em nenhum serviço/flag ao vivo. Converte a saída do motor incremental (`StructureEmission`/`StructureEventEmission`) para o MESMO shape de dict que `pipeline.py` já produz (`_swing_to_dict`/`_fvg_to_dict`/`_ob_to_dict`/`_bos_choch_to_dict`/`_liquidity_to_dict`/`_bpr_to_dict`) — o contrato exato que `persist_smc_engine_v2_run()` já sabe gravar nas 9 tabelas `technical_engine_smc_v2_*_shadow`, as mesmas que o MaximusTrader já espelha e renderiza.

**Cobertura:** 6 componentes por evento (swings, fvg, order_blocks, bos_choch, liquidity, bpr). **Excluídos deliberadamente:**
- `previous_high_low`/`sessions`/`retracements` — mesmo motivo de unidade de contagem já resolvido para retracements na Fase M1 (série contínua por candle no batch vs. eventos discretos no incremental).
- `IFVG_BULLISH`/`IFVG_BEARISH` — sem NENHUM conceito equivalente em `persistence.py`/`pipeline.py` do batch.

**Verificação (sem persistir nada, só a transformação em memória):** contagens exatas confirmadas contra o motor incremental real — amostra EURUSD 500 candles e stream completo de WINFUT H1 (12.018 candles): swings=1519, fvg=2966, order_blocks=1761, bos_choch=971, liquidity=1406, bpr=1370 — batendo com os números já validados nesta sessão para o motor incremental puro.

4 testes novos (`tests/test_smc_engine_v2/test_legacy_shadow_adapter.py`): paridade de contagem, contrato exato de chaves de saída (comparado campo a campo com as funções `_X_to_dict` do batch), sanidade de `ref_index`, e exclusões de escopo documentadas como asserções. Suíte completa: 186 passando (subdiretório), mesmas 7 falhas pré-existentes. Commit: `2290465`.

**O que NÃO foi feito neste commit (deliberado):** o adaptador não está conectado a nenhuma chamada de persistência, nenhum caminho de coletor ao vivo, nenhuma flag de ambiente. Isso continua sendo um próximo passo separado, que exige confirmação explícita antes de escrever em qualquer tabela real (mesmo shadow) ou tocar em qualquer serviço ao vivo — este ambiente é a VPS de produção (ver correção de premissa acima).

### Fase M3.2 — CONCLUÍDA (2026-07-01): persistência real validada na VPS

**Risco identificado e resolvido antes de qualquer escrita em banco real:** o freshness-check do batch ao vivo (`run_v2_pipeline_and_sync` em `infra/sync_v2.py`) decide se recalcula comparando o candle mais novo contra o `run` mais recente em `technical_engine_smc_v2_runs_shadow` para aquele `asset_id`+`timeframe`. Gravar um run do motor incremental usando o `timeframe` REAL faria esse run virar "o mais recente" e poderia fazer o batch **pular um recálculo de produção real**. Decisão do usuário: usar `timeframe="{real}_INCR"` (ex.: `"5min_INCR"`) — a query de freshness usa `timeframe IN (%s, %s)` só com o timeframe real + seu alias, nunca colide com o sufixo `_INCR`.

Criado `tools/smc_v3_validation/persist_incremental_shadow_for_visual_validation.py`: lê candles reais de `market_candles`, roda o motor incremental (alinhado via `SmcSharedEngineConfig`), converte via `legacy_shadow_adapter`, e persiste nas MESMAS 9 tabelas `technical_engine_smc_v2_*_shadow` já espelhadas no MaximusTrader — via `persist_smc_engine_v2_run()` sem modificação. Ferramenta manual/offline — não é chamada por nenhum cron/serviço/coletor ao vivo.

**Validado na própria VPS de produção** (com confirmação explícita do usuário antes da escrita real): rodado contra WINFUT real (asset_id=1, 200 candles de 5min, 2021-06-22 a 2021-06-23) — 236 estruturas persistidas (fvg=44, swings=22, order_blocks=28, bos_choch=12, liquidity=21, bpr=13), `run_id=smc_v2_incremental_20260701_234602`, `timeframe=5min_INCR`. **Confirmado por query direta** que o freshness-check do timeframe real (`WHERE timeframe IN ('5min','M5')`) continua vendo só os runs do batch, completamente isolado do novo run incremental. Commit: `a79b876`.

**O que falta para aparecer no site (fora do escopo deste repositório):**
1. Sincronizar manualmente essas linhas para o Hostinger (`sync_v2_shadow_tables`/`sync_v2_shadow_zones` — não é automático, precisa ser disparado à parte).
2. O MaximusTrader precisa reconhecer/aceitar o timeframe `"{real}_INCR"` para exibir (não verificado — o frontend provavelmente tem uma lista fixa de timeframes selecionáveis).
3. O bug de look-ahead na visualização do Replay (documentado na Fase M3, seção "Mapeamento MaximusTrader") continua existindo independente disso — mesmo com dados corretos chegando, o replay ainda mostraria o estado final antecipadamente.

### Fase M3.3 — CONCLUÍDA (2026-07-02): revisão — nome real do timeframe + serviço batch pausado

Usuário revisou a decisão da Fase M3.2 e pediu duas mudanças:

1. **Limpeza:** as 128 linhas de teste com `timeframe="5min_INCR"` (`run_id=smc_v2_incremental_20260701_234602`) foram deletadas das 6 tabelas onde existiam (`fvg`, `swings`, `order_blocks`, `bos_choch`, `liquidity`, `runs_shadow` — `bpr_shadow` nem existe ainda nesta base). Confirmado por query: 0 linhas restantes com esse timeframe.
2. **Nome real do timeframe por padrão:** `persist_incremental_shadow_for_visual_validation.py` agora grava com o timeframe REAL (ex.: `"5min"`), não mais com sufixo `_INCR` — `--shadow-suffix` continua disponível para reativar o isolamento numa execução específica. Isso reabre conscientemente o risco de colisão do freshness-check documentado na Fase M3.2 (independe de sincronização com o site estar ligada/desligada) — o usuário foi informado explicitamente e aceitou o risco.
3. **Sincronização automática permanece desativada** — decisão do usuário de controlar manualmente quando sincronizar com o Hostinger/site, sem mudança de código necessária (o script já não sincroniza automaticamente).

**Mitigação adicional aplicada nesta sessão:** para eliminar o risco por completo durante os testes, o usuário pausou `smc-candle-event-processor.service` (`systemctl stop`, confirmado `inactive` por `systemctl is-active`/`systemctl status`). Serviço de coleta de candles (`smc-asset-collector@WINFUT.service`) **não foi tocado** — continua coletando normalmente; apenas o ciclo de cálculo SMC batch/sync está pausado. Reversível a qualquer momento (`systemctl start smc-candle-event-processor.service`) quando o usuário decidir retomar o ciclo ao vivo.

Commit: `71db1d2`.

### Fase M3.4 — Auditoria completa do sistema realizada (2026-07-02) — ver Seção 8 (Super Plano)

Usuário pediu auditoria de ponta a ponta ("da coleta até o encontro e notificação da oportunidade") + backtest + estudo com IA + sincronização com o site + serviços. Cinco agentes de investigação rodaram em paralelo (só leitura/comandos de diagnóstico, nenhuma mudança de código ou configuração). Achados completos, consolidados por prioridade, na **Seção 8** abaixo.

---

## 8. Auditoria Completa do Sistema + Super Plano de Correção (2026-07-02)

Consolidação de 5 auditorias (agentes de exploração, só investigação — nenhum arquivo/serviço alterado): lógica de decisão de oportunidade, fidelidade do backtest, coleta + notificação, sistema de estudo com IA, e sincronização com o site + saúde de todos os serviços.

**Reprioritização explícita do usuário durante a auditoria:** a coleta ao vivo e os serviços parados **podem continuar parados** — não é prioridade consertar agora. A prioridade é **simular tudo via CSV** para testar o sistema inteiro de ponta a ponta antes de mexer em produção. Por isso a estrutura abaixo separa "achados documentados, correção não priorizada agora" de "o que entra no próximo passo real (Fase S)".

### 8.1 Achados operacionais ao vivo (DOCUMENTADOS, correção NÃO priorizada agora)

Auditoria real via `systemctl`/`journalctl`/SQL (não suposição) revelou que **praticamente todo o pipeline de dados em tempo real está parado há 10-16 dias**, mascarado por múltiplas camadas de heartbeat que gravam "OK" sem checar se houve progresso real:

- **Coleta morta desde 17-22/06/2026**: `market_candles` para WINFUT travado em 23/06/2021 (não é nem a fonte real); pares forex pararam em 22/06. `technical_engine_candle_events` (fila de eventos) parou para TODOS os símbolos entre 17-20/06.
- **Heartbeat mascara em múltiplas camadas, não só no asset-collector**: `technical_engine_asset_worker_heartbeats` grava `status='OK'` para WINFUT com timestamp de ONTEM À NOITE, mesmo com zero candle novo há 2 semanas. Achado extra: `technical_engine_smc_v2_runs_shadow` gera runs `COMPLETED` HOJE (`01:37:07`) para WINFUT 1min com **`last_candle_at = NULL`** — o motor causal incremental está "produzindo" desconectado da fonte real de dados, sem passar pela mesma checagem de frescor que `run_v2_pipeline_and_sync` usa.
- **Crash-loops ativos agora**: `smc-streamlit.service` (294.381 reinícios), `smc-trader.service` (646.157+ reinícios), `smc-ngrok.service` — todos por apontarem para arquivos/scripts que não existem mais no diretório atual do projeto (relíquia da renomeação `smc_trader_system` → `SMC_Trader_System_7_0`).
- **Núcleo real do pipeline em crash-loop silencioso**: `smc-analysis-worker` (usuário, SMC+Wyckoff+Elliott p/ 11 ativos) — 75.398 reinícios, nunca completou um ciclo com sucesso (`Operation not permitted`/`216/GROUP`). `smc-b3-robot` (usuário) — 89.466 reinícios, `WorkingDirectory` aponta pra diretório inexistente.
- **Units systemd duplicados/desalinhados**: versões de sistema E de usuário com o mesmo nome (`smc-analysis-worker`, `smc-study-forward-shadow`), cada uma quebrada de um jeito diferente, sugerindo confusão de infraestrutura nunca resolvida após a reorganização de diretórios.
- **Sync com o site (Contabo→Hostinger) está tecnicamente saudável**: `maximustrade.com.br` responde HTTP 200 em 0.63s, `smc-sync-watcher.service` está ativo e reportando a cada 60s — mas reporta os números "stale" (candle de 2021) como se fossem normais, sem nenhum threshold/alerta de frescor local, e a lista de serviços que ele monitora **não inclui** os serviços reais do pipeline de trading (só monitora infraestrutura de terminal/scanner, que também estão mortos).
- **`scripts/health_check.sh`** (cron a cada 5min) só monitora infraestrutura de exibição (Xvfb, terminais MT5, streamlit, ngrok) — nunca olha para o pipeline de trading real.
- Elliott/Wyckoff SÃO sincronizados para o site hoje (`sync_elliott_wyckoff`/`run_ew_pipeline_and_persist` existem e funcionam), não é só zonas SMC.

**Decisão do usuário: não corrigir agora.** Fica registrado como um bloco de trabalho futuro (ver 8.5, prioridade P2) — quando o usuário decidir religar a coleta ao vivo, esta lista já está pronta como checklist.

### 8.2 Achados na lógica de decisão de oportunidade (`study_gateway` + `opportunity_scanner`)

- **Bem desenhado**: anti-lookahead centralizado e consistente em `risk_management_v2.py` (`_avail_ok`/`_closed_candles`, aplicado a zonas/eventos/liquidez/candles HTF); `plan_id` determinístico (hash, sem `datetime.now()`); supersede atômico de planos `ACTIVE` antes de cada novo insert; gates do `evaluator.py` bem testados (18 cenários) e em ordem sensata (stale-check antes de qualquer avaliação de proximidade).
- **`plan_lifecycle.py` é código morto em produção** — só é usado no próprio teste; o ciclo de vida real é feito via SQL direto em `operational_plan_persistence.py`. Se algum plano futuro assumir que esse módulo governa o lifecycle real, está errado.
- **Janela estrutural de "sem sinal"**: `max_plan_age_minutes=10` é MENOR que o ciclo do `forward_runner` (15 min) — todo plano fica automaticamente `EXPIRED` para o scanner nos ~5 min finais de cada ciclo. Vale confirmar se é intencional (margem de segurança) ou lacuna.
- **Dedup não sobrevive entre ciclos do forward_runner**: `plan_id` muda a cada novo cálculo de 15 em 15 min (mesmo com mercado parado), então a chave de dedup (`symbol:tf:plan_id:setup_id:proximity`) reinicia a cada ciclo — risco de reemissão do "mesmo" sinal a cada 15 min, não de perda de sinal legítimo.
- **Confluência**: pesos somam exatamente 1.0 (`SMC_STRUCTURE=0.30, SMC_ZONES=0.15, LIQUIDITY=0.10, WYCKOFF=0.25, ELLIOTT=0.15, CONTEXTUAL=0.05`), mas a resolução de conflito entre fontes é puramente aritmética/ponderada (sem hierarquia), e há um bônus heurístico fixo (+0.10 de alignment) por concordância entre timeframes em `build_confluence_weighted_mtf` — não é errado, mas é arbitrário, não derivado dos dados.
- **Sanity gates Elliott/Wyckoff validam só integridade ESTRUTURAL, não correção ANALÍTICA** — uma fase Wyckoff mal-classificada (mas com dados bem formados, sem timestamp futuro) passa perfeitamente no sanity check. `readiness=PRONTO` depende desses gates, mas eles não garantem que a leitura Elliott/Wyckoff está certa, só que não está obviamente corrompida.
- **`_avail_ok` confia cegamente na ausência de timestamp** ("sem timestamp → não bloqueia") — não é bug do risk_management, mas transfere a responsabilidade de anti-lookahead pros dados upstream.

### 8.3 Achados de fidelidade do backtest ("simula o mercado real")

Inventário de ~25 ferramentas de backtest/replay encontradas. **Achado central: nenhuma combina simultaneamente os 4 requisitos que definem fidelidade real** (motor incremental candle-a-candle + Elliott/Wyckoff recalculados causalmente + lógica de produção real `risk_management_v2`/`evaluator.py` + latência MTF real M1→H1→D1) — cada requisito só aparece isolado em ferramentas diferentes:

| Requisito | Onde já existe |
|---|---|
| Motor SMC incremental verdadeiro | `technical_engine/smc_engine_v3/opportunity/canonical_backtest.py` (`CanonicalOpportunityBacktester`) — usa `SmcEngineV2Incremental.on_candle_closed()` + `evaluator.evaluate_opportunity()` REAL de produção. **Mas nunca foi conectado a um CLI/tool — só existe em 2 arquivos de teste unitário.** |
| Elliott/Wyckoff causal + envelope real | `tools/run_study_canonical_truth_replay_500.py` — usa `build_context_states`/`build_technical_truth_envelope_v2` real, mas roda em modo batch por janela amostrada (a cada 5 candles), não candle-a-candle. |
| Latência MTF real (M1 compondo H1) | `tools/r5a_mtf_replay.py` — único tool que respeita `candle_close = timestamp + duração` entre timeframes. Mas só testa a pipeline SMC/persistência, não avalia oportunidades nem simula trades. |
| Execução M1 detalhada (slippage/spread) | `technical_engine/signal_backtest_v1/` — boa fidelidade de execução, mas SMC vem de estado já persistido, não recalculado causalmente no replay. |

**Achado adicional**: nenhuma ferramenta replica fielmente NENHUM dos dois caminhos reais de produção (`dispatcher.py` batch+sem Elliott/Wyckoff, ou `forward_runner.py` persisted+com Elliott/Wyckoff) — e não existe gate formal comparando backtest vs. decisões reais historicamente tomadas em produção.

### 8.4 Sistema de estudo com IA redatora (reframed: on-demand por crédito, não automação)

Correção de entendimento do usuário: o estudo é **sob demanda, pago por crédito do cliente** — a ausência de gatilho automático não é um bug, é o modelo correto. Achados relevantes com essa moldura:

- **Arquitetura de guardrails está pronta e bem testada**: `prompt_builder.py` monta um payload REDUZIDO (não o envelope completo), `response_guard.py` rejeita qualquer tentativa da IA de alterar campos numéricos/decisão operacional (`LLM_FORBIDDEN_FIELDS`), `professional_study_renderer.py` separa campos "narrativos" (a IA pode escrever) de "locked" (entrada/stop/TPs/direção — sempre formatados pelo motor determinístico, nunca pela IA). Testes dedicados passam.
- **Falta o essencial para funcionar de verdade: `call_openrouter_redaction` NUNCA faz chamada HTTP real** — sempre retorna um texto mockado fixo, mesmo com API key configurada. Não é um bug de configuração, é ausência de implementação real neste arquivo.
- **O cliente LLM que de fato funcionava foi apagado do repositório** (`llm/study_llm_service.py` e módulos irmãos — removidos no commit `c651ac9`, mensagem genérica de "snapshot", sem explicação). 10 arquivos de teste (`tests/llm/`) falham hoje com `ModuleNotFoundError` por causa disso.
- **Persistência do draft depende de um CLI manual** (`tools/run_technical_engine_shadow_db_persistence.py`) sem qualquer automação — mesmo gerando um estudo real, ele não chegaria ao banco/dashboard sem um humano rodar esse script.
- **Custo/rate-limit está só na configuração, nunca aplicado em código** (`daily_limit=45`, `rpm_limit=18` existem no config mas nenhum contador/circuit-breaker os lê).

**Para funcionar como "sob demanda por crédito", falta implementar (não corrigido nesta sessão):** (1) a chamada HTTP real ao OpenRouter em `openrouter_client.py`; (2) decidir o destino do módulo `llm/` apagado (restaurar do histórico git ou reescrever); (3) um endpoint/fluxo que amarre "cliente consome 1 crédito → dispara `LLMRedactionRequest` sob demanda" (hoje não existe nenhum gatilho, nem automático nem sob demanda, conectado); (4) automatizar a persistência do draft gerado.

### 8.5 Próximos Passos Priorizados (Super Plano)

**P0 — Fase S: Harness de Simulação Completa via CSV** *(prioridade real do usuário agora)*

Objetivo: montar um ambiente de teste que processe os CSVs históricos candle a candle, com latência MTF real, rodando a MESMA lógica de decisão de produção (não uma reimplementação), para validar o sistema inteiro (SMC + Elliott + Wyckoff + confluência + risk management + opportunity scanner) sem depender da coleta ao vivo estar funcionando.

Proposta de sub-fases, reaproveitando os blocos MAIS causais já encontrados na auditoria (8.3) em vez de reescrever do zero:

- **S1** — Harness de ingestão CSV multi-timeframe com latência MTF real, **acelerado**: adaptar a abordagem de `r5a_mtf_replay.py` (candle M2→M5→M15→H1→H4→D1 respeitando `candle_close = timestamp + duração`) para alimentar o motor incremental em vez do pipeline batch de produção. **Requisito explícito do usuário**: a simulação deve rodar o mais rápido possível (sem esperar o tempo real passar entre candles, ao contrário de `r5a_mtf_replay.py` que roda em tempo real) — mas preservando rigorosamente a **ordem causal relativa** entre timeframes (nunca processar/fechar um candle H1 antes de todos os 60 candles M1 que o compõem já terem sido processados na simulação, e assim por diante para M5→M15→H1→H4→D1). Ou seja: acelerado no relógio de parede, mas com a mesma sequência lógica de fechamento que aconteceria ao vivo.
- **S2** — Motor SMC incremental sobre esse harness: já validado exaustivamente nesta sessão (Fases M1-M3, paridade exata contra dados reais) — reaproveitar diretamente `run_smc_engine_v3`/`SmcEngineV2Incremental`.
- **S3** — Elliott + Wyckoff recalculados causalmente a cada candle disponível (não apenas a cada 15 min como o `forward_runner` real faz) — adaptar `build_context_states()` para rodar dentro do loop candle-a-candle do harness, não em um timer externo.
- **S4** — Confluência + `risk_management_v2` + `opportunity_scanner/evaluator.py` REAIS (não uma versão de teste simplificada) rodando sobre a simulação — este é exatamente o gap que `CanonicalOpportunityBacktester` já resolve para SMC puro (8.3); a extensão aqui é incluir Elliott/Wyckoff/confluência também.
- **S5** — Notificação simulada (dry-run, sem HTTP real) + relatório comparando o que o sistema "teria decidido" candle a candle contra os últimos 3 meses de dados reais já processados nas Fases M1-M3.

**P1 — Fechar os achados da Seção 8.2** (lógica de decisão): decidir sobre `plan_lifecycle.py` (remover ou reconectar), revisar a janela `max_plan_age_minutes` vs. ciclo do `forward_runner`, decidir se a confluência precisa de hierarquia de fontes.

**P2 — Estudo com IA sob demanda** (8.4): implementar os 4 itens listados quando o usuário decidir priorizar essa feature.

**P3 — Achados operacionais ao vivo** (8.1): checklist já pronto, fica para quando o usuário decidir religar a coleta ao vivo — não bloqueia P0/S1-S5, que rodam inteiramente sobre CSV local.

**Pontos de decisão do usuário antes de qualquer implementação:**
1. Confirmar a sequência S1-S5 proposta acima (ou ajustar).
2. Confirmar se Elliott/Wyckoff devem entrar na Fase S desde já, ou se a primeira rodada é só SMC (mais rápido de montar, já que o motor incremental já está validado).
3. Escopo do "relatório de comparação" do S5 — o que exatamente conta como "o sistema decidiu certo" nesse contexto de simulação histórica.

## 9. Registro de Progresso — Fase S (Harness de Simulação CSV)

Ferramenta: `tools/fase_s_simulacao/run_harness_csv_mtf.py` (+ `tools/fase_s_simulacao/s4_opportunity_bridge.py`). Commit S1+S2: `ac390f7`.

### Fase S1 — CONCLUÍDA (2026-07-02): harness MTF acelerado e causal

Relógio-mestre no timeframe mais fino, drenando M2→M5→M15→H1→H4→D1 respeitando `candle_close <= master_deadline` — nunca processa um candle de TF maior antes de todos os candles de TF menor que "aconteceram" antes dele no tempo real terem sido processados, mas sem `time.sleep` (roda no limite da CPU). Validado com 3 escaladas sucessivas (1mês/4TF → 1mês/6TF → 3meses/6TF).

Resultado 3 meses / 6 timeframes (WINFUT, M2 mestre): 436s, candles M2=17.227/M5=6.893/M15=2.317/H1=609/H4=182/D1=60, 33.108 estruturas SMC geradas no total, **zero erros em todos os timeframes**.

### Fase S2 — CONCLUÍDA (2026-07-02): motor SMC incremental por timeframe

Uma instância de `SmcEngineV2Incremental` por timeframe (9 componentes registrados via `SmcSharedEngineConfig`), processando candle a candle dentro do relógio-mestre do S1. Resultado já incluído no S1 acima (mesma rodada).

### Fase S3 — CONCLUÍDA (2026-07-02): Elliott + Wyckoff causais

Adicionados `--context-timeframe` (default M5) e `--context-window` (default 500, espelha `ForwardRunnerConfig.candles_limit`) ao harness. A cada candle novo do context-timeframe, chama a MESMA `build_context_states()` da produção (`technical_engine/study_gateway/context_states.py`, usada por `forward_runner.py`) sobre uma janela rolante em memória — só muda o gatilho (candle a candle vs. timer de 15min).

Resultado 3 meses / 6 timeframes: 6.893 recálculos causais de Elliott/Wyckoff, **zero erros**, overhead de apenas +31s sobre o S1+S2 puro (467s vs 436s). Sanity: Elliott 6.831/6.893 passou (99,1%), Wyckoff 6.893/6.893 passou (100%).

### Fase S4 — CONCLUÍDA (2026-07-02): cadeia REAL de decisão de oportunidade

Criado `tools/fase_s_simulacao/s4_opportunity_bridge.py` (movido em 2026-07-02 para `technical_engine/study_gateway/incremental_opportunity_bridge.py` ao virar código de produção — ver Seção 10), conectando o estado do harness à cadeia de produção completa, sem tocar banco:

```
smc_v2_state (legacy shadow shape, via legacy_shadow_adapter — já usado no M3)
  + elliott_state/wyckoff_state (build_context_states, do S3)
  -> build_technical_truth_envelope_v2()   (smc_v2_adapter.py — REAL)
  -> build_operational_plan()              (risk_management_v2.py — REAL)
  -> PersistedOperationalPlanRef (montado em memória, réplica do mapeamento
     de save_operational_plan_shadow(), sem escrever no banco)
  -> evaluate_opportunity()                (opportunity_scanner/evaluator.py — REAL)
```

Decisão de design: **não** reaproveitou `CanonicalOpportunityBacktester`/`ReplayOpportunityAdapter` (`technical_engine/smc_engine_v3/opportunity/`) porque aquele usa geometria de entrada/stop/TP simplificada (RR fixo sobre o meio da zona) sem Elliott/Wyckoff/confluência — o pedido explícito da S4 era a cadeia real, não uma versão de teste.

Correção adicional feita durante a integração: `legacy_shadow_adapter.convert_incremental_to_legacy_shadow_shape()` não emitia `mitigation_status` para Order Blocks (só para FVG/BPR), fazendo `_extract_ob_zones()` sempre cair no default `"NAO_MITIGADO"` mesmo para OBs já mitigados — isso inflava artificialmente os candidatos a zona primária no `risk_management_v2.pick_primary_zone()`. Corrigido (1 linha) + teste de contrato de chaves atualizado (`tests/test_smc_engine_v2/test_legacy_shadow_adapter.py`).

Resultado de validação (1 mês / M5,M15,H1, `--context-window 200`): 2.374 decisões causais completas, **zero erros**. Readiness: BLOQUEADO=908, MONITORAR=966, PRONTO=500 (destes, 176 com `has_operation=True`, ou seja, sinal de entrada pronto). Proximity: DISTANTE=2.301, NA_ZONA=23, IMINENTE=18, PROXIMO=22, OBSERVANDO=10.

Rodada completa (3 meses / 6 timeframes / `--context-window 500`, WINFUT): 599s (~10min), **zero erros em S1/S2/S3/S4 nos 6 timeframes**. S3: 6.893 recálculos Elliott/Wyckoff, sanity Elliott 99,1%/Wyckoff 100%. S4: 6.893 avaliações causais completas — Readiness BLOQUEADO=5.424, PRONTO=837 (225 com `has_operation=True`), MONITORAR=632; Proximity DISTANTE=6.772, NA_ZONA=45, IMINENTE=37, PROXIMO=28, OBSERVANDO=11.

Suite de testes (`test_smc_engine_v2`, `test_technical_engine/test_study_gateway*`, `test_opportunity_scanner`) rodada após as mudanças: 624 passando, 42 falhas pré-existentes não relacionadas (dependem de conexão MySQL real ou de arquivos de documentação ausentes — confirmado que já falhavam antes desta sessão, ex. `test_load_latest_price_found` falha por `conn` real sem dado, não por regressão de código).

**Próximo passo original:** Fase S5 (notificação simulada dry-run + relatório comparando decisões candle-a-candle contra os 3 meses de dados reais) — ainda pendente de decisão do usuário sobre o escopo exato de "o sistema decidiu certo". **Superado em prioridade** pela Seção 10 abaixo: o usuário confirmou que o motor incremental deve virar o principal de produção agora, então o trabalho pulou direto para o cutover; S5 fica pendente para depois.

## 10. Motor Incremental como Principal — Cutover (2026-07-02, em andamento)

Usuário: *"temos que transformar o motor incremental no principal que deve calcular tudo candle a candle e aposentar o outro."* Planejado em modo de planejamento dedicado (plano salvo em `/home/bimaq/.claude/plans/agile-meandering-fairy.md`), com 2 agentes de exploração mapeando a fiação de produção atual e as opções de schema de persistência antes de escrever código.

**Decisões confirmadas pelo usuário para esta etapa:**
1. Só código — nenhum serviço systemd novo é criado/ativado; `smc-candle-event-processor.service` e `smc-study-forward-shadow.timer` continuam pausados. Ativação ao vivo fica para uma etapa futura, com aprovação explícita separada.
2. Dual-write mantido: o motor incremental grava nativamente em `smc_v2_structures`/`smc_v2_structure_events` (referência) E nas 9 tabelas legadas `technical_engine_smc_v2_*_shadow` (via `legacy_shadow_adapter.py`), para o site MaximusTrader continuar funcionando sem mudança nenhuma do lado dele.

**Descobertas-chave da exploração:**
- Batch hoje (`services/candle_event_processor/dispatcher.py`) é stateless, disparado por outbox MySQL (`technical_engine_candle_events`, claim via `SELECT...FOR UPDATE SKIP LOCKED`), recalcula a janela inteira do zero a cada candle. H1 nunca esteve no `TIMEFRAME_DISPATCH_MAP` (gap pré-existente). Só o handler M5 continua para Study Gateway → Risk → Scanner → sync Laravel.
- `forward_runner.py` em produção roda via timer systemd a cada 15 min (`--mode ONCE`), não candle a candle — exatamente a divergência que a Fase S3 já tinha provado resolver.
- Ambos os serviços batch estão hoje INATIVOS, mas `smc-asset-collector@WINFUT.service` (coletor MT5) está ATIVO — candles novos entram em `market_candles` sem processamento downstream.
- `SmcEngineV2Incremental` já suporta checkpoint/restore (`snapshot()`/`restore()`) — nunca tinha sido conectado a um runner real.
- **Descoberta corrigida em campo**: o schema nativo `smc_v2_*` (`migrations/20260629_smc_v2_incremental_schema.sql`) existia como ARQUIVO mas **nunca tinha sido aplicado no MySQL real** — aplicado nesta sessão (6 tabelas criadas, `CREATE TABLE IF NOT EXISTS`, aditivo, rollback documentado no próprio arquivo).
- As classes de repositório do schema nativo (`persistence/repositories.py`) só existiam em versão SQLite (usadas nos testes de integridade R2) — nunca tiveram equivalente MySQL. Esse era o gap real a fechar.
- `CutoverManager` (Fase 08, `technical_engine/smc_engine_v3/shadow/cutover.py`) já tinha a forma certa de roteamento por (asset, tf) mas era puramente em memória.

**Trabalho concluído:**
- `technical_engine/smc_engine_v3/incremental/live/mysql_repositories.py` — porte MySQL das 6 classes (`EngineRunRepositoryMySQL`, `StructureRepositoryMySQL`, `StructureEventRepositoryMySQL`, `CheckpointRepositoryMySQL`, `StreamVersionRepositoryMySQL`, `ReconciliationRepositoryMySQL`), mesma semântica de idempotência/conflito por hash. Bug descoberto e corrigido durante os testes: a coluna `payload_json` (tipo `JSON` no MySQL) reformata o texto ao gravar (espaços, etc.), então comparar hash do texto bruto lido de volta sempre divergia do hash calculado antes do INSERT — corrigido reserializando via a mesma forma canônica antes de hashear (`_hash_stored_json`). 6 testes novos passando contra MySQL real (`tests/test_smc_engine_v2/test_mysql_repositories_live.py`).
- `technical_engine/smc_engine_v3/incremental/live/cutover_store.py` — `PersistedCutoverStore`, casca fina sobre `CutoverManager` que persiste o roteamento em `smc_v2_active_stream_versions` via `StreamVersionRepositoryMySQL`, com `load_from_db()` para reidratar no boot. Não modifica `CutoverManager` (não quebra os testes da Fase 08). 3 testes novos passando (`tests/test_smc_engine_v2/test_cutover_store_live.py`).
- `technical_engine/study_gateway/incremental_opportunity_bridge.py` — `s4_opportunity_bridge.py` promovido de `tools/fase_s_simulacao/` para dentro de `technical_engine/`, já que deixou de ser só ferramenta de harness.
- `technical_engine/smc_engine_v3/incremental/live/runner.py` — `IncrementalCandleRunner`: cold-start/warm-restart via checkpoint (reaproveita `_load_market_candles`+replay quando não há checkpoint), persistência dupla (nativa + dual-write legado a cada `legacy_flush_every` candles via `legacy_shadow_adapter`+`persist_smc_engine_v2_run`, reaproveitados sem modificação), checkpoint periódico, e disparo da cadeia S3/S4 real (`build_context_states`→`build_technical_truth_envelope_v2`→`build_operational_plan`→`save_operational_plan_shadow`, todas funções de produção reaproveitadas sem modificação) no fechamento do `trigger_timeframe`. Roteamento consulta `PersistedCutoverStore.is_on_v2()` antes de processar qualquer candle vindo do outbox — se False, não faz nada (fallback batch preservado).
  - Bug encontrado e corrigido na validação: `_row_to_envelope` usava o SYMBOL como `asset_id` do `CandleEnvelope`, mas a engine era construída com o `asset_id` numérico como string — `EngineIdentityMismatchError` em toda candle. Corrigido para usar o mesmo `asset_id` em ambos.
- `tools/fase_s_simulacao/run_live_replay_check.py` — script de validação manual (sem systemd) contra `market_candles` REAL.

**Resultado da validação** (WINFUT, asset_id=1, timeframe=5min — únicos dados reais disponíveis em `market_candles` neste banco são de 2021, 206 candles M5; os 3 meses recentes usados na Fase S vieram de CSV, não estão em `market_candles`):
- `cold_start` (replay do zero): 206 candles processados, 238 estruturas + 503 eventos gravados nativamente, 2 checkpoints, **zero erros**.
- Segunda execução do mesmo script: `cold_start` restaurou via checkpoint em vez de reprocessar (`status=restored`, 0 candles reprocessados) — confirma que o warm-restart funciona.
- Reprocessamento de 20 candles pelo caminho "ao vivo" completo (nativo + cadeia de oportunidade real, dual-write legado deliberadamente OMITIDO nesta validação para não escrever um run novo nas tabelas `*_shadow` que o site lê para WINFUT com dado de 2021): 20 decisões causais completas via `build_context_states`+`build_technical_truth_envelope_v2`+`build_operational_plan`+`evaluate_opportunity` reais, **zero erros**.
- Suite de testes (`test_smc_engine_v2/`, `test_smc_engine_v2_r2_persistence_integrity.py`) rodada após as mudanças: 223 passando, 7 falhas pré-existentes confirmadas via `git stash` (referenciam `technical_engine/smc_engine_v2/swings.py`, caminho de um módulo legado que não existe mais nesta árvore — não relacionadas a esta mudança).

### Validação final com dado real e recente (2026-07-02)

Usuário pediu para recarregar `market_candles` com o mesmo CSV de 3 meses da Fase S antes de validar o dual-write legado. Criado `tools/fase_s_simulacao/reload_market_candles_from_csv.py`: faz backup das linhas existentes do asset (CSV em `runtime/fase_s/backups/`, 816 linhas de 2021 preservadas) antes de apagar, depois recarrega a partir dos CSVs canônicos. Executado para WINFUT (asset_id=1): **61.662 candles reais inseridos** em 7 timeframes (2026-03-23 a 2026-06-19) — M1=34.370, M2=17.227, M5=6.893, M15=2.318, H1=610, H4=183, D1=61.

Com esse dado real, rodado `run_live_replay_check.py --legacy-dual-write` (WINFUT, timeframe=5min). Bug encontrado e corrigido: `_flush_legacy` passava `"visual_overlays": []` (lista) para `persist_smc_engine_v2_run`, que espera um dict (`visual_overlays.items()`) — corrigido para `{}`.

**Resultado final, zero erros em todas as etapas:**
- `cold_start` (replay do zero, 100 dias de lookback): 6.775 candles processados, 8.313 estruturas + 17.993 eventos gravados nativamente, 34 checkpoints.
- Segunda execução: `cold_start` restaurou via checkpoint (`status=restored`, 0 candles reprocessados) — warm-restart confirmado novamente com volume real.
- Reprocessamento de 20 candles pelo caminho "ao vivo" completo, **incluindo desta vez o dual-write legado** (bloqueado uma vez pelo classificador de modo automático até confirmação explícita do usuário, depois autorizado): 2 flushes legados bem-sucedidos, 20 decisões de oportunidade reais, zero erros. Confirmado manualmente que `technical_engine_smc_v2_runs_shadow`/`*_fvg_shadow` etc. têm um novo run para WINFUT com `created_at=2026-07-02`, dado real e recente (não mais um risco de mostrar zonas de 2021 no site).
- Suite `tests/test_smc_engine_v2/` rodada de novo: 195 passando, mesmas 7 falhas pré-existentes (não relacionadas).

**Pendente / próximos passos desta fase:**
1. Escrever testes automatizados para `IncrementalCandleRunner` (unitários, com engine+conexão real) — hoje validado via script manual (`run_live_replay_check.py`), não via suíte pytest.
2. Reconciliação: `ReconciliationRepositoryMySQL` existe mas o runner ainda não chama `count_orphan_events`/`log` periodicamente.
3. Repetir a validação também para os outros timeframes (M2/M15/H1/H4/D1) e não só M5 — hoje só M5 (trigger_timeframe) foi exercitado de ponta a ponta incluindo a cadeia de oportunidade.
4. Fora de escopo desta etapa (confirmado): criar/ativar unidades systemd, apagar `pipeline.py`/`dispatcher.py` (ficam como fallback/ferramenta offline), migrar o site para o schema nativo.
