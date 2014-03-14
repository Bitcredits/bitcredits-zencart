<?php

/**
 * @package paymentMethod
 */
class bitcredits {
  var $code, $title, $description, $enabled, $payment;
  
  // class constructor
  function bitcredits() {
    global $order;
    $this->code = 'bitcredits';
    $this->title = MODULE_PAYMENT_BITCREDITS_TEXT_TITLE;
    $this->description = MODULE_PAYMENT_BITCREDITS_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_PAYMENT_BITCREDITS_SORT_ORDER;
    $this->enabled = ((MODULE_PAYMENT_BITCREDITS_STATUS == 'True') ? true : false);

    if ((int)MODULE_PAYMENT_BITCREDITS_ORDER_STATUS_ID > 0) {
      $this->order_status = MODULE_PAYMENT_BITCREDITS_ORDER_STATUS_ID;
      $payment='bitcredits';
    } else if ($payment=='bitcredits') {
      $payment='';
    }

    if (is_object($order))
      $this->update_status();

    $this->email_footer = MODULE_PAYMENT_BITCREDITS_TEXT_EMAIL_FOOTER;
  }

  // class methods
  function update_status() {
    global $db;
    global $order;
          
    // check that api key is not blank
    if (!MODULE_PAYMENT_BITCREDITS_APIKEY OR !strlen(MODULE_PAYMENT_BITCREDITS_APIKEY)) {
      $this->enabled = false;
    }
  }

  function javascript_validation() {
    return false;
  }

  function selection() {
    return array('id' => $this->code,
                 'module' => $this->title);
  }

  function pre_confirmation_check() {
    return false;
  }

  // called upon requesting step 3
  function confirmation() {
    return false;
  }
  
  // called upon requesting step 3 (after confirmation above)
  function process_button() {   
    global $order;
    $endpoint = (preg_match('/^https?:\/\//', MODULE_PAYMENT_BITCREDITS_API_ENDPOINT) ? '' : 'https://' ). MODULE_PAYMENT_BITCREDITS_API_ENDPOINT;
    ?>  
    <div id="bitcredits-payment-box">Loading...</div>
    <script type="text/javascript">
    //<![CDATA[
    if (document.getElementById("BitC") == null) {
      var bitc=document.createElement('script');
      bitc.type='text/javascript';
      bitc.setAttribute("id", "BitC");
      bitc.src = '<?php echo $endpoint; ?>/v1/bitcredits.js';
      var s=document.getElementsByTagName('script')[0];
      s.parentNode.insertBefore(bitc,s);
    }
    window.BitCredits = window.BitCredits || [];
    window.BitCredits.push(['onConfigReady', function(){
      window.BitCredits.push(['setupZenCart', <?php echo $order->info['total']; ?>]);
    }]);
    //]]>
    </script>
    <?php

    return false;
  }

  // called upon clicking confirm
  function before_process() {
    global $order;
    if(!isset($_COOKIE['bitc'])){
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'));
    }

    $endpoint = (preg_match('/^https?:\/\//', MODULE_PAYMENT_BITCREDITS_API_ENDPOINT) ? '' : 'https://' ). MODULE_PAYMENT_BITCREDITS_API_ENDPOINT;
    $method = '/v1/accounts/token/'.$_COOKIE['bitc'];

    $ch = curl_init();
    $data_string = json_encode($data);
    curl_setopt($ch, CURLOPT_URL, $endpoint . $method);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
    curl_setopt($ch, CURLOPT_USERPWD, MODULE_PAYMENT_BITCREDITS_APIKEY.':'); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json'
    ));
    $result = curl_exec($ch);
    $res = json_decode($result, true);

    if($order->info['total'] > $res['account']['balance']){
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'));

      return false;
    }

    return true;
  }

  // called upon clicking confirm (after before_process and after the order is created)
  function after_order_create($order_id) {
    global $order, $db;
    
    if(!isset($_COOKIE['bitc'])){
      return false;
    }

    // change order status to value selected by merchant
   $db->Execute("update ". TABLE_ORDERS. " set orders_status = " . intval(MODULE_PAYMENT_BITCREDITS_UNPAID_STATUS_ID) . " where orders_id = ". intval($insert_id));

    $endpoint = (preg_match('/^https?:\/\//', MODULE_PAYMENT_BITCREDITS_API_ENDPOINT) ? '' : 'https://' ). MODULE_PAYMENT_BITCREDITS_API_ENDPOINT;
    $method = '/v1/transactions';
    $data = array(
        'api_key' => MODULE_PAYMENT_BITCREDITS_APIKEY,
        'src_token' => $_COOKIE['bitc'],
        'dst_account' => '/zencart/orders/'.intval($order_id),
        'dst_account_create' => true,
        'amount' => $order->info['total'],
        'data' => array(
            'email' => $order->customer['email_address'],
            'firstname' => $order->customer['firstname'],
            'lastname' => $order->customer['lastname'],
            'order_id' => 'zc-'.intval($order_id)
        )
    );

    $ch = curl_init();
    $data_string = json_encode($data);
    curl_setopt($ch, CURLOPT_URL, $endpoint . $method);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data_string)));
    $result = curl_exec($ch);
    $res = json_decode($result, true);        

    if(
        $res == null
     || !isset($res['status'])
    ){
        return false;
    }elseif($res['status'] == 'error'){
        if(isset($res['message'])){
            return false;
        }else{
            return false;
        }
    }

    return true;
  }

  function after_process() {
    global $insert_id, $db;

    $db->Execute("update ". TABLE_ORDERS. " set orders_status = " . intval(MODULE_PAYMENT_BITCREDITS_PAID_STATUS_ID) . " where orders_id = ". intval($insert_id));

    $_SESSION['cart']->reset(true);

    return true;
  }

  function get_error() {
    return false;
  }

  function check() {
    global $db;

    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BITCREDITS_STATUS'");
      $this->_check = $check_query->RecordCount();
    }

    return $this->_check;
  }

  function install() {
    global $db, $messageStack;

    if (defined('MODULE_PAYMENT_BITCREDITS_STATUS')) {
      $messageStack->add_session('Bitcredits module already installed.', 'error');
      zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=bitcredits', 'NONSSL'));
      return 'failed';
    }

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) "
    ."values ('Enable Bitcredits Module', 'MODULE_PAYMENT_BITCREDITS_STATUS', 'True', 'Do you want to accept bitcoin payments via bitcredits.io?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
    ."values ('API Key', 'MODULE_PAYMENT_BITCREDITS_APIKEY', '', 'Enter your API Key which you generated at bitcredits.com', '6', '0', now());");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
    ."values ('API Endpoint', 'MODULE_PAYMENT_BITCREDITS_API_ENDPOINT', 'https://api.bitcredits.io', 'Enter API Endpoint which you can find at bitcredits.com', '6', '0', now());");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
    ."values ('Unpaid Order Status', 'MODULE_PAYMENT_BITCREDITS_UNPAID_STATUS_ID', '" . intval(DEFAULT_ORDERS_STATUS_ID) .  "', 'Automatically set the status of unpaid orders to this value.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
    ."values ('Paid Order Status', 'MODULE_PAYMENT_BITCREDITS_PAID_STATUS_ID', '2', 'Automatically set the status of paid orders to this value.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
    ."values ('Sort order of display.', 'MODULE_PAYMENT_BITCREDITS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '2', now())");
  }

  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }

  function keys() {
    return array(
                 'MODULE_PAYMENT_BITCREDITS_STATUS', 
                 'MODULE_PAYMENT_BITCREDITS_APIKEY',
                 'MODULE_PAYMENT_BITCREDITS_API_ENDPOINT',
                 'MODULE_PAYMENT_BITCREDITS_UNPAID_STATUS_ID',
                 'MODULE_PAYMENT_BITCREDITS_PAID_STATUS_ID',
                 'MODULE_PAYMENT_BITCREDITS_SORT_ORDER',
                );
  }
}
