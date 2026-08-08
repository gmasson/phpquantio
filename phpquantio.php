<?php
/**
 * PHPQuantio 3.2
 * Micro biblioteca PHP de arquivo único para desenvolvimento rápido de
 * ferramentas e sites seguros. Zero dependências, funções estáticas amplas.
 * https://github.com/gmasson/phpquantio
 * License MIT
 */

declare(strict_types=1);

class PHPQuantio
{
	/** Versão mínima do PHP suportada. */
	private const MIN_PHP_VERSION = '8.2';

	/** Nome da chave de sessão do gate private(). */
	private const SESSION_LOGIN_KEY = 'phpq_private';

	/** Nome da chave de sessão do token CSRF. */
	private const SESSION_CSRF_KEY = 'phpq_csrf';

	/** Extensões obrigatórias (mbstring para manipulação UTF-8). */
	private const REQUIRED_EXTENSIONS = ['mbstring'];

	/** Extensões recomendadas (usadas quando disponíveis). */
	private const RECOMMENDED_EXTENSIONS = ['iconv', 'curl', 'finfo'];

	/** Se proxies confiáveis devem ser honrados (X-Forwarded-*). Defina true
	 *  apenas quando o app roda atrás de um proxy/CDN conhecido (ex.: Cloudflare). */
	private static bool $trustProxy = false;

	/** Habilita confiança em headers de proxy (X-Forwarded-Proto/For).
	 *  Chame antes de headers()/init() se estiver atrás de proxy/CDN. */
	public static function trustProxy(bool $enabled = true): void
	{
		self::$trustProxy = $enabled;
	}

	/** Cache do JSON body decodificado (php://input só pode ser lido uma vez). */
	private static ?array $jsonCache = null;

	/**
	 * Garante versão do PHP, extensões obrigatórias e sessão iniciada com
	 * cookie endurecido (HttpOnly, Secure quando em HTTPS, SameSite=Lax).
	 * Idempotente.
	 */
	private static function init(): void
	{
		static $inited = false;
		if ($inited) {
			return;
		}

		if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
			die('Atualize o PHP para a versão >= ' . self::MIN_PHP_VERSION);
		}

		// Verifica extensões obrigatórias
		foreach (self::REQUIRED_EXTENSIONS as $ext) {
			if (!extension_loaded($ext)) {
				die("PHPQuantio requer a extensão PHP: {$ext}");
			}
		}

		if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
			session_set_cookie_params([
				'lifetime' => 0,
				'path'     => '/',
				'secure'   => self::isHttps(),
				'httponly' => true,
				'samesite' => 'Lax',
			]);
			session_start();
			// Regenera ID de sessão a cada 30 minutos (previne fixation)
			if (empty($_SESSION['phpq_last_regeneration']) || 
			    (time() - $_SESSION['phpq_last_regeneration']) > 1800) {
				session_regenerate_id(true);
				$_SESSION['phpq_last_regeneration'] = time();
			}
		}

		$inited = true;
	}

	/**
	 * Verifica se uma extensão PHP está disponível.
	 * Útil para verificar extensões opcionais (curl, finfo, iconv).
	 */
	public static function hasExtension(string $name): bool
	{
		return extension_loaded($name);
	}

	/** Detecta HTTPS. Só honra X-Forwarded-Proto quando trustProxy() foi habilitado. */
	private static function isHttps(): bool
	{
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			return true;
		}
		return self::$trustProxy && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
	}

	/**
	 * Acesso unificado às fontes de entrada. Faz trim em strings e devolve
	 * $fallback quando o valor está ausente/vazio. Se $key for omitido,
	 * retorna a fonte inteira.
	 *
	 * Fontes de usuário (get, post, request, cookie) são SANITIZADAS contra
	 * XSS (htmlspecialchars) na fronteira. As demais (session, server, env,
	 * ip, header, bearer, json) são internas ou usadas em lógica/comparação,
	 * então retornam sem escape para não corromper o dado.
	 *
	 * @param string $source get|post|request|cookie|session|server|env|header|bearer|json|ip
	 * @param string $key    Nome do campo (não usado em ip/bearer; opcional em json).
	 * @param mixed  $fallback Valor retornado se o campo não existir/for vazio.
	 * @return mixed
	 */
	public static function capture(string $source, string $key = '', mixed $fallback = null): mixed
	{
		if ($source === 'session') {
			self::init();
		}

		$sanitized = in_array($source, ['get', 'post', 'request', 'cookie'], true);

		switch ($source) {
			case 'ip':
				return self::clientIp() ?? $fallback;

			case 'bearer':
				$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
				if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
					return trim($m[1]);
				}
				return $fallback;

			case 'header':
				// Normaliza o nome do header para a chave $_SERVER correspondente
				// (HTTP_ + uppercase + underscores), aceitando tanto 'User-Agent'
				// quanto 'user-agent' ou 'user_agent'.
				$name = 'HTTP_' . strtoupper(str_replace(['-', ' '], '_', $key));
				return isset($_SERVER[$name]) ? trim((string) $_SERVER[$name]) : $fallback;

			case 'env':
				if ($key === '') {
					return $_ENV;
				}
				$val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
				if ($val === false || $val === null || $val === '') {
					return $fallback;
				}
				return (string) $val;

			case 'json':
				if (self::$jsonCache === null) {
					$raw = file_get_contents('php://input');
					$decoded = $raw !== false ? json_decode($raw, true) : null;
					self::$jsonCache = is_array($decoded) ? $decoded : [];
				}
				if (self::$jsonCache === []) {
					return $fallback;
				}
				return $key === '' ? self::$jsonCache : (self::$jsonCache[$key] ?? $fallback);

			case 'session':
				if ($key === '') {
					return $_SESSION ?? $fallback;
				}
				return $_SESSION[$key] ?? $fallback;

			case 'get':
			case 'post':
			case 'request':
			case 'cookie':
			case 'server':
				$store = match ($source) {
					'get'     => $_GET,
					'post'    => $_POST,
					'request' => $_REQUEST,
					'cookie'  => $_COOKIE,
					'server'  => $_SERVER,
				};

				if ($key === '') {
					return $sanitized ? self::sanitizeDeep($store) : $store;
				}
				if (!isset($store[$key])) {
					return $fallback;
				}

				$value = $store[$key];
				if (is_array($value)) {
					return $sanitized ? self::sanitizeDeep($value) : $value;
				}
				if (!is_scalar($value)) {
					return $fallback;
				}

				$value = trim((string) $value);
				if ($value === '') {
					return $fallback;
				}
				return $sanitized ? self::sanitizeScalar($value) : $value;

			default:
				return $fallback;
		}
	}

	/** Escapa um valor escalar para HTML (defesa XSS na fronteira). */
	private static function sanitizeScalar(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/** Sanitiza recursivamente um array de entrada. */
	private static function sanitizeDeep(mixed $value): mixed
	{
		if (is_array($value)) {
			$out = [];
			foreach ($value as $k => $v) {
				$out[$k] = self::sanitizeDeep($v);
			}
			return $out;
		}
		if (is_scalar($value)) {
			return self::sanitizeScalar(trim((string) $value));
		}
		return $value;
	}

	/**
	 * Resolve o IP do cliente. Por padrão usa REMOTE_ADDR (confiável). Só
	 * confia em X-Forwarded-For quando trustProxy() foi habilitado E você
	 * sabe que está atrás de um proxy/CDN que define esse header (ex.: Cloudflare).
	 */
	private static function clientIp(): ?string
	{
		if (self::$trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}

		$remote = $_SERVER['REMOTE_ADDR'] ?? '';
		return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
	}

	/**
	 * Valida um dado contra um tipo/regra. Retorna sempre true|false.
	 *
	 * @param mixed  $input Valor a verificar.
	 * @param string $type  required|int|float|numeric|bool|email|url|domain|ip|
	 *                       ipv4|ipv6|slug|uuid|min|max|length|between|in|not_in|
	 *                       regex|matches|different|cpf|cnpj|phone_br|cep|
	 *                       credit_card|json|password|date|csrf|captcha|
	 *                       hex|base64|alnum|alpha|digit|phone_intl|time
	 * @param mixed  $extra Parâmetro da regra (tamanho, faixa [min,max], lista,
	 *                       padrão regex, valor a comparar, opções de senha, etc.).
	 */
	public static function valid(mixed $input, string $type, mixed $extra = null): bool
	{
		$str = is_scalar($input) ? (string) $input : '';

		return match ($type) {
			'required' => is_array($input) ? $input !== [] : trim($str) !== '',
			'int'      => filter_var($str, FILTER_VALIDATE_INT) !== false,
			'float'    => filter_var($str, FILTER_VALIDATE_FLOAT) !== false,
			'numeric'  => is_numeric($str),
			'bool'     => filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,

			'email'    => filter_var($str, FILTER_VALIDATE_EMAIL) !== false,
			'url'      => filter_var($str, FILTER_VALIDATE_URL) !== false
				&& in_array(strtolower((string) (parse_url($str, PHP_URL_SCHEME) ?? '')), ['http', 'https'], true),
			'domain'   => (bool) preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/i', $str),
			'ip'       => filter_var($str, FILTER_VALIDATE_IP) !== false,
			'ipv4'     => filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
			'ipv6'     => filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
			'slug'     => (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $str),
			'uuid'     => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $str),

			'min'      => mb_strlen($str) >= (int) $extra,
			'max'      => mb_strlen($str) <= (int) $extra,
			'length'   => mb_strlen($str) === (int) $extra,
			'between'  => is_array($extra) && count($extra) === 2 && is_numeric($str)
				&& (float) $str >= (float) $extra[0] && (float) $str <= (float) $extra[1],
			'in'       => is_array($extra) && in_array($str, self::scalarToStrings($extra), true),
			'not_in'   => is_array($extra) && !in_array($str, self::scalarToStrings($extra), true),
			'regex'    => is_string($extra) && $extra !== '' && (bool) preg_match($extra, $str),
			'matches'  => hash_equals($str, is_scalar($extra) ? (string) $extra : ''),
			'different' => $str !== (is_scalar($extra) ? (string) $extra : ''),

			'hex'      => (bool) preg_match('/^[0-9a-f]+$/i', $str),
			'base64'   => self::validBase64($str),
			'alnum'    => (bool) preg_match('/^[a-zA-Z0-9]+$/', $str),
			'alpha'    => (bool) preg_match('/^[a-zA-Z]+$/', $str),
			'digit'    => (bool) preg_match('/^\d+$/', $str),
			'phone_intl' => self::validPhoneIntl($str),
			'time'     => self::validTime($str),

			'cpf'        => self::validCpf($str),
			'cnpj'       => self::validCnpj($str),
			'phone_br'   => self::validPhoneBr($str),
			'cep'        => self::validCep($str),
			'credit_card'=> self::validCreditCard($str),
			'json'       => self::validJson($str),
			'password'   => self::validStrongPassword($str, is_array($extra) ? $extra : []),

			'date'     => self::validDate($str, is_string($extra) && $extra !== '' ? $extra : 'Y-m-d'),

			'csrf'     => self::validCsrf($str, is_string($extra) && $extra !== '' ? $extra : ''),
			'captcha'  => self::validCaptcha($str, is_string($extra) && $extra !== '' ? $extra : 'captcha'),

			default    => false,
		};
	}

	/** Converte valores escalares de um array em strings (ignora não-escalares). */
	private static function scalarToStrings(array $values): array
	{
		$out = [];
		foreach ($values as $v) {
			if (is_scalar($v) || $v === null) {
				$out[] = (string) $v;
			}
		}
		return $out;
	}

	/** Remove caracteres não-dígitos (cache para evitar múltiplas chamadas). */
	private static function digitsOnly(string $value): string
	{
		return preg_replace('/\D/', '', $value) ?? '';
	}

	/** Valida CEP brasileiro (8 dígitos, com ou sem hífen). */
	private static function validCep(string $cep): bool
	{
		$digits = self::digitsOnly($cep);
		return strlen($digits) === 8;
	}

	/** Valida número de cartão de crédito via algoritmo de Luhn (com ou sem máscara). */
	private static function validCreditCard(string $card): bool
	{
		$digits = self::digitsOnly($card);
		$len = strlen($digits);
		if ($len < 13 || $len > 19) {
			return false;
		}
		$sum = 0;
		$alt = false;
		for ($i = $len - 1; $i >= 0; $i--) {
			$n = (int) $digits[$i];
			if ($alt) {
				$n *= 2;
				if ($n > 9) {
					$n -= 9;
				}
			}
			$sum += $n;
			$alt = !$alt;
		}
		return $sum % 10 === 0;
	}

	/** Valida se uma string é JSON válido. */
	private static function validJson(string $value): bool
	{
		if ($value === '') {
			return false;
		}
		json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE;
	}

	/** Valida telefone brasileiro: 10 dígitos (fixo) ou 11 (celular com 9º dígito). */
	private static function validPhoneBr(string $phone): bool
	{
		$digits = self::digitsOnly($phone);
		if (strlen($digits) === 10) {
			// Fixo: DDD 11-99 + 8 dígitos (primeiro 2 a 5)
			return (bool) preg_match('/^[1-9]{2}[2-5]\d{7}$/', $digits);
		}
		if (strlen($digits) === 11) {
			// Celular: DDD 11-99 + 9 + 8 dígitos (primeiro 6 a 9)
			return (bool) preg_match('/^[1-9]{2}9[6-9]\d{7}$/', $digits);
		}
		return false;
	}

	/** Verifica dígitos verificadores de CPF (aceita com ou sem máscara). */
	private static function validCpf(string $cpf): bool
	{
		$cpf = self::digitsOnly($cpf);
		if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
			return false;
		}
		for ($t = 9; $t < 11; $t++) {
			$sum = 0;
			for ($i = 0; $i < $t; $i++) {
				$sum += (int) $cpf[$i] * (($t + 1) - $i);
			}
			$digit = (($sum * 10) % 11) % 10;
			if ((int) $cpf[$t] !== $digit) {
				return false;
			}
		}
		return true;
	}

	/** Verifica dígitos verificadores de CNPJ numérico (aceita com ou sem máscara). */
	private static function validCnpj(string $cnpj): bool
	{
		$cnpj = self::digitsOnly($cnpj);
		if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
			return false;
		}
		$calc = static function (string $cnpj, int $length): int {
			$weights = $length === 12
				? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
				: [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
			$sum = 0;
			for ($i = 0; $i < $length; $i++) {
				$sum += (int) $cnpj[$i] * $weights[$i];
			}
			$rest = $sum % 11;
			return $rest < 2 ? 0 : 11 - $rest;
		};
		if ($calc($cnpj, 12) !== (int) $cnpj[12]) {
			return false;
		}
		return $calc($cnpj, 13) === (int) $cnpj[13];
	}

	/** Verifica requisitos mínimos de força de senha. */
	private static function validStrongPassword(string $password, array $options = []): bool
	{
		$minLength      = (int) ($options['min_length'] ?? 8);
		$requireUpper   = (bool) ($options['require_upper'] ?? true);
		$requireLower   = (bool) ($options['require_lower'] ?? true);
		$requireNumber  = (bool) ($options['require_number'] ?? true);
		$requireSpecial = (bool) ($options['require_special'] ?? true);

		if (mb_strlen($password) < $minLength) {
			return false;
		}
		if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
			return false;
		}
		if ($requireLower && !preg_match('/[a-z]/', $password)) {
			return false;
		}
		if ($requireNumber && !preg_match('/\d/', $password)) {
			return false;
		}
		if ($requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
			return false;
		}
		return true;
	}

	/** Valida se uma string é base64 válido. */
	private static function validBase64(string $value): bool
	{
		if ($value === '') {
			return false;
		}
		$decoded = base64_decode($value, true);
		return $decoded !== false && base64_encode($decoded) === $value;
	}

	/** Valida telefone internacional (formato E.164). */
	private static function validPhoneIntl(string $phone): bool
	{
		// E.164: + seguido de até 15 dígitos
		return (bool) preg_match('/^\+[1-9]\d{1,14}$/', $phone);
	}

	/** Valida formato de hora (HH:MM ou HH:MM:SS). */
	private static function validTime(string $time): bool
	{
		return (bool) preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
	}

	/** Valida uma data no formato informado (sem tolerar overflow, ex.: 31/02). */
	private static function validDate(string $value, string $format): bool
	{
		$dt = \DateTime::createFromFormat($format, $value);
		return $dt !== false && $dt->format($format) === $value;
	}

	/**
	 * Valida o token CSRF contra o guardado na sessão (tempo constante).
	 * O token NÃO é consumido: segue o padrão synchronizer (reutilizável na
	 * sessão), o que evita quebrar formulários com múltiplos submits/AJAX.
	 * Use $namespace para múltiplos formulários na mesma página (cada um com
	 * seu próprio token), evitando que um formulário sobrescreva o token de
	 * outro na sessão.
	 */
	private static function validCsrf(string $value, string $namespace = ''): bool
	{
		self::init();
		$key = self::SESSION_CSRF_KEY . ($namespace !== '' ? '_' . $namespace : '');
		if (empty($_SESSION[$key]) || $value === '') {
			return false;
		}
		return hash_equals($_SESSION[$key], $value);
	}

	/** Valida o captcha e CONSOME a resposta (uso único, evita replay). */
	private static function validCaptcha(string $value, string $name): bool
	{
		self::init();
		if (!isset($_SESSION['phpq_captcha_' . $name])) {
			return false;
		}
		$correct = (int) $_SESSION['phpq_captcha_' . $name];
		unset($_SESSION['phpq_captcha_' . $name]);
		return is_numeric(trim($value)) && (int) trim($value) === $correct;
	}

	/**
	 * Transforma um dado para um contexto de saída ou formato específico.
	 *
	 * @param string $input Dado de entrada.
	 * @param string $type  tags|html|html_attr|digits|alpha|alnum|slug|filename|
	 *                       urlsafe|mask|truncate|pass|verify|json|base64_encode|
	 *                       base64_decode|url_encode|url_decode
	 * @param mixed  $extra slug: separador (string); mask: padrão da máscara
	 *                       (string); truncate: tamanho (int); pass: opções do
	 *                       password_hash (array); verify: hash a comparar (string).
	 * @return string|bool String transformada; bool em 'verify'.
	 */
	public static function filter(string $input, string $type = 'tags', mixed $extra = null): string|bool
	{
		$input = trim($input);

		switch ($type) {
			case 'html':
				return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			case 'html_attr':
				return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			case 'digits':
				return preg_replace('/\D+/', '', $input) ?? '';

			case 'alpha':
				return preg_replace('/[^a-zA-Z]+/', '', $input) ?? '';

			case 'alnum':
				return preg_replace('/[^a-zA-Z0-9]+/', '', $input) ?? '';

			case 'slug':
				return self::toSlug($input, is_string($extra) && $extra !== '' ? $extra : '-');

			case 'filename':
				$name = basename($input);
				$name = preg_replace('/[^\w.\-]+/u', '_', $name) ?? '';
				$name = preg_replace('/\.{2,}/', '.', $name) ?? '';
				$name = trim($name, '.');
				return $name !== '' ? $name : 'file';

			case 'urlsafe':
				// Mantém apenas caracteres não reservados do RFC 3986 (A-Za-z0-9-._~).
				// Adequado para COMPONENTES de path de URL (slugs, segmentos) —
				// NUNCA usar em URLs completas (remove ':' e '/').
				return preg_replace('/[^A-Za-z0-9\-._~]/', '', $input) ?? '';

			case 'mask':
				return self::applyMask($input, is_string($extra) ? $extra : '');

			case 'truncate':
				return self::truncateText($input, is_int($extra) ? $extra : 100);

			case 'pass':
				$options = is_array($extra) ? $extra : [];
				return password_hash($input, PASSWORD_BCRYPT, $options);

			case 'verify':
				$hash = is_string($extra) ? $extra : '';
				return $hash !== '' && password_verify($input, $hash);

			case 'json':
				return json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			case 'base64_encode':
				return base64_encode($input);

			case 'base64_decode':
				$decoded = base64_decode($input, true);
				return $decoded !== false ? $decoded : '';

			case 'url_encode':
				return urlencode($input);

			case 'url_decode':
				return urldecode($input);

			case 'tags':
			default:
				return strip_tags($input);
		}
	}

	/** Mapa de transliteração pt-BR de fallback (usado quando iconv falha). */
	private static function transliteratePtBr(string $text): string
	{
		$map = [
			'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
			'ç' => 'c', 'ñ' => 'n',
			'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
			'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
			'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
			'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
			'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
			'Ç' => 'C', 'Ñ' => 'N',
		];
		return strtr($text, $map);
	}

	/** Gera slug legível: translitera acentos, minúsculas, separador. */
	private static function toSlug(string $text, string $separator = '-'): string
	{
		$transliterated = false;
		if (function_exists('iconv')) {
			$t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
			if ($t !== false && $t !== '') {
				$text = $t;
				$transliterated = true;
			}
		}
		if (!$transliterated) {
			$text = self::transliteratePtBr($text);
		}
		$text = strtolower($text);
		$text = preg_replace('/[^a-z0-9]+/', $separator, $text) ?? '';
		$text = trim($text, $separator);
		$text = preg_replace('/' . preg_quote($separator, '/') . '{2,}/', $separator, $text) ?? '';
		return $text;
	}

	/** Aplica máscara com '#' como coringa (ex.: ###.###.###-##). */
	private static function applyMask(string $value, string $mask): string
	{
		$value = preg_replace('/\D/', '', $value) ?? '';
		$result = '';
		$pos = 0;
		$len = strlen($value);
		$maskLen = strlen($mask);
		for ($i = 0; $i < $maskLen && $pos < $len; $i++) {
			$result .= $mask[$i] === '#' ? $value[$pos++] : $mask[$i];
		}
		return $result;
	}

	/** Trunca preservando palavras inteiras. */
	private static function truncateText(string $text, int $length = 100, string $suffix = '...'): string
	{
		if (mb_strlen($text) <= $length) {
			return $text;
		}
		$truncated = mb_substr($text, 0, $length);
		$lastSpace = mb_strrpos($truncated, ' ');
		if ($lastSpace !== false) {
			$truncated = mb_substr($truncated, 0, $lastSpace);
		}
		return $truncated . $suffix;
	}

	/**
	 * Converte um dado entre formatos.
	 *
	 * @param mixed  $input Valor de entrada.
	 * @param string $type  upper|lower|title|date_br|datetime_br|timeago|
	 *                       email_hide|phone_hide|name_hide|bytes
	 * @param mixed  $extra Reservado para tipos futuros.
	 * @return string
	 */
	public static function convert(mixed $input, string $type, mixed $extra = ''): string
	{
		$str = is_scalar($input) ? (string) $input : '';

		return match ($type) {
			'upper' => mb_strtoupper($str, 'UTF-8'),
			'lower' => mb_strtolower($str, 'UTF-8'),
			'title' => mb_convert_case($str, MB_CASE_TITLE, 'UTF-8'),

			'date_br'     => self::formatDate($input, 'd/m/Y'),
			'datetime_br' => self::formatDate($input, 'd/m/Y H:i'),
			'timeago'     => self::timeAgo($input),

			'email_hide' => self::maskEmail($str),
			'phone_hide' => self::maskPhone($str),
			'name_hide'  => self::maskName($str),

			'bytes' => self::humanBytes(is_numeric($str) ? (int) $str : 0),

			default => '',
		};
	}

	/** Formata data (string via strtotime, ou timestamp) no formato dado. */
	private static function formatDate(mixed $datetime, string $format): string
	{
		$ts = is_int($datetime) ? $datetime : strtotime((string) $datetime);
		return $ts === false ? '' : date($format, $ts);
	}

	/** Texto relativo em português ("há 5 minutos"). */
	private static function timeAgo(mixed $datetime): string
	{
		$ts = is_int($datetime) ? $datetime : strtotime((string) $datetime);
		if ($ts === false) {
			return '';
		}
		$diff = time() - $ts;
		if ($diff < 0) {
			return 'no futuro';
		}
		$intervals = [31536000 => 'ano', 2592000 => 'mês', 604800 => 'semana', 86400 => 'dia', 3600 => 'hora', 60 => 'minuto'];
		foreach ($intervals as $seconds => $label) {
			$count = (int) floor($diff / $seconds);
			if ($count >= 1) {
				$plural = $label === 'mês' ? 'meses' : $label . 's';
				return 'há ' . $count . ' ' . ($count === 1 ? $label : $plural);
			}
		}
		return 'agora mesmo';
	}

	/** Máscara LGPD de e-mail: g*****l@dominio.com */
	private static function maskEmail(string $email): string
	{
		if (!str_contains($email, '@')) {
			return $email;
		}
		[$local, $domain] = explode('@', $email, 2);
		$len = mb_strlen($local);
		if ($len <= 2) {
			$masked = str_repeat('*', max(1, $len));
		} else {
			$masked = mb_substr($local, 0, 1) . str_repeat('*', $len - 2) . mb_substr($local, -1);
		}
		return $masked . '@' . $domain;
	}

	/** Máscara LGPD de telefone BR: (11) ****-4321 */
	private static function maskPhone(string $phone): string
	{
		$d = preg_replace('/\D/', '', $phone) ?? '';
		if (strlen($d) < 10) {
			return str_repeat('*', max(1, strlen($d)));
		}
		$ddd  = substr($d, 0, 2);
		$last = substr($d, -4);
		return "($ddd) ****-$last";
	}

	/** Máscara LGPD de nome: primeiro nome + inicial do último. */
	private static function maskName(string $name): string
	{
		$parts = preg_split('/\s+/', trim($name)) ?: [];
		if (count($parts) < 2) {
			return $name;
		}
		return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
	}

	/** Humaniza bytes: 1536000 -> "1.5 MB". */
	private static function humanBytes(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		$bytes = max(0, $bytes);
		$pow = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
		$pow = min($pow, count($units) - 1);
		$value = $bytes / (1024 ** $pow);
		return ($pow === 0 ? (string) $value : number_format($value, 1)) . ' ' . $units[$pow];
	}

	/**
	 * Gera identificadores, tokens e segredos com gerador criptográfico.
	 *
	 * @param string $type  token|string|uuid|code|pin|password|nonce|csrf|
	 *                       csrf_field|captcha
	 * @param mixed  $extra token: nº de bytes (int); string/code/pin/password:
	 *                       tamanho (int); csrf_field/captcha: nome do campo (string).
	 * @param mixed  $extra2 string: charset (string).
	 * @return string
	 */
	public static function generate(string $type, mixed $extra = null, mixed $extra2 = null): string
	{
		switch ($type) {
			case 'token':
				$bytes = is_int($extra) && $extra > 0 ? $extra : 32;
				return bin2hex(random_bytes($bytes));

			case 'nonce':
				return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

			case 'uuid':
				$b = random_bytes(16);
				$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
				$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
				return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));

			case 'string':
				$length  = is_int($extra) && $extra > 0 ? $extra : 16;
				$charset = is_string($extra2) && $extra2 !== ''
					? $extra2
					: 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
				return self::randomFrom($length, $charset);

			case 'code':
				$length = is_int($extra) && $extra > 0 ? $extra : 6;
				return self::randomFrom($length, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

			case 'pin':
				$length = is_int($extra) && $extra > 0 ? $extra : 4;
				return self::randomFrom($length, '0123456789');

			case 'password':
				$length = is_int($extra) && $extra >= 8 ? $extra : 16;
				return self::strongPassword($length);

			case 'csrf':
				self::init();
				$namespace = is_string($extra) && $extra !== '' ? $extra : '';
				$key = self::SESSION_CSRF_KEY . ($namespace !== '' ? '_' . $namespace : '');
				if (empty($_SESSION[$key])) {
					$_SESSION[$key] = bin2hex(random_bytes(32));
				}
				return $_SESSION[$key];

			case 'csrf_field':
				// $extra = nome do campo HTML (string). Para múltiplos formulários
				// na mesma página, use $extra2 como namespace único por formulário.
				$name      = is_string($extra) && $extra !== '' ? $extra : 'csrf_token';
				$namespace = is_string($extra2) && $extra2 !== '' ? $extra2 : '';
				$token     = self::generate('csrf', $namespace);
				return '<input type="hidden" name="' . $name . '" value="' . $token . '">';

			case 'captcha':
				self::init();
				$name = is_string($extra) && $extra !== '' ? $extra : 'captcha';
				$a = random_int(10, 40);
				$b = random_int(1, 9);
				$op = random_int(0, 1) === 0 ? '+' : '-';
				$result = $op === '+' ? $a + $b : $a - $b; // $a > $b garante resultado positivo
				$_SESSION['phpq_captcha_' . $name] = $result;
				return "{$a} {$op} {$b}";

			default:
				return '';
		}
	}

	/** Sorteia $length caracteres de um charset usando CSPRNG. */
	private static function randomFrom(int $length, string $charset): string
	{
		$max = strlen($charset) - 1;
		if ($max < 0 || $length <= 0) {
			return '';
		}
		$out = '';
		for ($i = 0; $i < $length; $i++) {
			$out .= $charset[random_int(0, $max)];
		}
		return $out;
	}

	/** Senha forte com as 4 classes garantidas, embaralhada. */
	private static function strongPassword(int $length): string
	{
		$upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
		$lower = 'abcdefghijkmnopqrstuvwxyz';
		$digit = '23456789';
		$symbol = '!@#$%&*?-_';
		$all = $upper . $lower . $digit . $symbol;

		$chars = [
			$upper[random_int(0, strlen($upper) - 1)],
			$lower[random_int(0, strlen($lower) - 1)],
			$digit[random_int(0, strlen($digit) - 1)],
			$symbol[random_int(0, strlen($symbol) - 1)],
		];
		for ($i = count($chars); $i < $length; $i++) {
			$chars[] = $all[random_int(0, strlen($all) - 1)];
		}
		for ($i = count($chars) - 1; $i > 0; $i--) {
			$j = random_int(0, $i);
			[$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
		}
		return implode('', $chars);
	}

	/**
	 * Envia um conjunto de headers de segurança. Não faz nada se os headers
	 * já foram enviados.
	 *
	 * @param string $preset  'strict' (padrão), 'relaxed' ou 'api'.
	 * @param array  $overrides Headers a sobrescrever/adicionar (nome => valor).
	 */
	public static function headers(string $preset = 'strict', array $overrides = []): void
	{
		if (headers_sent()) {
			return;
		}

		$base = [
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
		];

		$byPreset = match ($preset) {
			'relaxed' => [
				'X-Frame-Options'         => 'SAMEORIGIN',
				'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'",
			],
			'api' => [
				'X-Frame-Options'         => 'DENY',
				'Cache-Control'           => 'no-store',
				'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
			],
			default => [ // strict
				'X-Frame-Options'         => 'DENY',
				'Content-Security-Policy' => "default-src 'self'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
			],
		};

		$headers = array_merge($base, $byPreset, $overrides);

		if (self::isHttps() && !isset($headers['Strict-Transport-Security'])) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
		}

		header_remove('X-Powered-By');
		foreach ($headers as $name => $value) {
			header($name . ': ' . $value);
		}
	}

	/**
	 * Rate limiting persistido em arquivo, com chave por IP (ou chave custom).
	 * Diferente de sessão: o limite vale mesmo para clientes que não enviam
	 * cookie. Ao exceder, responde 429 com Retry-After e encerra a execução.
	 *
	 * @param int    $requests Máximo de requisições por janela.
	 * @param int    $window   Duração da janela em segundos.
	 * @param string $by       'ip' (padrão) ou uma chave própria (ex.: ID do usuário).
	 */
	public static function rateLimit(int $requests = 60, int $window = 60, string $by = 'ip', ?string $storageDir = null): void
	{
		$key  = $by === 'ip' ? (self::clientIp() ?? 'unknown') : $by;
		$dir  = $storageDir ?? sys_get_temp_dir();
		$file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'phpq_rl_' . hash('sha256', $key);
		$now  = time();

		$fh = @fopen($file, 'c+');
		if ($fh === false) {
			return; // storage indisponível: não derruba o site (fail-open p/ rate limit)
		}

		flock($fh, LOCK_EX);
		$data  = json_decode(stream_get_contents($fh) ?: '[]', true) ?: [];
		$start = (int) ($data['start'] ?? 0);
		$hits  = (int) ($data['hits'] ?? 0);

		if ($now - $start >= $window) {
			$start = $now;
			$hits = 0;
		}
		$hits++;

		ftruncate($fh, 0);
		rewind($fh);
		fwrite($fh, json_encode(['start' => $start, 'hits' => $hits]));
		flock($fh, LOCK_UN);
		fclose($fh);

		if ($hits > $requests) {
			$retry = max(1, $window - ($now - $start));
			if (!headers_sent()) {
				header('HTTP/1.1 429 Too Many Requests');
				header('Retry-After: ' . $retry);
			}
			exit('Muitas requisições. Tente novamente mais tarde.');
		}
	}

	/**
	 * Verifica características da requisição atual.
	 *
	 * @param string $check 'https' | 'ajax' | 'method'
	 * @param string $extra Para 'method': método esperado (ex.: 'POST').
	 */
	public static function request(string $check, string $extra = ''): bool
	{
		return match ($check) {
			'https'  => self::isHttps(),
			'ajax'   => strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest',
			'method' => strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === strtoupper($extra),
			default  => false,
		};
	}

	/**
	 * Redireciona e encerra. Por padrão só permite caminho interno
	 * (começando com '/', sem '//' nem '\'), bloqueando open redirect.
	 * Para destino externo confiável, passe $allowExternal = true.
	 */
	public static function redirect(string $url, int $statusCode = 302, bool $allowExternal = false): never
	{
		if (!$allowExternal) {
			$internal = isset($url[0]) && $url[0] === '/'
				&& !str_starts_with($url, '//')
				&& !str_contains($url, '\\');
			if (!$internal) {
				$url = '/';
			}
		}
		header('Location: ' . $url, true, $statusCode);
		exit;
	}

	/** Envia resposta JSON padronizada e encerra. */
	public static function jsonResponse(mixed $data, int $statusCode = 200): never
	{
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=UTF-8');
			header('X-Content-Type-Options: nosniff');
			http_response_code($statusCode);
		}
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			// Dados não serializáveis (ex.: recursão) — retorna erro 500 explícito.
			if (!headers_sent()) {
				http_response_code(500);
			}
			echo '{"error":"Falha ao codificar JSON."}';
			exit;
		}
		echo $json;
		exit;
	}

	/**
	 * Mensagens flash (exibidas uma única vez).
	 *
	 * @param string $action 'set' | 'get'
	 * @return array{message: string, type: string}|bool|null
	 */
	public static function flash(string $action, string $message = '', string $type = 'info', string $name = 'phpq_flash'): array|bool|null
	{
		self::init();
		switch ($action) {
			case 'set':
				$_SESSION[$name] = ['message' => $message, 'type' => $type];
				return true;
			case 'get':
			default:
				if (empty($_SESSION[$name])) {
					return null;
				}
				$flash = $_SESSION[$name];
				unset($_SESSION[$name]);
				return $flash;
		}
	}

	/**
	 * Gate de senha simples (sem usuário). Regenera o ID de sessão no login
	 * para prevenir session fixation.
	 *
	 * @param string $action 'open' | 'check' | 'close'
	 * @param string $value       Senha digitada (só em 'open').
	 * @param string $correctHash Hash gerado com filter(..., 'pass') (só em 'open').
	 */
	public static function private(string $action, string $value = '', string $correctHash = ''): bool
	{
		self::init();
		switch ($action) {
			case 'open':
				if ($correctHash === '' || !password_verify($value, $correctHash)) {
					return false;
				}
				session_regenerate_id(true);
				$_SESSION[self::SESSION_LOGIN_KEY] = bin2hex(random_bytes(32));
				return true;
			case 'close':
				unset($_SESSION[self::SESSION_LOGIN_KEY]);
				return true;
			case 'check':
			default:
				return !empty($_SESSION[self::SESSION_LOGIN_KEY]);
		}
	}

	/**
	 * Valida e move um upload com verificação de conteúdo (MIME real via
	 * finfo). Fail-closed: sem finfo, ou extensão sem MIME mapeado, rejeita.
	 * Gera nome aleatório para o arquivo salvo.
	 *
	 * @param array  $file        Item de $_FILES (ex.: $_FILES['avatar']).
	 * @param string $destination Diretório de destino (existente e gravável).
	 * @param array  $allowedExt  Extensões permitidas, minúsculas.
	 * @param int    $maxBytes    Tamanho máximo (padrão 5 MB).
	 * @return array{ok: bool, error?: string, path?: string}
	 */
	public static function upload(
		array $file,
		string $destination,
		array $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
		int $maxBytes = 5_242_880
	): array {
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return ['ok' => false, 'error' => 'Nenhum arquivo enviado.'];
		}
		if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			return ['ok' => false, 'error' => 'Erro no upload (código ' . (int) $file['error'] . ').'];
		}
		if (($file['size'] ?? 0) > $maxBytes) {
			return ['ok' => false, 'error' => 'Arquivo excede o tamanho máximo permitido.'];
		}

		$extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
		if (!in_array($extension, $allowedExt, true)) {
			return ['ok' => false, 'error' => 'Extensão de arquivo não permitida.'];
		}

		// Verificação de conteúdo é obrigatória (fail-closed).
		if (!function_exists('finfo_open')) {
			return ['ok' => false, 'error' => 'Validação de conteúdo indisponível (finfo).'];
		}
		$allowedMimes = [
			'jpg'  => ['image/jpeg'],
			'jpeg' => ['image/jpeg'],
			'png'  => ['image/png'],
			'gif'  => ['image/gif'],
			'webp' => ['image/webp'],
			'pdf'  => ['application/pdf'],
		];
		if (!isset($allowedMimes[$extension])) {
			return ['ok' => false, 'error' => 'Tipo de arquivo sem verificação de conteúdo suportada.'];
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime  = $finfo !== false ? finfo_file($finfo, $file['tmp_name']) : false;
		if ($finfo !== false) {
			finfo_close($finfo);
		}
		if ($mime === false || !in_array($mime, $allowedMimes[$extension], true)) {
			return ['ok' => false, 'error' => 'O conteúdo do arquivo não corresponde à extensão informada.'];
		}

		if (!is_dir($destination) || !is_writable($destination)) {
			return ['ok' => false, 'error' => 'Diretório de destino inválido ou sem permissão de escrita.'];
		}

		$fileName = self::generate('string', 32) . '.' . $extension;
		$fullPath = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $fileName;

		if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
			return ['ok' => false, 'error' => 'Falha ao mover o arquivo enviado.'];
		}
		return ['ok' => true, 'path' => $fullPath];
	}

	/**
	 * Obtém o status code HTTP de uma URL. Restringe os protocolos a
	 * http/https (mitiga SSRF por schemes como file://, gopher://).
	 * Atenção: se $url vier do usuário, ainda há risco de SSRF para redes
	 * internas — valide o destino antes de chamar.
	 *
	 * @return int|false Status code, ou false em erro/timeout.
	 */
	public static function status(string $url, int $timeout = 5): int|false
	{
		if ($url === '') {
			return false;
		}
		$scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
		if (!in_array($scheme, ['http', 'https'], true)) {
			return false;
		}

		// Bloqueia hosts internos/loopback/link-local (mitiga SSRF para redes internas).
		$host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
			return false;
		}
		$ip = gethostbyname($host);
		if ($ip === $host) {
			return false; // não resolveu
		}
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			return false; // IP privado/reservado/loopback
		}

		$curl = curl_init($url);
		if ($curl === false) {
			return false;
		}
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_MAXREDIRS       => 5,
			CURLOPT_HEADER          => true,
			CURLOPT_NOBODY          => true,
			CURLOPT_TIMEOUT         => $timeout,
			CURLOPT_CONNECTTIMEOUT  => $timeout,
			CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_RESOLVE         => [],
		]);
		$response = curl_exec($curl);
		if ($response === false) {
			curl_close($curl);
			return false;
		}
		$statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return $statusCode;
	}

	/**
	 * Calcula dados de paginação (LIMIT/OFFSET e navegação).
	 *
	 * @param int $totalItems  Total de itens
	 * @param int $currentPage Página atual (1-based)
	 * @param int $perPage     Itens por página
	 * @return array{current_page:int, per_page:int, total_items:int,
	 *   total_pages:int, offset:int, has_previous:bool, has_next:bool,
	 *   from_item:int, to_item:int}
	 */
	public static function paginate(int $totalItems, int $currentPage = 1, int $perPage = 10): array
	{
		$perPage = max(1, $perPage);
		$totalPages = (int) max(1, ceil($totalItems / $perPage));
		$currentPage = max(1, min($currentPage, $totalPages));
		$offset = ($currentPage - 1) * $perPage;

		$fromItem = $totalItems > 0 ? $offset + 1 : 0;
		$toItem = min($offset + $perPage, $totalItems);

		return [
			'current_page' => $currentPage,
			'per_page'     => $perPage,
			'total_items'  => $totalItems,
			'total_pages'  => $totalPages,
			'offset'       => $offset,
			'has_previous' => $currentPage > 1,
			'has_next'     => $currentPage < $totalPages,
			'from_item'    => $fromItem,
			'to_item'      => $toItem,
		];
	}

	/**
	 * Gera um array de opções para select de paginação.
	 *
	 * @param int $totalItems Total de itens
	 * @param int $perPage    Itens por página
	 * @return array Array de opções com value/label
	 */
	public static function paginateOptions(int $totalItems, int $perPage = 10): array
	{
		$totalPages = (int) max(1, ceil($totalItems / $perPage));
		$options = [];
		for ($i = 1; $i <= $totalPages; $i++) {
			$options[] = [
				'value' => $i,
				'label' => "Página $i",
			];
		}
		return $options;
	}

	/**
	 * Gera um identificador único para cache.
	 *
	 * @param string $prefix Prefixo do cache
	 * @param array  $params Parâmetros para gerar o hash
	 * @return string Chave única de cache
	 */
	public static function cacheKey(string $prefix, array $params = []): string
	{
		$hash = hash('sha256', serialize($params));
		return $prefix . '_' . substr($hash, 0, 16);
	}

	/**
	 * Verifica se o ambiente é produção (baseado em variáveis comuns).
	 *
	 * @return bool True se for produção
	 */
	public static function isProduction(): bool
	{
		$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production');
		return in_array(strtolower($env), ['production', 'prod', 'live'], true);
	}

	/**
	 * Verifica se o ambiente é desenvolvimento.
	 *
	 * @return bool True se for desenvolvimento
	 */
	public static function isDevelopment(): bool
	{
		return !self::isProduction();
	}

	/**
	 * Retorna informações do ambiente.
	 *
	 * @return array Informações do ambiente
	 */
	public static function environmentInfo(): array
	{
		return [
			'php_version' => PHP_VERSION,
			'environment' => self::isProduction() ? 'production' : 'development',
			'extensions'  => [
				'mbstring' => extension_loaded('mbstring'),
				'iconv'    => extension_loaded('iconv'),
				'curl'     => extension_loaded('curl'),
				'finfo'    => extension_loaded('finfo'),
				'openssl'  => extension_loaded('openssl'),
			],
			'https'      => self::isHttps(),
			'ip'         => self::clientIp(),
		];
	}
}

