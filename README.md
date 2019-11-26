# EMS Online plugin for OsCommerce
This is the offical EMS Online plugin for OsCommerce

## About
By integrating your webshop with EMS Online you can accept payments from your customers in an easy and trusted manner with all relevant payment methods supported.

## Version number
Version 1.0.1

## Pre-requisites to install the plug-ins: 
- PHP v5.4 and above
- MySQL v5.4 and above

## Installation
1. Download the EMS Online plugin. This can be done via the Add-Ons page of osCommerce or via the merchant portal.

* Via the Add-Ons page  
  Choose the module ‘EMS-Online’ in osCommerce Add-Ons and click on ‘Download’

* Via the merchant portal  
  The plugin is located on the Service page under Open Source Plugins. Click on the ZIP link near osCommerce.

2. Upload the plugin to your osCommerce installation using sFTP software.

3. Are you going to offer Bank transfer in your webshop? Please do the following modification to ensure that the payment reference is mentioned in the e-mail to your client. This payment reference is required to be able to process the payment from your customer. 


  ```
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

This is the complete code:
    if (isset($payment_class->email_footer)) {
      $email_order .= $payment_class->email_footer . "\n\n";
    }
  }

  if ($payment_modules->selected_module == "emspay_banktransfer") {
    $payment_modules->after_process();
  }
```

4. Go to the osCommerce Admin panel and choose ‘Modules’ > ‘Payment’ > ‘Install Module’. Install the EMS Online modules.  
  Enable only those payment methods that you applied for and for which you have received a confirmation from us. 

5. Complete the configuration after the modules are installed. Select a module and click ‘Edit’.  
  * Enable the payment method  
  * Copy the API key.  
  * Configure the desired status and error codes or use the default set.
  * Select the sequence in which you want to offer the payment methods to your customers. The lowest number will be presented as first option. 
  * Klarna specific configuration
     * Copy the API Key of your test webshop in the Test API Key field.  
    When your Klarna application was approved an extra test webshop was created for you to use in your test with Klarna. The name of this webshop starts with ‘TEST Klarna’.
     * Test IP adresses.  
          If you are testing Klarna and want to make sure that Klarna is not offered on your paypage to your customers yet, you can enter the IP adresses you use in your test. Klarna will only be available for those IP addresses. Seperate the addresses with a semicolon (';'). 

6. Finally configure the webhook URL in the merchant portal.  
  Go to ‘Settings’ > ‘Webshops’ and select the relevant webshop. Click ‘Change details’ to enter your webhook. 

   Use the following webhook URL: `http(s)://www.example.com/ext/modules/payment/emspay/notify.php`.   
  You can check whether the webhook URL is correct by visiting it via your web browser. If the message ‘Only work to do if the status changed’ appears, the URL is correct.  

   Note: if you opt for https (recommended!) then you must have a working https certificate.

7. Usage  
  The orders associated with the different payment methods can be viewed in the osCommerce Admin control panel. The status set in the configuration indicates whether the payment for the order is complete.