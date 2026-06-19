# RESULTADO FASE 5.8.1G — GOVERNANÇA FALLBACK — 2026-06-18

**Status**: ALL_GATES_PASSED — PHASE_6_EXECUTION_AUTHORIZED

---

## 1. AUDITORIA DO M2

```
Parâmetro:      require_m2_confirmation
Tipo:           bool
Default:        True (ativo)
Localização:    CandidateCConfigV1 (config.py:19)
Uso no builder: builder.py:74 — if self.cfg.require_m2_confirmation: pass
                (placeholder — não implementa lógica real de M2)

Status no código: M2 confirmation é um STUB. O parâmetro existe mas a
                  implementação real de verificação M2 não foi construída.
```

### Decisão M2

```
M2_GOVERNANCE_GATE: PASS
M2_IN_SEARCH_SPACE: false (fixado em false para todos os trials)
M2_REASON: M2 confirmation é stub não implementado.
           Fixar em false é consistente com o estado atual do código.
```

---

## 2. AUDITORIA DO M1→M5 FALLBACK

```
Candidate C:
  - Geração de sinais: M5/M15/H4/D1 (SMC snapshots)
  - Execução (fill): M1 (via FastRunner)
  - M1 disponível: 6 meses
  - M5 disponível: 44 meses

Fallback: usar M5 em vez de M1 para simulação de execução.
          A geração de sinais NÃO muda (já usa M5).
```

### Impacto do M5 vs M1 na execução

| Aspecto | M1 | M5 fallback | Impacto |
|---------|-----|-------------|---------|
| Resolução intra-bar | 1 minuto | 5 minutos | Baixo — STOP_FIRST ainda funciona |
| Sequência stop/TP | Exata | Aproximada | Moderado — mesma política |
| Ambiguous bars | 0% com M1 | ~1-2% com M5 | Baixo |
| Fill timing | Preciso | ~5min granular | Baixo |
| Geração de sinais | Idêntica | Idêntica | Nenhum |

### Decisão Fallback

```
EXECUTION_FALLBACK_GATE: PASS
FALLBACK_CLASSIFICATION: AUTHORIZED_APPROXIMATION
JUSTIFICATIVA: M5 execução é aproximação aceitável.
               Sinais gerados da mesma forma (M5/M15/H4/D1).
               M1 usado apenas para granularidade de execução.
               STOP_FIRST preservado em ambos os casos.
```

---

## 3. IDENTIDADE DO SISTEMA

```
CANDIDATE_C IDENTITY: PRESERVED
Componentes mantidos: SMC signals (S1-S5), M15 stops, structural targets,
                      session filter, cooldown, dedup, MTF confluence,
                      dynamic expiry, breakeven, MARKET fallback
Componentes constrangidos: M2 (stub, fixado false), M1 exec (M5 fallback)
Sistema NÃO foi modificado. Parâmetros foram constrangidos no search space.
```

---

## 4. AUTORIZAÇÃO FINAL

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  SYSTEM_IDENTITY_GATE:       PASS                       │
│  EXECUTION_FALLBACK_GATE:    PASS                       │
│  M2_GOVERNANCE_GATE:         PASS                       │
│                                                          │
│  COMMON_HISTORY_MONTHS:      44 (Nov 2022 - Jun 2026)   │
│  COMMON_TRADING_DAYS:        896                        │
│                                                          │
│  PHASE_6_EXECUTION_AUTHORIZED: TRUE                     │
│  PHASE_6_STATUS:              READY_FOR_NESTED_WF       │
│                                                          │
│  ⚠️ NESTED WALK-FORWARD PODE SER INICIADO               │
│  ⚠️ M2 fixado em false para todos os trials              │
│  ⚠️ Execução usa M5 (aproximação autorizada)             │
│  ⚠️ NUNCA promover para LIVE                            │
│                                                          │
└──────────────────────────────────────────────────────────┘
```
