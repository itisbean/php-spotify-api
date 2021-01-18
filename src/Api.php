<?php

namespace spotifyMusic;

use GuzzleHttp\Client;
use kkboxMusic\Storage;
use GuzzleHttp\Cookie\FileCookieJar;

class Api
{

    protected $_client;

    private $_errmsg;

    static $cookiefile = __DIR__ . '/db/cookies';

    public function __construct($proxy = '')
    {
        $cookie = new FileCookieJar(self::$cookiefile, true);
        $config = [
            'headers' => [
                'Referer' => 'https://open.spotify.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ],
            'cookies' => $cookie
        ];
        if ($proxy) {
            $config['proxy'] = $proxy;
        }
        $this->_client = new Client($config);
    }

    /**
     * 获取token
     * @return string
     */
    private function _getToken()
    {
        $keyName = 'accessToken';
        $result = Storage::init()->get('spotify', $keyName);
        // 是否已存在且未过期
        if ($result && !empty($result['token'])) {
            if (isset($result['expires']) && $result['expires'] > time()) {
                return $result['token'];
            }
        }

        $url = "https://open.spotify.com/get_access_token?reason=transport&productType=web_player";
        $result = $this->_sendRequest($url, false);
        if ($result === false || !isset($result['accessToken'])) {
            return null;
        }

        $token = $result['accessToken'];
        $expires = floor($result['accessTokenExpirationTimestampMs'] / 1000);
        Storage::init()->set('spotify', $keyName, ['token' => $token, 'expires' => $expires]);

        return $token;
    }

    /**
     * 獲取歌手信息
     * @param string $singerId
     * @return array
     */
    public function getSingerInfo($singerId)
    {
        $url = 'https://api-partner.spotify.com/pathfinder/v1/query';
        $param = [
            'operationName' => 'queryArtistOverview',
            'variables' => json_encode([
                'uri' => 'spotify:artist:' . $singerId
            ]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => '53f2fcff0a0f47530d71f576113ed9db94fc3ccd1e8c7420c0852b828cadd2e0'
                ],
            ])
        ];
        $url .= '?' . http_build_query($param);

        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $data = [];
        if (!empty($result['data']['artist'])) {
            $result = $result['data']['artist'];
            $data = [
                'id' => $result['id'],
                'name' => $result['profile']['name'],
                'biography' => $result['profile']['biography']['text'],
                'avatar' => $result['visuals']['avatarImage']['sources'][0]['url'],
                'followers' => $result['stats']['followers'],
                'monthlyListeners' => $result['stats']['monthlyListeners'],
                'topCities' => $result['stats']['topCities'],
                'url' => $result['sharingInfo']['shareUrl']
            ];
        }

        return $this->_success($data);
    }

    /**
     * 獲取歌手全部專輯
     * @param string $singerId
     * @return array
     */
    public function getSingerAlbums($singerId)
    {
        $url = 'https://api-partner.spotify.com/pathfinder/v1/query';
        $param = [
            'operationName' => 'queryArtistDiscography',
            'variables' => json_encode([
                'uri' => 'spotify:artist:' . $singerId
            ]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => '4257bb9c1e5eaa6adb95092514dd09a35559f6ad0e284bed5e13547d342490aa'
                ],
            ])
        ];
        $url .= '?' . http_build_query($param);

        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $data = [];
        if (!empty($result['data']['artist']['discography'])) {
            $result = $result['data']['artist']['discography'];
            $albums = [];
            foreach ($result['albums']['items'] as $item) {
                $item = $item['releases']['items'][0];
                $source = end($item['coverArt']['sources']);
                $albums[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'cover' => $source ? $source['url'] : '',
                    'url' => $item['sharingInfo']['shareUrl'],
                    'public' => $item['date']['year'],
                    'type' => 'album'
                ];
            }
            foreach ($result['singles']['items'] as $item) {
                $item = $item['releases']['items'][0];
                $source = end($item['coverArt']['sources']);
                $albums[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'cover' => $source ? $source['url'] : '',
                    'url' => $item['sharingInfo']['shareUrl'],
                    'public' => $item['date']['year'],
                    'type' => 'single'
                ];
            }
            foreach ($result['compilations']['items'] as $item) {
                $item = $item['releases']['items'][0];
                $source = end($item['coverArt']['sources']);
                $albums[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'cover' => $source ? $source['url'] : '',
                    'url' => $item['sharingInfo']['shareUrl'],
                    'public' => $item['date']['year'],
                    'type' => 'compilation'
                ];
            }
            $data = [
                'albums_num' => $result['albums']['totalCount'],
                'singles_num' => $result['singles']['totalCount'],
                'compilations_num' => $result['compilations']['totalCount'],
                'albums' => $albums,
            ];
            $data['total_num'] = $data['albums_num'] + $data['singles_num'] + $data['compilations_num'];
        }

        return $this->_success($data);
    }

    /**
     * 獲取專輯信息
     * @param string $albumId
     * @return array
     */
    public function getAlbumInfo($albumId)
    {
        $url = 'https://api-partner.spotify.com/pathfinder/v1/query';
        $param = [
            'operationName' => 'getAlbumMetadata',
            'variables' => json_encode([
                'uri' => 'spotify:album:' . $albumId
            ]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => '1ecd86f7c8982af9c1381d2557c3a406d6dfe8f0f1b18665df26496a7a8e1d62'
                ],
            ])
        ];
        $url .= '?' . http_build_query($param);

        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $data = [];
        if (!empty($result['data']['album'])) {
            $album = $result['data']['album'];
            $id = explode(':', $album['uri'])[2];
            $artists = [];
            foreach ($album['artists']['items'] as $val) {
                $artists[] = [
                    'id' => explode(':', $val['uri'])[2],
                    'name' => $val['profile']['name'],
                    'avatar' => $val['visuals']['avatarImage']['sources'][0]['url'],
                    'url' => $val['sharingInfo']['shareUrl']
                ];
            }
            $data = [
                'id' => $id,
                'name' => $album['name'],
                'artists' => $artists,
                'cover' => end($album['coverArt']['sources'])['url'],
                'disc_num' => $album['discs']['totalCount'],
                'track_num' => $album['tracks']['totalCount'],
                'type' => $album['type'],
                'public' => date('Y-m-d', strtotime($album['date']['isoString'])),
                'label' => $album['label'],
                'copyright' => $album['copyright'],
                'url' => $album['sharingInfo']['shareUrl']
            ];
        }

        return $this->_success($data);
    }

    /**
     * 獲取專輯歌曲
     * @param string $albumId
     * @return array
     */
    public function getAlbumSongs($albumId)
    {
        $url = 'https://api-partner.spotify.com/pathfinder/v1/query';
        $param = [
            'operationName' => 'queryAlbumTracks',
            'variables' => json_encode([
                'uri' => 'spotify:album:' . $albumId,
                'offset' => 0,
                'limit' => 300
            ]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => '3ea563e1d68f486d8df30f69de9dcedae74c77e684b889ba7408c589d30f7f2e'
                ],
            ])
        ];
        $url .= '?' . http_build_query($param);
        
        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $data = ['total_count' => 0, 'items' => []];
        if (!empty($result['data']['album']['tracks'])) {
            $result = $result['data']['album']['tracks'];
            $data['total_count'] = $result['totalCount'];
            $items = [];
            foreach ($result['items'] as $item) {
                $track = $item['track'];
                $id = explode(':', $track['uri'])[2];
                $items[] = [
                    'id' => $id,
                    'uid' => $item['uid'],
                    'name' => $track['name'],
                    'playcount' => $track['playcount'],
                    'disc_number' => $track['discNumber'],
                    'track_number' => $track['trackNumber'],
                    'duration' => $track['duration']['totalMilliseconds'],
                    'url' => 'https://open.spotify.com/track/'.$id,
                    'album_id' => $albumId,
                    'artists' => $track['artists']['items'][0]['profile']['name']
                ];
            }
            $data['items'] = $items;
        }

        return $this->_success($data);
    }

    /**
     * 獲取歌單列表
     * @param string $playlistId
     * @return array
     */
    public function getPlaylist($playlistId)
    {
        $url = "https://spclient.wg.spotify.com/playlist/v2/playlist/{$playlistId}/metadata";
        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $updateAt = '';
        if (!empty($result['timestamp'])) {
            $updateAt = date('Y-m-d H:i:s', $result['timestamp']/1000);
        }

        $url = "https://api.spotify.com/v1/playlists/{$playlistId}";
        $param = [
            'fields' => 'description,followers(total),images,name,tracks(items(track.id,track.name,track.artists,track.popularity,track.external_urls),total),external_urls',
            'additional_types' => 'track,episode',
            'market' => 'US'
        ];
        $url .= '?'.http_build_query($param);

        $result = $this->_sendRequest($url);
        if ($result === false) {
            return $this->_error();
        }

        $tracks = [];
        foreach ($result['tracks']['items'] as $item) {
            $item = $item['track'];
            $artists = [];
            foreach ($item['artists'] as $artist) {
                $artists[] = [
                    'id' => $artist['id'],
                    'name' => $artist['name']
                ];
            }
            $tracks[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'artists' => $artists,
                'popularity' => $item['popularity'],
                'url' => $item['external_urls']['spotify']
            ];
        }
        $data = [
            'id' => $playlistId, 
            'name' => $result['name'], 
            'description' => $result['description'], 
            'images' => $result['images'][0]['url'], 
            'followers' => $result['followers']['total'], 
            'tracks' => $tracks,
            'url' => 'https://open.spotify.com/playlist/'.$playlistId,
            'update_at' => $updateAt
        ];

        return $this->_success($data);
    }

    private function _sendRequest($url, $isAuth = true)
    {
        $options = [];
        if ($isAuth) {
            $options['headers'] = ['authorization' => 'Bearer ' . $this->_getToken(), 'accept' => 'application/json'];
        }

        try {
            $response = $this->_client->get($url, $options);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->_error(__METHOD__. ', client error, [' . $e->getCode() . '] ' . $e->getMessage(), false);
        } catch(\GuzzleHttp\Exception\ServerException $e) {
            return $this->_error(__METHOD__. ', server error, [' . $e->getCode() . '] ' . $e->getMessage(), false);
        } catch (\Exception $e) {
            return $this->_error(__METHOD__. ', other error, [' . $e->getCode() . '] ' . $e->getMessage(), false);
        }

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    private function _success($data = [])
    {
        return ['ret' => true, 'data' => $data, 'msg' => ''];
    }

    private function _error($msg = '', $isArray = true)
    {
        if ($isArray) {
            return ['ret' => false, 'data' => null, 'msg' => $msg ?: $this->_errmsg];
        }
        $this->_errmsg = $msg;
        return false;
    }
}
