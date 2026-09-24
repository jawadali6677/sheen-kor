<?php

namespace App\Enums;

enum StripeCheckoutFulfillmentOutcome: string
{
    case Fulfilled = 'fulfilled';
    case AlreadyPaid = 'already_paid';
    case AwaitingWebhook = 'awaiting_webhook';
    case Unpaid = 'unpaid';
    case SessionMismatch = 'session_mismatch';
    case SessionNotLinked = 'session_not_linked';
    case NotPending = 'not_pending';
}
