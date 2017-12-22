<?php
/*
Plugin Name: Copy Content
Plugin URI: https://github.com/halftheory
Description: Copy Content
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 1.0
Network: false
*/

/*
Available filters:
copy_content_plugin_deactivation(string $db_prefix)
copy_content_plugin_uninstall(string $db_prefix)
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Copy_Content_Plugin')) :
class Copy_Content_Plugin {

	public function __construct() {
		@include_once(dirname(__FILE__).'/class.copy-content.php');
		$this->subclass = new Copy_Content();
	}

	public static function init() {
		$plugin = new self;
		return $plugin;
	}

	public static function activation() {
		$plugin = new self;
		return $plugin;
	}

	public static function deactivation() {
		$plugin = new self;
		global $wpdb;
		if (is_multisite()) {
			$wpdb->query("DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE '_site_transient_".$plugin->subclass->prefix."%' OR meta_key LIKE '_site_transient_timeout_".$plugin->subclass->prefix."%'");
		}
		else {
			$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_".$plugin->subclass->prefix."%' OR option_name LIKE '_transient_timeout_".$plugin->subclass->prefix."%'");
		}
		apply_filters('copy_content_plugin_deactivation', $plugin->subclass->prefix);
		return;
	}

	public static function uninstall() {
		$plugin = new self;
		if (is_multisite()) {
			delete_site_option($plugin->subclass->prefix);
		}
		else {
			delete_option($plugin->subclass->prefix);
		}
		apply_filters('copy_content_plugin_uninstall', $plugin->subclass->prefix);
		return;
	}

}
// Load the plugin.
add_action('init', array('Copy_Content_Plugin', 'init'));
endif;

register_activation_hook(__FILE__, array('Copy_Content_Plugin', 'activation'));
register_deactivation_hook(__FILE__, array('Copy_Content_Plugin', 'deactivation'));
function Copy_Content_Plugin_uninstall() {
	Copy_Content_Plugin::uninstall();
};
register_uninstall_hook(__FILE__, 'Copy_Content_Plugin_uninstall');
?>