# PLANO EXECUTIVO COMPLETO — MICROSSERVIÇOS POR ATIVO, EVIDÊNCIAS VISUAIS E ENTREGA MOBILE

**Projeto:** SMC Trader System 7.0  
**Versão do plano:** 1.0  
**Data-base arquitetural:** 16 de junho de 2026  
**Status:** Plano oficial proposto para execução  
**Escopo:** Sistema Local/VPS + MaximusTrader/Laravel + App Android/Kotlin Multiplatform  
**Objetivo principal:** Detectar oportunidades técnicas explicáveis, congelar as evidências técnicas e visuais do momento da detecção, persistir o pacote completo, notificar o cliente e apresentar o gráfico ilustrado no aplicativo.

---

# 0. COMO USAR ESTE DOCUMENTO

Este documento foi escrito para ser executável por:

- um programador humano;
- uma equipe de desenvolvimento;
- uma IA de código com acesso ao repositório;
- um agente autônomo que trabalhe fase por fase.

Cada fase deve ser executada isoladamente. Nenhuma fase pode ser marcada como concluída apenas porque arquivos foram criados. A conclusão exige:

1. código implementado;
2. migrations aplicadas em ambiente de teste;
3. testes automatizados passando;
4. validação operacional executada;
5. documentação atualizada;
6. relatório final da fase;
7. rollback documentado e, quando aplicável, testado.

## 0.1 Ordem obrigatória

```text
FASE A — Microserviço por ativo + eventos de candle fechado
    ↓
FASE B — OpportunityEvidenceBundleV1
    ↓
FASE C — ChartSnapshotRenderer + armazenamento
    ↓
FASE D — API mobile de evidências + gráfico no detalhe
    ↓
FASE E — FCM real + deep link + Android físico
    ↓
FASE F — Lifecycle e outcomes da oportunidade
    ↓
FASE G — Soak test + beta interno
    ↓
FASE H — Melhoria do site
```

Uma fase só pode começar quando os critérios de pronto da fase anterior forem satisfeitos, exceto trabalhos puramente documentais sem alteração de código.

---

# 1. OBJETIVO DO PRODUTO

O produto deve entregar a seguinte experiência:

```text
1. MT5 fecha um candle.
2. O microserviço do ativo detecta o novo candle fechado.
3. Um evento idempotente é publicado.
4. O Technical Engine atualiza SMC, Elliott, Wyckoff e contexto.
5. O Study Gateway gera a verdade técnica canônica.
6. O Risk Management gera o plano operacional.
7. O Opportunity Scanner encontra uma oportunidade válida.
8. O sistema congela todas as evidências utilizadas.
9. O sistema gera o gráfico ilustrado.
10. O pacote é persistido e sincronizado com o MaximusTrader.
11. O Laravel envia uma notificação FCM.
12. O cliente toca na notificação.
13. O aplicativo abre diretamente a oportunidade.
14. A tela de detalhe mostra gráfico, entrada, stop, alvos e explicações.
15. O sistema acompanha o ciclo de vida e o resultado da oportunidade.
```

## 1.1 Resultado esperado para o cliente

O cliente deve conseguir responder visualmente:

- Qual ativo gerou a oportunidade?
- Quando ela foi detectada?
- Qual era o preço naquele momento?
- Qual era a direção técnica?
- Qual era a entrada?
- Onde estava o stop?
- Quais eram TP1, TP2 e TP3?
- Qual FVG, OB, BPR ou liquidez sustentava o cenário?
- Qual estrutura BOS/CHOCH estava ativa?
- Qual era o contexto Elliott?
- Qual era a fase ou evento Wyckoff?
- Qual era o alinhamento entre H4, M15, M5 e M2?
- Qual foi a taxa histórica observada e qual o tamanho da amostra?
- O cenário ainda está ativo, foi invalidado ou atingiu algum alvo?

---

# 2. REGRAS ARQUITETURAIS INVIOLÁVEIS

As regras abaixo devem permanecer válidas em todas as fases.

```text
shadow_only=True
can_promote_trade=False
apply_automatically=False
llm_decision_used=False
anti_lookahead=True
deterministico=True
probabilidade_proibida=True
smc_recomputed_in_frontend=False
```

## 2.1 O Technical Engine continua sendo a fonte da verdade

O Laravel e o App Android nunca podem:

- recalcular FVG;
- recalcular OB;
- recalcular BOS/CHOCH;
- recalcular Elliott;
- recalcular Wyckoff;
- decidir direção;
- recalcular entrada, stop ou alvo;
- alterar readiness;
- alterar confidence;
- modificar o `technical_truth_hash`.

## 2.2 A LLM continua sendo apenas redatora

A LLM pode:

- redigir resumo;
- explicar evidências já calculadas;
- traduzir termos técnicos;
- gerar texto educacional.

A LLM não pode:

- calcular score;
- criar zonas;
- ajustar entrada;
- mudar stop;
- escolher alvos;
- promover oportunidade;
- alterar resultado;
- decidir COMPRA, VENDA ou AGUARDAR.

## 2.3 Preservação do SMC Engine V2

A pasta `technical_engine/smc_engine_v2/` está congelada como `STABLE_FROZEN_V2`.

Qualquer alteração nela exige:

1. fase própria de mudança;
2. justificativa técnica;
3. backtest;
4. regressão integral;
5. atualização de versão do motor;
6. migração ou compatibilidade explícita;
7. aprovação manual do responsável pelo produto.

As Fases A até H não devem modificar o comportamento do motor congelado.

---

# 3. BASELINE OBRIGATÓRIO ANTES DA FASE A

Criar uma branch exclusiva:

```bash
git checkout -b feature/opportunity-evidence-mobile-v1
```

Registrar:

```bash
git branch --show-current
git rev-parse HEAD
git status --short
python --version
mysql --version
node --version
java -version
```

Executar e salvar:

```bash
python -m pytest tests/ -q --tb=short
```

Executar as suítes críticas separadamente:

```bash
python -m pytest \
  tests/test_smc_engine_v2 \
  tests/test_study_gateway \
  tests/test_opportunity_scanner \
  -q --tb=short
```

Criar:

```text
docs/roadmap_evidence_mobile/BASELINE_ANTES_FASE_A.md
```

O baseline deve conter:

- commit;
- branch;
- arquivos modificados;
- serviços ativos;
- testes coletados;
- testes aprovados;
- testes falhos;
- testes ignorados;
- estado do banco;
- espaço em disco;
- memória;
- versões das dependências;
- caminhos de secrets sem revelar conteúdo;
- endpoints de health;
- último candle por ativo/timeframe;
- status dos robôs B3 e Forex;
- status do scanner;
- status do notifier;
- status do sync watcher.

## 3.1 Bloqueios antes de iniciar

A Fase A não deve começar se:

- não houver backup do banco;
- não houver commit ou snapshot reproduzível;
- o SMC V2 não passar na suíte crítica;
- o scanner estiver escrevendo fora de tabelas shadow;
- credenciais estiverem expostas no Git;
- o banco estiver sem espaço suficiente;
- os bridges MT5 estiverem instáveis.

---

# 4. ARQUITETURA-ALVO

```text
┌────────────────────────────────────────────────────────────────────┐
│ MT5 B3 / Forex                                                     │
└──────────────────────┬─────────────────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ ASSET COLLECTORS — 1 processo por ativo                            │
│ smc-asset-collector@WINFUT                                         │
│ smc-asset-collector@WDOFUT                                         │
│ smc-asset-collector@XAUUSDm                                        │
│ ...                                                                │
│ Cada worker controla M1/M2/M5/M15/H4/D1                            │
└──────────────────────┬─────────────────────────────────────────────┘
                       │ CANDLE_CLOSED
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ EVENT OUTBOX DURÁVEL — MySQL                                       │
│ technical_engine_candle_events                                     │
└──────────────────────┬─────────────────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ TECHNICAL PROCESSOR                                                 │
│ SMC V2 persisted + Elliott + Wyckoff + Contextual                  │
└──────────────────────┬─────────────────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ STUDY GATEWAY + RISK MANAGEMENT                                    │
│ TechnicalTruthEnvelopeV2 + OperationalPlanV2                       │
└──────────────────────┬─────────────────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ OPPORTUNITY SCANNER                                                 │
└──────────────────────┬─────────────────────────────────────────────┘
                       │ OPPORTUNITY_DETECTED
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ OPPORTUNITY EVIDENCE PIPELINE                                      │
│ Bundle Builder → ChartSpec → Renderer → Storage → Sync             │
└──────────────────────┬─────────────────────────────────────────────┘
                       │ HMAC
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ MAXIMUSTRADER                                                       │
│ Persistência → API Mobile → FCM → Lifecycle                        │
└──────────────────────┬─────────────────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────────────────┐
│ APP ANDROID                                                        │
│ Push → Deep Link → Detalhe → Gráfico → Evidências                  │
└────────────────────────────────────────────────────────────────────┘
```

---

# 5. PADRÕES TRANSVERSAIS

## 5.1 IDs

Todos os artefatos devem ter identificadores estáveis.

```text
event_id
run_id
envelope_id
operational_plan_id
signal_id
alert_id
opportunity_id
evidence_bundle_id
chart_snapshot_id
lifecycle_event_id
outcome_id
```

Formato recomendado:

```text
<tipo>-<uuid7/uuid12>
```

Exemplo:

```text
opp-01982c7e
evb-4abf82c09416
chart-82bd4a2f7d90
```

## 5.2 Tempo

Persistir internamente em UTC.

Campos obrigatórios:

```text
created_at
available_at
detected_at
opportunity_time
sent_at
opened_at
resolved_at
candle_time
```

A apresentação deve converter para a timezone do usuário. Para o Brasil:

```text
America/Sao_Paulo
```

## 5.3 Idempotência

Toda operação entre processos deve ter chave idempotente.

Exemplos:

```text
Candle: <asset_id>:<timeframe>:<candle_time>
Bundle: <opportunity_id>:bundle:v1
Chart: <evidence_bundle_id>:chart:v1
Sync: <evidence_bundle_id>:sync:v1
Push: <alert_id>:<user_id>:<device_id>
```

## 5.4 Hashes

Persistir:

```text
technical_truth_hash
bundle_hash
chart_spec_hash
image_sha256
payload_sha256
```

O hash deve ser calculado sobre JSON canônico:

- chaves ordenadas;
- UTF-8;
- sem espaços irrelevantes;
- números normalizados;
- timestamps em ISO-8601 UTC.

## 5.5 Versionamento de contratos

```text
CandleClosedEventV1
OpportunityEvidenceBundleV1
OpportunityChartSpecV1
OpportunityLifecycleEventV1
OpportunityOutcomeV1
MobileOpportunityEvidenceV1
```

Nunca alterar silenciosamente um contrato existente. Criar V2 quando houver mudança incompatível.

## 5.6 Logs estruturados

Cada log deve incluir, quando aplicável:

```json
{
  "service": "",
  "event": "",
  "symbol": "",
  "asset_id": 0,
  "timeframe": "",
  "event_id": "",
  "opportunity_id": "",
  "evidence_bundle_id": "",
  "status": "",
  "duration_ms": 0,
  "error_code": "",
  "timestamp": ""
}
```

## 5.7 Correlação

O `opportunity_id` deve acompanhar o fluxo inteiro:

```text
scanner
→ bundle
→ chart
→ upload
→ Laravel
→ job FCM
→ app
→ lifecycle
→ outcome
```

## 5.8 Segurança

- Secrets somente em `.env`, systemd credentials ou secret manager.
- Nenhum token em documentação.
- Nenhum objeto de evidência público sem autenticação.
- URLs de imagem devem exigir autorização ou assinatura temporária.
- HMAC com timestamp, nonce e body hash.
- Rate limit nos endpoints internos e mobile.
- Não armazenar service account Firebase no repositório.
- Redigir logs para não incluir tokens FCM completos.

---

# FASE A — MICROSSERVIÇO POR ATIVO + EVENTOS DE CANDLE FECHADO

## A.1 Objetivo

Substituir o loop monolítico por mercado por uma arquitetura com um processo independente para cada ativo, mantendo os timeframes do ativo sob responsabilidade do mesmo worker.

Arquitetura escolhida:

```text
1 ativo = 1 serviço
1 serviço = 6 tarefas de timeframe
```

Não criar 66 serviços separados.

## A.2 Resultado esperado

```text
WINFUT não espera WDOFUT.
XAUUSDm não bloqueia BTCUSDm.
Falha de um ativo não derruba os demais.
Cada candle fechado gera exatamente um evento.
Nenhum candle aberto entra no pipeline.
```

## A.3 Decisões técnicas

### A.3.1 Barramento de eventos

Usar inicialmente **MySQL Event Outbox**, porque:

- o MySQL já é dependência existente;
- o volume de 11 ativos × 6 timeframes é baixo;
- facilita auditoria;
- evita introduzir Redis antes do MVP;
- oferece persistência e idempotência.

Criar uma interface que permita migrar futuramente para Redis Streams:

```python
class CandleEventPublisher(Protocol):
    def publish(self, event: CandleClosedEventV1) -> PublishResult:
        ...
```

Implementação inicial:

```text
MySqlCandleEventPublisher
```

Implementação futura opcional:

```text
RedisStreamCandleEventPublisher
```

## A.4 Estrutura de diretórios

Criar:

```text
services/
└── asset_collector/
    ├── __init__.py
    ├── models.py
    ├── config.py
    ├── registry.py
    ├── mt5_gateway.py
    ├── candle_fetcher.py
    ├── closed_candle_detector.py
    ├── persistence.py
    ├── event_publisher.py
    ├── heartbeat.py
    ├── worker.py
    ├── cli.py
    └── errors.py
```

Criar processador:

```text
services/
└── candle_event_processor/
    ├── __init__.py
    ├── models.py
    ├── repository.py
    ├── claim.py
    ├── processor.py
    ├── dispatcher.py
    ├── heartbeat.py
    └── cli.py
```

## A.5 Contrato `CandleClosedEventV1`

```json
{
  "schema_version": "1.0",
  "event_type": "CANDLE_CLOSED",
  "event_id": "WINFUT:M5:2026-06-16T14:35:00Z",
  "asset_id": 1,
  "symbol": "WINFUT",
  "mt5_symbol": "WINM26",
  "market": "B3",
  "timeframe": "M5",
  "candle_time": "2026-06-16T14:35:00Z",
  "closed_at": "2026-06-16T14:40:01Z",
  "source": "MT5",
  "collector_instance": "host:pid",
  "ohlcv": {
    "open": 0.0,
    "high": 0.0,
    "low": 0.0,
    "close": 0.0,
    "volume": 0.0
  },
  "indicators": {
    "ema20": 0.0,
    "ema200": 0.0,
    "rsi14": 0.0,
    "atr14": 0.0
  },
  "created_at": "2026-06-16T14:40:02Z"
}
```

O `event_id` deve ser determinístico.

## A.6 Migrations VPS

Criar:

```text
database/migrations/YYYYMMDD_create_asset_collector_runtime.sql
```

### A.6.1 `technical_engine_candle_events`

Campos:

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
event_id VARCHAR(160) NOT NULL UNIQUE,
schema_version VARCHAR(16) NOT NULL,
event_type VARCHAR(40) NOT NULL,
asset_id BIGINT NOT NULL,
symbol VARCHAR(32) NOT NULL,
mt5_symbol VARCHAR(64) NULL,
market VARCHAR(16) NOT NULL,
timeframe VARCHAR(16) NOT NULL,
candle_time DATETIME(6) NOT NULL,
closed_at DATETIME(6) NOT NULL,
payload JSON NOT NULL,
status ENUM('PENDING','PROCESSING','COMPLETED','FAILED','DEAD') NOT NULL DEFAULT 'PENDING',
attempts INT NOT NULL DEFAULT 0,
available_at DATETIME(6) NOT NULL,
claimed_at DATETIME(6) NULL,
claimed_by VARCHAR(128) NULL,
processed_at DATETIME(6) NULL,
last_error_code VARCHAR(80) NULL,
last_error_message TEXT NULL,
created_at DATETIME(6) NOT NULL,
updated_at DATETIME(6) NOT NULL,
INDEX idx_candle_events_status_available (status, available_at),
INDEX idx_candle_events_asset_tf_time (asset_id, timeframe, candle_time)
```

### A.6.2 `technical_engine_asset_worker_heartbeats`

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
worker_id VARCHAR(128) NOT NULL,
asset_id BIGINT NOT NULL,
symbol VARCHAR(32) NOT NULL,
pid INT NULL,
status VARCHAR(32) NOT NULL,
last_cycle_started_at DATETIME(6) NULL,
last_cycle_finished_at DATETIME(6) NULL,
last_candle_times JSON NULL,
last_error JSON NULL,
metrics JSON NULL,
heartbeat_at DATETIME(6) NOT NULL,
created_at DATETIME(6) NOT NULL,
updated_at DATETIME(6) NOT NULL,
UNIQUE KEY uq_asset_worker (worker_id, asset_id),
INDEX idx_asset_worker_heartbeat (heartbeat_at)
```

### A.6.3 `technical_engine_event_processor_heartbeats`

Criar tabela equivalente para o processador.

## A.7 Registro de ativos

Criar:

```text
config/assets/
├── WINFUT.yaml
├── WDOFUT.yaml
├── PETR4.yaml
├── VALE3.yaml
├── ITUB3.yaml
├── XAUUSDm.yaml
├── BTCUSDm.yaml
├── EURUSDm.yaml
├── USDJPYm.yaml
├── XAGUSDm.yaml
└── ETHUSDm.yaml
```

Exemplo:

```yaml
symbol: WINFUT
asset_id: 1
market: B3
bridge_port: 11000
enabled: true
timeframes:
  M1:
    enabled: true
    poll_seconds: 10
  M2:
    enabled: true
    poll_seconds: 10
  M5:
    enabled: true
    poll_seconds: 15
  M15:
    enabled: true
    poll_seconds: 30
  H4:
    enabled: true
    poll_seconds: 60
  D1:
    enabled: true
    poll_seconds: 300
```

## A.8 Worker por ativo

O worker deve:

1. adquirir singleton lock por ativo;
2. conectar ao bridge correto;
3. validar `symbol_select`;
4. carregar o último candle persistido;
5. buscar candles recentes;
6. descartar candle aberto;
7. inserir candles com idempotência;
8. comparar último candle fechado por timeframe;
9. publicar evento apenas quando houver candle novo;
10. atualizar heartbeat;
11. aplicar retry por timeframe;
12. continuar processando outros timeframes se um falhar.

## A.9 Concorrência interna

Usar `asyncio` ou `ThreadPoolExecutor` com limite.

Recomendação:

```text
max_concurrent_timeframes_per_asset = 3
```

Não abrir uma conexão MT5 por timeframe. Compartilhar a sessão de bridge dentro do worker do ativo com proteção adequada.

## A.10 Processador de eventos

O processador deve usar claim transacional:

```sql
SELECT ...
FROM technical_engine_candle_events
WHERE status='PENDING'
  AND available_at <= NOW(6)
ORDER BY available_at
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

Fluxo:

```text
claim
→ status PROCESSING
→ executar pipeline aplicável
→ persistir resultados
→ status COMPLETED
```

Em falha:

```text
attempts += 1
available_at = now + backoff
```

Backoff sugerido:

```text
1s, 5s, 15s, 60s, 300s
```

Após cinco falhas:

```text
status = DEAD
```

## A.11 Roteamento por timeframe

```text
M1  → atualizar preço/scanner/freshness
M2  → atualizar trigger e contexto fino
M5  → executar SMC V2 + Elliott/Wyckoff + estudo + plano
M15 → atualizar estrutura e contexto
H4  → atualizar contexto HTF
D1  → atualizar contexto macro
```

O comportamento exato deve respeitar a arquitetura atual. Não recalcular timeframes que não fecharam candle.

## A.12 Systemd

Criar template:

```text
deploy/systemd/smc-asset-collector@.service
```

Exemplo:

```ini
[Unit]
Description=SMC Asset Collector %i
After=network-online.target mysql.service
Wants=network-online.target

[Service]
Type=simple
User=bimaq
WorkingDirectory=/home/bimaq/projetos/smc_trader_system
EnvironmentFile=/home/bimaq/projetos/smc_trader_system/.env
ExecStart=/home/bimaq/projetos/smc_trader_system/venv/bin/python \
  -m services.asset_collector.cli --symbol %i
Restart=always
RestartSec=5
TimeoutStopSec=30
KillSignal=SIGTERM
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
```

Criar:

```text
deploy/systemd/smc-candle-event-processor.service
```

## A.13 Migração segura

Não desligar imediatamente `run_b3.py` e `run_forex.py`.

Etapas:

```text
A1. Implementar workers em shadow.
A2. Workers leem MT5, mas não inserem/publicam.
A3. Comparar candles com coletores atuais.
A4. Ativar insert em tabela de shadow.
A5. Ativar eventos shadow.
A6. Validar paridade por 24h.
A7. Ativar um ativo canário.
A8. Desligar esse ativo no coletor legado.
A9. Validar 24h.
A10. Migrar os demais ativos progressivamente.
```

## A.14 Testes

Criar no mínimo:

```text
tests/test_asset_collector/
tests/test_candle_event_processor/
```

Casos obrigatórios:

- candle aberto não gera evento;
- candle fechado gera um evento;
- repetição não duplica evento;
- ativos independentes;
- falha em M15 não impede M1;
- bridge desconectado gera retry;
- reconnect funciona;
- insert idempotente;
- timezone normalizado;
- evento canônico;
- claim concorrente sem duplicidade;
- dead-letter após limite;
- shutdown gracioso;
- heartbeat atualizado;
- config inválida bloqueia inicialização;
- symbol alias correto;
- ativo desabilitado não inicia;
- pipeline não roda para candle já processado.

## A.15 Métricas

Registrar por ativo:

```text
collector_cycle_duration_ms
candles_fetched_total
candles_inserted_total
events_published_total
events_duplicate_total
bridge_errors_total
db_errors_total
last_closed_candle_age_seconds
worker_heartbeat_age_seconds
```

## A.16 Critérios de pronto

- 11 serviços de ativo disponíveis.
- Cada ativo possui heartbeat independente.
- Todos os timeframes configurados são monitorados.
- Nenhum candle aberto é persistido como fechado.
- Nenhum evento duplicado em 24 horas.
- Paridade com coletores legados ≥ 99,99%.
- Falha de um ativo não interrompe os demais.
- Processador recupera após reinício.
- Dead-letter operacional.
- Testes novos passando.
- Suíte crítica sem regressão.
- Relatório de migração criado.

## A.17 Rollback

Rollback deve:

1. parar templates de asset worker;
2. reativar `run_b3.py` e `run_forex.py`;
3. manter tabelas novas sem apagá-las;
4. marcar eventos pendentes como `PAUSED` ou interromper consumer;
5. validar último candle;
6. documentar o motivo.

## A.18 Relatório obrigatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_A_MICROSSERVICOS_ATIVO.md
```

---

# FASE B — OPPORTUNITYEVIDENCEBUNDLEV1

## B.1 Objetivo

Criar um pacote imutável contendo todas as evidências técnicas utilizadas no momento em que o scanner gera uma oportunidade.

## B.2 Regra principal

O bundle deve representar o estado no momento da detecção.

Resultados posteriores nunca podem alterar:

- candles congelados;
- zonas congeladas;
- entrada original;
- stop original;
- alvos originais;
- truth hash;
- engine versions;
- config hashes.

Atualizações posteriores pertencem ao lifecycle, não ao bundle original.

## B.3 Estrutura de diretórios

Criar:

```text
technical_engine/
└── opportunity_evidence/
    ├── __init__.py
    ├── models.py
    ├── enums.py
    ├── builder.py
    ├── validator.py
    ├── canonicalizer.py
    ├── hashing.py
    ├── persistence.py
    ├── repositories.py
    ├── serializer.py
    ├── outbox.py
    └── errors.py
```

## B.4 Contrato `OpportunityEvidenceBundleV1`

Campos obrigatórios:

```json
{
  "schema_version": "1.0",
  "evidence_bundle_id": "evb-...",
  "opportunity_id": "opp-...",
  "signal_id": "sig-...",
  "alert_id": "alert-...",
  "operational_plan_id": "plan-...",
  "envelope_id": "env-v2-...",
  "technical_truth_hash": "sha256:...",
  "bundle_hash": "sha256:...",
  "asset": {
    "asset_id": 1,
    "symbol": "WINFUT",
    "market": "B3",
    "currency": "BRL"
  },
  "timeframes": {
    "feed": "M1",
    "trigger": "M2",
    "base": "M5",
    "structure": "M15",
    "context": ["H4", "D1"]
  },
  "timing": {
    "detected_at": "",
    "opportunity_time": "",
    "base_candle_time": "",
    "feed_candle_time": "",
    "available_at": ""
  },
  "decision": {
    "direction": "ALTISTA",
    "readiness": "PRONTO",
    "proximity": "IMINENTE",
    "severity": "HIGH",
    "confidence_label": "MEDIA",
    "action": "COMPRA"
  },
  "levels": {
    "current_price": 0.0,
    "entry_low": 0.0,
    "entry_high": 0.0,
    "entry_reference": 0.0,
    "stop": 0.0,
    "tp1": 0.0,
    "tp2": 0.0,
    "tp3": 0.0,
    "rr_tp1": 0.0,
    "rr_tp2": 0.0,
    "rr_tp3": 0.0
  },
  "risk": {
    "risk_points": 0.0,
    "risk_percent": null,
    "contracts": null,
    "invalidation_rule": "",
    "trailing_rule": ""
  },
  "input_refs": {
    "smc_run_id": "",
    "elliott_run_id": "",
    "wyckoff_run_id": "",
    "contextual_run_id": "",
    "memory_run_id": null
  },
  "engine_versions": {
    "smc": "",
    "elliott": "",
    "wyckoff": "",
    "study_gateway": "",
    "risk_management": "",
    "scanner": ""
  },
  "config_hashes": {},
  "evidences": {
    "smc": [],
    "elliott": [],
    "wyckoff": [],
    "contextual": [],
    "mtf": [],
    "risk_filters": []
  },
  "zones": {
    "fvg": [],
    "order_blocks": [],
    "bpr": [],
    "liquidity": [],
    "sessions": [],
    "retracements": [],
    "previous_high_low": []
  },
  "structure": {
    "bos_choch": [],
    "swings": []
  },
  "market_context": {},
  "hit_rates": {
    "available": false,
    "sample_size": 0,
    "labels": [],
    "expectancy_r": null
  },
  "chart_spec_id": null,
  "chart_snapshot_id": null,
  "narrative": {
    "summary": "",
    "smc_explanation": "",
    "elliott_explanation": "",
    "wyckoff_explanation": "",
    "risk_explanation": ""
  },
  "guardrails": {
    "shadow_only": true,
    "can_promote_trade": false,
    "apply_automatically": false,
    "llm_decision_used": false,
    "anti_lookahead": true
  },
  "created_at": ""
}
```

## B.5 Evidências selecionadas

Não incluir todas as zonas históricas. Selecionar apenas:

- zonas visíveis na janela do gráfico;
- zonas referenciadas pelo plano;
- estrutura relevante;
- Elliott ativo;
- eventos Wyckoff relevantes;
- níveis de liquidez próximos;
- contexto HTF utilizado;
- blockers e reasons.

Persistir `selection_reason` para cada evidência.

## B.6 Migrations VPS

Criar:

```text
technical_engine_opportunity_evidence_bundles_shadow
technical_engine_opportunity_evidence_items_shadow
technical_engine_opportunity_evidence_outbox_shadow
```

### B.6.1 Bundle

Campos principais:

```text
evidence_bundle_id UNIQUE
opportunity_id UNIQUE
schema_version
symbol
asset_id
detected_at
envelope_id
operational_plan_id
technical_truth_hash
bundle_hash
bundle_json
status
chart_status
sync_status
created_at
updated_at
```

### B.6.2 Itens normalizados

Opcional, mas recomendado para auditoria:

```text
item_id
evidence_bundle_id
category
subtype
source_ref
selection_reason
payload_json
sort_order
```

### B.6.3 Outbox

```text
outbox_id
evidence_bundle_id
event_type
payload_hash
status
attempts
available_at
last_error
created_at
sent_at
```

## B.7 Integração com scanner

No `scan_once()`:

```text
1. scanner gera signal;
2. scanner persiste alert;
3. gera opportunity_id;
4. chama EvidenceBundleBuilder;
5. bundle validado;
6. bundle persistido;
7. evento OPPORTUNITY_EVIDENCE_CREATED no outbox;
8. somente então a oportunidade fica elegível para notificação.
```

Não bloquear todo o scanner por uma falha de renderer. Separar:

```text
OPPORTUNITY_DETECTED
EVIDENCE_BUILD_PENDING
EVIDENCE_READY
CHART_PENDING
READY_TO_SYNC
```

## B.8 Validação

O validator deve bloquear bundle se:

- `technical_truth_hash` ausente;
- `envelope_id` ausente;
- entrada ausente;
- stop ausente;
- direção inválida;
- timestamp futuro;
- evidência com `available_at > detected_at`;
- run stale;
- schema incompatível;
- hash não reproduzível;
- `llm_decision_used=True`.

## B.9 Rebuild

Criar ferramenta:

```bash
python tools/rebuild_opportunity_evidence.py --opportunity-id <id>
```

Uso permitido apenas para diagnóstico.

O rebuild deve gerar um novo bundle com novo ID e referência:

```text
supersedes_bundle_id
rebuild_reason
```

Nunca sobrescrever bundle original.

## B.10 Testes

Casos obrigatórios:

- bundle determinístico;
- hash estável;
- mesma oportunidade não duplica bundle;
- anti-lookahead;
- referência aos runs;
- seleção de zonas;
- níveis de risco;
- ausência de LLM decisora;
- bundle inválido bloqueado;
- outbox idempotente;
- serialização e desserialização;
- compatibilidade de schema;
- bundle original imutável.

## B.11 Critérios de pronto

- Bundle gerado para oportunidade de teste.
- Hash reproduzível.
- Todas as referências presentes.
- Nenhum dado futuro.
- Bundle persistido.
- Outbox criado.
- Scanner continua funcional se bundle falhar.
- Erros visíveis no health.
- Testes e regressão aprovados.

## B.12 Rollback

Desabilitar feature flag:

```text
OPPORTUNITY_EVIDENCE_ENABLED=false
```

Scanner volta a gerar apenas alertas antigos, sem apagar bundles já criados.

## B.13 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_B_EVIDENCE_BUNDLE.md
```

---

# FASE C — CHARTSNAPSHOTRENDERER + ARMAZENAMENTO

## C.1 Objetivo

Gerar uma representação visual congelada da oportunidade e salvar:

1. especificação JSON reconstruível;
2. imagem de alta resolução;
3. thumbnail otimizada para o aplicativo.

## C.2 Princípio

O renderer não calcula análise técnica. Ele apenas renderiza dados do bundle.

## C.3 Estrutura

```text
technical_engine/
└── chart_snapshot/
    ├── __init__.py
    ├── models.py
    ├── chart_spec_builder.py
    ├── renderer.py
    ├── plotly_renderer.py
    ├── image_optimizer.py
    ├── storage.py
    ├── local_storage.py
    ├── remote_storage.py
    ├── manifest.py
    ├── validator.py
    └── errors.py
```

## C.4 Contrato `OpportunityChartSpecV1`

```json
{
  "schema_version": "1.0",
  "chart_spec_id": "chs-...",
  "evidence_bundle_id": "evb-...",
  "symbol": "WINFUT",
  "timeframe": "M5",
  "timezone": "America/Sao_Paulo",
  "window": {
    "start": "",
    "end": "",
    "detection_index": 0,
    "candles_before": 120,
    "candles_after": 0
  },
  "candles": [],
  "indicators": {
    "ema20": [],
    "ema200": [],
    "atr14": [],
    "rsi14": []
  },
  "overlays": {
    "fvg": [],
    "order_blocks": [],
    "bpr": [],
    "liquidity": [],
    "bos_choch": [],
    "swings": [],
    "elliott": [],
    "wyckoff": [],
    "sessions": []
  },
  "trade_levels": {
    "entry_zone": {},
    "stop": {},
    "targets": [],
    "current_price": {},
    "detection_marker": {}
  },
  "legend": [],
  "annotations": [],
  "style_profile": "MAXIMUS_MOBILE_DARK_V1",
  "created_at": "",
  "chart_spec_hash": "sha256:..."
}
```

## C.5 Janela recomendada

Para M5:

```text
120 candles antes da detecção
0 candles depois no snapshot original
```

Para reconstrução no detalhe, o app pode buscar candles posteriores separadamente, mas o snapshot original deve terminar no momento detectado.

## C.6 Renderizações

Gerar três artefatos:

```text
thumbnail: 640 × 360 WebP
mobile:    1080 × 1350 WebP
full:      1600 × 900 PNG ou WebP
```

Qualidade WebP:

```text
quality = 82
```

## C.7 Renderer padrão

Usar uma interface:

```python
class ChartRenderer(Protocol):
    def render(self, spec: OpportunityChartSpecV1) -> RenderedChart:
        ...
```

Implementação inicial recomendada:

```text
PlotlyKaleidoRenderer
```

Motivos:

- stack já utiliza Plotly;
- suporta candlestick;
- suporta shapes;
- execução headless;
- permite exportar PNG/WebP.

O código não deve depender do dashboard Dash.

## C.8 Regras visuais

Obrigatório:

- candles legíveis;
- fundo escuro Maximus;
- entrada destacada;
- stop vermelho;
- TP1/TP2/TP3 distintos;
- detecção marcada por linha vertical;
- zonas com labels;
- legenda reduzida;
- Elliott sem poluição;
- Wyckoff com eventos essenciais;
- níveis sobrepostos ordenados por prioridade;
- colisão de labels tratada;
- eixo temporal sem gaps inúteis;
- título com símbolo, timeframe e momento da detecção;
- disclaimer visual curto.

## C.9 Camadas e prioridade

Ordem de renderização:

```text
1. sessões/background
2. FVG/BPR
3. OB
4. liquidez
5. candles
6. EMA
7. BOS/CHOCH
8. Elliott
9. Wyckoff
10. entrada/stop/alvos
11. marcador de detecção
12. labels
```

## C.10 Manifesto

Criar `ChartSnapshotManifestV1`:

```json
{
  "chart_snapshot_id": "chart-...",
  "chart_spec_id": "chs-...",
  "evidence_bundle_id": "evb-...",
  "renderer": "plotly-kaleido",
  "renderer_version": "",
  "style_profile": "",
  "files": [
    {
      "variant": "thumbnail",
      "mime_type": "image/webp",
      "width": 640,
      "height": 360,
      "size_bytes": 0,
      "sha256": "",
      "storage_key": ""
    }
  ],
  "created_at": ""
}
```

## C.11 Armazenamento

Criar interface:

```python
class EvidenceStorage(Protocol):
    def put(self, key: str, content: bytes, metadata: dict) -> StoredObject:
        ...
```

Implementações:

```text
LocalEvidenceStorage
LaravelEvidenceStorage
S3CompatibleEvidenceStorage
```

### MVP

Usar upload privado para Laravel:

```text
POST /api/internal/opportunity-evidence/{bundleId}/artifacts
```

Protegido por HMAC.

Laravel armazena via `Storage` em disk privado:

```text
storage/app/private/opportunity-evidence/
```

Não usar URL pública permanente.

## C.12 Estrutura de storage key

```text
opportunities/<YYYY>/<MM>/<symbol>/<opportunity_id>/
  bundle.json
  chart-spec.json
  chart-thumbnail.webp
  chart-mobile.webp
  chart-full.webp
  manifest.json
```

## C.13 Migrations VPS

Criar:

```text
technical_engine_opportunity_chart_specs_shadow
technical_engine_opportunity_chart_snapshots_shadow
technical_engine_opportunity_artifacts_shadow
```

## C.14 Migrations Laravel

Criar:

```text
opportunity_evidence_bundles
opportunity_evidence_artifacts
```

Campos essenciais:

```text
bundle_id
opportunity_id
schema_version
bundle_hash
technical_truth_hash
payload_json
status
created_at
```

Artifacts:

```text
artifact_id
bundle_id
variant
mime_type
width
height
size_bytes
sha256
storage_disk
storage_path
created_at
```

## C.15 Endpoint interno de upload

```text
POST /api/internal/opportunity-evidence
POST /api/internal/opportunity-evidence/{bundle}/artifacts
POST /api/internal/opportunity-evidence/{bundle}/complete
```

O complete deve validar:

- bundle recebido;
- artefatos obrigatórios;
- hashes;
- tamanho máximo;
- MIME;
- opportunity existente;
- idempotência.

## C.16 Limites

```text
bundle JSON: máximo 2 MB
chart spec: máximo 5 MB
thumbnail: máximo 300 KB
mobile: máximo 1,5 MB
full: máximo 3 MB
```

## C.17 Falhas

Estados:

```text
CHART_PENDING
CHART_RENDERING
CHART_READY
CHART_FAILED_RETRYABLE
CHART_FAILED_FINAL
UPLOAD_PENDING
UPLOAD_COMPLETED
```

## C.18 Testes

- ChartSpec determinístico.
- Nenhum candle posterior.
- Todas as coordenadas válidas.
- Imagem criada.
- Dimensões corretas.
- Hash correto.
- Labels essenciais presentes.
- Renderer tolera ausência de Elliott.
- Renderer tolera ausência de Wyckoff.
- Renderer bloqueia candles vazios.
- Upload HMAC.
- MIME inválido rejeitado.
- Hash divergente rejeitado.
- Retry de upload.
- Storage privado.
- Idempotência.

## C.19 Critérios de pronto

- Oportunidade de teste gera três imagens.
- Bundle e ChartSpec possuem hashes.
- Laravel recebe e valida.
- Arquivos ficam privados.
- Thumbnail carrega em menos de 1 segundo em rede de teste.
- Imagem mobile é legível em tela real.
- Nenhum dado futuro aparece.
- Testes aprovados.

## C.20 Rollback

```text
CHART_SNAPSHOT_ENABLED=false
```

Bundles continuam sendo criados, mas sem imagens. Não apagar artefatos existentes.

## C.21 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_C_CHART_RENDERER.md
```

---

# FASE D — API MOBILE DE EVIDÊNCIAS + GRÁFICO NO DETALHE

## D.1 Objetivo

Expor as evidências para o App Android e apresentar o gráfico congelado na tela de detalhe.

## D.2 Backend Laravel

Criar controllers:

```text
MobileOpportunityEvidenceController.php
MobileOpportunityArtifactController.php
```

Rotas:

```text
GET /api/mobile/opportunities/{id}/evidence
GET /api/mobile/opportunities/{id}/chart
GET /api/mobile/opportunities/{id}/artifacts/{variant}
```

## D.3 Autorização

Usuário deve:

- estar autenticado;
- estar ativo;
- possuir plano/licença compatível;
- ter acesso ao ativo;
- não estar bloqueado;
- respeitar limite do plano.

Criar policy:

```text
OpportunityPolicy::viewEvidence()
```

## D.4 Resposta `MobileOpportunityEvidenceV1`

```json
{
  "data": {
    "opportunity": {},
    "evidence_bundle": {
      "id": "",
      "schema_version": "1.0",
      "technical_truth_hash": "",
      "detected_at": "",
      "summary": {},
      "levels": {},
      "market_context": {},
      "evidences": {},
      "hit_rates": {},
      "lifecycle": {}
    },
    "chart": {
      "thumbnail_url": "",
      "mobile_url": "",
      "full_url": "",
      "expires_at": "",
      "width": 1080,
      "height": 1350
    },
    "disclaimer": ""
  }
}
```

Não retornar o JSON técnico integral ao cliente se houver campos internos sensíveis. Criar transformer explícito.

## D.5 URLs

Preferir endpoint autenticado que faça stream do arquivo.

Alternativa:

- gerar signed URL de curta duração;
- validade de 5 minutos;
- não expor caminho interno;
- renovar ao abrir a tela.

## D.6 Cache

Headers:

```text
ETag: sha256 do artefato
Cache-Control: private, max-age=300
```

## D.7 Android — dependências

Adicionar biblioteca de imagens compatível com Compose/KMP Android.

Recomendação:

```text
Coil 3
```

Criar:

```text
data/dto/OpportunityEvidenceDto.kt
data/remote/OpportunityEvidenceRemoteDataSource.kt
data/repository/OpportunityEvidenceRepositoryImpl.kt
domain/model/OpportunityEvidenceModels.kt
domain/repository/OpportunityEvidenceRepository.kt
domain/usecase/GetOpportunityEvidenceUseCase.kt
```

## D.8 Tela de detalhe

Atualizar:

```text
OpportunityDetailScreen.kt
OpportunityDetailViewModel.kt
```

Seções:

```text
1. Cabeçalho
2. Estado atual
3. Gráfico
4. Entrada/stop/alvos
5. Resumo técnico
6. Evidências SMC
7. Elliott
8. Wyckoff
9. Contexto MTF
10. Taxa histórica
11. Disclaimer
```

## D.9 Componente de gráfico

Criar:

```text
OpportunityChartCard.kt
ZoomableEvidenceImage.kt
ChartLegend.kt
EvidenceLayerChips.kt
```

MVP:

- imagem congelada;
- pinch-to-zoom;
- double tap;
- pan;
- loading placeholder;
- retry;
- botão tela cheia;
- botão “ver legenda”.

## D.10 Estado da UI

```kotlin
sealed interface EvidenceUiState {
    data object Idle
    data object Loading
    data class Success(...)
    data class Partial(...)
    data class Error(...)
}
```

`Partial` deve mostrar os níveis mesmo se a imagem falhar.

## D.11 Offline/cache

Cache local limitado:

```text
últimas 20 oportunidades abertas
máximo 100 MB
LRU eviction
```

Não armazenar token em cache comum.

## D.12 Acessibilidade

- content description;
- contraste;
- labels textuais;
- não depender apenas de cor;
- zoom;
- fonte escalável;
- explicação das siglas.

## D.13 Testes backend

- autenticação;
- autorização por plano;
- bundle inexistente;
- artifact inexistente;
- signed URL;
- ETag;
- acesso de outro usuário;
- rate limit;
- payload transformer;
- storage privado;
- opportunity expirada ainda consultável no histórico.

## D.14 Testes Android

- DTO parsing;
- ViewModel;
- loading/error/success;
- imagem;
- deep link com bundle pronto;
- deep link com bundle pendente;
- rotação da tela;
- offline cache;
- logout limpa dados protegidos;
- acessibilidade básica.

## D.15 Critérios de pronto

- App abre detalhe e mostra gráfico.
- Gráfico corresponde ao bundle.
- Níveis batem com o plano original.
- Cliente não recebe campos internos.
- Acesso exige autenticação.
- Cache funciona.
- Falha de imagem não bloqueia texto.
- Testes aprovados.
- APK debug instalado em emulador e aparelho.

## D.16 Rollback

Feature flag no backend:

```text
MOBILE_EVIDENCE_ENABLED=false
```

Feature flag no app:

```text
evidenceChartEnabled
```

## D.17 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_D_API_APP_GRAFICO.md
```

---

# FASE E — FCM REAL + DEEP LINK + ANDROID FÍSICO

## E.1 Objetivo

Comprovar o fluxo real:

```text
Laravel
→ Firebase
→ dispositivo físico
→ toque
→ app
→ oportunidade
→ gráfico
```

## E.2 Canonicalização de deep link

Adotar como deep link primário de produção:

```text
https://maximustrade.com.br/app/opportunities/{id}
```

Configurar Android App Links com:

```text
https://maximustrade.com.br/.well-known/assetlinks.json
```

Manter fallback:

```text
maximus://opportunity/{id}
```

O FCM deve enviar o link HTTPS canônico.

## E.3 Firebase

Validar:

- projeto correto;
- package `br.com.maximustrade.signals`;
- SHA-256 do certificado debug;
- SHA-256 do certificado release;
- `google-services.json`;
- service account;
- projeto e application ID;
- API FCM HTTP v1 habilitada.

## E.4 Backend

Atualizar payload:

```json
{
  "type": "opportunity_alert",
  "opportunity_id": "123",
  "alert_id": "...",
  "symbol": "WINFUT",
  "direction": "ALTISTA",
  "proximity": "IMINENTE",
  "timeframe": "M5",
  "opportunity_time": "",
  "sent_at": "",
  "deep_link": "https://maximustrade.com.br/app/opportunities/123",
  "evidence_status": "READY"
}
```

Enviar push apenas quando:

```text
opportunity.status = active
evidence.status = READY
chart.status = READY
```

Permitir exceção por flag para push sem gráfico em caso de contingência, mas padrão deve ser bloqueado.

## E.5 Queue worker

Garantir:

```text
php artisan queue:work
```

Executado como serviço supervisionado.

Criar:

```text
maximus-queue-worker.service
```

Configurar:

- restart;
- timeout;
- tries;
- backoff;
- failed jobs;
- health.

## E.6 Device registration

Fluxo:

```text
onNewToken
→ se usuário autenticado, registra
→ se não autenticado, guarda token pendente localmente
→ após login, registra
```

Campos:

```text
device_id
platform
fcm_token
app_version
os_version
device_model
timezone
locale
last_seen_at
```

Nunca logar token completo.

## E.7 Android

Configurar:

- `POST_NOTIFICATIONS`;
- channel `opportunity_alerts`;
- prioridade HIGH para IMINENTE/NA_ZONA;
- prioridade DEFAULT para OBSERVANDO;
- PendingIntent imutável;
- comportamento app aberto/fechado;
- deep link preservado após login;
- fallback para dashboard se oportunidade inválida.

## E.8 Matriz de teste físico

Testar pelo menos:

| Cenário | Resultado esperado |
|---|---|
| App fechado | Notificação abre detalhe |
| App em background | Abre detalhe |
| App em foreground | Mostra in-app banner ou notificação |
| Usuário deslogado | Faz login e retorna ao detalhe |
| Token renovado | Device atualizado |
| Sem permissão | App orienta ativação |
| Oportunidade expirada | Abre histórico/detalhe |
| Bundle pendente | Mostra “gráfico em preparação” |
| Rede lenta | Loading e retry |
| Sem internet | Cache ou erro amigável |
| Dois devices | Ambos recebem conforme preferência |
| Quiet hours | Push bloqueado/logado |
| Limite por hora | Push skipped com motivo |
| Token inválido | Device desativado |

## E.9 Instrumentação

Registrar:

```text
push_queued_at
push_sent_at
fcm_message_id
notification_received_at
notification_opened_at
detail_loaded_at
chart_loaded_at
```

No app, enviar eventos de abertura para backend:

```text
POST /api/mobile/opportunities/{id}/opened
POST /api/mobile/opportunities/{id}/chart-viewed
```

## E.10 Teste E2E controlado

Criar comando Laravel:

```bash
php artisan maximus:test-opportunity-push \
  --user=<id> \
  --opportunity=<id> \
  --device=<id>
```

Criar comando Python:

```bash
python tools/create_controlled_opportunity_e2e.py \
  --symbol WINFUT \
  --send
```

O teste deve usar oportunidade marcada:

```text
test_mode=true
```

Não misturar com métricas comerciais reais.

## E.11 Critérios de pronto

- Push recebido em Android físico.
- Toque abre URL correta.
- Login intermediário preserva destino.
- Detalhe e gráfico carregam.
- Eventos de opened/chart-viewed chegam ao backend.
- Token renewal funciona.
- Quiet hours funciona.
- Invalid token é tratado.
- E2E repetido três vezes sem intervenção manual.

## E.12 Rollback

```text
FCM_ENABLED=false
```

Manter oportunidades disponíveis via app sem push.

## E.13 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_E_FCM_ANDROID_FISICO.md
```

Anexar:

- IDs de teste;
- timestamps;
- capturas de tela;
- logs sanitizados;
- versões do app;
- modelo do aparelho;
- resultado de cada cenário.

---

# FASE F — LIFECYCLE E OUTCOMES DA OPORTUNIDADE

## F.1 Objetivo

Acompanhar a oportunidade desde a detecção até expiração, invalidação, stop ou alvos, sem alterar o bundle original.

## F.2 State machine

Estados principais:

```text
DETECTED
EVIDENCE_PENDING
EVIDENCE_READY
NOTIFICATION_PENDING
NOTIFIED
OPENED
WATCHING
ENTRY_TOUCHED
ENTRY_CONFIRMED
TP1_REACHED
TP2_REACHED
TP3_REACHED
STOP_REACHED
INVALIDATED
EXPIRED
CANCELLED
ERROR
```

## F.3 Transições permitidas

Exemplo:

```text
DETECTED → EVIDENCE_PENDING
EVIDENCE_PENDING → EVIDENCE_READY | ERROR
EVIDENCE_READY → NOTIFICATION_PENDING
NOTIFICATION_PENDING → NOTIFIED | ERROR
NOTIFIED → OPENED | WATCHING
WATCHING → ENTRY_TOUCHED | INVALIDATED | EXPIRED
ENTRY_TOUCHED → ENTRY_CONFIRMED | STOP_REACHED | INVALIDATED
ENTRY_CONFIRMED → TP1_REACHED | STOP_REACHED
TP1_REACHED → TP2_REACHED | STOP_REACHED
TP2_REACHED → TP3_REACHED | STOP_REACHED
```

Estados terminais:

```text
TP3_REACHED
STOP_REACHED
INVALIDATED
EXPIRED
CANCELLED
```

## F.4 Eventos

Contrato `OpportunityLifecycleEventV1`:

```json
{
  "schema_version": "1.0",
  "lifecycle_event_id": "",
  "opportunity_id": "",
  "event_type": "ENTRY_TOUCHED",
  "previous_state": "WATCHING",
  "new_state": "ENTRY_TOUCHED",
  "event_time": "",
  "market_price": 0.0,
  "candle_time": "",
  "timeframe": "M1",
  "source": "LIFECYCLE_MONITOR",
  "reason_code": "",
  "payload": {},
  "created_at": ""
}
```

## F.5 Monitor

Criar:

```text
technical_engine/
└── opportunity_lifecycle/
    ├── models.py
    ├── state_machine.py
    ├── monitor.py
    ├── price_evaluator.py
    ├── outcome_evaluator.py
    ├── persistence.py
    ├── repositories.py
    ├── heartbeat.py
    └── cli.py
```

Serviço:

```text
smc-opportunity-lifecycle.service
```

## F.6 Fonte de preço

Usar M1 fechado para outcome oficial.

Preço tick pode ser usado apenas para aviso visual, não para outcome oficial, salvo contrato específico futuro.

## F.7 Regras de entrada

Definir no plano:

```text
entry_low
entry_high
entry_reference
entry_confirmation_mode
```

Estados:

```text
ENTRY_TOUCHED: wick entra na zona
ENTRY_CONFIRMED: regra configurada satisfeita
```

## F.8 Stop e alvos

Usar valores congelados do bundle.

Nunca recalcular stop após a oportunidade.

Trailing deve gerar eventos separados e não reescrever stop original.

## F.9 Candle ambíguo

Se stop e alvo forem tocados no mesmo candle e não houver dados menores suficientes:

```text
assumir STOP primeiro
```

Persistir:

```text
ambiguous_bar=true
resolution_policy=STOP_FIRST_CONSERVATIVE
```

## F.10 Expiração

Oportunidade expira por:

- tempo máximo;
- plano expirado;
- contexto invalidado;
- sessão encerrada;
- entrada não tocada;
- regra específica.

## F.11 Outcome

Contrato `OpportunityOutcomeV1`:

```json
{
  "outcome_id": "",
  "opportunity_id": "",
  "final_state": "TP2_REACHED",
  "entry_touched": true,
  "entry_confirmed": true,
  "tp1_reached": true,
  "tp2_reached": true,
  "tp3_reached": false,
  "stop_reached": false,
  "invalidated": false,
  "expired": false,
  "max_favorable_excursion_r": 2.4,
  "max_adverse_excursion_r": 0.6,
  "realized_outcome_r": 2.0,
  "bars_to_entry": 6,
  "bars_to_resolution": 38,
  "ambiguous_bar": false,
  "resolved_at": "",
  "policy_version": "1.0"
}
```

## F.12 Migrations VPS

Criar:

```text
technical_engine_opportunity_lifecycle_events_shadow
technical_engine_opportunity_outcomes_shadow
technical_engine_opportunity_lifecycle_heartbeats_shadow
```

## F.13 Sync Laravel

Criar endpoints HMAC:

```text
POST /api/internal/opportunities/{id}/lifecycle
POST /api/internal/opportunities/{id}/outcome
```

Tabelas Laravel:

```text
opportunity_lifecycle_events
opportunity_outcomes
```

## F.14 App

Mostrar:

- status atual;
- linha do tempo;
- resultado final;
- preço detectado;
- preço de entrada;
- alvo atingido;
- horário dos eventos.

Não enviar push para todo evento. Preferências futuras podem permitir:

- entrada tocada;
- TP1;
- stop;
- invalidação.

## F.15 Métricas

```text
opportunities_detected
evidence_ready
push_sent
opened
entry_touched
entry_confirmed
tp1
tp2
tp3
stop
invalidated
expired
open_rate
entry_rate
resolution_rate
median_time_to_entry
median_time_to_resolution
```

## F.16 Testes

- transições válidas;
- transições inválidas bloqueadas;
- idempotência;
- candle ambíguo;
- gap de preço;
- entrada por wick;
- direção bullish/bearish;
- stop e TP;
- expiração;
- eventos fora de ordem;
- replay;
- restart do monitor;
- outcome imutável após terminal;
- sync Laravel;
- timeline mobile.

## F.17 Critérios de pronto

- Opportunity percorre lifecycle completo.
- Estados persistidos.
- Outcome reproduzível.
- Bundle original permanece igual.
- Eventos idempotentes.
- App apresenta timeline.
- Métricas disponíveis.
- Testes aprovados.

## F.18 Rollback

Desativar monitor:

```text
OPPORTUNITY_LIFECYCLE_ENABLED=false
```

Não apagar eventos existentes.

## F.19 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_F_LIFECYCLE_OUTCOMES.md
```

---

# FASE G — SOAK TEST + BETA INTERNO

## G.1 Objetivo

Provar estabilidade operacional antes de liberar a usuários externos.

## G.2 Etapas

### G.2.1 Soak técnico

Duração mínima:

```text
7 dias contínuos
```

Ideal:

```text
14 dias
```

### G.2.2 Beta interno

Grupo:

```text
5 a 10 usuários
```

Mercados iniciais:

```text
WINFUT
XAUUSDm
BTCUSDm
```

Timeframes de apresentação:

```text
M5 base
M15/H4 contexto
M2 gatilho
```

## G.3 SLOs mínimos

### Coleta

```text
Disponibilidade por ativo ≥ 99,5%
Atraso M1 em mercado aberto < 120s
Eventos duplicados = 0
Eventos DEAD < 0,1%
```

### Pipeline

```text
p95 candle → estudo < 15s
p95 estudo → oportunidade < 5s
p95 oportunidade → bundle < 5s
p95 bundle → chart < 15s
p95 chart → Laravel < 10s
p95 Laravel → FCM queued < 5s
```

### Mobile

```text
p95 detalhe API < 1,5s
p95 thumbnail < 1,0s
p95 mobile chart < 3,0s em rede 4G razoável
Crash-free sessions ≥ 99%
```

## G.4 Observabilidade

Dashboard operacional deve exibir:

- health de cada asset worker;
- último candle por timeframe;
- fila de eventos;
- eventos DEAD;
- duração do pipeline;
- scanner runs;
- bundles pendentes;
- charts falhos;
- uploads falhos;
- sync;
- queue Laravel;
- FCM;
- devices;
- app opens;
- lifecycle backlog.

## G.5 Alertas

Criar alertas para:

```text
worker sem heartbeat > 3 minutos
M1 atrasado > 3 minutos
eventos DEAD > 0
chart failure rate > 2%
upload failure rate > 2%
queue Laravel parada
FCM failure rate > 5%
disco > 80%
DB connections > 80%
```

## G.6 Testes de falha

Executar controladamente:

- reiniciar asset worker;
- derrubar bridge;
- bloquear MySQL temporariamente;
- reiniciar event processor;
- reiniciar renderer;
- simular falha de upload;
- reiniciar queue Laravel;
- invalidar token FCM;
- desligar internet do Android;
- reiniciar VPS;
- reiniciar Hostinger quando permitido;
- validar recovery.

## G.7 Backup e restore

Backup:

- banco VPS;
- banco Laravel;
- storage de evidências;
- configs;
- migrations;
- systemd;
- Firebase config sem expor secrets.

Testar restauração em ambiente separado.

## G.8 Segurança

Antes do beta:

- rotacionar credenciais antigas;
- remover secrets do histórico Git;
- executar `npm audit`;
- executar `composer audit`;
- revisar permissões Laravel;
- rate limit;
- validar HMAC replay protection;
- testar acesso cruzado entre usuários;
- revisar storage privado;
- validar logs sanitizados.

## G.9 Beta interno

Requisitos:

- termo de uso;
- disclaimer;
- política de privacidade;
- canal de suporte;
- registro de feedback;
- versão do app;
- lista de known issues;
- procedimento de rollback.

## G.10 Feedback

Capturar:

```text
O alerta foi compreensível?
O gráfico ajudou?
Entrada/stop/alvos ficaram claros?
SMC/Elliott/Wyckoff geraram confusão?
A notificação chegou no momento adequado?
O usuário abriu?
O gráfico carregou?
```

## G.11 Critérios de saída

- Sete dias sem incidente crítico.
- Nenhuma perda silenciosa de oportunidade.
- Nenhuma duplicidade de push relevante.
- E2E físico repetível.
- Backup restore validado.
- Crash-free ≥ 99%.
- Alertas operacionais funcionando.
- Feedback dos usuários coletado.
- Bloqueadores P0/P1 resolvidos.

## G.12 Relatórios

```text
docs/roadmap_evidence_mobile/RELATORIO_SOAK_TEST_7_DIAS.md
docs/roadmap_evidence_mobile/RELATORIO_BETA_INTERNO.md
docs/roadmap_evidence_mobile/GO_NO_GO_BETA_EXTERNO.md
```

---

# FASE H — MELHORIA DO SITE

## H.1 Objetivo

Melhorar a experiência web após o fluxo mobile estar estável.

## H.2 Prioridades

### H.2.1 Área do cliente

Criar rotas fora de `/admin`:

```text
/app/dashboard
/app/oportunidades
/app/oportunidades/{id}
/app/historico
/app/preferencias
/app/conta
```

### H.2.2 Detalhe web da oportunidade

Mostrar:

- gráfico;
- entrada;
- stop;
- alvos;
- evidências;
- lifecycle;
- outcome;
- download de relatório, se permitido;
- disclaimer.

Reutilizar dados da API mobile, não criar fonte paralela.

### H.2.3 Admin de evidências

Criar:

```text
/admin/evidencias
/admin/evidencias/{bundle}
```

Permitir:

- buscar bundle;
- verificar hashes;
- ver runs;
- comparar imagem e spec;
- ver erros;
- reprocessar chart;
- reenviar sync;
- nunca alterar bundle original.

### H.2.4 Painel de saúde

Melhorar:

- asset workers;
- candle lag;
- event queues;
- evidence pipeline;
- renderer;
- storage;
- Laravel queue;
- FCM;
- app telemetry.

### H.2.5 Gráfico web

Consolidar `Lightweight Charts v5`.

Implementar overlays a partir do `OpportunityChartSpecV1`:

- FVG;
- OB;
- BPR;
- liquidez;
- BOS/CHOCH;
- Elliott;
- Wyckoff;
- entrada/stop/alvos.

Plotly permanece apenas para ferramentas internas até remoção futura.

### H.2.6 Histórico e busca

Filtros:

- símbolo;
- direção;
- proximity;
- data;
- status;
- outcome;
- timeframe;
- tipo de setup.

### H.2.7 Performance

- paginação;
- indexes;
- ETag;
- lazy loading;
- thumbnail;
- virtualização;
- cache;
- compressão;
- WebSocket apenas onde necessário.

### H.2.8 Comercial

Somente após estabilidade:

- planos;
- limites;
- licença;
- assinatura;
- webhooks;
- trial;
- analytics de conversão.

Não misturar lógica comercial com cálculo técnico.

## H.3 Testes frontend

Adicionar:

```text
Vitest
React Testing Library
Playwright
```

Cobrir:

- login;
- 2FA;
- oportunidade;
- gráfico;
- autorização;
- admin;
- health;
- responsividade;
- erros.

## H.4 API versioning

Introduzir:

```text
/api/v1/mobile/...
/api/v1/internal/...
```

Manter aliases temporários e deprecation headers.

## H.5 Critérios de pronto

- Área do cliente separada.
- Detalhe web usa bundle oficial.
- Gráfico web consistente com imagem.
- Admin audita evidências.
- Testes frontend.
- Performance aceitável.
- Sem duplicar motor técnico.

## H.6 Relatório

```text
docs/roadmap_evidence_mobile/RESULTADO_FASE_H_SITE.md
```

---

# 6. FEATURE FLAGS

Criar configuração central:

```text
ASSET_MICROSERVICES_ENABLED
CANDLE_EVENTS_ENABLED
OPPORTUNITY_EVIDENCE_ENABLED
CHART_SNAPSHOT_ENABLED
EVIDENCE_SYNC_ENABLED
MOBILE_EVIDENCE_ENABLED
FCM_REQUIRE_EVIDENCE_READY
OPPORTUNITY_LIFECYCLE_ENABLED
CUSTOMER_WEB_AREA_ENABLED
```

Feature flags devem permitir ativação por ativo.

Exemplo:

```json
{
  "ASSET_MICROSERVICES_ENABLED": {
    "WINFUT": true,
    "WDOFUT": false
  }
}
```

---

# 7. ESTRATÉGIA DE COMMITS

Uma fase não deve resultar em um único commit gigante.

Padrão:

```text
feat(phase-a): add candle event contracts
feat(phase-a): add asset collector worker
feat(phase-a): add mysql event outbox
test(phase-a): cover idempotency and failure recovery
docs(phase-a): add operational report
```

Não misturar fases no mesmo pull request.

---

# 8. PADRÃO DE RELATÓRIO DE FASE

Todo relatório deve conter:

```markdown
# RESULTADO — FASE X

## 1. Status
## 2. Commit inicial
## 3. Commit final
## 4. Arquivos criados
## 5. Arquivos alterados
## 6. Migrations
## 7. Serviços
## 8. Testes executados
## 9. Resultados
## 10. Evidências operacionais
## 11. Métricas
## 12. Erros encontrados
## 13. Correções
## 14. Pendências
## 15. Riscos
## 16. Rollback
## 17. Critérios de pronto
## 18. Status final
```

Status permitidos:

```text
NAO_INICIADO
EM_EXECUCAO
BLOQUEADO
CONCLUIDO_COM_RESSALVAS
CONCLUIDO
```

Não utilizar “PRONTO PARA PRODUÇÃO” sem Fase G aprovada.

---

# 9. TESTE E2E CANÔNICO FINAL

O teste final deve comprovar:

```text
1. Novo candle fechado no MT5.
2. Asset worker insere candle.
3. Evento CANDLE_CLOSED criado.
4. Processor consome evento.
5. SMC/Elliott/Wyckoff atualizados.
6. TechnicalTruthEnvelopeV2 gerado.
7. OperationalPlanV2 gerado.
8. Scanner cria oportunidade.
9. Bundle gerado.
10. ChartSpec gerado.
11. Imagem gerada.
12. Bundle e imagem enviados ao Laravel.
13. Opportunity atualizada para READY.
14. FCM enviado.
15. Android físico recebe.
16. Toque abre oportunidade.
17. Gráfico carrega.
18. Lifecycle acompanha entrada e outcome.
```

Todos os passos devem compartilhar o mesmo `opportunity_id`.

---

# 10. DEFINIÇÃO DE SUCESSO DO PRIMEIRO PRODUTO

O primeiro produto estará realmente concluído quando:

- o cliente receber uma oportunidade real;
- a notificação abrir a oportunidade correta;
- o gráfico mostrar o estado do mercado no momento da detecção;
- entrada, stop e alvos estiverem claros;
- SMC, Elliott e Wyckoff estiverem visualmente explicados;
- os dados forem auditáveis por hash e run IDs;
- o bundle original permanecer imutável;
- o lifecycle mostrar o resultado;
- o sistema operar vários dias sem intervenção crítica;
- nenhum componente fora do Sistema Local recalcular a análise técnica.

---

# 11. NÃO FAZER DURANTE ESTE ROADMAP

Até concluir a Fase G, não priorizar:

- novos indicadores;
- novos ativos;
- iOS;
- execução automática de ordens;
- copy trading;
- IA decisora;
- créditos para estudos;
- redesign completo do site;
- novos gateways de pagamento;
- alterações no SMC V2 congelado;
- reescrita geral do backend;
- migração prematura para Kubernetes.

---

# 12. CHECKLIST FINAL POR FASE

## FASE A

- [ ] 1 worker por ativo
- [ ] 6 timeframes internos
- [ ] eventos idempotentes
- [ ] outbox
- [ ] consumer
- [ ] heartbeats
- [ ] systemd
- [ ] paridade 24h
- [ ] migração canário
- [ ] relatório

## FASE B

- [ ] bundle versionado
- [ ] hash
- [ ] runs referenciados
- [ ] anti-lookahead
- [ ] persistência
- [ ] outbox
- [ ] scanner integrado
- [ ] testes
- [ ] relatório

## FASE C

- [ ] ChartSpec
- [ ] renderer
- [ ] thumbnail
- [ ] imagem mobile
- [ ] imagem full
- [ ] storage privado
- [ ] upload HMAC
- [ ] hashes
- [ ] relatório

## FASE D

- [ ] APIs mobile
- [ ] policy
- [ ] DTOs Android
- [ ] repository
- [ ] ViewModel
- [ ] gráfico no detalhe
- [ ] zoom/cache
- [ ] testes
- [ ] relatório

## FASE E

- [ ] App Links
- [ ] FCM real
- [ ] queue worker
- [ ] device registration
- [ ] Android físico
- [ ] opened/chart-viewed
- [ ] matriz de teste
- [ ] relatório

## FASE F

- [ ] state machine
- [ ] monitor
- [ ] eventos
- [ ] outcomes
- [ ] sync
- [ ] timeline mobile
- [ ] métricas
- [ ] relatório

## FASE G

- [ ] soak 7 dias
- [ ] SLOs
- [ ] alertas
- [ ] chaos tests
- [ ] backup restore
- [ ] segurança
- [ ] beta interno
- [ ] go/no-go

## FASE H

- [ ] área cliente
- [ ] detalhe web
- [ ] gráfico web
- [ ] admin evidências
- [ ] health
- [ ] testes frontend
- [ ] versionamento API
- [ ] relatório

---

# 13. STATUS FINAL ESPERADO

```text
SISTEMA LOCAL
Microserviços por ativo, eventos idempotentes, Technical Engine determinístico,
scanner, bundle, renderer e lifecycle operacionais.

MAXIMUSTRADER
Hub seguro, persistência de evidências, storage privado, API mobile, FCM,
telemetria e administração.

APP ANDROID
Notificação real, deep link, detalhe completo, gráfico, evidências,
lifecycle e histórico.

PRODUTO
Scanner de oportunidades técnicas explicáveis, auditáveis e visualmente
compreensíveis, sem execução automática de ordens e sem LLM decisora.
```
