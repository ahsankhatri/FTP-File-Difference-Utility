<?php
/**
 * Client Configuration
 * 
 * @package FTP File Difference with local
 * @author : Ehsaan Khatree <ahsankhatri1992@gmail.com>
 */

// Specify your base path url here, empty path will assume directory path is current!
$basePath = './';

// Your HTTP Url path of fileDiff-server.php (other file for this utility)
$fileDiffPath = 'http://www.domain.com/fileDiff.php';

// Ignore / Don't compare files with local side
$ignoreFiles = array(
    'application/core/MY_Encrypt.php',
);

/* Configuration Ends Here */

$basePath=='' && $basePath = '.'; # Rewrite basepath
$basePath = rtrim( $basePath, '/' ) . '/';

if ( !is_dir($basePath) ) {
    die('Directory not exist on local! Please verify your path!');
}

// Server Files Data
$data = file_get_contents( $fileDiffPath );
$remote = json_decode( $data, TRUE );

// Fetch Rules
$data = file_get_contents( $fileDiffPath . '?json&request=rules');
$data = json_decode( $data, TRUE );

list($folderToScan, $excludeSpecificDirectory) = array_values($data);

$excludeSpecificDirectory = array_merge($excludeSpecificDirectory, $ignoreFiles);

$change = $nochange = $newfile = array();

$x=0;
foreach ($folderToScan as $folder) {
    $files = readDirectory( $folder );

    foreach ($files as $file) {

        $onlyPath = substr($file, strlen($basePath));
        $fileSig = md5_file( $file );
        $fileMD5 = md5( $onlyPath );

        if ( isset($remote[ $fileMD5 ]) ) {
            // Exist on both side;
            if ( $remote[ $fileMD5 ][ 'sig' ] == $fileSig ) {
                $nochange[ $x ] = $onlyPath;
            } else {
                $change[ $x ] = $onlyPath;
            }
            unset( $remote[ $fileMD5 ] );

        } else {
            // New file in local / Not exist on remote
            $newfile[ $x ] = $onlyPath;
        }
        $x++;
    }
}

# Convert ignore to RegEx
$ignoredRegex = str_replace(array('\*', '\|'), array('.*','|'), preg_quote( implode('|', $ignoreFiles), '/' ) );

# Ignore file which exist on remote or local
if ( count( $ignoreFiles ) ) {
    foreach ($remote as $index => $remoteFile) { # avoiding reference
        if ( preg_match('/^'.$ignoredRegex.'$/i', $remoteFile['path']) != 0 ) {
            unset( $remote[ $index ] );
        }
    }
}

// Display File Difference!
$newChangeString     = '<div class="newChange">%s</div>';
$newFileString       = '<div class="newFile">%s <span class="system">(local)</span></div>';
$noChangeString      = '<div class="noChange">%s</div>';
$noFileOnLocalString = '<div class="noExistLocal">%s <span class="system">(server)</span></div>';

$changeModified = array_map(function($v) use ($newChangeString) {
    return sprintf($newChangeString, $v);
}, $change);

$newFileModified = array_map(function($v) use ($newFileString) {
    return sprintf($newFileString, $v);
}, $newfile);

$noChangeModified = array_map(function($v) use ($noChangeString) {
    return sprintf($noChangeString, $v);
}, $nochange);

$noFileLocal = array_map(function($v) use ($noFileOnLocalString) {
    return sprintf($noFileOnLocalString, $v['path']);
}, $remote);

$array = $changeModified + $newFileModified + $noChangeModified;
$array = array_merge( $array, array_values($noFileLocal) );
ksort($array);

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Project FTP File Difference!</title>
<style type="text/css">
.diff {
    margin-top: 50px;
    font-family: 'Lato, appleLogo, sans-serif';
    font-size: 18px;
    line-height: 18px;
}
div > span.system {
    color: grey;
    font-size: 12px;
    font-style: italic;
}
.header {
    padding: 8px 0 0 10px;
    height: 30px;
    background: rgb(239, 239, 239);
    margin: 0;
    position: fixed;
    display: block;
    top: 0;
    left: 0;
    width: 100%;
    border-bottom: 1px solid rgb(124, 210, 255);
}
label {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.newChange {
    color: green;
}
.noChange {
    color: grey;
}
.noExistLocal, .newFile {
    color: blue;
}
</style>
<link href='http://fonts.googleapis.com/css?family=Lato&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('#hideNoChange').change(function() {
        var checked = $(this).prop('checked');
        if ( checked ) {
            $('.noChange').hide();
        } else {
            $('.noChange').show();
        }
    });

    $('#hideChanged').change(function() {
        var checked = $(this).prop('checked');
        if ( checked ) {
            $('.newChange').hide();
        } else {
            $('.newChange').show();
        }
    });

    $('#hideNewFile').change(function() {
        var checked = $(this).prop('checked');
        if ( checked ) {
            $('.newFile').hide();
        } else {
            $('.newFile').show();
        }
    });

    $('#notExistLocal').change(function() {
        var checked = $(this).prop('checked');
        if ( checked ) {
            $('.noExistLocal').show();
        } else {
            $('.noExistLocal').hide();
        }
    });

    $('#hideNoChange').prop('checked', true).trigger('change');
    $('#notExistLocal').prop('checked', true).trigger('change');
});
</script>
</head>
<body>
HTML;

echo $html; ?>

<div class="header">
    <label><input type="checkbox" class="noselect" id="hideNoChange" /> Hide Unchange Files</label>
    <label><input type="checkbox" class="noselect" id="hideChanged" /> Hide Changed Files</label>
    <label><input type="checkbox" class="noselect" id="hideNewFile" /> Hide New Files Which are on local</label>
    <label><input type="checkbox" class="noselect" id="notExistLocal" /> Show File Not Exist on Local</label>
</div>

<?php
echo '<div class="diff">';
echo implode('', $array);
echo '</div></body></html>';

/* Helper Goes Here */
function readDirectory($path, $d=0) {
    global $basePath, $excludeSpecificDirectory;

    $pattern = preg_quote( implode('|', $excludeSpecificDirectory), '/' );
    $pattern = str_replace(array('\*', '\|'), array('.*','|'), $pattern);

    $fullPath = $path;
    
    if ( $d == FALSE )
        $fullPath = $basePath.$path;

    $basePathLength = strlen($basePath);
    $files = array();

    $list = glob($fullPath);
    foreach ($list as $file) {

        if ( $pattern != '' && preg_match('/'.$pattern.'/i', $file, $match) != 0 ) {
            continue;
        }

        if ( is_file($file) ) {
            $files[] = $file;
        } else if ( is_dir($file) && $path != '*' ) {
            $files = array_merge($files, readDirectory( $file.'/*', 1 ));
        }
    }

    return $files;
}