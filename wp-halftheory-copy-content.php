<?php
/*
Plugin Name: Half/theory Copy Content
Plugin URI: https://github.com/halftheory/wp-halftheory-copy-content
GitHub Plugin URI: https://github.com/halftheory/wp-halftheory-copy-content
Description: Copy Content
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 2.0
Network: false
*/

/*
Available filters:
copycontent_deactivation(string $db_prefix, class $subclass)
copycontent_uninstall(string $db_prefix, class $subclass)
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Copy_Content_Plugin')) :
final class Copy_Content_Plugin {

	public function __construct() {
		@include_once(dirname(__FILE__).'/class-copy-content.php');
		if (class_exists('Copy_Content')) {
			$this->subclass = new Copy_Content(plugin_basename(__FILE__), '', true);
		}
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
		if ($plugin->subclass) {
			apply_filters('copycontent_deactivation', $plugin->subclass::$prefix, $plugin->subclass);
		}
		return;
	}

	public static function uninstall() {
		$plugin = new self;
		if ($plugin->subclass) {
			$plugin->subclass->delete_transient_uninstall();
			$plugin->subclass->delete_option_uninstall();
			apply_filters('copycontent_uninstall', $plugin->subclass::$prefix, $plugin->subclass);
		}
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