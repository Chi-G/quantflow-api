Quant Systems Backend Developer Technical Assessment

Role focus: Laravel backend engineering for Quant Core

This assessment is designed to mirror the kind of product and platform work you would handle day to day at Quant Systems. The goal is not to build an entire banking system, but to show how you design secure, maintainable, tenant-aware backend features for an institutional SaaS product.

Assignment At a Glance
- Product area: Quant Core (institutional banking and lending platform)
- Focus: Batch disbursement import, approval, and posting
- Suggested effort: 6-8 hours
- Submission window: 4 calendar days from receipt
- Expected submission format: Dockerized project zipped and shared by email

Scenario
- Quant Core serves microfinance banks, cooperatives, and digital lenders that regularly process approved disbursements in bulk. Operations users need a safe batch processor that can ingest payout instructions, validate them, route them for approval, and post them asynchronously without tenant leakage or duplicate processing.

- Your task is to build a tenant-aware Laravel API for an institutional batch disbursement module. You may simplify the surrounding banking system, but the solution should feel like a production-ready slice of a larger multi-tenant SaaS product.

Functional Requirements
- Allow a user to create a batch from either a CSV upload or a JSON payload.
- Each batch line item should capture beneficiary or account details, amount, narration, external reference, and processing status.
- Provide endpoints to list batches, view a single batch, and inspect line-item-level validation or processing results.
- Implement batch validation before approval. At minimum handle required fields, positive amounts, duplicate external references within the same batch, and invalid account or reference formats.
- Allow a validated batch to be submitted for approval, approved, or rejected with a reason.
- Ensure that only approved batches can be posted.
- Run posting asynchronously through a queued job or service and record success or failure per line item.
- Provide a way to retry only failed line items without reposting successful ones.
- Expose a batch summary or audit trail so operations users can see who created, approved, rejected, posted, or retried a batch.
- Protect against double posting or repeated submission of the same action.

Technical Requirements
- Use Laravel 12 or 13 with PHP 8.2 or higher.
- Use local, code-contained persistence and cache only. A filesystem-backed approach is preferred. Embedded SQLite is acceptable if you keep everything self-contained in the repo and Docker setup.
- Protect the API with authentication. Sanctum is preferred, but a comparable approach is acceptable.
- Tenant isolation is mandatory. You do not need to build full tenant provisioning, but your design must prevent cross-tenant reads and writes.
- Use Docker Compose so the solution can be started locally with minimal setup.
- Keep controllers thin and use request validation, service classes, jobs or events, and clear domain modeling.
- Provide API documentation as a Postman collection or OpenAPI or Swagger file.
- Although test implementation is not required for this assessment, you will be required to implement testing on the job.

Nice to Have
- Database or Redis-backed queue worker
- Sample CSV template download
- Webhook or notification stub when batch processing completes
- Filtering or pagination for batch history
- A clear idempotency strategy documented in the README

Submission Requirements
1. Package the full source code in a single zip file.
2. Include a README with setup steps, Docker commands, sample credentials, a sample batch file, and architecture notes.
3. Include a Postman collection or OpenAPI file.
4. Include a short note describing assumptions, tradeoffs, and what you would improve next with more time.
5. Email the zip file to hr@quantsystems.tech.

Evaluation Criteria
Quality of domain modeling and API design
- Tenant-aware security and data isolation
- Thoughtfulness of async processing and retry handling
- Code structure, readability, and maintainability
- Developer experience and clarity of documentation

Important Notes
- Real payment or ledger integrations are not required. A stubbed provider or fake posting service is acceptable.
- The solution should run without external infrastructure dependencies for persistence or caching.
- Keep storage local to the codebase or container.
- You may use Al tools while building the solution, but disclose where they assisted you in the README.
- We care more about correctness, product judgment, and tradeoff awareness than about building every possible extra feature.