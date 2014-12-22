<?php

require '../vendor/autoload.php';

$mailProcessor = new CS\Mail\Processor\RemoteProcessor(
        CS\Settings\GlobalSettings::getMailSenderURL(1), CS\Settings\GlobalSettings::getMailSenderSecret(1)
);

$mailSender = new CS\Mail\MailSender($mailProcessor);
$mailSender->setLocale('en-GB')
        ->setSiteId(1);

function sendAllEmails($mailSender, $email)
{
    //$mailSender->sendUnlockPassword($email, 'http://google.com/unlockUrl');
    $mailSender->sendRegistrationSuccessWithPassword($email, '#login', '#password');
    //$mailSender->sendLostPassword($email, 'http://google.com/restorePageUrl');
    $mailSender->sendNewDeviceAdded($email, '#deviceName');
    //$mailSender->sendSimCardChanged($email, '#deviceName');

    //$mailSender->sendNewPurchase($email, 'support@pumpic.com', 'User name', 'Product name', 100);
    //$mailSender->sendSystemSupportTicket($email, '#ticketId', '#userName', 'useremail@dizboard.com', '#ticketType', '#message', '#browser', '#os');
}

sendAllEmails($mailSender, 'b.orest@dizboard.com');
