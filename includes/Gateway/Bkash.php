<?php

namespace DCoders\Bkash\Gateway;

/**
 * Class Bkash
 * @since 2.0.0
 *
 * @package DCoders\Bkash\Gateway
 *
 * @author Kapil Paul
 */
class Bkash extends \WC_Payment_Gateway {
	/**
	 * Initialize the gateway
	 * Bkash constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
//		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thank_you_page' ) );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Init basic settings
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function init() {
		$this->id                 = 'bkash';
		$this->icon               = false;
		$this->has_fields         = true;
		$this->method_title       = __( 'bKash', 'dc-bkash' );
		$this->method_description = __( 'Pay via bKash payment', 'dc-bkash' );
		$title                    = dc_bkash_get_option( 'title', 'gateway' );
		$this->title              = empty( $title ) ? __( 'bKash', 'dc-bkash' ) : $title;
		$this->description        = dc_bkash_get_option( 'description', 'gateway' );
	}

	/**
	 * Process admin options
	 *
	 * @since 2.0.0
	 *
	 * @return bool|void
	 */
	public function admin_options() {
		parent::admin_options();

		$bkash_settings_url = admin_url( 'admin.php?page=dc-bkash#/settings' );

		echo "<p>You will get {$this->method_title} setting options in <a href='{$bkash_settings_url}'>here</a>.</p>";
	}

	/**
	 * Process the gateway integration
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		//empty cart
//		WC()->cart->empty_cart();
		$create_payment_data = $this->create_payment_request( $order );

		if ( is_wp_error( $create_payment_data ) ) {
			$create_payment_data = false;
		}

		return [
			'result'              => 'success',
			'order_number'        => $order_id,
			'amount'              => (float) $order->get_total(),
			'checkout_order_pay'  => $order->get_checkout_payment_url(),
			'redirect'            => $this->get_return_url( $order ),
			'create_payment_data' => $create_payment_data,
		];
	}

	/**
	 * Include payment scripts
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// if our payment gateway is disabled
		if ( 'no' === $this->enabled ) {
			return;
		}

		wp_enqueue_style( 'dc-bkash' );

		//loading this scripts only in checkout page.
		wp_enqueue_script( 'sweetalert' );
		wp_enqueue_script( 'dc-bkash' );

		$this->localize_scripts();
	}

	/**
	 * Localize scripts and passing data
	 *
	 * @return void
	 */
	public function localize_scripts() {
		global $woocommerce;

		$bkash_script_url = dc_bkash()->gateway->processor()->get_script();

		$data = [
			'amount'     => $woocommerce->cart->cart_contents_total,
			'nonce'      => wp_create_nonce( 'dc-bkash-nonce' ),
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'script_url' => $bkash_script_url,
		];

		wp_localize_script( 'dc-bkash', 'dc_bkash', $data );
	}

	/**
	 * Create bKash Payment request
	 *
	 * @param \WC_Order $order
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function create_payment_request( \WC_Order $order ) {
		$processor = dc_bkash()->gateway->processor();
		$response  = $processor->create_payment( (float) $order->get_total(), $order->get_id() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}
}
