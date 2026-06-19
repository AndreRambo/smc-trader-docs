# Backtest S26 — XAUUSDm Calibrado — 2026-06-17

## Configuração

| Item | Valor |
|------|-------|
| Símbolo | XAUUSDm |
| asset_id | 5 |
| Período | 2025-12-02 → 2026-06-02 (6 meses) |
| Candles M5 | 36.786 |
| Candles M1 | 72.203 |
| Config | `get_asset_config("XAUUSDm")` — XAUUSDM_M5 |
| OB thresholds | (2.0, 6.0, 15.0) USD |
| Session | 8h-17h UTC (London+NY) |
| BPR max_size | 8.0 USD |

## Resultados

| Métrica | Valor |
|---------|-------|
| Planos ativos | 3.490 / 36.786 (9.5%) |
| Sinais gerados | 2.158 |
| Entry hit rate | **80.9%** (1.745/2.158) |
| Stop hit | 1.085 (50.3%) |
| TP1 hit rate | **51.7%** (1.115/2.158) |
| Win rate (entrou → TP1) | **63.9%** (1.115/1.745) |

## Distribuição

| Proximidade | Qtd |
|------------|-----|
| OBSERVANDO | 1.076 |
| PROXIMO | 842 |
| IMINENTE | 146 |
| NA_ZONA | 94 |

| Direção | Qtd | % |
|---------|-----|---|
| ALTISTA | 1.025 | 47.5% |
| BAIXISTA | 1.133 | 52.5% |

## Comparativo S21 vs S26

| Métrica | S21 Baseline (2024) | S26 Calibrado (2025-2026) |
|---------|---------------------|---------------------------|
| asset_id | 2 | 5 |
| Período | 2024 (~79K candles) | Dez 2025 - Jun 2026 |
| Win rate | 48.6% (unique_setups) | 51.7% (TP1/total) |
| ALTISTA % | 87.3% | 47.5% |
| BAIXISTA % | 48.9% | 52.5% |
| Entry hit | 60.6% | 80.9% |

## Notas

1. **Datasets diferentes**: S21 usou asset_id=2 (2024, bull market forte). S26 usa asset_id=5
   (dados recentes Dec 2025-Jun 2026). A comparação direta não é apples-to-apples.

2. **ALTISTA imbalance corrigido**: S21 mostrava 87.3% ALTISTA vs 48.9% BAIXISTA, evidenciando
   trend bias não filtrado. S26 mostra distribuição balanceada (47.5%/52.5%).

3. **Backtest usa planner simplificado (EMA)**: O impacto total da calibração OB quality
   (session scoring UTC-based, size thresholds corretos) está no pipeline LIVE (dispatcher.py),
   não visível completamente neste backtest que usa EMA-based synthetic plans.

4. **Impacto real da calibração**: Visible em produção após restart do serviço
   `smc-candle-event-processor`. Os OBs do Gold formados 8h-13h UTC agora recebem
   score 83 (PREGAO) em vez de 56 (FORA).
