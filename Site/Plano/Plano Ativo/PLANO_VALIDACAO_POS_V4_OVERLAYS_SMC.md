# PLANO DE VALIDAÇÃO PÓS-V4 — OVERLAYS SMC E LEGIBILIDADE DO GRÁFICO

**Projeto:** Maximus Trade  
**Data:** 26/06/2026  
**Base:** resumo de implementação V4 informado pelo executor  
**Foco:** lifecycle, budgets, PDH/PDL, labels, zoom, renderers, autoscale e legibilidade  
**Status atual:** `BUILD_PASS / CORREÇÕES_V4_IMPLEMENTADAS / VALIDAÇÃO_VISUAL_PENDENTE`  
**Gate de produção:** `NO_GO`

---

# 1. Objetivo

Validar se as correções V4 realmente:

1. eliminaram zonas antigas estendidas até o candle atual;
2. removeram a parede vertical no lado direito;
3. reduziram PDH/PDL excessivos;
4. aplicaram budgets por timeframe;
5. adaptaram a densidade ao zoom;
6. preservaram zonas tecnicamente válidas;
7. não removeram zonas importantes por expiração arbitrária;
8. mantiveram candles e indicadores legíveis;
9. evitaram labels sobrepostos;
10. não alteraram indevidamente o autoscale;
11. mantiveram pan, zoom, live e prepend fluidos.

---

# 2. Estado informado

## Implementado

### `normalizerUtils.ts`

- zonas mitigadas terminam em `mitigatedAt`;
- zonas ativas recebem cap de 7 dias;
- PDH/PDL recebem cap de 24 horas;
- BOS/CHOCH/LIQ usam endpoints explícitos;
- removido fallback genérico para `lastCandleTime`.

### `pdhPdlNormalizer.ts`

- limite configurável;
- padrão 10;
- ordenação por recência;
- `maxPdhPdl` configurável.

### `smcVisibility.ts`

- budgets por timeframe;
- níveis de detalhe:
  - `OVERVIEW`;
  - `STANDARD`;
  - `DETAIL`;
- orçamento ajustado pelo zoom.

### `smcTypes.ts`

Redução de defaults:

```text
maxActiveZones: 300 → 24
maxStructures: 250 → 24
maxLabels: 40 → 16
maxItems: 150 → 24
```

### Renderers OB, FVG e BPR

- zonas ativas sem borda direita;
- zonas mitigadas com borda direita pontilhada;
- redução da parede vertical no candle atual.

### Normalizers

- logs de produção removidos.

---

# 3. Avaliação executiva

As alterações tratam corretamente quatro causas centrais:

```text
EXTENSÃO_INDEFINIDA
PDH_PDL_ILIMITADOS
BUDGETS_EXCESSIVOS
BORDA_DIREITA_ACUMULADA
```

Entretanto, o resumo não confirma a implementação completa de:

```text
LABEL_COLLISION_MANAGER
CLUSTERING
RANKING_POR_RELEVÂNCIA
DISTÂNCIA_ATR
AUTOSCALE_ISOLADO
VIEWPORT_CLIPPING
PRESETS
STATUS_VISUAL
POLÍTICA_ESPECÍFICA_PDH_PDL_POR_TIMEFRAME
```

Portanto:

```text
GATE_IMPLEMENTAÇÃO_V4: PASS_INFORMADO
GATE_BUILD: PASS_INFORMADO
GATE_VISUAL: PENDENTE
GATE_PRODUÇÃO: NO_GO
```

---

# 4. Risco crítico — cap fixo de 7 dias

## Problema

A regra:

```text
zona ativa expira 7 dias após a criação
```

pode funcionar como fallback em M2/M5/M15, mas é incorreta como regra global.

Uma zona D1 ou H4 pode permanecer tecnicamente válida por semanas ou meses.

## Consequências

- OB D1 válido desaparece após 7 dias;
- FVG H4 não mitigado desaparece prematuramente;
- contexto macro fica incompleto;
- análise multi-timeframe perde zonas relevantes;
- o gráfico parece limpo, mas tecnicamente errado.

## Correção recomendada

O lifecycle técnico deve ter prioridade:

```text
mitigação
invalidação
expiração definida pelo engine
```

TTL visual deve ser apenas fallback.

## Política inicial por timeframe

| Timeframe | Fallback visual sugerido |
|---|---:|
| M2 | 2 pregões |
| M5 | 3 pregões |
| M15 | 5 pregões |
| H1 | 15 dias |
| H4 | 60 dias |
| D1 | 180 dias ou sem TTL automático |

Melhor ainda: usar quantidade de barras, não dias corridos.

```ts
maxAgeBarsByTimeframe = {
  M2: 600,
  M5: 500,
  M15: 350,
  H1: 360,
  H4: 360,
  D1: 500,
}
```

Os valores devem ser calibrados com dados reais.

---

# 5. Risco crítico — PDH/PDL com cap fixo de 24 horas

## Problema

A validade de 24 horas é adequada para o nível diário usado na sessão seguinte, mas não deve ser aplicada da mesma forma em todos os timeframes.

## Política recomendada

| Timeframe | PDH/PDL padrão |
|---|---|
| M2 | últimos 2–3 pregões |
| M5 | últimos 3 pregões |
| M15 | últimos 3 pregões |
| H1 | últimos 5 níveis relevantes |
| H4 | últimos 5 níveis ou substituir por semanal |
| D1 | desativado |

No D1, priorizar:

```text
PWH/PWL
PMH/PML
```

## Gate

Confirmar que `maxPdhPdl` é escolhido por timeframe e não permanece 10 globalmente.

---

# 6. Ordem correta do pipeline visual

Confirmar esta ordem:

```text
1. normalização
2. lifecycle
3. filtro por timeframe
4. filtro por distância
5. ranking
6. clustering/deduplicação
7. budget
8. viewport clipping
9. label collision
10. renderer
```

Aplicar budget antes de ranking ou clustering pode ocultar zonas importantes e manter duplicatas.

---

# 7. Budgets por tipo

O resumo informa budgets por timeframe, mas deve haver limite por tipo.

Exemplo:

| Timeframe | OB | FVG | BPR | BOS/CHOCH | LIQ | PDH/PDL |
|---|---:|---:|---:|---:|---:|---:|
| D1 | 8 | 8 | 4 | 12 | 6 | 0 |
| H4 | 12 | 12 | 6 | 16 | 8 | 5 |
| H1 | 16 | 16 | 8 | 20 | 10 | 5 |
| M15 | 20 | 20 | 10 | 24 | 12 | 6 |
| M5 | 24 | 24 | 10 | 28 | 14 | 6 |
| M2 | 20 | 20 | 8 | 24 | 12 | 4 |

Um budget total de 24 não deve ser consumido por apenas um tipo.

---

# 8. Zoom detail com hysteresis

## Risco

Os limites:

```text
OVERVIEW > 500
STANDARD 120–500
DETAIL < 120
```

podem causar alternância rápida quando o usuário fica próximo de 120 ou 500 barras.

## Correção

Adicionar hysteresis:

```text
DETAIL entra abaixo de 110
DETAIL sai acima de 135

OVERVIEW entra acima de 520
OVERVIEW sai abaixo de 470
```

Isso evita flicker de labels e zonas durante zoom.

---

# 9. Labels ainda precisam de validação

A redução de `maxLabels` para 16 não resolve sozinha colisões.

Confirmar existência de:

```text
LabelCollisionManager
prioridade
retângulos reservados
ocultação por colisão
hover para detalhes
```

## Critério

Nenhum label deve:

- cobrir o preço atual;
- colidir com outro label;
- esconder candle crítico;
- repetir o mesmo identificador em sequência;
- aparecer fora da viewport.

---

# 10. Clustering e deduplicação

Confirmar agrupamento de zonas próximas.

## Regra inicial

Agrupar quando:

```text
overlap vertical >= 60%
ou
distância entre midpoints <= 0,25 ATR
```

## Resultado esperado

```text
OB ×3
FVG ×2
```

Mostrar apenas a zona dominante e detalhes no hover.

Sem clustering, M2/M5 ainda podem ficar poluídos mesmo com budget 20–24.

---

# 11. Ranking

Confirmar que os elementos escolhidos pelo budget são os mais relevantes.

Score sugerido:

```text
status
+ proximidade
+ força
+ confluência
+ recência
- número de toques
- idade
```

Não selecionar apenas por recência.

---

# 12. Distância do preço

Zonas muito distantes devem ser ocultadas do renderer principal.

Política inicial:

| Timeframe | Limite |
|---|---:|
| D1 | 30 ATR |
| H4 | 20 ATR |
| H1 | 15 ATR |
| M15 | 12 ATR |
| M5 | 10 ATR |
| M2 | 8 ATR |

---

# 13. Autoscale

Confirmar que primitives SMC não expandem a escala de preço.

## Teste

Adicionar uma zona artificial:

```text
50% acima do preço atual
```

O candle não pode ficar comprimido.

## Aceite

- somente candles e séries principais controlam autoscale;
- overlays distantes não alteram faixa;
- RSI continua isolado.

---

# 14. Viewport clipping

Mesmo zonas longas devem ser desenhadas somente na interseção com a viewport.

Não criar paths ou retângulos fora da faixa visível.

Isso reduz:

- custo de render;
- artefatos;
- bordas longas;
- risco de overflow.

---

# 15. Renderers ainda não citados

A correção de borda direita foi informada para:

```text
OB
FVG
BPR
```

Auditar também:

```text
LiquidityRenderer
PdhPdlRenderer
SessionRenderer
BosRenderer
ChochRenderer
SwingRenderer
```

## Verificar

- extensão temporal;
- borda direita;
- labels;
- clipping;
- status;
- autoscale;
- opacidade;
- linhas repetidas.

---

# 16. Testes visuais obrigatórios

# 16.1 D1

- PDH/PDL desligados;
- zonas D1 válidas não desaparecem após 7 dias;
- máximo 8 OB;
- máximo 8 FVG;
- sem parede branca;
- candles legíveis.

# 16.2 H4

- zonas válidas com mais de 7 dias permanecem;
- mitigadas terminam corretamente;
- sem retângulos de anos até o presente;
- escala preservada.

# 16.3 H1

- PDH/PDL limitados;
- labels sem colisão;
- budgets por tipo;
- zonas próximas priorizadas.

# 16.4 M15

- zonas locais expiram adequadamente;
- labels top rank;
- candles visíveis;
- zoom troca detalhe sem flicker.

# 16.5 M5

- clustering reduz duplicatas;
- labels não se sobrepõem;
- borda direita removida;
- pan/zoom fluido.

# 16.6 M2

- microestrutura filtrada;
- janela curta;
- labels críticos somente;
- sem bloco denso sobre o preço;
- FPS aceitável.

---

# 17. Testes funcionais

1. alternar D1 → M2 → H4;
2. zoom in/out repetidamente;
3. pan para esquerda;
4. carregar histórico;
5. receber candle live;
6. mitigar uma zona;
7. invalidar uma zona;
8. alternar tipos;
9. alternar preset;
10. entrar em Replay;
11. voltar ao live;
12. redimensionar a janela;
13. testar mobile;
14. testar fullscreen.

---

# 18. Métricas de debug

Adicionar painel de desenvolvimento:

```text
timeframe
barsVisible
detailLevel
zonesReceived
zonesAfterLifecycle
zonesAfterDistance
zonesAfterCluster
zonesAfterBudget
zonesRendered
labelsRequested
labelsRendered
labelsHidden
renderDurationMs
```

---

# 19. Critérios de aceite

- [ ] TTL de zona é específico por timeframe ou definido pelo engine.
- [ ] D1 não perde zonas válidas após 7 dias.
- [ ] PDH/PDL são específicos por timeframe.
- [ ] D1 inicia com PDH/PDL desligado.
- [ ] Budgets são separados por tipo.
- [ ] Ranking ocorre antes do budget.
- [ ] Clustering está ativo.
- [ ] Labels usam collision manager.
- [ ] Zoom usa hysteresis.
- [ ] Overlays não alteram autoscale.
- [ ] Viewport clipping está implementado.
- [ ] Todos os renderers foram auditados.
- [ ] Zonas ativas não possuem borda direita.
- [ ] Mitigadas terminam em `mitigatedAt`.
- [ ] Invalidadas terminam em `invalidatedAt`.
- [ ] Nenhuma zona histórica irrelevante atravessa o presente.
- [ ] D1, H4, H1, M15, M5 e M2 passam visualmente.
- [ ] Build passa.
- [ ] Lint passa.
- [ ] Testes passam.
- [ ] Console sem erros.

---

# 20. Gates

```text
GATE_TTL_BY_TIMEFRAME: PENDENTE
GATE_PDH_PDL_POLICY: PENDENTE
GATE_BUDGET_PER_TYPE: PENDENTE
GATE_RANKING: PENDENTE
GATE_CLUSTERING: PENDENTE
GATE_LABEL_COLLISION: PENDENTE
GATE_ZOOM_HYSTERESIS: PENDENTE
GATE_AUTOSCALE: PENDENTE
GATE_VIEWPORT_CLIP: PENDENTE
GATE_ALL_RENDERERS: PENDENTE
GATE_VISUAL_ALL_TIMEFRAMES: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 21. Ordem recomendada

```text
1. corrigir TTL global de 7 dias
2. política PDH/PDL por timeframe
3. budget por tipo
4. ranking
5. clustering
6. label collision
7. zoom hysteresis
8. autoscale
9. viewport clipping
10. auditoria de todos os renderers
11. testes por timeframe
12. aprovação visual
```

---

# 22. Conclusão

A V4 corrigiu as causas mais visíveis da poluição, mas ainda não deve ser considerada final.

O principal ponto a revisar é:

```text
CAP GLOBAL DE 7 DIAS
```

Essa regra limpa o gráfico, porém pode remover informação técnica válida em H4 e D1.

O gráfico só estará pronto quando a limpeza visual for obtida sem sacrificar a verdade técnica dos overlays.
