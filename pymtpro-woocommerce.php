<?php
/**
 * Plugin Name: pymtpro-woocommerce
 * Plugin URI: https://github.com/PYMTGlobal/
 * Description: Accept ION and Bitcoin on your WooCommerce-powered website with PYMTPro.
 * Version: 00.01.00
 * Author: PYMTPro.com
 * Author URI: https://www.PYMTPro.com
 * License: MIT
 * Text Domain: pymtpro-woocommerce
 */

/*  Copyright 2017 PYMTPro.com.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	function pymtpro_woocommerce_init()
	{

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * PYMTPro Payment Gateway
		 *
		 * Provides a PYMTPro Payment Gateway.
		 *
		 * @class       WC_Gateway_PYMTPro
		 * @extends     WC_Payment_Gateway
		 * @version     00.01.00
		 * @author      PYMTPro.com
		 */
		class WC_Gateway_PYMTPro extends WC_Payment_Gateway
		{
			var $notify_url;

			public function __construct()
			{
				$this->id   = 'pymtpro';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/pymtpro.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Proceed to PYMTPro', 'pymtpro-woocommerce');
				$this->notify_url        = $this->construct_notify_url();

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
				$this->description = $this->get_option('description');

				// Actions
				add_action(
					'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				)
				);
				add_action(
					'woocommerce_receipt_pymtpro', array(
					$this,
					'receipt_page'
				)
				);

				// Payment listener/API hook
				add_action(
					'woocommerce_api_wc_gateway_pymtpro', array(
					$this,
					'check_pymtpro_callback'
				)
				);
			}

			public function admin_options()
			{
				echo '<h3>' . __('PYMTPro Payment Gateway', 'pymtpro-woocommerce') . '</h3>';
				$pymt_account_email = get_option("pymt_account_email");
				$pymt_error_message = get_option("pymt_error_message");
				if ($pymt_account_email != false) {
					echo '<p>' . __('Successfully connected PYMTPro.com account: ', 'PYMTPro-woocommerce') . $pymt_account_email . '</p>';
				} elseif ($pymt_error_message != false) {
					echo '<p>' . __('Could not validate API Key: ', 'PYMTPro-woocommerce') . $pymt_error_message . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function process_admin_options()
			{
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'pymtpro' . DIRECTORY_SEPARATOR . 'pymtpro.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					$PYMTPro = PYMTPro::withApiToken($api_key, $api_secret);
					$user    = $PYMTPro->getUser();
					update_option("pymt_account_email", $user->email);
					update_option("pymt_error_message", false);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("pymt_account_email", false);
					update_option("pymt_error_message", $error_message);
					return;
				}
			}

			function construct_notify_url()
			{
				$callback_secret = get_option("pymt_callback_secret");
				if ($callback_secret == false) {
					$callback_secret = sha1(openssl_random_pseudo_bytes(20));
					update_option("pymt_callback_secret", $callback_secret);
				}
				$notify_url = WC()->api_request_url('WC_Gateway_PYMTPro');
				$notify_url = add_query_arg('pymt_secret', $callback_secret, $notify_url);
				return $notify_url;
			}

			function init_form_fields()
			{
				$this->form_fields = array(
					'enabled'     => array(
						'title'   => __('Enable PYMTPro plugin', 'pymtpro-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Show ion as an option to customers during checkout?', 'PYMTPro-woocommerce'),
						'default' => 'yes'
					),
					'title'       => array(
						'title'       => __('Title', 'woocommerce'),
						'type'        => 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default'     => __('PYMTPro-ION', 'pymtpro-woocommerce')
					),
					'description' => array(
						'title'       => __('Description', 'woocommerce'),
						'type'        => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
						'default'     => __('Pay with ION, a virtual currency.', 'pymtpro-woocommerce')
										 . " <a href='http://ionomy.com/' target='_blank'>"
										 . __('What is ION?', 'pymtpro-woocommerce')
										 . "</a>"
					),
					'apiKey'      => array(
						'title'       => __('API Token', 'pymtpro-woocommerce'),
						'type'        => 'text',
						'description' => __('')
					),
					'apiSecret'   => array(
						'title'       => __('API Secret', 'pymtpro-woocommerce'),
						'type'        => 'password',
						'description' => __('')
					)
				);
			}

			function process_payment($order_id)
			{

				require_once(plugin_dir_path(__FILE__) . 'pymtpro' . DIRECTORY_SEPARATOR . 'pymtpro.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_pymtpro', true, $this->get_return_url($order));

				// PYMTPro mangles the order param so we have to put it somewhere else and restore it on init
				$cancel_url = $order->get_cancel_order_url_raw();
				$cancel_url = add_query_arg('return_from_pymtpro', true, $cancel_url);
				$cancel_url = add_query_arg('cancelled', true, $cancel_url);
				$cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);

				$params = array(
					'coin'               => 'ion',
					'name'               => 'Order #' . $order_id,
					'description'        => '',
					'style'              => 'PYMTPro',
					'price_string'       => $order->get_total(),
					'price_currency_iso' => get_woocommerce_currency(),
					'callback_url'       => $this->notify_url,
					'custom'             => $order_id,
					'success_url'        => $success_url,
					'cancel_url'         => $cancel_url,
				);

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				if ($api_key == '' || $api_secret == '') {
					$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'pymtpro-woocommerce'));
					return;
				}

				try {
					$PYMTPro = PYMTPro::withApiToken($api_key, $api_secret);
					$code    = $PYMTPro->createButtonWithOptions($params)->button->code;
				}
				catch (Exception $e) {
					$order->add_order_note(__('Error while processing ion payment:', 'pymtpro-woocommerce') . ' ' . var_export($e, TRUE));
					$woocommerce->add_error(__($e . ' Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'pymtpro-woocommerce'));
					return;
				}
				//$woocommerce->add_error(__('results: '.$code.'', 'pymtpro-woocommerce'));
				//return;
				//echo json_encode($PYMTPro);
				return array(
					'result'   => 'success',
					'redirect' => "https://pymtpro.com/order/$code"
				);
			}

			function check_pymtpro_callback()
			{
				$callback_secret = get_option("pymtpro_callback_secret");
				if ($callback_secret != false && $callback_secret == $_REQUEST['callback_secret']) {
					$post_body = json_decode(file_get_contents("php://input"));
					if (isset($post_body->order)) {
						$pymtpro_order = $post_body->order;
						$order_id      = $pymtpro_order->custom;
						$order         = new WC_Order($order_id);
					} else if (isset($post_body->payout)) {
						header('HTTP/1.1 200 OK');
						exit("PYMTPro Payout Callback Ignored");
					} else {
						header("HTTP/1.1 400 Bad Request");
						exit("Unrecognized PYMTPro Callback");
					}
				} else {
					header("HTTP/1.1 401 Not Authorized");
					exit("Spoofed callback");
				}

				// Legitimate order callback from PYMTPro
				header('HTTP/1.1 200 OK');
				// Add PYMTPro metadata to the order
				update_post_meta($order->id, __('PYMTPro Order ID', 'pymtpro-woocommerce'), wc_clean($pymtpro_order->id));
				if (isset($pymtpro_order->customer) && isset($pymtpro_order->customer->email)) {
					update_post_meta($order->id, __('PYMTPro Account of Payer', 'pymtpro-woocommerce'), wc_clean($pymtpro_order->customer->email));
				}

				switch (strtolower($pymtpro_order->status)) {

					case 'completed':

						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('PYMTPro payment completed', 'pymtpro-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('PYMTPro reports payment cancelled.', 'pymtpro-woocommerce'));
						break;

				}

				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_pymtpro_gateway($methods)
		{
			$methods[] = 'WC_Gateway_PYMTPro';
			return $methods;
		}

		function woocommerce_handle_pymtpro_return()
		{
			if (!isset($_GET['return_from_pymtpro']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled PYMTPro payment', 'pymtpro-woocommerce'));
				}
			}

			// pymtpro order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}

		add_action('init', 'woocommerce_handle_pymtpro_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_pymtpro_gateway');
	}

	add_action('plugins_loaded', 'pymtpro_woocommerce_init', 0);
}
