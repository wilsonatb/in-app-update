<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object|null checkForUpdate(string $preferredType = 'flexible', ?int $minStalenessDays = null, ?int $minPriority = null, ?string $id = null)
 * @method static object|null startFlexibleUpdate(bool $allowAssetPackDeletion = false, ?string $id = null)
 * @method static object|null startImmediateUpdate(bool $allowAssetPackDeletion = false, ?string $id = null)
 * @method static object|null completeFlexibleUpdate(?string $id = null)
 * @method static object|null getInstallStatus()
 *
 * @see \Wilsonatb\InAppUpdate\InAppUpdate
 */
final class InAppUpdate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wilsonatb\InAppUpdate\InAppUpdate::class;
    }
}
