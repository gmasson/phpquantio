<?php
/**
* PHPQuantio 1.9.0
* Micro biblioteca PHP com funções úteis para desenvolvimento web
* https://github.com/gmasson/phpquantio
* License MIT
*/

# Verifica e inicia a sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

# Verifica a versão do PHP
if (version_compare(phpversion(), '7.3', '<')) {
	die("Por favor, atualize o PHP para uma versão mais recente");
}

# Configura o Timezone se não estiver configurado
if(!ini_get('date.timezone')) {
	date_default_timezone_set('GMT');
}

# Inicializa as variáveis de sessão para controle de acessos
if (empty($_SESSION['pq_min'])) {
	$_SESSION['pq_min'] = date('i');
	$_SESSION['pq_hits'] = 1;
}

# Limita o acesso a 60 requisições por minuto
if ($_SESSION['pq_hits'] >= 60 && $_SESSION['pq_min'] == date('i')) {
	header('HTTP/1.1 429 Too Many Requests');
	header('Retry-After: 60');
	die("Muitas requisições. Por favor, tente novamente mais tarde.");
} elseif ($_SESSION['pq_min'] != date('i')) {
	$_SESSION['pq_hits'] = 0;
	$_SESSION['pq_min'] = date('i');
} else {
	$_SESSION['pq_hits']++;
}

/**
 * Filtra a entrada de dados de acordo com o tipo especificado
 *
 * @param string $input Dado de entrada a ser filtrado
 * @param string $type Tipo de filtragem a ser aplicado
 * 	- 'html': Filtra a entrada de conteúdo HTML, convertendo caracteres especiais em entidades HTML.
 * 	- 'url': Filtra a entrada de URL, substituindo espaços por underscores e convertendo caracteres especiais em entidades HTML.
 * 	- 'user': Filtra a entrada de nome de usuário, convertendo para minúsculas e substituindo espaços por underscores.
 * 	- 'email': Filtra a entrada de endereço de e-mail, convertendo para minúsculas, substituindo espaços por underscores e validando o formato do e-mail.
 * 	- 'get': Filtra a entrada do valor recebido via método GET, removendo caracteres especiais.
 * 	- 'post': Filtra a entrada do valor recebido via método POST, removendo caracteres especiais.
 * 	- 'pass': Filtra a entrada de senha, adicionando strings e realizando um hash SHA256.
 * @param string $add Dado adicional para algumas filtragens como 'get', 'post' ou 'pass'
 * @return mixed Retorna o dado filtrado
 */

function pq_filter($input, $type = '', $add = '') {
	$input = trim($input);
	switch ($type) {
		case 'html':
			$input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			break;

		case 'url':
			$input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$input = str_replace(" ", "_", $input);
			break;

		case 'user':
			$input = strtolower($input);
			$input = str_replace(" ", "_", $input);
			break;

		case 'email':
			$input = strtolower($input);
			$input = str_replace(" ", "_", $input);
			if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
				return $input;
			} else {
				return false;
			}
			break;

		case 'get':
			$input = filter_input(INPUT_GET, $input, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
			$input = ($input !== null && $input !== false && $input !== '') ? $input : $add;
			$input = trim($input);
			break;

		case 'post':
			$input = filter_input(INPUT_POST, $input, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
			$input = ($input !== null && $input !== false && $input !== '') ? $input : $add;
			$input = trim($input);
			break;

		case 'pass':
			$input = strrev($input);
			$salt = ($add == '') ? '168584+^@gS457 dat#yii' : $add;
			$input = $salt . '9+ç~x' . $input . $salt;
			$input = hash('sha256', $input);
			$input = substr($input, 0, -2);
			$input = '$2a$' . $input;
			break;

		default:
			$input = strip_tags($input);
			break;
	}
	return $input;
}

/**
 * Retorna informações sobre o cliente, servidor e ambiente de execução, incluindo endereço IP
 *
 * @param string $opt Opção para especificar qual informação retornar:
 * 	- 'ip': Retorna o endereço IP do cliente.
 * 	- 'browser': Retorna o agente do navegador (user-agent) do cliente.
 * 	- 'port': Retorna a porta do cliente.
 * 	- 'referer': Retorna a URL da página de onde o usuário veio.
 * 	- 'root': Retorna o diretório raiz do documento.
 * 	- 'host': Retorna o nome do host do servidor.
 * 	- 'server_addr': Retorna o endereço IP do servidor.
 * 	- 'server_name': Retorna o nome do servidor.
 * 	- 'server_software': Retorna o software do servidor.
 * @return mixed Retorna a informação solicitada (IP, navegador, etc.)
 */
function pq_info($opt = 'ip') {
	switch ($opt) {
		case 'ip':
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				return $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				return $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
				return $_SERVER['REMOTE_ADDR'];
			} else {
				return null;
			}
			break;
		case 'browser':
			return $_SERVER['HTTP_USER_AGENT'];
			break;
		case 'port':
			return $_SERVER['REMOTE_PORT'];
			break;
		case 'referer':
			return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
			break;
		case 'root':
			return $_SERVER['DOCUMENT_ROOT'];
			break;
		case 'host':
			return $_SERVER['HTTP_HOST'];
			break;
		case 'server_addr':
			return $_SERVER['SERVER_ADDR'];
			break;
		case 'server_name':
			return $_SERVER['SERVER_NAME'];
			break;
		case 'server_software':
			return $_SERVER['SERVER_SOFTWARE'];
			break;
		default:
			return null;
			break;
	}
}

/**
 * Envia um email usando a função mail do PHP
 *
 * @param string $email Endereço de email de destino
 * @param string $subject Assunto do email
 * @param string $body Corpo do email
 * @param string $from Endereço de email do remetente
 * @return bool Retorna True se o email for enviado com sucesso ou False caso contrário
 */
function pq_mail($email, $subject, $body, $from) {
	$headers = "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
	$headers .= "From: " . $from . "\r\n";
	$headers .= "Reply-To: " . $from . "\r\n";
	$headers .= "Return-Path: " . $from . "\r\n";
	$sendMail = mail($email, $subject, $body, $headers);
	if ($sendMail) {
		return true;
	} else {
		return false;
	}
}

/**
 * Gera um captcha simples com uma operação matemática
 *
 * @param string $name Nome do captcha para armazenamento na sessão
 * @return string Retorna a soma ou subtração gerada do captcha
 */
function pq_captcha($name = 'captcha') {
	$num1 = rand(0, 10);
	$num2 = rand(0, 10);
	$operation = (rand(0, 1) == 0) ? '+' : '-';
	if ($operation === '+') {
		$captchaText = "$num1 + $num2";
		$captchaResult = $num1 + $num2;
	} else {
		$captchaText = "$num1 - $num2";
		$captchaResult = $num1 - $num2;
	}
	$_SESSION[$name] = $captchaResult;
	return $captchaText;
}

/**
 * Valida a resposta do captcha fornecida pelo usuário
 *
 * @param string $input Resposta do usuário ao captcha
 * @param string $name Nome do captcha armazenado na sessão
 * @return bool Retorna True se a resposta estiver correta ou False caso contrário
 */
function pq_validCaptcha($input, $name = 'captcha') {
	if (isset($_SESSION[$name])) {
		$submitted = pq_filter($input);
		$correct = $_SESSION[$name];
		unset($_SESSION[$name]);
		if ($submitted === $correct) {
			return true;
		}
	}
	return false;
}

/**
 * Realiza o login com uma senha única
 *
 * @param string $input Senha fornecida pelo usuário
 * @param string $correctPass Senha correta para comparação
 * @param string $token Token para validar sessão
 * @return bool Retorna True se a senha estiver correta ou False caso contrário
 */
function pq_login($input, $correctPass, $token = 'YSB2aWRhIMOpIGN1cnRhIGUgYmVsYQ==') {
	$inputPass = pq_filter($input, 'post');
	if ($inputPass === $correctPass) {
		$_SESSION['pq_login'] = $token;
		return true;
	} else {
		return false;
	}
}

/**
 * Verifica se o usuário está logado
 *
 * @param string $token Token para validar sessão
 * @return bool True se o usuário estiver logado, False caso contrário
 */
function pq_validLogin($token = 'YSB2aWRhIMOpIGN1cnRhIGUgYmVsYQ==') {
	if ($_SESSION['pq_login'] === $token && !empty($_SESSION['pq_login'])) {
		return true;
	} else {
		return false;
	}
}