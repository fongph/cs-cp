<?php

$default['db'] = array(
    'host' => '188.40.64.2',
    'username' => 'admin',
    'password' => 'dfhdfh7wRDDL2nZsdgsdgsLK45646qUMuTsdfsdf',
    'dbname' => 'main'
);

$default['dataDb'][1] = array(
    'host' => '188.40.64.2',
    'username' => 'admin',
    'password' => 'dfhdfh7wRDDL2nZsdgsdgsLK45646qUMuTsdfsdf',
    'dbname' => 'data'
);

//$default['db'] = array(
//    'host' => 'localhost',
//    'username' => 'root',
//    'password' => 'root',
//    'dbname' => 'main'
//);
//
//$default['dataDb'][1] = array(
//    'host' => 'localhost',
//    'username' => 'root',
//    'password' => 'root',
//    'dbname' => 'data'
//);

$default['redis'] = array(
    'database' => 1
);

$default['domain'] = 'http://cp.cs.test';
$default['staticDomain'] = 'http://cp.cs.test/static';
$default['cookieDomain'] = '.cs.test';
$default['demoDomain'] = 'http://demo.cp.cs';
$default['supportEmail'] = 'lol@lol.com';

$default['session']['cookieParams']['domain'] = $default['cookieDomain'];

$default['fenom']['options'] = array(
    'force_compile' => true
);
