<?php

function registerAutoload($config) {
    spl_autoload_register(function($class) use ($config) {
        foreach ($config['namespaces'] as $begin => $path) {
            if (strpos($class, $begin) === 0 && ($class = str_replace($begin . NAMESPACE_SEPARATOR, '', $class))) {
                $file = $path . str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $class) . '.php';
                if (is_file($file)) {
                    include $path . str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $class) . '.php';
                }
            }
        }
    });
}

function p($var, $die = false) {
    echo '<pre>';
    if ($var === false)
        echo 'false';
    elseif ($var === null)
        echo 'null';
    elseif ($var === true)
        echo 'true';
    else
        print_r($var);
    echo '</pre>';

    if ($die)
        die();
}

function d($var) {
    p($var, true);
}

function getPlanNormalName($name) {
    switch ($name) {
        case 'PRO':
            return 'Pro';
        case 'PRO Plus':
            return 'Pro+';
    }

    return $name;
}

function isErrorInArray($array) {
    foreach ($array as $value) {
        if (count($value)) {
            return true;
        }
    }

    return false;
}

function getNameFromEmail($email) {
    $parts = explode('@', $email);

    if (count($parts) == 2) {
        return $parts[0];
    }

    return 'Some Name';
}

function get(array $arr, $key, $default = null) {
    return isset($arr[$key]) ? $arr[$key] : $default;
}

function validatePhoneNumber($value) {
    return preg_match('#^\+[0-9]{9,13}$#', $value);
}

function logException(Exception $e, $fileName, $showRequest = false) {
    $string = PHP_EOL .
            date("Y-m-d H:i:s") . PHP_EOL .
            $e->getMessage() . PHP_EOL .
            $e->getTraceAsString() .
            ($showRequest ? PHP_EOL . "REQUEST:" . PHP_EOL . print_r($_REQUEST, true) : '');

    file_put_contents($fileName, $string, FILE_APPEND);
}

function getIPCountry($ip) {
    require_once 'tabgeo_country_v4.php';

    return tabgeo_country_v4($ip);
}

function getRealIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    return $_SERVER['REMOTE_ADDR'];
}
