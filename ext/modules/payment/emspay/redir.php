<?php
chdir('../../../../');

require_once 'emspay/ems_lib.php';
require_once 'includes/application_top.php';

// include the language translations
require DIR_WS_LANGUAGES . $language  . '/modules/payment/emspay.php';

$emspay = new Ems_Services_Lib( MODULE_PAYMENT_EMSPAY_APIKEY, MODULE_PAYMENT_EMSPAY_LOG_TO, MODULE_PAYMENT_EMSPAY_DEBUG_MODE == 'True' );
$status = $emspay->getOrderStatus( $_GET['order_id'] );

// only redirection is done here; no order status processing. Order status processing is done in notify

switch ( $status ) {
	case 'completed':
		$_SESSION['cart']->reset( true );
		tep_redirect( tep_href_link( FILENAME_CHECKOUT_SUCCESS, '', "SSL" ) );
		break;
	case 'cancelled':
		tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( EMSPAY_MESSAGE_PAYMENT_CANCELLED ), "SSL" ) );
		break;
	case 'expired':
		tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( EMSPAY_MESSAGE_PAYMENT_EXPIRED ), "SSL" ) );
		break;
	case 'processing':
	case 'see-transactions':
		// sometimes the payment method response is delayed; we'll check 2 times with 1 second delay
		if ( !empty( $_GET['try'] ) && $_GET['try'] >= 2 )
			tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( EMSPAY_MESSAGE_PAYMENT_UNKNOWN ), "SSL" ) );

		// delay 1 second, and retry
		sleep( 1 );
		$try = ( empty( $_GET['try'] ) ? 1 : ( $_GET['try'] + 1 ) );
		tep_redirect( tep_href_link( "ext/modules/payment/emspay/redir.php", tep_get_all_get_params( array( "try" ) ) . "try=" . $try ), "SSL" );
		break;
	case 'error':
		tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( EMSPAY_MESSAGE_PAYMENT_ERROR ), "SSL" ) );
		break;
	default:
		tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( EMSPAY_MESSAGE_PAYMENT_UNKNOWN ) . " " . $status, "SSL" ) );
		break;
}
