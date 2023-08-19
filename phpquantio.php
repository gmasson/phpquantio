<?php
/**
* PHPQuantio 1.3
* Micro biblioteca PHP com funções úteis para desenvolvimento web
* https://github.com/gmasson/phpquantio
* License MIT
*/

# Verifica se tem session_start
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

# Verifica a versão do PHP
if (version_compare(phpversion(), '7.3', '<')) {
	die( "Please update PHP to a higher version" );
}

# Configura o Timezone
if(!ini_get('date.timezone') ) {
	date_default_timezone_set('GMT');
}

# Preenche a sessão para verificação de segurança
if (empty($_SESSION['pq_min'])) {
    $_SESSION['pq_min'] = date('i');
    $_SESSION['pq_hits'] = 1;
}

# Limite de 40 acessos por minuto
if ($_SESSION['pq_hits'] >= 40 && $_SESSION['pq_min'] == date('i')) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 60');  // Define o cabeçalho Retry-After para sugerir quando a próxima solicitação deve ser feita
    die("Too many requests. Please try again later.");
} elseif ($_SESSION['pq_min'] != date('i')) {
    $_SESSION['pq_hits'] = 0;
    $_SESSION['pq_min'] = date('i');
} else {
    $_SESSION['pq_hits']++;
}

# Filtro para textos
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

		case 'invert':
			$input = strrev($input);
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
			break;

		case 'post':
			$input = filter_input(INPUT_POST, $input, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
			$input = ($input !== null && $input !== false && $input !== '') ? $input : $add;
			break;

		case 'pass':
			$input = strrev($input);
			$salt = ($add == '') ? '+154 56541@gSmmGHSdat #yii' : $add ;
			$salt = $salt . '984477' . $salt . '984477554984477';
			$input = md5('+422554984471654215435415421547' . $input . $salt);
			$input = substr($input, 0, -3);
			$input = '$2a$' . $input . '$';
			break;

		default:
			$input = strip_tags($input);
			break;
	}

	return $input;
}

# Gerador de senha
function pq_pass($input, $salt = '+154 56541@gSmmGHSdat #yii') {
	$input = strrev($input);
	$salt = $salt . '984477' . $salt . '984477554984477';
	$input = md5('+422554984471654215435415421547' . $input . $salt);
	$input = substr($input, 0, -3);
	$input = '$2a$' . $input . '$';
	return $input;
}

# Data e hora atual
function pq_time($type = '') {
	$formats = [
		'd' => 'd',
		'm' => 'm',
		'y' => 'Y',
		'a' => 'Y',
		'data' => 'd/m/Y',
		'date' => 'Y/m/d',
		'h' => 'h',
		'm' => 'i',
		's' => 's',
		'hora' => 'H:i:s',
		'hour' => 'H:i:s',
	];

	return isset($formats[$type]) ? date($formats[$type]) : date('Y/m/d - H:i:s');
}

# Envio de e-mail usando Mail
function pq_mail($email, $subject, $body, $from) {
	$headers = "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
	$headers .= "From: " . $from . "\r\n";
	$headers .= "Reply-To: " . $from . "\r\n";
	$headers .= "Return-Path: " . $from . "\r\n";
	$sendMail = mail($email, $subject, $body, $headers);

	return $sendMail;
}

# Navegador do usuário
function pq_browser() {
	return $_SERVER['HTTP_USER_AGENT'];
}

# IP do usuário
function pq_ip() {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		return $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		return $_SERVER['REMOTE_ADDR'];
	}
}

# Gerador de captcha
function pq_captcha($name = 'c21', $class = '') {
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

	$captchaHTML = "<label for='$name'>$captchaText = </label>";
	$captchaHTML .= "<input type='number' class='$class' name='$name' required>";

	return $captchaHTML;
}

# Validador do captcha
function pq_validCaptcha($value, $name = 'c21') {
	if (isset($_SESSION[$name])) {
		$submittedResult = pq_filter($name);
		$correctResult = $_SESSION[$name];
		unset($_SESSION[$name]);
		return $submittedResult === $correctResult;
	}
	return false;
}
