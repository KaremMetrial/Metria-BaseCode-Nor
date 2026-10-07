<?php

namespace App\Enums;

/**
 * The single registry of stable, machine-readable API error codes.
 *
 * These values are part of the public API contract: clients branch on them, so
 * they must never be renamed and must never be translated. Human-readable
 * counterparts live in `lang/{locale}/errors.php`.
 *
 * Centralising them here is what stops error codes from decaying into magic
 * strings scattered across controllers and services.
 */
enum ErrorCode: string
{
    // --- Generic HTTP / framework-level
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case RESOURCE_CONFLICT = 'RESOURCE_CONFLICT';
    case RESOURCE_IN_USE = 'RESOURCE_IN_USE';
    case RATE_LIMITED = 'RATE_LIMITED';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    // --- Authentication / authorization
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case ACCOUNT_DISABLED = 'ACCOUNT_DISABLED';
    case ACCOUNT_PENDING = 'ACCOUNT_PENDING';

    // --- Localization
    case UNSUPPORTED_LOCALE = 'UNSUPPORTED_LOCALE';

    // --- Phone numbers
    case INVALID_PHONE_NUMBER = 'INVALID_PHONE_NUMBER';
    case PHONE_REQUIRED = 'PHONE_REQUIRED';
    case PHONE_COUNTRY_MISMATCH = 'PHONE_COUNTRY_MISMATCH';
    case UNSUPPORTED_PHONE_COUNTRY = 'UNSUPPORTED_PHONE_COUNTRY';
    case PHONE_TYPE_NOT_ALLOWED = 'PHONE_TYPE_NOT_ALLOWED';
    case PHONE_ALREADY_EXISTS = 'PHONE_ALREADY_EXISTS';
    case PHONE_NOT_VERIFIED = 'PHONE_NOT_VERIFIED';

    // --- Location hierarchy
    case INVALID_LOCATION_HIERARCHY = 'INVALID_LOCATION_HIERARCHY';
    case INVALID_OTP = 'INVALID_OTP';
    case OTP_EXPIRED = 'OTP_EXPIRED';
    case OTP_CONSUMED = 'OTP_CONSUMED';
    case OTP_ATTEMPTS_EXCEEDED = 'OTP_ATTEMPTS_EXCEEDED';
    case OTP_COOLDOWN = 'OTP_COOLDOWN';
    case PROVIDER_UNAVAILABLE = 'PROVIDER_UNAVAILABLE';
    case INVALID_AMOUNT = 'INVALID_AMOUNT';
    case INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    case WALLET_LOCKED = 'WALLET_LOCKED';
    case CURRENCY_MISMATCH = 'CURRENCY_MISMATCH';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
    case INVALID_PAYMENT_STATUS = 'INVALID_PAYMENT_STATUS';
    case INVALID_WEBHOOK = 'INVALID_WEBHOOK';
    case PAYMENT_ALREADY_REFUNDED = 'PAYMENT_ALREADY_REFUNDED';
    case RECONCILIATION_REQUIRED = 'RECONCILIATION_REQUIRED';
    case CATEGORY_CYCLE = 'CATEGORY_CYCLE';
}
