<?php
namespace App\Enums;

enum FulfillmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingVerification = 'pending_verification';
    case AwaitingDeposit = 'awaiting_deposit';
    case PendingApproval = 'pending_approval';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
}
