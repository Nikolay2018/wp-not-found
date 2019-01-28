<?php
/**
 * Plugin Name: WP Not Found
 * Plugin URI: https://github.com/Nikolay2018/wp-not-found
 * Description: Plugin to logging Not Found errors to local file on your server.
 * Version: 1.0
 * Author: Mykola Yatsenko
 * Author URI: https://github.com/Nikolay2018
 * License: GPL2
 * Text Domain: wp-not-found
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Logging_Not_Found' ) ) {
	class Logging_Not_Found {
		const opt = 'wp_not_found_options';
		protected $options;

		/**
		 * Available variables for the Error Log format
		 *
		 * @var array
		 */
		protected $format_vars;

		protected $default_options;

		public function __construct() {
			$this->default_options = [
				'log_enabled'  => 0,
				'file_path'    => '',
				'log_format'   => '%datetime% [error]: open() "%path%" failed (2: No such file or directory), client: %client%, server: %server%, request: "%request%", host: "%host%", referrer: "%referrer%"',
				'date_format'  => 'Y/m/d H:i:s',
				'message_type' => 3,
			];

			// load options
			$this->options     = get_option( self::opt, array() );
			$this->format_vars = [
				'%datetime%' => __( 'Date and time of the error', 'wp-not-found' ),
				'%path%'     => __( 'Full path to the link with error', 'wp-not-found' ),
				'%client%'   => __( 'The IP address from which the user is getting the error', 'wp-not-found' ),
				'%server%'   => __( 'The name of the server host with error', 'wp-not-found' ),
				'%request%'  => __( 'Request method, Request URI and the information protocol', 'wp-not-found' ),
				'%host%'     => __( 'Host: header from the current request', 'wp-not-found' ),
				'%referrer%' => __( 'The address of the page (if any) which referred the user agent to the current link', 'wp-not-found' ),
			];

			$update_options = false;

			if ( ! isset( $this->options['general']['log_enabled'] ) ) {
				$this->options['general']['log_enabled'] = $this->default_options['log_enabled'];
				$update_options                          = true;
			}
			if ( ! isset( $this->options['general']['file_path'] ) ) {
				$this->options['general']['file_path'] = $this->default_options['file_path'];
				$update_options                        = true;
			}
			if ( ! isset( $this->options['general']['log_format'] ) ) {
				$this->options['general']['log_format'] = $this->default_options['log_format'];
				$update_options                         = true;
			}
			if ( ! isset( $this->options['general']['date_format'] ) ) {
				$this->options['general']['date_format'] = $this->default_options['date_format'];
				$update_options                          = true;
			}
			if ( ! isset( $this->options['general']['message_type'] ) ) {
				$this->options['general']['message_type'] = $this->default_options['message_type'];
				$update_options                           = true;
			}

			if ( $update_options ) {
				update_option( self::opt, $this->options );
			}

			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'template_redirect', array( $this, 'logging_error_to_file' ) );

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'plugin_settings' ) );
				add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
			}
		}

		public function load_textdomain() {
			load_plugin_textdomain( 'wp-not-found', false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}

		public function plugin_action_links( $links ) {
			$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wp-not-found' ) ) . '">' . __( 'Settings', 'wp-not-found' ) . '</a>';

			return $links;
		}

		/**
		 * Add Plugin admin menu
		 */
		public function plugin_menu() {
			add_menu_page(
				__( 'WP Not Found', 'wp-not-found' ),
				__( 'WP Not Found', 'wp-not-found' ),
				'manage_options',
				'wp-not-found',
				array( $this, 'settings_page' ),
				'dashicons-warning',
				'88.025'
			);

			add_submenu_page(
				'wp-not-found',
				__( 'WP Not Found', 'wp-not-found' ),
				__( 'WP Not Found', 'wp-not-found' ),
				'manage_options',
				'wp-not-found',
				array( $this, 'settings_page' )
			);
		}

		/**
		 * Register Plugin Settings
		 */
		public function plugin_settings() {
			register_setting(
				'wp_not_found_options',
				'wp_not_found_options',
				array( $this, 'sanitize_callback' )
			);

			add_settings_section(
				'wp_not_found_options',
				__( 'General Settings', 'wp-not-found' ),
				'',
				'wp-not-found'
			);

			add_settings_field(
				'log_enabled',
				__( 'Enable Not Found Error Logging', 'wp-not-found' ),
				array( $this, 'log_enabled_field' ),
				'wp-not-found',
				'wp_not_found_options'
			);

			add_settings_field(
				'file_path',
				__( 'Path to Error Log File', 'wp-not-found' ),
				array( $this, 'file_path_field' ),
				'wp-not-found',
				'wp_not_found_options'
			);

			add_settings_field(
				'log_format',
				__( 'Error Log Format', 'wp-not-found' ),
				array( $this, 'error_log_format' ),
				'wp-not-found',
				'wp_not_found_options'
			);
		}

		/**
		 * Callback function for sanitize settings form fields
		 *
		 * @param $options
		 *
		 * @return mixed
		 */
		public function sanitize_callback( $options ) {

			if ( empty( $options['general'] ) ) {
				return $options;
			}

			foreach ( $options['general'] as $name => &$val ) {

				switch ( $name ) {
					case 'log_enabled':
						$val = intval( $val );
						break;

					case 'file_path':
						$val = sanitize_text_field( $val );
						break;

					case 'log_format':
						break;

					case 'date_format':
						break;

					case 'message_type':
						$val = intval( $val );
						break;

					default:
						break;
				}
			}

			return $options;
		}

		/**
		 * Callback function for Enable/Disable Log field
		 */
		public function log_enabled_field() {
			$val = $this->options['general']['log_enabled']; ?>

			<label>
				<input type="checkbox" name="wp_not_found_options[general][log_enabled]" id="log_enabled"
				       value="1" <?php checked( 1, $val ); ?> />
				<?php _e( 'Enable/Disable', 'wp-not-found' ); ?>
			</label>

		<?php }

		/**
		 * Callback function for File Path field
		 */
		public function file_path_field() {
			$val = $this->options['general']['file_path']; ?>

			<input type="text" name="wp_not_found_options[general][file_path]" id="file_path" class="regular-text code"
			       value="<?php echo esc_attr( $val ); ?>" placeholder="<?php _e( 'Path to file', 'wp-not-found' ); ?>"/>
			<br/>
			<span class="description"><?php _e( 'Enter a full path to the file', 'wp-not-found' ); ?></span>

		<?php }

		/**
		 * Callback function for Error Log Format field
		 */
		public function error_log_format() {
			$val = $this->options['general']['log_format']; ?>

			<textarea name="wp_not_found_options[general][log_format]" id="log_format" class="large-text code" rows="3"
			          placeholder="<?php _e( 'Error Log Format', 'wp-not-found' ); ?>"><?php echo esc_attr( $val ); ?></textarea>
			<br/>
			<span class="description">
				<?php _e( 'Enter a format of the Error Log data. List of available variables:', 'wp-not-found' );
				echo '<br />';
				foreach ( $this->format_vars as $name => $description ) {
					echo $name . ' - ' . $description . '<br />';
				} ?>
			</span>

		<?php }

		/**
		 *  admin_menu_page callback function for render plugin Settings page
		 */
		public function settings_page() { ?>
			<div class="wrap">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<form action="<?php echo admin_url( 'options.php' ); ?>" method="POST">
					<?php settings_fields( 'wp_not_found_options' );
					do_settings_sections( 'wp-not-found' );
					submit_button(); ?>
				</form>
			</div>
		<?php }

		protected function get_log_data() {
			$date_format = $this->options['general']['date_format'];

			$log_data = array(
				'%datetime%' => current_time( $date_format ),
				'%path%'     => $_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'],
				'%client%'   => $_SERVER['REMOTE_ADDR'],
				'%server%'   => $_SERVER['SERVER_NAME'],
				'%request%'  => $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . $_SERVER['SERVER_PROTOCOL'],
				'%host%'     => $_SERVER['HTTP_HOST'],
			);

			if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$log_data['%referrer%'] = $_SERVER['HTTP_REFERER'];
			} else {
				$log_data['%referrer%'] = '';
			}
			foreach ( $this->format_vars as $key => $var ) {
				if ( ! array_key_exists( $key, $log_data ) ) {
					$log_data[ $key ] = '';
				}
			}

			return $log_data;
		}

		protected function get_log_message( $log_format ) {
			$log_data = $this->get_log_data();
			$message  = "\n" . str_replace( array_keys( $log_data ), $log_data, $log_format ) . "\n";

			return $message;
		}

		/**
		 * Function for write Error Log to file
		 *
		 * @return bool
		 */
		public function logging_error_to_file() {
			$logging_enabled = $this->options['general']['log_enabled'];
			$log_file        = $this->options['general']['file_path'];

			if ( ! is_404() || empty( $logging_enabled ) || empty( $log_file ) ) {
				return false;
			}

			$date_format  = $this->options['general']['date_format'];
			$log_format   = ( isset( $this->options['general']['log_format'] ) ) ? $this->options['general']['log_format'] : $this->default_options['log_format'];
			$message_type = $this->options['general']['message_type'];

			if ( empty( $log_format ) ) {
				$log_data = array(
					'%path%'    => '"' . $_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'] . '"' . ' failed (2: No such file or directory)',
					'%client%'  => 'client: ' . $_SERVER['REMOTE_ADDR'],
					'%server%'  => 'server: ' . $_SERVER['SERVER_NAME'],
					'%request%' => 'request: ' . '"' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . $_SERVER['SERVER_PROTOCOL'] . '"',
					'%host%'    => 'host: ' . '"' . $_SERVER['HTTP_HOST'] . '"',
				);

				if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
					$log_data['%referrer%'] = 'referrer: ' . '"' . $_SERVER['HTTP_REFERER'] . '"';
				}

				$message = "\n" . current_time( $date_format ) . ' [error]: open() ' . implode( ', ', $log_data ) . "\n";
			} else {
				$message = $this->get_log_message( $log_format );
			}

			$res = error_log( $message, $message_type, $log_file );

			return $res;
		}
	}
}

new Logging_Not_Found();
