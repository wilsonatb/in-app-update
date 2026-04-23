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
        $unsupportedResponse = $this->unsupportedResponse($method);

        if ($unsupportedResponse !== null) {
            return $unsupportedResponse;
        }

        if (function_exists('nativephp_call')) {
            $result = nativephp_call($method, json_encode($payload));

            if ($result) {
                $decoded = json_decode($result);

                return $decoded->data ?? null;
            }
        }

        return null;
    }

    private function unsupportedResponse(string $method): ?object
    {
        $platform = $this->detectNativePlatform();

        if ($platform === 'ios') {
            return (object) [
                'supported' => false,
                'status' => 'unsupported_platform',
                'platform' => $platform,
                'method' => $method,
                'message' => sprintf(
                    'InAppUpdate is Android-only. Method [%s] was skipped on iOS.',
                    $method
                ),
            ];
        }

        return null;
    }

    private function detectNativePlatform(): ?string
    {
        $rawPlatform = $_SERVER['NATIVEPHP_PLATFORM']
            ?? $_ENV['NATIVEPHP_PLATFORM']
            ?? getenv('NATIVEPHP_PLATFORM')
            ?? null;

        if (! is_string($rawPlatform) || $rawPlatform === '') {
            return null;
        }

        return mb_strtolower($rawPlatform);
    }
}
