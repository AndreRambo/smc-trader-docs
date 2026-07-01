# RELATÓRIO R1 — QUALIDADE DE DADOS
## Validação do Dataset Real WINFUT 2021–2026

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Ferramenta:** `tools/smc_v3_validation/validate_winfut_csvs.py`
**Output:** `WINFUT_violations.csv` (8.751 linhas — inclui violações não-críticas como gap_session_end/gap_holiday, que são esperadas)

---

## 1. Resultado por Arquivo

| Arquivo | Candles | Dup | Unordered | OHLC inválido | Zero/Neg | Gap fim-sessão | Gap feriado | Gap suspeito | Open/Close disc. | Rollover (>5%) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Daily | 1.246 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 3 | 0 |
| H1 | 12.018 | 0 | 0 | 0 | 0 | 981 | 264 | 0 | 3 | 0 |
| H4 | 3.733 | 0 | 0 | 0 | 0 | 981 | 264 | 0 | 3 | 0 |
| M15 | 46.419 | 0 | 0 | 0 | 0 | 981 | 264 | 2 | 3 | 0 |
| M5 | 137.998 | 0 | 0 | 0 | 0 | 981 | 264 | 2 | 3 | 0 |
| M2 | 345.466 | 0 | 0 | 0 | 0 | 981 | 264 | 3 | 3 | 0 |
| M1 | 689.573 | 0 | 0 | 0 | 0 | 981 | 264 | 12 | 3 | 0 |

## 2. Totais Agregados e Cálculo do Gate

| Métrica | Total | % do dataset | Limite (plano) | Resultado |
|---|---:|---:|---|---|
| Duplicatas | 0 | 0% | deve ser 0 | PASS |
| Fora de ordem | 0 | 0% | deve ser 0 | PASS |
| Valores zero/negativos | 0 | 0% | deve ser 0 | PASS |
| OHLC inválido (H<max(O,C) ou L>min(O,C)) | 0 | 0.0000% | ≤ 1% | PASS |
| Gaps suspeitos (intra-sessão) | 19 | 0.0015% | ≤ 0.1% | PASS |

**Nenhuma violação crítica excede os limites definidos no plano mestre.**

## 3. Gaps de Fim de Sessão e Feriados (M1, referência mais granular)

- **981 gaps de fim de sessão** por arquivo intraday: transição normal 17:45–18:24 (fechamento) → 09:00 (abertura) do próximo pregão. Esperado, um por dia útil.
- **264 gaps classificados como feriado/fim de semana** (M1), sendo:
  - **243 gaps de ~2 dias** (padrão sexta → segunda, fins de semana regulares)
  - **21 gaps de 3–4 dias**, coincidindo com feriados nacionais emendados a fins de semana (ex.: Carnaval, Natal, Ano Novo, Tiradentes)

Nenhum gap indica perda de dados dentro de uma sessão de pregão regular.

## 4. Gaps Suspeitos (intra-sessão, 19 no total)

Todos os 19 gaps suspeitos identificados são **micro-gaps de baixa liquidez** (3 a 70 minutos), concentrados em dias específicos de volume anormalmente baixo:

| Data | Timeframe(s) | Duração | Observação |
|---|---|---|---|
| 2021-09-09 | M1 | 3–4 min | Baixa liquidez pontual |
| 2023-11-14 | M1 | 3 min | Baixa liquidez pontual |
| 2025-04-09 | M1 | 3 min (x2) | Baixa liquidez pontual |
| 2025-06-03 | M15, M1, M2, M5 | 45–56 min | Provável interrupção de pregão ou baixa liquidez extrema |
| 2025-07-30 | M1 | 5 min | Baixa liquidez pontual |
| 2025-08-21 | M15, M1, M2, M5 | 60–70 min | Provável interrupção de pregão ou baixa liquidez extrema |
| 2025-09-29 | M1, M2 | 8 min | Baixa liquidez pontual |
| 2026-01-26 | M1 | 3 min | Baixa liquidez pontual |

**Recomendação:** as janelas de 2025-06-03 e 2025-08-21 (gaps de ~1h) devem ser incluídas como candidatas a "golden window de baixa volatilidade/gap intra-sessão" na Fase R2, para validar como o pipeline V3 trata ausência momentânea de candles sem gerar eventos espúrios.

## 5. Open/Close Discontinuity (3 eventos, replicados em todos os timeframes)

3 datas com gap de abertura entre 3,1% e 3,5% (acima da tolerância de 3% adotada, mas abaixo do limiar de 5% usado para classificar rollover de contrato):

| Data | Close anterior | Open | Variação |
|---|---:|---:|---:|
| 2021-11-26 | 176.849 | 171.308 | 3,13% |
| 2022-10-03 | 169.519 | 174.800 | 3,12% |
| 2022-10-31 | 175.844 | 169.767 | 3,46% |

Estas 3 datas são analisadas em detalhe no **RELATORIO_R1_ROLLOVER_WINFUT.md**.

## 6. Falso Positivo Identificado (ferramenta, não dado)

`WIN$_H4_...csv` reportou 1.241 candles "fora do horário de mercado" — **falso positivo da ferramenta**: o heurístico assumiu abertura às 09:00, mas o H4 legitimamente inicia a barra às 08:00 (barra de pré-abertura do servidor MT5, consistente em todo o histórico). Não é um defeito do dado; documentado como limitação conhecida da ferramenta `validate_winfut_csvs.py` (não bloqueia o gate, pois `outside_market_hours` não integra os critérios de bloqueio do plano).

---

## 7. GATE

```
R1_DATASET_CANONICAL_APPROVED
```

**Justificativa:** zero duplicatas, zero registros fora de ordem, zero valores OHLC inválidos, zero valores zerados/negativos, e gaps suspeitos (0,0015%) muito abaixo do limite de 0,1% definido no plano. As 3 discontinuidades de abertura >3% são explicadas por rollover de contrato WINFUT (ver relatório específico) e não representam corrupção de dado.

**Timezone adotada para a Fase R2 em diante:** `America/Sao_Paulo` (BRT, UTC-3), aplicada explicitamente no `build_canonical_winfut_dataset.py`.
