<?php
/**
 * create_account header_php.php
 *
 * @package modules
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: create_account.php 106 2010-03-14 20:55:15Z numinix $
 */
// This should be first line of the script:
$zco_notifier->notify('NOTIFY_MODULE_START_CREATE_ACCOUNT');

if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}
// BOF Captcha
if (CAPTCHA_CREATE_ACCOUNT == 'true' && file_exists(DIR_WS_CLASSES . 'captcha.php')) {
  require(DIR_WS_CLASSES . 'captcha.php');
  $captcha = new captcha();
}
// EOF Captcha
/**
 * Set some defaults
 */
  $process = false;
  $zone_name = '';
  $entry_state_has_zones = '';
  $error_state_input = false;
  $state = '';
  $zone_id = 0;
  $error = false;
  $email_format = (ACCOUNT_EMAIL_PREFERENCE == '1' ? 'HTML' : 'TEXT');
  $newsletter = (ACCOUNT_NEWSLETTER_STATUS == '1' ? false : true);
  
  $process_shipping = false;
  $zone_name_shipping = '';
  $entry_state_has_zones_shipping = '';
  $error_state_input_shipping = false;
  $state_shipping = '';
  $zone_id_shipping = 0;

/**
 * Process form contents
 */
if (isset($_POST['action']) && ($_POST['action'] == 'process')) {
  $process = true;
  
  $_SESSION['navigation']->snapshot = unserialize($_POST['snapshot']);

  if (ACCOUNT_GENDER == 'true') {
    if (isset($_POST['gender'])) {
      $gender = zen_db_prepare_input($_POST['gender']);
    } else {
      $gender = false;
    }
  }

  if (isset($_POST['email_format'])) {
    $email_format = zen_db_prepare_input($_POST['email_format']);
  }

  if (ACCOUNT_COMPANY == 'true') $company = zen_db_prepare_input($_POST['company']);
  $firstname = zen_db_prepare_input($_POST['firstname']);
  $lastname = zen_db_prepare_input($_POST['lastname']);
  $nick = zen_db_prepare_input($_POST['nick']);
  if (ACCOUNT_DOB == 'true') $dob = (empty($_POST['dob']) ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_db_prepare_input($_POST['dob']));
  $email_address = zen_db_prepare_input($_POST['email_address']);
  $street_address = zen_db_prepare_input($_POST['street_address']);
  if (ACCOUNT_SUBURB == 'true') $suburb = zen_db_prepare_input($_POST['suburb']);
  $postcode = zen_db_prepare_input($_POST['postcode']);
  $city = zen_db_prepare_input($_POST['city']);
  if (ACCOUNT_STATE == 'true') {
    $state = zen_db_prepare_input($_POST['state']);
    if (isset($_POST['zone_id'])) {
      $zone_id = zen_db_prepare_input($_POST['zone_id']);
    } else {
      $zone_id = false;
    }
  }
  $country = zen_db_prepare_input($_POST['zone_country_id']);
  
  if (ACCOUNT_TELEPHONE == 'true') {
    $telephone = zen_db_prepare_input($_POST['telephone']);
  }
  $fax = zen_db_prepare_input($_POST['fax']);
  $customers_authorization = CUSTOMERS_APPROVAL_AUTHORIZATION;
  $customers_referral = zen_db_prepare_input($_POST['customers_referral']);

  if (isset($_POST['newsletter'])) {
    $newsletter = zen_db_prepare_input($_POST['newsletter']);
  }

  $password = zen_db_prepare_input($_POST['password']);
  $confirmation = zen_db_prepare_input($_POST['confirmation']);
  
  // BOF Captcha
  if (is_object($captcha) && !$captcha->validateCaptchaCode()) {
    $error = true;
    $messageStack->add_session('login', ERROR_CAPTCHA);
  }
  // EOF Captcha


  if (DISPLAY_PRIVACY_CONDITIONS == 'true') {
    if (!isset($_POST['privacy_conditions']) || ($_POST['privacy_conditions'] != '1')) {
      $error = true;
      $messageStack->add_session('login', ERROR_PRIVACY_STATEMENT_NOT_ACCEPTED, 'error');
    }
  }

  if (ACCOUNT_GENDER == 'true') {
    if ( ($gender != 'm') && ($gender != 'f') ) {
      $error = true;
      $messageStack->add_session('login', ENTRY_GENDER_ERROR);
    }
  }

  if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_FIRST_NAME_ERROR);
  }

  if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_LAST_NAME_ERROR);
  }

  if (ACCOUNT_DOB == 'true') {
    if (ENTRY_DOB_MIN_LENGTH > 0 or !empty($_POST['dob'])) {
      if (substr_count($dob,'/') > 2 || checkdate((int)substr(zen_date_raw($dob), 4, 2), (int)substr(zen_date_raw($dob), 6, 2), (int)substr(zen_date_raw($dob), 0, 4)) == false) {
        $error = true;
        $messageStack->add_session('login', ENTRY_DATE_OF_BIRTH_ERROR);
      }
    }
  }

  if (ACCOUNT_COMPANY == 'true') {
    if ((int)ENTRY_COMPANY_MIN_LENGTH > 0 && strlen($company) < ENTRY_COMPANY_MIN_LENGTH) {
      $error = true;
      $messageStack->add_session('login', ENTRY_COMPANY_ERROR);
    }
  }


  if (strlen($email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_EMAIL_ADDRESS_ERROR);
  } elseif (zen_validate_email($email_address) == false) {
    $error = true;
    $messageStack->add_session('login', ENTRY_EMAIL_ADDRESS_CHECK_ERROR);
  } else {
    $check_email_query = "select count(*) as total
                            from " . TABLE_CUSTOMERS . "
                            where customers_email_address = '" . zen_db_input($email_address) . "'
                            and COWOA_account != 1";
    $check_email = $db->Execute($check_email_query);

    if ($check_email->fields['total'] > 0) {
      $error = true;
      $messageStack->add_session('login', ENTRY_EMAIL_ADDRESS_ERROR_EXISTS);
    }
  }

  if ($phpBB->phpBB['installed'] == true) {
    if (strlen($nick) < ENTRY_NICK_MIN_LENGTH)  {
      $error = true;
      $messageStack->add_session('login', ENTRY_NICK_LENGTH_ERROR);
    } else {
      // check Zen Cart for duplicate nickname
      $check_nick_query = "select * from " . TABLE_CUSTOMERS  . "
                           where customers_nick = '" . $nick . "'";
      $check_nick = $db->Execute($check_nick_query);
      if ($check_nick->RecordCount() > 0 ) {
        $error = true;
        $messageStack->add_session('login', ENTRY_NICK_DUPLICATE_ERROR);
      }
      // check phpBB for duplicate nickname
      if ($phpBB->phpbb_check_for_duplicate_nick($nick) == 'already_exists' ) {
        $error = true;
        $messageStack->add_session('login', ENTRY_NICK_DUPLICATE_ERROR . ' (phpBB)');
      }
    }
  }

  if (strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_STREET_ADDRESS_ERROR);
  }
  
  // BEGIN PO Box Ban 1/1
  if (defined('PO_BOX_ERROR')) {
    if ( preg_match('/PO BOX/si', $street_address) ) {
      $error = true;
      $messageStack->add_session('login', PO_BOX_ERROR);
    } else if ( preg_match('/POBOX/si', $street_address) ) {
      $error = true;
      $messageStack->add_session('login', PO_BOX_ERROR);
    } else if ( preg_match('/P\.O\./si', $street_address) ) {
      $error = true;
      $messageStack->add_session('login', PO_BOX_ERROR);
    } else if ( preg_match('/P\.O/si', $street_address) ) {
      $error = true;
      $messageStack->add_session('login', PO_BOX_ERROR);
    } else if ( preg_match('/PO\./si', $street_address) ) {
      $error = true;
      $messageStack->add_session('login', PO_BOX_ERROR);
    }
  }
  // END PO Box Ban 1/1

  if (strlen($city) < ENTRY_CITY_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_CITY_ERROR);
  }

  if (ACCOUNT_STATE == 'true') {
    $check_query = "SELECT count(*) AS total
                    FROM " . TABLE_ZONES . "
                    WHERE zone_country_id = :zoneCountryID";
    $check_query = $db->bindVars($check_query, ':zoneCountryID', $country, 'integer');
    $check = $db->Execute($check_query);
    $entry_state_has_zones = ($check->fields['total'] > 0);
    if ($entry_state_has_zones == true) {
      $zone_query = "SELECT distinct zone_id, zone_name, zone_code
                     FROM " . TABLE_ZONES . "
                     WHERE zone_country_id = :zoneCountryID
                     AND " .
                     ((trim($state) != '' && $zone_id == 0) ? "(upper(zone_name) like ':zoneState%' OR upper(zone_code) like '%:zoneState%') OR " : "") .
                    "zone_id = :zoneID
                     ORDER BY zone_code ASC, zone_name";

      $zone_query = $db->bindVars($zone_query, ':zoneCountryID', $country, 'integer');
      $zone_query = $db->bindVars($zone_query, ':zoneState', strtoupper($state), 'noquotestring');
      $zone_query = $db->bindVars($zone_query, ':zoneID', $zone_id, 'integer');
      $zone = $db->Execute($zone_query);

      //look for an exact match on zone ISO code
      $found_exact_iso_match = ($zone->RecordCount() == 1);
      if ($zone->RecordCount() > 1) {
        while (!$zone->EOF && !$found_exact_iso_match) {
          if (strtoupper($zone->fields['zone_code']) == strtoupper($state) ) {
            $found_exact_iso_match = true;
            continue;
          }
          $zone->MoveNext();
        }
      }

      if ($found_exact_iso_match) {
        $zone_id = $zone->fields['zone_id'];
        $zone_name = $zone->fields['zone_name'];
      } else {
        $error = true;
        $error_state_input = true;
        $messageStack->add_session('login', ENTRY_STATE_ERROR_SELECT);
      }
    } else {
      if (strlen($state) < ENTRY_STATE_MIN_LENGTH) {
        $error = true;
        $error_state_input = true;
        $messageStack->add_session('login', ENTRY_STATE_ERROR);
      }
    }
  }

  if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_POST_CODE_ERROR);
  }

  if ( (is_numeric($country) == false) || ($country < 1) ) {
    $error = true;
    $messageStack->add_session('login', ENTRY_COUNTRY_ERROR);
  }

  if (ACCOUNT_TELEPHONE == 'true') {
    if (strlen($telephone) < ENTRY_TELEPHONE_MIN_LENGTH) {
      $error = true;
      $messageStack->add_session('login', ENTRY_TELEPHONE_NUMBER_ERROR);
    }
  }
  
  // confirm email address modification
  if (FEC_CONFIRM_EMAIL == 'true') {
    $email_address_confirm = zen_db_prepare_input($_POST['email_address_confirm']);
    if ($email_address != $email_address_confirm) {
      $error = true;
      $messageStack->add_session('login', ENTRY_EMAIL_ADDRESS_CONFIRM_ERROR);
    }
  }


  if (strlen($password) < ENTRY_PASSWORD_MIN_LENGTH) {
    $error = true;
    $messageStack->add_session('login', ENTRY_PASSWORD_ERROR);
  } elseif ($password != $confirmation) {
    $error = true;
    $messageStack->add_session('login', ENTRY_PASSWORD_ERROR_NOT_MATCHING);
  }

// begin shipping
    if ($_GET['main_page'] != "create_account" && enable_shippingAddress()) {
      $process_shipping = true;
      if (ACCOUNT_GENDER == 'true') $gender_shipping = zen_db_prepare_input($_POST['gender_shipping']);
      if (ACCOUNT_COMPANY == 'true') $company_shipping = zen_db_prepare_input($_POST['company_shipping']);
      $firstname_shipping = zen_db_prepare_input($_POST['firstname_shipping']);
      $lastname_shipping = zen_db_prepare_input($_POST['lastname_shipping']);
      $street_address_shipping = zen_db_prepare_input($_POST['street_address_shipping']);
      if (ACCOUNT_SUBURB == 'true') $suburb_shipping = zen_db_prepare_input($_POST['suburb_shipping']);
      $postcode_shipping = zen_db_prepare_input($_POST['postcode_shipping']);
      $city_shipping = zen_db_prepare_input($_POST['city_shipping']);
      if (ACCOUNT_STATE == 'true') {
        $state_shipping = zen_db_prepare_input($_POST['state_shipping']);
        if (isset($_POST['zone_id_shipping'])) {
          $zone_id_shipping = zen_db_prepare_input($_POST['zone_id_shipping']);
        } else {
          $zone_id_shipping = false;
        }
      }
      $country_shipping = zen_db_prepare_input($_POST['zone_country_id_shipping']);
  //echo ' I SEE: country=' . $country . '&nbsp;&nbsp;&nbsp;state=' . $state . '&nbsp;&nbsp;&nbsp;zone_id=' . $zone_id;
      if (ACCOUNT_GENDER == 'true') {
        if ( ($gender_shipping != 'm') && ($gender_shipping != 'f') ) {
          $error = true;
          $messageStack->add_session('login', ENTRY_GENDER_ERROR);
        }
      }

      if (strlen($firstname_shipping) < ENTRY_FIRST_NAME_MIN_LENGTH) {
        $error = true;
        $messageStack->add_session('login', ENTRY_FIRST_NAME_ERROR);
      }

      if (strlen($lastname_shipping) < ENTRY_LAST_NAME_MIN_LENGTH) {
        $error = true;
        $messageStack->add_session('login', ENTRY_LAST_NAME_ERROR);
      }

      if (strlen($street_address_shipping) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
        $error = true;
        $messageStack->add_session('login', ENTRY_STREET_ADDRESS_ERROR);
      }

      if (strlen($city_shipping) < ENTRY_CITY_MIN_LENGTH) {
        $error = true;
        $messageStack->add_session('login', ENTRY_CITY_ERROR);
      }
      
      // BEGIN PO Box Ban 1/1
      if (defined('PO_BOX_ERROR')) {
        if ( preg_match('/PO BOX/si', $street_address_shipping) ) {
          $error = true;
          $messageStack->add_session('login', PO_BOX_ERROR);
        } else if ( preg_match('/POBOX/si', $street_address_shipping) ) {
          $error = true;
          $messageStack->add_session('login', PO_BOX_ERROR);
        } else if ( preg_match('/P\.O\./si', $street_address_shipping) ) {
          $error = true;
          $messageStack->add_session('login', PO_BOX_ERROR);
        } else if ( preg_match('/P\.O/si', $street_address_shipping) ) {
          $error = true;
          $messageStack->add_session('login', PO_BOX_ERROR);
        } else if ( preg_match('/PO\./si', $street_address_shipping) ) {
          $error = true;
          $messageStack->add_session('login', PO_BOX_ERROR);
        }
      }
      // END PO Box Ban 1/1

      if (ACCOUNT_STATE == 'true') {
        $check_query = "SELECT count(*) AS total
                        FROM " . TABLE_ZONES . "
                        WHERE zone_country_id = :zoneCountryID";
        $check_query = $db->bindVars($check_query, ':zoneCountryID', $country_shipping, 'integer');
        $check = $db->Execute($check_query);
        $entry_state_has_zones_shipping = ($check->fields['total'] > 0);
        if ($entry_state_has_zones_shipping == true) {
          $zone_query = "SELECT distinct zone_id, zone_name, zone_code
                         FROM " . TABLE_ZONES . "
                         WHERE zone_country_id = :zoneCountryID
                         AND " . 
                       ((trim($state_shipping) != '' && $zone_id_shipping == 0) ? "(upper(zone_name) like ':zoneState%' OR upper(zone_code) like '%:zoneState%') OR " : "") .
                        "zone_id = :zoneID
                         ORDER BY zone_code ASC, zone_name";

          $zone_query = $db->bindVars($zone_query, ':zoneCountryID', $country_shipping, 'integer');
          $zone_query = $db->bindVars($zone_query, ':zoneState', strtoupper($state_shipping), 'noquotestring');
          $zone_query = $db->bindVars($zone_query, ':zoneID', $zone_id_shipping, 'integer');
          $zone_shipping = $db->Execute($zone_query);

          //look for an exact match on zone ISO code
          $found_exact_iso_match_shipping = ($zone->RecordCount() == 1);
          if ($zone_shipping->RecordCount() > 1) {
            while (!$zone_shipping->EOF && !$found_exact_iso_match_shipping) {
              if (strtoupper($zone->fields['zone_code']) == strtoupper($state_shipping) ) {
                $found_exact_iso_match_shipping = true;
                continue;
              }
              $zone_shipping->MoveNext();
            }
          }

          if ($found_exact_iso_match_shipping) {
            $zone_id_shipping = $zone_shipping->fields['zone_id'];
            $zone_name_shipping = $zone_shipping->fields['zone_name'];
          } else {
            $error = true;
            $error_state_input_shipping = true;
            $messageStack->add_session('login', ENTRY_STATE_ERROR_SELECT);
          }
        } else {
          if (strlen($state_shipping) < ENTRY_STATE_MIN_LENGTH) {
            $error = true;
            $error_state_input_shipping = true;
            $messageStack->add_session('login', ENTRY_STATE_ERROR);
          }
        }
      }

      if (strlen($postcode_shipping) < ENTRY_POSTCODE_MIN_LENGTH) {
        $error = true;
        $messageStack->add_session('login', ENTRY_POST_CODE_ERROR);
      }

      if ( (is_numeric($country_shipping) == false) || ($country_shipping < 1) ) {
        $error = true;
        $messageStack->add_session('login', ENTRY_COUNTRY_ERROR);
      }
    }
// end shipping  

  if ($error == true) {
    // hook notifier class
    $zco_notifier->notify('NOTIFY_FAILURE_DURING_CREATE_ACCOUNT');
    // redirect back to login page
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
  } else {
    $sql_data_array = array('customers_firstname' => $firstname,
                            'customers_lastname' => $lastname,
                            'customers_email_address' => $email_address,
                            'customers_nick' => $nick,
                            'customers_telephone' => $telephone,
                            'customers_fax' => $fax,
                            'customers_newsletter' => (int)$newsletter,
                            'customers_email_format' => $email_format,
                            'customers_default_address_id' => 0,
                            'customers_password' => zen_encrypt_password($password),
                            'customers_authorization' => (int)CUSTOMERS_APPROVAL_AUTHORIZATION
    );

    if ((CUSTOMERS_REFERRAL_STATUS == '2' and $customers_referral != '')) $sql_data_array['customers_referral'] = $customers_referral;
    if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $gender;
    if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = (empty($_POST['dob']) || $dob_entered == '0001-01-01 00:00:00' ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_date_raw($_POST['dob']));

    zen_db_perform(TABLE_CUSTOMERS, $sql_data_array);

    $_SESSION['customer_id'] = $db->Insert_ID();

    $zco_notifier->notify('NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_CUSTOMER_RECORD', array_merge(array('customer_id' => $_SESSION['customer_id']), $sql_data_array));

    $sql_data_array = array('customers_id' => $_SESSION['customer_id'],
                            'entry_firstname' => $firstname,
                            'entry_lastname' => $lastname,
                            'entry_street_address' => $street_address,
                            'entry_postcode' => $postcode,
                            'entry_city' => $city,
                            'entry_country_id' => $country);

    if (ACCOUNT_GENDER == 'true') $sql_data_array['entry_gender'] = $gender;
    if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $company;
    if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $suburb;
    if (ACCOUNT_STATE == 'true') {
      if ($zone_id > 0) {
        $sql_data_array['entry_zone_id'] = $zone_id;
        $sql_data_array['entry_state'] = '';
      } else {
        $sql_data_array['entry_zone_id'] = '0';
        $sql_data_array['entry_state'] = $state;
      }
    }

    zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

    $address_id = $db->Insert_ID();

    $zco_notifier->notify('NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_ADDRESS_BOOK_RECORD', array_merge(array('address_id' => $address_id), $sql_data_array));

    $sql = "update " . TABLE_CUSTOMERS . "
              set customers_default_address_id = '" . (int)$address_id . "'
              where customers_id = '" . (int)$_SESSION['customer_id'] . "'";

    $db->Execute($sql);
    // shipping address
    if ($_GET['main_page'] != "create_account" && enable_shippingAddress()) {
      // create shipping address
      $sql_data_array = array(array('fieldName'=>'customers_id', 'value'=>$_SESSION['customer_id'], 'type'=>'integer'),
                              array('fieldName'=>'entry_firstname', 'value'=>$firstname_shipping, 'type'=>'string'),
                              array('fieldName'=>'entry_lastname','value'=>$lastname_shipping, 'type'=>'string'),
                              array('fieldName'=>'entry_street_address','value'=>$street_address_shipping, 'type'=>'string'),
                              array('fieldName'=>'entry_postcode', 'value'=>$postcode_shipping, 'type'=>'string'),
                              array('fieldName'=>'entry_city', 'value'=>$city_shipping, 'type'=>'string'),
                              array('fieldName'=>'entry_country_id', 'value'=>$country_shipping, 'type'=>'integer')
      );

      if (ACCOUNT_GENDER == 'true') $sql_data_array[] = array('fieldName'=>'entry_gender', 'value'=>$gender_shipping, 'type'=>'enum:m|f');
      if (ACCOUNT_COMPANY == 'true') $sql_data_array[] = array('fieldName'=>'entry_company', 'value'=>$company_shipping, 'type'=>'string');
      if (ACCOUNT_SUBURB == 'true') $sql_data_array[] = array('fieldName'=>'entry_suburb', 'value'=>$suburb_shipping, 'type'=>'string');
      if (ACCOUNT_STATE == 'true') {
        if ($zone_id_shipping > 0) {
          $sql_data_array[] = array('fieldName'=>'entry_zone_id', 'value'=>$zone_id_shipping, 'type'=>'integer');
          $sql_data_array[] = array('fieldName'=>'entry_state', 'value'=>'', 'type'=>'string');
        } else {
          $sql_data_array[] = array('fieldName'=>'entry_zone_id', 'value'=>0, 'type'=>'integer');
          $sql_data_array[] = array('fieldName'=>'entry_state', 'value'=>$state_shipping, 'type'=>'string');
        }
      }
      $db->perform(TABLE_ADDRESS_BOOK, $sql_data_array);
      $_SESSION['sendto'] = $db->Insert_ID();
    } else {
      // FEC MODIFICATION
      $_SESSION['sendto'] = $_SESSION['cart_address_id'] = (int)$address_id;
    }
    $_SESSION['shipping'] = ''; 
    $sql = "insert into " . TABLE_CUSTOMERS_INFO . "
                          (customers_info_id, customers_info_number_of_logons,
                           customers_info_date_account_created)
              values ('" . (int)$_SESSION['customer_id'] . "', '0', now())";

    $db->Execute($sql);
    
// BEGIN newsletter_subscribe mod 1/1
// If a newsletter only account exists we update the info,
// but keep the subscription active, and give them a message that to
// change they should do so on their account page (after creation).
    if(defined('NEWSONLY_SUBSCRIPTION_ENABLED') && (NEWSONLY_SUBSCRIPTION_ENABLED=='true')) {
      $check_subscribers_query = "select count(*) as total from " . TABLE_SUBSCRIBERS . "
                    where email_address = '" . zen_db_input($email_address) . "' ";
      $check_subscribers = $db->Execute($check_subscribers_query);
      if ($check_subscribers->fields['total'] > 0) {
        $sql = "UPDATE " . TABLE_SUBSCRIBERS . " SET
                customers_id = '" . (int)$_SESSION['customer_id'] . "',
                email_format = '" . zen_db_input($email_format) . "',
                confirmed = '1' 
                WHERE email_address = '" . zen_db_input($email_address) . "' ";
        $db->Execute($sql);
        $messageStack->add_session('login', SUBSCRIBE_MERGED_NEWSONLY_ACCT);
      } else {
        if (!empty($newsletter)) {
          $sql = "INSERT INTO " . TABLE_SUBSCRIBERS . " 
                  (customers_id, email_address, email_format, confirmed, subscribed_date)
                  VALUES ('" . (int)$_SESSION['customer_id'] . "', '" . zen_db_input($email_address) . "', '" . zen_db_input($email_format) . "', '1', now())";
          $db->Execute($sql);
        }
      }
    }
// END newsletter_subscribe mod 1/1

    // phpBB create account
    if ($phpBB->phpBB['installed'] == true) {
      $phpBB->phpbb_create_account($nick, $password, $email_address);
    }
    // End phppBB create account

    if (SESSION_RECREATE == 'True') {
      zen_session_recreate();
    }

    $_SESSION['customer_first_name'] = $firstname;
    $_SESSION['customer_default_address_id'] = $address_id;
    $_SESSION['customer_country_id'] = $country;
    $_SESSION['customer_zone_id'] = $zone_id;
    $_SESSION['customers_authorization'] = $customers_authorization;

    // restore cart contents
    $_SESSION['cart']->restore_contents();

    // hook notifier class
    $zco_notifier->notify('NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT');
    
    
    //  customer.io - add customer
    include_once(DIR_WS_CLASSES.'customerIO.php');
    $customerio = new CustomerIO();
    
    $email = str_replace('@', '@taras.', $email_address);
    $add_date = date('U');
        
    $extra = array('firstname' => $firstname,
                    'lastname' => $lastname);
    if ($_SESSION['cart']->count_contents() > 0) {
        $extra['has_cart'] = 1;
    } else {
        $extra['has_cart'] = 0;
    }
        
    $customerio->addUser((int)$_SESSION['customer_id'], $email, $add_date, $extra);
    

    // build the message content
    $name = $firstname . ' ' . $lastname;

    if (ACCOUNT_GENDER == 'true') {
      if ($gender == 'm') {
        $email_text = sprintf(EMAIL_GREET_MR, $lastname);
      } else {
        $email_text = sprintf(EMAIL_GREET_MS, $lastname);
      }
    } else {
      $email_text = sprintf(EMAIL_GREET_NONE, $firstname);
    }
    $html_msg['EMAIL_GREETING'] = str_replace('\n','',$email_text);
    $html_msg['EMAIL_FIRST_NAME'] = $firstname;
    $html_msg['EMAIL_LAST_NAME']  = $lastname;

    // initial welcome
    $email_text .=  EMAIL_WELCOME;
    $html_msg['EMAIL_WELCOME'] = str_replace('\n','',EMAIL_WELCOME);

    if (NEW_SIGNUP_DISCOUNT_COUPON != '' and NEW_SIGNUP_DISCOUNT_COUPON != '0') {
      $coupon_id = NEW_SIGNUP_DISCOUNT_COUPON;
      $coupon = $db->Execute("select * from " . TABLE_COUPONS . " where coupon_id = '" . $coupon_id . "'");
      $coupon_desc = $db->Execute("select coupon_description from " . TABLE_COUPONS_DESCRIPTION . " where coupon_id = '" . $coupon_id . "' and language_id = '" . $_SESSION['languages_id'] . "'");
      $db->Execute("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $coupon_id ."', '0', 'Admin', '" . $email_address . "', now() )");

      $text_coupon_help = sprintf(TEXT_COUPON_HELP_DATE, zen_date_short($coupon->fields['coupon_start_date']),zen_date_short($coupon->fields['coupon_expire_date']));

      // if on, add in Discount Coupon explanation
      //        $email_text .= EMAIL_COUPON_INCENTIVE_HEADER .
      $email_text .= "\n" . EMAIL_COUPON_INCENTIVE_HEADER .
      (!empty($coupon_desc->fields['coupon_description']) ? $coupon_desc->fields['coupon_description'] . "\n\n" : '') . $text_coupon_help  . "\n\n" .
      strip_tags(sprintf(EMAIL_COUPON_REDEEM, ' ' . $coupon->fields['coupon_code'])) . EMAIL_SEPARATOR;

      $html_msg['COUPON_TEXT_VOUCHER_IS'] = EMAIL_COUPON_INCENTIVE_HEADER ;
      $html_msg['COUPON_DESCRIPTION']     = (!empty($coupon_desc->fields['coupon_description']) ? '<strong>' . $coupon_desc->fields['coupon_description'] . '</strong>' : '');
      $html_msg['COUPON_TEXT_TO_REDEEM']  = str_replace("\n", '', sprintf(EMAIL_COUPON_REDEEM, ''));
      $html_msg['COUPON_CODE']  = $coupon->fields['coupon_code'] . $text_coupon_help;
    } //endif coupon

    if (NEW_SIGNUP_GIFT_VOUCHER_AMOUNT > 0) {
      $coupon_code = zen_create_coupon_code();
      $insert_query = $db->Execute("insert into " . TABLE_COUPONS . " (coupon_code, coupon_type, coupon_amount, date_created) values ('" . $coupon_code . "', 'G', '" . NEW_SIGNUP_GIFT_VOUCHER_AMOUNT . "', now())");
      $insert_id = $db->Insert_ID();
      $db->Execute("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $insert_id ."', '0', 'Admin', '" . $email_address . "', now() )");

      // if on, add in GV explanation
      $email_text .= "\n\n" . sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) .
      sprintf(EMAIL_GV_REDEEM, $coupon_code) .
      EMAIL_GV_LINK . zen_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . "\n\n" .
      EMAIL_GV_LINK_OTHER . EMAIL_SEPARATOR;
      $html_msg['GV_WORTH'] = str_replace('\n','',sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) );
      $html_msg['GV_REDEEM'] = str_replace('\n','',str_replace('\n\n','<br />',sprintf(EMAIL_GV_REDEEM, '<strong>' . $coupon_code . '</strong>')));
      $html_msg['GV_CODE_NUM'] = $coupon_code;
      $html_msg['GV_CODE_URL'] = str_replace('\n','',EMAIL_GV_LINK . '<a href="' . zen_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . '">' . TEXT_GV_NAME . ': ' . $coupon_code . '</a>');
      $html_msg['GV_LINK_OTHER'] = EMAIL_GV_LINK_OTHER;
    } // endif voucher

    // add in regular email welcome text
    $email_text .= "\n\n" . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_GV_CLOSURE;

    $html_msg['EMAIL_MESSAGE_HTML']  = str_replace('\n','',EMAIL_TEXT);
    $html_msg['EMAIL_CONTACT_OWNER'] = str_replace('\n','',EMAIL_CONTACT);
    $html_msg['EMAIL_CLOSURE']       = nl2br(EMAIL_GV_CLOSURE);

    // include create-account-specific disclaimer
    $email_text .= "\n\n" . sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, STORE_OWNER_EMAIL_ADDRESS). "\n\n";
    $html_msg['EMAIL_DISCLAIMER'] = sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, '<a href="mailto:' . STORE_OWNER_EMAIL_ADDRESS . '">'. STORE_OWNER_EMAIL_ADDRESS .' </a>');

    //send welcome email
    zen_mail($name, $email_address, EMAIL_SUBJECT, $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'welcome');

    // send additional emails
    if (SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO_STATUS == '1' and SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO !='') {
      if ($_SESSION['customer_id']) {
        $account_query = "select customers_firstname, customers_lastname, customers_email_address, customers_telephone, customers_fax
                            from " . TABLE_CUSTOMERS . "
                            where customers_id = '" . (int)$_SESSION['customer_id'] . "'";

        $account = $db->Execute($account_query);
      }

      $extra_info=email_collect_extra_info($name,$email_address, $account->fields['customers_firstname'] . ' ' . $account->fields['customers_lastname'], $account->fields['customers_email_address'], $account->fields['customers_telephone'], $account->fields['customers_fax']);
      $html_msg['EXTRA_INFO'] = $extra_info['HTML'];
      zen_mail('', SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO, SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO_SUBJECT . ' ' . EMAIL_SUBJECT,
      $email_text . $extra_info['TEXT'], STORE_NAME, EMAIL_FROM, $html_msg, 'welcome_extra');
    } //endif send extra emails

    zen_redirect(zen_href_link(FILENAME_CREATE_ACCOUNT_SUCCESS, '', 'SSL'));

  } //endif !error
}


/*
 * Set flags for template use:
 */
  $selected_country = (isset($_POST['zone_country_id']) && $_POST['zone_country_id'] != '') ? $country : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
  $selected_country_shipping = (isset($_POST['zone_country_id_shipping']) && $_POST['zone_country_id_shipping'] != '') ? $country_shipping : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;  
  $flag_show_pulldown_states = ((($process == true || $entry_state_has_zones == true) && $zone_name == '') || ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true' || $error_state_input) ? true : false;
  $flag_show_pulldown_states_shipping = ((($process_shipping == true || $entry_state_has_zones_shipping == true) && $zone_name_shipping == '') || ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true' || $error_state_input_shipping) ? true : false;
  $state = ($flag_show_pulldown_states) ? ($state == '' ? '&nbsp;' : $state) : $zone_name;
  $state_shipping = ($flag_show_pulldown_states_shipping) ? ($state_shipping == '' ? '&nbsp;' : $state_shipping) : $zone_name_shipping;
  $state_field_label = ($flag_show_pulldown_states) ? '' : ENTRY_STATE;
  $state_field_label_shipping = ($flag_show_pulldown_states_shipping) ? '' : ENTRY_STATE;

// This should be last line of the script:
$zco_notifier->notify('NOTIFY_MODULE_END_CREATE_ACCOUNT');
?>