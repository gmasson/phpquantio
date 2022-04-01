<?php
/**
* PHPQuantio 1.1
* Micro biblioteca PHP com funções úteis para desenvolvimento web
* https://github.com/gmasson/phpquantio
* License MIT
*/

# Verifica a versão do PHP
if ( phpversion() < '7.0' )
{
	die( "Please update PHP to a higher version" );
}

# Configura o Timezone
if( ! ini_get( 'date.timezone' ) )
{
    date_default_timezone_set('GMT');
}

/* Filters */

# Filters - Text
function phpq_filterText( $input, $type = '' )
{
	switch ( $type )
	{
		case 'print_html':
			$input = htmlspecialchars( $input );
			break;
		
		default:
			$input = strip_tags( $input );
			break;
	}
	return $input;
}

# Filters - Integers
function phpq_filterNumb( $input )
{
	$input = addslashes( $input );
	$input = strip_tags( $input );
	$input = intval( $input );
	return $input;
}

# Filters - URLs
function phpq_filterUrl( $input, $with_underline = '' )
{
	$input = phpq_filterText( $input, 'print_html' );
	if ( $with_underline != '' )
	{
		$input = str_replace( " ", "_", $input );
	}
	return $input;
}

# Filters - Username
function phpq_filterUser( $input, $with_underline = 'y' )
{
	$input = strip_tags( $input );
	$input = phpq_filterText( $input );
	$input = strtolower( $input );
	if ( $with_underline != '' )
	{
		$input = str_replace( " ", "_", $input );
	}
	return $input;
}

# Filters - Email
function phpq_filterEmail( $input )
{
	$input = phpq_filterUser( $input );
	if( !preg_match( "/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$/",$input ) )
	{
		return false;
	}
	else
	{
		return $input;
	}
}

# Filters - Password
function phpq_filterPass( $input, $key = 'try if you can' )
{
	$input = addslashes( $input );
	$input = strip_tags( $input );
	$input = $key . $input . $key;
	$input = crypt( $input, '$2a$07$r$' );
	//$input = md5( $input );
	return "$2a$08$4" . $input . "X21_";
}

# Filters - GET parameters
function phpq_filterGet( $input, $with_empty = '', $with_underline = '', $count = 0 )
{
	$input = ( isset( $_GET[ $input ] ) ) ? phpq_filterUrl( $_GET[ $input ], $with_underline ) : $with_empty;
	if ( empty( $input ) or strlen( $input ) < $count )
	{
		$input = $with_empty;
	}
	return $input;
}

# Filters - POST parameters
function phpq_filterPost( $input, $with_empty = '', $with_underline = '', $count = 0 )
{
	$input = ( isset( $_POST[ $input ] ) ) ? phpq_filterUrl( $_POST[ $input ], $with_underline  ) : $with_empty;
	if ( empty( $input ) or strlen( $input ) < $count )
	{
		$input = $with_empty;
	}
	return $input;
}


/* Times */

# Times - Current date
function phpq_date( $tipe = '' )
{
	switch ($tipe) {
		case 'd':
			return date( 'd' );
			break;
		
		case 'm':
			return date( 'm' );
			break;
		
		case 'y':
			return date( 'Y' );
			break;
		
		case 'a':
			return date( 'Y' );
			break;
		
		case 'pt':
			return date( 'd/m/Y' );
			break;
		
		default:
			return date( 'Y/m/d' );
			break;
	}
}

# Times - Current time
function phpq_hour( $tipe = '' )
{
	switch ($tipe) {
		case 'h':
			return date( 'h' );
			break;
		
		case 'm':
			return date( 'i' );
			break;
		
		case 's':
			return date( 's' );
			break;

		default:
			return date( 'H:i:s' );
			break;
	}
}

/* Generators */

# Generators - Hash
function phpq_hash( $size = 12 )
{
	$ma = "ABCDEFGHIJKLMNOPQRSTUVYXWZ"; // Letras maiúsculas
	$mi = "abcdefghijklmnopqrstuvyxwz"; // Letras minusculas
	$nu = "0123456789"; // Números
	$si = "!@#$%-&*()_+="; // Símbolos
	
	$pass .= str_shuffle( $ma );
	$pass .= str_shuffle( $mi );
	$pass .= str_shuffle( $nu );
	$pass .= str_shuffle( $si );
	return substr( str_shuffle( $pass ), 0, $size );
}

# Generators - Loren Ipsum
function phpq_lorem( $length = 445 ) {
	$text = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.".
	$maxLength = 445;
	$shortened = mb_substr( $text, 0, $length, 'UTF-8' );
	if ( $length > $maxLength ) {
		return $text;
	} else {
		return $shortened;
	}
}

/* Mail */

# Mail - Send
function phpq_mail( $array )
{
	$headers = "MIME-Version: 1.1\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
	$headers .= "From: " . $array['from'] . "\r\n";
	$headers .= "Return-Path: " . $array['return'] . "\r\n";
	$sendMail = mail( $array[ 'email' ], $array[ 'subject' ], $array[ 'body' ], $headers );
	
	if ( $sendMail ) 
	{ return true; } 
	else 
	{ return false; }
}

/* Miscellaneous */

# Miscellaneous - User browser
function phpq_browser()
{
	return $_SERVER[ 'HTTP_USER_AGENT' ];
}

# Miscellaneous - User IP
function phpq_ip()
{
	if (!empty($_SERVER['HTTP_CLIENT_IP']))
	{
		return $_SERVER['HTTP_CLIENT_IP'];
	}
	// verifica se vem de um proxy
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	else
	{
		return $_SERVER['REMOTE_ADDR'];
	}
}

# Miscellaneous - List of files in a folder
function phpq_fileListing( $folder, $tag = '<p>', $endTag = '</p>' )
{
	$dir = dir($folder);
	while($file = $dir -> read()){
		echo $tag . $file . $endTag;
	}
	$dir -> close();
}

# Miscellaneous - JSON information with simple structure
function phpq_extractJson( $file, $data )
{
	$arquivo = file_get_contents($file);
	$json = json_decode($arquivo);
	return $json->$data;
}

