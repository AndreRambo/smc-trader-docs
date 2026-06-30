# Plano R5A-MTF — Simulação MTF Candle-a-Candle com CSV WINFUT

**Status:** 🟡 Implementado — aguardando execução da verificação (1000 steps)
**Data:** 2026-06-30

---

## Context

**Banco alvo: MySQL local de desenvolvimento** (mesmo `.env` do coletor, mas
rodando localmente). O `--clean` apaga `market_candles WHERE asset_id=1` e
trunca as tabelas `*_shadow` no banco local. O VPS/produção não é afetado.
`PRODUCTION_DATABASE_TOUCHED = false`.

R4 (shadow runtime + rollback) está PASS. Esta fase cria `tools/r5a_mtf_replay.py`,
um script CLI que simula o ciclo real do coletor B3: 6 timeframes processados em
sincronia, com o SMC V2 pipeline + shadow incremental rodando TF-a-TF a cada step M5.

**Objetivo:** verificar que o shadow runtime funciona corretamente em cenário MTF
(múltiplos TFs interleaved), detectar divergências, e emitir relatório com gates.

**Velocidade:** acelerada — sem delays artificiais entre steps. O que é fiel ao
cenário real é a *ordem* de chegada dos candles (M2→M5→M15→H1→H4→D1) e a janela
de dados disponíveis. O delay de 60s entre ciclos do coletor NÃO é simulado.
Estimativa: 1000 steps M5 ≈ 20–50 minutos.

---

## Arquivos alterados

| Arquivo | Tipo | Descrição |
|---|---|---|
| `tools/r5a_mtf_replay.py` | **Criado** | Script CLI de replay MTF candle-a-candle (604 linhas) |
| `infra/sync_v2.py` | **Modificado** | Fix 1 — skip do sync Hostinger via `SMC_V2_SKIP_HOSTINGER_SYNC` |
| `technical_engine/smc_engine_v2/incremental/components/retracements.py` | **Corrigido** | Fix 2 — Bug FK: `ANCHOR_CHANGED` event sem StructureEmission pai |

---

## Bugs corrigidos nesta fase

### Bug 1 — Hostinger sync causa timeout de ~600s por step
`run_v2_pipeline_and_sync` chamava `sync_v2_shadow_zones()` no final,
independentemente de o pipeline ter rodado. Em modo offline, cada step travava ~600s.

**Fix aplicado (`infra/sync_v2.py`):**
```python
skip_hostinger = os.environ.get('SMC_V2_SKIP_HOSTINGER_SYNC', 'false').lower() == 'true'
if timeframe != '1min' and not skip_hostinger:
    ok, msg = sync_v2_shadow_zones(ticker, asset_id, timeframe)
```
O script de replay seta `SMC_V2_SKIP_HOSTINGER_SYNC=true` automaticamente.

### Bug 2 — `FOREIGN KEY constraint failed` no SQLite da shadow
`RetracementsComponent._maybe_update_anchor()` emitia evento `ANCHOR_CHANGED`
com `structure_id=anchor_id` interno, mas esse anchor nunca era inserido em
`smc_v2_structures` — violação da FK `smc_v2_structure_events.structure_id →
smc_v2_structures.structure_id`.

**Fix aplicado (`components/retracements.py`):**
Novo método `_emit_anchor_structure()`: emite o anchor como
`StructureEmission(structure_type="FIBONACCI_ANCHOR")` ANTES do evento
`ANCHOR_CHANGED`, satisfazendo a FK. Idempotente via `_emitted_struct_ids`.

**Testes:** 17/17 R4 shadow runtime passando após o fix.

### Bug 3 — `batch=False` silencioso quando banco não está limpo
O freshness check via `MAX(timestamp)` em `market_candles` retornava candles
de 2026 quando o replay tentava inserir candles de 2021 → pipeline nunca rodava.

**Fix:** função `assert_db_clean()` no `r5a_mtf_replay.py` aborta com erro
claro (exit 1) se o banco não estiver limpo. `--clean` é obrigatório.

---

## Fluxo de dados

```
CSVs (M2, M5, M15, H1, H4, D1)
  │  parse_csv() + compute_indicators()
  ▼
all_rows[tf] = list[dict]
  │
  ├─ Bootstrap (100 M5 + TFs proporcionais) ──► market_candles (INSERT batch)
  │
  └─ Loop M5-a-M5 (step 100 ... 1099):
       m5_deadline = m5_open + 5min
       │
       ├─ M2s pendentes (close ≤ deadline) → INSERT + run_v2_pipeline('2min') × N
       ├─ M5 do step       → INSERT + run_v2_pipeline('5min')
       ├─ M15 pendente     → INSERT + run_v2_pipeline('15min')
       ├─ H1 pendente      → INSERT + run_v2_pipeline('1h')
       ├─ H4 pendente      → INSERT + run_v2_pipeline('4h')
       └─ D1 pendente      → INSERT + run_v2_pipeline('1d')
            │
            ▼
       log_step → runtime/r5a_mtf_replay_log.jsonl
  │
  └─ Relatório final → runtime/r5a_mtf_replay_report.json
```

---

## Escopo de limpeza (`--clean`, MySQL asset_id=1)

`SET FOREIGN_KEY_CHECKS=0` antes dos TRUNCATEs.

- `DELETE FROM market_candles WHERE asset_id=1`
- TRUNCATE: `technical_engine_smc_v2_runs_shadow`, `fvg_shadow`, `swings_shadow`,
  `bos_choch_shadow`, `order_blocks_shadow`, `liquidity_shadow`,
  `previous_high_low_shadow`, `sessions_shadow`, `retracements_shadow`,
  `visual_overlays_shadow`
- Deletar `runtime/smc_v2_incremental_shadow.db` (SQLite)

**Preservar:** `assets`, opportunity, Elliott, Wyckoff, live_replay.

---

## Verificação

```bash
# 1. Limpar banco (já executado — banco está limpo)
# Se precisar limpar novamente:
#   o próprio script faz --clean

# 2. Run MTF com 1000 steps
cd "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"

SMC_V2_INCREMENTAL_SHADOW=true python tools/r5a_mtf_replay.py \
    --csv-dir /home/bimaq/projetos/SMC_Trader_System_7_0/data/csv_import/WINFUT_2021_2026 \
    --limit 1000 --bootstrap 100 --clean --verbose

# 3. Resumo por TF após o run
python3 -c "
import json; from collections import defaultdict
rows = [json.loads(l) for l in open('runtime/r5a_mtf_replay_log.jsonl')]
per = defaultdict(lambda: {'runs':0,'err':0,'divs':0})
for r in rows:
    for tf, res in r.get('results',{}).items():
        if not res.get('skipped'):
            per[tf]['runs'] += 1
            if res.get('error'): per[tf]['err'] += 1
            if res.get('divergence',{}).get('has_divergence'): per[tf]['divs'] += 1
for tf,s in sorted(per.items()): print(f'{tf:6}: runs={s[\"runs\"]:4d} errors={s[\"err\"]} divs={s[\"divs\"]}')
"

# 4. Relatório final com gates
python3 -c "import json; print(json.dumps(json.load(open('runtime/r5a_mtf_replay_report.json')), indent=2))"
```

**Gate esperado:** `gate_r5a_mtf_pass: true`

---

## Saídas geradas

- `runtime/r5a_mtf_replay_log.jsonl` — uma linha JSON por step M5
- `runtime/r5a_mtf_replay_report.json` — contagens por TF + gates

---

## Pendências

- [ ] Executar verificação de 1000 steps e confirmar `gate_r5a_mtf_pass: true`
- [ ] Commit do fix `retracements.py` (Bug 2)
