<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Models\Batch;
use App\Services\BatchService;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BatchController extends Controller
{
    use HasApiResponse;

    public function __construct(private readonly BatchService $batchService) {}

    #[OA\Get(
        path: '/api/v1/batches/template',
        summary: 'Download a sample CSV template',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        responses: [
            new OA\Response(response: 200, description: 'CSV file downloaded'),
        ]
    )]
    public function template(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="quantflow_template.csv"',
        ];

        $columns = ['reference_number', 'amount', 'currency', 'recipient_name', 'recipient_account', 'bank_code'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['TRX-001', '50000.00', 'NGN', 'Emeka Okafor', '0123456789', '058']);
            fputcsv($file, ['TRX-002', '120000.00', 'NGN', 'Amaka Nwosu', '0987654321', '033']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    #[OA\Post(
        path: '/api/v1/batches',
        summary: 'Upload a batch',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Batch created successfully'),
        ]
    )]
    public function store(StoreBatchRequest $request): JsonResponse
    {
        $batch = $this->batchService->createBatch($request, $request->user());

        return $this->success('Batch created successfully.', [
            'batch' => $batch,
        ], [], 201);
    }

    #[OA\Get(
        path: '/api/v1/batches',
        summary: 'List batches',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        responses: [
            new OA\Response(response: 200, description: 'Batches retrieved'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Batch::with('uploader')->orderBy('created_at', 'desc');

        if ($request->user()->role->value === 'operator') {
            $query->where('uploaded_by', $request->user()->id);
        }

        $batches = $query->paginate((int) $request->query('per_page', 15));

        return $this->success('Batches retrieved.', $batches->items(), [
            'total' => $batches->total(),
            'page' => $batches->currentPage(),
            'last_page' => $batches->lastPage(),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/batches/{uuid}',
        summary: 'Get batch details',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch details retrieved'),
        ]
    )]
    public function show(Batch $batch): JsonResponse
    {
        $stats = $this->batchService->getBatchStats($batch);

        return $this->success('Batch details retrieved.', [
            'batch' => $batch->load('uploader'),
            'stats' => $stats,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/batches/{uuid}/submit',
        summary: 'Submit batch for processing',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch submitted for processing'),
        ]
    )]
    public function submit(Batch $batch, Request $request): JsonResponse
    {
        try {
            $this->batchService->submitBatch($batch, $request->user());

            return $this->success('Batch submitted for processing.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/api/v1/batches/{uuid}/retry',
        summary: 'Retry a failed batch',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch retry initiated'),
        ]
    )]
    public function retry(Batch $batch, Request $request): JsonResponse
    {
        try {
            $this->batchService->retryBatch($batch, $request->user());

            return $this->success('Batch retry initiated.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/api/v1/batches/{uuid}/documents',
        summary: 'List documents in a batch',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch documents retrieved'),
        ]
    )]
    public function documents(Batch $batch, Request $request): JsonResponse
    {
        $documents = $batch->documents()->paginate((int) $request->query('per_page', 15));

        return $this->success('Batch documents retrieved.', $documents->items(), [
            'total' => $documents->total(),
            'page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/batches/{uuid}/status',
        summary: 'Get batch status',
        security: [['bearerAuth' => []]],
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch status retrieved'),
        ]
    )]
    public function status(Batch $batch): JsonResponse
    {
        return $this->success('Batch status retrieved.', [
            'status' => $batch->status->value,
            'total_records' => $batch->total_records,
            'processed_records' => $batch->processed_records,
        ]);
    }
}
