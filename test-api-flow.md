# QuantFlow API - Complete Testing Guide

Follow these steps in the Swagger UI (`http://localhost:8000/api/docs`) to fully test the batch document processing workflow.

## 1. Authentication
First, you need a Bearer token to authorize your requests.

### A. Register User (`POST /api/v1/auth/register`)
Use this JSON payload to create an Admin user:
```json
{
  "name": "Forahia Bank Manager",
  "email": "forahia@quantflow.app",
  "password": "password123",
  "password_confirmation": "password123" 
}
```
*Take the `token` from the response and paste it into the **Authorize** (Padlock) button at the top of Swagger.*

---

## 2. Batch Upload
Now that you are authenticated, upload your batch file.

### A. Upload Batch (`POST /api/v1/batches`)
- In the `file` field, select the `sample.csv` file from your project folder.
- Click **Execute**.
- **Important:** Copy the `uuid` from the response (e.g., `019ea82b-6b92-...`). This is your **Batch UUID**.

---

## 3. Processing the Batch
Submit the batch so the background queue parses the CSV into individual Document records.

### A. Submit Batch (`POST /api/v1/batches/{uuid}/submit`)
- Paste your **Batch UUID** into the path parameter.
- Click **Execute**.
- *(This tells the system to start reading the file and validating the rows).*

### B. Check Batch Status (`GET /api/v1/batches/{uuid}/status`)
- Paste your **Batch UUID** into the path parameter.
- Click **Execute**.
- You should see the status transition to `completed` or `partially_failed`, along with the total records processed.

---

## 4. Managing Documents
Now let's view the rows that were created from your CSV.

### A. List Documents in Batch (`GET /api/v1/batches/{uuid}/documents`)
- Paste your **Batch UUID** into the path parameter.
- Click **Execute**.
- You will see all 6 documents. Notice that valid rows are `status: validated` and invalid rows are `status: failed` with exact failure reasons.
- **Important:** Copy the `uuid` of **one** of the `validated` documents. This is your **Document UUID**.

### B. Add a Note to a Document (`PATCH /api/v1/documents/{uuid}`)
- Paste the **Document UUID** into the path parameter.
- Use this JSON payload:
```json
{
  "metadata": {
    "note": "Expedite this payout!"
  }
}
```
- Click **Execute**.

---

## 5. Approvals
The valid documents have generated Approval Requests for management to sign off on.

### A. List Pending Approvals (`GET /api/v1/approvals`)
- Click **Execute** (no parameters needed).
- Find an approval request in the list and copy its **`id`** (an integer, e.g., `1` or `2`).

### B. Approve the Payout (`POST /api/v1/approvals/{id}/approve`)
- Paste the integer **`id`** into the path parameter.
- Use this JSON payload:
```json
{
  "comment": "Funds verified, proceed with payout."
}
```
- Click **Execute**.

---

## 6. Audit Trail
Verify that the system tracked every single action you just took.

### A. List Audit Logs (`GET /api/v1/audit-logs`)
- Click **Execute**.
- You will see an immutable ledger showing the batch creation, batch processing, document updates, and approval signatures!