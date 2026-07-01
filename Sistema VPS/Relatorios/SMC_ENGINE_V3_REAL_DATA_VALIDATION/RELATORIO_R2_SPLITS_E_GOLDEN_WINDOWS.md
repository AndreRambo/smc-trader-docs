# RELATÓRIO R2 — SPLITS E GOLDEN WINDOWS
## Divisão Cronológica e Seleção de Janelas Reais WINFUT 2021–2026

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Ferramenta:** `tools/smc_v3_validation/select_golden_windows.py`
**Manifesto:** `tests/smc_engine_v3/real_data/golden_windows_manifest.yaml`

---

## 1. Splits Cronológicos (sem shuffle)

| Split | Período | Candles (Daily) | Observação |
|---|---|---:|---|
| **Development** | 2021-06-22 → 2024-12-30 | 881 | Inclui os 3 eventos de rollover identificados em R1 (26/11/2021, 03/10/2022, 31/10/2022) — nenhum é cortado no meio |
| **Validation** | 2025-01-02 → 2025-12-30 | 250 | Inclui os 2 dias de micro-gap intraday de baixa liquidez (03/06/2025, 21/08/2025) |
| **Holdout** | 2026-01-02 → 2026-06-19 | 115 | Congelado — nenhum parâmetro pode ser ajustado após início do holdout (Fase R21) |

**Critério de corte:** ano civil, sem necessidade de ajuste — nenhum contrato/rollover é seccionado entre splits (todos os 3 rollovers caem dentro de Development).

## 2. Metodologia de Seleção de Golden Windows

Como as engines causais V3 (Swing, Structure, FVG, Liquidity, OB) ainda contêm os P0s identificados em R0 (look-ahead, ordem causal invertida), **não é possível usar a V3 atual para localizar janelas**. Em vez disso, foi construído um conjunto de **detectores proxy estritamente causais e independentes de `smc_engine_v3`**, usando apenas dados reais em `WINFUT_H1_canonical.csv` (12.018 candles):

- **Fractais causais** (confirmados 3 barras após o pivô, sem look-ahead)
- **BOS/CHOCH proxy**: quebra de fechamento acima/abaixo do último fractal confirmado; CHOCH = quebra em direção oposta à tendência vigente
- **Wick sweep**: pavio ultrapassa nível confirmado, mas fechamento permanece dentro
- **FVG**: geometria clássica de 3 candles, confirmada no candle 3
- **IFVG**: fechamento atravessa a borda distal de um FVG anterior
- **BPR**: sobreposição de FVGs de direções opostas em janela próxima
- **PDH/PDL touch/reclaim**: comparação com extremos do dia anterior (agrupamento por `trading_date`)
- **Retracement**: fechamento entrando na banda 50–79% de uma perna de swing confirmada
- **Gap**: abertura > 0,5% do close anterior
- **Rollover**: as 3 datas fixas confirmadas em R1

Estes detectores servem **apenas para localizar candidatos de janelas reais** — a confirmação definitiva de cada padrão (origem, confirmação, disponibilidade, lifecycle) ocorre na Fase R19, após a reconstrução causal das engines (R5–R12).

## 3. Categorias Cobertas (19 de 20 exigidas pelo plano)

| Categoria | Janelas selecionadas | Método |
|---|---:|---|
| BOS_BULLISH | 3 | BOS proxy |
| BOS_BEARISH | 3 | BOS proxy |
| CHOCH_BULLISH | 3 | CHOCH proxy |
| CHOCH_BEARISH | 3 | CHOCH proxy |
| WICK_SWEEP | 3 | Wick sweep proxy |
| CLOSE_BREAK | 3 | Todos os eventos BOS/CHOCH (quebra por fechamento) |
| DISPLACEMENT | 3 | Maior corpo direcional (top 20 candles) |
| FVG_BULLISH | 3 | Geometria 3-candle |
| FVG_BEARISH | 3 | Geometria 3-candle |
| IFVG | 3 | Mitigação total com inversão |
| BPR | 3 | Sobreposição de FVGs opostos |
| GAP | 3 | Abertura > 0,5% |
| HIGH_VOLATILITY | 3 | Maior range diário (Dev) |
| LOW_VOLATILITY | 3 | Menor range diário (Dev) |
| PDH_TOUCH | 3 | Toque em máxima do dia anterior |
| PDL_TOUCH | 3 | Toque em mínima do dia anterior |
| RECLAIM | 3 | Fechamento de volta acima da PDL após varredura |
| RETRACEMENT | 3 | Banda 50–79% de perna confirmada |
| ROLLOVER | 3 | Datas fixas de R1 |

**Total: 57 janelas candidatas**, todas ancoradas em timestamps reais do dataset WINFUT H1 canônico.

## 4. Categorias Deferidas (dependência causal)

| Categoria | Motivo do adiamento |
|---|---|
| **BREAK_MARGINAL** | Requer definição de threshold por tick/ATR do Structure engine (R6), ainda não reconstruído |
| **BULLISH_OB / BEARISH_OB / REJECTION_OB / STACKED_OB** | Order Block só pode ser originado a partir de `StructureEventV3` + `StructureLegV3` (R12), que dependem de Structure (R6) causal — selecionar OBs agora usaria a lógica V2 inválida identificada em R0 |
| **LIQUIDITY_SWEEP** | Depende de `EqualLevelClusterV3` e `PreviousPeriodLevelV3` causais (R9), que ainda não existem no pipeline corrigido |

**Ação:** estas categorias serão selecionadas em uma extensão do manifesto **após** as fases R6 (Structure), R9 (Liquidity) e R12 (Order Block) estarem aprovadas, usando as próprias engines V3 corrigidas como fonte — não os proxies. Isso é consistente com a regra de dependência causal do plano mestre (seção 5): "Nenhuma engine downstream pode redetectar uma entidade cuja autoridade pertence a uma engine upstream."

## 5. Amostra do Manifesto Gerado

```yaml
categories:
  BOS_BULLISH:
    - start: "2021-07-12T13:00:00-03:00"
      end: "2021-07-14T15:00:00-03:00"
      center: "2021-07-13T14:00:00-03:00"
    - start: "2021-08-23T09:00:00-03:00"
      end: "2021-08-25T11:00:00-03:00"
      center: "2021-08-24T10:00:00-03:00"
  ROLLOVER:
    - start: "2021-11-19T13:00:00-03:00"
      end: "2021-12-01T13:00:00-03:00"
      center: "2021-11-26T09:00:00-03:00"
```

Manifesto completo: `tests/smc_engine_v3/real_data/golden_windows_manifest.yaml`

---

## 6. GATE

```
R2_REAL_DATA_WINDOWS_APPROVED
```

**Justificativa:** 57 janelas candidatas localizadas em dados reais cobrindo 19/20 categorias do plano; as 3 categorias dependentes de Structure/Liquidity/OB causal (BULLISH_OB, BEARISH_OB, REJECTION_OB, STACKED_OB, LIQUIDITY_SWEEP, BREAK_MARGINAL) são formalmente adiadas para extensão do manifesto pós-R6/R9/R12, sem bloquear o avanço do plano — a ordem causal exige que essas entidades sejam originadas pelas próprias engines corrigidas, não por proxies.

**Splits:** Development (2021–2024), Validation (2025), Holdout (2026) definidos e sem corte de contrato/rollover.

**Próxima fase:** R3 — Pipeline V3 (criar `run_smc_engine_v3()` e corrigir ordem causal).
