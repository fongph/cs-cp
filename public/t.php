<?php

define('ROOT_PATH', dirname(__FILE__) . '/../');
date_default_timezone_set('UTC');

require ROOT_PATH . 'vendor/autoload.php';

$config = require ROOT_PATH . 'build.php';

$di = new System\DI();
$di->set('config', $config);

$request = new System\Request($_GET, $_POST, $_COOKIE, $_SERVER);
$di->set('request', $request);
        
require ROOT_PATH . 'bootstrap.php';

$db = new \PDO(); // подключение к main базе

$siteId = $config['site'];
$userId = $request->get('u', 1); // id пользователя в нашей системе
$productId = $request->get('p', 7); // id  продукта в нашей системе (таблица ’product’)
$storeId = 'pumpic'; // наш идентификатор в fast spring

$manager = new CS\Billing\Manager($di['db']);
$gateway = new Seller\FastSpring\Gateway();

$order = $manager->getOrder();
$order->setSiteId($siteId)
        ->setUserId($userId)
        ->setStatus(CS\Models\Order\OrderRecord::STATUS_PENDING)
        ->setPaymentMethod(CS\Models\Order\OrderRecord::PAYMENT_METHOD_FASTSPRING)
        ->save();

$orderProduct = $manager->getOrderProduct();
$orderProduct->setOrder($order)
        ->setProduct($manager->getProduct($productId))
        ->loadReferenceNumber()
        ->save();

$getway = new Seller\FastSpring\Gateway();
$getway->setStoreId($storeId)
        ->setProductId($orderProduct->getReferenceNumber())
        ->setReferenceData($order->getId() . '-' . $order->getHash())
        ->setTestMode(); // не обязательно

$response = $getway->purchaseProduct()->send();

$redirectUrl = $response->getRedirectUrl();

header('Location: ' . $redirectUrl, true, $statusCode);