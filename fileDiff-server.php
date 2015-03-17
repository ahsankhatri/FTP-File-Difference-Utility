<?php
/**
 * Server Configuration
 * @author : Ehsaan Khatree <ahsankhatri1992@gmail.com>
 */

$basePath = '';

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
    'res/*',
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
    'application/config/xero.php',
    'application/core/Public_Controller.php',
    'res/css/error_log',
    'res/images/cafe/*',
    'static/images/users/*',
);

// Output Rules
// You should define rules on server because its good to maintain rules on one rather than many!
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
            'sig'   =>  md5_file( $file ),
        );
    }
}

// Output JSON to start working
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

        if ( preg_match('/'.$pattern.'/i', $file, $match) != 0 ) {
            continue;
        }
        
        if ( is_file($file) ) {
            $files[] = $file;
        } else if ( is_dir($file) ) {
            $files = array_merge($files, readDirectory( $file.'/*', 1 ));
        }
    }

    return $files;
}