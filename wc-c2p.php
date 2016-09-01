<?php
/*Plugin Name: WooCommerce Cointopay.com
Plugin URI: https://cointopay.com
Description: WooCommerce crypto payment gateway
Version: 0.1
Author: Cointopay.com
Author URI: https://cointopay.com*/
add_filter('woocommerce_payment_gateways', 'woocommerce_add_C2P_gateway');
add_action('plugins_loaded', 'woocommerce_C2P_init', 0);

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
/**
* Add the Gateway to WooCommerce
**/
	function woocommerce_add_C2P_gateway($methods)
	{
		$methods[] = 'WC_C2P';
		return $methods;
	}
	function woocommerce_C2P_init()
	{
		if (!class_exists('WC_Payment_Gateway'))
		{
			return;
		}
		class WC_C2P extends WC_Payment_Gateway
		{
			public function __construct()
			{
				$this->id = 'C2P';
				$this->icon = plugins_url( 'images/crypto.png', __FILE__ );
				$this->has_fields = false;
				$this->init_form_fields();
				$this->init_settings();
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];
				$this->altcoinid = $this->settings['altcoinid'];
				$this->merchantid = $this->settings['merchantid'];
				$this->secret = $this->settings['secret'];
				$this->debug = $this->settings['debug'];
				$this->msg['message'] = "";
				$this->msg['class'] = "";
				add_action('init', array(&$this, 'check_C2P_response'));
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_C2P_response' ) );

				$this->enabled = (($this->settings['enabled'] && !empty($this->merchantid) && !empty($this->secret)) ? 'yes' : 'no');

				$this->altcoinid == '' ? add_action( 'admin_notices', array( &$this, 'altcoinid_missing_message' ) ) : '';

				$this->merchantid == '' ? add_action( 'admin_notices', array( &$this, 'merchantid_missing_message' ) ) : '';

				$this->secret == '' ? add_action( 'admin_notices', array( &$this, 'secret_missing_message' ) ) : '';
			}
			function init_form_fields()
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'C2P' ),
						'type' => 'checkbox',
						'label' => __( 'Enable Cointopay', 'C2P' ),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __( 'Title', 'Cointopay Crypto Payment' ),
						'type' => 'text',
						'description' => __( 'This controls the title the user can see during checkout.', 'C2P' ),
						'default' => __( 'Crypto Checkout', 'C2P' )
					),
					'description' => array(
						'title' => __( 'Description', 'C2P' ),
						'type' => 'textarea',
						'description' => __( 'This controls the title the user can see during checkout.', 'C2P' ),
						'default' => __( 'You will be redirected to cointopay.com to complete your purchase. Do not forget to set PaymentConfirmationURL on Cointopay.com account: ' . get_site_url() . '/?wc-api=WC_C2P', 'C2P' )
					),
					'altcoinid' => array(
						'title' => __( 'Default Checkout AltCoinID', 'C2P' ),
						'type' => 'text',
						'description' => __( 'Please enter your Preferred AltCoinID (1 for bitcoin)', 'C2P' ) . ' ' . sprintf( __( 'You can get this information in: %sC2P Account%s.', 'C2P' ), '<a href=https://cointopay.com target=_blank>', '</a>' ),
						'default' => '1'
					),
					'merchantid' => array(
						'title' => __( 'Your MerchantID', 'C2P' ),
						'type' => 'text',
						'description' => __( 'Please enter your Cointopay Merchant ID', 'C2P' ) . ' ' . sprintf( __( 'You can get this information in: %sC2P Account%s.', 'C2P' ), '<a href=https://cointopay.com" target=_blank>', '</a>' ),
						'default' => ''
					),
					'secret' => array(
						'title' => __( 'SecurityCode', 'C2P' ),
						'type' => 'password',
						'description' => __( 'Please enter your Cointopay SecurityCode', 'C2P' ) . ' ' . sprintf( __( 'You can get this information in: %sCointopay Account%s.', 'C2P' ), '<a href=https://cointopay.com target=_blank>', '</a>' ),
						'default' => ''
					),
					'debug' => array(
						'title' => __( 'Debug Log', 'C2P' ),
						'type' => 'checkbox',
						'label' => __( 'Enable logging', 'C2P' ),
						'default' => 'no',
						'description' => __( 'Log Cointopay events, such as API requests, inside <code>woocommerce/logs/C2P.txt</code>', 'C2P' ),
					)
				);
			}
			public function admin_options()
			{
				?>
				<h3><?php _e('Cointopay Checkout', 'C2P');?></h3>
				<div id="wc_get_started">
					<span class="main"><?php _e('Provides a secure way to accept crypto currencies.', 'C2P'); ?></span>
					<p><a href="https://cointopay.com/index.jsp?#Register" target="_blank" class="button button-primary"><?php _e('Join free', 'C2P'); ?></a> <a href="https://cointopay.com" target="_blank" class="button"><?php _e('Learn more about WooCommerce and Cointopay', 'C2P'); ?></a></p>
				</div>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
				<?php
			}
			/**
			* There are no payment fields for Cointopay, but we want to show the description if set.
			**/
			function payment_fields()
			{
				if ($this->description)
					echo wpautop(wptexturize($this->description));
			}
			/**
			* Process the payment and return the result
			**/
			function process_payment($order_id)
			{
				$order = new WC_Order($order_id);
				$item_names = array();
				if (sizeof($order->get_items()) > 0) : foreach ($order->get_items() as $item) :
					if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
				endforeach; endif;
				$item_name = sprintf( __('Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode(', ', $item_names);
				//$ch = curl_init();
				//curl_setopt_array($ch, array(
				//CURLOPT_URL => 'https://cointopay.com/MerchantAPI',
				//CURLOPT_POSTFIELDS => 'Checkout=true&SecurityCode=' . $this->secret . '&MerchantID=' . $this->merchantid . '&Amount=' . number_format($order->order_total, 8, '.', '') . '&AltCoinID=' . $this->altcoinid . '&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $order->id . '&item=' . $item_name . '&custom=' . json_encode(array(
				//	'email' => $order->billing_email,
				//	'order_id' => $order->id,
				//	'order_key' => $order->order_key,
				//	'returnurl' => rawurlencode(esc_url($this->get_return_url($order))),
				//	'callbackurl' => get_site_url() . '/?wc-api=WC_C2P',
				//	'cancelurl' => rawurlencode(esc_url($order->get_cancel_order_url())))
				//),
				//CURLOPT_RETURNTRANSFER => true));
				//$redirect = curl_exec($ch);
				//curl_close($ch);
				$fullURL='https://cointopay.com/MerchantAPI?Checkout=true&SecurityCode=' . $this->secret . '&MerchantID=' . $this->merchantid . '&Amount=' . number_format($order->order_total, 8, '.', '') . '&AltCoinID=' . $this->altcoinid . '&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $order->id . ''; //$redirect

				return array(
					'result' => 'success',
					'redirect' => $fullURL
				);
			}
			/**
			* Check for valid C2P server callback
			**/
			function check_C2P_response()
			{
				$C2P = $_GET;
				$order_id = intval($C2P['CustomerReferenceNr']);

				if ($C2P['CustomerReferenceNr'] !== $order_id)
				{
					//if ($this->debug=='yes') $this->log->add( 'C2P', 'Error: Order does not match.' );
				}

				//if ( $C2P['status'] == 'paid' && $C2P['ConfirmCode'] !== '') {
				if ( $C2P['status'] == 'paid' ) {
					// Do your magic here, and return 200 OK to Cointopay.

					$order = new WC_Order($order_id);
					//if ($order->order_id == $order_id)
					//{
							// Validate Amount
							if ($C2P['TransactionID'] !== '')
							{
								// Payment completed
								$order->add_order_note( __('IPN: Payment completed notification from Cointopay', 'woocommerce') );
								$order->payment_complete();
								//if ($this->debug=='yes') $this->log->add( 'C2P', 'Payment complete.' );
							}
							else
							{
								if ($this->debug == 'yes')
								{
									$this->log->add( 'C2P', 'Payment error: TransactionID is null' );
								}
								// Put this order on-hold for manual checking
								$order->update_status( 'on-hold', sprintf( __( 'IPN: Validation error, Something went wrong.', 'woocommerce' ), $C2P['fiat']['amount'] ) );
							}
					//}
					header('Location: '.get_site_url());
					exit;
				}
			}
			/**
			 * Adds error message when not configured the AltCoinID.
			 *
			 * @return string Error Mensage.
			 */
			public function altcoinid_missing_message() {
				$message = '<div class="error">';
					$message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should enter your AltCoinID in Cointopay configuration. %sClick here to configure!%s' , 'wcC2P' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=wc_C2P">', '</a>' ) . '</p>';
				$message .= '</div>';
				echo $message;
			}
			/**
			 * Adds error message when not configured the MerchantID.
			 *
			 * @return string Error Mensage.
			 */
			public function merchantid_missing_message() {
				$message = '<div class="error">';
					$message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should enter your MerchantID in Cointopay configuration. %sClick here to configure!%s' , 'wcC2P' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=wc_C2P">', '</a>' ) . '</p>';
				$message .= '</div>';
				echo $message;
			}
			/**
			 * Adds error message when not configured the secret.
			 *
			 * @return String Error Mensage.
			 */
			public function secret_missing_message() {
				$message = '<div class="error">';
					$message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should check your SecurityCode in Cointopay configuration. %sClick here to configure!%s' , 'wcC2P' ), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=wc_C2P">', '</a>' ) . '</p>';
				$message .= '</div>';
				echo $message;
			}
		}
	}
}