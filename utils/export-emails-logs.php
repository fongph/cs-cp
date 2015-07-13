<?php

//export email logs from leads
//special for SD-1053 issue

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$mainDb = $di->get('db');

$records = $mainDb->query("SELECT * FROM `jira_logs` WHERE `event` = 'email-sended' AND `created_at` < FROM_UNIXTIME(1436530740)")->fetchAll(PDO::FETCH_ASSOC);

$mainDb->beginTransaction();

//
foreach ($records as $record) {
  $records['data']  = @json_decode($record['data'], true);
  
  if ($record['user_id'] !== null) {
      $userId = $mainDb->quote($record['user_id']);
      $type = $mainDb->quote($records['data']['type']);
      $date = $mainDb->quote($record['created_at']);
      $mainDb->exec("INSERT INTO `users_emails_log` SET `user_id` = {$userId}, `type` = {$type}, `date` = {$date}");
  }
}


$mainDb->commit();
var_dump(count($records));

