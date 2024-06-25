<?php
	include "config.php";

	require_once "vendor/autoload.php";

	use lfkeitel\phptotp\{Base32,Totp};

	$GLOBALS["names"] = [];

	function transfer($name) {
		$data = [
			"address" => $GLOBALS["hnsAddress"]
		];
		$result = request(true, "https://namebase.io/api/domains/".$name."/transfer", $data);
		if (@$result["success"]) {
			echo $name.": SUCCESS\n";

			$index = array_search($name, $GLOBALS["names"]);
			unset($GLOBALS["names"][$index]);
		}
		else {
			echo $name.": FAIL - ".$result["message"]."\n";
			die();
		}
	}

	function request($isPost, $url, $data=[]) {
		$post = json_encode($data);

		$headers = [
			"Content-Type: application/json",
			"Content-Length: ".strlen($post),
		];
		if (@$GLOBALS["2faSecret"]) {
			$headers[] = "x-totp-tokens: ".otp();
		}

		$cookies = "namebase-main=".@$GLOBALS["namebaseCookie"].";";

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_POST, $isPost);
		if ($isPost) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_COOKIE, $cookies);
		$result = curl_exec($curl);
		curl_close($curl);
		return json_decode($result, true);
	}

	function otp() {
		$key = $GLOBALS["2faSecret"];
		$secret = Base32::decode($key);
		$code = (new Totp())->GenerateToken($secret);
		return $code;
	}

	function getNames() {
		$i = 0;

		again:
		$result = request(false, "https://www.namebase.io/api/user/domains/".$GLOBALS["type"]."/".$i."?limit=100");
		//var_dump($result);

		if (@$result["success"]) {
			if (@$result["domains"]) {
				foreach ($result["domains"] as $key => $info) {
					$GLOBALS["names"][] = $info["name"];
				}
			}
			else {
				die("Finished\n");
			}
		}
		else {
			die("Failure, your namebase-main cookie is probably wrong\n");
		}

		startTransfers();
		goto again;
	}

	function startTransfers() {
		$GLOBALS["names"] = array_unique($GLOBALS["names"]);
		foreach ($GLOBALS["names"] as $name) {
			transfer($name);
		}
	}

	getNames();
?>