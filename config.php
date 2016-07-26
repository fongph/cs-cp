<?php

use CS\Settings\GlobalSettings,
    Models\Modules;

$default = array(
    'build' => $build['version'],
    'environment' => $build['environment'],
    'site' => $build['site'],
    'demo' => $build['demo'],
    'errorReporting' => E_ALL ^ E_NOTICE ^ E_DEPRECATED,
    'session' => array(
        'rememberMeTime' => 2592000, // 30 days
        'cookieParams' => array(
            'secure' => false,
            'httpOnly' => true
        )
    ),
    'logger' => array(
        'stream' => array(
            'filename' => ROOT_PATH . 'logs/system.log'
        ),
        'mail' => array(
            'from' => 'b.orest@dizboard.com',
            'to' => 'b.orest@dizboard.com',
            'subject' => 'Pumpic CP Logger'
        )
    ),
    'dbOptions' => array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8;',
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ),
    'fenom' => array(
        'templatesDir' => ROOT_PATH . 'app/templates/',
        'compileDir' => ROOT_PATH . 'tmp/',
        'options' => array(
            'force_include' => true
        )
    ),
    'locales' => array(
        'en-US' => 'English'
    ),
    'modules' => array(
        Modules::CALLS => 'Calls',
        Modules::SMS => 'SMS',
        Modules::LOCATIONS => 'Locations',
        Modules::BROWSER_BOOKMARKS => 'Bookmarks',
        Modules::BROWSER_HISTORY => 'Browser History',
        Modules::NOTES => 'Notes',
        Modules::CALENDAR => 'Calendar',
        Modules::CONTACTS => 'Contacts',
        Modules::PHOTOS => 'Photos',
        Modules::VIDEOS => 'Videos',
        Modules::EMAILS => 'Emails',
        Modules::APPLICATIONS => 'Applications',
        Modules::VIBER => 'Viber',
        Modules::SKYPE => 'Skype',
        Modules::WHATSAPP => 'Whatsapp',
        Modules::FACEBOOK => 'Facebook',
        Modules::KIK => 'Kik',
        Modules::INSTAGRAM => 'Instagram',
        Modules::SNAPCHAT => 'Snapchat',
        Modules::VK => 'VK Messages',
        Modules::KEYLOGGER => 'Keylogger',
        Modules::SMS_COMMANDS => 'SMS Commands',
        Modules::SETTINGS => 'Device Settings'
    ),
    'contents' => array(
        'names' => array(
            'how-to-install/android-instructions.html' => 'Android Installation Guide',
            'how-to-install/blackberry-instructions.html' => 'BlackBerry Installation Guide',
            'how-to-install/ios-instructions.html' => 'iPhone Installation Guide',
            'how-to-install/root-instructions.html' => 'Root Instructions',
            'instructions/activate-location-ios.html' => 'How to Activate Location',
            'instructions/activate-location-android.html'   => 'How to Activate Location',
            'instructions/activate-findmyiphone.html' => 'Location Tracking Activation Guide',
            'instructions/keylogger-activation.html' => 'How to enable Keylogger',
            'instructions/install-xposed.html' => 'How to Install Xposed',
            'instructions/detect-ios-jailbreak.html' => 'Check Jailbreak',
            'instructions/hide-unhide-cydia-icon.html' => 'Hide/Unhide Cydia',
            'instructions/uninstall-pumpic-ios.html' => 'How to Uninstall Pumpic on iOS',
            'instructions/uninstall-pumpic-android.html' => 'How to Uninstall Pumpic on Android',
            'instructions/prepare-ios-device-without-jailbreak.html' => 'Prepare iOS Device without Jailbreak',
        ),
        'auth' => array(
            'instructions/keylogger-activation.html',
            'instructions/detect-ios-jailbreak.html',
            'instructions/hide-unhide-cydia-icon.html',
            'instructions/uninstall-pumpic-ios.html',
            'instructions/uninstall-pumpic-android.html',
            'instructions/prepare-ios-device-without-jailbreak.html'
        )
    ),
    'bundlesNamespace' => 'first'
);

$default['mainURL'] = GlobalSettings::getMainURL($build['site']);

if ($build['environment'] == 'production') {
    $default['db'] = GlobalSettings::getMainDbConfig();
    $default['redis'] = GlobalSettings::getRedisConfig();

    if ($build['demo']) {
        $default['domain'] = GlobalSettings::getDemoControlPanelURL($build['site']);
        $default['staticDomain'] = GlobalSettings::getDemoControlPanelStaticURL($build['site']);
    } else {
        $default['domain'] = GlobalSettings::getControlPanelURL($build['site']);
        $default['staticDomain'] = GlobalSettings::getControlPanelStaticURL($build['site']);
        $default['url']['demo'] = GlobalSettings::getDemoControlPanelURL($build['site']);
    }
    
    $default['url']['registration'] = GlobalSettings::getRegistrationPageURL($build['site']);
    $default['cookieDomain'] = GlobalSettings::getCookieDomain($build['site']);
    $default['supportEmail'] = GlobalSettings::getSupportEmail($build['site']);
    
    $default['session']['cookieParams']['domain'] = $default['cookieDomain'];
  
    $default['s3'] = GlobalSettings::getS3Config();
    $default['cloudFront'] = GlobalSettings::getCloudFrontConfig();

    return $default;
} else if ($build['environment'] == 'development') {
    $default['errorReporting'] = E_ALL;

    $default['s3'] = GlobalSettings::getS3Config();
    $default['cloudFront'] = GlobalSettings::getCloudFrontConfig();
    $default['url']['demo'] = 'http://google.com/demo';
    $default['url']['registration'] = 'http://google.com/registration';
    $default['refundPolicyPage'] = 'http://google.com/refund-policy';

    if (file_exists(ROOT_PATH . 'development.config.php')) {
        require ROOT_PATH . 'development.config.php';
    }

    return $default;
} else if ($build['environment'] == 'testing') {
    // not using now
}
