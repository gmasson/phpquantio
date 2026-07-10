# PHPQuantio

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Versão-3.1-green.svg?style=flat-square)]()

> Micro biblioteca PHP de **arquivo único** para desenvolvimento rápido de ferramentas e sites seguros. Zero dependências, funções estáticas amplas.

PHPQuantio é uma biblioteca minimalista que reúne em uma única classe estática tudo o que você precisa para capturar, validar, filtrar, converter e gerar dados com segurança — além de utilitários de infraestrutura como headers de segurança, rate limiting, proteção de páginas, upload seguro, CSRF, captcha e paginação.

- **Repositório:** https://github.com/gmasson/phpquantio
- **Licença:** MIT
- **Versão:** 3.1
- **PHP mínimo:** 8.2

---

## Características

- **Arquivo único** — apenas `PHPQuantio.php`, sem composer, sem autoload.
- **Seguro por padrão** — CSRF, captcha, headers de segurança, rate limiting, upload com verificação de MIME real, senhas com bcrypt, sessão endurecida.
- **API unificada** — 5 verbos principais: `capture`, `valid`, `filter`, `convert`, `generate`.
- **Foco em Brasil** — validação de CPF, CNPJ, telefone BR, máscaras LGPD, datas em pt-BR.
- **Zero dependências** — usa apenas extensões nativas do PHP (cURL, finfo, mbstring, iconv).
- **Idempotente** — funções de init e sessão podem ser chamadas várias vezes sem efeito colateral.

---

## Requisitos

- **PHP 8.2+**
- Extensões recomendadas (opcionais, mas usadas quando disponíveis):
  - `mbstring` — manipulação multibyte (UTF-8)
  - `iconv` — transliteração de slugs
  - `cURL` — verificação de status HTTP (`status()`)
  - `fileinfo` — verificação de MIME em uploads (`upload()`)

---

## Instalação

Baixe `PHPQuantio.php` e coloque no seu projeto:

```
projeto/
├── PHPQuantio.php
└── index.php
```

### Uso

```php
<?php
require_once 'PHPQuantio.php';

// Se o app roda atrás de proxy/CDN (Cloudflare, nginx, etc.), habilite
// ANTES de qualquer chamada que use HTTPS/IP (headers, rateLimit, session):
PHPQuantio::trustProxy(true);

// Inicia sessão endurecida automaticamente quando necessário
PHPQuantio::headers('strict');
```

---

## Referência de API

O fluxo recomendado é:

```
capture (entrada)  →  valid (validação)  →  filter (saída)
```

### Verbos de dados

| Verbo     | Finalidade                                      |
|-----------|-------------------------------------------------|
| `capture` | Captura dados de fontes de entrada (GET, POST…)  |
| `valid`   | Validação booleana (sempre retorna `true/false`)|
| `filter`  | Transformação/sanitização para contexto de saída|
| `convert` | Conversão entre formatos (datas, caixa, LGPD)    |
| `generate`| Geração de tokens, IDs e segredos (CSPRNG)      |

### Segurança/Infra

| Função       | Finalidade                                              |
|--------------|---------------------------------------------------------|
| `trustProxy` | Habilita confiança em headers de proxy (X-Forwarded-*)  |
| `headers`    | Envia headers de segurança (presets strict/relaxed/api) |
| `rateLimit`  | Rate limiting persistido em arquivo (por IP ou chave)   |
| `private`    | Gate de senha compartilhada (proteção de página)        |
| `upload`      | Upload seguro com verificação de MIME real              |
| `request`     | Verifica características da requisição (https/ajax/method)|
| `redirect`    | Redirecionamento seguro (bloqueia open redirect)        |
| `jsonResponse`| Resposta JSON padronizada                               |
| `flash`       | Mensagens flash (exibidas uma única vez)               |
| `status`      | Status code HTTP de uma URL (mitiga SSRF)              |
| `paginate`    | Cálculo de paginação (LIMIT/OFFSET)                     |

---

## Guia detalhado

### 1. `capture()` — Captura de dados

Acesso unificado às fontes de entrada. Faz `trim` em strings e devolve `$fallback` quando o valor está ausente/vazio.

**Fontes de usuário** (`get`, `post`, `request`, `cookie`) são **sanitizadas contra XSS** (htmlspecialchars) na fronteira. As demais (`session`, `server`, `env`, `header`, `bearer`, `json`, `ip`) retornam sem escape para não corromper o dado.

```php
// Captura um campo POST (sanitizado contra XSS)
$nome = PHPQuantio::capture('post', 'nome');

// Com fallback
$pagina = PHPQuantio::capture('get', 'page', 1);

// Toda a fonte
$get = PHPQuantio::capture('get'); // array sanitizado

// IP do cliente
$ip = PHPQuantio::capture('ip');

// Bearer token (Authorization header)
$token = PHPQuantio::capture('bearer');

// JSON body
$email = PHPQuantio::capture('json', 'email');

// Header customizado
$agent = PHPQuantio::capture('header', 'User-Agent');
```

| Parâmetro    | Tipo   | Descrição                                                        |
|--------------|--------|------------------------------------------------------------------|
| `$source`    | string | `get\|post\|request\|cookie\|session\|server\|env\|header\|bearer\|json\|ip` |
| `$key`       | string | Nome do campo (vazio retorna a fonte inteira)                    |
| `$fallback`  | mixed  | Valor retornado se o campo não existir/for vazio                  |

---

### 2. `valid()` — Validação booleana

Sempre retorna `true` ou `false`. Nunca lança exceção.

```php
// Email
PHPQuantio::valid('user@example.com', 'email'); // true

// Inteiro
PHPQuantio::valid('42', 'int'); // true

// Senha forte (mínimo 8, com maiúscula, minúscula, número e especial)
PHPQuantio::valid('Senha@123', 'password'); // true

// Senha com opções customizadas
PHPQuantio::valid('abc', 'password', ['min_length' => 12, 'require_special' => false]);

// CPF/CNPJ (com ou sem máscara)
PHPQuantio::valid('123.456.789-09', 'cpf');
PHPQuantio::valid('11.222.333/0001-81', 'cnpj');

// Telefone BR (10 dígitos fixo ou 11 celular)
PHPQuantio::valid('(11) 98765-4321', 'phone_br'); // true

// CEP (8 dígitos, com ou sem hífen)
PHPQuantio::valid('01310-100', 'cep'); // true

// Cartão de crédito (Luhn, com ou sem máscara)
PHPQuantio::valid('4111 1111 1111 1111', 'credit_card'); // true

// JSON válido
PHPQuantio::valid('{"a":1}', 'json'); // true

// Tamanho entre min e max
PHPQuantio::valid('texto', 'between', [3, 10]);

// Valor em lista
PHPQuantio::valid('ativo', 'in', ['ativo', 'inativo', 'pendente']);

// Regex
PHPQuantio::valid('abc123', 'regex', '/^[a-z0-9]+$/');

// CSRF (com namespace opcional para múltiplos formulários)
PHPQuantio::valid($token, 'csrf');
PHPQuantio::valid($token, 'csrf', 'form_login');
```

#### Tipos de validação

| Tipo        | Descrição                                              | `$extra`                         |
|-------------|--------------------------------------------------------|----------------------------------|
| `required`  | Não vazio (string não vazia ou array não vazio)        | —                                |
| `int`       | Inteiro válido                                         | —                                |
| `float`     | Float válido                                           | —                                |
| `numeric`   | Numérico                                               | —                                |
| `bool`      | Booleano (`true`/`false`/`1`/`0`/`on`/`off`/`yes`/`no`)| —                                |
| `email`     | Email válido                                           | —                                |
| `url`       | URL http/https válida                                  | —                                |
| `domain`    | Domínio válido                                         | —                                |
| `ip`        | IP (v4 ou v6)                                          | —                                |
| `ipv4`      | IPv4                                                   | —                                |
| `ipv6`      | IPv6                                                   | —                                |
| `slug`      | Slug (`meu-slug-123`)                                  | —                                |
| `uuid`      | UUID v1-v5                                             | —                                |
| `min`       | Tamanho mínimo                                         | `int` (tamanho)                  |
| `max`       | Tamanho máximo                                         | `int` (tamanho)                  |
| `length`    | Tamanho exato                                          | `int` (tamanho)                  |
| `between`   | Valor numérico entre min e max                         | `[min, max]`                     |
| `in`        | Valor em lista                                         | `array`                          |
| `not_in`    | Valor não em lista                                     | `array`                          |
| `regex`     | Casar padrão regex                                     | `string` (padrão)                |
| `matches`   | Igualdade em tempo constante (hash_equals)             | `string` (valor a comparar)       |
| `different` | Diferente de                                          | `string` (valor a comparar)       |
| `cpf`         | CPF com dígitos verificadores                          | —                                |
| `cnpj`        | CNPJ com dígitos verificadores                         | —                                |
| `phone_br`    | Telefone BR (fixo 10 ou celular 11 dígitos)           | —                                |
| `cep`         | CEP brasileiro (8 dígitos, com ou sem hífen)           | —                                |
| `credit_card` | Cartão de crédito (Luhn, com ou sem máscara)          | —                                |
| `json`        | String JSON válida                                     | —                                |
| `password`    | Senha forte                                            | `array` (opções)                 |
| `date`      | Data no formato                                        | `string` (formato, padrão `Y-m-d`)|
| `csrf`      | Token CSRF                                             | `string` (namespace opcional)     |
| `captcha`   | Resposta de captcha (consome após validar)             | `string` (nome do captcha)        |

#### Opções de `password`

```php
[
    'min_length'      => 8,    // mínimo de caracteres
    'require_upper'   => true, // exige maiúscula
    'require_lower'   => true, // exige minúscula
    'require_number'  => true, // exige número
    'require_special' => true, // exige caractere especial
]
```

---

### 3. `filter()` — Transformação/sanitização

Transforma um dado para um contexto de saída ou formato específico.

```php
// Escapar HTML (saída em conteúdo)
echo PHPQuantio::filter($userInput, 'html');

// Escapar para atributo HTML
echo '<a href="' . PHPQuantio::filter($url, 'html_attr') . '">';

// Slug
PHPQuantio::filter('Olá Mundo!', 'slug'); // "ola-mundo"
PHPQuantio::filter('Olá Mundo!', 'slug', '_'); // "ola_mundo"

// Senha (hash bcrypt)
$hash = PHPQuantio::filter('minhaSenha', 'pass');

// Verificar senha
PHPQuantio::filter('minhaSenha', 'verify', $hash); // true/false

// Máscara
PHPQuantio::filter('12345678901', 'mask', '###.###.###-##'); // CPF formatado

// Truncar
PHPQuantio::filter($texto, 'truncate', 100); // 100 chars, preserva palavras

// Filename seguro
PHPQuantio::filter($_FILES['file']['name'], 'filename');
```

#### Tipos de filtro

| Tipo        | Descrição                                              | `$extra`                         |
|-------------|--------------------------------------------------------|----------------------------------|
| `tags`      | `strip_tags()` (padrão)                                | —                                |
| `html`      | `htmlspecialchars` (ENT_QUOTES \| ENT_HTML5)           | —                                |
| `html_attr` | `htmlspecialchars` para atributo HTML                 | —                                |
| `digits`    | Mantém apenas dígitos                                  | —                                |
| `alpha`     | Mantém apenas `a-zA-Z`                                 | —                                |
| `alnum`     | Mantém apenas `a-zA-Z0-9`                              | —                                |
| `slug`      | Slug legível (translitera acentos)                     | `string` (separador, padrão `-`) |
| `filename`  | Nome de arquivo seguro (sem path traversal)            | —                                |
| `urlsafe`   | Caracteres não reservados RFC 3986 (componentes de path)| —                                |
| `mask`      | Aplica máscara (`#` como coringa)                      | `string` (padrão da máscara)     |
| `truncate`  | Trunca preservando palavras                            | `int` (tamanho)                  |
| `pass`      | Hash bcrypt                                            | `array` (opções do password_hash)|
| `verify`    | Verifica senha contra hash                             | `string` (hash)                  |

> **Atenção sobre `urlsafe`**: este filtro mantém apenas caracteres não reservados do RFC 3986 (`A-Za-z0-9-._~`), removendo `:` e `/`. Use-o **apenas em componentes de path de URL** (slugs, segmentos) — **nunca em URLs completas**. Para URLs completas em atributos HTML (`href`, `src`), use `filter($url, 'html_attr')`.

---

### 4. `convert()` — Conversão de formatos

```php
// Caixa
PHPQuantio::convert('olá', 'upper'); // "OLÁ"
PHPQuantio::convert('OLÁ', 'lower'); // "olá"
PHPQuantio::convert('olá mundo', 'title'); // "Olá Mundo"

// Datas
PHPQuantio::convert('2026-07-09', 'date_br'); // "09/07/2026"
PHPQuantio::convert('2026-07-09 14:30', 'datetime_br'); // "09/07/2026 14:30"
PHPQuantio::convert('2026-07-09 14:30', 'timeago'); // "há X minutos"

// Máscaras LGPD
PHPQuantio::convert('joao.silva@example.com', 'email_hide'); // "j****a@example.com"
PHPQuantio::convert('(11) 98765-4321', 'phone_hide'); // "(11) ****-4321"
PHPQuantio::convert('João da Silva', 'name_hide'); // "João S."

// Bytes
PHPQuantio::convert(1536000, 'bytes'); // "1.5 MB"
```

| Tipo          | Descrição                                  |
|---------------|--------------------------------------------|
| `upper`       | Maiúsculas (UTF-8)                         |
| `lower`       | Minúsculas (UTF-8)                         |
| `title`       | Title case (UTF-8)                         |
| `date_br`     | Data em `d/m/Y`                            |
| `datetime_br` | Data/hora em `d/m/Y H:i`                   |
| `timeago`     | Texto relativo ("há 5 minutos")           |
| `email_hide`  | Máscara LGPD de e-mail                     |
| `phone_hide`  | Máscara LGPD de telefone                   |
| `name_hide`   | Máscara LGPD de nome (primeiro + inicial)  |
| `bytes`       | Humaniza bytes ("1.5 MB")                  |

---

### 5. `generate()` — Geração de tokens e IDs

Todos os geradores usam **CSPRNG** (`random_bytes` / `random_int`).

```php
// Token hex (32 bytes por padrão)
PHPQuantio::generate('token'); // 64 chars hex
PHPQuantio::generate('token', 16); // 32 chars hex

// UUID v4
PHPQuantio::generate('uuid');

// String aleatória (charset seguro, sem ambíguos)
PHPQuantio::generate('string', 8);

// Código alfanumérico (sem ambíguos)
PHPQuantio::generate('code', 6);

// PIN numérico
PHPQuantio::generate('pin', 4);

// Senha forte (4 classes garantidas, embaralhada)
PHPQuantio::generate('password', 16);

// Nonce para CSP
PHPQuantio::generate('nonce');

// CSRF (token reutilizável na sessão)
PHPQuantio::generate('csrf');
// Campo hidden pronto para formulário
echo PHPQuantio::generate('csrf_field');
// Com nome de campo customizado
echo PHPQuantio::generate('csrf_field', '_token');
// Com namespace para múltiplos formulários na mesma página
echo PHPQuantio::generate('csrf_field', '_token', 'form_login');

// Captcha matemático (armazena resposta na sessão)
$pergunta = PHPQuantio::generate('captcha'); // "23 + 7"
$pergunta = PHPQuantio::generate('captcha', 'login');
```

| Tipo         | Descrição                                  | `$extra` / `$extra2`                          |
|--------------|--------------------------------------------|-----------------------------------------------|
| `token`      | Token hex (CSPRNG)                         | `int` (bytes, padrão 32)                      |
| `nonce`      | Nonce base64url (16 bytes)                | —                                             |
| `uuid`       | UUID v4                                    | —                                             |
| `string`     | String aleatória (charset seguro)          | `int` (tamanho) / `string` (charset)          |
| `code`       | Código alfanumérico (sem ambíguos)         | `int` (tamanho)                               |
| `pin`        | PIN numérico                               | `int` (tamanho)                               |
| `password`   | Senha forte (4 classes garantidas)          | `int` (tamanho, mínimo 8)                     |
| `csrf`       | Token CSRF (reutilizável na sessão)        | `string` (namespace opcional)                 |
| `csrf_field` | Campo hidden pronto                        | `string` (nome campo) / `string` (namespace)  |
| `captcha`    | Captcha matemático (armazena resposta)     | `string` (nome do captcha)                    |

> **Múltiplos formulários na mesma página**: se você tem vários formulários na mesma página, cada um chamando `generate('csrf_field')`, **cada chamada sobrescreve o token anterior na sessão** — apenas o último será válido. Use o 3º parâmetro (`$extra2`) como namespace único por formulário, e valide com `valid($token, 'csrf', 'mesmo_namespace')`.

---

### 6. `headers()` — Headers de segurança

```php
// Preset strict (padrão) — negação por padrão
PHPQuantio::headers('strict');

// Preset relaxed — permite inline e frames same-origin
PHPQuantio::headers('relaxed');

// Preset API — CSP none, no-store
PHPQuantio::headers('api');

// Sobrescrever/adicionar headers
PHPQuantio::headers('strict', [
    'Content-Security-Policy' => "default-src 'self'",
    'X-Frame-Options'         => 'SAMEORIGIN',
]);
```

| Preset     | X-Frame-Options | CSP                                                              |
|------------|-----------------|------------------------------------------------------------------|
| `strict`   | `DENY`          | `default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` |
| `relaxed`  | `SAMEORIGIN`    | `default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'` |
| `api`      | `DENY`          | `default-src 'none'; frame-ancestors 'none'` + `Cache-Control: no-store` |

Headers base sempre enviados:
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Strict-Transport-Security` (apenas em HTTPS)

> Em ambientes locais/XAMPP sem HTTPS, **não adicione `upgrade-insecure-requests`** à CSP — isso quebra requisições HTTP locais.

---

### 7. `rateLimit()` — Rate limiting

Persistido em arquivo (funciona mesmo sem sessão/cookie). Ao exceder, responde 429 com `Retry-After` e encerra.

```php
// 60 requisições por minuto por IP (padrão)
PHPQuantio::rateLimit();

// 5 tentativas por 15 minutos, por chave customizada (ex.: ID do usuário)
PHPQuantio::rateLimit(5, 900, 'login_user_' . $userId);

// Storage próprio (recomendado em hosting compartilhado, fora de /tmp público)
PHPQuantio::rateLimit(60, 60, 'ip', __DIR__ . '/storage');
```

| Parâmetro      | Tipo   | Descrição                                  |
|----------------|--------|--------------------------------------------|
| `$requests`    | int    | Máximo de requisições por janela (padrão 60)|
| `$window`      | int    | Duração da janela em segundos (padrão 60)   |
| `$by`          | string | `'ip'` (padrão) ou chave própria            |
| `$storageDir`  | ?string| Diretório de storage (padrão `sys_get_temp_dir`)|

> **Fail-open**: se o storage (sys_get_temp_dir) estiver indisponível, o rate limit não derruba o site — apenas não aplica o limite.

---

### 8. `private()` — Proteção de página por senha

Gate de senha simples (sem usuário). Regenera o ID de sessão no login para prevenir session fixation.

```php
// Verificar se está logado
if (!PHPQuantio::private('check')) {
    // redireciona ou mostra formulário
}

// Login (senha comparada contra hash bcrypt)
$hash = PHPQuantio::filter('senha_secreta', 'pass');
PHPQuantio::private('open', $_POST['senha'], $hash);

// Logout
PHPQuantio::private('close');
```

| Parâmetro       | Tipo   | Descrição                              |
|-----------------|--------|----------------------------------------|
| `$action`       | string | `'open'` \| `'check'` \| `'close'`     |
| `$value`        | string | Senha digitada (só em `'open'`)        |
| `$correctHash`  | string | Hash bcrypt (só em `'open'`)          |

---

### 9. `upload()` — Upload seguro

Valida e move um upload com **verificação de conteúdo (MIME real via finfo)**. Fail-closed: sem finfo, ou extensão sem MIME mapeado, rejeita. Gera nome aleatório para o arquivo salvo.

```php
$result = PHPQuantio::upload(
    $_FILES['avatar'],
    __DIR__ . '/uploads',
    ['jpg', 'jpeg', 'png', 'webp'],
    2_097_152 // 2 MB
);

if ($result['ok']) {
    echo 'Salvo em: ' . $result['path'];
} else {
    echo 'Erro: ' . $result['error'];
}
```

| Parâmetro       | Tipo    | Descrição                                            |
|-----------------|---------|------------------------------------------------------|
| `$file`         | array   | Item de `$_FILES`                                    |
| `$destination`  | string  | Diretório de destino (existente e gravável)          |
| `$allowedExt`   | array   | Extensões permitidas, minúsculas                      |
| `$maxBytes`     | int     | Tamanho máximo (padrão 5 MB)                          |

**Retorno:** `['ok' => bool, 'error' => string]` ou `['ok' => true, 'path' => string]`.

MIMEs mapeados nativamente: `jpg`, `jpeg`, `png`, `gif`, `webp`, `pdf`.

---

### 10. `request()` — Características da requisição

```php
PHPQuantio::request('https');  // true se HTTPS
PHPQuantio::request('ajax');   // true se XMLHttpRequest
PHPQuantio::request('method', 'POST'); // true se método POST
```

---

### 11. `redirect()` — Redirecionamento seguro

Por padrão só permite caminho interno (começando com `/`, sem `//` nem `\`), bloqueando open redirect.

```php
PHPQuantio::redirect('/dashboard');
PHPQuantio::redirect('/login', 302);

// Destino externo confiável (use com cautela)
PHPQuantio::redirect('https://example.com', 302, true);
```

---

### 12. `jsonResponse()` — Resposta JSON

```php
PHPQuantio::jsonResponse(['status' => 'ok', 'data' => $rows], 200);
PHPQuantio::jsonResponse(['error' => 'Não autorizado'], 401);
```

Define `Content-Type: application/json; charset=UTF-8`, `X-Content-Type-Options: nosniff` e o status code. Se `json_encode` falhar (ex.: dados recursivos), retorna 500 com mensagem de erro.

---

### 13. `flash()` — Mensagens flash

```php
// Definir
PHPQuantio::flash('set', 'Salvo com sucesso!', 'success');

// Recuperar (consome após ler)
$flash = PHPQuantio::flash('get');
if ($flash) {
    echo "<div class=\"alert alert-{$flash['type']}\">{$flash['message']}</div>";
}
```

---

### 14. `status()` — Status HTTP de uma URL

Restringe os protocolos a http/https (mitiga SSRF por schemes como `file://`, `gopher://`).

```php
$code = PHPQuantio::status('https://example.com');
if ($code === 200) {
    // online
}
```

> Se `$url` vier do usuário, ainda há risco de SSRF para redes internas — valide o destino antes de chamar. A função bloqueia hosts que resolvem para IPs privados/loopback/reservados, mas resolução DNS pode mudar entre a verificação e a requisição (TOCTOU).

---

### 15. `paginate()` — Paginação

```php
$p = PHPQuantio::paginate(150, 3, 10);
// [
//   'current_page' => 3,
//   'per_page'     => 10,
//   'total_items'  => 150,
//   'total_pages'  => 15,
//   'offset'       => 20,
//   'has_previous' => true,
//   'has_next'     => true,
// ]

// Uso com SQL:
// SELECT * FROM itens LIMIT {$p['per_page']} OFFSET {$p['offset']}
```

---

## Segurança

### CSRF

O token CSRF é **reutilizável na sessão** (padrão synchronizer), o que evita quebrar formulários com múltiplos submits/AJAX. Para múltiplos formulários na mesma página, use **namespaces**:

```php
// Formulário de login
echo PHPQuantio::generate('csrf_field', '_token', 'form_login');
// Validação
PHPQuantio::valid($_POST['_token'], 'csrf', 'form_login');

// Formulário de newsletter (mesma página)
echo PHPQuantio::generate('csrf_field', '_token', 'form_news');
PHPQuantio::valid($_POST['_token'], 'csrf', 'form_news');
```

### Captcha

Captcha matemático simples (armazena a resposta na sessão e consome após validar):

```php
// No formulário
$pergunta = PHPQuantio::generate('captcha', 'login');
echo "Quanto é {$pergunta}?";
echo '<input type="number" name="captcha">';

// Na validação
if (!PHPQuantio::valid($_POST['captcha'], 'captcha', 'login')) {
    // erro
}
```

### Sessão

A sessão é iniciada automaticamente quando necessário, com cookie endurecido:
- `HttpOnly: true`
- `Secure: true` (em HTTPS)
- `SameSite: Lax`

### Upload

- Verifica `is_uploaded_file()`
- Valida extensão contra whitelist
- Verifica **MIME real** via `finfo` (fail-closed)
- Gera nome aleatório (evita path traversal e colisão)
- Move com `move_uploaded_file()`

---

## Exemplo completo

```php
<?php
require_once 'PHPQuantio.php';

PHPQuantio::headers('strict');
PHPQuantio::rateLimit(30, 60); // 30 req/min

if (!PHPQuantio::request('method', 'POST')) {
    PHPQuantio::redirect('/');
}

if (!PHPQuantio::valid(PHPQuantio::capture('post', '_token'), 'csrf', 'form_contato')) {
    PHPQuantio::jsonResponse(['error' => 'Token CSRF inválido'], 403);
}

$nome  = PHPQuantio::capture('post', 'nome');
$email = PHPQuantio::capture('post', 'email');

$erros = [];
if (!PHPQuantio::valid($nome, 'required') || !PHPQuantio::valid($nome, 'min', 3)) {
    $erros['nome'] = 'Nome inválido (mínimo 3 caracteres).';
}
if (!PHPQuantio::valid($email, 'email')) {
    $erros['email'] = 'Email inválido.';
}

if ($erros) {
    PHPQuantio::jsonResponse(['errors' => $erros], 422);
}

// Processa...
PHPQuantio::flash('set', 'Mensagem enviada com sucesso!', 'success');
PHPQuantio::jsonResponse(['status' => 'ok', 'token' => PHPQuantio::generate('csrf', 'form_contato')]);
```

---

## Licença

MIT — veja [LICENSE](LICENSE).