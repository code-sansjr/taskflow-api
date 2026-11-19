<?php

declare(strict_types=1);

namespace TaskFlow\Core;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $rulesForField = explode('|', $ruleString);
            $present = array_key_exists($field, $data);
            $isRequired = in_array('required', $rulesForField, true);
            $isNullable = in_array('nullable', $rulesForField, true);
            $isSometimes = in_array('sometimes', $rulesForField, true);

            if (!$present) {
                if ($isSometimes) {
                    continue;
                }

                if ($isRequired) {
                    $errors[$field][] = 'The field is required.';
                    continue;
                }

                if ($isNullable) {
                    $validated[$field] = null;
                }

                continue;
            }

            $value = $data[$field];

            if ($isRequired && ($value === null || $value === '')) {
                $errors[$field][] = 'The field is required.';
                continue;
            }

            if (($value === null || $value === '') && $isNullable) {
                $validated[$field] = null;
                continue;
            }

            foreach ($rulesForField as $rule) {
                if (in_array($rule, ['sometimes', 'required', 'nullable'], true)) {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $message = match ($name) {
                    'string' => is_string($value) ? null : 'The field must be a string.',
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) === false
                        ? 'The field must be an integer.'
                        : null,
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                        ? null
                        : 'The field must be a valid email address.',
                    'min' => is_string($value) && mb_strlen($value) < (int) $parameter
                        ? sprintf('The field must be at least %d characters.', (int) $parameter)
                        : null,
                    'max' => is_string($value) && mb_strlen($value) > (int) $parameter
                        ? sprintf('The field may not be greater than %d characters.', (int) $parameter)
                        : null,
                    'in' => in_array((string) $value, explode(',', (string) $parameter), true)
                        ? null
                        : 'The selected value is invalid.',
                    'date' => is_string($value) && self::isValidDate($value)
                        ? null
                        : 'The field must be a valid date in Y-m-d format.',
                    default => throw new \InvalidArgumentException("Unknown validation rule: {$name}"),
                };

                if ($message !== null) {
                    $errors[$field][] = $message;
                    break;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new HttpException(
                422,
                'Validation failed.',
                'validation_error',
                ['fields' => $errors]
            );
        }

        return $validated;
    }

    private static function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
