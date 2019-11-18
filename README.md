# wp-halftheory-copy-content
Wordpress plugin for shortcodes [copy-content] and [wp-copy-content].

This plugin allows editors to target and copy content from any external webpage or internal post.

Features:
- Copies HTML from any URL and then filters it via 'include' and 'exclude' tag selectors.
- Target specific parts of the HTML DOM (based on PHP's DOM/XPath).
- Target HTML comments.
- Include any tag/attribute combination as a shortcode argument.
- Allows you to automatically 'refresh' the content at any time.
- Automatically copy the excerpt and thumbnail into the current post.
- Set default parameters for each shortcode.
- Many file handling options (Keep original links, Remove from content, Download files).
- Copies the filtered HTML into the post content box (for further editing) or stores it in a database transient.

# [copy-content] arguments

- url (http://...)
- include (list of tag selectors)
- exclude (list of tag selectors)
- refresh (int seconds "86400" or string "1 day")
- update_excerpt (true/false)
- update_thumbnail (true/false)
- force-update (true/false)
- force-refresh (true/false)
- any HTML/XML tag (true/false or a list of attributes)

# [copy-content] examples

[copy-content url=http://wikipedia.org/ include=.central-textlogo,div.central-featured exclude=div.central-featured-logo-wrapper force-refresh=true]

[copy-content url=http://gli.tc/h/transistor.html include=#mainContent exclude=#container4 refresh="1 day" img=* h2=class,style div=false force-refresh=1]

[copy-content url=https://www.facebook.com/events/1590947440934598 include=code#u_0_g/comment exclude=._4x0d refresh="2 years" div=* code=* span=*]

[copy-content url=https://www.residentadvisor.net/events/962231 include=#event-item exclude=.ptb8]

# [wp-copy-content] arguments

- blog_id (multisite)
- include (list of tag selectors)
- exclude (list of tag selectors)
- refresh (int seconds "86400" or string "1 day")
- update_excerpt (true/false)
- update_thumbnail (true/false)
- force-update (true/false)
- force-refresh (true/false)
- any WP_Query argument (posts, taxonomies, search, etc.)

# [wp-copy-content] examples

[wp-copy-content p=5]

[wp-copy-content name=parent/slug]

[wp-copy-content category_name=news]

[wp-copy-content blog_id=3 category_name=news paged=2 update_thumbnail=0]

# Custom filters

The following filters are available for plugin/theme customization:
- halftheory_admin_menu_parent
- copycontent_admin_menu_parent
- copycontent_deactivation
- copycontent_uninstall
- copycontent_file_types
- copycontent_shortcode
- copycontent_get_content
- wpcopycontent_get_content
- wpcopycontent_query
- wpcopycontent_template
