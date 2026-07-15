<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
