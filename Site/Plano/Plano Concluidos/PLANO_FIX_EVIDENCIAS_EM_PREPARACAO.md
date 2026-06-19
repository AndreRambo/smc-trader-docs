# Plano: Corrigir "Evidências em preparação" no App Android

**Status:** 🔴 Aguardando aprovação
**Criado:** 2026-06-19 15:30 UTC+2
**Branch:** `feature/phase6-candidate-c-nested-walk-forward`

---

## Diagnóstico

O app Android mostra "Evidências em preparação" ao abrir a oportunidade #3. A notificação push chegou corretamente, os bundles e artifacts foram criados no Laravel (HTTP 201 em todos os steps), mas o endpoint `GET /api/mobile/opportunities/{id}/evidence` falha na deserialização pelo app.

### Causa raiz: 4 mismatches entre o que o Python/VPS envia e o que o app Android espera

#### 1. `evidences` — ARRAY vs MAP (CRÍTICO — causa a falha)

| O que o Python envia | O que o Kotlin espera |
|---|---|
| `"evidences": [{...}, {...}]` (array) | `Map<String, List<EvidenceItemDto>>` (objeto com chaves) |

Quando o Kotlinx.serialization tenta deserializar `[` como `{`, lança exceção. O `catch` no repositório retorna `Result.failure` → ViewModel mostra "Evidências em preparação".

#### 2. `levels` — nomes de campos diferentes

| Python envia | Kotlin espera |
|---|---|
| `entrada`, `zone_low`, `zone_high`, `r_pts` | `entry_reference`, `entry_low`, `entry_high`, `rr_tp1` |

Não quebra (campos são nullable), mas levels ficam vazios na UI.

#### 3. `hit_rates` — estrutura completamente diferente

| Python envia | Kotlin espera |
|---|---|
| `{tp1_before_stop, bars_to_tp1, session}` | `{available, sample_size, labels, expectancy_r}` |

Não quebra mas `available=false` → seção de taxas históricas não aparece.

#### 4. `EvidenceItemDto` — nomes de campos diferentes

| Python envia | Kotlin espera |
|---|---|
| `{type, description}` | `{category, subtype, source_ref, selection_reason, payload, sort_order}` |

Não quebra mas evidências ficam vazias.

### Fluxo completo

```
VPS (Python) → POST /api/internal/opportunity-evidence (bundle JSON)    [✅ 201]
VPS (Python) → POST /api/internal/opportunity-evidence/{id}/artifacts   [✅ 201 × 3]
VPS (Python) → POST /api/internal/opportunity-evidence/{id}/complete    [✅ 200]
                                                                         ↓
App Android → GET /api/mobile/opportunities/{id}/evidence                [❌ deserialization falha]
  → Kotlinx.serialization: evidence_bundle.evidences é array, esperava Map
  → Exception caught → Result.failure → "Evidências em preparação"
```

---

## Solução

### Arquivos a modificar

#### 1. `tools/send_chart_evidence_to_laravel.py` — Corrigir payload (VPS)

Função `build_bundle_payload()` — ajustar 3 seções:

**`evidences`** — mudar de array para Map<String, List>:
```python
"evidences": {
    "smc": [
        {"category": "zone", "subtype": "fvg_ob", "source_ref": signal["plan_id"],
         "selection_reason": "Zona SMC identificada (FVG/OB) na região de entrada",
         "sort_order": 1}
    ],
    "structure": [
        {"category": "mtf", "subtype": "htf_alignment",
         "selection_reason": f"MTF alinhado — H4 bias {signal['htf_bias']}",
         "sort_order": 2}
    ],
    "contextual": [
        {"category": "outcome", "subtype": "verified",
         "selection_reason": signal["outcome"],
         "sort_order": 3}
    ],
}
```

**`levels`** — usar nomes do Kotlin:
```python
"levels": {
    "entry_reference": signal["entrada"],
    "entry_low": signal["zone_low"],
    "entry_high": signal["zone_high"],
    "stop": signal["stop"],
    "tp1": signal["tp1"],
    "tp2": signal["tp2"],
    "tp3": signal["tp3"],
}
```

**`hit_rates`** — usar estrutura do Kotlin:
```python
"hit_rates": {
    "available": True,
    "sample_size": 1,
    "labels": ["TP1 atingido sem stop"],
    "expectancy_r": 1.0,
}
```

#### 2. `MaximusTrader/backend/app/Http/Controllers/Api/MobileOpportunityEvidenceController.php` — Normalizar no Laravel (proteção futura)

Método `transformBundle()` — adicionar normalização para lidar com payloads antigos ou de diferentes versões:

```php
private function transformBundle($bundle): ?array
{
    // ... existing code ...
    
    $payload = is_string($payload) ? json_decode($payload, true) : $payload;
    
    // Normalize evidences: if sent as flat array, wrap in {"smc": [...]}
    if (isset($payload['evidences']) && array_is_list($payload['evidences'])) {
        $payload['evidences'] = ['smc' => $payload['evidences']];
    }
    
    // Normalize levels field names
    if (isset($payload['levels']['entrada'])) {
        $payload['levels']['entry_reference'] = $payload['levels']['entrada'];
        unset($payload['levels']['entrada']);
    }
    // ... similar for zone_low→entry_low, zone_high→entry_high, r_pts→rr_tp1
    
    // Normalize hit_rates
    if (isset($payload['hit_rates']['tp1_before_stop'])) {
        $payload['hit_rates'] = [
            'available' => true,
            'sample_size' => 1,
            'labels' => ['TP1 antes do stop: sim'],
            'expectancy_r' => 1.0,
        ];
    }
    
    // ... rest of transformBundle ...
}
```

#### 3. (OPCIONAL) Android: tornar `evidences` mais tolerante

Se quisermos que o app seja robusto contra variações de formato, poderíamos mudar `evidences` para `JsonElement` e fazer parse manual. Mas isso é mais invasivo e desnecessário se corrigirmos o payload.

---

## Ordem de execução

```
1. Corrigir send_chart_evidence_to_laravel.py (15 min)
2. Re-enviar evidence bundle para oportunidade #3 (1 min)
3. Verificar no app Android se o gráfico aparece (2 min)
4. Se OK → Normalizar transformBundle() no Laravel (15 min)
5. Se OK → Commit e documentar
```

---

## Verificação

- [ ] `python tools/send_chart_evidence_to_laravel.py --opportunity-id 3 --signal-key 20260601_1502 --verbose` → HTTP 201 em todos os steps
- [ ] Abrir app Android → notificação da oportunidade #3 → tela de detalhes mostra gráfico (não "Evidências em preparação")
- [ ] Chart card mostra dimensões e botões "Miniatura" / "Tela cheia"
- [ ] Sumário aparece (violet card)
- [ ] Níveis (entrada/stop/TPs) aparecem
- [ ] Evidências SMC aparecem na seção correspondente
