<?php

use CS\Settings\GlobalSettings,
    Models\Modules;

$default = array(
    'build' => $build['version'],
    'environment' => $build['environment'],
    'site' => $build['site'],
    'errorReporting' => E_ALL ^ E_NOTICE,
    'session' => array(
        'rememberMeTime' => 2592000 // 30 days
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
        'templatesDir' => ROOT_PATH . 'templates/',
        'compileDir' => ROOT_PATH . 'tmp/',
        'options' => array(
            'force_include' => true
        )
    ),
    'locales' => array(
        'en-GB' => 'English'
    ),
    'modules' => array(
        Modules::CALLS => 'View Calls',
        Modules::SMS => 'View SMS',
        Modules::LOCATIONS => 'View Locations',
        Modules::BROWSER_BOOKMARKS => 'View Bookmarks',
        Modules::BROWSER_HISTORY => 'View Browser History',
        Modules::CALENDAR => 'View Calendar',
        Modules::CONTACTS => 'View Contacts',
        Modules::KEYLOGGER => 'Keylogger',
        Modules::PHOTOS => 'View Photos',
        Modules::VIDEOS => 'View Videos',
        Modules::VIBER => 'Viber Tracking',
        Modules::SKYPE => 'Skype Tracking',
        Modules::WHATSAPP => 'Whatsapp Tracking',
        Modules::FACEBOOK => 'Facebook Messages',
        Modules::VK => 'VK Messages',
        Modules::EMAILS => 'View Emails',
        Modules::APPLICATIONS => 'View Applications',
        Modules::SMS_COMMANDS => 'Sms Commands',
        Modules::SETTINGS => 'Device Settings'
    ),
    'contents' => array(
        'how-to-install/android-instructions.html' => 'Android Installation Guide',
        'how-to-install/blackberry-instructions.html' => 'BlackBerry Installation Guide',
        'how-to-install/ios-instructions.html' => 'iPhone Installation Guide',
        'how-to-install/root-instructions.html' => 'Root Instructions'
    )
);

if ($build['environment'] == 'production') {
    $default['db'] = GlobalSettings::getMainDbConfig();
    $default['redis'] = GlobalSettings::getRedisConfig();

    $default['domain'] = GlobalSettings::getControlPanelURL($build['site']);
    $default['registration'] = GlobalSettings::getRegistrationPageURL($build['site']);
    $default['staticDomain'] = GlobalSettings::getControlPanelStaticURL($build['site']);
    $default['cookieDomain'] = GlobalSettings::getCookieDomain($build['site']);
    $default['supportEmail'] = GlobalSettings::getSupportEmail($build['site']);
    $default['demoDomain'] = GlobalSettings::getDemoControlPanelURL($build['site']);
    
    $default['session']['cookieParams']['domain'] = $default['cookieDomain'];
  
    $default['s3'] = GlobalSettings::getS3Config();
    $default['cloudFront'] = GlobalSettings::getCloudFrontConfig();

    return $default;
} else if ($build['environment'] == 'development') {
    $default['errorReporting'] = E_ALL;

    $default['s3'] = GlobalSettings::getS3Config();
    $default['cloudFront'] = GlobalSettings::getCloudFrontConfig();
    $default['registration'] = 'http://www.google.com/registration/';

    if (file_exists(ROOT_PATH . 'development.config.php')) {
        require ROOT_PATH . 'development.config.php';
    }

    return $default;
} else if ($build['environment'] == 'testing') { // deprecated
    return array_merge($default, array(
        'db' => array(
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'dbname' => 'user_data_test',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'set names latin1;',
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            )
        )
    ));
}