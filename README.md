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

### Runtime behavior on iOS

If any `InAppUpdate` method is called on iOS, the plugin returns a controlled response and skips native execution:

- `supported: false`
- `status: "unsupported_platform"`
- `platform: "ios"`
- `message: "InAppUpdate is Android-only... was skipped on iOS."`

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

public ?string $updateFlowId = null;

public function checkUpdate(): void
{
    $this->updateFlowId = (string) str()->uuid();

    $result = InAppUpdate::checkForUpdate(
        preferredType: 'any', // flexible | immediate | any
        id: $this->updateFlowId,
    );

    // iOS-safe response: plugin skips execution and returns supported=false.
    if (($result->supported ?? true) === false) {
        return;
    }
}

#[OnNative(InAppUpdateStateChanged::class)]
public function onInAppUpdateStateChanged(
    string $status,
    ?string $updateType = null,
    ?string $id = null,
    ?string $installStatus = null,
    ?bool $isUpdateAvailable = null,
): void {
    if ($id !== $this->updateFlowId) {
        return;
    }

    if ($status === 'availability_checked' && $isUpdateAvailable) {
        InAppUpdate::startFlexibleUpdate(id: $id);
    }

    if (in_array($installStatus, ['downloaded'], true)) {
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

const check = await InAppUpdate.checkForUpdate({ preferredType: 'any', id });

// iOS-safe response: no error, just skip.
if (!check?.supported) {
    console.log('InAppUpdate not supported on this platform', check);
} else if (check?.isUpdateAvailable) {
    await InAppUpdate.startFlexibleUpdate({ id });
}

const latestStatus = await InAppUpdate.getInstallStatus();
if (latestStatus?.installStatus === 'downloaded') {
    await InAppUpdate.completeFlexibleUpdate({ id });
}

off(Events.InAppUpdateStateChanged, onState);
off(Events.InAppUpdateFlowCompleted, onFlowCompleted);
```

## Bridge Methods (PHP + JavaScript)

All methods use the same bridge functions internally:

| Method | Bridge function | What it does | Immediate return | Async events |
|---|---|---|---|---|
| `checkForUpdate(...)` | `InAppUpdate.CheckForUpdate` | Checks availability and allowed update types (`flexible`, `immediate`, `any`) with optional staleness/priority filters | `{ status: "checking", preferredType, id }` | `InAppUpdateStateChanged` (`status: "availability_checked"`), or `InAppUpdateFlowCompleted` with `result: "failed"` |
| `startFlexibleUpdate(...)` | `InAppUpdate.StartFlexibleUpdate` | Starts Play Core flexible flow | `{ status: "starting", updateType: "flexible", allowAssetPackDeletion, id }` | `InAppUpdateStateChanged` (`flow_started`, `install_state_changed`, `downloaded_pending_completion`), `InAppUpdateFlowCompleted` (`downloaded`, `installed`, `canceled`, `failed`) |
| `startImmediateUpdate(...)` | `InAppUpdate.StartImmediateUpdate` | Starts Play Core immediate flow | `{ status: "starting", updateType: "immediate", allowAssetPackDeletion, id }` | `InAppUpdateStateChanged` (`flow_started`, `install_state_changed`, `developer_triggered_update_in_progress`, `resuming_immediate_update`), `InAppUpdateFlowCompleted` (`installed`, `canceled`, `failed`) |
| `completeFlexibleUpdate(...)` | `InAppUpdate.CompleteFlexibleUpdate` | Installs already downloaded flexible update | `{ status: "completing", updateType: "flexible", id }` | `InAppUpdateStateChanged` (`completing_flexible_update`), or `InAppUpdateFlowCompleted` with `result: "failed"` |
| `getInstallStatus()` | `InAppUpdate.GetInstallStatus` | Returns last known native status snapshot | Last cached status object (initially `{ status: "idle" }`) | None |

> Reuse the same `id` across all calls/events in the same update attempt.

## Events

| Event | Purpose | Main payload fields |
|---|---|---|
| `Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged` | Non-terminal lifecycle/progress updates | `status`, `updateType`, `id`, `installStatus`, `installStatusCode`, `isUpdateAvailable`, `isFlexibleAllowed`, `isImmediateAllowed`, `preferredType`, `preferredTypeAllowed`, `passesStalenessConstraint`, `passesPriorityConstraint`, `updateAvailability`, `availableVersionCode`, `clientVersionStalenessDays`, `updatePriority`, `bytesDownloaded`, `totalBytesToDownload`, `error`, `errorCode` |
| `Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted` | Terminal flow outcome | `result`, `updateType`, `id`, `error`, `errorCode` |

### `InAppUpdateStateChanged.status` values

- `availability_checked`
- `flow_started`
- `install_state_changed`
- `downloaded_pending_completion`
- `developer_triggered_update_in_progress`
- `resuming_immediate_update`
- `completing_flexible_update`
- `resume_check_failed`

### `InAppUpdateFlowCompleted.result` values

- `installed`
- `downloaded`
- `canceled`
- `failed`

## Recommended flow (production + self-test)

1. Generate one UUID `id` per update attempt.
2. Register listeners for `InAppUpdateStateChanged` and `InAppUpdateFlowCompleted`.
3. Call `checkForUpdate(...)`.
4. If available and allowed, start `startImmediateUpdate(...)` (required) or `startFlexibleUpdate(...)` (optional).
5. For flexible, when `installStatus === 'downloaded'`, call `completeFlexibleUpdate(...)`.
6. On resume/re-entry, call `getInstallStatus()` and continue the same `id`.

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

## Support

For issues, questions, or feature requests:
- **Email:** diwdesign.wilson@gmail.com
- **GitHub Issues:** [Issues](https://github.com/wilsonatb/in-app-update/issues)

## License

MIT
