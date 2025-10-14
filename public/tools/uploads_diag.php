<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($root === '' || !is_dir($root)) $root = realpath(dirname(__DIR__)); // fallback

$dir  = $root.'/uploads/pod';
$uptmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();

echo "PHP SAPI:      ".php_sapi_name()."\n";
echo "Doc root:      $root\n";
echo "Uploads dir:   $dir\n";
echo "Tmp dir:       $uptmp\n";
echo "open_basedir:  ".(ini_get('open_basedir') ?: '(none)')."\n";
echo "file_uploads:  ".ini_get('file_uploads')."\n";
echo "post_max_size: ".ini_get('post_max_size')."\n";
echo "upload_max_filesize: ".ini_get('upload_max_filesize')."\n\n";

echo "[FS checks]\n";
echo "exists(upload/):      ".(is_dir(dirname($dir))?'yes':'NO')."\n";
echo "exists(upload/pod):   ".(is_dir($dir)?'yes':'NO')."\n";
echo "writable(upload/):    ".(is_writable(dirname($dir))?'yes':'NO')."\n";
echo "writable(upload/pod): ".(is_writable($dir)?'yes':'NO')."\n";
echo "writable(tmp):        ".(is_writable($uptmp)?'yes':'NO')."\n\n";

echo "[Write test]\n";
@mkdir($dir, 0775, true);
$test = $dir.'/.__write_test__';
$ok = @file_put_contents($test, 'ok');
echo $ok!==false ? "created: $test\n" : "FAILED to write test file\n";
@unlink($test);

echo "\nDone.\n";
