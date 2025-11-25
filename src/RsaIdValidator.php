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
 * @version     1.0.0
 * @link        https://github.com/nkenjana/phprsa-id-validator
 */

declare(strict_types=1);

namespace PhpRsaIdValidator;

use DateTime;
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
    
    /**
     * Validates a South African ID number
     * 
     * @param string $id The ID number to validate
     * @return array Validation result with extracted information
     * @throws InvalidArgumentException If input is not a string
     */
    public function validate(string $id): array
    {
        // Input validation
        if (!is_string($id)) {
            throw new InvalidArgumentException('ID must be a string');
        }

        $id = trim($id);
        $id = preg_replace('/\s+/', '', $id); // Remove any whitespace

        // Basic format validation
        if (!$this->validateFormat($id)) {
            return [
                'valid' => false, 
                'error' => 'Invalid ID format: must be exactly 13 digits'
            ];
        }

        try {
            // Extract components from ID number
            $components = $this->extractComponents($id);
            
            // Validate birth date
            if (!$this->validateBirthDate($components['yy'], $components['mm'], $components['dd'])) {
                return [
                    'valid' => false, 
                    'error' => 'Invalid birth date in ID'
                ];
            }

            // Validate check digit using SA Luhn algorithm
            if (!$this->validateLuhn($id)) {
                return [
                    'valid' => false, 
                    'error' => 'Invalid check digit (Luhn validation failed)'
                ];
            }

            // Return successful validation with extracted data
            return $this->buildSuccessResponse($components, $id);

        } catch (\Exception $e) {
            return [
                'valid' => false, 
                'error' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validates ID number format
     * 
     * @param string $id The ID number to validate
     * @return bool True if format is valid
     */
    private function validateFormat(string $id): bool
    {
        return preg_match(self::PATTERN, $id) === 1 && strlen($id) === self::ID_LENGTH;
    }

    /**
     * Extracts and validates components from ID number
     * 
     * @param string $id The ID number
     * @return array Extracted components
     */
    private function extractComponents(string $id): array
    {
        return [
            'yy' => substr($id, 0, 2),
            'mm' => substr($id, 2, 2),
            'dd' => substr($id, 4, 2),
            'gender_digits' => substr($id, 6, 4),
            'citizenship_digit' => substr($id, 10, 1),
            'check_digit' => substr($id, 12, 1)
        ];
    }

    /**
     * Validates birth date with century determination
     * 
     * @param string $yy Two-digit year
     * @param string $mm Two-digit month
     * @param string $dd Two-digit day
     * @return bool True if date is valid
     */
    private function validateBirthDate(string $yy, string $mm, string $dd): bool
    {
        $fullYear = $this->determineCentury($yy, $mm, $dd);
        return checkdate((int)$mm, (int)$dd, $fullYear);
    }

    /**
     * Determines the correct century for birth year
     * Uses age-based logic to handle century crossover
     * 
     * @param string $yy Two-digit year
     * @param string $mm Two-digit month
     * @param string $dd Two-digit day
     * @return int Full four-digit year
     */
    private function determineCentury(string $yy, string $mm, string $dd): int
    {
        $candidateYear20xx = (int)('20' . $yy);
        $candidateYear19xx = (int)('19' . $yy);
        
        // Calculate age for both century possibilities
        $age20xx = $this->calculateAge($candidateYear20xx, (int)$mm, (int)$dd);
        $age19xx = $this->calculateAge($candidateYear19xx, (int)$mm, (int)$dd);
        
        // Prefer the century that gives a reasonable age (0-122 years)
        if ($age20xx >= 0 && $age20xx <= 122) {
            return $candidateYear20xx;
        }
        
        if ($age19xx >= 0 && $age19xx <= 122) {
            return $candidateYear19xx;
        }
        
        // Default to 19xx if both are problematic (should be caught by date validation)
        return $candidateYear19xx;
    }

    /**
     * Calculates age based on birth date
     * 
     * @param int $year Birth year
     * @param int $month Birth month
     * @param int $day Birth day
     * @return int Age in years
     */
    private function calculateAge(int $year, int $month, int $day): int
    {
        $today = new DateTime();
        $birthDate = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));
        
        if (!$birthDate) {
            return -1;
        }
        
        $age = $today->diff($birthDate)->y;
        
        // If birth date hasn't occurred yet this year, subtract 1
        $currentMonth = (int)$today->format('m');
        $currentDay = (int)$today->format('d');
        
        if ($currentMonth < $month || ($currentMonth === $month && $currentDay < $day)) {
            $age--;
        }
        
        return $age;
    }

    /**
     * Validates ID number using the official South African Luhn variant
     * 
     * Algorithm:
     * 1) Sum digits in odd positions (1,3,5,7,9,11) — note: positions are 1-indexed
     * 2) Concatenate digits in even positions (2,4,6,8,10,12) into a number, multiply by 2
     * 3) Sum the digits of that product
     * 4) Add results of step 1 and 3, compute (10 - (total % 10)) % 10 → this is expected check digit
     * 5) Compare with last digit (position 13)
     * 
     * @param string $id The ID number to validate
     * @return bool True if Luhn check passes
     */
    private function validateLuhn(string $id): bool
    {
        // Ensure we have 13 digits
        if (strlen($id) !== self::ID_LENGTH) {
            return false;
        }

        // 1) Sum digits in odd positions (1,3,5,7,9,11) -> zero-based indices 0,2,4,6,8,10
        $sumOdd = 0;
        for ($i = 0; $i <= 10; $i += 2) {
            $sumOdd += (int)$id[$i];
        }

        // 2) Concatenate even position digits (2,4,6,8,10,12) -> indices 1,3,5,7,9,11
        $evenConcat = '';
        for ($i = 1; $i <= 11; $i += 2) {
            $evenConcat .= $id[$i];
        }

        // Multiply the concatenated number by 2
        $evenProduct = (string)(((int)$evenConcat) * 2);

        // 3) Sum digits of the product
        $sumEvenDigits = 0;
        $digits = str_split($evenProduct);
        foreach ($digits as $d) {
            $sumEvenDigits += (int)$d;
        }

        // 4) Add sums and compute check digit
        $total = $sumOdd + $sumEvenDigits;
        $calculatedCheck = (10 - ($total % 10)) % 10;

        // 5) Compare with last digit (index 12)
        return $calculatedCheck === (int)$id[12];
    }

    /**
     * Determines gender from ID number
     * 
     * @param string $genderDigits The gender digits from ID
     * @return string Gender description
     */
    private function determineGender(string $genderDigits): string
    {
        return ((int)$genderDigits >= self::GENDER_THRESHOLD) ? 'Male' : 'Female';
    }

    /**
     * Determines citizenship status from ID number
     * 
     * @param string $citizenshipDigit The citizenship digit from ID
     * @return string Citizenship description
     */
    private function determineCitizenship(string $citizenshipDigit): string
    {
        return ($citizenshipDigit === '0') ? 'SA Citizen' : 'Permanent Resident';
    }

    /**
     * Builds success response with extracted data
     * 
     * @param array $components Extracted ID components
     * @param string $id Original ID number
     * @return array Success response
     */
    private function buildSuccessResponse(array $components, string $id): array
    {
        $fullYear = $this->determineCentury($components['yy'], $components['mm'], $components['dd']);
        
        return [
            'valid' => true,
            'id_number' => $id,
            'date_of_birth' => sprintf('%04d-%02d-%02d', $fullYear, $components['mm'], $components['dd']),
            'gender' => $this->determineGender($components['gender_digits']),
            'citizenship' => $this->determineCitizenship($components['citizenship_digit']),
            'check_digit' => $components['check_digit'],
            'components' => [
                'birth_year' => $fullYear,
                'birth_month' => (int)$components['mm'],
                'birth_day' => (int)$components['dd'],
                'gender_code' => (int)$components['gender_digits'],
                'citizenship_code' => (int)$components['citizenship_digit']
            ]
        ];
    }
}
