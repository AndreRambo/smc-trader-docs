---
document_version: 2.0
master_version: 2.0
architecture_snapshot_date: 2026-06-30
status: ACTIVE
supersedes: 1.0
projeto: SMC Trader System 7.0
---

# AUTORIDADES DOCUMENTAIS E PRECEDÊNCIA

| Autoridade | Nível | Uso |
|---|---|---|
| `ARQUITETURA_OFICIAL.md` | NÍVEL 0 | Estado atual do sistema |
| `RELATORIO_ENGINES_INDICADORES_ZONAS.md` | NÍVEL 0 | Engines, tabelas e zonas |
| `00_PLANO_MESTRE_ORQUESTRACAO_8_ENGINES_SMC_V3.txt` | NÍVEL 1 | Ordem global, contratos, ownership, gates |
| `CONTRACT_TRACEABILITY_MATRIX.md` | NÍVEL 2 | Rastreabilidade de contratos |
| Este plano individual | NÍVEL 3 | Algoritmo e implementação específica |

---

# CAMINHOS OFICIAIS

| Recurso | Caminho |
|---|---|
| Diretório ativo | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3` |
| Diretório incremental | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3/incremental` |
| Diretório legado (backup) | `/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2` |
| Persistência V3 | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/technical_engine/smc_engine_v3/incremental/persistence` |
| Migrations oficiais | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/database/migrations` |
| Testes | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0/tests/smc_engine_v3/` |

---

# REGRA DE BACKUP E RUNTIME

- **backup congelado:** `/home/bimaq/projetos/SMC_Trader_System_7_0/backups/smc_engine_v2` — fora do runtime
- **proibido importar código do backup** — toda implementação ocorre na cópia V3
- **modo inicial:** `BASELINE_COMPAT`
- **guardrails:**
  - `shadow_only=True`
  - `can_promote_trade=False`
  - `apply_automatically=False`
  - `llm_decision_used=False`
  - `production_truth_replaced=False`

---


# PLANO OPERACIONAL — CORREÇÃO DA ENGINE DE ORDER BLOCKS SMC V2

## 1. Identificação do plano

**Projeto:** SMC Engine V2  
**Módulo principal afetado:** detector e ciclo de vida de Order Blocks  
**Documento-base técnico:** implementação atual enviada pelo usuário (`Código colado.py`)  
**Referência conceitual:** *SMC Bible — DexterrFX*  
**Objetivo:** corrigir a engine para que os Order Blocks sejam detectados, confirmados, disponibilizados, classificados, mitigados e invalidados de acordo com uma leitura SMC consistente, sem lookahead e sem misturar heurísticas estatísticas com validade estrutural.

---

## 2. Resultado esperado

Ao final deste plano, a engine deve:

1. Separar claramente o **candle de origem** do OB, o **evento estrutural que o confirma** e o **momento em que ele passa a estar disponível** para decisão.
2. Detectar o OB a partir da **origem real do deslocamento** que produziu o BOS/ChoCH válido, e não simplesmente do candle imediatamente anterior ao rompimento.
3. Tratar corretamente:
   - BOS;
   - ChoCH;
   - estrutura swing;
   - estrutura interna;
   - protected high/low;
   - weak high/low;
   - deslocamento;
   - FVG/imbalance;
   - liquidez;
   - sweep;
   - inducement;
   - Extreme OB;
   - Decisional OB;
   - refinamento por corpo, vela completa, metade, wick e timeframe inferior.
4. Implementar um ciclo de vida explícito para cada zona:
   - candidata;
   - confirmada;
   - fresh;
   - tocada;
   - parcialmente mitigada;
   - midpoint alcançado;
   - totalmente mitigada;
   - invalidada;
   - expirada;
   - convertida em breaker candidate.
5. Eliminar lookahead em:
   - swings;
   - confirmação estrutural;
   - classificação de subtipos;
   - plotagem;
   - backtest;
   - cálculo de estado futuro.
6. Separar:
   - **validade SMC**;
   - **qualidade/confluência SMC**;
   - **score estatístico calibrado por ativo/timeframe**.
7. Preservar os guardrails atuais:
   - `shadow_only`;
   - nenhuma escrita em banco de produção;
   - nenhuma promoção automática de trade;
   - nenhuma decisão baseada apenas em score.

---

## 3. Escopo

### 3.1 Incluído

- Revisão da lógica de detecção de Order Blocks.
- Correção dos índices temporais e de disponibilidade.
- Criação de um evento estrutural canônico.
- Detecção de deslocamento.
- Seleção da origem do impulso.
- Suporte a múltiplas zonas por evento estrutural.
- Ciclo de vida completo da zona.
- Refinamento da zona antes da mitigação.
- Novo modelo de qualidade.
- Revisão dos subtipos `NORMAL`, `REJECTION`, `STACKED` e integração com breaker clássico.
- Correção das métricas de volume.
- Reexecução de backtests e recalibração.
- Atualização de visualização e contratos de dados.
- Testes unitários, temporais, integrados e de regressão.

### 3.2 Fora do escopo nesta execução

- Criação de estratégia de entrada.
- Definição de stop e alvos.
- Promoção automática de oportunidades para trade real.
- Alteração de banco de produção.
- Otimização definitiva com menos de 12 meses de dados.
- Reescrita geral dos módulos de Wyckoff, Elliott ou Risk Management.

---

## 4. Regras conceituais obrigatórias

A implementação deve obedecer às seguintes regras.

### 4.1 O que origina um OB

Um Order Block válido deve ser associado ao candle, wick ou pequena base que representa a **origem do deslocamento responsável por uma quebra estrutural válida**.

Não assumir que:

- todo último candle contrário é um OB;
- todo candle imediatamente anterior ao BOS é um OB;
- toda quebra mínima de máxima/mínima é deslocamento;
- todo ChoCH confirma mudança total de estrutura;
- todo toque no limite proximal consome integralmente a zona.

### 4.2 Relação temporal obrigatória

Para cada OB devem existir, no mínimo, os seguintes marcos:

- `origin_index`: candle/base de origem;
- `displacement_start_index`: início da perna impulsiva;
- `break_index`: candle que rompe a estrutura;
- `confirmed_index`: candle em que a quebra é confirmada pelas regras da engine;
- `available_index`: primeiro candle em que o OB poderia ser usado sem lookahead;
- `first_touch_index`: primeiro toque após disponibilidade;
- `invalidated_index`: candle que invalida a zona, quando houver.

A regra padrão deve ser:

- o OB pode ser desenhado retroativamente a partir da origem;
- ele só pode ser usado em backtest, scanner ou decisão a partir de `available_index`;
- nenhum dado posterior a `available_index` pode influenciar atributos calculados para aquele instante.

### 4.3 Ordem correta do processamento

1. Detectar estrutura confirmada e disponível.
2. Detectar rompimento estrutural.
3. Validar deslocamento.
4. Delimitar a perna impulsiva.
5. Encontrar a origem válida.
6. Construir e refinar a zona.
7. Verificar se a origem permaneceu fresh até a confirmação.
8. Confirmar e disponibilizar o OB.
9. Acompanhar ciclo de vida.
10. Calcular confluências e score.

---

# PARTE I — PREPARAÇÃO E BASELINE

## 5. Fase 0 — Auditoria inicial e congelamento do comportamento atual

### 5.1 Objetivo

Criar uma referência reproduzível antes de alterar a engine.

### 5.2 Tarefas

1. Localizar no repositório:
   - arquivo real que contém `calculate_ob`;
   - modelo `ObV2`;
   - `OBQualityConfig`;
   - módulo de swings;
   - módulo de BOS/ChoCH;
   - módulo de FVG;
   - módulo de liquidez;
   - serialização/persistência shadow;
   - visual overlays;
   - backtests de OB;
   - testes atuais.
2. Registrar:
   - nomes e caminhos exatos;
   - assinaturas públicas;
   - dependências;
   - consumidores do DataFrame retornado;
   - consumidores de `ObV2`;
   - campos exibidos no dashboard;
   - defaults atuais.
3. Rodar a suíte completa antes de modificar qualquer coisa.
4. Salvar relatório de baseline contendo:
   - total de testes;
   - aprovados;
   - falhos;
   - ignorados;
   - duração;
   - hash do commit;
   - dataset utilizado;
   - quantidade de OBs por ativo/timeframe;
   - distribuição bullish/bearish;
   - distribuição HIGH/MEDIUM/LOW;
   - taxa de toque;
   - taxa de invalidação;
   - taxa de respeito atualmente usada.
5. Congelar um conjunto de candles de regressão:
   - casos bullish;
   - casos bearish;
   - BOS forte;
   - BOS fraco;
   - ChoCH falso;
   - gaps;
   - candle doji;
   - rejection candle;
   - base com múltiplas velas;
   - múltiplos OBs na mesma perna;
   - OB mitigado antes da confirmação;
   - zonas sobrepostas.

### 5.3 Entregáveis

- `RELATORIO_BASELINE_ORDER_BLOCK_ENGINE_V2.md`.
- Fixtures de candles determinísticas.
- Snapshot dos resultados atuais.

### 5.4 Critérios de aceite

- Baseline reproduzível em nova execução.
- Nenhuma alteração funcional feita ainda.
- Todos os testes atuais catalogados.

---

# PARTE II — CORREÇÃO TEMPORAL E ESTRUTURAL

## 6. Fase 1 — Corrigir anti-lookahead e os índices canônicos

### 6.1 Problema atual

O registro usa o candle do OB como origem e define confirmação/disponibilidade no candle seguinte, mesmo quando o BOS ocorre vários candles depois.

### 6.2 Objetivo

Fazer com que cada OB carregue os índices reais de origem, rompimento, confirmação e disponibilidade.

### 6.3 Alterações de modelo obrigatórias

Adicionar ao modelo de OB, ou equivalente:

- `origin_index`;
- `origin_at`;
- `displacement_start_index`;
- `displacement_end_index`;
- `break_index`;
- `break_at`;
- `confirmed_index`;
- `confirmed_at`;
- `available_index`;
- `available_at`;
- `structure_event_id`;
- `structure_kind` (`SWING` ou `INTERNAL`);
- `break_kind` (`BOS`, `CHOCH`, `MSS` ou enum oficial já existente);
- `broken_level`;
- `broken_swing_index`;
- `broken_swing_available_index`;
- `detection_definition`;
- `is_lookahead_safe`;
- `lookahead_validation_reason`.

### 6.4 Regras de disponibilidade

- O swing rompido deve já estar confirmado e disponível antes do rompimento.
- O fechamento do candle de rompimento deve ser conhecido antes de confirmar o evento.
- `available_index` deve ser igual ou posterior ao candle de rompimento.
- Se o sistema toma decisões somente no fechamento, usar como padrão o próximo instante operacional após o fechamento do rompimento.
- Nenhuma mitigação anterior ao `available_index` deve ser tratada como reteste operacional; ela deve ser tratada como perda de freshness pré-confirmação.

### 6.5 Validação anti-lookahead

Criar um validador que, para cada registro, confirme:

- `origin_index <= displacement_start_index <= break_index`;
- `confirmed_index >= break_index`;
- `available_index >= confirmed_index`;
- `broken_swing_available_index < break_index`;
- atributos de liquidez, FVG, subtipos e qualidade usam apenas candles com índice menor ou igual ao instante avaliado;
- nenhuma propriedade do futuro é usada para classificar o OB na origem.

### 6.6 Testes obrigatórios

- Origem 5 candles antes do BOS.
- Origem imediatamente anterior ao BOS.
- Swing só confirmado depois do suposto rompimento: evento deve ser rejeitado.
- FVG confirmado posteriormente: não pode aparecer antecipadamente.
- Mitigação ocorrida antes do BOS: marcar como reteste pré-confirmação.
- Plotagem histórica permitida, disponibilidade operacional proibida antes de `available_index`.

### 6.7 Critérios de aceite

- Zero OB disponível antes do BOS correspondente.
- Zero uso de swing não confirmado.
- Todos os registros aprovados pelo validador anti-lookahead.

---

## 7. Fase 2 — Criar evento estrutural canônico

### 7.1 Objetivo

Parar de procurar apenas o último swing por varredura reversa e passar a consumir eventos estruturais explícitos.

### 7.2 Estrutura mínima do evento

Cada evento deve conter:

- ID determinístico;
- direção;
- tipo de estrutura;
- tipo de rompimento;
- índice e preço do swing rompido;
- índice em que o swing ficou disponível;
- índice do candle de rompimento;
- preço de fechamento do rompimento;
- margem rompida em pontos, ticks e ATR;
- protected high/low relacionado;
- weak high/low relacionado;
- contexto de continuação ou reversão;
- validade temporal;
- origem do evento no módulo de estrutura.

### 7.3 Regras

- Usar `swings.Level` ou nível canônico equivalente, não apenas `high`/`low` do DataFrame.
- Diferenciar swing structure de internal structure.
- Diferenciar BOS de continuação e ChoCH.
- ChoCH isolado deve gerar confiança menor que BOS de continuação ou confirmação posterior.
- Para reversão completa, respeitar a regra estrutural oficial do projeto; não assumir mudança total apenas com o primeiro ChoCH.

### 7.4 Deduplicação

Substituir `used_swings` por deduplicação baseada em:

- `structure_event_id`;
- tipo de zona;
- origem;
- direção;
- timeframe;
- papel `EXTREME`/`DECISIONAL`.

### 7.5 Critérios de aceite

- Um mesmo evento pode produzir mais de uma zona válida quando houver fundamentos distintos.
- O mesmo OB não pode ser duplicado por reprocessamento do mesmo evento.
- BOS e ChoCH aparecem identificados separadamente.

---

# PARTE III — DESLOCAMENTO E ORIGEM REAL

## 8. Fase 3 — Implementar gate obrigatório de deslocamento

### 8.1 Objetivo

Impedir que qualquer fechamento marginal além de um swing gere automaticamente um OB.

### 8.2 Métricas de deslocamento

Calcular, de forma configurável por ativo/timeframe:

- corpo do candle de rompimento em ticks;
- corpo do candle de rompimento em ATR;
- range total em ATR;
- distância de fechamento além do nível rompido;
- proporção corpo/range;
- sequência de candles na direção do rompimento;
- overlap médio entre candles da perna;
- quantidade de candles impulsivos;
- velocidade do movimento;
- criação de FVG/imbalance;
- eficiência direcional da perna;
- volume da perna, quando disponível.

### 8.3 Estados

O evento deve ser classificado como:

- `NO_DISPLACEMENT`;
- `WEAK_DISPLACEMENT`;
- `VALID_DISPLACEMENT`;
- `STRONG_DISPLACEMENT`.

Somente `VALID_DISPLACEMENT` e `STRONG_DISPLACEMENT` devem confirmar OB canônico. Casos fracos podem ser mantidos em shadow como candidatos.

### 8.4 Configuração

Não usar valores absolutos fixos globais. Permitir perfis por:

- ativo;
- classe de ativo;
- timeframe;
- sessão;
- volatilidade.

Priorizar normalização por ATR e ticks.

### 8.5 Critérios de aceite

- Rompimentos marginais não geram OB confirmado.
- Rompimentos fortes com origem clara são preservados.
- O resultado informa por que o deslocamento foi aceito ou rejeitado.

---

## 9. Fase 4 — Delimitar a perna impulsiva

### 9.1 Objetivo

Identificar o trecho do preço que começou na origem e terminou no rompimento.

### 9.2 Regras

Para cada evento estrutural:

1. Definir o `break_index`.
2. Caminhar para trás procurando o início da expansão direcional.
3. Encerrar a busca quando ocorrer uma combinação configurável de:
   - mudança clara de direção;
   - candle-base;
   - overlap elevado;
   - swing interno;
   - sweep anterior;
   - retorno à faixa anterior;
   - início do FVG associado.
4. Registrar:
   - início da perna;
   - fim da perna;
   - candles pertencentes à perna;
   - métricas agregadas.

### 9.3 Critérios de aceite

- A perna nunca pode começar depois da origem escolhida.
- O candle imediatamente anterior ao BOS não é automaticamente considerado origem.
- A perna deve ser reproduzível com os mesmos dados.

---

## 10. Fase 5 — Selecionar a origem canônica do OB

### 10.1 Objetivo

Substituir o default `prev` por um seletor baseado em origem do deslocamento.

### 10.2 Modos suportados

Manter os modos atuais apenas para comparação, com nomes explícitos:

- `CANONICAL_ORIGIN` — novo padrão oficial;
- `PRE_BREAKOUT_CANDLE` — antigo `prev`, experimental;
- `LAST_OPPOSITE_CANDLE` — antigo `last_bear`, experimental;
- `EXTREME_OF_LEG` — antigo `upstream`, experimental;
- `BASE_CLUSTER` — base com múltiplas velas;
- `WICK_REFINEMENT`;
- `LOWER_TIMEFRAME_REFINEMENT`.

### 10.3 Critérios para origem válida

A origem deve atender a uma combinação mínima de:

- estar dentro ou imediatamente antes do início da perna impulsiva;
- possuir causalidade temporal com o deslocamento;
- não ser apenas uma vela aleatória no meio da perna;
- preceder FVG/imbalance relacionado;
- estar próxima de sweep, protected high/low ou ponto de reação relevante;
- permanecer fresh até a confirmação ou ter reteste pré-confirmação aceitável;
- produzir uma zona com limites válidos.

### 10.4 Candle de direção oposta

Usar a direção oposta como preferência, não como requisito absoluto.

Aceitar, quando sustentado por contexto:

- doji;
- hammer/shooting star;
- rejection candle;
- wick S2B/B2S;
- base curta de candles;
- candle da mesma cor com wick estrutural relevante;
- refinamento em timeframe inferior.

### 10.5 Proibição de fallback silencioso

Se não houver origem válida:

- não criar OB canônico;
- registrar `ORIGIN_NOT_FOUND`;
- opcionalmente criar candidato shadow com razão explícita;
- nunca usar o candle anterior ao rompimento sem indicar modo experimental.

### 10.6 Limites da zona

A zona-base deve ser definida primeiro em modo `full` e depois refinada.

Registrar:

- limite distal;
- limite proximal;
- midpoint;
- origem dos limites;
- modo de refinamento;
- timeframe de refinamento;
- se a zona vem de candle único, wick ou cluster.

### 10.7 Critérios de aceite

- O modo canônico seleciona a origem do deslocamento.
- Não existe fallback silencioso.
- Todas as seleções guardam uma explicação auditável.

---

# PARTE IV — MULTIPLAS ZONAS E REFINAMENTO

## 11. Fase 6 — Suportar Extreme e Decisional OB

### 11.1 Objetivo

Permitir mais de uma zona válida por evento estrutural.

### 11.2 Papéis

- `EXTREME`: origem mais distante/próxima do protected high/low que iniciou a expansão maior.
- `DECISIONAL`: última zona que produziu a decisão estrutural imediata antes do BOS/ChoCH.
- `REFINED`: subzona confirmada em timeframe inferior.
- `SECONDARY`: zona válida, porém com prioridade menor.

### 11.3 Regras

- Um evento pode ter `EXTREME` e `DECISIONAL` simultaneamente.
- Cada zona deve possuir ID próprio.
- As zonas devem compartilhar `structure_event_id`.
- Não eliminar uma zona apenas porque outra já foi criada para o mesmo swing.
- Deduplicar por overlap, origem e papel.

### 11.4 Critérios de aceite

- Casos com duas zonas legítimas são representados sem duplicação artificial.
- O dashboard consegue exibir o papel de cada zona.

---

## 12. Fase 7 — Aplicar refinamento antes da mitigação

### 12.1 Problema atual

A mitigação é calculada na zona completa e depois o record pode ser convertido para `body` ou `half`.

### 12.2 Objetivo

Calcular primeiro os limites finais da zona e só depois acompanhar seu ciclo de vida.

### 12.3 Ordem obrigatória

1. Construir zona-base.
2. Aplicar modo de refinamento.
3. Validar `top > bottom`.
4. Calcular midpoint e tamanho final.
5. Armazenar limites finais.
6. Acompanhar toque/mitigação/invalidação usando os limites finais.

### 12.4 Modos

- `FULL_CANDLE`;
- `BODY`;
- `HALF`;
- `WICK_ONLY`;
- `LOWER_TIMEFRAME_ZONE`;
- `CUSTOM_PROXIMAL_DISTAL`.

### 12.5 MTF

Para refinamento em timeframe inferior:

- registrar zona pai;
- registrar zona filha;
- manter relação `parent_ob_id`;
- garantir alinhamento temporal;
- impedir uso de candles inferiores posteriores ao `available_index` do contexto analisado.

### 12.6 Critérios de aceite

- O estado de mitigação é calculado na mesma geometria exibida e operada.
- Zona pai e zona refinada não se confundem.

---

# PARTE V — FRESHNESS, MITIGAÇÃO E INVALIDAÇÃO

## 13. Fase 8 — Implementar freshness pré-confirmação

### 13.1 Objetivo

Verificar se a origem já foi testada entre o candle de origem e o BOS.

### 13.2 Campos

- `pre_confirmation_touch_count`;
- `pre_confirmation_first_touch_index`;
- `pre_confirmation_max_penetration_pct`;
- `fresh_at_confirmation`;
- `freshness_label`;
- `freshness_reason`.

### 13.3 Regras

- Não contar o próprio candle de origem como toque.
- Medir interseção entre candles posteriores e limites finais da zona.
- Diferenciar toque superficial, midpoint e preenchimento integral.
- Rejeitar ou rebaixar zonas profundamente mitigadas antes da confirmação.

### 13.4 Critérios de aceite

- Nenhuma zona já consumida antes do BOS aparece como fresh.
- O motivo da rejeição fica auditável.

---

## 14. Fase 9 — Criar máquina de estados do ciclo de vida

### 14.1 Estados mínimos

- `CANDIDATE`;
- `CONFIRMED`;
- `FRESH`;
- `TOUCHED`;
- `PARTIALLY_MITIGATED`;
- `MIDPOINT_REACHED`;
- `FULLY_MITIGATED`;
- `INVALIDATED`;
- `EXPIRED`;
- `BREAKER_CANDIDATE`.

### 14.2 Eventos mínimos

- confirmação;
- disponibilização;
- primeiro toque;
- novo toque;
- alcance do midpoint;
- preenchimento total;
- fechamento além do distal;
- wick além do distal;
- reação mínima atingida;
- timeout/expiração;
- quebra estrutural oposta após falha.

### 14.3 Interseção geométrica correta

Um candle só toca uma zona quando os intervalos se intersectam.

Para cada candle, avaliar:

- intervalo total `[low, high]`;
- corpo `[min(open, close), max(open, close)]`;
- limites `[bottom, top]`.

Não considerar apenas:

- bullish: `low <= top`;
- bearish: `high >= bottom`.

Essas condições isoladas aceitam candles totalmente do outro lado da zona.

### 14.4 Profundidade de penetração

Calcular:

- pontos penetrados;
- ticks penetrados;
- percentual da zona penetrado;
- se alcançou midpoint;
- se cruzou distal;
- se fechou além do distal.

### 14.5 Invalidação

Separar configurações:

- invalidação por wick;
- invalidação por fechamento;
- tolerância em ticks;
- tolerância em ATR;
- invalidação parcial em caso de gap.

### 14.6 Múltiplos testes

Registrar:

- número de toques;
- intervalo entre toques;
- reação após cada toque;
- perda progressiva de qualidade;
- limite máximo de testes.

### 14.7 Critérios de aceite

- Primeiro toque proximal não remove automaticamente a zona.
- Mitigação e invalidação são estados distintos.
- Zonas totalmente atravessadas por gap são classificadas corretamente.

---

## 15. Fase 10 — Definir “respeito” de forma operacional

### 15.1 Objetivo

Substituir métricas ambíguas de respeito.

### 15.2 Definição mínima

Uma zona é respeitada quando:

1. fica disponível;
2. recebe toque válido;
3. produz reação mínima configurável;
4. a reação ocorre antes da invalidação;
5. a reação é medida sem usar candles anteriores à disponibilidade.

### 15.3 Métricas de reação

Permitir:

- pontos;
- ticks;
- ATR;
- múltiplo do tamanho da zona;
- múltiplo de risco;
- alcance de estrutura-alvo.

### 15.4 Resultados

- `NO_TOUCH`;
- `TOUCHED_NO_REACTION`;
- `RESPECTED`;
- `PARTIAL_RESPECT`;
- `INVALIDATED_BEFORE_REACTION`;
- `EXPIRED`.

### 15.5 Critérios de aceite

- “Tocar” não equivale a “respeitar”.
- Backtests distinguem claramente toque, reação e invalidação.

---

# PARTE VI — LIQUIDEZ, IMBALANCE E CONTEXTO

## 16. Fase 11 — Integrar FVG/imbalance

### 16.1 Objetivo

Associar o OB ao imbalance produzido pela mesma perna de deslocamento.

### 16.2 Regras

- FVG deve pertencer à perna do evento.
- FVG deve ser conhecido até o instante de confirmação.
- Registrar distância entre OB e FVG.
- Registrar overlap OB/FVG quando houver.
- Distinguir ausência de FVG de FVG ainda não confirmado.

### 16.3 Campos

- `fvg_aligned`;
- `fvg_id`;
- `fvg_created_index`;
- `fvg_overlap_pct`;
- `imbalance_strength`.

### 16.4 Critérios de aceite

- O OB não recebe alinhamento com FVG futuro.
- O vínculo é explicável e reproduzível.

---

## 17. Fase 12 — Integrar liquidez, sweep e inducement

### 17.1 Objetivo

Transformar `liquidity_aligned` em informação realmente calculada.

### 17.2 Eventos de liquidez

Avaliar:

- BSL;
- SSL;
- EQH;
- EQL;
- structure high/low;
- trendline liquidity;
- liquidez interna;
- liquidez externa;
- protected high/low;
- weak high/low;
- sweep;
- inducement.

### 17.3 Regras direcionais

- Bullish OB: priorizar sweep de SSL antes da expansão.
- Bearish OB: priorizar sweep de BSL antes da expansão.
- Registrar quando a zona anterior provavelmente funcionou como inducement.
- Não exigir sweep em todos os casos, mas tratá-lo como confluência forte.

### 17.4 Campos

- `liquidity_aligned`;
- `liquidity_event_id`;
- `liquidity_kind`;
- `sweep_detected`;
- `sweep_index`;
- `inducement_detected`;
- `inducement_zone_id`;
- `protected_level_relation`.

### 17.5 Critérios de aceite

- Campos visuais não ficam com defaults enganosos.
- A engine informa qual liquidez foi tomada e quando.

---

## 18. Fase 13 — Integrar premium/discount e contexto MTF

### 18.1 Objetivo

Melhorar ranking sem transformar contexto em requisito universal.

### 18.2 Regras

- Bullish OB recebe bônus em discount.
- Bearish OB recebe bônus em premium.
- Registrar posição no dealing range.
- Registrar alinhamento com D1/H4/M15 conforme arquitetura oficial.
- Não invalidar automaticamente um OB apenas por não estar na metade ideal.

### 18.3 Campos

- `pricing_zone`;
- `range_high`;
- `range_low`;
- `range_midpoint`;
- `htf_bias_aligned`;
- `context_timeframe`;
- `setup_timeframe`;
- `trigger_timeframe`.

---

# PARTE VII — QUALIDADE, SUBTIPOS E VOLUME

## 19. Fase 14 — Separar validade, confluência e score estatístico

### 19.1 Problema atual

O score usa principalmente tamanho absoluto e sessão.

### 19.2 Novo modelo em três camadas

#### Camada A — Validade SMC

Resultado binário ou enum:

- estrutura válida;
- swing disponível;
- rompimento válido;
- deslocamento válido;
- origem válida;
- zona geométrica válida;
- freshness mínima;
- sem invalidação prévia.

Saídas:

- `VALID`;
- `CANDIDATE_ONLY`;
- `REJECTED`.

#### Camada B — Confluência SMC

Pontuar:

- força do deslocamento;
- FVG/imbalance;
- sweep;
- inducement;
- Extreme/Decisional;
- premium/discount;
- alinhamento HTF;
- protected level;
- freshness;
- número de toques.

#### Camada C — Calibração estatística

Manter como modificadores:

- sessão;
- tamanho;
- volume;
- ativo;
- timeframe;
- volatilidade.

### 19.3 Normalização

Substituir tamanho absoluto como fundamento principal por:

- ticks;
- ATR;
- percentual do preço;
- percentil histórico por ativo/timeframe.

### 19.4 Saídas

- `smc_validity_status`;
- `smc_validity_reasons`;
- `confluence_score`;
- `confluence_label`;
- `statistical_score`;
- `statistical_profile_id`;
- `final_quality_score`;
- `final_quality_label`.

### 19.5 Critérios de aceite

- Um OB estruturalmente inválido nunca recebe `HIGH` só por sessão/tamanho.
- Score estatístico não substitui validade SMC.

---

## 20. Fase 15 — Corrigir REJECTION

### 20.1 Regras mínimas

Para classificar como `REJECTION`, exigir combinação de:

- wick dominante;
- corpo mínimo ou tratamento específico para doji;
- fechamento de rejeição;
- localização do fechamento dentro do range;
- sweep ou toque de liquidez relevante;
- deslocamento posterior;
- confirmação estrutural.

### 20.2 Proteção contra doji

- Não dividir por corpo quase zero sem tratamento.
- Usar epsilon em ticks/ATR.
- Classificar doji em categoria própria quando necessário.

### 20.3 Critérios de aceite

- Wick grande isolado não é suficiente.
- Rejection depende de contexto e consequência estrutural.

---

## 21. Fase 16 — Corrigir STACKED e separar BREAKER

### 21.1 STACKED

Definir como sobreposição/nesting válido entre zonas:

- ainda ativas ou fresh;
- da mesma direção;
- relacionadas temporalmente;
- no mesmo contexto ou em múltiplos timeframes;
- sem depender de informação futura.

### 21.2 Restrições temporais

Ao classificar um novo OB:

- considerar apenas estado das zonas anteriores conhecido até `available_index` do novo OB;
- não usar `mitigated_index` futuro;
- aplicar janela temporal máxima configurável.

### 21.3 BREAKER clássico

Manter separado:

1. OB original válido;
2. falha/invalidação;
3. rompimento através da zona;
4. mudança estrutural compatível;
5. reteste da zona invertida.

### 21.4 Critérios de aceite

- `STACKED` não significa “sobrepõe qualquer OB mitigado antigo”.
- `BREAKER` mantém semântica SMC própria.

---

## 22. Fase 17 — Corrigir volume e Percentage

### 22.1 Problema

O atual `OBVolume` soma o candle de rompimento e dois candles anteriores, mas o nome sugere volume da origem.

### 22.2 Solução

Separar:

- `origin_volume`;
- `displacement_volume`;
- `breakout_volume`;
- `relative_volume`;
- `volume_available`;
- `volume_source`.

### 22.3 Percentage

- Renomear a métrica atual para algo descritivo, caso seja mantida.
- Não tratá-la como participação institucional.
- Documentar fórmula, janela e limitações.
- Preferir medidas de desequilíbrio de volume apenas se a fonte de dados permitir.

### 22.4 Critérios de aceite

- Nenhum nome de campo induz interpretação incorreta.
- Ausência de volume real não gera falsa precisão.

---

# PARTE VIII — API, VISUALIZAÇÃO E COMPATIBILIDADE

## 23. Fase 18 — Atualizar contratos e visual overlays

### 23.1 Campos mínimos na zona visual

- ID da zona;
- ID do evento estrutural;
- direção;
- papel;
- subtipo;
- origem;
- confirmação;
- disponibilidade;
- limites;
- midpoint;
- estado atual;
- número de toques;
- profundidade máxima;
- mitigação;
- invalidação;
- validade SMC;
- confluência;
- score estatístico;
- liquidity alignment;
- FVG alignment;
- freshness;
- modo de refinamento.

### 23.2 Plotagem

- `display_from` pode começar na origem para contexto visual.
- Marcar visualmente o ponto de confirmação.
- Impedir que scanner/backtest use a zona antes de `available_index`.
- Exibir estilos distintos para:
   - candidata;
   - confirmada/fresh;
   - parcialmente mitigada;
   - invalidada;
   - breaker.

### 23.3 Compatibilidade

- Manter campos legados durante janela de transição.
- Adicionar versão de schema.
- Emitir warning de depreciação para:
   - `close_mitigation`;
   - `prev` como default;
   - `Percentage` antigo;
   - `MitigatedIndex` como único estado.

---

# PARTE IX — TESTES

## 24. Estratégia geral de testes

Todos os testes devem ser determinísticos e independentes de internet.

### 24.1 Testes unitários

Cobrir:

- seleção de origem;
- delimitação de perna;
- rompimento;
- deslocamento;
- refinamento;
- interseção geométrica;
- penetração;
- midpoint;
- invalidação;
- freshness;
- Extreme/Decisional;
- rejection;
- stacked;
- breaker;
- volume;
- score.

### 24.2 Testes temporais

Para cada cenário:

- rodar com dataset completo;
- rodar candle a candle;
- comparar resultados disponíveis em cada instante;
- garantir que resultados passados não mudam por informação futura, salvo atualização explícita de estado.

### 24.3 Testes de regressão

Comparar:

- modo canônico novo;
- `PRE_BREAKOUT_CANDLE` legado;
- `LAST_OPPOSITE_CANDLE` legado;
- `EXTREME_OF_LEG` legado.

### 24.4 Casos obrigatórios

1. Bullish OB com último candle vendedor na origem.
2. Bearish OB com último candle comprador na origem.
3. Origem doji.
4. Origem por wick.
5. Base de três candles.
6. BOS sem deslocamento.
7. ChoCH sem confirmação posterior.
8. ChoCH seguido de BOS.
9. OB já tocado antes do BOS.
10. Toque apenas no proximal.
11. Toque até midpoint.
12. Mitigação completa.
13. Invalidação por wick.
14. Invalidação por fechamento.
15. Gap atravessando a zona.
16. Dois OBs no mesmo evento.
17. Extreme e Decisional simultâneos.
18. Stack MTF.
19. Breaker clássico.
20. Swing ainda não disponível.
21. FVG futuro não pode ser usado.
22. Sweep futuro não pode ser usado.
23. Timeframe sem volume.
24. Timezone naive e aware.
25. Zona com tamanho zero.
26. Dados com NaN.
27. Candles fora de ordem.
28. Reprocessamento idempotente.

### 24.5 Testes de propriedade

Validar invariantes:

- `top > bottom`;
- índices em ordem temporal;
- `available_index >= confirmed_index`;
- zona invalidada não volta para fresh;
- penetração entre 0% e 100% quando houver interseção parcial;
- IDs determinísticos;
- nenhum evento usa candles futuros.

### 24.6 Performance

Medir:

- complexidade por número de candles;
- número médio de zonas ativas;
- memória;
- impacto de MTF;
- impacto da máquina de estados.

Meta inicial:

- não degradar mais de 2x no mesmo dataset sem justificativa;
- evitar varreduras reversas completas repetidas por candle;
- preferir consumo de eventos pré-calculados.

---

# PARTE X — BACKTEST E RECALIBRAÇÃO

## 25. Fase 19 — Reexecutar calibração

### 25.1 Problema

O resultado anterior de 72% para `prev` foi obtido com definição simplificada de toque/respeito e precisa ser reavaliado.

### 25.2 Comparações

Comparar pelo menos:

- `CANONICAL_ORIGIN`;
- `PRE_BREAKOUT_CANDLE`;
- `LAST_OPPOSITE_CANDLE`;
- `EXTREME_OF_LEG`;
- `BASE_CLUSTER`.

### 25.3 Métricas

Por ativo/timeframe:

- quantidade de candidatos;
- quantidade de confirmados;
- taxa de freshness;
- taxa de primeiro toque;
- taxa de midpoint;
- taxa de mitigação completa;
- taxa de invalidação;
- reação média em ticks/ATR;
- MAE;
- MFE;
- tempo até primeiro toque;
- tempo até reação;
- número de toques;
- resultado por sessão;
- resultado por papel;
- resultado por FVG;
- resultado por sweep;
- resultado por qualidade;
- falsos positivos;
- cobertura.

### 25.4 Regras estatísticas

- Não otimizar definitivamente com dataset insuficiente.
- Separar treino, validação e holdout temporal.
- Evitar selecionar thresholds no mesmo período usado para reportar desempenho.
- Reportar intervalos de confiança.
- Preservar perfis por ativo/timeframe.

### 25.5 Critérios de aceite

- Métrica de respeito possui definição documentada.
- Resultado reproduzível.
- O modo canônico é comparado honestamente com o legado.
- Nenhuma promoção automática baseada apenas no backtest.

---

# PARTE XI — CONFIGURAÇÃO

## 26. Atualização de `OBQualityConfig` ou equivalente

Organizar configuração em blocos:

### 26.1 Estrutura

- tipos de eventos aceitos;
- swing/internal;
- fechamento obrigatório;
- margem mínima de rompimento.

### 26.2 Deslocamento

- ATR mínimo;
- corpo/range mínimo;
- eficiência mínima;
- FVG obrigatório ou opcional;
- quantidade máxima/mínima de candles.

### 26.3 Origem

- modo padrão;
- busca máxima para trás;
- aceitar doji;
- aceitar same-color;
- aceitar cluster;
- regras de fallback explícito.

### 26.4 Refinamento

- full/body/half/wick/MTF;
- timeframe inferior permitido;
- limites de nesting.

### 26.5 Freshness

- máximo de toques pré-confirmação;
- penetração máxima;
- midpoint permitido ou não.

### 26.6 Ciclo de vida

- toque por wick/body;
- invalidação por wick/close;
- tolerância;
- máximo de testes;
- expiração.

### 26.7 Qualidade

- pesos SMC;
- pesos estatísticos;
- thresholds por ativo/timeframe;
- versão do perfil.

### 26.8 Guardrails

- `shadow_only` obrigatório;
- `apply_automatically=False`;
- `can_promote_trade=False`;
- `requires_backtest_validation=True`.

---

# PARTE XII — MIGRAÇÃO E COMPATIBILIDADE

## 27. Estratégia de migração

### 27.1 Versão de schema

Introduzir versão explícita:

- legado: `OB_SCHEMA_V1`;
- novo: `OB_SCHEMA_V2_CANONICAL`.

### 27.2 Execução paralela

Durante o período shadow:

- rodar detector legado;
- rodar detector canônico;
- persistir ambos separadamente;
- comparar por evento;
- não substituir produção imediatamente.

### 27.3 Compatibilidade

- Adicionar adaptador V2 → contrato legado onde necessário.
- Não remover campos antigos na primeira entrega.
- Emitir warnings de depreciação.
- Atualizar consumidores gradualmente.

### 27.4 Rollback

- Feature flag para retornar ao detector legado.
- Migração sem alteração destrutiva.
- Nenhuma mudança irreversível no banco.

---

# PARTE XIII — ORGANIZAÇÃO DE IMPLEMENTAÇÃO

## 28. Sequência recomendada de commits

1. `test(ob): add baseline fixtures and current behavior snapshots`
2. `model(ob): add canonical temporal and structure fields`
3. `feat(structure): expose canonical break events`
4. `fix(ob): enforce anti-lookahead availability`
5. `feat(ob): add displacement gate`
6. `feat(ob): detect impulse leg boundaries`
7. `feat(ob): add canonical origin selector`
8. `feat(ob): support extreme and decisional roles`
9. `feat(ob): refine zone before lifecycle evaluation`
10. `feat(ob): add pre-confirmation freshness`
11. `feat(ob): add lifecycle state machine`
12. `feat(ob): integrate fvg and liquidity evidence`
13. `refactor(ob): split validity confluence and statistical scoring`
14. `fix(ob): harden rejection stacked and breaker semantics`
15. `fix(ob): rename and correct volume metrics`
16. `feat(ui): expose canonical ob metadata and states`
17. `test(ob): add temporal property and integration coverage`
18. `bench(ob): compare canonical and legacy profiles`
19. `docs(ob): publish migration and validation report`

Cada commit deve:

- compilar;
- manter testes existentes ou atualizar justificadamente;
- não misturar múltiplas fases grandes;
- incluir testes da mudança.

---

## 29. Mapa lógico de módulos sugerido

A IA executora deve adaptar os nomes ao repositório real, sem criar duplicações desnecessárias.

- `structure_events.py` — eventos BOS/ChoCH canônicos.
- `ob_displacement.py` — métricas e gate de deslocamento.
- `ob_origin_selector.py` — origem, base, wick e papéis.
- `ob_zone_refinement.py` — full/body/half/wick/MTF.
- `ob_lifecycle.py` — freshness, toque, mitigação e invalidação.
- `ob_evidence.py` — FVG, liquidez, sweep e pricing.
- `ob_scoring.py` — validade, confluência e estatística.
- `ob_models.py` ou modelos existentes — contratos.
- `ob_legacy_adapter.py` — compatibilidade temporária.
- `ob_visualization.py` — overlays.

Não criar novos módulos se a arquitetura oficial já possuir equivalentes.

---

# PARTE XIV — CRITÉRIOS DE ACEITE POR MARCO

## 30. Marco A — Segurança temporal

Aprovado quando:

- índices reais armazenados;
- anti-lookahead validado;
- decisões bloqueadas antes de `available_index`;
- swings indisponíveis rejeitados.

## 31. Marco B — Origem canônica

Aprovado quando:

- `CANONICAL_ORIGIN` é o padrão;
- `prev` deixa de ser padrão;
- fallback silencioso removido;
- origem auditável.

## 32. Marco C — Ciclo de vida correto

Aprovado quando:

- interseção geométrica corrigida;
- toque, midpoint, mitigação e invalidação separados;
- primeiro toque não consome automaticamente a zona;
- gaps tratados.

## 33. Marco D — Evidências SMC

Aprovado quando:

- deslocamento calculado;
- FVG ligado ao evento;
- liquidez/sweep calculados;
- Extreme/Decisional suportados.

## 34. Marco E — Score confiável

Aprovado quando:

- validade estrutural separada de score;
- tamanho absoluto não domina universalmente;
- perfis versionados por ativo/timeframe.

## 35. Marco F — Backtest validado

Aprovado quando:

- respeito redefinido;
- modos comparados;
- holdout temporal usado;
- relatório completo produzido.

---

# PARTE XV — DEFINITION OF DONE

## 36. Condições finais obrigatórias

A execução só pode ser considerada concluída quando:

1. Todos os testes novos e existentes passam.
2. Não há lookahead detectado.
3. `CANONICAL_ORIGIN` é o default oficial.
4. Modos legados continuam disponíveis apenas para comparação.
5. Nenhum fallback cria OB artificial sem marcação.
6. O ciclo de vida distingue toque, mitigação e invalidação.
7. O score SMC não depende apenas de tamanho/sessão.
8. Campos `structure_confirmed` e `liquidity_aligned` são realmente calculados.
9. Rejection, Stacked e Breaker possuem semânticas distintas.
10. Volume foi renomeado ou corrigido.
11. O dashboard exibe disponibilidade, estado e papel da zona.
12. O detector antigo pode ser reativado por feature flag.
13. Tudo permanece `shadow_only`.
14. Um relatório final documenta:
    - arquivos alterados;
    - decisões;
    - testes;
    - resultados;
    - riscos;
    - limitações;
    - comparação legado versus canônico.

---

# PARTE XVI — INSTRUÇÃO EXECUTIVA PARA A IA DE CÓDIGO

## 37. Procedimento obrigatório

A IA executora deve seguir estas regras:

1. Ler a arquitetura oficial mais recente antes de alterar código.
2. Inspecionar os modelos e módulos existentes antes de criar novos arquivos.
3. Não presumir nomes de campos, enums ou diretórios.
4. Reutilizar eventos estruturais existentes quando forem anti-lookahead e canônicos.
5. Implementar uma fase por vez.
6. Rodar testes após cada fase.
7. Não alterar defaults de produção sem feature flag.
8. Não remover compatibilidade antes da fase de migração.
9. Não promover resultados para trade.
10. Não escrever no banco de produção.
11. Não usar dados futuros em classificação histórica.
12. Não declarar uma fase concluída sem evidência de testes.
13. Gerar relatório ao final de cada marco.
14. Interromper e documentar qualquer ambiguidade arquitetural relevante em vez de improvisar.

---

## 38. Relatório final exigido

Criar `RELATORIO_FINAL_CORRECAO_ORDER_BLOCK_ENGINE_V2.md` contendo:

- resumo executivo;
- baseline;
- arquivos criados;
- arquivos alterados;
- enums e modelos adicionados;
- mudanças de contrato;
- regras anti-lookahead;
- definição final de OB;
- definição de deslocamento;
- definição de mitigação;
- definição de invalidação;
- definição de respeito;
- integração com FVG e liquidez;
- novo scoring;
- testes executados;
- resultados por suíte;
- benchmarks;
- comparação legado versus canônico;
- limitações conhecidas;
- próximos passos;
- hash dos commits;
- status final de guardrails.

---

# 39. Prioridade resumida

A ordem obrigatória de prioridade é:

1. Corrigir índices e anti-lookahead.
2. Consumir estrutura canônica.
3. Implementar deslocamento.
4. Encontrar origem real.
5. Refinar antes de acompanhar a zona.
6. Implementar freshness.
7. Implementar máquina de estados.
8. Suportar Extreme/Decisional.
9. Integrar FVG, liquidez, sweep e inducement.
10. Separar validade, confluência e score estatístico.
11. Corrigir subtipos e volume.
12. Reexecutar backtests e calibração.

---

## 40. Resultado funcional final

O sistema deve deixar de responder apenas:

> “houve fechamento além de um swing e o candle anterior virou OB”

E passar a responder de forma auditável:

> “um evento estrutural previamente disponível foi rompido por deslocamento válido; a origem da perna foi localizada e refinada; a zona estava fresh no instante da confirmação; o OB ficou disponível sem lookahead; suas evidências de FVG, liquidez, pricing e contexto foram calculadas; seu ciclo de vida passou a ser acompanhado até toque, reação, mitigação, invalidação ou expiração.”

---
# SEÇÕES ESPECÍFICAS — ORDER BLOCK ENGINE V3

## Ownership do Domínio (Confirmado)

Order Block é dono exclusivo de:
- `OrderBlockV3`
- `OrderBlockLifecycleEventV3`
- `OrderBlockRefinementV3`
- `OrderBlockEngineStateV3`

**Regra:** OB consome StructureEvent, StructureLeg, Liquidity e FVG como evidência opcional. Não pode depender de FVG que só se torna contextual após o próprio OB.

## Contratos Produzidos

| Contrato | Consumidor | Gate |
|---|---|---|
| `OrderBlockV3` | Context Association | G8 |

## Contratos Consumidos

| Contrato | Produtor | Gate |
|---|---|---|
| `StructureEventV3` | Structure | G3 |
| `StructureLegV3` | Structure | G3 |
| `LiquidityEventV3` | Liquidity | G6 |
| `FvgEventV3` | FVG | G7 (opcional) |
| `DealingRangeV3` | Retracement | G5 |

## Gate de Entrada

G7 (FVG Core Ready) para evidência opcional

## Gate de Saída

**G8 — OB Core Ready:** structure event, structure leg, liquidity, origem/freshness, lifecycle, FVG evidence opcional.

## Regra Crítica

- Preservar subtipos `NORMAL`, `REJECTION`, `STACKED`
- `STACKED` não é breaker block clássico
- FVG é evidência opcional para validade/qualidade
- OB não pode exigir FVG que só se torna contextual após o próprio OB

## Caminhos Batch

- `smc_engine_v3/order_blocks.py`

## Caminhos Incrementais

- `incremental/components/ob.py`
