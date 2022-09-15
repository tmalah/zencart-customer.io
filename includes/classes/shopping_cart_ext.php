<?php
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

/*
* TODO: Store and restore basket for bundles
*/

class shoppingCartExt extends shoppingCart {

  	function restore_contents() {
		global $db;
		if (!$_SESSION['customer_id']) return false;
		$this->notify('NOTIFIER_CART_RESTORE_CONTENTS_START');
		// insert current cart contents in database
		if (is_array($this->contents)) {
		  reset($this->contents);
		  while (list($products_id, ) = each($this->contents)) {
			//          $products_id = urldecode($products_id);
			$qty = $this->contents[$products_id]['qty'];
			$product_query = "select products_id
								from " . TABLE_CUSTOMERS_BASKET . "
								where customers_id = '" . (int)$_SESSION['customer_id'] . "'
								and products_id = '" . zen_db_input($products_id) . "'";

			$product = $db->Execute($product_query);

			if ($product->RecordCount()<=0) {
			  $sql = "insert into " . TABLE_CUSTOMERS_BASKET . "
									(customers_id, products_id, customers_basket_quantity,
									 customers_basket_date_added)
									 values ('" . (int)$_SESSION['customer_id'] . "', '" . zen_db_input($products_id) . "', '" .
			  $qty . "', '" . date('Ymd') . "')";

			  $db->Execute($sql);

			  if (isset($this->contents[$products_id]['attributes'])) {
				reset($this->contents[$products_id]['attributes']);
				while (list($option, $value) = each($this->contents[$products_id]['attributes'])) {

				  //clr 031714 udate query to include attribute value. This is needed for text attributes.
				  $attr_value = $this->contents[$products_id]['attributes_values'][$option];
				  //                zen_db_query("insert into " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " (customers_id, products_id, products_options_id, products_options_value_id, products_options_value_text) values ('" . (int)$customer_id . "', '" . zen_db_input($products_id) . "', '" . (int)$option . "', '" . (int)$value . "', '" . zen_db_input($attr_value) . "')");
				  $products_options_sort_order= zen_get_attributes_options_sort_order(zen_get_prid($products_id), $option, $value);
				  if ($attr_value) {
					$attr_value = zen_db_input($attr_value);
				  }
				  $sql = "insert into " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . "
										(customers_id, products_id, products_options_id,
										 products_options_value_id, products_options_value_text, products_options_sort_order)
										 values ('" . (int)$_SESSION['customer_id'] . "', '" . zen_db_input($products_id) . "', '" .
				  $option . "', '" . $value . "', '" . $attr_value . "', '" . $products_options_sort_order . "')";

				  $db->Execute($sql);
				}
			  }
			  if (isset($this->contents[$products_id]['products'])) {
  			    $sql = "insert into " . TABLE_CUSTOMERS_BASKET_BUNDLE . "
										(customers_id, products_id, products)
										 values ('" . (int)$_SESSION['customer_id'] . "', '" . zen_db_input($products_id) . "', '" . serialize($this->contents[$products_id]['products']) . "')";
				$db->Execute($sql);
			  }
			} else {
			  $sql = "update " . TABLE_CUSTOMERS_BASKET . "
						set customers_basket_quantity = '" . $qty . "'
						where customers_id = '" . (int)$_SESSION['customer_id'] . "'
						and products_id = '" . zen_db_input($products_id) . "'";

			  $db->Execute($sql);

			}
		  }
		}

		// reset per-session cart contents, but not the database contents
		$this->reset(false);

		$products_query = "select products_id, customers_basket_quantity
							 from " . TABLE_CUSTOMERS_BASKET . "
							 where customers_id = '" . (int)$_SESSION['customer_id'] . "'";

		$products = $db->Execute($products_query);

		while (!$products->EOF) {
		  $this->contents[$products->fields['products_id']] = array('qty' => $products->fields['customers_basket_quantity']);
		  // attributes
		  // set contents in sort order

		  //CLR 020606 update query to pull attribute value_text. This is needed for text attributes.
		  //        $attributes_query = zen_db_query("select products_options_id, products_options_value_id, products_options_value_text from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . zen_db_input($products['products_id']) . "'");

		  $order_by = ' order by LPAD(products_options_sort_order,11,"0")';

		  $attributes = $db->Execute("select products_options_id, products_options_value_id, products_options_value_text
								 from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . "
								 where customers_id = '" . (int)$_SESSION['customer_id'] . "'
								 and products_id = '" . zen_db_input($products->fields['products_id']) . "' " . $order_by);

		  while (!$attributes->EOF) {
			$this->contents[$products->fields['products_id']]['attributes'][$attributes->fields['products_options_id']] = $attributes->fields['products_options_value_id'];
			//CLR 020606 if text attribute, then set additional information
			if ($attributes->fields['products_options_value_id'] == PRODUCTS_OPTIONS_VALUES_TEXT_ID) {
			  $this->contents[$products->fields['products_id']]['attributes_values'][$attributes->fields['products_options_id']] = $attributes->fields['products_options_value_text'];
			}
			$attributes->MoveNext();
		  }

		  $bundledProduct = $db->Execute("SELECT products
			  			  	  	            FROM " . TABLE_CUSTOMERS_BASKET_BUNDLE . "
								           WHERE customers_id = " . (int)$_SESSION['customer_id']."
										     AND products_id = '" . zen_db_input($products->fields['products_id']) . "'");

		   if (!$bundledProduct->EOF) {
			   $this->contents[$products->fields['products_id']]['products'] = unserialize($bundledProduct->fields['products']);
		   }

		  $products->MoveNext();
		}
		$this->cartID = $this->generate_cart_id();
		$this->notify('NOTIFIER_CART_RESTORE_CONTENTS_END');
		$this->cleanup();
  	}

	function reset($reset_database = false) {
		global $db;

		if (isset($_SESSION['customer_id']) && ($reset_database == true)) {
		  $sql = "delete from " . TABLE_CUSTOMERS_BASKET_BUNDLE . " where customers_id = '" . (int)$_SESSION['customer_id'] . "'";
		  $db->Execute($sql);
		}
		parent::reset($reset_database);
	}

	function cleanup() {
		global $db;
		reset($this->contents);
		while (list($key,) = each($this->contents)) {
		  if (!isset($this->contents[$key]['qty']) || $this->contents[$key]['qty'] <= 0) {
			unset($this->contents[$key]);
			// remove from database
			if (isset($_SESSION['customer_id'])) {
			  $sql = "delete from " . TABLE_CUSTOMERS_BASKET_BUNDLE . "
						where customers_id = '" . (int)$_SESSION['customer_id'] . "'
						and products_id = '" . $key . "'";

			  $db->Execute($sql);
			}
		  }
		}
		parent::cleanup();
	}

	function remove($products_id) {
		global $db;
		parent::remove($products_id);
		if ($_SESSION['customer_id']) {
		  $sql = "delete from " . TABLE_CUSTOMERS_BASKET_BUNDLE . "
					where customers_id = '" . (int)$_SESSION['customer_id'] . "'
					and products_id = '" . zen_db_input($products_id) . "'";
		  $db->Execute($sql);
		}
	}

	function add_cart_bundle($postData) {
		global $db;

		$prid = $postData['bundle_id'].':'.md5(rand());
		 $this->add_cart($prid);


		 if ($this->contents[$prid]) {
			 /*
			 $this->contents[$prid]['products'] = array();
			 foreach($postData['products_id'] as $id => $product) {
				 array_push($this->contents[$prid]['products'], $product);
			 }
			 */
			 $this->contents[$prid]['products'] = $postData['products_id'];
		 }


		if (isset($_SESSION['customer_id'])) {
			$sql = "insert into " . TABLE_CUSTOMERS_BASKET_BUNDLE . "
								  (customers_id, products_id, products)
								  values ('" . (int)$_SESSION['customer_id'] . "', '" . zen_db_input($prid) . "', '" . serialize($postData['products_id']) . "')";
			$db->Execute($sql);
		 }
		 
		 $this->cartID = $this->generate_cart_id();

        $this->notify('NOTIFIER_CART_ADD_CART_END');
    
        $ec_analytics = new ec_analytics();
        $ec_analytics->generate_info('NOTIFIER_CART_ADD_CART_END');

	}
    
    
	function actionUpdateProduct($goto, $parameters) {
		global $messageStack;

		for ($i=0, $n=sizeof($_POST['products_id']); $i<$n; $i++) {
			  $adjust_max= 'false';
			  if (!is_numeric($_POST['cart_quantity'][$i]) || $_POST['cart_quantity'][$i] < 0) {
       			$messageStack->add_session('header', ERROR_CORRECTIONS_HEADING . ERROR_PRODUCT_QUANTITY_UNITS_SHOPPING_CART . zen_get_products_name($_POST['products_id'][$i]) . ' ' . PRODUCTS_ORDER_QTY_TEXT . $_POST['cart_quantity'][$i], 'error');
			    continue;
      	      }
			  if ( in_array($_POST['products_id'][$i], (is_array($_POST['cart_delete']) ? $_POST['cart_delete'] : array())) or $_POST['cart_quantity'][$i]==0) {
				$this->remove($_POST['products_id'][$i]);
			  } else {
					$add_max = zen_get_products_quantity_order_max($_POST['products_id'][$i]);
					$cart_qty = $this->in_cart_mixed($_POST['products_id']);
					$new_qty = $_POST['cart_quantity'][$i];

					//echo 'I SEE actionUpdateProduct: ' . $_POST['products_id'] . ' ' . $_POST['products_id'][$i] . '<br>';
		 			$new_qty = $this->adjust_quantity($new_qty, $_POST['products_id'][$i], 'shopping_cart');

				   if (zen_get_info_page($_POST['products_id'][$i]) == 'product_bundle_info') {
						//die('I see Update Cart: ' . $_POST['products_id'][$i] . ' add qty: ' . $add_max . ' - cart qty: ' . $cart_qty . ' - newqty: ' . $new_qty);
						if (($add_max == 1 and $cart_qty == 1)) {
				  			// do not add
				  			$adjust_max= 'true';
						} else {
				  			// adjust quantity if needed
				  			if (($new_qty + $cart_qty > $add_max) and $add_max != 0) {
								$adjust_max= 'true';
								$new_qty = $add_max - $cart_qty;
				  			}
							$this->contents[$_POST['products_id'][$i]]['qty'] = (float)$new_qty;
						}
				   } else {
						//die('I see Update Cart: ' . $_POST['products_id'][$i] . ' add qty: ' . $add_max . ' - cart qty: ' . $cart_qty . ' - newqty: ' . $new_qty);
						if (($add_max == 1 and $cart_qty == 1)) {
				  			// do not add
				  			$adjust_max= 'true';
						} else {
				  			// adjust quantity if needed
				  			if (($new_qty + $cart_qty > $add_max) and $add_max != 0) {
								$adjust_max= 'true';
								$new_qty = $add_max - $cart_qty;
				  			}
				  			$attributes = ($_POST['id'][$_POST['products_id'][$i]]) ? $_POST['id'][$_POST['products_id'][$i]] : '';
				  			$this->add_cart($_POST['products_id'][$i], $new_qty, $attributes, false);
						}
					}
					if ($adjust_max == 'true') {
						$messageStack->add_session('shopping_cart', ERROR_MAXIMUM_QTY . zen_get_products_name($_POST['products_id'][$i]), 'caution');
					} else {
						if (DISPLAY_CART == 'false' && $_GET['main_page'] != FILENAME_SHOPPING_CART) {
							$messageStack->add_session('header', SUCCESS_ADDED_TO_CART_PRODUCT, 'success');
					}
			  	}
		   }
		}
        
            //  send cart=0 to customer.io
    if (isset($_SESSION['customer_id'])) {
        if ($this->count_contents() == 0) {
            $info = array('has_cart' => 0);
        } else {
            
            $info = array('has_cart' => 1,
                          'cart_content' => $this->get_cart_html(),
                          'cart_added' => time());
        }
      
        
        $info['action'] = 'actionUpdateProduct';
        include_once(DIR_WS_CLASSES.'customerIO.php');
        $customerio = new CustomerIO();
        $customerio->EditUser((int)$_SESSION['customer_id'], '', $info);
    }
        
		zen_redirect(zen_href_link($goto, zen_get_all_get_params($parameters)));
	}
    
 
    
	function get_products($check_for_valid_cart = false) { 
		 global $db, $currencies;
		 $products = parent::get_products($check_for_valid_cart);

		 foreach($products as $i => $product) {
			 $prod_id = key($product);
			 if (zen_get_info_page($product['id']) == 'product_bundle_info') {

				//echo "<!-- ".print_r($product,true)." -->";

				$products[$i]['products'] = $this->contents[$product['id']]['products'];

				$bundle = $db->Execute("select products_id, products_price, products_tax_class_id, products_weight,
												products_priced_by_attribute, product_is_always_free_shipping, products_discount_type, products_discount_type_from,
												products_virtual, products_model
										  from " . TABLE_PRODUCTS . "
										 where products_id = '" . (int)$product['id'] . "'");
				if (!$bundle->EOF) {
					foreach($products[$i]['products'] as $id => $productInBundle) {
						$product_id = key($productInBundle);

						$bundleProduct = $db->Execute("SELECT IF(b.products_price != '',b.products_price,p.products_price) products_price,
													   b.options, b.group_name,
													   p.products_id, p.products_tax_class_id, p.products_weight,
													   p.products_priced_by_attribute, p.product_is_always_free_shipping, p.products_discount_type, p.products_discount_type_from,
													   p.products_virtual, p.products_model,
													   pd.products_name, p.products_image
												  FROM ".TABLE_BUNDLES." b, ".TABLE_PRODUCTS." p, " . TABLE_PRODUCTS_DESCRIPTION . " pd
												 WHERE b.bundles_id = ".(int)$product['id']."
												   AND b.id          = ".(int)$id."
												   AND b.products_id = ".(int)$product_id."
												   AND b.products_id = p.products_id
												   AND p.products_id = pd.products_id
												   AND pd.language_id = '" . (int)$_SESSION['languages_id'] . "'");

						if (!$bundleProduct->EOF) {
							$products_tax = zen_get_tax_rate($bundleProduct->fields['products_tax_class_id']);
							$options = unserialize($bundleProduct->fields['options']);
							//$products[$i]['price']  += ( ($productInBundle['quantity']) * zen_add_tax($bundleProduct->fields['products_price'],$products_tax)); // OVERWRITE - Done in SQL
							$products[$i]['price']  +=  zen_round((($productInBundle[$product_id]['quantity']) * $bundleProduct->fields['products_price']), $currencies->currencies[DEFAULT_CURRENCY]['decimal_places']); // OVERWRITE - Done in SQL
							$products[$i]['weight'] += (($productInBundle[$product_id]['quantity']) * $bundleProduct->fields['products_weight']);
							if ( $bundleProduct->fields['group_name'] != "" ) {
								$products[$i]['products'][$id][$product_id]['name'] = $bundleProduct->fields['group_name'] . ':<br />' . $bundleProduct->fields['products_name'];
							} else {
								$products[$i]['products'][$id][$product_id]['name'] = $bundleProduct->fields['products_name'];
							}
							$products[$i]['products'][$id][$product_id]['image'] = $bundleProduct->fields['products_image'];

							if (isset($productInBundle[$product_id]['attr'])) {
								foreach($productInBundle[$product_id]['attr'] as $option => $value) {
									$pa = $db->Execute("SELECT options_values_price, price_prefix, products_attributes_weight, products_attributes_weight_prefix
														  FROM ".TABLE_PRODUCTS_ATTRIBUTES."
														 WHERE products_id       = ".(int)$product_id."
														   AND options_id        = ".(int)$option."
														   AND options_values_id = ".(int)$value);

									$bundleOptionValue = $options[$option][$value];
									if ($bundleOptionValue['price'] != '') {
										//$products[$i]['price'] += (($productInBundle['quantity']) * zen_add_tax($bundleOptionValue['price'], $products_tax));
										$products[$i]['price'] += zen_round((($productInBundle[$product_id]['quantity']) *$bundleOptionValue['price']), $currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
									} else {
										if (!$pa->EOF) {
											//$products[$i]['price']  += (($productInBundle['quantity']) * zen_add_tax(($pa->fields['options_values_price'] * ($pa->fields['price_prefix'] == '-' ? -1 : 1)), $products_tax) );
											$products[$i]['price']  += zen_round((($productInBundle[$product_id]['quantity']) * ($pa->fields['options_values_price'] * ($pa->fields['price_prefix'] == '-' ? -1 : 1)) ), $currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);

										}
									}
									if (!$pa->EOF) {
										$products[$i]['weight'] += (($productInBundle[$product_id]['quantity']) * ($pa->fields['products_attributes_weight'] * ($pa->fields['products_attributes_weight_prefix'] == '-' ? -1 : 1)));
									}
								}
							}
						}
					}

					//$products[$i]['price'] = zen_round($products[$i]['price'], $currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
					$products[$i]['final_price'] =$products[$i]['price'];
				}
			 }
		 }

		$callStack = debug_backtrace(); // $callStack[1][class] == 'order' -- if order is asking for product, return full list
		if (isset($callStack[1]) && isset($callStack[1]['class']) && ($callStack[1]['class'] == 'order' || $callStack[1]['class'] == 'temp_order')) {
			 $newProductList = array();
			 foreach($products as $i => $product) {
				 $newProductList[] = $product;
				 if (zen_get_info_page($product['id']) == 'product_bundle_info') {
					$bundle = $db->Execute("select products_id, products_price, products_tax_class_id, products_weight,
													products_priced_by_attribute, product_is_always_free_shipping, products_discount_type, products_discount_type_from,
													products_virtual, products_model
											  from " . TABLE_PRODUCTS . "
											 where products_id = '" . (int)$product['id'] . "'");
					if (!$bundle->EOF) {
						foreach($products[$i]['products'] as $id => $productInBundle) {
				 		 $product_id = key($productInBundle);

						$bundleProduct = $db->Execute("SELECT IF(b.products_price != '',b.products_price,p.products_price) products_price,
													   b.options, b.group_name,
													   p.products_id, p.master_categories_id, p.products_status, pd.products_name, p.products_model, p.products_image,
					                                   p.products_weight, p.products_tax_class_id,
													   p.products_quantity_order_min, p.products_quantity_order_units,
													   p.product_is_free, p.products_priced_by_attribute,
													   p.products_discount_type, p.products_discount_type_from
												  FROM ".TABLE_BUNDLES." b, ".TABLE_PRODUCTS." p, " . TABLE_PRODUCTS_DESCRIPTION . " pd
												 WHERE b.bundles_id = ".(int)$product['id']."
												   AND b.id          = ".(int)$id."
												   AND b.products_id = ".(int)$product_id."
												   AND b.products_id = p.products_id
												   AND p.products_id = pd.products_id
												   AND pd.language_id = '" . (int)$_SESSION['languages_id'] . "'");

						if (!$bundleProduct->EOF) {
 						    $options = unserialize($bundleProduct->fields['options']);

							$prid = $bundleProduct->fields['products_id'];
							$new_qty = $productInBundle[$product_id]['quantity'] * $product['quantity'];

							$name = $bundleProduct->fields['products_name'];
							if ( $bundleProduct->fields['group_name'] != "" ) {
								$name = $bundleProduct->fields['group_name'] . ':<br />' . $name;
							}

							$newProductList[] = array('id' => $product_id,
												'bundle_id' => $product['id'],
									  		    'category' => $bundleProduct->fields['master_categories_id'],
												'name' => $name,
												'model' => $bundleProduct->fields['products_model'],
												'image' => $bundleProduct->fields['products_image'],
												'price' => 0.00,
												'quantity' => $new_qty,
												'weight' => 0,
												'final_price' => 0,
												'onetime_charges' => 0,
												'tax_class_id' => 0,
												'attributes' => (isset($this->contents[$product['id']]['products'][$id][$product_id]['attr']) ? $this->contents[$product['id']]['products'][$id][$product_id]['attr'] : ''),
												'attributes_values' => '',
												'products_priced_by_attribute' => 0,
												'product_is_free' => 0,
												'products_discount_type' => 0,
												'products_discount_type_from' => 0);
						 }
					  }
					}
				 }
			 }
			 return $newProductList;
		}
		 return $products;
	}

	function calculate() {
	    global $db;
		parent::calculate();

		if (!is_array($this->contents)) return 0;

		reset($this->contents);
		while (list($bundle_id, ) = each($this->contents)) {
			if (isset($this->contents[$bundle_id]['products'])) {

				$bundle = $db->Execute("select products_id, products_price, products_tax_class_id, products_weight,
                         						products_priced_by_attribute, product_is_always_free_shipping, products_discount_type, products_discount_type_from,
												products_virtual, products_model
                          				  from " . TABLE_PRODUCTS . "
                          				 where products_id = '" . (int)$bundle_id . "'");
				reset($this->contents[$bundle_id]['products']);
				while (list($id, ) = each($this->contents[$bundle_id]['products'])) {
					$products_id = key($this->contents[$bundle_id]['products'][$id]);

					// get bundle product info and options
					$product = $db->Execute("SELECT IF(b.products_price != '',b.products_price,p.products_price) products_price,
									   	       b.options,
											   p.products_id, p.products_tax_class_id, p.products_weight,
                          					   p.products_priced_by_attribute, p.product_is_always_free_shipping, p.products_discount_type, p.products_discount_type_from,
                          					   p.products_virtual, p.products_model
									      FROM ".TABLE_BUNDLES." b, ".TABLE_PRODUCTS." p
										 WHERE b.bundles_id = ".(int)$bundle_id."
										   AND b.id          = ".(int)$id."
										   AND b.products_id = ".(int)$products_id."
										   AND b.products_id = p.products_id");
					if (!$product->EOF) {
						$options = unserialize($product->fields['options']);
						$qty  = $this->contents[$bundle_id]['products'][$id][$products_id]['quantity'];
						$prid = $product->fields['products_id'];
        				$products_tax = zen_get_tax_rate($bundle->fields['products_tax_class_id']);
		        		$products_price = $product->fields['products_price']; // OVERWRITE - Done in SQL
					    $products_weight = $product->fields['products_weight'];
						if ((ereg('^GIFT', addslashes($product->fields['products_model'])))) {
						  $this->free_shipping_item += ($qty * $this->contents[$bundle_id]['qty']);
						  $this->free_shipping_price += zen_add_tax($products_price, $products_tax) * ($qty * $this->contents[$bundle_id]['qty']);
						  $this->free_shipping_weight += (($qty * $this->contents[$bundle_id]['qty']) * $products_weight);
						}
						$this->total += zen_add_tax($products_price, $products_tax) * ($qty * $this->contents[$bundle_id]['qty']);
        				$this->weight += (($qty * $this->contents[$bundle_id]['qty']) * $products_weight);

						if (isset($this->contents[$bundle_id]['products'][$id][$products_id]['attr'])) {
					        foreach($this->contents[$bundle_id]['products'][$id][$products_id]['attr'] as $option => $value) {
								$pa = $db->Execute("SELECT options_values_price, price_prefix, products_attributes_weight, products_attributes_weight_prefix
													  FROM ".TABLE_PRODUCTS_ATTRIBUTES."
													 WHERE products_id       = ".(int)$products_id."
													   AND options_id        = ".(int)$option."
													   AND options_values_id = ".(int)$value);

								$bundleOptionValue = $options[$option][$value];
								if ($bundleOptionValue['price'] != '') {
									$this->total += ($qty * $this->contents[$bundle_id]['qty']) * zen_add_tax($bundleOptionValue['price'], $products_tax);
								} else {
									if (!$pa->EOF) {
										$this->total += ( ($qty * $this->contents[$bundle_id]['qty']) * zen_add_tax(($pa->fields['options_values_price'] * ($pa->fields['price_prefix'] == '-' ? -1 : 1)), $products_tax) );
									}
								}
								if (!$pa->EOF) {
									$this->weight += ( ($qty * $this->contents[$bundle_id]['qty']) * ($pa->fields['products_attributes_weight'] * ($pa->fields['products_attributes_weight_prefix'] == '-' ? -1 : 1)));
								}
							}
						}
					}
				}
			}
		}
	}
}
