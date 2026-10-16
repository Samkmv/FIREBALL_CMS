<?php
declare(strict_types=1);
// Compose navigation outside the preserved player source. Its library controls
// live in the offcanvas; playback controls and their lifecycle stay untouched.
ob_start();require dirname(__DIR__).'/partials/player.php';$playerDocument=(string)ob_get_clean();
$playerDocument=preg_replace('~<aside class="rp-playlists">.*?</aside>~s','',$playerDocument,1,$libraryReplaced);
if($libraryReplaced!==1)throw new RuntimeException('Tape Room library navigation slot is unavailable.');
$navigation=$this->partial('navbar',['is_player'=>true,'config'=>$config]);
$head=$this->partial('shell-assets',['player_shell'=>true,'config'=>$config]);
$scripts=$this->partial('torrent-dialog',['config'=>$config]).$this->partial('shell-scripts',['skip_bootstrap'=>true]).'<script src="'.htmlSC(theme_asset_versioned('js/browser-compat.js')).'" defer></script><script src="'.htmlSC(theme_asset_versioned('vendor/webtorrent/webtorrent.min.js')).'" defer></script><script src="'.htmlSC(theme_asset_versioned('vendor/music-metadata/metadata.min.js')).'" defer></script><script src="'.htmlSC(theme_asset_versioned('js/torrent-core.js')).'" defer></script><script src="'.htmlSC(theme_asset_versioned('js/torrent.js')).'" defer></script>';
$playerDocument=preg_replace_callback('~<header class="rp-header">.*?</header>~s',static fn()=>$navigation,$playerDocument,1,$replaced);
if($replaced!==1)throw new RuntimeException('Tape Room navigation slot is unavailable.');
echo str_replace(['</head>','</body>'],[$head.'</head>',$scripts.'</body>'],$playerDocument);
