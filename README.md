# redis-banlist

Класс, позволяющий отсеивать траффик, по триггеру слишком частых запросов к вашему веб приложению.
Это может быть как API, так и простое приложение на базе любого MVC фреймфорка (и не только его).

Что потребуется:
Redis,
PHP5,
модуль php5-redis 

Базовая и быстрая установка Ubuntu (Debian)
sudo apt-get install redis-server php5-redis (подразумевается, что ядро PHP5 уже установлено и настроено)

Основная конфигурация коннектора класса находится в 
```php
private $config = [
        'host' => 'localhost', //хост
        'port' => 6379 //и порт на котором стартует Redis из "коробки"
    ];
```
Конфигурация триггеров поведения

```php
private $spam_listener_key = [
        'ttl_per_request' => 10, //частота запросов в секундах. Период запросов с одного IP, при котором пользователь может быть ботом (установите свое значение)
        'name' => 'key:request:', //префикс ключа для удобства поиска "горячих пользователей". Пример живого ключа -  key:request:11.22.33.44
        'requests' => 30 //Счетчик подозрительных запросов, чаще чем ttl_per_request
    ];
```

API:
Пример использования находится в test.php.

Более детально ниже.


Создаем экземпляр класса:

```php
$black_list = new RedisBlackList();
```
Вызываем listen() для прослушивания траффика
```php
$black_list->listen(bool $capture = false, bool $autoban = false);
//$capture - нужно ли перманентно логировать все запросы, проходящие через listen()?
//$autoban - после срабатывания триггера бана, отдавать ли этому IP 403 Forbidden?
```
Данный код желательно разместить до инициализации логики приложения.

Дополнительно:
Для удаления IP из черного списка, можно воспользоваться следующим кодом:

```php
$black_list = new RedisBlackList();
$black_list->unBanIP(string $ip);
```

#Формирование автоматических списков для веб серверов.

Подходит для отработки с помощью crontab
```php
$black_list = new RedisBlackList();
$black_list->createBlackList(string $path_to_file, string $type);
```
```php
$path_to_file -  путь к файлу с правами на запись без расширения файла. Например /path/to/my_black_list
$type - должен принимать или nginx, или apache
```
Далее этот файл необходимо подключить в конфигурации вебсервера.
#Nginx

```nginx
http {
  #некоторый конфиг
  include /path/to/my_black_list.conf;
  #некоторый конфиг
}
```
#Apache

В конфигурации виртуального хоста

```apache
RewriteEngine On
RewriteMap access txt:/path/to/my_black_list.txt
RewriteCond ${access:%{REMOTE_ADDR}} deny [NC]
RewriteRule ^ - [L,F]
```



