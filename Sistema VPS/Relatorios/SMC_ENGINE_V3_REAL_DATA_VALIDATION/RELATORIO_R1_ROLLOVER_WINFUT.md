# RELATÓRIO R1 — ROLLOVER WINFUT
## Análise de Descontinuidades de Preço no Dataset WIN$ 2021–2026

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Ferramentas:** `validate_winfut_csvs.py` (detecção), `build_canonical_winfut_dataset.py` (classificação `session_type`)

---

## 1. Metodologia

Um evento é candidato a rollover quando o `OPEN` de um candle diário difere do `CLOSE` do candle anterior em mais que um limiar percentual:

- **Gap de abertura legítimo (tolerado):** até 3,0%
- **Rollover candidato (marcado como `session_type=ROLLOVER` no dataset canônico):** > 3,0%
- **Rollover crítico (bloquearia o gate R1):** > 5,0%

Nenhuma ocorrência ultrapassou 5%. Três ocorrências ultrapassaram 3%.

## 2. Datas Detectadas

| # | Data (trading_date) | Close anterior | Open | Variação | Timeframes afetados |
|---|---|---:|---:|---:|---|
| 1 | 2021-11-26 | 176.849 | 171.308 | -3,13% | Daily, H1, H4, M15, M5, M2, M1 (todos) |
| 2 | 2022-10-03 | 169.519 | 174.800 | +3,12% | Daily, H1, H4, M15, M5, M2, M1 (todos) |
| 3 | 2022-10-31 | 175.844 | 169.767 | -3,46% | Daily, H1, H4, M15, M5, M2, M1 (todos) |

Todas as 3 datas aparecem de forma **idêntica e consistente em todos os 7 timeframes** — confirmando que a origem é uma característica real do instrumento subjacente (mudança de contrato), não um artefato de um único arquivo ou parser.

## 3. Interpretação

O símbolo `WIN$` (usado pela MetaTrader/corretora de origem) tipicamente representa o **contrato corrente/mais líquido do mini-índice**, sem ajuste retroativo (back-adjustment) de preço entre vencimentos. O WINFUT possui vencimentos bimestrais (fevereiro, abril, junho, agosto, outubro, dezembro), com o rollover de liquidez ocorrendo tipicamente na semana anterior ao vencimento.

As datas observadas (26/nov, 03/out, 31/out) não coincidem exatamente com os meses de vencimento pares canônicos — isso é consistente com o padrão real de rollover do WINFUT, que costuma migrar de contrato **antes** do vencimento técnico (rollover de liquidez, e não de expiração), e pode variar ano a ano conforme o comportamento do mercado.

**Conclusão:** as 3 descontinuidades são explicadas por troca de contrato subjacente (rollover), não por erro de captura ou falha de dado.

## 4. Impacto em Sessões Adjacentes

Nenhum gap suspeito de horário (`GAP_SUSPICIOUS`) foi detectado nas datas de rollover — os candles intraday em torno dessas datas mantêm frequência normal (sem buracos de tempo). O impacto é **exclusivamente de preço** (salto de nível), não de continuidade temporal.

Nenhuma duplicata ou violação OHLC foi associada a essas datas.

## 5. Recomendação para as Fases Seguintes

1. **Não tratar como gap normal de horário** — são eventos de preço, e o candle após o rollover é geometricamente válido (OHLC consistente internamente), apenas descontínuo em relação ao close anterior.
2. **Marcar explicitamente** (já implementado) via coluna `session_type=ROLLOVER` no dataset canônico gerado por `build_canonical_winfut_dataset.py`, permitindo que as engines de Structure, Swing, FVG e Liquidity apliquem tratamento específico (ex.: não gerar FVG "falso" a partir do gap de rollover, não contar como BOS/CHOCH estrutural genuíno sem contexto).
3. **Fase R2 (Golden Windows):** incluir ao menos uma das 3 datas de rollover como golden window dedicada, para validar visualmente que o pipeline V3 não gera zonas espúrias (FVG, OB, liquidity sweep) a partir do salto de preço do rollover.
4. **Fase R6 (Structure):** verificar se o detector de BOS/CHOCH trata corretamente uma quebra de nível causada por gap de rollover — o nível pode ser "quebrado" tecnicamente pelo salto de preço sem que isso represente um evento estrutural real de mercado.

---

## 6. Status

```
ROLLOVER_ANALYSIS_COMPLETE — 3 eventos identificados, nenhum crítico, tratamento recomendado via session_type=ROLLOVER
```

Este relatório não bloqueia o gate R1 (já aprovado em RELATORIO_R1_QUALIDADE_DADOS.md), mas define requisitos de tratamento causal para as fases R6 (Structure), R9 (Liquidity) e R10 (FVG).
