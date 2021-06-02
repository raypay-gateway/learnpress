<?php
/**
 * Template for displaying RayPay payment form.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/raypay-payment/form.php.
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

<p><?php echo $this->get_description(); ?></p>

<div id="learn-press-raypay-form" class="<?php if(is_rtl()) echo ' learn-press-form-raypay-rtl'; ?>">
    <p class="learn-press-form-row">
        <label><?php echo wp_kses( __( 'Email', 'learnpress-raypay' ), array( 'span' => array() ) ); ?></label>
        <input type="text" name="learn-press-raypay[email]" id="learn-press-raypay-payment-email"
               maxlength="30" value=""  placeholder="example@gmail.com"/>
    <div class="learn-press-raypay-form-clear"></div>
    </p>
    <div class="learn-press-raypay-form-clear"></div>
    <p class="learn-press-form-row">
        <label><?php echo wp_kses( __( 'Mobile', 'learnpress-raypay' ), array( 'span' => array() ) ); ?></label>
        <input type="text" name="learn-press-raypay[mobile]" id="learn-press-raypay-payment-mobile" value=""
               placeholder="09xxxxxxxxx"/>
    <div class="learn-press-raypay-form-clear"></div>
    </p>
    <div class="learn-press-raypay-form-clear"></div>
</div>
