<?php

/**
 * Client Configuration
 *
 * @package FTP File Difference with local
 * @author : Ehsaan Khatree <ahsankhatri1992@gmail.com>
 */

set_time_limit(120);
ignore_user_abort(false);

// Specify your base path url here, empty path will assume directory path is current!
$basePath = './';

// Your HTTP Url path of fileDiff-server.php (other file for this utility)
$fileDiffPath = 'http://www.domain.com/fileDiff.php';

// Ignore / Don't compare files with local side
$ignoreFiles = array(
    'application/core/MY_Encrypt.php',
);

// Define encryption key to decrypt code sent over HTTP
define('ENCRYPTION_KEY', 'xxxxxx');

// Path of https://github.com/chrisboulton/php-diff repository
define('PHP_DIFF_LIBRARY_PATH', '');

/* Configuration Ends Here */

$basePath == '' && $basePath = '.'; # Rewrite basepath
$basePath = rtrim($basePath, '/') . '/';

if (!is_dir($basePath)) {
    die('Directory not exist on local! Please verify your path!');
}

$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($action === 'compare' && !empty($_GET['file'])) {
    $path = rawurldecode($_GET['file']);

    if (!file_exists(PHP_DIFF_LIBRARY_PATH . '/lib/Diff.php')) {
        echo 'Unable to find path for <strong>php-diff</strong> library. Please checkout <a href="https://github.com/chrisboulton/php-diff">https://github.com/chrisboulton/php-diff</a> repository and set absolute path for <strong>PHP_DIFF_LIBRARY_PATH</strong> constant.';
        exit;
    }

    if (file_exists($path)) {
        $data = file_get_contents($fileDiffPath . '?json&request=code&file=' . $path);
        $data = json_decode($data, TRUE);

        if (is_array($data) && isset($data['data'])) {
            $remoteCode = explode("\n", simpleEncryptDecrypt('decrypt', $data['data']));
            $localCode = explode("\n", file_get_contents($path));

            require_once PHP_DIFF_LIBRARY_PATH . '/lib/Diff.php';
            require_once PHP_DIFF_LIBRARY_PATH . '/lib/Diff/Renderer/Html/SideBySide.php';

            $diff = new Diff($remoteCode, $localCode, array(
                'ignoreWhitespace' => ($_GET['ignoreWhiteSpaces'] == 'true'),
            ));

            $renderer = new Diff_Renderer_Html_SideBySide([
                'title1' => 'Remote (' . $path . ')',
                'title2' => 'Local (' . $path . ')',
            ]);
            $content = $diff->Render($renderer);

            if (empty($content)) {
                $content = 'Unable to determine differences.';
            }
        } else {
            echo 'Error while reading remote code.';
            exit;
        }
    } else {
        echo 'File does not exist on local.';
        exit;
    }
} else if (null === $action) {

    // Server Files Data
    $data = file_get_contents($fileDiffPath);
    $remote = json_decode($data, TRUE);

    // Fetch Rules
    $data = file_get_contents($fileDiffPath . '?json&request=rules');
    $data = json_decode($data, TRUE);

    list($folderToScan, $excludeSpecificDirectory) = array_values($data);

    $excludeSpecificDirectory = array_merge($excludeSpecificDirectory, $ignoreFiles);

    $change = $nochange = $newfile = array();

    $x = 0;
    foreach ($folderToScan as $folder) {
        $files = readDirectory($folder);

        foreach ($files as $file) {

            $onlyPath = substr($file, strlen($basePath));
            $fileSig = md5_file_xos($file);
            $fileMD5 = md5($onlyPath);

            if (isset($remote[$fileMD5])) {
                // Exist on both side;
                if ($remote[$fileMD5]['sig'] == $fileSig) {
                    $nochange[$x] = $onlyPath;
                } else {
                    $change[$x] = $onlyPath;
                }
                unset($remote[$fileMD5]);
            } else {
                // New file in local / Not exist on remote
                $newfile[$x] = $onlyPath;
            }
            $x++;
        }
    }

    # Convert ignore to RegEx
    $ignoredRegex = str_replace(array('\*', '\|'), array('.*', '|'), preg_quote(implode('|', $ignoreFiles), '/'));

    # Ignore file which exist on remote or local
    if (count($ignoreFiles)) {
        foreach ($remote as $index => $remoteFile) { # avoiding reference
            if (preg_match('/^' . $ignoredRegex . '$/i', $remoteFile['path']) != 0) {
                unset($remote[$index]);
            }
        }
    }

    // Display File Difference!
    $newChangeString     = '<div class="newChange">%s <a href="' . basename($_SERVER['SCRIPT_FILENAME']) . '?action=compare&file=%s" target="_blank">(diff)</a></div>';
    $newFileString       = '<div class="newFile">%s <span class="system">(local)</span></div>';
    $noChangeString      = '<div class="noChange">%s</div>';
    $noFileOnLocalString = '<div class="noExistLocal">%s <span class="system">(server)</span></div>';

    $changeModified = array_map(function ($v) use ($newChangeString) {
        return sprintf($newChangeString, $v, rawurlencode($v));
    }, $change);

    $newFileModified = array_map(function ($v) use ($newFileString) {
        return sprintf($newFileString, $v);
    }, $newfile);

    $noChangeModified = array_map(function ($v) use ($noChangeString) {
        return sprintf($noChangeString, $v);
    }, $nochange);

    $noFileLocal = array_map(function ($v) use ($noFileOnLocalString) {
        return sprintf($noFileOnLocalString, $v['path']);
    }, $remote);

    $array = $changeModified + $newFileModified + $noChangeModified;
    $array = array_merge($array, array_values($noFileLocal));
    ksort($array);
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Project FTP File Difference!</title>
<style type="text/css">
.main-content {
    margin-top: 50px;
}
.diff {
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

/* Difference CSS */
.Differences {
	width: 100%;
	border-collapse: collapse;
	border-spacing: 0;
	empty-cells: show;
}
.Differences thead th {
	text-align: left;
	border-bottom: 1px solid #000;
	background: #aaa;
	color: #000;
	padding: 4px;
}
.Differences tbody th {
	text-align: right;
	background: #ccc;
	width: 4em;
	padding: 1px 2px;
	border-right: 1px solid #000;
	vertical-align: top;
	font-size: 13px;
}
.Differences td {
	padding: 1px 2px;
	font-family: Consolas, monospace;
	font-size: 13px;
}
.DifferencesSideBySide .ChangeInsert td.Left {
	background: #dfd;
}
.DifferencesSideBySide .ChangeInsert td.Right {
	background: #cfc;
}
.DifferencesSideBySide .ChangeDelete td.Left {
	background: #f88;
}
.DifferencesSideBySide .ChangeDelete td.Right {
	background: #faa;
}
.DifferencesSideBySide .ChangeReplace .Left {
	background: #fe9;
}
.DifferencesSideBySide .ChangeReplace .Right {
	background: #fd8;
}
.Differences ins, .Differences del {
	text-decoration: none;
}
.DifferencesSideBySide .ChangeReplace ins, .DifferencesSideBySide .ChangeReplace del {
	background: #fc0;
}
.Differences .Skipped {
	background: #f7f7f7;
}
.DifferencesInline .ChangeReplace .Left,
.DifferencesInline .ChangeDelete .Left {
	background: #fdd;
}
.DifferencesInline .ChangeReplace .Right,
.DifferencesInline .ChangeInsert .Right {
	background: #dfd;
}
.DifferencesInline .ChangeReplace ins {
	background: #9e9;
}
.DifferencesInline .ChangeReplace del {
	background: #e99;
}
pre {
	width: 100%;
	overflow: auto;
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

    $('#ignoreWhiteSpaces').change(function() {
        var checked = $(this).prop('checked');
        replaceURL = window.location.href.match(/ignoreWhiteSpaces=(true|false)/)
        action = checked ? 'true' : 'false'

        window.location = replaceURL === null ? window.location.href + '&ignoreWhiteSpaces=' + action : window.location.href.replace('ignoreWhiteSpaces=' + replaceURL[1], 'ignoreWhiteSpaces=' + action);
    });

    $('#hideNoChange').prop('checked', true).trigger('change');
    $('#notExistLocal').prop('checked', true).trigger('change');
});
</script>
</head>
<body>
HTML;

if (null === $action) {
    $content = implode('', $array);
    $html .= <<<HTML
<div class="header">
	<label><input type="checkbox" class="noselect" id="hideNoChange" /> Hide Unchange Files</label>
	<label><input type="checkbox" class="noselect" id="hideChanged" /> Hide Changed Files</label>
	<label><input type="checkbox" class="noselect" id="hideNewFile" /> Hide New Files Which are on local</label>
	<label><input type="checkbox" class="noselect" id="notExistLocal" /> Show File Not Exist on Local</label>
</div>
<div class="diff main-content">
{$content}
</div>
HTML;
} else if ('compare' === $action) {
	$checked_ignoreWhiteSpaces = $_GET['ignoreWhiteSpaces'] == 'true' ? 'checked="checked"' : '';
	$html .= <<<HTML
<div class="header">
	<label><input type="checkbox" class="noselect" id="ignoreWhiteSpaces" {$checked_ignoreWhiteSpaces} /> Ignore Whitespaces</label>
</div>

<div class="main-content">
{$content}
</div>
HTML;
}


$html .= '</body></html>';
echo $html;

/* Helper Goes Here */
function readDirectory($path, $d = 0)
{
    global $basePath, $excludeSpecificDirectory;

    $pattern = preg_quote(implode('|', $excludeSpecificDirectory), '/');
    $pattern = str_replace(array('\*', '\|'), array('.*', '|'), $pattern);

    $fullPath = $path;

    if ($d == FALSE)
        $fullPath = $basePath . $path;

    $basePathLength = strlen($basePath);
    $files = array();

    $list = glob($fullPath);
    foreach ($list as $file) {

        if ($pattern != '' && preg_match('/' . $pattern . '/i', $file, $match) != 0) {
            continue;
        }

        if (is_file($file)) {
            $files[] = $file;
        } else if (is_dir($file) && $path != '*') {
            $files = array_merge($files, readDirectory($file . '/*', 1));
        }
    }

    return $files;
}

function md5_file_xos($filename, $raw_output = false)
{
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
