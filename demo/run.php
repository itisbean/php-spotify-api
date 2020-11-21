<?php

use spotifyMusic\Api;

include_once __DIR__.'/../src/Api.php';
include_once __DIR__.'/../src/Storage.php';
include_once __DIR__.'/../vendor/autoload.php';

$proxy = 'http://127.0.0.1:1087';

$api = new Api();
// $ret = $api->getSingerInfo('2zzKlxMsKTPMsZacZCPRNA');
// $ret = $api->getSingerAlbums('2zzKlxMsKTPMsZacZCPRNA');
// $ret = $api->getAlbumSongs('0zPpdDiDX6JnGZRHXFYuZt');
// $ret = $api->getAlbumInfo('0zPpdDiDX6JnGZRHXFYuZt');
$ret = $api->getPlaylist('37i9dQZEVXbLwpL8TjsxOG');
echo json_encode($ret, JSON_UNESCAPED_UNICODE)."\n";