<?php

class CustomerIO {
    var $errorMessage;
    var $errorCode;
    
    /**
     * Cache the information on the API location on the server
     */
    private $_apiUrl = 'https://track.customer.io/api/v1/customers/';
    
    /**
     * Default to a 300 second timeout on server calls
     */
    private $_timeout = 300; 
    
    /**
     * Default to a 8K chunk size
     */
    var $chunkSize = 8192;
    
    /**
     * Cache the user api_key so we only have to log in once per client instantiation
     */
    private $_apiKey;

    /**
     * Cache the user site_id so we only have to log in once per client instantiation
     */
    private $_siteId;

    /**
     * Cache the user api_key so we only have to log in once per client instantiation
     */
    private $_secure = false;
    
    /**
     * Connect to the Customer.io server.
     * 
     * @param string $apikey Your Customer.io api key
     * @param string $siteId Your Customer.io site id
     * @param string $secure Whether or not this should use a secure connection
     */
    function __construct($apikey = 'api-key', $siteId = 'site-id', $secure=false) {
        $this->secure = $secure;
        $this->_apiKey = $apikey;
        $this->_siteId = $siteId;
    }

    function setTimeout($seconds){
        if (is_int($seconds)){
            $this->timeout = $seconds;
            return true;
        }
    }
    function getTimeout(){
        return $this->timeout;
    }
    function useSecure($val){
        if ($val===true){
            $this->_secure = true;
        } else {
            $this->_secure = false;
        }
    }

    /**
     * Add new user to your account.
     * 
     * @param integer  $id         User id in your system
     * @param string   $email      User email address
     * @param datetime $createTime User create time in your system
     * @param array    $extraInfo  User extra info, like first_name, plan_name
     */
    function addUser($id, $email, $createTime, $extraInfo=array()) {
        $url = $this->_apiUrl . $id;

        if (!is_array($extraInfo)) {
            throw new Exception('$extraInfo param must be an array');
        }

        $extraInfo['email']      = $email;
        $extraInfo['created_at'] = $createTime;

        return $this->_callServer($url, $extraInfo, "PUT");
    }

    /**
     * Edit user data.
     * 
     * @param integer $id   User id in your system
     * @param array   $info User extra info, like first_name, plan_name
     */
    function EditUser($id, $email = '', $info=array()) {
        global $db;
        
        if ($email == '') {
            $customer = $db->execute("SELECT customers_email_address FROM ".TABLE_CUSTOMERS."
                                      WHERE customers_id = '".$id."'");
            $email = $customer->fields['customers_email_address'];
        }
        
        $url = $this->_apiUrl . $id;

        if (!is_array($info)) {
            throw new Exception('$extraInfo param must be an array');
        }
        
        $info['email']      = $email;

        return $this->_callServer($url, $info, "PUT");
    }

    /**
     * Trigger event.
     * 
     * @param integer $id    User id in your system
     * @param string  $name  Event name
     * @param string  $value Event value, can be null
     */
    function triggerEvent($id, $name, $data = array()) {
        $url = $this->_apiUrl . $id . '/events';

        if (!isset($name)) {
            throw new Exception('Event name can\t be null');
        }

        $info = array('name' => $name);
        if (isset($data)) {
            $info['data'] = $data;
        }

        return $this->_callServer($url, $info);
    }

    /**
     * Call customer.io server.
     * 
     * @param string $url Customer.io endpoint
     * @param array  $data  User info
     */
    private function _callServer($url, $data, $requestType="POST") {
        
        //  save log
        $fp = fopen(DIR_FS_CATALOG . 'logs/customerio/' . date('Ymdhis') . '.log', 'a');
        fwrite($fp, $url."\r\n");
        foreach ($data as $key => $value) {
            fwrite($fp, $key.' => '.$value."\r\n");
        }
        fwrite($fp, "\r\n");
        fclose($fp);
    
        $session = curl_init();

        curl_setopt($session, CURLOPT_URL, $url);
        curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($session, CURLOPT_HTTPGET, 1);
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, $requestType);
        curl_setopt($session, CURLOPT_VERBOSE, 1);
        curl_setopt($session, CURLOPT_POSTFIELDS, http_build_query($data));

        curl_setopt($session,CURLOPT_USERPWD, $this->_siteId . ":" . $this->_apiKey);

        if (!$this->_secure) {
            curl_setopt($session,CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($session, CURLOPT_SSLVERSION, 3);

        curl_exec($session);
        if(curl_errno($session))
        {
            $this->errorCode    = curl_errno($session);
            $this->errorMessage = curl_error($session);
            curl_close($session);
            return false;
        } else {
            curl_close($session);
            return true;
        }
    } 
    
}
?>
