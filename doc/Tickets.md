الجداول
CREATE TABLE ticket_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tenant_id INT UNSIGNED NULL,

    parent_id BIGINT UNSIGNED NULL,

    priority_level TINYINT DEFAULT 3,

    is_active TINYINT(1) DEFAULT 1,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ticket_cat_parent
        FOREIGN KEY (parent_id) REFERENCES ticket_categories(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_ticket_cat_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE CASCADE
);
CREATE TABLE ticket_category_translations (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    category_id BIGINT UNSIGNED NOT NULL,

    language_code VARCHAR(10) NOT NULL,

    name VARCHAR(255) NOT NULL,

    description TEXT NULL,

    UNIQUE KEY uniq_category_language (category_id, language_code),

    CONSTRAINT fk_ticket_category_translation
        FOREIGN KEY (category_id) REFERENCES ticket_categories(id)


        CREATE TABLE support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tenant_id INT UNSIGNED NOT NULL,

    ticket_number VARCHAR(100) UNIQUE,

    user_id INT UNSIGNED NOT NULL,
    entity_id BIGINT UNSIGNED NULL,

    order_id BIGINT UNSIGNED NULL,

    category_id BIGINT UNSIGNED NOT NULL,

    subject VARCHAR(500) NOT NULL,
    description MEDIUMTEXT NOT NULL,

    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',

    status ENUM(
        'open',
        'pending',
        'awaiting_customer',
        'awaiting_vendor',
        'in_progress',
        'resolved',
        'closed',
        'cancelled'
    ) DEFAULT 'open',

    assigned_to INT UNSIGNED NULL,

    attachments JSON NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_ticket_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_entity
        FOREIGN KEY (entity_id) REFERENCES entities(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_ticket_category
        FOREIGN KEY (category_id) REFERENCES ticket_categories(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ticket_assigned
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE SET NULL
);


CREATE TABLE ticket_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tenant_id INT UNSIGNED NOT NULL,

    ticket_id BIGINT UNSIGNED NOT NULL,

    sender_user_id INT UNSIGNED NOT NULL,

    message TEXT NOT NULL,

    is_internal TINYINT(1) DEFAULT 0,

    attachments JSON NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ticket_msg_ticket
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_msg_user
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_msg_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE CASCADE
);

CREATE TABLE ticket_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    ticket_id BIGINT UNSIGNED NOT NULL,

    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,

    changed_by INT UNSIGNED NULL,

    notes TEXT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ticket_status_ticket
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_status_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE SET NULL
);
DESCRIBE users;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(11) unsigned
NO
PRI
NULL
auto_increment
username
varchar(50)
NO
UNI
NULL
email
varchar(191)
NO
UNI
NULL
password_hash
varchar(255)
YES
NULL
preferred_language
varchar(8)
YES
MUL
NULL
phone
varchar(45)
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
timezone_id
int(10) unsigned
YES
MUL
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


        ON DELETE CASCADE
);
/
