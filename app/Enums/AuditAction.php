<?php

namespace App\Enums;

/**
 * Registry of auditable actions.
 *
 * Every audit row records one of these values, so the set of things worth
 * auditing is reviewable in one file instead of being inferred from grep. The
 * values are stored in `audit_logs.action`, so they are effectively permanent.
 */
enum AuditAction: string
{
    // Authentication
    case USER_LOGIN = 'user.login';
    case USER_LOGOUT = 'user.logout';
    case USER_LOGIN_FAILED = 'user.login_failed';

    // Identity
    case USER_PHONE_CHANGED = 'user.phone_changed';
    case USER_STATUS_CHANGED = 'user.status_changed';

    // Access control
    case ROLE_ASSIGNED = 'role.assigned';
    case ROLE_REVOKED = 'role.revoked';
    case PERMISSION_CHANGED = 'permission.changed';

    // Vendors
    case VENDOR_APPROVED = 'vendor.approved';
    case VENDOR_REJECTED = 'vendor.rejected';

    // Money
    case WALLET_ADJUSTED = 'wallet.adjusted';
    case PAYMENT_REFUND_REQUESTED = 'payment.refund_requested';
    case PAYMENT_REFUNDED = 'payment.refunded';

    case MYFATOORAH_OPERATION_RESOLVED = 'myfatoorah.operation_resolved';

    case MYFATOORAH_OPERATION = 'myfatoorah.operation';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
