<?php

namespace NesStocks;

class WalmartChecker {

	public function __construct($config, $http = null) {
		$this->config = $config;
		$this->http = $http ?: new HttpClient();
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

	public function getStoreInventory($storeId, $deptId, $sku) {
		$params = array(
			'searchQuery' => 'store=' . $storeId . '&size=24&dept=' . $deptId . '&query=' . $sku,
		);

		$search = $this->http->post_params('https://www.walmart.com/store/ajax/search', $params);
		$sr = json_decode($search, true);
		$sresult = json_decode($sr['searchResults'], true);

		if ($sr === null || $sresult === null) {
			throw new \Exception("Unable to parse inventory response: " . $search);
		}

		return $sresult['results'];
	}

	public function check($zip) {
		// get all stores by zip code
		$stores = $this->getStoresByZip($zip);
		$stocks = array();

		// loop through each store and get inventory
		foreach ($stores as $loc) {
			$avail = 'Out of Stock';
			$quantity = 0;

			// get store inventory by store id
			$inventory = $this->getStoreInventory($loc['id'], $this->config['deptId'], $this->config['sku']);

			if (count($inventory) > 0) {
				$avail = $inventory[0]['inventory']['status'];
				$quantity = $inventory[0]['inventory']['quantity'];
			}

			$stocks[] = array(
				'id' => $loc['address']['postalCode'],
				'zip' => $loc['address']['postalCode'],
				'avail' => $avail,
				'address' => $loc['address']['address1'],
				'city' => $loc['address']['city'],
				'state' => $loc['address']['state'],
				'location' => $loc['address']['address1'],
				'phone' => $loc['phone'],
				'store' => $loc['storeType']['displayName'],
				'onhand_quantity' => null,
				'threshold_quantity' => null,
				'saleable_quantity' => $quantity,
			);
		}

		return $stocks;
	}
}
