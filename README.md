# php-spotify-api

spotify api for php

## Composer Install

```php
composer require itisbean/php-spotify-api
```
## Use

```php
// 引入autoload.php（框架中使用不需要）
include_once __DIR__.'/../vendor/autoload.php';
// 实例化Api类
$api = new \spotifyMusic\Api();
// 调用
$ret = $api->getSingerInfo('2zzKlxMsKTPMsZacZCPRNA');
```

## Function

### Get singer information

```php
/**
 * 獲取歌手信息
 * @param string $singerId
 * @return array
 */
$api->getSingerInfo('2zzKlxMsKTPMsZacZCPRNA');
```

### Get all albums of the singer

```php
/**
 * 獲取歌手全部專輯
 * @param string $singerId
 * @return array
 */
$api->getSingerAlbums('2zzKlxMsKTPMsZacZCPRNA');
```

### Get album information

```php
/**
 * 獲取專輯信息
 * @param string $albumId
 * @return array
 */
$api->getAlbumInfo('0zPpdDiDX6JnGZRHXFYuZt');
```

### Get album songs

```php
/**
 * 獲取專輯歌曲
 * @param string $albumId
 * @return array
 */
$api->getAlbumSongs('0zPpdDiDX6JnGZRHXFYuZt');
```

### Get the playlist list

```php
/**
 * 獲取歌單列表
 * @param string $playlistId
 * @return array
 */
$api->getPlaylist('37i9dQZEVXbLwpL8TjsxOG');
```
