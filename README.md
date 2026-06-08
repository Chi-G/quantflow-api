# QuantFlow API

QuantFlow is a batch-driven financial document processing API.

## Requirements
- PHP 8.3+
- Composer
- SQLite (default for local)
- Redis (for queues)

## Setup Instructions
1. Install dependencies:
   ```bash
   composer install
   ```
2. Copy the `.env.example` file:
   ```bash
   cp .env.example .env
   ```
3. Generate the application key:
   ```bash
   php artisan key:generate
   ```
4. Configure the `.env` file (SQLite and Redis are used by default):
   ```env
   DB_CONNECTION=sqlite
   QUEUE_CONNECTION=redis
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```
6. Start the Queue Worker:
   ```bash
   php artisan queue:work redis --queue=batches,notifications,default
   ```
7. Start the API Server:
   ```bash
   php artisan serve
   ```

## Docker Setup (Optional)
A `docker-compose.yml` is provided for running the app, redis, and queue worker together.
```bash
docker-compose up -d
```

## API Documentation
- **Swagger UI**: Interactive API documentation is automatically generated and available at `http://localhost:8000/api/docs` while the server is running.
- **Postman**: A complete `POSTMAN_COLLECTION.json` file is included in the root of the project. Import this directly into Postman to test the full lifecycle.

## AI Tools Disclosure
AI tools (Gemini 3.1 Pro) were used to scaffold the boilerplate, implement the standard JSON response envelopes, and write the feature tests.

## Idempotency Strategy
To prevent duplicate processing and ensure safe retries, the API employs the following strategies:
1. **Atomic Locks**: When a batch is submitted, `Cache::lock("batch_{id}_processing")` is acquired via Redis. If a second request arrives concurrently, it is instantly rejected.
2. **State Machines**: Batches enforce strict state transitions (e.g. only `pending` or `failed` batches can be submitted).
3. **Traceability**: All requests are assigned a unique `X-Request-ID` via middleware, which is logged to track duplicate attempts or traces across the queue.
4. **Row-Level Idempotency**: Document processing uses a unique composite key (`reference_number` + `batch_id`) preventing duplicate validation of the same row.
