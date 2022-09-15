<?php

$customers_where = " BETWEEN 151860 AND 151908";

include_once('includes/application_top.php');
include_once(DIR_WS_CLASSES.'customerIO.php');

$customerio = new CustomerIO();

if (isset($_GET['action']) && $_GET['action'] == 'customers') {

    $customers_sql = "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address,
                      ci.customers_info_date_account_created
                      FROM ".TABLE_CUSTOMERS." c, ".TABLE_CUSTOMERS_INFO." ci
                      WHERE c.customers_id ".$customers_where."
                      AND c.customers_id = ci.customers_info_id";

                     
    $customers = $db->execute($customers_sql);
    //echo '<pre>'; print_r($customers); echo '</pre>'; exit();
    $i = 0;
    while (!$customers->EOF) {
        
        $i++;
        
        //  get orders count
        $orders_count_sql = "SELECT DISTINCT orders_id FROM ".TABLE_ORDERS."
                             WHERE customers_id = '".$customers->fields['customers_id']."'";
        $orders_count = $db->execute($orders_count_sql);
        
        $orders_total_sql = "SELECT sum(order_total) as total FROM ".TABLE_ORDERS."
                             WHERE customers_id = '".$customers->fields['customers_id']."'";
        $orders_total = $db->execute($orders_total_sql);
        
        $has_cart_sql = "SELECT customers_id FROM ".TABLE_CUSTOMERS_BASKET."
                         WHERE customers_id = '".$customers->fields['customers_id']."'";
        $has_cart = $db->execute($has_cart_sql);
        
        //$email = str_replace('@', '@taras.', $customers->fields['customers_email_address']);
        $email = $customers->fields['customers_email_address'];
        $add_date = date('U', strtotime($customers->fields['customers_info_date_account_created']));
        
        $extra = array('firstname' => $customers->fields['customers_firstname'],
                       'lastname' => $customers->fields['customers_lastname'],
                       //'orders_count' => $orders_count->RecordCount(),
                       //'orders_total' => $orders_total->fields['total'],
                       //'has_cart' => ($has_cart->RecordCount() > 0 ? 1 : 0));
                       );
        //echo '<pre>'; print_r($extra); echo '</pre>'; exit();
        $customerio->addUser($customers->fields['customers_id'], $email, $add_date, $extra);
        
        echo $i.': added '.$email.'<br />';
        
        $customers->MoveNext();
    }
}

/**********************************************************************/

if (isset($_GET['action']) && $_GET['action'] == 'purchases') {
    
    $classes_names_sql = "SELECT classes_id, classes_name
                          FROM ".TABLE_CLASSES."
                          ORDER BY classes_id";
    $classes_result = $db->execute($classes_names_sql);
    
    $classes_names = array();
    while (!$classes_result->EOF) {
        $classes_names[$classes_result->fields['classes_id']] = $classes_result->fields['classes_name'];
        $classes_result->MoveNext();
    }

    include_once(DIR_WS_CLASSES . 'order.php');
    
    $order_ids_sql = "SELECT orders_id, customers_id
                      FROM ".TABLE_ORDERS."
                      WHERE customers_id ".$customers_where;
                      //WHERE customers_id = 110400";
                      
                      
    $order_ids = $db->execute($order_ids_sql);

    $i = 0;
    while (!$order_ids->EOF) {
        
        $i++;
        $order_info = new order($order_ids->fields['orders_id']);
        
        $qty = 0;
        $products_array = array();
        foreach ($order_info->products as $product) {
            
            $class_names = array();
            
            $product_classes_sql = "SELECT products_classes
                                    FROM ".TABLE_PRODUCTS."
                                    WHERE products_id = ".(int)$product['id'];
            $product_classes = $db->execute($product_classes_sql);
            
            while (!$product_classes->EOF) {
                
                if ($product_classes->fields['products_classes'] != '') {
                    
                    $classes_array = explode(',', $product_classes->fields['products_classes']);
                    
                    $class_names = array();
                    $event_classes = array();
                    foreach ($classes_array as $class_id) {
                        if ($class_id != '') {
                            if (!in_array($classes_names[$class_id], $class_names)) {
                                $class_names[] = $classes_names[$class_id];
                                $event_classes[$class_id] = $classes_names[$class_id];
                            }
                        }
                    }
                }
                
                $product_classes->MoveNext();
            }
            
            if (count($class_names) == 1) {
                //$class_names = $class_names[0];
            }
            
            //  send events "class"
            foreach ($event_classes as $key => $value) {            
                $data = array('date' => date('U'),
                              'class_id' => $key,
                              'class_name' => $value);            
                $customerio->triggerEvent($order_ids->fields['customers_id'], 'class', $data);
            }
                    
            $prod_array[] = array(
                'id' => (int)$product['id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'price' => $product['final_price'],
                'class' => $class_names
            );
            $qty += (int)$product['qty'];
            
            // send event "product"
            $data = array('id' => (int)$product['id'],
                          'name' => $product['name'],
                          'model' => $product['model'],
                          'price' => $product['final_price'],
                          'date' => date('U'),
                          'qty' => (int)$product['qty']);
                          
            $customerio->triggerEvent($order_ids->fields['customers_id'], 'product', $data);
        }
        
        $data = array(
            'date' => date('U', strtotime($order_info->info['date_purchased'])),
            'total' => $order_info->info['total'],
            'qty' => $qty,
            'products' => $prod_array
        );
//echo '<pre>'; print_r($data); echo '</pre>'; exit();
        $customerio->triggerEvent($order_ids->fields['customers_id'], 'purchased', $data);
        
        echo $i.': added '.$order_ids->fields['customers_id'].'<br>';
        //exit();
        $order_ids->MoveNext();
    }
    
}


echo 'end';

?>