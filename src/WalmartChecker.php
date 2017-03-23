<?php

namespace NesStocks;

class WalmartChecker {

	public function __construct($config, $http = null) {
		$this->config = $config;
		$this->http = $http ?: new HttpClient();
	}

	public function getStoreInfo($storeId) {
		$headers = array(
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
			"Accept-encoding: gzip, deflate, sdch, br",
			"Accept-language: en-US,en;q=0.8",
		);

		$result = $this->http->get('https://www.walmart.com/store/' . $storeId, $headers);

		if ($this->http->lastHttpCode != 200) {
			return array();
		}

		if (!preg_match('/store: ({.+?}),[\r\n]/s', $result, $json)) {
			throw new \Exception("Unable to parse store JSON: " . $result);
		}

		$json = json_decode($json[1], true);

		return array(
			'id'      => $json['id'],
			'zip'     => $json['address']['postalCode'],
			'address' => $json['address']['address1'],
			'city'    => $json['address']['city'],
			'state'   => $json['address']['state'],
			'phone'   => isset($json['phone']) ? $json['phone'] : null,
			'store'   => $json['storeType']['displayName'],
		);
	}

	public function getProductId($sku) {
		$headers = array(
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
			"Accept-encoding: gzip, deflate, sdch, br",
			"Accept-language: en-US,en;q=0.8",
		);

		$result = $this->http->get('https://www.walmart.com/ip/' . $sku, $headers);

		if (!preg_match('/"product":"([^"]+)"/s', $result, $product)) {
			throw new \Exception("Unable to parse product ID: " . $result);
		}

		return $product[1];
	}

	public function getInventoryByZip($zip, $productID) {
		$data = $this->http->get('https://www.walmart.com/terra-firma/item/' . $productID . '/location/' . $zip . '?selected=true&wl13=');
		$result = json_decode($data, true);

		if (!$result) {
			throw new \Exception("Unable to parse response: " . $data);
		}

		if (!isset($result['payload'])) {
			return array(); // no stores
		}

		$stores = array();

		foreach ($result['payload']['offers'] as $offer) {
			if (!$offer['fulfillment']['pickupable']) {
				continue; // online/third party only
			}

			$price = null;

			if (isset($offer['pricesInfo']['priceMap']['CURRENT'])) {
				$price = $offer['pricesInfo']['priceMap']['CURRENT']['currencyUnitSymbol'];
				$price .= $offer['pricesInfo']['priceMap']['CURRENT']['price'];
			}

			foreach ($offer['fulfillment']['pickupOptions'] as $option) {
				$option['price'] = $price;
				$stores[] = $option;
			}
		}

		return $stores;
	}

	public function check($zip) {
		// get all stores by zip code
		$stores = $this->getInventoryByZip($zip, $this->config['productId']);
		$stocks = array();

		// loop through each store and get inventory
		foreach ($stores as $loc) {
			$avail = 'Out of Stock';
			$quantity = 0;
			$info = array();

			if ($loc['availability'] != 'NOT_AVAILABLE') {
				$avail = $loc['availability'];
				$quantity = $loc['urgentQuantity']; // actual quantity?
				$info = $this->getStoreInfo($loc['storeId']);
			}

			$stock = array(
				'id' => $loc['storeId'],
				'zip' => null,
				'avail' => $avail,
				'address' => $loc['storeAddress'],
				'city' => null,
				'state' => null,
				'location' => $loc['storeAddress'],
				'phone' => null,
				'store' => $loc['storeName'],
				'onhand_quantity' => null,
				'threshold_quantity' => null,
				'saleable_quantity' => $quantity,
			);

			if ($info) {
				$stock = array_merge($stock, $info);
			}

			$stocks[] = $stock;
		}

		return $stocks;
	}


	public function getStoresByZip($zip) {
		$data = $this->http->get('https://www.walmart.com/store/ajax/detail-navigation?location='. $zip);
		$result = json_decode($data, true);

		if ($result['status'] == 'error' && $result['message'] == 'no-stores') {
			return array();
		}

		if (!$result['payload'] || !$result['payload']['stores'] || !$result['payload']['stores'][0]) {
			throw new \Exception("Unable to parse location response: " . $data);
		}

		return $result['payload']['stores'];
	}

	public function searchStore($storeId, $deptId, $query) {
		$params = array(
			'searchQuery' => 'store=' . $storeId . '&size=24&dept=' . $deptId . '&query=' . $query
		);

		$search = $this->http->post_params('https://www.walmart.com/store/ajax/search', $params);
		$sr = json_decode($search, true);
		$sresult = json_decode($sr['searchResults'], true);

		if ($sr === null || $sresult === null) {
			throw new \Exception("Unable to parse inventory response: " . $search);
		}

		return $sresult['results'];
	}
}
