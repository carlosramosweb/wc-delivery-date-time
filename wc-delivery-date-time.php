<?php
//error_reporting(0);
/*---------------------------------------------------------
Plugin Name: Delivery Date & Time for Woocommerce
Plugin URI: https://profiles.wordpress.org/carlosramosweb/#content-plugins
Author: carlosramosweb
Author URI: https://criacaocriativa.com
Donate link: https://donate.criacaocriativa.com
Description: Esse plugin é uma versão BETA. Sistema de escolha de datas para entrega de delivery ou mercado com horas e carência de tempo para montar a compra.
Text Domain: wc-delivery-date-time
Version: 3.0.0
Requires at least: 3.5.0
Tested up to: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html 
Package: WooCommerce
------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Delivery_Date_Time_for_WooCommerce' ) ) {		
	class Delivery_Date_Time_for_WooCommerce {
	    
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init_functions' ) );
			register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		}

		public function init_functions() { 
			add_action( 'init', array( $this, 'wc_load_plugin_textdomain' ) );
			add_action( 'admin_menu', array( $this, 'wc_plugin_menu' ) );
			add_action( 'woocommerce_review_order_after_order_total', array( $this, 'customise_checkout_field' ), 10, 2 );
			add_action( 'woocommerce_checkout_process', array( $this, 'wc_delivery_field_validation' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'wc_delivery_field_update' ) );
			add_filter( 'woocommerce_email_order_meta', array( $this, 'wc_email_order_meta' ), 10, 3 );
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'wc_order_columns_function' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'wc_order_columns_values_function' ) );
			add_filter( 'wp_ajax_customise_checkout_field_times_callback', array( $this, 'customise_checkout_field_times_callback' ) );
			add_filter( 'wp_ajax_customise_checkout_field_times_callback', array( $this, 'customise_checkout_field_times_callback' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links_settings' ) );
		}

		public function activate_plugin() {	
			update_option( 'Activated_Plugin', 'wc-delivery-date-time', 'yes' );	
			if ( is_admin() && get_option( 'Activated_Plugin' ) == 'wc-delivery-date-time' ) {
				$delivery_settings = array();
				$delivery_settings = get_option( 'wc_delivery_date_time_settings' );
				if( $delivery_settings == "" ) {				
					$new_settings = array(
						'wc_settings_enabled'			=> "yes",
						'wc_opening_hours'				=> 8,
						'wc_closing_hours'				=> 18,
						'wc_number_days_available'		=> 5,
						'wc_time_interval_delivery'		=> 2,
						'wc_time_for_goods_separation'	=> 2,
						'wc_shipping_methods'			=> "",
						'wc_pickup_methods'				=> ""
					);
					update_option( "wc_delivery_date_time_settings", $new_settings, 'yes' );
				}
			}
		}

		public function wc_load_plugin_textdomain(){
			load_plugin_textdomain( 'wc-delivery-date-time', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
		}

		public static function plugin_action_links_settings( $links ) {
			$action_links = array(
				'settings'	=> '<a href="' . esc_url( admin_url( 'admin.php?page=wc_delivery_date_time_settings' ) ) . '" title="'. __( 'Settings Plugin', 'wc-delivery-date-time' ) .'" class="error">'. __( 'Settings', 'wc-delivery-date-time' ) .'</a>',
				'donate' 	=> '<a href="' . esc_url( 'https://donate.criacaocriativa.com') . '" title="'. __( 'Donation Plugin', 'wc-delivery-date-time' ) .'" class="error">'. __( 'Donation', 'wc-delivery-date-time' ) .'</a>',
			);
	
			return array_merge( $action_links, $links );
		}

		public function wc_plugin_menu(){
			add_menu_page( 
				__( 'Woocommerce Order Delivery', 'wc-delivery-date-time' ),
				__( 'Delivery DateTime', 'wc-delivery-date-time' ), 
				'manage_options', 
				'wc_delivery_date_time_settings', 
				array( $this, 'wc_admin_page_delivery_date_time_settings' ),
				'dashicons-store', 
				59 
			);
		}

		public function wc_email_order_meta( $order_obj, $sent_to_admin, $plain_text ) {
			$delivery_type 		= get_post_meta( $order_obj->get_id(), 'wc_delivery_type', true );
			$delivery_date 		= get_post_meta( $order_obj->get_id(), 'wc_ddt_delivery_date', true );
			$delivery_time 		= get_post_meta( $order_obj->get_id(), 'wc_ddt_delivery_time', true );
			$delivery_date_time = $delivery_date . " " . $delivery_time;

			if ( $plain_text === false && $delivery_type != "" ) {
				echo '<h2>' . $delivery_type . '</h2><ul>';
				echo '<li><strong>' . $delivery_date_time .'</strong></li></ul>';
			} else {
				echo "$delivery_type \n $delivery_date_time";
			}
		}

		public function wc_delivery_field_validation(){
			if ( isset( $_POST['wc_ddt_radio'] ) && $_POST['wc_ddt_radio'] == "home_delivery" ||
				isset( $_POST['wc_ddt_radio'] ) && $_POST['wc_ddt_radio'] == "local_pickup"	) { 
				$wc_ddt_date = sanitize_text_field( $_POST['wc_ddt_date'] );
				$wc_ddt_time = sanitize_text_field( $_POST['wc_ddt_time']);
				if ( $wc_ddt_date == "" ){
					wc_add_notice( __( '<strong>Delivery Date</strong> is a required.', 'wc-delivery-date-time' ), 'error' );
				}
				if ( $wc_ddt_time == "" ){
					wc_add_notice( __( '<strong>Delivery Time</strong> is a required.', 'wc-delivery-date-time' ), 'error' );
				}
			}
		}

		public function wc_order_columns_function( $columns ){
			$delivery_settings = get_option( 'wc_delivery_date_time_settings' );
			if ( $delivery_settings['wc_settings_enabled'] == "yes" ) {
				$columns['wc_delivery_date_time'] = __( 'Delivery', 'wc-delivery-date-time' );
			}
			return $columns;
		}

		public function wc_order_columns_values_function( $column ) {
			if ( $column == 'wc_delivery_date_time' ) {				
				$delivery_date = esc_attr( get_post_meta( get_the_ID(), 'wc_ddt_delivery_date', true ) );
				$delivery_time = esc_attr( get_post_meta( get_the_ID(), 'wc_ddt_delivery_time', true ) );
				if( $delivery_date != "" && $delivery_time != "" ) {
					echo $delivery_date;
					echo "<br/>";
					echo $delivery_time;
				} else {
					echo "N/A";
				}
			}
		}

		public function wc_delivery_field_update( $order_id ) {
			if ( isset( $_POST['wc_ddt_radio'] ) && $_POST['wc_ddt_radio'] == "home_delivery" ||
				isset( $_POST['wc_ddt_radio'] ) && $_POST['wc_ddt_radio'] == "local_pickup" ) { 
				if ( isset( $_POST['wc_ddt_date'] ) && $_POST['wc_ddt_date'] != "" ) { 
					if ( isset( $_POST['wc_ddt_time'] ) && $_POST['wc_ddt_time'] != "" ) {

						$delivery_type = __( 'Home Delivery', 'wc-delivery-date-time' );
						if ( $_POST['wc_ddt_radio'] == "local_pickup" ) {
							$delivery_type = __( 'Scheduled Local Pickup', 'wc-delivery-date-time' );
						}

						$delivery_date = $_POST['wc_ddt_date'];
						$delivery_time = $_POST['wc_ddt_time'];

						update_post_meta( $order_id, 'wc_ddt_delivery_type', sanitize_text_field( $delivery_type ) );
						update_post_meta( $order_id, 'wc_ddt_delivery_date', sanitize_text_field( $_POST['wc_ddt_date'] ) );
						update_post_meta( $order_id, 'wc_ddt_delivery_time', sanitize_text_field( $_POST['wc_ddt_time'] ) );

						$order = new WC_Order( $order_id );
						$current_user = wp_get_current_user();

						$order_note = "\n" . $delivery_type . ":\n" . $delivery_date . " " . $delivery_time . "\n\n";
						$order->add_order_note( $order_note, $current_user->display_name );

						$order_post 	= get_post( $order_id );
						$order_excerpt	= $order_post->post_excerpt;
						$order_note 	= $delivery_type . ":\n" . $delivery_date . " " . $delivery_time . "\n\n";
						$note_data 		= array(
						  'ID' 				=> $order_id,
						  'post_excerpt'	=> $order_note . $order_excerpt,
						 );						 
						wp_update_post( $note_data );
					}
				}
			}
		}

		public function holiday_days_check( $set_date ) {
			date_default_timezone_set( 'America/Sao_Paulo' );

			if ( $set_date != "" ) { 
				$wc_date 			= "";
				$delivery_settings 	= array();
				$delivery_settings 	= get_option( 'wc_delivery_date_time_settings' );
				$wc_holiday_days 	= esc_attr( $delivery_settings['wc_holiday_days'] );

				$wc_holiday_days 	= str_replace( " ", "", $wc_holiday_days );
				$holiday_days 		= explode( ",", $wc_holiday_days );

				foreach ( $holiday_days as $days ) {
					if ( $set_date == $days ) {
						$en_date 	= implode( '-', array_reverse( explode( '/', $set_date ) ) );
						$add_date 	= date( "Y-m-d", strtotime( $en_date . ' +1 day') );
						$wc_date 	= implode( '/', array_reverse( explode( '-', $add_date ) ) );
					}
				}
				return $wc_date;

			} else {
				return "0";
			}
		}

		public function customise_checkout_field( $checkout ){

			date_default_timezone_set( 'America/Sao_Paulo' );
			$delivery_settings = array();
			$delivery_settings = get_option( 'wc_delivery_date_time_settings' );
			$enabled = esc_attr( $delivery_settings['wc_settings_enabled'] );

			if ( $enabled == "yes" ) {

				$wc_opening_hours 				= (int) esc_attr( $delivery_settings['wc_opening_hours'] );
				$wc_start_interval_hours_shop 	= (int) esc_attr( $delivery_settings['wc_start_interval_hours_shop'] );
				$wc_interval_finish_hours_shop 	= (int) esc_attr( $delivery_settings['wc_interval_finish_hours_shop'] );
				$wc_closing_hours 				= (int) esc_attr( $delivery_settings['wc_closing_hours'] );

				$wc_shipping_methods 			= esc_attr( $delivery_settings['wc_shipping_methods'] );
				$wc_pickup_methods 				= esc_attr( $delivery_settings['wc_pickup_methods'] );

				$wc_number_days_available 		= (int) esc_attr( $delivery_settings['wc_number_days_available'] );
				$time_interval_delivery 		= (int) esc_attr( $delivery_settings['wc_time_interval_delivery'] );
				$time_for_goods_separation 		= (int) esc_attr( $delivery_settings['wc_time_for_goods_separation'] );
				$wc_holiday_days 				= $delivery_settings['wc_holiday_days'];

				$wc_day_week = array(
					'0' => 'sunday',
					'1' => 'monday', 
					'2' => 'tuesday', 
					'3' => 'wednesday', 
					'4' => 'thursday', 
					'5' => 'friday', 
					'6' => 'saturday'
				);

				$set_day 			= date( "d/m/Y", strtotime( "now" ) );
				$wc_day_check 		= strtolower( date( "l", strtotime( "now" ) ) );
				$hours_now_check 	= substr( date( "H:i" ), 0, 2 );

				if( $hours_now_check >= $wc_closing_hours ) {
					$en_date 		= implode( '-', array_reverse( explode( '/', $set_day ) ) );
					$en_date 		= date( $en_date, strtotime( "+1 day" ) );
					$set_day 		= implode( '/', array_reverse( explode( '-', $en_date ) ) );
					$wc_day_check 	= strtolower( date( "l", strtotime( "+1 day" ) ) );
				}

				if( $wc_holiday_days != "" ) {
					$holiday_check = $this->holiday_days_check( $set_day );
					if ( $holiday_check != "" && $holiday_check != "0" ) {
						$wc_holiday 	= strtolower( date( "Y-m-d", strtotime( $holiday_check ) ) );
						$wc_day_check 	= strtolower( date( "l", strtotime( $wc_holiday ) ) );
					}
				}

				foreach ( $wc_day_week as $key => $wc_day ) {
					if ( $wc_day_check == $wc_day ) {
						$day_week 				= esc_attr( $delivery_settings['wc_day_week_' . $wc_day] );
						$opening_hours  		= esc_attr( $delivery_settings['wc_opening_hours_' . $wc_day] );
						$start_interval_hours  	= esc_attr( $delivery_settings['wc_start_interval_hours_' . $wc_day] );
						$interval_finish_hours  = esc_attr( $delivery_settings['wc_interval_finish_hours_' . $wc_day] );
						$closing_hours 			= esc_attr( $delivery_settings['wc_closing_hours_' . $wc_day] );
						$disable_interval 		= esc_attr( $delivery_settings['wc_disable_interval_' . $wc_day] );
					}
				}

				if ( $opening_hours != "" ) {
					$wc_opening_hours  = (int) $opening_hours;
				}
				if ( $closing_hours != "" ) {
					$wc_closing_hours  = (int) $closing_hours;
				}
				if ( $disable_interval == "" ) {
					if ( $start_interval_hours == "" && $interval_finish_hours == "" ) {
						if ( $wc_start_interval_hours_shop != "" && $wc_interval_finish_hours_shop != "" ) {
							$start_interval_hours 	= (int) $wc_start_interval_hours_shop;
							$interval_finish_hours 	= (int) $wc_interval_finish_hours_shop;
						}
					}
				}
				if ( $time_for_goods_separation > 0 && $wc_day_check == $day_week ) {
					$wc_opening_hours  = (int) ( $hours_now_check + $time_for_goods_separation );
				}

				if ( $time_interval_delivery > 1 ) {
					$time_interval = $time_interval_delivery;
					while ( $wc_opening_hours <= $wc_closing_hours ) {
						$d 					= mktime( $wc_opening_hours, 0 );
						$wc_date_hours 		= substr( date( "H:i", $d ), 0, 2 );
						$wc_date_hours_last = ( $wc_date_hours + $time_interval );
						if ( $wc_date_hours_last > $wc_closing_hours ) {
							$wc_date_hours_last = $wc_closing_hours;
						}
						if ( $start_interval_hours != "" && $interval_finish_hours != "" ) {
							if ( $wc_date_hours >= $start_interval_hours && $wc_date_hours_last <= $interval_finish_hours ) {
								$range_arr[$wc_opening_hours]['disabled'] 	= "yes";
								$range_arr[$wc_opening_hours]['value'] 		= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
							} else {
								$range_arr[$wc_opening_hours]['disabled'] 	= "";
								$range_arr[$wc_opening_hours]['value'] 		= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
							}
						} else {
							$range_arr[$wc_opening_hours]['disabled'] 	= "";
							$range_arr[$wc_opening_hours]['value']  	= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
						}

						$range_arr[$wc_opening_hours]['first'] = date( "H", $d );
						$range_arr[$wc_opening_hours]['last']  = $wc_date_hours_last;

						if ( $wc_date_hours_last >= $wc_closing_hours ) {
							$wc_opening_hours 	+= $wc_closing_hours + 1;
						}
						$wc_opening_hours += $time_interval;
					}
				} else {
					while ( $wc_opening_hours <= $wc_closing_hours ) {
						$d = mktime( $wc_opening_hours, 0 );
						if ( $start_interval_hours != "" && $interval_finish_hours != "" ) {
							$wc_hours_check = substr( date( "H:i", $d ), 0, 2 );
							if ( $wc_hours_check > $start_interval_hours && $wc_hours_check < $interval_finish_hours ) {
								$range_arr[$wc_opening_hours]['disabled'] 	= "yes";
								$range_arr[$wc_opening_hours]['value'] 		= "às " . date( "H", $d ) . "h";
							} else {
								$range_arr[$wc_opening_hours]['disabled'] 	= "";
								$range_arr[$wc_opening_hours]['value'] 		= "às " . date( "H", $d ) . "h";
							}				
						} else {
							$range_arr[$wc_opening_hours]['disabled'] 	= "";
							$range_arr[$wc_opening_hours]['value'] 		= "às " . date( "H", $d ) . "h";
						}

						$range_arr[$wc_opening_hours]['first'] = date( "H", $d );
						$range_arr[$wc_opening_hours]['last']  = "";

						$wc_opening_hours++;
					}
				}

				function createDateRange( $startDate, $endDate, $format = "d/m/Y" ){
					$begin 	= new DateTime( $startDate );
					$end 	= new DateTime( $endDate );

					$interval 	= new DateInterval( 'P1D' ); // 1 Day
					$dateRange 	= new DatePeriod( $begin, $interval, $end );

					$range = [];
					foreach ( $dateRange as $key => $date ) {
						$range[$date->format( $format )]['disabled'] 	= "";
					    $range[$date->format( $format )]['value'] 		= $date->format( $format );
					}
					return $range;
				}

				$wc_number_days_available = ( $wc_number_days_available != "" ) ? $wc_number_days_available : 5;
				$number_days = "+" . $wc_number_days_available . " day";
				$get_date = date( "m/d/Y" ) . " - " . date( "m/d/Y", strtotime( $number_days ) );

				$date_range 	= explode( '-', $get_date, 2 );
				$start_date 	= (string) $date_range[0];
				$end_date 		= (string) $date_range[1];
				$date_range_arr = createDateRange( $start_date, $end_date );

				$sys_day_week = [];
				$sys_day_week['sunday'] 	= esc_attr( $delivery_settings['wc_day_week_sunday'] );
				$sys_day_week['monday'] 	= esc_attr( $delivery_settings['wc_day_week_monday'] );
				$sys_day_week['tuesday'] 	= esc_attr( $delivery_settings['wc_day_week_tuesday'] );
				$sys_day_week['wednesday'] 	= esc_attr( $delivery_settings['wc_day_week_wednesday'] );
				$sys_day_week['thursday'] 	= esc_attr( $delivery_settings['wc_day_week_thursday'] );
				$sys_day_week['friday'] 	= esc_attr( $delivery_settings['wc_day_week_friday'] );
				$sys_day_week['saturday'] 	= esc_attr( $delivery_settings['wc_day_week_saturday'] );

				$day_check = [];
				foreach ( $sys_day_week as $key => $value ) {
					if ( $value != "yes" ) {
						$day_check[$key] = $key;
					} else {
						unset( $day_check[$key] );
					}
				}

				foreach ( $date_range_arr as $key => $value ) {
					$br_date 	= $value['value'];
					$en_date 	= implode( '-', array_reverse( explode( '/', $value['value'] ) ) );
					$day_week 	= strtolower( date( "l", strtotime( $en_date ) ) );
					if ( in_array( $day_week, $day_check ) && $day_week == $day_check[$day_week] ) {
						$date_range_arr[$key]['disabled'] 		= "yes";
						$date_range_arr[$key]['disabled_label'] = " [" . __( 'Closed Store', 'wc-delivery-date-time' ) . "] ";
					}
					$holiday_check = "";
					$holiday_check = $this->holiday_days_check( $br_date );
					if ( $holiday_check != "" && $holiday_check != "0" ) {
						$date_range_arr[$key]['disabled'] 		= "yes";
						$date_range_arr[$key]['disabled_label'] = " [" . __( 'Holiday Day', 'wc-delivery-date-time' ) . "] ";
					}
				}

				$option_date = '';
				foreach ( $date_range_arr as $key => $value ) {
					$disabled 		= "";
					$disabled_label = "";
					if ( $value['disabled'] == "yes" ) {
						$disabled 		= ( $value['disabled'] == "yes" ) ? 'disabled=""' : "";
						$disabled_label = $value['disabled_label'];
						$option_date .= '<option value="' . $value['value'] . '" ' . $disabled . '>' . $value['value'] . $disabled_label . '</option>';
					} else {
						$option_date .= '<option value="' . $value['value'] . '">' . $value['value'] . '</option>';
					}
				}

				$option_time = '<option value="">' . __( 'Select Time', 'wc-delivery-date-time' ) . '</option>';
				foreach ( $range_arr as $key => $value ) {
					$disabled 		= "";
					$disabled_label = "";
					if ( $value['disabled'] == "yes" ) {
						$disabled 		= ( $value['disabled'] == "yes" ) ? 'disabled=""' : "";
						$disabled_label = " [" . __( 'Interval Shop', 'wc-delivery-date-time' ) . "] ";
						$option_time .= '<option value="' . $value['value'] . '" ' . $disabled . '>' . $value['value'] . $disabled_label . '</option>';
					} else {
						$separation = "+" . $time_for_goods_separation . " hour";
						$time_separation = date( "H", strtotime( $separation ) );
						if ( $time_for_goods_separation > 0 && $time_separation >= $value['first'] ) {
							$disabled_label = " [" . __( 'Unavailable', 'wc-delivery-date-time' ) . "] ";
							$option_time .= '<option value="' . $value['value'] . '" disabled="">' . $value['value'] . $disabled_label . '</option>';
						} else {
							$option_time .= '<option value="' . $value['value'] . '">' . $value['value'] . '</option>';
						}	
					}
				}

				$wc_style_display 	= 'style="display:none;"';
				$wc_display_methods = '';
				$chosen_methods 	= WC()->session->get( 'chosen_shipping_methods' );
				$chosen_method 		= strstr( $chosen_methods[0], ':', true );

				if( $wc_shipping_methods != "" && $chosen_method == $wc_shipping_methods ) {
					$wc_style_display = 'style="display:block;"';
					$wc_display_methods = 'delivery';
				} else if( $wc_pickup_methods != "" && $chosen_method == $wc_pickup_methods ) {
					$wc_style_display = 'style="display:block;"';
					$wc_display_methods = 'pickup';
				}


				echo '<tr class="order-date-time">';
				echo '<td colspan="2" class="td-date-time" style="padding: 0 !important; text-align: left;">';

				echo '<div id="wc-ddt-loading" style="display:none;">';
				echo '<img src="' . esc_url( plugins_url( 'images/loading.gif', __FILE__ ) ) . '" class="ddt-loading">';
				echo '</div>';

				echo '<div id="wc-delivery-date-time" class="customise_checkout_field" ' . $wc_style_display . '>';

				if ( $wc_display_methods == 'delivery' ) {
					echo '<h3>' . __( 'Delivery Scheduling', 'wc-delivery-date-time' ) . '</h3>';
					$select_opction = '<option value="home_delivery">' . __( 'Schedule Delivery', 'wc-delivery-date-time' ) . '</option>';
				} else if ( $wc_display_methods == 'pickup' ) {
					echo '<h3>' . __( 'Local Pickup Scheduling', 'wc-delivery-date-time' ) . '</h3>';
					$select_opction = '<option value="local_pickup">' . __( 'Schedule Local Pickup', 'wc-delivery-date-time' ) . '</option>';
				} else {
					echo '<h3>' . __( 'Scheduling', 'wc-delivery-date-time' ) . '</h3>';
					$select_opction = '';
				}

				echo '
				<p class="form-row wc_ddt_radio" id="wc_radio_field">
					<label for="wc_ddt_radio" class="">' . __( 'Do you want to Schedule Delivery Date and Time at Your Home or pickup at the store?', 'wc-delivery-date-time' ) . '&nbsp;
					<span class="optional">' . __( '(opcional)', 'wc-delivery-date-time' ) . '</span>
					</label>
					<span class="woocommerce-input-wrapper">
					<select name="wc_ddt_radio" id="wc-ddt-select-type" class="select" data-placeholder="' . __( 'Select date', 'wc-delivery-date-time' ) . '">
					   <option value="standard_delivery">' . __( 'Not', 'wc-delivery-date-time' ) . '</option>
					   ' . $select_opction . '
					</select>
					</span>
				</p>';

				echo '
				<p class="form-row wc_ddt_select_date" id="wc-ddt-box-date" style="display:none;">
					<label for="wc_ddt_date" class="wc_ddt_delivery_date">' . __( 'Delivery Date', 'wc-delivery-date-time' ) . '&nbsp; <span class="required">' . __( '(required)', 'wc-delivery-date-time' ) . '</span></label>
					<label for="wc_ddt_date" class="wc_ddt_pickup_date" style="display:none;">' . __( 'Pickup Date', 'wc-delivery-date-time' ) . '&nbsp; <span class="required">' . __( '(required)', 'wc-delivery-date-time' ) . '</span></label>
					<span class="woocommerce-input-wrapper">
					<select name="wc_ddt_date" id="wc-ddt-select-date" class="select" data-placeholder="' . __( 'Select date', 'wc-delivery-date-time' ) . '" required="">
					   ' . $option_date . '
					</select>
					</span>
			  	</p>';

				echo '
				<p class="form-row wc_ddt_select_time" id="wc-ddt-box-time" style="display:none;">
				 <label for="wc_ddt_time" class="wc_ddt_delivery_time">' . __( 'Delivery Time', 'wc-delivery-date-time' ) . '&nbsp; <span class="required">' . __( '(required)', 'wc-delivery-date-time' ) . '</span></label>
					<label for="wc_ddt_date" class="wc_ddt_pickup_time" style="display:none;">' . __( 'Pickup Time', 'wc-delivery-date-time' ) . '&nbsp; <span class="required">' . __( '(required)', 'wc-delivery-date-time' ) . '</span></label>
				 <span class="woocommerce-input-wrapper">
				    <select name="wc_ddt_time" id="wc-ddt-select-time" class="select" data-placeholder="' . __( 'Select Time', 'wc-delivery-date-time' ) . '" required="">
				       ' . $option_time . '
				    </select>
				 </span>
				</p>';

				echo '</div>';

				echo '</td>';
				echo '</tr>';
				?>
				<style type="text/css">
					#wc-delivery-date-time {
						display: block;
						overflow: hidden;
						width: 100%;
						height: auto;
						background-color: #dad9d9;
						padding: 20px 20px 10px;
						margin-top: 30px;
						margin-bottom: 30px;
						border-radius: 0px;
					}
					#wc-ddt-loading { 
						display: block;
						position: absolute;
					    z-index: 2;
					    width: 100%;
					    max-width: 400px;
					    height: auto;
					    min-height: 405px;
					    background-color: #f1f2f3;
					    text-align: center;
					    opacity: 0.8;
					    margin-top: 20px;
					}
					#wc-ddt-loading .ddt-loading {
						display: block;
						width: 35px;
						height: 35px; 
    					margin: 0 auto;
    					margin-top: 150px;
					}
					#wc-delivery-date-time select {
						color: #444;
						line-height: 28px;
						padding: 10px 20px;
						border-radius: 30px;
						min-width: 200px;
						width: 100%;
					}
				</style>
				<script>					
					var wc_select_type 	= document.getElementById( "wc-ddt-select-type" );
					var wc_select_date 	= document.getElementById( "wc-ddt-select-date" );
					var wc_select_time 	= document.getElementById( "wc-ddt-select-time" );

					wc_select_type.addEventListener( 'change', ( event ) => {
						document.getElementById('wc-ddt-loading').setAttribute("style", "display: block;");
						if ( event.target.value == "home_delivery" || event.target.value == "local_pickup" ) {
							document.getElementById('wc-ddt-box-date').setAttribute( "style", "display: block;" );
							document.getElementById('wc-ddt-box-time').setAttribute( "style", "display: block;" );
							if ( event.target.value == "local_pickup" ) {
								document.getElementsByClassName('wc_ddt_delivery_date')[0].setAttribute( "style", "display: none" );
								document.getElementsByClassName('wc_ddt_delivery_time')[0].setAttribute( "style", "display: none;" );
								document.getElementsByClassName('wc_ddt_pickup_date')[0].setAttribute( "style", "display: block;" );
								document.getElementsByClassName('wc_ddt_pickup_time')[0].setAttribute( "style", "display: block;" );
							} else {
								document.getElementsByClassName('wc_ddt_pickup_date')[0].setAttribute( "style", "display: none;" );
								document.getElementsByClassName('wc_ddt_pickup_time')[0].setAttribute( "style", "display: none;" );
								document.getElementsByClassName('wc_ddt_delivery_date')[0].setAttribute( "style", "display: block;" );
								document.getElementsByClassName('wc_ddt_delivery_time')[0].setAttribute( "style", "display: block;" );
							}
							document.getElementById('wc-ddt-select-date').focus();
						} else {
							document.getElementById('wc-ddt-box-date').setAttribute( "style", "display: none;" );
							document.getElementById('wc-ddt-box-time').setAttribute( "style", "display: none;" );
						}
						document.getElementById('wc-ddt-loading').setAttribute( "style", "display: none;" );
					});

					wc_select_time.addEventListener( 'change', ( event ) => {
						if ( event.target.value != "" ) {
							console.log( 'wc-ddt-select-time' );
							document.getElementById('payment').focus();
						} 
					});
					
					wc_select_date.addEventListener( 'change', ( event ) => {
						document.getElementById('wc-ddt-loading').setAttribute("style", "display: block;");
						jQuery.post(
						    woocommerce_params.ajax_url,
						    { 
						    	'action': 'customise_checkout_field_times_callback',
						    	'set_date'	: event.target.value,
						    },
						    function( response, status ) {
								document.getElementById('wc-ddt-loading').setAttribute( "style", "display: none;" );
								document.getElementById('wc-ddt-select-time').innerHTML = "";
								document.getElementById('wc-ddt-select-time').innerHTML = response;
								document.getElementById('wc-ddt-select-time').focus();
						    }
						);
					});
				</script>
				<?php
			}
		}

		public function customise_checkout_field_times_callback(){
			date_default_timezone_set( 'America/Sao_Paulo' );

			if ( isset( $_POST['set_date'] ) && $_POST['set_date'] != "" ) {

				$delivery_settings = array();
				$delivery_settings = get_option( 'wc_delivery_date_time_settings' );

				$wc_opening_hours_shop 			= (int) esc_attr( $delivery_settings['wc_opening_hours'] );
				$wc_start_interval_hours_shop 	= (int) esc_attr( $delivery_settings['wc_start_interval_hours_shop'] );
				$wc_interval_finish_hours_shop 	= (int) esc_attr( $delivery_settings['wc_interval_finish_hours_shop'] );
				$wc_closing_hours_shop 			= (int) esc_attr( $delivery_settings['wc_closing_hours'] );

				$wc_number_days_available 		= (int) esc_attr( $delivery_settings['wc_number_days_available'] );
				$time_interval_delivery 		= (int) esc_attr( $delivery_settings['wc_time_interval_delivery'] );
				$time_for_goods_separation 		= (int) esc_attr( $delivery_settings['wc_time_for_goods_separation'] );

				$wc_day_week = array(
					'0' => 'sunday',
					'1' => 'monday', 
					'2' => 'tuesday', 
					'3' => 'wednesday', 
					'4' => 'thursday', 
					'5' => 'friday', 
					'6' => 'saturday'
				);

				$date_now 			= date( "Y-m-d" );
				$set_date 			= sanitize_text_field( $_POST['set_date'] );
				$en_date 			= implode( '-', array_reverse( explode( '/', $set_date ) ) );
				$wc_day_check 		= strtolower( date( "l", strtotime( $en_date ) ) );
				$hours_now_check 	= substr( date( "H:i" ), 0, 2 );

				if( $set_date == $date_now && $hours_now_check >= $wc_closing_hours ) {
					$wc_day_check = strtolower( date( "Y-m-d", strtotime( "+1 day" ) ) );
				}

				$day_week 				= esc_attr( $delivery_settings['wc_day_week_' . $wc_day_check] );
				$opening_hours  		= esc_attr( $delivery_settings['wc_opening_hours_' . $wc_day_check] );
				$start_interval_hours  	= esc_attr( $delivery_settings['wc_start_interval_hours_' . $wc_day_check] );
				$interval_finish_hours  = esc_attr( $delivery_settings['wc_interval_finish_hours_' . $wc_day_check] );
				$closing_hours 			= esc_attr( $delivery_settings['wc_closing_hours_' . $wc_day_check] );
				$disable_interval 		= esc_attr( $delivery_settings['wc_disable_interval_' . $wc_day_check] );

				if ( $wc_opening_hours_shop != "" && $wc_closing_hours_shop != "" ) {
					$wc_opening_hours  = (int) $wc_opening_hours_shop;
					$wc_closing_hours  = (int) $wc_closing_hours_shop;
				}
				if ( $wc_opening_hours_shop == "" && $wc_closing_hours_shop == "" ) {
					$wc_opening_hours  = 8;
					$wc_closing_hours  = 18;
				}
				if ( $opening_hours != "" && $closing_hours != "" ) {
					$wc_opening_hours  = (int) $opening_hours;
					$wc_closing_hours  = (int) $closing_hours;
				}

				if ( $disable_interval == "" ) {
					if ( $start_interval_hours == "" && $interval_finish_hours == "" ) {
						if ( $wc_start_interval_hours_shop != "" && $wc_interval_finish_hours_shop != "" ) {
							$start_interval_hours 	= (int) $wc_start_interval_hours_shop;
							$interval_finish_hours 	= (int) $wc_interval_finish_hours_shop;
						}
					}
				}

				if ( $time_for_goods_separation > 0 && $wc_day_check == $day_week ) {
					$wc_opening_hours  = (int) ( $hours_now_check + $time_for_goods_separation );
				}

				if ( $time_interval_delivery > 1 ) {
					$time_interval = $time_interval_delivery;
					while ( $wc_opening_hours <= $wc_closing_hours ) {
						$d 					= mktime( $wc_opening_hours, 0 );
						$wc_date_hours 		= substr( date( "H:i", $d ), 0, 2 );
						$wc_date_hours_last = ( $wc_date_hours + $time_interval );
						if ( $wc_date_hours_last > $wc_closing_hours ) {
							$wc_date_hours_last = $wc_closing_hours;
						}
						if ( $start_interval_hours != "" && $interval_finish_hours != "" ) {
							if ( $wc_date_hours >= $start_interval_hours && $wc_date_hours_last <= $interval_finish_hours ) {
								$range_arr[$wc_opening_hours]['disabled'] 	= "yes";
								$range_arr[$wc_opening_hours]['value'] 		= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
							} else {
								$range_arr[$wc_opening_hours]['disabled'] 	= "";
								$range_arr[$wc_opening_hours]['value'] 		= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
							}
						} else {
							$range_arr[$wc_opening_hours]['disabled'] 	= "";
							$range_arr[$wc_opening_hours]['value']  	= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
						}

						$range_arr[$wc_opening_hours]['first'] = date( "H", $d );
						$range_arr[$wc_opening_hours]['last']  = $wc_date_hours_last;

						if ( $wc_date_hours_last >= $wc_closing_hours ) {
							$wc_opening_hours 	+= $wc_closing_hours + 1;
						}
						$wc_opening_hours += $time_interval;
					}
				} else {
					while ( $wc_opening_hours <= $wc_closing_hours ) {
						$d = mktime( $wc_opening_hours, 0 );
						if ( $start_interval_hours != "" && $interval_finish_hours != "" ) {
							$wc_hours_check = substr( date( "H:i", $d ), 0, 2 );
							if ( $wc_hours_check > $start_interval_hours && $wc_hours_check < $interval_finish_hours ) {
								$range_arr[$wc_opening_hours]['disabled'] 	= "yes";
								$range_arr[$wc_opening_hours]['value'] 		= "Das " . date( "H", $d ) . "h às " . $wc_date_hours_last . "h";
							} else {
								$range_arr[$wc_opening_hours]['disabled'] 	= "";
								$range_arr[$wc_opening_hours]['value'] 		= "às " . date( "H", $d ) . "h";
							}				
						} else {
							$range_arr[$wc_opening_hours]['disabled'] 	= "";
							$range_arr[$wc_opening_hours]['value'] 		= "às " . date( "H", $d ) . "h";
						}

						$range_arr[$wc_opening_hours]['first'] = date( "H", $d );
						$range_arr[$wc_opening_hours]['last']  = "";
						$wc_opening_hours++;
					}
				}

				$option_time = '<option value="">' . __( 'Select Time', 'wc-delivery-date-time' ) . '</option>';
				foreach ( $range_arr as $key => $value ) {
					$disabled 		= "";
					$disabled_label = "";
					if ( $value['disabled'] == "yes" ) {
						$disabled 		= ( $value['disabled'] == "yes" ) ? 'disabled=""' : "";
						$disabled_label = " [" . __( 'Interval Shop', 'wc-delivery-date-time' ) . "] ";
						$option_time .= '<option value="' . $value['value'] . '" ' . $disabled . '>' . $value['value'] . $disabled_label . '</option>';
					} else {
						$date_now   = date( "d/m/Y" );
						$set_date 	= sanitize_text_field( $_POST['set_date'] );
						$separation = "+" . $time_for_goods_separation . " hour";
						$time_separation = date( "H", strtotime( $separation ) );
						if ( $time_for_goods_separation > 0 && $time_separation >= $value['first'] && $date_now == $set_date ) {
							$disabled_label = " [" . __( 'Unavailable', 'wc-delivery-date-time' ) . "] ";
							$option_time .= '<option value="' . $value['value'] . '" disabled="">' . $value['value'] . $disabled_label . '</option>';
						} else {
							$option_time .= '<option value="' . $value['value'] . '">' . $value['value'] . '</option>';
						}	
					}
				}

				echo $option_time;
				exit();
			} else {
				echo 0;
				exit();
			}

		}

		public function wc_admin_page_delivery_date_time_settings(){ 

			 $message = "";
			 if( isset( $_POST['_update'] ) && isset( $_POST['_wpnonce'] ) ) {
				$_update 	= sanitize_text_field( $_POST['_update'] );
				$_wpnonce 	= sanitize_text_field( $_POST['_wpnonce'] );
			 }

			 if( isset( $_wpnonce ) && isset( $_update ) ) {
				if ( ! wp_verify_nonce( $_wpnonce, "wc-delivery-date-time-update-settings" ) ) {
					$message = 'error';
					
				} else if ( empty( $_update ) ) {
					$message = 'error';			
				}
				
				if( isset( $_POST ) ) {
					$post_settings = array();
					$post_settings = (array)$_POST;

					$new_settings['wc_settings_enabled'] = ( isset( $post_settings['wc_settings_enabled'] ) ) ? sanitize_text_field( $post_settings['wc_settings_enabled'] ) : "no";
					$new_settings['wc_opening_hours'] = ( isset( $post_settings['wc_opening_hours'] ) ) ? sanitize_text_field( $post_settings['wc_opening_hours'] ) : "";
					$new_settings['wc_start_interval_hours_shop'] = ( isset( $post_settings['wc_start_interval_hours_shop'] ) ) ? sanitize_text_field( $post_settings['wc_start_interval_hours_shop'] ) : "";
					$new_settings['wc_interval_finish_hours_shop'] = ( isset( $post_settings['wc_interval_finish_hours_shop'] ) ) ? sanitize_text_field( $post_settings['wc_interval_finish_hours_shop'] ) : "";
					$new_settings['wc_closing_hours'] = ( isset( $post_settings['wc_closing_hours'] ) ) ? sanitize_text_field( $post_settings['wc_closing_hours'] ) : "";

					$wc_day_week = array(
						'0' => 'sunday',
						'1' => 'monday', 
						'2' => 'tuesday', 
						'3' => 'wednesday', 
						'4' => 'thursday', 
						'5' => 'friday', 
						'6' => 'saturday'
					);

					foreach ( $wc_day_week as $key => $wc_day ) {
						$new_settings['wc_day_week_' . $wc_day] = ( isset( $post_settings['wc_day_week_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_day_week_' . $wc_day] ) : "no";
						$new_settings['wc_opening_hours_' . $wc_day] = ( isset( $post_settings['wc_opening_hours_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_opening_hours_' . $wc_day] ) : "";
						$new_settings['wc_start_interval_hours_' . $wc_day] = ( isset( $post_settings['wc_start_interval_hours_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_start_interval_hours_' . $wc_day] ) : "";
						$new_settings['wc_interval_finish_hours_' . $wc_day] = ( isset( $post_settings['wc_interval_finish_hours_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_interval_finish_hours_' . $wc_day] ) : "";
						$new_settings['wc_closing_hours_' . $wc_day] = ( isset( $post_settings['wc_closing_hours_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_closing_hours_' . $wc_day] ) : "";
						$new_settings['wc_disable_interval_' . $wc_day] = ( isset( $post_settings['wc_disable_interval_' . $wc_day] ) ) ? sanitize_text_field( $post_settings['wc_disable_interval_' . $wc_day] ) : "";
					}

					$new_settings['wc_shipping_methods'] = ( isset( $post_settings['wc_shipping_methods'] ) ) ? sanitize_text_field( $post_settings['wc_shipping_methods'] ) : "";
					$new_settings['wc_pickup_methods'] = ( isset( $post_settings['wc_pickup_methods'] ) ) ? sanitize_text_field( $post_settings['wc_pickup_methods'] ) : "";
					$new_settings['wc_number_days_available'] = ( isset( $post_settings['wc_number_days_available'] ) ) ? sanitize_text_field( $post_settings['wc_number_days_available'] ) : 5;
					$new_settings['wc_time_interval_delivery'] = ( isset( $post_settings['wc_time_interval_delivery'] ) ) ? sanitize_text_field( $post_settings['wc_time_interval_delivery'] ) : "";
					$new_settings['wc_time_for_goods_separation'] = ( isset( $post_settings['wc_time_for_goods_separation'] ) ) ? sanitize_text_field( $post_settings['wc_time_for_goods_separation'] ) : "";
					$new_settings['wc_holiday_days'] = ( isset( $post_settings['wc_holiday_days'] ) ) ? sanitize_textarea_field( $post_settings['wc_holiday_days'] ) : "";
					
					$delivery_settings = array();
					$delivery_settings = get_option( 'wc_delivery_date_time_settings' );

					if ( $delivery_settings == "" ) {
						update_option( "wc_delivery_date_time_settings", $new_settings );
					} else {
						update_option( "wc_delivery_date_time_settings", array_merge( $delivery_settings, $new_settings ) );
					}

				}
				
				$message = "updated";	
			 }
			
			$new_delivery_settings = array();
			$new_delivery_settings = get_option( 'wc_delivery_date_time_settings' );

			$enabled 						= esc_attr( $new_delivery_settings['wc_settings_enabled'] );
			$wc_opening_hours 				= esc_attr( $new_delivery_settings['wc_opening_hours'] );
			$wc_start_interval_hours_shop 	= esc_attr( $new_delivery_settings['wc_start_interval_hours_shop'] );
			$wc_interval_finish_hours_shop 	= esc_attr( $new_delivery_settings['wc_interval_finish_hours_shop'] );
			$wc_closing_hours 				= esc_attr( $new_delivery_settings['wc_closing_hours'] );

			$wc_day_week_sunday 			= esc_attr( $new_delivery_settings['wc_day_week_sunday'] );
			$wc_opening_hours_sunday 		= esc_attr( $new_delivery_settings['wc_opening_hours_sunday'] );
			$wc_start_interval_hours_sunday = esc_attr( $new_delivery_settings['wc_start_interval_hours_sunday'] );
			$wc_interval_finish_hours_sunday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_sunday'] );
			$wc_closing_hours_sunday 		= esc_attr( $new_delivery_settings['wc_closing_hours_sunday'] );
			$wc_disable_interval_sunday 	= esc_attr( $new_delivery_settings['wc_disable_interval_sunday'] );

			$wc_day_week_monday				= esc_attr( $new_delivery_settings['wc_day_week_monday'] );
			$wc_opening_hours_monday 		= esc_attr( $new_delivery_settings['wc_opening_hours_monday'] );
			$wc_start_interval_hours_monday = esc_attr( $new_delivery_settings['wc_start_interval_hours_monday'] );
			$wc_interval_finish_hours_monday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_monday'] );
			$wc_closing_hours_monday 		= esc_attr( $new_delivery_settings['wc_closing_hours_monday'] );
			$wc_disable_interval_monday 	= esc_attr( $new_delivery_settings['wc_disable_interval_monday'] );

			$wc_day_week_tuesday			= esc_attr( $new_delivery_settings['wc_day_week_tuesday'] );
			$wc_opening_hours_tuesday 		= esc_attr( $new_delivery_settings['wc_opening_hours_tuesday'] );
			$wc_start_interval_hours_tuesday = esc_attr( $new_delivery_settings['wc_start_interval_hours_tuesday'] );
			$wc_interval_finish_hours_tuesday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_tuesday'] );
			$wc_closing_hours_tuesday 		= esc_attr( $new_delivery_settings['wc_closing_hours_tuesday'] );
			$wc_disable_interval_tuesday 	= esc_attr( $new_delivery_settings['wc_disable_interval_tuesday'] );

			$wc_day_week_wednesday			= esc_attr( $new_delivery_settings['wc_day_week_wednesday'] );
			$wc_opening_hours_wednesday 	= esc_attr( $new_delivery_settings['wc_opening_hours_wednesday'] );
			$wc_start_interval_hours_wednesday = esc_attr( $new_delivery_settings['wc_start_interval_hours_wednesday'] );
			$wc_interval_finish_hours_wednesday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_wednesday'] );
			$wc_closing_hours_wednesday 	= esc_attr( $new_delivery_settings['wc_closing_hours_wednesday'] );
			$wc_disable_interval_wednesday 	= esc_attr( $new_delivery_settings['wc_disable_interval_wednesday'] );

			$wc_day_week_thursday			= esc_attr( $new_delivery_settings['wc_day_week_thursday'] );
			$wc_opening_hours_thursday 		= esc_attr( $new_delivery_settings['wc_opening_hours_thursday'] );
			$wc_start_interval_hours_thursday = esc_attr( $new_delivery_settings['wc_start_interval_hours_thursday'] );
			$wc_interval_finish_hours_thursday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_thursday'] );
			$wc_closing_hours_thursday 		= esc_attr( $new_delivery_settings['wc_closing_hours_thursday'] );
			$wc_disable_interval_thursday 	= esc_attr( $new_delivery_settings['wc_disable_interval_thursday'] );

			$wc_day_week_friday				= esc_attr( $new_delivery_settings['wc_day_week_friday'] );
			$wc_opening_hours_friday 		= esc_attr( $new_delivery_settings['wc_opening_hours_friday'] );
			$wc_start_interval_hours_friday = esc_attr( $new_delivery_settings['wc_start_interval_hours_friday'] );
			$wc_interval_finish_hours_friday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_friday'] );
			$wc_closing_hours_friday 		= esc_attr( $new_delivery_settings['wc_closing_hours_friday'] );
			$wc_disable_interval_friday 	= esc_attr( $new_delivery_settings['wc_disable_interval_friday'] );

			$wc_day_week_saturday			= esc_attr( $new_delivery_settings['wc_day_week_saturday'] );
			$wc_opening_hours_saturday 		= esc_attr( $new_delivery_settings['wc_opening_hours_saturday'] );
			$wc_start_interval_hours_saturday = esc_attr( $new_delivery_settings['wc_start_interval_hours_saturday'] );
			$wc_interval_finish_hours_saturday= esc_attr( $new_delivery_settings['wc_interval_finish_hours_saturday'] );
			$wc_closing_hours_saturday 		= esc_attr( $new_delivery_settings['wc_closing_hours_saturday'] );
			$wc_disable_interval_saturday 	= esc_attr( $new_delivery_settings['wc_disable_interval_saturday'] );

			$wc_shipping_methods 			= esc_attr( $new_delivery_settings['wc_shipping_methods'] );
			$wc_pickup_methods 				= esc_attr( $new_delivery_settings['wc_pickup_methods'] );
			$wc_number_days_available 		= esc_attr( $new_delivery_settings['wc_number_days_available'] );
			$wc_time_interval_delivery 		= esc_attr( $new_delivery_settings['wc_time_interval_delivery'] );
			$wc_time_for_goods_separation 	= esc_attr( $new_delivery_settings['wc_time_for_goods_separation'] );
			$wc_holiday_days 				= esc_attr( $new_delivery_settings['wc_holiday_days'] );

			?>
			<div id="wpwrap">			    
				<h1><?php echo __( 'Delivery Date & Time for Woocommerce', 'wc-delivery-date-time' ); ?></h1>
				<p><?php echo __( 'Order delivery, order date and time, order limit, order delivery date for WooCommerce', 'wc-delivery-date-time' ); ?></p>

			    <?php if( isset( $message ) ) { ?>
			        <div class="wrap">
			    	<?php if( $message == "updated" ) { ?>
			            <div id="message" class="updated notice is-dismissible" style="margin-left: 0px;">
			                <p><?php echo __( 'Updates made successfully!', 'wc-delivery-date-time' ); ?></p>
			                <button type="button" class="notice-dismiss">
			                    <span class="screen-reader-text">
			                        <?php echo __( 'Close', 'wc-delivery-date-time' ); ?>
			                    </span>
			                </button>
			            </div>
			            <?php } ?>
			            <?php if( $message == "error" ) { ?>
			            <div id="message" class="updated error is-dismissible" style="margin-left: 0px;">
			                <p><?php echo __( 'Oops! We were unable to make the updates!', 'wc-delivery-date-time' ); ?></p>
			                <button type="button" class="notice-dismiss">
			                    <span class="screen-reader-text">
			                        <?php echo __( 'Close', 'wc-delivery-date-time' ); ?>
			                    </span>
			                </button>
			            </div>
			        <?php } ?>
			    	</div>
			    <?php } ?>

				<hr/>
			    <div class="wrap woocommerce">
			    	<!---->
					<form action="<?php echo esc_url( admin_url( 'admin.php?page=wc_delivery_date_time_settings' ) ); ?>" method="post">
		                <!---->
		                <table class="form-table">
		                    <tbody>
		                        <!---->
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Enable:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
		                                <label>
		                                    <input type="checkbox" name="wc_settings_enabled" value="yes" <?php if( $enabled == "yes" ) { echo 'checked="checked"'; } ?> class="form-control">
		                                    <?php echo __( 'Enable plugin', 'wc-delivery-date-time' ); ?>
		                                </label>
		                           </td>
		                        </tr>
		                        <!---->
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Opening Hours Shop:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_opening_hours" placeholder="Ex: 8" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_opening_hours; ?>">				
		                            </td>
		                        </tr>

		                        <!---->
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Start Interval Shop:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_start_interval_hours_shop" placeholder="Ex: 12" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_start_interval_hours_shop; ?>">				
		                            </td>
		                        </tr>
		                        <!---->
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Finish Interval Shop:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_interval_finish_hours_shop" placeholder="Ex: 14" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_interval_finish_hours_shop; ?>">				
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Closing Hours Shop:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_closing_hours" placeholder="Ex: 17" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_closing_hours; ?>">		
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr>
		                            <th colspan="2" style="padding: 5px;">
		                            	<hr/>
		                            </th>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Select Days Week and Hours:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
		                            	<label style="display: inline-block; min-width: 110px;">
	                            			<input type="checkbox" name="wc_day_week_sunday" value="yes" <?php if( $wc_day_week_sunday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Sunday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_sunday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_sunday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_sunday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_sunday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_sunday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_sunday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_sunday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_sunday; ?>">		                            	
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_sunday" value="yes" <?php if( $wc_disable_interval_sunday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

										<hr/>
										<label style="display: inline-block; min-width: 110px;">
                             			<input type="checkbox" name="wc_day_week_monday" value="yes" <?php if( $wc_day_week_monday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Monday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_monday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_monday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_monday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_monday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_monday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_monday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_monday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_monday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_monday" value="yes" <?php if( $wc_disable_interval_monday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

                            			<hr/>
                            			<label style="display: inline-block; min-width: 110px;">
                            			<input type="checkbox" name="wc_day_week_tuesday" value="yes" <?php if( $wc_day_week_tuesday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Tuesday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_tuesday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_tuesday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_tuesday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_tuesday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_tuesday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_tuesday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_tuesday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_tuesday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_tuesday" value="yes" <?php if( $wc_disable_interval_tuesday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

                            			<hr/>
                            			<label style="display: inline-block; min-width: 110px;">
                            			<input type="checkbox" name="wc_day_week_wednesday" value="yes" <?php if( $wc_day_week_wednesday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Wednesday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_wednesday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_wednesday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_wednesday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_wednesday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_wednesday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_wednesday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_wednesday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_wednesday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_wednesday" value="yes" <?php if( $wc_disable_interval_wednesday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

                            			<hr/>
                            			<label style="display: inline-block; min-width: 110px;">
                            			<input type="checkbox" name="wc_day_week_thursday" value="yes" <?php if( $wc_day_week_thursday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Thursday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_thursday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_thursday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_thursday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_thursday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_thursday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_thursday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_thursday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_thursday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_thursday" value="yes" <?php if( $wc_disable_interval_thursday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

                            			<hr/>
                            			<label style="display: inline-block; min-width: 110px;">
                            			<input type="checkbox" name="wc_day_week_friday" value="yes" <?php if( $wc_day_week_friday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Friday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_friday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_friday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_friday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_friday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_friday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_friday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_friday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_friday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_friday" value="yes" <?php if( $wc_disable_interval_friday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

                            			<hr/>
                            			<label style="display: inline-block; min-width: 110px;">
                            			<input type="checkbox" name="wc_day_week_saturday" value="yes" <?php if( $wc_day_week_saturday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
                            			<?php echo __( 'Saturday', 'wc-delivery-date-time' ); ?>
                            			</label>
										<input type="number" name="wc_opening_hours_saturday" placeholder="<?php echo __( 'Opening', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_opening_hours_saturday; ?>">
										&nbsp;&nbsp;&nbsp;&nbsp;
										<label style="display: inline-block; background-color: #CCC; padding: 0 10px;">
											<input type="number" name="wc_start_interval_hours_saturday" placeholder="<?php echo __( 'Start Interval', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_start_interval_hours_saturday; ?>">
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="number" name="wc_interval_finish_hours_saturday" placeholder="<?php echo __( 'Interval Finish', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_interval_finish_hours_saturday; ?>">
										</label>
										&nbsp;&nbsp;&nbsp;&nbsp;
										<input type="number" name="wc_closing_hours_saturday" placeholder="<?php echo __( 'Closing', 'wc-delivery-date-time' ); ?>" min="0" max="24" class="form-control input-text wc_input_decimal" style="min-width: 100px;" value="<?php echo $wc_closing_hours_saturday; ?>">
		                            	<label style="display: inline-block; padding: 0 10px;">
	                            			<input type="checkbox" name="wc_disable_interval_saturday" value="yes" <?php if( $wc_disable_interval_saturday == "yes" ) { echo 'checked="checked"'; } ?> class="form-control"> 
	                            			<?php echo __( 'Disable Interval Geral', 'wc-delivery-date-time' ); ?>
                            			</label>
										<!---->

		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr>
		                            <th colspan="2" style="padding: 5px;">
		                            	<hr/>
		                            </th>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Shipping Method:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
		                            	<select name="wc_shipping_methods" class="" id="" style="min-width: 200px; max-width: 400px;"  required="">
		                            		<option value="">
		                            			<?php echo __( 'Select Shipping', 'wc-delivery-date-time' ); ?>
		                            		</option>
		                            		<?php 
		                            		foreach ( WC()->shipping()->load_shipping_methods() as $key => $method ) {
		                            			$selected 	= ( $wc_shipping_methods == $method->id ) ? 'selected=""' : "";
		                            			$title 		= empty( $method->method_title ) ? ucfirst( $method->id ) : $method->method_title;
		                            			$shipping_methods[ strtolower( $method->id ) ] = esc_html( $title ); 
		                            		?>
		                            		<option value="<?php echo $method->id; ?>" <?php echo $selected; ?>>
		                            			<?php echo $title; ?>
		                            		</option>
		                            		<?php } ?>
		                            	</select>
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Pickup Method:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
		                            	<select name="wc_pickup_methods" class="" id="" style="min-width: 200px; max-width: 400px;"  required="">
		                            		<option value="">
		                            			<?php echo __( 'Select Shipping', 'wc-delivery-date-time' ); ?>
		                            		</option>
		                            		<?php 
		                            		foreach ( WC()->shipping()->load_shipping_methods() as $key => $method ) {
		                            			$selected 	= ( $wc_pickup_methods == $method->id ) ? 'selected=""' : "";
		                            			$title 		= empty( $method->method_title ) ? ucfirst( $method->id ) : $method->method_title;
		                            			$shipping_methods[ strtolower( $method->id ) ] = esc_html( $title ); 
		                            		?>
		                            		<option value="<?php echo $method->id; ?>" <?php echo $selected; ?>>
		                            			<?php echo $title; ?>
		                            		</option>
		                            		<?php } ?>
		                            	</select>
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr>
		                            <th colspan="2" style="padding: 5px;">
		                            	<hr/>
		                            </th>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Number of days available:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_number_days_available" placeholder="Ex: 5~30" min="5" max="30" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_number_days_available; ?>">
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Time Interval Delivery:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_time_interval_delivery" placeholder="Ex: 2" min="0" max="12" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_time_interval_delivery; ?>">
		                            </td>
		                        </tr>
		                        <!---->		
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Time for Goods Separation:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
										<input type="number" name="wc_time_for_goods_separation" placeholder="Ex: 2" min="0" class="form-control input-text wc_input_decimal" style="min-width: 200px; max-width: 400px;" value="<?php echo $wc_time_for_goods_separation; ?>">
		                            </td>
		                        </tr>
		                        <!---->	
		                        <tr>
		                            <th colspan="2" style="padding: 5px;">
		                            	<hr/>
		                            </th>
		                        </tr>
		                        <!---->		
		                        <tr valign="top">
		                            <th scope="row" style="padding: 10px;">
		                                <label>
		                                    <?php echo __( 'Holiday Days:', 'wc-delivery-date-time' ); ?>
		                                </label>
		                            </th>
		                            <td>
		                            	<textarea name="wc_holiday_days" placeholder="Ex: 23/06/2021,16/12/2021" style="width: 100%; height: 100px;"><?php echo $wc_holiday_days; ?></textarea>
										<br/><i><strong>Obs:</strong> A forma correta é dia/mês/ano e pode mais de uma data separada por virgula.</i>
		                            </td>
		                        </tr>
		                        <!---->			
		                    </tbody>
		                </table>
		                <!---->
		                <hr/>
		                <div class="submit">
		                    <input type="hidden" name="_update" value="1">
		                    <input type="hidden" name="_wpnonce" value="<?php echo sanitize_text_field( wp_create_nonce( 'wc-delivery-date-time-update-settings' ) ); ?>">
		                    <button class="button button-primary" type="submit">
		                    	<?php echo __( 'Salvar Alterações', 'wc-delivery-date-time' ) ; ?>
		                    </button>
		                </div>
		                <!---->
					</form>
				</div>
				<!---->
			</div>
		<?php
		}

	}
	new Delivery_Date_Time_for_WooCommerce();
	//=>
}