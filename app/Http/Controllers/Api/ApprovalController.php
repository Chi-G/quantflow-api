<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class ApprovalController extends Controller
{
    use HasApiResponse;

    public function __construct(private readonly ApprovalService $approvalService)
    {
    }

    #[OA\Get(
        path: "/api/v1/approvals",
        summary: "List pending approvals",
        security: [["bearerAuth" => []]],
        tags: ["Approvals"],
        responses: [
            new OA\Response(response: 200, description: "Pending approvals retrieved")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalRequest::with(['document', 'requester'])
            ->where('status', \App\Enums\ApprovalStatus::Pending->value);

        $approvals = $query->paginate((int) $request->query('per_page', 15));

        return $this->success('Pending approvals retrieved.', $approvals->items(), [
            'total' => $approvals->total(),
            'page' => $approvals->currentPage(),
            'last_page' => $approvals->lastPage(),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/approvals/{id}",
        summary: "Get approval details",
        security: [["bearerAuth" => []]],
        tags: ["Approvals"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Approval request details retrieved")
        ]
    )]
    public function show(ApprovalRequest $approval): JsonResponse
    {
        return $this->success('Approval request details retrieved.', [
            'approval' => $approval->load(['document', 'requester', 'approver', 'batch']),
        ]);
    }

    #[OA\Post(
        path: "/api/v1/approvals/{id}/approve",
        summary: "Approve a document",
        security: [["bearerAuth" => []]],
        tags: ["Approvals"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "comment", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Document approved successfully")
        ]
    )]
    public function approve(ApprovalRequest $approval, Request $request): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string']]);

        try {
            $this->approvalService->approve($approval, $request->user(), $request->input('comment'));
            return $this->success('Document approved successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: "/api/v1/approvals/{id}/reject",
        summary: "Reject a document",
        security: [["bearerAuth" => []]],
        tags: ["Approvals"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["comment"],
                properties: [
                    new OA\Property(property: "comment", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Document rejected successfully")
        ]
    )]
    public function reject(ApprovalRequest $approval, Request $request): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string']]);

        try {
            $this->approvalService->reject($approval, $request->user(), $request->input('comment'));
            return $this->success('Document rejected successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
