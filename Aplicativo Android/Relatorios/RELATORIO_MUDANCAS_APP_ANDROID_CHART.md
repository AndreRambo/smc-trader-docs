# Relatório de Mudanças — App Android — Exibição de Gráfico

**Data:** 2026-06-19
**Branch:** `feature/phase6-candidate-c-nested-walk-forward`
**Objetivo:** Exibir o gráfico PNG da oportunidade diretamente no app (remover placeholder "Toque para ver o gráfico")

---

## 1. Arquivos modificados/criados

| Arquivo | Ação | Descrição |
|---------|------|-----------|
| `core/ui/ImageDecoder.kt` | **Criado** | `expect fun decodeToImageBitmap()` + `expect fun decodeBase64ToBytes()` |
| `core/ui/ImageDecoder.android.kt` | **Criado** | `actual fun` — `BitmapFactory.decodeByteArray` + `Base64.decode` |
| `core/api/ApiClient.kt` | Modificado | Adicionado `getBytes(fullUrl)` para download de imagem com auth |
| `domain/model/OpportunityModels.kt` | Modificado | `ChartDto` ganhou campo `thumbnail_base64: String?` |
| `features/opportunities/OpportunityDetailScreen.kt` | Modificado | `ChartCard` renderiza imagem real via base64 |
| `MaximusTrader/backend/.../MobileOpportunityEvidenceController.php` | Modificado | `buildChartResponse()` lê thumbnail do disco → base64 inline |

---

## 2. Fluxo de exibição do gráfico

```
ANTES (quebrado):
  App → GET /api/mobile/opportunities/{id}/evidence → recebe chart URLs
  App → GET /api/mobile/.../artifacts/thumbnail (SEPARADO, exige auth)
       → FALHA silenciosamente → placeholder "📊 Toque para ver o gráfico"
       → Clique abre browser com URL → "unauthenticated"

AGORA (funcionando):
  App → GET /api/mobile/opportunities/{id}/evidence → recebe chart URLs + thumbnail_base64
  App → decodeBase64ToBytes() → decodeToImageBitmap() → Image composable
       → GRÁFICO EXIBIDO DIRETAMENTE, sem HTTP extra, sem auth separada
```

---

## 3. Detalhe das mudanças por arquivo

### 3.1 `core/ui/ImageDecoder.kt` (commonMain) — NOVO

```kotlin
expect fun ByteArray.decodeToImageBitmap(): ImageBitmap?
expect fun String.decodeBase64ToBytes(): ByteArray
```

Funções `expect` declaradas em commonMain, implementadas por plataforma.

### 3.2 `core/ui/ImageDecoder.android.kt` (androidMain) — NOVO

```kotlin
actual fun ByteArray.decodeToImageBitmap(): ImageBitmap? {
    val androidBitmap = BitmapFactory.decodeByteArray(this, 0, size)
    return androidBitmap?.asImageBitmap()
}

actual fun String.decodeBase64ToBytes(): ByteArray =
    Base64.decode(this, Base64.DEFAULT)
```

Implementação Android: `BitmapFactory` para PNG → `ImageBitmap`, `Base64` para string → bytes. Null-safe: retorna null se bytes não forem imagem válida.

### 3.3 `core/api/ApiClient.kt` — MODIFICADO

Adicionado método `getBytes(fullUrl)`:
```kotlin
suspend fun getBytes(fullUrl: String): ByteArray {
    val token = getTokenCached()
    val path = if (fullUrl.startsWith(AppConfig.API_BASE_URL)) {
        fullUrl.removePrefix(AppConfig.API_BASE_URL)
    } else ...
    return httpClient.get(path) { injectBearerToken(token) }.body()
}
```

Strip do base URL para evitar duplicação com `defaultRequest` do Ktor. **Nota:** Este método não é mais usado pelo ChartCard (substituído por base64), mas permanece disponível para uso futuro.

### 3.4 `domain/model/OpportunityModels.kt` — MODIFICADO

```kotlin
data class ChartDto(
    val thumbnail_url: String? = null,
    val mobile_url: String? = null,
    val full_url: String? = null,
    val thumbnail_base64: String? = null,  // ← NOVO
    val expires_at: String? = null,
    val width: Int = 1080,
    val height: Int = 1350
)
```

### 3.5 `features/opportunities/OpportunityDetailScreen.kt` — MODIFICADO

Função `ChartCard` reescrita:

```kotlin
@Composable
private fun ChartCard(chart: ChartDto, uriHandler: UriHandler) {
    var bitmap by remember { mutableStateOf<ImageBitmap?>(null) }
    var loading by remember { mutableStateOf(chart.thumbnail_base64 != null) }

    LaunchedEffect(chart.thumbnail_base64) {
        chart.thumbnail_base64?.let { b64 ->
            try {
                val bytes = withContext(Dispatchers.Default) { b64.decodeBase64ToBytes() }
                bitmap = withContext(Dispatchers.Default) { bytes.decodeToImageBitmap() }
            } catch (_: Exception) { }
        }
        loading = false
    }

    // UI: loading → CircularProgressIndicator
    //      bitmap != null → Image(bitmap)
    //      else → placeholder "📊 Toque para ver o gráfico"
}
```

**O que foi removido:**
- `ApiClient` / `koinInject` — não precisa mais de HTTP
- `Dispatchers.IO` — não precisa mais de network thread
- `.clickable { uriHandler.openUri(...) }` do Box principal

**O que foi mantido:**
- Botões "Miniatura" e "Tela cheia" (abrem URLs no browser para zoom)

### 3.6 `MobileOpportunityEvidenceController.php` (Laravel) — MODIFICADO

```php
// buildChartResponse() — lê thumbnail do disco e codifica base64
$thumbArtifact = $artifacts->get('thumbnail');
if ($thumbArtifact && Storage::disk(...)->exists(...)) {
    $thumbnailBase64 = base64_encode(
        Storage::disk(...)->get($thumbArtifact->storage_path)
    );
}
return [
    ...
    'thumbnail_base64' => $thumbnailBase64,  // NOVO
    ...
];
```

---

## 4. Histórico de iterações (bugs corrigidos)

| Versão | Problema | Causa | Correção |
|--------|----------|-------|----------|
| v1 | NPE crash | `rememberDecodedImageBitmap` @Composable dentro de `try/catch` | Separado: download em `LaunchedEffect`, decode em contexto @Composable |
| v2 | NPE crash | `BitmapFactory.decodeByteArray` retornava null → `.asImageBitmap()` NPE | `decodeToImageBitmap()` retorna `ImageBitmap?` com null-check |
| v3 | Placeholder sempre | Download HTTP do artifact falhava (erro de auth/resolução de URL) | Substituído HTTP por base64 inline no JSON do evidence |
| v4 | **(atual)** | — | Base64 inline: zero HTTP extra, zero auth separada |

---

## 5. Dependências

**Nenhuma dependência nova adicionada.** Tudo usa APIs existentes:
- `android.graphics.BitmapFactory` (Android SDK)
- `android.util.Base64` (Android SDK)
- `kotlinx.coroutines.Dispatchers` / `withContext` (já no projeto)
- `androidx.compose.ui.graphics.ImageBitmap` (já no projeto)
- `androidx.compose.foundation.Image` (já no projeto)
- `Storage` facade (Laravel — já no projeto)

---

## 6. Deploy pendente

```bash
# Laravel (VPS → Hostinger)
scp -P 65002 \
  .../MobileOpportunityEvidenceController.php \
  u963484916@82.25.73.246:domains/maximustrade.com.br/app/Http/Controllers/Api/
ssh -p 65002 u963484916@82.25.73.246 "cd domains/maximustrade.com.br && php artisan config:clear && php artisan route:clear"

# Android APK (VPS)
cd .../AppAndroid/MaximusTrader && ./gradlew assembleDebug
# Baixar APK → instalar no celular
```
