<?php
/**
 * PHP RSA ID Validator
 *
 * A professional validation library for South African ID numbers
 * Compliant with South African Department of Home Affairs specifications
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
use InvalidArgumentException;

/**
 * RSA ID Number Validator
 *
 * Validates South African ID numbers according to official specifications:
 * - Format: YYMMDDSSSSCAZ
 * - Luhn algorithm verification (SA variant)
 * - Date validation with century determination
 * - Gender and citizenship extraction
 *
 * @final This class should not be extended
 */
final class RsaIdValidator
{
    private const ID_LENGTH = 13;
    private const PATTERN = '/^[0-9]{13}$/';
    private const GENDER_THRESHOLD = 5000;
    private const MAX_PLAUSIBLE_AGE = 122;

    /**
     * Validates a South African ID number.
     *
     * Accepts `mixed` (rather than a strict `string` type) because this is
     * usually called at a public boundary (form input, request bodies) where
     * the caller doesn't control the incoming type. A non-string input
     * throws InvalidArgumentException with a clear message instead of a
     * generic TypeError.
     *
     * @throws InvalidArgumentException If input is not a string
     */
    public function validate(mixed $id): IdValidationResult
    {
        if (!is_string($id)) {
            throw new InvalidArgumentException('ID must be a string');
        }

        $id = trim($id);
        $id = preg_replace('/\s+/', '', $id); // Remove any internal whitespace

        if (!$this->validateFormat($id)) {
            return IdValidationResult::failure('Invalid ID format: must be exactly 13 digits');
        }

        $components = $this->extractComponents($id);

        $fullYear = $this->determineCentury($components['yy'], $components['mm'], $components['dd']);

        if ($fullYear === null) {
            return IdValidationResult::failure('Invalid birth date in ID');
        }

        $mm = (int) $components['mm'];
        $dd = (int) $components['dd'];

        if (!checkdate($mm, $dd, $fullYear)) {
            return IdValidationResult::failure('Invalid birth date in ID');
        }

        if (!$this->validateLuhn($id)) {
            return IdValidationResult::failure('Invalid check digit (Luhn validation failed)');
        }

        $dateOfBirth = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $fullYear, $mm, $dd));

        return IdValidationResult::success(
            idNumber: $id,
            dateOfBirth: $dateOfBirth,
            gender: $this->determineGender($components['gender_digits']),
            citizenship: $this->determineCitizenship($components['citizenship_digit']),
            checkDigit: (int) $components['check_digit'],
        );
    }

    /**
     * Convenience helper for callers who only need a yes/no answer.
     */
    public static function isValid(mixed $id): bool
    {
        return (new self())->validate($id)->valid;
    }

    private function validateFormat(string $id): bool
    {
        return preg_match(self::PATTERN, $id) === 1 && strlen($id) === self::ID_LENGTH;
    }

    /**
     * @return array{yy: string, mm: string, dd: string, gender_digits: string, citizenship_digit: string, check_digit: string}
     */
    private function extractComponents(string $id): array
    {
        return [
            'yy' => substr($id, 0, 2),
            'mm' => substr($id, 2, 2),
            'dd' => substr($id, 4, 2),
            'gender_digits' => substr($id, 6, 4),
            'citizenship_digit' => substr($id, 10, 1),
            'check_digit' => substr($id, 12, 1),
        ];
    }

    /**
     * Determines the correct century for a two-digit birth year.
     *
     * An RSA ID number only encodes a two-digit year, so "35" is genuinely
     * ambiguous between 1935 and 2035. We resolve the ambiguity the only
     * way that's actually sound: a candidate century is rejected outright
     * if it would place the birth date in the future (nobody can be born
     * in the future), and otherwise accepted if it produces a plausible
     * age. 20xx is preferred when both are plausible, since that's the
     * far more common real-world case for a 2-digit-year ID.
     *
     * Returns null if neither century produces a valid, non-future,
     * plausible-age date.
     */
    private function determineCentury(string $yy, string $mm, string $dd): ?int
    {
        $mmInt = (int) $mm;
        $ddInt = (int) $dd;

        foreach ([(int) ('20' . $yy), (int) ('19' . $yy)] as $candidateYear) {
            if (!checkdate($mmInt, $ddInt, $candidateYear)) {
                continue;
            }

            $age = $this->calculateAge($candidateYear, $mmInt, $ddInt);

            if ($age !== null && $age >= 0 && $age <= self::MAX_PLAUSIBLE_AGE) {
                return $candidateYear;
            }
        }

        return null;
    }

    /**
     * Calculates age in whole years for a given birth date.
     *
     * Returns null if the date is invalid or lies in the future — a future
     * birth date is never a valid age for either century candidate.
     */
    private function calculateAge(int $year, int $month, int $day): ?int
    {
        $today = new DateTimeImmutable();
        $birthDate = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-%02d', $year, $month, $day)
        );

        if ($birthDate === false || $birthDate > $today) {
            return null;
        }

        return $today->diff($birthDate)->y;
    }

    /**
     * Validates ID number using the official South African Luhn variant.
     *
     * Algorithm:
     * 1) Sum digits in odd positions (1,3,5,7,9,11) — note: positions are 1-indexed
     * 2) Concatenate digits in even positions (2,4,6,8,10,12) into a number, multiply by 2
     * 3) Sum the digits of that product
     * 4) Add results of step 1 and 3, compute (10 - (total % 10)) % 10 → this is the expected check digit
     * 5) Compare with last digit (position 13)
     */
    private function validateLuhn(string $id): bool
    {
        $sumOdd = 0;
        for ($i = 0; $i <= 10; $i += 2) {
            $sumOdd += (int) $id[$i];
        }

        $evenConcat = '';
        for ($i = 1; $i <= 11; $i += 2) {
            $evenConcat .= $id[$i];
        }

        $evenProduct = (string) (((int) $evenConcat) * 2);

        $sumEvenDigits = 0;
        foreach (str_split($evenProduct) as $d) {
            $sumEvenDigits += (int) $d;
        }

        $total = $sumOdd + $sumEvenDigits;
        $calculatedCheck = (10 - ($total % 10)) % 10;

        return $calculatedCheck === (int) $id[12];
    }

    private function determineGender(string $genderDigits): Gender
    {
        return ((int) $genderDigits >= self::GENDER_THRESHOLD) ? Gender::Male : Gender::Female;
    }

    private function determineCitizenship(string $citizenshipDigit): Citizenship
    {
        return ($citizenshipDigit === '0') ? Citizenship::Citizen : Citizenship::PermanentResident;
    }
}
