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

/**
 * Citizenship status derived from an RSA ID number's citizenship digit.
 */
enum Citizenship: string
{
    case Citizen = 'SA Citizen';
    case PermanentResident = 'Permanent Resident';
}
