SELECT * FROM `entities`
 Profiling [ Edit inline ] [ Edit ] [ Explain SQL ] [ Create PHP code ] [ Refresh ]
 Show all	|			Number of rows: 
25
Filter rows: 
Search this table
Sort by key: 
None
Full texts
id
parent_id
tenant_id
branch_code
user_id
store_name
slug
vendor_type
store_type
registration_number
tax_number
phone
mobile
email
website_url
status
suspension_reason
is_verified
joined_at
approved_at
created_at
updated_at

Edit Edit
Copy Copy
Delete Delete
1
NULL
1
NULL
7
الكيان الرئيسي الأول
main-entity-1
product_seller
company
REG-1001
TAX-1001
+991609900001
+991500000001
main9@exampl111e.com
https://main9.com
approved
9999
1
2026-02-07 13:53:10
2026-02-07 13:53:10
2026-02-07 13:53:10
2026-02-15 05:40:17

Edit Edit
Copy Copy
Delete Delete
4
NULL
2
NULL
7
الكيان الفرعي الأول
home202255454
service_provider
brand
12345
66
0559740334
+971555000002
zedanmahmoud99@gmail.com
https://main9.com
approved
NULL
1
2026-02-10 13:45:16
NULL
2026-02-10 13:45:16
2026-02-14 03:14:05

Edit Edit
Copy Copy
Delete Delete
5
NULL
3
NULL
7
فرع الكيان الأول2
home555
product_seller
individual
1236
1254
791559740334
+971500000001
zedanmahmoud9@gmail.com
https://branch1.com
approved
NULL
1
2026-02-12 12:39:23
NULL
2026-02-12 12:39:23
2026-02-26 08:33:53

Edit Edit
Copy Copy
Delete Delete
6
NULL
1
NULL
1
Brand 1
brand-1
product_seller
individual
NULL
NULL
000000000
NULL
brand1@system.local
NULL
approved
NULL
1
2026-02-21 02:19:22
NULL
2026-02-21 02:19:22
2026-02-26 08:33:30

Edit Edit
Copy Copy
Delete Delete
7
NULL
1
NULL
1
Brand 2
brand-2
product_seller
individual
NULL
NULL
000000000
NULL
brand2@system.local
NULL
approved
NULL
1
2026-02-21 02:19:22
NULL
2026-02-21 02:19:22
2026-02-26 08:33:33

Edit Edit
Copy Copy
Delete Delete
8
NULL
1
NULL
1
Brand 3
brand-3
product_seller
individual
NULL
NULL
000000000
NULL
brand3@system.local
NULL
approved
NULL
1
2026-02-21 02:19:22
NULL
2026-02-21 02:19:22
2026-02-26 08:33:38

Edit Edit
Copy Copy
Delete Delete
9
1
1
NULL
1
Brand 4
brand-4
product_seller
individual
NULL
NULL
000000000
NULL
brand4@system.local
NULL
approved
NULL
1
2026-02-21 02:19:22
NULL
2026-02-21 02:19:22
2026-02-26 08:33:41

Edit Edit
Copy Copy
Delete Delete
10
NULL
2
NULL
1
Brand 5
brand-5
product_seller
individual
NULL
NULL
000000000
NULL
brand5@system.local
NULL
approved
NULL
NULL
2026-02-21 02:19:22
NULL
2026-02-21 02:19:22
2026-02-26 08:33:48
 --لم يظهر الجميع// وكذلك الوظائف- صور القوائم لم تظهر- استخدم//SELECT *
FROM homepage_sections
WHERE tenant_id = :tenant_id
   OR tenant_id IS NULL
ORDER BY sort_order;
////////////////
api/entities_attribute_values لحفظ وتعديل وحذف وفلتره القيم
api/entities_attributes لجلب القيم بالترجمات
api/entity_settings لحفظ وتعديل وجلب الاعدادات
api/entities لحفظ وتعديل وحذف وفلتره القيم
api/languages لجلب اللغات لتسجيل الترجمات
api/tenants لجلب الكيان
api/entity_types لجلب النوع 
/admin/fragments/addresses.php?embedded=1&tenant_id=1&lang=ar&owner_type=entity&owner_id=24



الصور احتاج حقول بالنماذج غير منضمه للصور بحيث لو الصوره غير موجوده يرسل الرابط لها  اولا/entity_logo
entity_logo
/admin/fragments/media_studio.php?embedded=1&tenant_id=1&lang=ar&owner_id=24&image_type_id=4
///////////
entity_cover
/admin/fragments/media_studio.php?embedded=1&tenant_id=1&lang=ar&owner_id=24&image_type_id=5
///////////
entity_license
/admin/fragments/media_studio.php?embedded=1&tenant_id=1&lang=ar&owner_id=24&image_type_id=6

/api/addresses اضافة/تعديل/ حذف/ عرض/ فلتر
api/countries?language=ar
api/cities?country_id=25&language=ar
حسب لغة المستخدم يستقبل  حسب المرسل اليه 
owner_type Index enum('user', 'entity')	
owner_id Index	bigint(20)
ويستقبل الارقام مباشرة يعبي تلاقائي//fallback تلقائي لـ owner_type و owner_id
admin/fragments/addresses.php?entities=1&tenant_id=1&lang=ar&owner_id=4&owner_type=entity
/admin/fragments/media_studio.php?embedded=1&tenant_id=1&lang=ar&owner_id=24&image_type_id=4
//api/jobs
api/job_skills
api/job_interviews
api/job_applications
api/job_application_questions
api/job_application_answers
api/job_alerts
api/languages
/qooqz/doc به ملفات md 
///
our SQL query has been executed successfully.
DESCRIBE products;
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
product_type_id
int(10) unsigned
NO
MUL
NULL
tenant_id
bigint(20)
NO
NULL
created_by_user_id
int(11) unsigned
YES
MUL
NULL
sku
varchar(100)
NO
UNI
NULL
slug
varchar(255)
NO
UNI
NULL
barcode
varchar(100)
YES
UNI
NULL
brand_id
bigint(20)
YES
MUL
NULL
is_active
tinyint(1)
YES
MUL
1
is_featured
tinyint(1)
YES
0
is_bestseller
tinyint(1)
YES
0
is_new
tinyint(1)
YES
0
stock_quantity
int(11)
YES
0
low_stock_threshold
int(11)
YES
5
stock_status
enum('in_stock','out_of_stock','on_backorder')
YES
MUL
in_stock
manage_stock
tinyint(1)
YES
1
allow_backorder
tinyint(1)
YES
0
total_sales
int(11)
YES
0
rating_average
decimal(3,2)
YES
MUL
0.00
rating_count
int(11)
YES
0
views_count
int(11)
YES
0
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
published_at
datetime
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_types;
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
NO
UNI
NULL
name
varchar(100)
NO
NULL
description
varchar(255)
YES
NULL
is_active
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_translations;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(500)
NO
MUL
NULL
short_description
text
YES
NULL
description
longtext
YES
NULL
specifications
longtext
YES
NULL
meta_title
varchar(255)
YES
NULL
meta_description
text
YES
NULL
meta_keywords
varchar(500)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_variant_attributes;
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
variant_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
attribute_value_id
bigint(20)
NO
MUL
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_variants;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
sku
varchar(100)
YES
UNI
NULL
barcode
varchar(100)
YES
UNI
NULL
stock_quantity
int(11)
YES
0
low_stock_threshold
int(11)
YES
5
is_active
tinyint(1)
YES
1
is_default
tinyint(1)
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
DESCRIBE product_stock_movements;
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
product_id
bigint(20)
NO
MUL
NULL
variant_id
bigint(20)
YES
MUL
NULL
change_quantity
int(11)
NO
NULL
type
enum('restock','sale','return','adjustment')
NO
NULL
reference_id
bigint(20)
YES
NULL
notes
text
YES
NULL
created_at
datetime
NO
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_stock_alerts;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
variant_id
bigint(20) unsigned
YES
MUL
NULL
user_id
int(11)
NO
MUL
NULL
email
varchar(191)
NO
NULL
is_notified
tinyint(1)
YES
MUL
0
notified_at
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
DESCRIBE product_reviews;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11) unsigned
NO
MUL
NULL
rating
tinyint(4)
NO
MUL
NULL
title
varchar(255)
YES
NULL
comment
text
YES
NULL
is_verified_purchase
tinyint(1)
YES
0
is_approved
tinyint(1)
YES
MUL
0
helpful_count
int(11)
YES
0
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_relations;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
related_product_id
bigint(20) unsigned
NO
MUL
NULL
relation_type
enum('related','upsell','cross_sell','alternative','accessory')
NO
MUL
NULL
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_questions;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
question
text
NO
NULL
is_approved
tinyint(1)
YES
MUL
0
helpful_count
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
DESCRIBE product_pricing;
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
product_id
bigint(20)
YES
MUL
NULL
variant_id
bigint(20)
YES
MUL
NULL
price
decimal(15,2)
NO
NULL
tax_rate
decimal(5,2)
YES
NULL
cost_price
decimal(15,2)
YES
NULL
compare_at_price
decimal(15,2)
YES
NULL
currency_code
char(3)
NO
MUL
NULL
pricing_type
enum('fixed','discount','auction','service')
YES
fixed
start_at
datetime
YES
NULL
end_at
datetime
YES
NULL
country_id
bigint(20)
YES
NULL
city_id
bigint(20)
YES
NULL
is_active
tinyint(1)
YES
MUL
1
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
DESCRIBE product_physical_attributes;
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
product_id
bigint(20) unsigned
NO
UNI
NULL
variant_id
bigint(20) unsigned
YES
UNI
NULL
weight
decimal(10,3)
YES
NULL
length
decimal(10,2)
YES
NULL
width
decimal(10,2)
YES
NULL
height
decimal(10,2)
YES
NULL
weight_unit
enum('kg','g','lb')
NO
kg
dimension_unit
enum('cm','mm','in')
NO
cm
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
DESCRIBE product_categories;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(11)
NO
PRI
NULL
auto_increment
product_id
bigint(20)
NO
MUL
NULL
category_id
bigint(20)
NO
MUL
NULL
is_primary
tinyint(1)
YES
MUL
0
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_values;
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
attribute_id
bigint(20)
NO
MUL
NULL
value
varchar(255)
NO
NULL
slug
varchar(255)
NO
MUL
NULL
sort_order
int(11)
YES
0
is_active
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_value_translations;
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
attribute_value_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
label
varchar(255)
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attributes;
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
slug
varchar(100)
NO
UNI
NULL
attribute_type_id
int(10) unsigned
NO
MUL
NULL
is_filterable
tinyint(1)
YES
MUL
1
is_visible
tinyint(1)
YES
1
is_required
tinyint(1)
YES
0
is_variation
tinyint(1)
YES
MUL
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
is_global
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_translations;
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
attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_assignments;
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
product_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
attribute_value_id
bigint(20)
YES
MUL
NULL
custom_value
varchar(255)
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
DESCRIBE product_answers;
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
question_id
bigint(20)
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
answer
text
NO
NULL
is_approved
tinyint(1)
YES
MUL
0
is_staff_answer
tinyint(1)
YES
0
helpful_count
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
DESCRIBE product_comparisons;
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
user_id
int(11)
NO
MUL
NULL
product_id
bigint(20)
NO
MUL
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_comparison_items;
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
comparison_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
added_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_bundles;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
bundle_name
varchar(255)
NO
NULL
bundle_name_ar
varchar(255)
NO
NULL
description
text
YES
NULL
description_ar
text
YES
NULL
bundle_image
varchar(500)
YES
NULL
original_total_price
decimal(15,2)
NO
NULL
bundle_price
decimal(15,2)
NO
NULL
discount_amount
decimal(15,2)
NO
NULL
discount_percentage
decimal(5,2)
YES
NULL
stock_quantity
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
sold_count
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
DESCRIBE product_bundle_items;
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
bundle_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
quantity
int(11)
YES
1
product_price
decimal(15,2)
NO
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE categories;
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
parent_id
bigint(20)
YES
MUL
NULL
slug
varchar(255)
NO
UNI
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
sort_order
int(11)
YES
MUL
0
is_active
tinyint(1)
YES
MUL
1
is_featured
tinyint(1)
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
DESCRIBE category_translations;
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
category_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
MUL
NULL
description
text
YES
NULL
slug
varchar(255)
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
meta_title
varchar(255)
YES
NULL
meta_description
text
YES
NULL
meta_keywords
varchar(500)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE category_attributes;
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
category_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
is_required
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
DESCRIBE category_attribute_translations;
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
category_attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
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
DESCRIBE orders;
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
order_number
varchar(100)
NO
UNI
NULL
user_id
int(11) unsigned
NO
MUL
NULL
cart_id
bigint(20)
YES
MUL
NULL
order_type
enum('online','pos','phone','wholesale')
YES
online
status
enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed')
YES
MUL
pending
payment_status
enum('pending','paid','partial','failed','refunded')
YES
MUL
pending
fulfillment_status
enum('unfulfilled','partial','fulfilled')
YES
unfulfilled
subtotal
decimal(15,2)
NO
NULL
tax_amount
decimal(15,2)
YES
0.00
shipping_cost
decimal(15,2)
YES
0.00
discount_amount
decimal(15,2)
YES
0.00
coupon_discount
decimal(15,2)
YES
0.00
loyalty_points_discount
decimal(15,2)
YES
0.00
wallet_amount_used
decimal(15,2)
YES
0.00
total_amount
decimal(15,2)
NO
NULL
grand_total
decimal(15,2)
NO
NULL
currency_code
varchar(8)
YES
SAR
coupon_code
varchar(100)
YES
NULL
loyalty_points_used
int(11)
YES
0
loyalty_points_earned
int(11)
YES
0
shipping_address_id
int(11)
YES
MUL
NULL
billing_address_id
int(11)
YES
MUL
NULL
delivery_company_id
bigint(20)
YES
MUL
NULL
estimated_delivery_date
date
YES
NULL
actual_delivery_date
datetime
YES
NULL
customer_notes
text
YES
NULL
internal_notes
text
YES
NULL
ip_address
varchar(45)
YES
NULL
user_agent
text
YES
NULL
is_gift
tinyint(1)
YES
0
gift_message
text
YES
NULL
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
confirmed_at
datetime
YES
NULL
shipped_at
datetime
YES
NULL
delivered_at
datetime
YES
NULL
cancelled_at
datetime
YES
NULL
cancellation_reason
text
YES
NULL
assigned_driver_id
bigint(20)
YES
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_items;
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
order_id
bigint(20) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20)
NO
MUL
NULL
product_variant_id
bigint(20)
YES
NULL
product_name
varchar(500)
NO
NULL
sku
varchar(100)
NO
NULL
quantity
int(11)
NO
NULL
unit_price
decimal(15,2)
NO
NULL
sale_price
decimal(15,2)
YES
NULL
discount_amount
decimal(15,2)
YES
0.00
tax_rate
decimal(5,2)
YES
0.00
tax_amount
decimal(15,2)
YES
0.00
subtotal
decimal(15,2)
NO
NULL
total
decimal(15,2)
NO
NULL
currency_code
varchar(8)
YES
SAR
commission_rate
decimal(5,2)
YES
0.00
commission_amount
decimal(15,2)
YES
0.00
selected_attributes
text
YES
NULL
special_instructions
text
YES
NULL
is_refunded
tinyint(1)
YES
0
refunded_quantity
int(11)
YES
0
refunded_amount
decimal(15,2)
YES
0.00
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_status_history;
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
order_id
bigint(20)
NO
MUL
NULL
status
enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed')
NO
MUL
NULL
notes
text
YES
NULL
notified_customer
tinyint(1)
YES
0
changed_by
int(11)
YES
MUL
NULL
ip_address
varchar(45)
YES
NULL
created_at
datetime
YES
MUL
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_reviews;
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
order_id
bigint(20)
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
vendor_id
bigint(20)
NO
MUL
NULL
delivery_company_id
bigint(20)
YES
MUL
NULL
overall_rating
tinyint(4)
NO
MUL
NULL
product_quality_rating
tinyint(4)
YES
NULL
delivery_rating
tinyint(4)
YES
NULL
service_rating
tinyint(4)
YES
NULL
comment
text
YES
NULL
is_approved
tinyint(1)
YES
0
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities;
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
parent_id
bigint(20) unsigned
YES
MUL
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
branch_code
varchar(50)
YES
NULL
user_id
int(11) unsigned
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
vendor_type
enum('product_seller','service_provider','both')
YES
product_seller
store_type
enum('individual','company','brand')
YES
individual
registration_number
varchar(100)
YES
NULL
tax_number
varchar(100)
YES
NULL
phone
varchar(45)
NO
NULL
mobile
varchar(45)
YES
NULL
email
varchar(191)
NO
NULL
website_url
varchar(500)
YES
NULL
status
enum('pending','approved','suspended','rejected')
YES
MUL
pending
suspension_reason
text
YES
NULL
is_verified
tinyint(1)
YES
0
joined_at
datetime
YES
current_timestamp()
approved_at
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discounts;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
type
varchar(50)
NO
NULL
code
varchar(100)
YES
MUL
NULL
auto_apply
tinyint(1)
NO
0
priority
int(11)
NO
0
is_stackable
tinyint(1)
NO
0
currency_code
char(3)
YES
NULL
max_redemptions
int(11)
YES
NULL
max_redemptions_per_user
int(11)
YES
NULL
current_redemptions
int(11)
NO
0
starts_at
datetime
YES
MUL
NULL
ends_at
datetime
YES
NULL
status
varchar(30)
NO
MUL
active
created_by
bigint(20) unsigned
YES
NULL
created_at
datetime
NO
current_timestamp()
updated_at
datetime
NO
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discount_actions;
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
discount_id
bigint(20) unsigned
NO
MUL
NULL
action_type
varchar(50)
NO
NULL
action_value
longtext
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discount_translations;
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
discount_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(10)
NO
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
terms_conditions
text
YES
NULL
marketing_badge
varchar(255)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discount_scopes;
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
discount_id
bigint(20) unsigned
NO
MUL
NULL
scope_type
varchar(50)
NO
MUL
NULL
scope_id
bigint(20) unsigned
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discount_exclusions;
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
discount_id
bigint(20) unsigned
NO
MUL
NULL
excluded_discount_id
bigint(20) unsigned
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE discount_conditions;
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
discount_id
bigint(20) unsigned
NO
MUL
NULL
condition_type
varchar(100)
NO
NULL
operator
varchar(20)
NO
=
condition_value
longtext
NO
NULL
Query results operations
  
Open new phpMyAdmin window
our SQL query has been executed successfully.
DESCRIBE entities;
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
parent_id
bigint(20) unsigned
YES
MUL
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
branch_code
varchar(50)
YES
NULL
user_id
int(11) unsigned
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
vendor_type
enum('product_seller','service_provider','both')
YES
product_seller
store_type
enum('individual','company','brand')
YES
individual
registration_number
varchar(100)
YES
NULL
tax_number
varchar(100)
YES
NULL
phone
varchar(45)
NO
NULL
mobile
varchar(45)
YES
NULL
email
varchar(191)
NO
NULL
website_url
varchar(500)
YES
NULL
status
enum('pending','approved','suspended','rejected')
YES
MUL
pending
suspension_reason
text
YES
NULL
is_verified
tinyint(1)
YES
0
joined_at
datetime
YES
current_timestamp()
approved_at
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
DESCRIBE entities_attributes;
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
slug
varchar(100)
NO
UNI
NULL
attribute_type
enum('text','number','select','boolean')
YES
text
is_required
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_attribute_translations;
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
attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_attribute_values;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
value
text
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_bank_accounts;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
bank_name
varchar(255)
NO
NULL
account_holder_name
varchar(255)
NO
NULL
account_number
varbinary(255)
NO
NULL
iban
varbinary(255)
YES
NULL
swift_code
varbinary(255)
YES
NULL
is_primary
tinyint(1)
YES
0
is_verified
tinyint(1)
YES
0
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_working_hours;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
day_of_week
tinyint(3) unsigned
NO
NULL
is_open
tinyint(1)
NO
1
open_time
time
YES
NULL
close_time
time
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
DESCRIBE entity_financial_balances;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
entity_id
bigint(20) unsigned
NO
PRI
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
total_transactions
int(11)
YES
0
total_sales_count
int(11)
YES
0
total_refunds_count
int(11)
YES
0
total_sales_amount
decimal(15,2)
YES
0.00
total_refunds_amount
decimal(15,2)
YES
0.00
net_sales
decimal(15,2)
YES
0.00
total_commission
decimal(15,2)
YES
0.00
total_vat
decimal(15,2)
YES
0.00
total_net_commission
decimal(15,2)
YES
0.00
total_invoiced
decimal(15,2)
YES
0.00
total_paid
decimal(15,2)
YES
0.00
total_balance
decimal(15,2)
YES
0.00
pending_balance
decimal(15,2)
YES
0.00
invoiced_balance
decimal(15,2)
YES
0.00
total_invoices
int(11)
YES
0
total_payments
int(11)
YES
0
total_credit_notes
int(11)
YES
0
last_transaction_date
datetime
YES
NULL
last_invoice_date
datetime
YES
NULL
last_payment_date
datetime
YES
NULL
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_logs;
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
user_id
int(11) unsigned
YES
MUL
NULL
entity_type
varchar(100)
NO
MUL
NULL
entity_id
bigint(20) unsigned
YES
NULL
action
enum('create','update','delete')
NO
NULL
changes
longtext
YES
NULL
ip_address
varchar(45)
YES
NULL
created_at
datetime
NO
current_timestamp()
updated_at
datetime
NO
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_payment_methods;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
account_email
varbinary(191)
YES
NULL
account_id
varbinary(255)
YES
NULL
is_active
tinyint(1)
YES
1
created_at
datetime
YES
current_timestamp()
payment_method_id
bigint(20) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_settings;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
entity_id
bigint(20) unsigned
NO
PRI
NULL
auto_accept_orders
tinyint(1)
YES
0
allow_cod
tinyint(1)
YES
0
min_order_amount
decimal(10,2)
YES
0.00
preparation_time_minutes
int(11)
YES
0
allow_online_booking
tinyint(1)
YES
0
booking_window_days
int(11)
YES
0
max_bookings_per_slot
int(11)
YES
0
booking_cancellation_allowed
tinyint(1)
YES
1
allow_preorders
tinyint(1)
YES
0
max_daily_orders
int(11)
YES
0
is_visible
tinyint(1)
YES
1
maintenance_mode
tinyint(1)
YES
0
show_reviews
tinyint(1)
YES
1
show_contact_info
tinyint(1)
YES
1
featured_in_app
tinyint(1)
YES
0
default_payment_method
varchar(50)
YES
NULL
allow_multiple_payment_methods
tinyint(1)
YES
1
delivery_radius_km
int(11)
YES
0
free_delivery_min_order
decimal(10,2)
YES
0.00
notification_preferences
longtext
YES
NULL
additional_settings
longtext
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
DESCRIBE entity_translations;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
branch_code
varchar(50)
YES
NULL
meta_title
varchar(255)
YES
NULL
meta_description
text
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
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_types;
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
code
varchar(50)
NO
UNI
NULL
name
varchar(150)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE addresses;
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
owner_type
enum('user','entity')
NO
MUL
NULL
owner_id
bigint(20) unsigned
NO
NULL
address_line1
varchar(255)
NO
NULL
address_line2
varchar(255)
YES
NULL
city_id
int(11)
YES
MUL
NULL
country_id
int(11)
YES
MUL
NULL
postal_code
varchar(20)
YES
NULL
latitude
decimal(10,7)
YES
NULL
longitude
decimal(11,7)
YES
NULL
is_primary
tinyint(1)
YES
0
created_at
timestamp
YES
current_timestamp()
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
primary_marker
varchar(100)
YES
UNI
NULL
VIRTUAL GENERATED
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE font_settings;
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
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
font_family
varchar(255)
NO
NULL
font_size
varchar(50)
YES
NULL
font_weight
varchar(50)
YES
NULL
line_height
varchar(50)
YES
NULL
category
enum('heading','body','button','navigation','other')
YES
other
is_active
tinyint(1)
YES
1
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
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE button_styles;
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
NO
MUL
NULL
theme_id
bigint(20)
YES
MUL
NULL
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
NULL
button_type
enum('primary','secondary','success','danger','warning','info','outline','link')
NO
MUL
NULL
background_color
varchar(7)
NO
NULL
text_color
varchar(7)
NO
NULL
border_color
varchar(7)
YES
NULL
border_width
int(11)
YES
0
border_radius
int(11)
YES
4
padding
varchar(50)
YES
10px 20px
font_size
varchar(50)
YES
14px
font_weight
varchar(50)
YES
normal
hover_background_color
varchar(7)
YES
NULL
hover_text_color
varchar(7)
YES
NULL
hover_border_color
varchar(7)
YES
NULL
is_active
tinyint(1)
YES
1
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
DESCRIBE themes;
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
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
description
text
YES
NULL
thumbnail_url
varchar(500)
YES
NULL
preview_url
varchar(500)
YES
NULL
version
varchar(50)
YES
1.0.0
author
varchar(255)
YES
NULL
is_active
tinyint(1)
YES
MUL
0
is_default
tinyint(1)
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
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE card_styles;
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
NO
MUL
NULL
theme_id
bigint(20)
YES
MUL
NULL
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
NULL
card_type
enum('product','category','vendor','blog','feature','testimonial','other')
NO
MUL
NULL
background_color
varchar(7)
YES
#FFFFFF
border_color
varchar(7)
YES
#E0E0E0
border_width
int(11)
YES
1
border_radius
int(11)
YES
8
shadow_style
varchar(100)
YES
none
padding
varchar(50)
YES
16px
hover_effect
enum('none','lift','zoom','shadow','border','brightness')
YES
none
text_align
enum('left','center','right')
YES
left
image_aspect_ratio
varchar(50)
YES
1:1
is_active
tinyint(1)
YES
1
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
DESCRIBE color_settings;
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
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
color_value
varchar(7)
NO
NULL
category
enum('primary','secondary','accent','background','text','border','status','other')
YES
MUL
other
is_active
tinyint(1)
YES
1
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
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE design_settings;
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
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
setting_value
text
YES
NULL
setting_type
enum('text','number','color','image','boolean','select','json')
YES
text
category
enum('layout','header','footer','sidebar','homepage','product','cart','checkout','other')
YES
MUL
other
is_active
tinyint(1)
YES
1
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
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE permissions;
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
1
key_name
varchar(100)
NO
NULL
display_name
varchar(150)
NO
NULL
description
text
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
is_active
tinyint(1)
YES
1
module
varchar(100)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE roles;
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
key_name
varchar(100)
NO
NULL
display_name
varchar(150)
NO
NULL
created_at
timestamp
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE role_permissions;
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
NO
MUL
NULL
role_id
bigint(20) unsigned
NO
MUL
NULL
permission_id
bigint(20) unsigned
NO
MUL
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE resource_permissions;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(11)
NO
PRI
NULL
auto_increment
permission_id
bigint(20) unsigned
NO
MUL
NULL
role_id
bigint(20) unsigned
NO
NULL
tenant_id
int(10) unsigned
NO
MUL
1
resource_type
varchar(50)
NO
NULL
can_view_all
tinyint(1)
NO
0
can_view_own
tinyint(1)
NO
0
can_view_tenant
tinyint(1)
NO
0
can_create
tinyint(1)
NO
0
can_edit_all
tinyint(1)
NO
0
can_edit_own
tinyint(1)
NO
0
can_delete_all
tinyint(1)
NO
0
can_delete_own
tinyint(1)
NO
0
created_at
datetime
YES
current_timestamp()
tenant_key
int(10) unsigned
YES
MUL
NULL
STORED GENERATED
Query results operations
  
Open new phpMyAdmin window

Your SQL query has been executed successfully.
DESCRIBE jobs;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
job_title
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
job_type
enum('full_time','part_time','contract','temporary','internship','freelance','remote')
NO
MUL
NULL
employment_type
enum('permanent','temporary','seasonal')
YES
permanent
application_form_type
enum('simple','custom','external')
NO
simple
external_application_url
varchar(500)
YES
NULL
experience_level
enum('entry','junior','mid','senior','executive','director')
NO
MUL
NULL
category
varchar(100)
YES
NULL
department
varchar(100)
YES
NULL
positions_available
int(11)
YES
1
salary_min
decimal(15,2)
YES
NULL
salary_max
decimal(15,2)
YES
NULL
salary_currency
varchar(8)
YES
SAR
salary_period
enum('hourly','daily','weekly','monthly','yearly')
YES
monthly
salary_negotiable
tinyint(1)
YES
0
country_id
int(11)
NO
MUL
NULL
city_id
int(11)
YES
MUL
NULL
work_location
varchar(255)
YES
NULL
is_remote
tinyint(1)
YES
0
status
enum('draft','published','closed','filled','cancelled')
YES
MUL
draft
application_deadline
datetime
YES
MUL
NULL
start_date
date
YES
NULL
views_count
int(11)
YES
0
applications_count
int(11)
YES
0
is_featured
tinyint(1)
YES
MUL
0
is_urgent
tinyint(1)
YES
0
created_by
int(11)
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
published_at
datetime
YES
NULL
closed_at
datetime
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_translations;
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
job_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
job_title
varchar(255)
NO
NULL
description
longtext
NO
NULL
requirements
text
YES
NULL
responsibilities
text
YES
NULL
benefits
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_applications;
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
job_id
bigint(20)
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
full_name
varchar(255)
NO
NULL
email
varchar(191)
NO
NULL
phone
varchar(45)
NO
NULL
current_position
varchar(255)
YES
NULL
current_company
varchar(255)
YES
NULL
years_of_experience
int(11)
YES
NULL
expected_salary
decimal(15,2)
YES
NULL
currency_code
varchar(8)
YES
SAR
notice_period
int(11)
YES
NULL
cv_file_url
varchar(500)
NO
NULL
cover_letter
text
YES
NULL
portfolio_url
varchar(500)
YES
NULL
linkedin_url
varchar(500)
YES
NULL
status
enum('submitted','under_review','shortlisted','interview_scheduled','interviewed','offered','accepted','rejected','withdrawn')
YES
MUL
submitted
rating
tinyint(4)
YES
NULL
notes
text
YES
NULL
reviewed_by
int(11)
YES
MUL
NULL
reviewed_at
datetime
YES
NULL
ip_address
varchar(45)
YES
NULL
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_application_questions;
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
job_id
bigint(20)
NO
MUL
NULL
question_text
text
NO
NULL
question_type
enum('text','textarea','select','multiselect','radio','checkbox','file','date','number')
YES
text
options
text
YES
NULL
is_required
tinyint(1)
YES
0
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_application_answers;
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
application_id
bigint(20)
NO
MUL
NULL
question_id
bigint(20)
NO
MUL
NULL
answer_text
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_interviews;
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
application_id
bigint(20)
NO
MUL
NULL
interview_type
enum('phone','video','in_person','technical','hr','final')
NO
NULL
interview_date
datetime
NO
MUL
NULL
interview_duration
int(11)
YES
60
location
varchar(500)
YES
NULL
meeting_link
varchar(500)
YES
NULL
interviewer_name
varchar(255)
YES
NULL
interviewer_email
varchar(191)
YES
NULL
status
enum('scheduled','confirmed','completed','cancelled','rescheduled','no_show')
YES
MUL
scheduled
feedback
text
YES
NULL
rating
tinyint(4)
YES
NULL
notes
text
YES
NULL
created_by
int(11)
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
DESCRIBE job_alerts;
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
user_id
int(11)
NO
MUL
NULL
alert_name
varchar(255)
NO
NULL
keywords
varchar(500)
YES
NULL
job_type
varchar(100)
YES
NULL
experience_level
varchar(100)
YES
NULL
country_id
int(11)
YES
MUL
NULL
city_id
int(11)
YES
MUL
NULL
salary_min
decimal(15,2)
YES
NULL
is_active
tinyint(1)
YES
MUL
1
frequency
enum('instant','daily','weekly')
YES
daily
last_sent_at
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
DESCRIBE job_skills;
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
job_id
bigint(20)
NO
MUL
NULL
skill_name
varchar(100)
NO
MUL
NULL
proficiency_level
enum('basic','intermediate','advanced','expert')
YES
intermediate
is_required
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE languages;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
code
varchar(8)
NO
PRI
NULL
name
varchar(100)
NO
NULL
direction
varchar(3)
NO
ltr
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_categories;
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
NULL
parent_id
bigint(20)
YES
MUL
NULL
slug
varchar(255)
NO
UNI
NULL
sort_order
int(11)
YES
0
is_active
tinyint(1)
YES
1
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE job_category_translations;
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
category_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
Query results operations
  
Open new phpMyAdmin window
