# InAppUpdate Plugin for NativePHP Mobile

Android-only NativePHP plugin for Google Play In-App Updates, with both **Flexible** and **Immediate** flows.

[![PHP Version](https://img.shields.io/packagist/php-v/wilsonatb/in-app-update.svg?color=blue)](https://packagist.org/packages/wilsonatb/in-app-update)
[![Downloads](https://img.shields.io/packagist/dt/wilsonatb/in-app-update.svg?color=red)](https://packagist.org/packages/wilsonatb/in-app-update)
![License](https://img.shields.io/github/license/wilsonatb/in-app-update.svg?color=green)

## Screenshot

<p align="center">
    <img height="500" alt="InAppUpdate Plugin Screenshot" src="https://github.com/user-attachments/assets/5923e8a9-8a16-464b-80ad-8dd1d9b90fb6" />
</p>

## Platform Support

| Platform | Support |
|---|---|
| Android | ✅ Supported (Google Play Core) |
| iOS | ❌ Not supported |

## Installation

```bash
composer require wilsonatb/in-app-update
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register wilsonatb/in-app-update
```

## Quick Start (PHP / Livewire)

```php
use Native\Mobile\Attributes\OnNative;
use Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted;
use Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged;
use Wilsonatb\InAppUpdate\Facades\InAppUpdate;

$id = (string) str()->uuid();

InAppUpdate::checkForUpdate(
    preferredType: 'any', // flexible | immediate | any
    id: $id,
);

#[OnNative(InAppUpdateStateChanged::class)]
public function onInAppUpdateStateChanged(
    string $status,
    ?string $updateType = null,
    ?string $id = null,
    ?string $installStatus = null,
): void {
    if ($id !== $this->updateFlowId) {
        return;
    }

    if ($status === 'availability_checked') {
        InAppUpdate::startFlexibleUpdate(id: $id);
    }

    if ($installStatus === 'downloaded') {
        InAppUpdate::completeFlexibleUpdate(id: $id);
    }
}

#[OnNative(InAppUpdateFlowCompleted::class)]
public function onInAppUpdateFlowCompleted(
    string $result,
    string $updateType,
    ?string $id = null,
): void {
    // installed | downloaded | canceled | failed
}
```

## JavaScript Usage (Vue / React / Inertia)

```javascript
import { InAppUpdate, Events } from '@wilsonatb/in-app-update';
import { on, off } from '@nativephp/native';

const id = crypto.randomUUID();

const onState = (payload) => console.log('state', payload);
const onFlowCompleted = (payload) => console.log('completed', payload);

on(Events.InAppUpdateStateChanged, onState);
on(Events.InAppUpdateFlowCompleted, onFlowCompleted);

await InAppUpdate.checkForUpdate({ preferredType: 'any', id });
await InAppUpdate.startFlexibleUpdate({ id });
await InAppUpdate.completeFlexibleUpdate({ id });

const latestStatus = await InAppUpdate.getInstallStatus();

off(Events.InAppUpdateStateChanged, onState);
off(Events.InAppUpdateFlowCompleted, onFlowCompleted);
```

## Recommended Flow

### Automatic flow (recommended for production)

1. Generate one UUID `id` per update attempt.
2. Call `checkForUpdate(...)` at app start and periodically while active.
3. If update is available:
   - start `startImmediateUpdate(...)` for required updates, or
   - start `startFlexibleUpdate(...)` for optional updates.
4. Listen to `InAppUpdateStateChanged` + `InAppUpdateFlowCompleted`.
5. If flexible reaches `downloaded` / `downloaded_pending_completion`, call `completeFlexibleUpdate(...)`.
6. On resume/re-entry, call `getInstallStatus()` to continue pending flows.

### Manual flow (testing with buttons/UI)

1. `checkForUpdate(...)`
2. `startFlexibleUpdate(...)` or `startImmediateUpdate(...)`
3. `completeFlexibleUpdate(...)` only after flexible download completes

> Reuse the same `id` across all calls/events in the same update attempt.

## API

### PHP Facade Methods

- `InAppUpdate::checkForUpdate(string $preferredType = 'flexible', ?int $minStalenessDays = null, ?int $minPriority = null, ?string $id = null)`
- `InAppUpdate::startFlexibleUpdate(bool $allowAssetPackDeletion = false, ?string $id = null)`
- `InAppUpdate::startImmediateUpdate(bool $allowAssetPackDeletion = false, ?string $id = null)`
- `InAppUpdate::completeFlexibleUpdate(?string $id = null)`
- `InAppUpdate::getInstallStatus()`

### JavaScript Methods

- `InAppUpdate.checkForUpdate(options)`
- `InAppUpdate.startFlexibleUpdate(options)`
- `InAppUpdate.startImmediateUpdate(options)`
- `InAppUpdate.completeFlexibleUpdate(options)`
- `InAppUpdate.getInstallStatus()`

## Events

| Event | Purpose |
|---|---|
| `Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged` | Availability, progress, and status transitions |
| `Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted` | Terminal results (`installed`, `downloaded`, `canceled`, `failed`) |

### Common `InAppUpdateStateChanged.status` values

- `availability_checked`
- `flow_started`
- `install_state_changed`
- `downloaded_pending_completion`
- `developer_triggered_update_in_progress`
- `resuming_immediate_update`

## Requirements

### Permissions

No additional Android permissions are required.

### Android Dependencies (included by plugin)

- `com.google.android.play:app-update:2.1.0`
- `com.google.android.play:app-update-ktx:2.1.0`

## Testing with Internal App Sharing

1. Install a build that already includes this plugin.
2. Upload a newer build (higher `versionCode`) to Internal App Sharing.
3. Open the sharing URL on the same device, but do **not** install from the Play page.
4. Open your installed app and run the update flow.

### Troubleshooting

- Test account must have installed the app from Google Play at least once.
- Installed build and uploaded build must share the same `applicationId` and signing key.
- Play will only offer updates for higher `versionCode`.
- `inAppUpdatePriority` is not available in Internal App Sharing.

## License

MIT
