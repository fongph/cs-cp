<?php

//APPLICATION_ENV: development | testing | production


define('ROOT_PATH', dirname(__FILE__) . '/../');
define('APPLICATION_ENV', 'development');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('NAMESPACE_SEPARATOR', '\\');

date_default_timezone_set('UTC');

if (APPLICATION_ENV == 'development') {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL ^ E_NOTICE);
}

if (APPLICATION_ENV === 'development') {
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && (error_reporting() & $error['type'])) {
            ob_clean();
            echo '<pre>' . $error['message'] . '</pre>';
            echo '<pre>FILE: ' . $error['file'] . ':' . $error['line'] . '</pre>';
            die;
        }
    });

    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return;
        }

        ob_clean();
        echo '<pre>' . $errstr . '</pre>';
        echo '<pre>FILE: ' . $errfile . ':' . $errline . '</pre>';
        die;
    });

    set_exception_handler(function($e) {
        ob_clean();
        echo sprintf('<pre>Uncaught Exception - %s: "%s" at %s line %s</pre>', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
        die;
    });
} else {
    function showErrorPage($die = true)
    {
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        require(ROOT_PATH . '500.html');

        if ($die) {
            exit();
        }
    }

    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && (error_reporting() & $error['type'])) {
            showErrorPage(false);
        }
    });

    set_error_handler(function($errno) {
        if (!(error_reporting() & $errno)) {
            return;
        }

        showErrorPage();
    });

    set_exception_handler(function() {
        showErrorPage();
    });
}

ob_start();

require ROOT_PATH . 'vendor/autoload.php';
$config = require ROOT_PATH . 'config.php';

$di = new System\DI();
$di->set('config', $config);

require ROOT_PATH . 'bootstrap.php';

$parts = explode('?', $_SERVER['REQUEST_URI']);
$requestUri = urldecode(array_shift($parts));
$di->set('requestUri', $requestUri);

$di['router']->execute($requestUri, function($route) use ($di) {
    if ($route !== false) {
        if (isset($route->target, $route->target['controller'], $route->target['action'])) {
            if (class_exists('Controllers' . NAMESPACE_SEPARATOR . $route->target['controller'])) {
                $controllerName = 'Controllers' . NAMESPACE_SEPARATOR . $route->target['controller'];
                $cnt = new $controllerName($di);

                if (!(isset($route->target['public']) || $di->get('auth')->hasIdentity())) {
                    if (APPLICATION_ENV == 'development') {
                        throw new Exception('Access denied!');
                    } else {
                        $cnt->redirect($di->get('router')->getRouteUrl('main'));
                    }
                }

                if (isset($route->params)) {
                    $cnt->setParams($route->params);
                }

                if (!$cnt->callAction($route->target['action'])) {
                    $cnt->error404();
                }
            } else {
                $cnt = new Controllers\BaseController($di);
                $cnt->error404();
            }
        }
    } else {
        $cnt = new Controllers\BaseController($di);
        $cnt->error404();
    }
});