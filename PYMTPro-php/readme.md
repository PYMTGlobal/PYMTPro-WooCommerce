PYMTPro-php Version: 0.01.0
================

Accept ION on your PHP-powered website with PYMTPro.com. 


Installation
-------

If you don't have a PYMTPro account, sign up at https://pymtpro.com/register/.

USAGE  
-------

Get API Token and Secret From PYMTPro  https://pymtpro.com/panel/api

```
require_once(__DIR__.'/pymtpro.php');

$apiToken = '{GeneratedToken}';
$apiSecret = '{GeneratedSecret}';

$params = array(
	'coin'               => 'ion', //CryptoType ion, btc
	'name'               => 'Order Name', // Order Name
	'description'        => 'Order Description', // Order Description
	'style'              => 'PYMTPro', // Order Name
	'price_string'       => '5.00', // Price in USD if price_currency_iso is set to USD
	'price_currency_iso' => 'USD', // Currency USD, ION, BTC
	'callback_url'       => 'http://website.com/pymtpro/pymtpro_ipn.php', // PYMTPro IPN
	'custom'             => 'Custom Name',
	'success_url'        => 'http://website.com/ordersuccess.php', // Url to send user once order has been processed
	'cancel_url'         => 'http://website.com/ordercancel.php',  // Url to send user if user cancels order
);
 
$buyurl = 'https://pymtpro.com/order/';
$Gateway = PYMTPro::withApiToken($apiToken,$apiSecret);
$code = $Gateway->createButtonWithOptions($params)->button->code;

echo $buyurl.$code;
```
URL returned is what you want to display in a iframe or something for the enduser.

Call BackUrl
-------
  
```
$post_body = json_decode(file_get_contents("php://input"));
if (isset($post_body->order)) {
    $PYMTPro_order  = $post_body->order;
    $order_id       = $PYMTPro_order->custom;
    $order          = $PYMTPro_order->name;
} else if (isset($post_body->payout)) {
    header('HTTP/1.1 200 OK');
    exit("PYMTPro Payout Callback Ignored");
} else {
    header("HTTP/1.1 400 Bad Request");
    exit("Unrecognized PYMTPro Callback");
}
```