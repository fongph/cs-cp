<?php

define('ROOT_PATH', dirname(__FILE__) . '/../');
date_default_timezone_set('UTC');
error_reporting(E_ALL);

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

$console = new ConsoleKit\Console();

/**
 *  Cоздаем нового пользователя
 * 
 *  php console.php add-user email password
 */
$console->addCommand('add-user', function($args) use ($console, $config) {
    if (count($args) == 2) {
        $di = new System\DI();
        $di->set('config', $config);

        require ROOT_PATH . 'bootstrap.php';

        error_reporting(E_ALL);

        $usersManager = new CS\Users\UsersManager($di->get('db'));
        $usersManager->getUser()
                ->setLogin($args[0])
                ->setSiteId($di['config']['site'])
                ->setPassword($usersManager->getPasswordHash($args[1]))
                ->save();

        $console->writeln("User created!");
    }
});

/**
 *  Создаем файл с настройками окружения, версией сайта и номером билда
 * 
 *  php console.php build environment siteId
 * 
 *  для тестового сайта siteId - 0
 */
$console->addCommand('build', function($args) use ($console) {
    if (count($args) == 2) {
        if (!in_array($args[0], array('development', 'production', 'testing', 'staging'))) {
            $console->writeln("Invalid environment value!", \ConsoleKit\TextWriter::STDERR);
            die;
        }
        $siteId = intval($args[1]);

        if ($siteId < 0 || $siteId > 255) {
            $console->writeln("Site must be between 0 and 255!", \ConsoleKit\TextWriter::STDERR);
            die;
        }

        $build = array(
            'version' => time(),
            'environment' => $args[0],
            'site' => $args[1]
        );

        if (file_put_contents(ROOT_PATH . 'build.php', '<?php return ' . var_export($build, true) . ';', LOCK_EX) == false) {
            $console->writeln("File write error!", \ConsoleKit\TextWriter::STDERR);
            die;
        }

        $console->writeln("Build created!");
    }
});
$console->run();