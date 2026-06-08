<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class AuditController extends Controller
{
    use HasApiResponse;

    #[OA\Get(
        path: "/api/v1/audit-logs",
        summary: "List audit logs",
        security: [["bearerAuth" => []]],
        tags: ["Audit"],
        responses: [
            new OA\Response(response: 200, description: "Audit logs retrieved")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return $this->success('Audit logs retrieved.', $logs->items(), [
            'total' => $logs->total(),
            'page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/audit-logs/{id}",
        summary: "Get audit log details",
        security: [["bearerAuth" => []]],
        tags: ["Audit"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Audit log details retrieved")
        ]
    )]
    public function show(AuditLog $audit): JsonResponse
    {
        return $this->success('Audit log details retrieved.', [
            'audit' => $audit->load(['user', 'auditable']),
        ]);
    }
}
