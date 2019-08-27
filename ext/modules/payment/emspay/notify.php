<?php
chdir('../../../../');
require_once 'emspay/ems_lib.php';
require_once 'includes/application_top.php';

$emspay = new Ems_Services_Lib( MODULE_PAYMENT_EMSPAY_APIKEY, MODULE_PAYMENT_EMSPAY_LOG_TO, MODULE_PAYMENT_EMSPAY_DEBUG_MODE == 'True' );

$input = json_decode( file_get_contents( "php://input" ), true );

if ( !$input )
    die( "Invalid JSON" );

if ( in_array( $input['event'], array( "status_changed" ) ) ) {

    // get emspay order information
    $emspay_order = $emspay->getOrderDetails( $input['order_id'] );

    // if we can't retrieve the order; possible it was a Klarna order
    if ($emspay_order == 'error') {
        $emspay = new Ems_Services_Lib( MODULE_PAYMENT_EMSPAY_KLARNA_TEST_APIKEY, MODULE_PAYMENT_EMSPAY_LOG_TO, MODULE_PAYMENT_EMSPAY_DEBUG_MODE == 'True' );
        $emspay_order = $emspay->getOrderDetails( $input['order_id'] );
    }

    $orders_id = $emspay_order['merchant_order_id'];
    $status = $emspay_order['status'];

    // get current order status
    $orders_status_q = tep_db_query( "SELECT orders_status FROM orders WHERE orders_id = '" . tep_db_input( $orders_id ) . "'" );
    if ( !tep_db_num_rows( $orders_status_q ) ) {
        die( "ERROR: Unable to find order_id" . $orders_id );
    }
    $orders_status_r = tep_db_fetch_array( $orders_status_q );
    $orders_status_id = $orders_status_r['orders_status'];

    switch ( $status ) {
    case 'completed':
        // check if order already has completed status; if so do nothing
        if ($orders_status_id == MODULE_PAYMENT_EMSPAY_COMPLETE_STATUS_ID)
            die("Order already up-to-date");

        // check if current status is new or pending; only then update to completed
        $comments = "";
        $new_status = $orders_status_id;
        if ( in_array( $orders_status_id, array( MODULE_PAYMENT_EMSPAY_NEW_STATUS_ID, MODULE_PAYMENT_EMSPAY_PENDING_STATUS_ID ) ) ) {
            $new_status = MODULE_PAYMENT_EMSPAY_COMPLETE_STATUS_ID;
        } else {
            $comments = "Not updated; status is not in allowed updated status list. Current status: " . $orders_status_id;
        }
        tep_db_query( "UPDATE orders SET orders_status = '" . tep_db_input( $new_status ) . "' WHERE orders_id = '" . tep_db_input( $orders_id ) . "'" );
        $add_array = array(
            "orders_id" => $orders_id,
            "orders_status_id" => $new_status,
            "date_added" => 'now()',
            "customer_notified" => 0,
            "comments" => $comments,
        );
        tep_db_perform( "orders_status_history", $add_array );
        break;
    case 'cancelled':
    case 'expired':
    case 'error':
        // check if order already has cancelled status; if so do nothing
        if ($orders_status_id == MODULE_PAYMENT_EMSPAY_CANCELLED_STATUS_ID)
            die("Order already up-to-date");

        tep_db_query( "UPDATE orders SET orders_status = '" . tep_db_input( MODULE_PAYMENT_EMSPAY_CANCELLED_STATUS_ID ) . "' WHERE orders_id = '" . tep_db_input( $orders_id ) . "'" );
        $add_array = array(
            "orders_id" => $orders_id,
            "orders_status_id" => MODULE_PAYMENT_EMSPAY_CANCELLED_STATUS_ID,
            "date_added" => 'now()',
            "customer_notified" => 0,
            "comments" => "Ginger status: " . $status,
        );
        tep_db_perform( "orders_status_history", $add_array );
        break;        
    default:
        // do nothing
        break;
    }
}