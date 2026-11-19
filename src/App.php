<?php

declare(strict_types=1);

namespace TaskFlow;

use TaskFlow\Auth\Auth;
use TaskFlow\Auth\JwtService;
use TaskFlow\Controllers\AuthController;
use TaskFlow\Controllers\DashboardController;
use TaskFlow\Controllers\ProjectController;
use TaskFlow\Controllers\TaskController;
use TaskFlow\Core\Env;
use TaskFlow\Core\HttpException;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;
use TaskFlow\Core\Router;
use TaskFlow\Repositories\ProjectRepository;
use TaskFlow\Repositories\TaskRepository;
use TaskFlow\Repositories\UserRepository;

final class App
{
    private readonly Router $router;

    public function __construct(\PDO $pdo)
    {
        $users = new UserRepository($pdo);
        $projects = new ProjectRepository($pdo);
        $tasks = new TaskRepository($pdo);

        $jwt = new JwtService(
            (string) Env::get('JWT_SECRET', ''),
            (int) Env::get('JWT_TTL', 3600)
        );
        $auth = new Auth($jwt);

        $authController = new AuthController($users, $jwt, $auth);
        $projectController = new ProjectController($auth, $projects);
        $taskController = new TaskController($auth, $projects, $tasks);
        $dashboardController = new DashboardController($auth, $projects, $tasks);

        $this->router = new Router();

        $this->router->get('/api/health', static fn (): Response => Response::json([
            'data' => [
                'status' => 'ok',
                'service' => 'TaskFlow API',
                'time' => date(DATE_ATOM),
            ],
        ]));

        $this->router->post('/api/auth/register', [$authController, 'register']);
        $this->router->post('/api/auth/login', [$authController, 'login']);
        $this->router->get('/api/me', [$authController, 'me']);

        $this->router->get('/api/projects', [$projectController, 'index']);
        $this->router->post('/api/projects', [$projectController, 'store']);
        $this->router->get('/api/projects/{id}', [$projectController, 'show']);
        $this->router->put('/api/projects/{id}', [$projectController, 'update']);
        $this->router->patch('/api/projects/{id}', [$projectController, 'update']);
        $this->router->delete('/api/projects/{id}', [$projectController, 'destroy']);

        $this->router->get('/api/projects/{project_id}/tasks', [$taskController, 'index']);
        $this->router->post('/api/projects/{project_id}/tasks', [$taskController, 'store']);
        $this->router->get('/api/tasks/{id}', [$taskController, 'show']);
        $this->router->put('/api/tasks/{id}', [$taskController, 'update']);
        $this->router->patch('/api/tasks/{id}', [$taskController, 'update']);
        $this->router->delete('/api/tasks/{id}', [$taskController, 'destroy']);

        $this->router->get('/api/dashboard', [$dashboardController, 'index']);
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $exception) {
            $error = [
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ];

            if ($exception->details !== []) {
                $error['details'] = $exception->details;
            }

            return Response::json(['error' => $error], $exception->status);
        } catch (\Throwable $exception) {
            error_log((string) $exception);

            $message = Env::bool('APP_DEBUG')
                ? $exception->getMessage()
                : 'An unexpected error occurred.';

            return Response::json([
                'error' => [
                    'code' => 'internal_error',
                    'message' => $message,
                ],
            ], 500);
        }
    }
}
