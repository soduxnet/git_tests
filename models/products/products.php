<?php


class Products {

	// pull product
	// @param string $permalink
	// @param array|mixed $params
	/////////////////////////////////////
	public static function pull($permalink, $params = array()) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);
		$data = array();

		if(empty($params)) {
			$cols = "*";
		} else {
			$cols = $params['cols'];
		}

		$query = "SELECT $cols FROM xp_products_digital WHERE Visible=1 && permalink=?";
		if($stmt = $conn->prepare($query)) {
			$stmt->bind_param("s",$permalink);
			$stmt->execute();

			$r = $stmt->get_result();
			while($row = $r->fetch_assoc()) {
				$data = array(
					"id"				=>	$row['PID'],
					"type"				=>	$row['Type'],
					"title"				=>	$row['Title'],
					"sku_usd" 			=>	$row['SKU_USD'],
					"sku_clp"			=>	$row['SKU_CLP'],
					"preorder_sku"		=> 	$row['Prepurchase_SKU'],
					"price_usd" 		=>	$row['Price_USD'],
					"price_clp"			=>	$row['Price_CLP'],
					"discount_pct"		=> 	$row['Discount_PCT'],
					"discount_start"	=> 	$row['Discount_Start_Date'],
					"discount_end"		=> 	$row['Discount_End_Date'],
					"platforms"			=>	$row['Platforms']
				);
			}

			$cols = "Publisher,Developer,Genres,Trailers,Screenshots,System_REQ,
			Release_USD,Release_CLP,Purchase_Date_USD,Purchase_Date_CLP,
			Details_EN,Details_ES,Modos,Languages,Ratings,Legal,Linked_Products";

			$child_data = array();
			$child_query = "SELECT $cols FROM xp_products_digital_child WHERE parent_id='".$data['id']."'";
			$r = mysqli_query($conn,$child_query);
			while($row = $r->fetch_assoc()) {
				$child_data = array(
					"publisher"			=>	$row['Publisher'],
					"developer"			=>	$row['Developer'],
					"genres"			=>	$row['Genres'],
					"trailers"			=>	$row['Trailers'],
					"screenshots"		=>	$row['Screenshots'],
					"requirements"		=>	$row['System_REQ'],
					"release_usd"		=>	$row['Release_USD'],
					"release_clp"		=>	$row['Release_CLP'],
					"purchase_date_usd"	=>	$row['Purchase_Date_USD'],
					"purchase_date_clp"	=>	$row['Purchase_Date_CLP'],
					"details_en"		=>	$row['Details_EN'],
					"details_es"		=>	$row['Details_ES'],
					"modos"				=>	$row['Modos'],
					"lang"				=>	$row['Languages'],
					"ratings"			=>	$row['Ratings'],
					"legal"				=>	$row['Legal'],
					"linked"			=>	$row['Linked_Products']
				);
			}

			$product = array_merge($data, $child_data);

			$stmt->close();
			return $product;
		}

		$db->closeDB($conn);
	}

	// product thumbnail
	// @param array|mixed $params
	// @param string $lang
	// @param string $url
	/////////////////////////////////////
	public function thumbnail($params, $lang, $url) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);
		$data = array();

		if(is_array($params)) {
			$limit = (isset($params['length'])) ? $params['length'] : 8;
			$custom_query = (isset($params['custom_query'])) ? $params['custom_query'] : false;
			$results = array();
			//$rpid = rand(1,15);

			if($lang === "ES") {
				$cols = "Title,Type,SKU_CLP,Price_CLP,Permalink,KeyArt,Platforms,Permalink,Discount_PCT,Discount_Start_Date,Discount_End_Date";
				if($custom_query):
					// 3. TODO: add view count col to parent && discount > 0
					$where = $params['where'];
					$query = "SELECT $cols FROM xp_products_digital WHERE $where AND Visible=1 ORDER BY RAND() LIMIT $limit";
				else:
					$query = "SELECT $cols FROM xp_products_digital WHERE Visible=1 ORDER BY RAND() LIMIT $limit";
				endif;
			} else {
				$cols = "Title,Type,SKU_USD,Price_USD,Permalink,KeyArt,Platforms,Permalink,Discount_PCT,Discount_Start_Date,Discount_End_Date";
				$query = "SELECT $cols FROM xp_products_digital WHERE PID >= '".$rpid."' AND Visible=1 LIMIT $limit";
			}

			$r = mysqli_query($conn, $query);
			while($row = mysqli_fetch_assoc($r)) {
				$results[] = $row;
			}

			return $this->thumbnail_results($results, $url);

		} else {
			return 0;
		}
		$db->closeDB($conn);
	}

	// thumbnail results of query
	// @param query $results
	// @param string $url
	/////////////////////////////////////
	public function thumbnail_results($results, $url) {
		$thumbs = '';
		$today = date('Y-m-d H:i:s');

		foreach($results as $k => $v) {
			$title = $results[$k]['Title'];
			$art = $url . $results[$k]['KeyArt'];
			$sku = $results[$k]['SKU_CLP'];
			$link = $url . '/juego/' . $results[$k]['Permalink'];
			$discount = $results[$k]['Discount_PCT'];
			$discount_start = $results[$k]['Discount_Start_Date'];
			$discount_end = $results[$k]['Discount_End_Date'];
			$discount_ready = ($discount > 0 && $discount_start <= $today && $discount_end > $today) ? true : false;

			$thumbs .= '<div class="col-md-3 col-sm-6 thumbnail">';

			//list($width, $height, $type, $attr) = getimagesize($art);
			//$thumbs .= '<div class="thumbimg">';
			//$thumbs .= '<a href="'.$link.'"><img src="'.$art.'" alt="'.$title.'" /></a>';
			$thumbs .= '<div class="thumbimg" style="background: url('.$art.') 50% 50% no-repeat; background-size: cover; margin-bottom: 8px;" >';

			if($discount_ready) {
				$postsku = 'post-'. $sku;
				$thumbs .= '<div class="timer-meta saletimer '.$postsku.'" data-countdown="'.$discount_end.'" data-id="'.$sku.'">
				<i class="far fa-clock"></i> 00d 00:00:00</div>';
			} else {
				$discount = 0;
			}

			$type = $results[$k]['Type'];
			if($type === "PreOrder" || $type === "PreOrder_DLC"):
				$thumbs .= '<div class="game-type reserva">Reserva</div>';
			elseif($type === "DLC"):
				$thumbs .= '<div class="game-type dlc">DLC</div>';
			elseif($type === "Bundle"):
				$thumbs .= '<div class="game-type bundle">Haz</div>';
			elseif($type === "Currency"):
				$thumbs .= '<div class="game-type currency">Moneda</div>';
			endif;

			$thumbs .= '<div class="fav-meta"><i class="fas fa-shopping-cart icart cart-add" data-sku="'.$sku.'"></i>';
			$thumbs .= '<i class="fas fa-heart"></i></div>';
			$thumbs .= '<a href="'.$link.'" class="img-link"></a>';
			$thumbs .= '</div>';

			$thumbs .= '<div class="platform-meta">';

			$platforms = unserialize($results[$k]['Platforms']);
			foreach($platforms as $platform) {
				if($platform === "PC Windows"):
					$thumbs .= '<i class="fab fa-windows" title="Windows"></i>';
				elseif($platform === "Linux"):
					$thumbs .= '<i class="fab fa-linux" title="Linux"></i>';
				elseif($platform === "Mac" || $platform === "Mac OSX"):
					$thumbs .= '<i class="fab fa-apple" title="Mac"></i>';
				elseif($platform === "Steam"):
					$thumbs .= '<i class="fab fa-steam" title="Steam"></i>';
				endif;
			}

			$thumbs .= '</div>';

			$thumbs .= '<h2><a href="'.$link.'">'.$title.'</a></h2>';
			$thumbs .= '<div class="cart-meta cart-add" data-sku="'.$sku.'">';

			// TODO: Do a LANG / GeoCheck
			$price = $results[$k]['Price_CLP'];
			$tax = 19;

			$msrp_prediscount = $this->discount_msrp(0, $price, $tax);
			$msrp = $this->discount_msrp($discount, $price, $tax);

			if($discount_ready) {
				$thumbs .= '<div class="discount">-'.$discount.'%</div>';
				$thumbs .= '<div class="add-to-cart discounted">';
				$thumbs .= '<span>$'.number_format($msrp_prediscount, 0, '', '.').'</span><strong>$'.number_format($msrp, 0, '', '.').'</strong>';
				$thumbs .= '</div>';
			} else {
				$thumbs .= '<div class="add-to-cart">';
				$thumbs .= '$' . number_format($msrp, 0, '', '.');
				$thumbs .= '</div>';
			}

			$thumbs .= '</div>';

			$thumbs .= '</div>';
		}

		return $thumbs;
	}

	// linked products
	// array of product ids (int)
	// @param array $product_ids
	// @param string $lang
	// @param string $url
	/////////////////////////////////////
	public function linkedproducts($product_ids, $lang, $url) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		if(is_array($product_ids)) {
			$ids = implode(",",$product_ids);
			$results = array();

			if($lang === "ES") {
				$cols = "Title,SKU_CLP,Price_CLP,Permalink,Keyart,Discount_PCT,Discount_Start_Date,Discount_End_Date";
				$query = 'SELECT ' . $cols . ' FROM xp_products_digital WHERE PID IN ('.$ids.') AND Visible=1';
			} else {
				$cols = "Title,SKU_USD,Price_USD,Permalink,Keyart,Discount_PCT,Discount_Start_Date,Discount_End_Date";
				$query = 'SELECT ' . $cols . ' FROM xp_products_digital WHERE PID IN ('.$ids.') AND Visible=1';
			}

			$r = mysqli_query($conn, $query);
			while($row = mysqli_fetch_assoc($r)) {
				$results[] = $row;
			}

			$data = '';
			$today = date('Y-m-d H:i:s');
			foreach($results as $k => $v) {
				$perma = $url . "/juego/" . $results[$k]['Permalink'];
				$img = $url .  $results[$k]['Keyart'];
				$game_discount = (int)$results[$k]['Discount_PCT'];
				$discount_ready = ($game_discount > 0 && $results[$k]['Discount_Start_Date'] <= $today && $results[$k]['Discount_End_Date'] > $today) ? true : false;

				$price = $results[$k]['Price_CLP'];
				$tax = 19;

				// TODO: Lang / GeoCheck
				if($discount_ready):
					$msrp = $this->discount_msrp($game_discount, $price, $tax);
				else:
					$msrp = $this->discount_msrp(0, $price, $tax);
				endif;

				$data .= '<a href="'.$perma.'">';
				$data .= '<img src="'.$img.'" alt="'.$results[$k]['Title'].'" />';
				$data .= '<h6>'.$results[$k]['Title'].'</h6>';
				$data .= '<span class="price">$'.number_format($msrp, 0, '', '.').'</span>';

				if($discount_ready)
					$data .= '<span class="dctpct">-'.$game_discount.'%</span>';

				$data .= '<div class="clear"></div>';
				$data .= '</a>';
			}

			return $data;

		} else {
			return 0;
		}

		$db->closeDB($conn);
	}

	// product genres
	// @param string|int $cat_ids
	// @param string $lang
	// @param string $url
	/////////////////////////////////////
	public static function genres($cat_ids, $lang, $url) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		if(isset($cat_ids) && $cat_ids !== '') {
			$genres = unserialize($cat_ids);
			$cats = implode(",",$genres);
			$results = array();

			$query = 'SELECT Cat_Name FROM xp_product_categories WHERE PID IN ('.$cats.')';
			$r = mysqli_query($conn, $query);

			while($row = mysqli_fetch_assoc($r)) {
				$results[] = $row;
			}

			$data = '';
			$j = 0;
			foreach($results as $result) {
				$search = $url . '/juegos?cat=' . $result['Cat_Name'];
				if($j > 0):
					$data .= ', <a href="'.$search.'">'.$result['Cat_Name'].'</a>';
				else:
					$data .= '<a href="'.$search.'">'.$result['Cat_Name'].'</a>';
				endif;

				$j++;
			}
			return $data;

		} else {
			return 0;
		}

		$db->closeDB($conn);
	}

	// featured products slider
	// @param array $params
	// @param string $lang
	// @param string $url
	/////////////////////////////////////
	public function featured($params, $lang, $url) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		if(is_array($params) && isset($params)) {
			$limit = $params['limit'];
			$ids = implode(",",$params['ids']);

			if($lang === "ES"):
				$cols = "Title,SKU_CLP,Price_CLP,Keyart,Discount_PCT,Discount_Start_Date,Discount_End_Date,Permalink";
			else:
				$cols = "Title,SKU_USD,Price_USD,Keyart,Discount_PCT,Discount_Start_Date,Discount_End_Date,Permalink";
			endif;

			$query = 'SELECT '.$cols.' FROM xp_products_digital WHERE PID IN ('.$ids.') AND Visible=1 ORDER BY RAND() LIMIT ' . $limit;
			$r = mysqli_query($conn, $query);

			while($row = mysqli_fetch_assoc($r)) {
				$results[] = $row;
			}

			$slider = '<div class="xpsliders">';

			$today = date('Y-m-d H:i:s');
			foreach($results as $k => $v) {
				$title 		= $results[$k]['Title'];
				$sku		= $results[$k]['SKU_CLP'];
				$img 		= $url . $results[$k]['Keyart'];
				$link 		= $url . '/juego/' . $results[$k]['Permalink'];
				$discount 	= $results[$k]['Discount_PCT'];
				$discount_start = $results[$k]['Discount_Start_Date'];
				$discount_end = $results[$k]['Discount_End_Date'];
				$discount_ready = ($discount > 0 && $discount_start <= $today && $discount_end > $today) ? true : false;

				$slider .= '<div>';

				$slider .= '<a class="imgblock" href="'.$link.'"><img src="'.$img.'" alt="'.$title.'"/></a>';

				if($discount_ready) {
					$postsku = 'post-'. $sku;
					$slider .= '<div class="discount-meta">';
					$slider .= '<div class="discount">-'.$discount.'%</div>';
					$slider .= '<div class="timer-meta saletimer '.$postsku.'" data-countdown="'.$discount_end.'" data-id="'.$sku.'">
					<i class="far fa-clock"></i> 00d 00:00:00</div>';
					$slider .= '</div>';
				} else {
					$discount = 0;
				}

				if($lang === "ES" || $lang === "es") {
					$price = $results[$k]['Price_CLP'];
					$tax = 19;
					$msrp = $this->discount_msrp($discount, $price, $tax);
				} else {
					$price = $results[$k]['Price_USD'];
					$msrp = $this->discount_msrp($discount, $price, $tax);
				}

				$slider .= '<div class="slider-spine cart-add" data-sku="'.$sku.'">';
				$slider .= '<h2>'.$title.'</h2>';
				$slider .= '<div class="price">$'.number_format($msrp, 0, '', '.').'</div>';
				$slider .= '</div>';

				$slider .= '</div>';
			}

			$slider .= '</div>';

			return $slider;
		}

		$db->closeDB($conn);

	}

	// discount msrp
	// method handles price conversions, taxes, etc.
	// @param int $discount_pct
	// @param int|float $price
	// @param int|float $tax
	/////////////////////////////////////
	public function discount_msrp($discount_pct, $price, $tax) {
		if($discount_pct > 0) {
			$n = (int) $discount_pct;
			$dct = intval($price * ($n/100));
			$price = $price - $dct;
		}

		$tax = intval($price * ($tax/100));
		$msrp = intval($price + $tax);

		return $msrp;
	}

}
