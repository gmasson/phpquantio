# PHPQuantio
PHPQuantio é uma micro biblioteca PHP com funções úteis para desenvolvimento web.

## Configuração padrão

### Iniciando a sessão
A sessão é automaticamente iniciada se ainda não estiver iniciada.

### Verificação da Versão do PHP
A biblioteca requer PHP 7.3 ou superior. Se o PHP estiver em uma versão inferior, uma mensagem de erro será exibida.

## Documentação

### Limitação de Acesso por Requisições

```php
phpq_sec($requests);
```

Limita o acesso por requisições em 1 minuto. `$requests` é o número máximo de requisições permitidas por minuto.

**Exemplo de uso:**

```php
phpq_sec(60); // Limita a 60 requisições por minuto
// Retorna uma mensagem de erro se o limite de requisições for excedido
```

### Filtragem de Dados

```php
phpq_filter($input, $type, $add);
```

Filtra a entrada de dados de acordo com o tipo especificado. Os tipos disponíveis são:
- 'html': Filtra a entrada de conteúdo HTML.
- 'url': Filtra a entrada de URL.
- 'user': Filtra a entrada de nome de usuário.
- 'email': Filtra a entrada de endereço de e-mail ou retorna `false` se não for um email válido
- 'get': Filtra a entrada do valor recebido via método GET.
- 'post': Filtra a entrada do valor recebido via método POST.
- 'pass': Filtra a entrada de senha.

**Exemplo de uso:**

```php
$input = phpq_filter('gabriel@example.com', 'email');
// Retorna o dado filtrado
```

### Informações do Cliente e Servidor

```php
phpq_info($opt);
```

Retorna informações sobre o cliente, servidor e ambiente de execução. As opções disponíveis são:
- 'ip': Retorna o endereço IP do cliente.
- 'browser': Retorna o agente do navegador (user-agent) do cliente.
- 'port': Retorna a porta do cliente.
- 'referer': Retorna a URL da página de onde o usuário veio.
- 'root': Retorna o diretório raiz do documento.
- 'host': Retorna o nome do host do servidor.
- 'server_addr': Retorna o endereço IP do servidor.
- 'server_name': Retorna o nome do servidor.
- 'server_software': Retorna o software do servidor.

**Exemplo de uso:**

```php
$ip = phpq_info('ip');
// Retorna o endereço IP do cliente
```

### Verificação de Status de Link

```php
phpq_status($url);
```

Obtém o status code de um link.

**Exemplo de uso:**

```php
$status = phpq_status('https://www.example.com');
// Retorna o status code do link ou false em caso de erro
```

### Contagem de Registros em Arquivo JSON

```php
phpq_countJSON($path);
```

Conta o número de registros em um arquivo JSON.

**Exemplo de uso:**

```php
$count = phpq_countJSON('data.json');
// Retorna o número de registros se for bem-sucedido, falso caso contrário
```

### Envio de Email

```php
phpq_mail($email, $subject, $body, $from);
```

Envia um email usando a função mail do PHP.

**Exemplo de uso:**

```php
$sendMail = phpq_mail('destino@example.com', 'Assunto do Email', 'Corpo do Email', 'remetente@example.com');
// Retorna True se o email for enviado com sucesso ou False caso contrário
```

### Geração de Captcha

```php
phpq_captcha($name);
```

Gera um captcha simples com uma operação matemática.

**Exemplo de uso:**

```php
$captcha = phpq_captcha('captcha');
// Retorna a soma ou subtração gerada do captcha
```

### Validação de Captcha

```php
phpq_validCaptcha($input, $name);
```

Valida a resposta do captcha fornecida pelo usuário.

**Exemplo de uso:**

```php
$isValid = phpq_validCaptcha($_POST['captcha'], 'captcha');
// Retorna True se a resposta estiver correta ou False caso contrário
```

### Login com Senha Única

```php
phpq_login($input, $correctPass, $token);
```

Realiza o login com uma senha única.

**Exemplo de uso:**

```php
$isLogged = phpq_login($_POST['password'], 'senha_correta');
// Retorna True se a senha estiver correta ou False caso contrário
```

### Verificação de Login

```php
phpq_validLogin($token);
```

Verifica se o usuário está logado.

**Exemplo de uso:**

```php
$loggedIn = phpq_validLogin();
// Retorna True se o usuário estiver logado, False caso contrário
```

## Licença

Este projeto está licenciado sob a Licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.