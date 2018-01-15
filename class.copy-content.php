<?php
/*
Available filters:
copycontent_db_prefix(string $db_prefix)
copycontent_allowable_tags(array $allowable_tags)
copycontent_before_update_db(string $output, array $atts, string $hash)
copycontent_wrap_output(string $output, array $atts, string $shortcode)
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Copy_Content')) :
class Copy_Content {

	public function __construct() {
		$this->plugin_name = get_called_class();
		$this->plugin_title = ucwords(str_replace('_', ' ', $this->plugin_name));
		$this->prefix = sanitize_key($this->plugin_name);
		$this->prefix = preg_replace("/[^a-z0-9]/", "", $this->prefix);
		$this->prefix = apply_filters('copycontent_db_prefix', $this->prefix);

		$this->shortcode = 'copy-content';
		add_shortcode($this->shortcode, array($this, 'shortcode'));
		$this->domwrapper = 'domwrapper';

		// TODO: add admin page to select file downloading options
		// just testing
		if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
			add_filter('copycontent_before_update_db', array($this, 'copycontent_before_update_db'), 10, 3);
			add_filter('copycontent_deactivation', array($this, 'copycontent_deactivation'));
		}
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
			if (substr_count($str, "&lt;") > substr_count($str, "<") || preg_match("/&lt;\/[a-z0-9]+&gt;/is", $str)) {
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
		$str = str_replace("&nbsp;", ' ', $str);
		$str = str_replace("&#160;", ' ', $str);
		$str = str_replace("\xc2\xa0", ' ',$str);

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
		$str = str_replace("<!--", "###COMMENT_OPEN###", $str);
		$str = str_replace("-->", "###COMMENT_CLOSE###", $str);
		$str = strip_tags($str, $allowable_tags);
		$str = str_replace("###COMMENT_OPEN###", "<!--", $str);
		$str = str_replace("###COMMENT_CLOSE###", "-->", $str);
		return $str;
	}

	/* functions */

	private function true($value) {
		if (is_bool($value)) {
			return $value;
		}
		elseif (empty($value)) {
			return false;
		}
		elseif (is_int($value)) {
			if ($value == 1) {
				return true;
			}
			elseif ($value == 0) {
				return false;
			}
			return $value;
		}
		elseif (is_string($value)) {
			if ($value == '1' || $value == 'true') {
				return true;
			}
			elseif ($value == '0' || $value == 'false') {
				return false;
			}
			return $value;
		}
		return false;
	}

	public function get_url_contents($url, $is_string = true) {
		if ($this->url_exists($url) === false) {
			return false;
		}
		$str = '';
		// use user_agent when available
		$user_agent = false;
		if (isset($_SERVER["HTTP_USER_AGENT"])) {
			$user_agent = $_SERVER["HTTP_USER_AGENT"];
		}
		// try php
		if ($user_agent) {
			$options = array('http' => array('user_agent' => $user_agent));
			$context = stream_context_create($options);
			$str = file_get_contents($url, false, $context);
		}
		else {
			$str = file_get_contents($url);
		}
		// try curl
		if ($str === false || (is_string($str) && trim($str) == '')) {
			if (function_exists('curl_init')) {
				$curl = @curl_init();
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				if ($user_agent) {
	  				curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
	  			}
				curl_setopt($curl, CURLOPT_URL, $url);
				$str = curl_exec($curl);
				curl_close($curl);
			}
		}
		if ($str === false || (is_string($str) && trim($str) == '')) {
			return false;
		}
		if ($is_string) {
			$str = $this->fix_potential_html_string($str);
			$str = $this->trim_excess_space($str);
		}
		return $str;
	}

	private function loadHTML($str)	{
		$dom = @DOMDocument::loadHTML('<'.$this->domwrapper.'>'.mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8').'</'.$this->domwrapper.'>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		return $dom;
	}

	private function saveHTML($dom)	{
		$str = html_entity_decode($dom->saveHTML());
		$str = preg_replace("/<[\/]?".$this->domwrapper."[^>]*>/is", "", $str);
		$xhtml_tags = array('area','base','basefont','br','col','frame','hr','img','input','link','meta','param');
		$str = preg_replace("/<(".implode("|", $xhtml_tags).")([^>]*?)[ \/]*>/is", "<$1$2 />\n", $str);
		return $this->trim_excess_space($str);
	}

	private function selector_to_xpath($value) {
		/*
		e.g. div#myID/comment,span.myClass,#myID2
		*/
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

	private function allowable_tags() {
		$arr = array(
			'a' => array('href', 'title', 'target', 'rel'),
			'audio' => '*',
			'b' => '',
			'blockquote' => '',
			'br' => '',
			'code' => '',
			'del' => '',
			'div' => array('style'),
			'em' => '',
			'embed' => '*',
			'h1' => '',
			'h2' => '',
			'h3' => '',
			'h4' => '',
			'h5' => '',
			'h6' => '',
			'hr' => '',
			'i' => '',
			'iframe' => '*',
			'img' => array('src', 'alt', 'title', 'border', 'width', 'height', 'style'),
			'li' => '',
			'object' => '*',
			'ol' => '',
			'p' => array('style', 'align'),
			'param' => '*',
			'span' => array('style'),
			'strong' => '',
			'table' => '*',
			'tbody' => '',
			'td' => '',
			'th' => '',
			'thead' => '',
			'tr' => '',
			'u' => '',
			'ul' => '',
			'video' => '*',
		);
		return apply_filters('copycontent_allowable_tags', $arr);
	}

	private function relative_links_absolute($str, $url) {
		if (strpos($str, '<') === false) {
			return $str;
		}
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
		);
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
				$matches[0] = array_unique($matches[0]);
				foreach ($matches[0] as $match) {
					$match_new = preg_replace($value['preg_replace_pattern'], $value['preg_replace_replacement'], $match);
					$str = str_replace($match, $match_new, $str);
				}
			}
		}
		return $str;
	}

	private function get_option() {
		if (is_multisite()) {
			$option = get_site_option($this->prefix, array());
		}
		else {
			$option = get_option($this->prefix, array());
		}
		return $option;
	}
	private function update_option($option) {
		if (is_multisite()) {
			$bool = update_site_option($this->prefix, $option);
		}
		else {
			$bool = update_option($this->prefix, $option);
		}
		return $bool;
	}
	private function get_transient($transient) {
		if (is_multisite()) {
			$value = get_site_transient($transient);
		}
		else {
			$value = get_transient($transient);
		}
		return $value;
	}
	private function set_transient($transient, $value, $expiration = 0) {
		if (is_string($expiration)) {
			$expiration = strtotime('+'.$expiration) - time();
			if (!$expiration || $expiration < 0) {
				$expiration = 0;
			}
		}
		if (is_multisite()) {
			$transient = substr($transient, 0, 167);
			$bool = set_site_transient($transient, $value, $expiration);
		}
		else {
			$transient = substr($transient, 0, 172);
			$bool = set_transient($transient, $value, $expiration);
		}
		return $bool;
	}
	private function delete_transient($transient) {
		if (is_multisite()) {
			$bool = delete_site_transient($transient);
		}
		else {
			$bool = delete_transient($transient);
		}
		return $bool;
	}

	private function update_db($str = '', $str_fallback = '', $atts, $option = array(), $hash) {
		if (empty($str)) {
			$str = $str_fallback;
		}
		else {
			$str = $this->relative_links_absolute($str, $atts['url']);
		}
		if (!empty($str)) {
			if (!isset($option[$hash])) {
				$option[$hash] = array();
			}
			if (empty($option[$hash])) {
				$i = 0;
			}
			else {
				$i = max(array_keys($option[$hash])) + 1;
			}

			// one might use this filter to download files and change links, see below
			$str = apply_filters('copycontent_before_update_db', $str, $atts, $hash);

			if ($this->set_transient($this->prefix.'_'.$hash.'_'.$i, $str, $atts['refresh'])) {
				unset($atts['force-refresh']); // avoid storing in db
				$option[$hash][$i] = $atts;
			}
		}
		$this->update_option($option);
		return $str;
	}
	private function wrap_output($str, $atts) {
		$str = '<div class="'.$this->shortcode.'">
		<div class="'.$this->shortcode.'-header"><small>From <a href="'.esc_url($atts['url']).'">'.parse_url($atts['url'], PHP_URL_HOST).'</a></small></div>
		<div class="'.$this->shortcode.'-content">'.$str.'</div></div>';
		return apply_filters('copycontent_wrap_output', $str, $atts, $this->shortcode);
	}
	private function update_and_output($str = '', $str_fallback = '', $atts, $option = array(), $hash) {
		$str = $this->update_db($str, $str_fallback, $atts, $option, $hash);
		if (empty($str)) {
			return '';
		}
		return $this->wrap_output($str, $atts);
	}

	/* shortcode */

	public function shortcode($atts = array(), $content = '', $shortcode = '') {
		if (!in_the_loop()) {
			return '';
		}
		$defaults = array(
			'url' => '',
			'include' => 'body',
			'exclude' => '',
			'raw' => false,
			'refresh' => 0,
			'force-refresh' => false,
		);
		//$atts = shortcode_atts($defaults, $atts, $this->shortcode); // removes keys not found in defaults
		$atts = array_merge($defaults, (array)$atts);

		if (empty($atts['url'])) {
			return '';
		}

		if ($this->true($atts['raw']) === true) {
			$atts['raw'] = true;
		}
		else {
			$atts['raw'] = false;
		}
		if ($this->true($atts['force-refresh']) === true) {
			$atts['force-refresh'] = true;
		}
		else {
			$atts['force-refresh'] = false;
		}

		$atts_old = $atts;

		// proceed if first pull or refresh expired
		// urls are not unique! this system checks for duplicates
		$str = $str_fallback = '';
		$option = $this->get_option();
		$hash = hash('adler32', $atts['url']);

		// found this url
		if (isset($option[$hash])) {
			// look for quick output
			if (!empty($option[$hash])) {
				$atts_search = $atts;
				unset($atts_search['force-refresh']);
				$i = array_search($atts_search, $option[$hash]);
				// found this url with these atts
				if ($i !== false) {
					$transient = $this->get_transient($this->prefix.'_'.$hash.'_'.$i);
					// success
					if ($transient !== false && !$atts['force-refresh']) {
						return $this->wrap_output($transient, $atts_old);
					}
					elseif ($transient !== false && $atts['force-refresh']) {
						$this->delete_transient($this->prefix.'_'.$hash.'_'.$i);
						unset($option[$hash][$i]);
					}
					else {
						unset($option[$hash][$i]);
					}
				}
			}
			// look for fallback - get last in array - probably the last update
			if (!empty($option[$hash])) {
				$fallback_search = array_reverse($option[$hash], true);
				foreach ($fallback_search as $i => $value) {
					$transient = $this->get_transient($this->prefix.'_'.$hash.'_'.$i);
					if ($transient !== false) {
						$str_fallback = $transient;
						break;
					}
					else {
						unset($option[$hash][$i]);
					}
				}
			}
			// look for raw str
			$transient = $this->get_transient($this->prefix.'_'.$hash);
			if ($transient !== false) {
				$str = $transient;
			}
			else {
				if (empty($str_fallback)) { // no raw or fallback, remove url
					unset($option[$hash]);
				}
			}
		}

		// get remote content
		if (empty($str) || $atts['force-refresh']) {
			$contents = $this->get_url_contents($atts['url']);
			// store it
			if ($contents !== false) {
				if ($this->set_transient($this->prefix.'_'.$hash, $contents, $atts['refresh'])) {
					if (!isset($option[$hash])) {
						$option[$hash] = array();
					}
					$str = $contents;
				}
			}
		}

		// sorry, nothing worked
		if (empty($str)) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}

		// no html
		if (strpos($str, '<') === false) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}

		/*
		html handling:
		1. remove scripts + styles
		2. include
		3. exclude (from includes)
		4. strip tags + attrs
		5. resolve relative links
		*/

		// script/style tags - special case - remove all contents
		$strip_all = array('script', 'style');
		foreach ($strip_all as $value) {
			$str = preg_replace("/<".$value."[^>]*>.*?<\/".$value.">/is", "", $str);
			$str = preg_replace("/<[\/]?".$value."[^>]*>/is", "", $str);
		}

		if (!class_exists('DOMXPath')) {
			// strip body?
			if ($atts['include'] == $defaults['include']) {
				$str = preg_replace("/^.*?<".$atts['include']."[^>]*>(.*?)<\/".$atts['include'].">.*$/is", "$1", $str);
			}
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}

		$dom = $this->loadHTML($str);
		if (empty($dom->textContent)) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}
		$xpath = new DOMXPath($dom);

		// resolve multiples
		$maybe_array = array(
			'include',
			'exclude',
		);
		foreach ($maybe_array as $value) {
			if (empty($atts[$value])) {
				continue;
			}
			if (!is_string($atts[$value])) {
				continue;
			}
			if (strpos($atts[$value], ",") !== false) {
				$atts[$value] = explode(",", $atts[$value]);
				$atts[$value] = array_map('trim', $atts[$value]);
				$atts[$value] = array_filter($atts[$value]);
			}
		}

		// keep
		if (!empty($atts['include'])) {
			$atts['include'] = (array)$atts['include'];
			$keep = array();
			foreach ($atts['include'] as $value) {
				$value = $this->selector_to_xpath($value);
				$tags = $xpath->query('//'.$value);
				if ($tags->length == 0) {
					continue;
				}
				foreach ($tags as $tag) {
					// node
					if ($tag->tagName) {
						if ($tag->tagName == $this->domwrapper) {
							continue;
						}
						$keep[] = $tag->ownerDocument->saveXML($tag);
					}
					// comment
					elseif ($tag->nodeName == '#comment' && !empty($tag->nodeValue)) {
						$keep[] = $tag->nodeValue;;
					}
				}
			}
			if (!empty($keep)) {
				$str = implode("\n", $keep);
				$dom = $this->loadHTML($str);
				$xpath = new DOMXPath($dom);
			}
		}

		// remove
		if (!empty($atts['exclude'])) {
			$atts['exclude'] = (array)$atts['exclude'];
			$remove = array();
			foreach ($atts['exclude'] as $value) {
				$value = $this->selector_to_xpath($value);
				$tags = $xpath->query('//'.$value);
				if ($tags->length == 0) {
					continue;
				}
				foreach ($tags as $tag) {
					// only nodes
					if (!$tag->tagName) {
						continue;
					}
					if ($tag->tagName == $this->domwrapper) {
						continue;
					}
					$remove[] = $tag;
				}
			}
			if (!empty($remove)) {
				$before_remove = $str;
				foreach($remove as $value) {
					$value->parentNode->removeChild($value);
				}
				$str = $this->saveHTML($dom);
				if (empty($str)) {
					$str = $before_remove;
				}
				else {
					$xpath = new DOMXPath($dom);
				}
			}
		}

		if ($atts['raw']) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}

		// handle extra atts as tag includes/excludes
		$allowable_tags = $this->allowable_tags();
		if (count($atts) > count($defaults)) {
			foreach ($atts as $key => $value) {
				if (isset($defaults[$key])) {
					continue;
				}
				// remove
				if ($this->true($value) === false && isset($allowable_tags[$key])) {
					unset($allowable_tags[$key]);
				}
				// add/change
				elseif ($this->true($value) === true) {
					$allowable_tags[$key] = '';
				}
				elseif ($value == 'all' || $value == '*') {
					$allowable_tags[$key] = '*';
				}
				else {
					if (strpos($value, ",") !== false) {
						$value = explode(",", $value);
						$value = array_map('trim', $value);
						$value = array_filter($value);
					}
					$allowable_tags[$key] = (array)$value;
				}
			}
		}

		if (empty($allowable_tags)) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}

		// strip tags
		$keep_comments = false;
		if (strpos($atts_old['include'], "/comment") !== false) {
			$keep_comments = true;
		}
		$strip_tags = '<'.implode('><', array_keys($allowable_tags)).'>';
		if ($keep_comments) {
			$str = $this->strip_tags_html_comments($str, $strip_tags);
		}
		else {
			$str = strip_tags($str, $strip_tags);
		}
		$str = $this->trim_excess_space($str);
		if (empty($str)) {
			return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
		}
		$dom = $this->loadHTML($str);
		$xpath = new DOMXPath($dom);

		$text_tags = array(
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
		$void_tags = array(
			'area',
			'base',
			'br',
			'col',
			'command',
			'embed',
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
		$remove_tags = array();
		$tags = $xpath->query('//*[not(self::br)]');
		foreach ($tags as $tag) {
			if (!$tag->tagName) {
				continue;
			}
			if ($tag->tagName == $this->domwrapper) {
				continue;
			}
			if (!array_key_exists($tag->tagName, $allowable_tags)) { // should be taken care of by strip_tags
				if (trim($tag->nodeValue) == "" && $tag->childNodes->length == 0) {
					$remove = true;
					if ($keep_comments) {
						$tag_html = $tag->ownerDocument->saveHTML($tag);
						if (strpos($tag_html, "<!--") !== false) {
							$remove = false;
						}
					}
					if ($remove) {
						$remove_tags[] = $tag;
					}
				}
				continue;
			}
			if (empty($allowable_tags[$tag->tagName]) && in_array($tag->tagName, $text_tags) && !in_array($tag->tagName, $void_tags)) { // remove empty, only for text-tags (probably)
				if (trim($tag->nodeValue) == "" && $tag->childNodes->length == 0) {
					$remove = true;
					if ($keep_comments) {
						$tag_html = $tag->ownerDocument->saveHTML($tag);
						if (strpos($tag_html, "<!--") !== false) {
							$remove = false;
						}
					}
					if ($remove) {
						$remove_tags[] = $tag;
						continue;
					}
				}
			}
			if ($allowable_tags[$tag->tagName] === '*') {
				continue;
			}
			if ($tag->attributes->length == 0) {
				continue;
			}
			// remove attr
			if (!is_array($allowable_tags[$tag->tagName])) {
				$allowable_tags[$tag->tagName] = explode(",", $allowable_tags[$tag->tagName]);
				$allowable_tags[$tag->tagName] = array_map('trim', $allowable_tags[$tag->tagName]);
				$allowable_tags[$tag->tagName] = array_filter($allowable_tags[$tag->tagName]);
			}
			$remove_attr = array();
			for ($i = 0; $i < $tag->attributes->length; $i++) {
				$my_attr = $tag->attributes->item($i)->name;
				if (!in_array($my_attr, $allowable_tags[$tag->tagName])) {
					$remove_attr[] = $my_attr;
				}
			}
			if (!empty($remove_attr)) {
				foreach ($remove_attr as $value) {
					$tag->removeAttribute($value);
				}
			}
		}
		foreach($remove_tags as $value) {
			$value->parentNode->removeChild($value);
		}

		$str = $this->saveHTML($dom);
		return $this->update_and_output($str, $str_fallback, $atts_old, $option, $hash);
	}

	/* filters */

	// download files and change links
	public function copycontent_before_update_db($str, $atts, $hash) {
		// just testing
		if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
			return $str;
		}

		$my_host = parse_url(home_url(), PHP_URL_HOST);

		// only for external sites
		if ($my_host == parse_url($atts['url'], PHP_URL_HOST)) {
			return $str;
		}

		$replacements = array();
		$links = array(
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
		);
		$files = array(
			'gif',
			'jpg',
			'jpeg',
			'png',
		);
		if (preg_match_all("/ (".implode("|", $links).")=[\"']([^\"']+?\.(".implode("|", $files)."))[\"']/is", $str, $matches)) {
			$matches[2] = array_unique($matches[2]);
			foreach ($matches[2] as $match) {
				if ($my_host != parse_url($match, PHP_URL_HOST)) {
					$replacements[] = $match;
				}
			}
		}
		if (empty($replacements)) {
			return $str;
		}

		$uploads = wp_upload_dir();
		$my_basedir = $uploads['basedir'].'/'.$this->prefix.'/'.$hash;
		if (wp_mkdir_p($my_basedir)) {
			$my_baseurl = $uploads['baseurl'].'/'.$this->prefix.'/'.$hash;
			$max = 255; // max length 255
			foreach ($replacements as $value) {
				$host = sanitize_title(parse_url($value, PHP_URL_HOST));
				$basename = basename($value);
				$path = sanitize_title( str_replace($basename, '', parse_url($value, PHP_URL_PATH)) );
				$path = substr($path, 0, $max - strlen($host) - strlen($basename) - 2);
				$arr = array($host, $path, $basename);
				$arr = array_map('trim', $arr, array_fill(0, 3, " -"));
				$arr = array_filter($arr);
				$filename = implode("-", $arr);
				if (file_exists($my_basedir.'/'.$filename) && !$atts['force-refresh']) {
					$str = str_replace($value, $my_baseurl.'/'.$filename, $str);
					continue;
				}
				if ($file = $this->get_url_contents($value, false)) {
					if ($file === false) {
						continue;
					}
					if (@file_put_contents($my_basedir.'/'.$filename, $file)) {
						@chmod($my_basedir.'/'.$filename, 0777);
						$str = str_replace($value, $my_baseurl.'/'.$filename, $str);
					}
				}
			}
		}

		return $str;
	}

	// delete files
	public function copycontent_deactivation($prefix) {
		// just testing
		if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
			return;
		}
		if (!class_exists('WP_Filesystem_Direct')) {
			@include_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php');
			@include_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php');
		}
		$direct = new WP_Filesystem_Direct('direct');
		if (is_multisite()) {
			$args = array(
				'public' => 1,
				'archived' => 0,
				'spam' => 0,
				'deleted' => 0,
				'orderby' => 'path'
			);
			$sites = get_sites($args);
			foreach ($sites as $key => $value) {
				switch_to_blog($value->blog_id);
				$uploads = wp_upload_dir();
				$my_basedir = $uploads['basedir'].'/'.$prefix;
				$direct->rmdir($my_basedir, true);
				restore_current_blog();
			}
		}
		else {
			$uploads = wp_upload_dir();
			$my_basedir = $uploads['basedir'].'/'.$prefix;
			$direct->rmdir($my_basedir, true);
		}
		return;
	}

}
endif;
?>