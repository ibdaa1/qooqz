# Job Categories - Issues Fixed

## Summary

Fixed two critical issues preventing job categories from being created and media from being selected.

## Issue 1: "Field 'name' is required" Error ✅ FIXED

### Problem
When trying to create a job category with translations, the API returned:
```json
{
    "success": false,
    "message": "Field 'name' is required."
}
```

Even though the data sent included the name in translations:
```json
{
    "tenant_id": "1",
    "slug": "home",
    "translations": [
        {
            "language_code": "ar",
            "name": "محمود زيدان",
            "description": "1223"
        }
    ]
}
```

### Root Cause
The validator in `JobCategoriesValidator.php` was checking for `name` field directly on the category data:
```php
if (empty($data['name'])) {
    throw new InvalidArgumentException("Field 'name' is required.");
}
```

But according to the database schema:
- `job_categories` table does NOT have a `name` column
- `name` only exists in `job_category_translations` table

### Solution

#### File: `/api/v1/models/jobs/validators/JobCategoriesValidator.php`
**Removed incorrect validation** (lines 18-20):
```php
// BEFORE:
if (empty($data['name'])) {
    throw new InvalidArgumentException("Field 'name' is required.");
}

// AFTER:
// Note: 'name' is not a field in job_categories table
// It only exists in job_category_translations table
// Translations are validated separately via validateTranslation()
```

The `validateTranslation()` method (lines 75-96) already properly validates name in translations.

#### File: `/api/v1/models/jobs/services/JobCategoriesService.php`
**Enhanced create() and update() methods** to handle translations array:

```php
// Save translations if provided as array
if ($categoryId && !empty($data['translations']) && is_array($data['translations'])) {
    foreach ($data['translations'] as $translation) {
        if (!empty($translation['language_code']) && !empty($translation['name'])) {
            $this->saveTranslation($categoryId, $translation['language_code'], $translation);
        }
    }
}

// Handle deleted translations if provided
if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
    foreach ($data['deleted_translations'] as $lang) {
        $this->deleteTranslation($categoryId, $lang);
    }
}
```

### Result
✅ Categories can now be created with translations array
✅ Multiple translations can be saved in one request
✅ Deleted translations are properly removed
✅ Backwards compatible with legacy single translation format

---

## Issue 2: Media Studio Not Opening ✅ FIXED

### Problem
When clicking "Select Image" or "Select Icon" buttons, nothing happened. The media studio didn't open.

### Root Cause
The JavaScript was using `window.MediaStudio.open()` API:
```javascript
if (typeof window.MediaStudio !== 'undefined' && window.MediaStudio.open) {
    window.MediaStudio.open({
        mode: 'select',
        imageType: IMAGE_TYPE_ID,
        onSelect: (selected) => { ... }
    });
}
```

But the platform uses iframe modal approach (like in `entities.php`), not a JavaScript API.

### Solution

#### File: `/admin/fragments/job_categories.php`
**Added Media Studio Modal HTML:**
```html
<!-- Media Studio Modal -->
<div id="mediaStudioModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:90%; height:90vh;">
        <span class="close" id="mediaStudioClose" style="font-size:2rem; cursor:pointer;">&times;</span>
        <iframe id="mediaStudioFrame" style="width:100%; height:calc(100% - 40px); border:none;"></iframe>
    </div>
</div>
```

#### File: `/admin/assets/js/pages/job_categories.js`
**Replaced with iframe modal approach:**

1. **Track current target field:**
```javascript
let _currentImageType = null;
```

2. **Open modal with iframe:**
```javascript
function openMediaStudio(targetField) {
    _currentImageType = targetField;
    
    if (el.mediaModal && el.mediaFrame) {
        el.mediaModal.style.display = 'block';
        const categoryId = el.formId?.value || 'new';
        el.mediaFrame.src = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${state.tenantId}&lang=${state.language}&owner_id=${categoryId}&image_type_id=${IMAGE_TYPE_ID}`;
    }
}
```

3. **Close modal:**
```javascript
function closeMediaStudio() {
    if (el.mediaModal) {
        el.mediaModal.style.display = 'none';
        _currentImageType = null;
    }
}
```

4. **Handle postMessage from iframe:**
```javascript
function handleMediaMessage(event) {
    if (!event.data || typeof event.data !== 'object') return;
    
    if (event.data.type === 'media-selected' || event.data.type === 'image-selected') {
        const imageUrl = event.data.url || event.data.imageUrl;
        const targetField = _currentImageType;
        
        if (imageUrl && targetField) {
            if (targetField === 'image') {
                if (el.categoryImageUrl) el.categoryImageUrl.value = imageUrl;
                if (el.categoryImagePreview) {
                    el.categoryImagePreview.innerHTML = `<img src="${esc(imageUrl)}" alt="Category Image" style="max-width:200px;border-radius:8px;">`;
                }
            } else if (targetField === 'icon') {
                if (el.categoryIconUrl) el.categoryIconUrl.value = imageUrl;
                if (el.categoryIconPreview) {
                    el.categoryIconPreview.innerHTML = `<img src="${esc(imageUrl)}" alt="Category Icon" style="max-width:100px;border-radius:8px;">`;
                }
            }
            closeMediaStudio();
        }
    }
}
```

5. **Added elements to cache:**
```javascript
el = {
    // ... existing elements ...
    mediaModal: document.getElementById('mediaStudioModal'),
    mediaFrame: document.getElementById('mediaStudioFrame'),
    mediaClose: document.getElementById('mediaStudioClose')
};
```

6. **Added event listeners:**
```javascript
// Close button
if (el.mediaClose) el.mediaClose.onclick = closeMediaStudio;

// PostMessage handler
window.addEventListener('message', handleMediaMessage);
```

7. **Get tenant_id for iframe URL:**
```javascript
state.tenantId = (window.APP_CONFIG && window.APP_CONFIG.TENANT_ID) || 
                (metaTag && metaTag.dataset.tenantId) || 1;
```

### How It Works
1. User clicks "Select Image" or "Select Icon" button
2. `openMediaStudio(targetField)` is called
3. Modal overlay appears with iframe loading media_studio.php
4. User browses and selects an image in the media studio
5. Media studio sends postMessage with selected image data
6. `handleMediaMessage()` receives the message
7. Image URL and preview are updated
8. Modal closes automatically

### URL Pattern
```
/admin/fragments/media_studio.php?embedded=1&tenant_id=1&lang=ar&owner_id=123&image_type_id=11
```

Where:
- `embedded=1` - Tells media studio it's in iframe mode
- `tenant_id` - Current tenant
- `lang` - Current language (ar/en)
- `owner_id` - Category ID (or 'new' for new category)
- `image_type_id=11` - Job category image type

### Result
✅ Media studio opens in modal overlay
✅ User can browse and select images
✅ Image selection works via postMessage
✅ Preview updates correctly
✅ Modal closes after selection
✅ Consistent with entities.php pattern

---

## Testing

### Test Creating Category with Translations
1. Open `/admin/fragments/job_categories.php`
2. Click "Add Category" button
3. Fill in basic info (slug, sort order, status)
4. Go to "Translations" tab
5. Select language (e.g., Arabic)
6. Click "Add Translation"
7. Enter name and description
8. Click "Save"
9. ✅ Should succeed without "Field 'name' is required" error

### Test Media Studio
1. Open job category form (add or edit)
2. Go to "Media" tab
3. Click "Select Image" button
4. ✅ Modal should open with media studio iframe
5. Select an image in media studio
6. ✅ Modal should close and image preview should appear
7. Click "Select Icon" button
8. ✅ Modal should open again
9. Select an icon
10. ✅ Icon preview should update

---

## Files Modified

### Backend
1. `/api/v1/models/jobs/validators/JobCategoriesValidator.php`
   - Removed incorrect `name` validation from main category validation
   - Name is only validated in translations (validateTranslation method)

2. `/api/v1/models/jobs/services/JobCategoriesService.php`
   - Enhanced create() method to handle translations array
   - Enhanced update() method to handle translations array
   - Added support for deleted_translations array
   - Maintains backwards compatibility

### Frontend
3. `/admin/fragments/job_categories.php`
   - Added media studio modal HTML structure

4. `/admin/assets/js/pages/job_categories.js`
   - Replaced window.MediaStudio.open() with iframe modal
   - Added openMediaStudio() function
   - Added closeMediaStudio() function
   - Added handleMediaMessage() function
   - Added modal elements to cache
   - Added event listeners for close button and postMessage
   - Added tenant_id to state

---

## Database Schema Reference

### job_categories
```sql
- id (bigint)
- tenant_id (int unsigned)
- parent_id (bigint) - nullable, for hierarchy
- slug (varchar 255)
- sort_order (int) - default 0
- is_active (tinyint) - default 1
- created_at (datetime)
```

### job_category_translations
```sql
- id (bigint)
- category_id (bigint)
- language_code (varchar 8) - e.g., 'ar', 'en'
- name (varchar 255) - THIS IS WHERE NAME LIVES
- description (text) - nullable
```

---

## Summary

Both critical issues are now resolved:

1. ✅ **Name validation fixed** - Categories can be created with translations
2. ✅ **Media studio fixed** - Users can select images and icons

The job categories module is now fully functional!
