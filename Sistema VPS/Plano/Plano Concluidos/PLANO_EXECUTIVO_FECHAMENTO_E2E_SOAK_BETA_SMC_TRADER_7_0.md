# PLANO EXECUTIVO — FECHAMENTO OPERACIONAL, E2E, SOAK TEST E BETA INTERNO

**Projeto:** SMC Trader System 7.0  
**Versão:** 1.0  
**Data-base:** 16 de junho de 2026  
**Origem:** Relatório final do roadmap de microserviços, evidências e mobile  
**Objetivo:** Fechar as pendências reais após a implementação das Fases A–H, comprovar o fluxo ponta a ponta e preparar o sistema para beta interno controlado.  
**Status inicial recomendado:** `CONCLUIDO_COM_RESSALVAS`  
**Escopo de segurança desta versão:** a rotação da credencial SSH exposta fica explicitamente fora do escopo por decisão do responsável pelo projeto.

---

# 0. CONTEXTO

O roadmap principal foi implementado em código, com os seguintes resultados reportados:

- 34 commits no branch `feature/opportunity-evidence-mobile-v1`;
- 94 arquivos alterados;
- 9.102 inserções;
- 299 testes críticos passando;
- 164 testes do SMC Engine V2 passando;
- 11 asset collectors ativos;
- 1 candle event processor ativo;
- 11 ativos × 6 timeframes = 66 pares ativo/timeframe;
- 1.780 eventos processados;
- 1.774 eventos concluídos;
- 6 eventos em retry;
- 9 tabelas shadow criadas;
- Evidence Bundle implementado;
- Chart Renderer implementado;
- APIs mobile implementadas;
- App Android compilando;
- lifecycle implementado;
- área web do cliente implementada localmente.

Entretanto, ainda existem lacunas operacionais e de validação:

1. erro no scanner:
   ```text
   'LatestPriceRef' object has no attribute 'status'
   ```
2. seis eventos em retry sem causa consolidada;
3. coletores legados desativados antes da validação formal de paridade;
4. integração completa `scanner → bundle → chart → upload → Laravel` ainda não comprovada integralmente;
5. site ainda não implantado na Hostinger com as alterações atuais;
6. `assetlinks.json` ainda sem fingerprint SHA-256 final;
7. Android físico ainda não testado;
8. FCM real ainda não comprovado ponta a ponta;
9. lifecycle ainda não validado com uma oportunidade real completa;
10. soak test de sete dias ainda não ocorreu;
11. beta interno ainda não iniciou.

---

# 1. OBJETIVO FINAL

O plano será considerado concluído apenas quando existir evidência real deste fluxo:

```text
MT5 fecha candle
→ asset collector detecta candle fechado
→ CandleClosedEventV1 é persistido
→ candle event processor processa
→ SMC/Elliott/Wyckoff são atualizados
→ TechnicalTruthEnvelopeV2 é gerado
→ OperationalPlanV2 é persistido
→ Opportunity Scanner detecta oportunidade
→ OpportunityEvidenceBundleV1 é criado
→ OpportunityChartSpecV1 é criado
→ ChartSnapshotRenderer gera os artefatos
→ bundle e artefatos são enviados ao Laravel
→ oportunidade fica READY
→ FCM envia push real
→ Android físico recebe
→ toque abre a oportunidade correta
→ gráfico e evidências carregam
→ lifecycle acompanha a oportunidade
→ outcome é persistido
→ sistema opera por sete dias sem incidente crítico
→ beta interno é liberado para grupo controlado
```

---

# 2. FORA DO ESCOPO

Nesta versão do plano, não executar:

- rotação da credencial SSH exposta;
- alteração da política de autenticação SSH da VPS;
- remoção da autenticação por senha;
- migração para Kubernetes;
- inclusão de novos ativos;
- inclusão de novos indicadores;
- alteração do SMC Engine V2;
- execução automática de ordens;
- iOS;
- redesign geral do site;
- novos gateways de pagamento;
- IA com poder decisório.

## 2.1 Regra de proteção

Embora a rotação SSH esteja fora do escopo:

- não imprimir a credencial;
- não incluí-la em comandos;
- não usar `SSHPASS='...'`;
- não salvar senha em scripts;
- não versionar senha;
- não escrever senha em relatórios;
- não alterar a configuração SSH atual sem autorização.

Caso o deploy exija senha interativa, a IA deve interromper somente nesse ponto e solicitar que o usuário execute a autenticação diretamente no terminal, sem enviar a senha pelo chat.

---

# 3. GUARDRAILS INVIOLÁVEIS

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

Também permanece obrigatório:

- SMC Engine V2 congelado;
- Laravel não recalcula análise;
- Android não recalcula análise;
- frontend apenas renderiza;
- nenhum resultado pode alterar o Evidence Bundle original;
- nenhum estado terminal de lifecycle pode ser reescrito silenciosamente.

---

# 4. STATUS CORRETO NO INÍCIO

Registrar antes de executar:

```text
ROADMAP PRINCIPAL: CONCLUIDO_COM_RESSALVAS

FASE A: CONCLUIDO_COM_RESSALVAS
FASE B: CONCLUIDO
FASE C: CONCLUIDO_COM_RESSALVAS
FASE D: CONCLUIDO_COM_RESSALVAS
FASE E: EM_EXECUCAO
FASE F: CONCLUIDO_COM_RESSALVAS
FASE G: EM_EXECUCAO
FASE H: CONCLUIDO_COM_RESSALVAS

GO/NO-GO:
NO-GO para beta externo.
GO para fechamento operacional e validação interna controlada.
```

Não sobrescrever relatórios históricos. Criar documento novo de correção de status.

---

# 5. ORDEM DE EXECUÇÃO

```text
FASE 0 — Baseline e auditoria pós-roadmap
FASE 1 — Corrigir scanner e eventos em retry
FASE 2 — Validar continuidade da coleta por ativo/timeframe
FASE 3 — Fechar integração Evidence Bundle + Chart + Upload
FASE 4 — Implantar backend/frontend na Hostinger
FASE 5 — Configurar App Links e validar FCM em Android físico
FASE 6 — Validar lifecycle e outcome reais
FASE 7 — Executar soak test formal de 7 dias
FASE 8 — Executar beta interno controlado
FASE 9 — Auditoria final e decisão GO/NO-GO
```

---

# FASE 0 — BASELINE E AUDITORIA PÓS-ROADMAP

## 0.1 Objetivo

Capturar o estado real do sistema antes das correções.

## 0.2 Branch

Criar branch nova a partir do estado atual:

```bash
git checkout -b fix/roadmap-closeout-e2e-soak-v1
```

Se já existir, revisar antes de reutilizar.

## 0.3 Registrar Git

Nos três projetos:

```text
Sistema Local
MaximusTrader
App Android
```

Registrar:

```bash
git branch --show-current
git rev-parse HEAD
git status --short
git remote -v
git log -10 --oneline
```

## 0.4 Registrar ambiente

```bash
date -Is
hostname
whoami
uname -a
df -h
free -h
python --version
mysql --version
git --version
node --version
npm --version
php --version
composer --version
java -version
```

## 0.5 Serviços

Registrar estado de:

- 11 asset collectors;
- candle event processor;
- scanner;
- notifier;
- lifecycle monitor;
- FastAPI;
- Dash;
- bridges B3 e Forex;
- coletores legados, se ainda existirem;
- Laravel queue worker;
- sync watcher.

```bash
systemctl list-units --type=service | grep -E 'smc|mt5|maximus'
```

## 0.6 Banco

Executar e salvar:

```sql
SELECT status, COUNT(*)
FROM technical_engine_candle_events
GROUP BY status;
```

```sql
SELECT symbol, timeframe, MAX(candle_time)
FROM technical_engine_candle_events
GROUP BY symbol, timeframe;
```

```sql
SELECT COUNT(*)
FROM technical_engine_opportunity_evidence_bundles_shadow;
```

```sql
SELECT COUNT(*)
FROM technical_engine_opportunity_chart_snapshots_shadow;
```

## 0.7 Testes baseline

Python:

```bash
python -m pytest \
  tests/test_smc_engine_v2 \
  tests/test_study_gateway \
  tests/test_opportunity_scanner \
  tests/test_asset_collector \
  tests/test_candle_event_processor \
  tests/test_opportunity_evidence \
  tests/test_chart_snapshot \
  tests/test_opportunity_lifecycle \
  -q --tb=short
```

Android:

```bash
./gradlew :composeApp:assembleDebug
```

Frontend:

```bash
npm ci
npm run build
```

Laravel:

```bash
php artisan route:list
php artisan migrate:status
php artisan test
```

## 0.8 Relatório

```text
docs_geral/Relatorios/BASELINE_POS_ROADMAP_FECHAMENTO_E2E.md
```

## 0.9 Critérios de pronto

- estado Git registrado;
- serviços registrados;
- banco registrado;
- testes executados;
- erros conhecidos classificados;
- branch criada;
- baseline salvo.

---

# FASE 1 — CORRIGIR SCANNER E EVENTOS EM RETRY

## 1.1 Objetivo

Eliminar erros operacionais que impedem o fluxo completo.

## 1.2 Corrigir `LatestPriceRef.status`

Localizar:

```bash
grep -RIn "LatestPriceRef" technical_engine tests
grep -RIn "\.status" technical_engine/opportunity_scanner tests
```

Identificar se:

- o atributo deveria existir;
- o scanner deveria consultar outro campo;
- o modelo foi alterado sem adapter;
- há incompatibilidade de versão.

## 1.3 Regra de correção

A correção deve:

- preservar compatibilidade;
- não alterar lógica do SMC;
- não inventar status;
- incluir teste de regressão;
- não usar fallback silencioso apenas para esconder o erro.

## 1.4 Testes obrigatórios

Criar testes para:

- `LatestPriceRef` válido;
- preço stale;
- preço ausente;
- scanner bloqueado corretamente;
- scanner prossegue com preço válido;
- integração com signal builder;
- integração com Evidence Bundle.

## 1.5 Investigar eventos em retry

```sql
SELECT
    event_id,
    symbol,
    timeframe,
    status,
    attempts,
    available_at,
    claimed_at,
    claimed_by,
    last_error_code,
    last_error_message,
    updated_at
FROM technical_engine_candle_events
WHERE status IN ('FAILED','PENDING','PROCESSING','DEAD')
ORDER BY updated_at DESC;
```

Classificar:

```text
TRANSIENT_DB
TRANSIENT_MT5
PIPELINE_ERROR
SCANNER_ERROR
MODEL_ERROR
CONFIG_ERROR
POISON_EVENT
UNKNOWN
```

## 1.6 Corrigir eventos presos

1. corrigir causa;
2. resetar somente eventos retryable;
3. preservar attempts;
4. registrar `requeue_reason`;
5. reprocessar;
6. confirmar `COMPLETED`;
7. mover evento irrecuperável para `DEAD`;
8. gerar alerta.

## 1.7 Health

Adicionar ou validar:

```text
events_pending
events_processing
events_retrying
events_dead
oldest_pending_age_seconds
last_completed_event_at
```

## 1.8 Critérios de pronto

- erro eliminado;
- testes novos passando;
- seis eventos classificados;
- recuperáveis concluídos;
- irrecuperáveis em DEAD com motivo;
- nenhum evento preso em PROCESSING;
- scanner sem exceção;
- relatório criado.

## 1.9 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_1_SCANNER_EVENTOS.md
```

---

# FASE 2 — VALIDAR CONTINUIDADE DA COLETA

## 2.1 Objetivo

Compensar a ausência de paridade formal antes do desligamento dos coletores legados.

## 2.2 Escopo

```text
11 ativos × 6 timeframes = 66 pares ativo/timeframe
```

## 2.3 Ferramenta

Criar:

```text
tools/audit_asset_collection_continuity.py
```

Uso:

```bash
python tools/audit_asset_collection_continuity.py \
  --hours 24 \
  --all-assets \
  --all-timeframes \
  --markdown
```

Resultados por par:

- último candle;
- idade;
- quantidade esperada;
- quantidade observada;
- gaps;
- duplicidades;
- timezone;
- candles fora de ordem;
- candles abertos persistidos;
- eventos sem candle;
- candles sem evento.

## 2.4 Regras de mercado

B3:

- dias úteis;
- sessão configurada;
- feriados;
- leilões;
- ausência legítima fora da sessão.

Forex/cripto:

- mercado 24h/5 ou 24h/7 conforme ativo;
- manutenção da corretora;
- timezone do broker.

## 2.5 Canary retrospectivo

Auditar 48 horas detalhadas de:

```text
WINFUT
XAUUSDm
BTCUSDm
```

## 2.6 Critérios de pronto

- 66 pares auditados;
- nenhum candle aberto tratado como fechado;
- duplicidade zero;
- gaps explicados;
- heartbeat recente;
- atraso dentro do SLO;
- ferramenta versionada;
- relatório criado.

## 2.7 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_2_CONTINUIDADE_COLETA.md
```

---

# FASE 3 — FECHAR INTEGRAÇÃO EVIDENCE + CHART + UPLOAD

## 3.1 Objetivo

Comprovar:

```text
scanner
→ opportunity
→ evidence bundle
→ chart spec
→ chart artifacts
→ upload HMAC
→ Laravel
→ opportunity READY
```

## 3.2 Estados obrigatórios

```text
DETECTED
EVIDENCE_PENDING
EVIDENCE_READY
CHART_PENDING
CHART_RENDERING
CHART_READY
UPLOAD_PENDING
UPLOAD_COMPLETED
READY
ERROR
```

Nenhuma oportunidade pode ser marcada `READY` sem:

- bundle válido;
- manifest válido;
- thumbnail;
- mobile image;
- full image;
- hashes validados;
- upload confirmado.

## 3.3 Ferramenta E2E controlada

Criar:

```text
tools/run_controlled_opportunity_e2e.py
```

Uso:

```bash
python tools/run_controlled_opportunity_e2e.py \
  --symbol WINFUT \
  --timeframe M5 \
  --test-mode \
  --wait \
  --markdown
```

A ferramenta deve:

1. criar ou selecionar oportunidade controlada;
2. preservar `test_mode=true`;
3. gerar bundle;
4. gerar chart;
5. persistir;
6. fazer upload;
7. consultar Laravel;
8. validar hashes;
9. registrar IDs;
10. não contaminar métricas comerciais.

## 3.4 Validação de artefatos

Verificar:

- tamanho;
- MIME;
- dimensões;
- hash;
- storage privado;
- correspondência com opportunity;
- correspondência com bundle;
- ausência de candle futuro;
- entrada/stop/alvos visíveis.

## 3.5 Validação visual

Revisar:

```text
WINFUT
XAUUSDm
BTCUSDm
```

Com oportunidades ALTISTA e BAIXISTA.

## 3.6 Critérios de pronto

- oportunidade percorre todas as etapas;
- três imagens geradas;
- upload confirmado;
- Laravel retorna evidence;
- hashes batem;
- status READY;
- retries funcionam;
- falha de renderer não derruba scanner;
- relatório criado.

## 3.7 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_3_INTEGRACAO_EVIDENCE_CHART.md
```

---

# FASE 4 — DEPLOY DO SITE NA HOSTINGER

## 4.1 Objetivo

Implantar backend Laravel e frontend React atualizados.

## 4.2 Regra SSH

A rotação da credencial SSH está fora do escopo.

Ainda assim:

- não usar `SSHPASS`;
- não inserir senha em comando;
- não salvar senha em script;
- usar autenticação já configurada;
- se exigir senha, o usuário digita diretamente no terminal.

## 4.3 Pré-deploy

Registrar:

- commit;
- branch;
- migrations pendentes;
- build frontend;
- testes backend;
- espaço;
- backup do banco;
- backup dos arquivos atuais;
- estado da queue;
- estado do FCM.

## 4.4 Backend

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Validar:

```bash
php artisan migrate:status
php artisan route:list
php artisan about
```

## 4.5 Frontend

```bash
npm ci
npm run build
```

## 4.6 Storage

Validar:

- diretório privado de evidências;
- permissões;
- artifacts não públicos;
- endpoint autenticado.

## 4.7 Queue

Confirmar worker de:

```text
SendOpportunityPushNotification
```

Verificar failed jobs.

## 4.8 Health

Testar:

```text
/api/sync/health
/api/mobile/opportunities/active
/api/mobile/opportunities/{id}/evidence
/api/mobile/opportunities/{id}/chart
```

## 4.9 Critérios de pronto

- deploy concluído;
- migrations aplicadas;
- frontend publicado;
- área `/app/*` acessível;
- admin de evidências acessível;
- endpoints mobile respondendo;
- storage privado funcional;
- queue ativa;
- health OK;
- relatório criado.

## 4.10 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_4_DEPLOY_HOSTINGER.md
```

---

# FASE 5 — APP LINKS, FCM REAL E ANDROID FÍSICO

## 5.1 Objetivo

Comprovar:

```text
Laravel
→ Firebase
→ Android físico
→ toque
→ deep link
→ detalhe
→ gráfico
```

## 5.2 Fingerprints

Debug:

```bash
./gradlew signingReport
```

Release:

```bash
keytool -list -v -keystore <keystore>
```

Não imprimir senha.

## 5.3 assetlinks.json

Atualizar:

```text
https://maximustrade.com.br/.well-known/assetlinks.json
```

Validar:

- package correto;
- namespace correto;
- SHA-256 correto;
- HTTPS;
- content-type JSON;
- sem redirect indevido.

## 5.4 Build

```bash
./gradlew clean :composeApp:assembleDebug
```

Registrar hash, tamanho, versionName e versionCode.

## 5.5 Android físico

```bash
adb devices
adb install -r <apk>
```

## 5.6 Matriz obrigatória

| Cenário | Esperado |
|---|---|
| app fechado | abre detalhe |
| background | abre detalhe |
| foreground | recebe e navega |
| deslogado | login e retorno ao detalhe |
| token renovado | device atualizado |
| sem permissão | orientação exibida |
| bundle pendente | estado parcial |
| rede lenta | loading e retry |
| sem internet | cache ou erro amigável |
| oportunidade expirada | detalhe/histórico |
| dois devices | ambos recebem conforme regra |
| quiet hours | push bloqueado |
| limite por hora | push não enviado |
| token inválido | device desativado |

## 5.7 Telemetria

Confirmar:

```text
push_queued_at
push_sent_at
notification_received_at
notification_opened_at
detail_loaded_at
chart_loaded_at
```

## 5.8 Critérios de pronto

- push real recebido;
- deep link HTTPS validado;
- fallback custom scheme validado;
- login preserva destino;
- gráfico carrega;
- opened registrado;
- chart-viewed registrado;
- três execuções E2E consecutivas;
- relatório com capturas e timestamps.

## 5.9 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_5_FCM_ANDROID_FISICO.md
```

---

# FASE 6 — VALIDAR LIFECYCLE E OUTCOME REAIS

## 6.1 Objetivo

Validar a máquina de estados com oportunidade real ou controlada.

## 6.2 Cenários

```text
EXPIRED sem entrada
ENTRY_TOUCHED
ENTRY_CONFIRMED
TP1_REACHED
STOP_REACHED
INVALIDATED
AMBIGUOUS_BAR
```

## 6.3 Regras

- usar M1 fechado;
- preservar bundle;
- usar stop e alvos originais;
- aplicar `STOP_FIRST_CONSERVATIVE`;
- estados terminais imutáveis;
- eventos idempotentes.

## 6.4 Ferramenta de replay

Criar:

```text
tools/replay_opportunity_lifecycle.py
```

Uso:

```bash
python tools/replay_opportunity_lifecycle.py \
  --opportunity-id <id> \
  --validate-only \
  --markdown
```

## 6.5 Critérios de pronto

- oportunidade percorre lifecycle;
- outcome persistido;
- bundle inalterado;
- eventos sem duplicidade;
- timeline visível;
- replay reproduz resultado;
- relatório criado.

## 6.6 Relatório

```text
docs_geral/Relatorios/RESULTADO_FECHAMENTO_FASE_6_LIFECYCLE_REAL.md
```

---

# FASE 7 — SOAK TEST FORMAL DE 7 DIAS

## 7.1 Objetivo

Comprovar estabilidade contínua.

## 7.2 Início formal

Registrar:

```text
started_at UTC
started_at America/Sao_Paulo
commit
versões
serviços
config hash
```

## 7.3 Duração

```text
168 horas reais
```

Não declarar concluído antes do tempo.

## 7.4 Monitoramento

Coletar a cada cinco minutos:

- heartbeats;
- último candle;
- gaps;
- fila de eventos;
- retries;
- dead letters;
- scanner errors;
- bundles;
- chart failures;
- upload failures;
- Laravel queue;
- FCM;
- lifecycle backlog;
- disco;
- memória;
- conexões DB;
- CPU.

## 7.5 SLOs

```text
collector availability >= 99.5%
M1 lag em mercado aberto < 120s
event duplicate = 0
dead event rate < 0.1%
chart failure rate < 2%
upload failure rate < 2%
FCM failure rate < 5%
crash-free app >= 99%
```

## 7.6 Chaos tests

Executar:

- restart de collector;
- restart do processor;
- restart do scanner;
- restart do renderer;
- restart da queue;
- bridge indisponível temporariamente;
- banco indisponível temporariamente;
- falha de upload;
- token FCM inválido;
- internet Android desligada.

## 7.7 Relatórios diários

```text
docs_geral/Relatorios/Soak/SOAK_DIA_01.md
...
docs_geral/Relatorios/Soak/SOAK_DIA_07.md
```

## 7.8 Relatório final

```text
docs_geral/Relatorios/RELATORIO_SOAK_TEST_7_DIAS_FECHAMENTO.md
```

## 7.9 Critérios de pronto

- 168 horas reais;
- nenhum incidente P0;
- incidentes P1 resolvidos;
- SLOs atendidos;
- recovery comprovado;
- backups verificados;
- relatório final.

---

# FASE 8 — BETA INTERNO CONTROLADO

## 8.1 Objetivo

Liberar para grupo restrito.

## 8.2 Grupo

```text
5 a 10 usuários
```

## 8.3 Ativos iniciais

```text
WINFUT
XAUUSDm
BTCUSDm
```

## 8.4 Requisitos

- APK identificado;
- termo de uso;
- disclaimer;
- política de privacidade;
- canal de suporte;
- limitações conhecidas;
- rollback;
- analytics mínimos.

## 8.5 Métricas

- push delivery;
- open rate;
- chart load;
- tempo até detalhe;
- falha de login;
- erro de API;
- clareza do gráfico;
- compreensão da oportunidade;
- feedback por ativo.

## 8.6 Duração

Mínimo recomendado:

```text
7 dias
```

## 8.7 Critérios de pronto

- usuários ativos;
- telemetria coletada;
- incidentes classificados;
- feedback consolidado;
- nenhuma falha crítica;
- relatório criado.

## 8.8 Relatório

```text
docs_geral/Relatorios/RELATORIO_BETA_INTERNO_FECHAMENTO.md
```

---

# FASE 9 — AUDITORIA FINAL E GO/NO-GO

## 9.1 Objetivo

Determinar se o sistema pode avançar.

## 9.2 Revisão

Revisar:

- coleta;
- eventos;
- scanner;
- bundle;
- chart;
- upload;
- Laravel;
- FCM;
- Android;
- lifecycle;
- soak;
- beta;
- segurança;
- backup;
- rollback;
- documentação.

## 9.3 Status permitidos

```text
NO_GO
GO_BETA_INTERNO
GO_BETA_EXTERNO_LIMITADO
GO_PRODUCAO_CONTROLADA
```

## 9.4 Regras

Não usar `GO_BETA_EXTERNO_LIMITADO` se:

- soak não concluiu;
- Android físico falhou;
- renderer não integrou;
- queue não está estável;
- gaps não explicados;
- scanner ainda lança exceção;
- dead letters sem causa;
- storage não privado;
- lifecycle inconsistente.

## 9.5 Documento final

```text
docs_geral/Relatorios/GO_NO_GO_FINAL_SMC_TRADER_SYSTEM_7_0.md
```

---

# 6. FEATURE FLAGS OBRIGATÓRIAS

Confirmar:

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

---

# 7. TESTES OBRIGATÓRIOS

## Python

Executar suíte crítica após cada fase.

## Laravel

```bash
php artisan test
php artisan route:list
php artisan migrate:status
```

## React

```bash
npm ci
npm run build
npm test
```

## Android

```bash
./gradlew test
./gradlew :composeApp:assembleDebug
```

## E2E

O mesmo `opportunity_id` deve existir em:

- scanner;
- bundle;
- chart;
- Laravel;
- FCM;
- Android;
- lifecycle;
- outcome.

---

# 8. PADRÃO DE RELATÓRIO

Cada fase deve conter:

```markdown
# RESULTADO — FASE X

## 1. Status
## 2. Objetivo
## 3. Commit inicial
## 4. Commit final
## 5. Arquivos criados
## 6. Arquivos alterados
## 7. Migrations
## 8. Serviços
## 9. Testes
## 10. Evidências
## 11. Métricas
## 12. Erros
## 13. Correções
## 14. Pendências
## 15. Riscos
## 16. Rollback
## 17. Critérios de aceite
## 18. Status final
```

Status:

```text
NAO_INICIADO
EM_EXECUCAO
BLOQUEADO
CONCLUIDO_COM_RESSALVAS
CONCLUIDO
```

---

# 9. ESTRATÉGIA DE COMMITS

```text
fix(closeout-1): correct LatestPriceRef scanner contract
test(closeout-1): cover retry and dead-letter recovery
feat(closeout-2): add collection continuity auditor
feat(closeout-3): integrate evidence chart upload pipeline
deploy(closeout-4): add Hostinger release runbook
fix(closeout-5): finalize Android app links
test(closeout-6): validate lifecycle replay
ops(closeout-7): add seven-day soak automation
docs(closeout-9): publish final go-no-go
```

---

# 10. ROLLBACK GLOBAL

Em qualquer falha crítica:

1. desativar feature flag da fase;
2. preservar dados;
3. não apagar tabelas;
4. interromper consumidor afetado;
5. restaurar serviço anterior;
6. registrar incidente;
7. validar health;
8. manter Evidence Bundles imutáveis.

---

# 11. CONCLUSÃO ESPERADA

O plano estará concluído quando:

- scanner sem exceção;
- retries resolvidos;
- coleta validada;
- evidence pipeline completo;
- site implantado;
- Android físico validado;
- FCM real validado;
- lifecycle real validado;
- soak test de 168 horas concluído;
- beta interno executado;
- GO/NO-GO documentado.

A ausência de rotação SSH nesta versão não autoriza exposição, impressão ou reutilização insegura da credencial.
