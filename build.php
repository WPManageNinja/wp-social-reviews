<?php
// set only to run from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line!');
}

// get args from command line
$nodeBuild = in_array('--node-build', $argv);

if ($nodeBuild) {
    echo "\nBuilding Main App\n";
    $ret = exec("npx mix --production", $out3, $err4);
    if ($err4) {
        print_r($err4);
    }
    echo implode("\n", $out3);
}

define('WP_USE_THEMES', false);
require(__DIR__ . '/../../../wp-blog-header.php');

$fileLists = [
    'readme.txt',
    'index.php',
    'wp-social-reviews.php',
    'composer.json',
];

$renameFiles = [];
$deleteFolders = [];
$deleteFiles = [];
$targetFolder = 'builds/wp-social-reviews';


$folderLists = [
    'app',
    'assets',
    'boot',
    'config',
    'database',
    'language',
    'vendor',
];

global $wp_filesystem;
require_once(ABSPATH . '/wp-admin/includes/file.php');
WP_Filesystem();

function deleteFileOrFolder($fileOrFolder)
{
    echo 'Deleting: ' . $fileOrFolder;
    // new line
    echo "\n";
    $fileSystemDirect = new WP_Filesystem_Direct(false);
    $result = $fileSystemDirect->rmdir($fileOrFolder, true);

    if (!$result) {
        echo 'ERROR on Delete: ' . $fileOrFolder;
    }
}

function copyFileOrFolder($src, $dest)
{
    if (is_file($src)) {
        copy($src, $dest);
        return;
    }

    $result = copy_dir($src, $dest);

    if (is_wp_error($result)) {
        echo 'ERROR: ' . $result->get_error_message();
    }
}

// delete the folder if exists
if (file_exists($targetFolder)) {
    deleteFileOrFolder($targetFolder);
}

if (!file_exists($targetFolder)) {
    mkdir($targetFolder, 0755, true);
}

foreach ($fileLists as $file) {
    $source = __DIR__ . '/' . $file;
    $target = $targetFolder . '/' . $file;
    copyFileOrFolder($source, $target);
}

foreach ($folderLists as $folder) {
    $source = __DIR__ . '/' . $folder;
    $target = $targetFolder . '/' . $folder;
    echo $target . "\n";
    copyFileOrFolder($source, $target);
}

foreach ($deleteFolders as $folder) {
    $target = $targetFolder . '/' . $folder;
    deleteFileOrFolder($target);
}

// delete the $deleteFiles files now
foreach ($deleteFiles as $file) {
    $target = $targetFolder . '/' . $file;
    deleteFileOrFolder($target);
}

// Rename Required Files
foreach ($renameFiles as $source => $target) {
    rename($targetFolder . '/' . $source, $targetFolder . '/' . $target);
}

echo "\nPro Build Completed";


function deleteFilesByExtension($directory, $extensions = []) {
    if (!is_dir($directory)) {
        echo "The specified directory does not exist.";
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if (in_array($fileinfo->getExtension(), $extensions)) {
            $path = $fileinfo->getRealPath();
            unlink($path);
            echo "Deleted " . $path . PHP_EOL;
        }
    }
}

// Now delete .txt and .map files in assets folder recursively
deleteFilesByExtension($targetFolder.'/assets', ['map', 'txt']);


