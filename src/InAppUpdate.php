<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate;

final class InAppUpdate
{
    /**
     * Check whether a Play Core update is available and which flow types are allowed.
     */
    public function checkForUpdate(
        string $preferredType = 'flexible',
        ?int $minStalenessDays = null,
        ?int $minPriority = null,
        ?string $id = null,
    ): ?object {
        $payload = array_filter([
            'preferredType' => $preferredType,
            'minStalenessDays' => $minStalenessDays,
            'minPriority' => $minPriority,
            'id' => $id,
        ], static fn ($value) => $value !== null);

        return $this->call('InAppUpdate.CheckForUpdate', $payload);
    }

    /**
     * Start a flexible in-app update flow.
     */
    public function startFlexibleUpdate(
        bool $allowAssetPackDeletion = false,
        ?string $id = null,
    ): ?object {
        return $this->call('InAppUpdate.StartFlexibleUpdate', array_filter([
            'allowAssetPackDeletion' => $allowAssetPackDeletion,
            'id' => $id,
        ], static fn ($value) => $value !== null));
    }

    /**
     * Start an immediate in-app update flow.
     */
    public function startImmediateUpdate(
        bool $allowAssetPackDeletion = false,
        ?string $id = null,
    ): ?object {
        return $this->call('InAppUpdate.StartImmediateUpdate', array_filter([
            'allowAssetPackDeletion' => $allowAssetPackDeletion,
            'id' => $id,
        ], static fn ($value) => $value !== null));
    }

    /**
     * Complete a downloaded flexible update.
     */
    public function completeFlexibleUpdate(?string $id = null): ?object
    {
        return $this->call('InAppUpdate.CompleteFlexibleUpdate', array_filter([
            'id' => $id,
        ], static fn ($value) => $value !== null));
    }

    /**
     * Return the latest known install/update status from native side.
     */
    public function getInstallStatus(): ?object
    {
        return $this->call('InAppUpdate.GetInstallStatus', []);
    }

    private function call(string $method, array $payload): ?object
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call($method, json_encode($payload));

            if ($result) {
                $decoded = json_decode($result);

                return $decoded->data ?? null;
            }
        }

        return null;
    }
}
