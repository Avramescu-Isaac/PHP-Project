<?php
$catalog = [
  'sword-hound' => ['title'=>'Revenge of the Iron-Blooded Sword Hound','cover'=>'manhwa/sword-hound/cover.png','genres'=>['Action','Fantasy'],'description'=>'Revenge of the Iron-Blooded Sword Hound is ready to read in your library.','pages'=>[]],
  'player' => ['title'=>'The Player Hides His Past','cover'=>'manhwa/player/cover.png','genres'=>['Action','Fantasy'],'description'=>'Open the chapter below to start reading, or save this title for later.','pages'=>[]],
  'reincarnator' => ['title'=>'Reincarnator','cover'=>'manhwa/reincarnator/cover.png','genres'=>['Action','Adventure'],'description'=>'Open the chapter below to start reading, or save this title for later.','pages'=>[]],
  'solo' => ['title'=>'Solo Max-Level Newbie','cover'=>'manhwa/solo/cover.jpg','genres'=>['Action','Adventure'],'description'=>'Open the chapter below to start reading, or save this title for later.','pages'=>[]],
];

// Natural filename order keeps 2 before 10. Only local image files are included.
foreach ($catalog as $id => &$comic) {
    $comic['chapter'] = in_array($id, ['sword-hound', 'player'], true) ? 1 : 0;
    $relative = 'manhwa/'.$id.'/chapter-'.$comic['chapter'];
    $directory = dirname(__DIR__).'/public/assets/'.$relative;
    $files = is_dir($directory) ? scandir($directory) : [];
    $files = array_filter($files, fn($file) => preg_match('/\.(jpe?g|png|webp)$/i', $file) && is_file($directory.'/'.$file));
    natsort($files);
    $comic['pages'] = array_map(fn($file) => $relative.'/'.$file, array_values($files));
    $comic['chapter'] = in_array($id, ['sword-hound', 'player'], true) ? 1 : 0;
}
unset($comic);
return $catalog;
