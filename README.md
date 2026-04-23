# InAppUpdate Plugin for NativePHP Mobile

Android-only Google Play Core in-app updates for NativePHP Mobile, with both flexible and immediate flows.

## Installation

```bash
composer require wilsonatb/in-app-update
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register wilsonatb/in-app-update
```

## Requirements

### Android Permissions

None required.

### Android Dependencies

- `com.google.android.play:app-update:2.1.0`
- `com.google.android.play:app-update-ktx:2.1.0`

### iOS

Not supported. This plugin is Play Core (Android) only.

## Usage

### PHP (Livewire / Blade)

```php
use Wilsonatb\InAppUpdate\Facades\InAppUpdate;
use Native\Mobile\Attributes\OnNative;
use Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged;
use Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted;

$id = (string) str()->uuid();

// 1) Check availability
InAppUpdate::checkForUpdate(
    preferredType: 'flexible', // or 'immediate' / 'any'
    minStalenessDays: 2,
    minPriority: 3,
    id: $id,
);

// 2) Start the flow
InAppUpdate::startFlexibleUpdate(id: $id);
// or:
// InAppUpdate::startImmediateUpdate(id: $id);

// 3) For flexible updates, complete once downloaded
InAppUpdate::completeFlexibleUpdate(id: $id);

// 4) Pull latest known status synchronously
$status = InAppUpdate::getInstallStatus();

#[OnNative(InAppUpdateStateChanged::class)]
public function onInAppUpdateStateChanged(
    string $status,
    ?string $updateType = null,
    ?string $id = null,
    ?string $installStatus = null,
    ?string $error = null,
): void {
    // Handle state transitions
}

#[OnNative(InAppUpdateFlowCompleted::class)]
public function onInAppUpdateFlowCompleted(
    string $result,
    string $updateType,
    ?string $id = null,
    ?string $error = null,
): void {
    // Handle completion/failure
}
```

### JavaScript (Vue / React / Inertia)

```javascript
import { InAppUpdate, Events } from '@wilsonatb/in-app-update';
import { on, off } from '@nativephp/native';

const id = crypto.randomUUID();

const stateHandler = (payload) => console.log('state', payload);
const completedHandler = (payload) => console.log('completed', payload);

on(Events.InAppUpdateStateChanged, stateHandler);
on(Events.InAppUpdateFlowCompleted, completedHandler);

await InAppUpdate.checkForUpdate({
    preferredType: 'flexible',
    minStalenessDays: 2,
    minPriority: 3,
    id,
});

await InAppUpdate.startFlexibleUpdate({ id });
await InAppUpdate.completeFlexibleUpdate({ id });

const latestStatus = await InAppUpdate.getInstallStatus();

off(Events.InAppUpdateStateChanged, stateHandler);
off(Events.InAppUpdateFlowCompleted, completedHandler);
```

## API

- `checkForUpdate({ preferredType: 'flexible'|'immediate'|'any', minStalenessDays, minPriority, id })`
- `startFlexibleUpdate({ allowAssetPackDeletion, id })`
- `startImmediateUpdate({ allowAssetPackDeletion, id })`
- `completeFlexibleUpdate({ id })`
- `getInstallStatus()`

## Recommended usage flow

1. Generate one UUID `id` per update attempt.
2. Call `checkForUpdate(...)` with your policy (`preferredType`, optional `minStalenessDays`, optional `minPriority`).
3. Start the selected flow:
   - `startFlexibleUpdate(...)`, or
   - `startImmediateUpdate(...)`.
4. Listen to `InAppUpdateStateChanged` for progress/state transitions and `InAppUpdateFlowCompleted` for terminal states.
5. If flexible flow reaches `downloaded`, call `completeFlexibleUpdate(...)`.
6. On app resume / re-entry, call `getInstallStatus()` to refresh UI and continue pending flow.

> Note: `id` is a correlation identifier for one update attempt. Reuse the same `id` across all method calls/events for that attempt.

## Events

| Event | Payload | Description |
|---|---|---|
| `Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged` | `status`, `updateType`, `installStatus`, `installStatusCode`, `isUpdateAvailable`, `isFlexibleAllowed`, `isImmediateAllowed`, `preferredType`, `preferredTypeAllowed`, `passesStalenessConstraint`, `passesPriorityConstraint`, `updateAvailability`, `availableVersionCode`, `updatePriority`, `clientVersionStalenessDays`, `bytesDownloaded`, `totalBytesToDownload`, `installErrorCode`, `result`, `id`, `error`, `errorCode` | Emitted for availability checks, flow start, resume checks, and install state transitions |
| `Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted` | `result`, `updateType`, `id`, `error`, `errorCode` | Emitted when flow reaches `downloaded`, `installed`, `canceled`, or `failed` |

### Common `InAppUpdateStateChanged.status` values

- `availability_checked`
- `flow_started`
- `install_state_changed`
- `downloaded_pending_completion`
- `developer_triggered_update_in_progress`
- `resuming_immediate_update`

## Testing in-app updates

Use **Internal App Sharing** to test real Play Core update flows.

1. Install on the test device a build that already includes this plugin and supports in-app updates.
2. Upload a newer build (higher `versionCode`) to Internal App Sharing in Play Console.
3. Open the internal sharing URL on the same test device, but **do not install** the update from the Play Store page.
4. Open your installed app from launcher/home and run your update flow (`checkForUpdate`, then flexible or immediate).

### Troubleshooting

- Test account must have downloaded the app from Google Play at least once.
- Installed build and Play build must use the same **applicationId** and signing key.
- Play only updates to a **higher versionCode**.
- `inAppUpdatePriority` is not supported with Internal App Sharing uploads.

## License

MIT
