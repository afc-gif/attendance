<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeploymentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DeploymentMonitorController extends Controller
{
    public function index(DeploymentHistoryService $history): Response
    {
        $this->authorizeAdmin();

        return response()->view('admin.deployments.index', [
            'snapshot' => $history->snapshot(),
        ]);
    }

    public function data(DeploymentHistoryService $history): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json($history->snapshot())
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();

        if (! ($user instanceof User) || ! $user->isAdmin()) {
            abort(403, 'Unauthorized. Deployment monitoring is restricted to administrators.');
        }
    }
}
