<?php

define('ROOT_PATH', dirname(__FILE__) . '/');
date_default_timezone_set('UTC');
error_reporting(E_ALL);

require ROOT_PATH . 'vendor/autoload.php';

function var_export_min($var, $return = false)
{
    if (is_array($var)) {
        $toImplode = array();
        foreach ($var as $key => $value) {
            $toImplode[] = var_export($key, true) . '=>' . var_export_min($value, true);
        }

        $code = 'array(' . implode(',', $toImplode) . ')';

        if ($return) {
            return $code;
        }

        echo $code;
    } else {
        return var_export($var, $return);
    }
}

use Symfony\Component\Console\Application,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

$console = new Application();

$console->register('build')
    ->setDefinition(array(
        new InputArgument('site', InputArgument::REQUIRED, 'Site number')
    ))
    ->addOption(
        'environment', 'e', InputOption::VALUE_REQUIRED, 'Application environment', 'development'
    )
    ->addOption(
        'without-module', 'w', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Modules that will not be included in the build', array()
    )
    ->addOption(
        'demo', 'd', InputOption::VALUE_REQUIRED, 'Is a demo version of application', 0
    )
    ->setDescription('Generate build.php file')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $site = $input->getArgument('site');
        $environment = $input->getOption('environment');
        $modules = $input->getOption('without-module');
        $demo = $input->getOption('demo');

        if ($site < 0 || $site > 255) {
            $output->writeln("<error>Site must be between 0 and 255!</error>");
            return;
        }

        if (!in_array($environment, array('development', 'production', 'testing', 'staging'))) {
            $output->writeln("<error>Invalid environment value!</error>");
            return;
        }

        $build = array(
            'version' => time(),
            'environment' => $environment,
            'site' => $site,
            'demo' => $demo
        );

        $config = require ROOT_PATH . 'config.php';

        foreach ($modules as $value) {
            if (isset($config['modules'][$value])) {
                unset($config['modules'][$value]);
            }
        }

        if (file_put_contents(ROOT_PATH . 'build.php', '<?php return ' . var_export_min($config, true) . ';', LOCK_EX) == false) {
            $output->writeln("<error>File write error!</error>");
            return;
        }

        $output->writeln("Build created!");
    });

$console->register('load-demo-user')
    ->setDescription('Export demo user data from db to file')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        if (!is_file(ROOT_PATH . 'build.php')) {
            $output->writeln("<error>build.php file file not found!</error>");
            return;
        }

        $config = include ROOT_PATH . 'build.php';

        $di = new System\DI();
        $di->set('config', $config);

        require ROOT_PATH . 'bootstrap.php';

        $usersManager = new CS\Users\UsersManager($di->get('db'));
        try {
            $userData = $usersManager->getUserDataById($di['config']['site'], $di['config']['demo']);
        } catch (CS\Users\UserNotFoundException $e) {
            $output->writeln("<error>Demo user not found!</error>");
            return;
        }

        if (file_put_contents(ROOT_PATH . 'demoUserData.php', '<?php return ' . var_export_min($userData, true) . ';', LOCK_EX) == false) {
            $output->writeln("<error>File write error!</error>");
            return;
        }

        $output->writeln('User data successfully exported to file!');
    });

$console->register('update-demo-user-data')
    ->setDescription('Update demo user data time of devices')
    ->setDefinition(array(
        new InputArgument('days', InputArgument::REQUIRED, 'The number of days to increase')
    ))
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $days = $input->getArgument('days');

        if (!is_file(ROOT_PATH . 'build.php')) {
            $output->writeln("<error>build.php file file not found!</error>");
            return;
        }

        $config = include ROOT_PATH . 'build.php';

        if ($config['demo'] == 0) {
            $output->writeln("<error>Demo user id not defined for this build!</error>");
            return;
        }

        $demoUserId = $config['demo'];

        $di = new System\DI();
        $di->set('config', $config);

        require ROOT_PATH . 'bootstrap.php';

        $logger = new Monolog\Logger('logger');
        $logger->pushHandler(new Monolog\Handler\StreamHandler(ROOT_PATH . 'logs/demo.log', Monolog\Logger::INFO));

        $userDevices = $di['db']->query("SELECT `id` FROM `devices` WHERE `user_id` = {$demoUserId}")->fetchAll(\PDO::FETCH_COLUMN);

        /**
         * @todo Update to use multiple data databases
         */
        $pdo = $di['dataDb'];

        try {
            $value = $days * 3600 * 24;
            $devicesExpression = '`dev_id` IN (' . implode(',', $userDevices) . ')';

            $pdo->beginTransaction();
            $pdo->exec("UPDATE `applications_timelines` SET `start` = `start` + {$value}, `finish` = `finish` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `browser_history` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `calendar_events` SET `start` = `start` + {$value}, `end` = `end` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `call_log` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `emails` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `facebook_calls` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `facebook_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `geo_events` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `instagram_comments` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `instagram_posts` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `keylogger` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `kik_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `photos` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `skype_calls` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `skype_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `sms_log` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `viber_calls` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `viber_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `video` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `vk_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `whatsapp_calls` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `whatsapp_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `notes` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");
            $pdo->exec("UPDATE `snapchat_messages` SET `timestamp` = `timestamp` + {$value} WHERE {$devicesExpression}");


        } catch (\Exception $exception) {
            $logger->addError(sprintf(
                'Exception during updatting demo user data - %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()
            ));
            $pdo->rollBack();
            $output->writeln("<error>Exception during updating demo user data</error>");
            return;
        }

        $pdo->commit();

        $message = date('r') . " - User data successfully updated by $days days!";

        $logger->addInfo($message);
        $output->writeln($message);
    });

$console->register('update-demo-user-devices-status')
    ->setDescription('Update demo user devices last update time')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        if (!is_file(ROOT_PATH . 'build.php')) {
            $output->writeln("<error>build.php file file not found!</error>");
            return;
        }

        $config = include ROOT_PATH . 'build.php';

        if ($config['demo'] == 0) {
            $output->writeln("<error>Demo user id not defined for this build!</error>");
            return;
        }

        $demoUserId = $config['demo'];

        $di = new System\DI();
        $di->set('config', $config);

        require ROOT_PATH . 'bootstrap.php';

        $logger = new Monolog\Logger('logger');
        $logger->pushHandler(new Monolog\Handler\StreamHandler(ROOT_PATH . 'logs/demo.log', Monolog\Logger::INFO));

        /**
         * @todo Update to use multiple data databases
         */
        $pdo = $di['dataDb'];

        try {
            $pdo->beginTransaction();

            $userDevices = $di['db']->query("SELECT `id`, `os` FROM `devices` WHERE `user_id` = {$demoUserId}")->fetchAll(\PDO::FETCH_ASSOC);

            if (count($userDevices)) {
                foreach ($userDevices as $device) {
                    $time = time() - rand(0, 900);
                    if ($device['os'] != 'icloud') {
                        $di['db']->exec("UPDATE `devices` SET `last_visit` = {$time} WHERE `id` = {$device['id']}");
                    } else {
                        $di['db']->exec("UPDATE `devices_icloud` SET `last_backup` = {$time}, `last_sync` = {$time} WHERE `dev_id` = {$device['id']}");
                    }
                }
            }
        } catch (\Exception $exception) {
            $logger->addError(sprintf(
                'Exception during updatting demo user data - %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()
            ));
            $pdo->rollBack();
            $output->writeln("<error>Exception during updating demo user data</error>");
            return;
        }

        $pdo->commit();

        $message = "User devices last update date successfully updated!";

        $logger->addInfo($message);
        $output->writeln($message);
    });

$console->register('add-user')
    ->setDefinition(array(
        new InputArgument('email', InputArgument::REQUIRED, 'User email'),
        new InputArgument('password', InputArgument::REQUIRED, 'User password'),
    ))
    ->setDescription('Create new user')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        if (!is_file(ROOT_PATH . 'build.php')) {
            $output->writeln("<error>build.php file file not found!</error>");
            return;
        }

        $config = include ROOT_PATH . 'build.php';

        $di = new System\DI();
        $di->set('config', $config);

        require ROOT_PATH . 'bootstrap.php';

        $email = $input->getArgument('email');

        $usersManager = new CS\Users\UsersManager($di->get('db'));
        $usersManager->getUser()
            ->setLogin($email)
            ->setSiteId($di['config']['site'])
            ->setPassword($usersManager->getPasswordHash($input->getArgument('password')))
            ->save();

        $output->writeln(sprintf('Record created for user <info>%s</info>', $email));
    });

$console->register('icloud-info')
    ->setDefinition(array(
        new InputArgument('email', InputArgument::REQUIRED, 'User email'),
        new InputArgument('password', InputArgument::REQUIRED, 'User password'),
    ))
    ->setDescription('iCloud FMI info')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $icloud = new \CS\ICloud\Locations\Sosumi($input->getArgument('email'), $input->getArgument('password'));

        $output->writeln(print_r($icloud->devices, true));
    });

$console->register('icloud-backup-info')
    ->setDefinition(array(
        new InputArgument('email', InputArgument::REQUIRED, 'User email'),
        new InputArgument('password', InputArgument::REQUIRED, 'User password'),
    ))
    ->setDescription('iCloud backup info')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $backup = new \CS\ICloud\Backup($input->getArgument('email'), $input->getArgument('password'));

        $output->writeln(print_r($backup->getDevices(), true));
    });

$console->run();

