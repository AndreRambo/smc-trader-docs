# ADR — UNIFICAÇÃO BATCH V3 × INCREMENTAL SMC ENGINE V3

**Data:** 2026-06-30
**Status:** DECIDIDO
**Decisão:** Opção 2 — incremental como implementação canônica, batch como reference/adapter

## Contexto

Duas tracks de correção existem em paralelo:
- **V3 batch** (`smc_engine_v3/*.py`): 8 engines com `detection_definition="CANONICAL_V3"`, temporal window, SHA-256 IDs, guardrails. Testes: 37/37.
- **V2 incremental** (`smc_engine_v2/incremental/`): PHASE_08_COMPLETE + R1-R4 PASS + R5A. Causal (`available_at` guard), persistência atômica, shadow runtime real.

Ambas corrigem os mesmos problemas: anti-lookahead, lifecycle, IDs determinísticos, shadow-only.

## Decisão

**O incremental unified será a implementação canônica de runtime.** A V3 batch servirá como:
1. **Reference implementation** para algoritmos (a lógica CANONICAL_V3 dos planos individuais)
2. **Adapter/verifier** para paridade e regressão
3. **Baseline documentada** para todo o comportamento corrigido

**Motivos:**
- Incremental já possui shadow runtime real (R4 PASS, 17 testes gate)
- Persistência atômica por tick (R2, SAVEPOINT)
- Já integrado com `infra/sync_v2.py` e Opportunity Scanner (R3)
- V3 batch entrega a lógica corrigida mas não tem runtime operacional (dry-run only)

## Consequências

- Manter ambas as tracks até cutover G10
- V3 batch como autoridade lógica (documentação, testes, baseline)
- Incremental como autoridade operacional (runtime, persistência)
- Unificar via ADR, não via código (risco de reescrever R1-R5A)
- R5A deve ser concluído antes de qualquer unificação de código

## Alternativas rejeitadas

- Reescrever incremental com lógica V3 batch: risco de perder R1-R4
- Abandonar incremental: perde shadow runtime real
- Manter divergentes indefinidamente: duas verdades algorítmicas

## Rollback

- V3 batch permanece disponível como fallback de lógica
- Incremental pode ser desligado via flag (R4 provou)
- Nenhuma migração de código necessária para rollback
