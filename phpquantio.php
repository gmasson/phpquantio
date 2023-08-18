<?php
/**
* PHPQuantio 1.2
* Micro biblioteca PHP com funções úteis para desenvolvimento web
* https://github.com/gmasson/phpquantio
* License MIT
*/

# Verifica se tem session_start
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

# Verifica a versão do PHP
if ( phpversion() < '7.3' ) {
	die( "Please update PHP to a higher version" );
}

# Configura o Timezone
if( ! ini_get( 'date.timezone' ) ) {
	date_default_timezone_set('GMT');
}

# Filtro para textos
function pq_filter($input, $type = '') {
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
			$input = filter_input(INPUT_GET, $input, FILTER_SANITIZE_STRING);
			$input = ($input !== null && $input !== false && $input !== '') ? $input : $with_empty;
			break;

		case 'post':
			$input = filter_input(INPUT_POST, $input, FILTER_SANITIZE_STRING);
			$input = ($input !== null && $input !== false && $input !== '') ? $input : $with_empty;
			break;

		default:
			$input = strip_tags($input);
			break;
	}

	return $input;
}

# Gerador de senhas
function pq_pass($input, $key = 'try +', $substr = -1) {
	$input = pq_filter($input);
	$input = substr($input, 0, $substr);
	$input = strrev($input);
	$input = $input . $key;
	$input = password_hash($input, PASSWORD_DEFAULT);
	$input = '$2a$' . $input;
	return $input;
}

# Data Atual
function pq_date($type = '') {
	$formats = [
		'd' => 'd',
		'm' => 'm',
		'y' => 'Y',
		'a' => 'Y',
		'pt' => 'd/m/Y',
	];

	return isset($formats[$type]) ? date($formats[$type]) : date('Y/m/d');
}

# Hora atual
function pq_hour($type = '') {
	$formats = [
		'h' => 'h',
		'm' => 'i',
		's' => 's',
	];

	return isset($formats[$type]) ? date($formats[$type]) : date('H:i:s');
}

# Gerador de Hash
function pq_hash($size = 12) {
	$characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%-&*()_+=";
	$randomString = '';

	for ($i = 0; $i < $size; $i++) {
		$randomString .= $characters[rand(0, strlen($characters) - 1)];
	}

	return $randomString;
}

# Gerador de Loren Ipsum
function pq_lorem($length = 445) {
	$text = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
	$maxLength = 445;

	if ($length > $maxLength) {
		return $text;
	} else {
		return mb_substr($text, 0, $length, 'UTF-8');
	}
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

# Listar arquivos de uma pasta
function pq_fileListing($folder, $tag = '<p>', $endTag = '</p>') {
	$dir = dir($folder);
	while ($file = $dir->read()) {
		echo $tag . pq_filterText($file) . $endTag;
	}
	$dir->close();
}

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

function pq_validCaptcha($name = 'c21') {
	if (isset($_SESSION[$name]) && isset(pq_filterPost($name))) {
		$submittedResult = pq_filterPost($name);
		$correctResult = $_SESSION[$name];
		unset($_SESSION[$name]);
		return $submittedResult === $correctResult;
	}
	return false;
}
