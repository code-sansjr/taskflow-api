<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TaskFlow\Core\HttpException;
use TaskFlow\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testValidPayloadIsReturned(): void
    {
        $data = Validator::validate([
            'name' => 'Sandro',
            'email' => 'sandro@example.com',
        ], [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190',
        ]);

        self::assertSame('Sandro', $data['name']);
        self::assertSame('sandro@example.com', $data['email']);
    }

    public function testRequiredFieldFails(): void
    {
        try {
            Validator::validate([], ['name' => 'required|string']);
            self::fail('Validation should have failed.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->status);
            self::assertSame('validation_error', $exception->errorCode);
            self::assertArrayHasKey('name', $exception->details['fields']);
        }
    }

    public function testNullableFieldAcceptsNull(): void
    {
        $data = Validator::validate(
            ['description' => null],
            ['description' => 'nullable|string|max:10']
        );

        self::assertNull($data['description']);
    }

    public function testInvalidDateFails(): void
    {
        $this->expectException(HttpException::class);

        Validator::validate(
            ['due_date' => '2026-02-30'],
            ['due_date' => 'required|date']
        );
    }

    public function testInvalidEnumFails(): void
    {
        $this->expectException(HttpException::class);

        Validator::validate(
            ['status' => 'archived'],
            ['status' => 'required|string|in:todo,doing,done']
        );
    }

    public function testOptionalFieldIsSkippedWhenSometimesIsUsed(): void
    {
        $data = Validator::validate(
            ['title' => 'Nova tarefa'],
            ['title' => 'sometimes|required|string', 'description' => 'sometimes|nullable|string']
        );

        self::assertSame(['title' => 'Nova tarefa'], $data);
    }
}
