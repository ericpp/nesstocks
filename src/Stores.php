<?php

namespace NesStocks;

class Stores {
	function __construct($filename) {
		$this->filename = $filename;
	}

	function get() {
		$stores = file($this->filename, FILE_IGNORE_NEW_LINES);

		return array_map(function ($store) {
			$item = explode(' | ', $store);
			return array($item[2], $item[3]);
		}, $stores);
	}
}

