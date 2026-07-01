# RELATÓRIO R1 — INVENTÁRIO CSV
## Auditoria do Dataset Real WINFUT 2021–2026

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Ferramenta:** `tools/smc_v3_validation/inventory_winfut_csvs.py`
**Fonte:** `/home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/WINFUT_2021_2026`
**Output:** `WINFUT_inventory.json` (mesmo diretório deste relatório)

---

## 1. Arquivos Encontrados

| Arquivo | Timeframe | Candles | Tamanho | Separador | Período |
|---|---|---:|---:|---|---|
| `WIN$_Daily_202106220000_202606190000.csv` | Daily | 1.246 | 0.07 MB | TAB | 2021.06.22 → 2026.06.19 |
| `WIN$_H4_202106220800_202606191600.csv` | H4 | 3.733 | 0.24 MB | TAB | 2021.06.22 08:00 → 2026.06.19 16:00 |
| `WIN$_H1_202106220900_202606191800.csv` | H1 | 12.018 | 0.75 MB | TAB | 2021.06.22 09:00 → 2026.06.19 18:00 |
| `WIN$_M15_202106220900_202606191815.csv` | M15 | 46.419 | 2.86 MB | TAB | 2021.06.22 09:00 → 2026.06.19 18:15 |
| `WIN$_M5_202106220900_202606191820.csv` | M5 | 137.998 | 8.38 MB | TAB | 2021.06.22 09:00 → 2026.06.19 18:20 |
| `WIN$_M2_202106220900_202606191824.csv` | M2 | 345.466 | 20.69 MB | TAB | 2021.06.22 09:00 → 2026.06.19 18:24 |
| `WIN$_M1_202106220900_202606191824.csv` | M1 | 689.573 | 40.89 MB | TAB | 2021.06.22 09:00 → 2026.06.19 18:24 |

**TOTAL: 7 arquivos, 1.236.453 candles, ≈ 74 MB.**

## 2. Estrutura do Schema

- **Encoding:** UTF-8 (com BOM em alguns arquivos — tratado como `utf-8-sig`)
- **Separador:** TAB (`\t`) em todos os arquivos
- **Cabeçalho:** colunas delimitadas por `<` e `>`
- **Colunas intraday (H1, H4, M1, M2, M5, M15):** `DATE, TIME, OPEN, HIGH, LOW, CLOSE, TICKVOL, VOL, SPREAD` (9 colunas)
- **Colunas Daily:** `DATE, OPEN, HIGH, LOW, CLOSE, TICKVOL, VOL, SPREAD` (8 colunas — sem `TIME`)
- **Formato de data:** `YYYY.MM.DD`
- **Formato de hora:** `HH:MM:SS`
- **Preços:** inteiros em pontos WINFUT (ex.: `220256` = 220.256 pontos), convertidos para float na canonicalização
- **`TICKVOL`:** volume de ticks (negociações); **`VOL`:** volume financeiro estimado; **`SPREAD`:** sempre `1` (não informativo)

## 3. Observações Estruturais

1. O timeframe **Daily não possui coluna `TIME`** — tratado com formato de data isolado no parser.
2. O horário de mercado observado é **09:00–18:24** (WINFUT/B3), com o candle H4 iniciando às 08:00 (barra pré-abertura do servidor MT5).
3. **Nenhuma coluna de timezone explícita** existe nos arquivos — decisão adotada: assumir **America/Sao_Paulo (BRT, UTC-3)**, coerente com o horário de pregão B3 09:00–18:00 e confirmado no relatório de qualidade (R1_QUALIDADE_DADOS).
4. O período coberto é **2021-06-22 a 2026-06-19**, ≈ 5 anos completos, cobrindo os splits Development (2021–2024), Validation (2025) e Holdout (2026) definidos na Fase R2.
5. M1 é o timeframe mais denso (689.573 candles) — base para replay candle a candle e golden windows.

---

**Ferramenta usada:** `python3 tools/smc_v3_validation/inventory_winfut_csvs.py --input <dir> --output WINFUT_inventory.json`
**Resultado:** inventário íntegro, sem erros de leitura em nenhum dos 7 arquivos.
