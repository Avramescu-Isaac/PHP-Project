<?php
$files = glob(__DIR__.'/../public/assets/manhwa/*/chapter-*/*');
$bad = [];
foreach ($files as $file) {
    if (@getimagesize($file) === false) $bad[] = $file;
}
echo count($files).' image files checked; '.count($bad).' invalid.'.PHP_EOL;
foreach ($bad as $file) echo $file.PHP_EOL;
exit(count($bad) ? 1 : 0);
