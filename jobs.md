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
bigint(20) unsigned
NO
PRI
NULL
auto_increment
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
