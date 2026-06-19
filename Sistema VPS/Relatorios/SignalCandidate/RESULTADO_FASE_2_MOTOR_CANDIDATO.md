# RESULTADO FASE 2 — MOTOR DE SINAIS CANDIDATO — 2026-06-17

**Status**: ✅ CONCLUÍDO  
**Testes**: 35/35 passando (Fase 1: 23 + Fase 2: 12 novos)

---

## Entregas

### Arquivos criados (6)

| Arquivo | Descrição | Linhas |
|---------|-----------|--------|
| `repositories.py` | SMCStateRepository — dataloader com anti-lookahead (load_snapshot, load_candles_ending_at) | 360 |
| `setup_detector.py` | SetupDetector — S1-S5 com HTF bias | 290 |
| `entry_selector.py` | EntrySelector — 6 tipos de entrada (ZONE_EDGE, MIDPOINT, FVG_50, MSS_RETEST, CONFIRMATION, MARKET) | 180 |
| `stop_selector.py` | StopSelector — ancora estrutural + buffer conforme documento | 210 |
| `target_selector.py` | TargetSelector — TP1/TP2/TP3 com barreiras, monotonicidade, R caps | 290 |
| `geometry_validator.py` | GeometryValidator — valida geometria, R:R, monotonicidade | 90 |
| `signal_builder.py` | SignalBuilder — orquestrador determinístico | 150 |

### Testes novos (12)

| Teste | O que cobre |
|-------|------------|
| `test_s3_fvg_detection` | Detecção de FVG + estrutura |
| `test_s1_sweep_mss` | Detecção de sweep + MSS + reteste |
| `test_detector_with_empty_data` | SetupDetector com dados vazios |
| `test_long_stop_from_protected_swing` | Stop LONG via swing protegido |
| `test_stop_blocked_on_bad_geometry` | Stop bloqueado por geometria |
| `test_long_targets_pro_trend` | Alvos pró-tendência LONG |
| `test_short_targets_counter_trend` | Alvos contra-tendência SHORT |
| `test_valid_signal_passes` | Validador aprova sinal correto |
| `test_bad_geometry_blocked` | Validador bloqueia geometria ruim |
| `test_rr_too_low_blocked` | Validador bloqueia R:R < mínimo |
| `test_zone_edge_long` | Entrada ZONE_EDGE em OB |
| `test_market_entry` | Entrada a mercado |

---

## Integração verificada

Teste com dados reais WINFUT (Junho 2026, 15 dias, 16h UTC):

- **12 sinais** detectados em 15 dias (~0.8/dia)
- Setups ativos: S1, S2, S3, S5
- S4 (Breaker): não disparou — detector precisa de tuning
- Sinais com entrada, stop, TP1 e R:R válidos
- Anti-lookahead: candles carregados corretamente até `up_to_time`

Exemplo de sinal:
```
Jun 11 | S2 LONG  PRO_TREND  e=170560 s=170125 tp1=171205 rr=1.48
```

---

## Setups (status)

| Setup | Status | Notas |
|-------|--------|-------|
| S1 Sweep+MSS+Reteste | ✅ Funcionando | 2 sinais em 15 dias |
| S2 OB Continuação | ✅ Funcionando | 3 sinais |
| S3 FVG Estrutura | ✅ Funcionando | 3 sinais |
| S4 Breaker Block | ⚠️ Não disparou | Precisa tuning na Fase 4 |
| S5 Protected Swing | ✅ Funcionando | 4 sinais |

---

## Correção FASE 4 — Multi-Timeframe Stops

**Problema identificado**: Stops estavam ancorados no M5 (microestrutura), resultando em 91% stop-out.
**Correção**: `stop_selector.py` foi reescrito para priorizar M15 (estrutura operacional) como âncora primária.
Isso reduziu stop-out de 91% → 70% e triplicou a taxa de sobrevivência.

## Guardrails (todos ativos)

```
shadow_only=True ✅
candidate_only=True ✅
anti_lookahead=True ✅  (candles filtrados por up_to_time)
deterministico=True ✅   (sem LLM, sem random)
can_promote_trade=False ✅
production_signal_emission=False ✅
```

---

## Pendências para Fase 3

- Simulador de backtest (dataset builder, event simulator, execution model, costs, ambiguity policy)
- O tuning do detector S4 será feito na Fase 4 (exploratório)

## Próxima Fase

**FASE 3 — SIMULADOR DE BACKTEST**
