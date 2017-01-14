<?php

namespace NesStocks;

class BestBuyChecker {

	function __construct($config, $http = null) {
		$this->config = $config;
		$this->http = $http ?: new HttpClient();
	}

	function findStores($zip) {
		$url = 'http://www.bestbuy.com/site/store-locator/' . $zip;

		$headers = array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36',
		);

		$response = $this->http->get($url, $headers);

		if (!preg_match('/window\.appData = ([^;]+);/', $response, $ad)) {
			throw new Exception('Unable to parse window.appData');
		}

		$appData = json_decode($ad[1], true);

		return $appData['stores'];
	}

	function check($zip) {
		$url = 'http://www.bestbuy.com/productfulfillment/c/api/1.0/storeAvailability';

		$data = json_encode(array(
			"skus" => array(
				array(
					"quantity" => 1,
					"skuId" => $this->config['sku'],
				)
			),
			"zipCode" => $zip,
			"customerUuid" => null,
		));

		$headers = array(
			'Content-Type: application/json',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36',
		);

		$response = $this->http->post($url, $data, $headers);
		$result = json_decode($response, true);

		$stores = array();

		if (!is_array($result['storeAvailabilities'])) {
			throw new Exception("Unable to parse response: " . $response);
		}

		if (count($result['storeAvailabilities']) == 0) {
			$stores[] = array(
				'id' => $zip,
				'zip' => $zip,
				'avail' => 'Out of Stock',
				'address' => '',
				'city' => '',
				'state' => '',
				'location' => '',
				'phone' => '',
				'store' => '',
				'onhand_quantity' => null,
				'saleable_quantity' => null,
			);
		}

		foreach ($result['storeAvailabilities'] as $item) {
			$store = $item['store'];

			$stores[] = array(
				'id' => $store['address']['zipcode'],
				'zip' => $store['address']['zipcode'],
				'avail' => (isset($item['skuAvailabilities']) ? $item['skuAvailabilities'][0]['availabilityType'] : null),
				'address' => $store['address']['street'],
				'city' => $store['address']['city'],
				'state' => $store['address']['state'],
				'location' => $store['address']['street'],
				'phone' => $store['phone'],
				'store' => $store['name'],
				'onhand_quantity' => null,
				'saleable_quantity' => null,
			);
		}

		return $stores;
	}
}
