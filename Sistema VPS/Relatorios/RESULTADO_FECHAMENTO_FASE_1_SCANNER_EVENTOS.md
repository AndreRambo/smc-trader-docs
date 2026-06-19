# RESULTADO — FASE 1 — SCANNER E EVENTOS EM RETRY

**Status:** CONCLUIDO

---

## 1. Scanner `LatestPriceRef.status`

**Causa:** Argumentos trocados em `evaluate_opportunity(price, plan)` — a função espera `(plan, price)`. O `price` era tratado como `plan`, causando acesso a `.status` inexistente.

**Correção:** Commit `ab35fa3` — ordem correta + `status="ACTIVE"` maiúsculo.

## 2. Eventos em Retry

| Classificação | Quantidade | Causa |
|--------------|-----------|-------|
| PIPELINE_ERROR | 16 (M5) | Bugs no dispatcher (já corrigidos) |
| STUCK_PROCESSING | 3 | Processor reiniciado durante ciclo |

**Ação:** Reset 16 FAILED + 3 PROCESSING → PENDING com `available_at=NOW()`. Todos processados com sucesso.

**Resultado após 30s:** 0 FAILED, 0 DEAD, 0 STUCK. Processor consumindo normalmente.

## 3. Testes

- Python: 851 passed, 1 failed (pré-existente), 3 skipped
- Sem regressão nova

## 4. Critérios de Aceite

- [x] Erro `LatestPriceRef.status` eliminado
- [x] 16 eventos classificados como PIPELINE_ERROR
- [x] Recuperáveis concluídos (0 FAILED)
- [x] Nenhum evento DEAD
- [x] Nenhum PROCESSING stuck
- [x] Scanner sem exceção
- [x] Relatório criado
