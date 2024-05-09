<?php
/**
* PHPQuantio 2.0
* Micro biblioteca PHP com funções úteis para desenvolvimento web
* https://github.com/gmasson/phpquantio
* License MIT
*/

/**
 * Token para uso interno.
 */
define('PHPQ_TOKEN', 'YSB2aWRhIMOpIGN1cnRhIGUgYmVsYQ==');

/**
 * Inicia a sessão se não estiver iniciada.
 */
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/**
 * Verifica a versão do PHP.
 */
if (version_compare(phpversion(), '7.3', '<')) {
	die("Por favor, atualize o PHP para uma versão mais recente");
}

/**
 * Limita o acesso por requisições em 1 minuto.
 * 
 * @param int $requests O número máximo de requisições permitidas por minuto.
 */
function phpq_sec($requests = 60) {
	if (empty($_SESSION['phpq_min'])) {
		$_SESSION['phpq_min'] = date('i');
		$_SESSION['phpq_hits'] = 1;
	}
	if ($_SESSION['phpq_hits'] >= $requests && $_SESSION['phpq_min'] == date('i')) {
		header('HTTP/1.1 429 Too Many Requests');
		header('Retry-After: 60');
		die("Muitas requisições. Por favor, tente novamente mais tarde.");
	} elseif ($_SESSION['phpq_min'] != date('i')) {
		$_SESSION['phpq_hits'] = 1;
		$_SESSION['phpq_min'] = date('i');
	} else {
		$_SESSION['phpq_hits']++;
	}
}

/**
 * Filtra a entrada de dados de acordo com o tipo especificado.
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
 * @param string $add Dado adicional para algumas filtragens como 'get', 'post' ou 'pass' (opcional)
 * @return mixed Retorna o dado filtrado
 */
function phpq_filter($input, $type = '', $add = '') {
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
 * Retorna informações sobre o cliente, servidor e ambiente de execução, incluindo endereço IP.
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
function phpq_info($opt = 'ip') {
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
			return false;
			break;
	}
}

/**
 * Obtém o status code de um link.
 *
 * @param string $url O URL do link a ser verificado.
 * @return int|bool O status code do link ou false em caso de erro.
 */
function phpq_status($url) {
    $curl = curl_init($url);
    if (!$curl) {
        return false;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        curl_close($curl);
        return false;
    } else {
        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $status_code;
    }
}

/**
 * Conta o número de registros em um arquivo JSON.
 *
 * @param string $path O caminho para o arquivo JSON.
 * @return int|false O número de registros se for bem-sucedido, falso caso contrário.
 */
function phpq_countJSON($path) {
    if (is_readable($path)) {
        $jsonContent = file_get_contents($path);
        $json_data = json_decode($jsonContent, true);
        if ($json_data !== null) {
            return count($json_data);
        } else {
            return false;
        }
    } else {
        return false;
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
function phpq_mail($email, $subject, $body, $from) {
	$headers = "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
	$headers .= "From: " . $from . "\r\n";
	$headers .= "Reply-To: " . $from . "\r\n";
	$headers .= "Return-Path: " . $from . "\r\n";
	$sendMail = mail($email, $subject, $body, $headers);
	return $sendMail;
}

/**
 * Gera um captcha simples com uma operação matemática
 *
 * @param string $name Nome do captcha para armazenamento na sessão (opcional)
 * @return string Retorna a soma ou subtração gerada do captcha
 */
function phpq_captcha($name = 'captcha') {
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
 * @param string $name Nome do captcha armazenado na sessão (opcional)
 * @return bool Retorna True se a resposta estiver correta ou False caso contrário
 */
function phpq_validCaptcha($input, $name = 'captcha') {
	if (isset($_SESSION[$name])) {
		$submitted = phpq_filter($input);
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
 * @param string $token Token para validar sessão (opcional)
 * @return bool Retorna True se a senha estiver correta ou False caso contrário
 */
function phpq_login($input, $correctPass, $token = PHPQ_TOKEN) {
	$inputPass = phpq_filter($input, 'post');
	if ($inputPass === $correctPass) {
		$_SESSION['phpq_login'] = $token;
		return true;
	} else {
		return false;
	}
}

/**
 * Verifica se o usuário está logado
 *
 * @param string $token Token para validar sessão (opcional)
 * @return bool True se o usuário estiver logado, False caso contrário
 */
function phpq_validLogin($token = PHPQ_TOKEN) {
	if ($_SESSION['phpq_login'] === $token && !empty($_SESSION['phpq_login'])) {
		return true;
	} else {
		return false;
	}
}
