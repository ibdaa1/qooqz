 ولديك ملف //https://github.com/ibdaa1/qooqz/blob/copilot/update-delivery-management-files/firebase-messaging-sw.js به مفتاح الاشعارات احتاج ملفات/
https://github.com/ibdaa1/qooqz/blob/copilot/update-delivery-management-files/api/shared/helpers/notification.php تم تحديثه
https://github.com/ibdaa1/qooqz/tree/copilot/update-delivery-management-files/admin/assets/js موجود
https://github.com/ibdaa1/qooqz/blob/copilot/update-delivery-management-files/admin/includes/footer.php تم تحديثه
https://github.com/ibdaa1/qooqz/blob/copilot/update-delivery-management-files/api/shared/config/constants.php تم تحديثه ووضع الملفات به 

تعديلهم لارسال اشعارات
نحتاج ارسال اشعارات نحتاج جعل لكل مستاجر ID 
api/notification_channels
api/notification_counters
api/notification_deliveries
api/notification_types
api/notifications
api/user_devices
admin/fragments/notifications.php
admin/assets/js/pages/notifications.js
admin/assets/css/pages/notifications.css
htdocs/firebase-messaging-sw.js
htdocs/api/shared/helpers/notification.php

هذا لدي//Your SQL query has been executed successfully.
DESCRIBE notification_channels;
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
is_active
tinyint(1)
YES
1
created_at
timestamp
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_counters;
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
recipient_type
enum('user','entity','tenant')
NO
NULL
recipient_id
bigint(20) unsigned
NO
NULL
unread_count
int(11)
YES
0
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_deliveries;
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
notification_id
bigint(20) unsigned
NO
MUL
NULL
channel_id
int(10) unsigned
NO
MUL
NULL
delivery_status
enum('pending','sent','failed')
YES
pending
attempts
int(11)
YES
0
sent_at
datetime
YES
NULL
error_message
text
YES
NULL
created_at
timestamp
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_types;
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
varchar(150)
NO
NULL
description
text
YES
NULL
is_active
tinyint(1)
NO
1
default_template
longtext
YES
NULL
created_at
timestamp
YES
current_timestamp()
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notifications;
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
sender_entity_id
bigint(20) unsigned
YES
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
title
varchar(500)
NO
NULL
message
mediumtext
NO
NULL
sent_at
timestamp
YES
current_timestamp()
data
longtext
YES
NULL
notification_type_id
int(10) unsigned
YES
MUL
NULL
priority
enum('low','normal','high','urgent')
YES
normal
expires_at
datetime
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE user_devices;
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
user_id
int(11)
NO
MUL
NULL
fcm_token
text
NO
UNI
NULL
device_type
varchar(20)
YES
web
device_name
varchar(100)
YES
NULL
user_agent
text
YES
NULL
ip
varchar(45)
YES
NULL
last_seen_at
datetime
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
NULL
on update current_timestamp()

