# Plano: Corrigir Zonas SMC no Gráfico do Site Admin

## Contexto

Zonas SMC aparecem no Dashboard Local (Dash/Plotly :8050) mas NÃO no site admin (React + Lightweight Charts). Investigação forense completa do pipeline de 6 estágios (Engine → sync_v2 → Laravel sync_zones → API JSON → smcNormalize → SmcPaneRenderer) confirmou que **nenhum campo é estruturalmente perdido**. O problema está em 3 modos de falha silenciosa no frontend.

## Causas Raiz (confirmadas por forensic trace)

### Causa 1 (Principal): `display_from`/`display_to` NULL → zona descartada sem warning

`smcNormalize.ts` linha 122:
```typescript
if (fromTime === null || toTime === null) continue
```
Se `display_from` ou `display_to` chegam NULL do backend (dados antigos pré-fix 2026-06-09, ou engine chamado sem timestamps Series), a zona é silenciosamente pulada. Nenhum console.warn, nenhum log.

**Fix**: Adicionar fallback com warning + usar `lastCandleTime` ou extensão mínima de 1 hora para zonas ativas.

### Causa 2: `display_to` não tem fallback nos builders do sync_v2.py

6 builders passam `display_to` sem fallback (linhas 96, 130, 173, etc.). Se o engine não populou (dados antigos), o NULL propaga até o frontend.

**Fix**: Adicionar fallback chain em todos os builders: `display_to or confirmed_at or origin_at or event_time`.

### Causa 3: Labels posicionadas no topo da zona, não no midpoint (50%)

`SmcPaneRenderer.ts` linha 129: `yOf(it.topPrice)` para labels de zona. O `midpoint` está disponível no payload do banco e é recalculado como `linePrice` no `smcNormalize.ts` (linha 135), mas **não é usado** para posicionar labels.

**Fix**: Usar `it.linePrice` em vez de `it.topPrice` para labels de zona.

## Plano de Execução

### Fase 1: Diagnóstico (sem alterações de código)

1. Verificar se sync está rodando na VPS:
   ```bash
   pgrep -f sync_watcher && systemctl status smc-sync-watcher
   ```
2. Verificar se há dados recentes no sync_zones do site:
   ```bash
   # Via curl autenticado no Hostinger — checar quantas zonas existem e se display_from é NULL
   ```
3. Rodar sync manual forçado para WINFUT M5 e ver output:
   ```bash
   python -c "from infra.sync_v2 import sync_v2_shadow_zones; print(sync_v2_shadow_zones('WINFUT', 1, '5min'))"
   ```

### Fase 2: Correções no Frontend (3 arquivos)

**Arquivo 1**: `MaximusTrader/frontend/src/components/chart/smc/smcNormalize.ts`
- Linha 120: `toTime = toUnix(z.display_to) ?? last ?? (fromTime !== null ? (fromTime + 3600) as UTCTimestamp : null)`
- Adicionar `console.warn` quando zona é pulada por falta de time/price

**Arquivo 2**: `MaximusTrader/frontend/src/components/chart/smc/SmcPaneRenderer.ts`
- Linha 129: Trocar `it.topPrice` por `it.linePrice` para labels de zona (midpoint 50%)

**Arquivo 3**: `MaximusTrader/frontend/src/components/chart/smc/smcTypes.ts`
- `maxActiveZones: 80` → `300`

### Fase 3: Correções no sync_v2.py (VPS)

**Arquivo**: `infra/sync_v2.py`
- 6 builders: adicionar fallback para `display_to`:
  - FVG (linha 96): `z.get('display_to') or z.get('confirmed_at') or z.get('origin_at')`
  - OB (linha 130): mesmo padrão
  - BOS/CHOCH (linha 173): `z.get('line_end_time') or z.get('broken_at') or z.get('event_time')`
  - Liquidity, Swings, BPR: padrão similar

### Fase 4: Deploy e Verificação

1. Build frontend: `npm run build`
2. Deploy frontend: `bash tools/deploy.sh --frontend --no-build`
3. Testar no site: abrir `/admin/grafico`, selecionar WINFUT M5, verificar zonas visíveis

## Verificação

- `console.log(items.length)` em `updateSmcPrimitive()` > 0
- Zonas FVG/OB visíveis como retângulos coloridos no gráfico
- Labels de zona centralizadas verticalmente (midpoint 50%)
- Nenhum `console.warn` de zona pulada
