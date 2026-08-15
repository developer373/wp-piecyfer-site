<?php
$dumpFile = __DIR__ . '/../backups/piecyfer-db-2026-08-15-links-and-icons-fixed.sql';
$gzFile = $dumpFile . '.gz';

$cmd = '"D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe" -u root piecyfer > "' . $dumpFile . '"';
echo "Running: $cmd\n";
exec($cmd, $out, $ret);

if ($ret === 0 && file_exists($dumpFile)) {
  echo "Dump successful, file size: " . filesize($dumpFile) . " bytes\n";
  $gz = gzopen($gzFile, 'w9');
  $fp = fopen($dumpFile, 'r');
  while (!feof($fp)) {
    gzwrite($gz, fread($fp, 1024 * 512));
  }
  fclose($fp);
  gzclose($gz);
  unlink($dumpFile);
  echo "Compressed to $gzFile (" . filesize($gzFile) . " bytes)!\n";
} else {
  echo "Dump failed with code $ret\n";
}
