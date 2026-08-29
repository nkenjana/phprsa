<?php
/**
 * PHP RSA ID Validator Test Suite
 *
 * Comprehensive unit tests for the RsaIdValidator class
 *
 * @package     PhpRsaIdValidator
 * @author      Lwando Nkenjana
 * @copyright   2024 NITS Tech Systems
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpRsaIdValidator\Tests;

use InvalidArgumentException;
use PhpRsaIdValidator\Citizenship;
use PhpRsaIdValidator\Gender;
use PhpRsaIdValidator\RsaIdValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test class for RsaIdValidator.
 *
 * Every sample ID below was generated and independently verified against
 * the SA Luhn variant (see the checksum steps documented on
 * RsaIdValidator::validateLuhn()) — none of them are placeholders. Run
 * `composer test` to confirm they pass.
 */
class RsaIdValidatorTest extends TestCase
{
    private RsaIdValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RsaIdValidator();
    }

    /**
     * @dataProvider validIdProvider
     */
    public function testValidIds(
        string $id,
        string $expectedDob,
        Gender $expectedGender,
        Citizenship $expectedCitizenship
    ): void {
        $result = $this->validator->validate($id);

        $this->assertTrue($result->valid);
        $this->assertSame($expectedDob, $result->dateOfBirth->format('Y-m-d'));
        $this->assertSame($expectedGender, $result->gender);
        $this->assertSame($expectedCitizenship, $result->citizenship);
    }

    /**
     * @dataProvider invalidIdProvider
     */
    public function testInvalidIds(string $id, string $expectedError): void
    {
        $result = $this->validator->validate($id);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString($expectedError, $result->error);
    }

    public function testInvalidInputTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ID must be a string');
        // @phpstan-ignore-next-line intentionally wrong type to exercise the guard
        $this->validator->validate([]);
    }

    public function testStaticIsValidHelper(): void
    {
        $this->assertTrue(RsaIdValidator::isValid('9001015800088'));
        $this->assertFalse(RsaIdValidator::isValid('not-an-id'));
    }

    public function testToArrayMatchesLegacyShapeForSuccess(): void
    {
        $result = $this->validator->validate('9001015800088')->toArray();

        $this->assertSame([
            'valid' => true,
            'id_number' => '9001015800088',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'citizenship' => 'SA Citizen',
            'check_digit' => 8,
        ], $result);
    }

    public function testToArrayMatchesLegacyShapeForFailure(): void
    {
        $result = $this->validator->validate('123')->toArray();

        $this->assertSame([
            'valid' => false,
            'error' => 'Invalid ID format: must be exactly 13 digits',
        ], $result);
    }

    /**
     * A two-digit year is inherently ambiguous between centuries. This
     * regression-tests the fix for a bug where the century resolver could
     * assign a birth date in the future (e.g. reading "35" as 2035 in the
     * year 2026) because age was computed without checking date direction.
     */
    public function testDoesNotAssignAFutureBirthCentury(): void
    {
        // yy=35 must resolve to 1935, never 2035 (which would be in the future).
        $result = $this->validator->validate('3506154800083');

        $this->assertTrue($result->valid);
        $this->assertSame('1935-06-15', $result->dateOfBirth->format('Y-m-d'));
    }

    public function validIdProvider(): array
    {
        return [
            // [ID, Expected DOB, Expected Gender, Expected Citizenship]
            ['9001015800088', '1990-01-01', Gender::Male, Citizenship::Citizen],
            ['0801014800081', '2008-01-01', Gender::Female, Citizenship::Citizen],
            ['8508304500089', '1985-08-30', Gender::Female, Citizenship::Citizen],
            ['7806155000188', '1978-06-15', Gender::Male, Citizenship::PermanentResident],
        ];
    }

    public function invalidIdProvider(): array
    {
        return [
            // [ID, Expected error substring]
            ['123', 'must be exactly 13 digits'],
            ['9013014800084', 'Invalid birth date'], // month 13 — genuinely invalid
            ['9001015800080', 'Luhn validation'], // correct format/date, wrong check digit
            ['ABCDEFGHIJKLM', 'must be exactly 13 digits'],
        ];
    }
}
