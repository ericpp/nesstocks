<?php

namespace NesStocks;

class HttpClient {
	public $debug = false;
	public $cookies = false;
	public $cookiejar = null;
	public $lastHttpCode = null;

	function __construct($debug = false) {
		$this->debug = $debug;
	}

	function __destruct() {
		if ($this->cookiejar) {
			unlink($this->cookiejar);
		}
	}

	function request($url, $data = null, $headers = null) {
		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if ($this->cookies) {
			if (!$this->cookiejar) {
				$this->cookiejar = tempnam(sys_get_temp_dir(), 'stocks');
			}

			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
		}

		// curl_setopt($ch, CURLOPT_ENCODING , 'gzip');

		if ($this->debug) {
			curl_setopt($ch, CURLOPT_VERBOSE, true);
		}

		if ($data) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		if (is_array($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		//execute post
		$result = curl_exec($ch);

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		// curl can return multiple sets of headers if following a redirect
		// save the last set of headers into $header
		$header_sets = explode("\r\n\r\n", trim(substr($result, 0, $header_size)));
		$header = array_pop($header_sets);

		$body = substr($result, $header_size);

		if (stripos($header, 'Content-Encoding: gzip') !== false) {
			$body = gzdecode($body);
		}

		// get the http result code
		$this->lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		//close connection
		curl_close($ch);

		return $body;
	}

	function get($url, $headers = null) {
		return $this->request($url, null, $headers);
	}

	function post($url, $data, $headers = null) {
		return $this->request($url, $data, $headers);
	}

	function post_params($url, $params, $headers = null) {
		return $this->request($url, http_build_query($params), $headers);
	}

	function post_json($url, $json, $headers = null) {
		if (is_array($json)) {
			$json = json_encode($json);
		}

		$hdr = array('Content-Type: application/json; charset=UTF-8');

		if (is_array($headers)) {
			$hdr = array_merge($hdr, $headers);
		}

		return $this->request($url, $json, $hdr);
	}
}
