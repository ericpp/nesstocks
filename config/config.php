<?php

return [
	'stocks_file' => 'stocks-' . date("YmdHi") . '.txt',
	'avails_file' => 'avails-' . date("YmdHi") . '.txt',
	'stores_file' => 'config/stores.txt',
	'retailers' => [
		'Target'  => [
			'checker' => 'TargetChecker',
			'sku'     => '207-29-0180',
		],
		'Walmart' => [
			'checker' => 'WalmartChecker',
			'sku'     => '54043501',
			'deptId'  => '2636',
			'productId' => '5VRQ98YB00LF',
		],
		/*
		'BestBuy' => [
			'checker' => 'BestBuyChecker',
			'sku'     => '5389100',
		],
		'ToysRUs' => [
			'checker' => 'ToysRUsChecker',
			'sku'     => [24607614, 106283536],
		],
		*/
	],
];
