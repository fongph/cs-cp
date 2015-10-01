<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

//$config = array(
//    'db' => array('host' => 'localhost', 'username' => 'root', 'password' => 'root', 'dbname' => 't', 'options' => array(1002 => 'set names utf8mb4;', 3 => 2, 19 => 2))
//);
//
//$pdo = new \PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']}", $config['db']['username'], $config['db']['password'], $config['db']['options']);
//
//
//$pdo->query("INSERT INTO `lol1` SET `vasa` = 'Привет 😃 Ках дела?', `vasa2` = 'Привет 😃 Ках дела?'");
//
//var_dump($pdo->query("SELECT * FROM `lol1` ORDER BY `id` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC));
//var_dump($pdo->query("SHOW WARNINGS")->fetchAll(PDO::FETCH_ASSOC));
//
//
//die;



echo var_dump('\u043B');

$a = pack('H*', 0xF08080A7);
$a = pack('H*', 170);

echo var_dump($a);
die;

$userId = $a;

echo var_dump($pdo->query("SELECT `id` FROM `lol` WHERE `id` = '{$userId}'")->fetchColumn());

echo var_dump($pdo->query("SELECT * FROM `lol` WHERE  id = 1")->fetch());