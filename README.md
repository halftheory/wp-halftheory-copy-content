# wp-copy-content
Wordpress plugin for shortcode [copy-content].

This plugin allows editors to target and copy content from any webpage.

Features:
- Copies HTML from any URL and then filters it via 'include' and 'exclude' tag selectors.
- Target specific parts of the HTML DOM (based on PHP's DOM/XPath).
- Target HTML comments.
- Include any tag/attribute combination as a shortcode argument.
- Stores filtered HTML in a database transient, allowing you to automatically 'refresh' the content at any time.

# Shortcode arguments

[copy-content (arguments)]
- url (http://...)
- include (list of tag selectors)
- exclude (list of tag selectors)
- raw (true/false)
- refresh (int seconds "86400" or string "1 day")
- force-refresh (true/false)
- any HTML/XML tag (true/false or a list of attributes)

# Shortcode examples

[copy-content url=http://wikipedia.org/ include=.central-textlogo,div.central-featured exclude=div.central-featured-logo-wrapper force-refresh=true]

[copy-content url=http://gli.tc/h/transistor.html include=#mainContent exclude=#container4 refresh="1 day" img=* h2=class,style div=false force-refresh=1]

[copy-content url=https://www.facebook.com/events/1590947440934598 include=code#u_0_g/comment exclude=._4x0d refresh="2 years" div=* code=* span=*]

[copy-content url=https://www.residentadvisor.net/events/962231 include=#event-item exclude=.ptb8]

# Custom filters

The following filters are available for plugin/theme customization:
- copy_content_db_prefix
- copy_content_allowable_tags
- copy_content_before_update_db
- copy_content_wrap_output
- copy_content_plugin_deactivation
- copy_content_plugin_uninstall

# Disclaimer

Note: This plugin has no relation to this https://wordpress.org/plugins/wp-copy-content/
