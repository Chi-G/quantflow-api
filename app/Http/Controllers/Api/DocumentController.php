<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class DocumentController extends Controller
{
    #[OA\Get(
        path: "/api/v1/documents",
        summary: "List documents",
        security: [["bearerAuth" => []]],
        tags: ["Documents"],
        parameters: [
            new OA\Parameter(name: "filter[status]", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "filter[batch_id]", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "List of documents")
        ]
    )]
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $documents = QueryBuilder::for(Document::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('batch_id'),
            ])
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/api/v1/documents/{uuid}",
        summary: "Get a specific document",
        security: [["bearerAuth" => []]],
        tags: ["Documents"],
        parameters: [
            new OA\Parameter(name: "uuid", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Document details"),
            new OA\Response(response: 404, description: "Document not found")
        ]
    )]
    public function show(Document $document): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $document
        ]);
    }

    #[OA\Patch(
        path: "/api/v1/documents/{uuid}",
        summary: "Update document metadata (pre-submit only)",
        security: [["bearerAuth" => []]],
        tags: ["Documents"],
        parameters: [
            new OA\Parameter(name: "uuid", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "metadata", type: "object")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Document updated"),
            new OA\Response(response: 403, description: "Forbidden - Batch already submitted")
        ]
    )]
    public function update(Request $request, Document $document): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'metadata' => 'required|array'
        ]);

        if ($document->batch->status !== \App\Enums\BatchStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update metadata. Batch has already been submitted.'
            ], 403);
        }

        $document->update([
            'metadata' => array_merge((array) $document->metadata, $validated['metadata'])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document metadata updated successfully.',
            'data' => $document
        ]);
    }
}
