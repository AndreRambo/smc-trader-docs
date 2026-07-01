# RELATÓRIO R4 — SESSIONS
## Sessão B3/WINFUT Causal + Achado Crítico no BOS/CHOCH

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivos alterados:** `technical_engine/smc_engine_v3/b3_calendar.py` (novo), `incremental/components/sessions.py`, `incremental/components/bos_choch.py`

---

## 1. Sessão B3/WINFUT

O `SessionsComponent` já usava `zoneinfo.ZoneInfo` corretamente (sem `pytz`, sem offset fixo) e era causal (nenhum `datetime.now()`), mas suas sessões default eram forex (LONDON/NEW_YORK/TOKYO). Foi criado `b3_calendar.py` com:

- `WINFUT_SESSION_CONFIG`: `America/Sao_Paulo`, abertura 09:00, fechamento 18:30 (cobre os candles de cauda observados até 18:24 no R1)
- `b3_holidays(2021, 2026)`: 87 datas de feriados nacionais observados pela B3, calculadas deterministicamente (Páscoa via algoritmo de Gauss + datas fixas), não digitadas à mão — evita erro silencioso de data. Inclui Carnaval, Sexta-feira Santa, Corpus Christi, Tiradentes, Dia do Trabalho, Independência, Nossa Senhora Aparecida, Finados, Proclamação da República, Consciência Negra (a partir de 2024), véspera e dia de Natal, véspera e dia de Ano Novo.

## 2. Bug Encontrado e Corrigido: Sessão Nunca Fechava

**Sintoma:** ao configurar a sessão B3 real e rodar contra dados intraday reais, `SESSION_OPEN` disparava 1 vez e `SESSION_CLOSE` nunca disparava.

**Causa:** o dataset intraday só contém candles **dentro** do horário de mercado (09:00–18:24) — nunca existe um candle "fora da janela" no feed. O candle das 09:00 do dia seguinte também cai dentro de `[09:00, 18:30)`, então a checagem `in_window` nunca via `False` para forçar o fechamento.

**Correção:** adicionado rastreamento de `trading_date` por sessão aberta. Quando um novo candle chega com uma `trading_date` diferente da sessão atualmente aberta, a sessão do dia anterior é fechada **usando o último candle real daquele dia** (não o candle do novo dia), antes de avaliar a abertura da nova sessão.

**Resultado real (12.018 candles H1, 2021–2026):**

```
SESSION_OPEN:  1.246
SESSION_CLOSE: 1.245  (a última sessão ainda está aberta ao fim do dataset — esperado)
```

**1.246 SESSION_OPEN bate exatamente com os 1.246 candles do arquivo Daily real (R1)** — validação cruzada forte de que a sessão está sendo detectada em todos os dias de pregão corretos, nem mais nem menos.

## 3. PeriodSummary (TradingPeriodSummaryV3)

Adicionado ao payload do `SESSION_CLOSE`: `period_high`, `period_low`, `period_high_candle_id`, `period_low_candle_id`, `period_start_candle_id`, `trading_date`. Rastreado incrementalmente candle a candle (causal), serializado em `to_state_dict()`/`restore_from_state_dict()` para suportar checkpoint/restore (Fase R16).

Exemplo real:
```
{'session_name': 'B3_WINFUT', 'trading_date': '2021-06-24',
 'period_high': 220951.0, 'period_high_candle_id': 'WINFUT:H1:19',
 'period_low': 219552.0, 'period_low_candle_id': 'WINFUT:H1:21', ...}
```

Esta estrutura é o que a Fase R7 (Previous High/Low) consumirá para calcular PDH/PDL reais por dia de pregão — hoje `PreviousHighLowComponent` usa uma janela de N candles fixos (`period_candles=24`), não os limites de sessão real. **Registrado como P1 para R7.**

---

## 4. Achado Crítico Durante Validação: BOS/CHOCH Disparando Múltiplas Vezes por Nível

### 4.1 Como foi descoberto

Antes de fechar o R4, o usuário pediu uma checagem de sanidade das zonas calculadas no R3. A razão `bos_choch/swings` estava em **3,14** — muito acima do esperado. O plano mestre exige explicitamente (seção "R6 Structure"): `events_per_broken_level <= 1`.

### 4.2 Causa raiz

Em `incremental/components/bos_choch.py`, a função `_detect_bos_choch()` comparava o preço de fechamento de **cada candle** contra o nível do swing confirmado mais recente (`self._confirmed_highs[-1]`), mas **nunca marcava esse nível como "quebrado"**. Resultado: uma vez que o preço fechava acima de um swing high, **todo candle subsequente** que também fechasse acima daquele mesmo nível gerava um **novo evento** (com `struct_id` distinto, pois o hash inclui `break_candle_id`) — até que um novo swing confirmado substituísse o nível de referência.

Isso violava diretamente a invariante causal do plano: um nível estrutural deve gerar **no máximo uma** quebra.

### 4.3 Correção

Adicionado campo `"broken": False` a cada swing confirmado (`_confirmed_highs`/`_confirmed_lows`). A checagem de quebra agora exige `not h1["broken"]`; ao disparar o evento, o nível é marcado `h1["broken"] = True` e não gera mais eventos até ser substituído por um novo swing confirmado.

### 4.4 Resultado da Correção (12.018 candles H1 reais)

| Métrica | Antes | Depois |
|---|---:|---:|
| bos_choch total | 4.775 | 635 |
| swings total | 1.519 | 1.519 (inalterado) |
| razão bos_choch/swings | 3,14 | **0,418** |
| origin_candle_id com múltiplos eventos | não medido | 3 de 632 |

**Verificação dos 3 casos restantes:** todos são legítimos, não bugs residuais. Um mesmo candle pode ser simultaneamente o swing HIGH mais recente e o swing LOW mais recente (listas `_confirmed_highs`/`_confirmed_lows` são independentes), gerando um evento de quebra de alta E um evento de quebra de baixa a partir do mesmo `origin_candle_id`, mas em **níveis de preço diferentes** e **direções diferentes** — não é a mesma quebra duplicada. Exemplo real:

```
WINFUT:H1:6237 BOS_BULLISH  BULLISH  level=171261.0  break=WINFUT:H1:6246
WINFUT:H1:6237 CHOCH_BEARISH BEARISH level=170058.0  break=WINFUT:H1:6268
```

**Zero violações reais de "um break por nível" após a correção**, confirmadas em 632 níveis distintos processados em 12.018 candles reais.

### 4.5 Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2102 passed, 0 failed (242.5s)
```

Nenhuma regressão introduzida pela correção.

---

## 5. GATE

```
R4_SESSIONS_PASS
```

**Justificativa:**
- Sessão B3/WINFUT causal, timezone `America/Sao_Paulo` explícita, calendário de feriados real calculado deterministicamente
- `SESSION_OPEN` bate exatamente (1.246 = 1.246) com o inventário real do R1
- `PeriodSummary` (high/low por sessão) implementado e serializável para checkpoint
- Bug crítico de causalidade no BOS/CHOCH (violação de `events_per_broken_level <= 1`) encontrado e corrigido durante esta fase, com evidência real de correção (3,14 → 0,418) e verificação manual dos casos residuais
- 2.102 testes de regressão, 0 falhas

**P1 aberto para R7:** `PreviousHighLowComponent` usa janela de candles fixa, não os limites de sessão do `SessionsComponent` — PDH/PDL não são tecnicamente "dia de pregão anterior real" ainda.

**Nota:** a correção do BOS/CHOCH pertence tematicamente à Fase R6 (Structure), mas foi resolvida agora por ter sido descoberta durante a validação de sanidade do R4/R3. Será revisitada formalmente em R6 junto com os demais requisitos de Structure (protected/weak, StructureLegV3, scope).

**Próxima fase:** R5 — Swing.
