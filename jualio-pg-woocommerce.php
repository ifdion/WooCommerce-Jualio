<?php
/*
Plugin Name: WooCommerce Jualio Payment Gateway
Plugin URI: http://www.saklik.com
Description: Jualio Payment gateway for woocommerce (For Now Only IDR Currency)
Version: 1.0
Author: Yuzar
Author URI: http://www.saklik.com
*/

include_once('settings.php');
// include_once('jualio-request.php');


add_action('plugins_loaded', 'woocommerce_jualio2_pg_init', 0);
function woocommerce_jualio2_pg_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Jualio_2_PG extends WC_Payment_Gateway{
    /**
     * __construct
     **/

    public function __construct(){
      $this -> id = 'jualiov2';
      $this -> method_title = 'jualiov2';
      $this -> icon = plugins_url( 'assets/visa-master-bersama-prima_01.png', __FILE__ );
      $this -> has_fields = false;

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];

      $this -> client_id = $this -> settings['client_id'];
      $this -> customer_key = $this -> settings['customer_key'];
      $this -> payment_channel = $this -> settings['payment_channel'];

      $this -> status = $this -> settings['status'];

      $this -> liveurl = 'https://app.jualio.com/client/v2/payments/actions/create';
      $this -> devurl = 'http://dev.app.jualio.com/client/v2/payments/actions/create';

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      $this -> notifyurl = admin_url('admin-ajax.php').'?action=jualio_notification';

      if ($this->status == 'no'){
        $this -> liveurl = $this -> devurl;
      }
      
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      } else {
        add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
      }
    }

    /**
     * setup form fields
     *
     * @return void
     * @author 
     **/
    function init_form_fields(){
      $this -> form_fields = array(
        'enabled' => array(
          'title' => __('Enable/Disable', 'jualiov2'),
          'type' => 'checkbox',
          'label' => __('Enable Jualio Payment Module.', 'jualiov2'),
          'default' => 'no'),
        'title' => array(
          'title' => __('Title:', 'jualiov2'),
          'type'=> 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'jualiov2'),
          'default' => __('Jualio', 'jualiov2')),
        'description' => array(
          'title' => __('Description:', 'jualiov2'),
          'type' => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'jualiov2'),
          'default' => __('Pay securely by Credit or Debit card or internet banking through Jualio Secure Servers.', 'jualiov2')),
        'client_id' => array(
          'title' => __('Client ID', 'jualiov2'),
          'type' => 'text',
          'description' => __('Get Client ID from jualio officer')),
        'customer_key' => array(
          'title' => __('Customer Key', 'jualiov2'),
          'type' => 'text',
          'description' => __('Get Customer Key from jualio officer')),
        'payment_channel' => array(
          'title' => __('Payment Channel', 'jualiov2'),
          'type' => 'select',
          'options' => array(
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card'),
          'description' => __('Select default payment channel.', 'jualiov2')),
        'status' => array(
          'title' => __('Live/Sandbox', 'jualiov2'),
          'type' => 'checkbox',
          'label' => __('Live Jualio Payment.', 'jualiov2'),
          'default' => 'no'),
      );
    }
    /**
     * render options
     *
     **/
    
    public function admin_options(){
      echo '<h3>'.__('Jualio Payment Gateway', 'jualiov2').'</h3>';
      echo '<p>'.__('Jualio is most popular payment gateway for online shopping in Indonesia (Only can use currency IDR)').'</p>';
      echo '<table class="form-table">';
      // Generate the HTML For the settings form.
      $this -> generate_settings_html();
      echo '</table>';
    }


    /**
     * request payment to jualio API and return the payment url
     *
     **/
    public function get_jualio_url($order){

      $items = $order->get_items();
      $address = $order->get_address();

      $jualio_request = array(
        'object' => 'payment',
        'customer_key' => $this->customer_key,
        'callback_url' => $this->get_return_url($order),
        'notify_url' => $this->notifyurl,
        'invoice_no' => $order->get_order_number(),
        'carts' => array(),
        'buyer_data' => array(
          'name' => $address['first_name'] . ' ' . $address['last_name'],
          'email' => $address['email'],
          'mobile_no' => $address['phone'],
          'address' => $address['address_1'] . ' ' . $address['address_2']
        ),
        'payment_channel' => array(
          'type' => $this->payment_channel,
          'direct' => false
        )
      );

      foreach ($items as $key => $value) {
        $product = $order->get_product_from_item($value);

        $jualio_product = array();
        $jualio_product['name'] = $value['name'];
        $jualio_product['amount'] = intval($value['line_total']);
        $jualio_product['category'] = 'plain';
        if ($product->post->post_excerpt) {
          $jualio_product['description'] = $product->post->post_excerpt;
        } else {
          $jualio_product['description'] = $value['name'];
        }
        if (wp_get_attachment_url($product->get_image_id() )) {
          $jualio_product['image'] = wp_get_attachment_url($product->get_image_id() );
        } else {
          $jualio_product['image'] = 'https://i.jual.io/no-image.png';
        }
        
        $jualio_request['carts'][] = $jualio_product;
      }


      // add shipping cost
      $shipping_cost = 0;
      if ($order->get_total_shipping()) {
        $shipping_cost = $order->get_total_shipping();
      }
      if ($shipping_cost != 0) {
        $jualio_shipping = array(
          'name'=> 'Shipping Cost',
          'amount'=> $order->get_total_shipping(),
          'category'=> 'plain',
          'description'=> 'Shipping Cost',
          'image'=> 'https://i.jual.io/no-image.png'
        );
        $jualio_request['carts'][] = $jualio_shipping;
      }

      // add aditional fees
      $order_fees = $order->get_fees();

      foreach ($order_fees as $key => $value) {
        $fee_item = array(
          'name'=> $value['name'],
          'amount'=> intval($value['line_total']),
          'category'=> 'plain',
          'description'=> $value['name'],
          'image'=> 'https://i.jual.io/no-image.png'
        );
        $jualio_request['carts'][] = $fee_item;
      }

      // echo '<pre>';
      // print_r($jualio_request);
      // echo '</pre>';
      // wp_die('die' );

      $response = wp_remote_post( $this->liveurl, array(
        'method' => 'POST',
        'headers' => array(
          'Authorization' => 'Basic ' . $this->client_id . ':',
          'Content-Type' => 'application/json'
        ),
        'body' => json_encode($jualio_request),
        )
      );

      $response_body = json_decode($response['body']);

      // // DEBUG : cek url
      // echo 'url '.$this->liveurl;
      // // DEBUG : cek client id
      // echo 'client id '.$this->client_id;
      // // DEBUG : cek request
      // echo 'request '.json_encode($jualio_request);
      // // DEBUG : cek response
      // echo 'response '.json_encode($response['body']);
      // wp_die('die');

      return $response_body->data->payment_url;

    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
      global $woocommerce;
      $order = new WC_Order( $order_id );

      // Mark as on-hold (we're awaiting the cheque)
      $order->update_status('on-hold', __( 'Awaiting payment', 'jualiov2' ));

      // Reduce stock levels
      $order->reduce_order_stock();

      // Remove cart
      $woocommerce->cart->empty_cart();

      return array(
        'result' => 'success',
        'redirect' => $this->get_jualio_url( $order )
      );
    }

  } // end class

  /**
   * Add the Gateway to WooCommerce
   **/
  function woocommerce_add_jualio_gateway($methods) {
    $methods[] = 'WC_Jualio_2_PG';
    return $methods;
  }
  add_filter('woocommerce_payment_gateways', 'woocommerce_add_jualio_gateway' );


  /**
   * automatically set payment as complete if jualio returns true
   * http://stackoverflow.com/questions/25114082/woocommerce-action-hook-to-redirect-to-custom-thank-you-page
   *
   **/
  
  add_action( 'woocommerce_thankyou', 'wc_succes_is_success');

  function wc_succes_is_success( $order_id ){
      $order = new WC_Order( $order_id );

      if (isset($_GET['status']) && $_GET['status'] == 'SUCCESS' && strpos($_SERVER['HTTP_REFERER'], 'jualio.com')) {
      // DEV TESTING 
      // if (isset($_GET['status']) && $_GET['status'] == 'SUCCESS' ) {
        // echo 'success bro';
        // Payment complete
        $order->payment_complete();
        // Add Jualio Invoice
        $order->add_order_note( __('Jualio Invoice No: ' . $_GET['invoice_no'], 'jualiov2') );
      }
  };

  /**
   * Add an ajax get handler to catch notification from jualio
   *
   **/
  add_action('wp_ajax_nopriv_jualio_notification', 'process_jualio_notification');

  function process_jualio_notification(){
    update_option( 'test-notify-jualio', json_encode($_POST) );
  }


} // end func init

?>