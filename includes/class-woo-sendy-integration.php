<?php
/**
 * WooCommerce Sendy Subscriptions
 *
 * @package  FWS_Woo_Sendy_Subscription_Integration
 * @category Integration
 * @author   Olaf Lederer
 */



class FWS_Woo_Sendy_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;

		$this->id                 = 'fws-woo-sendy';
		$this->method_title       = __( 'Sendy Subscription', 'fws-woo-sendy' );
		$this->method_description = __( 'Add buyers to your Sendy Mailer list', 'fws-woo-sendy' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->sendy_url          = $this->get_option( 'sendy_url' );
		$this->sendy_api          = $this->get_option( 'sendy_api' );
		$this->sendy_list        = $this->get_option( 'sendy_list' );
		$this->sendy_list_cust       = $this->get_option( 'sendy_list_customers' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_'.$this->id, array( $this, 'process_admin_options' ) );
        //add_action( 'woocommerce_order_status_processing', array( $this, 'add_to_sendy_mailer' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_to_sendy_mailer' ) );

	}


	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'sendy_url' => array(
				'title'             => __( 'Sendy URL', 'fws-woo-sendy' ),
				'type'              => 'text',
				'description'       => __( 'URL of your Sendy installtion', 'fws-woo-sendy' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'sendy_api' => array(
				'title'             => __( 'Sendy API key', 'fws-woo-sendy' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Your Sendy API key', 'fws-woo-sendy' ),
			),
			'sendy_list' => array(
				'title'             => __( 'Sendy Mailer List ID newsletter', 'fws-woo-sendy' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Name of your Sendy mailing list', 'fws-woo-sendy' ),
			),
			'sendy_list_customers' => array(
				'title'             => __( 'Sendy Mailer List ID for all customers', 'fws-woo-sendy' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Name of your Sendy mailing list (customers)', 'fws-woo-sendy' ),
			),

			'sendy_subscribe_text' => array(
				'title'             => __( 'Text for newsletter subscription', 'fws-woo-sendy' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The text for the subscription on the checkout page.', 'fws-woo-sendy' ),
			)
		);
	}


   public function add_to_sendy_mailer( $order_id) {
		global $woocommerce;
		$checkout_url = $woocommerce->cart->get_checkout_url();
		$order = new WC_Order($order_id);
		$subscribed = get_post_meta($order_id, 'sendy_subscribed', true);
		//file_put_contents(WP_PLUGIN_DIR . '/woo-sendy/debug.log', serialize($already_subscribed));
		$list = $this->sendy_list_cust;
		$api = $this->sendy_api;
		if ($list != '') {
			$result = $this->create_sendy_request($order, $checkout_url, $list, $api);
			if ($result == 1) {
				$order->add_order_note( $order->billing_email.' added to the "customer" mailing list');
			} elseif ($result == 'Already subscribed.') {
				$order->add_order_note('Customer '. $order->billing_email.' was already subscribed to the mailing list');
			} else {
				$order->add_order_note('Failed to add '. $order->billing_email.' to the mailing list');
			}
		}
		if ( $subscribed && $this->sendy_list != '') {
			$list_news = $this->sendy_list;
			$result2 = $this->create_sendy_request($order, $checkout_url, $list_news, $api, 'true');
			if ($result2 == 1) {
				$order->add_order_note( $order->billing_email.' added to the newsletter list');
			} elseif ($result2 == 'Already subscribed.') {
				$order->add_order_note($order->billing_email.' was already subscribed to the newsletter');
			} else {
				$order->add_order_note('Failed to add '. $order->billing_email.' to the newsletter list');
			}
		}
	}

	public function create_sendy_request($order, $checkout_url, $list, $api, $gdpr = '') {
		$url = rtrim($this->sendy_url ,"/");
		// Subscribe customers
		$postdata = http_build_query(
			array(
				'name' => $order->billing_first_name,
				'Lastname' =>  $order->billing_last_name,
				'Shop' => get_bloginfo('name'),
				'Orderdate' => date('Y-m-d'),
				'email' => $order->billing_email,
				'ipaddress' => $_SERVER['REMOTE_ADDR'],
				'referrer' => $checkout_url,
				'Orderdate' => date('Y-m-d'),
				'list' => $list,
				'api_key' => $api,
				'boolean' => 'true',
				'gdpr' => $gdpr
			)
		);

		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url.'/subscribe');
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-type: application/x-www-form-urlencoded'
		));


		$result = curl_exec($ch);
		return $result;
	}

	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {

		return $settings;
	}

}


