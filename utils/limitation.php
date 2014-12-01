<?php

require '../vendor/autoload.php';

use CS\Models\Limitation,
    CS\Models\Limitation\LimitationRecord;
;

$basicLimitation = new Limitation();
$basicLimitation->setCall(700)
        ->setSms(300)
        ->setValue(
                Limitation::GPS |
                Limitation::BLOCK_NUMBER |
                Limitation::BLOCK_WORDS |
                Limitation::BROWSER_HISTORY |
                Limitation::BROWSER_BOOKMARK |
                Limitation::CONTACT |
                Limitation::CALENDAR | 
                Limitation::PHOTOS |
                Limitation::EMAILS |
                Limitation::APPLICATIONS
        );

echo 'Basic plan: ' . $basicLimitation->getValue() . PHP_EOL;

$premiumLimitation = new Limitation();
$premiumLimitation->setCall(LimitationRecord::UNLIMITED_VALUE)
        ->setSms(LimitationRecord::UNLIMITED_VALUE)
        ->setValue(
                Limitation::GPS |
                Limitation::BLOCK_NUMBER |
                Limitation::BLOCK_WORDS |
                Limitation::BROWSER_HISTORY |
                Limitation::BROWSER_BOOKMARK |
                Limitation::CONTACT |
                Limitation::CALENDAR | 
                Limitation::PHOTOS |
                Limitation::VIBER |
                Limitation::WHATSAPP |
                Limitation::VIDEO |
                Limitation::SKYPE |
                Limitation::FACEBOOK |
                Limitation::VK |
                Limitation::EMAILS |
                Limitation::APPLICATIONS |
                Limitation::KEYLOGGER |
                Limitation::OLD_DATA |
                Limitation::SMS_COMMANDS
        );

echo 'Premium plan: ' . $premiumLimitation->getValue() . PHP_EOL;