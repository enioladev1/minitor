<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Ingest\RecordIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    public function __invoke(Request $request, RecordIngestor $ingestor): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['message' => 'Missing project token.'], 401);
        }

        $project = Project::query()->where('token', $token)->first();

        if ($project === null) {
            return response()->json(['message' => 'Invalid project token.'], 401);
        }

        $validated = $request->validate([
            'records' => ['present', 'array'],
            'records.*' => ['array'],
        ]);

        $ingestor->ingest($project, $validated['records']);

        return response()->json(['status' => 'ok', 'accepted' => count($validated['records'])]);
    }
}
