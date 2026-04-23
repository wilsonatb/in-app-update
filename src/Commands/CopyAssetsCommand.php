<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Copy assets hook command for InAppUpdate plugin.
 *
 * This hook runs during the copy_assets phase of the build process.
 * Use it to copy ML models, binary files, or other assets that need
 * to be in specific locations in the native project.
 *
 * @see NativePluginHookCommand
 */
final class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:in-app-update:copy-assets';

    protected $description = 'Copy assets for InAppUpdate plugin';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->copyAndroidAssets();
        }

        return self::SUCCESS;
    }

    /**
     * Copy assets for Android build
     */
    protected function copyAndroidAssets(): void
    {
        $this->info('Android assets copied for InAppUpdate');
    }
}
