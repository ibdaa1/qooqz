DESCRIBE homepage_sections;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(10) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
theme_id
int(10) unsigned
YES
NULL
section_type
varchar(50)
NO
NULL
component
varchar(100)
YES
NULL
title
varchar(255)
YES
NULL
subtitle
varchar(255)
YES
NULL
layout_type
varchar(50)
YES
grid
layout_config
text
YES
NULL
items_per_row
tinyint(3) unsigned
NO
4
background_color
varchar(30)
YES
NULL
text_color
varchar(30)
YES
NULL
padding
varchar(50)
YES
NULL
custom_css
text
YES
NULL
custom_html
text
YES
NULL
data_source
varchar(255)
YES
NULL
is_active
tinyint(1)
NO
1
sort_order
smallint(6)
NO
MUL
0
created_at
datetime
NO
current_timestamp()
updated_at
datetime
YES
NULL
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE homepage_section_translations;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(10) unsigned
NO
PRI
NULL
auto_increment
section_id
int(10) unsigned
NO
MUL
NULL
language_code
varchar(10)
NO
NULL
title
varchar(255)
YES
NULL
subtitle
varchar(255)
YES
NULL
created_at
datetime
NO
current_timestamp()
updated_at
datetime
YES
NULL
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE banners;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20)
NO
PRI
NULL
tenant_id
int(10) unsigned
YES
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
title
varchar(255)
NO
NULL
subtitle
varchar(500)
YES
NULL
link_url
varchar(500)
YES
NULL
link_text
varchar(100)
YES
NULL
position
enum('homepage_main','homepage_secondary','category_top','product_sidebar','footer','popup','other')
YES
MUL
homepage_main
theme_id
bigint(20)
YES
MUL
NULL
background_color
varchar(7)
YES
#FFFFFF
text_color
varchar(7)
YES
#000000
button_style
varchar(100)
YES
NULL
sort_order
int(11)
YES
0
is_active
tinyint(1)
YES
MUL
1
start_date
datetime
YES
MUL
NULL
end_date
datetime
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE banner_translations;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20)
NO
PRI
NULL
banner_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
title
varchar(255)
NO
NULL
subtitle
varchar(500)
YES
NULL
link_text
varchar(100)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ads;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
campaign_id
bigint(20) unsigned
NO
MUL
NULL
target_type
enum('url','entity')
YES
url
target_value
varchar(500)
YES
NULL
status
enum('active','paused','rejected')
YES
active
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_campaigns;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
YES
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
name
varchar(255)
NO
NULL
budget
decimal(12,2)
YES
0.00
currency_id
smallint(5) unsigned
NO
MUL
NULL
pricing_model
enum('fixed','cpm','cpc')
YES
fixed
start_date
datetime
YES
NULL
end_date
datetime
YES
NULL
status
enum('draft','active','paused','completed')
YES
draft
created_by
int(10) unsigned
YES
MUL
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_translations;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
ad_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
title
varchar(255)
YES
NULL
description
text
YES
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_placements;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
code
varchar(100)
YES
UNI
NULL
name
varchar(255)
YES
NULL
description
text
YES
NULL
placement_key
varchar(100)
NO
MUL
NULL
page
varchar(100)
YES
NULL
width
int(11)
YES
NULL
height
int(11)
YES
NULL
max_ads
int(11)
YES
1
created_at
datetime
YES
current_timestamp()
status
enum('active','inactive','draft')
YES
active
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_placement_items;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
placement_id
bigint(20) unsigned
NO
MUL
NULL
ad_id
bigint(20) unsigned
NO
MUL
NULL
priority
int(11)
YES
1
weight
int(11)
YES
1
start_date
datetime
YES
NULL
end_date
datetime
YES
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_stats;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
ad_id
bigint(20) unsigned
NO
MUL
NULL
views
int(11)
YES
0
clicks
int(11)
YES
0
date
date
NO
NULL
Query results operations
  
Open new phpMyAdmin window
