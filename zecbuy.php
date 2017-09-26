<?php

$hashrate = 1000000;
$blockReward = 10;
$buyThreshold = 2;
$fee = 0.04;
$diffRate = 0.20;
$ALGORITHM = 24;

$API_ID = getenv('NICEHASH_API_ID');
$API_KEY = getenv('NICEHASH_API_KEY');
$HOST = getenv('NICEHASH_POOL_HOST');
$PORT = getenv('NICEHASH_POOL_PORT');
$USER = getenv('NICEHASH_POOL_USER');
$PASSWORD = getenv('NICEHASH_POOL_PASSWORD');

$now = (new DateTime())->format('Y-m-d: H:i:s');

$jsonArray = json_decode(file_get_contents("https://api.nicehash.com/api?method=balance&id=$API_ID&key=$API_KEY"), true);
$amount = $jsonArray['result']['balance_confirmed'];
if (0.01 >= floatval($amount)) {
	exit;
}

$jsonArray = json_decode(file_get_contents('https://whattomine.com/coins/166.json'), true);
$diff = $jsonArray['difficulty'];

$json = file_get_contents("https://api.nicehash.com/api?method=orders.get&location=0&algo=$ALGORITHM");
$orders = json_decode($json, true)['result']['orders'];

$jsonArray = json_decode(file_get_contents('https://api.bitfinex.com/v2/ticker/tZECBTC'), true);
$zecbtc = reset($jsonArray);

$orders = array_filter($orders, function($x) {
	return $x['workers'] !== 0;
});
$priceList = [];
$count = 1;
foreach ($orders as $order) {
	$priceList[$count] = $order['price'];
	$count++;
}

$dayReword = (($hashrate / ($diff * 8192)) * (1 - $fee) * $blockReward * 86400);
$dayBtc = $zecbtc * $dayReword;

$buyPrice = floatval($priceList[max(array_keys($priceList)) - $buyThreshold]);
if (0 >= $buyPrice) {
	echo "$now [E] buy plice";
	exit;
}
$buyPrice = $buyPrice + 0.0101;

if ($dayBtc > $buyPrice + $diffRate) {
	echo "$now [I] buy. day reword $dayReword zec \n";
	echo "$now [I] buy. day reword $dayBtc btc \n";
	echo "$now [I] buy. buy price $buyPrice  btc \n";
	return file_get_contents("https://api.nicehash.com/api?method=orders.create&id=$API_ID&key=$API_KEY&location=0&algo=$ALGORITHM&amount=$amount&price=$buyPrice&limit=0&pool_host=$HOST&pool_port=$PORT&pool_user=$USER&pool_pass=$PASSWORD");
} else {
	echo "$now [I] not buy. day reword $dayBtc btc \n";
	echo "$now [I] not buy. buy price $buyPrice  btc \n";
}
