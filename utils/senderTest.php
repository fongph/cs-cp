<?php

require '../vendor/autoload.php';

$mailProcessor = new CS\Mail\Processor\RemoteProcessor(
        CS\Settings\GlobalSettings::getMailSenderURL(1), CS\Settings\GlobalSettings::getMailSenderSecret(1)
);

$mailSender = new CS\Mail\MailSender($mailProcessor);
$mailSender->setLocale('aaa')
        ->setSiteId(1);

var_dump($mailSender->sendLostPassword('b.orest@dataedu.com', '123'));