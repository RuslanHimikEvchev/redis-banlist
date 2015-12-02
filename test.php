<?php
error_reporting(1);
ini_set('display_errors', 1);
require_once 'Banlist.php';
$ban = new RedisBlackList();
$ban->listen();
//print_r($ban->getData());