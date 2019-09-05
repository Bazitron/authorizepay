<?php

namespace Spdevs\AuthorizePay;

trait AuthorizeStatuses
{
    public static function isPossibleFullRefund($status)
    {
        return in_array($status, [
            self::TRANSACTION_STATUS_CAPTURED_PENDING_SETTLEMENT,
            self::TRANSACTION_STATUS_SETTLED_SUCCESSFULLY,
        ], true);
    }

    public static function isPossiblePartialRefund($status)
    {
        return in_array($status, [
            self::TRANSACTION_STATUS_SETTLED_SUCCESSFULLY,
        ], true);
    }

    public static function isPossibleCustomRefund($status)
    {
        return in_array($status, [
            self::TRANSACTION_STATUS_SETTLED_SUCCESSFULLY,
        ], true);
    }

    public static function isSettled($status)
    {
        return in_array($status, [
            self::TRANSACTION_STATUS_SETTLED_SUCCESSFULLY,
        ], true);
    }
}
