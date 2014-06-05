<?php
/*
 * Plugin Name: Easy Digital Downloads - Recount Earnings
 * Description: Allows you to recalculate the earnings of products in EDD. Useful if product earnings get off somehow
 * Author: Pippin Williamson
 * Version: 1.0
 */

class EDD_Recount_Earnings {

	public function __construct() {
		add_action( 'edd_stats_meta_box', array( $this, 'stats_box_link' ) );
		add_filter( 'edd_tools_tabs', array( $this, 'add_tab' ) );
		add_action( 'edd_tools_tab_recount_earnings', array( $this, 'tools_page' ) );
		add_action( 'edd_recount_earnings', array( $this, 'recount' ) );
		add_action( 'edd_recount_store_earnings', array( $this, 'recount_store_earnings' ) );
	}

	public function stats_box_link() {
		global $post;

		$args = array(
			'edd_action' => 'recount_earnings',
			'action'     => 'edit',
			'post'       => $post->ID
		);

		$base_url = admin_url( 'post.php' );

		echo '<tr>';
			echo '<td colspan="2">';
				echo '<a href="' . add_query_arg( $args, $base_url ) . '">' . __( 'Recount Earnings', 'edd-recount-earnings' ) . '</a>';
			echo '</td>';
		echo '</tr>';
	}

	public function add_tab( $tabs ) {
		$tabs['recount_earnings'] = __( 'Recount Earnings', 'edd' );

		return $tabs;
	}

	public function tools_page() {
?>
	<div class="postbox">
		<h3><span><?php _e( 'Recount Store Earnings', 'edd' ); ?></span></h3>
		<div class="inside">
			<p><?php _e( 'Use this tool to recount your store\'s total earnings in the case they have become incorrect.', 'edd' ); ?></p>
			<form method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-tools&tab=recount_earnings' ); ?>">
				<p><input type="hidden" name="edd_action" value="recount_store_earnings" /></p>
				<p>
					<?php submit_button( __( 'Recount Earnings', 'edd' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>
		</div><!-- .inside -->
	</div><!-- .postbox -->
<?php
	}

	public function recount() {

		global $edd_logs, $wpdb;

		if( empty( $_GET['post'] ) )
			return;

		if( ! current_user_can( 'edit_products' ) )
			wp_die( 'Cheating' );

		$download_id = absint( $_GET['post'] );

		if( ! get_post( $download_id ) )
			return;

		$args = array(
			'post_parent' => $download_id,
			'log_type'    => 'sale',
			'nopaging'    => true,
			'fields'      => 'ids',
		);

		$log_ids  = $edd_logs->get_connected_logs( $args, 'sale' );
		$earnings = 0;

		if( $log_ids ) {
			$log_ids     = implode( ',', $log_ids );
			$payment_ids = $wpdb->get_col( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key='_edd_log_payment_id' AND post_id IN ($log_ids);" );

			foreach( $payment_ids as $payment_id ) {
				$items = edd_get_payment_meta_cart_details( $payment_id );
				foreach( $items as $item ) {
					if( $item['id'] != $download_id )
						continue;

					$earnings += $item['price'];
				}
			}
		}

		if( ! empty( $earnings ) ) {
			update_post_meta( $download_id, '_edd_download_earnings', $earnings );
		}

		$args = array(
			'action'     => 'edit',
			'post'       => $download_id
		);

		$base_url = admin_url( 'post.php' );

		wp_redirect( add_query_arg( $args, $base_url ) ); exit;

	}

	public function recount_store_earnings() {

		global $edd_logs, $wpdb;

		if( ! current_user_can( 'view_shop_reports' ) )
			wp_die( 'Cheating' );

		$total = (float) 0;

		$args = apply_filters( 'edd_get_total_earnings_args', array(
			'offset' => 0,
			'number' => -1,
			'mode'   => 'live',
			'status' => array( 'publish', 'revoked' ),
			'fields' => 'ids'
		) );

		$payments = edd_get_payments( $args );
		if ( $payments ) {
			foreach ( $payments as $payment ) {
				$total += edd_get_payment_amount( $payment );
			}
		}

		// Cache results for 1 day. This cache is cleared automatically when a payment is made
		set_transient( 'edd_earnings_total', $total, 86400 );

		if( $total < 0 ) {
			$total = 0; // Don't ever show negative earnings
		}

		$total = round( $total, 2 );

		// Store the total for the first time
		update_option( 'edd_earnings_total', $total );

		wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-tools&tab=recount_earnings' ) ); exit;

	}

}
new EDD_Recount_Earnings;