<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InAppUpdateStateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $status,
        public ?string $updateType = null,
        public ?string $id = null,
        public ?string $installStatus = null,
        public ?int $installStatusCode = null,
        public ?bool $isUpdateAvailable = null,
        public ?bool $isFlexibleAllowed = null,
        public ?bool $isImmediateAllowed = null,
        public ?string $preferredType = null,
        public ?bool $preferredTypeAllowed = null,
        public ?bool $passesStalenessConstraint = null,
        public ?bool $passesPriorityConstraint = null,
        public ?int $updateAvailability = null,
        public ?int $availableVersionCode = null,
        public ?int $clientVersionStalenessDays = null,
        public ?int $updatePriority = null,
        public ?int $installErrorCode = null,
        public ?int $bytesDownloaded = null,
        public ?int $totalBytesToDownload = null,
        public ?string $result = null,
        public ?string $error = null,
        public ?int $errorCode = null,
    ) {}
}
