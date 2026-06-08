# GEMINI.md — AI Agent Instructions for `quantflow-api`

> **Project**: QuantFlow API — Quant Systems Backend Developer Technical Assessment  
> **Stack**: Laravel 13.x · PHP 8.3+ · MySQL · Redis · Laravel Sanctum · Queues  
> **Architecture**: RESTful API (no Blade views) · Feature-first folder structure  
> **Author context**: Full-Stack Engineer, 6 years experience, Laravel backend focus

---

## 1. Project Overview

QuantFlow is a **batch-driven financial document processing API** that simulates the kind of multi-tenant approval pipeline used by banks, fintechs, and digital lenders. The system ingests CSV/XLSX batch files, validates each record, processes documents asynchronously, routes them through a structured approval chain, and exposes audit-ready status tracking per batch, per document, and per operation.

This file is the **single source of truth** for all AI-assisted development on this project. Always read this file before making changes.

---

## 2. Repository Structure

```
quantflow-api/
├── app/
│   ├── Console/Commands/          # Artisan CLI commands
│   ├── Enums/                     # PHP 8.1+ backed enums (Status, Role, etc.)
│   ├── Events/                    # Domain events (BatchUploaded, DocumentApproved, etc.)
│   ├── Exceptions/                # Custom exceptions + Handler overrides
│   ├── Http/
│   │   ├── Controllers/Api/       # All API controllers (versioned: V1/)
│   │   ├── Middleware/            # Custom middleware
│   │   ├── Requests/              # FormRequest validation classes
│   │   └── Resources/             # API Resources (JsonResource + ResourceCollection)
│   ├── Jobs/                      # Queued jobs (ProcessBatch, SendApprovalNotification, etc.)
│   ├── Listeners/                 # Event listeners
│   ├── Models/                    # Eloquent models
│   ├── Notifications/             # Laravel notifications (email, DB)
│   ├── Policies/                  # Gate & Policy classes
│   ├── Repositories/              # Optional: Repository pattern per model
│   └── Services/                  # Business logic services (BatchService, ApprovalService, etc.)
├── bootstrap/
├── config/
│   └── quantflow.php              # App-specific config (batch limits, approval chains, etc.)
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php                    # Main API routes (v1 prefix)
│   └── console.php
├── storage/
│   └── app/batches/               # Uploaded CSV/XLSX files stored here
├── tests/
│   ├── Feature/                   # Feature tests per endpoint/flow
│   └── Unit/                      # Unit tests per Service/Job
├── GEMINI.md                      # This file
└── README.md
```

---

## 3. Domain Models & Database Schema

### 3.1 Users
```
users
  - id (bigint, PK)
  - name (string)
  - email (string, unique)
  - password (hashed)
  - role (enum: admin, operator, approver, auditor)
  - is_active (boolean, default true)
  - created_at, updated_at
```

### 3.2 Batches
```
batches
  - id (bigint, PK)
  - uuid (uuid, unique)            ← public-facing identifier
  - uploaded_by (FK → users.id)
  - file_name (string)
  - file_path (string)             ← storage path
  - file_type (enum: csv, xlsx)
  - total_records (integer)
  - processed_records (integer, default 0)
  - status (enum: pending, processing, completed, failed, partially_failed)
  - metadata (json, nullable)      ← arbitrary batch-level info
  - submitted_at (timestamp, nullable)
  - completed_at (timestamp, nullable)
  - created_at, updated_at
```

### 3.3 Documents
```
documents
  - id (bigint, PK)
  - uuid (uuid, unique)
  - batch_id (FK → batches.id)
  - reference_number (string)      ← from batch file row
  - amount (decimal 18,2)
  - currency (string, default 'NGN')
  - recipient_name (string)
  - recipient_account (string)
  - bank_code (string)
  - status (enum: pending, validated, processing, approved, rejected, failed)
  - failure_reason (text, nullable)
  - metadata (json, nullable)
  - processed_at (timestamp, nullable)
  - created_at, updated_at
```

### 3.4 Approval Requests
```
approval_requests
  - id (bigint, PK)
  - document_id (FK → documents.id)
  - batch_id (FK → batches.id)
  - requested_by (FK → users.id)
  - approved_by (FK → users.id, nullable)
  - status (enum: pending, approved, rejected)
  - comment (text, nullable)
  - approval_level (integer, default 1)  ← for multi-level chains
  - responded_at (timestamp, nullable)
  - created_at, updated_at
```

### 3.5 Audit Logs
```
audit_logs
  - id (bigint, PK)
  - user_id (FK → users.id, nullable)
  - auditable_type (morphTo)
  - auditable_id (bigint)
  - event (string)                 ← created, updated, approved, rejected, etc.
  - old_values (json, nullable)
  - new_values (json, nullable)
  - ip_address (string, nullable)
  - user_agent (string, nullable)
  - created_at
```

### 3.6 Notifications (DB channel)
```
notifications                      ← Laravel default notifications table
  - id (uuid)
  - type
  - notifiable_type / notifiable_id
  - data (json)
  - read_at
  - created_at, updated_at
```

---

## 4. API Endpoints (v1)

All routes are prefixed: `/api/v1`  
All authenticated routes use: `auth:sanctum` middleware  
Roles enforced via Policies + custom `role` middleware

### 4.1 Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register a new user |
| POST | `/auth/login` | Login → returns Sanctum token |
| POST | `/auth/logout` | Revoke current token |
| GET | `/auth/me` | Get authenticated user profile |

### 4.2 Batches
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/batches` | Upload a CSV/XLSX batch file |
| GET | `/batches` | List all batches (paginated) |
| GET | `/batches/{uuid}` | Get single batch with stats |
| POST | `/batches/{uuid}/submit` | Trigger async batch processing |
| GET | `/batches/{uuid}/documents` | List all documents in a batch |
| GET | `/batches/{uuid}/status` | Lightweight status polling endpoint |

### 4.3 Documents
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/documents` | List documents (filterable by status, batch) |
| GET | `/documents/{uuid}` | Get single document details |
| PATCH | `/documents/{uuid}` | Update document metadata (pre-submit only) |

### 4.4 Approvals
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/approvals` | List pending approval requests for current user |
| POST | `/approvals/{id}/approve` | Approve a document |
| POST | `/approvals/{id}/reject` | Reject a document with comment |
| GET | `/approvals/{id}` | Get approval request detail |

### 4.5 Audit
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/audit-logs` | List audit logs (admin/auditor only, paginated) |
| GET | `/audit-logs/{id}` | Get a specific audit log entry |

---

## 5. Business Logic Rules

### Batch Processing Flow
1. Operator uploads a CSV/XLSX file via `POST /batches`
2. File is validated (type, size, required columns) and stored in `storage/app/batches/`
3. Batch record created with status `pending`
4. Operator calls `POST /batches/{uuid}/submit` to kick off processing
5. A `ProcessBatch` job is dispatched to the queue
6. Job parses each row, creates `Document` records, runs per-row validation
7. Invalid rows → `Document.status = failed`, `failure_reason` stored
8. Valid rows → `Document.status = validated`, `ApprovalRequest` created per document
9. Batch `status` updated to `processing`, then `completed` or `partially_failed`
10. Approvers receive notifications (DB + email) for pending requests

### Approval Flow
- Only users with `role = approver` or `admin` can act on approval requests
- Approving a document → `Document.status = approved`, `ApprovalRequest.status = approved`
- Rejecting → `Document.status = rejected`, comment required
- All approval actions trigger an audit log entry
- Batch `status` updates once all documents in it have been actioned

### Validation Rules (CSV Row Level)
Required columns: `reference_number`, `amount`, `currency`, `recipient_name`, `recipient_account`, `bank_code`
- `reference_number`: required, unique within batch
- `amount`: required, numeric, greater than 0
- `currency`: required, uppercase, 3 chars (ISO 4217)
- `recipient_account`: required, string, 10 digits (NUBAN format)
- `bank_code`: required, string, 3-6 chars

### Authorization (Policy Rules)
- `admin`: full access
- `operator`: can upload, view own batches/documents, submit
- `approver`: can list/action only their assigned approval requests
- `auditor`: read-only access to batches, documents, audit logs

---

## 6. Enums (PHP 8.1 Backed Enums)

```php
// app/Enums/BatchStatus.php
enum BatchStatus: string {
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyFailed = 'partially_failed';
}

// app/Enums/DocumentStatus.php
enum DocumentStatus: string {
    case Pending = 'pending';
    case Validated = 'validated';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
}

// app/Enums/UserRole.php
enum UserRole: string {
    case Admin = 'admin';
    case Operator = 'operator';
    case Approver = 'approver';
    case Auditor = 'auditor';
}

// app/Enums/ApprovalStatus.php
enum ApprovalStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

---

## 7. Key Services

### BatchService (`app/Services/BatchService.php`)
- `uploadBatch(Request $request, User $user): Batch`
- `submitBatch(Batch $batch): void` — dispatches `ProcessBatch` job
- `getBatchStats(Batch $batch): array`

### DocumentService (`app/Services/DocumentService.php`)
- `createFromRow(array $row, Batch $batch): Document`
- `validateRow(array $row): array` — returns `['valid' => bool, 'errors' => []]`
- `markFailed(Document $doc, string $reason): void`

### ApprovalService (`app/Services/ApprovalService.php`)
- `createApprovalRequest(Document $doc, User $requestedBy): ApprovalRequest`
- `approve(ApprovalRequest $request, User $approver, ?string $comment): void`
- `reject(ApprovalRequest $request, User $approver, string $comment): void`

### AuditService (`app/Services/AuditService.php`)
- `log(string $event, Model $subject, ?User $actor, array $old = [], array $new = []): void`

---

## 8. Jobs & Queues

### ProcessBatch Job (`app/Jobs/ProcessBatch.php`)
- `__construct(Batch $batch)`
- Dispatched to `batches` queue
- Parses file row by row using a chunked reader (League CSV or PhpSpreadsheet)
- Calls `DocumentService::createFromRow()` and `DocumentService::validateRow()` per row
- Updates `Batch::processed_records` progressively
- On completion: fires `BatchProcessed` event
- On failure: marks batch `failed`, logs to audit

### SendApprovalNotification Job (`app/Jobs/SendApprovalNotification.php`)
- Dispatched after each `ApprovalRequest` is created
- Uses `notifications` queue
- Sends via DB channel + Mail

### Queue configuration
```
# .env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# config/queue.php — add named queues
'batches' → high priority
'notifications' → low priority
'default' → default
```

Supervisor config example (production):
```ini
[program:quantflow-batches]
command=php artisan queue:work redis --queue=batches --tries=3 --timeout=120

[program:quantflow-notifications]
command=php artisan queue:work redis --queue=notifications --tries=5
```

---

## 9. API Response Format

All responses must follow this envelope:

```json
// Success
{
  "success": true,
  "message": "Batch uploaded successfully.",
  "data": { ... },
  "meta": { "page": 1, "total": 200 }   // only on paginated responses
}

// Error
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Error message"]
  }
}
```

Implement this via a `ApiResponse` helper class or a trait `HasApiResponse`.

---

## 10. Error Handling

Override `app/Exceptions/Handler.php` to return JSON for all exceptions:

- `ValidationException` → 422 with errors array
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `ModelNotFoundException` → 404
- `ThrottleRequestsException` → 429
- Generic `Exception` → 500 (message hidden in production)

---

## 11. Testing Strategy

### Feature Tests (must cover)
- `BatchUploadTest` — valid file, invalid file, wrong format, oversized
- `BatchSubmitTest` — submit pending batch, re-submit error
- `BatchProcessingTest` — job processes valid rows, logs invalid rows
- `ApprovalFlowTest` — approve/reject document, unauthorized user attempt
- `AuditLogTest` — verify audit entries created on key events
- `AuthTest` — register, login, logout, invalid credentials

### Run tests
```bash
php artisan test
php artisan test --filter BatchUploadTest
php artisan test --coverage
```

---

## 12. Environment Variables (.env)

```dotenv
APP_NAME=QuantFlow
APP_ENV=local
APP_KEY=           # generate with: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quantflow
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@quantflow.app"
MAIL_FROM_NAME="QuantFlow"

SANCTUM_STATEFUL_DOMAINS=localhost
SESSION_DRIVER=redis
CACHE_STORE=redis

FILESYSTEM_DISK=local
BATCH_MAX_FILE_SIZE_MB=10
BATCH_MAX_ROWS=10000
```

---

## 13. Submission Checklist

Before submitting the project, verify:

- [ ] All endpoints return JSON with the standard envelope
- [ ] `ProcessBatch` job runs on the queue, not synchronously
- [ ] Failed row processing does NOT fail the entire batch — partial failure is handled
- [ ] Approval requests are only actionable by users with `approver` or `admin` role
- [ ] Audit logs are written for: batch submit, document status change, approval actions
- [ ] All routes have appropriate middleware (`auth:sanctum`, role checks)
- [ ] Unit + Feature tests pass with `php artisan test`
- [ ] A `README.md` exists with: setup instructions, `.env.example`, Postman collection link, Swagger access, or sample requests
- [ ] A `POSTMAN_COLLECTION.json` (or `.yaml`) is included in the repo root
- [ ] Swagger API documentation is available at `/api/docs`
- [ ] Code is in a single ZIP or public GitHub repo

---

## 14. Nice to Have (Implement if time allows)

- [ ] Database-level locking or Redis lock on batch processing to prevent duplicate processing
- [ ] `GET /batches/{uuid}/status` as a lightweight SSE-ready polling endpoint
- [ ] Slack/webhook notification on batch completion (configurable via `.env`)
- [ ] `X-Request-ID` header propagation through all jobs and logs (for traceability)
- [ ] A simple idempotency strategy document in `README.md`

---

## 15. Coding Conventions
Always use:
- PHP 8.3 features: readonly properties, enum types, match expressions, named arguments, fibers where relevant
- Use `strict_types=1` in all files
- Use Laravel Form Requests for all validation (no inline `$request->validate()` in controllers)
- Use API Resources (`JsonResource`) for all output — no raw model/array returns
- Controllers must stay thin: call a Service, return a Resource
- Use `uuid` not `id` in all public-facing URLs
- All models must use the `HasUuids` trait or a UUID observer
- No raw SQL — use Eloquent or Query Builder
- Follow PSR-12 coding standard
- All Service methods must be type-hinted (parameters + return types)
- declare(strict_types=1) in every file
- Constructor property promotion with readonly
- Explicit return types on every method
- Backed enums for all status fields
- final on all Service, Request, Resource, and Job classes
- Form Requests for validation (never inline)
- API Resources for all output (never raw arrays)
- Thin controllers — one service call, one resource return
- Named arguments on calls with 3+ parameters
- match() instead of switch()
- Nullsafe operator (?->) instead of null checks
- Eager loading (with()) to prevent N+1
- Route Model Binding via uuid
- Policies for all authorization logic
- HasApiResponse trait in all controllers

---

## 16. AI Agent Behaviour Rules (for Gemini / Claude / Cursor / Copilot)

When assisting with this project, always:

1. **Read this file first** before generating any code
2. **Follow the folder structure** in section 2 — do not create files outside the defined structure
3. **Use the enums** from section 6 for all status fields — never hardcode string literals
4. **Wrap all responses** in the ApiResponse envelope from section 9
5. **Dispatch jobs** for all batch/document processing — never process synchronously in a controller
6. **Write a test** for every new endpoint or service method you create
7. **Log to audit_logs** for every state-changing action on Batch, Document, or ApprovalRequest
8. **Never expose** `id` (integer) in API responses — always use `uuid`
9. **Ask before modifying migrations** that have already been run in any environment
10. **Do not add packages** not listed in section 18 without confirming with the developer

---

## 17. Common Artisan Commands

```bash
# Setup
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Development
php artisan make:model Document -mfsc      # model + migration + factory + seeder + controller
php artisan make:job ProcessBatch
php artisan make:event BatchProcessed
php artisan make:listener HandleBatchProcessed --event=BatchProcessed
php artisan make:notification ApprovalRequested
php artisan make:policy BatchPolicy --model=Batch
php artisan make:resource BatchResource
php artisan make:request StoreBatchRequest

# Queue
php artisan queue:work --queue=batches,notifications,default
php artisan queue:listen
php artisan queue:failed
php artisan queue:retry all

# Testing
php artisan test
php artisan test --parallel
php artisan test --coverage --min=80
```

---

## 18. Approved Packages

```json
{
  "require": {
    "laravel/framework": "^13.0",
    "laravel/sanctum": "^4.x",
    "league/csv": "^9.x",
    "phpoffice/phpspreadsheet": "^3.x",
    "spatie/laravel-query-builder": "^6.x",
    "spatie/laravel-permission": "^6.x"
  },
  "require-dev": {
    "laravel/pint": "^1.x",
    "laravel/telescope": "^5.x",
    "fakerphp/faker": "^1.x",
    "pestphp/pest": "^3.x",
    "pestphp/pest-plugin-laravel": "^3.x"
  }
}
```

---

## 19. Sample Postman Request: Upload Batch

```
POST /api/v1/batches
Authorization: Bearer {token}
Content-Type: multipart/form-data

Body:
  file: [attach CSV file]

Response 201:
{
  "success": true,
  "message": "Batch uploaded successfully.",
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "file_name": "transactions_june.csv",
    "status": "pending",
    "total_records": 0,
    "created_at": "2025-06-08T10:00:00Z"
  }
}
```

---

## 20. Expected CSV/XLSX Column Format

```
reference_number,amount,currency,recipient_name,recipient_account,bank_code
TRX-001,50000.00,NGN,Emeka Okafor,0123456789,058
TRX-002,120000.00,NGN,Amaka Nwosu,0987654321,033
TRX-003,bad_amount,NGN,Test User,0111222333,011
```

Rows with validation errors (like `bad_amount`) should be logged as `failed` documents; they must not halt processing of subsequent rows.

---

*Last updated: June 2025 — Chijindu / Forahia Solutions*
