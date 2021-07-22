<?php
if ( ! class_exists('Halftheory_Copy_Content_Filters', false) ) :
	class Halftheory_Copy_Content_Filters {

        public function __construct() {
        }

        public static function get_filters() {
            $res = array();
            $filters = array(
                'make_relative_links_absolute',
                'remove_tags_script_style',
                'remove_tags_text_empty',
                'fix_links_google',
                'fix_links_facebook',
                'fix_img_lazy',
                'br2nl',
            );
            $plugin_name = get_called_class();
            $i = 10;
            foreach ( $filters as $filter ) {
                if ( method_exists($plugin_name, $filter) ) {
                    $res[ $i++ ] = array( $plugin_name, $filter );
                }
            }
            return $res;
        }

        public static function add_filters() {
            if ( function_exists('add_filter') ) {
                $filters = self::get_filters();
                if ( ! empty($filters) ) {
                    foreach ( $filters as $priority => $filter ) {
                        add_filter('copycontent_content_filters', $filter, $priority, 3);
                    }
                    return true;
                }
            }
            return false;
        }

        /* functions - filters */

        public static function make_relative_links_absolute( $content = '', $url = '', $atts = array() ) {
            if ( strpos($content, '<') === false ) {
                return $content;
            }
            if ( strpos($url, 'http') === false ) {
                return $content;
            }
            if ( class_exists('Halftheory_Copy_Content', false) ) {
                $links = Halftheory_Copy_Content::get_instance()->get_attributes_for_urls();
            } else {
                $links = array(
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
            }
            if ( function_exists('wp_parse_url') ) {
                $scheme = wp_parse_url($url, PHP_URL_SCHEME);
                $host = wp_parse_url($url, PHP_URL_HOST);
            } else {
                $scheme = parse_url($url, PHP_URL_SCHEME);
                $host = parse_url($url, PHP_URL_HOST);
            }
            $replaces = array(
                // does not begin with (http:// or https:// or .. or / or # or mailto:)
                array(
                    'preg_match_all' => "/ (" . implode('|', $links) . ")=[\"'](?!(http:\/\/|https:\/\/|\.\.|\/|#|mailto:))[^\"']+[\"']/is",
                    'preg_replace_pattern' => "/(=[\"'])([^\"']+[\"'])/is",
                    'preg_replace_replacement' => "$1" . str_replace(basename($url), '', $url) . "$2",
                ),
                // begins with /
                array(
                    'strpos_needle' => '="/',
                    'preg_match_all' => "/ (" . implode('|', $links) . ")=[\"']\/[^\/]{1}[^\"']+[\"']/is",
                    'preg_replace_pattern' => "/(=[\"'])(\/[^\"']+[\"'])/is",
                    'preg_replace_replacement' => "$1" . $scheme . "://" . $host . "$2",
                ),
                // is only /
                array(
                    'strpos_needle' => '="/"',
                    'preg_match_all' => "/ (" . implode('|', $links) . ")=[\"']\/[\"']/is",
                    'preg_replace_pattern' => "/(=[\"'])(\/[\"'])/is",
                    'preg_replace_replacement' => "$1" . $scheme . "://" . $host . "$2",
                ),
            );
            foreach ( $replaces as $value ) {
                if ( isset($value['strpos_needle']) ) {
                    if ( strpos($content, $value['strpos_needle']) === false ) {
                        continue;
                    }
                }
                if ( preg_match_all($value['preg_match_all'], $content, $matches) ) {
                    if ( ! empty($matches[0]) ) {
                        $matches[0] = array_unique($matches[0]);
                        $replace = array();
                        foreach ( $matches[0] as $match ) {
                            $replace[ $match ] = preg_replace($value['preg_replace_pattern'], $value['preg_replace_replacement'], $match);
                        }
                        $content = str_replace(array_keys($replace), $replace, $content);
                    }
                }
            }
            // begins with ../ - special case.
            $replaces_dots = array(
                'strpos_needle' => '="../',
                'preg_match_all' => "/ (" . implode('|', $links) . ")=[\"'](\.\.\/[^\"']+)[\"']/is",
            );
            if ( strpos($content, $replaces_dots['strpos_needle']) !== false ) {
                if ( preg_match_all($replaces_dots['preg_match_all'], $content, $matches) ) {
                    if ( ! empty($matches[2]) ) {
                        $matches[2] = array_unique($matches[2]);
                        $replace = array();
                        foreach ( $matches[2] as $match ) {
                            $replacement = str_replace(basename($url), '', $url);
                            $levels = substr_count($match, '../');
                            while ( $levels > 0 ) {
                                $replacement = dirname($replacement);
                                $levels--;
                            }
                            $replace[ $match ] = $replacement . '/' . ltrim($match, './');
                        }
                        $content = str_replace(array_keys($replace), $replace, $content);
                    }
                }
            }
            return $content;
        }

        public static function remove_tags_script_style( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            if ( ! class_exists('Halftheory_Copy_Content', false) ) {
                return $content;
            }
            $tags = array(
                'code',
                'noscript',
                'pre',
                'script',
                'style',
            );
            foreach ( $tags as $tag ) {
                $content = Halftheory_Copy_Content::get_instance()->strip_single_tag($content, $tag);
            }
            return $content;
        }

        public static function remove_tags_text_empty( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            $func = function ( $content ) use ( &$func ) {
                $tmp = $content;
                $tags = array(
                    'b',
                    'blockquote',
                    'del',
                    'div',
                    'em',
                    'h1',
                    'h2',
                    'h3',
                    'h4',
                    'h5',
                    'h6',
                    'i',
                    'p',
                    'span',
                    'strong',
                    'u',
                );
                foreach ( $tags as $tag ) {
                    if ( strpos($tmp, '<' . $tag) === false ) {
                        continue;
                    }
                    $tmp = preg_replace("/[\s]*<$tag [^>]*>[\s]*<\/[ ]*$tag>[\s]*/is", '', $tmp);
                    $tmp = preg_replace("/[\s]*<$tag>[\s]*<\/[ ]*$tag>[\s]*/is", '', $tmp);
                }
                if ( $tmp !== $content ) {
                    $content = $func($tmp);
                }
                return $content;
            };
            $content = $func($content);
            return $content;
        }

        public static function fix_links_google( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            if ( strpos($content, '/url?') === false ) {
                return $content;
            }
            $func = function ( $matches ) {
                parse_str($matches[1], $output);
                if ( isset($output['url']) ) {
                    return $output['url'];
                }
                return $matches[0];
            };
            $func2 = function ( $matches ) {
                parse_str($matches[1], $output);
                if ( isset($output['url']) ) {
                    return '"' . $output['url'] . '"';
                }
                return $matches[0];
            };
            $content = preg_replace_callback(
                "/http[s]?:\/\/[\w\-\.]+\/url\?([\w\-\.\/\!\$\*\+\:\(\)\=~@&',;%_]+)/s",
                $func,
                $content
            );
            $content = preg_replace_callback(
                "/\"\/url\?([\w\-\.\/\!\$\*\+\:\(\)\=~@&',;%_]+)\"/s",
                $func2,
                $content
            );
            return $content;
        }

        public static function fix_links_facebook( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            if ( strpos($content, 'http') === false ) {
                return $content;
            }
            if ( ! empty($url) ) {
                if ( strpos($url, 'facebook') === false && strpos($url, 'fb') === false ) {
                    return $content;
                }
            }
            $query_vars = array( 'eid', 'fbclid', '_nc_cat', '_nc_ohc', '_nc_ht' );
            $func = function ( $matches ) {
                return urldecode($matches[1]);
            };
            $content = preg_replace_callback(
                "/http[s]?:\/\/[\w\-\.]+\/l\.php\?u\=([\w\-\.\/\!\$\*\+\:\(\)\=~@&',;%_#]+)/s",
                $func,
                $content
            );
            $content = preg_replace("/(http[s]?:\/\/[\w\-\.]+\/[^\?]*)\?(" . implode('|', $query_vars) . ")\=[\w\-\.\=&;%_]+/s", "$1", $content);
            $content = preg_replace("/(http[s]?:\/\/[\w\-\.]+\/[^\?]*\?[\w\-\.\/\!\$\*\+\:\(\)\=~@&',;%_]+?)(&|&amp;)(" . implode('|', $query_vars) . ")\=[\w\-\.\=&;%_]+/s", "$1", $content);
            $content = preg_replace("/(http[s]?:\/\/[\w\-\.]+\/[^&]*)(&|&amp;)h\=[\w\-\=&;_]+/s", "$1", $content);
            $content = preg_replace("/(http[s]?:\/\/[\w\-\.]+\/[^\"]*?)(&|&amp;)h\=[\w\-\=&;_]+\"/s", "$1\"", $content);
            return $content;
        }

        public static function fix_img_lazy( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            if ( ! class_exists('Halftheory_Copy_Content', false) || ! function_exists('wp_extract_urls') || ! function_exists('wp_specialchars_decode') ) {
                return $content;
            }
            if ( strpos($content, '<img ') === false && strpos($content, 'lazy') === false ) {
                return $content;
            }
            if ( preg_match_all("/<img [^>]+>/is", $content, $matches) ) {
                $replace = array();
                foreach ( $matches[0] as $value ) {
                    if ( preg_match("/ class=\"[\"]*?lazy[\"]*\"/is", $value) ) {
                        $file_bad = $file_good = false;
                        $urls = wp_extract_urls(wp_specialchars_decode($value));
                        foreach ( $urls as $u ) {
                            if ( ! $file_good && Halftheory_Copy_Content::get_instance()->is_valid_file($u, 'image') ) {
                                $file_good = $u;
                            }
                            if ( ! $file_bad && ! Halftheory_Copy_Content::get_instance()->is_valid_file($u, 'image') ) {
                                $file_bad = $u;
                            }
                            if ( $file_good && $file_bad ) {
                                break;
                            }
                        }
                        if ( $file_good && $file_bad ) {
                            $replace[ $value ] = str_replace($file_bad, $file_good, $value);
                        }
                    }
                }
                if ( ! empty($replace) ) {
                    $content = str_replace(array_keys($replace), $replace, $content);
                }
            }
            return $content;
        }

        public static function br2nl( $content = '', $url = '', $atts = array() ) {
            if ( empty($content) ) {
                return $content;
            }
            $content = preg_replace("/[\s]*<br[\/ ]*>[\s]*/is", "\n", $content);
            if ( function_exists('force_balance_tags') ) {
                $content = force_balance_tags($content);
            }
            if ( class_exists('Halftheory_Copy_Content', false) ) {
                $content = Halftheory_Copy_Content::get_instance()->trim_excess_space($content);
            } else {
                $content = trim($content);
            }
            return $content;
        }
    }
endif;
