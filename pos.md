DESCRIBE pos_sessions;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
cashier_user_id
int(11) unsigned
YES
MUL
NULL
opened_at
datetime
YES
current_timestamp()
closed_at
datetime
YES
NULL
opening_balance
decimal(15,2)
YES
NULL
closing_balance
decimal(15,2)
YES
NULL
total_cash
decimal(15,2)
YES
0.00
total_card
decimal(15,2)
YES
0.00
status
enum('open','closed')
YES
open
total_sales
decimal(15,2)
YES
NULL
STORED GENERATED
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE tenant_users;
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
user_id
int(11) unsigned
NO
MUL
NULL
role_id
bigint(20) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
joined_at
datetime
YES
current_timestamp()
is_active
tinyint(1)
NO
0
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE tenants;
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
name
varchar(150)
NO
NULL
domain
varchar(255)
YES
UNI
NULL
owner_user_id
int(11) unsigned
NO
MUL
NULL
status
enum('active','suspended')
NO
active
created_at
timestamp
YES
current_timestamp()
updated_at
timestamp
YES
NULL
on update current_timestamp()
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
tenant_id
int(10) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
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
bigint(20) unsigned
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
pos_session_id
bigint(20)
YES
NULL
cashier_user_id
int(11)
YES
NULL
branch_entity_id
bigint(20)
YES
MUL
NULL
sales_channel
enum('online','pos','mobile_app','call_center','marketplace')
YES
online
delivery_entity_id
bigint(20) unsigned
YES
MUL
NULL
delivery_zone_id
bigint(20) unsigned
YES
NULL
auction_id
bigint(20) unsigned
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
tenant_id
int(10) unsigned
NO
NULL
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
inventory_entity_id
bigint(20)
YES
NULL

