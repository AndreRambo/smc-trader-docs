# Split Temporal — WINFUT V1

**Data:** 2026-06-25 19:41 BRT
**Período total:** 2021-06-22 09:00:00 → 2026-06-19 18:20:00

## Splits

| Split | Período | Purge | Embargo | Propósito |
|-------|---------|-------|---------|-----------|
| DISCOVERY         | 2021-06-22T09:00:00 → 2022-12-31T18:20:00 | 0d | 30d | Explorar padroes, identificar zonas mais promissor |
| TRAIN             | 2023-01-30T09:00:00 → 2024-06-30T18:20:00 | 30d | 30d | Treinar parametros candidatos, otimizar filtros e  |
| VALIDATION        | 2024-07-30T09:00:00 → 2025-06-30T18:20:00 | 30d | 60d | Validar parametros, ajustar hiperparametros, selec |
| TEST_FINAL        | 2025-08-29T09:00:00 → 2026-01-31T18:20:00 | 60d | 90d | Teste final com dados congelados — NAO ajustar par |
| RECENT_HOLDOUT    | 2026-04-30T09:00:00 → 2026-06-19T18:20:00 | 90d | 0d | Periodo recente reservado — NAO e live forward fut |

## Candles por Split

| Split | 2min | 5min | 15min | 60min | 4h | 1d |
|-------|-------|-------|-------|-------|-------|-------|
| DISCOVERY         | 104066 | 41561 | 13982 | 3557 | 1144 | 381 |
| TRAIN             | 96919 | 38703 | 13018 | 3349 | 1053 | 351 |
| VALIDATION        | 64368 | 25706 | 8646 | 2276 | 682 | 227 |
| TEST_FINAL        | 29949 | 11978 | 4028 | 1060 | 317 | 105 |
| RECENT_HOLDOUT    | 9884 | 3955 | 1330 | 350 | 104 | 34 |

## Zonas por Split (FVG)

| Split | 2min | 5min | 15min | 60min | 4h | 1d |
|-------|-------|-------|-------|-------|-------|-------|
| DISCOVERY         | 19634 | 8266 | 3021 | 933 | 542 | 327 |
| TRAIN             | 17332 | 7060 | 2645 | 796 | 273 | 246 |
| VALIDATION        | 11757 | 4733 | 1750 | 538 | 183 | 162 |
| TEST_FINAL        | 5325 | 2106 | 771 | 250 | 87 | 93 |
| RECENT_HOLDOUT    | 1833 | 827 | 271 | 85 | 27 | 21 |

## Zonas por Split (OB)

| Split | 2min | 5min | 15min | 60min | 4h | 1d |
|-------|-------|-------|-------|-------|-------|-------|
| DISCOVERY         | 4643 | 1811 | 641 | 168 | 61 | 57 |
| TRAIN             | 4254 | 1683 | 592 | 166 | 43 | 39 |
| VALIDATION        | 2747 | 1090 | 376 | 109 | 36 | 36 |
| TEST_FINAL        | 1268 | 509 | 157 | 45 | 12 | 9 |
| RECENT_HOLDOUT    | 417 | 160 | 56 | 15 | 3 | 6 |

## Regras

- **Purge**: Nenhuma zona/evento do split N pode influenciar split N+1
- **Embargo**: Gap entre train/val/test para prevenir information leakage
- **TEST_FINAL**: Nenhum ajuste de parâmetros permitido
- **FORWARD_SHADOW**: Mínimo 40 pregões para validação