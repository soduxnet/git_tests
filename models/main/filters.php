<?php

class Filters extends Products {

	// filter product listings
	// @param array|mixed $params
	/////////////////////////////////////
	public function filter($params) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		if(is_array($params) && isset($params)) {
			$cat 	= (isset($params['cat'])) ? $params['cat'] : '';
			$pub 	= (isset($params['pub'])) ? $params['pub'] : '';
			$sys 	= (isset($params['sys'])) ? $params['sys'] : '';
			$k 		= (isset($params['key'])) ? $params['key'] : '';
			$key 	= (isset($k) && strlen($k) > 0) ? explode(",", $k) : '';
			$url 	= $params['url'];

			$where 		= '';
			$type 		= '';
			$new_where 	= '';
			$new 		= false;
			$cols 		= "*";
			$child 		= ($cat === '' && $pub === '' && $sys === '') ? false : true;

			// keys
			if(is_array($key)):
				foreach($key as $choice) {
					if($choice === "new") {
						$new_where = "xp_products_digital_child.release_clp < (NOW() + INTERVAL 2 HOUR) AND xp_products_digital_child.release_clp >= (NOW() - INTERVAL 14 DAY)";
						$new = true;
					}
					//if($choice === "popular") {
					//	$where = '';
					//}
					if($choice === "dlc") {
						if(strlen($type) > 0) {
							$type .= ',DLC,PreOrder_DLC';
						} else {
							$type = 'DLC,PreOrder_DLC';
						}
					}
					if($choice === "ofertas") {
						if(strlen($where) > 0) {
							$where .= (strlen($type) > 0) ? 'AND xp_products_digital.discount_pct > 0
							AND xp_products_digital.discount_start_date <= (NOW())
							AND xp_products_digital.discount_end_date > (NOW() + INTERVAL 2 HOUR)' : 'AND Discount_PCT > 0
							AND Discount_Start_Date <= (NOW())
							AND Discount_End_Date > (NOW() + INTERVAL 2 HOUR)';
						} else {
							$where = (strlen($type) > 0) ? 'xp_products_digital.discount_pct > 0
							AND xp_products_digital.discount_start_date <= (NOW())
							AND xp_products_digital.discount_end_date < (NOW() + INTERVAL 2 HOUR)' : 'Discount_PCT > 0
							AND Discount_Start_Date <= (NOW())
							AND Discount_End_Date > (NOW() + INTERVAL 2 HOUR)';
						}
					}
					if($choice === "reserva") {
						if(strlen($type) > 0) {
							$type .= ',PreOrder,PreOrder_DLC';
						} else {
							$type = 'PreOrder,PreOrder_DLC';
						}
					}
					if($choice === "bundle") {
						if(strlen($type) > 0) {
							$type .= ',Bundle';
						} else {
							$type = 'Bundle';
						}
					}
					if($choice === "cheap") {
						if(strlen($where) > 0) {
							$where .= (strlen($type) > 0) ? 'AND xp_products_digital.price_clp <= 16500' : 'AND Price_CLP <= 16500';
						} else {
							$where = (strlen($type) > 0) ? 'xp_products_digital.price_clp <= 16500' : 'Price_CLP <= 16500';
						}
					}
				}
			endif;

			if(strlen($where) > 0 || strlen($type) > 0 || $new === true) {

				// do fulltext index check on column "type"
				$index = mysqli_query($conn, "SHOW INDEX FROM xp_products_digital WHERE Column_name = 'Type'");
				$col_exists = $index->fetch_assoc();

				if($col_exists === NULL && $col_exists['Column_name'] !== 'Type')
					mysqli_query($conn,"ALTER TABLE xp_products_digital ADD FULLTEXT(Type)");

				// types
				$and_type = ($child === false && $new === true) ? " AND MATCH(Type) AGAINST ('" . $type . "'
				IN BOOLEAN MODE)" : " AND MATCH(xp_products_digital.type) AGAINST ('" . $type . "' IN BOOLEAN MODE)";
				$in_type= ($child === false && $new === false) ? "MATCH(Type) AGAINST ('" . $type . "'
				IN BOOLEAN MODE)" : "MATCH(xp_products_digital.type) AGAINST ('" . $type . "' IN BOOLEAN MODE)";

				if((strlen($where) > 0) && strlen($type) === 0) {
					// parent only
					$parent = '';
					$parent .= (strlen($where) > 0) ? $where : '';

					echo $this->thumbnail(array("length"=>16,"custom_query"=>true,"where"=>$parent),'ES',$url);
				} else {
					// parent + child
					if($sys !== '') {
						$index = mysqli_query($conn, "SHOW INDEX FROM xp_products_digital WHERE Column_name = 'Platforms'");
						$col_exists = $index->fetch_assoc();

						if($col_exists === NULL && $col_exists['Column_name'] !== 'Platforms')
							mysqli_query($conn,"ALTER TABLE xp_products_digital ADD FULLTEXT(Platforms)");
					}

					$pub_str = ' AND xp_products_digital_child.publisher="' . $pub . '"';
					$sys_str = ' AND MATCH(xp_products_digital.platforms) AGAINST ('.$sys.' IN BOOLEAN MODE)';
					$cat_str = ' AND xp_products_digital_child.genres LIKE "%'.$cat.'%"';

					if($child === true || $new === true)
						$parent = 'SELECT '.$cols.' FROM xp_products_digital, xp_products_digital_child WHERE ';
					else
						$parent = 'SELECT '.$cols.' FROM xp_products_digital WHERE ';

					$parent .= (strlen($where) > 0) ? $where : '';

					if($type !== '')
						$parent .= ((strlen($type) > 0) && strlen($where) > 0) ? $and_type : $in_type;

					if($type !== '')
						$new_where = " AND " . $new_where;

					$parent .= ($new === true) ? $new_where : '';
					$parent .= ($pub !== '') ? $pub_str : '';
					$parent .= ($sys !== '') ? $sys_str : '';
					$parent .= ($cat !== '') ? $cat_str : '';
					$parent .= " AND Visible=1 AND SKU_CLP != ''"; // TODO: Apply SKU check based on geocode

					if($child === true || $new === true)
						$parent .= ' AND xp_products_digital.pid = xp_products_digital_child.parent_id';

					$parent .= " LIMIT 16";

					//var_dump($parent);

					$r = mysqli_query($conn, $parent);
					while($row = mysqli_fetch_assoc($r)) {
						$results[] = $row;
					}

					if(!empty($results)):
						return $this->thumbnail_results($results, $url);
					else:
						return '<h2 class="noresult">No se encontraron resultados, por favor seleccione algo diferente.</h2>';
					endif;

				}


			} elseif($cat != '' || $pub != '' || $sys != '') {

					$pub_str = 'xp_products_digital_child.publisher="' . $pub . '"';

					if($sys !== '') {
						$index = mysqli_query($conn, "SHOW INDEX FROM xp_products_digital WHERE Column_name = 'Platforms'");
						$col_exists = $index->fetch_assoc();

						if($col_exists === NULL && $col_exists['Column_name'] !== 'Platforms')
							mysqli_query($conn,"ALTER TABLE xp_products_digital ADD FULLTEXT(Platforms)");
					}

					if($pub != ''):
						$sys_str = ' AND MATCH(xp_products_digital.platforms) AGAINST ("'.$sys.'" IN BOOLEAN MODE)';
					else:
						$sys_str = 'MATCH(xp_products_digital.platforms) AGAINST ("'.$sys.'" IN BOOLEAN MODE)';
					endif;

					if($pub != '' || $sys != ''):
						$cat_str = ' AND xp_products_digital_child.genres LIKE "%'.$cat.'%"';
					else:
						$cat_str = 'xp_products_digital_child.genres LIKE "%'.$cat.'%"';
					endif;

					$parent = 'SELECT '.$cols.' FROM xp_products_digital, xp_products_digital_child WHERE ';

					$parent .= ($pub !== '') ? $pub_str : '';
					$parent .= ($sys !== '') ? $sys_str : '';
					$parent .= ($cat !== '') ? $cat_str : '';
					$parent .= " AND Visible=1 AND SKU_CLP != ''"; // TODO: Apply SKU check based on geocode

					$parent .= ' AND xp_products_digital.pid = xp_products_digital_child.parent_id';

					$parent .= " LIMIT 16";

					$r = mysqli_query($conn, $parent);
					while($row = mysqli_fetch_assoc($r)) {
						$results[] = $row;
					}

					if(!empty($results)):
						return $this->thumbnail_results($results, $url);
					else:
						return '<h2 class="noresult">No se encontraron resultados, por favor seleccione algo diferente.</h2>';
					endif;

			} else {

				// no one
				//return 0;
				return '<h2 class="noresult">No se encontraron resultados, por favor seleccione algo diferente.</h2>';
			}

		}

		$db->closeDB($conn);
	}


	// get game category filters
	// @param array|mixed $params
	/////////////////////////////////////
	public function game_cat_filters($params = array()) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		$pull 		= (isset($params['pull'])) ? $params['pull'] : 'all';
		$parent 	= (isset($params['parent'])) ? $params['parent'] : 0;
		$output 	= '';
		$data_pub 	= array();
		$data_cat 	= array();
		$results	= array();

		$query = "SELECT DISTINCT Publisher FROM xp_products_digital_child";
		$r = mysqli_query($conn, $query);

		$output .= '<select class="filter-select pub" data-select="pub">
			<option value="">Editores</option>';

		while($row = mysqli_fetch_assoc($r)) {
			$output .= '<option value="'.$row['Publisher'].'">'.$row['Publisher'].'</option>';
		}

		$output .= '</select>';

		$output .= '<select class="filter-select cat" data-select="cat">
			<option value="">Categorias</option>';

		$query = "SELECT PID,Cat_Name FROM xp_product_categories";
		$r = mysqli_query($conn, $query);

		while($row = mysqli_fetch_assoc($r)) {
			$output .= '<option value="'.$row['PID'].'">'.ucwords($row['Cat_Name']).'</option>';
		}

		$output .= '</select>';

		$output .= '
		<select class="filter-select sys" data-select="sys">
			<option value="">Plataforma</option>
			<option value="PC Windows">PC Windows</option>
			<option value="Steam">Steam</option>
			<option value="Mac OSX">Mac OSX</option>
			<option value="Linux">Linux</option>
		</select>
		';

		$output .= '<div class="clear-filter"><i class="far fa-times-square"></i> filtros claros</div>';

		return $output;

		$db->closeDB($conn);
	}

	// product search
	// @param string $item
	// @param string $url
	/////////////////////////////////////
	public function search($item,$url) {
		$db = new xpDatabase;
		$conn = $db->openDB(HOST, DB_NAME, DB_PASS, DB_USER);

		// TODO: Add a "Common_Tags" column >> nicknames, shortners, etc. for games
		//$index = mysqli_query($conn, "SHOW INDEX FROM xp_products_digital_child WHERE Column_name = 'Common_Tags'");
		//$col_exists = $index->fetch_assoc();

		//if($col_exists === NULL && $col_exists['Column_name'] !== 'Common_Tags')
		//	mysqli_query($conn,"ALTER TABLE xp_products_digital_child ADD FULLTEXT(Common_Tags)");

		$query = 'SELECT * FROM xp_products_digital WHERE Visible=1 AND Title LIKE "%'.$item.'%"';
		$r = mysqli_query($conn, $query);

		while($row = mysqli_fetch_assoc($r)) {
			$results[] = $row;
		}

		if(!empty($results)):
			return $this->thumbnail_results($results, $url);
		else:
			return '<h2 class="noresult">No se encontraron resultados, por favor seleccione algo diferente.</h2>';
		endif;

		$db->closeDB($conn);
	}

}
