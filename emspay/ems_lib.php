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

        $this->plugin_version = 'osc-1.0.0';
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
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
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

    public function emsCreateKlarnaOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url, $order_lines)
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'birthdate'     => !empty($customer['birthdate']) ? (string)$customer['birthdate'] : null,
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'ip_address'    => !empty($customer['ip_address']) ? (string)$customer['ip_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'gender'        => !empty($customer['gender'   ]) ? (string)$customer['gender'   ] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
                'phone_numbers' => array($customer['phone_number']),
            ],                 
            "description"       => (string)$description,
            "return_url"        => (string)$return_url,
            "transactions"      => [
                [
                    "payment_method"         => "klarna",
                ]
            ],
            'extra' => [
                'plugin' => $this->plugin_version,
            ],
            'order_lines' => $order_lines,
        ];

        if ($webhook_url != null)
            $post['webhook_url'] = $webhook_url;

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function emsCreateCreditCardOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => "credit-card",
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
    public function emsCreateApplePayOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => "apple-pay",
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

    public function emsCreateBcOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => "bancontact",
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

    public function emsCreatePayconiqOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url)
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => "payconiq",
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

    public function emsCreatePaypalOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "paypal",
                )
            ),
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

    public function emsCreateSofortOrder($orders_id, $total, $description, $customer, $webhook_url, $return_url )
    {
        $post = [
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => [
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ],
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => [
                [
                    "payment_method" => "sofort",
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

    public function emsCreateBanktransferOrder($orders_id, $total, $description, $customer = array(), $webhook_url)
    {
        $post = [
            "type"         => "payment",
            "currency"     => "EUR",
            "amount"       => 100 * round($total, 2),
            "description"  => (string)$description,
            "transactions" => [
                [
                    "payment_method" => "bank-transfer",
                ]
            ],
            "merchant_order_id" => (string)$orders_id,
			'customer' => [
	            'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
	            'address_type'  => 'customer',
	            'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
	            'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
	            'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
	            'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
	            'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
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
}