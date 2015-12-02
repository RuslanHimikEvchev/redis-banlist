<?php
error_reporting(1);
require_once 'Banlist.php';
$ban = new RedisBlackList();


//$ban->listen();
//$ban->createBlackList('/var/www/data/black_list', 'apache');

//print_r($ban->getData());