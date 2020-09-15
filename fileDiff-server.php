<?php
/**
 * Server Configuration
 * @author : Ehsaan Khatree <ahsankhatri1992@gmail.com>
 */
set_time_limit(120);

$basePath = './';

/**
 * Specify directory list to scan files for diff!
 * @var array
 */
$folderToScan = array(
    'application/controllers/*',
    'application/config/*',
    'application/core/*',
    'application/helpers/*',
    'application/libraries/*',
    'application/models/*',
    'application/third_party/*',
    'application/views/*',
    'static/*',
);

/**
 * Exclude specific directories or files
 * @var array
 */
$excludeSpecificDirectory = array(
    'application/libraries/PHPMailer',
    'application/config/constants.php',
    'application/config/database.php',
    'application/config/config.php',
    'static/fonts/*',
    'static/images/products/*',
    '*/error_log',
);

// Define encryption key to decrypt code sent over HTTP
define('ENCRYPTION_KEY', 'xxxxxx');

/* Configuration Ends Here */

$basePath=='' && $basePath = '.'; # Rewrite basepath
$basePath = rtrim( $basePath, '/' ) . '/';

if ( !is_dir($basePath) ) {
    header('Content-type: application/json');
    echo json_encode(array(
        'status'  =>  false,
        'message' =>  'Directory not exist on local! Please verify your path!',
    ));
    exit;
}

// Output Rules. You should define rules on server because its good to maintain rules on one rather than many!
if ( isset($_GET['json']) && $_GET['request'] == 'rules' ) {
    header('Content-type: application/json');
    echo json_encode(array(
        'include'   =>  $folderToScan,
        'exclude'   =>  $excludeSpecificDirectory,
    ));
    exit;
}

// Send encrypted content of file to client.
if ( isset($_GET['file']) && $_GET['request'] == 'code' && !empty($_GET['file']) ) {
	$currentBasePath = realpath($basePath);
	$requestedPath = realpath($currentBasePath.'/'.rawurldecode($_GET['file']));

	// Avoid LFI attacks
	if (substr($requestedPath,0,strlen($currentBasePath)) !== $currentBasePath) {
		header ("HTTP/1.0 403 Forbidden");
		exit;
	}

    header('Content-type: application/json');
    echo json_encode(array(
        'data' => simpleEncryptDecrypt('encrypt', file_get_contents($requestedPath)),
    ));
    exit;
}

$json = array();

foreach ($folderToScan as $folder) {
    $files = readDirectory( $folder );

    foreach ($files as $file) {
        
        $json[ md5($file) ] = array(
            'path'  =>  $file,
            'sig'   =>  md5_file_xos( $file ),
        );
    }
}

// Output JSON to start working
header('Content-type: application/json');
echo json_encode( $json );


function readDirectory($path, $d=0) {
    global $basePath, $excludeSpecificDirectory;

    $pattern = preg_quote( implode('|', $excludeSpecificDirectory), '/' );
    $pattern = str_replace(array('\*', '\|'), array('.*','|'), $pattern);

    $fullPath = $path;
    
    if ( $d == FALSE )
        $fullPath = $basePath.$path;

    $files = array();

    $list = glob($fullPath);
    foreach ($list as $file) {

        if ( $pattern != '' && preg_match('/^'.$pattern.'$/i', $file, $match) != 0 ) {
            continue;
        }

        /* Empty basepath bug fixed */
        if ( $d == FALSE )
            $file = substr($file, strlen($basePath));
        
        if ( is_file($file) ) {
            $files[] = $file;
        } else if ( is_dir($file) && $path != '*' ) {
            $files = array_merge($files, readDirectory( $file.'/*', 1 ));
        }
    }

    return $files;
}

function md5_file_xos($filename, $raw_output = false) {
    $data = '';
    $fp = fopen($filename, 'rb');
    while (!feof($fp)) {
        $data .= str_replace(array("\r\n", "\r"), "\n", fgets($fp));
    }
    fclose($fp);

    $data = md5($data);
    if (true === $raw_output) {
        $data = pack('H*', $data);
    }

    return $data;
}

/**
 * simple method to encrypt or decrypt a plain text string
 * initialization vector(IV) has to be the same when encrypting and decrypting
 *
 * @param string $action: can be 'encrypt' or 'decrypt'
 * @param string $string: string to encrypt or decrypt
 *
 * @return string
 */
function simpleEncryptDecrypt($action, $string)
{
	$output = false;
	$encrypt_method = "AES-256-CBC";
	$secret_iv = 'randomString#12231'; // change this to one more secure
	$key = hash('sha256', ENCRYPTION_KEY);

	// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
	$iv = substr(hash('sha256', $secret_iv), 0, 16);
	if ($action == 'encrypt') {
		$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
		$output = base64_encode($output);
	} else if ($action == 'decrypt') {
		$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
	}
	return $output;
}
