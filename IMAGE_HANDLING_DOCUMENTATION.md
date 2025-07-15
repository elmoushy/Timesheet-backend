# Employee Image Handling Documentation

## Overview
This document describes the BLOB-based image handling system for employee profile images in the Timesheet application.

## Database Schema
The `xxx_employees` table has an `image_path` column of type `LONGBLOB` that stores binary image data directly in the database.

## Model Features

### Employee Model (`app/Models/Employee.php`)

#### Hidden Attributes
- `image_path` - Raw binary data is hidden from JSON serialization to prevent UTF-8 encoding issues

#### Appended Attributes
- `image_url` - Data URI format for displaying images
- `optimized_image_url` - Data URI with proper MIME type detection

#### Image Methods

##### `hasImage(): bool`
Checks if the employee has an image stored.

##### `getImageSize(): int`
Returns the size of the stored image in bytes.

##### `getImageMimeType(): ?string`
Detects and returns the MIME type of the stored image.

##### `getImageBase64Attribute(): ?string`
Returns the base64-encoded image data.

##### `getImageUrlAttribute(): ?string`
Returns a data URI for the image (defaults to JPEG MIME type).

##### `getOptimizedImageUrlAttribute(): ?string`
Returns a data URI with proper MIME type detection.

##### `validateImageData(string $imageData): bool`
Static method to validate if binary data represents a valid image.

## Controller Features

### EmployeeController (`app/Http/Controllers/API/EmployeeController.php`)

#### Image Upload Endpoints

##### `POST /api/employees/{id}/upload-image`
Uploads an image for an employee.

**Request:**
- `image_path` - Image file (jpeg, png, jpg, gif, max 10MB)

**Response:**
```json
{
  "message": "Employee image uploaded successfully",
  "data": {
    "image_url": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
    "optimized_image_url": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77wgAAAABJRU5ErkJggg==",
    "has_image": true,
    "image_size": 1024,
    "mime_type": "image/png"
  }
}
```

##### `GET /api/employees/{id}/image`
Retrieves image data for an employee.

**Response:**
```json
{
  "message": "Employee image retrieved successfully",
  "data": {
    "image_url": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
    "optimized_image_url": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77wgAAAABJRU5ErkJggg==",
    "image_base64": "/9j/4AAQSkZJRgABAQAAAQABAAD...",
    "image_size": 1024,
    "mime_type": "image/png"
  }
}
```

##### `DELETE /api/employees/{id}/image`
Deletes an employee's image.

**Response:**
```json
{
  "message": "Employee image deleted successfully",
  "data": {
    "has_image": false
  }
}
```

## Usage Examples

### Frontend Integration

#### Display Employee Image
```javascript
// Employee data from API
const employee = {
  id: 1,
  first_name: "John",
  last_name: "Doe",
  image_url: "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
  optimized_image_url: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77wgAAAABJRU5ErkJggg=="
};

// Use optimized_image_url for better MIME type accuracy
const imageUrl = employee.optimized_image_url || employee.image_url;
if (imageUrl) {
  document.getElementById('employee-image').src = imageUrl;
} else {
  document.getElementById('employee-image').src = '/default-avatar.png';
}
```

#### Upload Image
```javascript
const formData = new FormData();
formData.append('image_path', fileInput.files[0]);

fetch(`/api/employees/${employeeId}/upload-image`, {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(data => {
  if (data.data.image_url) {
    document.getElementById('employee-image').src = data.data.optimized_image_url;
  }
});
```

## Validation Rules

### File Upload Validation
- **File types:** jpeg, png, jpg, gif
- **Max size:** 10,240 KB (10MB)
- **Binary validation:** Images are validated using `getimagesize()` function

### Image Data Validation
- Binary data is validated before storage
- Invalid image data returns 422 error

## Error Handling

### Common Issues Fixed

1. **"Malformed UTF-8 characters" Error**
   - **Cause:** Binary data being included in JSON serialization
   - **Solution:** Added `image_path` to `$hidden` array in Employee model

2. **Undefined Array Key Error**
   - **Cause:** Accessing `$this->attributes['image_path']` when column is null
   - **Solution:** Added `isset()` checks in all image accessor methods

3. **MIME Type Detection**
   - **Cause:** Generic JPEG MIME type for all images
   - **Solution:** Added proper MIME type detection with `getimagesize()`

## Database Migration

The migration `2025_07_15_074703_modify_image_path_to_blob_in_xxx_employees_table.php` converts the `image_path` column from `VARCHAR(255)` to `LONGBLOB`:

```php
// Up migration
Schema::table('xxx_employees', function (Blueprint $table) {
    $table->dropColumn('image_path');
});

Schema::table('xxx_employees', function (Blueprint $table) {
    $table->binary('image_path')->nullable()->after('user_status');
});
```

## Security Considerations

1. **File Type Validation:** Only specific image types are allowed
2. **Size Limits:** Maximum file size is enforced
3. **Binary Validation:** Images are validated before storage
4. **No File System Storage:** Images are stored in database, reducing file system security concerns

## Performance Considerations

1. **Database Size:** BLOB storage increases database size
2. **Memory Usage:** Large images consume memory during processing
3. **Transfer Speed:** Base64 encoding increases data transfer size by ~33%
4. **Caching:** Consider implementing image caching for frequently accessed images

## Best Practices

1. **Always check `hasImage()`** before accessing image data
2. **Use `optimized_image_url`** for better MIME type accuracy
3. **Implement proper error handling** for image upload failures
4. **Consider image compression** before storage for better performance
5. **Use appropriate image formats** (PNG for graphics, JPEG for photos)
