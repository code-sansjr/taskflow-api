<?php

declare(strict_types=1);

namespace TaskFlow\Controllers;

use PDOException;
use TaskFlow\Auth\Auth;
use TaskFlow\Auth\JwtService;
use TaskFlow\Core\Env;
use TaskFlow\Core\HttpException;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;
use TaskFlow\Core\Validator;
use TaskFlow\Repositories\UserRepository;

final class AuthController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JwtService $jwt,
        private readonly Auth $auth
    ) {
    }

    public function register(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:8|max:72',
        ]);

        $name = trim((string) $data['name']);
        $email = strtolower(trim((string) $data['email']));
        $password = (string) $data['password'];

        if (mb_strlen($name) < 2) {
            throw new HttpException(
                422,
                'Validation failed.',
                'validation_error',
                ['fields' => ['name' => ['The name must be at least 2 characters.']]]
            );
        }

        if ($this->users->emailExists($email)) {
            throw new HttpException(
                422,
                'Validation failed.',
                'validation_error',
                ['fields' => ['email' => ['This email is already registered.']]]
            );
        }

        $hashCost = max(4, min(31, (int) Env::get('PASSWORD_HASH_COST', 12)));

        try {
            $user = $this->users->create(
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT, ['cost' => $hashCost])
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException(
                    422,
                    'Validation failed.',
                    'validation_error',
                    ['fields' => ['email' => ['This email is already registered.']]]
                );
            }

            throw $exception;
        }

        $token = $this->jwt->issue((int) $user['id']);

        return Response::json([
            'data' => [
                'user' => $user,
                'token' => $token['token'],
                'token_type' => 'Bearer',
                'expires_in' => $token['expires_in'],
            ],
        ], 201);
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'email' => 'required|email|max:190',
            'password' => 'required|string|max:72',
        ]);

        $email = strtolower(trim((string) $data['email']));
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify((string) $data['password'], (string) $user['password_hash'])) {
            throw new HttpException(401, 'Invalid email or password.', 'invalid_credentials');
        }

        unset($user['password_hash']);
        $token = $this->jwt->issue((int) $user['id']);

        return Response::json([
            'data' => [
                'user' => $user,
                'token' => $token['token'],
                'token_type' => 'Bearer',
                'expires_in' => $token['expires_in'],
            ],
        ]);
    }

    public function me(Request $request): Response
    {
        $userId = $this->auth->userId($request);
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new HttpException(401, 'User not found.', 'unauthorized');
        }

        return Response::json(['data' => $user]);
    }
}
