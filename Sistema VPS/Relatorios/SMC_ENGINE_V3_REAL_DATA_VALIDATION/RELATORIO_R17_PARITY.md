# RELATÓRIO R17 — PARIDADE

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `tools/smc_v3_validation/compare_batch_replay.py`

---

## 1. Ferramenta Criada

`compare_batch_replay.py` compara **fingerprint completo** (não apenas presença de IDs) de todas as estruturas produzidas por `BatchAdapter` vs `ReplayAdapter`: `structure_type`, `direction`, `origin_candle_id`, `confirmation_candle_id`, `availability_candle_id`, `confirmed_at`, `available_at`, `top_price`, `bottom_price`, `midpoint_price`, `level_price` e `payload` completo — hash SHA-256 de todos esses campos juntos por `structure_id`.

## 2. Comparações Exigidas pelo Plano — Cobertura

| Comparação exigida | Como foi validada |
|---|---|
| batch vs replay | `compare_batch_replay.py` (nova ferramenta desta fase) — fingerprint campo a campo |
| batch vs chunk | Já validado no R16 (`CHUNK_REPLAY`, `diff_count=0` no dataset completo) |
| replay vs resume | Já validado no R16 (`CHECKPOINT_RESUME`, hash de estado final idêntico) |
| batch vs persisted replay | Já validado no R16 (`PERSISTED_REPLAY`, hash de estado final idêntico via SQLite real) |
| single process vs restart | Já validado no R14 (restart idempotente: reprocessar os mesmos 2.000 candles reais não duplica nenhuma linha, `state_hash` idêntico) |

## 3. Resultado com Dados Reais (12.018 candles H1, 2021–2026)

```
batch vs replay: parity=True  common=24.113  mismatches=0

GATE: R17_PARITY_PASS
```

**Zero mismatches em qualquer campo** entre os dois adaptadores, sobre a totalidade das 24.113 estruturas de 5 anos de dados reais.

## 4. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (264.4s)
```

---

## 5. GATE

```
R17_PARITY_PASS
```

**Justificativa:**
- Todas as 5 comparações de paridade exigidas pelo plano têm evidência real, seja desta fase (`compare_batch_replay.py`) ou reaproveitada de R14/R16 (mesma fonte de verdade: motor incremental causal por construção)
- Zero divergência de campo em 24.113 estruturas reais entre `BatchAdapter` e `ReplayAdapter`
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R18 — Métricas de Sanidade.
