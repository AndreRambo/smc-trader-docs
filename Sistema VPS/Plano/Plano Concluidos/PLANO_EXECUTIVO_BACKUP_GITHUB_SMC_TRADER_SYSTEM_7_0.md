# PLANO EXECUTIVO — BACKUP E SINCRONIZAÇÃO DOS PROJETOS SMC TRADER SYSTEM 7.0 NO GITHUB

**Projeto:** SMC Trader System 7.0  
**Versão do plano:** 1.0  
**Objetivo:** Criar backups Git privados, seguros e sincronizados dos projetos Sistema Local, MaximusTrader, App Android e fontes/configurações próprias do MT5.  
**Ambiente:** VPS Linux, usuário `bimaq`  
**Execução:** Programador humano ou IA de código com acesso ao terminal da VPS  
**Status inicial:** Git e GitHub CLI já instalados na VPS  

---

# 0. OBJETIVO FINAL

Ao concluir este plano, deverão existir quatro repositórios privados no GitHub:

| Repositório | Diretório local | Conteúdo |
|---|---|---|
| `smc-trader-system-7-local` | `/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0` | Sistema Local, Technical Engine, serviços, migrations e documentação |
| `maximus-trader-web` | `/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader` | Backend Laravel e frontend React |
| `maximus-trader-android` | `/home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid` | Aplicativo Kotlin Multiplatform |
| `smc-mt5-infra` | `/home/bimaq/projetos/SMC_Trader_System_7_0/MT5Backup` | Fontes MQL5, presets, templates e documentação sanitizada |

Também deverão existir:

- backup local comprimido antes das alterações;
- `.gitignore` seguro em cada projeto;
- auditoria de secrets antes do primeiro push;
- remote `github-backup` configurado;
- primeiro push validado;
- clone de restauração testado;
- script de sincronização automática;
- serviço e timer `systemd`;
- relatório final de execução.

---

# 1. REGRAS DE SEGURANÇA INVIOLÁVEIS

## 1.1 Nunca enviar ao GitHub

Proibido versionar:

```text
.env
.env.*
settings.json com secrets
passwords
tokens
API keys
HMAC secrets
chaves SSH
service account Firebase
google-services.json
keystore Android
local.properties
dumps de banco
banco SQLite
logs
histórico de mercado
Wine prefix completo
instalação completa do MT5
executáveis do MT5
DLLs
arquivos .ex5 compilados
venv
node_modules
vendor
builds
APK
AAB
cache
backups compactados
```

## 1.2 GitHub não substitui backup completo

O GitHub guardará:

- código;
- documentação;
- migrations;
- configurações de exemplo;
- fontes MQL5;
- templates e presets sanitizados.

O GitHub não guardará:

- banco MySQL;
- credenciais;
- arquivos operacionais;
- storage de gráficos;
- secrets;
- Wine prefix;
- configuração real de contas MT5.

## 1.3 Repositórios privados

Todos os quatro repositórios devem ser criados com:

```text
visibility = private
```

## 1.4 Não usar `git push --force`

É proibido executar:

```bash
git push --force
git push -f
```

sem autorização explícita do responsável pelo projeto.

## 1.5 Não apagar arquivos locais

Durante este plano:

- não apagar `.env`;
- não apagar `settings.json`;
- não apagar credenciais;
- não apagar builds;
- apenas ignorar arquivos no Git;
- usar `git rm --cached` somente para remover do índice, nunca do disco.

## 1.6 Não executar `git pull` automático na VPS

O script de sincronização automática deve somente:

```text
git add
git commit
git push
```

Não deve executar `git pull` automático para evitar merges inesperados na VPS.

---

# 2. VARIÁVEIS PADRÃO

Executar:

```bash
export ROOT="/home/bimaq/projetos/SMC_Trader_System_7_0"
export LOCAL_REPO="$ROOT/SMC_Trader_System 7.0"
export WEB_REPO="$ROOT/MaximusTrader"
export ANDROID_REPO="$ROOT/AppAndroid"
export MT5_REPO="$ROOT/MT5Backup"
export STAMP="$(date +%Y%m%d_%H%M%S)"
export BACKUP_DIR="/home/bimaq/backups/github_preparacao_$STAMP"
```

Validar:

```bash
printf '%s\n' "$ROOT" "$LOCAL_REPO" "$WEB_REPO" "$ANDROID_REPO" "$MT5_REPO" "$BACKUP_DIR"
```

---

# 3. FASE 0 — PRÉ-CHECAGEM

## 3.1 Verificar ferramentas

```bash
git --version
gh --version
ssh -V
tar --version
```

## 3.2 Verificar espaço em disco

```bash
df -h /
du -sh "$ROOT"
```

Bloquear execução se:

```text
espaço livre < 10 GB
```

## 3.3 Verificar permissões

```bash
whoami
id
ls -ld "$ROOT"
```

Esperado:

```text
usuário = bimaq
```

## 3.4 Verificar autenticação GitHub

```bash
gh auth status
```

Se não autenticado:

```bash
gh auth login --hostname github.com --git-protocol ssh --web
```

Escolher:

```text
GitHub.com
SSH
Login with a web browser
```

Depois:

```bash
gh auth status
ssh -T git@github.com
```

Obter username:

```bash
export OWNER="$(gh api user --jq .login)"
echo "$OWNER"
```

Bloquear execução se `OWNER` estiver vazio:

```bash
test -n "$OWNER" || {
  echo "ERRO: usuário GitHub não identificado."
  exit 1
}
```

## 3.5 Configurar identidade Git

```bash
git config --global user.name "André Queiroz"
git config --global user.email "nicolasdrumond51@gmail.com"
git config --global init.defaultBranch main
```

Validar:

```bash
git config --global --list
```

## 3.6 Localizar repositórios Git existentes

```bash
find "$ROOT" -maxdepth 4 -type d -name ".git" -print
```

Verificar cada projeto:

```bash
git -C "$LOCAL_REPO" rev-parse --is-inside-work-tree 2>/dev/null || echo "Sistema Local sem Git"
git -C "$WEB_REPO" rev-parse --is-inside-work-tree 2>/dev/null || echo "MaximusTrader sem Git"
git -C "$ANDROID_REPO" rev-parse --is-inside-work-tree 2>/dev/null || echo "AppAndroid sem Git"
```

Listar remotes:

```bash
git -C "$LOCAL_REPO" remote -v 2>/dev/null || true
git -C "$WEB_REPO" remote -v 2>/dev/null || true
git -C "$ANDROID_REPO" remote -v 2>/dev/null || true
```

## 3.7 Critérios de conclusão da Fase 0

- [ ] Git funciona.
- [ ] GitHub CLI funciona.
- [ ] VPS autenticada.
- [ ] SSH com GitHub validado.
- [ ] Username GitHub obtido.
- [ ] Espaço em disco suficiente.
- [ ] Diretórios confirmados.
- [ ] Repositórios existentes identificados.
- [ ] Remotes existentes registrados.

---

# 4. FASE 1 — BACKUP LOCAL PRÉ-GIT

## 4.1 Criar diretório seguro

```bash
umask 077
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
```

## 4.2 Backup do Sistema Local

```bash
tar \
  --exclude='venv' \
  --exclude='.venv' \
  --exclude='__pycache__' \
  --exclude='runtime' \
  --exclude='tmp_runtime' \
  --exclude='logs' \
  --exclude='backups' \
  --exclude='*.log' \
  -C "$ROOT" \
  -czf "$BACKUP_DIR/sistema_local.tar.gz" \
  "SMC_Trader_System 7.0"
```

## 4.3 Backup do site

```bash
tar \
  --exclude='backend/vendor' \
  --exclude='backend/storage/logs' \
  --exclude='backend/storage/framework/cache' \
  --exclude='frontend/node_modules' \
  --exclude='frontend/dist' \
  --exclude='*.log' \
  -C "$ROOT" \
  -czf "$BACKUP_DIR/maximus_trader_web.tar.gz" \
  "MaximusTrader"
```

## 4.4 Backup do App Android

```bash
tar \
  --exclude='.gradle' \
  --exclude='.idea' \
  --exclude='build' \
  --exclude='local.properties' \
  -C "$ROOT" \
  -czf "$BACKUP_DIR/app_android.tar.gz" \
  "AppAndroid"
```

## 4.5 Verificar os arquivos

```bash
ls -lh "$BACKUP_DIR"
sha256sum "$BACKUP_DIR"/*.tar.gz > "$BACKUP_DIR/SHA256SUMS.txt"
cat "$BACKUP_DIR/SHA256SUMS.txt"
```

## 4.6 Testar integridade

```bash
tar -tzf "$BACKUP_DIR/sistema_local.tar.gz" >/dev/null
tar -tzf "$BACKUP_DIR/maximus_trader_web.tar.gz" >/dev/null
tar -tzf "$BACKUP_DIR/app_android.tar.gz" >/dev/null
```

## 4.7 Critérios de conclusão da Fase 1

- [ ] Três arquivos `.tar.gz` criados.
- [ ] Hashes SHA-256 gerados.
- [ ] Integridade validada.
- [ ] Permissão do backup restrita.
- [ ] Nenhum backup enviado ao GitHub.

---

# 5. FASE 2 — `.gitignore` DO SISTEMA LOCAL

## 5.1 Entrar no projeto

```bash
cd "$LOCAL_REPO"
```

## 5.2 Fazer backup do `.gitignore`

```bash
cp .gitignore ".gitignore.antes_backup_github_$STAMP" 2>/dev/null || true
```

## 5.3 Adicionar regras

```bash
cat >> .gitignore <<'EOF'

# ==========================================================
# GITHUB BACKUP — SISTEMA LOCAL
# ==========================================================

# Secrets
.env
.env.*
!.env.example
!.env.*.example
settings.json
secrets.json
*.pem
*.key
*.p12
*.pfx
*.credentials.json
**/firebase-credentials.json
**/service-account*.json
**/google-services.json

# Python
venv/
.venv/
env/
__pycache__/
*.py[cod]
.pytest_cache/
.mypy_cache/
.ruff_cache/
.coverage
htmlcov/

# Runtime
runtime/
tmp_runtime/
logs/
*.log
*.pid
screenlog.*
nohup.out

# Banco e dumps
*.sql
*.sql.gz
*.dump
*.db
*.sqlite
*.sqlite3

# Backups e pacotes
backups/
baseline_backups/
*.tar
*.tar.gz
*.tgz
*.zip
*.7z

# Node
node_modules/
dist/
build/
coverage/

# IDE
.idea/
.vscode/
*.swp
*.swo

# Sistema
.DS_Store
Thumbs.db
EOF
```

## 5.4 Criar arquivo de configuração de exemplo

Se existir `settings.json`:

```bash
cp settings.json settings.example.json
```

Abrir e sanitizar:

```bash
nano settings.example.json
```

Remover valores reais de:

```text
password
db_password
api_key
token
secret
hmac
ssh_password
firebase
private_key
client_secret
```

Substituir por:

```text
CHANGE_ME
```

## 5.5 Validar ignore

```bash
git check-ignore -v .env 2>/dev/null || true
git check-ignore -v settings.json 2>/dev/null || true
```

---

# 6. FASE 3 — `.gitignore` DO MAXIMUSTRADER

## 6.1 Entrar no projeto

```bash
cd "$WEB_REPO"
```

## 6.2 Fazer backup do `.gitignore`

```bash
cp .gitignore ".gitignore.antes_backup_github_$STAMP" 2>/dev/null || true
```

## 6.3 Adicionar regras

```bash
cat >> .gitignore <<'EOF'

# ==========================================================
# GITHUB BACKUP — MAXIMUSTRADER
# ==========================================================

# Laravel secrets
backend/.env
backend/.env.*
!backend/.env.example
!backend/.env.*.example
backend/storage/firebase-credentials.json
backend/storage/*service-account*.json
backend/auth.json
backend/*.pem
backend/*.key

# Laravel runtime
backend/vendor/
backend/storage/logs/
backend/storage/framework/cache/data/
backend/storage/framework/sessions/
backend/storage/framework/views/
backend/bootstrap/cache/*.php
backend/.phpunit.cache/
backend/coverage/

# Frontend
frontend/.env
frontend/.env.*
!frontend/.env.example
frontend/node_modules/
frontend/dist/
frontend/build/
frontend/coverage/

# Logs e bancos
*.log
*.sql
*.sql.gz
*.dump
*.sqlite
*.sqlite3

# IDE
.idea/
.vscode/

# Sistema
.DS_Store
Thumbs.db
EOF
```

## 6.4 Validar arquivos de exemplo

Confirmar:

```bash
ls -la backend/.env.example 2>/dev/null || true
ls -la frontend/.env.example 2>/dev/null || true
```

Criar exemplos sanitizados caso não existam.

---

# 7. FASE 4 — `.gitignore` DO APP ANDROID

## 7.1 Entrar no projeto

```bash
cd "$ANDROID_REPO"
```

## 7.2 Fazer backup do `.gitignore`

```bash
cp .gitignore ".gitignore.antes_backup_github_$STAMP" 2>/dev/null || true
```

## 7.3 Adicionar regras

```bash
cat >> .gitignore <<'EOF'

# ==========================================================
# GITHUB BACKUP — APP ANDROID/KMP
# ==========================================================

.gradle/
**/build/
build/
.idea/
*.iml
local.properties

# Assinatura
*.jks
*.keystore
keystore.properties
signing.properties

# Secrets
.env
.env.*
secrets.properties
**/google-services.json
**/firebase-credentials.json
*.pem
*.key
*.p12
*.pfx

# Kotlin e Native
.kotlin/
konan/
.cxx/
.externalNativeBuild/

# Pacotes
*.apk
*.aab
*.apks

# Logs
*.log

# Sistema
.DS_Store
Thumbs.db
EOF
```

## 7.4 Validar

```bash
git check-ignore -v local.properties 2>/dev/null || true
find . -name google-services.json -print
```

---

# 8. FASE 5 — AUDITORIA DE SECRETS E ARQUIVOS GRANDES

Executar separadamente em:

```text
$LOCAL_REPO
$WEB_REPO
$ANDROID_REPO
```

## 8.1 Script de auditoria

Criar:

```bash
mkdir -p /home/bimaq/bin
nano /home/bimaq/bin/audit-git-backup.sh
```

Conteúdo:

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${1:?Uso: audit-git-backup.sh /caminho/do/repo}"

cd "$REPO"

echo "=== REPOSITÓRIO ==="
pwd

echo
echo "=== ARQUIVOS SENSÍVEIS EXISTENTES ==="
find . \
  -type f \
  \( \
    -name ".env" -o \
    -name ".env.*" -o \
    -name "*.pem" -o \
    -name "*.key" -o \
    -name "*.p12" -o \
    -name "*.pfx" -o \
    -name "*.jks" -o \
    -name "*.keystore" -o \
    -name "firebase-credentials.json" -o \
    -name "google-services.json" -o \
    -name "local.properties" \
  \) \
  -not -path "./.git/*" \
  -print

echo
echo "=== PADRÕES SUSPEITOS ==="
grep -RInE \
  --exclude-dir=.git \
  --exclude-dir=venv \
  --exclude-dir=.venv \
  --exclude-dir=node_modules \
  --exclude-dir=vendor \
  --exclude='*.log' \
  '(password|passwd|api[_-]?key|private[_-]?key|client[_-]?secret|hmac[_-]?secret|ssh[_-]?password|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY)' \
  . | head -n 300 || true

echo
echo "=== ARQUIVOS MAIORES QUE 50 MB ==="
find . \
  -type f \
  -size +50M \
  -not -path "./.git/*" \
  -printf '%s %p\n' |
sort -nr |
numfmt --field=1 --to=iec || true
```

Permissão:

```bash
chmod 700 /home/bimaq/bin/audit-git-backup.sh
```

Executar:

```bash
/home/bimaq/bin/audit-git-backup.sh "$LOCAL_REPO"
/home/bimaq/bin/audit-git-backup.sh "$WEB_REPO"
/home/bimaq/bin/audit-git-backup.sh "$ANDROID_REPO"
```

Salvar resultados:

```bash
mkdir -p "$BACKUP_DIR/audits"

/home/bimaq/bin/audit-git-backup.sh "$LOCAL_REPO" \
  > "$BACKUP_DIR/audits/sistema_local.txt"

/home/bimaq/bin/audit-git-backup.sh "$WEB_REPO" \
  > "$BACKUP_DIR/audits/maximus_web.txt"

/home/bimaq/bin/audit-git-backup.sh "$ANDROID_REPO" \
  > "$BACKUP_DIR/audits/android.txt"
```

## 8.2 Bloqueios

Não prosseguir se houver no staged Git:

```text
.env
password real
private key
service account
google-services.json
keystore
arquivo > 50 MB sem justificativa
dump de banco
```

---

# 9. FASE 6 — CRIAR REPOSITÓRIOS PRIVADOS NO GITHUB

## 9.1 Verificar se já existem

```bash
gh repo view "$OWNER/smc-trader-system-7-local" >/dev/null 2>&1 \
  && echo "Já existe: smc-trader-system-7-local" || true

gh repo view "$OWNER/maximus-trader-web" >/dev/null 2>&1 \
  && echo "Já existe: maximus-trader-web" || true

gh repo view "$OWNER/maximus-trader-android" >/dev/null 2>&1 \
  && echo "Já existe: maximus-trader-android" || true

gh repo view "$OWNER/smc-mt5-infra" >/dev/null 2>&1 \
  && echo "Já existe: smc-mt5-infra" || true
```

## 9.2 Criar somente os ausentes

```bash
gh repo view "$OWNER/smc-trader-system-7-local" >/dev/null 2>&1 ||
gh repo create "$OWNER/smc-trader-system-7-local" \
  --private \
  --description "Sistema Local e Technical Engine do SMC Trader System 7.0"

gh repo view "$OWNER/maximus-trader-web" >/dev/null 2>&1 ||
gh repo create "$OWNER/maximus-trader-web" \
  --private \
  --description "Backend Laravel e frontend React do MaximusTrader"

gh repo view "$OWNER/maximus-trader-android" >/dev/null 2>&1 ||
gh repo create "$OWNER/maximus-trader-android" \
  --private \
  --description "Aplicativo Android Kotlin Multiplatform do MaximusTrader"

gh repo view "$OWNER/smc-mt5-infra" >/dev/null 2>&1 ||
gh repo create "$OWNER/smc-mt5-infra" \
  --private \
  --description "Fontes, presets e infraestrutura sanitizada do MetaTrader 5"
```

## 9.3 Validar visibilidade

```bash
gh repo view "$OWNER/smc-trader-system-7-local" --json name,visibility
gh repo view "$OWNER/maximus-trader-web" --json name,visibility
gh repo view "$OWNER/maximus-trader-android" --json name,visibility
gh repo view "$OWNER/smc-mt5-infra" --json name,visibility
```

Esperado:

```text
visibility = PRIVATE
```

---

# 10. FASE 7 — PUBLICAR O SISTEMA LOCAL

## 10.1 Inicializar Git se necessário

```bash
cd "$LOCAL_REPO"
test -d .git || git init -b main
```

## 10.2 Preservar remotes existentes

Listar:

```bash
git remote -v
```

Adicionar remote exclusivo:

```bash
git remote get-url github-backup >/dev/null 2>&1 ||
git remote add github-backup \
  "git@github.com:$OWNER/smc-trader-system-7-local.git"
```

## 10.3 Remover arquivos sensíveis apenas do índice

```bash
git rm -r --cached \
  .env \
  venv \
  .venv \
  runtime \
  tmp_runtime \
  logs \
  backups \
  settings.json \
  2>/dev/null || true
```

## 10.4 Staging

```bash
git add -A
git status --short
```

## 10.5 Auditoria do staged

```bash
git diff --cached --name-only
```

Bloquear se aparecer arquivo sensível.

## 10.6 Commit

```bash
git diff --cached --quiet ||
git commit -m "backup: snapshot inicial do Sistema Local na VPS"
```

## 10.7 Tag

```bash
git tag -a "backup-antes-fase-a-$(date +%Y%m%d)" \
  -m "Backup do Sistema Local antes da Fase A" \
  2>/dev/null || true
```

## 10.8 Push

```bash
git push -u github-backup HEAD
git push github-backup --tags
```

Não executar `--all` sem revisar branches existentes.

## 10.9 Validar

```bash
git remote -v
git status
gh repo view "$OWNER/smc-trader-system-7-local"
```

---

# 11. FASE 8 — PUBLICAR O MAXIMUSTRADER

```bash
cd "$WEB_REPO"
test -d .git || git init -b main

git remote get-url github-backup >/dev/null 2>&1 ||
git remote add github-backup \
  "git@github.com:$OWNER/maximus-trader-web.git"

git rm -r --cached \
  backend/.env \
  backend/vendor \
  backend/storage/logs \
  frontend/node_modules \
  frontend/dist \
  2>/dev/null || true

git add -A
git status --short
git diff --cached --name-only

git diff --cached --quiet ||
git commit -m "backup: snapshot inicial do MaximusTrader Web na VPS"

git tag -a "backup-antes-fase-a-$(date +%Y%m%d)" \
  -m "Backup do site antes da Fase A" \
  2>/dev/null || true

git push -u github-backup HEAD
git push github-backup --tags

git status
gh repo view "$OWNER/maximus-trader-web"
```

---

# 12. FASE 9 — PUBLICAR O APP ANDROID

```bash
cd "$ANDROID_REPO"
test -d .git || git init -b main

git remote get-url github-backup >/dev/null 2>&1 ||
git remote add github-backup \
  "git@github.com:$OWNER/maximus-trader-android.git"

git rm -r --cached \
  .gradle \
  .idea \
  local.properties \
  2>/dev/null || true

find . -type d -name build -prune -exec git rm -r --cached {} \; \
  2>/dev/null || true

find . -name google-services.json -exec git rm --cached {} \; \
  2>/dev/null || true

git add -A
git status --short
git diff --cached --name-only

git diff --cached --quiet ||
git commit -m "backup: snapshot inicial do App Android na VPS"

git tag -a "backup-antes-fase-a-$(date +%Y%m%d)" \
  -m "Backup do App Android antes da Fase A" \
  2>/dev/null || true

git push -u github-backup HEAD
git push github-backup --tags

git status
gh repo view "$OWNER/maximus-trader-android"
```

---

# 13. FASE 10 — PREPARAR E PUBLICAR A INFRAESTRUTURA MT5

## 13.1 Escopo permitido

Versionar:

```text
*.mq5
*.mqh
*.set
*.tpl
README
scripts próprios
documentação
unit files sanitizados
```

Não versionar:

```text
*.ex5
terminal64.exe
DLLs
Wine prefix
logs
histórico
cache
contas
senhas
config real
```

## 13.2 Localizar fontes

```bash
find /home/bimaq \
  -type f \
  \( \
    -iname "*.mq5" -o \
    -iname "*.mqh" -o \
    -iname "*.set" -o \
    -iname "*.tpl" \
  \) \
  -not -path "*/venv/*" \
  -not -path "*/node_modules/*" \
  -not -path "*/.git/*" \
  2>/dev/null
```

Localizar pastas MQL5:

```bash
find /home/bimaq -type d -name "MQL5" 2>/dev/null
```

## 13.3 Criar estrutura sanitizada

```bash
mkdir -p "$MT5_REPO"/{Experts,Indicators,Includes,Presets,Templates,docs}
```

## 13.4 Copiar somente arquivos próprios

A IA deve identificar os caminhos reais e apresentar a lista antes de copiar.

Exemplo:

```bash
cp -a "/CAMINHO/REAL/MQL5/Experts/BIMAQ/." \
  "$MT5_REPO/Experts/"

cp -a "/CAMINHO/REAL/MQL5/Indicators/BIMAQ/." \
  "$MT5_REPO/Indicators/"

cp -a "/CAMINHO/REAL/MQL5/Include/BIMAQ/." \
  "$MT5_REPO/Includes/"
```

Não executar cópia de diretório genérico `MQL5/` inteiro.

## 13.5 `.gitignore`

```bash
cat > "$MT5_REPO/.gitignore" <<'EOF'
*.ex5
*.exe
*.dll
*.log
*.dat
*.hcc
*.tmp
*.cache
*.key
*.pem
logs/
history/
bases/
cache/
MQL5/Files/
Wine/
.wine*/
EOF
```

## 13.6 README

```bash
cat > "$MT5_REPO/README.md" <<'EOF'
# Infraestrutura MT5 — SMC Trader System 7.0

Este repositório contém apenas arquivos próprios e sanitizados:

- Expert Advisors em código-fonte;
- includes próprios;
- indicadores próprios;
- presets;
- templates;
- documentação de instalação.

Não contém contas, senhas, logs, histórico de mercado,
terminal MT5, Wine prefix, executáveis ou credenciais.
EOF
```

## 13.7 Auditoria

```bash
/home/bimaq/bin/audit-git-backup.sh "$MT5_REPO"
```

## 13.8 Publicar

```bash
cd "$MT5_REPO"
test -d .git || git init -b main

git remote get-url github-backup >/dev/null 2>&1 ||
git remote add github-backup \
  "git@github.com:$OWNER/smc-mt5-infra.git"

git add -A
git status --short
git diff --cached --name-only

git diff --cached --quiet ||
git commit -m "backup: snapshot inicial da infraestrutura MT5"

git push -u github-backup HEAD
```

---

# 14. FASE 11 — TESTE DE RESTAURAÇÃO

## 14.1 Criar diretório temporário

```bash
export RESTORE_TEST="/tmp/teste-restauracao-github-$STAMP"
mkdir -p "$RESTORE_TEST"
```

## 14.2 Clonar

```bash
git clone \
  "git@github.com:$OWNER/smc-trader-system-7-local.git" \
  "$RESTORE_TEST/sistema-local"

git clone \
  "git@github.com:$OWNER/maximus-trader-web.git" \
  "$RESTORE_TEST/site"

git clone \
  "git@github.com:$OWNER/maximus-trader-android.git" \
  "$RESTORE_TEST/android"

git clone \
  "git@github.com:$OWNER/smc-mt5-infra.git" \
  "$RESTORE_TEST/mt5"
```

## 14.3 Validar

```bash
du -sh "$RESTORE_TEST"/*
find "$RESTORE_TEST" -maxdepth 2 -type d -name .git -print
```

Verificar que os arquivos proibidos não existem:

```bash
find "$RESTORE_TEST" \
  -type f \
  \( \
    -name ".env" -o \
    -name "google-services.json" -o \
    -name "firebase-credentials.json" -o \
    -name "*.jks" -o \
    -name "*.keystore" \
  \) \
  -print
```

Esperado:

```text
nenhum resultado
```

## 14.4 Remover clone de teste

```bash
rm -rf "$RESTORE_TEST"
```

## 14.5 Critérios

- [ ] Quatro clones concluídos.
- [ ] Repositórios não vazios.
- [ ] Código principal presente.
- [ ] Secrets ausentes.
- [ ] Arquivos grandes proibidos ausentes.
- [ ] Remoção do clone de teste concluída.

---

# 15. FASE 12 — SCRIPT DE SINCRONIZAÇÃO AUTOMÁTICA

## 15.1 Criar script

```bash
nano /home/bimaq/bin/github-backup-sync.sh
```

Conteúdo:

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

LOCK_FILE="/tmp/github-backup-sync.lock"
LOG_DIR="/home/bimaq/logs"
LOG_FILE="$LOG_DIR/github-backup-sync.log"

mkdir -p "$LOG_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || {
    echo "$(date -Is) Outro backup já está em execução." >> "$LOG_FILE"
    exit 0
}

REPOS=(
  "/home/bimaq/projetos/SMC_Trader_System_7_0/SMC_Trader_System 7.0"
  "/home/bimaq/projetos/SMC_Trader_System_7_0/MaximusTrader"
  "/home/bimaq/projetos/SMC_Trader_System_7_0/AppAndroid"
  "/home/bimaq/projetos/SMC_Trader_System_7_0/MT5Backup"
)

FORBIDDEN_REGEX='(^|/)(\.env($|\.)|.*\.(pem|key|p12|pfx|jks|keystore)|firebase-credentials\.json|google-services\.json|local\.properties|settings\.json)$'

sync_repo() {
    local repo="$1"

    echo "$(date -Is) Iniciando: $repo" >> "$LOG_FILE"

    if [[ ! -d "$repo/.git" ]]; then
        echo "$(date -Is) ERRO: sem .git em $repo" >> "$LOG_FILE"
        return 1
    fi

    cd "$repo"

    if ! git remote get-url github-backup >/dev/null 2>&1; then
        echo "$(date -Is) ERRO: remote github-backup ausente em $repo" >> "$LOG_FILE"
        return 1
    fi

    git add -A

    local forbidden
    forbidden="$(
      git diff --cached --name-only |
      grep -E "$FORBIDDEN_REGEX" |
      grep -vE '\.env(\..+)?\.example$' || true
    )"

    if [[ -n "$forbidden" ]]; then
        echo "$(date -Is) BLOQUEADO: arquivos sensíveis:" >> "$LOG_FILE"
        echo "$forbidden" >> "$LOG_FILE"
        git reset
        return 1
    fi

    local large_files=""
    while IFS= read -r file; do
        [[ -f "$file" ]] || continue

        size="$(stat -c%s "$file")"

        if (( size > 50000000 )); then
            large_files+="$size $file"$'\n'
        fi
    done < <(git diff --cached --name-only --diff-filter=ACM)

    if [[ -n "$large_files" ]]; then
        echo "$(date -Is) BLOQUEADO: arquivos maiores que 50 MB:" >> "$LOG_FILE"
        echo "$large_files" >> "$LOG_FILE"
        git reset
        return 1
    fi

    if git diff --cached --quiet; then
        echo "$(date -Is) Sem alterações: $repo" >> "$LOG_FILE"
        return 0
    fi

    git commit -m "backup(vps): sincronização automática $(date -Is)"

    git push github-backup HEAD

    echo "$(date -Is) Concluído: $repo" >> "$LOG_FILE"
}

status=0

for repo in "${REPOS[@]}"; do
    if ! sync_repo "$repo"; then
        status=1
    fi
done

exit "$status"
```

## 15.2 Permissão

```bash
chmod 700 /home/bimaq/bin/github-backup-sync.sh
```

## 15.3 Teste manual

```bash
/home/bimaq/bin/github-backup-sync.sh
```

## 15.4 Verificar log

```bash
tail -n 200 /home/bimaq/logs/github-backup-sync.log
```

## 15.5 Bloqueios

Não criar timer antes de:

- [ ] primeiro push manual aprovado;
- [ ] clone de restauração aprovado;
- [ ] script manual aprovado;
- [ ] log sem erro;
- [ ] nenhum secret enviado.

---

# 16. FASE 13 — SYSTEMD PARA SINCRONIZAÇÃO DIÁRIA

## 16.1 Serviço

Criar:

```bash
sudo nano /etc/systemd/system/smc-github-backup.service
```

Conteúdo:

```ini
[Unit]
Description=Backup dos projetos SMC Trader para GitHub
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=bimaq
ExecStart=/home/bimaq/bin/github-backup-sync.sh
Nice=10
IOSchedulingClass=best-effort
IOSchedulingPriority=7
```

## 16.2 Timer

Criar:

```bash
sudo nano /etc/systemd/system/smc-github-backup.timer
```

Conteúdo:

```ini
[Unit]
Description=Executa backup GitHub diário dos projetos SMC Trader

[Timer]
OnCalendar=*-*-* 03:10:00
Persistent=true
RandomizedDelaySec=10m
Unit=smc-github-backup.service

[Install]
WantedBy=timers.target
```

## 16.3 Ativar

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now smc-github-backup.timer
```

## 16.4 Testar

```bash
sudo systemctl start smc-github-backup.service
sudo systemctl status smc-github-backup.service --no-pager
```

## 16.5 Verificar agenda

```bash
systemctl status smc-github-backup.timer --no-pager
systemctl list-timers --all | grep github-backup
```

## 16.6 Logs

```bash
journalctl -u smc-github-backup.service -n 100 --no-pager
tail -n 100 /home/bimaq/logs/github-backup-sync.log
```

---

# 17. FASE 14 — BACKUP DE SECRETS E BANCOS FORA DO GITHUB

Esta fase é obrigatória para backup completo, mas não envia conteúdo ao GitHub.

## 17.1 Itens

```text
MySQL VPS
MySQL Hostinger
.env Sistema Local
.env Laravel
service account Firebase
google-services.json
keystore Android
settings.json
configuração de contas MT5
Wine prefix
storage de evidências
```

## 17.2 Diretório seguro

```bash
export PRIVATE_BACKUP="/home/bimaq/backups/private_$STAMP"
mkdir -p "$PRIVATE_BACKUP"
chmod 700 "$PRIVATE_BACKUP"
```

## 17.3 Dumps

Executar somente após preencher variáveis seguras.

Exemplo VPS:

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  -u "$DB_USER" \
  -p \
  "$DB_NAME" \
  | gzip > "$PRIVATE_BACKUP/mysql_vps.sql.gz"
```

Não colocar senha diretamente no comando ou no script.

## 17.4 Arquivo criptografado

Usar uma ferramenta de criptografia disponível, por exemplo `age` ou `gpg`.

Exemplo com `age`:

```bash
tar -C "$PRIVATE_BACKUP" -czf - . |
age -p > "/home/bimaq/backups/private_$STAMP.tar.gz.age"
```

A senha não deve ser salva no servidor em texto.

## 17.5 Destino externo

Copiar arquivo criptografado para:

- outro servidor;
- storage privado;
- computador local;
- mídia offline.

---

# 18. VALIDAÇÃO FINAL

Executar:

```bash
gh repo list "$OWNER" --visibility private --limit 100
```

Verificar remotes:

```bash
git -C "$LOCAL_REPO" remote -v
git -C "$WEB_REPO" remote -v
git -C "$ANDROID_REPO" remote -v
git -C "$MT5_REPO" remote -v
```

Verificar status:

```bash
git -C "$LOCAL_REPO" status --short
git -C "$WEB_REPO" status --short
git -C "$ANDROID_REPO" status --short
git -C "$MT5_REPO" status --short
```

Verificar timer:

```bash
systemctl is-enabled smc-github-backup.timer
systemctl is-active smc-github-backup.timer
systemctl list-timers --all | grep smc-github-backup
```

Verificar último log:

```bash
tail -n 200 /home/bimaq/logs/github-backup-sync.log
```

---

# 19. CRITÉRIOS DE ACEITE

O plano só pode ser marcado como concluído quando:

- [ ] VPS autenticada no GitHub.
- [ ] SSH validado.
- [ ] Quatro repositórios privados criados.
- [ ] Backup local comprimido criado.
- [ ] Hashes dos backups gerados.
- [ ] `.gitignore` seguro em cada projeto.
- [ ] Secrets revisados.
- [ ] Nenhum secret versionado.
- [ ] Nenhum arquivo proibido maior que 50 MB versionado.
- [ ] Sistema Local enviado.
- [ ] MaximusTrader enviado.
- [ ] App Android enviado.
- [ ] Fontes MT5 sanitizadas enviadas.
- [ ] Quatro clones de teste concluídos.
- [ ] Script automático validado.
- [ ] Timer systemd ativo.
- [ ] Log sem erro.
- [ ] Plano de backup privado definido.
- [ ] Relatório final criado.

---

# 20. ROLLBACK

## 20.1 Desabilitar sincronização automática

```bash
sudo systemctl disable --now smc-github-backup.timer
sudo systemctl stop smc-github-backup.service 2>/dev/null || true
```

## 20.2 Remover somente o remote de backup

Exemplo:

```bash
git -C "$LOCAL_REPO" remote remove github-backup
```

Repetir nos demais apenas se necessário.

## 20.3 Não apagar repositórios locais

Não remover `.git` sem autorização.

## 20.4 Restaurar `.gitignore`

Usar os backups:

```text
.gitignore.antes_backup_github_<timestamp>
```

## 20.5 Restaurar arquivos

Os arquivos locais não deveriam ter sido apagados. Caso necessário, usar:

```text
$BACKUP_DIR
```

---

# 21. RELATÓRIO OBRIGATÓRIO

Criar:

```text
/home/bimaq/projetos/SMC_Trader_System_7_0/BACKUP_GITHUB_EXECUCAO.md
```

Template:

```markdown
# RELATÓRIO — BACKUP GITHUB SMC TRADER SYSTEM 7.0

## 1. Data
## 2. Usuário GitHub
## 3. Diretórios processados
## 4. Repositórios criados
## 5. Visibilidade
## 6. Remotes configurados
## 7. Commits enviados
## 8. Tags enviadas
## 9. Auditoria de secrets
## 10. Arquivos excluídos pelo gitignore
## 11. Arquivos grandes encontrados
## 12. Backup local
## 13. Hashes
## 14. Teste de restauração
## 15. Script automático
## 16. Timer systemd
## 17. Logs
## 18. Erros encontrados
## 19. Correções aplicadas
## 20. Pendências
## 21. Riscos
## 22. Status final
```

Status permitido:

```text
NAO_INICIADO
EM_EXECUCAO
BLOQUEADO
CONCLUIDO_COM_RESSALVAS
CONCLUIDO
```

---

# 22. INSTRUÇÃO FINAL PARA A IA EXECUTORA

A IA deve:

1. ler este plano inteiro;
2. executar uma fase por vez;
3. mostrar os resultados antes de prosseguir;
4. não inventar paths de MT5;
5. não copiar o Wine prefix inteiro;
6. não enviar secrets;
7. não usar force push;
8. preservar remotes existentes;
9. usar `github-backup` como remote;
10. interromper em qualquer sinal de credencial real no staged Git;
11. registrar todos os comandos e outputs relevantes;
12. criar o relatório final;
13. nunca declarar sucesso sem testar clone e timer.

O resultado esperado é um backup Git privado, restaurável, auditado e sincronizado, sem exposição de credenciais.
