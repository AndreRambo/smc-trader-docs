# RELATÓRIO R18 — MÉTRICAS DE SANIDADE

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `tools/smc_v3_validation/generate_smc_v3_validation_report.py`

---

## 1. Ferramenta Criada

Calcula, por engine, contagem total/ativa/terminal e distribuição por `structure_type`, além dos alarmes obrigatórios do plano:
- `structure_events_per_swing > 1`
- 100% dos OBs mitigados
- níveis com múltiplos BOS/CHOCH (duplicidade de quebra estrutural)

## 2. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

| Engine | Total | Ativas | Terminais | Distribuição |
|---|---:|---:|---:|---|
| sessions | 4.984 | 4.984 | 0 | OPEN=2.492 CLOSE=2.492 |
| swings | 1.519 | 1.519 | 0 | HIGH=767 LOW=752 |
| bos_choch | 971 | 971 | 0 | WICK_SWEEP_HIGH=162 BOS_BEARISH=245 WICK_SWEEP_LOW=174 CHOCH_BULLISH=100 CHOCH_BEARISH=87 BOS_BULLISH=203 |
| previous_high_low | 2.490 | 2.490 | 0 | PDH=1.245 PDL=1.245 |
| retracements | 5.212 | 5.212 | 0 | FIBONACCI_ANCHOR=772 FIBO_LEVEL=3.700 DEALING_RANGE=740 |
| liquidity | 1.406 | 56 | 1.350 | BUYSIDE=707 SELLSIDE=699 |
| fvg | 4.400 | 1.483 | 2.917 | FVG_BEARISH=1.478 FVG_BULLISH=1.488 IFVG_BEARISH=733 IFVG_BULLISH=701 |
| bpr | 1.370 | 15 | 1.355 | BULLISH=668 BEARISH=702 |
| order_blocks | 1.761 | 28 | 1.733 | OB_BULLISH=928 OB_BEARISH=833 |

**Nota metodológica:** "ativa"/"terminal" aqui é definido estritamente por presença de evento `MITIGATED`/`SWEPT` — componentes sem esse tipo de evento terminal (sessions, swings, bos_choch, previous_high_low, retracements) aparecem sempre como 100% "ativos" neste critério simplificado; isso não significa ausência de lifecycle real (swings têm SUPERSEDED, PDH/PDL têm RECLAIM, etc. — já auditados em R13 com zero violações).

## 3. Alarmes Verificados

```
structure_events_per_swing: 0,639  (limite: <= 1,0)  -> OK
100% dos OBs mitigados: 1.733/1.761 = 98,4%  (não é 100%, há 28 OBs ainda ativos)  -> OK
duplicate_structure_breaks (múltiplos BOS no mesmo nível): 0  -> OK

ALARMS: 0
errors: []
```

## 4. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (276.7s)
```

Nenhum código de engine alterado nesta fase — apenas a ferramenta de métricas.

---

## 5. GATE

```
R18_SANITY_METRICS_PASS
```

**Justificativa:**
- Zero alarmes disparados sobre 23.113+ estruturas reais de 5 anos de dados
- `structure_events_per_swing = 0,639`, bem abaixo do limite de 1,0 (reconfirma a correção do R4/R6)
- Nenhum nível com múltiplas quebras BOS/CHOCH (reconfirma R6)
- OBs não estão 100% mitigados (28 zonas ativas remanescentes — comportamento saudável, não uma engine "sempre mitigando tudo")
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R19 — Golden Windows (revisão manual/checklist).
