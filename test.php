<?php
error_reporting(1);
require_once 'Banlist.php';
$ban = new RedisBlackList();


//$ban->listen(false);
$ban->createApacheBlackList();

//print_r($ban->getData());