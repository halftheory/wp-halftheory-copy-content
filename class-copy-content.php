<?php
/*
Available filters:
copycontent_file_types
copycontent_shortcode
copycontent_get_content
wpcopycontent_get_content
wpcopycontent_query
wpcopycontent_template
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Plugin')) {
	@include_once(dirname(__FILE__).'/class-halftheory-helper-plugin.php');
}

if (!class_exists('Copy_Content') && class_exists('Halftheory_Helper_Plugin')) :
final class Copy_Content extends Halftheory_Helper_Plugin {

	public static $plugin_basename;
	public static $prefix;
	public static $active = false;
	public static $image_sizes = array();
	public $shortcode_copycontent = 'copy-content';
	public $shortcode_wpcopycontent = 'wp-copy-content';
	private $option_prefix = array();
	private $domwrapper = 'domwrapper';
	private $loop_ends = array();

	/* setup */

	public function init($plugin_basename = '', $prefix = '') {
		parent::init($plugin_basename, $prefix);
		self::$active = $this->get_option(static::$prefix, 'active', false);
		$this->option_prefix = array(
			$this->shortcode_copycontent => 'copycontent',
			$this->shortcode_wpcopycontent => 'wpcopycontent',
		);
	}

	protected function setup_actions() {
		parent::setup_actions();

		// stop if not active
		if (empty(self::$active)) {
			return;
		}

		// admin
		if (!$this->is_front_end()) {
			add_action('post_updated', array($this,'post_updated'), 20, 3);
			add_action('admin_notices', array($this,'admin_notices'));
		}

		// shortcodes
		if (!shortcode_exists($this->shortcode_copycontent)) {
			add_shortcode($this->shortcode_copycontent, array($this,'shortcode'));
		}
		if (!shortcode_exists($this->shortcode_wpcopycontent)) {
			add_shortcode($this->shortcode_wpcopycontent, array($this,'shortcode'));
		}
	}

	/* admin */

	public function menu_page() {
 		global $title;
		?>
		<div class="wrap">
			<h2><?php echo $title; ?></h2>
		<?php
 		$plugin = new static(static::$plugin_basename, static::$prefix, false);

		if ($plugin->save_menu_page()) {
        	$save = function() use ($plugin) {
				// get values
				$options_arr = $plugin->get_options_array();
				$options = array();
				foreach ($options_arr as $value) {
					$name = $plugin::$prefix.'_'.$value;
					if (!isset($_POST[$name])) {
						continue;
					}
					if ($this->empty_notzero($_POST[$name])) {
						continue;
					}
					$options[$value] = $_POST[$name];
				}
				// save it
	            $updated = '<div class="updated"><p><strong>'.esc_html__('Options saved.').'</strong></p></div>';
	            $error = '<div class="error"><p><strong>'.esc_html__('Error: There was a problem.').'</strong></p></div>';
				if (!empty($options)) {
		            if ($plugin->update_option($plugin::$prefix, $options)) {
		            	echo $updated;
		            }
		        	else {
		        		// where there changes?
		        		$options_old = $plugin->get_option($plugin::$prefix, null, array());
		        		ksort($options_old);
		        		ksort($options);
		        		if ($options_old !== $options) {
		            		echo $error;
		            	}
		            	else {
			            	echo $updated;
		            	}
		        	}
				}
				else {
		            if ($plugin->delete_option($plugin::$prefix)) {
		            	echo $updated;
		            }
		        	else {
		            	echo $updated;
		        	}
				}
			};
			$save();
        } // save

		// show the form
		$options_arr = $plugin->get_options_array();
		$options = $plugin->get_option($plugin::$prefix, null, array());
		$options = array_merge( array_fill_keys($options_arr, null), $options );
		?>
	    <form id="<?php echo $plugin::$prefix; ?>-admin-form" name="<?php echo $plugin::$prefix; ?>-admin-form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<?php
		// Use nonce for verification
		wp_nonce_field($plugin::$plugin_basename, $plugin->plugin_name.'::'.__FUNCTION__);
		?>
	    <div id="poststuff">

        <p><label for="<?php echo $plugin::$prefix; ?>_active"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_active" name="<?php echo $plugin::$prefix; ?>_active" value="1"<?php checked($options['active'], 1); ?> /> <?php echo $plugin->plugin_title; ?> <?php _e('active?'); ?></label></p>

        <!-- copy-content -->
		<h3>[<?php echo $plugin->shortcode_copycontent; ?>] <?php _e('shortcode'); ?></h3>
        <p><?php _e('For external URLs.'); ?></p>

        <div class="postbox">
        	<div class="inside">
		        <p><label for="<?php echo $plugin::$prefix; ?>_copycontent_update_excerpt"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_copycontent_update_excerpt" name="<?php echo $plugin::$prefix; ?>_copycontent_update_excerpt" value="1"<?php checked($options['copycontent_update_excerpt'], 1); ?> /> <?php _e('Update my post <strong>excerpt</strong> where possible.'); ?></label></p>

		        <p><label for="<?php echo $plugin::$prefix; ?>_copycontent_update_thumbnail"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_copycontent_update_thumbnail" name="<?php echo $plugin::$prefix; ?>_copycontent_update_thumbnail" value="1"<?php checked($options['copycontent_update_thumbnail'], 1); ?> /> <?php _e('Update my post <strong>thumbnail</strong> where possible.'); ?></label></p>

	            <label for="<?php echo $plugin::$prefix; ?>_copycontent_shortcode_defaults">
	            	<h4><?php _e('Shortcode Defaults'); ?></h4>
	            	<?php
	            	$placeholder = '';
	            	$arr = array_filter($plugin->get_shortcode_defaults($plugin->shortcode_copycontent));
	            	foreach ($arr as $key => $value) {
	            		$placeholder .= "$key=$value ";
	            	}
	            	?>
					<input type="text" id="<?php echo $plugin::$prefix; ?>_copycontent_shortcode_defaults" name="<?php echo $plugin::$prefix; ?>_copycontent_shortcode_defaults" value="<?php echo esc_attr($options['copycontent_shortcode_defaults']); ?>" style="min-width: 20em; width: 50%;" placeholder="<?php echo esc_attr($placeholder); ?>" />
				</label>

	            <h4><?php _e('File Handling'); ?></h4>
	            <table style="min-width: 50%;">
	            	<tr>
	            		<th><?php _e('File Type'); ?></th>
	            		<th><?php _e('Behavior'); ?></th>
	            		<th><?php _e('File Exists'); ?></th>
	            	</tr>
	            <?php
	            $options['copycontent_file_handling'] = $plugin->make_array($options['copycontent_file_handling']);
	            foreach ($plugin->get_file_types() as $file_key => $file_type) {
	            	if (!isset($options['copycontent_file_handling'][$file_key])) {
	            		$options['copycontent_file_handling'][$file_key] = '';
	            	}
	            	if (!isset($options['copycontent_file_exists'][$file_key])) {
	            		$options['copycontent_file_exists'][$file_key] = '';
	            	}
	            	sort($file_type['extensions']);
	            	?>
	            	<tr>
	            		<td><label for="<?php echo $plugin::$prefix; ?>_copycontent_file_handling[<?php echo $file_key; ?>]"><span title="<?php echo esc_attr(implode(", ", $file_type['extensions'])); ?>"><?php echo $file_type['label']; ?></span></label></td>

	            		<td><select id="<?php echo $plugin::$prefix; ?>_copycontent_file_handling[<?php echo $file_key; ?>]" name="<?php echo $plugin::$prefix; ?>_copycontent_file_handling[<?php echo $file_key; ?>]">
	            			<?php foreach ($plugin->get_file_handling_options() as $key => $value) : ?>
							<option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options['copycontent_file_handling'][$file_key]); ?>><?php echo esc_html($value); ?></option>
						<?php endforeach; ?>
						</select></td>

	            		<td><select id="<?php echo $plugin::$prefix; ?>_copycontent_file_exists[<?php echo $file_key; ?>]" name="<?php echo $plugin::$prefix; ?>_copycontent_file_exists[<?php echo $file_key; ?>]">
	            			<?php foreach ($plugin->get_file_exists_options() as $key => $value) : ?>
							<option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options['copycontent_file_exists'][$file_key]); ?>><?php echo esc_html($value); ?></option>
						<?php endforeach; ?>
						</select></td>
	            	</tr>
	            	<?php
	            }
	            ?>
	            </table>
        	</div>
        </div>

        <!-- wp-copy-content -->
		<h3>[<?php echo $plugin->shortcode_wpcopycontent; ?>] <?php _e('shortcode'); ?></h3>
        <p><?php _e('For internal posts.'); ?></p>

        <div class="postbox">
        	<div class="inside">
		        <p><label for="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_excerpt"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_excerpt" name="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_excerpt" value="1"<?php checked($options['wpcopycontent_update_excerpt'], 1); ?> /> <?php _e('Update my post <strong>excerpt</strong> where possible.'); ?></label></p>

		        <p><label for="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_thumbnail"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_thumbnail" name="<?php echo $plugin::$prefix; ?>_wpcopycontent_update_thumbnail" value="1"<?php checked($options['wpcopycontent_update_thumbnail'], 1); ?> /> <?php _e('Update my post <strong>thumbnail</strong> where possible.'); ?></label></p>

	            <label for="<?php echo $plugin::$prefix; ?>_wpcopycontent_shortcode_defaults">
	            	<h4><?php _e('Shortcode Defaults'); ?></h4>
	            	<?php
	            	$placeholder = '';
	            	$arr = array_filter($plugin->get_shortcode_defaults($plugin->shortcode_wpcopycontent));
	            	foreach ($arr as $key => $value) {
	            		$placeholder .= "$key=$value ";
	            	}
	            	?>
					<input type="text" id="<?php echo $plugin::$prefix; ?>_wpcopycontent_shortcode_defaults" name="<?php echo $plugin::$prefix; ?>_wpcopycontent_shortcode_defaults" value="<?php echo esc_attr($options['wpcopycontent_shortcode_defaults']); ?>" style="min-width: 20em; width: 50%;" placeholder="<?php echo esc_attr($placeholder); ?>" />
				</label>

	            <h4><?php _e('File Handling'); ?></h4>
	            <table style="min-width: 50%;">
	            	<tr>
	            		<th><?php _e('File Type'); ?></th>
	            		<th><?php _e('Behavior'); ?></th>
	            		<th><?php _e('File Exists'); ?></th>
	            	</tr>
	            <?php
	            $options['wpcopycontent_file_handling'] = $plugin->make_array($options['wpcopycontent_file_handling']);
	            foreach ($plugin->get_file_types() as $file_key => $file_type) {
	            	if (!isset($options['wpcopycontent_file_handling'][$file_key])) {
	            		$options['wpcopycontent_file_handling'][$file_key] = '';
	            	}
	            	if (!isset($options['wpcopycontent_file_exists'][$file_key])) {
	            		$options['wpcopycontent_file_exists'][$file_key] = '';
	            	}
	            	sort($file_type['extensions']);
	            	?>
	            	<tr>
	            		<td><label for="<?php echo $plugin::$prefix; ?>_wpcopycontent_file_handling[<?php echo $file_key; ?>]"><span title="<?php echo esc_attr(implode(", ", $file_type['extensions'])); ?>"><?php echo $file_type['label']; ?></span></label></td>

	            		<td><select id="<?php echo $plugin::$prefix; ?>_wpcopycontent_file_handling[<?php echo $file_key; ?>]" name="<?php echo $plugin::$prefix; ?>_wpcopycontent_file_handling[<?php echo $file_key; ?>]">
	            			<?php foreach ($plugin->get_file_handling_options() as $key => $value) : ?>
							<option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options['wpcopycontent_file_handling'][$file_key]); ?>><?php echo esc_html($value); ?></option>
						<?php endforeach; ?>
						</select></td>

	            		<td><select id="<?php echo $plugin::$prefix; ?>_wpcopycontent_file_exists[<?php echo $file_key; ?>]" name="<?php echo $plugin::$prefix; ?>_wpcopycontent_file_exists[<?php echo $file_key; ?>]">
	            			<?php foreach ($plugin->get_file_exists_options() as $key => $value) : ?>
							<option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options['wpcopycontent_file_exists'][$file_key]); ?>><?php echo esc_html($value); ?></option>
						<?php endforeach; ?>
						</select></td>
	            	</tr>
	            	<?php
	            }
	            ?>
	            </table>
        	</div>
        </div>

        <?php submit_button(__('Update'), array('primary','large'), 'save'); ?>

        </div><!-- poststuff -->
    	</form>

		</div><!-- wrap -->
		<?php
	}

	public function post_updated($post_id, $post_after, $post_before) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
    	// update only on Edit>Post page
		if (isset($_POST)) {
			if (isset($_POST['_wpnonce'])) {
				if (wp_verify_nonce($_POST['_wpnonce'], 'update-post_'.$post_id)) {
					if (trim($post_before->post_content) != trim($post_after->post_content) || strpos($post_after->post_content, 'force-update') !== false || strpos($post_after->post_content, 'force-refresh') !== false) {
						$messages = array();

						if (has_shortcode($post_after->post_content, $this->shortcode_copycontent)) {
							if (preg_match_all("/".get_shortcode_regex(array($this->shortcode_copycontent))."/is", $post_after->post_content, $matches)) {
								if (!empty($matches[0])) {
									foreach ($matches[0] as $key => $match) {
										$atts = $this->get_shortcode_atts($matches[3][$key], $this->shortcode_copycontent);
										if (!isset($atts['force-update']) && !isset($atts['force-refresh'])) {
											$atts['force-update'] = true;
										}
										$content = $matches[5][$key];
										$res = self::copycontent_get_content($atts, $content, $post_id, array('get_shortcode_atts' => false));
										if ($res['update']) {
											$postarr = self::update_post_shortcode($res['post_id'], $res['content'], $res, $this->shortcode_copycontent);
										}
										if (!empty($res['messages'])) {
											$messages = array_merge($messages, $res['messages']);
										}
										if (!empty($res['file_handling'])) {
											$arr = array('content', 'excerpt', 'thumbnail');
											foreach ($arr as $value) {
												if (isset($res['file_handling'][$value])) {
													if (!empty($res['file_handling'][$value]['messages'])) {
														$messages = array_merge($messages, $res['file_handling'][$value]['messages']);
													}
												}
											}
										}
									}
								}
							}
						}
						if (has_shortcode($post_after->post_content, $this->shortcode_wpcopycontent)) {
							if (preg_match_all("/".get_shortcode_regex(array($this->shortcode_wpcopycontent))."/is", $post_after->post_content, $matches)) {
								if (!empty($matches[0])) {
									foreach ($matches[0] as $key => $match) {
										$atts = $this->get_shortcode_atts($matches[3][$key], $this->shortcode_wpcopycontent);
										if (!isset($atts['force-update']) && !isset($atts['force-refresh'])) {
											$atts['force-update'] = true;
										}
										$content = $matches[5][$key];
										$res = self::wpcopycontent_get_content($atts, $content, $post_id, array('get_shortcode_atts' => false));
										if ($res['update']) {
											$postarr = self::update_post_shortcode($res['post_id'], $res['content'], $res, $this->shortcode_wpcopycontent);
										}
										if (!empty($res['messages'])) {
											$messages = array_merge($messages, $res['messages']);
										}
										if (!empty($res['file_handling'])) {
											$arr = array('content', 'excerpt', 'thumbnail');
											foreach ($arr as $value) {
												if (isset($res['file_handling'][$value])) {
													if (!empty($res['file_handling'][$value]['messages'])) {
														$messages = array_merge($messages, $res['file_handling'][$value]['messages']);
													}
												}
											}
										}
									}
								}
							}
						}

						if (!empty($messages)) {
							$this->set_transient(static::$prefix.'_admin_notices', $messages, '1 day');
						}
					}
				}
			}
		}
	}

	public function admin_notices() {
		global $current_screen;
		if (empty($current_screen)) {
			return;
		}
		if ($current_screen->base == 'post' && $current_screen->parent_base == 'edit') {
			$arr = $this->get_transient(static::$prefix.'_admin_notices');
			if (!empty($arr)) {
				$this->delete_transient(static::$prefix.'_admin_notices');
				foreach ($arr as $value) {
					if ($value['class'] != 'error') {
						continue;
					}
					echo '<div class="'.$value['class'].'"><p>'.esc_html__($value['message']).'</p></div>'."\n";
				}
			}
		}
	}

	/* shortcodes */

	public function shortcode($atts = array(), $content = '', $shortcode = '') {
		$content = $this->trim_excess_space(force_balance_tags($content));
		if (!in_the_loop()) {
			return $content;
		}
		if (!is_singular()) {
			return $content;
		}

		// copy-content
		if ($shortcode == $this->shortcode_copycontent) {
			$res = self::copycontent_get_content($atts, $content, get_the_ID());
			// use the 'copycontent_get_content' filter to modify output before updating
			if ($res['update']) {
				$content = apply_filters('the_content', $res['content']);
				$postarr = self::update_post_shortcode($res['post_id'], $res['content'], $res, $shortcode);
			}
		}
		// wp-copy-content
		elseif ($shortcode == $this->shortcode_wpcopycontent) {
			$res = self::wpcopycontent_get_content($atts, $content, get_the_ID());
			// use the 'wpcopycontent_get_content' filter to modify output before updating
			if ($res['update']) {
				$content = apply_filters('the_content', $res['content']);
				$postarr = self::update_post_shortcode($res['post_id'], $res['content'], $res, $shortcode);
				if (!empty($res['content_loop'])) {
					$this->add_loop_end($res['content_loop']);
				}
			}
		}

		return apply_filters('copycontent_shortcode', $content, $shortcode);
	}

	private function add_loop_end($str = '') {
		if (empty($this->loop_ends)) {
			global $wp_filter;
			$i = 20;
			while ($wp_filter['loop_end']->offsetExists($i) === true) {
				$i++;
			}
			add_action('loop_end', array($this,'loop_end'), $i);
		}
		$this->loop_ends[] = $str;
	}

	public function loop_end($wp_query) {
		if (!is_main_query()) {
			return;
		}
		if (!in_the_loop()) {
			return;
		}
		if (!is_singular()) {
			return;
		}
		if (!$wp_query->in_the_loop) {
			return;
		}
		if (empty($this->loop_ends)) {
			return;
		}
		foreach ($this->loop_ends as $value) {
			echo apply_filters('copycontent_shortcode', $value, $this->shortcode_wpcopycontent);
		}
		$this->loop_ends = array();
	}

	/* functions - copy-content */

	public static function copycontent_get_content($atts = array(), $content = '', $post_id = 0, $actions = 'all') {
		$plugin = new static(static::$plugin_basename, static::$prefix, false);

		// $actions provides a way of skipping through code (set false)
		$actions_keys = array(
			'get_shortcode_atts',
			'get_transient',
			'file_get_contents_extended',
			'set_transient',
			'update_excerpt',
			'update_thumbnail',
			'include',
			'exclude',
			'tags',
			'relative_links_absolute',
			'file_handling',
		);
		if (is_array($actions)) {
			$actions = wp_parse_args($actions, array_fill_keys($actions_keys, true));
		}
		else {
			if ($actions === 'all' || $plugin->is_true($actions)) {
				$actions = array_fill_keys($actions_keys, true);
			}
			else {
				$actions = array_fill_keys($actions_keys, false);
			}
		}

		// get_shortcode_atts
		if ($actions['get_shortcode_atts']) {
			$atts = $plugin->get_shortcode_atts($atts, $plugin->shortcode_copycontent);
		}

		$res = array(
			'actions' => $actions,
			'atts' => $atts,
			'post_id' => $post_id,
			'content' => $content,
			'excerpt' => '',
			'thumbnail' => '',
			'file_handling' => array(),
			'messages' => array(),
			'update' => false,
		);

		if (!isset($atts['url'])) {
			$res['messages'][] = $plugin->message_array('error', __('Error: No URL defined.'));
			return apply_filters('copycontent_get_content', $res);
		}
		if (empty($atts['url'])) {
			$res['messages'][] = $plugin->message_array('error', __('Error: URL is empty.'));
			return apply_filters('copycontent_get_content', $res);
		}

		if (!isset($atts['refresh']) && !isset($atts['force-update']) && !isset($atts['force-refresh']) && !$plugin->empty_notzero($res['content'])) {
			$res['messages'][] = $plugin->message_array('updated', __('Success: Refresh not required.'));
			return apply_filters('copycontent_get_content', $res);
		}

		$str = '';
		$force_refresh = isset($atts['force-refresh']);
		$transient_name = $plugin::$prefix.'_'.hash('adler32', $atts['url']);

		// get_transient
		if ($actions['get_transient']) {
			if (!$force_refresh) {
				$str_new = $plugin->get_transient($transient_name);
				if ($str_new !== false) {
					$str = $str_new;
					$res['messages'][] = $plugin->message_array('updated', __('Success: Transient found.'));
				}
				else {
					$force_refresh = true;
					$res['messages'][] = $plugin->message_array('error', __('Alert: Transient not found or expired.'));
				}
			}
		}

        // file_get_contents_extended
        if ($actions['file_get_contents_extended']) {
			if ($force_refresh) {
				$str_new = $plugin->file_get_contents_extended($atts['url']);
				if ($str_new !== false) {
					$str = $str_new;
					$res['messages'][] = $plugin->message_array('updated', __('Success: URL contents was refreshed.'));
					// set_transient
					if ($actions['set_transient']) {
						if (!isset($atts['refresh'])) {
							$atts['refresh'] = 0;
						}
						$plugin->delete_transient($transient_name);
						if ($plugin->set_transient($transient_name, $str, $atts['refresh'])) {
							$res['messages'][] = $plugin->message_array('updated', __('Success: Transient was set.'));
						}
						else {
							$res['messages'][] = $plugin->message_array('error', __('Error: Transient could not be set.'));
						}
					}
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Error: URL contents not found.'));
				}
			}
		}

		// sorry, nothing worked
		if (empty($str)) {
			$res['messages'][] = $plugin->message_array('error', __('Error: Refreshed content was empty.'));
			return apply_filters('copycontent_get_content', $res);
		}
		// no html or files
		if (strpos($str, '<') === false && strpos($str, 'http') === false) {
			$str = $plugin->trim_excess_space($str);
			if ($res['content'] != $str) {
				$res['content'] = $str;
				$res['update'] = true;
				$res['messages'][] = $plugin->message_array('updated', __('Success: Content was updated.'));
			}
			$res['messages'][] = $plugin->message_array('updated', __('Alert: Refreshed content contains no HTML or links.'));
			return apply_filters('copycontent_get_content', $res);
		}

		// update_excerpt
		if ($actions['update_excerpt']) {
			if (isset($atts['update_excerpt'])) {
				$found = false;
				$search = array(
					'<meta name="description" ([^>]+)>' => 'content="([^"]+)"',
					'<meta property="og:description" ([^>]+)>' => 'content="([^"]+)"',
					'<meta name="twitter:title" ([^>]+)>' => 'content="([^"]+)"',
				);
				foreach ($search as $key => $value) {
					preg_match_all("/$key/is", $str, $matches);
					if (!$matches) {
						continue;
					}
					if (empty($matches[1])) {
						continue;
					}
					preg_match_all("/$value/is", $matches[1][0], $matches);
					if (!$matches) {
						continue;
					}
					if (empty($matches[1])) {
						continue;
					}
					$res['excerpt'] = $matches[1][0];
					$found = true;
					break;
				}
				if ($found) {
					$res['messages'][] = $plugin->message_array('updated', __('Success: Excerpt was found.'));
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Alert: Excerpt not found.'));
				}
			}
		}

		// update_thumbnail
		if ($actions['update_thumbnail']) {
			if (isset($atts['update_thumbnail'])) {
				$found = false;
				$search = array(
					'<link rel="image_src" ([^>]+)>' => 'href="([^"]+)"',
					'<meta property="og:image" ([^>]+)>' => 'content="([^"]+)"',
					'<meta name="twitter:image" ([^>]+)>' => 'content="([^"]+)"',
				);
				foreach ($search as $key => $value) {
					preg_match_all("/$key/is", $str, $matches);
					if (!$matches) {
						continue;
					}
					if (empty($matches[1])) {
						continue;
					}
					preg_match_all("/$value/is", $matches[1][0], $matches);
					if (!$matches) {
						continue;
					}
					if (empty($matches[1])) {
						continue;
					}
					$res['thumbnail'] = $matches[1][0];
					$found = true;
					break;
				}
				if ($found) {
					$res['messages'][] = $plugin->message_array('updated', __('Success: Thumbnail was found.'));
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Alert: Thumbnail not found.'));
				}
			}
		}

		$has_dom = true;
		if (!class_exists('DOMXPath')) {
			$has_dom = false;
			$res['messages'][] = $plugin->message_array('error', __('Error: DOMXPath not found. Parsing cannot continue.'));
		}

		// include
		if ($actions['include'] && $has_dom) {
			if (isset($atts['include'])) {
				$dom = $plugin->loadHTML($str);
				$xpath = new DOMXPath($dom);
				$keep = array();
				foreach ($atts['include'] as $value) {
					$xpath_q = $plugin->selector_to_xpath($value);
					$tags = $xpath->query('//'.$xpath_q);
					if ($tags->length == 0) {
						$res['messages'][] = $plugin->message_array('error', __('Alert: Include not found - ').$value);
						continue;
					}
					foreach ($tags as $tag) {
						// node
						if ($tag->tagName) {
							if ($tag->tagName == $plugin->domwrapper) {
								continue;
							}
							$keep[$value] = $tag->ownerDocument->saveXML($tag);
						}
						// comment
						elseif ($tag->nodeName == '#comment' && !empty($tag->nodeValue)) {
							$keep[$value] = $tag->nodeValue;
						}
					}
				}
				$str = implode("\n", $keep);
				$res['messages'][] = $plugin->message_array('updated', __('Success: Included - ').implode(", ", array_keys($keep)));
			}
		}

		// exclude
		if ($actions['exclude'] && $has_dom) {
			if (isset($atts['exclude'])) {
				$dom = $plugin->loadHTML($str);
				$xpath = new DOMXPath($dom);
				$remove = array();
				foreach ($atts['exclude'] as $value) {
					$xpath_q = $plugin->selector_to_xpath($value);
					$tags = $xpath->query('//'.$xpath_q);
					if ($tags->length == 0) {
						$res['messages'][] = $plugin->message_array('error', __('Alert: Exclude not found - ').$value);
						continue;
					}
					foreach ($tags as $tag) {
						// only nodes
						if (!$tag->tagName) {
							continue;
						}
						if ($tag->tagName == $plugin->domwrapper) {
							continue;
						}
						$remove[$value] = $tag;
					}
				}
				if (!empty($remove)) {
					foreach($remove as $value) {
						$value->parentNode->removeChild($value);
					}
					$str = $plugin->saveHTML($dom);
					$res['messages'][] = $plugin->message_array('updated', __('Success: Excluded - ').implode(", ", array_keys($remove)));
				}
			}
		}

		// tags
		if ($actions['tags']) {
			if (isset($atts['tags'])) {
				// remove attributes - use dom
				if ($has_dom) {
					$dom = $plugin->loadHTML($str);
					$xpath = new DOMXPath($dom);
					$msg_remove_attr = array();
					foreach ($atts['tags'] as $key => $value) { // $key is tag here
						if (!is_array($value)) {
							continue;
						}
						$xpath_q = $plugin->selector_to_xpath($key);
						$tags = $xpath->query('//'.$xpath_q);
						if ($tags->length == 0) {
							$res['messages'][] = $plugin->message_array('error', __('Alert: Tag not found - ').$key);
							continue;
						}
						foreach ($tags as $tag) {
							// only nodes
							if (!$tag->tagName) {
								continue;
							}
							if ($tag->tagName == $plugin->domwrapper) {
								continue;
							}
							$remove_attr = array();
							for ($i = 0; $i < $tag->attributes->length; $i++) {
								$my_attr = $tag->attributes->item($i)->name;
								if (!in_array($my_attr, $value)) {
									$remove_attr[] = $my_attr;
								}
							}
							if (!empty($remove_attr)) {
								foreach ($remove_attr as $my_attr) {
									$tag->removeAttribute($my_attr);
								}
								$msg_remove_attr[] = $key;
							}
						}
					}
					if (!empty($msg_remove_attr)) {
						$str = $plugin->saveHTML($dom);
						$res['messages'][] = $plugin->message_array('updated', __('Success: Tags updated - ').implode(", ", array_unique($msg_remove_attr)));
					}
				}
			}
			// remove tags - use strip_tags (safely removes nested tags)
			// also strip tags/comments when there is nothing in tags array
			if (preg_match_all("/<([\w]+)[^>]*>/", $str, $matches, PREG_PATTERN_ORDER)) {
				if (!empty($matches[1])) {
					$msg_remove = array();
					$tags = array_unique($matches[1]);
					if (isset($atts['tags'])) {
						$strip_all = array('script', 'style'); // script/style tags - special case - remove all contents
						foreach ($atts['tags'] as $key => $value) { // $key is tag here
							if ($value === false) {
								$k = array_search($key, $tags);
								if (!$plugin->empty_notzero($k)) {
									unset($tags[$k]);
									$msg_remove[] = $key;
									if (in_array($key, $strip_all)) {
										$str = preg_replace("/<".$key."[^>]*>.*?<\/".$key.">/is", "", $str);
										$str = preg_replace("/<[\/]?".$key."[^>]*>/is", "", $str);
									}
								}
								else {
									$res['messages'][] = $plugin->message_array('error', __('Alert: Tag not found - ').$key);
								}
							}
						}
					}
					$tags = '<'.implode('><', $tags).'>';
					// comments?
					$has_comments = false;
					if (isset($atts['include'])) {
						foreach ($atts['include'] as $value) {
							if (strpos($value, "/comment") !== false) {
								$has_comments = true;
								break;
							}
						}
					}
					if ($has_comments) {
						$str = $plugin->strip_tags_html_comments($str, $tags);
					}
					else {
						$str = strip_tags($str, $tags);
					}
					if (!empty($msg_remove)) {
						$res['messages'][] = $plugin->message_array('updated', __('Success: Tags removed - ').implode(", ", $msg_remove));
					}
				}
			}
		}

		// relative_links_absolute
		if ($actions['relative_links_absolute']) {
			$str = $plugin->relative_links_absolute($str, $atts['url']);
		}

		// file_handling
		if ($actions['file_handling']) {
			// content
			list($str, $res['file_handling']['content']) = $plugin::file_handling($str, $post_id, 'all', $plugin->shortcode_copycontent);
			// excerpt
			if ($actions['update_excerpt']) {
				if (isset($atts['update_excerpt'])) {
					list($res['excerpt'], $res['file_handling']['excerpt']) = $plugin::file_handling($res['excerpt'], $post_id, 'all', $plugin->shortcode_copycontent);
				}
			}
			// thumbnail
			if ($actions['update_thumbnail']) {
				if (isset($atts['update_thumbnail'])) {
					list($res['thumbnail'], $res['file_handling']['thumbnail']) = $plugin::file_handling($res['thumbnail'], $post_id, 'image', $plugin->shortcode_copycontent);
				}
			}
			$res['messages'][] = $plugin->message_array('updated', __('Success: File handling completed.'));
		}

		$str = $plugin->trim_excess_space($str);

		if ($res['content'] != $str) {
			$res['content'] = $str;
			$res['update'] = true;
			$res['messages'][] = $plugin->message_array('updated', __('Success: Content was updated.'));
		}
		return apply_filters('copycontent_get_content', $res);
	}

	/* functions - wp-copy-content */

	public static function wpcopycontent_get_content($atts = array(), $content = '', $post_id = 0, $actions = 'all') {
		$plugin = new static(static::$plugin_basename, static::$prefix, false);

		// $actions provides a way of skipping through code (set false)
		$actions_keys = array(
			'get_shortcode_atts',
			'query',
			'get_transient',
			'set_transient',
			'update_excerpt',
			'update_thumbnail',
			'include',
			'exclude',
			'relative_links_absolute',
			'file_handling',
		);
		if (is_array($actions)) {
			$actions = wp_parse_args($actions, array_fill_keys($actions_keys, true));
		}
		else {
			if ($actions === 'all' || $plugin->is_true($actions)) {
				$actions = array_fill_keys($actions_keys, true);
			}
			else {
				$actions = array_fill_keys($actions_keys, false);
			}
		}

		// get_shortcode_atts
		if ($actions['get_shortcode_atts']) {
			$atts = $plugin->get_shortcode_atts($atts, $plugin->shortcode_wpcopycontent);
		}

		$res = array(
			'actions' => $actions,
			'atts' => $atts,
			'post_id' => $post_id,
			'content' => $content,
			'content_loop' => '',
			'excerpt' => '',
			'thumbnail' => '',
			'file_handling' => array(),
			'messages' => array(),
			'update' => false,
		);

		// does blog exist?
		$switch_blog = false;
		if (is_multisite() && isset($atts['blog_id'])) {
			if (get_current_blog_id() != $atts['blog_id']) {
				$count = get_sites(array('count' => true, 'site__in' => array($atts['blog_id'])));
				if ($count == 0) {
					$res['messages'][] = $plugin->message_array('error', __('Error: Blog not found.'));
					return apply_filters('wpcopycontent_get_content', $res);
				}
				$switch_blog = true;
			}
		}

		// is it a single or loop query?
		// single we can copy into shortcode
		// loop query just add under content

		$str = '';
		$query = null;

		// query
		if ($actions['query']) {
			if (isset($atts['query'])) {
				if ($switch_blog) {
					switch_to_blog($atts['blog_id']);
				}

				// try to not query the current post for this blog (still might show up in the loop)
				if (!$switch_blog && !empty($post_id)) {
					$remove = array('p','page_id','post__in');
					foreach ($remove as $value) {
						if (isset($atts['query'][$value])) {
							if (is_array($atts['query'][$value])) {
								$key = array_search($post_id, $atts['query'][$value]);
								if ($key !== false) {
									$atts['query'][$value][$key] = 0;
								}
							}
							elseif ($atts['query'][$value] == $post_id) {
								unset($atts['query'][$value]);
							}
						}
					}
					if (!isset($atts['query']['post__not_in'])) {
						$atts['query']['post__not_in'] = array();
					}
					$atts['query']['post__not_in'] = array_merge($plugin->make_array($atts['query']['post__not_in']), array($post_id));
				}

				// filter the query and copy it back to main result
				$atts['query'] = apply_filters('wpcopycontent_query', $atts['query'], $res);
				$res['atts']['query'] = $atts['query'];

				$query = new WP_Query($atts['query']);
				if ($query->have_posts()) {
					$query->in_the_loop = false; // extra security in avoiding our loop_end action
					// single
					if ($query->is_singular()) {
						$query->the_post();
						global $post;
						if ($post->ID != $post_id) {
							$str = $post->post_content;
						}
						else {
							$query = null;
							wp_reset_postdata();
						}
					}
					// loop
					else {
						$posts = query_posts($atts['query']);
						if (!empty($posts)) {
							ob_start();
							while (have_posts()) { // Start the loop.
								the_post();
								global $post;
								if ($post->ID != $post_id) {
									if ($template = $plugin::get_template()) {
										$template = apply_filters('wpcopycontent_template', $template, $post, $atts['query']);
										load_template($template, false);
									}
								}
							} // End the loop.
							$str = ob_get_clean();
						}
						wp_reset_query();
					}
				}
				else {
					$query = null;
					wp_reset_postdata();
				}
				if (is_object($query)) {
					$res['messages'][] = $plugin->message_array('updated', __('Success: Query found posts.'));
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Error: Query found no posts.'));
				}

			    if ($switch_blog) {
					restore_current_blog();
			    }
			}
		}

		// sorry, nothing worked
		if (empty($str)) {
			if (is_object($query)) {
				$query = null;
				wp_reset_postdata();
			}
			$res['messages'][] = $plugin->message_array('error', __('Error: Refreshed content was empty.'));
			return apply_filters('wpcopycontent_get_content', $res);
		}

		// transient is only used to check 'refresh' for singles
		if (isset($atts['refresh']) && !empty($post_id) && is_object($query)) {
			if ($query->is_singular()) {
				$force_refresh = isset($atts['force-refresh']);
				$transient_name = $plugin::$prefix.'_'.hash('adler32', $post->guid);
				// get_transient
				if ($actions['get_transient']) {
					$transient = $plugin->get_transient($transient_name);
					if ($transient !== false) {
						$res['messages'][] = $plugin->message_array('updated', __('Success: Transient found.'));
						// maybe return here?
						if ($transient == $post->post_content && !isset($atts['force-update']) && !isset($atts['force-refresh']) && !$plugin->empty_notzero($res['content'])) {
							if (is_object($query)) {
								$query = null;
								wp_reset_postdata();
							}
							$res['messages'][] = $plugin->message_array('updated', __('Success: Refresh not required.'));
							return apply_filters('wpcopycontent_get_content', $res);
						}
					}
					else {
						$force_refresh = true;
						$res['messages'][] = $plugin->message_array('error', __('Alert: Transient not found or expired.'));
					}
				}
				if ($force_refresh) {
					// set_transient
					if ($actions['set_transient']) {
						$plugin->delete_transient($transient_name);
						if ($plugin->set_transient($transient_name, $post->post_content, $atts['refresh'])) {
							$res['messages'][] = $plugin->message_array('updated', __('Success: Transient was set.'));
						}
						else {
							$res['messages'][] = $plugin->message_array('error', __('Error: Transient could not be set.'));
						}
					}
				}
			}
		}

		// update_excerpt
		if ($actions['update_excerpt'] && is_object($query)) {
			if (isset($atts['update_excerpt']) && $query->is_singular()) {
				if (!empty($post->post_excerpt)) {
					$res['excerpt'] = $post->post_excerpt;
					$res['messages'][] = $plugin->message_array('updated', __('Success: Excerpt was found.'));
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Alert: Excerpt was empty.'));
				}
			}
		}

		// update_thumbnail
		if ($actions['update_thumbnail'] && is_object($query)) {
			if (isset($atts['update_thumbnail']) && $query->is_singular()) {
				if ($switch_blog) {
					switch_to_blog($atts['blog_id']);
				}

				// if same blog, we only need filename, as file_handling will return the id
				// files from different blogs must be copied into this media library
				$thumbnail = false;
				if ($id = get_post_thumbnail_id($post)) {
					$thumbnail = wp_get_attachment_url($id);
				}
				if ($thumbnail) {
					$res['thumbnail'] = $thumbnail;
					$res['messages'][] = $plugin->message_array('updated', __('Success: Thumbnail was found.'));
				}
				else {
					$res['messages'][] = $plugin->message_array('error', __('Alert: Thumbnail was empty.'));
				}

			    if ($switch_blog) {
					restore_current_blog();
			    }
			}
		}

		// no html or files
		if (strpos($str, '<') === false && strpos($str, 'http') === false) {
			$str = $plugin->trim_excess_space($str);
			if (is_object($query)) {
				if ($query->is_singular() && $res['content'] != $str) {
					$res['content'] = $str;
					$res['update'] = true;
				}
				elseif (!$query->is_singular()) {
					$res['content_loop'] = $str;
					$res['update'] = true;
				}
				$query = null;
				wp_reset_postdata();
				if ($res['update']) {
					$res['messages'][] = $plugin->message_array('updated', __('Success: Content was updated.'));
				}
			}
			$res['messages'][] = $plugin->message_array('updated', __('Alert: Refreshed content contains no HTML or links.'));
			return apply_filters('wpcopycontent_get_content', $res);
		}

		$has_dom = true;
		if (!class_exists('DOMXPath')) {
			$has_dom = false;
			$res['messages'][] = $plugin->message_array('error', __('Error: DOMXPath not found. Parsing cannot continue.'));
		}

		// include
		if ($actions['include'] && $has_dom) {
			if (isset($atts['include'])) {
				$dom = $plugin->loadHTML($str);
				$xpath = new DOMXPath($dom);
				$keep = array();
				foreach ($atts['include'] as $value) {
					$xpath_q = $plugin->selector_to_xpath($value);
					$tags = $xpath->query('//'.$xpath_q);
					if ($tags->length == 0) {
						$res['messages'][] = $plugin->message_array('error', __('Alert: Include not found - ').$value);
						continue;
					}
					foreach ($tags as $tag) {
						// node
						if ($tag->tagName) {
							if ($tag->tagName == $plugin->domwrapper) {
								continue;
							}
							$keep[$value] = $tag->ownerDocument->saveXML($tag);
						}
						// comment
						elseif ($tag->nodeName == '#comment' && !empty($tag->nodeValue)) {
							$keep[$value] = $tag->nodeValue;
						}
					}
				}
				$str = implode("\n", $keep);
				$res['messages'][] = $plugin->message_array('updated', __('Success: Included - ').implode(", ", array_keys($keep)));
			}
		}

		// exclude
		if ($actions['exclude'] && $has_dom) {
			if (isset($atts['exclude'])) {
				$dom = $plugin->loadHTML($str);
				$xpath = new DOMXPath($dom);
				$remove = array();
				foreach ($atts['exclude'] as $value) {
					$xpath_q = $plugin->selector_to_xpath($value);
					$tags = $xpath->query('//'.$xpath_q);
					if ($tags->length == 0) {
						$res['messages'][] = $plugin->message_array('error', __('Alert: Exclude not found - ').$value);
						continue;
					}
					foreach ($tags as $tag) {
						// only nodes
						if (!$tag->tagName) {
							continue;
						}
						if ($tag->tagName == $plugin->domwrapper) {
							continue;
						}
						$remove[$value] = $tag;
					}
				}
				if (!empty($remove)) {
					foreach($remove as $value) {
						$value->parentNode->removeChild($value);
					}
					$str = $plugin->saveHTML($dom);
					$res['messages'][] = $plugin->message_array('updated', __('Success: Excluded - ').implode(", ", array_keys($remove)));
				}
			}
		}

		// relative_links_absolute
		if ($actions['relative_links_absolute']) {
			$url = home_url('/');
			if (is_multisite() && isset($atts['blog_id'])) {
				if (get_current_blog_id() != $atts['blog_id']) {
					$url = get_blog_option($atts['blog_id'], 'siteurl', '');
				}
			}
			$str = $plugin->relative_links_absolute($str, $url);
		}

		// file_handling
		if ($actions['file_handling']) {
			// content
			list($str, $res['file_handling']['content']) = $plugin::file_handling($str, $post_id, 'all', $plugin->shortcode_wpcopycontent);
			// excerpt
			if ($actions['update_excerpt']) {
				if (isset($atts['update_excerpt'])) {
					list($res['excerpt'], $res['file_handling']['excerpt']) = $plugin::file_handling($res['excerpt'], $post_id, 'all', $plugin->shortcode_wpcopycontent);
				}
			}
			// thumbnail
			if ($actions['update_thumbnail']) {
				if (isset($atts['update_thumbnail'])) {
					list($res['thumbnail'], $res['file_handling']['thumbnail']) = $plugin::file_handling($res['thumbnail'], $post_id, 'image', $plugin->shortcode_wpcopycontent, false);
				}
			}
			$res['messages'][] = $plugin->message_array('updated', __('Success: File handling completed.'));
		}

		$str = $plugin->trim_excess_space($str);

		if (is_object($query)) {
			if ($query->is_singular() && $res['content'] != $str) {
				$res['content'] = $str;
				$res['update'] = true;
			}
			elseif (!$query->is_singular()) {
				$res['content_loop'] = $str;
				$res['update'] = true;
			}
			$query = null;
			wp_reset_postdata();
			if ($res['update']) {
				$res['messages'][] = $plugin->message_array('updated', __('Success: Content was updated.'));
			}
		}
		return apply_filters('wpcopycontent_get_content', $res);
	}

	/* functions - file handling */

	public static function file_handling($str = '', $post_id = 0, $file_types_user = 'all', $shortcode = 'copy-content', $ignore_my_host = true) {
		$plugin = new static(static::$plugin_basename, static::$prefix, false);

		$res = array(
			'post_id' => $post_id,
			'shortcode' => $shortcode,
			'file_types' => array(),
			'external_files' => array(),
			'replacements' => array(),
			'attachment_ids' => array(),
			'messages' => array(),
		);

		if (empty($str)) {
			$res['messages'][] = $plugin->message_array('error', __('Error: Content is empty.'));
			return array($str, $res);
		}
		if (strpos($str, 'http') === false) {
			$res['messages'][] = $plugin->message_array('updated', __('Alert: No links found.'));
			return array($str, $res);
		}
		if (empty($file_types_user)) {
			$res['messages'][] = $plugin->message_array('error', __('Error: No file types defined.'));
			return array($str, $res);
		}

		// find which file types to check
		$file_types = array();
		$file_types_system = $plugin->get_file_types();
		if (is_array($file_types_user)) {
			foreach ($file_types_user as $value) {
				if (isset($file_types_system[$value])) {
					$file_types[$value] = $file_types_system[$value];
				}
			}
		}
		else {
			if ($file_types_user === 'all' || $plugin->is_true($file_types_user)) {
				$file_types = $file_types_system;
			}
			else {
				$file_types_user = $plugin->make_array($file_types_user);
				foreach ($file_types_user as $value) {
					if (isset($file_types_system[$value])) {
						$file_types[$value] = $file_types_system[$value];
					}
				}
			}
		}
		if (empty($file_types)) {
			$res['messages'][] = $plugin->message_array('updated', __('Alert: No file types found.'));
			return array($str, $res);
		}
		$res['file_types'] = $file_types;

		// put all external files in an array
		$external_files = array();
		$my_host = parse_url(home_url(), PHP_URL_HOST);
		foreach ($file_types as $key => $value) {
			if (preg_match_all("/(http[s]?:\/\/[a-z0-9\-\.]+\/[\w\-\.\/\!\$\*\+\:\(\)\=~@&',;% ]+\.(".implode("|", $value['extensions'])."))([^a-z0-9]|$)/is", $str, $matches)) {
				if (!empty($matches[1])) {
					foreach ($matches[1] as $match) {
						if ($my_host == parse_url($match, PHP_URL_HOST) && $ignore_my_host) {
							continue;
						}
						if (!isset($external_files[$key])) {
							$external_files[$key] = array();
						}
						$external_files[$key][] = $match;
					}
				}
			}
			if (isset($external_files[$key])) {
				$external_files[$key] = array_unique($external_files[$key]);
			}
		}
		if (empty($external_files)) {
			$res['messages'][] = $plugin->message_array('updated', __('Alert: No external files found.'));
			return array($str, $res);
		}
		$res['external_files'] = $external_files;

		// apply handling rules for each file type, collect 'replacements'
		$replacements = array();
		$file_handling_arr = $plugin->make_array($plugin->get_option($plugin::$prefix, $plugin->option_prefix[$shortcode].'_file_handling', array()));
		$file_exists_arr = $plugin->make_array($plugin->get_option($plugin::$prefix, $plugin->option_prefix[$shortcode].'_file_exists', array()));
		$post_data = array(
			'post_author' => get_current_user_id(),
			'post_content' =>__('Created by plugin: ').$plugin->plugin_title,
		);
		foreach ($external_files as $key => $urls) {
			$file_handling = 'keep';
			if (isset($file_handling_arr[$key])) {
				$file_handling = $file_handling_arr[$key];
			}
			if ($file_handling == 'keep') {
				continue;
			}
			elseif ($file_handling == 'remove') {
				foreach ($urls as $url) {
					$replacements[$url] = "";
				}
			}
			elseif ($file_handling == 'download') {
				$file_exists = 'discard';
				if (isset($file_exists_arr[$key])) {
					$file_exists = $file_exists_arr[$key];
				}
				foreach ($urls as $url) {
					// already uploaded?
					$exists = false;
					$posts = array();
					$args = array(
						'post_type' => 'attachment',
						'post_status' => 'any',
						'posts_per_page' => -1,
						'no_found_rows' => true,
						'nopaging' => true,
						'ignore_sticky_posts' => true,
						'orderby' => 'modified',
						'suppress_filters' => false,
						'meta_query' => array(
							array(
								'key' => '_wp_attached_file',
								'compare' => 'LIKE',
								'value' => basename($url),
							)
						)
					);
					if (!empty($post_id)) {
						// 1. check media attached to this post
						$posts_tmp = get_posts(array_merge($args, array('post_parent' => (int)$post_id)));
						if (!empty($posts_tmp) && !is_wp_error($posts_tmp)) {
							$posts = $posts_tmp;
						}
						// 2. search all other media
						$posts_tmp = get_posts(array_merge($args, array('post_parent__not_in' => array((int)$post_id))));
						if (!empty($posts_tmp) && !is_wp_error($posts_tmp)) {
							$posts = array_merge($posts, $posts_tmp);
						}
					}
					else {
						$posts = get_posts($args);
					}
					if (!empty($posts) && !is_wp_error($posts)) {
						foreach ($posts as $post) {
							if ($arr = wp_get_attachment_metadata($post->ID)) {
								if ($arr['file'] == basename($url) || strpos($arr['file'], '/'.basename($url)) !== false) {
									$exists = $post;
									break;
								}
							}
						}
					}
					// new upload
					if (!$exists) {
						if (!$plugin->download_functions_loaded()) {
							continue;
						}
						$tmp = download_url($url);
						if (is_wp_error($tmp)) {
							@unlink($tmp);
							continue;
						}
						$file_array = array(
							'name' => basename($url),
							'tmp_name' => $tmp
						);
						$id = media_handle_sideload($file_array, $post_id, basename($url), $post_data);
						if (is_wp_error($id)) {
							@unlink($tmp);
							continue;
						}
						$url_new = wp_get_attachment_url($id);
						$replacements[$url] = $url_new;
						$res['attachment_ids'][$url_new] = $id;
					}
					// file exists
					else {
						$url_discard = wp_get_attachment_url($exists->ID);
						$res['attachment_ids'][$url_discard] = $exists->ID;
						// discard
						if ($file_exists == 'discard') {
							$replacements[$url] = $url_discard;
						}
						// replace
						elseif ($file_exists == 'replace') {
							if (!$plugin->download_functions_loaded()) {
								$replacements[$url] = $url_discard;
								continue;
							}
							// download new file
							$tmp = download_url($url);
							if (is_wp_error($tmp)) {
								@unlink($tmp);
								$replacements[$url] = $url_discard;
								continue;
							}
							// delete old files
							$meta = wp_get_attachment_metadata($exists->ID);
							$backup_sizes = $plugin->make_array(get_post_meta($exists->ID, '_wp_attachment_backup_sizes', true));
							$file = str_replace('//', '/', get_attached_file($exists->ID));
							$delete = wp_delete_attachment_files($exists->ID, $meta, $backup_sizes, $file);
							if (!$delete) {
								@unlink($tmp);
								$replacements[$url] = $url_discard;
								continue;
							}
							// move new file to old location
							$move = rename($tmp, $file);
							if (!$move) {
								@unlink($tmp);
								$replacements[$url] = $url_discard;
								continue;
							}
							// thumbs
							if (wp_attachment_is_image($exists->ID)) {
								foreach ($plugin->get_image_sizes() as $size => $value) {
									image_make_intermediate_size($file, $value['width'], $value['height'], $value['crop']);
								}
							}
							// update db
							wp_update_attachment_metadata($exists->ID, wp_generate_attachment_metadata($exists->ID, $file));
							$post_date = current_time('mysql');
							$postarr = array(
								'ID' => $exists->ID,
								'post_modified' => $post_date,
								'post_modified_gmt' => get_gmt_from_date($post_date),
							);
							$filetype = wp_check_filetype($file);
							if ($filetype['type']) {
								$postarr['post_mime_type'] = $filetype['type'];
							}
							$id = wp_update_post(array_merge($post_data, $postarr), true);
							if (empty($id) || is_wp_error($id)) {
								$replacements[$url] = $url_discard;
								continue;
							}
							$url_new = wp_get_attachment_url($id);
							$replacements[$url] = $url_new;
							$res['attachment_ids'][$url_new] = $id;
						}
						elseif ($file_exists == 'new') {
							if (!$plugin->download_functions_loaded()) {
								$replacements[$url] = $url_discard;
								continue;
							}
							$tmp = download_url($url);
							if (is_wp_error($tmp)) {
								@unlink($tmp);
								$replacements[$url] = $url_discard;
								continue;
							}
							$file_array = array(
								'name' => basename($url),
								'tmp_name' => $tmp
							);
							$id = media_handle_sideload($file_array, $post_id, basename($url), $post_data);
							if (is_wp_error($id)) {
								@unlink($tmp);
								$replacements[$url] = $url_discard;
								continue;
							}
							$url_new = wp_get_attachment_url($id);
							$replacements[$url] = $url_new;
							$res['attachment_ids'][$url_new] = $id;
						}
					}
				}//foreach
			}//download
		}
		if (empty($replacements)) {
			$res['messages'][] = $plugin->message_array('updated', __('Alert: No replacements made.'));
			return array($str, $res);
		}
		$str = str_replace(array_keys($replacements), $replacements, $str);
		$res['replacements'] = $replacements;

		return array($str, $res);
	}

	private function download_functions_loaded() {
		if (!function_exists('download_url')) {
			@include_once(ABSPATH.'wp-admin/includes/file.php');
		}
		if (!function_exists('media_handle_sideload')) {
			@include_once(ABSPATH.'wp-admin/includes/media.php');
		}
		if (!function_exists('wp_read_image_metadata')) {
			@include_once(ABSPATH.'wp-admin/includes/image.php');
		}
		if (function_exists('download_url') && function_exists('media_handle_sideload') && function_exists('wp_read_image_metadata')) {
			return true;
		}
		return false;
	}

	private function get_image_sizes() {
		if (empty(self::$image_sizes)) {
			$sizes = array();
			$wp_additional_image_sizes = wp_get_additional_image_sizes();
			$get_intermediate_image_sizes = get_intermediate_image_sizes();
			foreach($get_intermediate_image_sizes as $size) {
				if (isset($wp_additional_image_sizes[$size])) {
					$sizes[$size] = array(
						'width' => (int)$wp_additional_image_sizes[$size]['width'],
						'height' => (int)$wp_additional_image_sizes[$size]['height'],
						'crop' =>  (bool)$wp_additional_image_sizes[$size]['crop']
					);
				}
				else {
					$sizes[$size]['width'] = (int)get_option($size.'_size_w');
					$sizes[$size]['height'] = (int)get_option($size.'_size_h');
					$sizes[$size]['crop'] = (bool)get_option($size.'_crop');
				}
			}
			self::$image_sizes = $sizes;
		}
		return self::$image_sizes;
	}

	/* functions - update post */

	public static function update_post_shortcode($post_id, $content = '', $res = array(), $shortcode = 'copy-content', $update_post = true) {
		$post = get_post($post_id);
		if (empty($post)) {
			return false;
		}
		if (!has_shortcode($post->post_content, $shortcode)) {
			return false;
		}

		$post_date = current_time('mysql');
		$postarr = array(
			'ID' => $post->ID,
			'post_modified' => $post_date,
			'post_modified_gmt' => get_gmt_from_date($post_date),
		);

		// content
		/*
		score system for potential multiple shortcodes:
		1. shortcode has same url
		2. shortcode has high number of the same tags
		3. or update first shortcode
		*/

		$shortcode_regex = get_shortcode_regex(array($shortcode));

		$func_replace = function($shortcode_full = '') use ($post, $content, $shortcode, $shortcode_regex) {
			if (strpos($shortcode_full, $shortcode) === false) {
				return false;
			}
			if (strpos($post->post_content, $shortcode_full) === false) {
				return false;
			}
			$content_old = preg_replace("/".$shortcode_regex."/is", "$5", $shortcode_full, 1);
			// nothing to update
			if (empty($content) && empty($content_old)) {
				return false;
			}
			// no closing tag
			elseif (empty($content)) {
				$replacement = preg_replace("/".$shortcode_regex."/is", "[$1$2$3$4$6]", $shortcode_full, 1);
			}
			// with closing tag
			else {
				$replacement = preg_replace("/".$shortcode_regex."/is", "[$1$2$3]".$content."[/$2$6]", $shortcode_full, 1);
			}
			return preg_replace("/".preg_quote($shortcode_full, '/')."/s", $replacement, $post->post_content, 1);
		};

		$func = function() use ($post, $res, $shortcode_regex, $func_replace) {
			if (preg_match_all("/".$shortcode_regex."/is", $post->post_content, $matches)) {
				if (empty($matches[0])) {
					return false;
				}

				// check for one $matches[0]
				if (count($matches[0]) == 1) {
					return $func_replace(current($matches[0]));
				}

				$url = false;
				if (isset($res['atts'])) {
					if (isset($res['atts']['url'])) {
						$url = untrailingslashit($res['atts']['url']);
					}
				}
				if ($url && strpos($post->post_content, $url) !== false) {
					$found = array();
					foreach ($matches[0] as $key => $value) {
						if (strpos($matches[3][$key], $url) !== false) {
							$found[$key] = $value;
						}
					}
					if (!empty($found)) {
						// check for one $found
						if (count($found) == 1) {
							return $func_replace(current($found));
						}
						$matches[0] = $found;
					}
				}

				$tags = false;
				if (isset($res['atts'])) {
					$tags = array_keys($res['atts']);
					if (isset($res['atts']['tags'])) {
						$tags = array_merge($tags, array_keys($res['atts']['tags']));
						$key = array_search('tags', $tags);
						if ($key !== false) {
							unset($tags[$key]);
						}
					}
					elseif (isset($res['atts']['query_user'])) {
						$tags = array_merge($tags, array_keys($res['atts']['query_user']));
						$key = array_search('query_user', $tags);
						if ($key !== false) {
							unset($tags[$key]);
						}
						$key = array_search('query', $tags);
						if ($key !== false) {
							unset($tags[$key]);
						}
					}
					sort($tags);
				}
				if (!empty($tags)) {
					$scores = array();
					foreach ($matches[0] as $key => $value) {
						if (empty(trim($matches[3][$key]))) {
							continue;
						}
						if (preg_match_all("/(".implode("|", $tags).")=/is", $matches[3][$key], $tag_matches)) {
							if (!empty($tag_matches[0])) {
								$scores[$key] = count($tag_matches[0]);
							}
						}
					}
					if (!empty($scores)) {
						// check for one $scores
						if (count($scores) == 1) {
							$key = key($scores);
							return $func_replace($matches[0][$key]);
						}
						// rearrange $matches[0] by score
						arsort($scores);
						$found = array();
						foreach ($scores as $key => $value) {
							$found[$key] = $matches[0][$key];
						}
						$matches[0] = $found;
					}
				}

				return $func_replace(current($matches[0]));
			}
			return false;
		};

		if ($replaced = $func()) {
			$postarr['post_content'] = $replaced;
		}

		// excerpt
		$excerpt = false;
		if (isset($res['atts']) && isset($res['excerpt'])) {
			if (isset($res['atts']['update_excerpt'])) {
				$excerpt = $res['excerpt'];
			}
		}
		elseif (isset($res['excerpt'])) {
			$excerpt = $res['excerpt'];
		}
		if ($excerpt) {
			$postarr['post_excerpt'] = $excerpt;
		}

		// thumbnail
		$thumbnail = false;
		if (isset($res['atts']) && isset($res['thumbnail'])) {
			if (isset($res['atts']['update_thumbnail'])) {
				$thumbnail = $res['thumbnail'];
			}
		}
		elseif (isset($res['thumbnail'])) {
			$thumbnail = $res['thumbnail'];
		}
		if ($thumbnail && $update_post) {
			if (isset($res['file_handling'])) {
				if (isset($res['file_handling']['thumbnail'])) {
					if (array_key_exists($thumbnail, $res['file_handling']['thumbnail']['attachment_ids'])) {
						set_post_thumbnail($post, $res['file_handling']['thumbnail']['attachment_ids'][$thumbnail]);
					}
				}
			}
		}

		// post
		if ($update_post) {
			$id = wp_update_post($postarr, true);
			if (empty($id) || is_wp_error($id)) {
				return false;
			}
		}
		foreach ($postarr as $key => $value) {
			if (isset($post->$key)) {
				$post->$key = $value;
			}
		}
		return $postarr;
	}

	/* functions - string parsing */

	public function get_shortcode_atts($atts = array(), $shortcode = 'copy-content') {
		$defaults_system = $this->get_shortcode_defaults($shortcode);
		$defaults_system['update_excerpt'] = $this->get_option(static::$prefix, $this->option_prefix[$shortcode].'_update_excerpt', false);
		$defaults_system['update_thumbnail'] = $this->get_option(static::$prefix, $this->option_prefix[$shortcode].'_update_thumbnail', false);
		$defaults_user = $this->make_array(shortcode_parse_atts($this->get_option(static::$prefix, $this->option_prefix[$shortcode].'_shortcode_defaults', '')));
		$defaults = wp_parse_args($defaults_user, $defaults_system);
		if (!is_array($atts)) {
			$atts = $this->make_array(shortcode_parse_atts($atts));
		}
		$atts = wp_parse_args($atts, $defaults);
		// resolve user input
		if (!empty($atts)) {
			$trim_quotes = function($str) use (&$trim_quotes) {
				if (is_string($str)) {
					$str = trim($str, " '".'"');
				}
				elseif (is_array($str)) {
					$str = array_map($trim_quotes, $str);
				}
				return $str;
			};
			$atts = array_map($trim_quotes, $atts);
			if (isset($atts['include'])) {
				$atts['include'] = $this->make_array($atts['include']);
			}
			if (isset($atts['exclude'])) {
				$atts['exclude'] = $this->make_array($atts['exclude']);
			}
			if (isset($atts['update_excerpt'])) {
				$atts['update_excerpt'] = $this->is_true($atts['update_excerpt']);
			}
			if (isset($atts['update_thumbnail'])) {
				$atts['update_thumbnail'] = $this->is_true($atts['update_thumbnail']);
			}
			if (isset($atts['force-update'])) {
				$atts['force-update'] = $this->is_true($atts['force-update']);
			}
			if (isset($atts['force-refresh'])) {
				$atts['force-refresh'] = $this->is_true($atts['force-refresh']);
			}
			$tags = array_diff_key($atts, $defaults_system);
			if (!empty($tags)) {
				// copy-content - handle extra atts as tag includes/excludes
				if ($shortcode == $this->shortcode_copycontent) {
					$atts['tags'] = array();
					foreach ($tags as $key => $value) {
						unset($atts[$key]);
						if (is_numeric($value) || is_bool($value) || $value === 'true' || $value === 'false') {
							$atts['tags'][$key] = $this->is_true($value);
						}
						elseif ($value === 'all' || $value === '*') {
							$atts['tags'][$key] = true;
						}
						elseif (is_numeric($key) && is_string($value)) {
							/*
							arguments with no attributes will be given numeric keys
							e.g. "div span"
						    [0] => div
						    [1] => span
							*/
							$atts['tags'][$value] = array();
						}
						elseif (is_numeric($key)) {
							continue;
						}
						else {
							$atts['tags'][$key] = $this->make_array($value);
						}
					}
				}
				// wp-copy-content - handle extra atts as wp_query args - https://developer.wordpress.org/reference/classes/wp_query/
				elseif ($shortcode == $this->shortcode_wpcopycontent) {
					$atts['query_user'] = array();
					$atts['query'] = array();

					$func_replace = function(&$key) {
						$replace = array(
							'ID' => 'p',
							'id' => 'p',
							'post_id' => 'p',
							'type' => 'post_type',
							'post_name' => 'name',
							'search' => 's',
							'category' => 'category_name',
						);
						if (isset($replace[$key])) {
							$key = $replace[$key];
						}
						return $key;
					};
					$func_array = function($key, $value, &$arr = array()) {
						$array_fields = array('author__in','author__not_in','category__and','category__in','category__not_in','tag__and','tag__in','tag__not_in','tag_slug__and','tag_slug__in','tax_query','post_parent__in','post_parent__not_in','post__in','post__not_in','post_name__in','date_query','meta_query');
						if (in_array($key, $array_fields)) {
							$arr[$key] = $this->make_array($value);
						}
						else {
							$arr[$key] = $value;
						}
						return $arr;
					};
					$func_extra = function($key, &$arr = array()) {
						$extra = array(
							// singular
							'p' => array(
								'post_type' => 'any',
								'post_status' => 'any'
							),
							'name' => array(
								'post_type' => 'any',
								'post_status' => 'any'
							),
							'page_id' => array(
								'post_type' => 'any',
								'post_status' => 'any'
							),
							'pagename' => array(
								'post_type' => 'any',
								'post_status' => 'any'
							),
							// loops
							'post_parent' => array(
								'post_type' => 'any',
								'post_status' => array('publish','inherit'),
							),
							'post_parent__in' => array(
								'post_type' => 'any',
								'post_status' => array('publish','inherit'),
							),
							'post__in' => array(
								'post_type' => 'any',
								'post_status' => array('publish','inherit'),
							),						
							'post_name__in' => array(
								'post_type' => 'any',
								'post_status' => array('publish','inherit'),
							),
							'post_type' => array(
								'post_status' => array('publish','inherit'),
							),
						);
						if (isset($extra[$key])) {
							foreach ($extra[$key] as $extra_key => $value) {
								if (!isset($arr[$extra_key])) {
									$arr[$extra_key] = $value;
								}
							}
						}
						return $arr;
					};

					foreach ($tags as $key => $value) {
						unset($atts[$key]);
						if (is_numeric($key) && is_string($value)) {
							$atts['query_user'][$value] = true;
							$func_replace($value);
							$atts['query'][$value] = true;
							$func_extra($value,$atts['query']);
						}
						elseif (is_numeric($key)) {
							continue;
						}
						else {
							$atts['query_user'][$key] = $value;
							$func_replace($key);
							$func_array($key,$value,$atts['query']);
							$func_extra($key,$atts['query']);
						}
					}

					$is_paged = false;
					$paged_fields = array('nopaging','posts_per_page','posts_per_archive_page','offset','paged','page');
					foreach ($paged_fields as $value) {
						if (isset($atts['query'][$value])) {
							$is_paged = true;
							break;
						}
					}
					if (!$is_paged) {
						$atts['query']['nopaging'] = true;
					}
				}
			}
		}
		$atts = array_filter($atts);
		return $atts;
	}

	private function loadHTML($str)	{
		$dom = @DOMDocument::loadHTML('<'.$this->domwrapper.'>'.mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8').'</'.$this->domwrapper.'>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		return $dom;
	}

	private function saveHTML($dom)	{
		$str = $dom->saveHTML();
		$str = preg_replace("/<[\/]?".$this->domwrapper."[^>]*>/is", "", $str);
		// wp adds single space before closer, so we should match it
		if (preg_match_all("/(<(".implode("|", $this->get_void_tags()).") [^>]+)>/is", $str, $matches)) {
			if (!empty($matches[0])) {
				$replace = array();
				foreach ($matches[0] as $key => $value) {
					$replace[$value] = rtrim($matches[1][$key], '/ ').' />';
				}
				$str = str_replace(array_keys($replace), $replace, $str);
			}
		}
		return $str;
	}

	private function selector_to_xpath($value) {
		// e.g. div#myID/comment, span.myClass, #myID2
		$comment = false;
		if (strpos($value, "/comment") !== false) {
			$comment = true;
			$value = str_replace("/comment", "", $value);
		}
		if (strpos($value, "#") !== false) {
			$arr = explode("#", $value);
			$arr = array_filter($arr);
			if (count($arr) == 2) {
				$value = $arr[0]."[@id='".$arr[1]."']";
			}
			elseif (!empty($arr)) {
				$key = key($arr);
				$value = "*[@id='".$arr[$key]."']";
			}
		}
		elseif (strpos($value, ".") !== false) {
			$arr = explode(".", $value);
			$arr = array_filter($arr);
			if (count($arr) == 2) {
				$value = $arr[0]."[contains(@class,'".$arr[1]."')]";
			}
			elseif (!empty($arr)) {
				$key = key($arr);
				$value = "*[contains(@class,'".$arr[$key]."')]";
			}
		}
		if ($comment) {
			$value = $value.'/comment()'; // this must be single quotes! todo: iconv
		}
		return $value;
	}

	private function relative_links_absolute($str = '', $url = '') {
		if (strpos($str, '<') === false) {
			return $str;
		}
		if (strpos($url, 'http') === false) {
			return $str;
		}
		$links = $this->get_attributes_for_urls();
		$replaces = array(
			// does not begin with (http:// or https:// or .. or / or #)
			array (
				'preg_match_all' => "/ (".implode("|", $links).")=[\"'](?!(http:\/\/|https:\/\/|\.\.|\/|#))[^\"']+[\"']/is",
				'preg_replace_pattern' => "/(=[\"'])([^\"']+[\"'])/is",
				'preg_replace_replacement' => "$1".str_replace(basename($url), '', $url)."$2"
			),
			// begins with ../
			array (
				'strpos_needle' => '="../',
				'preg_match_all' => "/ (".implode("|", $links).")=[\"']\.\.\/[^\"']+[\"']/is",
				'preg_replace_pattern' => "/(=[\"'])\.\.(\/[^\"']+[\"'])/is",
				'preg_replace_replacement' => "$1".dirname(str_replace(basename($url), '', $url))."$2"
			),
			// begins with /
			array (
				'strpos_needle' => '="/',
				'preg_match_all' => "/ (".implode("|", $links).")=[\"']\/[^\/]{1}[^\"']+[\"']/is",
				'preg_replace_pattern' => "/(=[\"'])(\/[^\"']+[\"'])/is",
				'preg_replace_replacement' => "$1".parse_url($url, PHP_URL_SCHEME)."://".parse_url($url, PHP_URL_HOST)."$2"
			),
			// is only /
			array (
				'strpos_needle' => '="/"',
				'preg_match_all' => "/ (".implode("|", $links).")=[\"']\/[\"']/is",
				'preg_replace_pattern' => "/(=[\"'])(\/[\"'])/is",
				'preg_replace_replacement' => "$1".parse_url($url, PHP_URL_SCHEME)."://".parse_url($url, PHP_URL_HOST)."$2"
			),
		);
		foreach ($replaces as $value) {
			if (isset($value['strpos_needle'])) {
				if (strpos($str, $value['strpos_needle']) === false) {
					continue;
				}
			}
			if (preg_match_all($value['preg_match_all'], $str, $matches)) {
				if (!empty($matches[0])) {
					$matches[0] = array_unique($matches[0]);
					$replace = array();
					foreach ($matches[0] as $match) {
						$replace[$match] = preg_replace($value['preg_replace_pattern'], $value['preg_replace_replacement'], $match);
					}
					$str = str_replace(array_keys($replace), $replace, $str);
				}
			}
		}
		return $str;
	}

	/* functions - arrays */

    private function get_options_array() {
		return array(
			'active',
			// copy-content
			'copycontent_update_excerpt', // update_excerpt overwrite in shortcode
			'copycontent_update_thumbnail', // update_thumbnail overwrite in shortcode
			'copycontent_shortcode_defaults', // string
			'copycontent_file_handling', // keep, remove, download
			'copycontent_file_exists', // discard, replace, new
			// wp-copy-content
			'wpcopycontent_update_excerpt',
			'wpcopycontent_update_thumbnail',
			'wpcopycontent_shortcode_defaults',
			'wpcopycontent_file_handling',
			'wpcopycontent_file_exists',
		);
    }

    private function get_shortcode_defaults($shortcode = 'copy-content') {
		$defaults = array(
			'url' => '',
			'include' => 'body',
			'exclude' => '',
			'refresh' => 0, // number or string, 0 = forever
			'update_excerpt' => false,
			'update_thumbnail' => false,
			'force-update' => false,
			'force-refresh' => false,
		);
		if ($shortcode == $this->shortcode_wpcopycontent) {
			$defaults = array(
				'include' => '',
				'exclude' => '',
				'refresh' => 0, // number or string, 0 = forever
				'update_excerpt' => false,
				'update_thumbnail' => false,
				'force-update' => false,
				'force-refresh' => false,
			);
			if (is_multisite()) {
				$defaults = array_merge( array('blog_id' => get_current_blog_id()), $defaults );
			}
		}
		return $defaults;
	}

    private function get_file_handling_options() {
    	$options = array(
    		'keep' => __('Keep original link'),
    		'remove' => __('Remove from content'),
    		'download' => __('Download file'),
    	);
    	return $options;
    }

    private function get_file_exists_options() {
    	$options = array(
    		'discard' => __('Discard the new file and keep the old file'),
    		'replace' => __('Replace the old file with the new file'),
    		'new' => __('Download the new file and keep the old file'),
    	);
    	return $options;
    }

    private function get_file_types() {
    	// https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types
    	$file_types = array(
			'image' => array(
				'label' => __('Images'),
				'mime_type' => 'image/%',
				'extensions' => array('jpg','jpeg','png','tif','tiff','svg','bmp','ico','gif','webp'),
			),
			'audio' => array(
				'label' => __('Audio'),
				'mime_type' => 'audio/%',
				'extensions' => array('aac','wav','mp3','ogg','oga','flac','aif','mid','midi','opus','weba','wma','m4a'),
			),
			'video' => array(
				'label' => __('Video'),
				'mime_type' => 'video/%',
				'extensions' => array('mp4','mov','avi','m4v','flv','mpeg','ogv','ts','webm','mpg','3gp','3g2','wmv'),
			),
			'doc' => array(
				'label' => __('Documents'),
				'mime_type' => 'application/%',
				'extensions' => array('doc','docx','odp','ods','odt','pdf','ppt','pptx','rtf','xls','xlsx','pps','ppsx','key'),
			),
			'css' => array(
				'label' => __('Stylesheets'),
				'mime_type' => 'text/css',
				'extensions' => array('css','scss','sass','less'),
			),
			'javascript' => array(
				'label' => __('Javascript'),
				'mime_type' => 'text/javascript',
				'extensions' => array('js','mjs'),
			),
			'zip' => array(
				'label' => __('Archives'),
				'mime_type' => 'application/%',
				'extensions' => array('bz','bz2','gz','rar','tar','zip'),
			),
    	);
    	return apply_filters('copycontent_file_types', $file_types);
    }

	private function get_attributes_for_urls() {
		$links = array(
			// https://stackoverflow.com/questions/2725156/complete-list-of-html-tag-attributes-which-have-a-url-value
			'href',
			'codebase',
			'cite',
			'background',
			'action',
			'longdesc',
			'src',
			'usemap',
			'classid',
			'data',
			// style tags? url=, background=,
		);
		return $links;
	}

	private function get_void_tags() {
		$void_tags = array(
			'area',
			'base',
			'basefont',
			'br',
			'col',
			'command',
			'embed',
			'frame',
			'hr',
			'img',
			'input',
			'keygen',
			'link',
			'menuitem',
			'meta',
			'param',
			'source',
			'track',
			'wbr',
		);
		return $void_tags;
	}

	private function message_array($class = 'updated', $message = '') {
		return array('class' => $class, 'message' => $message);
	}

	/* functions-common */

	private function url_exists($url = '') {
		if (function_exists(__FUNCTION__)) {
			$func = __FUNCTION__;
			return $func($url);
		}
		if (empty($url)) {
			return false;
		}
		$url_check = @get_headers($url);
		if (!is_array($url_check) || strpos($url_check[0], "404") !== false) {
			return false;
		}
		return true;
	}

	private function fix_potential_html_string($str = '') {
		if (function_exists(__FUNCTION__)) {
			$func = __FUNCTION__;
			return $func($str);
		}
		if (empty($str)) {
			return $str;
		}
		if (strpos($str, "&lt;") !== false) {
			if (substr_count($str, "&lt;") > substr_count($str, "<") || preg_match("/&lt;\/[\w]+&gt;/is", $str)) {
				$str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
			}
		}
		elseif (strpos($str, "&#039;") !== false) {
			$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		}
		return $str;
	}

	private function trim_excess_space($str = '') {
		if (function_exists(__FUNCTION__)) {
			$func = __FUNCTION__;
			return $func($str);
		}
		if (empty($str)) {
			return $str;
		}
		$replace_with_space = array("&nbsp;", "&#160;", "\xc2\xa0");
		$str = str_replace($replace_with_space, ' ', $str);

		if (strpos($str, "</") !== false) {
			$str = preg_replace("/[\t\n\r ]*(<\/[^>]+>)/s", "$1", $str); // no space before closing tags
		}

		$str = preg_replace("/[\t ]*(\n|\r)[\t ]*/s", "$1", $str);
		$str = preg_replace("/(\n\r){3,}/s", "$1$1", $str);
		$str = preg_replace("/[\n]{3,}/s", "\n\n", $str);
		$str = preg_replace("/[ ]{2,}/s", ' ', $str);
		return trim($str);
	}

	private function strip_tags_html_comments($str = '', $allowable_tags = '') {
		if (function_exists(__FUNCTION__)) {
			$func = __FUNCTION__;
			return $func($str, $allowable_tags);
		}
		if (empty($str)) {
			return $str;
		}
		$replace = array(
			"<!--" => "###COMMENT_OPEN###",
			"-->" => "###COMMENT_CLOSE###",
		);
		$str = str_replace(array_keys($replace), $replace, $str);
		$str = strip_tags($str, $allowable_tags);
		$str = str_replace($replace, array_keys($replace), $str);
		return $str;
	}

	private function file_get_contents_extended($filename = '') {
		if (function_exists(__FUNCTION__)) {
			$func = __FUNCTION__;
			return $func($filename);
		}
		if (empty($filename)) {
			return false;
		}
		$is_url = false;
		if (strpos($filename, 'http') === 0) {
			if ($this->url_exists($filename) === false) {
				return false;
			}
			$is_url = true;
		}
		$str = '';
		// use user_agent when available
		$user_agent = 'PHP'.phpversion().'/'.__FUNCTION__;
		if (isset($_SERVER["HTTP_USER_AGENT"]) && !empty($_SERVER["HTTP_USER_AGENT"])) {
			$user_agent = $_SERVER["HTTP_USER_AGENT"];
		}
		// try php
		$options = array('http' => array('user_agent' => $user_agent));
		// try 'correct' way
		if ($str_php = @file_get_contents($filename, false, stream_context_create($options))) {
			$str = $str_php;
		}
		// try 'insecure' way
		if (empty($str)) {
			$options['ssl'] = array(
				'verify_peer' => false,
				'verify_peer_name' => false,
			);
			if ($str_php = @file_get_contents($filename, false, stream_context_create($options))) {
				$str = $str_php;
			}
		}
		// try curl
		if (empty($str) && $is_url) {
			if (function_exists('curl_init')) {
				$c = @curl_init();
				// try 'correct' way
				curl_setopt($c, CURLOPT_URL, $filename);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($c, CURLOPT_MAXREDIRS, 10);
                $str = curl_exec($c);
                // try 'insecure' way
                if (empty($str)) {
                    curl_setopt($c, CURLOPT_URL, $filename);
                    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($c, CURLOPT_USERAGENT, $user_agent);
                    $str = curl_exec($c);
                }
				curl_close($c);
			}
		}
		$str = $this->fix_potential_html_string($str);
		$str = $this->trim_excess_space($str);
		if (empty($str)) {
			return false;
		}
		return $str;
	}
}
endif;
?>