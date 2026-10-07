<?php

/**
 * Human-readable messages for App\Enums\ErrorCode.
 *
 * Keys must match ErrorCode values exactly. The machine-readable `code` in API
 * responses is never translated; this file provides the paired human message.
 */
return [
    'UNAUTHENTICATED' => 'You are not authenticated. Please sign in.',
    'FORBIDDEN' => 'You are not allowed to perform this action.',
    'NOT_FOUND' => 'The requested resource was not found.',
    'METHOD_NOT_ALLOWED' => 'The request method is not supported for this endpoint.',
    'VALIDATION_FAILED' => 'The submitted data is invalid.',
    'RESOURCE_CONFLICT' => 'The operation conflicts with existing data.',
    'RESOURCE_IN_USE' => 'This item is still in use and cannot be removed.',
    'RATE_LIMITED' => 'Too many attempts. Please try again later.',
    'INTERNAL_ERROR' => 'An unexpected error occurred. Please try again later.',

    'INVALID_CREDENTIALS' => 'These credentials do not match our records.',
    'ACCOUNT_DISABLED' => 'Your account has been disabled. Please contact support.',
    'ACCOUNT_PENDING' => 'Your account is still under review.',

    'UNSUPPORTED_LOCALE' => 'The requested language is not supported.',

    'INVALID_PHONE_NUMBER' => 'The phone number is not valid.',
    'PHONE_REQUIRED' => 'A phone number is required.',
    'PHONE_COUNTRY_MISMATCH' => 'The phone number does not match the selected country.',
    'UNSUPPORTED_PHONE_COUNTRY' => 'The selected country is not supported.',
    'PHONE_TYPE_NOT_ALLOWED' => 'This type of phone number is not allowed.',
    'PHONE_ALREADY_EXISTS' => 'This phone number is already in use.',
    'PHONE_NOT_VERIFIED' => 'This phone number has not been verified.',

    'INVALID_LOCATION_HIERARCHY' => 'The selected location data is inconsistent.',
    'INVALID_OTP' => 'The verification code is invalid.',
    'OTP_EXPIRED' => 'The verification code has expired.',
    'OTP_CONSUMED' => 'This verification code has already been used.',
    'OTP_ATTEMPTS_EXCEEDED' => 'Too many verification attempts. Request a new code later.',
    'OTP_COOLDOWN' => 'Please wait before requesting another verification code.',
    'PROVIDER_UNAVAILABLE' => 'The service is temporarily unavailable. Please try again later.',
    'INVALID_AMOUNT' => 'Enter a valid amount in minor currency units.',
    'INSUFFICIENT_BALANCE' => 'The wallet balance is insufficient.',
    'WALLET_LOCKED' => 'This wallet is locked.',
    'CURRENCY_MISMATCH' => 'The currency is not supported for this operation.',
    'IDEMPOTENCY_CONFLICT' => 'This idempotency key was used with different request data.',
    'INVALID_PAYMENT_STATUS' => 'The payment cannot be processed in its current state.',
    'INVALID_WEBHOOK' => 'The payment notification could not be verified.',
    'PAYMENT_ALREADY_REFUNDED' => 'The payment has already been refunded.',
    'RECONCILIATION_REQUIRED' => 'This operation requires reconciliation before it can continue.',
    'CATEGORY_DEPTH_EXCEEDED' => 'The category exceeds the supported nesting depth.',
    'CATEGORY_CYCLE' => 'A category cannot be its own ancestor.',
];
