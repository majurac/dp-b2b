<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Frontend {

	public function __construct(
		private readonly DP_Quick_Order_Assets $assets
	) {
		add_shortcode( DP_Quick_Order_Config::SHORTCODE, [ $this, 'render_shortcode' ] );
	}

	public function render_shortcode( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to use Quick Order.', 'dp-b2b-quick-order' ) . '</p>';
		}

		$user_id = get_current_user_id();
		if ( ! (bool) apply_filters( 'dp_b2b_quick_order_user_allowed', true, $user_id ) ) {
			return '<p class="dp-qo-access-denied">' . esc_html__( 'You do not have access to the Quick Order.', 'dp-b2b-quick-order' ) . '</p>';
		}

		ob_start();
		$this->render_template();
		return (string) ob_get_clean();
	}

	private function render_template(): void {
		$template = DP_QUICK_ORDER_PATH . 'templates/quick-order.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
