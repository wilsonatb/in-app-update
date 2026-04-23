<?php

declare(strict_types=1);

/**
 * Plugin validation tests for InAppUpdate.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';
});

describe('Plugin Manifest', function () {
    it('has a valid nativephp.json file', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $content = file_get_contents($this->manifestPath);
        $manifest = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    it('has required fields', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest)->toHaveKeys(['namespace', 'bridge_functions', 'android', 'events']);
        expect($manifest['namespace'])->toBe('InAppUpdate');
    });

    it('targets Android only', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['platforms'] ?? [])->toContain('android');
        expect($manifest['platforms'] ?? [])->not->toContain('ios');
    });

    it('declares all in-app update bridge functions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $names = array_map(
            static fn (array $function) => $function['name'] ?? null,
            $manifest['bridge_functions']
        );

        expect($names)->toContain('InAppUpdate.CheckForUpdate');
        expect($names)->toContain('InAppUpdate.StartFlexibleUpdate');
        expect($names)->toContain('InAppUpdate.StartImmediateUpdate');
        expect($names)->toContain('InAppUpdate.CompleteFlexibleUpdate');
        expect($names)->toContain('InAppUpdate.GetInstallStatus');
    });
});

describe('Native Code', function () {
    it('has Android Kotlin file', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/src/InAppUpdateFunctions.kt';

        expect(file_exists($kotlinFile))->toBeTrue();

        $content = file_get_contents($kotlinFile);
        expect($content)->toContain('package com.wilsonatb.plugins.in_app_update');
        expect($content)->toContain('object InAppUpdateFunctions');
        expect($content)->toContain('BridgeFunction');
        expect($content)->toContain('AppUpdateManagerFactory');
        expect($content)->toContain('NativeActionCoordinator.dispatchEvent');
        expect($content)->toContain('BridgeResponse.success');
    });

    it('has matching bridge function classes in native code', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $kotlinFile = $this->pluginPath.'/resources/android/src/InAppUpdateFunctions.kt';

        $kotlinContent = file_get_contents($kotlinFile);

        foreach ($manifest['bridge_functions'] as $function) {
            // Extract class name from the function reference
            if (isset($function['android'])) {
                $parts = explode('.', $function['android']);
                $className = end($parts);
                expect($kotlinContent)->toContain("class {$className}");
            }

        }
    });
});

describe('PHP Classes', function () {
    it('has service provider', function () {
        $file = $this->pluginPath.'/src/InAppUpdateServiceProvider.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Wilsonatb\InAppUpdate');
        expect($content)->toContain('class InAppUpdateServiceProvider');
    });

    it('has facade', function () {
        $file = $this->pluginPath.'/src/Facades/InAppUpdate.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Wilsonatb\InAppUpdate\Facades');
        expect($content)->toContain('class InAppUpdate extends Facade');
    });

    it('has main implementation class', function () {
        $file = $this->pluginPath.'/src/InAppUpdate.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Wilsonatb\InAppUpdate');
        expect($content)->toContain('class InAppUpdate');
    });

    it('enforces android-only guard in PHP bridge calls', function () {
        $file = $this->pluginPath.'/src/InAppUpdate.php';
        $content = file_get_contents($file);

        expect($content)->toContain('NATIVEPHP_PLATFORM');
        expect($content)->toContain('InAppUpdate is Android-only.');
        expect($content)->toContain('unsupported_platform');
        expect($content)->toContain('supported');
    });
});

describe('JavaScript Bridge', function () {
    it('enforces android-only guard in JavaScript bridge calls', function () {
        $file = $this->pluginPath.'/resources/js/inAppUpdate.js';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('detectPlatform');
        expect($content)->toContain('unsupportedPlatformResponse');
        expect($content)->toContain('InAppUpdate is Android-only.');
        expect($content)->toContain('unsupported_platform');
    });
});

describe('Composer Configuration', function () {
    it('has valid composer.json', function () {
        $composerPath = $this->pluginPath.'/composer.json';
        expect(file_exists($composerPath))->toBeTrue();

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['extra']['nativephp']['manifest'])->toBe('nativephp.json');
    });
});

describe('Events', function () {
    it('declares state and flow events', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        expect($manifest['events'])->toContain('Wilsonatb\\InAppUpdate\\Events\\InAppUpdateStateChanged');
        expect($manifest['events'])->toContain('Wilsonatb\\InAppUpdate\\Events\\InAppUpdateFlowCompleted');
    });

    it('has PHP event classes', function () {
        expect(file_exists($this->pluginPath.'/src/Events/InAppUpdateStateChanged.php'))->toBeTrue();
        expect(file_exists($this->pluginPath.'/src/Events/InAppUpdateFlowCompleted.php'))->toBeTrue();
    });
});

describe('Lifecycle Hooks', function () {
    it('has valid hooks configuration', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        if (isset($manifest['hooks'])) {
            expect($manifest['hooks'])->toBeArray();

            $validHooks = ['pre_compile', 'post_compile', 'copy_assets', 'post_build'];
            foreach (array_keys($manifest['hooks']) as $hook) {
                expect($hook)->toBeIn($validHooks);
            }
        }
    });

    it('has copy_assets hook command', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['hooks']['copy_assets'] ?? null)->not->toBeNull();

        $commandFile = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        expect(file_exists($commandFile))->toBeTrue();
    });

    it('copy_assets command extends NativePluginHookCommand', function () {
        $commandFile = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        $content = file_get_contents($commandFile);

        expect($content)->toContain('extends NativePluginHookCommand');
        expect($content)->toContain('use Native\Mobile\Plugins\Commands\NativePluginHookCommand');
    });

    it('copy_assets command has correct signature', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $expectedSignature = $manifest['hooks']['copy_assets'];

        $commandFile = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        $content = file_get_contents($commandFile);

        expect($content)->toContain('$signature = \''.$expectedSignature.'\'');
    });

    it('copy_assets command has platform-specific methods', function () {
        $commandFile = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        $content = file_get_contents($commandFile);

        // Android-only plugin should only handle Android.
        expect($content)->toContain('$this->isAndroid()');
        expect($content)->not->toContain('$this->isIos()');
    });

    it('has valid assets configuration', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        // Assets are at top level with android/ios nested inside
        if (isset($manifest['assets'])) {
            expect($manifest['assets'])->toBeArray();

            if (isset($manifest['assets']['android'])) {
                expect($manifest['assets']['android'])->toBeArray();
            }

            if (isset($manifest['assets']['ios'])) {
                expect($manifest['assets']['ios'])->toBeArray();
            }
        }
    });
});
