<?php

function p($var, $die = false)
{
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

function d($var)
{
    p($var, true);
}

function getNameFromEmail($email)
{
    $parts = explode('@', $email);

    if (count($parts) == 2) {
        return $parts[0];
    }

    return 'Some Name';
}

function get(array $arr, $key, $default = null)
{
    return isset($arr[$key]) ? $arr[$key] : $default;
}

function validatePhoneNumber($value)
{
    return preg_match('#^[\+]?[0-9]{3,20}$#', $value);
}

function goBack() {   
    
    $_url = false;
    if(isset($_COOKIE['document_referer'])) {
        $_url = $_COOKIE['document_referer'];
    }
    
    return $_url;
}