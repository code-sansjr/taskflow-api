<?php

declare(strict_types=1);

namespace TaskFlow\Controllers;

use TaskFlow\Auth\Auth;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;
use TaskFlow\Repositories\ProjectRepository;
use TaskFlow\Repositories\TaskRepository;

final class DashboardController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->userId($request);
        $summary = $this->tasks->summaryForUser($userId);
        $projectCount = $this->projects->countByUser($userId);

        $completionRate = $summary['total'] > 0
            ? round(($summary['done'] / $summary['total']) * 100, 1)
            : 0.0;

        return Response::json([
            'data' => [
                'projects' => $projectCount,
                'tasks' => $summary,
                'completion_rate' => $completionRate,
            ],
        ]);
    }
}
