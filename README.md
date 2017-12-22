# wp-copy-content
Wordpress plugin for shortcode [copy-content]

Targets and copies content from any webpage.

Arguments:
- url (http://...)
- include (list of tag selectors)
- exclude (list of tag selectors)
- raw (true/false)
- refresh (int seconds "86400" or string "1 day")
- force-refresh (true/false)
- any HTML/XML tag (true/false or a list of attributes)

Examples:

[copy-content url=http://wikipedia.org/ include=.central-textlogo,div.central-featured exclude=div.central-featured-logo-wrapper force-refresh=true]

[copy-content url=http://gli.tc/h/transistor.html include=#mainContent exclude=#container4 refresh=’1 day’ img=* h2=class,style div=false force-refresh=1]

[copy-content url=https://www.facebook.com/events/1590947440934598 include=code#u_0_g/comment exclude=._4x0d refresh=’2 years’ div=* code=* span=*]

[copy-content url=https://www.residentadvisor.net/events/479119 include=#event-item exclude=.ptb8]