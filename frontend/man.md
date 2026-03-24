DESCRIBE homepage_sections;
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
auto_increment
tenant_id
int(10) unsigned
YES
MUL
NULL
section_type
enum('slider','categories','featured_products','new_products','deals','brands','vendors','banners','testimonials','custom_html','other')
NO
MUL
NULL
component
varchar(60)
YES
NULL
title
varchar(255)
YES
NULL
subtitle
varchar(500)
YES
NULL
layout_type
enum('grid','slider','list','carousel','masonry')
YES
grid
layout_config
longtext
YES
NULL
items_per_row
int(11)
YES
4
background_color
varchar(7)
YES
#FFFFFF
text_color
varchar(7)
YES
#000000
padding
varchar(50)
YES
40px 0
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
YES
MUL
1
sort_order
int(11)
YES
MUL
0
theme_id
bigint(20)
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
DESCRIBE homepage_section_translations;
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
auto_increment
section_id
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
YES
NULL
subtitle
varchar(500)
YES
NULL

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
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE ad_payments;
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
amount
decimal(12,2)
YES
NULL
currency_id
smallint(5) unsigned
NO
MUL
NULL
status
enum('pending','paid','failed')
YES
pending
paid_at
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
DESCRIBE images;
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
auto_increment
owner_id
bigint(20)
YES
MUL
NULL
image_type_id
int(10) unsigned
YES
MUL
NULL
tenant_id
bigint(20) unsigned
NO
MUL
NULL
user_id
bigint(20) unsigned
YES
MUL
NULL
filename
varchar(255)
YES
NULL
url
varchar(500)
YES
NULL
thumb_url
varchar(500)
YES
NULL
mime_type
varchar(50)
YES
NULL
size
bigint(20)
YES
NULL
visibility
enum('private','public')
YES
private
is_main
tinyint(1)
YES
0
sort_order
int(11)
YES
0
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
DESCRIBE image_types;
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
code
varchar(50)
YES
UNI
NULL
name
varchar(50)
NO
UNI
NULL
description
varchar(255)
YES
NULL
width
int(11)
NO
NULL
height
int(11)
NO
NULL
crop
enum('fit','fill','cover')
YES
cover
quality
tinyint(4)
YES
85
format
enum('jpg','png','webp')
YES
webp
is_thumbnail
tinyint(1)
YES
0
icon
varchar(100)
NO
fa-image
color
varchar(30)
NO
#6b7280

---

## Public API — Ads Endpoint

### GET /api/public/ads

Fetch active ads assigned to placement(s) for the given tenant.

Query parameters:
| Parameter       | Type    | Required | Description                                  |
|----------------|---------|----------|----------------------------------------------|
| tenant_id       | int     | Yes      | Tenant identifier                            |
| placement_key   | string  | No       | Filter by placement key (e.g. `homepage_top`) |
| lang            | string  | No       | Language code (default: `ar`)                |
| limit           | int     | No       | Max ads to return (1–50, default: 25)        |

Response (200 OK):
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "target_type": "url",
      "target_value": "https://example.com",
      "title": "Ad Title",
      "description": "Ad description",
      "image_url": "https://cdn.example.com/ads/ad1.webp",
      "thumb_url": "https://cdn.example.com/ads/ad1_thumb.webp",
      "priority": 1,
      "weight": 10
    }
  ]
}
```

### GET /api/public/ads/{id}

Fetch a single active ad by ID (for click-through and detail pages).

Tracking endpoints:
- `GET /api/track_view.php?id={ad_id}` — record an ad view (daily deduplication per session)
- `GET /api/track_click.php?id={ad_id}` — record an ad click (no deduplication)

Views and clicks are stored in the `ad_stats` table (upsert by `ad_id + date`).

### homepage_sections — section data_source for ads

To display ads in a homepage section, set the section's `data_source` to:

```
ads:{placement_key}
```

Example: `data_source = ads:homepage_top` will fetch all active ads
assigned to the placement with `placement_key = homepage_top`.

Compatible components: `ad_banner`, `ad_slider`, `ad_native`

### Notes on banners table images

The `banners` table does **not** have `image_url` or `mobile_image_url` columns.
Banner images are stored in the unified `images` table with `image_type_id = 9`
and are returned via a LEFT JOIN in the public banners API endpoint.
