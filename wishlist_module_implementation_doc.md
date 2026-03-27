# Wishlist Module — Technical Implementation Guide

## Purpose
This document describes the full technical implementation of the Wishlist system inside the project. It is intended for backend developers implementing database, API, and business logic layers.

---

## 1. System Overview

The Wishlist module allows users to:

- Create one or multiple wishlists
- Add products to wishlists
- Remove products (soft delete)
- Maintain tenant and entity isolation
- Prevent duplicate wishlist items

Architecture:

```
User
 └── Wishlists
        └── Wishlist Items
               └── Products
```

---

## 2. Database Tables

### 2.1 Table: `wishlists`
Represents a wishlist owned by a user.

| Column | Description |
|---|---|
| id | Primary key |
| user_id | Owner of wishlist |
| wishlist_name | Display name |
| is_public | Visibility flag |
| is_default | Default wishlist |
| total_items | Cached count |
| tenant_id | Tenant isolation |
| entity_id | Entity isolation |
| created_at | Creation time |
| updated_at | Last update |

---

### 2.2 Table: `wishlist_items`
Represents products inside a wishlist.

| Column | Description |
|---|---|
| id | Primary key |
| wishlist_id | Related wishlist |
| product_id | Product reference |
| product_variant_id | Variant reference |
| priority | Sorting priority |
| notes | Optional notes |
| tenant_id | Tenant isolation |
| entity_id | Entity isolation |
| removed_at | Soft delete timestamp |
| created_at | Creation time |
| updated_at | Last update |

---

## 3. Required Constraints

### 3.1 Foreign Key

```sql
ALTER TABLE wishlist_items
ADD CONSTRAINT fk_wishlist_items_wishlist
FOREIGN KEY (wishlist_id)
REFERENCES wishlists(id)
ON DELETE CASCADE;
```

Behavior:
- Deleting a wishlist removes all related items automatically.

---

### 3.2 Prevent Duplicate Products

```sql
ALTER TABLE wishlist_items
ADD UNIQUE uniq_item
(wishlist_id, product_id, product_variant_id);
```

Prevents inserting the same product twice.

---

## 4. Indexing Strategy

### Default Wishlist Lookup

```sql
ALTER TABLE wishlists
ADD INDEX idx_user_default (user_id, is_default);
```

Used when loading the user's default wishlist.

### Recommended Indexes

- idx_wishlist_id
- idx_product_id
- idx_product_variant_id
- idx_scope_active (tenant_id, entity_id, wishlist_id, removed_at)

---

## 5. Business Logic Flow

### Add Product to Wishlist

1. Get user's default wishlist
2. Create one if it does not exist
3. Insert product into wishlist_items

---

## 6. Backend Layer Structure (MVC)

```
controllers/
services/
models/
```

### Recommended Services

- WishlistService
- WishlistItemService

Controllers must NOT directly access database queries.

---

## 7. Model Examples

### WishlistModel

```php
public function getDefaultWishlist($userId)
{
    return $this->db->fetch(
        "SELECT * FROM wishlists
         WHERE user_id = ?
         AND is_default = 1
         LIMIT 1",
        [$userId]
    );
}
```

---

### WishlistItemModel

```php
public function addItem($wishlistId, $productId)
{
    $sql = "INSERT INTO wishlist_items
            (wishlist_id, product_id, tenant_id, entity_id)
            VALUES (?, ?, ?, ?)";

    return $this->db->execute($sql, [
        $wishlistId,
        $productId,
        TENANT_ID,
        ENTITY_ID
    ]);
}
```

---

## 8. Soft Delete Strategy

Items must not be physically deleted.

Instead:

```sql
UPDATE wishlist_items
SET removed_at = NOW()
WHERE id = ?;
```

All SELECT queries must include:

```sql
AND removed_at IS NULL
```

---

## 9. Multi-Tenant Rules

All queries MUST include tenant and entity scope:

```sql
AND tenant_id = ?
AND entity_id = ?
```

This prevents data leakage between entities or tenants.

---

## 10. Fetch Wishlist With Products

```sql
SELECT
    wi.id,
    p.name,
    wi.priority
FROM wishlist_items wi
JOIN products p
ON p.id = wi.product_id
WHERE wi.wishlist_id = ?
AND wi.removed_at IS NULL;
```

---

## 11. Recommended API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/wishlist | Get wishlist |
| POST | /api/wishlist/add | Add product |
| DELETE | /api/wishlist/remove | Remove product |
| GET | /api/wishlist/items | List items |

---

## 12. Performance Notes

- Use indexed lookups only
- Avoid COUNT(*) on large tables
- Maintain cached total_items
- Update counters asynchronously when possible

---

## 13. Future Enhancements

- Guest wishlist
- Merge after login
- Redis caching
- Recommendation engine
- Event-based updates

---

## 14. Implementation Checklist

- [ ] Foreign key added
- [ ] Unique constraint added
- [ ] Required indexes created
- [ ] Soft delete en