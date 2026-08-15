<?php
$source = __DIR__ . '/../../wp-content/plugins';
$dest = __DIR__ . '/../backups/plugins-pre-update';

if (!is_dir($dest)) {
  mkdir($dest, 0777, true);
}

echo "Backing up plugins from $source to $dest...\n";

function copy_dir($src, $dst) {
  $dir = opendir($src);
  @mkdir($dst);
  while (false !== ($file = readdir($dir))) {
    if (($file != '.') && ($file != '..')) {
      if (is_dir($src . '/' . $file)) {
        copy_dir($src . '/' . $file, $dst . '/' . $file);
      } else {
        copy($src . '/' . $file, $dst . '/' . $file);
      }
    }
  }
  closedir($dir);
}

$plugins = scandir($source);
foreach ($plugins as $p) {
  if ($p !== '.' && $p !== '..') {
    echo "  Backing up plugin: $p...\n";
    if (is_dir($source . '/' . $p)) {
      copy_dir($source . '/' . $p, $dest . '/' . $p);
    } else {
      copy($source . '/' . $p, $dest . '/' . $p);
    }
  }
}

echo "Plugins backup completed successfully!\n";
