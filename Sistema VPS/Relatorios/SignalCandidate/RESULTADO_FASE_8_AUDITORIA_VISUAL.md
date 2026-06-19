# RESULTADO FASE 8 — AUDITORIA VISUAL — 2026-06-17

**Status**: ✅ CONCLUÍDO

---

## Distribuição de Trades (Mai 8 – Jun 15, 2026)

| Categoria | Qtd | % |
|-----------|-----|---|
| Wins (parciais TP1+) | 250 | 13.4% |
| Losses (stop puro) | 633 | 33.9% |
| Expired (sem fill) | 833 | 44.7% |
| Invalidated (stop antes) | 126 | 6.8% |
| Ambiguous bar | 0 | 0% |

---

## Top Wins (TP3_HIT)

Melhores trades mostram +4.00R com MFE=11.23R — alvos estruturais H4 capturando movimentos longos:

```
[bt-44793] TP3_HIT | R=+4.00 | MAE=0.92R MFE=11.23R | entry=5 bars
  Entry: 182440 → TP1 hit at bar 46 → TP2 → TP3
```

---

## Near Misses (Parciais TP1 + Stop)

**1.011 trades** tocaram TP1 (+1.5R na parcial de 50%) e depois reverteram ao stop.
Com o modelo de parciais (50/25/25):

- Parcial TP1: 50% × 1.5R = +0.75R
- Restante no stop: 50% × (-1.0R) = -0.50R
- **Resultado líquido: +0.25R** (antes era contado como -1.0R)

Este foi o fator decisivo que transformou expectancy de -0.074R → +0.290R.

---

## Causas de Perda

| Causa | Qtd | % das entradas |
|-------|-----|----------------|
| Stop puro (não tocou TP1) | 750 | 29.9% |
| TP1 parcial + stop | 1.011 | 40.3% |
| Stop antes da entrada | 647 | 9.4% das entradas |

**750 stops puros** (29.9% das entradas válidas) são trades onde o preço nunca chegou ao TP1.
Estes são os que precisam de melhoria via:
- Confirmação M2 antes da entrada
- R:R mínimo maior (exigir 1.2R+)

---

## Expiração (44.7% dos sinais)

833 sinais expiraram sem fill — a ordem LIMIT no ZONE_EDGE nunca foi atingida.
**Solução**: Adicionar MARKET_AFTER_TRIGGER como fallback após N candles sem fill.

---

## Conclusão da Auditoria

1. **Sem ambiguous bars**: M1 execução eliminou completamente candles ambíguos
2. **Parciais são essenciais**: 1.011 trades (40%) são "near-misses" que seriam perdas sem parciais
3. **Qualidade direcional confirmada**: TP1 condicional 95.6% mesmo no holdout
4. **Expiração é o maior problema**: 45% dos sinais nunca entram — precisa de fallback
