<?php
if ( ! class_exists('WP_List_Table', false) && is_readable(ABSPATH . 'wp-admin/includes/class-wp-list-table.php') ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
if ( ! class_exists('Halftheory_Copy_Content_Table_Transients', false) ) :
	#[AllowDynamicProperties]
	final class Halftheory_Copy_Content_Table_Transients extends WP_List_Table {

		public $prefix = '';
		private $checked = false;
		private $items_from_posts = array();

		public function __construct( $prefix = '' ) {
			if ( ! empty($prefix) ) {
				$this->prefix = $prefix . '_';
			}
			parent::__construct(
				array(
					'ajax' => false,
				)
			);
		}

		protected function display_tablenav( $which ) {
		}

		protected function column_default( $item, $column_name ) {
			if ( isset($item[ $column_name ]) ) {
				return $item[ $column_name ];
			}
		}

		protected function get_table_classes() {
			return array( 'widefat', 'fixed', 'striped', $this->_args['plural'] );
		}

		public function get_columns() {
			$columns = array(
				'url' => _x('URL', 'column name'),
				'post' => _x('Post / Title', 'column name'),
				'textarea_excerpt' => _x('Excerpt', 'column name'),
				'cb_filter' => _x('Filter', 'column name'),
				'cb' => '<label class="screen-reader-text" for="cb-select-all-delete">' . __('Select All') . '</label> <input id="cb-select-all--delete" type="checkbox" /> ' . _x('Delete', 'column name'),
			);
			return $columns;
		}

		public function column_url( $item ) {
			if ( isset($this->items_from_posts[ $item ]) ) {
                $arr = wp_list_pluck($this->items_from_posts[ $item ], 'url');
                $arr = array_unique($arr);
				?>
				<input type="hidden" name="<?php echo esc_attr($this->prefix); ?>url_<?php echo esc_attr($item); ?>" id="<?php echo esc_attr($this->prefix); ?>url_<?php echo esc_attr($item); ?>" value="<?php echo esc_attr(current($arr)); ?>" />
				<?php
                $arr = array_map('make_clickable', $arr);
                echo wp_kses_post(implode('<br />', $arr));
			} else {
				?>
				<label for="cb-delete-<?php echo esc_attr($item); ?>"><?php echo esc_html('(Not found - ID: ' . str_replace($this->prefix, '', $item) . ')'); ?></label>
				<?php
			}
		}

		public function column_post( $item ) {
			if ( isset($this->items_from_posts[ $item ]) ) {
                $arr = wp_list_pluck($this->items_from_posts[ $item ], 'ID');
                $arr = array_unique($arr);
                $arr = array_map(
                    function ( $v ) {
						$title = _draft_or_post_title($v);
            			if ( current_user_can('edit_post', $v) ) {
                        	return '<strong><a href="' . esc_url(get_edit_post_link($v)) . '">' . $title . '</a></strong>';
                        } elseif ( current_user_can('read_post', $v) ) {
                        	return '<strong>' . $title . '</strong>';
                        } else {
                        	return __('(Private post)');
                        }
                    },
                    $arr
                );
                echo wp_kses_post(implode('<br />', $arr));
			} elseif ( class_exists('Halftheory_Copy_Content', false) ) {
				if ( $v = Halftheory_Copy_Content::get_instance()->get_transient($item) ) {
					if ( $t = Halftheory_Copy_Content::get_instance()->get_field_from_html('title', $v) ) {
						?>
						<label for="cb-delete-<?php echo esc_attr($item); ?>"><?php echo esc_html($t); ?></label>
						<?php
					}
				}
			}
		}

		public function column_textarea_excerpt( $item ) {
			if ( class_exists('Halftheory_Copy_Content', false) ) {
				if ( $v = Halftheory_Copy_Content::get_instance()->get_transient($item) ) {
					$v = Halftheory_Copy_Content::get_instance()->trim_excess_space($v);
					$v = str_replace(array( "\r", "\t" ), array( "\n", '' ), $v);
					$v = preg_replace("/[\n]+/s", "\n", $v);
                    $v = substr($v, 0, 200);
                    ?>
                    <textarea style="width: 100%; max-height: 5em;" readonly><?php echo Halftheory_Copy_Content::get_instance()->esc_textarea_substitute($v); ?>&hellip;</textarea>
                    <?php
				}
			}
		}

		public function column_cb_filter( $item ) {
			?>
			<label class="screen-reader-text" for="cb-filter-<?php echo esc_attr($item); ?>"><?php esc_html_e('Select:'); ?></label>
			<input type="checkbox" name="<?php echo esc_attr($this->prefix); ?>transients_filter[]" id="cb-filter-<?php echo esc_attr($item); ?>" value="<?php echo esc_attr($item); ?>"<?php checked($this->checked, true); ?> />
			<?php
		}

		protected function column_cb( $item ) {
			?>
			<label class="screen-reader-text" for="cb-delete-<?php echo esc_attr($item); ?>"><?php esc_html_e('Select:'); ?></label>
			<input type="checkbox" name="<?php echo esc_attr($this->prefix); ?>transients_delete[]" id="cb-delete-<?php echo esc_attr($item); ?>" value="<?php echo esc_attr($item); ?>"<?php checked($this->checked, true); ?> />
			<?php
		}

		public function prepare_items( $items = array(), $items_from_posts = array() ) {
			// headers.
			$columns = $this->get_columns();
			$hidden = array();
			$sortable = $this->get_sortable_columns();
			$this->_column_headers = array( $columns, $hidden, $sortable );
			// items.
			$this->items = $items;
			$this->items_from_posts = $items_from_posts;
		}

		public function print_column_headers( $with_id = true ) {
			list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

			$current_url = set_url_scheme( 'http://' . wp_unslash($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) );
			$current_url = remove_query_arg( 'paged', $current_url );

			$current_orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';

			if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
				$current_order = 'desc';
			} else {
				$current_order = 'asc';
			}

			// removed code for $columns['cb'] here!

			foreach ( $columns as $column_key => $column_display_name ) {
				$class = array( 'manage-column', "column-$column_key" );

				if ( in_array( $column_key, $hidden, true ) ) {
					$class[] = 'hidden';
				}

				if ( 'cb' === $column_key ) {
					$class[] = 'check-column';
				} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
					$class[] = 'num';
				}

				if ( $column_key === $primary ) {
					$class[] = 'column-primary';
				}

				if ( isset( $sortable[ $column_key ] ) ) {
					list( $orderby, $desc_first ) = $sortable[ $column_key ];

					if ( $current_orderby === $orderby ) {
						$order = 'asc' === $current_order ? 'desc' : 'asc';

						$class[] = 'sorted';
						$class[] = $current_order;
					} else {
						$order = strtolower( $desc_first );

						if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
							$order = $desc_first ? 'desc' : 'asc';
						}

						$class[] = 'sortable';
						$class[] = 'desc' === $order ? 'asc' : 'desc';
					}

					$column_display_name = wp_sprintf(
						'<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
						esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ),
						$column_display_name
					);
				}

				$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
				$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
				$id    = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . implode( ' ', $class ) . "'";
				}

				// add an inline style.
				$style   = ( 'cb' === $column_key || 'cb_filter' === $column_key ) ? ' style="width: 6em;"' : '';

				echo "<$tag $scope $id $class$style>$column_display_name</$tag>";
			}
		}
	}
endif;
