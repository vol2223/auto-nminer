<?php

$hashrate = 1000000;
$blockReward = 10;
$buyThreshold = 1;
$fee = 0;
$diffRate = 0.13;
$cancelDiffRate = 0.08;
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

$myOrders = $nicehashApi->myGet();

$orders = array_filter($orders, function($x) {
	return $x['workers'] !== 0;
});

$priceList = [];
$count = 0;
foreach ($orders as $order) {
	$priceList[$count] = $order['price'];
	$count++;
}

$dayReword = (($hashrate / ($diff * 8192)) * (1 - $fee) * $blockReward * 86400);
$dayBtc = $zecbtc * $dayReword;

$buyPrice = floatval($priceList[max(array_keys($priceList))]);

//$buyPrice = $dayBtc - 0.11;
//$fee = $amount * 0.03 + $amount * 0.01 + 0.0006 + $amount * 0.04;
//$minutesDayBtc = $dayBtc/24/60;
//$minutesBuyBtc = $buyPrice/24/60;
//$processTimeByDay = 86400 * ($amount/$dayBtc);
//$processTimeByBuy = 86400 * (($amount - $fee)/$buyPrice);
//$processTimeFromAmount = (($processTimeByBuy - $processTimeByDay) / 60);
// var_dump($minutesBuyBtc * $processTimeFromAmount);
//$rewordBtc = ($minutesDayBtc * $processTimeFromAmount) - ($minutesBuyBtc * $processTimeFromAmount);
// var_dump($rewordBtc);
//exit;

if (0 >= $buyPrice) {
	echo "$now [E] buy plice $buyPrice\n";
	echo  count($priceList) . "\n";
	echo implode(',', array_keys($priceList)) . "\n";
	exit;
}
$buyPrice = $buyPrice + 0.0101;

if ($dayBtc > $buyPrice + $diffRate) {
	if (0.01 >= floatval($amount)) {
		exit;
	}
	echo "$now [I] Purchase is established. day reword $dayReword zec\n";
	echo "$now [I] Purchase is established. day reword $dayBtc btc\n";
	echo "$now [I] Purchase is established. buy price $buyPrice  btc\n";
	$nicehashApi->create($amount, $buyPrice, $HOST, $PORT, $USER, $PASSWORD);

} else {
	foreach ($myOrders['result']['orders'] as $order) {
		if ($cancelDiffRate >= $dayBtc - $order['price']) {
			$orderId = $order['id'];
			$cancelPrice = $order['price'];
			$jsonArray = $nicehashApi->orderRemove($orderId);
			echo "$now [I] cancel. orderID: $orderId price: $cancelPrice \n";
		}
	}
//	echo "$now [I] not buy. day reword $dayBtc btc\n";
//	echo "$now [I] not buy. buy price $buyPrice  btc\n";
	$diffPrice = $dayBtc - $buyPrice;
	echo "$now [I] not buy. diff price $diffPrice btc\n";
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
