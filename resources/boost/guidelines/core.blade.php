## wilsonatb/in-app-update

Android-only Google Play Core in-app updates (flexible + immediate flows).

### Installation

```bash
composer require wilsonatb/in-app-update
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register wilsonatb/in-app-update
```

### PHP Usage (Livewire/Blade)

Use the `InAppUpdate` facade:

@verbatim
<code-snippet name="Using InAppUpdate Facade" lang="php">
use Wilsonatb\InAppUpdate\Facades\InAppUpdate;
use Native\Mobile\Attributes\OnNative;
use Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged;
use Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted;

$id = (string) str()->uuid();

InAppUpdate::checkForUpdate(preferredType: 'flexible', id: $id); // flexible | immediate | any
InAppUpdate::startFlexibleUpdate(id: $id);
InAppUpdate::completeFlexibleUpdate(id: $id);

$status = InAppUpdate::getInstallStatus();

#[OnNative(InAppUpdateStateChanged::class)]
public function onInAppUpdateStateChanged(string $status, ?string $updateType = null, ?string $id = null): void
{
    // Handle state changes
}

#[OnNative(InAppUpdateFlowCompleted::class)]
public function onInAppUpdateFlowCompleted(string $result, string $updateType, ?string $id = null): void
{
    // Handle flow completion
}
</code-snippet>
@endverbatim

### Available Methods

- `InAppUpdate::checkForUpdate(...)`
- `InAppUpdate::startFlexibleUpdate(...)`
- `InAppUpdate::startImmediateUpdate(...)`
- `InAppUpdate::completeFlexibleUpdate(...)`
- `InAppUpdate::getInstallStatus()`

### Recommended flow

1. Generate one UUID `id` per update attempt.
2. Call `InAppUpdate::checkForUpdate(...)`.
3. Start either flexible or immediate update.
4. Listen for `InAppUpdateStateChanged` and `InAppUpdateFlowCompleted`.
5. If flexible reaches `downloaded`, call `InAppUpdate::completeFlexibleUpdate(...)`.
6. On app resume, call `InAppUpdate::getInstallStatus()` to refresh UI state.

`id` is the correlation identifier for one attempt and should be reused across calls/events for that attempt.

### Events

- `InAppUpdateStateChanged`
- `InAppUpdateFlowCompleted`
- `InAppUpdateStateChanged` payload can include: `status`, `updateType`, `installStatus`, `installStatusCode`, `isUpdateAvailable`, `isFlexibleAllowed`, `isImmediateAllowed`, `preferredType`, `preferredTypeAllowed`, `passesStalenessConstraint`, `passesPriorityConstraint`, `updateAvailability`, `availableVersionCode`, `updatePriority`, `clientVersionStalenessDays`, `bytesDownloaded`, `totalBytesToDownload`, `installErrorCode`, `result`, `id`, `error`, `errorCode`
- `InAppUpdateFlowCompleted` payload includes: `result`, `updateType`, `id`, `error`, `errorCode`
- Common `InAppUpdateStateChanged.status` values: `availability_checked`, `flow_started`, `install_state_changed`, `downloaded_pending_completion`, `developer_triggered_update_in_progress`, `resuming_immediate_update`

@verbatim
<code-snippet name="Listening for InAppUpdate Events" lang="php">
use Native\Mobile\Attributes\OnNative;
use Wilsonatb\InAppUpdate\Events\InAppUpdateStateChanged;
use Wilsonatb\InAppUpdate\Events\InAppUpdateFlowCompleted;

#[OnNative(InAppUpdateStateChanged::class)]
public function onInAppUpdateStateChanged($status, $updateType = null, $id = null)
{
    // Handle state changes
}

#[OnNative(InAppUpdateFlowCompleted::class)]
public function onInAppUpdateFlowCompleted($result, $updateType, $id = null, $error = null)
{
    // Handle completion/failure
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using InAppUpdate in JavaScript" lang="javascript">
import { InAppUpdate, Events } from '@wilsonatb/in-app-update';
import { on, off } from '@nativephp/native';

const id = crypto.randomUUID();

const stateHandler = (payload) => console.log('state', payload);
const completedHandler = (payload) => console.log('completed', payload);

on(Events.InAppUpdateStateChanged, stateHandler);
on(Events.InAppUpdateFlowCompleted, completedHandler);

await InAppUpdate.checkForUpdate({ preferredType: 'flexible', id });
await InAppUpdate.startFlexibleUpdate({ id });
await InAppUpdate.completeFlexibleUpdate({ id });

const latest = await InAppUpdate.getInstallStatus();

off(Events.InAppUpdateStateChanged, stateHandler);
off(Events.InAppUpdateFlowCompleted, completedHandler);
</code-snippet>
@endverbatim

### Testing (Google Play Internal App Sharing)

1. Install on the test device a build that already supports in-app updates.
2. Upload a newer build (higher `versionCode`) to Internal App Sharing.
3. Open the sharing link on device, but do **not** install from Play Store page.
4. Open the installed app and run `checkForUpdate`, then start flexible or immediate flow.

Troubleshooting:
- Use a Google account that already downloaded the app from Play.
- Keep same `applicationId` and signing key between installed and uploaded builds.
- Ensure uploaded build has higher `versionCode`.
- `inAppUpdatePriority` is not available in Internal App Sharing.
