# PLANO DE CORREÇÃO — VITE DYNAMIC IMPORT, MIME `text/html` E DEPLOY ATÔMICO

**Projeto:** Maximus Trade  
**Domínio:** `https://maximustrade.com.br`  
**Data:** 26/06/2026  
**Erro observado:**

```text
Failed to load module script:
Expected a JavaScript-or-Wasm module script but the server responded
with a MIME type of "text/html".

Uncaught TypeError:
Failed to fetch dynamically imported module:
https://maximustrade.com.br/assets/Dashboard-CUM1NNki.js
```

Chunks também afetados:

```text
/assets/CandlestickChart-DWE7J-2F.js
/assets/Dashboard-CUM1NNki.js
```

**Status:** `DEPLOY_REPROVADO / FRONTEND_INDISPONÍVEL_PARCIALMENTE`

---

# 1. Diagnóstico

O navegador está solicitando um arquivo JavaScript:

```text
/assets/Dashboard-CUM1NNki.js
```

mas o servidor responde com HTML, provavelmente o `index.html` da SPA.

Isso normalmente acontece quando:

1. o navegador ou CDN possui um `index.html`/chunk principal antigo;
2. um novo build gerou hashes diferentes;
3. os chunks antigos foram removidos do servidor;
4. o servidor aplica fallback SPA também em `/assets/*`;
5. o arquivo ausente retorna `index.html` com HTTP 200;
6. o navegador rejeita o conteúdo porque módulos JavaScript exigem MIME JavaScript.

## Causa mais provável neste caso

```text
version skew + deploy não atômico + fallback de assets para index.html
```

O arquivo principal carregado no navegador ainda referencia:

```text
Dashboard-CUM1NNki.js
CandlestickChart-DWE7J-2F.js
```

mas esses arquivos não existem mais na pasta pública atual.

---

# 2. O que não fazer

Não corrigir apenas com:

```nginx
types {
    application/javascript js;
}
```

Isso não resolve se o conteúdo devolvido continuar sendo HTML.

Não usar:

```nginx
location / {
    try_files $uri /index.html;
}
```

sem criar uma regra anterior e específica para `/assets/`.

Não apagar a pasta antiga de assets antes de publicar todos os arquivos do novo build.

Não cachear `index.html` por um período longo.

---

# 3. Diagnóstico imediato no servidor

Executar:

```bash
curl -I https://maximustrade.com.br/assets/Dashboard-CUM1NNki.js
curl -I https://maximustrade.com.br/assets/CandlestickChart-DWE7J-2F.js
```

Resultado incorreto provável:

```text
HTTP/2 200
content-type: text/html
```

Verificar o conteúdo:

```bash
curl -s https://maximustrade.com.br/assets/Dashboard-CUM1NNki.js | head -n 10
```

Se aparecer:

```html
<!doctype html>
<html>
```

está confirmado que o servidor retornou o HTML da SPA.

## Verificar se os arquivos existem

Substituir o caminho pelo diretório real:

```bash
find /var/www/maximustrade -type f \
  \( -name 'Dashboard-CUM1NNki.js' \
  -o -name 'CandlestickChart-DWE7J-2F.js' \)
```

Verificar quais chunks Dashboard existem:

```bash
find /var/www/maximustrade -type f \
  -path '*/assets/*' \
  -name 'Dashboard-*.js'
```

Verificar referências antigas:

```bash
grep -R "Dashboard-CUM1NNki" \
  /var/www/maximustrade \
  --exclude-dir=node_modules \
  --exclude-dir=.git
```

---

# 4. Correção emergencial

## 4.1 Gerar build limpo

No frontend:

```bash
rm -rf dist
npm ci
npm run build
```

Validar:

```bash
test -f dist/index.html
find dist/assets -maxdepth 1 -type f | sort | head -n 30
```

Buscar as referências geradas:

```bash
grep -R "Dashboard-" dist
grep -R "CandlestickChart-" dist
```

## 4.2 Publicar o build como conjunto único

O `index.html`, o arquivo principal e todos os chunks precisam pertencer ao mesmo build.

Não publicar somente arquivos alterados.

Exemplo temporário:

```bash
rsync -av --delete-delay \
  dist/ \
  /var/www/maximustrade/public/
```

Atenção: `--delete-delay` é preferível a apagar tudo antes, mas a solução definitiva deve usar release atômico e preservar assets antigos.

## 4.3 Limpar cache

Após confirmar que os arquivos estão no servidor:

- purgar cache do CDN/Cloudflare, caso exista;
- limpar cache do proxy reverso;
- abrir o site em janela anônima;
- executar hard reload.

O hard reload é apenas validação. Ele não substitui a correção de cache e deploy.

---

# 5. Configuração Nginx — SPA estática

Usar quando o frontend é servido diretamente a partir do `dist`.

```nginx
server {
    listen 443 ssl http2;
    server_name maximustrade.com.br www.maximustrade.com.br;

    root /var/www/maximustrade/current;
    index index.html;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    location ^~ /assets/ {
        try_files $uri =404;

        access_log off;
        expires 1y;

        add_header Cache-Control \
            "public, max-age=31536000, immutable" \
            always;
    }

    location = /index.html {
        try_files $uri =404;

        add_header Cache-Control \
            "no-cache, no-store, must-revalidate" \
            always;

        add_header Pragma "no-cache" always;
        add_header Expires "0" always;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## Regra crítica

```nginx
location ^~ /assets/ {
    try_files $uri =404;
}
```

Um chunk inexistente deve retornar:

```text
404
```

Nunca:

```text
200 + index.html
```

---

# 6. Configuração Nginx — Laravel + frontend Vite

Quando API Laravel e SPA compartilham o domínio:

```nginx
server {
    listen 443 ssl http2;
    server_name maximustrade.com.br www.maximustrade.com.br;

    root /var/www/maximustrade/current/public;
    index index.php index.html;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    location ^~ /assets/ {
        try_files $uri =404;

        access_log off;
        expires 1y;

        add_header Cache-Control \
            "public, max-age=31536000, immutable" \
            always;
    }

    location = /index.html {
        try_files $uri =404;

        add_header Cache-Control \
            "no-cache, no-store, must-revalidate" \
            always;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Se o Laravel devolve o HTML da SPA por uma rota catch-all, garantir que `/assets/*` nunca chegue a essa rota.

---

# 7. Validar a configuração Nginx

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Depois:

```bash
curl -I https://maximustrade.com.br/assets/ARQUIVO_EXISTENTE.js
```

Esperado:

```text
HTTP/2 200
content-type: text/javascript
cache-control: public, max-age=31536000, immutable
```

Arquivo inexistente:

```bash
curl -I https://maximustrade.com.br/assets/arquivo-inexistente.js
```

Esperado:

```text
HTTP/2 404
```

Não aceitar:

```text
HTTP/2 200
content-type: text/html
```

---

# 8. Deploy atômico

## 8.1 Estrutura

```text
/var/www/maximustrade/
├── releases/
│   ├── 20260626_181500/
│   ├── 20260626_190000/
│   └── 20260626_193000/
└── current -> releases/20260626_193000/
```

## 8.2 Script sugerido

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="/var/www/maximustrade"
RELEASE_ID="$(date +%Y%m%d_%H%M%S)"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_ID"
CURRENT_LINK="$APP_ROOT/current"

npm ci
npm run build

test -f dist/index.html
test -d dist/assets

mkdir -p "$RELEASE_DIR"
rsync -a dist/ "$RELEASE_DIR/"

find "$RELEASE_DIR/assets" -type f | grep -q '\.js$'
find "$RELEASE_DIR/assets" -type f | grep -q '\.css$'

ln -sfn "$RELEASE_DIR" "$APP_ROOT/current.new"
mv -Tf "$APP_ROOT/current.new" "$CURRENT_LINK"

sudo nginx -t
sudo systemctl reload nginx

find "$APP_ROOT/releases" \
  -mindepth 1 \
  -maxdepth 1 \
  -type d \
  -printf '%T@ %p\n' \
  | sort -nr \
  | tail -n +6 \
  | cut -d' ' -f2- \
  | xargs -r rm -rf
```

## Regra

O symlink só deve mudar depois que:

- todo build foi copiado;
- `index.html` existe;
- os chunks existem;
- validações locais passaram.

---

# 9. Preservar chunks antigos

Mesmo com deploy atômico, usuários que já abriram o site podem permanecer executando o build anterior.

Manter pelo menos:

```text
3 a 5 releases anteriores
```

por um período suficiente.

Outra opção:

- assets de todos os releases em armazenamento compartilhado;
- nunca sobrescrever hashes;
- expirar arquivos antigos apenas depois de dias.

Como os nomes possuem hash, versões diferentes podem coexistir.

---

# 10. Política de cache

## HTML

```text
Cache-Control: no-cache, no-store, must-revalidate
```

ou no mínimo:

```text
Cache-Control: no-cache
```

## Assets com hash

```text
Cache-Control: public, max-age=31536000, immutable
```

## API

Definir separadamente; não aplicar política de assets à API.

---

# 11. Recuperação de erro de dynamic import

Adicionar em `src/main.tsx`, antes de renderizar a aplicação:

```ts
const PRELOAD_RELOAD_KEY = 'maximus-vite-preload-reload-at';
const PRELOAD_RELOAD_WINDOW_MS = 60_000;

window.addEventListener('vite:preloadError', (event) => {
  event.preventDefault();

  const now = Date.now();
  const previous = Number(
    sessionStorage.getItem(PRELOAD_RELOAD_KEY) ?? 0,
  );

  if (
    Number.isFinite(previous) &&
    now - previous < PRELOAD_RELOAD_WINDOW_MS
  ) {
    console.error(
      '[vite:preloadError] Reload já executado recentemente.',
      event.payload,
    );

    window.dispatchEvent(
      new CustomEvent('app:chunk-load-failed', {
        detail: event.payload,
      }),
    );

    return;
  }

  sessionStorage.setItem(
    PRELOAD_RELOAD_KEY,
    String(now),
  );

  window.location.reload();
});
```

Depois que o app inicializar corretamente:

```ts
window.setTimeout(() => {
  sessionStorage.removeItem(
    'maximus-vite-preload-reload-at',
  );
}, 10_000);
```

## Objetivo

Quando o usuário tiver uma versão antiga aberta e ocorrer novo deploy:

1. import dinâmico falha;
2. Vite emite `vite:preloadError`;
3. a página recarrega uma vez;
4. recebe o HTML atual;
5. carrega os chunks novos.

## Proteção

Não criar loop infinito de reload.

---

# 12. Verificar `base` no Vite

Para deploy no domínio raiz:

```ts
export default defineConfig({
  base: '/',
});
```

Para subdiretório:

```ts
export default defineConfig({
  base: '/nome-do-subdiretorio/',
});
```

Como os erros usam:

```text
https://maximustrade.com.br/assets/...
```

o deploy aparenta estar na raiz. Ainda assim, confirmar `base`.

---

# 13. Manifesto de build

Gerar manifesto:

```ts
export default defineConfig({
  build: {
    manifest: true,
  },
});
```

Validar no deploy:

```bash
test -f dist/.vite/manifest.json
```

Opcionalmente, criar um identificador:

```text
build-id.json
```

Exemplo:

```json
{
  "buildId": "20260626_193000",
  "commit": "abc1234"
}
```

Expor o build ID na aplicação e nos logs.

---

# 14. Health check pós-deploy

Criar script:

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

BASE_URL="https://maximustrade.com.br"

HTML="$(curl -fsS "$BASE_URL/")"

ASSETS="$(
  printf '%s' "$HTML" \
  | grep -oE '/assets/[^"]+\.(js|css)' \
  | sort -u
)"

test -n "$ASSETS"

while read -r asset; do
  headers="$(curl -fsSI "$BASE_URL$asset")"

  echo "$headers" | grep -qE '^HTTP/.* 200'
  echo "$headers" | grep -qiE \
    '^content-type: (text/javascript|application/javascript|text/css)'
done <<< "$ASSETS"
```

Também verificar imports dinâmicos a partir do manifest.

---

# 15. Ordem de correção

```text
1. Confirmar resposta HTML nos chunks
2. Confirmar arquivos ausentes
3. Gerar build limpo
4. Publicar build completo
5. Corrigir regra /assets
6. Corrigir cache do index.html
7. Purgar CDN
8. Testar em janela anônima
9. Implementar deploy atômico
10. Manter releases antigas
11. Adicionar vite:preloadError
12. Criar health check
```

---

# 16. Critérios de aceite

- [ ] Chunk existente responde HTTP 200.
- [ ] Chunk existente responde MIME JavaScript.
- [ ] Chunk inexistente responde HTTP 404.
- [ ] `/assets/*` nunca retorna `index.html`.
- [ ] `index.html` não fica cacheado por longo prazo.
- [ ] Assets com hash usam cache immutable.
- [ ] Build é publicado como conjunto único.
- [ ] Deploy usa troca atômica.
- [ ] Releases anteriores são preservadas.
- [ ] CDN foi purgado.
- [ ] `vite:preloadError` possui reload protegido.
- [ ] Dashboard abre após novo deploy.
- [ ] CandlestickChart abre após novo deploy.
- [ ] Reload em aba antiga recupera a aplicação.
- [ ] Console não contém erro MIME.
- [ ] Console não contém dynamic import failure.
- [ ] Health check passa.
- [ ] Rollback foi testado.

---

# 17. Gates

```text
GATE_CHUNK_EXISTS: PENDENTE
GATE_ASSET_MIME: PENDENTE
GATE_ASSET_404: PENDENTE
GATE_HTML_CACHE: PENDENTE
GATE_ATOMIC_DEPLOY: PENDENTE
GATE_OLD_CHUNKS: PENDENTE
GATE_PRELOAD_RECOVERY: PENDENTE
GATE_HEALTH_CHECK: PENDENTE
GATE_PRODUCTION: NO_GO
```

---

# 18. Resultado esperado

```text
REQUEST /assets/chunk-hash.js
    │
    ├── arquivo existe
    │      → 200 text/javascript
    │
    └── arquivo não existe
           → 404
           → nunca index.html

NOVO DEPLOY
    → assets copiados completamente
    → index publicado junto
    → symlink trocado atomicamente
    → releases antigas preservadas
    → navegador antigo recarrega uma vez
```

---

# 19. Conclusão

O erro atual não indica falha no código SMC.

Ele indica inconsistência entre:

```text
HTML/entrypoint carregado
e
chunks disponíveis no servidor
```

A solução definitiva exige:

```text
deploy atômico
+ regra específica para /assets
+ cache correto
+ preservação de chunks antigos
+ recuperação vite:preloadError
```
