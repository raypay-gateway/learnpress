<?php
/*
Plugin Name: RayPay payment gateway for LearnPress
Plugin URI: http://raypay.ir/
Description: <a href="https://raypay.ir">RayPay</a> secure payment gateway for LearnPress
Author:Saminray
Version: 1.0
Author URI: https://saminray.com
Tags: learnpress, raypay
Text Domain: learnpress-raypay
Domain Path: /languages/
*/

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

define( 'LP_ADDON_RAYPAY_PAYMENT_FILE', __FILE__ );
define( 'LP_ADDON_RAYPAY_PAYMENT_VER', '1.0.0' );
define( 'LP_ADDON_RAYPAY_PAYMENT_REQUIRE_VER', '1.0.0' );

/**
 * Class LP_Addon_RayPay_Payment_Preload
 */
class LP_Addon_RayPay_Payment_Preload {

	/**
	 * LP_Addon_RayPay_Payment_Preload constructor.
	 */
	public function __construct() {
		load_plugin_textdomain( 'learnpress-raypay', false, basename( dirname(__FILE__) ) . '/languages' );
		add_action( 'learn-press/ready', array( $this, 'load' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Load addon
	 */
	public function load() {
		LP_Addon::load( 'LP_Addon_RayPay_Payment', 'inc/load.php', __FILE__ );
		remove_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin notice
	 */
	public function admin_notices() {
		?>
        <div class="error">
            <p><?php echo wp_kses(
					sprintf(
						__( '<strong>%s</strong> addon version %s requires %s version %s or higher is <strong>installed</strong> and <strong>activated</strong>.', 'learnpress-raypay' ),
						__( 'RayPay payment gateway for LearnPress', 'learnpress-raypay' ),
						LP_ADDON_RAYPAY_PAYMENT_VER,
						sprintf( '<a href="%s" target="_blank"><strong>%s</strong></a>', admin_url( 'plugin-install.php?tab=search&type=term&s=learnpress' ), __( 'LearnPress', 'learnpress-raypay' ) ),
						LP_ADDON_RAYPAY_PAYMENT_REQUIRE_VER
					),
					array(
						'a'      => array(
							'href'  => array(),
							'blank' => array()
						),
						'strong' => array()
					)
				); ?>
            </p>
        </div>
		<?php
	}
}

new LP_Addon_RayPay_Payment_Preload();