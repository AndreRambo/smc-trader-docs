# DECISÕES DE PRODUTO E ESCOPO DO MVP — SMC TRADER SYSTEM 7.0

**Data:** 16 de Junho de 2026
**Versão:** 1.0
**Baseado em:** `VISAO_PRODUTO_ENTREGAS_EXPECTATIVAS_SMC_TRADER_SYSTEM_7_0.md` + `RELATORIO_GERAL_STATUS` + `PLANO_EXECUTIVO`
**Status:** Aguardando confirmação do dono do produto nas decisões marcadas como [CONFIRMAR]

---

## 1. Resumo Executivo

### Posicionamento no MVP

O SMC Trader System 7.0 será posicionado como:

> **Scanner de oportunidades técnicas SMC com contexto de mercado, alertas mobile e dashboard web de apoio.**

**Entrega principal do MVP:** O trader recebe alertas push no celular quando o motor SMC detecta uma oportunidade técnica, vê o contexto completo (entrada, stop, alvos, confluências) e toma sua própria decisão de operar.

**Usuário-alvo inicial:** Trader pessoa física que opera WINFUT, XAUUSDm ou BTCUSDm e quer ser alertado passivamente sobre oportunidades SMC sem precisar ficar monitorando gráficos.

**Foco do MVP:** Scanner de oportunidades → alertas mobile. O dashboard web é ferramenta de apoio (análise mais profunda) e administração. O app mobile é o canal principal de entrega de valor ao usuário final.

**Ordem correta de evolução:**

```
Pipeline confiável → Dashboard web funcional → App mobile com alertas → Beta fechado → Produção
```

**O sistema entrega análise técnica, não promessa de lucro.** Toda comunicação com o usuário deixa claro que se trata de uma oportunidade técnica identificada por metodologia determinística. A decisão de operar é exclusivamente do trader.

---

## 2. Decisão Principal de Posicionamento

### Análise de Opções

| Opção | Vantagens | Riscos | Recomendação |
|-------|-----------|--------|-------------|
| **A — Sinais de trading** | Monetização mais direta; usuário entende rápido a proposta | Regulação CVM/Bacen; expectativa de "garantia de lucro"; dano reputacional se setup falhar; pode ser classificado como consultoria financeira | ❌ NÃO recomendado |
| **B — Scanner de oportunidades** (recomendado) | Alinhado ao que está implementado; evita conflito regulatório; posicionamento técnico/educacional; usuário mantém autonomia de decisão | Usuário pode achar "menos direto" que sinais prontos; exige educar usuário sobre diferença entre oportunidade e recomendação | ✅ RECOMENDADO |
| **C — Plataforma educacional** | Baixo risco regulatório; escalável via cursos/conteúdo; comunidade | Exige produção de conteúdo didático adicional; monetização mais lenta; não é o que está sendo construído (motor técnico) | ⚠️ Bom complemento futuro |
| **D — Dashboard técnico avançado** | Diferenciação para traders experientes; visual rico | Concorrência direta com TradingView; exige UX de altíssimo nível; app mobile seria subutilizado | ❌ Não para MVP |

### Decisão

**Posicionamento oficial: Scanner de oportunidades SMC com contexto técnico e alertas.**

**Fundamento (Encontrado nos arquivos):**
- O pipeline SMC Engine V2 já está implementado e testado (STABLE_FROZEN_V2, 164 testes)
- O Opportunity Scanner já tem 10+ gates determinísticos validados (306 testes)
- A cadeia completa de alerta (Scanner → HMAC Post → Laravel → FCM Push → App) já está arquitetada
- Nenhum componente do sistema executa ordens ou promete resultado
- O guardrail `can_promote_trade=False` força o posicionamento correto

**Implicações deste posicionamento:**
- Toda comunicação usa linguagem de "oportunidade", nunca "sinal" ou "recomendação"
- O app sempre mostra disclaimer de risco antes de exibir detalhes
- O nome do produto evita termos como "signals" se possível ([CONFIRMAR] — ver Seção 13)
- O marketing enfatiza o motor técnico determinístico, não resultados de trading
- Precificação baseada em acesso à tecnologia (planos), não em performance dos setups

---

## 3. Linguagem Oficial do Produto

### 3.1 Termos Permitidos

| Termo | Quando usar |
|-------|------------|
| Oportunidade técnica identificada | Alerta push, lista de oportunidades |
| Zona de interesse detectada | Alerta push, descrição do setup |
| Contexto SMC favorável | Detalhe da oportunidade |
| Setup em formação | Quando preço está se aproximando da zona |
| Região operacional | Descrição da zona de entrada/stop/alvos |
| Alerta de aproximação | Quando preço está próximo da zona mas ainda não chegou |
| Cenário monitorado | Quando condições estão se formando mas gates ainda não passaram todos |
| Estudo técnico | Documento canônico (TechnicalTruthEnvelopeV2) |
| Análise determinística | Descrição do motor SMC |
| Taxa histórica de acerto | Hit rates (NUNCA "probabilidade") |
| Scanner de oportunidades | Nome do produto/módulo |
| Plataforma de apoio à decisão | Descrição geral do sistema |

### 3.2 Termos Proibidos

| Termo Proibido | Motivo |
|----------------|--------|
| Sinal de entrada / sinal certeiro | Sugere recomendação de trade; implicação regulatória |
| Lucro garantido / resultado garantido | Falso — análise técnica não garante resultado |
| Probabilidade de ganho / chance de acerto | Guardrail `probabilidade_proibida=True` — usa-se "taxa histórica" |
| Entrada garantida / operação recomendada | Viola princípio de que a decisão é do trader |
| Compre agora / venda agora | Ordem direta — o sistema não dá ordens |
| Ganho esperado / retorno projetado | Implica previsão de lucro — o sistema não prevê preços |
| Setup infalível / estratégia vencedora | Linguagem de marketing enganosa |
| Robô de trading / robô investidor | O sistema não executa ordens automaticamente |
| Consultoria de investimento | Implica registro na CVM como consultor |
| Copy trading | O sistema não replica operações |

### 3.3 Exemplos de Linguagem Correta

**Alerta Push (máximo ~100 caracteres):**

```
WINFUT — Oportunidade técnica SMC detectada. Zona FVG ativa em M5. Toque para ver contexto.
```

```
XAUUSDm — Zona de interesse em formação. Preço se aproxima de região operacional. Toque para detalhes.
```

```
BTCUSDm — Alerta de aproximação. Setup SMC sendo monitorado em M5. Verifique o contexto.
```

**Detalhe da Oportunidade (app):**

```
Contexto técnico: O scanner identificou uma zona FVG (Fair Value Gap) ativa 
em WINFUT M5 com alinhamento de estrutura (BOS) em M15 e viés de alta em H1. 
Confluência Elliott: Onda 3 de impulso em formação. Wyckoff: Fase Markup.

Este alerta representa uma oportunidade técnica identificada pelo motor SMC 
determinístico do Maximus Trader. A decisão de operar é exclusivamente sua. 
Resultados passados não garantem resultados futuros.
```

**Dashboard/Web:**

```
Oportunidades técnicas ativas — detectadas pelo scanner SMC determinístico.
Estes não são sinais de compra ou venda. São condições técnicas de mercado 
identificadas automaticamente. Operar ou não é decisão do trader.
```

---

## 4. Escopo do MVP

### 4.1 MVP do Sistema Local

**Obrigatório (entra no MVP):**

| Item | Status Atual | Ação Necessária |
|------|-------------|-----------------|
| Coleta MT5 B3 + Forex funcionando | ⚠️ B3 OK, Forex em loop restart (R1 da Fase 0) | Corrigir `smc-forex-robot` antes da Fase 2 |
| SMC Engine V2 processando e persistindo | ✅ STABLE_FROZEN_V2 — 164 testes | Nenhuma — manter congelado |
| Elliott Wave + Wyckoff funcionando | ✅ Estáveis — 7 sanity rules | Nenhuma |
| Study Gateway gerando estudos canônicos | ✅ PRONTO — 123 testes | Nenhuma |
| Opportunity Scanner detectando oportunidades | ✅ ATIVO — 306 testes, 10+ gates | Nenhuma |
| Sync com MaximusTrader via HMAC | ⚠️ Funcional mas não automático | Implementar sync watcher (Fase 2) |
| Guardrails ativos | ✅ Todos os 8 invariants ativos | Manter — não desativar |
| Logs básicos de operação | ⚠️ Logs existem mas não estruturados | Implementar JSON structured logging (Fase 3) |
| Retry/heartbeat | ❌ Não implementado | Implementar na Fase 3 |

**Não entra no MVP:**

| Item | Motivo |
|------|--------|
| Execução automática de ordens | Guardrail `shadow_only=True` — bloqueado permanentemente no MVP |
| Promoção automática de configuração | Guardrail `apply_automatically=False` |
| Alterar pipeline SMC V2 | Está `STABLE_FROZEN_V2` — mudanças exigem processo formal |
| IA decidindo trade | Guardrail `llm_decision_used=False` |
| Sinais ao vivo (`can_promote_trade`) | Só após beta fechado validado |
| iOS (Sistema Local não tem relação com iOS) | Não aplicável ao Sistema Local |

### 4.2 MVP do MaximusTrader Backend

**Obrigatório (entra no MVP):**

| Item | Status Atual | Ação Necessária |
|------|-------------|-----------------|
| Receber dados do Sistema Local via HMAC | ✅ Funcional — 6 endpoints sync + scanner | Nenhuma — adicionar checksum (Fase 2) |
| Validar HMAC (VerifySyncHmac) | ✅ Implementado — timestamp ±5min, assinatura SHA-256 | Nenhuma |
| Persistir candles, zonas, Elliott, Wyckoff, estudos | ✅ 7 tabelas sync_* + migrations | Nenhuma |
| Persistir oportunidades do scanner | ✅ `scanner_alerts` + `opportunities` | Nenhuma |
| API mobile funcional (auth + opportunities + preferences) | ✅ 9 endpoints mobile implementados | Adicionar endpoint dashboard se necessário (Fase 5) |
| Autenticação (Sanctum + 2FA TOTP) | ✅ Funcional | Nenhuma |
| FCM push (FirebasePushService + Job) | ✅ Implementado — OAuth JWT, dry-run, preferências | Nenhuma — não ativar para usuários reais sem validação |
| Controle de usuários e planos (admin) | ✅ CRUD + Spatie roles + planos | Nenhuma |
| Webhooks de pagamento | ✅ 7 provedores implementados | Testar em produção com pelo menos 1 provedor |
| Health endpoint de sync | ❌ Não implementado | Implementar na Fase 3 |

**Não entra no MVP:**

| Item | Motivo |
|------|--------|
| Recalcular SMC no backend | Responsabilidade exclusiva do Sistema Local |
| Duplicar lógica de scoring | Fonte da verdade é o Sistema Local |
| Features comerciais complexas (cupons, trial automático) | Pós-MVP |
| Chat/suporte integrado ao site | Ferramenta externa no MVP |

### 4.3 MVP do MaximusTrader Frontend

**Obrigatório (entra no MVP):**

| Item | Status Atual | Ação Necessária |
|------|-------------|-----------------|
| Dashboard administrativo | ✅ 15 páginas existentes | Nenhuma estrutural |
| Gráfico com candles + zonas SMC | ⚠️ Funcional, mas 3 bibliotecas coexistem | Consolidar em lightweight-charts (Fase 4) |
| Lista de oportunidades no admin | ✅ Existente | Verificar se dados reais aparecem |
| Painel de saúde do sistema | ❌ Não implementado | Criar na Fase 3 |
| Error Boundary global | ❌ Não implementado | Criar na Fase 4 |
| Visualização confiável dos overlays SMC | ⚠️ SMC overlay engine implementado (8 módulos) | Validar com dados reais (Fase 4) |
| Landing page pública | ✅ Existente | Atualizar conforme decisões de produto |
| Login/Register com 2FA | ✅ Existente | Nenhuma |

**Não entra no MVP:**

| Item | Motivo |
|------|--------|
| 3 bibliotecas de gráfico | Consolidar em 1 — remover ApexCharts, depreciar Plotly |
| Recalcular indicadores no navegador | Fonte da verdade é a API |
| Replay histórico avançado | Funcionalidade premium — validar depois |
| Recursos visuais que prejudiquem performance | Performance > estética no MVP |
| Internacionalização (i18n) | Apenas pt-BR no MVP |
| Modo escuro/claro toggle | Apenas dark mode (já implementado) |

### 4.4 MVP do App Android

**Obrigatório (entra no MVP):**

| Item | Status Atual | Ação Necessária |
|------|-------------|-----------------|
| Login com 2FA | ✅ Implementado | Nenhuma |
| Lista de oportunidades ativas | ✅ Implementado | Nenhuma |
| Detalhe da oportunidade (entrada/stop/alvos) | ✅ Implementado | Nenhuma |
| Push notification (FCM) | ✅ Implementado | Validar com dados reais (Fase 6) |
| Deep link para oportunidade | ✅ Implementado | Validar fluxo completo (Fase 6) |
| Preferências básicas (push on/off, ativos on/off) | ✅ Implementado | Expandir para múltiplos ativos (Fase 5) |
| Disclaimer de risco visível | ✅ Existente | Revisar texto conforme Seção 3 |
| Logout | ✅ Implementado | Nenhuma |
| Error handling em todas as telas | ✅ Implementado | Nenhuma |

**Desejável (entra se houver tempo, ou logo após MVP):**

| Item | Prioridade | Impacto se não tiver |
|------|-----------|---------------------|
| Dashboard (cards resumo) | P1 | Usuário não vê status geral do mercado |
| Histórico de oportunidades | P1 | Usuário não consegue revisar oportunidades passadas |
| Conta/Perfil | P2 | Usuário não gerencia dispositivos nem troca senha |
| Preferências avançadas (quiet hours, radar states) | P1 | Usuário recebe alertas de madrugada; não filtra por tipo de setup |
| DTOs/Mappers/UseCases tipados | P1 | Código menos seguro; ausência não bloqueia funcionalidade |

**Não entra no MVP:**

| Item | Motivo |
|------|--------|
| iOS | Android primeiro; iOS exige `iosMain` completo + testes |
| Cache offline de dados de mercado | App é canal de consulta; exige conexão |
| Gráficos no app | Site é o canal de análise visual aprofundada |
| Execução de ordens | Bloqueado permanentemente |
| Cálculo de indicadores local | Violação de arquitetura — app não calcula |

---

## 5. Ativos e Timeframes do MVP

### 5.1 Ativos

**Decisão: 3 ativos para MVP interno, expandindo para 6 no beta fechado.**

| Ativo | MVP Interno | Beta Fechado | Produção | Motivo |
|-------|------------|-------------|----------|--------|
| **WINFUT** | ✅ Sim | ✅ Sim | ✅ Sim | Principal ativo B3; maior liquidez; minicontrato mais operado; testes existentes focam WINFUT |
| **XAUUSDm** | ✅ Sim | ✅ Sim | ✅ Sim | Ouro — alta liquidez 24h; Forex; diversifica tipo de ativo |
| **BTCUSDm** | ✅ Sim | ✅ Sim | ✅ Sim | Bitcoin — atrai público diferente; opera 24/7; marketing mais fácil |
| **WDOFUT** | ❌ Não | ✅ Sim | ✅ Sim | Segundo minicontrato B3 mais operado |
| **PETR4** | ❌ Não | ✅ Sim | ✅ Sim | Ação mais líquida da B3 |
| **VALE3** | ❌ Não | ✅ Sim | ✅ Sim | Segunda ação mais líquida |
| **ETHUSDm** | ❌ Não | ❌ Não | ✅ Sim | Cripto — menor prioridade que BTC |
| **EURUSDm** | ❌ Não | ❌ Não | ✅ Sim | Forex — menor prioridade que ouro |
| **USDJPYm** | ❌ Não | ❌ Não | ✅ Sim | Forex — menor prioridade |
| **ITUB3** | ❌ Não | ❌ Não | ✅ Sim | Ação — menor prioridade |

**Fundamento (Encontrado nos arquivos):**
- Scanner configurado para 6 ativos (WINFUT, WDOFUT, PETR4, VALE3, XAUUSDm, BTCUSDm)
- 34.072 zonas já sincronizadas com WINFUT
- Backtests executados com WINFUT 6M e multi-ativo
- Começar com 3 ativos reduz superfície de falha e permite validação mais profunda

### 5.2 Timeframes

| Timeframe | Uso no MVP | Papel |
|-----------|-----------|-------|
| **M5** | ✅ Primário | Timeframe principal para detecção de oportunidades e zonas SMC |
| **M15** | ✅ Confirmação | Confirmação estrutural (BOS/CHOCH) e alinhamento |
| **H1** | ✅ Contexto | Viés de fundo (HTF bias) e contexto macro |
| **M1** | ✅ Aproximação | Apenas para scanner de aproximação e early entry radar; não gera zonas principais |
| **H4** | ❌ Pós-MVP | Contexto avançado — entra no beta |
| **D1** | ❌ Pós-MVP | Contexto macro — entra no beta |

**Fundamento (Encontrado nos arquivos):**
- SMC Engine V2 processa M2, M5, M15, H1, H4, D1 (M1 excluído do pipeline principal)
- Estudo canônico usa MTF fusion H4/M15/M5
- M1 é usado apenas para coleta e early entry radar
- Adicionar timeframes sem necessidade aumenta volume de sync e complexidade

---

## 6. Papel Oficial de Cada Projeto no MVP

| Projeto | Papel no MVP | Entrega Obrigatória | Não Deve Fazer |
|---------|-------------|--------------------|----------------|
| **Sistema Local** (`SMC_Trader_System 7.0/`) | Motor de cálculo 24/7. Coleta dados MT5, processa análise SMC/Elliott/Wyckoff, detecta oportunidades e sincroniza com o site. Fonte da verdade de todos os dados técnicos. | Coleta 3 ativos × 3 timeframes; SMC V2 pipeline; scanner com 10+ gates; sync automático HMAC; heartbeat 60s; retry com backoff; logs JSON | Expor dados direto ao usuário final; executar ordens; usar IA como motor de decisão; alterar pipeline SMC V2 sem processo formal |
| **MaximusTrader Backend** (`MaximusTrader/backend/`) | Hub central. Recebe dados do Sistema Local, persiste no MySQL, expõe API REST para o frontend e app, gerencia usuários/planos/licenças, envia push FCM. | 43 endpoints ativos; validação HMAC + checksum; persistência sync_*; API mobile (9 endpoints); FCM push (dry-run validado); controle de planos e limites; health endpoint | Recalcular SMC ou qualquer análise técnica; tomar decisões sobre oportunidades; processar dados de mercado brutos; duplicar lógica do Sistema Local |
| **MaximusTrader Frontend** (`MaximusTrader/frontend/`) | Dashboard web para análise visual e administração. Gráfico, watchlist, admin. Canal secundário do usuário (primário é o app). | Gráfico unificado lightweight-charts com overlays SMC; painel admin funcional; painel de saúde do sistema; Error Boundary; landing page pública; testes (mínimo 10) | Recalcular zonas no navegador; duplicar lógica de scoring; usar 3 bibliotecas de gráfico; processar dados localmente sem API |
| **App Android** (`AppAndroid/MaximusTrader/`) | Canal mobile de alertas e consulta. Interface simples e rápida para o trader receber oportunidades e decidir. Produto principal para o usuário final. | Login + 2FA; push FCM; lista de oportunidades; detalhe da oportunidade; deep link; preferências básicas; disclaimer de risco; testes (mínimo 10) | Calcular análise técnica; acessar banco direto; funcionar como app de trading; decidir quais oportunidades alertar |
| **Infraestrutura** | VPS Linux 24/7 + Hostinger + Cloudflare + systemd. Manter tudo rodando com saúde visível. | Backups automáticos (MySQL VPS + Hostinger); logrotate; monitoramento de serviços systemd; restart automático em falha; Cloudflare WAF; uptime > 99% | Depender exclusivamente de MT5 Wine sem monitoramento; deploy sem backup/rollback; expor portas desnecessárias |

**Princípio arquitetural (Encontrado nos arquivos):**

```
Sistema Local: Calcula tudo, não exibe nada.
MaximusTrader: Recebe tudo, organiza, exibe e notifica.
AppAndroid: Recebe alertas, exibe contexto, não calcula nada.
```

Esta separação de responsabilidades é intencional e não deve ser violada. Nenhum frontend ou app recalcula SMC. A fonte da verdade é sempre o Sistema Local.

---

## 7. Critérios Para Beta Fechado

### 7.1 Condições Obrigatórias

Checklist para liberar acesso a 5-20 usuários reais:

**Estabilidade do Pipeline:**

- [ ] Sistema rodando **7 dias consecutivos** sem intervenção manual crítica
- [ ] Nenhum serviço crítico parado por mais de 5 minutos
- [ ] Sync funcionando automaticamente (event-driven) por 7 dias
- [ ] Heartbeat visível no painel admin, atualizado a cada 60s
- [ ] Retry com backoff testado e funcionando (simulação de falha de rede)

**Cobertura de Dados:**

- [ ] Pelo menos **1 ativo** funcionando de ponta a ponta (coleta → processamento → sync → exibição → push)
- [ ] Pelo menos **3 ativos** configurados e sincronizando (recomendado: WINFUT, XAUUSDm, BTCUSDm)
- [ ] Pelo menos **M5 + M15 + H1** processando e visíveis no gráfico
- [ ] Zonas SMC renderizando corretamente no gráfico web

**App e Notificações:**

- [ ] Push FCM validado em **dispositivo Android real**
- [ ] Deep link abrindo tela correta da oportunidade
- [ ] Login + 2FA funcionando (pelo menos 1 conta real testada)
- [ ] Lista de oportunidades ativas exibindo dados reais do scanner
- [ ] Detalhe da oportunidade com entrada/stop/alvos correto

**Linguagem e Compliance:**

- [ ] Linguagem de risco revisada em todos os canais (push, app, site)
- [ ] Disclaimer de risco visível antes de cada detalhe de oportunidade
- [ ] Termo "sinal" ou "recomendação" **ausente** de todo o conteúdo
- [ ] Política de privacidade e termos de uso publicados no site
- [ ] Texto aprovado pelo dono do produto ([CONFIRMAR])

**Operação e Monitoramento:**

- [ ] Logs de falha acessíveis (últimos 7 dias)
- [ ] Backup diário configurado e testado (pelo menos 1 restore test)
- [ ] Painel de saúde mostrando status verde para todos os indicadores
- [ ] Erros HMAC = 0 nos últimos 7 dias
- [ ] Runbook de operação documentado com procedimentos de restart, recovery e rollback

**Segurança:**

- [ ] Nenhuma ordem automática (guardrail `can_promote_trade=False` confirmado)
- [ ] Nenhuma promessa de lucro em qualquer canal
- [ ] Autenticação Sanctum + 2FA ativa
- [ ] Cloudflare WAF ativo
- [ ] Secrets (API keys, credentials) não expostos em logs ou repositório

### 7.2 Condições Desejáveis (Não Bloqueantes)

- [ ] App Dashboard implementado (cards resumo)
- [ ] Histórico de oportunidades no app
- [ ] 3 ativos no beta (não apenas 1)
- [ ] Testes E2E documentados
- [ ] Cobertura de testes frontend > 10 testes
- [ ] Cobertura de testes app > 10 testes

---

## 8. Critérios Para Produção

Checklist para liberar comercialmente:

**Todos os itens do Beta Fechado + :**

**Cobertura de Ativos e Timeframes:**

- [ ] Pelo menos **3 ativos** estáveis (WINFUT, XAUUSDm, BTCUSDm) com 30 dias de operação sem falha crítica
- [ ] M5, M15 e H1 processando e sincronizando sem atraso > 5 min
- [ ] Todos os overlays SMC (FVG, OB, BOS/CHOCH, Liquidity, BPR) renderizando corretamente

**Testes e Qualidade:**

- [ ] Testes integrados E2E passando (fluxo MT5 → App)
- [ ] Cenários de falha testados e recuperação automática validada
- [ ] Testes Python: manter baseline (717+ passando nas suites críticas)
- [ ] Testes frontend: mínimo 10 passando
- [ ] Testes app Android: mínimo 10 passando
- [ ] Performance com 1500+ zonas validada (sem crash, sem flicker)

**App:**

- [ ] Dashboard implementado e funcional
- [ ] Histórico implementado e funcional
- [ ] Conta/Perfil implementado
- [ ] Preferências avançadas implementadas (quiet hours, radar states, max pushes)
- [ ] App assinado e distribuível (Google Play Store ou APK assinado)

**Comercial e Legal:**

- [ ] Todos os 4 planos comerciais configurados (Free, Starter, Pro, Enterprise)
- [ ] Pelo menos 1 provedor de pagamento testado em produção com transação real
- [ ] Política de privacidade e termos de uso publicados
- [ ] Disclaimer de risco em todas as telas relevantes do app e site
- [ ] Textos legais aprovados ([CONFIRMAR])

**Operação:**

- [ ] Backups automáticos diários (VPS MySQL + Hostinger MySQL)
- [ ] Logs rotativos configurados (logrotate)
- [ ] Monitoramento externo ativo (UptimeRobot ou similar)
- [ ] Runbook de operação completo e testado
- [ ] Procedimento de rollback testado para cada componente
- [ ] Canal de suporte definido (email, WhatsApp ou ticket)

**Documentação:**

- [ ] OpenAPI/Swagger documentando endpoints públicos
- [ ] README atualizado refletindo estado atual (v7.0)
- [ ] Documentação de arquitetura atualizada

---

## 9. Decisões Comerciais Pendentes

| Decisão Comercial | Opções | Recomendação | Impacto | Status |
|-------------------|--------|-------------|---------|--------|
| **O que o plano Free oferece?** | (A) 1 ativo + 1 alerta/dia + M5 apenas, (B) 1 ativo + ilimitado + M5, (C) 2 ativos + básico | (A) Controla custo de push FCM; incentiva upgrade; suficiente para testar o produto | Define retenção e conversão do Free → Starter | [CONFIRMAR] |
| **Plano Starter terá quais timeframes?** | (A) M5 + M2, (B) M5 apenas, (C) M5 + M15 | (C) M5 + M15 — ter confirmação sem ter contexto H1 diferencia do Pro | Define proposta de valor de cada plano | [CONFIRMAR] |
| **Plano Pro terá quais timeframes?** | (A) M5 + M15 + H1, (B) Todos menos H4/D1, (C) Todos | (A) M5 + M15 + H1 — cobre o operacional com contexto; H4/D1 fica para Enterprise | Define upgrade path Pro → Enterprise | [CONFIRMAR] |
| **Plano Enterprise terá quais diferenciais?** | (A) H4/D1 + replay + mais ativos, (B) Tudo ilimitado, (C) API access | (A) Timeframes macro + replay + 11 ativos | Define ticket máximo e features premium | [CONFIRMAR] |
| **Haverá período de trial?** | (A) 7 dias grátis do Pro, (B) Sem trial — apenas Free, (C) 14 dias | (A) 7 dias — suficiente para usuário avaliar; barreira baixa | Define ativação e risco de churn pós-trial | [CONFIRMAR] |
| **Precificação: mensal, anual ou ambos?** | (A) Ambos com desconto anual (~15%), (B) Apenas mensal, (C) Apenas anual | (A) Ambos — padrão de mercado; anual melhora LTV e previsibilidade | Define fluxo de caixa e estrutura de planos | [CONFIRMAR] |
| **Limite de alertas por dia por plano?** | (A) Free:1, Starter:5, Pro:20, Enterprise:50, (B) Sem limite por plano, (C) Limite configurável | (A) Limites evitam abuso e segmentam planos naturalmente | Define capacidade de processamento e custo de push FCM | [CONFIRMAR] |
| **O app será incluído em todos os planos?** | (A) Sim — todos têm app, (B) App só Starter+, (C) App com features por plano | (A) App é canal principal de entrega de valor — não faz sentido restringir | Define estratégia de canal | ✅ Livre para todos |
| **Replay será recurso premium?** | (A) Sim — Pro e Enterprise, (B) Todos os planos, (C) Apenas Enterprise | (A) Pro+ — replay é análise avançada, justifica upgrade | Diferencia Pro de Starter | ✅ Pro e Enterprise |
| **Cobrança será manual ou automática?** | (A) Automática via Hotmart/Kiwify, (B) Manual (PIX/boleto), (C) Ambos | (A) Automática — webhooks já implementados para 7 provedores | Define complexidade de integração de pagamento | ✅ Automática |

---

## 10. Decisões Técnicas Pendentes

| Decisão Técnica | Opções | Recomendação | Impacto | Status |
|----------------|--------|-------------|---------|--------|
| **lightweight-charts será definitivo?** | (A) Sim — única biblioteca, (B) Sim — com Plotly fallback temporário, (C) Avaliar depois | (A) — SMC overlay engine (8 módulos) foi construído para lightweight-charts; ApexCharts removido; Plotly depreciado | Unifica manutenção, reduz bundle | ✅ DECIDIDO |
| **Plotly fica como fallback ou será removido?** | (A) Remover, (B) Manter como fallback 1 mês, (C) Manter para sempre | (B) Manter depreciado 1 mês — se lightweight-charts tiver bug crítico, fallback existe; remover após 30 dias sem incidentes | Segurança durante transição | ✅ DECIDIDO |
| **ApexCharts será removido?** | (A) Sim — já, (B) Manter legado | (A) Remover — não há componente ativo usando; apenas ocupa bundle | Reduz manutenção e tamanho do bundle | ✅ DECIDIDO |
| **MySQL VPS será fonte da verdade?** | (A) Sim — VPS é fonte, site é espelho, (B) Site como fonte | (A) — a VPS calcula; inverter criaria acoplamento e latência | Define arquitetura de sync | ✅ DECIDIDO |
| **Sync será event-driven ou cron?** | (A) Event-driven (watcher Python), (B) Cron a cada 60s, (C) Híbrido | (A) Event-driven com watcher — menor latência, detecta vela nova imediatamente | Define latência máxima de sync | ✅ DECIDIDO |
| **App precisa de cache offline?** | (A) Sim — DataStore cache, (B) Não — sempre online, (C) Cache só de oportunidades ativas | (B) Não para MVP — adiciona complexidade desnecessária; app é canal de consulta | Define arquitetura do app | ✅ DECIDIDO |
| **FCM será ativado para usuários reais quando?** | (A) No beta fechado, (B) Só na produção, (C) Já no MVP interno | (A) No beta — já implementado; precisa validação com dados reais antes de produção | Define quando usuários reais recebem push | ✅ DECIDIDO |
| **OpenAPI/Swagger obrigatório antes do beta?** | (A) Sim, (B) Não — documentação manual, (C) Parcial (só mobile) | (A) Sim — endpoints mobile são contrato com o app; documentação evita quebra | Define qualidade da documentação da API | ✅ DECIDIDO |
| **Painel de saúde: página separada ou integrado ao Dashboard?** | (A) Página admin separada, (B) Cards no Dashboard existente, (C) Ambos | (B) Cards no Dashboard admin — visibilidade imediata ao logar | Define UX do administrador | ✅ DECIDIDO |
| **Quem monitora falhas fora do horário comercial?** | (A) Apenas logs — sem alerta, (B) UptimeRobot, (C) Bot Telegram/WhatsApp | (B) UptimeRobot para MVP — gratuito, simples, cobre uptime do site | Define cobertura de monitoramento | [CONFIRMAR] |

---

## 11. Métricas de Saúde do Sistema

### 11.1 Definições Oficiais

| Métrica | 🟢 Verde (OK) | 🟡 Amarelo (Warning) | 🔴 Vermelho (Critical) | Onde aparece |
|---------|-------------|---------------------|----------------------|-------------|
| **Último sync bem-sucedido** | < 5 min | 5-15 min | > 15 min | Painel de saúde (admin) |
| **Último candle WINFUT M5** | < 6 min (1 vela + margem) | 6-20 min | > 20 min | Painel de saúde (admin) |
| **Último candle XAUUSDm M5** | < 6 min | 6-20 min | > 20 min | Painel de saúde (admin) |
| **Último candle BTCUSDm M5** | < 6 min | 6-20 min | > 20 min | Painel de saúde (admin) |
| **Status robô B3** | Running | — | Stopped/Failed | Painel de saúde (admin) + systemd |
| **Status robô Forex** | Running | — | Stopped/Failed | Painel de saúde (admin) + systemd |
| **Status scanner** | Running, última scan < 5 min | Última scan 5-15 min | Stopped ou > 15 min sem scan | Painel de saúde (admin) |
| **Status MT5 (bridges)** | Ambos bridges running | 1 bridge parado | Ambos parados | Painel de saúde (admin) |
| **Push FCM últimas 24h** | Sucesso > 95% | Sucesso 80-95% | Sucesso < 80% | Painel de saúde (admin) |
| **Erros HMAC nas últimas 24h** | 0 | 1-5 | > 5 | Painel de saúde (admin) + logs |
| **Latência do sync (ms)** | < 2000ms | 2000-5000ms | > 5000ms | Painel de saúde (admin) |
| **Fila de jobs (Laravel)** | < 10 pendentes | 10-50 pendentes | > 50 pendentes | Painel de saúde (admin) |
| **Espaço em disco VPS** | < 70% | 70-85% | > 85% | Painel de saúde (admin) |
| **Espaço em disco Hostinger** | < 70% | 70-85% | > 85% | Painel de saúde (admin) |
| **Erros 5xx na API (última hora)** | 0 | 1-10 | > 10 | Painel de saúde (admin) |

### 11.2 Frequência de Atualização

- **Heartbeat (VPS → Site):** A cada 60 segundos
- **Painel de saúde (UI):** Refresh a cada 30 segundos (polling) ou via WebSocket
- **Alertas de saúde (admin):** Notificação visual no painel quando qualquer métrica ficar 🟡 ou 🔴

---

## 12. Roadmap Reorganizado Por Produto

### Fase 1 — Alinhamento e Baseline (Em Andamento)

- [x] Relatório geral do sistema
- [x] Plano executivo com 8 fases
- [x] Baseline técnico (Fase 0)
- [x] Documento de visão de produto
- [x] Este documento de decisões de MVP
- [ ] Respostas do dono às perguntas da Seção 13
- [ ] Confirmação das decisões marcadas como [CONFIRMAR]

### Fase 2 — Pipeline Confiável (2-3 dias)

**Objetivo:** O sistema funciona sem intervenção manual.

- [ ] Corrigir `smc-forex-robot` (loop de restart)
- [ ] Implementar sync watcher event-driven (Fase 2 do Plano Executivo)
- [ ] Implementar heartbeat a cada 60s (Fase 3 do Plano Executivo)
- [ ] Implementar retry com backoff exponencial
- [ ] Implementar JSON structured logging
- [ ] Criar health endpoint (`GET /api/sync/health`)
- [ ] Criar painel de saúde básico no admin

### Fase 3 — MVP Web (3-4 dias)

**Objetivo:** Site confiável para análise e administração.

- [ ] Consolidar gráficos (remover ApexCharts, depreciar Plotly)
- [ ] Validar lightweight-charts com dados reais (1.500 zonas)
- [ ] Implementar Error Boundary global
- [ ] Painel de saúde funcional no admin
- [ ] Testes frontend (mínimo 10)

### Fase 4 — MVP Mobile (4-5 dias)

**Objetivo:** App funcional com fluxo completo de alertas.

- [ ] Dashboard no app (cards resumo)
- [ ] Histórico de oportunidades no app
- [ ] Preferências avançadas (quiet hours, radar states, max pushes)
- [ ] DTOs/Mappers/UseCases tipados
- [ ] Testes unitários no app (mínimo 10)
- [ ] Conta/Perfil no app

### Fase 5 — Validação E2E (3-4 dias)

**Objetivo:** Pipeline validado de ponta a ponta.

- [ ] Teste E2E: MT5 → VPS → Sync → Site → Push → App → Deep Link
- [ ] Cenários de falha testados (7 cenários)
- [ ] Performance validada (1500+ zonas)
- [ ] Push FCM validado em dispositivo real
- [ ] Resultados documentados

### Fase 6 — Beta Fechado (2-4 semanas)

**Objetivo:** 5-20 usuários reais usando o sistema.

- [ ] Checklist da Seção 7 completo
- [ ] Sistema rodando 7 dias sem intervenção
- [ ] Monitoramento intenso: logs, erros, feedback
- [ ] Ajustes baseados em feedback
- [ ] Linguagem e UX refinadas

### Fase 7 — Produção Assistida (1-2 semanas)

**Objetivo:** Lançamento comercial.

- [ ] Checklist da Seção 8 completo
- [ ] Deploy controlado com rollback
- [ ] 24h sem incidentes
- [ ] Runbook operacional publicado
- [ ] App distribuível (Play Store ou APK)
- [ ] Planos comerciais ativos

---

## 13. Perguntas Para o Dono Responder

Estas perguntas devem ser respondidas **antes de iniciar a Fase 2 (Pipeline Confiável)** para evitar retrabalho de posicionamento e linguagem. As decisões marcadas como [CONFIRMAR] nas seções anteriores dependem destas respostas.

```md
## Respostas do Dono do Produto

### Posicionamento

1. O produto será vendido como:
   - [ ] Scanner de oportunidades SMC (recomendado)
   - [ ] Sinais de trading
   - [ ] Plataforma educacional
   - [ ] Dashboard técnico avançado
   - [ ] Outro: ________

2. A linguagem dos alertas será:
   - [ ] "Oportunidade técnica identificada" (recomendado)
   - [ ] "Zona de interesse SMC detectada"
   - [ ] "Sinal de entrada"
   - [ ] "Setup em formação"
   - [ ] Outra: ________

3. O nome "Maximus Trade Signals" está confirmado ou será alterado?
   - [ ] Mantém "Maximus Trade Signals"
   - [ ] Alterar para: ________
   - Motivo: ________

### Produto e Experiência

4. O foco principal do produto para o usuário final é:
   - [ ] App mobile com alertas (site é apoio/admin)
   - [ ] Site com dashboard e gráficos (app é complemento)
   - [ ] Ambos igualmente importantes

5. O app deve ser:
   - [ ] Minimalista: alertas + detalhe (MVP mais rápido)
   - [ ] Completo: dashboard + histórico + conta + preferências (MVP mais longo)
   - [ ] Evolutivo: começa minimalista, expande no beta

6. O site deve ter área logada para trader ver gráficos?
   - [ ] Sim — trader pode acessar gráfico e watchlist via web
   - [ ] Não — site é só admin; trader usa apenas o app

### Ativos e Mercado

7. Ativos do MVP (escolha 3):
   - [ ] WINFUT
   - [ ] WDOFUT
   - [ ] XAUUSDm
   - [ ] BTCUSDm
   - [ ] PETR4
   - [ ] VALE3
   - [ ] Outros: ________

8. Timeframes que geram alertas para o usuário:
   - [ ] M5 (principal)
   - [ ] M15 (confirmação)
   - [ ] H1 (contexto)
   - [ ] H4 (contexto avançado)
   - [ ] D1 (macro)

9. Deve gerar alertas fora do horário de mercado B3?
   - [ ] Sim — Forex e Crypto operam 24h
   - [ ] Não — apenas durante o horário B3
   - [ ] Configurável pelo usuário

10. Qual threshold de conservadorismo para gerar alerta?
    - [ ] Conservador — só setups muito claros (menos alertas, mais qualidade)
    - [ ] Moderado — equilíbrio entre quantidade e qualidade
    - [ ] Relaxado — mais alertas, usuário filtra

### Comercial

11. Plano gratuito (Free) terá:
    - [ ] 1 ativo + 1 alerta/dia + M5 apenas (recomendado)
    - [ ] 1 ativo + ilimitado + M5 apenas
    - [ ] 2 ativos + M5
    - [ ] Outro: ________

12. Período de trial para planos pagos:
    - [ ] 7 dias grátis do Pro (recomendado)
    - [ ] 14 dias grátis
    - [ ] Sem trial
    - [ ] Outro: ________

13. Precificação:
    - [ ] Mensal + anual (com desconto)
    - [ ] Apenas mensal
    - [ ] Apenas anual

14. Os preços atuais estão confirmados?
    - [ ] Sim: Free R$0 / Starter R$29,90 / Pro R$59,90 / Enterprise R$99,90
    - [ ] Não — ajustar para: ________

15. Provedor de pagamento principal:
    - [ ] Hotmart (já integrado)
    - [ ] Kiwify (já integrado)
    - [ ] Stripe (já integrado)
    - [ ] MercadoPago (já integrado)
    - [ ] Outro: ________

### Operação

16. Quem monitora falhas do sistema no dia a dia?
    - [ ] O próprio dono
    - [ ] DevOps/terceiro contratado
    - [ ] Sistema automático com notificação
    - [ ] Outro: ________

17. Canal de alerta de falha para o operador:
    - [ ] Email
    - [ ] WhatsApp
    - [ ] Telegram
    - [ ] UptimeRobot
    - [ ] Apenas painel de saúde (sem notificação proativa)

18. Tempo aceitável de sync atrasado antes de alertar o operador:
    - [ ] 5 min (rigoroso)
    - [ ] 15 min (moderado — recomendado)
    - [ ] 30 min (relaxado)

19. O que fazer se o MT5 cair por mais de 1 hora?
    - [ ] Apenas logar e aguardar
    - [ ] Notificar operador para intervenção manual
    - [ ] Colocar site em modo manutenção (avisar usuários)
    - [ ] Outro: ________

20. SLA de disponibilidade esperado:
    - [ ] 99% (8h de downtime/ano)
    - [ ] 99.5% (44h de downtime/ano)
    - [ ] 99.9% (9h de downtime/ano)
    - [ ] Não definido

### Técnico

21. A decisão de usar lightweight-charts como única biblioteca de gráfico está confirmada?
    - [ ] Sim — remover ApexCharts, depreciar Plotly
    - [ ] Não — manter alternativas

22. O MySQL VPS continuará sendo a fonte da verdade dos dados?
    - [ ] Sim — VPS calcula, site espelha
    - [ ] Não — inverter arquitetura

23. Endpoints que não podem quebrar (contrato com o app):
    - [ ] /api/auth/* (login, 2FA)
    - [ ] /api/mobile/* (oportunidades, dispositivos, preferências)
    - [ ] Ambos
    - [ ] Outros: ________

24. O app precisa funcionar offline (cache de oportunidades)?
    - [ ] Sim — cache local das últimas oportunidades
    - [ ] Não — sempre online (recomendado para MVP)

25. Existe plano para iOS? Se sim, horizonte:
    - [ ] Imediato (junto com Android)
    - [ ] Após MVP Android validado (recomendado)
    - [ ] Sem previsão
    - [ ] Nunca

### Feedback e Qualidade

26. Quantos usuários no beta fechado?
    - [ ] 5 (mínimo para feedback)
    - [ ] 10 (recomendado)
    - [ ] 20 (mais feedback, mais exposição)

27. Canal de feedback dos usuários beta:
    - [ ] Grupo WhatsApp
    - [ ] Email
    - [ ] Formulário
    - [ ] Conversa direta

28. Critério para considerar o beta um sucesso:
    - [ ] Nenhum bug crítico em 7 dias
    - [ ] Usuários reportando valor real ("me ajudou em uma operação")
    - [ ] Pipeline 100% automático por 7 dias
    - [ ] Todos os itens acima
```

---

## 14. Recomendações Finais

### Posicionamento

**Recomendação:** Scanner de oportunidades SMC com contexto técnico e alertas.

**Justificativa:** Alinhado ao que já está implementado (SMC Engine V2, Scanner com 10+ gates, pipeline de alertas). Evita conflito regulatório. Posiciona o produto como ferramenta técnica, não como consultoria financeira. Mantém o trader como decisor final.

### Escopo do MVP

**Recomendação:** MVP focado em 3 ativos (WINFUT, XAUUSDm, BTCUSDm), 3 timeframes (M5, M15, H1), app como canal principal de alertas, site como apoio e administração.

**Justificativa:** Reduz superfície de falha. Permite validação profunda do pipeline antes de expandir. Foca no fluxo de valor principal: trader recebe alerta → vê contexto → decide.

### Ativos Iniciais

**Recomendação:** WINFUT, XAUUSDm, BTCUSDm.

**Justificativa:** WINFUT é o ativo mais testado (34.072 zonas sincronizadas). XAUUSDm diversifica para Forex 24h. BTCUSDm atrai público diferente e é excelente para marketing.

### Linguagem dos Alertas

**Recomendação:** "Oportunidade técnica identificada" e "Zona de interesse SMC detectada".

**Justificativa:** Evita conotação de recomendação financeira. Alinhado aos guardrails ativos (`probabilidade_proibida=True`). Transmite seriedade técnica.

### Próxima Fase Recomendada

**ANTES de qualquer código:** O dono do produto responde as 28 perguntas da Seção 13 e confirma as decisões marcadas como [CONFIRMAR].

**Depois:** Executar Fase 2 — Pipeline Confiável (sync automático, heartbeat, retry). Esta fase é independente das decisões comerciais e beneficia qualquer posicionamento de produto.

### O Que Deve Ser Bloqueado Até Decisão Explícita

- ❌ Definir nomes de planos e features finais → Aguardar respostas 11-15
- ❌ Escrever textos finais de marketing/landing page → Aguardar resposta 1-3
- ❌ Configurar provedor de pagamento principal → Aguardar resposta 15
- ❌ Publicar app na Play Store → Aguardar beta fechado concluído
- ❌ Ativar `can_promote_trade` → Bloqueado permanentemente até beta validado
- ❌ Expandir além de 3 ativos no pipeline → Aguardar beta fechado

### O Que Pode Avançar Sem Decisão

- ✅ Correção do `smc-forex-robot` (loop de restart) — independe de produto
- ✅ Implementação do sync watcher event-driven — infraestrutura
- ✅ Implementação de heartbeat e retry — infraestrutura
- ✅ Consolidação de gráficos (remover ApexCharts) — decisão técnica já tomada
- ✅ Testes frontend e app — qualidade, independe de produto

---

*Documento gerado em 16 de Junho de 2026 com base na análise do relatório geral, plano executivo, baseline técnico e visão de produto.*

*Decisões marcadas como ✅ DECIDIDO foram tomadas com base em análise técnica dos arquivos do projeto. Decisões marcadas como [CONFIRMAR] dependem de input do dono do produto.*

*Inferências técnicas estão fundamentadas nos trechos relevantes dos documentos de referência. Dados confirmados nos arquivos estão sinalizados como "Encontrado nos arquivos".*
