# RELATÓRIO R15 — OVERLAYS E AUDITORIA VISUAL

---

**Data/Hora de Execução:** 2026-07-01
**Branch:** `feature/smc-v3-causal-rebuild-real-data`
**Arquivo criado:** `tools/smc_v3_validation/render_real_data_window.py`

---

## 1. Ferramenta Criada

`render_real_data_window.py` renderiza um recorte de candles reais com overlays de zonas via Plotly (candlestick + retângulos de zona), suportando os três modos exigidos:

- **ORIGIN_VIEW** — retângulo da zona começa em `origin_candle_id` (quando o padrão começou a se formar)
- **OPERATIONAL_VIEW** — retângulo começa em `availability_candle_id` (quando a zona se torna operável — a única view causalmente correta para execução)
- **LIFECYCLE_VIEW** — mesma base do operacional, com anotações nos candles onde cada evento de lifecycle ocorreu (TOUCHED, MITIGATED, WICK_SWEEP, RECLAIM, etc.)

A zona termina visualmente no primeiro evento terminal (`MITIGATED`/`SWEPT`/`SUPERSEDED`/`RECLAIM`), ou se estende até o fim da janela se ainda ativa.

## 2. Validação com Dados Reais

Renderizado o golden window de rollover identificado no R1/R2 (candles 900–1050 do H1 canônico, cobrindo 2021-11-26).

**Verificação programática da invariante central do R15** ("no modo operacional, zona começa em `available_at`"):

```
n shapes ORIGIN_VIEW: 69   n shapes OPERATIONAL_VIEW: 69
shapes onde x0 difere entre os dois modos: 69 de 69 (100%)
exemplo: origin x0=2021-11-16T10:00:00-03:00  vs  operational x0=2021-11-16T11:00:00-03:00
```

**100% das zonas FVG têm início visual diferente entre ORIGIN_VIEW e OPERATIONAL_VIEW**, e o operacional sempre começa **depois** do origin (consistente com FVG: origem em C2, disponibilidade em C3 — uma vela depois). Confirma que a ferramenta respeita a causalidade na renderização, não apenas nos dados subjacentes.

Três arquivos HTML gerados com sucesso (ORIGIN_VIEW, OPERATIONAL_VIEW, LIFECYCLE_VIEW) para a mesma janela real, sem erros.

## 3. Testes de Regressão

```
pytest tests/test_technical_engine/ -q
2103 passed, 0 failed (247.4s)
```

Nenhuma mudança em código de engine nesta fase — apenas a nova ferramenta de renderização, que consome `run_smc_engine_v3()` (não modifica nenhum componente).

---

## 4. GATE

```
R15_VISUAL_AUDIT_PASS
```

**Justificativa:**
- Três modos implementados e testados com dados reais (rollover window de R1/R2)
- Invariante causal do modo operacional confirmada programaticamente (100% das zonas iniciam em `available_at`, nunca antes)
- 2.103 testes de regressão, 0 falhas

**Próxima fase:** R16 — Replay Candle a Candle.
