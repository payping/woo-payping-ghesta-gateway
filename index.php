<?php
/*
Plugin Name: gateway-payping-ghesta-for-woocommerce
Version: 1.0.0
Description:  درگاه خرید اعتباری قسطا
Plugin URI: https://www.payping.ir/
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir
*/
if (!defined('ABSPATH'))
	exit;

define('WCG_GPPDIR', plugin_dir_path( __FILE__ ));
define('WCG_GPPDIRU', plugin_dir_url( __FILE__ ));
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
/**
 * Detect plugin. For use in Admin area only.
 */
if( ! is_plugin_active( 'woo-payping-gateway/index.php' ) ){
    add_action( 'admin_notices', 'payping_ghesta_plugin_admin_notice' );
}else{
	add_action('plugins_loaded', 'Load_payping_ghesta_Gateway', 0);
}

//Display admin notices 
function payping_ghesta_plugin_admin_notice(){ ?>
	<div class="notice notice-warning is-dismissible">
		<p><?php _e('برای استفاده از پرداخت قسطی باید افزونه پرداخت پی‌پینگ نصب و فعال باشد.', 'textdomain') ?></p>
	</div>
<?php
}

function Load_payping_ghesta_Gateway(){
	if( class_exists('WC_Payment_Gateway') && !class_exists('wc_payping_ghesta') && !function_exists('woocommerce_add_payping_ghesta_Gateway') ){
		/* 
			add payment methods to woocommerce
			@return $methods
		*/
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_payping_ghesta_Gateway');
		function woocommerce_add_payping_ghesta_Gateway($methods){
			$methods[] = 'wc_payping_ghesta';
			return $methods;
		}

		class wc_payping_ghesta extends WC_Payment_Gateway{

			public function __construct(){
				$this->pp_gate = new WC_payping;
				
				$this->ioserver = $this->pp_gate->ioserver;
				if( $this->ioserver == 'yes'){
					$this->serverUrl  = 'https://api.payping.io/v2';
				}else{
                	$this->serverUrl  = 'https://api.payping.ir/v2';
				}
				
				$this->id = 'WC_payping_Ghesta';
				$this->method_title = __('درگاه خرید اعتباری قسطا', 'woocommerce');
				$this->method_description = __('تنظیمات نمایش درگاه خرید اعتباری قسطا', 'woocommerce');
				$this->icon = apply_filters('WC_payping_Ghesta_logo', WCG_GPPDIRU . '/assets/images/logo.png');
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();
				
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];

				$this->paypingToken = $this->pp_gate->paypingToken;

				$this->success_massage = $this->settings['success_massage'];
				$this->failed_massage = $this->settings['failed_massage'];
                
                $this->Debug_Mode = $this->pp_gate->Debug_Mode;

				if(version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				else
					add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

				add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_payping_Ghesta_Gateway'));
				add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_payping_Ghesta_Gateway'));
			}

			public function admin_options(){
				parent::admin_options();
			}

			public function init_form_fields(){
				$this->form_fields = apply_filters('WC_payping_Ghesta_Config', array(
						'base_confing' => array(
							'title' => __('تنظیمات پایه ای', 'woocommerce'),
							'type' => 'title',
							'description' => '',
						),
						'enabled' => array(
							'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
							'type' => 'checkbox',
							'label' => __('فعالسازی درگاه خرید اعتباری قسطا', 'woocommerce'),
							'description' => __('برای فعالسازی درگاه خرید اعتباری قسطا باید چک باکس را تیک بزنید', 'woocommerce'),
							'default' => 'yes',
							'desc_tip' => true,
						),
					
						'title' => array(
							'title' => __('عنوان درگاه', 'woocommerce'),
							'type' => 'text',
							'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
							'default' => __('درگاه خرید اعتباری قسطا', 'woocommerce'),
							'desc_tip' => true,
						),
						'description' => array(
							'title' => __('توضیحات درگاه', 'woocommerce'),
							'type' => 'text',
							'desc_tip' => true,
							'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
							'default' => __('پرداخت از طریق درگاه خرید اعتباری قسطا', 'woocommerce')
						),
						'payment_confing' => array(
							'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
							'type' => 'title',
							'description' => '',
						),
						'success_massage' => array(
							'title' => __('پیام پرداخت موفق', 'woocommerce'),
							'type' => 'textarea',
							'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پی‌پینگ استفاده نمایید .', 'woocommerce'),
							'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
						),
						'failed_massage' => array(
							'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
							'type' => 'textarea',
							'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'woocommerce'),
							'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
						)
					)
				);
			}

			public function process_payment($order_id){
				$order = new WC_Order($order_id);
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}

			public function Send_to_payping_Ghesta_Gateway($order_id){
				global $woocommerce;
				$woocommerce->session->order_id_payping = $order_id;
				$order = new WC_Order($order_id);
				$currency = $order->get_currency();
				$currency = apply_filters('WC_payping_ghesta_Currency', $currency, $order_id);

				$form = '<form action="" method="POST" class="payping-checkout-form" id="payping-checkout-form">
						<input type="submit" name="payping_submit" class="button alt" id="payping-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
				
				$form = apply_filters('WC_payping_Ghesta_Form', $form, $order_id, $woocommerce);

				do_action('WC_payping_Ghesta_Gateway_Before_Form', $order_id, $woocommerce);
				echo $form;
				do_action('WC_payping_Ghesta_Gateway_After_Form', $order_id, $woocommerce);

                $Amount = intval( $order->get_total() );
                        
                        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                        if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                        )
                            $Amount = $Amount * 1;
                        else if (strtolower($currency) == strtolower('IRHT'))
                            $Amount = $Amount * 1000;
                        else if (strtolower($currency) == strtolower('IRHR'))
                            $Amount = $Amount * 100;
                        else if (strtolower($currency) == strtolower('IRR'))
                            $Amount = $Amount / 10;
                
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_payping_gateway', $Amount, $currency);

				$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_payping_ghesta'));

				$products = array();
				$order_items = $order->get_items();
				foreach ((array)$order_items as $product) {
					$products[] = $product['name'] . ' (' . $product['qty'] . ') ';
				}
				$products = implode(' - ', $products);

				$Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
				if( $this->ghesta == 'yes' ){
					$Mobile = get_post_meta( $order_id, '_Mobile', true );
				}else{
					$Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true): '-';
				}
				
				$Email = $order->get_billing_email();
				$Paymenter = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				$ResNumber = intval($order->get_order_number());

				//Hooks for iranian developer
				$Description = apply_filters('WC_payping_ghesta_Description', $Description, $order_id);
				$Mobile = apply_filters('WC_payping_ghesta_Mobile', $Mobile, $order_id);
				$Email = apply_filters('WC_payping_ghesta_Email', $Email, $order_id);
				$Paymenter = apply_filters('WC_payping_ghesta_Paymenter', $Paymenter, $order_id);
				$ResNumber = apply_filters('WC_payping_ghesta_ResNumber', $ResNumber, $order_id);
				do_action('WC_payping_ghesta_Gateway_Payment', $order_id, $Description, $Mobile);
				$Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
				$Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';
				
				$payerIdentity = $Mobile;
				
				$data = array(
					'payerName'=>$Paymenter,
					'Amount' => $Amount,
					'payerIdentity'=> $payerIdentity ,
					'returnUrl' => $CallbackUrl,
					'Description' => $Description ,
					'clientRefId' => $order->get_order_number()
				);

                $args = array(
                    'body' => json_encode($data),
                    'timeout' => '45',
                    'redirection' => '5',
                    'httpsversion' => '1.0',
                    'blocking' => true,
	               'headers' => array(
		              'Authorization' => 'Bearer '.$this->paypingToken,
		              'Content-Type'  => 'application/json',
		              'Accept' => 'application/json'
		              ),
                    'cookies' => array()
                );
             
				$api_url  = apply_filters( 'WC_payping_ghesta_Gateway_Payment_api_url', $this->serverUrl . '/pay', $order_id );
				
				$api_args = apply_filters( 'WC_payping_ghesta_Gateway_Payment_api_args', $args, $order_id );
                
                $response = wp_remote_post($api_url, $api_args);
                
                /* Call Function Show Debug In Console */
                WC_GPP_Debug_Log($this->Debug_Mode, $response, "Pay"); 
                
				$XPP_ID = $response["headers"]["x-paypingrequest-id"];
					if( is_wp_error($response) ){
						$Message = $response->get_error_message();
					}else{	
						$code = wp_remote_retrieve_response_code( $response );
						if( $code === 200){
							if (isset($response["body"]) and $response["body"] != '') {
								$code_pay = wp_remote_retrieve_body($response);
								$code_pay =  json_decode($code_pay, true);
								wp_redirect(sprintf('https://payping.ir/installment/%s?type=ghesta', $code_pay["code"]));
								exit;
							} else {
								$Message = ' تراکنش ناموفق بود- کد خطا : '.$XPP_ID;
								$Fault = $Message;
							}
						} elseif ( $code == 400) {
							$Message = wp_remote_retrieve_body( $response ).'<br /> کد خطا: '.$XPP_ID;
							$Fault = '';
						} else {
							$Message = wp_remote_retrieve_body( $response ).'<br /> کد خطا: '.$XPP_ID;
						}
					}

				if (!empty($Message) && $Message) {

					$Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
					$Fault = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
					$Note = apply_filters('WC_payping_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
					$order->add_order_note($Note);

					$Fault = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
					$Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
					$Notice = apply_filters('WC_payping_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
					if ($Notice)
						wc_add_notice($Notice, 'error');
					$Fault = $Notice;
					do_action('WC_payping_Send_to_Gateway_Failed', $order_id, $Fault);
				}
			}

			public function Return_from_payping_Ghesta_Gateway(){
				global $woocommerce;

				if( isset( $_GET['wc_order'] ) ){
					$order_id = sanitize_text_field($_GET['wc_order']);
				}elseif( isset( $_POST['wc_order'] ) ){
					$order_id = sanitize_text_field($_POST['wc_order']);
				}elseif( isset( $_GET['clientrefid'] ) ){
					$order_id = sanitize_text_field($_GET['clientrefid']);
				}elseif( isset( $_POST['clientrefid'] ) ){
					$order_id = sanitize_text_field($_POST['clientrefid']);
				}else{
					$order_id = $woocommerce->session->order_id_payping;
					unset( $woocommerce->session->order_id_payping );
				}

				$order_id = apply_filters('WC_payping_return_order_id', $order_id);
				
				if($order_id){

					$order = new WC_Order($order_id);
					$currency = $order->get_currency();
					$currency = apply_filters('WC_payping_Currency', $currency, $order_id);

					if($order->status != 'completed'){

                        $Amount = intval($order->order_total);
                        
                        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                        if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                        )
                            $Amount = $Amount * 1;
                        else if (strtolower($currency) == strtolower('IRHT'))
                            $Amount = $Amount * 1000;
                        else if (strtolower($currency) == strtolower('IRHR'))
                            $Amount = $Amount * 100;
                        else if (strtolower($currency) == strtolower('IRR'))
                            $Amount = $Amount / 10;
							
                        $refid = sanitize_text_field($_POST['refid']);
						$refid = apply_filters('WC_payping_return_refid', $refid);
						
						$data = array('refId' => $refid, 'amount' => $Amount);
                        $args = array(
                            'body' => json_encode($data),
                            'timeout' => '45',
                            'redirection' => '5',
                            'httpsversion' => '1.0',
                            'blocking' => true,
	                        'headers' => array(
	                       	'Authorization' => 'Bearer ' . $this->paypingToken,
	                       	'Content-Type'  => 'application/json',
	                       	'Accept' => 'application/json'
	                       	),
                         'cookies' => array()
                        );
                    $verify_api_url = apply_filters( 'WC_payping_Gateway_Payment_verify_api_url', $this->serverUrl . '/pay/verify', $order_id );
                    $response = wp_remote_post($verify_api_url, $args);
					$body = wp_remote_retrieve_body( $response );
                    /* Call Function Show Debug In Console */
                    WC_GPP_Debug_Log($this->Debug_Mode, $response, "Verify");
                        
                    $XPP_ID = $response["headers"]["x-paypingrequest-id"];
                    if( is_wp_error($response) ){
                        $Status = 'failed';
				        $Fault = $response->get_error_message();
						$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$response->get_error_message();
					}else{
						$code = wp_remote_retrieve_response_code( $response );
						
						if ( $code === 200 ) {
							if (isset( $refid ) and $refid != '') {
								$Status = 'completed';
								$Transaction_ID = $refid;
								$Fault = '';
								$Message = '';
							} else {
                                $Status = 'failed';
								$Transaction_ID = $refid;
								$Message = 'متاسفانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $body .'<br /> شماره خطا: '.$XPP_ID;
								$Fault = $code;
							}
						} elseif ( $code == 400) {
							$rbody = json_decode( $body, true );
							if( array_key_exists('15', $rbody) ){
								$Status = 'completed';
								$Transaction_ID = $refid;
								$Fault = '';
								$Message = '';
							}else{
								$Status = 'failed';
				            	$Transaction_ID = $refid;
								$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$XPP_ID;
								$Fault = $code;
							}
						} else {
                            $Status = 'failed';
				            $Transaction_ID = $refid;
							$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$XPP_ID;
                            $Fault = $code;
						}
					}

						if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
							update_post_meta($order_id, '_transaction_id', $Transaction_ID);

							$order->payment_complete($Transaction_ID);
							$woocommerce->cart->empty_cart();

							$Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
							$Note = apply_filters('WC_payping_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
							if ($Note)
								$order->add_order_note($Note, 1);

							$Notice = wpautop(wptexturize($this->success_massage));

							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

							$Notice = apply_filters('WC_payping_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
							if ($Notice)
								wc_add_notice($Notice, 'success');

							do_action('WC_payping_Return_from_Gateway_Success', $order_id, $Transaction_ID, $response);

							wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
							exit;
						}else{

							$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد پیگیری : ' . $Transaction_ID) : '';

							$Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'woocommerce'), $Message, $tr_id);

							$Note = apply_filters('WC_payping_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
							if ($Note)
								$order->add_order_note($Note, 1);

							$Notice = wpautop(wptexturize($Note));

							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

							$Notice = str_replace("{fault}", $Message, $Notice);
							$Notice = apply_filters('WC_payping_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
							if ($Notice)
								wc_add_notice($Notice, 'error');

							do_action('WC_payping_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

							wp_redirect($woocommerce->cart->get_checkout_url());
							exit;
						}
					}else{


						$Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

						$Notice = wpautop(wptexturize($this->success_massage.' شناسه خطای پی پینگ:'.$XPP_ID));

						$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

						$Notice = apply_filters('WC_payping_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
						if ($Notice)
							wc_add_notice($Notice, 'success');

						do_action('WC_payping_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

						wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
						exit;
					}
				}else{

					$Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
					$Notice = wpautop(wptexturize($this->failed_massage.' شناسه خطای پی پینگ:'.$XPP_ID));
					$Notice = str_replace("{fault}", $Fault, $Notice);
					$Notice = apply_filters('WC_payping_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
					if ($Notice)
						wc_add_notice($Notice, 'error');

					do_action('WC_payping_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

					wp_redirect($woocommerce->cart->get_checkout_url());
					exit;
				}
			}

		}
	}
	
/**
 * @snippet       Add Custom Field @ WooCommerce Checkout Page
 */
 
function payping_ghestaadd_custom_checkout_field( $checkout ) { 
   $current_user = wp_get_current_user();
   $saved_Mobile = $current_user->Mobile;
   woocommerce_form_field( 'Mobile', array(        
      'type' => 'text',        
      'class' => array( 'form-row-wide' ),        
      'label' => 'شماره همراه',        
      'placeholder' => '09123456789',        
      'required' => true,        
      'default' => $saved_Mobile,        
   ), $checkout->get_value( 'Mobile' ) ); 
}
add_action( 'woocommerce_before_order_notes', 'payping_ghestaadd_custom_checkout_field' );
/**
 * @snippet       Validate Custom Field @ WooCommerce Checkout Page
 */
  
function payping_ghestavalidate_new_checkout_field(){
	if( ! isset( $_POST['Mobile'] ) ){
		wc_add_notice( 'شماره همراه را وارد کنید..', 'error' );
	}elseif( preg_match("/^09[0-9]{9}$/", $_POST['Mobile'] ) == false ){
		wc_add_notice( 'شماره همراه باید 11 رقمی و با 0 شروع شود.', 'error' );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'payping_ghestavalidate_new_checkout_field', 10, 2 );
/**
 * @snippet       Save & Display Custom Field @ WooCommerce Order
 */
function payping_ghestasave_new_checkout_field( $order_id ) { 
    if( $_POST['Mobile'] ) update_post_meta( $order_id, '_Mobile', sanitize_text_field( $_POST['Mobile'] ) );
}
add_action( 'woocommerce_checkout_update_order_meta', 'payping_ghestasave_new_checkout_field' );
	
function payping_ghestashow_new_checkout_field_order( $order ) {    
   $order_id = $order->get_id();
   if(get_post_meta( $order_id, '_Mobile', true ))echo sprintf(esc_html('<p><strong>شماره همراه:</strong>%s</p>'), get_post_meta( $order_id, '_Mobile', true ));
}
add_action( 'woocommerce_email_after_order_table', 'payping_ghestashow_new_checkout_field_emails', 20, 4 );
	
function payping_ghestashow_new_checkout_field_emails( $order, $sent_to_admin, $plain_text, $email ){
    if(get_post_meta( $order->get_id(), '_Mobile', true ))echo sprintf(esc_html('<p><strong>شماره همراه:</strong>%s</p>'), get_post_meta($order->get_id(), '_Mobile', true));
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'payping_ghestashow_new_checkout_field_order', 10, 1 );
	
}