<?php

$hashrate = 1000000;
$blockReward = 10;
$buyThreshold = 2;
$fee = 0.04;
$diffRate = 0.25;
$cancelDiffRate = 0.2;
$ALGORITHM = 24;

$API_ID = getenv('NICEHASH_API_ID');
$API_KEY = getenv('NICEHASH_API_KEY');
$HOST = getenv('NICEHASH_POOL_HOST');
$PORT = getenv('NICEHASH_POOL_PORT');
$USER = getenv('NICEHASH_POOL_USER');
$PASSWORD = getenv('NICEHASH_POOL_PASSWORD');

$now = (new DateTime())->format('Y-m-d: H:i:s');

$nicehashApi = new NicehashAPI($API_ID, $API_KEY, $ALGORITHM);

$jsonArray = $nicehashApi->balance();
$amount = $jsonArray['result']['balance_confirmed'];

$jsonArray = json_decode(file_get_contents('https://whattomine.com/coins/166.json'), true);
$diff = $jsonArray['difficulty'];

$jsonArray = $nicehashApi->get();
$orders = $jsonArray['result']['orders'];

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
	if (0.01 >= floatval($amount)) {
		exit;
	}
	echo "$now [I] buy. day reword $dayReword zec \n";
	echo "$now [I] buy. day reword $dayBtc btc \n";
	echo "$now [I] buy. buy price $buyPrice  btc \n";
	$nicehashApi->create($amount, $buyPrice, $HOST, $PORT, $USER, $PASSWORD);

} else {
	$myOrders = $nicehashApi->myGet();
	if (empty($myOrders['result']['orders']) and 0.01 >= floatval($amount)) {
		exec('ruby zecsell.rb');
	}
	if ($cancelDiffRate >= $dayBtc - $buyPrice) {
		foreach ($myOrders['result']['orders'] as $order) {
			$orderId = $order['id'];
			$jsonArray = $nicehashApi->orderRemove($orderId);
		};
		echo "$now [I] cancel. orderID: $orderId \n";
	}
	echo "$now [I] not buy. day reword $dayBtc btc \n";
	echo "$now [I] not buy. buy price $buyPrice  btc \n";
}

class NicehashAPI
{
	private $apiId;
	private $apiKey;
	private $algorithm;

	const url = 'https://api.nicehash.com/api';

	public function __construct($apiId, $apiKey, $algorithm)
	{
		$this->apiId = $apiId;
		$this->apiKey = $apiKey;
		$this->algorithm = $algorithm;
	}

	private function url($method, $isPrivateApi = false, $params = [])
	{
		$token = '&id=' . $this->apiId . '&key=' . $this->apiKey . '&location=0&algo=' . $this->algorithm;
		if ($isPrivateApi) {
			$token = '&my' . $token;
		}
		if (!empty($params)) {
			$urlParams = '';
			foreach ($params as $key => $value) {
				$urlParams = $urlParams . '&' . $key . '=' . $value;
			}
			$token = $token . $urlParams;
		}

		return json_decode(file_get_contents(self::url . "?method=$method$token"), true);
	}

	public function myGet()
	{
		return $this->url('orders.get', true);
	}

	public function balance()
	{
		return $this->url('balance');
	}

	public function get()
	{
		return $this->url('orders.get');
	}

	public function orderRemove($orderId)
	{
		return $this->url('orders.remove', false, ['order' => $orderId]);
	}

	public function create($amount, $price, $host, $port, $user, $password)
	{
		return $this->url(
			'orders.create',
			false,
			[
				'amount'    => $amount,
				'price'     => $price,
				'pool_host' => $host,
				'pool_port' => $port,
				'pool_user' => $user,
				'pool_pass' => $password,
				'limit'     => 0,
			]
		);
	}
}
