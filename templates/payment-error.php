<?php
/**
 * Template for displaying RayPay payment error message.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/raypay-payment/payment-error.php.
 *
 * @author   Saminray
 * @link 	 https://saminray.com
 * @package  LearnPress/RayPay/Templates
 * @version  1.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();
?>

<?php $settings = LP()->settings; ?>

<div class="learn-press-message error ">
	<div><?php echo __( 'Payment failed.', 'learnpress-raypay' ); ?></div>
</div>
