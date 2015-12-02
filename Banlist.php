<?php

class RedisBlackList
{
    //Redis connection params
    private $config = [
        'host' => 'localhost',
        'port' => 6379
    ];

    //Key for banlist
    private $main_banlist = 'banlist';

    //Key for capture requests
    private $capture_key = 'requests:data:';

    //Temporary key for listening requests
    private $keys_handler = [
        'ttl_per_request' => 10,
        'name' => 'key:request:',
        'requests' => 30
    ];

    //Redis connection handler
    private function connect()
    {
        $redis = new Redis();
        $redis->connect($this->config['host'], $this->config['port']);
        return $redis;
    }

    //Check if this ip is banned
    private function getStatus()
    {
        $redis = $this->connect();
        if (intval($redis->hGet($this->main_banlist, $_SERVER['REMOTE_ADDR']))) {
            $this->clearUserData();
            $redis = null;
            header('HTTP/1.0 502 Bad Gateway');
            die();
        }
    }

    //Main listen function
    public function listen()
    {
        $this->getStatus(); //Die, if ip is banned right now
        $redis = $this->connect();
        $this->keys_handler['name'] = $this->keys_handler['name'] . $_SERVER['REMOTE_ADDR']; //key like key:request:12.34.56.78

        $count_last_request = intval($redis->hGet($this->keys_handler['name'], 'request')); //get count of captured request null or int

        $redis->hIncrBy($this->keys_handler['name'], 'request', 1); //increment count of request

        $this->captureRequest(); //function for capture request uri (like apache access logs)

        $redis->expire($this->keys_handler['name'], $this->keys_handler['ttl_per_request']); //expire key ttl between requests
        if ($count_last_request >= $this->keys_handler['requests']) //if count of captured requests > value of config
        {
            $redis->hSet($this->main_banlist, $_SERVER['REMOTE_ADDR'], 1); //ban this ip
        }
        $redis = null;
    }

    //simple access logining
    private function captureRequest()
    {
        $redis = $this->connect();
        $this->capture_key = $this->capture_key . $_SERVER['REMOTE_ADDR'];
        $request = $_SERVER['REQUEST_URI'];
        $time = $_SERVER['REQUEST_TIME'];
        $capture_value = $request . '||' . $time . '||' . uniqid('RBL-UNIQ-ID-'); //Captured request, unix timestamp, uniqid for save all requests
        $redis->sAdd($this->capture_key, $capture_value);
        $redis = null;
        return;
    }

    public function getData()
    {
        $info_arr = [];
        $redis = $this->connect();
        $banned_arr = $redis->sMembers($this->main_banlist);
        if (!empty($banned_arr)) {
            foreach ($banned_arr as $ip) {
                $info_arr[$ip] = $redis->sMembers($this->capture_key . $ip);
            }
            $redis = null;
            return $info_arr;
        }
        $redis = null;
        return [];
    }

    private function clearUserData()
    {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach ($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                setcookie($name, '', time() - 1000);
                setcookie($name, '', time() - 1000, '/');
            }
        }
        return;
    }
}