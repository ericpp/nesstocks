<?php

namespace NesStocks;

require_once("vendor/autoload.php");

$config = require("config/config.php");

// files to store store availability
$stocks_file = $config['stocks_file'];
$avails_file = $config['avails_file'];

// list of stores to check
$input_file = $config['stores_file'];

// resume file from a previous session
$resume_file = null;

// input file from command line
if (isset($_SERVER['argv'][1])) {
	$input_file = $_SERVER['argv'][1];
}

// resume file from command line
if (isset($_SERVER['argv'][2])) {
	$resume_file = $_SERVER['argv'][2];
}

// list of checked store ids and zip codes
$used_ids = $used_zips = array();

// list of store checker objects
$checkers = array();

// if resume file specified, load used_ids and used_zips with checked stores
if ($resume_file) {
	$stocks = file($resume_file, FILE_IGNORE_NEW_LINES);

	foreach ($stocks as $stock) {
		list($zipcode, $id, $s, $c, $zip, $retailer) = explode(' | ', $stock);
		print "loading " . $retailer . " " . $id . " " . $zipcode . "\n";
		$used_zips[$retailer][$zipcode] = $zipcode;
		$used_zips[$retailer][$zip] = $zip;
		$used_ids[$retailer][$id] = $id;
	}
}

// load the list of stores
$stores = new Stores($input_file);

// loop through all retailer zipcodes
foreach ($stores->get() as $zip_retailer) {
	list($zipcode, $retailer) = $zip_retailer;

	print "=== " . $retailer . " " . $zipcode . " ===\n";

	// skip if already checked
	if (isset($used_zips[$retailer]) && isset($used_zips[$retailer][$zipcode])) {
		print "already searched zip: " . $zipcode . "\n";
		continue;
	}

	// create checker object if not already created
	if (!isset($checkers[$retailer]) && isset($config['retailers'][$retailer])) {
		$cfg = $config['retailers'][$retailer];
		$cls = "\\NesStocks\\" . $cfg['checker'];
		$checkers[$retailer] = new $cls($cfg);
	}

	// skip if no checker defined for retailer
	if (!isset($checkers[$retailer])) {
		print "no checker for: " . $retailer . "\n";
		continue;
	}

	// check the store's availability
	$result = $checkers[$retailer]->check($zipcode);

	// loop through store results and save to avails/stocks files and used_ids/zips
	foreach ($result as $item) {
		$line = join(' | ', array(
			$item['state'],
			$item['city'],
			$item['zip'],
			$retailer,
			$item['address'],
			$item['phone'],
			$item['avail'],
			$item['onhand_quantity'],
			$item['saleable_quantity'],
		)) . "\n";

		// already checked this store's id
		if (isset($used_ids[$retailer][$item['id']])) {
			print "EXISTING STORE ID: ";
		}
		// already checked this store's zip code
		else if (isset($used_zips[$retailer][$item['zip']])) {
			print "EXISTING ZIP: ";
		}

		// show available if not out of stock and sold in store
		if ($item['avail'] != 'Out of Stock' && $item['avail'] != 'Not sold in this store') {
			print "AVAILABLE: ";
		}

		// print availability
		print $line;

		// save to avails file if available
		if (!isset($used_ids[$retailer][$item['id']]) && !isset($used_zips[$retailer][$item['zip']]) && $item['avail'] != 'Out of Stock' && $item['avail'] != 'Not sold in this store') {
			file_put_contents($avails_file, $line, FILE_APPEND);
		}

		// save to stocks file
		file_put_contents($stocks_file, $zipcode . ' | ' . $item['id'] . ' | ' . $line, FILE_APPEND);

		// mark the store's id and zip as checked
		$used_ids[$retailer][$item['id']] = $item['id'];
		$used_zips[$retailer][$item['zip']] = $item['zip'];
	}

	// mark the search zip code as checked
	$used_zips[$retailer][$zipcode] = $zipcode;
}

