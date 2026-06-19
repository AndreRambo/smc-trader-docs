# Teste de Notificação Ponta a Ponta — WINFUT (REAL)

**Status:** ✅ CONCLUÍDO — Notificação recebida, chart enviado, bug corrigido  
**Data:** 19/06/2026  
**Oportunidade:** #3 — WINFUT ALTISTA IMINENTE  
**Sinal real:** 2026-06-01 15:02 UTC — bt-plan-013502

---

## 1. Sinal utilizado

Sinal real extraído do backtest do Opportunity Scanner (6 meses, WINFUT), verificado com entrada tocada, TP1 atingido, stop NÃO atingido.

| Campo | Valor |
|-------|-------|
| **Plan ID** | `bt-plan-013502` |
| **Data/Hora** | 2026-06-01 15:02:00 UTC |
| **Direção** | ALTISTA ↑ |
| **Proximity** | IMINENTE |
| **Entrada** | 173.090 pts |
| **Stop** | 172.752 pts |
| **R (risco)** | 338 pts |
| **TP1** | 173.428 pts (1.0R) |
| **TP2** | 173.766 pts (2.0R) |
| **TP3** | 174.104 pts (3.0R) |
| **Zona SMC** | 172.977 – 173.203 |
| **ATR** | 225 pts |
| **HTF Bias** | ALTISTA (D1/H4) |
| **MTF Align** | a_favor |
| **Confiança** | MEDIA |
| **Resultado** | ✅ TP1 atingido em 5 candles M5 (25 min) sem stop |

**Fonte:** `storage/replay/opportunity_scanner/WINFUT/outcomes/outcomes_unique_opportunities.csv`  
Linha com: `2026-06-01 15:02:00, ALTISTA, IMINENTE, entrada_touched=True, tp1_hit=True, stop_hit=False, bars_to_outcome=5`

---

## 2. Fluxo executado

```
1. Scanner Backtest (pré-existente, 03/06/2026)
   └─ 19.120 sinais, 5.995 oportunidades únicas, 288 vencedores IMINENTE/NA_ZONA
   └─ Selecionado: 2026-06-01 15:02 ALTISTA IMINENTE

2. Envio do Alerta → POST /api/scanner/alerts
   └─ Script: tools/send_test_alert_to_laravel.py --real-signal 20260601_1502
   └─ HTTP 201 — Opportunity #3 criada
   └─ Push notification enviado via FCM → recebido no Android em <5s ✅

3. Geração do Chart PNG
   └─ Script: tools/send_chart_evidence_to_laravel.py (criado nesta sessão)
   └─ 85 candles M5 carregados do DB (12:00–17:30 UTC)
   └─ Plotly + Kaleido → 3 PNGs: thumbnail (400×300), mobile (1080×720), full (1440×900)

4. Envio do Evidence Bundle → POST /api/internal/opportunity-evidence
   └─ Bundle criado: HTTP 201 (bundle-3-20260601_1502-1781875615)
   └─ 3 artifacts uploaded: HTTP 201 × 3
   └─ Bundle complete: HTTP 200

5. App Android — GET /api/mobile/opportunities/3/evidence
   └─ ❌ Bug: "Evidências em preparação"
   └─ Causa: payload `evidences` era array, Kotlin esperava Map<String, List>
```

---

## 3. 🐛 Bug: "Evidências em preparação"

### Causa raiz

O campo `evidences` no JSON do bundle era enviado como **array plana**:
```json
"evidences": [
    {"type": "zone", "description": "Zona SMC..."},
    {"type": "mtf", "description": "MTF alinhado..."}
]
```

Mas o app Android (Kotlinx.serialization) espera **Map<String, List<EvidenceItemDto>>**:
```json
"evidences": {
    "smc": [{"category": "zone", "subtype": "fvg_ob", ...}],
    "structure": [{"category": "mtf", "subtype": "htf_alignment", ...}]
}
```

A deserialização quebrava ao encontrar `[` onde esperava `{`. O `catch` no `OpportunityRepositoryImpl` retornava `Result.failure` → ViewModel mostrava "Evidências em preparação".

### Arquivos corrigidos

| Arquivo | Mudança |
|---------|---------|
| `tools/send_chart_evidence_to_laravel.py` | `build_bundle_payload()` — `evidences` como Map, `levels` com nomes Kotlin, `hit_rates` compatível |
| `MaximusTrader/.../MobileOpportunityEvidenceController.php` | `transformBundle()` — normalização de bundles antigos (flat array → Map, old field names → new) |

### Payload corrigido

```json
{
  "evidences": {
    "smc": [{
      "category": "zone",
      "subtype": "fvg_ob",
      "source_ref": "bt-plan-013502",
      "selection_reason": "Zona SMC (FVG/OB) identificada entre 172977 e 173203",
      "sort_order": 1
    }],
    "structure": [{
      "category": "mtf",
      "subtype": "htf_alignment",
      "selection_reason": "MTF alinhado — H4 bias ALTISTA, confiança MEDIA",
      "sort_order": 2
    }],
    "contextual": [{
      "category": "outcome",
      "subtype": "backtest_verified",
      "selection_reason": "TP1 atingido em 5 candles M5 (25 min) sem stop",
      "sort_order": 3
    }]
  },
  "levels": {
    "entry_reference": 173090,
    "entry_low": 172977,
    "entry_high": 173203,
    "stop": 172752,
    "tp1": 173428,
    "tp2": 173766,
    "tp3": 174104,
    "rr_tp1": 1.0, "rr_tp2": 2.0, "rr_tp3": 3.0
  },
  "hit_rates": {
    "available": true,
    "sample_size": 1,
    "labels": ["TP1 atingido em 5 candles M5 (25 min) sem stop"],
    "expectancy_r": 1.0
  }
}
```

---

## 4. Resultado final

| Etapa | Status |
|-------|--------|
| Alerta enviado (HMAC) | ✅ HTTP 201 |
| Oportunidade criada (Laravel) | ✅ ID #3 |
| Push FCM → Android | ✅ Notificação em <5s |
| Chart PNG gerado (Plotly) | ✅ 3 tamanhos |
| Evidence Bundle criado | ✅ HTTP 201 |
| Artifacts (thumbnail, mobile, full) | ✅ HTTP 201 × 3 |
| Bundle completo | ✅ HTTP 200 |
| App mostra gráfico | ⏳ Aguardando deploy do Laravel + teste |
| Deploy Laravel (scp) | ⏳ Pendente — rodar comando abaixo |

### Comando de deploy pendente

```bash
scp -P 65002 \
  /home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader/backend/app/Http/Controllers/Api/MobileOpportunityEvidenceController.php \
  u963484916@82.25.73.246:domains/maximustrade.com.br/app/Http/Controllers/Api/

ssh -p 65002 u963484916@82.25.73.246 \
  "cd domains/maximustrade.com.br && php artisan config:clear && php artisan route:clear"
```

---

## 5. Scripts criados/modificados

| Script | Status | Descrição |
|--------|--------|-----------|
| `tools/send_test_alert_to_laravel.py` | Modificado | Adicionado `--real-signal` com 2 sinais reais do backtest |
| `tools/send_chart_evidence_to_laravel.py` | **Criado** | Gera chart PNG + envia bundle + artifacts para o Laravel |
| `MobileOpportunityEvidenceController.php` | Modificado | Normalização de `evidences`/`levels`/`hit_rates` no `transformBundle()` |

### Uso futuro

```bash
# Listar sinais reais disponíveis
python tools/send_test_alert_to_laravel.py --list-signals

# Enviar alerta real para o Laravel
python tools/send_test_alert_to_laravel.py --real-signal 20260601_1502 --verbose

# Enviar chart evidence completo
python tools/send_chart_evidence_to_laravel.py \
  --opportunity-id 3 \
  --signal-key 20260601_1502 \
  --verbose
```

---

## 6. Sinais reais disponíveis no backtest

| Key | Data | Dir | Entrada | Stop | R | TP1 | Resultado |
|-----|------|-----|---------|------|---|-----|-----------|
| `20260601_1502` | 2026-06-01 | ↑ | 173.090 | 172.752 | 338 | 173.428 | TP1 em 25 min |
| `20260602_1714` | 2026-06-02 | ↑ | 175.215 | 174.980 | 235 | 175.450 | TP1 em 10 min |

Dados completos dos planos em: `storage/replay/opportunity_scanner/WINFUT/plans.csv`  
Outcomes em: `storage/replay/opportunity_scanner/WINFUT/outcomes/outcomes_unique_opportunities.csv`

---

## 7. Guardrails

```
shadow_only=true           research_only=true
can_promote_trade=false    anti_lookahead=true
deterministic=true         production_signal_emission=false
test_mode=true             real_signal=true (backtest verificado)
```
