<?php

namespace MainWP\Child;

// phpcs:disable
if ( defined( 'MAINWP_CHILD_DEBUG' ) && MAINWP_CHILD_DEBUG === true ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', true );
	ini_set( 'display_startup_errors', true );
} else {
	if ( isset( $_REQUEST['mainwpsignature'] ) ) {
		ini_set( 'display_errors', false );
		error_reporting( 0 );
	}
}
// phpcs:enable


require_once ABSPATH . '/wp-admin/includes/file.php';
require_once ABSPATH . '/wp-admin/includes/plugin.php';

class MainWP_Child {

	public static $version  = '4.0.7.1';
	private $update_version = '1.5';

	public $plugin_slug;
	private $plugin_dir;

	public function __construct( $plugin_file ) {
		$this->update();
		$this->load_all_options();

		$this->plugin_dir  = dirname( $plugin_file );
		$this->plugin_slug = plugin_basename( $plugin_file );

		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action( 'init', array( &$this, 'check_login' ), 1 );
		add_action( 'init', array( &$this, 'parse_init' ), 9999 );		
		add_action( 'init', array( &$this, 'localization' ), 33 );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'pre_current_active_plugins', array( MainWP_Child_Updates::get_instance(), 'detect_premium_themesplugins_updates' ) ); // to support detect premium plugins update.
		add_action( 'core_upgrade_preamble', array( MainWP_Child_Updates::get_instance(), 'detect_premium_themesplugins_updates' ) ); // to support detect premium themes.
		
		MainWP_Pages::get_instance()->init();

		if ( is_admin() ) {
			MainWP_Helper::update_option( 'mainwp_child_plugin_version', self::$version, 'yes' );
		}

		MainWP_Connect::instance()->check_other_auth();

		// init functions.
		MainWP_Clone::get()->init();
		MainWP_Child_Server_Information::init();
		MainWP_Client_Report::instance()->init();
		MainWP_Child_Plugins_Check::instance();
		MainWP_Child_Themes_Check::instance();
		MainWP_Utility::instance()->run_saved_snippets();
		
		if ( ! get_option( 'mainwp_child_pubkey' ) ) {
			MainWP_Child_Branding::instance()->save_branding_options( 'branding_disconnected', 'yes' );
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			if ( isset( $_GET['mainwp_child_run'] ) && ! empty( $_GET['mainwp_child_run'] ) ) {
				add_action( 'init', array( MainWP_Utility::get_class_name(), 'cron_active' ), PHP_INT_MAX );
			}
		}
	}

	public function load_all_options() {
		global $wpdb;

		if ( ! defined( 'WP_INSTALLING' ) || ! is_multisite() ) {
			$alloptions = wp_cache_get( 'alloptions', 'options' );
		} else {
			$alloptions = false;
		}

		if ( ! defined( 'WP_INSTALLING' ) || ! is_multisite() ) {
			$notoptions = wp_cache_get( 'notoptions', 'options' );
		} else {
			$notoptions = false;
		}

		if ( ! isset( $alloptions['mainwp_db_version'] ) ) {
			$suppress = $wpdb->suppress_errors();
			$options  = array(
				'mainwp_child_auth',
				'mainwp_branding_plugin_header',
				'mainwp_child_reports_db',
				'mainwp_child_fix_htaccess',
				'mainwp_child_pluginDir',
				'mainwp_updraftplus_hide_plugin',
				'mainwp_backwpup_ext_enabled',
				'mainwpKeywordLinks',
				'mainwp_child_server',
				'mainwp_kwl_options',
				'mainwp_kwl_keyword_links',
				'mainwp_keyword_links_htaccess_set',
				'mainwp_pagespeed_hide_plugin',
				'mainwp_kwl_enable_statistic',
				'mainwp_child_clone_permalink',
				'mainwp_child_restore_permalink',
				'mainwp_ext_snippets_enabled',
				'mainwp_child_pubkey',
				'mainwp_child_nossl',
				'mainwp_security',
				'mainwp_backupwordpress_ext_enabled',
				'mainwp_branding_button_contact_label',
				'mainwp_branding_extra_settings',
				'mainwp_branding_child_hide',
				'mainwp_branding_ext_enabled',
				'mainwp_pagespeed_ext_enabled',
				'mainwp_linkschecker_ext_enabled',
				'mainwp_child_branding_settings',
				'mainwp_child_plugintheme_days_outdate',
			);
			$query    = "SELECT option_name, option_value FROM $wpdb->options WHERE option_name in (";
			foreach ( $options as $option ) {
				$query .= "'" . $option . "', ";
			}
			$query  = substr( $query, 0, strlen( $query ) - 2 );
			$query .= ")"; // phpcs:ignore

			$alloptions_db = $wpdb->get_results( $query ); // phpcs:ignore -- safe query
			$wpdb->suppress_errors( $suppress );
			if ( ! is_array( $alloptions ) ) {
				$alloptions = array();
			}
			if ( is_array( $alloptions_db ) ) {
				foreach ( (array) $alloptions_db as $o ) {
					$alloptions[ $o->option_name ] = $o->option_value;
					unset( $options[ array_search( $o->option_name, $options ) ] );
				}
				foreach ( $options as $option ) {
					$notoptions[ $option ] = true;
				}
				if ( ! defined( 'WP_INSTALLING' ) || ! is_multisite() ) {
					wp_cache_set( 'alloptions', $alloptions, 'options' );
					wp_cache_set( 'notoptions', $notoptions, 'options' );
				}
			}
		}

		return $alloptions;
	}


	public function update() {
		$update_version = get_option( 'mainwp_child_update_version' );

		if ( $update_version === $this->update_version ) {
			return;
		}

		if ( false === $update_version ) {
			$options = array(
				'mainwp_child_legacy',
				'mainwp_child_auth',
				'mainwp_child_uniqueId',
				'mainwp_child_htaccess_set',
				'mainwp_child_fix_htaccess',
				'mainwp_child_pubkey',
				'mainwp_child_server',
				'mainwp_child_nonce',
				'mainwp_child_nossl',
				'mainwp_child_nossl_key',
				'mainwp_child_remove_wp_version',
				'mainwp_child_remove_rsd',
				'mainwp_child_remove_wlw',
				'mainwp_child_remove_core_updates',
				'mainwp_child_remove_plugin_updates',
				'mainwp_child_remove_theme_updates',
				'mainwp_child_remove_php_reporting',
				'mainwp_child_remove_scripts_version',
				'mainwp_child_remove_styles_version',
				'mainwp_child_remove_readme',
				'mainwp_child_clone_sites',
				'mainwp_child_pluginDir',
				'mainwp_premium_updates',
				'mainwp_child_activated_once',
				'mainwp_maintenance_opt_alert_404',
				'mainwp_maintenance_opt_alert_404_email',
				'mainwp_ext_code_snippets',
				'mainwp_ext_snippets_enabled',
				'mainwp_temp_clone_plugins',
				'mainwp_temp_clone_themes',
				'mainwp_child_click_data',
				'mainwp_child_clone_from_server_last_folder',
				'mainwp_child_clone_permalink',
				'mainwp_child_restore_permalink',
				'mainwp_keyword_links_htaccess_set',
				'mainwp_kwl_options',
				'mainwp_kwl_keyword_links',
				'mainwp_kwl_click_statistic_data',
				'mainwp_kwl_statistic_data_',
				'mainwp_kwl_enable_statistic',
				'mainwpKeywordLinks',
			);
			foreach ( $options as $option ) {
				MainWP_Helper::fix_option( $option );
			}
		} elseif ( ( '1.0' === $update_version ) || ( '1.1' === $update_version ) ) {
			$options = array(
				'mainwp_child_pubkey',
				'mainwp_child_update_version',
				'mainwp_child_auth',
				'mainwp_child_clone_permalink',
				'mainwp_child_restore_permalink',
				'mainwp_ext_snippets_enabled',
				'mainwp_child_fix_htaccess',
				'mainwp_child_pluginDir',
				'mainwp_child_htaccess_set',
				'mainwp_child_nossl',
				'mainwp_updraftplus_ext_enabled',
				'mainwpKeywordLinks',
				'mainwp_keyword_links_htaccess_set',
				'mainwp_pagespeed_ext_enabled',
				'mainwp_linkschecker_ext_enabled',
				'mainwp_maintenance_opt_alert_404',
			);
			foreach ( $options as $option ) {
				MainWP_Helper::fix_option( $option, 'yes' );
			}

			if ( ! is_array( get_option( 'mainwp_security' ) ) ) {
				$securityOptions = array(
					'wp_version'      => 'mainwp_child_remove_wp_version',
					'rsd'             => 'mainwp_child_remove_rsd',
					'wlw'             => 'mainwp_child_remove_wlw',
					'core_updates'    => 'mainwp_child_remove_core_updates',
					'plugin_updates'  => 'mainwp_child_remove_plugin_updates',
					'theme_updates'   => 'mainwp_child_remove_theme_updates',
					'php_reporting'   => 'mainwp_child_remove_php_reporting',
					'scripts_version' => 'mainwp_child_remove_scripts_version',
					'styles_version'  => 'mainwp_child_remove_styles_version',
					'readme'          => 'mainwp_child_remove_readme',
				);

				$security = array();
				foreach ( $securityOptions as $option => $old ) {
					$value               = get_option( $old );
					$security[ $option ] = ( 'T' === $value );
				}
				MainWP_Helper::update_option( 'mainwp_security', $security, 'yes' );
			}
		}

		if ( ! empty( $update_version ) && version_compare( $update_version, '1.4', '<=' ) ) {
			if ( ! is_array( get_option( 'mainwp_child_branding_settings' ) ) ) {
				$brandingOptions = array(
					'hide'                     => 'mainwp_branding_child_hide',
					'extra_settings'           => 'mainwp_branding_extra_settings',
					'preserve_branding'        => 'mainwp_branding_preserve_branding',
					'branding_header'          => 'mainwp_branding_plugin_header',
					'support_email'            => 'mainwp_branding_support_email',
					'support_message'          => 'mainwp_branding_support_message',
					'remove_restore'           => 'mainwp_branding_remove_restore',
					'remove_setting'           => 'mainwp_branding_remove_setting',
					'remove_server_info'       => 'mainwp_branding_remove_server_info',
					'remove_connection_detail' => 'mainwp_branding_remove_connection_detail',
					'remove_wp_tools'          => 'mainwp_branding_remove_wp_tools',
					'remove_wp_setting'        => 'mainwp_branding_remove_wp_setting',
					'remove_permalink'         => 'mainwp_branding_remove_permalink',
					'contact_label'            => 'mainwp_branding_button_contact_label',
					'email_message'            => 'mainwp_branding_send_email_message',
					'message_return_sender'    => 'mainwp_branding_message_return_sender',
					'submit_button_title'      => 'mainwp_branding_submit_button_title',
					'disable_wp_branding'      => 'mainwp_branding_disable_wp_branding',
					'show_support'             => 'mainwp_branding_show_support',
					'disable_change'           => 'mainwp_branding_disable_change',
					'disable_switching_theme'  => 'mainwp_branding_disable_switching_theme',
					'branding_ext_enabled'     => 'mainwp_branding_ext_enabled',
				);

				$convertBranding = array();
				foreach ( $brandingOptions as $option => $old ) {
					$value                      = get_option( $old );
					$convertBranding[ $option ] = $value;
				}
				MainWP_Helper::update_option( 'mainwp_child_branding_settings', $convertBranding );
			}
		}

		MainWP_Helper::update_option( 'mainwp_child_update_version', $this->update_version, 'yes' );
	}

	public function localization() {
		load_plugin_textdomain( 'mainwp-child', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
	}

	public function template_redirect() {
		MainWP_Utility::instance()->maintenance_alert();
	}

	public function parse_init() {

		if ( isset( $_REQUEST['cloneFunc'] ) ) {

			// if not valid result then return.
			$valid_clone = MainWP_Clone_Install::get()->request_clone_funct();
			// not valid clone.
			if ( ! $valid_clone ) {
				return;
			}
		}

		global $wp_rewrite;
		$snPluginDir = basename( $this->plugin_dir );
		if ( isset( $wp_rewrite->non_wp_rules[ 'wp-content/plugins/' . $snPluginDir . '/([^js\/]*)$' ] ) ) {
			unset( $wp_rewrite->non_wp_rules[ 'wp-content/plugins/' . $snPluginDir . '/([^js\/]*)$' ] );
		}

		if ( isset( $wp_rewrite->non_wp_rules[ 'wp-content/plugins/' . $snPluginDir . '/(.*)$' ] ) ) {
			unset( $wp_rewrite->non_wp_rules[ 'wp-content/plugins/' . $snPluginDir . '/(.*)$' ] );
		}

		if ( get_option( 'mainwp_child_fix_htaccess' ) === false ) {
			include_once ABSPATH . '/wp-admin/includes/misc.php';

			$wp_rewrite->flush_rules();
			MainWP_Helper::update_option( 'mainwp_child_fix_htaccess', 'yes', 'yes' );
		}

		// if login required.
		if ( isset( $_REQUEST['login_required'] ) && ( '1' === $_REQUEST['login_required'] ) && isset( $_REQUEST['user'] ) ) {
			$valid_login_required = MainWP_Connect::instance()->parse_login_required();
			// return parse init if login required are not valid.
			if ( ! $valid_login_required ) {
				return;
			}
		}

		/**
		 * Security
		 */
		MainWP_Security::fix_all();
		MainWP_Debug::process( $this );

		// Register does not require auth, so we register here.
		if ( isset( $_POST['function'] ) && 'register' === $_POST['function'] ) {
			define( 'DOING_CRON', true );
			MainWP_Utility::fix_for_custom_themes();
			MainWP_Connect::instance()->register_site(); // register the site and exit.
		}

		// auth here.
		$auth = MainWP_Connect::instance()->auth( isset( $_POST['mainwpsignature'] ) ? $_POST['mainwpsignature'] : '', isset( $_POST['function'] ) ? $_POST['function'] : '', isset( $_POST['nonce'] ) ? $_POST['nonce'] : '', isset( $_POST['nossl'] ) ? $_POST['nossl'] : 0 );
		
		// parse auth, if it is not correct actions then exit with message or return.
		if ( ! MainWP_Connect::instance()->parse_init_auth( $auth ) ) {
			return;
		}
		
		$this->parse_init_extensions();
		
		global $_wp_submenu_nopriv;
		if ( null === $_wp_submenu_nopriv ) {
			$_wp_submenu_nopriv = array(); // phpcs:ignore -- to fix warning.
		}

		// execute callable functions here.
		MainWP_Child_Callable::get_instance()->init_call_functions( $auth );

		MainWP_Keyword_Links::instance()->parse_init_keyword_links();
	}

	public function check_login() {
		MainWP_Connect::instance()->check_login();
	}
	
	public function admin_init() {
		if ( MainWP_Helper::is_admin() && is_admin() ) {
			MainWP_Clone::get()->init_ajax();
		}
	}
	
	private function parse_init_extensions() {
		// Handle fatal errors for those init if needed.
		MainWP_Child_Branding::instance()->branding_init();
		MainWP_Client_Report::instance()->creport_init();
		\MainWP_Child_IThemes_Security::instance()->ithemes_init();
		\MainWP_Child_Updraft_Plus_Backups::instance()->updraftplus_init();
		\MainWP_Child_Back_Up_WordPress::instance()->init();
		\MainWP_Child_WP_Rocket::instance()->init();
		\MainWP_Child_Back_WP_Up::instance()->init();
		\MainWP_Child_Back_Up_Buddy::instance();
		\MainWP_Child_Wordfence::instance()->wordfence_init();
		\MainWP_Child_Timecapsule::instance()->init();
		\MainWP_Child_Staging::instance()->init();
		\MainWP_Child_Pagespeed::instance()->init();
		\MainWP_Child_Links_Checker::instance()->init();
		\MainWP_Child_WPvivid_BackupRestore::instance()->init();
	}


	/*
	 * hook to deactivation child plugin action
	 */
	public function deactivation( $deact = true ) {

		$mu_plugin_enabled = apply_filters( 'mainwp_child_mu_plugin_enabled', false );
		if ( $mu_plugin_enabled ) {
			return;
		}

		$to_delete   = array(
			'mainwp_child_pubkey',
			'mainwp_child_nonce',
			'mainwp_child_nossl',
			'mainwp_child_nossl_key',
			'mainwp_security',
			'mainwp_child_server',
		);
		$to_delete[] = 'mainwp_ext_snippets_enabled';
		$to_delete[] = 'mainwp_ext_code_snippets';

		foreach ( $to_delete as $delete ) {
			if ( get_option( $delete ) ) {
				delete_option( $delete );
				wp_cache_delete( $delete, 'options' );
			}
		}

		if ( $deact ) {
			do_action( 'mainwp_child_deactivation' );
		}
	}

	/*
	 * hook to activation child plugin action
	 */
	public function activation() {
		$mu_plugin_enabled = apply_filters( 'mainwp_child_mu_plugin_enabled', false );
		if ( $mu_plugin_enabled ) {
			return;
		}

		$to_delete = array(
			'mainwp_child_pubkey',
			'mainwp_child_nonce',
			'mainwp_child_nossl',
			'mainwp_child_nossl_key',
		);
		foreach ( $to_delete as $delete ) {
			if ( get_option( $delete ) ) {
				delete_option( $delete );
			}
		}

		MainWP_Helper::update_option( 'mainwp_child_activated_once', true );

		// delete bad data if existed.
		$to_delete = array( 'mainwp_ext_snippets_enabled', 'mainwp_ext_code_snippets' );
		foreach ( $to_delete as $delete ) {
			delete_option( $delete );
		}
	}

}
