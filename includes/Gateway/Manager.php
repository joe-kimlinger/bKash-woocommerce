<?php

namespace DCoders\Bkash\Gateway;

/**
 * Class Manager
 * @since 2.0.0
 *
 * @package DCoders\Bkash
 *
 * @author Kapil Paul
 */
class Manager {
	/**
	 * Hold instance of bKash
	 *
	 * @since 2.0.0
	 */
	public $bkash_instance;

	/**
	 * Manager constructor.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );
		add_action( 'dc_bkash_execute_payment_success', [ $this, 'after_execute_payment' ], 10, 2 );
	}

	/**
	 * Add payment class to the container
	 *
	 * @since 2.0.0
	 *
	 * @return Bkash
	 */
	public function bkash() {
		$this->bkash_instance = false;

		if ( ! $this->bkash_instance ) {
			$this->bkash_instance = new Bkash();
		}

		return $this->bkash_instance;
	}

	/**
	 * Register WooCommerce Payment Gateway
	 *
	 * @param array $gateways
	 *
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = $this->bkash();

		return $gateways;
	}

	/**
	 * Get bKash processor class instance
	 *
	 * @since 2.0.0
	 *
	 * @return Processor
	 */
	public function processor() {
		return Processor::get_instance();
	}

	/**
	 * Store data in db after execute payment success
	 * bKash do not verify the payment id every time
	 *
	 * @param $order
	 * @param $execute_payment
	 *
	 * @return mixed
	 */
	public function after_execute_payment( $order, $execute_payment ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}

		try {
			$payment_id        = sanitize_text_field( $execute_payment['paymentID'] );
			$order_grand_total = (float) $order->get_total();
			$verified          = 0;

			$processor         = dc_bkash()->gateway->processor();
			$order_grand_total = $processor->get_final_amount( $order_grand_total );

			if ( $execute_payment['amount'] === $order_grand_total ) {
				$order->add_order_note(
					sprintf( __( 'bKash payment completed. Transaction ID #%s! Amount: %s', BKASH_TEXT_DOMAIN ),
						$execute_payment['trxID'],
						$order_grand_total
					)
				);

				$order->payment_complete();

			} else {
				$order->update_status(
					'on-hold',
					__( "Partial payment.Transaction ID #{$execute_payment['trxID']}! Amount: {$execute_payment['amount']}", BKASH_TEXT_DOMAIN )
				);
			}

			$payment_info = $processor->verify_payment( $payment_id, $order_grand_total );

			if ( isset( $payment_info['transactionStatus'] ) && isset( $payment_info['trxID'] ) ) {
				$verified = 1;
			} else {
				$payment_info = $execute_payment;
			}

			$insert_data = [
				'order_number'        => $order->get_id(),
				'payment_id'          => isset( $payment_info['paymentID'] ) ? $payment_info['paymentID'] : $payment_id,
				'trx_id'              => isset( $payment_info['trxID'] ) ? $payment_info['trxID'] : '',
				'transaction_status'  => isset( $payment_info['transactionStatus'] ) ? $payment_info['transactionStatus'] : '',
				'invoice_number'      => isset( $payment_info['merchantInvoiceNumber'] ) ? $payment_info['merchantInvoiceNumber'] : '',
				'amount'              => isset( $payment_info['amount'] ) ? floatval( $payment_info['amount'] ) : $order_grand_total,
				'verification_status' => $verified,
			];

			dc_bkash_insert_transaction( $insert_data );

			/**
			 * Fires after the execute payment insert
			 */
			do_action( 'dc_bkash_after_execute_payment', $order, $payment_info );

		} catch ( \Exception $e ) {
			error_log( 'dc_bkash after execute payment ' . print_r( $e->getMessage() ) );
		}
	}
}
