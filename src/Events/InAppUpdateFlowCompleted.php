<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InAppUpdateFlowCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $result,
        public string $updateType,
        public ?string $id = null,
        public ?string $error = null,
        public ?int $errorCode = null,
    ) {}
}
