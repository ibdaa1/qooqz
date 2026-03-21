{
    "summary": {
        "ok": false,
        "timestamp": "2026-03-21 15:11:42",
        "total_time_ms": 282.1,
        "total_tests": 15,
        "passed": 12,
        "failed": 3,
        "score": "80%",
        "security_checklist": {
            "IDOR blocked": "PASS",
            "Tenant escape blocked": "PASS",
            "CSRF enforced": "PASS",
            "XSS escaped": "PASS",
            "Permission escalation": "PASS",
            "Tenant isolation": "FAIL",
            "DB indexes": "FAIL",
            "Load test": "PASS",
            "Caching": "PASS",
            "Missing endpoints": "PASS",
            "Fail-safe deny": "PASS"
        }
    },
    "results": [
        {
            "test": "DB Connection",
            "status": "PASS",
            "detail": "PDO متصل بنجاح",
            "data": []
        },
        {
            "test": "IDOR Blocked",
            "status": "PASS",
            "detail": "جميع مستودعات البيانات تُقيّد tenant_id بشكل صحيح",
            "data": {
                "images_tenant_999": true,
                "themes_tenant_999": true,
                "products_tenant_999": true,
                "no_tenant_scope_in_raw_sql": "تحذير: الاستعلام المباشر بدون tenant_id ممكن — الحماية تكون في طبقة التطبيق",
                "images_repo_has_tenant_scope": true,
                "themes_repo_has_tenant_scope": true
            }
        },
        {
            "test": "Tenant Escape Blocked",
            "status": "PASS",
            "detail": "جميع المستودعات المتحققة تُقيّد tenant_id",
            "data": {
                "images": "SCOPED ✔",
                "themes": "SCOPED ✔",
                "ads": "SCOPED ✔",
                "products": "SCOPED ✔",
                "orders": "SCOPED ✔",
                "ad_campaigns": "SCOPED ✔",
                "ad_placements": "SCOPED ✔",
                "escrow": "SCOPED ✔",
                "cross_tenant_db_check": "tenant1_count=55 | tenant2_count=0"
            }
        },
        {
            "test": "CSRF Enforced",
            "status": "PASS",
            "detail": "حماية CSRF مفعّلة ومتحققة",
            "data": {
                "csrf_file_exists": "موجود: /home/hcsfcsto/public_html/api/shared/helpers/CSRF.php",
                "has_token_generation": true,
                "has_validation": true,
                "has_session_storage": true,
                "has_time_expiry": true,
                "token_consistent": true,
                "token_length": 64,
                "token_entropy_ok": true,
                "validate_valid": true,
                "validate_invalid": true,
                "validate_empty": true,
                "routes_with_csrf_ref": "2 من 166 ملف مسار"
            }
        },
        {
            "test": "XSS Escaped",
            "status": "PASS",
            "detail": "جميع المخرجات تُهرَّب، استعلامات مُعدَّة فقط",
            "data": {
                "escaped_38fff51c": {
                    "input": "<script>alert(1)</script>",
                    "escaped": "&lt;script&gt;alert(1)&lt;/script&gt;",
                    "safe": true
                },
                "escaped_33f83b40": {
                    "input": "\"><script>alert(1)</script>",
                    "escaped": "&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;",
                    "safe": true
                },
                "escaped_30802023": {
                    "input": "'; DROP TABLE users; --",
                    "escaped": "&apos;; DROP TABLE users; --",
                    "safe": true
                },
                "escaped_afe47532": {
                    "input": "<img src=x onerror=alert(1)>",
                    "escaped": "&lt;img src=x onerror=alert(1)&gt;",
                    "safe": true
                },
                "escaped_a2054297": {
                    "input": "javascript:alert(1)",
                    "escaped": "javascript:alert(1)",
                    "safe": true
                },
                "escaped_19882fed": {
                    "input": "<svg onload=alert(1)>",
                    "escaped": "&lt;svg onload=alert(1)&gt;",
                    "safe": true
                },
                "escaped_3f1c4e4b": {
                    "input": "&lt;script&gt;",
                    "escaped": "&amp;lt;script&amp;gt;",
                    "safe": true
                },
                "escaped_379d3408": {
                    "input": "{{7*7}}",
                    "escaped": "{{7*7}}",
                    "safe": true
                },
                "escaped_5c4bd6a2": {
                    "input": "${7*7}",
                    "escaped": "${7*7}",
                    "safe": true
                },
                "admin_fragments_with_escaping": "36 من 40",
                "media_studio_uses_escaping": true,
                "repos_using_prepared_stmts": 150,
                "repos_with_raw_queries": 0
            }
        },
        {
            "test": "Permission Escalation Blocked",
            "status": "PASS",
            "detail": "نظام RBAC مفعّل وصلاحيات مُطبَّقة",
            "data": {
                "rbac_file_exists": true,
                "has_permission_check": true,
                "has_role_check": true,
                "has_deny_default": true,
                "has_tenant_scope": true,
                "routes_with_auth": "147 من 166",
                "routes_without_auth": [
                    "country_taxes.php",
                    "coupons.php",
                    "product_attribute_assignments.php",
                    "product_attribute_translations.php",
                    "product_attribute_value_translations.php",
                    "product_attribute_values.php",
                    "product_attributes.php",
                    "product_categories.php",
                    "product_physical_attributes.php",
                    "product_reviews.php",
                    "product_stock_alerts.php",
                    "product_types.php",
                    "queues.php",
                    "reviews.php",
                    "services.php",
                    "support.php",
                    "users.php",
                    "users_account.php",
                    "wallet.php"
                ],
                "media_studio_canCreate": true,
                "media_studio_isSuperAdmin": true,
                "media_studio_canDelete": true
            }
        },
        {
            "test": "Tenant Isolation Full",
            "status": "FAIL",
            "detail": "تحذير: بعض الجداول لا تحتوي tenant_id",
            "data": {
                "table_images_has_tenant_id": "موجود ✔",
                "table_themes_has_tenant_id": "موجود ✔",
                "table_products_has_tenant_id": "موجود ✔",
                "table_orders_has_tenant_id": "موجود ✔",
                "table_ads_has_tenant_id": "غير موجود ✗",
                "table_ad_campaigns_has_tenant_id": "موجود ✔",
                "table_ad_placements_has_tenant_id": "موجود ✔",
                "table_categories_has_tenant_id": "موجود ✔",
                "table_users_has_tenant_id": "غير موجود ✗",
                "table_tenants_has_tenant_id": "غير موجود ✗",
                "table_escrow_transactions_has_tenant_id": "موجود ✔",
                "images_t1_count": 55,
                "images_t2_count": 0,
                "images_total_count": 55,
                "tenant_id_indexed": {
                    "images": true,
                    "themes": true,
                    "products": true
                }
            }
        },
        {
            "test": "DB Indexes Present",
            "status": "FAIL",
            "detail": "تحذير: بعض الفهارس مفقودة — أداء الاستعلامات قد يتدهور",
            "data": {
                "images.tenant_id": "فهرس موجود ✔",
                "images.owner_id": "فهرس موجود ✔",
                "themes.tenant_id": "فهرس موجود ✔",
                "orders.tenant_id": "فهرس موجود ✔",
                "ads.tenant_id": "فهرس مفقود ✗",
                "ad_campaigns.tenant_id": "فهرس موجود ✔",
                "products.tenant_id": "فهرس موجود ✔",
                "escrow_transactions.tenant_id": "فهرس موجود ✔",
                "users.email": "فهرس موجود ✔",
                "unique_themes.slug": "فهرس فريد ✔",
                "unique_ad_placements.code": "فهرس فريد ✔",
                "unique_users.email": "فهرس فريد ✔"
            }
        },
        {
            "test": "Load Test",
            "status": "PASS",
            "detail": "متوسط الاستجابة: 0.39ms (الهدف: ≤180ms)",
            "data": {
                "images_list": {
                    "avg_ms": 0.51,
                    "min_ms": 0.44,
                    "max_ms": 0.8,
                    "iterations": 10,
                    "passed": true
                },
                "themes_active": {
                    "avg_ms": 0.45,
                    "min_ms": 0.38,
                    "max_ms": 0.59,
                    "iterations": 10,
                    "passed": true
                },
                "simple_ping": {
                    "avg_ms": 0.21,
                    "min_ms": 0.18,
                    "max_ms": 0.25,
                    "iterations": 10,
                    "passed": true
                },
                "overall_avg_ms": 0.39,
                "target_ms": 180
            }
        },
        {
            "test": "Caching Layer",
            "status": "PASS",
            "detail": "التخزين المؤقت مُهيَّأ (جزئي أو كامل)",
            "data": {
                "redis_available": false,
                "apcu_available": false,
                "file_cache_dir": "/home/hcsfcsto/public_html/api/storage/cache",
                "file_cache_available": false,
                "cache_manager_exists": true,
                "cache_manager_has_get": true,
                "cache_manager_has_set": true,
                "cache_manager_has_redis": true,
                "cache_manager_has_file": true,
                "redis_helper_exists": true,
                "cache_infrastructure_ready": true,
                "summary": "بنية التخزين المؤقت جاهزة (جزئي)"
            }
        },
        {
            "test": "Missing Endpoints Check",
            "status": "PASS",
            "detail": "جميع المسارات المطلوبة موجودة",
            "data": {
                "required_routes_found": 26,
                "required_routes_missing": [],
                "total_route_files": 166,
                "route_files_list": [
                    "Role_permissions.php",
                    "account.php",
                    "ad_campaigns.php",
                    "ad_payments.php",
                    "ad_placement_items.php",
                    "ad_placements.php",
                    "ad_translations.php",
                    "addresses.php",
                    "admin.php",
                    "ads.php",
                    "attribute_types.php",
                    "attributes.php",
                    "auction_activity_log.php",
                    "auction_bids.php",
                    "auction_translations.php",
                    "auction_watchers.php",
                    "auctions.php",
                    "audit_logs.php",
                    "auth.php",
                    "auto_bid_settings.php",
                    "bad_words.php",
                    "banners.php",
                    "brands.php",
                    "button_styles.php",
                    "card_styles.php",
                    "cart_events.php",
                    "cart_items.php",
                    "carts.php",
                    "categories-tenants.php",
                    "categories.php",
                    "category_attributes.php",
                    "cities.php",
                    "color_settings.php",
                    "commission_credit_notes.php",
                    "commission_invoice_items.php",
                    "commission_invoices.php",
                    "commission_payments.php",
                    "commission_transactions.php",
                    "countries.php",
                    "country_taxes.php",
                    "coupons.php",
                    "currencies.php",
                    "delivery_orders.php",
                    "delivery_providers.php",
                    "delivery_tracking.php",
                    "delivery_zones.php",
                    "design_settings.php",
                    "diagnostic.php",
                    "discount_actions.php",
                    "discount_conditions.php",
                    "discount_exclusions.php",
                    "discount_redemptions.php",
                    "discount_scopes.php",
                    "discount_translations.php",
                    "discounts.php",
                    "driver_locations.php",
                    "entities.php",
                    "entities_attribute_values.php",
                    "entities_attributes.php",
                    "entities_working_hours.php",
                    "entity_bank_accounts.php",
                    "entity_financial_balances.php",
                    "entity_payment_methods.php",
                    "entity_settings.php",
                    "entity_translations.php",
                    "entity_types.php",
                    "escrow_dispute_evidence.php",
                    "escrow_disputes.php",
                    "escrow_ledger.php",
                    "escrow_payments.php",
                    "escrow_status_history.php",
                    "escrow_transactions.php",
                    "flash_sale_products.php",
                    "flash_sales.php",
                    "flash_sales_translations.php",
                    "font_settings.php",
                    "generate_certificate_files.php",
                    "generate_qr.php",
                    "health.php",
                    "homepage_sections.php",
                    "image-types.php",
                    "images.php",
                    "independent_drivers.php",
                    "job_alerts.php",
                    "job_application_answers.php",
                    "job_application_questions.php",
                    "job_applications.php",
                    "job_categories.php",
                    "job_interviews.php",
                    "job_skills.php",
                    "jobs.php",
                    "languages.php",
                    "media.php",
                    "mobile.php",
                    "municipality_officials.php",
                    "notification_channels.php",
                    "notification_counters.php",
                    "notification_deliveries.php",
                    "notification_types.php",
                    "notifications.php",
                    "order_items.php",
                    "order_reviews.php",
                    "order_status_history.php",
                    "orders.php",
                    "payment_methods.php",
                    "payments.php",
                    "permissions.php",
                    "pos_sessions.php",
                    "print_certificate.php",
                    "product_attribute_assignments.php",
                    "product_attribute_translations.php",
                    "product_attribute_value_translations.php",
                    "product_attribute_values.php",
                    "product_attributes.php",
                    "product_bundle-items.php",
                    "product_bundles.php",
                    "product_categories.php",
                    "product_comparison_items.php",
                    "product_comparisons.php",
                    "product_meta.php",
                    "product_physical_attributes.php",
                    "product_pricing.php",
                    "product_questions.php",
                    "product_relations.php",
                    "product_reviews.php",
                    "product_stock_alerts.php",
                    "product_stock_movements.php",
                    "product_translations.php",
                    "product_types.php",
                    "product_variant_attributes.php",
                    "product_variants.php",
                    "products.php",
                    "provider_zones.php",
                    "public.php",
                    "queues.php",
                    "resource_permissions.php",
                    "resource_permissions_debug_snippet.php",
                    "return_items.php",
                    "return_status_history.php",
                    "returns.php",
                    "reviews.php",
                    "roles.php",
                    "seo_meta.php",
                    "services.php",
                    "shipping.php",
                    "subscription_invoices.php",
                    "subscription_payments.php",
                    "subscription_plan_translations.php",
                    "subscription_plans.php",
                    "subscriptions.php",
                    "support.php",
                    "support_tickets.php",
                    "system_settings.php",
                    "tenant_domains.php",
                    "tenant_users.php",
                    "tenants.php",
                    "themes.php",
                    "ticket_categories.php",
                    "ticket_messages.php",
                    "ticket_status_history.php",
                    "timezones.php",
                    "units.php",
                    "user.php",
                    "users.php",
                    "users_account.php",
                    "wallet.php"
                ]
            }
        },
        {
            "test": "Fail-safe Deny",
            "status": "PASS",
            "detail": "النظام يرفض الطلبات غير المصرح بها بشكل افتراضي",
            "data": {
                "has_http_response_code": true,
                "has_403": true,
                "has_notFound": true,
                "has_error_method": true,
                "auth_has_exit": true,
                "auth_has_session_check": true,
                "rbac_returns_false_on_no_perm": true,
                "rbac_has_deny_default": true,
                "bootstrap_has_error_handler": false,
                "routes_with_db_failsafe": "36 من 166",
                "images_route_db_check": true
            }
        },
        {
            "test": "SQL Injection Prevention",
            "status": "FAIL",
            "detail": "تحذير: قد تكون هناك ثغرات حقن SQL",
            "data": {
                "payload_1e54e119": {
                    "payload": "' OR '1'='1",
                    "rows_returned": 0,
                    "safe": true
                },
                "payload_30802023": {
                    "payload": "'; DROP TABLE users; --",
                    "rows_returned": 0,
                    "safe": true
                },
                "payload_713b2c77": {
                    "payload": "1' UNION SELECT * FROM users --",
                    "rows_returned": 55,
                    "safe": false
                },
                "payload_80d8b2bb": {
                    "payload": "admin'--",
                    "rows_returned": 0,
                    "safe": true
                },
                "payload_54ce0755": {
                    "payload": "1 OR 1=1",
                    "rows_returned": 55,
                    "safe": false
                }
            }
        },
        {
            "test": "Session Security",
            "status": "PASS",
            "detail": "إعدادات الجلسة آمنة",
            "data": {
                "use_strict_mode": false,
                "cookie_httponly": false,
                "cookie_samesite": "غير محدد",
                "use_only_cookies": true,
                "gc_maxlifetime": "1440 ثانية",
                "bootstrap_sets_httponly": false,
                "bootstrap_sets_samesite": false,
                "bootstrap_sets_strict": false,
                "bootstrap_regenerates_id": false,
                "session_config_sets_httponly": true,
                "session_config_sets_samesite": true,
                "session_config_sets_secure": true,
                "session_config_sets_strict": true,
                "session_config_regenerates_id": true,
                "session_config_path_isolated": true,
                "connection_is_https": true,
                "cookie_secure": false
            }
        },
        {
            "test": "File Upload Security",
            "status": "PASS",
            "detail": "رفع الملفات محمي بشكل كافٍ",
            "data": {
                "has_mime_check": true,
                "has_extension_check": true,
                "has_size_check": true,
                "has_no_php_in_ext": true,
                "uploads_dir_exists": true,
                "uploads_dir_writable": true,
                "php_files_in_uploads": 0,
                "no_php_files_in_uploads": true,
                "htaccess_in_uploads": false
            }
        }
    ]
}
