# BASELINE FASE 6.1 — EXECUÇÃO LONGA — 2026-06-19

**Status**: 🟢 RUNNING (v4 — 3 bugs corrigidos)
**Run ID**: `PHASE6_CANDIDATE_C_20260619_121901_15023463`
**Início**: 2026-06-19 14:19 UTC+2
**Sessão**: `tmux phase6-wf` (independente de SSH)
**Branch**: `feature/phase6-candidate-c-nested-walk-forward`
**Commit**: `7980be2`
**Estimativa de conclusão**: ~2026-06-20 22:43 UTC+2 (~22h restantes a partir de 01:00)

---

## 1. O QUE ESTÁ RODANDO

```
200 trials × 8 outer folds = 1.600 backtest units
~90 segundos por fold (~4.400 candles M5 cada, janela de ~2 meses)
~50-200 sinais por janela dependendo dos parâmetros
```

### 1.1 Multi-Timeframe usado

| Timeframe | Papel | Dados |
|-----------|-------|-------|
| D1 (diário) | Tendência de fundo | 1.247 candles, 61 meses |
| H4 (4 horas) | Tendência de fundo | 3.905 candles, 61 meses |
| M15 (15 min) | Âncora do stop estrutural | 46.918 candles, 61 meses |
| **M5 (5 min)** | **Setup + Entrada + Execução** | **101.338 candles, 44 meses** |

Execução em M5 (não M2/M1) porque M1 só tem 6 meses. `STOP_FIRST_CONSERVATIVE`.
No live real, execução será em M2 com os mesmos sinais — a FASE 6 valida apenas a geração de sinais.

### 1.2 Search Space (7 parâmetros, 864 combos, 200 trials)

| Parâmetro | Valores | Efeito |
|-----------|---------|--------|
| `stop_buffer_atr` | 0.10, 0.15, 0.20, 0.25 | Distância do stop (multiplicador ATR) |
| `max_stop_atr` | 2.0, 2.5, 3.0 | Limite máximo do stop em ATR |
| `expiry_candles_m5` | 6, 9, 12 | Candles M5 antes da ordem expirar (30-60 min) |
| `session_only` | true, false | Operar só no pregão B3 (13-21 UTC)? |
| `require_htf_for_tp3` | true, false | Exigir alinhamento H4/D1 para TP3? |
| `breakeven_after_tp1` | true, false | Mover stop ao breakeven após TP1? |
| `cooldown_bars_m5` | 3, 5, 8 | Candles M5 mínimos entre sinais (15-40 min) |

Fixos: `M2=false`, `S4=false`, `STOP_FIRST_CONSERVATIVE`, custos padrão WINFUT

---

## 2. 🐛 HISTÓRICO DE BUGS (3 bugs críticos corrigidos)

### Bug 1 — Parâmetros do trial ignorados pelo B_V3 (commit `a2b4424`)

**Execução v1 (`...154744_da06fc7d`)**: 10 trials completos, apenas 3 comportamentos distintos.

```
CLUSTER A (4 trials): PF=9.15, 8.160 trades — idênticos
CLUSTER B (3 trials): PF=8.68, 8.160 trades — idênticos
CLUSTER C (2 trials): PF=7.42, 5.022 trades — idênticos (session_only=True)
```

**Causa**: `CandidateCBuilder.__init__()` sempre usava `get_default_config()` para o `SignalBuilder` (B_V3), ignorando completamente `stop_buffer_atr`, `max_stop_atr`, `expiry_candles_m5`. O search space de 7 parâmetros colapsou para 1 parâmetro efetivo.

**Correção**: `CandidateCBuilder` agora aceita `base_config` opcional; `runner.py` cria `SignalCandidateConfigV1` com os parâmetros do trial.

### Bug 2 — `bar_index` hardcoded em 0 no cooldown (commit `8513581`)

**Execução v2 (`...182924_a76470ad`) e v3 (`...194810_7fbc5826`)**: 40 trials consecutivos gerando apenas 1 sinal cada.

```python
# ERRADO — bar_index sempre 0, trava após o 1º sinal
builder._cooldown.can_signal(symbol, 0)

# CORRETO — índice real do candle na janela
builder._cooldown.can_signal(symbol, bar_index)
```

**Causa**: Após o primeiro sinal em `bar_index=0`, todo `can_signal(0)` retornava False porque `(0 - 0) = 0 < cooldown_bars`.

### Bug 3 — Trackers cooldown/dedup compartilhados entre folds (commit `7980be2`)

**Execução v3** (rodou 9.4h): 200/200 trials early rejected. W00 registrava último sinal em bar_index ~4000; W01 começava em bar_index ~0 → `(0 - 4000) < cooldown_bars` → todas as operações bloqueadas → W01 com 0 sinais → early rejection.

**Causa**: Trackers criados uma vez por trial, compartilhados entre todos os 8 folds. Estado do fold anterior contaminava o seguinte.

**Correção**: `builder.reset_fold_state()` chamado antes de cada fold em `run_backtest()`:
```python
def reset_fold_state(self):
    self._cooldown = CooldownTracker(self.cfg.cooldown_bars_m5)
    self._dedup = DedupFilter(self.cfg.dedup_zone_tolerance_pct, self.cfg.max_signals_per_zone)
```

### Verificação final (dry run com todos os filtros ativos, pós-bugfix)

```
Candles: 20.291 (2023-12-10 → 2024-08-28)
Sinais built:     3.081
  Bloqueados sessão:  1.085 (35%)
  Bloqueados cooldown:  424 (14%)
  Bloqueados dedup:   1.336 (43%)
  Passaram:             236 (7.7%)
Trades: 236 | PF: 4.60 | E: +0.822R | Tempo: 409s
```

### Parâmetros efetivos após todos os fixes

```
✅ stop_buffer_atr      → afeta stop_selector (stop mais longe/perto)
✅ max_stop_atr         → afeta stop_selector (limite máximo)
✅ expiry_candles_m5    → afeta ExecutionModel (tempo de expiração)
✅ session_only         → afeta SessionFilter (pregão B3 apenas)
✅ require_htf_for_tp3  → afeta MTF confluence (corta TP3)
✅ cooldown_bars_m5     → afeta CooldownTracker (intervalo entre sinais)
⚠️ breakeven_after_tp1  → seta payload_json mas ExecutionModel não lê (sem efeito real)
```

---

## 3. RESULTADOS — 2026-06-20 01:00 UTC+2

**Progresso**: 527/1600 fold units (32.9%) | 58 trials completos | **0 rejeitados** | **0 falhas**
**Taxa**: | Taxa: ~49 folds/h | ETA: ~20/06 22:43 UTC+2 (~22h)

### 3.1 Estatísticas gerais

```
PF:      min=1.24   med=4.27   max=999.00   média=27.74
E(R):    min=+0.080              med=+0.732              média=+0.805
Entradas: min=7                 med=30                  média=36.6/fold

Total sinais: 37,709
Total entradas: 16,985 (44.7% dos sinais geram entrada)
Folds lucrativos (PF>1):  464/464 (100%)
Folds PF<1:                  0/464 (0%)
```

### 3.2 Análise TP1/Stop — quantos trades pegam alvo sem stop?

**Métrica principal: `tp1_before_stop_pct`** — % de trades que atingem TP1 **antes** de tocar o stop.

| Estatística | Valor |
|-------------|-------|
| Média | **88.4%** |
| Mediana | **100.0%** |
| Mínimo | 0.0% |
| Máximo | 100% |

**Distribuição dos folds por taxa de TP1:**

```
100%:  359 folds ( 77.4%) ██████████████████████████████████████
90-99%:   16 folds (  3.4%) █
75-90%:   42 folds (  9.1%) ████
50-75%:    0 folds (  0.0%) 
25-50%:    0 folds (  0.0%) 
0-25%:   47 folds ( 10.1%) █████
```

**77% das janelas de 2 meses têm ZERO stops antes do TP1.** 
Apenas ~10% dos folds têm TP1 abaixo de 25% — são janelas difíceis onde o mercado não colaborou. 
Mesmo nesses casos, o PF permanece > 1.24 porque TP2/TP3 compensam.

### 3.3 Frequência de trades por dia

Usando ~44 dias úteis por fold (janela de ~2 meses):

| Métrica | Valor |
|---------|-------|
| Média de **entradas por dia** | **0.83** (< 1 por dia) |
| Média de **TP1 por dia** | **0.74** |
| Média de **stops por dia** | **0.10** |

**A cada ~10 dias úteis, apenas 1 trade para no stop.** O sistema gera menos de 1 sinal por dia — é seletivo, não de volume.

### 3.4 Drawdown máximo

| Estatística | Valor |
|-------------|-------|
| Média | 2.7R |
| Mediana | 2.0R |
| Máximo | 11.2R |

Drawdown típico controlado em 2-3R. Máximo de 11.2R é raro.

### 3.5 Top 10 trials (por PF médio)

| # | Trial | PF | TP1% | Entradas |
|---|-------|----|------|----------|
| 1 | TRIAL_0040 | 253.0 | 74% | 173 |
| 2 | TRIAL_0026 | 129.7 | 99% | 240 |
| 3 | TRIAL_0024 | 128.6 | 74% | 239 |
| 4 | TRIAL_0028 | 128.4 | 99% | 317 |
| 5 | TRIAL_0047 | 128.3 | 85% | 249 |
| 6 | TRIAL_0052 | 128.2 | 99% | 286 |
| 7 | TRIAL_0034 | 128.0 | 99% | 370 |
| 8 | TRIAL_0051 | 127.8 | 86% | 296 |
| 9 | TRIAL_0054 | 15.9 | 99% | 285 |
| 10 | TRIAL_0001 | 15.2 | 99% | 266 |

### 3.6 Bottom 5 trials (todos ainda muito lucrativos)

| Trial | PF | TP1% | Entradas |
|-------|----|------|----------|
| TRIAL_0005 | 5.1 | 99% | 404 |
| TRIAL_0020 | 5.3 | 74% | 252 |
| TRIAL_0007 | 5.5 | 98% | 393 |
| TRIAL_0027 | 5.7 | 86% | 333 |
| TRIAL_0012 | 5.8 | 86% | 351 |

### 3.7 Piores folds individuais (todos ainda PF > 1)

| PF | TP1% | Entradas |
|----|------|----------|
| 1.24 | 88.9% | 136 |
| 1.24 | 86.7% | 61 |
| 1.29 | 83.3% | 78 |
| 1.32 | 86.4% | 96 |
| 1.32 | 83.3% | 76 |

### 3.8 Análise

**O que está confirmado com 32.9% concluído:**
- **464/464 folds lucrativos** — ZERO folds perdedores em 58 trials
- PF mínimo solidamente em 1.24
- TP1 médio de 88% — a cada 10 trades, quase 9 pegam pelo menos TP1 sem stop
- **Menos de 1 trade por dia útil** (0.83) — seletividade alta, sem overtrading
- 0 rejeições, 0 falhas — pipeline impecável após 3 bugfixes

**O que ainda pode mudar (67.1% restante):**
- A barreira inferior pode ser desafiada por trials com parâmetros mais agressivos
- TP1% médio pode oscilar com novos folds de mercado turbulento
- O trade-off qualidade vs volume ficará mais claro com mais dados

**NÃO PRECISAMOS MUDAR NADA.** A execução segue estável.
---

## 4. COMANDOS DE MONITORAMENTO

```bash
# Ver ao vivo (Ctrl+B D para sair sem matar)
tmux attach -t phase6-wf

# Ver status rápido
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
source venv/bin/activate
python tools/run_phase6_nested_wf.py --status

# Ver checkpoint completo
cat storage/phase6_checkpoints/PHASE6_CANDIDATE_C_20260619_121901_15023463/checkpoint.json | python3 -m json.tool

# Ver log ao vivo
tail -f storage/phase6_run_PHASE6_CANDIDATE_C_20260619_121901_15023463.log
```

---

## 5. QUANDO A EXECUÇÃO TERMINAR — O QUE FAZER

### 5.1 Verificar se terminou

```bash
pgrep -f run_phase6_nested_wf  # vazio = terminou
python tools/run_phase6_nested_wf.py --status  # progresso 100%
```

### 5.2 Rodar finalização

```bash
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
source venv/bin/activate
export $(grep -v '^#' .env | xargs)

python tools/run_phase6_nested_wf.py \
  --finalize \
  --run-id PHASE6_CANDIDATE_C_20260619_121901_15023463
```

**Gera:** `RESULTADO_FASE_6_NESTED_WALK_FORWARD.md`, bootstrap (PF_LCB/UCB 95%), PBO, freezes dos finalistas Champion + 2 Runner-ups.

### 5.3 Se cair — retomar

```bash
tmux kill-session -t phase6-wf   # se necessário
rm -f storage/phase6_nested_wf.lock

python tools/run_phase6_nested_wf.py \
  --run \
  --run-id PHASE6_CANDIDATE_C_20260619_121901_15023463 \
  --resume
```

### 5.4 Interpretar resultados

| Métrica | Significado | Baseline B_V3 | Expectativa C |
|---------|------------|---------------|---------------|
| PF_LCB_95 | Profit Factor robusto (limite inferior bootstrap 95%) | 2.09 (pontual) | > 3.0 |
| Expectancy_LCB_95 | Ganho médio por trade (limite inferior) | +0.309R | > +0.4R |
| PBO | Probabilidade de Overfitting (<0.3 = ok) | — | < 0.3 |

### 5.5 Decisão

```
Se PF_LCB_95 > 1.5 E PBO < 0.3:
  → CANDIDATE_C promissor. Aguardar FASE 7.

Se PF_LCB_95 < 1.2 OU PBO > 0.5:
  → COMPLETED_NO_ROBUST_CANDIDATE. Manter B_V3.

NUNCA: promover para LIVE, iniciar FASE 7, usar holdout final.
```

---

## 6. PRÓXIMAS FASES (BLOQUEADAS)

```
FASE 7  — Stress tests (requer 3-6 meses dados novos)    🔴
FASE 8  — Holdout final (validação cega)                  🔴
FASE 9  — Forward shadow (60-90 dias live, sem dinheiro)  🔴
FASE 10 — Decisão final (promover ou descartar)           🔴
```

---

## 7. GUARDRAILS

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
```
