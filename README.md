# PHP RSA ID Validator

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)
![Packagist](https://img.shields.io/badge/Packagist-Ready-orange.svg)

A lightweight PHP library for validating South African ID numbers. It performs
full structural checks, validates birth dates (with correct century
resolution), determines gender, identifies citizenship, and verifies the
Luhn check digit. Ideal for forms, authentication systems, HR platforms, and
any application requiring reliable South African ID verification.

---

## Features

- Validates full RSA ID format (`YYMMDDSSSSCAZ`)
- Birth date extraction with century detection that correctly rejects
  future-dated interpretations
- Gender identification based on the sequence number
- Citizen vs. permanent resident status
- Full Luhn algorithm check digit validation
- Typed, immutable result object — no more guessing at array keys

---

## Requirements

- PHP **8.1** or later

---

## Installation

### Using Composer

```bash
composer require phprsa/id-validator
```

### Manual load

If you're not using Composer, require the three source files directly
(in this order, since `RsaIdValidator` depends on the other two):

```php
require 'src/Gender.php';
require 'src/Citizenship.php';
require 'src/IdValidationResult.php';
require 'src/RsaIdValidator.php';
```

---

## Usage

```php
use PhpRsaIdValidator\RsaIdValidator;

$validator = new RsaIdValidator();
$result = $validator->validate('9001015800088');

if ($result->valid) {
    echo $result->dateOfBirth->format('Y-m-d'); // 1990-01-01
    echo $result->gender->value;                // Male
    echo $result->citizenship->value;           // SA Citizen
} else {
    echo "Error: {$result->error}";
}
```

`validate()` returns an `IdValidationResult` object rather than a bare array,
so your editor can autocomplete the available fields and typos fail at
static-analysis time instead of silently returning `null`:

| Property        | Type                 | Notes                                  |
|-----------------|----------------------|-----------------------------------------|
| `valid`         | `bool`               | Always present                          |
| `idNumber`      | `?string`            | Present only when `valid` is `true`     |
| `dateOfBirth`   | `?DateTimeImmutable` | Present only when `valid` is `true`     |
| `gender`        | `?Gender`            | Backed enum: `Gender::Male` / `Gender::Female` |
| `citizenship`   | `?Citizenship`       | Backed enum: `Citizenship::Citizen` / `Citizenship::PermanentResident` |
| `checkDigit`    | `?int`               | Present only when `valid` is `true`     |
| `error`         | `?string`            | Present only when `valid` is `false`    |

### Quick boolean check

If you just need a yes/no answer, skip the object entirely:

```php
if (RsaIdValidator::isValid($submittedId)) {
    // proceed
}
```

### Migrating from v1.x (array-based results)

v1.x returned a plain associative array. If you're not ready to switch call
sites over to the typed properties yet, `toArray()` reproduces the old shape:

```php
$array = $validator->validate($id)->toArray();
// ['valid' => true, 'id_number' => ..., 'date_of_birth' => 'Y-m-d string', 'gender' => 'Male', 'citizenship' => 'SA Citizen', 'check_digit' => 8]
```

---

## A note on century ambiguity

An RSA ID number only stores a two-digit year, so a year like `35` is
genuinely ambiguous between 1935 and 2035 — the ID number alone can't tell
you which. This library resolves that the only sound way it can: a
candidate century is rejected outright if it would place the birth date in
the **future**, and otherwise accepted if it produces a plausible age (0–122
years). Where both centuries remain plausible, 2000s is preferred, since
that's the more common case in practice. If your application needs different
disambiguation rules (e.g. always assuming the older century for a
particular use case), you'll want to layer that logic on top of
`dateOfBirth` yourself.

---

## Demo

`demo/index.php` is a small self-contained web page for manual testing. It's
for demonstration only — remove it or put it behind auth before deploying
anywhere near production, since it accepts raw ID numbers over POST.

```bash
php -S localhost:8000 -t demo
# then visit http://localhost:8000
```

---

## Running the tests

```bash
composer install
composer test
```

All sample IDs in the test suite are real, independently-verified numbers
(checked against the Luhn variant documented in
`RsaIdValidator::validateLuhn()`) — not placeholders.

---

## License

MIT
