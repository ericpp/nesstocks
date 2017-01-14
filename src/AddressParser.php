<?php

namespace NesStocks;

class AddressParser {
	function parse_city($address) {
		if (preg_match('/\b([^,]+), +([A-Z]{2})\b/', $address, $m)) {
			return $m[1]; // City, ST
		}

		if (preg_match('/\b(\w+ \w+) +([A-Z]{2})\b/', $address, $m)) {
			return $m[1]; // My City ST
		}

		if (preg_match('/\b(\w+) +([A-Z]{2})/', $address, $m)) {
			return $m[1]; // City ST
		}

		return null;
	}

	function parse_state($address) {
		if (preg_match('/\b([A-Z]{2})\b/', $address, $m)) {
			return $m[1];
		}

		return null;
	}

	function parse_zip($address) {
		if (preg_match('/\b[A-Z]{2} (\d{5})\b/', $address, $m)) {
			return $m[1]; // ST 11111
		}

		if (preg_match('/\b(\d{5})\b/', $address, $m)) {
			return $m[1]; // 11111
		}

		return null;
	}
}
