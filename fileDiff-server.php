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