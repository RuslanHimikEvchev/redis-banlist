<?php

class RedisBlackList
{
    private $redis;

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

    public function __construct()
    {
        $this->redis = $this->connect();
    }

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
        if (intval($this->redis->hGet($this->main_banlist, $_SERVER['REMOTE_ADDR']))) {
            $this->clearUserData();
            $this->redis->close();
            header('HTTP/1.0 403 Forbidden');
            die();
        }
    }

    //Main listen function
    public function listen($capture = false, $autoban = false)
    {
        if($autoban)
            $this->getStatus(); //Die, if ip is banned right now

        $this->keys_handler['name'] = $this->keys_handler['name'] . $_SERVER['REMOTE_ADDR']; //key like key:request:12.34.56.78

        $count_last_request = intval($this->redis->hGet($this->keys_handler['name'], 'request')); //get count of captured request null or int

        $this->redis->hIncrBy($this->keys_handler['name'], 'request', 1); //increment count of request

        if($capture)
            $this->captureRequest(); //function for capture request uri (like apache access logs)

        $this->redis->expire($this->keys_handler['name'], $this->keys_handler['ttl_per_request']); //expire key ttl between requests
        if ($count_last_request >= $this->keys_handler['requests']) //if count of captured requests > value of config
        {
            $this->redis->hSet($this->main_banlist, $_SERVER['REMOTE_ADDR'], 1); //ban this ip
            $this->redis->expire($this->keys_handler['name'], -1); //Save key structure for banned user
        }
        $this->redis->close();
    }

    //simple access logining
    private function captureRequest()
    {
        $this->capture_key = $this->capture_key . $_SERVER['REMOTE_ADDR'];
        $request = $_SERVER['REQUEST_URI'];
        $time = $_SERVER['REQUEST_TIME'];
        $capture_value = $request . '||' . $time . '||' . uniqid('RBL-UNIQ-ID-'); //Captured request, unix timestamp, uniqid for save all requests
        $this->redis->sAdd($this->capture_key, $capture_value);
        $this->redis->close();
        return;
    }

    public function getData()
    {
        $info_arr = [];
        $banned_arr = $this->redis->sMembers($this->main_banlist);
        if (!empty($banned_arr)) {
            foreach ($banned_arr as $ip) {
                $info_arr[$ip] = $this->redis->sMembers($this->capture_key . $ip);
            }
            $this->redis->close();
            return $info_arr;
        }
        $this->redis->close();
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

    /**
     * @param $ip
     * @return bool
     */

    public function unBanIP($ip)
    {
        if(!empty($ip))
        {
            $this->redis->hSet($this->main_banlist, $ip, 0);
            $this->redis->close();
            return true;
        }
        return false;
    }

    /**
     * @param $path_to_black_list /path/to/black_list.txt (string)
     * @param $type string type of web server
     * @return bool
     *
     * For example, Apache can read IP black list from txt file. In VH config:
     *
     *      RewriteEngine On
     *      RewriteMap access txt:/path/to/blacklist.txt
     * in .htaccess or VH config:
     *
     *      RewriteEngine On (only for .htaccess)
     *      RewriteCond ${access:%{REMOTE_ADDR}} deny [NC]
     *      RewriteRule ^ - [L,F]
     *
     * Example of blacklist.txt
     *
     *      111.222.33.44  deny
     *      55.66.77.88    deny
     *
     * More details: http://stackoverflow.com/questions/13008242/ban-ips-from-text-file-using-htaccess
     *
     * For nginx
     *  include /path/to/deny/list.conf
     *
     */

    public function createBlackList($path_to_black_list = '/var/www/data/black_list', $type = 'apache')
    {
        $to_list = '';

        $file_ext = ($type == 'apache') ? '.txt' : '.conf';

        $ip_arr = $this->redis->hGetAll($this->main_banlist);
        // [ip] => 1 or 0
        foreach ($ip_arr as $ip => $deny) {
            if(intval($deny) == 0)
                continue;
            else
                $deny = 'deny';
            if($type == 'apache')
                $to_list .= $ip . ' ' . $deny . "\n";
            if($type == 'nginx')
                $to_list .= $deny . ' ' . $ip . ";\n";
        }
        file_put_contents($path_to_black_list.$file_ext, $to_list);
        $this->redis->close();
        return true;
    }
}