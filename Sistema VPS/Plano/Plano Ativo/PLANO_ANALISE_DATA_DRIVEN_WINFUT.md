# Plano: Análise Data-Driven de Zonas WINFUT → Backtest Inteligente

**Status**: 🔄 EM EXECUÇÃO
**Data**: 2026-06-25
**Motivo**: Walk-forward 400 trials não atingiu meta (≥2 trades/dia, PF>2). Nova abordagem: analisar dados reais antes de otimizar.

---

## Contexto

O walk-forward com 400 trials mostrou que **nenhum trial atingiu ≥2 trades/dia com PF>2**. O melhor foi 1.9 trades/dia com PF=1.19. A abordagem de otimizar parâmetros no escuro não está funcionando.

**Nova abordagem:** Analisar os dados reais do banco para entender o que o mercado faz, depois construir um backtest baseado em evidências.

---

## Dados Disponíveis no Banco

### Zonas SMC V2 (shadow tables, WINFUT)

| Tipo | 5min | 15min | 2min | 60min | 4h | 1d | Total |
|------|------|-------|------|-------|-----|-----|-------|
| FVG | 25.985 | 9.511 | 63.109 | 2.966 | 1.229 | 975 | **103.775** |
| Order Blocks | 5.945 | 2.039 | 15.037 | 571 | 174 | 168 | **24.934** |
| BOS/CHOCH | 6.188 | 2.104 | 15.591 | 596 | 179 | 171 | **24.829** |
| Liquidity | 3.634 | 1.182 | 9.423 | 318 | 100 | 111 | **14.768** |
| Swings | 15.622 | 5.242 | 39.719 | 1.338 | 390 | 402 | **62.713** |

### Velas (market_candles)

| TF | Velas | Período | Indicadores |
|----|-------|---------|-------------|
| 5min | 137.998 | 2021-06 → 2026-06 | EMA20, EMA200, RSI, Volume, ATR |
| 15min | 46.419 | 2021-06 → 2026-06 | iguais |
| 2min | 345.466 | 2021-06 → 2026-06 | iguais |
| 60min | 12.018 | 2021-06 → 2026-06 | iguais |
| 4h | 3.733 | 2021-06 → 2026-06 | iguais |
| 1d | 1.246 | 2021-06 → 2026-06 | iguais |

### Outros dados

- **Wyckoff phases**: tabelas disponíveis
- **Trade backtest results**: 854 trades históricos com outcome_final

---

## FASE A — Script de Análise Exploratória

### Criar: `tools/analyze_winfut_zones.py`

Script que conecta ao MySQL e gera relatório com:

### A1. Taxa de respeito por tipo de zona

Para cada zona bullish:
1. Entry = midpoint da zona
2. Verificar nos candles seguintes: preço tocou midpoint E foi na direção da zona?
3. Medir: % de zonas onde preço reagiu

### A2. Taxa de TP1 por tipo de zona

Para cada zona com entry no midpoint:
1. Entry = midpoint
2. Stop = bottom/top da zona + buffer
3. TP1 = próximo swing high/low ou zona oposta
4. Verificar: TP1 atingido antes do stop?

### A3. Impacto dos indicadores

- Volume acima da média → taxa de TP1
- RSI alinhado → taxa de TP1
- EMA200 alinhada → taxa de TP1
- Sessão ativa (09-18h BRT) → taxa de TP1

### A4. Qual timeframe é mais confiável

Comparar FVG entre TFs: 2min vs 5min vs 15min vs 60min

### A5. Volume do OB como filtro

OBs com ob_volume alto → melhor TP1?

### Output: `docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_ZONAS_WINFUT.md`

---

## FASE B — Backtest Baseado em Evidências

### Criar: `tools/run_evidence_backtest.py`

Baseado nos achados da FASE A:

### B1. Seleção de zonas
Usar apenas zonas com taxa de respeito > 70% e TP1 > 60%

### B2. Entry scoring (0-100)

| Fator | Peso | Fonte |
|-------|------|-------|
| Tipo de zona | 30% | Tabela FASE A |
| Volume | 25% | market_candles |
| EMA alinhada | 20% | ema200 |
| Sessão | 15% | 09-18h BRT |
| HTF | 10% | H4/D1 |

Score mínimo: 60/100

### B3. Gestão de risco
- Entry: midpoint (limit)
- Stop: zona + buffer ATR
- TP1/TP2/TP3: swings e zonas opostas
- Expiry: X velas

### B4. Métricas alvo

| Métrica | Meta |
|---------|------|
| TP1 hit rate | > 65% |
| Trades/dia | ≥ 1.0 |
| PF | > 2.0 |
| E(R) | > 0.2R |

---

## Ordem de Execução

1. Criar `tools/analyze_winfut_zones.py`
2. Rodar → gerar ANALISE_ZONAS_WINFUT.md
3. Analisar achados → decidir filtros
4. Criar `tools/run_evidence_backtest.py`
5. Rodar backtest → validar métricas
6. Iterar se necessário

---

## Arquivos

| Arquivo | Ação |
|---------|------|
| `tools/analyze_winfut_zones.py` | CRIAR |
| `tools/run_evidence_backtest.py` | CRIAR |
| `docs_geral/Sistema VPS/Relatorios/Backtest/ANALISE_ZONAS_WINFUT.md` | CRIAR |

---

## Verificação

- [ ] Script de análise roda sem erro
- [ ] Relatório tem dados reais
- [ ] Backtest roda com trades reais
- [ ] Métricas: TP1>60%, T/dia≥1.0, PF>2.0
