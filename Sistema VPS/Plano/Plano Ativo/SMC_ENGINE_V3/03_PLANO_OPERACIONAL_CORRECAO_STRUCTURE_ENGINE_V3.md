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


# PLANO OPERACIONAL — CORREÇÃO DA ENGINE DE ESTRUTURA SMC V2

**Projeto:** SMC Trader System 7.0  
**Módulo:** Structure Detection — BOS / CHOCH / Protected & Weak Levels  
**Escopo:** correção arquitetural, temporal e semântica da engine de estrutura  
**Modo de execução:** incremental, auditável, anti-lookahead, shadow-only até aprovação  
**Documento de referência:** implementação atual de `calculate_bos_choch()` e `calculate_bos_choch_records()`  
**Objetivo:** substituir a lógica retrospectiva baseada em quatro swings por uma engine estrutural canônica, incremental e causal, sem quebrar a compatibilidade operacional do sistema.

---

# 1. OBJETIVO GERAL

Corrigir a engine de estrutura para que BOS, CHOCH, reversão, sweeps e níveis estruturais sejam detectados de forma:

- causal;
- incremental;
- sem lookahead;
- idempotente;
- explicável;
- compatível com swing structure e internal structure;
- integrada com OB, FVG, liquidez, Wyckoff, Elliott, sinais e contexto MTF;
- segura para replay, backtest, shadow e futura promoção.

A nova engine deve preservar a lógica upstream apenas como perfil de compatibilidade e comparação, nunca como verdade operacional principal.

---

# 2. PROBLEMA ATUAL

A implementação atual:

1. espera quatro swings;
2. classifica padrões retroativamente;
3. registra BOS/CHOCH no índice de um swing anterior;
4. busca a quebra no futuro;
5. pode depender de swings que ainda não estavam disponíveis no momento da quebra;
6. não mantém estado de tendência;
7. não diferencia estrutura swing e interna;
8. não mantém protected high/low e weak high/low;
9. trata wick break e close break como variações do mesmo evento;
10. não diferencia CHOCH inicial de reversão confirmada;
11. elimina eventos de forma destrutiva;
12. não preserva níveis ativos não rompidos;
13. não considera EQH/EQL, tolerância por tick ou força da quebra;
14. não representa gaps de sessão, rollover e falhas de continuidade;
15. não possui processamento incremental real.

O resultado atual é útil para paridade histórica, mas não é adequado como verdade estrutural operacional.

---

# 3. PRINCÍPIOS OBRIGATÓRIOS

## 3.1. Anti-lookahead absoluto

Nenhum swing, nível ou evento pode ser usado antes de seu `available_index`.

Toda entidade temporal deve possuir, quando aplicável:

- `origin_index`;
- `confirmed_index`;
- `available_index`;
- `earliest_execution_index`.

## 3.2. Separação entre origem e evento

O nível estrutural nasce em um swing, mas o BOS/CHOCH ocorre no candle da quebra.

A engine deve separar:

- origem do nível;
- confirmação do nível;
- ativação do nível;
- candle da quebra;
- confirmação do evento;
- disponibilidade do evento.

## 3.3. Máquina de estados

A classificação de BOS/CHOCH não deve depender apenas da ordenação dos últimos quatro swings.

Ela deve depender do estado estrutural vigente:

- `NEUTRO`;
- `ALTISTA`;
- `BAIXISTA`;
- `TRANSICAO_ALTISTA`;
- `TRANSICAO_BAIXISTA`.

## 3.4. Estrutura em camadas

A engine deve processar separadamente:

- `SWING`;
- `INTERNAL`.

Nenhum evento interno deve sobrescrever automaticamente a estrutura swing.

## 3.5. Eventos não destrutivos

Eventos históricos não devem ser apagados.

Eles devem poder ser classificados como:

- `ACTIVE`;
- `CONFIRMED`;
- `SUPERSEDED`;
- `NESTED`;
- `DUPLICATE`;
- `INVALIDATED`;
- `FAILED`;
- `EXPIRED`.

## 3.6. Shadow-only

Durante toda a implementação:

- não alterar decisões de trade em produção;
- não promover sinais;
- não escrever na verdade operacional sem feature flag;
- não alterar o pipeline oficial sem aprovação;
- manter `can_promote_trade=False`;
- manter `apply_automatically=False`;
- gerar comparativos entre engine antiga e nova.

---

# 4. ESCOPO FUNCIONAL

A nova engine deve contemplar:

- BOS de continuação;
- CHOCH;
- reversão confirmada;
- sweep por pavio;
- reclaim;
- failure swing;
- protected high;
- protected low;
- weak high;
- weak low;
- EQH;
- EQL;
- estrutura swing;
- estrutura interna;
- break por fechamento;
- break por wick;
- gap break;
- break marginal;
- displacement break;
- neutralização;
- transição;
- alinhamento MTF;
- contexto de sessão;
- contexto de rollover;
- vínculo com perna impulsiva;
- vínculo com OB/FVG/liquidez.

---

# 5. FORA DO ESCOPO DESTA FASE

Não implementar nesta fase:

- lógica de entrada;
- stop loss final;
- take profit final;
- promoção automática de sinais;
- otimização genética;
- substituição da engine oficial;
- recalibração definitiva por ativo;
- mudança do modelo de risco;
- alteração de banco de produção sem migração aprovada.

---

# 6. ARQUITETURA-ALVO

A solução deve ser dividida em módulos independentes.

## 6.1. `SwingAvailabilityAdapter`

Responsável por normalizar swings recebidos do detector atual.

Deve garantir:

- `swing_id`;
- `origin_index`;
- `confirmed_index`;
- `available_index`;
- `price`;
- `high_low`;
- `timeframe`;
- `scope`;
- `method`;
- `is_valid`;
- `source_version`.

## 6.2. `StructureLevelRegistry`

Responsável por manter níveis estruturais ativos.

Tipos mínimos:

- `PROTECTED_HIGH`;
- `PROTECTED_LOW`;
- `WEAK_HIGH`;
- `WEAK_LOW`;
- `EQH`;
- `EQL`;
- `UNCLASSIFIED_HIGH`;
- `UNCLASSIFIED_LOW`.

## 6.3. `StructureStateMachine`

Responsável por manter o estado atual por:

- ativo;
- timeframe;
- scope;
- sessão ou stream.

## 6.4. `BreakDetector`

Responsável por detectar:

- close break;
- wick sweep;
- gap break;
- reclaim;
- marginal break;
- displacement break.

## 6.5. `StructureEventClassifier`

Responsável por transformar o rompimento em:

- BOS;
- CHOCH;
- REVERSAL_CONFIRMED;
- SWEEP;
- RECLAIM;
- FAILED_CHOCH;
- RANGE_BREAK;
- UNCLASSIFIED_BREAK.

## 6.6. `StructureEventStore`

Responsável por persistência, idempotência e versionamento.

## 6.7. `StructureOverlayAdapter`

Responsável por visualização sem antecipação temporal.

## 6.8. `LegacyParityAdapter`

Responsável por preservar o algoritmo antigo apenas para:

- comparação;
- testes de paridade;
- auditoria;
- replay histórico;
- diagnóstico de divergência.

---

# 7. CONTRATOS DE DADOS

## 7.1. Modelo `SwingPointV3`

Campos mínimos:

```text
swing_id
asset
timeframe
scope
origin_index
confirmed_index
available_index
origin_at
confirmed_at
available_at
high_low
price
method
strength
left_bars
right_bars
is_valid
source_version
```

Regras:

- `high_low` deve ser `1` para high e `-1` para low;
- `available_index >= confirmed_index >= origin_index`;
- o preço deve ser compatível com high/low do candle de origem;
- o swing não pode entrar no estado estrutural antes de `available_index`.

## 7.2. Modelo `StructureLevelV3`

Campos mínimos:

```text
level_id
asset
timeframe
scope
level_type
direction
price
origin_swing_id
origin_index
origin_at
confirmed_index
available_index
status
activated_at
broken_index
broken_at
swept_index
swept_at
invalidated_index
invalidated_at
parent_structure_id
created_by_event_id
metadata
```

## 7.3. Modelo `StructureStateV3`

Campos mínimos:

```text
state_id
asset
timeframe
scope
trend_state
previous_trend_state
protected_high_id
protected_low_id
weak_high_id
weak_low_id
last_bos_id
last_choch_id
transition_started_at
updated_index
updated_at
version
```

## 7.4. Modelo `StructureEventV3`

Campos mínimos:

```text
event_id
asset
timeframe
scope
event_type
direction
status

broken_level_id
broken_swing_id
broken_level_price
broken_level_origin_index
broken_level_available_index

break_index
break_at
confirmed_index
available_index
earliest_execution_index

break_method
break_context
previous_state
resulting_state

penetration_pts
penetration_ticks
penetration_atr
body_atr
range_atr
body_to_range_ratio
close_location_ratio
displacement_score
quality_label

impulse_leg_id
parent_structure_id
previous_event_id

is_gap_break
is_session_boundary
is_rollover_affected
is_data_gap_affected
is_replay_safe

raw
```

---

# 8. ENUMS OBRIGATÓRIOS

## 8.1. Trend state

```text
NEUTRO
ALTISTA
BAIXISTA
TRANSICAO_ALTISTA
TRANSICAO_BAIXISTA
```

## 8.2. Structure scope

```text
SWING
INTERNAL
```

## 8.3. Event type

```text
BOS
CHOCH
REVERSAL_CONFIRMED
SWEEP
RECLAIM
FAILED_CHOCH
RANGE_BREAK
UNCLASSIFIED_BREAK
```

## 8.4. Break method

```text
CLOSE_BREAK
WICK_SWEEP
GAP_BREAK
MARGINAL_BREAK
DISPLACEMENT_BREAK
```

## 8.5. Level type

```text
PROTECTED_HIGH
PROTECTED_LOW
WEAK_HIGH
WEAK_LOW
EQH
EQL
UNCLASSIFIED_HIGH
UNCLASSIFIED_LOW
```

## 8.6. Event status

```text
CANDIDATE
CONFIRMED
SUPERSEDED
NESTED
DUPLICATE
INVALIDATED
FAILED
EXPIRED
```

---

# 9. FASE 0 — AUDITORIA E CONGELAMENTO

## Objetivo

Mapear todas as dependências da engine atual antes de alterar qualquer contrato.

## Tarefas

1. localizar todos os usos de:
   - `calculate_bos_choch`;
   - `calculate_bos_choch_records`;
   - `BosChochV2`;
   - `BrokenIndex`;
   - `event_time`;
   - `broken_at`;
   - `line_start_time`;
   - `line_end_time`.

2. localizar consumidores em:
   - OB;
   - FVG;
   - liquidez;
   - directional bias;
   - contextual;
   - opportunity scanner;
   - backtest;
   - dashboard;
   - persistência;
   - APIs;
   - testes;
   - scripts de replay.

3. gerar documento:
   - `AUDITORIA_DEPENDENCIAS_STRUCTURE_ENGINE_V2.md`.

4. congelar baseline:
   - quantidade de BOS;
   - quantidade de CHOCH;
   - distribuição por direção;
   - ativos;
   - timeframes;
   - datas;
   - fixtures;
   - hashes dos resultados.

5. criar feature flags:

```text
STRUCTURE_ENGINE_MODE=legacy|shadow_v3|v3
STRUCTURE_V3_WRITE_ENABLED=false
STRUCTURE_V3_SIGNAL_ENABLED=false
STRUCTURE_V3_OVERLAY_ENABLED=false
```

## Critérios de aceite

- nenhuma alteração funcional em produção;
- todos os consumidores mapeados;
- baseline reproduzível;
- fixtures salvas;
- feature flags testadas.

---

# 10. FASE 1 — NORMALIZAÇÃO DOS SWINGS

## Objetivo

Garantir que nenhum swing seja usado antes de sua disponibilidade.

## Tarefas

1. criar `SwingAvailabilityAdapter`;
2. aceitar entrada antiga:
   - `HighLow`;
   - `Level`;
3. enriquecer com:
   - `origin_index`;
   - `confirmed_index`;
   - `available_index`;
4. quando o detector de swings não fornecer confirmação:
   - inferir apenas se o método for explicitamente conhecido;
   - caso contrário, marcar como `availability_unknown`;
5. impedir processamento operacional de swings com disponibilidade desconhecida;
6. validar alinhamento entre DataFrames;
7. validar timestamps;
8. validar high/low do candle de origem;
9. criar testes de pivôs confirmados tardiamente.

## Critérios de aceite

- nenhum swing entra na engine antes de `available_index`;
- todos os swings possuem ID estável;
- inconsistências geram erro explícito;
- nenhum fallback silencioso.

---

# 11. FASE 2 — REGISTRO DE NÍVEIS ATIVOS

## Objetivo

Substituir o modelo de “quatro swings e busca futura” por níveis estruturais ativos.

## Tarefas

1. criar `StructureLevelRegistry`;
2. registrar cada swing disponível;
3. classificar inicialmente como:
   - `UNCLASSIFIED_HIGH`;
   - `UNCLASSIFIED_LOW`;
4. após confirmação estrutural, promover para:
   - protected;
   - weak;
   - EQH/EQL;
5. manter histórico de status;
6. nunca excluir níveis;
7. desativar nível apenas por evento explícito;
8. impedir duplicatas pelo mesmo swing;
9. suportar um registry separado por scope.

## Critérios de aceite

- níveis ativos preservados;
- níveis rompidos continuam auditáveis;
- nenhum BOS/CHOCH é necessário para manter o nível;
- histórico completo disponível.

---

# 12. FASE 3 — MÁQUINA DE ESTADOS

## Objetivo

Criar o estado estrutural canônico.

## Regras do estado ALTISTA

Manter:

```text
protected_low
weak_high
```

Eventos:

```text
close acima do weak_high
→ BOS ALTISTA

wick abaixo do protected_low e fechamento acima
→ SSL_SWEEP

close abaixo do protected_low
→ CHOCH BAIXISTA
→ TRANSICAO_BAIXISTA
```

## Regras do estado BAIXISTA

Manter:

```text
protected_high
weak_low
```

Eventos:

```text
close abaixo do weak_low
→ BOS BAIXISTA

wick acima do protected_high e fechamento abaixo
→ BSL_SWEEP

close acima do protected_high
→ CHOCH ALTISTA
→ TRANSICAO_ALTISTA
```

## Regras do estado NEUTRO

- não emitir CHOCH;
- formar estrutura inicial;
- usar sequência mínima configurável;
- promover para ALTISTA ou BAIXISTA somente após confirmação suficiente.

## Regras de transição

### `TRANSICAO_BAIXISTA`

- CHOCH bearish não confirma reversão total;
- aguardar quebra adicional relevante;
- se ocorrer BOS bearish subsequente:
  - emitir `REVERSAL_CONFIRMED`;
  - mudar para BAIXISTA;
- se preço recuperar o nível e retomar bullish:
  - emitir `FAILED_CHOCH` ou `RECLAIM`;
  - retornar para ALTISTA.

### `TRANSICAO_ALTISTA`

Aplicar lógica inversa.

## Critérios de aceite

- estado sempre determinístico;
- transições reproduzíveis;
- CHOCH não altera tendência final sozinho;
- todas as transições possuem evento e motivo.

---

# 13. FASE 4 — PROTECTED E WEAK LEVELS

## Objetivo

Implementar a semântica estrutural central.

## Regras

### Mercado ALTISTA

- o low que origina um rompimento válido do high anterior torna-se `PROTECTED_LOW`;
- o próximo high ainda não rompido torna-se `WEAK_HIGH`.

### Mercado BAIXISTA

- o high que origina um rompimento válido do low anterior torna-se `PROTECTED_HIGH`;
- o próximo low ainda não rompido torna-se `WEAK_LOW`.

## Tarefas

1. vincular protected level ao BOS que o confirmou;
2. vincular weak level ao alvo estrutural atual;
3. registrar substituição do protected level;
4. impedir múltiplos protected levels ativos no mesmo scope;
5. preservar histórico;
6. expor IDs para OB e FVG.

## Critérios de aceite

- cada estado bullish tem protected low e weak high quando aplicável;
- cada estado bearish tem protected high e weak low;
- troca de protected level ocorre apenas por evento válido;
- relações são auditáveis.

---

# 14. FASE 5 — DETECTOR DE BREAKS

## Objetivo

Detectar rompimentos causalmente, candle a candle.

## Tarefas

1. processar cada candle fechado;
2. avaliar apenas níveis ativos e disponíveis;
3. exigir cruzamento real do nível;
4. distinguir:
   - close break;
   - wick sweep;
   - gap break;
5. calcular tolerância:
   - ticks;
   - ATR;
6. calcular penetração;
7. calcular força;
8. verificar continuidade temporal;
9. verificar sessão;
10. verificar rollover;
11. gerar evento candidato;
12. classificar qualidade.

## Cruzamento bullish por fechamento

```text
close_anterior <= nível + tolerância
close_atual > nível + margem
```

## Cruzamento bearish por fechamento

```text
close_anterior >= nível - tolerância
close_atual < nível - margem
```

## Wick sweep bullish

```text
high_atual > nível
close_atual <= nível
```

## Wick sweep bearish

```text
low_atual < nível
close_atual >= nível
```

## Critérios de aceite

- preço já além do nível não gera novo rompimento;
- wick não é BOS;
- gap é classificado separadamente;
- toda quebra possui contexto e margem.

---

# 15. FASE 6 — EQH E EQL

## Objetivo

Evitar classificar níveis praticamente iguais como HH/LL artificiais.

## Tarefas

1. criar tolerância por:
   - ticks;
   - ATR;
2. classificar:
   - `EQH`;
   - `EQL`;
3. impedir promoção automática para BOS;
4. enviar eventos para módulo de liquidez;
5. registrar pool de liquidez;
6. preservar múltiplos swings no cluster;
7. calcular nível médio ou faixa.

## Critérios de aceite

- swings dentro da tolerância formam cluster;
- EQH/EQL não mudam a tendência sozinhos;
- sweep do cluster é detectável;
- nível pode alimentar BSL/SSL.

---

# 16. FASE 7 — FORÇA E DISPLACEMENT DA QUEBRA

## Objetivo

Diferenciar quebra marginal de quebra estrutural forte.

## Métricas mínimas

- `penetration_pts`;
- `penetration_ticks`;
- `penetration_atr`;
- `body_atr`;
- `range_atr`;
- `body_to_range_ratio`;
- `close_location_ratio`;
- presença de FVG;
- velocidade da perna;
- sobreposição entre candles;
- volume, quando confiável;
- continuidade temporal.

## Classificação

```text
WEAK_BREAK
VALID_BREAK
DISPLACEMENT_BREAK
```

## Regras

- quebra marginal pode permanecer como evento fraco;
- displacement deve exigir critérios configuráveis;
- score não deve ser hardcoded para WINFUT M5;
- perfis devem ser por ativo/timeframe;
- ausência de ATR pronto deve impedir score definitivo.

## Critérios de aceite

- eventos possuem score explicável;
- parâmetros configuráveis;
- score não altera validade estrutural básica;
- score apenas qualifica o evento.

---

# 17. FASE 8 — SWING STRUCTURE E INTERNAL STRUCTURE

## Objetivo

Processar duas estruturas independentes e relacionadas.

## Tarefas

1. criar pipeline `SWING`;
2. criar pipeline `INTERNAL`;
3. impedir que evento interno altere diretamente estado swing;
4. permitir relação:
   - `parent_structure_id`;
   - `parent_leg_id`;
5. diferenciar:
   - internal BOS;
   - swing BOS;
   - internal CHOCH;
   - swing CHOCH;
6. expor ambos no dashboard;
7. permitir filtros visuais.

## Critérios de aceite

- eventos internos e swing coexistem;
- complex pullback não destrói estrutura principal;
- cada evento possui scope;
- contexto MTF é preservado.

---

# 18. FASE 9 — CHOCH, MSS E REVERSÃO CONFIRMADA

## Objetivo

Evitar considerar todo CHOCH como mudança total de tendência.

## Tarefas

1. CHOCH deve iniciar transição;
2. definir segunda confirmação estrutural;
3. emitir:
   - `CHOCH`;
   - `REVERSAL_CONFIRMED`;
4. detectar `FAILED_CHOCH`;
5. detectar reclaim;
6. preservar tendência anterior até confirmação;
7. registrar nível da segunda quebra.

## Critérios de aceite

- CHOCH sozinho não muda tendência final;
- reversão exige evento adicional;
- falha de CHOCH é auditável;
- direção final é determinística.

---

# 19. FASE 10 — CONTEXTO DE SESSÃO, GAP E ROLLOVER

## Objetivo

Impedir que descontinuidades artificiais confirmem estrutura sem contexto.

## Tarefas

1. validar continuidade dos timestamps;
2. detectar candle ausente;
3. detectar gap de sessão;
4. detectar troca de contrato;
5. detectar rollover;
6. marcar:

```text
CONTINUOUS
SESSION_GAP
DATA_GAP
ROLLOVER
```

7. impedir que rollover confirme evento operacional por padrão;
8. permitir perfil configurável;
9. registrar a razão do bloqueio.

## Critérios de aceite

- gaps artificiais não são tratados como displacement normal;
- rollover é explicitamente marcado;
- regras configuráveis;
- nenhum descarte silencioso.

---

# 20. FASE 11 — PROCESSAMENTO INCREMENTAL

## Objetivo

Executar a engine candle a candle.

## Fluxo obrigatório

```text
novo candle fechado
→ validar continuidade
→ disponibilizar novos swings
→ atualizar níveis
→ verificar sweeps
→ verificar close breaks
→ classificar evento
→ atualizar estado
→ persistir
→ emitir snapshot
```

## Tarefas

1. criar estado serializável;
2. permitir retomar após restart;
3. garantir idempotência;
4. evitar varrer todo o futuro;
5. impedir duplicidade no replay;
6. implementar checkpoint;
7. suportar batch histórico usando o mesmo core incremental.

## Critérios de aceite

- batch e streaming produzem o mesmo resultado;
- reinício não duplica eventos;
- ordem temporal respeitada;
- nenhum acesso a candles futuros.

---

# 21. FASE 12 — PERSISTÊNCIA E VERSIONAMENTO

## Objetivo

Adicionar a nova verdade sem quebrar dados existentes.

## Tarefas

1. criar tabelas/coleções V3;
2. não sobrescrever V2;
3. adicionar `engine_version`;
4. adicionar `source_version`;
5. adicionar `config_hash`;
6. adicionar `run_id`;
7. adicionar `replay_id`;
8. criar índices por:
   - ativo;
   - timeframe;
   - scope;
   - break_at;
   - event_type;
9. criar migração reversível;
10. criar rollback.

## Critérios de aceite

- V2 preservada;
- V3 separada;
- rollback testado;
- escrita controlada por feature flag.

---

# 22. FASE 13 — OVERLAYS E DASHBOARD

## Objetivo

Corrigir a representação temporal.

## Regras visuais

- linha começa na origem do nível;
- linha termina na quebra;
- rótulo BOS/CHOCH aparece no candle da quebra;
- protected level e weak level possuem estilos distintos;
- sweep possui marcador próprio;
- CHOCH e reversal confirmed possuem estilos diferentes;
- internal e swing possuem espessuras diferentes;
- nada aparece antes de `available_index`.

## Tarefas

1. criar overlay V3;
2. manter overlay V2 para comparação;
3. adicionar toggle:
   - Legacy;
   - V3;
   - Both;
4. mostrar tooltip com:
   - tipo;
   - scope;
   - nível;
   - origem;
   - quebra;
   - método;
   - estado anterior;
   - estado resultante;
   - score;
5. mostrar divergências.

## Critérios de aceite

- nenhum label antecipado;
- nenhuma linha fora do intervalo;
- eventos visíveis na posição correta;
- dashboard funciona em troca de timeframe.

---

# 23. FASE 14 — INTEGRAÇÃO COM OB E FVG

## Objetivo

Fornecer estrutura causal para os módulos dependentes.

## Integração com OB

OB deve receber:

```text
structure_event_id
broken_level_id
impulse_leg_id
protected_level_id
event_available_index
```

## Integração com FVG

FVG deve receber:

```text
structure_event_id
impulse_leg_id
break_direction
break_index
displacement_score
```

## Regras

- OB não pode usar BOS futuro;
- FVG não pode ser marcado como displacement apenas por proximidade temporal;
- toda associação deve ser causal;
- direção deve ser compatível;
- scope deve ser compatível.

## Critérios de aceite

- OB e FVG possuem IDs de estrutura;
- nenhum vínculo por heurística temporal isolada;
- testes anti-lookahead passam.

---

# 24. FASE 15 — TESTES UNITÁRIOS

## Casos mínimos obrigatórios

### Estrutura bullish

- formação inicial;
- BOS bullish;
- protected low;
- weak high;
- novo BOS bullish;
- CHOCH bearish;
- reversão confirmada.

### Estrutura bearish

- casos inversos.

### Sweeps

- wick acima e fechamento abaixo;
- wick abaixo e fechamento acima;
- sweep sem mudança de tendência.

### Anti-lookahead

- swing confirmado tardiamente;
- swing ainda indisponível;
- break antes da disponibilidade;
- replay parcial.

### EQH/EQL

- níveis iguais;
- níveis dentro da tolerância;
- níveis fora da tolerância.

### Gaps

- sessão;
- candle ausente;
- rollover;
- gap real intraday.

### Scopes

- internal BOS dentro de swing bullish;
- internal CHOCH sem alterar swing;
- complex pullback.

### Persistência

- replay repetido;
- restart;
- checkpoint;
- idempotência.

## Critérios de aceite

- cobertura mínima de 90% no core;
- 100% dos casos anti-lookahead;
- nenhuma dependência de candles futuros;
- fixtures determinísticas.

---

# 25. FASE 16 — TESTES DE PROPRIEDADE

Implementar invariantes:

1. nenhum evento pode existir antes de `available_index`;
2. `break_index >= broken_level_available_index`;
3. BOS bullish só rompe nível acima;
4. BOS bearish só rompe nível abaixo;
5. sweep por wick não pode ser close break;
6. CHOCH deve ser contra o estado anterior;
7. protected level deve ser oposto ao weak level;
8. internal não substitui swing automaticamente;
9. evento histórico nunca é apagado;
10. execução batch deve igualar execução incremental.

---

# 26. FASE 17 — REPLAY E BACKTEST SHADOW

## Objetivo

Comparar V2 e V3 sem promover trade.

## Ativos mínimos

- WINFUT;
- WDOFUT;
- um ativo Forex;
- um índice ou ação;
- um ativo com gaps frequentes.

## Timeframes mínimos

- M1;
- M2;
- M5;
- M15;
- H4;
- D1.

## Métricas

- total de BOS;
- total de CHOCH;
- total de sweeps;
- total de reversal confirmed;
- divergência V2/V3;
- eventos antecipados pela V2;
- eventos removidos por anti-lookahead;
- distribuição por scope;
- distribuição por break method;
- precisão temporal;
- impacto em OB;
- impacto em FVG;
- impacto no directional bias.

## Critérios de aceite

- nenhuma promoção de trade;
- relatório por ativo/timeframe;
- divergências explicadas;
- nenhum erro silencioso;
- todos os eventos rastreáveis.

---

# 27. FASE 18 — MIGRAÇÃO CONTROLADA

## Etapa 1 — Legacy only

```text
STRUCTURE_ENGINE_MODE=legacy
```

## Etapa 2 — Shadow V3

```text
STRUCTURE_ENGINE_MODE=shadow_v3
```

- roda V2 e V3;
- persiste V3 separadamente;
- gera comparativo;
- não altera sinais.

## Etapa 3 — V3 visual

- dashboard pode exibir V3;
- sinais continuam V2.

## Etapa 4 — V3 contextual

- módulos de estudo podem consumir V3;
- trade continua bloqueado.

## Etapa 5 — promoção futura

Somente após:

- validação de backtest;
- replay;
- revisão humana;
- aprovação arquitetural;
- aprovação dos guardrails;
- estabilidade operacional.

---

# 28. ROLLBACK

O rollback deve permitir:

1. desativar V3 por variável de ambiente;
2. interromper escrita V3;
3. voltar overlay para V2;
4. manter dados V3 para auditoria;
5. não apagar dados;
6. não alterar schema V2;
7. restaurar baseline.

Comando ou procedimento de rollback deve ser documentado em:

```text
ROLLBACK_STRUCTURE_ENGINE_V3.md
```

---

# 29. OBSERVABILIDADE

Logs mínimos:

```text
structure.swing_available
structure.level_created
structure.level_promoted
structure.level_swept
structure.level_broken
structure.event_confirmed
structure.state_changed
structure.reversal_confirmed
structure.choch_failed
structure.replay_divergence
structure.lookahead_blocked
structure.rollover_blocked
```

Métricas mínimas:

- níveis ativos;
- eventos por tipo;
- divergência V2/V3;
- eventos bloqueados por lookahead;
- eventos por gap;
- tempo de processamento;
- duplicatas evitadas;
- estados por ativo/timeframe.

---

# 30. CONFIGURAÇÃO

Criar configuração por perfil:

```text
price_tick
atr_period
equal_level_tolerance_ticks
equal_level_tolerance_atr
break_margin_ticks
break_margin_atr
require_close_break
allow_wick_sweep
allow_gap_break
allow_cross_session_break
allow_rollover_break
min_body_atr
min_range_atr
min_body_to_range_ratio
min_displacement_score
swing_scope_config
internal_scope_config
```

Regras:

- nada hardcoded para WINFUT;
- defaults documentados;
- hash de configuração persistido;
- perfis por ativo/timeframe;
- fallback explícito.

---

# 31. CRITÉRIOS DE ACEITE GERAIS

A implementação só pode ser considerada concluída quando:

1. nenhum evento depende de swing futuro;
2. todo swing respeita `available_index`;
3. eventos são emitidos no candle da quebra;
4. BOS/CHOCH não são registrados no swing de origem;
5. wick sweep é separado de close break;
6. protected/weak levels existem;
7. CHOCH não confirma reversão sozinho;
8. internal e swing são independentes;
9. EQH/EQL usam tolerância;
10. batch e incremental são idênticos;
11. persistência é idempotente;
12. overlays não antecipam eventos;
13. V2 permanece disponível;
14. feature flags funcionam;
15. rollback foi testado;
16. testes passam;
17. backtest shadow foi executado;
18. relatório final foi entregue.

---

# 32. DEFINITION OF DONE

A fase estará concluída somente com:

- código compilando;
- testes unitários passando;
- testes de integração passando;
- testes anti-lookahead passando;
- replay determinístico;
- baseline V2 preservado;
- V3 persistida separadamente;
- dashboard comparativo funcional;
- documentação atualizada;
- changelog;
- relatório final;
- zero promoção automática;
- zero alteração silenciosa de produção.

---

# 33. ARQUIVOS ESPERADOS

A IA de código deve criar ou atualizar, conforme a arquitetura real do projeto:

```text
technical_engine/structure/
  __init__.py
  swing_availability_adapter.py
  structure_level_registry.py
  structure_state_machine.py
  structure_break_detector.py
  structure_event_classifier.py
  structure_engine_v3.py
  structure_models_v3.py
  structure_config_v3.py
  structure_persistence_v3.py
  structure_overlays_v3.py
  legacy_parity_adapter.py
```

Testes:

```text
tests/structure_v3/
  test_swing_availability.py
  test_structure_levels.py
  test_structure_state_machine.py
  test_break_detector.py
  test_sweeps.py
  test_eqh_eql.py
  test_choch_reversal.py
  test_internal_vs_swing.py
  test_session_gap_rollover.py
  test_incremental_parity.py
  test_idempotency.py
  test_anti_lookahead.py
  test_overlays.py
```

Documentação:

```text
docs/architecture/STRUCTURE_ENGINE_V3.md
docs/migrations/STRUCTURE_ENGINE_V3_MIGRATION.md
docs/operations/ROLLBACK_STRUCTURE_ENGINE_V3.md
docs/reports/RELATORIO_FINAL_STRUCTURE_ENGINE_V3.md
```

---

# 34. RELATÓRIO FINAL OBRIGATÓRIO

A IA deve entregar um relatório com:

## 34.1. Resumo executivo

- o que foi corrigido;
- o que ficou pendente;
- status final;
- riscos remanescentes.

## 34.2. Arquivos alterados

Tabela:

| Arquivo | Tipo | Alteração |
|---|---|---|

## 34.3. Contratos criados

- modelos;
- enums;
- tabelas;
- DTOs;
- APIs.

## 34.4. Testes

| Suíte | Total | Passou | Falhou | Skip |
|---|---:|---:|---:|---:|

## 34.5. Anti-lookahead

Demonstrar:

- swing indisponível bloqueado;
- evento no break index;
- replay parcial;
- igualdade batch/incremental.

## 34.6. Comparativo V2/V3

| Métrica | V2 | V3 | Diferença |
|---|---:|---:|---:|

## 34.7. Impacto downstream

- OB;
- FVG;
- liquidez;
- directional bias;
- scanner;
- dashboard.

## 34.8. Guardrails

Confirmar explicitamente:

```text
shadow_only = true
can_promote_trade = false
apply_automatically = false
llm_decision_used = false
production_truth_replaced = false
```

## 34.9. Rollback

- procedimento;
- comandos;
- validação.

## 34.10. Status final

Usar uma das opções:

```text
STRUCTURE_V3_COMPLETED_SHADOW
STRUCTURE_V3_COMPLETED_WITH_LIMITATIONS
STRUCTURE_V3_BLOCKED
STRUCTURE_V3_FAILED
```

---

# 35. ORDEM DE EXECUÇÃO RECOMENDADA

Executar exatamente nesta ordem:

1. auditoria;
2. baseline;
3. feature flags;
4. normalização de swings;
5. registry de níveis;
6. máquina de estados;
7. protected/weak levels;
8. break detector;
9. sweeps;
10. EQH/EQL;
11. força/displacement;
12. internal/swing;
13. CHOCH/reversal;
14. sessão/gap/rollover;
15. processamento incremental;
16. persistência;
17. overlays;
18. integração com OB/FVG;
19. testes;
20. replay shadow;
21. relatório final.

Não avançar para integração downstream antes de os testes anti-lookahead estarem verdes.

---

# 36. REGRAS PARA A IA DE CÓDIGO

1. Não reescrever módulos não relacionados.
2. Não remover compatibilidade V2.
3. Não alterar enums públicos sem migração.
4. Não usar candles futuros.
5. Não criar fallback silencioso.
6. Não converter sweep em BOS.
7. Não considerar CHOCH como reversão final.
8. Não apagar eventos históricos.
9. Não hardcodar parâmetros do WINFUT.
10. Não promover sinais.
11. Não ignorar falhas de alinhamento.
12. Não declarar concluído sem testes.
13. Não mudar produção sem feature flag.
14. Documentar toda decisão arquitetural.
15. Em caso de ambiguidade, preservar dados e marcar como `UNCLASSIFIED`, nunca inventar uma classificação.

---

# 37. RESULTADO ESPERADO

Ao final, a engine deverá responder corretamente:

- qual é o estado estrutural atual;
- qual nível está protegido;
- qual nível está fraco;
- qual nível foi varrido;
- qual nível foi rompido;
- em qual candle a quebra ocorreu;
- quando o evento ficou disponível;
- se o evento é internal ou swing;
- se foi BOS, CHOCH ou reversão confirmada;
- se houve displacement;
- se o evento ocorreu em sessão contínua, gap ou rollover;
- qual OB e FVG pertencem à perna estrutural;
- por que a classificação foi emitida.

A engine final deve ser causal, incremental, explicável e segura para servir como base canônica dos demais módulos SMC.

---
# SEÇÕES ESPECÍFICAS — STRUCTURE ENGINE V3

## Ownership do Domínio (Confirmado)

Structure é dona exclusiva de:
- `StructureLevelV3`
- `StructureStateV3`
- `StructureEventV3`
- `StructureLegV3`
- `SwingSmcRoleProjectionV3`

**Regra:** Structure é dona de protected/weak, BOS/CHOCH, trend state, StructureLeg e confirmação estrutural. Não modifica o registro canônico de Swing.

## Contratos Produzidos

| Contrato | Consumidor | Gate |
|---|---|---|
| `StructureEventV3` | FVG, Order Block | G3 |
| `StructureLegV3` | Retracement | G3 |
| `SwingSmcRoleProjectionV3` | Read models, OB, Liquidity | G3 |
| `StructureLevelV3` | Liquidity | G3 |

## Contratos Consumidos

| Contrato | Produtor | Gate |
|---|---|---|
| `CanonicalSwingContractV1` | Swing | G2 |

## Gate de Entrada

G2 (Swing Core Ready)

## Gate de Saída

**G3 — Structure Ready:** CanonicalSwing adapter, StructureLevel, StructureState, StructureEvent, StructureLeg, role projection, anti-lookahead.

## Caminhos Batch

- `smc_engine_v3/structure.py`

## Caminhos Incrementais

- `incremental/components/bos_choch.py`

## Proibição

- Não modificar o registro canônico de Swing
- Não criar protected/weak diretamente no swing
