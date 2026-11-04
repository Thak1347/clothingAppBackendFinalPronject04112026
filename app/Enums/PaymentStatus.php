<?php
namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case RefundPending = 'refund_pending';
    case PartiallyRefunded = 'partially_refunded';
    case Cancelled = 'cancelled';
}
