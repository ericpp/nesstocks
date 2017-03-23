<?php

namespace NesStocks;

class TargetChecker {

	function __construct($config, $http = null, $parser = null) {
		$this->config = $config;
		$this->accessKey = null;

		if (isset($config['key'])) {
			$this->accessKey = $config['key'];
		}

		$this->http = $http ?: new HttpClient();
		$this->parser = $parser ?: new AddressParser();
	}

	function getAccessKey() {
		// return if defined
		if (isset($this->accessKey)) {
			return $this->accessKey;
		}

		// try to scrape the access key from target.com
		$result = $this->http->get('http://www.target.com/');

		if (preg_match('/accesskey\s*:\s*"([^"]+)"/', $result, $keys)) {
			$this->accessKey = $keys[1];
		}

		// complain if can't find accessKey
		if (!isset($this->accessKey)) {
			throw new \Exception("Unable to find target.com accessKey");
		}

		return $this->accessKey;
	}

	function getStorePrice($storeId) {
		$url = 'http://redsky.target.com/v2/pdp/dpci/' . $this->config['sku'] . '?storeId=' . $storeId;
		$response = $this->http->get($url);
		$result = json_decode($response, true);

		if (isset($result['product']['price']['offerPrice'])) {
			return $result['product']['price']['offerPrice']['formattedPrice'];
		}

		return null;
	}

	function check($zip) {
		$url = 'http://api.target.com/products/v3/saleable_quantity_by_location?key=' . $this->getAccessKey();
		$data = json_encode(array(
			"products" => array(
				array(
					"product_id" => $this->config['sku'],
					"desired_quantity" => "1"
				)
			),
			"nearby" => $zip,
			"radius" => "25",
			"multichannel_options" => array(
				array("multichannel_option" => "none")
			)
		));

		$response = $this->http->post_json($url, $data);
		$result = json_decode($response, true);

		$stores = array();

		if (!$result['products'] || !$result['products'][0] || !is_array($result['products'][0]['stores'])) {
			throw new \Exception("Unable to parse response: " . $response);
		}

		foreach ($result['products'][0]['stores'] as $item) {
			$store = $item['store_address'];
			$fmt = explode(', ', $item['formatted_store_address']);
			$len = count($fmt);

			$price = null;

			if ($item['onhand_quantity'] > 0) {
				$price = $this->getStorePrice($item['store_id']);
			}

			$stores[] = array(
				'id' => $item['store_id'],
				'zip' => $this->parser->parse_zip($fmt[$len-1]),
				'avail' => $item['availability_status'],
				'address' => join(', ', array_slice($fmt, 0, $len-2)),
				'city' => $fmt[$len-2],
				'state' => $this->parser->parse_state($fmt[$len-1]),
				'location' => $store,
				'phone' => $item['store_main_phone'],
				'store' => $item['store_name'],
				'onhand_quantity' => $item['onhand_quantity'],
				// 'threshold_quantity' => $item['threshold_quantity'],
				'saleable_quantity' => $item['saleable_quantity'],
				'price' => $price,
			);
		}

		return $stores;
	}
}
