<?php
/*
Plugin Name: New Coupon Apply Commission
Description: Create new coupon during order placed
Author: Think To Share
Version: 0.0.1
*/
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); 
global $wac_version;
$wac_version='0.1';
if(!defined('ABSPATH')) exit;

function wac_error() {file_put_contents(dirname(__file__).'/install_log.txt', ob_get_contents());}
if(defined('WP_DEBUG') && true===WP_DEBUG) add_action('activated_plugin','wac_error');

function wac_activate($upgrade) {
    require_once(ABSPATH.basename(get_admin_url()).'/includes/upgrade.php');
    update_option('wac_db_version',$wac_version,'no');
}

register_activation_hook(__FILE__,'wac_activate');

function wac_uninstall() {delete_option('wac_db_version');}
register_uninstall_hook(__FILE__,'wac_uninstall');


/**********Begin Coupon module*********** */



//creation of new coupon code during placing order

function wc_commission_create_new_coupon($order_id) {

    $order = wc_get_order( $order_id );
    if($order->get_status() == 'wc-completed')
    {
        apply_filters('wc_commission_create_coupon', $order_id);
        apply_filters('wc_commission_create_commission');
    }
}
add_action( 'woocommerce_order_status_changed', 'create_coupon'); 

 
if(!function_exists('create_coupon')) {

    function create_coupon($order_id) {
        global $wpdb;
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        $line_items = $order->get_items();
        $product_ids = [];
            foreach ( $line_items as $line_item ) {
                $product_ids[] = $line_item->get_product_id();
            }

        $coupon_code = wp_generate_password(8, false).$user_id;
        
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type( 'percent' );
        $coupon->set_amount( 10 );
        $coupon->set_individual_use( true );
        $coupon->set_product_ids( $product_ids) ;
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_date_expires( strtotime( '+1 month' ) );
        $coupon_id = $coupon->save();  
        
        update_post_meta( $coupon_id, 'commission_eligible', $user_id);
    }
}

add_filter('wc_commission_create_coupon', 'create_coupon',10,1);


if(!function_exists('wc_commission_create_commission_table'))
{
    //creation of new commission table for future use/
    function wc_commission_create_commission_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'commissions';
        $insert_sql  = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(11) NOT NULL AUTO_INCREMENT,
            order_id bigint(11) NOT NULL,
            user_id bigint(11) NOT NULL,
            commission_amount decimal(10,2) NOT NULL,
            commission_status varchar(20) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate";

        dbDelta($insert_sql);
    }
}

add_filter('wc_commission_create_commission','wc_commission_create_commission_table');


/**********End Coupon module*********** */


//Check if the order is placed with valid coupon or not

function wc_commission_check_order_coupon( $order_id ) {

    $order = wc_get_order( $order_id );
    $applied_coupons = $order->get_coupon_codes();
    foreach($applied_coupons as $applied_coupon) {
       $coupon = new WC_Coupon($applied_coupon);
       $metas = $coupon->get_meta_data();
        foreach($metas as $meta) {
            $data = $meta->get_data();
           if(
                $data['key'] === 'commission_eligible') 
                {
                   
                   apply_filters('wc_commission_apply_commission', $order, $data['value']);

                }
            }
    }
}
 
add_action('woocommerce_thankyou', 'wc_commission_check_order_coupon');



if(! function_exists('add_commission_for_order')) {
    
    function add_commission_for_order($order, $user_id) {
        $total = $order->calculate_totals();
        $commission = $total * 10 / 100;  
        global $wpdb;
        $table_name = $wpdb->prefix . 'commissions';
        $coupon_exists = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );

            if ($coupon_exists) {
            foreach ($coupon_exists as $result) {
                $commission_amount = $result->commission_amount;
                $wpdb->update(
                    $table_name,
                    array(
                        'commission_amount' => $commission_amount + $commission,
                    ),
                    array(
                        'order_id' => $result->order_id,
                        'user_id' => $result->user_id,
                    )
                );  
            }  

            } else {
                // Coupon code does not exist in commission table, insert new row
                $wpdb->insert(
                    $table_name,
                    array(
                        'order_id' => $order->get_id(),
                        'user_id' => $user_id,
                        'commission_amount' => $commission,
                    )
                );
            } 
        
    }
}

add_filter('wc_commission_apply_commission', 'add_commission_for_order', 10, 2);



/****Withdrawl Custom Post Type */

add_action( 'init', 'create_custom_post_type' );
 
if(!function_exists('create_custom_post_type'))
{
    function create_custom_post_type() {
 
        $args = array(
          'labels' => array(
           'name' => __( 'Withdrawal' ),
           'singular_name' => __( 'Withdrawal' )
          ),
          'public' => true,
          'has_archive' => true,
          'rewrite' => array('slug' => 'withdrawal'),
         );
         
        register_post_type( 'Withdrawal',$args);
    }
}
