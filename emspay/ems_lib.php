<?php

class Ems_Services_Lib
{
    public $debugMode;
    public $logTo;
    public $apiKey;
    public $apiEndpoint;
    public $apiVersion;
    public $debugCurl;

    public function __construct($apiKey, $logTo, $debugMode)
    {
        $this->debugMode   = $debugMode;
        $this->logTo       = $logTo;
        $this->apiKey      = $apiKey;
        $this->apiEndpoint = 'https://api.online.emspay.eu';
        $this->apiVersion  = "v1";

        $this->debugCurl   = false;

        $this->plugin_version = 'osc-2.0.0';
    }

    public function emsLog($contents)
    {
        if ($this->logTo == 'file') {
            $file = dirname(__FILE__) . '/inglog.txt';
            file_put_contents($file, date('Y-m-d H.i.s') . ": ", FILE_APPEND);

            if (is_array($contents)) {
                $contents = var_export($contents, true);
            } elseif (is_object($contents)) {
                $contents = json_encode($contents);
            }

            file_put_contents($file, $contents . "\n", FILE_APPEND);
        } else {
            error_log($contents);
        }
    }

    public function performApiCall($api_method, $post = false)
    {
        $url = implode("/", array($this->apiEndpoint, $this->apiVersion, $api_method));

        $curl = curl_init($url);

        $length = 0;
        if ($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            $length = strlen($post);
        }

        $request_headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "User-Agent: gingerphplib",
            "X-Ginger-Client-Info: " . php_uname(),
            "Authorization: Basic " . base64_encode($this->apiKey . ":"),
            "Connection: close",
            "Content-length: " . $length,
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 2 = to check the existence of a common name and also verify that it matches the hostname provided. In production environments the value of this option should be kept at 2 (default value).
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);

        if ($this->debugCurl) {
            curl_setopt($curl, CURLOPT_VERBOSE, 1); // prevent caching issues
            $file = dirname(__FILE__) . '/ingcurl.txt';
            $file_handle = fopen($file, "a+");
            curl_setopt($curl, CURLOPT_STDERR, $file_handle); // prevent caching issues
        }

        $responseString = curl_exec($curl);

        if ($responseString == false) {
            $response = array('error' => curl_error($curl));
        } else {
            $response = json_decode($responseString, true);

            if (!$response) {
                $this->emsLog('invalid json: JSON error code: ' . json_last_error() . "\nRequest: " . $responseString);
                $response = array('error' =>  'Invalid JSON');
            }
        }
        curl_close($curl);

        return $response;
    }

    public function emsGetIssuers()
    {
        // API Request to ING to fetch the issuers
        return $this->performApiCall("ideal/issuers/");
    }

    public function emsCreateIdealOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url, $issuer_id )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' 		  => $customer,
            "description"       => (string)$description,
            "return_url"        => (string)$return_url,
            "transactions"      => [
                [
                    "payment_method"         => "ideal",
                    "payment_method_details" => array("issuer_id" => $issuer_id)
                ]
            ],
            'extra' => [
                'plugin' => $this->plugin_version,
            ],
        ];

        if ($webhook_url != null)
            $post['webhook_url'] = $webhook_url;

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function emsCreateOrder($orders_id, $total, $description, $customer, $webhook_url, $payment_id, $return_url = '')
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' 		  => $customer,
            "description"       => (string)$description,
            "return_url"        => (string)$return_url,
            "transactions"      => [
                [
                    "payment_method"         => $this->plugin_version,
                ]
            ],
            'extra' => [
                'plugin' => $this->plugin_version,
            ],
        ];

	  if ($return_url != null)
		$post['return_url'] = $return_url;

        if ($webhook_url != null)
            $post['webhook_url'] = $webhook_url;

        $order = json_encode($post);

        return $this->performApiCall("orders/", $order);
    }

    public function emsCreateOrderWithOrderLines($orders_id, $total, $description, $customer, $webhook_url, $payment_id, $return_url, $order_lines)
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' 		  => $customer,
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => $payment_id,
                ]
            ],
            'extra' => [
                'plugin' => $this->plugin_version,
            ],
		'order_lines' 	  => $order_lines,
        ];

        if ($webhook_url != null)
            $post['webhook_url'] = $webhook_url;

        $order = json_encode($post);

        return $this->performApiCall("orders/", $order);
    }

    public function getOrderStatus($order_id)
    {
        $order = $this->performApiCall("orders/" . $order_id . "/");

        if (!is_array($order) || array_key_exists('error', $order)) {
            return 'error';
        }
        else {
            return $order['status'];
        }
    }

    public function getOrderDetails($order_id)
    {
        $order = $this->performApiCall("orders/" . $order_id . "/");

        if (!is_array($order) || array_key_exists('error', $order)) {
            return 'error';
        }
        else {
            return $order;
        }
    }

    public function getCustomerInfo($gender = '', $birthdate = '')
    {
	  global $order, $languages_id, $customer_id;

	  if (empty($gender)||empty($birthdate)) {
		// check if it's not english
		$language_row = tep_db_fetch_array(tep_db_query("SELECT * FROM languages WHERE languages_id = '" . $languages_id . "'"));
		$customer_data_not_in_customer_object = tep_db_fetch_array(tep_db_query("SELECT customers_dob, customers_gender FROM customers WHERE customers_id = '" . (int)$customer_id . "'"));

		if (empty($gender)) {
		    $gender = $customer_data_not_in_customer_object['customers_gender'] == "f" ? 'female' : 'male';
		}

		if (empty($birthdate)) {
		    $birthdate = date("Y-m-d", strtotime($customer_data_not_in_customer_object['customers_dob']));
		}
	  }

	  return array(
		  'email_address' => !empty($order->customer['email_address']) ? (string)$order->customer['email_address'] : null,
		  'first_name' => !empty($order->customer['firstname']) ? (string)$order->customer['firstname'] : null,
		  'last_name' => !empty($order->customer['lastname']) ? (string)$order->customer['lastname'] : null,
		  'address_type' => 'customer',
		  'address' => !empty($order->customer['street_address'] . "\n" . $order->customer['postcode'] . ' ' . $order->customer['city']) ? (string)($order->customer['street_address'] . "\n" . $order->customer['postcode'] . ' ' . $order->customer['city']) : null,
		  'postal_code' => !empty($order->customer['postcode']) ? (string)$order->customer['postcode'] : null,
		  'country' => !empty($order->customer['country']['iso_code_2']) ? (string)$order->customer['country']['iso_code_2'] : null,
		  'locale' => $language_row['code'] == 'en' ? 'en_GB' : 'nl_NL',
		  'phone_numbers' => !empty($order->customer['telephone']) ? [(string)$order->customer['telephone']] : null,
		  'ip_address' => !empty(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) ? (string)filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) : null,
		  'gender' => $gender,
		  'birthdate' => $birthdate,
	  );
    }
}