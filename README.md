# PHPQuantio 1.9.0
PHPQuantio é uma micro biblioteca PHP com funções úteis para desenvolvimento web.

## Licença
- MIT

## Requisitos
- PHP 7.3 ou superior

## Configuração padrão

### Iniciando a sessão
A sessão é automaticamente iniciada se ainda não estiver iniciada.

### Verificação da Versão do PHP
A biblioteca requer PHP 7.3 ou superior. Se o PHP estiver em uma versão inferior, uma mensagem de erro será exibida.

### Configuração do Timezone
O Timezone é configurado para GMT se não estiver configurado.

## Controle de Acessos
A biblioteca controla o acesso limitando a 60 requisições por minuto.

## Funções

### pq_filter(input, type, add)
Filtra a entrada de dados de acordo com o tipo especificado.

- html: Filtra conteúdo HTML.
- url: Filtra URL.
- user: Filtra nome de usuário.
- email: Filtra endereço de e-mail.
- get: Filtra valor recebido via método GET.
- post: Filtra valor recebido via método POST.
- pass: Filtra senha.

#### Exemplo:
```php
// Exemplo de uso da função pq_filter
$filteredInput = pq_filter('nomeinput', 'post');
```

### pq_info(opt)
Retorna informações sobre o cliente, servidor e ambiente de execução.

- ip: Endereço IP do cliente.
- browser: Agente do navegador (user-agent) do cliente.
- port: Porta do cliente.
- referer: URL da página de onde o usuário veio.
- root: Diretório raiz do documento.
- host: Nome do host do servidor.
- server_addr: Endereço IP do servidor.
- server_name: Nome do servidor.
- server_software: Software do servidor.

#### Exemplo:
```php
// Exemplo de uso da função pq_info
$clientIP = pq_info('ip');
```

### pq_mail(email, subject, body, from)
Envia um email usando a função mail do PHP.

- email: Endereço de email de destino.
- subject: Assunto do email.
- body: Corpo do email.
- from: Endereço de email do remetente.

#### Exemplo:
```php
// Exemplo de uso da função pq_mail
$emailSent = pq_mail('destinatario@example.com', 'Assunto do Email', 'Corpo do Email', 'remetente@example.com');
```

### pq_captcha(name)
Gera um captcha simples com uma operação matemática.

- name: Nome do captcha para armazenamento na sessão.

#### Exemplo:
```php
// Exemplo de uso da função pq_captcha
$captchaText = pq_captcha();
```

### pq_validCaptcha(input, name)
Valida a resposta do captcha fornecida pelo usuário.

- input: Resposta do usuário ao captcha.
- name: Nome do captcha armazenado na sessão.

#### Exemplo:
```php
// Exemplo de uso da função pq_validCaptcha
$isValidCaptcha = pq_validCaptcha($_POST['captcha']);
```

### pq_login(input, correctPass, token)
Realiza o login com uma senha única.

- input: Senha fornecida pelo usuário.
- correctPass: Senha correta para comparação.
- token: Token para validar sessão.

#### Exemplo:
```php
// Exemplo de uso da função pq_login
$loggedIn = pq_login($_POST['password'], $correctPassword);
```

### pq_validLogin(token)
Verifica se o usuário está logado.

- token: Token para validar sessão.

#### Exemplo:
```php
// Exemplo de uso da função pq_validLogin
$isValidLogin = pq_validLogin();
```
