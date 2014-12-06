<?php

define('ROOT_PATH', dirname(__FILE__) . '/../');
date_default_timezone_set('UTC');

ob_start();

require ROOT_PATH . 'vendor/autoload.php';

$config = require ROOT_PATH . 'build.php';

error_reporting($config['errorReporting']);

$whoops = new Whoops\Run;
$logger = new Monolog\Logger('logger');
$logger->pushProcessor(new Monolog\Processor\WebProcessor());

if ($config['environment'] == 'development') {
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::DEBUG));

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $whoops->pushHandler(new \Whoops\Handler\JsonResponseHandler);
    } else {
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    }

    $whoops->pushHandler(new \Whoops\Handler\CallbackHandler(function($exception, $inspector, $run) use ($logger) {
        $logger->addError(sprintf(
                        'Uncaught Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()
        ));
    }));
} else {
    $logger->pushHandler(new Monolog\Handler\StreamHandler($config['logger']['stream']['filename'], Monolog\Logger::INFO));
    $logger->pushHandler(new Monolog\Handler\NativeMailerHandler($config['logger']['mail']['from'], $config['logger']['mail']['subject'], $config['logger']['mail']['to']));

    $whoops->pushHandler(new \Whoops\Handler\CallbackHandler(function($exception, $inspector, $run) use ($logger) {
        $logger->addError(sprintf(
                        'Uncaught Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()
        ));
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        require(ROOT_PATH . '500.html');
        die;
    }));
}

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

        if (!(isset($route->target['public']) || $di->get('auth')->hasIdentity())) {
            $di->getFlashMessages()->add(System\FlashMessages::ERROR, "Access denied!");
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
