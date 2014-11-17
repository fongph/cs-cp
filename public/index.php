<?php

define('ROOT_PATH', dirname(__FILE__) . '/../');
date_default_timezone_set('UTC');

ob_start();

require ROOT_PATH . 'vendor/autoload.php';

if (!is_file(ROOT_PATH . 'build.php')) {
    $build = array(
        'version' => 0,
        'environment' => 'development',
        'site' => 0
    );
    $config = include ROOT_PATH . 'config.php';
} else {
    // @TODO: написать config.builder который будет билдить конфиг при
    // установке приложения, и нужно будет подгружать только build.php
    $build = include ROOT_PATH . 'build.php';
    $config = include ROOT_PATH . 'config.php';
}

error_reporting($config['errorReporting']);

$whoops = new Whoops\Run;
$logger = new Monolog\Logger('logger');
$logger->pushProcessor(new Monolog\Processor\WebProcessor());

if ($config['environment'] == 'development') {
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::DEBUG));
} else {
    $whoops->pushHandler(new \Whoops\Handler\CallbackHandler(function() {
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        require(ROOT_PATH . '500.html');
    }));
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::INFO));
    $logger->pushHandler(new Monolog\Handler\NativeMailerHandler($config['logger']['mail']['from'], $config['logger']['mail']['subject'], $config['logger']['mail']['to']));
}

Monolog\ErrorHandler::register($logger);
$whoops->register();

$di = new System\DI();
$di->set('config', $config);
$di->set('request', new System\Request($_GET, $_POST, $_COOKIE, $_SERVER));
$di->set('logger', $logger);

require ROOT_PATH . 'bootstrap.php';

$parts = explode('?', $di->get('request')->server('REQUEST_URI'));
$requestUri = urldecode(array_shift($parts));
$di->set('requestUri', $requestUri);

$di['router']->execute($requestUri, function($route) use ($di) {
    if ($route !== false &&
            isset($route->target, $route->target['controller'], $route->target['action']) &&
            class_exists('Controllers\\' . $route->target['controller'])) {
        
        $controllerName = 'Controllers\\' . $route->target['controller'];
        $controller = new $controllerName($di);

        //p($di->get('auth')->hasIdentity(), 1);
        
        if (!(isset($route->target['public']) || $di->get('auth')->hasIdentity())) {
            if ($di['config']['environment'] == 'development') {
                throw new Exception('Access denied!');
            }
            $controller->redirect($di->get('router')->getRouteUrl('main'));
        }

        if (isset($route->params)) {
            $controller->setParams($route->params);
        }

        if ($controller->callAction($route->target['action'])) {
            return;
        }
    }

    $cnt = new Controllers\BaseController($di);
    $cnt->error404();
});
