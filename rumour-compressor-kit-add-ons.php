<?php

/**
 * Plugin Name: Rumour Product Add Ons
 * Description: Display products from a specific category as a dropdown on a main product page
 * Version: 1.5
 * Author: Rumour Media
 * Author URI: https://rumourmedia.com
**/

if (!defined('ABSPATH')) exit; // Exit if accessed directly


// ---------------------------------------------------------------------------------------------------------------------------------------------------------------------
function get_current_category() {
    global $post;

    // Initialize array for product categories
    $current_cats = array();
    $cat_terms = get_the_terms($post->ID, 'product_cat');

    // Get main product category names
    foreach ($cat_terms as $term) {
        $product_cat_name = $term->name;
        // Assign category names to array
        $current_cats[] = $product_cat_name;
    }
    return $current_cats;
}

// Display Compressor Location Field in 'Compressor Kits'  
// * Compressor Kit products must have Compressor Location set *
add_action('woocommerce_product_options_general_product_data', 'add_compressor_location');
function add_compressor_location() {
    $current_cats = get_current_category();

    if (in_array('Compressor Kits', $current_cats)) {
        woocommerce_wp_select(
            array(
                'id' => 'compressor_location',
                'label' => __('Compressor Location', 'woocommerce'),
                'options' => array(
                    '' => __('choose', 'woocommerce'),
                    'L8' => __('L8', 'woocommerce'),
                    'L6' => __('L6', 'woocommerce'),
                    'R8' => __('R8', 'woocommerce'),
                    'R6' => __('R6', 'woocommerce')
                ),
            )
        );
    }
}

// Save Compressor Location 
add_action('woocommerce_process_product_meta', 'save_compressor_location');
function save_compressor_location($post_id) {
    $compressor_location_select = $_POST['compressor_location'];
    update_post_meta($post_id, 'compressor_location', esc_attr($compressor_location_select));
}

function get_compressor_kits_products() {
    // create array of category products
    $args = array(
        'category'      => 'compressor-kits',
        'limit'         => -1,
    );

    // Retrieve products
    $comp_product_array = wc_get_products($args);
    return $comp_product_array;
}

// Create dropdown of Compressor Kits
function compressor_kit_options($current_cats) {
    
    $comp_product_array = get_compressor_kits_products();

    // Check if category products array is not empty 
    if (!empty($comp_product_array)) {
		$options = array();

        // Loop thru product array
        foreach ($comp_product_array as $products) {

            // Initialize array for product tags
            $tags_array =  array();
            $product_tags = wp_get_post_terms($products->get_id(), 'product_tag', array('fields' => 'names')); // Get array of Product tags 
            $tags_array = $product_tags; // Assign product tags to array

            if (array_intersect($tags_array, $current_cats)) {

                $product_id = $products->get_id(); // Get product ID
                $product_price = " +$" . $products->get_regular_price(); // Get product price
                $product_name = $products->get_name(); // Get product name
                $product_sku = $products->get_sku(); // Get product SKU
                $product_meta = $products->get_meta('compressor_location'); // Get product meta

                $options[$product_id . $product_meta] = $product_name . $product_price; // Display product as name + price
            }
        }
        
        $options = array(
            0 => __('Select an option...', 'woocommerce'),
            1 => __('No Compressor & Bracket ', 'woocommerce')
        ) + $options;

        return $options;
    }
}

function create_compressor_kit_dropdown($options) {
    $domain = 'woocommerce';

     // Add select field
     woocommerce_form_field('compressor-options', array(
        'id'            => 'compressor-options',
        'type'          => 'select',
        'label'         => __('Add Compressor & Bracket', $domain),
        'required'      => false,
        'options'       => $options,
    ), '');
    // echo '<p>Number of Compressor & Bracket options: ' . count($options) . '</p>';
}

// Add dropdown of Compressor Kits to product page
add_action('woocommerce_before_add_to_cart_button', 'complete_systems_dropdown');
function complete_systems_dropdown() {
    if (is_product()) {
        $current_cats = get_current_category();
    
        if ( in_array('Complete A/C Systems',  $current_cats) ) {
            
            $options = compressor_kit_options($current_cats);

            // foreach ($current_cats as $category) {
            //     echo "<script>console.log( '$category' );</script>";
            // }

            // foreach ($options as $option) {
            //     echo "<script>console.log( '$option' );</script>";
            // }

            $options_count = count($options);

            if ($options_count > 2) {
                create_compressor_kit_dropdown($options);   
            }

        }
    }
}

// Add selected Compressor Option to cart
add_action( 'woocommerce_add_to_cart', 'add_compressor_to_cart', 10, 6 );
function add_compressor_to_cart() {
    if ( isset( $_POST['compressor-options'] ) ) {
        // Get product ID
        $the_product_id = sanitize_text_field( $_POST['compressor-options'] );

        // Get cart
        $cart = WC()->cart;

        // If cart is NOT empty
        if ( ! $cart->is_empty() ) {
            // Cart id
            $product_cart_id = $cart->generate_cart_id( $the_product_id );

            // Find product in cart
            $in_cart = $cart->find_product_in_cart( $product_cart_id );

            // If product NOT in cart
            if ( ! $in_cart ) {
                remove_action('woocommerce_add_to_cart', __FUNCTION__);
                $cart->add_to_cart( $the_product_id );
            } 
        } else {
            remove_action('woocommerce_add_to_cart', __FUNCTION__);
            $cart->add_to_cart( $the_product_id );
        }
    }
}

add_filter('woocommerce_add_cart_item_data', 'add_compressor_location_data', 10, 3);
function add_compressor_location_data($cart_item_data, $product_id, $variation_id) {
    $compressor_option = filter_input(INPUT_POST, 'compressor-options');

    if ( ! empty($compressor_option) ) {
        $cart_item_data['compressor-options'] = substr($compressor_option, -2);
        return $cart_item_data;
    }
    return $cart_item_data;
}

add_filter('woocommerce_cart_item_name', 'compressor_location_cart_field', 10, 2);
function compressor_location_cart_field($title, $cart_item) {
    global $product;

    $product = $cart_item['data'];
    $product_type = $product->get_type();
    $product_cat_ids = $cart_item['data']->get_category_ids();

    if (is_cart() || is_checkout()) {
        $sku = '<br/>SKU: ' . $product->get_sku();
        
        if (!empty($cart_item['compressor-options']) && $product_type == 'variation' || !empty($cart_item['compressor-options']) && in_array(277, $product_cat_ids)) {
            $compressor_location = $cart_item['compressor-options'];
            $sku .= $compressor_location;
        }

        echo $title;
        echo $sku;
    }
}

add_action( 'woocommerce_add_order_item_meta', 'add_compressor_location_to_cart_item', 10, 3 );
function add_compressor_location_to_cart_item( $item_id, $cart_item, $cart_item_key ) {
    $custom_field_value = $cart_item['compressor-options'];
    $product_cat_ids = $cart_item['data']->get_category_ids();

    // We add the custom field value as an attribute for this product 277
    if (!empty($custom_field_value) && in_array(13051, $product_cat_ids) ) {
        wc_update_order_item_meta( $item_id, 'Compressor Location', $custom_field_value );
    }
}  