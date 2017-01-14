<?php

namespace NesStocks;

class ToysRUsChecker {
	private $cookieSet = false;

	public function __construct($config, $http = null, $parser = null) {
		$this->config = config;
		$this->http = $http ?: new HttpClient();
		$this->http->cookies = true;
		$this->parser = $parser ?: new AddressParser();
	}

	public function setCookie($productId) {
		if (!$this->cookieSet) {
			$this->http->get('http://www.toysrus.com/product/index.jsp?productId=' . $productId . '&cp=&parentPage=search');
		}

		$this->cookieSet = true;
	}

	public function check($zip) {
		list($skuId, $productId) = $this->config['sku'];

		$this->setCookie($productId);

		$url = 'http://www.toysrus.com/storefrontsearch/stores.jsp';

		$params = array(
			'skuId' => $skuId, // 24607614,
			'quantity' => 1,
			'postalCode' => $zip, // 55109,
			'latitude' => '',
			'longitude' => '',
			'productId' => $productId, // 106283536,
			'startIndexForPagination' => 0,
			'searchRadius' => 50,
			'pageType' => 'product',
			'ispu_or_sts' => 'null',
			'displayAllStoresFlag' => 'false',
			'displayAllStoreLink' => 'false',
		);

		$headers = array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36',
			'X-Prototype-Version: 1.6.0',
			'X-Requested-With: XMLHttpRequest',
		);

		$html = $this->http->post_params($url, $params, $headers);

		if (!preg_match_all('/class="storeName"><strong>([^<]+).*?class="storeDistance">([^<]+).*?class="storeAddress">([^<]+)(.*?)<\/tr>/s', $html, $match)) {
			throw new Exception('Unable to parse reponse: ' . $html);
		}

		$stores = array();

		foreach ($match[1] as $idx => $storeName) {
			$storeName = ucwords(strtolower($storeName));
			$distance = $match[2][$idx];
			$fullAddress = trim($match[3][$idx]);
			$extra = $match[4][$idx];

			if (!preg_match('/\[(\d+)\]/', $storeName, $storeNum)) {
				throw new Exception('Unable to parse store number: ' . $storeName);
			}

			$address = trim(ucwords(strtolower(explode("\n", trim($fullAddress))[0])), " ,\r\n");

			$city = ucwords(strtolower($this->parser->parse_city($fullAddress)));

			if (!$city) {
				throw new Exception("Unable to parse city: " . $fullAddress);
			}

			$state = $this->parser->parse_state($fullAddress);

			if (!$state) {
				throw new Exception("Unable to parse state: " . $fullAddress);
			}


			if (preg_match('/class="out-stock">([^>]+)<\/span>/s', $extra, $desc)) {
				$avail = 'Out of Stock';
				$availDesc = $desc[1];
			}
			else if (preg_match('/class="in-stock">([^>]+)<\/span>/s', $extra, $desc)) {
				$avail = 'In Stock';
				$availDesc = $desc[1];
			}
			else if (preg_match('/class="storepickup"><strong>([^>]+)<\/strong>/s', $extra, $desc)) {
				$avail = $desc[1];
				$availDesc = $desc[1];
			}
			else {
				$avail = 'Unknown';
			}

			$stores[] = array(
				'id' => $storeNum[1],
				'zip' => '', $this->parser->parse_zip($address),
				'avail' => $avail,
				'address' => $address,
				'city' => $city,
				'state' => $state,
				'location' => $address,
				'phone' => '',
				'store' => $storeName,
				'onhand_quantity' => null,
				'saleable_quantity' => null,
			);
		}

		return $stores;
	}
}
