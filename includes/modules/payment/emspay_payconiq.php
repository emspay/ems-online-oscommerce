<?php
class emspay_payconiq {
  var $code, $title, $description, $sort_order, $enabled, $debug_mode, $log_to, $emspay, $id;

  // Class Constructor
  function emspay_payconiq() {
    global $order;

    $this->code = 'emspay_payconiq';
    $this->id = 'payconiq';
    $this->title_selection = MODULE_PAYMENT_EMSPAY_PAYCONIQ_TEXT_TITLE;
    $this->title = 'EMS Online ' . $this->title_selection;
    $this->description = MODULE_PAYMENT_EMSPAY_PAYCONIQ_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_PAYMENT_EMSPAY_PAYCONIQ_SORT_ORDER;
    $this->enabled = ( ( MODULE_PAYMENT_EMSPAY_PAYCONIQ_STATUS == 'True' ) ? true : false );
    $this->debug_mode = ( ( MODULE_PAYMENT_EMSPAY_DEBUG_MODE == 'True' ) ? true : false );
    $this->log_to = MODULE_PAYMENT_EMSPAY_LOG_TO;

    if ( (int)MODULE_PAYMENT_EMSPAY_ORDER_STATUS_ID > 0 ) {
      $this->order_status = MODULE_PAYMENT_EMSPAY_ORDER_STATUS_ID;
      $payment = 'emspay_payconiq';
    } else if ( $payment=='emspay_payconiq' ) {
        $payment='';
      }
    if ( is_object( $order ) ) {
      $this->update_status();
    }

    $this->emspay = null;
    if ($this->enabled) {
      if ( file_exists( 'emspay/ems_lib.php' ) ) {
        require_once 'emspay/ems_lib.php';
        $this->emspay = new Ems_Services_Lib( MODULE_PAYMENT_EMSPAY_APIKEY, $this->log_to, $this->debug_mode );
      } else {
        // TODO: SHOULD GIVE WARNING
      }
    }
  }

  // Class Methods
  function update_status() {
    global $order;

    if ( ( $this->enabled == true ) && ( (int)MODULE_PAYMENT_EMSPAY_PAYCONIQ_ZONE > 0 ) ) {
      $check_flag = false;
      $check_query = tep_db_query( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . intval( MODULE_PAYMENT_EMSPAY_PAYCONIQ_ZONE ) . "' and zone_country_id = '" . intval( $order->billing['country']['id'] ) . "' order by zone_id" );
      while ( $check = tep_db_fetch_array( $check_query ) ) {
        if ( $check['zone_id'] < 1 ) {
          $check_flag = true;
          break;
        }
        elseif ( $check['zone_id'] == $order->billing['zone_id'] ) {
          $check_flag = true;
          break;
        }
      }

      if ( $check_flag == false ) {
        $this->enabled = false;
      }
    }

    if ( $order->info['currency'] != "EUR" ) {
      $this->enabled = false;
    }

    // check that api key is not blank
    if ( !MODULE_PAYMENT_EMSPAY_APIKEY or !strlen( MODULE_PAYMENT_EMSPAY_APIKEY ) ) {
      print 'no secret '.MODULE_PAYMENT_EMSPAY_APIKEY;
      $this->enabled = false;
    }
  }

  function javascript_validation() {
    return false;
  }

  function selection() {
    $selection['id'] = $this->code;
    $selection['module'] = $this->title_selection;
    return $selection;
  }

  function pre_confirmation_check() {
  }

  function confirmation() {
    return false;
  }

  function process_button() {
    return false;
  }

  function before_process() {
    return false;
  }

  function after_process() {
    global $insert_id, $order;

    $webhook_url = null;
    if (MODULE_PAYMENT_EMSPAY_SEND_IN_WEBHOOK == "True")
      $webhook_url =  tep_href_link( "ext/modules/payment/emspay/notify.php", '', 'SSL' );

    $customer = $this->emspay->getCustomerInfo();
    $emspay_order = $this->emspay->emsCreateOrder( $insert_id,
	    							   $order->info['total'],
	    							   STORE_NAME . " " . $insert_id,
	    							   $customer,
	    							   $webhook_url,
	    							   $this->id,
	    							   tep_href_link( "ext/modules/payment/emspay/redir.php", '', 'SSL' )
								 );

    // change order status to value selected by merchant
    tep_db_query( "update ". TABLE_ORDERS. " set orders_status = " . intval( MODULE_PAYMENT_EMSPAY_NEW_STATUS_ID ) . ", emspay_order_id = '" . $emspay_order['id']  . "' where orders_id = ". intval( $insert_id ) );

    $this->emspay->emsLog( $emspay_order );

    if ( !is_array( $emspay_order ) or array_key_exists( 'error', $emspay_order) or $emspay_order['status'] == 'error' ) {
      // TODO: Remove this? I don't know if I like it removing orders, or make it optional
      $this->tep_remove_order( $insert_id, $restock = true );
      tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( "Error placing emspay order" ), 'SSL' ) );
    }
    else {
      tep_redirect( $emspay_order['transactions'][0]['payment_url'] );
    }
    return false;
  }

  function get_error() {
    return false;
  }

  function check() {
    if ( !isset( $this->_check ) ) {
      $check_query = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_EMSPAY_PAYCONIQ_STATUS'" );
      $this->_check = tep_db_num_rows( $check_query );
    }
    return $this->_check;
  }

  function tableColumnExists($table_name, $column_name) {
    $check_q = tep_db_query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . $table_name . "' AND COLUMN_NAME = '" . $column_name ."'");
    return tep_db_num_rows($check_q);
  }

  function install() {

    // ADD EMSPAY ORDER ID TO THE ORDERS TABLE
    if (!$this->tableColumnExists("orders", "emspay_order_id")) {
      if (!tep_db_query("ALTER TABLE orders ADD emspay_order_id VARCHAR( 36 ) NULL DEFAULT NULL ;")) {
        die("To be able to work; please add the column emspay_order_id (VARCHAR 36, DEFAULT NULL) to your order table");
      }
    }

    $sort_order = 0;
    $add_array = array(
      "configuration_title" => 'Enable EMS Online Payconiq Module',
      "configuration_key" => 'MODULE_PAYMENT_EMSPAY_PAYCONIQ_STATUS',
      "configuration_value" => 'False',
      "configuration_description" => 'Do you want to accept Payconiq payments using EMS Online?',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('True', 'False'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Payment Zone',
      "configuration_key" => 'MODULE_PAYMENT_EMSPAY_PAYCONIQ_ZONE',
      "configuration_value" => 0,
      "configuration_description" => 'If a zone is selected, only enable this payment method for that zone.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_zone_classes(",
      "use_function" => "tep_get_zone_class_title",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Sort Order of Display',
      "configuration_key" => 'MODULE_PAYMENT_EMSPAY_PAYCONIQ_SORT_ORDER',
      "configuration_value" => 0,
      "configuration_description" => 'Sort order of display. Lowest is displayed first.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

  }

  function remove() {
    tep_db_query( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
  }

  function keys() {
    return array(
      'MODULE_PAYMENT_EMSPAY_PAYCONIQ_STATUS',
      'MODULE_PAYMENT_EMSPAY_PAYCONIQ_ZONE',
      'MODULE_PAYMENT_EMSPAY_PAYCONIQ_SORT_ORDER',
    );
  }

  function tep_remove_order( $order_id, $restock = false ) {
    if ( $restock == 'on' ) {
      $order_query = tep_db_query( "select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'" );
      while ( $order = tep_db_fetch_array( $order_query ) ) {
        tep_db_query( "update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order['products_quantity'] . ", products_ordered = products_ordered - " . $order['products_quantity'] . " where products_id = '" . (int)$order['products_id'] . "'" );
      }
    }

    tep_db_query( "delete from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "'" );
  }

}