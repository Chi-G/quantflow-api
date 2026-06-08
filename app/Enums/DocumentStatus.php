<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
