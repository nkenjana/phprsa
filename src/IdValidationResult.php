<?php
/**
 * PHP RSA ID Validator
 *
 * @package     PhpRsaIdValidator
 * @author      Lwando Nkenjana
 * @copyright   2024 NITS Tech Systems
 * @license     MIT
 * @version     2.0.0
 * @link        https://github.com/nkenjana/phprsa-id-validator
 */

declare(strict_types=1);

namespace PhpRsaIdValidator;

use DateTimeImmutable;

/**
 * Result of validating an RSA ID number.
 *
 * Replaces the old bare associative array so callers get typed,
 * autocomplete-friendly access instead of string-keyed lookups.
 * `toArray()` is provided for anyone migrating from the v1 array shape.
 */
final class IdValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?string $idNumber = null,
        public readonly ?DateTimeImmutable $dateOfBirth = null,
        public readonly ?Gender $gender = null,
        public readonly ?Citizenship $citizenship = null,
        public readonly ?int $checkDigit = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function success(
        string $idNumber,
        DateTimeImmutable $dateOfBirth,
        Gender $gender,
        Citizenship $citizenship,
        int $checkDigit,
    ): self {
        return new self(
            valid: true,
            idNumber: $idNumber,
            dateOfBirth: $dateOfBirth,
            gender: $gender,
            citizenship: $citizenship,
            checkDigit: $checkDigit,
        );
    }

    public static function failure(string $error): self
    {
        return new self(valid: false, error: $error);
    }

    /**
     * Backward-compatible array shape matching the v1.x return value,
     * for callers migrating from `validate(): array`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (!$this->valid) {
            return [
                'valid' => false,
                'error' => $this->error,
            ];
        }

        return [
            'valid' => true,
            'id_number' => $this->idNumber,
            'date_of_birth' => $this->dateOfBirth?->format('Y-m-d'),
            'gender' => $this->gender?->value,
            'citizenship' => $this->citizenship?->value,
            'check_digit' => $this->checkDigit,
        ];
    }
}
