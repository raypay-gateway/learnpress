<?php
/**
 * RayPay payment gateway class.
 *
 * @author   Saminray
 * @link 	 https://saminray.com
 * @package  LearnPress/RayPay/Classes
 * @version  1.0
 */
// session_start();

// Prevent loading this file directly
@session_start();
if(isset($_SESSION['modulebank_raypay_form'])&&$_SESSION['modulebank_raypay_form'])
{
    $form = $_SESSION['modulebank_raypay_form'];
    $_SESSION['modulebank_raypay_form'] = '';
    unset($_SESSION['modulebank_raypay_form']);
    echo $form;
    exit;
}
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Gateway_RayPay' ) ) {
    /**
     * Class LP_Gateway_RayPay
     */
    class LP_Gateway_RayPay extends LP_Gateway_Abstract
    {

        /**
         * @var array
         */
        private $form_data = array();

        /**
         * @var
         */
        private $link;

        /**
         * @var string
         */
        protected $payment_endpoint;

        /**
         * @var string
         */
        protected $verify_endpoint;

        /**
         * @var array|bool|mixed|null
         */
        private $user_id = null;

        /**
         * @var array|bool|mixed|null
         */
        private $acceptor_code = null;


        /**
         * @var array|null
         */
        protected $settings = null;

        /**
         * @var null
         */
        protected $order = null;

        /**
         * @var null
         */
        protected $posted = null;


        /**
         * LP_Gateway_RayPay constructor.
         */
        public function __construct()
        {
            $this->id = 'raypay';
            $this->payment_endpoint = 'http://185.165.118.211:14000/raypay/api/v1/Payment/getPaymentTokenWithUserID';
            $this->verify_endpoint = 'http://185.165.118.211:14000/raypay/api/v1/Payment/checkInvoice';

            $this->method_title = __('RayPay', 'learnpress-raypay');;
            $this->method_description = __('Make a payment with RayPay.', 'learnpress-raypay');
            $this->icon = '';

            // Get settings
            $this->title = LP()->settings->get("{$this->id}.title", $this->method_title);
            $this->description = LP()->settings->get("{$this->id}.description", $this->method_description);

            $settings = LP()->settings;

            // Add default values for fresh installs
            if ($settings->get("{$this->id}.enable")) {
                $this->settings = array();
                $this->settings['user_id'] = $settings->get("{$this->id}.user_id");
                $this->settings['acceptor_code'] = $settings->get("{$this->id}.acceptor_code");
            }

            $this->user_id = sanitize_text_field($this->settings['user_id']);
            $this->acceptor_code = sanitize_text_field($this->settings['acceptor_code']);


            if (did_action('learn_press/raypay-add-on/loaded')) {
                return;
            }

            // check payment gateway enable
            add_filter('learn-press/payment-gateway/' . $this->id . '/available', array(
                $this,
                'raypay_available'
            ), 10, 2);

            do_action('learn_press/raypay-add-on/loaded');

            parent::__construct();

            // web hook
            if (did_action('init')) {
                $this->register_web_hook();
            } else {
                add_action('init', array($this, 'register_web_hook'));
            }
            add_action('learn_press_web_hooks_processed', array($this, 'web_hook_process_raypay'));

            add_action("learn-press/before-checkout-order-review", array($this, 'error_message'));
        }

        /**
         * Register web hook.
         *
         * @return array
         */
        public function register_web_hook()
        {
            learn_press_register_web_hook('raypay', 'learn_press_raypay');
        }

        /**
         * Admin payment settings.
         *
         * @return array
         */
        public function get_settings()
        {

            return apply_filters('learn-press/gateway-payment/raypay/settings',
                array(
                    array(
                        'title' => __('Enable', 'learnpress-raypay'),
                        'id' => '[enable]',
                        'default' => 'no',
                        'type' => 'yes-no'
                    ),
                    array(
                        'title' => __('User ID', 'learnpress-raypay'),
                        'id' => '[user_id]',
                        'type' => 'text',
                        'visibility' => array(
                            'state' => 'show',
                            'conditional' => array(
                                array(
                                    'field' => '[enable]',
                                    'compare' => '=',
                                    'value' => 'yes'
                                )
                            )
                        )
                    ),
                    array(
                        'title' => __('Acceptor Code', 'learnpress-raypay'),
                        'id' => '[acceptor_code]',
                        'type' => 'text',
                        'visibility' => array(
                            'state' => 'show',
                            'conditional' => array(
                                array(
                                    'field' => '[enable]',
                                    'compare' => '=',
                                    'value' => 'yes'
                                )
                            )
                        )
                    )
                )
            );
        }

        /**
         * Payment form.
         */
        public function get_payment_form()
        {
            ob_start();
            $template = learn_press_locate_template('form.php', learn_press_template_path() . '/addons/raypay-payment/', LP_ADDON_RAYPAY_PAYMENT_TEMPLATE);
            include $template;

            return ob_get_clean();
        }

        /**
         * Error message.
         *
         * @return array
         */
        public function error_message()
        {
            if (!isset($_SESSION))
                session_start();
            if (isset($_SESSION['raypay_error']) && intval($_SESSION['raypay_error']) === 1) {
                $_SESSION['raypay_error'] = 0;
                $template = learn_press_locate_template('payment-error.php', learn_press_template_path() . '/addons/raypay-payment/', LP_ADDON_RAYPAY_PAYMENT_TEMPLATE);
                include $template;
            }
        }

        /**
         * @return mixed
         */
        public function get_icon()
        {
            if (empty($this->icon)) {
                $this->icon = LP_ADDON_RAYPAY_PAYMENT_URL . 'assets/images/raypay.png';
            }

            return parent::get_icon();
        }

        /**
         * Check gateway available.
         *
         * @return bool
         */
        public function raypay_available()
        {

            if (LP()->settings->get("{$this->id}.enable") != 'yes') {
                return false;
            }

            return true;
        }

        /**
         * Validate form fields.
         *
         * @return bool
         * @throws Exception
         * @throws string
         */
        public function validate_fields() {
            $posted        = learn_press_get_request( 'learn-press-raypay' );
            $email   = !empty( $posted['email'] ) ? $posted['email'] : "";
            $mobile  = !empty( sanitize_text_field($posted['mobile']) ) ? sanitize_text_field($posted['mobile']) : "";
            $error_message = array();
            if ( !empty( $email ) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message[] = __( 'Invalid email format.', 'learnpress-raypay' );
             }
            if ( !empty( $mobile ) && !preg_match("/^(09)(\d{9})$/", $mobile)) {
                $error_message[] = __( 'Invalid mobile format.', 'learnpress-raypay' );
            }

            $error = sizeof( $error_message );
            $this->posted = $posted;
            return !$error;
        }

        /**
         * RayPay payment process.
         *
         * @param $order
         *
         * @return array
         * @throws string
         */
        public function process_payment($order)
        {
            $this->order = learn_press_get_order($order);
            $order = $this->order;

            if( !$this->validate_fields()){

                $note = __('Invalid information.', 'learnpress-raypay');
                $payment_error = $note;
                $payment_error .= "\n";
                $payment_error .= get_post_meta($order->id, 'raypay_payment_error', TRUE);
                update_post_meta($order->id, __('raypay_payment_error', 'learnpress-raypay'), $payment_error);
                $output = learn_press_add_message($note, 'error');
                exit();
            }
            else{


                $invoice_id = round(microtime(true) * 1000);


                $customer_name = $order->get_customer_name();
                $callback = get_site_url() . '/?' . learn_press_get_web_hook('raypay') . '=1&order_id=' . $order->get_id() . '&';

                $currency_code = learn_press_get_currency();
                if ($currency_code == 'IRR') {
                    $amount = $order->order_total;
                } else {
                    $note = __("Currency is not supported", 'learnpress-raypay');
                    $payment_error = $note;
                    $payment_error .= "\n";
                    $payment_error .= get_post_meta($order->id, 'raypay_payment_error', TRUE);
                    update_post_meta($order->id, __('raypay_payment_error', 'learnpress-raypay'), $payment_error);
                    learn_press_add_message($note, 'error');

                    return false;
                }

                //Set params and headers
                $data = array(
                    'amount' => strval($amount),
                    'invoiceID' => strval($invoice_id),
                    'userID' => $this->user_id,
                    'redirectUrl' => $callback,
                    'factorNumber' => strval($order->get_id()),
                    'acceptorCode' => $this->acceptor_code,
                    'mobile' => (!empty($this->posted['mobile'])) ? $this->posted['mobile'] : "",
                    'email' => (!empty($this->posted['email'])) ? $this->posted['email'] : "",
                    'fullName' => $customer_name,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 15,
                );
                $response = $this->call_gateway_endpoint($this->payment_endpoint, $args);

                //Check error
                if (is_wp_error($response)) {
                    $payment_error = __('An error occurred while creating the transaction.', 'learnpress-raypay');
                    $payment_error .= "\n";
                    $payment_error .= get_post_meta($order->id, 'raypay_payment_error', TRUE);
                    update_post_meta($order->id, __('raypay_payment_error', 'learnpress-raypay'), $payment_error);
                    learn_press_add_message($response->get_error_message(), 'error');

                    return false;
                }
                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);

                $result = json_decode($result);


                //Check http error
                if ($http_status != 200 || empty($result) || empty($result->Data)) {
                    $note = '';
                    $note .= __('An error occurred while creating the transaction.', 'learnpress-raypay');
                    $note .= '<br/>';
                    $note .= sprintf(__('error status: %s', 'learnpress-raypay'), $http_status);


                    if (!empty($result->Message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'learnpress-raypay'), $result->Message);
                        $payment_error = $result->Message;
                        $payment_error .= "\n";
                        $payment_error .= get_post_meta($order->id, 'raypay_payment_error', TRUE);
                        update_post_meta($order->id, __('raypay_payment_error', 'learnpress-raypay'), $payment_error);
                        learn_press_add_message($note, 'error');
                    }

                    return false;
                }
                // Save ID of this transaction
                update_post_meta($order->id, __('raypay_invoice_id', 'learnpress-raypay'), $invoice_id);

                // Set remote status of the transaction to 1 as it's primary value.
                update_post_meta($order->id, __('raypay_transaction_status', 'learnpress-raypay'), 1);

                $access_token = $result->Data->Accesstoken;
                $terminal_id = $result->Data->TerminalID;

                $_SESSION['modulebank_raypay_form'] = '<p style="color:#ff0000; font:18px Tahoma; direction:rtl;">در حال اتصال به درگاه بانکی. لطفا صبر کنید ...</p>
							<form name="frmRayPayPayment" method="post" action=" https://mabna.shaparak.ir:8080/Pay ">
							<input type="hidden" name="TerminalID" value="' . $terminal_id . '" />
							<input type="hidden" name="token" value="' . $access_token . '" />
							<input class="submit" type="submit" value="پرداخت" /></form>
							<script>document.frmRayPayPayment.submit();</script>';
                return array('result' => 'success', 'redirect' => $callback);
            }
        }

        /**
         * Handle a web hook
         *
         */
        public function web_hook_process_raypay()
        {
            //$invoice_id = sanitize_text_field($_GET['?invoiceID']);
            $invoice_id = sanitize_text_field( $_GET['?invoiceID'] );
            $order_id = sanitize_text_field($_GET['order_id']);

            //Check id or order_id is empty
            if (empty($invoice_id) || empty($order_id)) {
                learn_press_add_message(__('An error occurred while redirecting from gateway.', 'learnpress-raypay'), 'error');
                wp_redirect(esc_url(learn_press_get_page_link('checkout')));

                exit();
            }

            $order = LP_Order::instance($order_id);
            //Check order is empty
            if (empty($order)) {
                learn_press_add_message(__('An error occurred while redirecting from gateway.', 'learnpress-raypay'), 'error');
                wp_redirect(esc_url(learn_press_get_page_link('checkout')));

                exit();
            }

            //Check order status
            if ($order->has_status('completed')) {
                learn_press_add_message(__('Order has been completed.', 'learnpress-raypay'), 'error');
                wp_redirect(esc_url(learn_press_get_page_link('checkout')));

                exit();
            }

            //Set params and headers
            $verify_url = add_query_arg('pInvoiceID', $invoice_id, $this->verify_endpoint);


            $data = array(
                'order_id' => $order_id,
            );

            $headers = array(
                'Content-Type' => 'application/json',
            );

            $args = array(
                'body' => json_encode($data),
                'headers' => $headers,
                'timeout' => 15,
            );

            $response = $this->call_gateway_endpoint($verify_url, $args);

            //Check Error
            if (is_wp_error($response)) {
                $payment_error = __('An error occurred while verifying the transaction.' . 'learnpress-raypay');
                $payment_error .= "\n";
                $payment_error .= get_post_meta($order->id, 'raypay_payment_error', TRUE);
                update_post_meta($order->id, __('raypay_payment_error' . 'learnpress-raypay'), $payment_error);
                learn_press_add_message($response->get_error_message(), 'error');
                wp_redirect(esc_url(learn_press_get_page_link('checkout')));

                exit();
            }

            $http_status = wp_remote_retrieve_response_code($response);
            $result = wp_remote_retrieve_body($response);
            $result = json_decode($result);

            //Check http Error
            if ($http_status != 200) {
                $note = '';
                $note .= __('An error occurred while verifying the transaction.', 'learnpress-raypay');
                $note .= '<br/>';
                $note .= sprintf(__('error status: %s', 'learnpress-raypay'), $http_status);

                if (!empty($result->Message)) {
                    $note .= '<br/>';
                    $note .= sprintf(__('error message: %s', 'learnpress-raypay'), $result->Message);
                    $payment_error = $result->Message;
                    learn_press_add_message($note, 'error');
                }

                $payment_error .= "\n";
                $payment_error .= get_post_meta($order_id, 'raypay_payment_error', TRUE);
                update_post_meta($order_id, __('raypay_payment_error', 'learnpress-raypay'), $payment_error);
                learn_press_add_message($note, 'error');
                $order->update_status('failed');
                wp_redirect(esc_url(learn_press_get_page_link('checkout')));

                exit();
            } else {

                $state = $result->Data->State;
                $verify_order_id = $result->Data->FactorNumber;
                $verify_amount = $result->Data->Amount;

                if ($state === 1) {
                    $message = __('Payment succeeded.', 'learnpress-raypay');
                    update_post_meta($order_id, __('raypay_transaction_order_id', 'learnpress-raypay'), $verify_order_id);
                    update_post_meta($order_id, __('raypay_transaction_amount', 'learnpress-raypay'), $verify_amount);
                    $order->payment_complete($verify_order_id);
                    wp_redirect($this->get_return_url($order));
                    //wp_redirect(learn_press_is_enable_cart() ? learn_press_get_page_link('cart') : get_site_url());
                    //wp_redirect(esc_url($this->get_return_url($order)));
                    exit();

                } else {

                    $message = __('Payment failed.', 'learnpress-raypay');
                    $order->update_status('failed');
                    learn_press_add_message($message, 'error');
                    wp_redirect(learn_press_get_page_link('checkout'));
                    //wp_redirect(learn_press_is_enable_cart() ? learn_press_get_page_link('cart') : get_site_url());
                    exit();
                    //return FALSE;
                }
            }
        }

        /**
         * Calls the gateway endpoints.
         *
         * Tries to get response from the gateway for 2 times.
         *
         * @param $url
         * @param $args
         *
         * @return array|\WP_Error
         */
        private function call_gateway_endpoint($url, $args)
        {
            $number_of_connection_tries = 2;
            while ($number_of_connection_tries) {
                $response = wp_remote_post($url, $args);
                if (is_wp_error($response)) {
                    $number_of_connection_tries--;
                    continue;
                } else {
                    break;
                }
            }

            return $response;
        }
    }
}