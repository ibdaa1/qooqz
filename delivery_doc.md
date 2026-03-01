DESCRIBE delivery_zones;
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
provider_id
bigint(20) unsigned
NO
MUL
NULL
zone_name
varchar(255)
NO
NULL
zone_type
enum('city','district','radius','polygon')
NO
MUL
NULL
city_id
int(11)
YES
MUL
NULL
center_lat
decimal(10,7)
YES
NULL
center_lng
decimal(11,7)
YES
NULL
radius_km
decimal(6,2)
YES
NULL
delivery_fee
decimal(15,2)
NO
NULL
free_delivery_over
decimal(15,2)
YES
NULL
min_order_value
decimal(15,2)
YES
NULL
estimated_minutes
int(10) unsigned
NO
45
is_active
tinyint(1)
YES
MUL
1
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE delivery_providers;
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
tenant_user_id
bigint(20) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
provider_type
enum('company','entity_driver','independent_driver')
NO
MUL
NULL
vehicle_type
enum('bike','car','van','truck')
NO
bike
license_number
varchar(100)
YES
NULL
is_online
tinyint(1)
NO
MUL
0
is_active
tinyint(1)
YES
MUL
1
rating
decimal(3,2) unsigned
NO
0.00
total_deliveries
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
DESCRIBE delivery_orders;
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
order_id
bigint(20) unsigned
NO
MUL
NULL
provider_id
bigint(20) unsigned
YES
MUL
NULL
pickup_address_id
bigint(20) unsigned
NO
MUL
NULL
dropoff_address_id
bigint(20) unsigned
NO
MUL
NULL
delivery_zone_id
bigint(20) unsigned
YES
MUL
NULL
delivery_status
enum('pending','assigned','accepted','picked_up','on_the_way','delivered','cancelled')
YES
MUL
pending
cancelled_by
enum('customer','provider','admin','system')
YES
NULL
cancellation_reason
varchar(255)
YES
NULL
delivery_fee
decimal(15,2)
YES
0.00
calculated_fee
decimal(15,2)
NO
0.00
provider_payout
decimal(15,2)
NO
0.00
assigned_at
datetime
YES
NULL
rejection_count
tinyint(3) unsigned
NO
0
picked_up_at
datetime
YES
NULL
delivered_at
datetime
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
DESCRIBE driver_locations;
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
provider_id
bigint(20) unsigned
NO
UNI
NULL
latitude
decimal(10,7)
NO
NULL
longitude
decimal(11,7)
NO
NULL
location
point
NO
MUL
NULL
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE delivery_tracking;
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
delivery_order_id
bigint(20) unsigned
NO
MUL
NULL
provider_id
bigint(20) unsigned
YES
NULL
latitude
decimal(10,7)
NO
NULL
longitude
decimal(11,7)
NO
NULL
status_note
varchar(255)
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
DESCRIBE provider_zones;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
provider_id
bigint(20) unsigned
NO
PRI
NULL
zone_id
bigint(20) unsigned
NO
PRI
NULL
assigned_at
timestamp
NO
current_timestamp()
is_active
tinyint(1)
NO
1
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
DESCRIBE cities;
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
country_id
int(11)
NO
MUL
NULL
name
varchar(200)
NO
NULL
state
varchar(200)
YES
NULL
latitude
decimal(10,7)
YES
MUL
NULL
longitude
decimal(11,7)
YES
NULL
location
point
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE countries;
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
iso2
char(2)
YES
MUL
NULL
iso3
char(3)
YES
NULL
name
varchar(200)
NO
NULL
currency_code
varchar(8)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE currencies;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
smallint(5) unsigned
NO
PRI
NULL
auto_increment
code
char(3)
NO
UNI
NULL
name
varchar(50)
NO
NULL
symbol
varchar(10)
NO
NULL
symbol_position
enum('before','after')
YES
before
decimal_places
tinyint(3) unsigned
YES
2
created_at
datetime
YES
current_timestamp()

الملفات مثل
admin/fragments/delivery.php
/admin/assets/js/pages/delivery.js 
admin/assets/css/pages/delivery.css
htdocs/api/v1/models/
Contracts/
repositories/
validators/
services/
controllers/
api/routes.delivery_zones.php
api/routes.delivery_providers.php
api/routes.delivery_orders.php
api/routes.driver_locations.php
api/routes.delivery_tracking.php
api/routes.provider_zones.php
