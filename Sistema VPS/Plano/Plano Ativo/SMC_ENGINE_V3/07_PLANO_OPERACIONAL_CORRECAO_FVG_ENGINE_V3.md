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


# PLANO OPERACIONAL — CORREÇÃO DA ENGINE DE FAIR VALUE GAPS SMC V2

## 1. Identificação do plano

**Projeto:** SMC Engine V2  
**Módulo principal afetado:** detecção, confirmação, classificação, ciclo de vida e visualização de Fair Value Gaps  
**Documento-base técnico:** implementação atual enviada pelo usuário (`Código colado(1).py`)  
**Referência conceitual:** *SMC Bible — DexterrFX* e conceitos SMC/ICT já adotados pelo projeto  
**Objetivo:** transformar o detector atual, que reconhece corretamente o gap geométrico clássico de três candles, em uma engine FVG temporalmente segura, contextualizada, rastreável e adequada a backtest, scanner e operação shadow, sem lookahead e sem confundir simples desequilíbrio geométrico com FVG operacional de deslocamento.

---

## 2. Resultado esperado

Ao final deste plano, a engine deve:

1. Detectar corretamente o FVG clássico de três candles, preservando os limites canônicos:
   - bullish: `high[candle_1] < low[candle_3]`;
   - bearish: `low[candle_1] > high[candle_3]`.
2. Registrar corretamente os três candles do padrão e seus marcos temporais:
   - início do padrão;
   - candle de deslocamento;
   - candle que completa o padrão;
   - confirmação;
   - disponibilidade;
   - primeiro instante executável sem lookahead.
3. Separar explicitamente:
   - `RAW_IMBALANCE` ou FVG geométrico;
   - FVG direcional validado;
   - FVG de deslocamento;
   - FVG associado a evento estrutural;
   - FVG de entrada do modelo do livro;
   - IFVG e BPR como estruturas derivadas.
4. Eliminar lookahead em:
   - confirmação do padrão;
   - disponibilidade para backtest;
   - junção de FVGs;
   - classificação de displacement;
   - mitigação;
   - plotagem;
   - associação com BOS/ChoCH;
   - score e confluências.
5. Implementar um ciclo de vida explícito:
   - candidato;
   - confirmado;
   - fresh;
   - tocado;
   - parcialmente preenchido;
   - Consequent Encroachment alcançado;
   - totalmente preenchido;
   - invalidado;
   - invertido;
   - expirado.
6. Substituir a regra binária `midpoint tocado = mitigado` por estados e métricas de profundidade.
7. Impedir que gaps de sessão, buracos de coleta, candles não contíguos ou rollover sejam classificados como FVG intraday canônico.
8. Refatorar a junção de FVGs para não criar retângulos sintéticos em regiões que foram negociadas normalmente.
9. Associar FVGs à perna impulsiva e ao evento estrutural correto, respeitando direção e causalidade.
10. Integrar FVG com:
    - BOS/ChoCH/MSS;
    - Order Block e POI;
    - liquidez e sweep;
    - premium/discount;
    - contexto MTF;
    - sessão e volatilidade.
11. Separar:
    - validade geométrica;
    - validade SMC;
    - qualidade contextual;
    - calibração estatística por ativo e timeframe.
12. Preservar os guardrails atuais:
    - execução `shadow_only`;
    - nenhuma escrita em produção;
    - nenhuma promoção automática de trade;
    - nenhum campo de score usado isoladamente como decisão de entrada.

---

## 3. Escopo

### 3.1 Incluído

- Auditoria integral do detector atual.
- Correção dos índices temporais.
- Novo contrato de dados de FVG.
- Separação entre gap geométrico e FVG validado.
- Gate de deslocamento.
- Associação causal com impulso e estrutura.
- Controle de continuidade temporal, sessão e rollover.
- Filtro por tick e volatilidade.
- Máquina de estados de preenchimento e invalidação.
- Refatoração da junção/agrupamento.
- Integração com OB, POI, liquidez e MTF.
- Classificação de FVGs por tipo e qualidade.
- IFVG e BPR como fase derivada, após estabilização da base.
- Atualização de visualização, serialização e contratos de API.
- Correção ou remoção de métodos legados silenciosos.
- Testes unitários, temporais, integrados, de propriedade, performance e regressão.
- Reexecução de backtests e recalibração por ativo/timeframe.

### 3.2 Fora do escopo nesta execução

- Definição completa da estratégia de entrada, stop e alvo.
- Promoção automática de sinais para conta real.
- Escrita em tabelas de produção.
- Otimização definitiva com dataset insuficiente.
- Reescrita geral dos módulos de Swings, BOS/ChoCH, Liquidity, OB, Wyckoff, Elliott ou Risk Management.
- Inferência de negociação intrabar que não possa ser comprovada com o nível de resolução disponível.

---

## 4. Regras conceituais obrigatórias

### 4.1 FVG geométrico clássico

Usar três candles fechados:

- `candle_1 = i - 1`;
- `candle_2 = i`;
- `candle_3 = i + 1`.

**Bullish FVG:**

- `high[candle_1] < low[candle_3]`;
- `bottom = high[candle_1]`;
- `top = low[candle_3]`.

**Bearish FVG:**

- `low[candle_1] > high[candle_3]`;
- `bottom = high[candle_3]`;
- `top = low[candle_1]`.

A existência dessa geometria deve gerar primeiro um **candidato geométrico**, não necessariamente um FVG operacional de alta qualidade.

### 4.2 Relação temporal obrigatória

Para cada FVG devem existir, no mínimo:

- `pattern_start_index`: candle 1;
- `displacement_index`: candle 2;
- `pattern_end_index`: candle 3;
- `confirmed_index`: candle em que o candle 3 fechou e o padrão passou a ser conhecido;
- `available_index`: índice a partir do qual a informação pode ser consumida sem lookahead;
- `earliest_execution_index`: primeiro candle em que uma estratégia pode executar, conforme o modelo operacional;
- `first_touch_index`;
- `ce_reached_index`;
- `full_fill_index`;
- `invalidated_index`;
- `inverted_index`.

Regra padrão:

- `confirmed_index = pattern_end_index`;
- `available_index = pattern_end_index`, quando o pipeline publica eventos após o fechamento;
- `earliest_execution_index = pattern_end_index + 1`, quando a execução só pode ocorrer no próximo candle;
- nenhuma interação com a zona deve ser avaliada antes do instante operacional definido.

### 4.3 Ordem correta de processamento

1. Validar candles, timestamps, tick e continuidade.
2. Detectar o gap geométrico de três candles.
3. Registrar os três índices do padrão.
4. Confirmar o padrão somente após o candle 3 fechado.
5. Calcular tamanho em pontos, ticks e ATR válido.
6. Avaliar direção e qualidade do candle de deslocamento.
7. Associar o FVG à perna impulsiva.
8. Associar, quando possível, a BOS/ChoCH/MSS e POI.
9. Classificar o FVG.
10. Publicar a zona a partir de `available_index`.
11. Acompanhar o ciclo de vida somente com dados posteriores disponíveis.
12. Calcular confluências e score estatístico sem alterar a validade histórica.

### 4.4 Princípios de segurança

- Não usar o candle 3 antes de seu fechamento.
- Não usar eventos estruturais que ainda não estavam confirmados.
- Não expandir retroativamente uma zona com FVGs formados no futuro.
- Não considerar toque quando não existe interseção geométrica comprovada.
- Não assumir preenchimento intrabar que o OHLC não comprova.
- Não misturar gaps de sessão ou rollover com FVG intraday sem classificação explícita.
- Não apagar FVGs-filhos ao criar agrupamentos analíticos.

---

# PARTE I — PREPARAÇÃO E BASELINE

## 5. Fase 0 — Auditoria inicial e congelamento do comportamento atual

### 5.1 Objetivo

Criar uma referência reproduzível antes de alterar o detector.

### 5.2 Tarefas

1. Localizar no repositório:
   - arquivo real de `calculate_fvg`;
   - modelo `FvgV2`;
   - enums de direção e mitigação;
   - módulo de ATR;
   - calendário/sessão de mercado;
   - módulo de BOS/ChoCH;
   - módulo de Order Blocks e POIs;
   - módulo de liquidez;
   - serialização e persistência shadow;
   - visual overlays;
   - scanners e backtests consumidores;
   - testes atuais.
2. Registrar caminhos, assinaturas públicas, dependências e consumidores.
3. Auditar todos os usos de:
   - `calculate_fvg`;
   - `calculate_fvg_records`;
   - `tag_displacement_fvgs`;
   - `_join_consecutive_fvg`;
   - `_calculate_mitigation`;
   - `detect_fvg`;
   - `detect_fvg_with_params`;
   - `calculate_mitigation`.
4. Rodar a suíte completa antes de modificar qualquer arquivo.
5. Salvar baseline contendo:
   - commit/hash atual;
   - total de testes;
   - aprovados, falhos e ignorados;
   - duração;
   - datasets utilizados;
   - total de FVGs por ativo/timeframe;
   - bullish/bearish;
   - com e sem `join_consecutive`;
   - distribuição de tamanho em pontos e ATR;
   - total marcado como displacement;
   - total mitigado pelo midpoint;
   - idade média até toque;
   - taxa de FVGs cross-session;
   - impacto dos defaults atuais.
6. Congelar fixtures determinísticas com, no mínimo:
   - bullish FVG clássico;
   - bearish FVG clássico;
   - ausência de gap;
   - igualdade exata entre limites;
   - gap de um tick;
   - candle central contrário;
   - doji central;
   - dois FVGs consecutivos sobrepostos;
   - dois FVGs consecutivos separados em preço;
   - FVG antes, durante e depois de BOS;
   - FVG de direção oposta ao BOS;
   - gap de sessão;
   - buraco de coleta;
   - rollover;
   - touch proximal;
   - CE alcançado;
   - preenchimento total;
   - gap-through sem negociação comprovada;
   - inversão.
7. Gerar snapshots do DataFrame e de `FvgV2` atuais.

### 5.3 Entregáveis

- `RELATORIO_BASELINE_FVG_ENGINE_V2.md`.
- Fixtures versionadas.
- Snapshot dos resultados atuais.
- Mapa de dependências e consumidores.

### 5.4 Critérios de aceite

- Baseline reproduzível.
- Resultados atuais registrados antes de qualquer correção.
- Nenhuma alteração funcional misturada à fase de auditoria.
- Todos os métodos legados com consumidores identificados.

---

# PARTE II — CORREÇÃO TEMPORAL E CONTRATO CANÔNICO

## 6. Fase 1 — Corrigir anti-lookahead e índices do padrão

### 6.1 Problema atual

O FVG é registrado no índice do candle central, mas seus limites dependem do candle seguinte. Mesmo assim, confirmação e disponibilidade são atribuídas ao candle central.

### 6.2 Objetivo

Garantir que nenhum FVG seja conhecido, plotado ou usado antes do fechamento do terceiro candle.

### 6.3 Campos obrigatórios

Adicionar ao modelo, ou equivalente:

- `pattern_start_index`;
- `pattern_start_at`;
- `displacement_index`;
- `displacement_at`;
- `pattern_end_index`;
- `pattern_end_at`;
- `confirmed_index`;
- `confirmed_at`;
- `available_index`;
- `available_at`;
- `earliest_execution_index`;
- `earliest_execution_at`;
- `detection_version`;
- `is_lookahead_safe`;
- `lookahead_validation_reason`.

Manter `ref_index` apenas por compatibilidade, documentando qual índice ele representa. O contrato novo não pode depender de um `ref_index` ambíguo.

### 6.4 Regras temporais

- O candle 3 deve estar fechado.
- `confirmed_index` nunca pode ser menor que `pattern_end_index`.
- `available_index` nunca pode ser menor que `confirmed_index`.
- `earliest_execution_index` deve respeitar a semântica do backtest:
  - entrada no fechamento: somente se o motor comprovar que o evento foi publicado após o fechamento e não usa o mesmo preço de forma impossível;
  - entrada na abertura seguinte: `pattern_end_index + 1`.
- O acompanhamento de toque deve começar no primeiro candle posterior ao instante de disponibilidade operacional.
- A plotagem pode mostrar a geometria histórica, mas deve diferenciar visualmente formação, confirmação e tradabilidade.

### 6.5 Validação anti-lookahead

Criar um teste incremental:

1. Processar candles até `pattern_end_index - 1`: nenhum FVG deve existir.
2. Acrescentar o candle 3 fechado: o FVG pode ser confirmado.
3. Reprocessar com candles futuros: os campos históricos de origem, confirmação, limites e score inicial não podem mudar.
4. Truncar o dataset em cada candle e comparar com a execução completa filtrada pelo mesmo instante.

### 6.6 Testes obrigatórios

- Padrão bullish confirmado somente no candle 3.
- Padrão bearish confirmado somente no candle 3.
- Nenhum overlay antes de `confirmed_index` no modo operacional.
- Nenhum toque contabilizado no candle central.
- Nenhuma execução possível antes de `earliest_execution_index`.
- Resultado idêntico entre modo batch e modo incremental até cada timestamp.

### 6.7 Critérios de aceite

- Zero FVG disponível no candle central.
- Zero divergência temporal batch versus incremental.
- Todos os consumidores atualizados para usar os índices canônicos.
- Testes específicos de lookahead aprovados.

---

## 7. Fase 2 — Criar evento canônico `FvgEventV3`

### 7.1 Objetivo

Substituir o registro mínimo atual por um contrato rastreável e extensível.

### 7.2 Estrutura mínima

O evento deve conter, no mínimo:

**Identidade**

- `fvg_id` estável e determinístico;
- `schema_version`;
- `asset_id` ou símbolo;
- `timeframe`;
- `direction`;
- `detection_version`.

**Padrão temporal**

- campos da Fase 1;
- `session_id`;
- `contract_id`, quando aplicável;
- `source_data_version`.

**Geometria**

- `top`;
- `bottom`;
- `midpoint` ou `ce_price`;
- `size_points`;
- `size_ticks`;
- `size_atr`;
- `price_tick`;
- `proximal_price`;
- `distal_price`.

**Classificação**

- `geometric_status`;
- `validation_status`;
- `fvg_class`;
- `displacement_status`;
- `structure_alignment_status`;
- `quality_label`;
- `quality_score`;
- `quality_reasons`;
- `rejection_reasons`.

**Contexto**

- `impulse_leg_id`;
- `structure_event_id`;
- `source_poi_id`;
- `source_ob_id`;
- `liquidity_event_id`;
- `higher_timeframe_context_id`;
- `premium_discount_position`.

**Ciclo de vida**

- campos de toque, CE, fill, invalidação, inversão e expiração;
- `status`;
- `fill_percentage`;
- `max_penetration_percentage`;
- `touch_count`;
- `is_fresh`.

**Auditoria**

- `raw_geometry`;
- `config_snapshot`;
- `evidence`;
- `guardrails`.

### 7.3 Identificador determinístico

O ID deve derivar de campos estáveis, por exemplo:

- ativo;
- timeframe;
- direção;
- `pattern_start_index` ou timestamp;
- `pattern_end_index` ou timestamp;
- limites normalizados em ticks;
- versão do detector.

Não usar UUID aleatório como identidade primária do mesmo evento em recomputações, pois isso dificulta deduplicação e regressão.

### 7.4 Compatibilidade

- Manter adaptador temporário para `FvgV2`.
- Preservar o DataFrame antigo durante a execução paralela.
- Documentar campos deprecados.
- Não remover campos antigos até que todos os consumidores estejam migrados.

### 7.5 Critérios de aceite

- Evento serializável e determinístico.
- Reprocessamento do mesmo dataset gera os mesmos IDs.
- Compatibilidade temporária comprovada.
- Nenhum campo temporal ambíguo.

---

# PARTE III — DETECÇÃO GEOMÉTRICA, CONTINUIDADE E VALIDAÇÃO

## 8. Fase 3 — Isolar o detector geométrico puro

### 8.1 Objetivo

Preservar a fórmula correta atual em uma camada simples, determinística e sem contexto.

### 8.2 Responsabilidade exclusiva

O detector geométrico deve apenas:

1. receber candles fechados e validados;
2. verificar a relação entre candle 1 e candle 3;
3. calcular limites e tamanho bruto;
4. emitir `GEOMETRIC_CANDIDATE`;
5. registrar os três índices.

Ele não deve:

- classificar displacement;
- associar BOS/ChoCH;
- calcular qualidade final;
- juntar zonas;
- determinar mitigação;
- consultar dados futuros.

### 8.3 Igualdade e tolerância

Definir explicitamente:

- igualdade não forma FVG;
- a diferença deve respeitar `price_tick`;
- arredondar limites para a grade do ativo;
- rejeitar gap inferior a `min_gap_ticks`;
- registrar motivo de rejeição.

### 8.4 Validações de entrada

- OHLC finito;
- `high >= max(open, close)`;
- `low <= min(open, close)`;
- `high >= low`;
- timestamps crescentes;
- sem duplicatas não resolvidas;
- preço compatível com tick, conforme tolerância do feed.

### 8.5 Critérios de aceite

- Fórmula geométrica preservada.
- Saída determinística.
- Nenhuma dependência de estrutura ou futuro.
- Casos bullish, bearish, igualdade e um tick cobertos por testes.

---

## 9. Fase 4 — Validar continuidade temporal, sessão e rollover

### 9.1 Problema atual

Qualquer trio de linhas consecutivas do DataFrame é tratado como três candles contínuos, mesmo quando existe fechamento de sessão, falha de coleta ou mudança de contrato.

### 9.2 Objetivo

Impedir falsos FVGs causados por descontinuidade de dados.

### 9.3 Verificações obrigatórias

Para cada trio:

- diferença temporal esperada entre candle 1, 2 e 3;
- ausência de candle faltante;
- mesma sessão, quando exigido;
- mesmo contrato futuro;
- ausência de rollover no intervalo;
- ausência de reset de feed;
- ausência de dados marcados como incompletos;
- calendário oficial do ativo.

### 9.4 Classificações

Não descartar silenciosamente. Classificar como:

- `CONTIGUOUS_INTRASESSION`;
- `CROSS_SESSION_GAP`;
- `MISSING_BAR_GAP`;
- `ROLLOVER_GAP`;
- `CONTRACT_CHANGE_GAP`;
- `UNKNOWN_DISCONTINUITY`.

O perfil operacional intraday deve aceitar apenas `CONTIGUOUS_INTRASESSION`, salvo configuração explícita.

### 9.5 Configuração mínima

- `require_contiguous_bars`;
- `allow_cross_session_fvg`;
- `allow_rollover_fvg`;
- `allow_contract_change_fvg`;
- `expected_bar_interval`;
- `session_calendar_id`;
- `max_timestamp_tolerance`.

Defaults recomendados para WINFUT intraday:

- exigir continuidade;
- não aceitar cross-session como FVG canônico;
- não aceitar rollover;
- não aceitar mudança de contrato.

### 9.6 Critérios de aceite

- Gap de abertura não aparece como FVG intraday canônico.
- Buraco de coleta não aparece como FVG válido.
- Eventos rejeitados permanecem auditáveis.
- Testes de calendário e rollover aprovados.

---

## 10. Fase 5 — Implementar gate de FVG validado e displacement

### 10.1 Objetivo

Separar o simples gap geométrico de um desequilíbrio causado por deslocamento relevante.

### 10.2 Métricas do candle central

Calcular, no mínimo:

- `middle_body_points`;
- `middle_body_ticks`;
- `middle_body_atr`;
- `middle_range_points`;
- `middle_range_atr`;
- `body_to_range_ratio`;
- `close_location_value`;
- `direction_matches_gap`;
- `upper_wick_ratio`;
- `lower_wick_ratio`;
- `relative_volume`, se houver volume confiável.

### 10.3 Métricas do impulso

- tamanho total da perna em pontos e ATR;
- número de candles da perna;
- velocidade por candle;
- sobreposição média entre candles;
- quantidade de candles direcionais;
- eficiência do deslocamento;
- distância rompida além da estrutura;
- presença de um ou mais FVGs na perna.

### 10.4 Classes mínimas

- `GEOMETRIC_FVG`: somente geometria.
- `DIRECTIONAL_FVG`: candle central e fechamento coerentes.
- `DISPLACEMENT_FVG`: geometria + deslocamento válido.
- `STRUCTURE_FVG`: pertence à perna que causa ou confirma evento estrutural.
- `BOOK_ENTRY_FVG`: atende ao modelo específico de entrada definido a partir do POI e do primeiro pullback.

As classes podem ser cumulativas ou hierárquicas, mas o contrato deve ser explícito.

### 10.5 Candle central contrário

Não usar apenas `require_middle_direction` como chave binária global.

Regra recomendada:

- candle central alinhado aumenta a qualidade;
- candle contrário pode permanecer como `GEOMETRIC_FVG`;
- para subir de classe, deve compensar com métricas fortes de deslocamento e contexto;
- doji deve possuir tratamento específico para evitar aprovação por corpo quase zero.

### 10.6 Configuração

- `min_gap_ticks`;
- `min_gap_atr`;
- `min_middle_body_atr`;
- `min_middle_range_atr`;
- `min_body_to_range_ratio`;
- `min_close_location_value`;
- `min_impulse_efficiency`;
- `max_overlap_ratio`;
- `require_direction_alignment_for_validated`;
- `require_displacement_for_operational`.

### 10.7 Critérios de aceite

- Todo FVG operacional deriva de um candidato geométrico válido.
- Nem todo candidato geométrico sobe para FVG operacional.
- Motivos de aprovação/rejeição persistidos.
- Candle central contrário não recebe qualidade alta sem evidência adicional.
- Thresholds configuráveis por ativo/timeframe.

---

## 11. Fase 6 — Normalizar tamanho por tick e ATR canônico

### 11.1 Objetivo

Eliminar comparações frágeis em ponto flutuante e garantir portabilidade entre ativos.

### 11.2 Tick

- obter `price_tick` do cadastro canônico do ativo;
- normalizar limites para a grade;
- calcular `size_ticks`;
- rejeitar tamanho abaixo de `min_gap_ticks`;
- registrar erro quando o tick não estiver disponível e o perfil exigir tick.

### 11.3 ATR

- reutilizar o ATR canônico do projeto;
- definir fórmula oficial, preferencialmente a mesma usada nos demais módulos;
- exigir warmup completo;
- registrar `atr_ready`;
- não classificar qualidade alta antes do warmup;
- não usar `rolling(..., min_periods=1)` como se fosse ATR-14 completo.

### 11.4 Campos

- `atr_period`;
- `atr_method`;
- `atr_value_at_confirmation`;
- `atr_ready`;
- `size_atr`;
- `middle_body_atr`;
- `middle_range_atr`.

### 11.5 Critérios de aceite

- FVG menor que um tick não existe.
- Limites sempre alinhados à grade do ativo.
- ATR incompleto não é tratado como ATR pronto.
- Mesmo perfil funciona em WINFUT, Forex e outros ativos por normalização.

---

# PARTE IV — PERNA IMPULSIVA E ESTRUTURA

## 12. Fase 7 — Criar evento canônico de perna impulsiva

### 12.1 Objetivo

Permitir que o FVG seja ligado à causa estrutural correta, e não apenas à proximidade temporal de um BOS.

### 12.2 Campos mínimos da perna

- `impulse_leg_id`;
- `direction`;
- `start_index`;
- `end_index`;
- `available_index`;
- `start_price`;
- `end_price`;
- `range_points`;
- `range_atr`;
- `candle_count`;
- `efficiency_ratio`;
- `overlap_ratio`;
- `structure_event_id`;
- `source_poi_id`;
- `is_displacement_valid`.

### 12.3 Regras

- A perna deve ser delimitada sem usar pivôs futuros não disponíveis.
- O FVG pertence à perna se seus três candles estiverem dentro da janela causal ou se o padrão for completado imediatamente após o rompimento como continuação da mesma perna.
- A direção do FVG deve ser compatível com a direção da perna para classificação de displacement.
- Um FVG contrário pode existir geometricamente, mas não deve herdar a classificação de displacement da perna oposta.

### 12.4 Critérios de aceite

- Todo `DISPLACEMENT_FVG` possui `impulse_leg_id` válido.
- A mesma perna pode conter múltiplos FVGs.
- A associação é determinística e auditável.
- Nenhum evento futuro altera retroativamente a perna já disponível.

---

## 13. Fase 8 — Refazer a associação com BOS/ChoCH/MSS

### 13.1 Problema atual

A classificação atual marca FVGs que aparecem de 1 a 5 candles depois de qualquer BOS/ChoCH, sem exigir direção compatível ou participação na perna causadora.

### 13.2 Objetivo

Associar por causalidade, direção e disponibilidade.

### 13.3 Regras de associação

Um FVG pode receber `STRUCTURE_FVG=True` somente quando:

1. existe evento estrutural canônico;
2. o evento estava confirmado de forma lookahead-safe;
3. a direção do FVG é compatível com o evento;
4. o FVG pertence à perna que causou o rompimento ou à continuação imediata configurada;
5. a janela causal não atravessa descontinuidade de sessão/contrato;
6. o evento estrutural referencia o nível rompido e o índice de quebra;
7. a associação é resolvida por `structure_event_id`, não por simples proximidade global.

### 13.4 Janelas permitidas

Definir separadamente:

- FVG que se forma antes do candle de quebra dentro da perna;
- FVG cujo candle 3 é o candle de quebra;
- FVG que se completa imediatamente após o candle de quebra como continuação;
- FVG tardio, que não deve receber a mesma classificação.

### 13.5 BOS versus ChoCH

- BOS de continuação e ChoCH/MSS devem permanecer diferenciados.
- ChoCH isolado não deve receber automaticamente o mesmo peso de BOS confirmado.
- Guardar `break_kind`, `structure_kind` e `structure_strength`.

### 13.6 Substituição de `tag_displacement_fvgs`

A função atual deve ser:

- deprecada;
- substituída por um associador causal;
- ou transformada em adaptador que delega ao novo serviço.

Ela não pode continuar usando apenas `delta = fvg_index - broken_index`.

### 13.7 Critérios de aceite

- FVG bullish não é associado a BOS bearish.
- FVG anterior ao BOS, mas dentro da perna causal, pode ser classificado corretamente.
- FVG tardio não recebe displacement apenas por proximidade.
- Todos os vínculos possuem `structure_event_id` e razão de associação.

---

# PARTE V — AGRUPAMENTO E JUNÇÃO

## 14. Fase 9 — Remover a junção destrutiva atual

### 14.1 Problema atual

FVGs consecutivos da mesma direção são unidos por envelope de máximo e mínimo. Isso pode criar uma área contínua que inclui preços negociados normalmente e também pode expandir retroativamente uma zona com informação futura.

### 14.2 Objetivo

Preservar FVGs canônicos individuais e mover o agrupamento para uma camada separada.

### 14.3 Regra principal

O detector base nunca deve apagar FVGs-filhos nem sobrescrever seus limites.

### 14.4 Tipos de relação entre FVGs

- `OVERLAPPING`: zonas se sobrepõem em preço.
- `TOUCHING`: distância menor ou igual à tolerância em ticks.
- `NESTED`: uma zona está contida em outra.
- `TEMPORALLY_CONSECUTIVE_ONLY`: consecutivas no tempo, separadas em preço.
- `SAME_IMPULSE_CLUSTER`: pertencem à mesma perna.
- `SEPARATE`: sem relação suficiente.

### 14.5 Quando um grupo visual pode existir

Somente quando:

- mesma direção;
- mesma perna ou contexto causal;
- zonas sobrepostas ou tocando dentro da tolerância;
- nenhuma faixa intermediária sem desequilíbrio for incorporada indevidamente;
- todos os componentes permanecerem registrados.

### 14.6 Zona de grupo

Se houver grupo:

- `group_id` separado;
- `component_fvg_ids`;
- `group_confirmed_index = max(component.confirmed_index)`;
- `group_available_index = max(component.available_index)`;
- ciclo de vida do grupo iniciado somente após `group_available_index`;
- limites podem ser envelope apenas se a união geométrica for contínua;
- caso contrário, usar cluster multilobado/coleção, não um retângulo único.

### 14.7 Compatibilidade

O parâmetro `join_consecutive=True` deve:

- ser deprecado;
- ou mapear para um modo de agrupamento seguro;
- nunca mais destruir componentes.

### 14.8 Testes obrigatórios

- FVGs 100–105 e 110–115 não viram 100–115.
- FVGs 100–105 e 104–110 podem formar união contínua.
- Grupo só fica disponível após o último componente.
- Candle entre os componentes não mitiga uma zona futura.
- Desagrupar reproduz exatamente os FVGs-filhos.

### 14.9 Critérios de aceite

- Zero envelope sintético sobre faixa negociada.
- Zero expansão retroativa.
- Componentes preservados.
- Agrupamento reversível e auditável.

---

# PARTE VI — CICLO DE VIDA, PREENCHIMENTO E INVERSÃO

## 15. Fase 10 — Criar máquina de estados do FVG

### 15.1 Estados mínimos

Usar enums oficiais em português ou inglês conforme o padrão do projeto, mas manter semântica equivalente:

- `CANDIDATE`;
- `CONFIRMED`;
- `FRESH`;
- `TOUCHED`;
- `PARTIALLY_FILLED`;
- `CE_REACHED`;
- `FULLY_FILLED`;
- `INVALIDATED`;
- `INVERTED`;
- `EXPIRED`;
- `DATA_AMBIGUOUS`.

### 15.2 Eventos mínimos

- confirmação do padrão;
- primeiro contato proximal;
- entrada parcial;
- alcance de 25%, 50%, 75%;
- preenchimento distal;
- fechamento além do limite de invalidação;
- gap-through;
- reteste após inversão;
- expiração por idade ou sessão.

### 15.3 Semântica do CE

O midpoint deve ser denominado explicitamente `ce_price` ou Consequent Encroachment.

Tocar CE deve:

- preencher `ce_reached_index`;
- atualizar `max_penetration_percentage`;
- não apagar automaticamente o FVG;
- não ser sinônimo de `FULLY_FILLED`.

### 15.4 Campos mínimos

- `status`;
- `first_touch_index`;
- `first_touch_at`;
- `ce_reached_index`;
- `ce_reached_at`;
- `full_fill_index`;
- `full_fill_at`;
- `invalidated_index`;
- `invalidated_at`;
- `inverted_index`;
- `inverted_at`;
- `touch_count`;
- `fill_percentage`;
- `max_penetration_percentage`;
- `last_interaction_index`;
- `status_reason`.

### 15.5 Critérios de aceite

- Tocar CE não encerra automaticamente a zona.
- Preenchimento total possui campo próprio.
- Estados são monotônicos, salvo transição explícita para inversão.
- Histórico de transições auditável.

---

## 16. Fase 11 — Implementar geometria correta de interação

### 16.1 Objetivo

Distinguir toque real, preenchimento parcial, preenchimento total e gap-through.

### 16.2 Interseção de intervalos

Antes de qualquer estado, exigir interseção:

- `candle_high >= zone_bottom`;
- `candle_low <= zone_top`.

Se não houver interseção, não registrar toque.

### 16.3 Direção e profundidade

Para bullish FVG, preço normalmente retorna de cima para baixo:

- limite proximal: `top`;
- CE: midpoint;
- limite distal: `bottom`.

Para bearish FVG, preço normalmente retorna de baixo para cima:

- limite proximal: `bottom`;
- CE: midpoint;
- limite distal: `top`.

Calcular profundidade normalizada de 0% a 100%, respeitando a direção.

### 16.4 Gap-through

Quando um candle posterior aparece totalmente além da zona sem interseção e o candle anterior estava do outro lado:

- não assumir negociação dentro da zona;
- registrar `GAP_THROUGH` ou `DATA_AMBIGUOUS`;
- manter evidência de que OHLC não comprova preenchimento;
- permitir política configurável somente para análises específicas.

### 16.5 Wick versus body

Manter métricas separadas:

- `wick_touch`;
- `body_touch`;
- `close_inside`;
- `close_beyond_ce`;
- `close_beyond_distal`.

Não reduzir toda a lógica a um único parâmetro de mitigação.

### 16.6 Critérios de aceite

- Candle totalmente abaixo de bullish FVG não conta como toque sem interseção.
- Profundidade calculada corretamente em ambas as direções.
- Wick, body e close disponíveis separadamente.
- Gap-through auditável e não tratado como certeza.

---

## 17. Fase 12 — Definir invalidação, preenchimento e expiração

### 17.1 Objetivo

Formalizar conceitos que hoje estão misturados.

### 17.2 Preenchimento total

`FULLY_FILLED` ocorre quando o preço alcança o limite distal com interseção comprovada, conforme a regra de wick/body configurada para essa métrica.

### 17.3 Invalidação

A invalidação deve ser configurável e separada de full fill. Exemplos de regras possíveis:

- fechamento além do limite distal;
- deslocamento contrário além de tolerância em ticks;
- evento estrutural contrário confirmado;
- expiração por idade sem reação.

O perfil deve dizer qual regra é canônica para cada uso.

### 17.4 Expiração

Configurar:

- máximo de candles ativo;
- máximo de sessões;
- expiração no fim do pregão;
- expiração após N toques;
- política por timeframe.

### 17.5 Freshness

- `FRESH` enquanto não houver contato pós-disponibilidade;
- primeiro toque remove freshness;
- múltiplos testes devem reduzir qualidade, sem necessariamente apagar a zona;
- manter `touch_count` e profundidade por toque.

### 17.6 Critérios de aceite

- Full fill, invalidação e expiração são eventos distintos.
- Política configurável e persistida no snapshot.
- Nenhum estado inferido com dados anteriores à disponibilidade.

---

## 18. Fase 13 — Implementar IFVG como derivação segura

### 18.1 Pré-condição

Executar somente depois que as Fases 10–12 estiverem estáveis.

### 18.2 Conceito

Um IFVG não deve nascer simplesmente porque o preço tocou ou preencheu a zona. Deve existir falha/inversão comprovada segundo regra definida.

### 18.3 Regras mínimas

- FVG original confirmado;
- preço atravessa e fecha além do limite de invalidação;
- direção de inversão definida;
- evento disponível sem lookahead;
- opcionalmente, reteste da zona invertida;
- novo `ifvg_id` ligado ao `source_fvg_id`.

### 18.4 Campos

- `source_fvg_id`;
- `inversion_direction`;
- `inversion_confirmed_index`;
- `inversion_available_index`;
- `retest_index`;
- `ifvg_status`.

### 18.5 Critérios de aceite

- Nenhum IFVG sem FVG pai.
- Inversão não é criada pelo simples CE.
- Sem lookahead na confirmação e reteste.

---

## 19. Fase 14 — Implementar BPR como estrutura derivada

### 19.1 Pré-condição

FVGs individuais e IFVGs devem estar estabilizados.

### 19.2 Conceito

BPR deve derivar da sobreposição geométrica de desequilíbrios opostos compatíveis no tempo/contexto.

### 19.3 Regras

- FVG bullish e bearish confirmados;
- interseção real entre zonas;
- disponibilidade do BPR somente após ambos estarem confirmados;
- preservar IDs dos componentes;
- não criar BPR com componente futuro retroativo;
- contexto temporal máximo configurável.

### 19.4 Critérios de aceite

- BPR possui componentes rastreáveis.
- Zona é exatamente a interseção, não o envelope.
- Disponibilidade usa o maior `available_index` dos componentes.

---

# PARTE VII — POI, OB, LIQUIDEZ E CONTEXTO MTF

## 20. Fase 15 — Integrar FVG com POI e Order Block

### 20.1 Objetivo

Distinguir FVG isolado de desequilíbrio originado por um POI válido.

### 20.2 Associações

- `source_poi_id`;
- `source_ob_id`;
- `source_ob_role` (`EXTREME`, `DECISIONAL` ou enum oficial);
- distância entre POI e início da perna;
- FVG dentro ou fora da perna do OB;
- alinhamento direcional;
- disponibilidade temporal do OB.

### 20.3 Regras

- Não associar OB que só foi confirmado depois do FVG sem marcar a relação como retrospectiva.
- Para uso operacional, todas as evidências devem estar disponíveis no instante da decisão.
- Um FVG pode existir sem OB; nesse caso, manter `None` e não inventar vínculo.
- O score deve diferenciar vínculo confirmado, provável e ausente.

### 20.4 Modelo específico do livro

Criar classificador ou detector separado para `BOOK_ENTRY_FVG`, com:

- POI identificado;
- grande candle/impulso após o POI;
- primeiro pullback relevante;
- desequilíbrio delimitado conforme o modelo definido;
- CE calculado;
- rastreabilidade para os candles usados.

Não substituir o FVG clássico de três candles por esse modelo. Manter ambos explicitamente diferenciados.

### 20.5 Critérios de aceite

- Vínculos temporais seguros.
- FVG clássico e modelo do livro distinguíveis.
- Nenhum vínculo inventado na ausência de evidência.

---

## 21. Fase 16 — Integrar liquidez, sweep e inducement

### 21.1 Objetivo

Adicionar contexto SMC sem tornar liquidez condição geométrica obrigatória.

### 21.2 Eventos possíveis

- BSL sweep antes de movimento bearish;
- SSL sweep antes de movimento bullish;
- EQH/EQL;
- liquidez interna/externa;
- protected high/low;
- inducement;
- liquidity run;
- trap.

### 21.3 Regras direcionais

- bullish FVG recebe alinhamento positivo quando surge após sweep de SSL e impulso bullish válido;
- bearish FVG recebe alinhamento positivo quando surge após sweep de BSL e impulso bearish válido;
- liquidez oposta ou ausente não invalida automaticamente o gap geométrico;
- confluência só pode usar eventos disponíveis até `available_index`.

### 21.4 Campos

- `liquidity_event_id`;
- `liquidity_kind`;
- `liquidity_direction`;
- `liquidity_aligned`;
- `sweep_index`;
- `sweep_distance_atr`;
- `inducement_present`;
- `liquidity_evidence`.

### 21.5 Critérios de aceite

- Associação direcional correta.
- Ausência de liquidez não gera dado falso.
- Sem uso de sweep futuro.

---

## 22. Fase 17 — Integrar premium/discount e contexto MTF

### 22.1 Objetivo

Contextualizar a posição do FVG no dealing range e na estrutura superior.

### 22.2 Campos

- `dealing_range_id`;
- `range_high`;
- `range_low`;
- `equilibrium`;
- `premium_discount_position`;
- `htf_bias`;
- `htf_structure_id`;
- `parent_fvg_id`;
- `child_fvg_ids`;
- `mtf_alignment`.

### 22.3 Regras

- bullish FVG em discount pode receber confluência positiva;
- bearish FVG em premium pode receber confluência positiva;
- isso não substitui validade geométrica ou deslocamento;
- contexto HTF deve estar disponível no instante analisado;
- FVG filho deve permanecer independente e apenas referenciar o pai.

### 22.4 Critérios de aceite

- Contexto MTF sem lookahead.
- Pais e filhos preservados.
- Premium/discount calculado em range canônico.

---

# PARTE VIII — QUALIDADE E CLASSIFICAÇÃO

## 23. Fase 18 — Separar validade, confluência e score estatístico

### 23.1 Problema

Tamanho do gap e ATR ajudam a reduzir ruído, mas não comprovam sozinhos um FVG SMC de qualidade.

### 23.2 Modelo em três camadas

#### Camada A — Validade geométrica/técnica

Obrigatória para existência:

- OHLC válido;
- trio contínuo;
- gap geométrico real;
- tamanho mínimo em ticks;
- confirmação no candle 3;
- limites válidos;
- anti-lookahead aprovado.

Saída:

- `VALID`;
- `INVALID`;
- `AMBIGUOUS_DATA`.

#### Camada B — Validade SMC/contextual

Avaliar:

- deslocamento;
- direção do candle central;
- eficiência da perna;
- BOS/ChoCH associado;
- POI/OB;
- sweep/liquidez;
- posição premium/discount;
- contexto MTF;
- freshness.

Saída:

- flags e razões independentes;
- não colapsar tudo em um único booleano opaco.

#### Camada C — Calibração estatística

Avaliar por ativo/timeframe:

- tamanho em ATR/ticks;
- sessão;
- idade até toque;
- profundidade média;
- reação após CE;
- classe do FVG;
- alinhamento estrutural;
- número de testes;
- desempenho out-of-sample.

### 23.3 Saídas mínimas

- `validity_status`;
- `validity_reasons`;
- `confluence_score`;
- `confluence_reasons`;
- `statistical_score`;
- `statistical_profile_id`;
- `quality_label`;
- `operational_eligibility`;
- `blocked_reasons`.

### 23.4 Regras

- Score estatístico não corrige invalidade temporal.
- Sessão não transforma gap inválido em FVG válido.
- Nenhum peso calibrado pode ser aplicado silenciosamente a outro ativo/timeframe.
- Quando não existe perfil, usar `UNCALIBRATED`, não default oculto de WINFUT M5.

### 23.5 Critérios de aceite

- Camadas separadas no modelo e no dashboard.
- Razões auditáveis.
- Perfil de calibração explícito.
- Nenhum score promove trade automaticamente.

---

## 24. Fase 19 — Definir classificação final de tipos

### 24.1 Tipos recomendados

- `GEOMETRIC`;
- `DIRECTIONAL`;
- `DISPLACEMENT`;
- `STRUCTURE_ALIGNED`;
- `POI_ORIGINATED`;
- `BOOK_ENTRY`;
- `INVERSION`;
- `BPR_COMPONENT`.

### 24.2 Regras de precedência

- Tipos básicos não devem ser perdidos ao aplicar classificação mais específica.
- Preferir conjunto de tags ou campos separados a um único enum que apaga informação.
- IFVG e BPR são derivados, não substitutos silenciosos do FVG original.

### 24.3 Critérios de aceite

- Um FVG pode ser geométrico + displacement + structure-aligned.
- Classificação reproduzível.
- Nenhuma ambiguidade entre tipo e estado do ciclo de vida.

---

# PARTE IX — API, LEGADO E VISUALIZAÇÃO

## 25. Fase 20 — Corrigir funções legadas silenciosas

### 25.1 Problema

Métodos legados retornam listas vazias ou status fixo, podendo esconder falhas de integração.

### 25.2 Tarefas

Para cada método legado:

- localizar consumidores;
- decidir entre delegar, deprecar com erro explícito ou remover;
- adicionar warning estruturado durante período de transição;
- impedir retorno silencioso incorreto.

### 25.3 Regras

- `detect_fvg` deve delegar ao detector oficial ou lançar exceção clara.
- `detect_fvg_with_params` deve mapear parâmetros suportados ou falhar explicitamente.
- `calculate_mitigation` deve usar a máquina de estados ou ser removido.
- Nenhum método deprecado pode parecer funcional retornando valor neutro fixo.

### 25.4 Critérios de aceite

- Zero retorno silencioso falso.
- Consumidores migrados ou bloqueados explicitamente.
- Logs de depreciação testados.

---

## 26. Fase 21 — Atualizar contratos de DataFrame, API e persistência

### 26.1 DataFrame de compatibilidade

Pode manter:

- `FVG`;
- `Top`;
- `Bottom`;
- `MitigatedIndex` deprecado.

Adicionar, no mínimo:

- `PatternStartIndex`;
- `DisplacementIndex`;
- `PatternEndIndex`;
- `ConfirmedIndex`;
- `AvailableIndex`;
- `EarliestExecutionIndex`;
- `FirstTouchIndex`;
- `CEReachedIndex`;
- `FullFillIndex`;
- `InvalidatedIndex`;
- `Status`;
- `FillPercentage`;
- `FvgClass`;
- `IsDisplacement`;
- `StructureEventId`;
- `ImpulseLegId`;
- `IsCrossSession`;
- `IsLookaheadSafe`.

### 26.2 Persistência shadow

- criar versão de schema;
- salvar config snapshot;
- salvar IDs determinísticos;
- não reescrever produção;
- permitir comparação V2 versus V3.

### 26.3 Compatibilidade

- adaptador `FvgEventV3 -> FvgV2`;
- feature flag para engine nova;
- logs de campos perdidos na adaptação;
- versionamento explícito na API.

### 26.4 Critérios de aceite

- Consumidores antigos continuam funcionando durante migração.
- Novos campos disponíveis para scanner e dashboard shadow.
- Schema versionado e reversível.

---

## 27. Fase 22 — Corrigir visual overlays

### 27.1 Objetivo

Mostrar o FVG sem antecipação temporal e com ciclo de vida compreensível.

### 27.2 Campos mínimos na zona visual

- `zone_id`;
- `fvg_id`;
- `direction`;
- `price_top`;
- `price_bottom`;
- `ce_price`;
- `pattern_start_index`;
- `confirmed_from`;
- `tradable_from`;
- `display_to`;
- `status`;
- `fill_percentage`;
- `touch_count`;
- `fvg_class`;
- `is_displacement`;
- `structure_event_id`;
- `quality_label`;
- `is_lookahead_safe`;
- `group_id`, quando houver.

### 27.3 Regras de plotagem

- No modo operacional, retângulo inicia em `confirmed_index` ou `available_index` conforme semântica definida.
- O padrão completo pode ser indicado por marcadores separados nos três candles.
- CE deve ser linha própria, não sinônimo de fim da zona.
- Cores/opacidade devem refletir classe e estado, não apenas direção.
- FVG preenchido, invalidado e invertido devem ser visualmente distintos.
- Grupos devem preservar componentes e não mascarar faixas vazias.
- Fallback de timestamp nunca pode voltar silenciosamente ao candle de origem e antecipar a zona.

### 27.4 Critérios de aceite

- Nenhuma zona operacional aparece antes da confirmação.
- Estado visual corresponde ao estado persistido.
- Componentes de grupo podem ser inspecionados.
- Testes de snapshot visual ou payload aprovados.

---

# PARTE X — TESTES

## 28. Estratégia geral de testes

### 28.1 Testes unitários do detector geométrico

Cobrir:

- bullish clássico;
- bearish clássico;
- sem gap;
- igualdade exata;
- gap de um tick;
- gap abaixo do mínimo;
- limites normalizados;
- OHLC inválido;
- NaN/Inf;
- timestamps duplicados.

### 28.2 Testes temporais

- FVG inexistente antes do candle 3.
- Confirmação somente após candle 3 fechado.
- Execução somente no primeiro índice permitido.
- Batch versus incremental.
- Truncamento em cada candle.
- Nenhum atributo inicial alterado por dados futuros.

### 28.3 Testes de continuidade

- candles contíguos;
- candle faltante;
- cross-session;
- fim de semana;
- rollover;
- mudança de contrato;
- timestamp fora da tolerância.

### 28.4 Testes de displacement

- candle central forte e alinhado;
- candle central fraco;
- candle contrário;
- doji;
- perna eficiente;
- perna com alta sobreposição;
- FVG na perna do BOS;
- FVG anterior ao BOS dentro da perna;
- FVG posterior tardio;
- direção oposta ao evento.

### 28.5 Testes de agrupamento

- sobreposição;
- toque por tolerância;
- zonas separadas em preço;
- zonas aninhadas;
- disponibilidade do grupo;
- preservação dos filhos;
- nenhuma mitigação retroativa.

### 28.6 Testes de ciclo de vida

- fresh;
- primeiro toque;
- 25%;
- CE;
- 75%;
- full fill;
- invalidação por fechamento;
- expiração;
- múltiplos toques;
- gap-through;
- IFVG;
- BPR.

### 28.7 Testes de integração

- FVG + impulso;
- FVG + BOS/ChoCH;
- FVG + OB/POI;
- FVG + liquidez;
- FVG + MTF;
- serialização;
- persistência shadow;
- dashboard;
- scanner;
- backtest.

### 28.8 Testes de propriedade

Usar geração de OHLC válida para assegurar:

- `top > bottom` em todo FVG válido;
- `size_ticks >= min_gap_ticks`;
- índices temporalmente ordenados;
- status não usa índice anterior à disponibilidade;
- fill entre 0% e 100%;
- IDs determinísticos;
- grupos não perdem componentes;
- BPR é interseção, não envelope.

### 28.9 Testes de regressão

- fixtures congeladas na Fase 0;
- comparação V2 versus V3;
- diferença explicada por categoria;
- nenhum desvio silencioso.

### 28.10 Performance

Comparar:

- implementação atual batch;
- nova implementação incremental;
- janelas pequenas e grandes;
- 11 ativos × 6 timeframes;
- uso de memória;
- tempo médio por candle fechado;
- número de FVGs ativos simultâneos.

Meta:

- evitar busca completa do futuro para cada FVG;
- preferir processamento streaming com coleção de zonas ativas;
- complexidade prática próxima de `O(n × k_ativo)`, com `k_ativo` controlado.

---

# PARTE XI — BACKTEST E RECALIBRAÇÃO

## 29. Fase 23 — Redefinir métricas operacionais

### 29.1 Objetivo

Evitar que simples toque ou CE seja interpretado como respeito lucrativo.

### 29.2 Métricas por FVG

- taxa de primeiro toque;
- tempo até primeiro toque;
- profundidade máxima;
- taxa de CE;
- taxa de full fill;
- taxa de invalidação;
- taxa de inversão;
- reação após proximal;
- reação após CE;
- máxima excursão favorável após toque;
- máxima excursão adversa;
- retorno em pontos, ticks, ATR e R;
- reação antes de invalidação;
- desempenho por classe;
- desempenho por sessão;
- desempenho por faixa de tamanho;
- desempenho por contexto estrutural.

### 29.3 Definição de respeito

Criar definição parametrizada, por exemplo:

1. zona disponível;
2. preço toca a zona com interseção real;
3. antes da invalidação, reage ao menos:
   - X ticks;
   - ou Y ATR;
   - ou Z múltiplos da profundidade/risco;
4. registrar se a reação ocorreu a partir de proximal, CE ou distal.

Não usar `MitigatedIndex != None` como sinônimo de respeito.

### 29.4 Critérios de aceite

- Métrica de respeito documentada.
- Touch, CE, fill e respeito separados.
- Resultados reproduzíveis.

---

## 30. Fase 24 — Reexecutar backtests e calibração

### 30.1 Perfis a comparar

- engine atual V2;
- FVG geométrico V3;
- FVG direcional;
- displacement FVG;
- structure-aligned FVG;
- POI-originated;
- book-entry FVG;
- com e sem filtros de sessão;
- com diferentes thresholds de tick/ATR;
- individual versus cluster seguro.

### 30.2 Segmentação mínima

- ativo;
- timeframe;
- direção;
- sessão;
- classe;
- tamanho em ATR;
- posição premium/discount;
- BOS versus ChoCH;
- com/sem sweep;
- com/sem OB;
- primeiro, segundo e terceiro toque;
- fresh versus testado.

### 30.3 Regras estatísticas

- separar treino, validação e teste temporal;
- evitar otimização no mesmo período usado para medir resultado;
- reportar tamanho da amostra;
- intervalos de confiança ou bootstrap, quando aplicável;
- não promover threshold com amostra insuficiente;
- manter Candidate B/C frozen quando exigido pela arquitetura oficial;
- não declarar otimização definitiva antes do gate de dados.

### 30.4 Saídas

- `RELATORIO_BACKTEST_FVG_ENGINE_V3.md`;
- tabela V2 versus V3;
- matriz por classe;
- thresholds recomendados por perfil;
- limitações;
- decisão `GO_SHADOW`, `HOLD` ou `NO_GO`.

### 30.5 Critérios de aceite

- Nenhum backtest usa zona antes de `available_index`.
- Resultados out-of-sample separados.
- Toda diferença relevante explicada.
- Nenhuma promoção automática para produção.

---

# PARTE XII — CONFIGURAÇÃO

## 31. Criar `FVGDetectionConfig` ou equivalente

### 31.1 Estrutura e tempo

- `schema_version`;
- `confirmation_semantics`;
- `execution_semantics`;
- `require_closed_candles`;
- `lookahead_guard_enabled`.

### 31.2 Continuidade

- `require_contiguous_bars`;
- `expected_bar_interval`;
- `max_timestamp_tolerance`;
- `allow_cross_session_fvg`;
- `allow_rollover_fvg`;
- `allow_contract_change_fvg`;
- `session_calendar_id`.

### 31.3 Geometria

- `price_tick` ou referência ao cadastro;
- `min_gap_ticks`;
- `min_gap_atr`;
- `strict_inequality`;
- `tick_rounding_mode`.

### 31.4 ATR

- `atr_period`;
- `atr_method`;
- `require_full_atr_warmup`.

### 31.5 Deslocamento

- `min_middle_body_atr`;
- `min_middle_range_atr`;
- `min_body_to_range_ratio`;
- `min_close_location_value`;
- `min_impulse_efficiency`;
- `max_overlap_ratio`;
- `require_direction_alignment_for_validated`;
- `require_displacement_for_operational`.

### 31.6 Associação estrutural

- `structure_link_mode`;
- `allow_pre_break_fvg`;
- `allow_break_candle_fvg`;
- `max_post_break_continuation_candles`;
- `require_direction_match`.

### 31.7 Agrupamento

- `grouping_enabled`;
- `group_same_impulse_only`;
- `group_overlap_required`;
- `group_touch_tolerance_ticks`;
- `preserve_components` obrigatório `True`.

### 31.8 Ciclo de vida

- `touch_mode`;
- `ce_mode`;
- `full_fill_mode`;
- `invalidation_mode`;
- `invalidation_tolerance_ticks`;
- `max_active_bars`;
- `max_touch_count`;
- `expire_at_session_end`;
- `gap_through_policy`.

### 31.9 Contexto

- `enable_structure_context`;
- `enable_poi_context`;
- `enable_liquidity_context`;
- `enable_premium_discount`;
- `enable_mtf_context`.

### 31.10 Guardrails

- `shadow_only=True`;
- `can_promote_trade=False`;
- `apply_automatically=False`;
- `requires_backtest_validation=True`;
- `fail_closed_on_missing_config=True` para perfis críticos.

### 31.11 Critérios de aceite

- Nenhum default oculto de WINFUT M5 aplicado a outro perfil.
- Snapshot da config persistido em cada execução.
- Configuração validada antes do processamento.

---

# PARTE XIII — MIGRAÇÃO E COMPATIBILIDADE

## 32. Estratégia de migração

### 32.1 Versão de schema

- manter V2 congelada;
- introduzir `FVG_ENGINE_V3_SHADOW`;
- versionar payload, banco shadow e dashboard;
- não alterar dados históricos em produção.

### 32.2 Execução paralela

Durante o período de validação:

- rodar V2 e V3 sobre os mesmos candles;
- salvar resultados separados;
- comparar detecção, disponibilidade, classe e estado;
- registrar divergências por motivo;
- não permitir que V3 promova trade.

### 32.3 Compatibilidade

- adaptadores explícitos;
- feature flags;
- consumidores migrados gradualmente;
- warnings para campos deprecados;
- documentação de diferença semântica de `MitigatedIndex`.

### 32.4 Rollback

- manter caminho de retorno à V2 shadow;
- não remover código antigo até aprovação dos marcos;
- rollback não pode apagar resultados de auditoria V3;
- registrar versão ativa em cada execução.

### 32.5 Critérios de aceite

- V2 e V3 podem coexistir.
- Rollback testado.
- Nenhuma escrita indevida em produção.

---

# PARTE XIV — ORGANIZAÇÃO DE IMPLEMENTAÇÃO

## 33. Sequência recomendada de commits

1. `audit(fvg): baseline e mapa de dependencias`
2. `test(fvg): fixtures geometricas e temporais`
3. `model(fvg): contrato FvgEventV3`
4. `fix(fvg): indices de confirmacao e disponibilidade`
5. `refactor(fvg): detector geometrico puro`
6. `feat(fvg): continuidade sessao e rollover`
7. `feat(fvg): normalizacao tick e atr canonico`
8. `feat(fvg): gate direcional e displacement`
9. `feat(fvg): impulse leg canonica`
10. `fix(fvg): associacao causal com bos choch`
11. `refactor(fvg): agrupamento nao destrutivo`
12. `feat(fvg): maquina de estados`
13. `fix(fvg): geometria de toque e gap-through`
14. `feat(fvg): invalidacao expiracao freshness`
15. `feat(fvg): integracao poi ob liquidez`
16. `feat(fvg): contexto mtf premium discount`
17. `feat(fvg): score em camadas`
18. `fix(fvg): APIs legadas sem retorno silencioso`
19. `feat(fvg): payload visual e persistencia shadow`
20. `feat(fvg): ifvg derivado`
21. `feat(fvg): bpr derivado`
22. `perf(fvg): processamento incremental de zonas ativas`
23. `test(fvg): regressao integracao propriedade performance`
24. `docs(fvg): relatorio final e resultados de backtest`

Cada commit deve:

- compilar;
- manter testes verdes;
- conter escopo único;
- não misturar refatoração massiva com alteração de regra;
- atualizar documentação e changelog quando necessário.

---

## 34. Mapa lógico de módulos sugerido

A IA deve adaptar os nomes à arquitetura real, sem criar estrutura paralela desnecessária.

- `fvg_geometry.py`: detecção pura de três candles.
- `fvg_validation.py`: continuidade, tick, ATR e validação direcional.
- `fvg_displacement.py`: métricas de candle/perna e classificação.
- `impulse_leg.py`: evento canônico da perna.
- `fvg_structure_linker.py`: vínculo causal com BOS/ChoCH.
- `fvg_grouping.py`: grupos não destrutivos.
- `fvg_lifecycle.py`: estados e interações.
- `fvg_inversion.py`: IFVG.
- `bpr.py`: Balanced Price Range.
- `fvg_context.py`: POI, OB, liquidez, premium/discount e MTF.
- `fvg_scoring.py`: validade, confluência e estatística.
- `fvg_records.py`: modelos e adaptadores.
- `fvg_visual.py`: payload visual.
- `fvg_config.py`: configuração e validação.
- `tests/fvg/`: suíte dedicada.

Antes de criar novos arquivos, verificar se a arquitetura oficial já possui serviços equivalentes.

---

# PARTE XV — CRITÉRIOS DE ACEITE POR MARCO

## 35. Marco A — Segurança temporal

Aprovado quando:

- confirmação ocorre no candle 3;
- disponibilidade está correta;
- backtest não usa o candle central como confirmação;
- batch e incremental coincidem;
- plotagem não antecipa zona.

## 36. Marco B — Detecção válida

Aprovado quando:

- detector geométrico está isolado;
- continuidade, sessão e rollover são verificados;
- tick e ATR canônico são aplicados;
- gap geométrico e FVG validado estão separados.

## 37. Marco C — Displacement e estrutura

Aprovado quando:

- perna impulsiva canônica existe;
- FVG é associado por causalidade;
- direção do BOS/ChoCH é respeitada;
- proximidade temporal isolada não classifica displacement.

## 38. Marco D — Agrupamento seguro

Aprovado quando:

- componentes são preservados;
- zonas separadas não viram envelope contínuo;
- grupo só existe após último componente;
- zero lookahead de junção.

## 39. Marco E — Ciclo de vida correto

Aprovado quando:

- touch, CE, full fill, invalidation e expiration são distintos;
- interseção geométrica é exigida;
- gap-through é ambíguo, não preenchimento certo;
- múltiplos toques e freshness são rastreados.

## 40. Marco F — Evidências SMC

Aprovado quando:

- POI/OB, liquidez, estrutura e MTF são ligados por IDs;
- relações respeitam disponibilidade temporal;
- modelo clássico e modelo do livro estão diferenciados.

## 41. Marco G — Qualidade confiável

Aprovado quando:

- validade, confluência e estatística estão separadas;
- perfis são explícitos por ativo/timeframe;
- score não corrige invalidade;
- razões estão disponíveis no dashboard.

## 42. Marco H — Backtest validado

Aprovado quando:

- métricas de respeito foram redefinidas;
- V2 versus V3 comparados;
- out-of-sample separado;
- limitações documentadas;
- decisão permanece shadow até gate formal.

---

# PARTE XVI — DEFINITION OF DONE

## 43. Condições finais obrigatórias

A correção somente pode ser considerada concluída quando:

1. O FVG não existe operacionalmente antes do candle 3 fechado.
2. `confirmed_index`, `available_index` e `earliest_execution_index` têm semântica documentada e testada.
3. O detector geométrico está separado do classificador SMC.
4. Cross-session, missing bar e rollover não contaminam o FVG intraday.
5. Tamanho usa tick e ATR canônico com warmup.
6. `DISPLACEMENT_FVG` pertence à perna impulsiva correta.
7. BOS/ChoCH é associado por direção e causalidade.
8. A junção destrutiva foi removida.
9. Componentes de grupos são preservados.
10. CE não é sinônimo de mitigação total.
11. Touch, partial fill, CE, full fill, invalidation e expiration são estados distintos.
12. Interseção real é exigida para toque.
13. Gap-through não é tratado como negociação comprovada.
14. Funções legadas não retornam resultados falsos silenciosamente.
15. Visualização não antecipa a zona.
16. Persistência shadow é versionada.
17. V2 e V3 foram executadas em paralelo.
18. Testes unitários, temporais, integrados, de propriedade, regressão e performance estão verdes.
19. Backtests usam somente eventos disponíveis.
20. Relatório final contém arquivos, decisões, testes, métricas, limitações e próximos passos.
21. Guardrails continuam ativos:
    - `shadow_only=True`;
    - `can_promote_trade=False`;
    - `apply_automatically=False`;
    - `requires_backtest_validation=True`.

---

# PARTE XVII — INSTRUÇÃO EXECUTIVA PARA A IA DE CÓDIGO

## 44. Procedimento obrigatório

A IA executora deve seguir esta ordem:

1. Ler a arquitetura oficial e as convenções do repositório.
2. Não presumir caminhos; localizar arquivos reais.
3. Rodar e registrar baseline.
4. Criar fixtures antes das alterações funcionais.
5. Implementar por fases e commits pequenos.
6. Não reescrever o módulo inteiro sem necessidade comprovada.
7. Preservar compatibilidade por adaptadores e feature flags.
8. Fazer testes incrementais após cada fase.
9. Comparar batch versus streaming para anti-lookahead.
10. Não usar dados futuros em nenhuma classificação histórica.
11. Não alterar defaults de produção.
12. Não gravar em produção.
13. Não promover trade.
14. Não esconder falhas com fallback silencioso.
15. Quando houver ambiguidade conceitual, persistir `UNKNOWN`/`AMBIGUOUS` e evidência, em vez de inventar certeza.
16. Atualizar documentação, schema e changelog.
17. Encerrar somente com relatório final completo.

### 44.1 Regra de parada

Parar e reportar antes de continuar quando:

- a arquitetura oficial conflitar com este plano;
- o modelo de BOS/ChoCH não for lookahead-safe;
- não existir cadastro confiável de tick;
- timestamps não permitirem validar continuidade;
- testes de baseline já estiverem falhando de forma não relacionada;
- uma migração exigir escrita em produção;
- houver risco de quebrar contratos públicos sem adaptador.

A IA não deve improvisar silenciosamente nesses casos.

---

## 45. Relatório final exigido

Gerar `RELATORIO_FINAL_CORRECAO_FVG_ENGINE_V3.md` contendo:

1. Resumo executivo.
2. Status de cada fase.
3. Arquivos criados, alterados e removidos.
4. Modelos e campos adicionados.
5. Configurações e defaults.
6. Definição temporal final.
7. Definição de cada classe de FVG.
8. Máquina de estados final.
9. Regras de junção/agrupamento.
10. Regras de associação com estrutura.
11. Regras de continuidade e sessão.
12. Métodos legados migrados/deprecados.
13. Testes executados:
    - totais;
    - aprovados;
    - falhos;
    - ignorados;
    - duração.
14. Comparação V2 versus V3:
    - contagens;
    - diferenças;
    - causas.
15. Performance antes/depois.
16. Resultados de backtest por classe.
17. Evidências anti-lookahead.
18. Limitações conhecidas.
19. Pendências.
20. Decisão final:
    - `GO_SHADOW`;
    - `HOLD`;
    - `NO_GO`.
21. Confirmação expressa dos guardrails.
22. Hash do commit final e comandos exatos para reproduzir testes.

---

# 46. Prioridade resumida

## P0 — Obrigatório antes de qualquer confiança operacional

1. Corrigir confirmação e disponibilidade para o candle 3.
2. Eliminar lookahead do backtest e do overlay.
3. Bloquear cross-session, falhas de coleta e rollover.
4. Refazer displacement por perna e direção.
5. Remover junção destrutiva e retroativa.
6. Separar CE de full fill/invalidated.
7. Exigir interseção geométrica real.

## P1 — Obrigatório antes da recalibração

8. Criar contrato canônico V3.
9. Normalizar por tick e ATR com warmup.
10. Implementar máquina de estados.
11. Corrigir APIs legadas.
12. Integrar estrutura, POI/OB e liquidez.
13. Separar validade, confluência e score.

## P2 — Evolução após a base estável

14. Contexto MTF e premium/discount.
15. Modelo específico `BOOK_ENTRY_FVG`.
16. IFVG.
17. BPR.
18. Otimização incremental e calibração avançada.

---

## 47. Resultado funcional final

A engine final deve responder, para cada zona, de forma inequívoca:

- qual trio de candles criou o FVG;
- quando o padrão foi confirmado;
- quando ficou disponível;
- quando uma estratégia poderia executá-lo;
- se os candles eram contíguos e da mesma sessão/contrato;
- qual o tamanho em pontos, ticks e ATR;
- se é apenas geométrico ou possui deslocamento;
- a qual perna e evento estrutural pertence;
- se veio de POI/OB e após qual evento de liquidez;
- se está fresh, tocado, em CE, preenchido, invalidado, invertido ou expirado;
- qual foi a profundidade de cada interação;
- se pertence a um grupo, IFVG ou BPR;
- quais evidências sustentam sua validade e qualidade;
- qual configuração produziu o resultado;
- se todo o cálculo é lookahead-safe.

Somente depois de atender integralmente essas condições a nova engine poderá permanecer em shadow para validação prolongada e comparação estatística. Nenhuma promoção para operação real deve ocorrer dentro deste plano.

---
# SEÇÕES ESPECÍFICAS — FVG ENGINE V3

## Ownership do Domínio (Confirmado)

FVG é dona exclusiva de:
- `FvgEventV3`
- `FvgLifecycleEventV3`
- `FvgGroupProjectionV3`
- `FvgEngineStateV3`

**Regra:** FVG geométrico puro depende apenas de candles validados e continuity metadata. Classificação displacement/contextual depende de Structure, Swing e Liquidity. Associação com OB é enriquecimento posterior.

## Contratos Produzidos

| Contrato | Consumidor | Gate |
|---|---|---|
| `FvgEventV3` | Order Block, BPR | G7 |

## Contratos Consumidos

| Contrato | Produtor | Gate |
|---|---|---|
| `StructureEventV3` | Structure | G3 |
| `StructureLegV3` | Structure | G3 |
| `LiquidityEventV3` | Liquidity | G6 |
| `DealingRangeV3` | Retracement | G5 |

## Gate de Entrada

G6 (Liquidity Ready) para classificação contextual

## Gate de Saída

- **G7 — FVG Core Ready:** geometria, confirmação candle 3, continuity, lifecycle, contextual links causais
- **G7B — IFVG/BPR Ready:** IFVG/BPR derivados e sem dependência circular

## Regra Crítica

- Preservar geometria clássica de três candles
- Incorporar IFVG e BPR
- Reconhecer componente `bpr.py` existente
- Consumir Structure, Liquidity e DealingRange somente na classificação contextual
- Separar FVG geométrico, displacement e contexto

## Caminhos Batch

- `smc_engine_v3/fvg.py`
- `smc_engine_v3/bpr.py`

## Caminhos Incrementais

- `incremental/components/fvg.py`
- `incremental/components/bpr.py`
