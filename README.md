# EMS Online plugin for OsCommerce
This is the offical EMS Online plugin for OsCommerce

## About
By integrating your webshop with EMS Online you can accept payments from your customers in an easy and trusted manner with all relevant payment methods supported.

## Version number
Version 1.0.0

## Pre-requisites to install the plug-ins: 
- PHP v5.4 and above
- MySQL v5.4 and above

## Installation
Upload this extention to your OsCommerce extensions and use the settings to enable the payment methods.

Are you going to offer Bank transfer in your webshop? Please do the following modification to ensure that the payment reference is mentioned in the e-mail to your client. This payment reference is required to be able to process the payment from your customer.

Edit /checkout_process.php

After:
if (isset($payment_class->email_footer)) {
    $email_order .= $payment_class->email_footer . "\n\n";
  }
}

Add:
if ($payment_modules->selected_module == "emspay_banktransfer") {
  $payment_modules->after_process();
}

It will look like:
if (isset($payment_class->email_footer)) {
    $email_order .= $payment_class->email_footer . "\n\n";
  }
}

if ($payment_modules->selected_module == "emspay_banktransfer") {
  $payment_modules->after_process();
}