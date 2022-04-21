<?php
/*
Plugin Name: Half/theory Copy Content
Plugin URI: https://github.com/halftheory/wp-halftheory-copy-content
GitHub Plugin URI: https://github.com/halftheory/wp-halftheory-copy-content
Description: Half/theory Copy Content Plugin.
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 3.0
Network: false
*/

/*
Available filters:
copycontent_content_filters
copycontent_file_types
copycontent_is_valid_file
copycontent_shortcode
wpcopycontent_query
wpcopycontent_pagination_args
wpcopycontent_template
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Plugin', false) && is_readable(dirname(__FILE__) . '/class-halftheory-helper-plugin.php') ) {
	include_once dirname(__FILE__) . '/class-halftheory-helper-plugin.php';
}

if ( ! class_exists('Halftheory_Copy_Content', false) && class_exists('Halftheory_Helper_Plugin', false) ) :
	final class Halftheory_Copy_Content extends Halftheory_Helper_Plugin {

        protected static $instance;
        public static $prefix;
        public static $active = false;

        public $shortcode_copycontent = 'copy-content';
        public $shortcode_wpcopycontent = 'wp-copy-content';
        public $shortcodes = array();
        private $domwrapper = 'domwrapper';

        /* setup */

        protected function setup_globals( $plugin_basename = null, $prefix = null ) {
            parent::setup_globals($plugin_basename, $prefix);

            self::$active = $this->get_options_context('db', 'active');
            $this->plugin_description = __('Created by plugin: ') . $this->plugin_title;
            $this->shortcodes = array(
                $this->shortcode_copycontent => 'copycontent',
                $this->shortcode_wpcopycontent => 'wpcopycontent',
            );

            // Only on our menu_page.
            if ( $this->is_menu_page() ) {
                $this->menu_page_tabs = array(
                    '' => array(
                        'name' => __('Settings'),
                        'callback' => 'menu_page',
                    ),
                    'transients' => array(
                        'name' => __('Stored HTML'),
                        'callback' => 'menu_page_transients',
                    ),
                );
            }
        }

        protected function setup_actions() {
            parent::setup_actions();

            // content filters - always load in admin.
            if ( ! $this->is_front_end() ) {
                if ( ! class_exists('Halftheory_Copy_Content_Filters', false) && is_readable(dirname(__FILE__) . '/class-halftheory-copy-content-filters.php') ) {
                    include_once dirname(__FILE__) . '/class-halftheory-copy-content-filters.php';
                }
                if ( class_exists('Halftheory_Copy_Content_Filters', false) ) {
                    Halftheory_Copy_Content_Filters::add_filters();
                }
                foreach ( $this->shortcodes as $shortcode => $shortcode_prefix ) {
                    if ( $filters = $this->get_options_context('db', $shortcode_prefix . '_content_filters', false) ) {
                        foreach ( $filters as $priority => $filter ) {
                            add_filter($shortcode_prefix . '_content_filters_active', $filter, $priority, 3);
                        }
                    }
                }
            }

            // Stop if not active.
            if ( empty(self::$active) ) {
                return;
            }

            if ( ! $this->is_front_end() ) {
                // admin.
                add_action('add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2);
                add_action('post_updated', array( $this, 'post_updated' ), 20, 3);
                add_action('admin_notices', array( $this, 'admin_notices' ));
            } else {
                // public.
                add_action('init', array( $this, 'init' ), 20);
                add_filter('the_content', array( $this, 'the_content' ), 20);
            }

            // shortcodes.
            foreach ( $this->shortcodes as $shortcode => $shortcode_prefix ) {
                if ( ! shortcode_exists($shortcode) ) {
                    add_shortcode($shortcode, array( $this, 'shortcode' ));
                }
            }
        }

        public static function plugin_uninstall() {
            static::$instance->delete_transient_uninstall();
            static::$instance->delete_option_uninstall();
            parent::plugin_uninstall();
        }

        /* admin */

        public function menu_page() {
            $plugin = static::$instance;

            // redirect to tab functions.
            if ( $plugin->load_menu_page_tab() ) {
                return;
            }

            global $title;
            ?>
            <div class="wrap">
            <h2><?php echo esc_html($title); ?></h2>

            <?php
            if ( $plugin->save_menu_page(__FUNCTION__) ) {
                $save = function () use ( $plugin ) {
                    // text fields.
                    $text_fields = array(
                        'copycontent_shortcode_defaults',
                        'wpcopycontent_shortcode_defaults',
                    );
                    // filter fields.
                    $filter_fields = array(
                        'copycontent_content_filters',
                        'wpcopycontent_content_filters',
                    );
                    // get values.
                    $options = array();
                    foreach ( array_keys($plugin->get_options_context('default')) as $value ) {
                        $name = $plugin::$prefix . '_' . $value;
                        if ( ! isset($_POST[ $name ]) ) {
                            continue;
                        }
                        if ( $plugin->empty_notzero($_POST[ $name ]) ) {
                            continue;
                        }
                        if ( in_array($value, $text_fields, true) ) {
                            $_POST[ $name ] = trim(stripslashes($_POST[ $name ]));
                        }
                        if ( in_array($value, $filter_fields, true) ) {
                            $_POST[ $name ] = $plugin->make_content_filters_array('admin_form', 'db', $_POST[ $name ]);
                        }
                        $options[ $value ] = $_POST[ $name ];
                    }
                    // save it.
                    $updated = '<div class="updated"><p><strong>' . esc_html__('Options saved.') . '</strong></p></div>';
                    $error = '<div class="error"><p><strong>' . esc_html__('Error: There was a problem.') . '</strong></p></div>';
                    if ( ! empty($options) ) {
                        $options = $plugin->get_options_context('input', null, array(), $options);
                        if ( $plugin->update_option($plugin::$prefix, $options) ) {
                            echo $updated;
                        } else {
                            echo $error;
                        }
                    } else {
                        if ( $plugin->delete_option($plugin::$prefix) ) {
                            echo $updated;
                        } else {
                            echo $updated;
                        }
                    }
                };
                $save();
            }

            // Show the form.
            $options = $plugin->get_options_context('admin_form');
            ?>

            <?php $plugin->print_menu_page_tabs(); ?>

            <form id="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" name="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" method="post" action="<?php echo esc_attr($_SERVER['REQUEST_URI']); ?>">
            <?php
            // Use nonce for verification.
            wp_nonce_field($plugin->plugin_basename, $plugin->plugin_name . '::' . __FUNCTION__);
            ?>
            <div id="poststuff">

            <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_active"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_active" name="<?php echo esc_attr($plugin::$prefix); ?>_active" value="1"<?php checked($options['active'], true); ?> /> <?php echo esc_html($plugin->plugin_title); ?> <?php esc_html_e('active?'); ?></label></p>

            <?php foreach ( $plugin->shortcodes as $shortcode => $shortcode_prefix ) : ?>
                <h3>[<?php echo esc_html($shortcode); ?>] <?php esc_html_e('shortcode'); ?></h3>
                <p>
                    <?php
                    switch ( $shortcode ) {
                        case $plugin->shortcode_copycontent:
                        default:
                            esc_html_e('For external URLs.');
                            break;

                        case $plugin->shortcode_wpcopycontent:
                            esc_html_e('For internal posts.');
                            break;
                    }
                    ?>
                </p>
                <div class="postbox">
                    <div class="inside">
                        <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_excerpt"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_excerpt" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_excerpt" value="1"<?php checked($options[ $shortcode_prefix . '_update_excerpt' ], true); ?> /> <?php _e('Update my post <strong>excerpt</strong> where possible.'); ?></label></p>

                        <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_thumbnail"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_thumbnail" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_update_thumbnail" value="1"<?php checked($options[ $shortcode_prefix . '_update_thumbnail' ], true); ?> /> <?php _e('Update my post <strong>thumbnail</strong> where possible.'); ?></label></p>

                        <label for="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_shortcode_defaults">
                            <h4><?php esc_html_e('Shortcode Defaults'); ?></h4>
                            <?php
                            $placeholder = '';
                            $arr = $plugin->get_shortcode_atts_context($shortcode, 'default');
                            $arr['update_excerpt'] = $options[ $shortcode_prefix . '_update_excerpt' ];
                            $arr['update_thumbnail'] = $options[ $shortcode_prefix . '_update_thumbnail' ];
                            $arr = array_filter($arr,
                                function ( $v ) use ( $plugin ) {
                                    return ! $plugin->empty_notzero($v);
                                }
                            );
                            ksort($arr);
                            foreach ( $arr as $key => $value ) {
                                if ( is_array($value) ) {
                                    sort($value);
                                    $value = implode(',', $value);
                                }
                                $placeholder .= "$key=$value ";
                            }
                            ?>
                            <input type="text" id="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_shortcode_defaults" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_shortcode_defaults" value="<?php echo esc_attr($options[ $shortcode_prefix . '_shortcode_defaults' ]); ?>" style="min-width: 20em; width: 50%;" placeholder="<?php echo esc_attr(trim($placeholder)); ?>" />
                        </label>

                        <h4><?php esc_html_e('Content Filters'); ?></h4>
                        <?php
                        $arr_form = $plugin->make_content_filters_array('wp_filter', 'admin_form');
                        if ( ! empty($arr_form) ) {
                            ?>
                            <table style="min-width: 50%;" class="wp-list-table striped">
                            <?php
                            $arr_db = $plugin->make_content_filters_array('db', 'admin_form', $options[ $shortcode_prefix . '_content_filters' ]);
                            $i = 0;
                            foreach ( $arr_form as $key => $value ) {
                                $label = $plugin::$prefix . '_' . $shortcode_prefix . '_content_filters_' . ( $i++ );
                                $checked = isset($arr_db[ $key ]) ? $key : false;
                                ?>
                                <tr><td><input type="checkbox" id="<?php echo esc_attr($label); ?>" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_content_filters[]" value="<?php echo esc_attr($key); ?>"<?php checked($checked, $key); ?> /></td><td><label for="<?php echo esc_attr($label); ?>"><?php echo esc_html($value); ?></label></td></tr>
                                <?php
                            }
                            ?>
                            </table>
                            <?php
                        } else {
                            ?>
                            <p><?php esc_html_e('No filters found.'); ?></p>
                            <?php
                        }
                        ?>

                        <h4><?php esc_html_e('File Handling'); ?></h4>
                        <table style="min-width: 50%;" class="wp-list-table striped">
                            <tr>
                                <th><?php esc_html_e('File Type'); ?></th>
                                <th><?php esc_html_e('Behavior'); ?></th>
                                <th><?php esc_html_e('File Exists'); ?></th>
                            </tr>
                        <?php
                        foreach ( $plugin->get_file_types() as $file_key => $file_type ) {
                            if ( ! isset($options[ $shortcode_prefix . '_file_handling' ][ $file_key ]) ) {
                                $options[ $shortcode_prefix . '_file_handling' ][ $file_key ] = '';
                            }
                            if ( ! isset($options[ $shortcode_prefix . '_file_exists' ][ $file_key ]) ) {
                                $options[ $shortcode_prefix . '_file_exists' ][ $file_key ] = '';
                            }
                            sort($file_type['extensions']);
                            ?>
                            <tr>
                                <td><label for="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_file_handling[<?php echo esc_attr($file_key); ?>]"><span title="<?php echo esc_attr(implode(', ', $file_type['extensions'])); ?>"><?php echo esc_html($file_type['label']); ?></span></label></td>

                                <td><select id="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_file_handling[<?php echo esc_attr($file_key); ?>]" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_file_handling[<?php echo esc_attr($file_key); ?>]">
                                    <?php foreach ( $plugin->get_file_handling_options() as $key => $value ) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options[ $shortcode_prefix . '_file_handling' ][ $file_key ]); ?>><?php echo esc_html($value); ?></option>
                                    <?php endforeach; ?>
                                </select></td>

                                <td><select id="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_file_exists[<?php echo esc_attr($file_key); ?>]" name="<?php echo esc_attr($plugin::$prefix); ?>_<?php echo esc_attr($shortcode_prefix); ?>_file_exists[<?php echo esc_attr($file_key); ?>]">
                                    <?php foreach ( $plugin->get_file_exists_options() as $key => $value ) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"<?php selected($key, $options[ $shortcode_prefix . '_file_exists' ][ $file_key ]); ?>><?php echo esc_html($value); ?></option>
                                    <?php endforeach; ?>
                                </select></td>
                            </tr>
                            <?php
                        }
                        ?>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php submit_button(__('Update'), array( 'primary', 'large' ), 'save'); ?>

            </div><!-- poststuff -->
            </form>

            </div><!-- wrap -->
            <?php
        }

        public function menu_page_transients( $plugin ) {
            global $title;
            ?>
            <div class="wrap">
            <h2><?php echo esc_html($title); ?></h2>

            <?php
            if ( $plugin->save_menu_page(__FUNCTION__) ) {
                $save = function () use ( $plugin ) {
                    // get values.
                    $input_arr = array(
                        'url',
                        'content',
                    );
                    $input = array();
                    foreach ( $input_arr as $value ) {
                        $name = $plugin::$prefix . '_' . $value;
                        if ( ! isset($_POST[ $name ]) ) {
                            continue;
                        }
                        $_POST[ $name ] = trim(stripslashes($_POST[ $name ]));
                        if ( $plugin->empty_notzero($_POST[ $name ]) ) {
                            continue;
                        }
                        $input[ $value ] = $_POST[ $name ];
                    }
                    $updated = '<div class="updated"><p><strong>' . esc_html__('HTML contents saved.') . '</strong></p></div>';
                    $error = '<div class="error"><p><strong>' . esc_html__('Error: There was a problem.') . '</strong></p></div>';
                    if ( count($input) !== count($input_arr) ) {
                        echo $error;
                        return;
                    }
                    if ( $plugin->set_transient_filtered(null, $input['content'], $input['url']) ) {
                        echo $updated;
                    } else {
                        echo $error;
                    }
                };
                $save();
            } elseif ( $plugin->save_menu_page(__FUNCTION__, 'save_transients') ) {
                $save = function () use ( $plugin ) {
                    // delete.
                    $name = $plugin::$prefix . '_transients_delete';
                    $deleted = array();
                    if ( isset($_POST[ $name ]) ) {
                        $updated = '<div class="updated"><p><strong>' . esc_html__('Transients deleted.') . '</strong></p></div>';
                        $error = '<div class="error"><p><strong>' . esc_html__('Not all transients were deleted.') . '</strong></p></div>';
                        if ( ! empty($_POST[ $name ]) && is_array($_POST[ $name ]) ) {
                            foreach ( $_POST[ $name ] as $value ) {
                                if ( $plugin->delete_transient($value) ) {
                                    $deleted[] = $value;
                                }
                            }
                            if ( count($_POST[ $name ]) === count($deleted) ) {
                                echo $updated;
                            } else {
                                echo $error;
                            }
                        } else {
                            echo $error;
                        }
                    }
                    // filter.
                    $name = $plugin::$prefix . '_transients_filter';
                    if ( isset($_POST[ $name ]) ) {
                        $updated = '<div class="updated"><p><strong>' . esc_html__('Transients filtered.') . '</strong></p></div>';
                        $error = '<div class="error"><p><strong>' . esc_html__('Not all transients were filtered.') . '</strong></p></div>';
                        if ( ! empty($_POST[ $name ]) && is_array($_POST[ $name ]) ) {
                            $filtered = array();
                            foreach ( $_POST[ $name ] as $key => $value ) {
                                // can't filter what was deleted.
                                if ( in_array($value, $deleted, true) ) {
                                    unset($_POST[ $name ][ $key ]);
                                    continue;
                                }
                                if ( $str = $plugin->get_transient($value) ) {
                                    $url = isset($_POST[ $plugin::$prefix . '_url_' . $value ]) ? trim(stripslashes($_POST[ $plugin::$prefix . '_url_' . $value ])) : '';
                                    if ( $plugin->set_transient_filtered($value, $str, $url) ) {
                                        $filtered[] = $value;
                                    }
                                }
                            }
                            if ( count($_POST[ $name ]) === count($filtered) ) {
                                echo $updated;
                            } else {
                                echo $error;
                            }
                        } else {
                            echo $error;
                        }
                    }
                };
                $save();
            }
            ?>

            <?php $plugin->print_menu_page_tabs(); ?>

            <form id="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" name="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" method="post" action="<?php echo esc_attr($_SERVER['REQUEST_URI']); ?>">
            <?php
            // Use nonce for verification.
            wp_nonce_field($plugin->plugin_basename, $plugin->plugin_name . '::' . __FUNCTION__);
            ?>
            <div id="poststuff">

            <h3><?php esc_html_e('Input HTML'); ?></h3>
            <p><?php esc_html_e('Use this form to manually update the locally stored record (transient) of the HTML contents for an external URL. This can be useful in certain cases: when the plugin cannot reach the URL; when a login is required; when javascript changes the DOM; etc.'); ?></p>
            <div class="postbox">
                <div class="inside">
                    <label for="<?php echo esc_attr($plugin::$prefix); ?>_url">
                        <h4><?php esc_html_e('External URL'); ?></h4>
                        <?php
                        $val = isset($_POST[ $plugin::$prefix . '_url' ]) ? trim(stripslashes($_POST[ $plugin::$prefix . '_url' ])) : '';
                        ?>
                        <input type="text" id="<?php echo esc_attr($plugin::$prefix); ?>_url" name="<?php echo esc_attr($plugin::$prefix); ?>_url" value="<?php echo esc_attr($val); ?>" style="min-width: 20em; width: 50%;" />
                    </label>
                    <label for="<?php echo esc_attr($plugin::$prefix); ?>_content">
                        <h4><?php esc_html_e('HTML Contents'); ?></h4>
                        <?php
                        $val = isset($_POST[ $plugin::$prefix . '_content' ]) ? trim(stripslashes($_POST[ $plugin::$prefix . '_content' ])) : '';
                        ?>
                        <textarea id="<?php echo esc_attr($plugin::$prefix); ?>_content" name="<?php echo esc_attr($plugin::$prefix); ?>_content" style="min-width: 20em; width: 50%; min-height: 20em;"><?php echo $plugin->esc_textarea_substitute($val); ?></textarea>
                    </label>
                </div>
            </div>
            <?php submit_button(__('Update'), array( 'primary', 'large' ), 'save'); ?>

            <h3><?php esc_html_e('Existing HTML'); ?></h3>
            <?php
            global $wpdb;
            // find existing transients.
            $items = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_" . $plugin::$prefix . "_%' ORDER BY option_id ASC");
            if ( ! empty($items) ) {
                // include table.
                if ( ! class_exists('Halftheory_Copy_Content_Table_Transients', false) && is_readable(dirname(__FILE__) . '/class-halftheory-copy-content-table-transients.php') ) {
                    include_once dirname(__FILE__) . '/class-halftheory-copy-content-table-transients.php';
                }
                if ( class_exists('Halftheory_Copy_Content_Table_Transients', false) ) {
                    // trim items.
                    $items = array_map(
                        function ( $v ) {
                            return str_replace('_transient_', '', $v);
                        },
                        $items
                    );
                    // make an index from post shortcodes.
                    $items_from_posts = array();
                    $arr = $wpdb->get_results("SELECT ID,post_content FROM $wpdb->posts WHERE post_content LIKE '%[" . $plugin->shortcode_copycontent . " %' AND post_content LIKE '% url=%' AND post_type != 'revision' AND post_status != 'auto-draft' ORDER BY ID ASC");
                    if ( ! empty($arr) ) {
                        foreach ( $arr as $value ) {
                            if ( $urls = $plugin->get_urls_from_content($value->post_content) ) {
                                foreach ( $urls as $url ) {
                                    $transient = $plugin->get_transient_name_hash($url);
                                    if ( ! isset($items_from_posts[ $transient ]) ) {
                                        $items_from_posts[ $transient ] = array();
                                    }
                                    $items_from_posts[ $transient ][] = array(
                                        'ID' => $value->ID,
                                        'url' => $url,
                                    );
                                }
                            }
                        }
                    }
                    // print table.
                    $wp_list_table = new Halftheory_Copy_Content_Table_Transients($plugin::$prefix);
                    $wp_list_table->prepare_items($items, $items_from_posts);
                    $wp_list_table->display();
                    submit_button(__('Update'), array( 'primary', 'large' ), 'save_transients');
                } else {
                    ?>
                    <div class="postbox">
                        <div class="inside">
                            <p><?php esc_html_e('No table class.'); ?></p>
                        </div>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="postbox">
                    <div class="inside">
                        <p><?php esc_html_e('No transients found.'); ?></p>
                    </div>
                </div>
                <?php
            }
            ?>

            </div><!-- poststuff -->
            </form>

            </div><!-- wrap -->
            <?php
        }

        public function add_meta_boxes( $post_type, $post ) {
            if ( is_object($post) ) {
                if ( $urls = $this->get_urls_from_content($post->post_content) ) {
                    add_meta_box(
                        static::$prefix,
                        $this->plugin_title,
                        array( $this, 'meta_box' ),
                        null,
                        'advanced',
                        'default',
                        array( 'urls' => $urls )
                    );
                }
            }
        }

        public function meta_box( $post, $args ) {
            if ( ! isset($args['args']) ) {
                return;
            }
            if ( empty($args['args']) ) {
                return;
            }
            if ( ! isset($args['args']['urls']) ) {
                return;
            }
            if ( empty($args['args']['urls']) ) {
                return;
            }
            $urls = $this->make_array($args['args']['urls']);
            echo '<ul>';
            foreach ( $urls as $url ) {
                if ( $v = $this->get_transient_from_url($url) ) {
                    $arr = array( __('URL: ') . make_clickable($url) );
                    if ( $t = $this->get_field_from_html('title', $v, $url) ) {
                        $arr[] = __('Title: ') . '<strong>' . $t . '</strong>';
                    }
                    $v = $this->trim_excess_space($v);
                    $v = str_replace(array( "\r", "\t" ), array( "\n", '' ), $v);
                    $v = preg_replace("/[\n]+/s", "\n", $v);
                    ?>
                    <li>
                        <p><?php echo wptexturize(implode(__(' - '), $arr)); ?></p>
                        <textarea style="width: 100%; max-height: 5em;" readonly><?php echo $this->esc_textarea_substitute($v); ?></textarea>
                    </li>
                    <?php
                }
            }
            echo '</ul>';
        }

        public function post_updated( $post_id, $post_after, $post_before ) {
            if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
                return;
            }
            if ( wp_is_post_revision($post_id) ) {
                return;
            }
            // update only on Edit>Post page.
            if ( isset($_POST) ) {
                if ( isset($_POST['_wpnonce']) ) {
                    if ( wp_verify_nonce($_POST['_wpnonce'], 'update-post_' . $post_id) ) {
                        remove_action(current_action(), array( $this, __FUNCTION__ ), 20);
                        $fields_old = $fields_new = array(
                            'content' => trim($post_after->post_content),
                            'excerpt' => trim($post_after->post_excerpt),
                            'thumbnail' => get_post_thumbnail_id($post_id),
                        );
                        $messages = array();
                        // copy-content.
                        if ( has_shortcode($fields_old['content'], $this->shortcode_copycontent) ) {
                            if ( preg_match_all('/' . get_shortcode_regex(array( $this->shortcode_copycontent )) . '/s', $fields_old['content'], $matches, PREG_SET_ORDER) ) {
                                foreach ( $matches as $key => $match ) {
                                    $atts = $this->get_shortcode_atts_context($this->shortcode_copycontent, 'input', $match[3]);
                                    // has url?
                                    if ( empty($atts['url']) ) {
                                        $this->admin_notice_add('error', __('Error: No URL found - ') . '[' . $match[2] . $match[3] . ']');
                                        continue;
                                    }
                                    // content - must have closing tag and not wrap any content.
                                    if ( strpos($match[0], '[/' . $this->shortcode_copycontent . ']') !== false && $this->empty_notzero(trim($match[5])) ) {
                                        if ( $tmp = $this->copycontent_get_content($atts, $post_id) ) {
                                            $tag_old = $match[0];
                                            $tag_new = '[' . $this->shortcode_copycontent . ' ' . trim($match[3]) . ']' . $tmp . '[/' . $this->shortcode_copycontent . ']';
                                            $fields_new['content'] = preg_replace('/' . preg_quote($tag_old, '/') . '/s', $tag_new, $fields_new['content'], 1);
                                        }
                                    } elseif ( strpos($match[0], '[/' . $this->shortcode_copycontent . ']') === false ) {
                                        $this->admin_notice_add('error', __('Error: No closing tag found - ') . '[' . $match[2] . ' ' . $match[3] . '] - add [/' . $this->shortcode_copycontent . ']');
                                    }
                                    // excerpt.
                                    if ( $this->empty_notzero($fields_new['excerpt']) && $atts['update_excerpt'] ) {
                                        if ( $tmp = $this->get_field_from_html('excerpt', null, $atts['url']) ) {
                                            $fields_new['excerpt'] = $tmp;
                                        }
                                    }
                                    // thumbnail.
                                    if ( empty($fields_new['thumbnail']) && $atts['update_thumbnail'] ) {
                                        if ( $tmp = $this->get_field_from_html('thumbnail', null, $atts['url']) ) {
                                            if ( $id = $this->media_upload_from_url($tmp, $post_id) ) {
                                                $fields_new['thumbnail'] = $id;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // wp-copy-content.
                        if ( has_shortcode($fields_old['content'], $this->shortcode_wpcopycontent) ) {
                            if ( preg_match_all('/' . get_shortcode_regex(array( $this->shortcode_wpcopycontent )) . '/s', $fields_old['content'], $matches, PREG_SET_ORDER) ) {
                                foreach ( $matches as $key => $match ) {
                                    $atts = $this->get_shortcode_atts_context($this->shortcode_wpcopycontent, 'input', $match[3]);
                                    $tmp = $this->wpcopycontent_get_content($atts, $post_id);
                                    // has posts?
                                    if ( $tmp === false ) {
                                        $this->admin_notice_add('error', __('Error: No posts found - ') . '[' . $match[2] . $match[3] . ']');
                                        continue;
                                    }
                                    // content - closing tag is optional.
                                    if ( strpos($match[0], '[/' . $this->shortcode_wpcopycontent . ']') !== false && $this->empty_notzero(trim($match[5])) && ! isset($tmp['loop_end']) ) {
                                            $tag_old = $match[0];
                                            $tag_new = '[' . $this->shortcode_wpcopycontent . ' ' . trim($match[3]) . ']' . $tmp['content'] . '[/' . $this->shortcode_wpcopycontent . ']';
                                            $fields_new['content'] = preg_replace('/' . preg_quote($tag_old, '/') . '/s', $tag_new, $fields_new['content'], 1);
                                    }
                                    // excerpt.
                                    if ( $this->empty_notzero($fields_new['excerpt']) && $atts['update_excerpt'] && isset($tmp['excerpt']) ) {
                                            $fields_new['excerpt'] = $tmp['excerpt'];
                                    }
                                    // thumbnail.
                                    if ( empty($fields_new['thumbnail']) && $atts['update_thumbnail'] && isset($tmp['thumbnail']) ) {
                                        if ( is_numeric($tmp['thumbnail']) ) {
                                            $fields_new['thumbnail'] = $tmp['thumbnail'];
                                        } elseif ( $id = $this->media_upload_from_url($tmp['thumbnail'], $post_id) ) {
                                            $fields_new['thumbnail'] = $id;
                                        }
                                    }
                                }
                            }
                        }
                        // update.
                        if ( $fields_old !== $fields_new ) {
                            $post_date = current_time('mysql');
                            $postarr = array(
                                'ID' => $post_id,
                                'post_modified' => $post_date,
                                'post_modified_gmt' => get_gmt_from_date($post_date),
                                'post_content' => $fields_new['content'],
                                'post_excerpt' => $fields_new['excerpt'],
                            );
                            $res = wp_update_post(wp_slash($postarr), true);
                            if ( empty($res) || is_wp_error($res) ) {
                                $this->admin_notice_add('error', __('Error: Post update failed.'));
                            } else {
                                $this->admin_notice_add('success', __('Success: Post shortcode updated.'));
                            }
                            if ( $fields_old['thumbnail'] !== $fields_new['thumbnail'] ) {
                                $res = set_post_thumbnail($post_id, $fields_new['thumbnail']);
                                if ( ! $res ) {
                                    $this->admin_notice_add('error', __('Error: Thumbnail update failed.'));
                                } else {
                                    $this->admin_notice_add('success', __('Success: Thumbnail updated.'));
                                }
                            }
                        }
                        $this->admin_notices_set();
                        add_action(current_action(), array( $this, __FUNCTION__ ), 20, 3);
                    }
                }
            }
        }

        public function admin_notices() {
            global $current_screen;
            if ( empty($current_screen) ) {
                return;
            }
            if ( ! is_object($current_screen) ) {
                return;
            }
            if ( $current_screen->base === 'post' && $current_screen->parent_base === 'edit' ) {
                parent::admin_notices();
            }
        }

        /* public */

        public function init() {
            foreach ( $this->shortcodes as $shortcode => $shortcode_prefix ) {
                $this->add_shortcode_wpautop_control($shortcode, 'the_content');
            }
        }

        public function the_content( $value = '' ) {
            $str = trim(strip_tags($value));
            if ( strpos($str, $this->plugin_description) === 0 ) {
                return '';
            }
            return $value;
        }

        /* shortcodes */

        public function shortcode( $atts = array(), $content = '', $shortcode = 'copy-content' ) {
            $content = $this->trim_excess_space(force_balance_tags($content));
            if ( ! in_the_loop() ) {
                return $content;
            }
            if ( ! is_singular() ) {
                return $content;
            }
            $atts = $this->get_shortcode_atts_context($shortcode, 'input', $atts);
            // only act if the contents are empty, i.e. probably has no closing tag.
            if ( $this->empty_notzero($content) ) {
                if ( $shortcode === $this->shortcode_copycontent ) {
                    if ( $tmp = $this->copycontent_get_content($atts) ) {
                        $content = $tmp;
                    }
                } elseif ( $shortcode === $this->shortcode_wpcopycontent ) {
                    if ( $tmp = $this->wpcopycontent_get_content($atts) ) {
                        if ( isset($tmp['loop_end']) ) {
                            $this->loop_end_add($tmp['content']);
                        } else {
                            $content = $tmp['content'];
                        }
                    }
                }
            }
            // wpautop.
            if ( $atts['wpautop'] ) {
                $content = wpautop($content);
            }
            return apply_filters('copycontent_shortcode', $content, $shortcode, $atts);
        }

        private function loop_end_add( $str = '' ) {
            if ( ! isset($this->loop_ends) ) {
                $this->loop_ends = array();
                add_action('loop_end', array( $this, 'loop_end' ), $this->get_filter_next_priority('loop_end', 20));
            }
            $this->loop_ends[] = $str;
        }

        public function loop_end( $wp_query ) {
            if ( ! is_main_query() ) {
                return;
            }
            if ( ! in_the_loop() ) {
                return;
            }
            if ( ! is_singular() ) {
                return;
            }
            if ( ! $wp_query->in_the_loop ) {
                return;
            }
            if ( ! $wp_query->is_singular ) {
                return;
            }
            if ( isset($this->loop_ends) ) {
                if ( ! empty($this->loop_ends) ) {
                    foreach ( $this->loop_ends as $value ) {
                        echo apply_filters('copycontent_shortcode', $value, $this->shortcode_wpcopycontent);
                    }
                    $this->loop_ends = array();
                }
            }
        }

        /* functions - copy-content */

        private function copycontent_get_content( $atts = array(), $post_id = 0 ) {
            if ( ! isset($atts['url']) ) {
                return false;
            }
            if ( empty($atts['url']) ) {
                return false;
            }
            $str = '';
            $force_refresh = isset($atts['force_refresh']) ? $atts['force_refresh'] : false;
            // get_transient.
            if ( ! $force_refresh ) {
                if ( $tmp = $this->get_transient_from_url($atts['url']) ) {
                    $str = $tmp;
                } else {
                    $force_refresh = true;
                }
            }
            // file_get_contents_extended.
            if ( $force_refresh ) {
                if ( $tmp = $this->file_get_contents_extended($atts['url']) ) {
                    // set_transient.
                    if ( $tmp = $this->set_transient_filtered(null, $tmp, $atts['url'], $atts) ) {
                        $str = $tmp;
                    }
                }
            }

            // sorry, nothing worked.
            if ( $this->empty_notzero($str) ) {
                return false;
            }

            // strip comments?
            $keep_comments = false;
            if ( ! empty($atts['include']) ) {
                foreach ( $atts['include'] as $value ) {
                    if ( strpos($value, '/comment') !== false ) {
                        $keep_comments = true;
                        break;
                    }
                }
            }
            if ( ! $keep_comments ) {
                $str = preg_replace('/[\s]*<!--.+?-->[\s]*/is', '', $str);
            }

            // no html or files.
            if ( strpos($str, '<') === false && strpos($str, 'http') === false ) {
                return $str;
            }

            $has_dom = class_exists('DOMXPath');

            // include.
            if ( ! empty($atts['include']) && $has_dom ) {
                $dom = $this->loadHTML($str);
                $xpath = new DOMXPath($dom);
                $keep = array();
                foreach ( $atts['include'] as $value ) {
                    $xpath_q = $this->selector_to_xpath($value);
                    $tags = $xpath->query('//' . $xpath_q);
                    if ( $tags->length === 0 ) {
                        continue;
                    }
                    foreach ( $tags as $tag ) {
                        if ( $tag->tagName ) {
                            // node.
                            if ( $tag->tagName === $this->domwrapper ) {
                                continue;
                            }
                            $keep[] = $tag->ownerDocument->saveXML($tag);
                        } elseif ( $tag->nodeName === '#comment' && ! empty($tag->nodeValue) ) {
                            // comment.
                            $keep[] = $tag->nodeValue;
                        }
                    }
                }
                if ( ! empty($keep) ) {
                    $str = implode("\n", $keep);
                }
            }

            // exclude.
            if ( ! empty($atts['exclude']) && $has_dom ) {
                $dom = $this->loadHTML($str);
                $xpath = new DOMXPath($dom);
                $remove = array();
                foreach ( $atts['exclude'] as $value ) {
                    $xpath_q = $this->selector_to_xpath($value);
                    $tags = $xpath->query('//' . $xpath_q);
                    if ( $tags->length === 0 ) {
                        continue;
                    }
                    foreach ( $tags as $tag ) {
                        // only nodes.
                        if ( ! $tag->tagName ) {
                            continue;
                        }
                        if ( $tag->tagName === $this->domwrapper ) {
                            continue;
                        }
                        $remove[ $value ] = $tag;
                    }
                }
                if ( ! empty($remove) ) {
                    foreach ( $remove as $value ) {
                        $value->parentNode->removeChild($value);
                    }
                    $str = $this->saveHTML($dom);
                }
            }

            // tags - handle extra atts as tag includes/excludes.
            $tags = array_diff_key($atts, $this->get_shortcode_atts_context($this->shortcode_copycontent, 'default'));
            if ( ! empty($tags) ) {
                // sort into arrays.
                $arr = array(
                    'keep' => array(),
                    'remove' => array(),
                    'attr' => array(),
                );
                foreach ( $tags as $key => $value ) {
                    if ( is_numeric($value) || is_bool($value) || $value === 'true' || $value === 'false' ) {
                        if ( $this->is_true($value) ) {
                            $arr['keep'][] = $key;
                        } else {
                            $arr['remove'][] = $key;
                        }
                    } elseif ( $value === 'all' || $value === '*' ) {
                        $arr['keep'][] = $key;
                    } elseif ( is_numeric($key) && is_string($value) ) {
                        // arguments with no attributes will be given numeric keys.
                        if ( ! array_key_exists($value, $arr['attr']) ) {
                            $arr['attr'][ $value ] = array();
                        }
                    } elseif ( is_numeric($key) ) {
                        continue;
                    } else {
                        if ( ! array_key_exists($key, $arr['attr']) ) {
                            $arr['attr'][ $key ] = $this->make_array($value);
                        }
                    }
                }
                // remove tags - keep content.
                if ( ! empty($arr['remove']) ) {
                    $arr['remove'] = array_unique($arr['remove']);
                    foreach ( $arr['remove'] as $tag ) {
                        if ( in_array($tag, $arr['keep'], true) ) {
                            continue;
                        }
                        if ( array_key_exists($tag, $arr['attr']) ) {
                            continue;
                        }
                        $str = preg_replace("/(<$tag [^>]*>|<$tag>|<\/[ ]*$tag>)/is", '', $str);
                    }
                }
                // remove attributes - use dom.
                if ( ! empty($arr['attr']) && $has_dom ) {
                    $dom = $this->loadHTML($str);
                    $xpath = new DOMXPath($dom);
                    $changed = false;
                    foreach ( $arr['attr'] as $key => $value ) { // $key is tag here.
                        $xpath_q = $this->selector_to_xpath($key);
                        $tags = $xpath->query('//' . $xpath_q);
                        if ( $tags->length === 0 ) {
                            continue;
                        }
                        foreach ( $tags as $tag ) {
                            // only nodes.
                            if ( ! $tag->tagName ) {
                                continue;
                            }
                            if ( $tag->tagName === $this->domwrapper ) {
                                continue;
                            }
                            $remove_attr = array();
                            for ( $i = 0; $i < $tag->attributes->length; $i++ ) {
                                $my_attr = $tag->attributes->item($i)->name;
                                if ( ! in_array($my_attr, $value, true) ) {
                                    $remove_attr[] = $my_attr;
                                }
                            }
                            if ( ! empty($remove_attr) ) {
                                foreach ( $remove_attr as $my_attr ) {
                                    $tag->removeAttribute($my_attr);
                                }
                                $changed = true;
                            }
                        }
                    }
                    if ( $changed ) {
                        $str = $this->saveHTML($dom);
                    }
                }
            }

            // file_handling.
            $str = $this->file_handling_content($str, $this->shortcode_copycontent, $post_id);

            $str = $this->trim_excess_space($str);
            if ( $this->empty_notzero($str) ) {
                return false;
            }
            return $str;
        }

        /* functions - wp-copy-content */

        public function wpcopycontent_query_args( $atts = array(), $post_id = 0, $switch_blog = false ) {
            $query_args = array();
            // data formatting.
            $func_make_query_args = function ( $arr ) {
                if ( empty($arr) ) {
                    return false;
                }
                $res = array();
                foreach ( $arr as $v ) {
                    $pos = strpos($v, '=');
                    if ( $pos !== false && $pos >= 1 ) {
                        $res = array_merge($res, wp_parse_args($v));
                    }
                }
                if ( empty($res) ) {
                    return false;
                }
                return $res;
            };
            if ( $tmp = $func_make_query_args($atts['query']) ) {
                $query_args = $tmp;
            }
            if ( $tmp = $func_make_query_args($atts['date_query']) ) {
                $query_args['date_query'] = array( $tmp );
            }
            if ( $tmp = $func_make_query_args($atts['meta_query']) ) {
                $query_args['meta_query'] = array( $tmp );
            }
            if ( $tmp = $func_make_query_args($atts['tax_query']) ) {
                $query_args['tax_query'] = array( $tmp );
            }
            // handle extra atts as query variables - https://developer.wordpress.org/reference/classes/wp_query/
            $args = array_diff_key($atts, $this->get_shortcode_atts_context($this->shortcode_wpcopycontent, 'default'));
            if ( ! empty($args) ) {
                $query_args = array_merge($query_args, $args);
            }
            unset($args);
            // more data formatting.
            $query_args = $this->check_wp_query_args($query_args);
            // add extra query variables.
            $fields_extra = array(
                // singular.
                'p' => array(
                    'post_type' => 'any',
                    'post_status' => 'any',
                ),
                'name' => array(
                    'post_type' => 'any',
                    'post_status' => 'any',
                ),
                'page_id' => array(
                    'post_type' => 'any',
                    'post_status' => 'any',
                ),
                'pagename' => array(
                    'post_type' => 'any',
                    'post_status' => 'any',
                ),
                // loops.
                'post_parent' => array(
                    'post_type' => 'any',
                    'post_status' => array( 'publish', 'inherit' ),
                ),
                'post_parent__in' => array(
                    'post_type' => 'any',
                    'post_status' => array( 'publish', 'inherit' ),
                ),
                'post__in' => array(
                    'post_type' => 'any',
                    'post_status' => array( 'publish', 'inherit' ),
                ),
                'post_name__in' => array(
                    'post_type' => 'any',
                    'post_status' => array( 'publish', 'inherit' ),
                ),
                'post_type' => array(
                    'post_status' => array( 'publish', 'inherit' ),
                ),
            );
            foreach ( $query_args as $key => $value ) {
                if ( isset($fields_extra[ $key ]) ) {
                    foreach ( $fields_extra[ $key ] as $k => $v ) {
                        if ( ! array_key_exists($k, $query_args) ) {
                            $query_args[ $k ] = $v;
                        }
                    }
                }
            }
            // try to not query the current post for this blog (still might show up in the loop).
            if ( ! $switch_blog && ! empty($post_id) ) {
                foreach ( array( 'p', 'page_id', 'post__in' ) as $value ) {
                    if ( isset($query_args[ $value ]) ) {
                        if ( is_array($query_args[ $value ]) ) {
                            $key = array_search($post_id, $query_args[ $value ], true);
                            if ( $key !== false ) {
                                $query_args[ $value ][ $key ] = 0;
                            }
                        } elseif ( (int) $query_args[ $value ] === (int) $post_id ) {
                            unset($query_args[ $value ]);
                        }
                    }
                }
                if ( ! isset($query_args['post__not_in']) ) {
                    $query_args['post__not_in'] = array();
                }
                $query_args['post__not_in'] = array_merge($query_args['post__not_in'], array( $post_id ));
            }
            // pagination.
            if ( $atts['pagination'] ) {
                if ( is_paged() ) {
                    global $paged, $page;
                    $query_args['paged'] = ! empty($paged) ? $paged : $page;
                }
            } else {
                $is_paged = false;
                $fields_paged = array( 'nopaging', 'posts_per_page', 'posts_per_archive_page', 'offset', 'paged', 'page' );
                foreach ( $fields_paged as $value ) {
                    if ( isset($query_args[ $value ]) ) {
                        $is_paged = true;
                        break;
                    }
                }
                if ( ! $is_paged ) {
                    $query_args['nopaging'] = true;
                    $query_args['posts_per_page'] = -1;
                    $query_args['ignore_sticky_posts'] = true;
                }
            }

            return apply_filters('wpcopycontent_query', $query_args, $atts);
        }

        public function wpcopycontent_get_content( $atts = array(), $post_id = 0 ) {
            // does blog exist?
            $switch_blog = false;
            if ( is_multisite() && isset($atts['blog_id']) ) {
                if ( get_current_blog_id() !== $atts['blog_id'] ) {
                    $count = get_sites(array( 'count' => true, 'site__in' => array( $atts['blog_id'] ) ));
                    if ( $count === 0 ) {
                        return false;
                    }
                    $switch_blog = true;
                }
            }

            // query_args.
            $query_args = $this->wpcopycontent_query_args($atts, $post_id, $switch_blog);
            // sorry, nothing worked.
            if ( empty($query_args) ) {
                return false;
            }

            if ( $switch_blog ) {
                switch_to_blog($atts['blog_id']);
                $query_args = $this->check_wp_query_args($query_args);
            }

            $res = array( 'content' => '' );

            // query.
            // is it a single or loop query? single we can copy into shortcode. loop query just add under content.
            $query = new WP_Query($query_args);
            if ( $query->have_posts() ) {
                // extra security in avoiding our loop_end action.
                $query->in_the_loop = false;
                if ( $query->is_singular() ) {
                    // single.
                    $query->the_post();
                    global $post;
                    if ( $switch_blog || ( ! $switch_blog && $post->ID !== $post_id ) ) {
                        $res['content'] = $post->post_content;
                        // excerpt.
                        if ( $atts['update_excerpt'] && ! $this->empty_notzero($post->post_excerpt) ) {
                            $res['excerpt'] = $post->post_excerpt;
                        }
                        // thumbnail.
                        if ( $atts['update_thumbnail'] ) {
                            if ( $id = get_post_thumbnail_id($post) ) {
                                $res['thumbnail'] = $switch_blog ? wp_get_attachment_url($id) : $id;
                            }
                        }
                    }
                    wp_reset_postdata();
                } else {
                    // loop.
                    wp_reset_postdata();
                    $posts = query_posts($query_args);
                    if ( ! empty($posts) ) {
                        ob_start();
                        // Start the loop.
                        while ( have_posts() ) {
                            the_post();
                            global $post;
                            if ( $switch_blog || ( ! $switch_blog && $post->ID !== $post_id ) ) {
                                $template = apply_filters('wpcopycontent_template', false, $post, $atts);
                                if ( empty($template) ) {
                                    $template = $this->get_template();
                                }
                                if ( empty($template) ) {
                                    $template = locate_template(array( 'index.php' ), false);
                                }
                                if ( ! empty($template) && file_exists($template) ) {
                                    load_template($template, false);
                                }
                                // excerpt.
                                if ( $atts['update_excerpt'] && ! isset($res['excerpt']) && ! $this->empty_notzero($post->post_excerpt) ) {
                                    $res['excerpt'] = $post->post_excerpt;
                                }
                                // thumbnail.
                                if ( $atts['update_thumbnail'] && ! isset($res['thumbnail']) ) {
                                    if ( $id = get_post_thumbnail_id($post) ) {
                                        $res['thumbnail'] = $switch_blog ? wp_get_attachment_url($id) : $id;
                                    }
                                }
                            }
                        }
                        // End the loop.
                        if ( $atts['pagination'] ) {
                            if ( $switch_blog ) {
                                restore_current_blog();
                            }
                            // Previous/next page navigation.
                            $args = array(
                                'prev_text'          => __('Previous'),
                                'next_text'          => __('Next'),
                                'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page') . '</span>',
                            );
                            the_posts_pagination( apply_filters('wpcopycontent_pagination_args', $args, $atts) );
                        }
                        $res['content'] = ob_get_clean();
                        $res['loop_end'] = true;
                    }
                    wp_reset_query();
                }
            } else {
                wp_reset_postdata();
            }

            if ( $switch_blog ) {
                restore_current_blog();
            }

            $res['content'] = apply_filters('wpcopycontent_content_filters_active', $res['content'], null, $atts);

            // sorry, nothing worked.
            if ( $this->empty_notzero($res['content']) ) {
                return false;
            }

            // strip comments?
            $keep_comments = false;
            if ( ! empty($atts['include']) ) {
                foreach ( $atts['include'] as $value ) {
                    if ( strpos($value, '/comment') !== false ) {
                        $keep_comments = true;
                        break;
                    }
                }
            }
            if ( ! $keep_comments ) {
                $res['content'] = preg_replace('/[\s]*<!--.+?-->[\s]*/is', '', $res['content']);
            }

            // no html or files.
            if ( strpos($res['content'], '<') === false && strpos($res['content'], 'http') === false ) {
                return $res;
            }

            $has_dom = class_exists('DOMXPath');

            // include.
            if ( ! empty($atts['include']) && $has_dom ) {
                $dom = $this->loadHTML($res['content']);
                $xpath = new DOMXPath($dom);
                $keep = array();
                foreach ( $atts['include'] as $value ) {
                    $xpath_q = $this->selector_to_xpath($value);
                    $tags = $xpath->query('//' . $xpath_q);
                    if ( $tags->length === 0 ) {
                        continue;
                    }
                    foreach ( $tags as $tag ) {
                        if ( $tag->tagName ) {
                            // node.
                            if ( $tag->tagName === $this->domwrapper ) {
                                continue;
                            }
                            $keep[] = $tag->ownerDocument->saveXML($tag);
                        } elseif ( $tag->nodeName === '#comment' && ! empty($tag->nodeValue) ) {
                            // comment.
                            $keep[] = $tag->nodeValue;
                        }
                    }
                }
                if ( ! empty($keep) ) {
                    $res['content'] = implode("\n", $keep);
                }
            }

            // exclude.
            if ( ! empty($atts['exclude']) && $has_dom ) {
                $dom = $this->loadHTML($res['content']);
                $xpath = new DOMXPath($dom);
                $remove = array();
                foreach ( $atts['exclude'] as $value ) {
                    $xpath_q = $this->selector_to_xpath($value);
                    $tags = $xpath->query('//' . $xpath_q);
                    if ( $tags->length === 0 ) {
                        continue;
                    }
                    foreach ( $tags as $tag ) {
                        // only nodes.
                        if ( ! $tag->tagName ) {
                            continue;
                        }
                        if ( $tag->tagName === $this->domwrapper ) {
                            continue;
                        }
                        $remove[ $value ] = $tag;
                    }
                }
                if ( ! empty($remove) ) {
                    foreach ( $remove as $value ) {
                        $value->parentNode->removeChild($value);
                    }
                    $res['content'] = $this->saveHTML($dom);
                }
            }

            // file_handling.
            $res['content'] = $this->file_handling_content($res['content'], $this->shortcode_wpcopycontent, $post_id);

            $res['content'] = $this->trim_excess_space($res['content']);
            if ( $this->empty_notzero($res['content']) ) {
                return false;
            }
            return $res;
        }

        /* functions - html string parsing */

        private function loadHTML( $str ) {
            $dom = @DOMDocument::loadHTML('<' . $this->domwrapper . '>' . mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8') . '</' . $this->domwrapper . '>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
            return $dom;
        }

        private function saveHTML( $dom ) {
            $str = $dom->saveHTML();
            $str = preg_replace("/<[\/]?" . $this->domwrapper . "[^>]*>/is", '', $str);
            // wp adds single space before closer, so we should match it.
            if ( preg_match_all("/(<(" . implode('|', $this->get_void_tags()) . ") [^>]+)>/is", $str, $matches) ) {
                if ( ! empty($matches[0]) ) {
                    $replace = array();
                    foreach ( $matches[0] as $key => $value ) {
                        $replace[ $value ] = rtrim($matches[1][ $key ], '/ ') . ' />';
                    }
                    $str = str_replace(array_keys($replace), $replace, $str);
                }
            }
            return $str;
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

        private function selector_to_xpath( $value ) {
            // e.g. div#myID/comment, span.myClass, #myID2
            $comment = false;
            if ( strpos($value, '/comment') !== false ) {
                $comment = true;
                $value = str_replace('/comment', '', $value);
            }
            if ( strpos($value, '#') !== false ) {
                $arr = explode('#', $value);
                $arr = array_filter($arr);
                if ( count($arr) === 2 ) {
                    $value = $arr[0] . "[@id='" . $arr[1] . "']";
                } elseif ( ! empty($arr) ) {
                    $key = key($arr);
                    $value = "*[@id='" . $arr[ $key ] . "']";
                }
            } elseif ( strpos($value, '.') !== false ) {
                $arr = explode('.', $value);
                $arr = array_filter($arr);
                if ( count($arr) === 2 ) {
                    $value = $arr[0] . "[contains(@class,'" . $arr[1] . "')]";
                } elseif ( ! empty($arr) ) {
                    $key = key($arr);
                    $value = "*[contains(@class,'" . $arr[ $key ] . "')]";
                }
            }
            if ( $comment ) {
                // this must be in single quotes! TODO: iconv
                $value = $value . '/comment()';
            }
            return $value;
        }

        public function get_field_from_html( $field = 'title', $content = '', $url = '' ) {
            if ( empty($content) && ! empty($url) ) {
                $content = $this->get_transient_from_url($url);
            }
            if ( empty($content) ) {
                return false;
            }
            $res = false;
            switch ( $field ) {
                case 'title':
                default:
                    if ( preg_match('/<title[^>]*>([^<]+)<\/title>/is', $content, $matches) ) {
                        $res = $matches[1];
                    } else {
                        $search = array(
                            '<meta property="og:title" ([^>]+)>' => 'content="([^"]+)"',
                            '<meta property="twitter:title" ([^>]+)>' => 'content="([^"]+)"',
                            '<meta name="twitter:title" ([^>]+)>' => 'content="([^"]+)"',
                        );
                        foreach ( $search as $key => $value ) {
                            preg_match_all("/$key/is", $content, $matches);
                            if ( ! $matches ) {
                                continue;
                            }
                            if ( empty($matches[1]) ) {
                                continue;
                            }
                            preg_match_all("/$value/is", $matches[1][0], $matches);
                            if ( ! $matches ) {
                                continue;
                            }
                            if ( empty($matches[1]) ) {
                                continue;
                            }
                            $res = trim($matches[1][0]);
                            break;
                        }
                    }
                    break;

                case 'excerpt':
                    $search = array(
                        '<meta name="description" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta property="og:description" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta property="twitter:description" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta property="og:title" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta property="twitter:title" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta name="twitter:title" ([^>]+)>' => 'content="([^"]+)"',
                    );
                    foreach ( $search as $key => $value ) {
                        preg_match_all("/$key/is", $content, $matches);
                        if ( ! $matches ) {
                            continue;
                        }
                        if ( empty($matches[1]) ) {
                            continue;
                        }
                        preg_match_all("/$value/is", $matches[1][0], $matches);
                        if ( ! $matches ) {
                            continue;
                        }
                        if ( empty($matches[1]) ) {
                            continue;
                        }
                        $res = trim($matches[1][0]);
                        break;
                    }
                    break;

                case 'thumbnail':
                    $search = array(
                        '<link rel="image_src" ([^>]+)>' => 'href="([^"]+)"',
                        '<meta property="og:image" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta property="twitter:image" ([^>]+)>' => 'content="([^"]+)"',
                        '<meta name="twitter:image" ([^>]+)>' => 'content="([^"]+)"',
                    );
                    foreach ( $search as $key => $value ) {
                        preg_match_all("/$key/is", $content, $matches);
                        if ( ! $matches ) {
                            continue;
                        }
                        if ( empty($matches[1]) ) {
                            continue;
                        }
                        preg_match_all("/$value/is", $matches[1][0], $matches);
                        if ( ! $matches ) {
                            continue;
                        }
                        if ( empty($matches[1]) ) {
                            continue;
                        }
                        if ( $file = $this->is_valid_file($matches[1][0], 'image') ) {
                            $res = trim($file);
                            break;
                        }
                    }
                    break;
            }
            return $res;
        }

        public function get_attributes_for_urls() {
            $links = array(
                // https://stackoverflow.com/questions/2725156/complete-list-of-html-tag-attributes-which-have-a-url-value
                'action',
                'background',
                'cite',
                'classid',
                'codebase',
                'data',
                'href',
                'longdesc',
                'src',
                'usemap',
            );
            return $links;
        }

        /* functions - transients */

        public function get_transient_name_hash( $url = '' ) {
            return static::$prefix . '_' . hash('adler32', untrailingslashit(set_url_scheme($url, 'http')));
        }

        public function get_transient_from_url( $url = '' ) {
            return $this->get_transient($this->get_transient_name_hash($url));
        }

        public function set_transient_filtered( $transient = '', $content = '', $url = '', $atts = array() ) {
            if ( empty($transient) && ! empty($url) ) {
                $transient = $this->get_transient_name_hash($url);
            }
            if ( empty($transient) ) {
                return false;
            }
            // remove html we don't need.
            $content = apply_filters('copycontent_content_filters_active', $content, $url, $atts);
            if ( $this->set_transient($transient, $content) ) {
                return $content;
            }
            // some html causes set_transient to break. remove html tags until it works.
            $tags = array(
                'dom-module',
                'template',
                'iron-iconset-svg',
                'svg',
                'path',
                'style',
                'script',
            );
            foreach ( $tags as $tag ) {
                $content = $this->strip_single_tag($content, $tag);
                // try again.
                $content = $this->trim_excess_space($content);
                if ( $this->set_transient($transient, $content) ) {
                    return $content;
                }
            }
            return false;
        }

        public function get_urls_from_content( $content = '' ) {
            $res = false;
            if ( has_shortcode($content, $this->shortcode_copycontent) ) {
                if ( preg_match_all("/ url=[\"']*([^\"'\s\]]+)/is", $content, $matches) ) {
                    $res = array();
                    foreach ( $matches[1] as $url ) {
                        $res[] = $this->trim_quotes($url);
                    }
                    $res = array_unique($res);
                }
            }
            return $res;
        }

        /* functions - file handling */

        private function file_handling_content( $content = '', $shortcode = 'copy-content', $post_id = 0 ) {
            // get urls and filter them.
            $urls = wp_extract_urls(wp_specialchars_decode($content));
            $my_host = wp_parse_url(home_url(), PHP_URL_HOST);
            foreach ( $urls as $k => $v ) {
                if ( substr($v, -1) === '/' ) {
                    unset($urls[ $k ]);
                    continue;
                }
                if ( strpos(basename($v), '.') === false ) {
                    unset($urls[ $k ]);
                    continue;
                }
                if ( $my_host === wp_parse_url($v, PHP_URL_HOST) ) {
                    unset($urls[ $k ]);
                    continue;
                }
            }
            if ( empty($urls) ) {
                return $content;
            }

            // get options.
            $options_file_handling = $this->get_options_context('db', $this->shortcodes[ $shortcode ] . '_file_handling');
            // are we keeping everything?
            $keep_all = true;
            foreach ( $options_file_handling as $file_type => $action ) {
                if ( $action !== 'keep' ) {
                    $keep_all = false;
                    break;
                }
            }
            if ( $keep_all ) {
                return $content;
            }
            $options_file_exists = $this->get_options_context('db', $this->shortcodes[ $shortcode ] . '_file_exists');

            // apply handling rules for each file type, collect 'replacements'.
            $replacements = array();
            $urls = array_unique($urls);
            foreach ( $urls as $url ) {
                // find file type.
                $my_file_type = false;
                foreach ( $options_file_handling as $file_type => $action ) {
                    if ( $this->is_valid_file($url, $file_type) ) {
                        $my_file_type = $file_type;
                        break;
                    }
                }
                if ( ! $my_file_type ) {
                    continue;
                }
                // keep.
                switch ( $options_file_handling[ $my_file_type ] ) {
                    case 'keep':
                    default:
                        break;

                    case 'remove':
                        $replacements[ $url ] = '';
                        break;

                    case 'download':
                        $attachment_id = $this->get_existing_attachment_id($url, $post_id);
                        // new.
                        if ( ! $attachment_id ) {
                            if ( $id = $this->media_upload_from_url($url, $post_id) ) {
                                $replacements[ $url ] = wp_get_attachment_url($id);
                            }
                        }
                        // exists.
                        if ( $attachment_id && isset($options_file_exists[ $my_file_type ]) ) {
                            switch ( $options_file_exists[ $my_file_type ] ) {
                                case 'discard':
                                default:
                                    $replacements[ $url ] = wp_get_attachment_url($attachment_id);
                                    break;

                                case 'replace':
                                    if ( $id = $this->media_upload_from_url($url, $post_id) ) {
                                        $replacements[ $url ] = wp_get_attachment_url($id);
                                        $this->delete_attachment($attachment_id);
                                    } else {
                                        $replacements[ $url ] = wp_get_attachment_url($attachment_id);
                                    }
                                    break;

                                case 'new':
                                    if ( $id = $this->media_upload_from_url($url, $post_id) ) {
                                        $replacements[ $url ] = wp_get_attachment_url($id);
                                    } else {
                                        $replacements[ $url ] = wp_get_attachment_url($attachment_id);
                                    }
                                    break;
                            }
                        }
                        break;
                }
            }
            if ( ! empty($replacements) ) {
                $content = str_replace(array_keys($replacements), $replacements, $content);
            }
            return $content;
        }

        private function get_existing_attachment_id( $url, $post_id = 0 ) {
            $res = false;
            // file already uploaded?
            $filename = sanitize_file_name(pathinfo($url, PATHINFO_FILENAME));
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
                    'relation' => 'OR',
                    array(
                        'key' => '_wp_attached_file',
                        'compare' => 'LIKE',
                        'value' => $filename,
                    ),
                    array(
                        'key' => '_wp_attachment_metadata',
                        'compare' => 'LIKE',
                        'value' => $filename,
                    ),
                ),
            );
            if ( ! empty($post_id) ) {
                // 1. check media attached to this post.
                $posts_tmp = get_posts(array_merge($args, array( 'post_parent' => (int) $post_id )));
                if ( ! empty($posts_tmp) && ! is_wp_error($posts_tmp) ) {
                    $posts = $posts_tmp;
                }
                // 2. search all other media.
                $posts_tmp = get_posts(array_merge($args, array( 'post_parent__not_in' => array( (int) $post_id ) )));
                if ( ! empty($posts_tmp) && ! is_wp_error($posts_tmp) ) {
                    $posts = array_merge($posts, $posts_tmp);
                }
            } else {
                $posts = get_posts($args);
            }
            if ( ! empty($posts) && ! is_wp_error($posts) ) {
                foreach ( $posts as $post ) {
                    if ( $arr = wp_get_attachment_metadata($post->ID) ) {
                        $tmp = isset($arr['file']) ? $arr['file'] : wp_get_attachment_url($post->ID);
                        if ( strpos($tmp, $filename) === 0 || strpos($tmp, '/' . $filename) !== false ) {
                            $res = $post->ID;
                            break;
                        }
                    }
                }
            }
            return $res;
        }

        private function media_upload_from_url( $url = '', $post_id = 0 ) {
            if ( $this->is_front_end() ) {
                return false;
            }
            if ( ! current_user_can('upload_files') ) {
                return false;
            }
            if ( ! $this->download_functions_loaded() ) {
                return false;
            }
            $tmp = download_url($url);
            if ( is_wp_error($tmp) ) {
                return false;
            }
            $file_array = array(
                'name' => basename($url),
                'tmp_name' => $tmp,
            );
            $post_data = array(
                'post_author' => get_current_user_id(),
                'post_content' => $this->plugin_description,
            );
            $id = media_handle_sideload($file_array, (int) $post_id, basename($url), $post_data);
            if ( is_wp_error($id) ) {
                @unlink($tmp);
                return false;
            }
            return (int) $id;
        }

        private function delete_attachment( $id ) {
            if ( ! is_attachment($id) ) {
                return false;
            }
            if ( ! current_user_can('edit_files') ) {
                return false;
            }
            $meta = $this->make_array(wp_get_attachment_metadata($id));
            $backup_sizes = $this->make_array(get_post_meta($id, '_wp_attachment_backup_sizes', true));
            $file = str_replace('//', '/', get_attached_file($id));
            $res = wp_delete_attachment_files($id, $meta, $backup_sizes, $file);
            return $res;
        }

        private function download_functions_loaded() {
            if ( ! function_exists('download_url') && is_readable(ABSPATH . 'wp-admin/includes/file.php') ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists('media_handle_sideload') && is_readable(ABSPATH . 'wp-admin/includes/media.php') ) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            if ( ! function_exists('wp_read_image_metadata') && is_readable(ABSPATH . 'wp-admin/includes/image.php') ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            if ( function_exists('download_url') && function_exists('media_handle_sideload') && function_exists('wp_read_image_metadata') ) {
                return true;
            }
            return false;
        }

        private function get_file_handling_options() {
            return array(
                'keep' => __('Keep original link'),
                'remove' => __('Remove from content'),
                'download' => __('Download file'),
            );
        }

        private function get_file_exists_options() {
            return array(
                'discard' => __('Discard the new file and keep the old file'),
                'replace' => __('Replace the old file with the new file'),
                'new' => __('Download the new file and keep the old file'),
            );
        }

        public function get_file_types() {
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types
            $file_types = array(
                'image' => array(
                    'label' => __('Images'),
                    'mime_type' => 'image/%',
                    'extensions' => array( 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'svg', 'bmp', 'ico', 'gif', 'webp' ),
                ),
                'audio' => array(
                    'label' => __('Audio'),
                    'mime_type' => 'audio/%',
                    'extensions' => array( 'aac', 'wav', 'mp3', 'ogg', 'oga', 'flac', 'aif', 'mid', 'midi', 'opus', 'weba', 'wma', 'm4a' ),
                ),
                'video' => array(
                    'label' => __('Video'),
                    'mime_type' => 'video/%',
                    'extensions' => array( 'mp4', 'mov', 'avi', 'm4v', 'flv', 'swf', 'mpeg', 'ogv', 'ts', 'webm', 'mpg', '3gp', '3g2', 'wmv' ),
                ),
                'doc' => array(
                    'label' => __('Documents'),
                    'mime_type' => 'application/%',
                    'extensions' => array( 'doc', 'docx', 'odp', 'ods', 'odt', 'pdf', 'ppt', 'pptx', 'rtf', 'xls', 'xlsx', 'pps', 'ppsx', 'key' ),
                ),
                'css' => array(
                    'label' => __('Stylesheets'),
                    'mime_type' => 'text/css',
                    'extensions' => array( 'css', 'scss', 'sass', 'less' ),
                ),
                'javascript' => array(
                    'label' => __('Javascript'),
                    'mime_type' => 'text/javascript',
                    'extensions' => array( 'js', 'mjs' ),
                ),
                'zip' => array(
                    'label' => __('Archives'),
                    'mime_type' => 'application/%',
                    'extensions' => array( 'bz', 'bz2', 'gz', 'rar', 'tar', 'zip', 'sit', '7z' ),
                ),
            );
            return apply_filters('copycontent_file_types', $file_types);
        }

        public function is_valid_file( $filename = '', $file_type = 'image' ) {
            $res = $filename;
            // check against file types.
            $file_types = $this->get_file_types();
            if ( isset($file_types[ $file_type ]) ) {
                if ( ! in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $file_types[ $file_type ]['extensions'], true) ) {
                    $res = false;
                }
            }
            // image - special case.
            if ( $file_type === 'image' ) {
                if ( preg_match("/^(blank|spacer|pixel|[0-9]+)\.gif$/i", basename($filename)) ) {
                    $res = false;
                }
            }
            return apply_filters('copycontent_is_valid_file', $res, $filename, $file_type);
        }

        /* functions - options */

        protected function get_options_default() {
            $content_filters = array();
            if ( class_exists('Halftheory_Copy_Content_Filters', false) ) {
                $content_filters = Halftheory_Copy_Content_Filters::get_filters();
            }
            return apply_filters(static::$prefix . '_options_default',
                array(
                    'active' => false,
                    // copy-content.
                    'copycontent_update_excerpt' => true,
                    'copycontent_update_thumbnail' => false,
                    'copycontent_shortcode_defaults' => '',
                    'copycontent_content_filters' => $content_filters,
                    'copycontent_file_handling' => array(),
                    'copycontent_file_exists' => array(),
                    // wp-copy-content.
                    'wpcopycontent_update_excerpt' => true,
                    'wpcopycontent_update_thumbnail' => false,
                    'wpcopycontent_shortcode_defaults' => '',
                    'wpcopycontent_content_filters' => array(),
                    'wpcopycontent_file_handling' => array(),
                    'wpcopycontent_file_exists' => array(),
                )
            );
        }

        public function get_shortcode_atts_context( $shortcode = 'copy-content', $context = 'db', $input = array(), $key = null, $default = null ) {
            $atts_default = array();
            if ( $shortcode === $this->shortcode_copycontent ) {
                $atts_default = array(
                    'force_refresh' => false,
                    'update_excerpt' => $this->get_options_context('default', $this->shortcodes[ $shortcode ] . '_update_excerpt'),
                    'update_thumbnail' => $this->get_options_context('default', $this->shortcodes[ $shortcode ] . '_update_thumbnail'),
                    'include' => array( 'body' ),
                    'exclude' => array(),
                    'wpautop' => false,
                    'url' => '',
                );
            } elseif ( $shortcode === $this->shortcode_wpcopycontent ) {
                $atts_default = array(
                    'force_refresh' => false,
                    'update_excerpt' => $this->get_options_context('default', $this->shortcodes[ $shortcode ] . '_update_excerpt'),
                    'update_thumbnail' => $this->get_options_context('default', $this->shortcodes[ $shortcode ] . '_update_thumbnail'),
                    'include' => array(),
                    'exclude' => array(),
                    'wpautop' => true,
                    'pagination' => true,
                    'query' => array(),
                    'date_query' => array(),
                    'meta_query' => array(),
                    'tax_query' => array(),
                );
                if ( is_multisite() ) {
                    $atts_default['blog_id'] = get_current_blog_id();
                }
            }
            // default handling.
            if ( is_null($default) ) {
                if ( ! $this->empty_notzero($key) && array_key_exists($key, $atts_default) ) {
                    $default = $atts_default[ $key ];
                } elseif ( ! $this->empty_notzero($key) ) {
                    $default = false;
                } elseif ( $this->empty_notzero($key) ) {
                    $default = array();
                }
            }

            // data type checking.
            $func_data_check = function ( $res ) use ( $atts_default ) {
                if ( is_array($res) ) {
                    foreach ( $atts_default as $k => $v ) {
                        if ( array_key_exists($k, $res) ) {
                            if ( is_bool($v) && ! is_bool($res[ $k ]) ) {
                                $res[ $k ] = $this->is_true($res[ $k ]);
                            } elseif ( is_int($v) && ! is_int($res[ $k ]) ) {
                                $res[ $k ] = (int) $res[ $k ];
                            } elseif ( is_float($v) && ! is_float($res[ $k ]) ) {
                                $res[ $k ] = (float) $res[ $k ];
                            } elseif ( is_array($v) && ! is_array($res[ $k ]) ) {
                                $res[ $k ] = $this->make_array($res[ $k ]);
                            }
                        }
                    }
                }
                return $res;
            };

            $res = $default;
            switch ( $context ) {
                case 'db':
                default:
                    $res1 = array(
                        'update_excerpt' => $this->get_options_context('db', $this->shortcodes[ $shortcode ] . '_update_excerpt'),
                        'update_thumbnail' => $this->get_options_context('db', $this->shortcodes[ $shortcode ] . '_update_thumbnail'),
                    );
                    $res2 = $this->make_array(shortcode_parse_atts($this->get_options_context('db', $this->shortcodes[ $shortcode ] . '_shortcode_defaults')));
                    $res2 = $this->trim_quotes($res2);
                    $res = array_merge($atts_default, $res1, $res2);
                    $res = $func_data_check($res);
                    break;

                case 'default':
                    $res = $atts_default;
                    break;

                case 'input':
                    $res1 = $this->get_shortcode_atts_context($shortcode, 'db');
                    if ( empty($res1) ) {
                        $res1 = $atts_default;
                    }
                    if ( ! is_array($input) ) {
                        $input = $this->make_array(shortcode_parse_atts(trim($input)));
                    }
                    $input = $this->trim_quotes($input);
                    $res = array_merge($res1, $input);
                    $res = $func_data_check($res);
                    break;
            }

            if ( ! $this->empty_notzero($key) && is_array($res) ) {
                if ( array_key_exists($key, $res) ) {
                    return $res[ $key ];
                }
                return $default;
            }
            return $res;
        }

        public function make_content_filters_array( $input = 'wp_filter', $output = 'admin_form', $data = null ) {
            $input_db = array();
            // convert all input to db format.
            switch ( $input ) {
                case 'wp_filter':
                    global $wp_filter;
                    if ( isset($wp_filter['copycontent_content_filters']) && is_object($wp_filter['copycontent_content_filters']) ) {
                        if ( isset($wp_filter['copycontent_content_filters']->callbacks) && is_array($wp_filter['copycontent_content_filters']->callbacks) ) {
                            foreach ( $wp_filter['copycontent_content_filters']->callbacks as $priority => $value ) {
                                if ( ! is_array($value) ) {
                                    continue;
                                }
                                if ( ! is_array(current($value)) ) {
                                    continue;
                                }
                                $filter = current($value);
                                if ( ! isset($filter['function']) ) {
                                    continue;
                                }
                                $input_db[ $priority ] = $filter['function'];
                            }
                        }
                    }
                    break;

                case 'admin_form':
                    if ( ! empty($data) && is_array($data) ) {
                        foreach ( $data as $value ) {
                            if ( strpos($value, '::') === false ) {
                                continue;
                            }
                            $arr = array_filter(explode('::', $value));
                            if ( count($arr) < 2 ) {
                                continue;
                            }
                            $priority = $arr[0];
                            unset($arr[0]);
                            if ( count($arr) === 1 ) {
                                $filter = current($arr);
                            } else {
                                $filter = array_values($arr);
                            }
                            $input_db[ $priority ] = $filter;
                        }
                    }
                    break;

                case 'db':
                default:
                    if ( ! empty($data) && is_array($data) ) {
                        $input_db = $data;
                    }
                    break;
            }

            $res = array();
            // format the output.
            switch ( $output ) {
                case 'wp_filter':
                    foreach ( $input_db as $priority => $filter ) {
                        $key = implode('::', (array) $filter);
                        $res[ $priority ] = array(
                            $key => array(
                                'function' => $filter,
                                'accepted_args' => 3,
                            ),
                        );
                    }
                    break;

                case 'admin_form':
                    foreach ( $input_db as $priority => $filter ) {
                        $key = array_merge( (array) $priority, (array) $filter );
                        $key = implode('::', $key);
                        $res[ $key ] = implode(' :: ', (array) $filter) . ' (' . $priority . ')';
                    }
                    break;

                case 'db':
                default:
                    $res = $input_db;
                    break;
            }
            return $res;
        }

        /* functions-common */

        public function esc_textarea_substitute( $text ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($text);
            }
            // https://developer.wordpress.org/reference/functions/esc_textarea/
            // if flags is only 'ENT_QUOTES' strings with special characters like ascii art will return empty.
            $safe_text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, $this->get_option('blog_charset', null, 'UTF-8'));
            return apply_filters('esc_textarea', $safe_text, $text);
        }

        private function get_filter_next_priority( $tag, $priority_start = 10 ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($tag, $priority_start);
            }
            global $wp_filter;
            $i = $priority_start;
            if ( isset($wp_filter[ $tag ]) ) {
                while ( $wp_filter[ $tag ]->offsetExists($i) === true ) {
                    $i++;
                }
            }
            return $i;
        }

        private function file_get_contents_extended( $filename = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($filename);
            }
            if ( empty($filename) ) {
                return false;
            }
            $is_url = false;
            if ( strpos($filename, 'http') === 0 ) {
                if ( $this->url_exists($filename) === false ) {
                    return false;
                }
                $is_url = true;
            }
            $str = '';
            // use user_agent when available.
            $user_agent = 'PHP' . phpversion() . '/' . __FUNCTION__;
            if ( isset($_SERVER['HTTP_USER_AGENT']) ) {
                if ( ! empty($_SERVER['HTTP_USER_AGENT']) ) {
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                }
            }
            // try php.
            $options = array( 'http' => array( 'user_agent' => $user_agent ) );
            // try 'correct' way.
            if ( $str_php = @file_get_contents($filename, false, stream_context_create($options)) ) {
                $str = $str_php;
            }
            // try 'insecure' way.
            if ( empty($str) ) {
                $options['ssl'] = array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                );
                if ( $str_php = @file_get_contents($filename, false, stream_context_create($options)) ) {
                    $str = $str_php;
                }
            }
            // try curl.
            if ( empty($str) && $is_url) {
                if ( function_exists('curl_init') ) {
                    $c = @curl_init();
                    // try 'correct' way.
                    curl_setopt($c, CURLOPT_URL, $filename);
                    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($c, CURLOPT_MAXREDIRS, 10);
                    $str = curl_exec($c);
                    // try 'insecure' way.
                    if ( empty($str) ) {
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
            if ( empty($str) ) {
                return false;
            }
            return $str;
        }

        private function fix_potential_html_string( $str = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($str);
            }
            if ( empty($str) ) {
                return $str;
            }
            if ( strpos($str, '&lt;') !== false ) {
                if ( substr_count($str, '&lt;') > substr_count($str, '<') || preg_match("/&lt;\/[\w]+&gt;/is", $str) ) {
                    $str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
                }
            } elseif ( strpos($str, '&#039;') !== false ) {
                $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
            }
            return $str;
        }

        public function strip_single_tag( $str = '', $tag = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($str, $tag);
            }
            if ( empty($tag) ) {
                return $str;
            }
            if ( strpos($str, '<' . $tag) === false ) {
                return $str;
            }
            // has closing tag.
            $str = preg_replace("/[\s]*<$tag [^>]*>.*?<\/[ ]*$tag>[\s]*/is", '', $str);
            $str = preg_replace("/[\s]*<$tag>.*?<\/[ ]*$tag>[\s]*/is", '', $str);
            // no closing tag.
            $str = preg_replace("/[\s]*<$tag [^>]+>[\s]*/is", '', $str);
            $str = preg_replace("/[\s]*<" . $tag . "[ \/]*>[\s]*/is", '', $str);
            return $str;
        }

        public function trim_excess_space( $str = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($str);
            }
            if ( empty($str) ) {
                return $str;
            }
            $replace_with_space = array( '&nbsp;', '&#160;', "\xc2\xa0" );
            $str = str_replace($replace_with_space, ' ', $str);

            if ( strpos($str, '</') !== false ) {
                // no space before closing tags.
                $str = preg_replace('/[\s]*(<\/[^>]+>)/s', '$1', $str);
            }
            if ( strpos($str, '<br') !== false ) {
                // no br at start/end.
                $str = preg_replace('/^<br[\/ ]*>/is', '', $str);
                $str = preg_replace('/<br[\/ ]*>$/is', '', $str);
                // limit to max 2 brs.
                $str = preg_replace('/(<br[\/ ]*>[\s]*){3,}/is', '$1$1', $str);
                // no br directly next to p tags.
                $str = preg_replace('/(<p>|<p [^>]+>)[\s]*<br[\/ ]*>[\s]*/is', '$1', $str);
                $str = preg_replace('/[\s]*<br[\/ ]*>[\s]*(<\/p>)/is', '$1', $str);
            }

            $str = preg_replace("/[\t ]*(\n|\r)[\t ]*/s", '$1', $str);
            $str = preg_replace("/(\n|\r){3,}/s", "\n\n", $str);
            $str = preg_replace('/[ ]{2,}/s', ' ', $str);
            return trim($str);
        }

        public function trim_quotes( $str = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($str);
            }
            if ( is_string($str) ) {
                $str = trim($str, " \n\r\t\v\0'" . '"');
            } elseif ( is_array($str) ) {
                $str = array_map(array( $this, __FUNCTION__ ), $str);
            }
            return $str;
        }

        private function url_exists( $url = '' ) {
            if ( function_exists(__FUNCTION__) ) {
                $func = __FUNCTION__;
                return $func($url);
            }
            if ( empty($url) ) {
                return false;
            }
            $url_check = @get_headers($url);
            if ( ! is_array($url_check) || strpos($url_check[0], '404') !== false ) {
                return false;
            }
            return true;
        }
    }

	// Load the plugin.
    Halftheory_Copy_Content::get_instance(true, plugin_basename(__FILE__));
endif;
