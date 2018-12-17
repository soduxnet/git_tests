<?php

$path = realpath(__DIR__ . '/../..');
require($path . '/vendor/autoload.php');

class Images {

	const API_KEY = "XXXXX";
	protected $abs_url = "/home/example/path";

	function __construct() {
	}

	// compress images - image optimization method
	// @param string $type
	// @param string $url
	// @param string $img_name
	// @param string $path
	// @param int $optimization
	// @param string $new_img_name
	/////////////////////////////////////
	public function compress($type, $url, $img_name, $path, $optimization=0, $new_img_name="") {
		\ShortPixel\setKey(self::API_KEY);

		if($type === "local") {

			if($optimization > 0 && $new_img_name === "") {
				\ShortPixel\fromFile($url)->wait(300)->optimize($optimization)->toFiles($path);
			} elseif($optimization > 0 && $new_img_name !== "") {
				\ShortPixel\fromFile($url)->optimize($optimization)->toFiles($path, $new_img_name);
			} else {
				\ShortPixel\fromFile($url)->wait(300)->toFiles($path);
			}

		} elseif($type === "external") {

		} elseif($type === "folder") {

			\ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
			$stop = false;
			$path = $this->abs_url . $path;
			$domain = "https://example.com/xp" . $path;

			if($optimization > 0 && $new_img_name === "") {
				while(!$stop) {
					$ret = \ShortPixel\fromFolder($path, $test)->wait(300)->optimize($optimization)->toFiles($path);
					if(count($ret->succeed) + count($ret->failed) + count($ret->same) + count($ret->pending) == 0) {
						$stop = true;
					}
					return true;
				}
			} elseif($optimization > 0 && $new_img_name !== "") {
				//\ShortPixel\fromFile($url)->optimize($optimization)->toFiles($path, $new_img_name);
			} else {
				while(!$stop) {
					$ret = \ShortPixel\fromFolder($path, $domain)->wait(300)->toFiles($path);
					if(count($ret->succeeded) + count($ret->failed) + count($ret->same) + count($ret->pending) == 0) {
						$stop = true;
					}
					return true;
				}
			}

		} else {
			// TODO: ADD ERROR MESSAGE
			// TODO: ADD CONSOLE ERROR MESSAGE
			// TODO: ADD LOG DATA
			return 0;
		}
	}

	// return image path string
	public function storeImage($params) {
		if(is_array($params)) {

			// 1. Get file
			$file = $params['file'];
			$pdir = $params['dir'];

			$img = preg_split("/[?]+/", $file);
			$name = basename($img[0]);
			list($txt, $ext) = explode(".", $name);

			// 2. If folder exists add it to folder, otherwise create new folder
			if(!file_exists("../products/$pdir"))
				mkdir("../products/$pdir", 0755);

			//check if the files are only image / document
			if($ext === "jpg" || $ext === "jpeg" || $ext === "png" || $ext === "gif"){

				$folder = "../products/$pdir/";

				if(file_exists("../products/$pdir/$name")) {
					echo "File already exists...";
					$new_string = "/products/$pdir/$name";
					return $new_string;
				} else {
					$upload = file_put_contents("../products/$pdir/$name",file_get_contents($img[0]));

					//check success
					if($upload) {
						$new_string = "/products/$pdir/$name";
						$path = $this->abs_url . "/products/$pdir";
						$absurl = $this->abs_url . $new_string;

						// compress image
						//self::compress("local",$absurl,$txt,$absurl,2);

						return $new_string;
					} else {
						echo "Please upload images only.";
						echo $pdir;
						// TODO: ADD ERROR MESSAGE
						// TODO: ADD CONSOLE ERROR MESSAGE
						// TODO: ADD LOG DATA
					}
				}
			}

		} else {

			// TODO: ADD ERROR MESSAGE
			// TODO: ADD CONSOLE ERROR MESSAGE
			// TODO: ADD LOG DATA
			return;

		}
	}

	// get image data
	public static function get_image_data ($file) {
		$content;

		if (function_exists('curl_version'))
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $file);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$content = curl_exec($curl);
			curl_close($curl);
		}
		else if (file_get_contents(__FILE__) && ini_get('allow_url_fopen'))
		{
			$content = file_get_contents($file);
		}
		else
		{
			// You have neither cUrl installed or allow_url_fopen activated. Please setup one of those!
			$content = 0;
		}

		return $content;
	}

}
