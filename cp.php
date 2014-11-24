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
        ->setDescription('Generate build.php file')
        ->setCode(function (InputInterface $input, OutputInterface $output) {
            $site = $input->getArgument('site');
            $environment = $input->getOption('environment');
            $modules = $input->getOption('without-module');

            if ($site < 0 || $site > 255) {
                $output->writeln("<error>Site must be between 0 and 255!<.error>");
                return;
            }

            if (!in_array($environment, array('development', 'production', 'testing', 'staging'))) {
                $output->writeln("<error>Invalid environment value!</error>");
                return;
            }

            $build = array(
                'version' => time(),
                'environment' => $environment,
                'site' => $site
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

$console->run();

