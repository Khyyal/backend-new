Act as a senior Laravel architect and developer.


I want you to design and implement a reusable, **domain-agnostic Media Service inside the `Modules\Support` module**.

The Media Service must provide a generic infrastructure for uploading, temporarily storing, attaching, detaching, deleting, and cleaning up media.

Do not introduce or reference any business/domain-specific model, module, entity, collection name, or workflow.

---

## Core requirement

I do not want business APIs to receive uploaded files directly.

The intended generic flow is:

```text
Client
   │
   │ upload file
   ▼
Media API
   │
   │ create temporary media
   ▼
Temporary Media
   │
   │ return stable media ID
   ▼
Client
   │
   │ use media ID in another request
   ▼
Business/Application Layer
   │
   │ attach media to a HasMedia model
   ▼
Spatie Media Library
```

The frontend should reference media using a stable identifier.

For example:

```json
{
    "media_id": "01K..."
}
```

or:

```json
{
    "media_ids": [
        "01K...",
        "01K..."
    ]
}
```

Do **not** use the media URL as the identifier.

The URL is response/output data only.

---

# Existing technology

The application uses:

```text
Laravel

```

Media-capable models implement:

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
```

The Media Service must work with any model implementing:

```php
Spatie\MediaLibrary\HasMedia
```

It must not know anything about specific application domains.

---

# Module location

Implement everything under:

```text
Modules/Support/
```

Adapt the exact directory structure to the existing Support module conventions after inspecting the project.

A possible structure is:

```text
Modules/Support/
├── Models/
│   └── TemporaryUpload.php
│
├── Services/
│   └── Media/
│       ├── MediaService.php
│       └── TemporaryMediaService.php
│
├── Http/
│   ├── Controllers/
│   │   └── MediaController.php
│   │
│   ├── Requests/
│   │   └── UploadMediaRequest.php
│   │
│   └── Resources/
│       └── MediaResource.php
│
├── Jobs/
│   └── CleanupExpiredMedia.php
│
└── ...
```

Do not blindly follow this structure if the existing project has a better established convention.

---

# Architecture

Separate responsibilities properly.

## MediaService

Responsible for generic permanent media operations:

```php
upload()
attach()
detach()
delete()
```

It should operate against:

```php
Spatie\MediaLibrary\HasMedia
```

without knowing the concrete model.

For example, the generic API should conceptually support:

```php
$mediaService->attach(
    media: $media,
    model: $model,
    collection: $collection,
);
```

The service must not contain:

```php
if ($model instanceof ...)
```

or any business-specific branching.

---

## TemporaryMediaService

Responsible for temporary uploads.

Responsibilities should include:

```php
createTemporary()
promote()
expire()
cleanupExpired()
```

Use a dedicated persistence model if appropriate.

---

# Temporary uploads

Create a dedicated table for temporary uploads if the existing application does not already provide an equivalent abstraction.

For example:

```text
temporary_uploads
-----------------
id
user_id
media_id
expires_at
created_at
updated_at
```

Use the project's existing UUID/ULID conventions.

Do not duplicate metadata already maintained by Spatie Media Library unnecessarily.

The temporary record should establish:

```text
authenticated user
        ↓
temporary upload
        ↓
Spatie media
```

This association is required for authorization and cleanup.

---

# Upload lifecycle

The lifecycle should be:

```text
UPLOAD
   │
   ▼
TEMPORARY
   │
   ├───────────────┐
   │               │
   │ promoted      │ expires
   ▼               ▼
ATTACHED         DELETED
   │
   ▼
PERMANENT
```

When media is successfully attached to a model:

- mark/remove the temporary state appropriately
- prevent it from being treated as an unused temporary upload
- preserve the Spatie media record

When temporary media expires:

- delete the temporary record
- delete the associated Spatie media
- allow Spatie Media Library to handle physical file deletion correctly

Do not manually delete physical files if Spatie Media Library provides the correct deletion mechanism.

---

# API

Provide a generic endpoint for uploading media.

For example:

```http
POST /api/media
```

using:

```text
multipart/form-data
```

The API should return a stable media identifier.

Example:

```json
{
    "id": "01K...",
    "status": "temporary",
    "url": "https://...",
    "filename": "example.jpg",
    "mime_type": "image/jpeg",
    "size": 245123,
    "expires_at": "2026-10-08T12:00:00Z"
}
```

The exact response structure should follow the application's existing API conventions.

---

# Deletion API

Provide a generic deletion endpoint if appropriate:

```http
DELETE /api/media/{media}
```

It must verify that the authenticated user is authorized to delete the media.

Do not allow users to delete another user's temporary media.

---

# Authorization

This is a critical requirement.

Never trust a client-provided media ID.

Before attaching temporary media, verify:

```text
media exists
+
temporary record exists
+
temporary record belongs to authenticated user
+
temporary media has not expired
+
media has not already been promoted/attached
```

Do not allow:

```text
User A
  ↓
media_id belonging to User B
  ↓
attach
```

Use Laravel authorization/policies where appropriate.

Do not bypass authorization simply because the media ID is difficult to guess.

---

# Generic attachment

The service must support arbitrary models implementing:

```php
HasMedia
```

and arbitrary collection names supplied by the application layer.

For example:

```php
$model
$collection
$media
```

must be passed into the generic service.

Do not define business-specific collections inside the Support module.

The Support module must not contain constants such as:

```php
LOGO_COLLECTION
COVER_COLLECTION
PROFILE_COLLECTION
```

unless they are genuinely generic to the entire application's infrastructure.

Collection semantics belong to the consuming application layer.

---

# Storage

Do not hardcode a storage disk.

Do not assume:

```php
Storage::disk('s3')
```

or any other specific storage provider.

Use Spatie Media Library's configured filesystem/disk.

The implementation should remain compatible with:

```text
local
S3
S3-compatible storage
other Laravel filesystem drivers
```

where supported by the existing Media Library configuration.

---

# Validation

Create proper Laravel Form Requests.

The upload endpoint should validate:

- file presence
- MIME type
- file size
- allowed file types

Prefer MIME validation rather than trusting the file extension.

Make configurable values configurable where appropriate:

```text
temporary upload lifetime
maximum upload size
allowed MIME types
```

Do not hardcode these values throughout the service.

---

# Cleanup

Implement a queued Job or suitable scheduled command for expired temporary uploads.

For example:

```text
CleanupExpiredMedia
```

The cleanup operation must be:

- safe
- idempotent
- efficient
- batch-oriented if necessary

It should handle large numbers of expired records without loading everything into memory.

Consider:

```php
chunkById()
```

or an equivalent efficient strategy.

Register the cleanup through Laravel's scheduler according to the application's existing Laravel version and scheduling conventions.

---

# Concurrency and race conditions

Consider concurrent requests such as:

```text
Request A:
attach media

Request B:
delete media
```

or:

```text
Request A:
promote media

Request B:
promote the same media
```

Design the database operations and transaction boundaries appropriately.

Prevent the same temporary media from being promoted multiple times.

If storage operations cannot participate in database transactions, explicitly account for that.

---

# Transactions

Use database transactions where appropriate.

Understand that:

```text
database transaction
```

does not automatically roll back:

```text
filesystem/S3 operations
```

Design the service so failures do not leave large numbers of inconsistent records or orphaned files.

Explain the transaction boundaries in the implementation.

---

# Soft deletes

Do not assume that media should be deleted when a consuming model is soft-deleted.

The Media Service should only manage media according to explicit media operations and the lifecycle defined here.

Do not introduce automatic domain-specific behavior.

---

# Performance

The implementation must avoid unnecessary queries.

Pay attention to:

- repeated media lookups
- authorization queries
- temporary upload lookups
- cleanup queries
- N+1 problems
- loading entire expired datasets into memory

Use appropriate indexes.

The temporary upload table should have indexes appropriate for:

```text
user_id
media_id
expires_at
```

Evaluate the exact indexes based on the final queries.

---

# API Resource

Use a Laravel API Resource for media responses.

Do not expose the entire Spatie `Media` model.

Expose only the required fields, such as:

```text
id
status
url
filename
mime_type
size
expires_at
```

Follow existing API serialization conventions if the project already has them.

---

# Testing

Write tests for at least:

### Upload

```text
authenticated user can upload
unauthenticated user cannot upload
invalid MIME type is rejected
oversized file is rejected
```

### Authorization

```text
user can access their own temporary media
user cannot attach another user's temporary media
user cannot delete another user's media
```

### Promotion

```text
temporary media can be attached
temporary media becomes permanent
expired media cannot be attached
same media cannot be promoted twice
```

### Cleanup

```text
expired temporary uploads are removed
associated media is removed
associated storage files are removed
non-expired uploads remain
cleanup is safe to run repeatedly
```

### Failure cases

Test relevant failures around:

```text
database transaction
media attachment
storage deletion
duplicate operations
```

Use the project's existing testing conventions.

---

# Existing project inspection

Before writing code, inspect the repository and determine:

1. Existing `Modules/Support` structure.
2. Existing service conventions.
3. Existing controller conventions.
4. Existing API response conventions.
5. Existing authentication system.
6. Existing authorization/policy conventions.
7. Existing UUID/ULID strategy.
8. Existing filesystem configuration.
9. Existing Spatie Media Library configuration.
10. Existing queue configuration.
11. Existing scheduler configuration.
12. Existing upload/media abstractions.
13. Existing database naming conventions.
14. Existing test structure.

If an existing abstraction already solves part of this problem, extend it instead of creating a duplicate system.

---

# Important architectural constraint

The Support Media infrastructure must remain completely **domain-agnostic**.

It must know only about concepts such as:

```text
Media
TemporaryUpload
HasMedia
MediaCollection
AuthenticatedUser
Storage
```

It must NOT know about:

```text
business entities
business modules
business-specific collections
business-specific workflows
business-specific validation
```

The consuming application layer is responsible for deciding:

```text
which model
which collection
which media
when to attach
when to detach
```

The Support module only provides the infrastructure required to perform those operations safely.

---

# Final deliverables

Implement the feature completely and provide:

1. Migration(s)
2. Models
3. Services
4. Controllers
5. Form Requests
6. API Resources
7. Routes
8. Policies/authorization
9. Cleanup Job
10. Scheduler configuration
11. Tests
12. Configuration if required
13. Example generic usage of the service

Before finishing, perform a code review specifically for:

- authorization vulnerabilities
- orphaned media
- orphaned temporary records
- race conditions
- duplicate promotion
- transaction boundaries
- expired media
- storage cleanup
- N+1 queries
- unnecessary abstractions
- unnecessary database queries
- Spatie Media Library v11 compatibility
- Laravel 12 compatibility

Do not generate a generic standalone implementation without first inspecting the existing repository and adapting it to the project's conventions.
