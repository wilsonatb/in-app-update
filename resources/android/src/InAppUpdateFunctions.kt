package com.wilsonatb.plugins.in_app_update

import androidx.fragment.app.FragmentActivity
import android.os.Handler
import android.os.Looper
import com.google.android.play.core.appupdate.AppUpdateManager
import com.google.android.play.core.appupdate.AppUpdateManagerFactory
import com.google.android.play.core.appupdate.AppUpdateOptions
import com.google.android.play.core.install.InstallStateUpdatedListener
import com.google.android.play.core.install.model.AppUpdateType
import com.google.android.play.core.install.model.InstallStatus
import com.google.android.play.core.install.model.UpdateAvailability
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.lifecycle.NativePHPLifecycle
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

object InAppUpdateFunctions {
    private const val UPDATE_REQUEST_CODE = 9154
    private const val EVENT_STATE_CHANGED = "Wilsonatb\\InAppUpdate\\Events\\InAppUpdateStateChanged"
    private const val EVENT_FLOW_COMPLETED = "Wilsonatb\\InAppUpdate\\Events\\InAppUpdateFlowCompleted"

    private var appUpdateManager: AppUpdateManager? = null
    private var listenerRegistered = false
    private var lifecycleRegistered = false
    private var lastKnownStatus: MutableMap<String, Any> = mutableMapOf("status" to "idle")
    private var currentFlowId: String? = null
    private var currentFlowType: Int? = null
    private var currentActivity: FragmentActivity? = null

    private val installStateListener = InstallStateUpdatedListener { state ->
        val payload = mutableMapOf<String, Any>(
            "status" to "install_state_changed",
            "installStatus" to installStatusLabel(state.installStatus()),
            "installStatusCode" to state.installStatus(),
            "bytesDownloaded" to state.bytesDownloaded(),
            "totalBytesToDownload" to state.totalBytesToDownload(),
            "updateType" to updateTypeLabel(currentFlowType),
        )

        currentFlowId?.let { payload["id"] = it }
        if (state.installErrorCode() != 0) {
            payload["installErrorCode"] = state.installErrorCode()
        }

        updateLastKnownStatus(payload)
        dispatchStateChanged(payload)

        when (state.installStatus()) {
            InstallStatus.DOWNLOADED -> dispatchFlowCompleted(
                result = "downloaded",
                updateType = updateTypeLabel(currentFlowType),
                id = currentFlowId
            )
            InstallStatus.INSTALLED -> dispatchFlowCompleted(
                result = "installed",
                updateType = updateTypeLabel(currentFlowType),
                id = currentFlowId
            )
            InstallStatus.CANCELED -> dispatchFlowCompleted(
                result = "canceled",
                updateType = updateTypeLabel(currentFlowType),
                id = currentFlowId
            )
            InstallStatus.FAILED -> dispatchFlowCompleted(
                result = "failed",
                updateType = updateTypeLabel(currentFlowType),
                id = currentFlowId,
                errorCode = state.installErrorCode(),
                error = "In-app update failed"
            )
        }
    }

    private fun manager(activity: FragmentActivity): AppUpdateManager {
        currentActivity = activity
        if (appUpdateManager == null) {
            appUpdateManager = AppUpdateManagerFactory.create(activity)
        }

        if (!listenerRegistered) {
            appUpdateManager?.registerListener(installStateListener)
            listenerRegistered = true
        }

        if (!lifecycleRegistered) {
            lifecycleRegistered = true
            NativePHPLifecycle.on(NativePHPLifecycle.Events.ON_RESUME) {
                currentActivity?.let(::checkForResumeStatus)
            }
        }

        return appUpdateManager!!
    }

    private fun checkForResumeStatus(activity: FragmentActivity) {
        manager(activity).appUpdateInfo
            .addOnSuccessListener { info ->
                if (info.installStatus() == InstallStatus.DOWNLOADED) {
                    val payload = mutableMapOf<String, Any>(
                        "status" to "downloaded_pending_completion",
                        "updateType" to "flexible",
                        "installStatus" to installStatusLabel(info.installStatus()),
                        "installStatusCode" to info.installStatus()
                    )
                    currentFlowId?.let { payload["id"] = it }

                    updateLastKnownStatus(payload)
                    dispatchStateChanged(payload)
                    dispatchFlowCompleted(
                        result = "downloaded",
                        updateType = "flexible",
                        id = currentFlowId
                    )
                    return@addOnSuccessListener
                }

                if (info.updateAvailability() == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS) {
                    currentFlowType = AppUpdateType.IMMEDIATE
                    val payload = mutableMapOf<String, Any>(
                        "status" to "developer_triggered_update_in_progress",
                        "updateAvailability" to info.updateAvailability(),
                        "isUpdateAvailable" to true,
                        "updateType" to "immediate",
                        "installStatus" to installStatusLabel(info.installStatus()),
                        "installStatusCode" to info.installStatus()
                    )
                    currentFlowId?.let { payload["id"] = it }

                    updateLastKnownStatus(payload)
                    dispatchStateChanged(payload)

                    resumeImmediateUpdate(activity, info)
                }
            }
            .addOnFailureListener { error ->
                dispatchStateChanged(
                    mapOf(
                        "status" to "resume_check_failed",
                        "error" to (error.message ?: "Unable to inspect in-app update state")
                    )
                )
            }
    }

    private fun resumeImmediateUpdate(activity: FragmentActivity, info: com.google.android.play.core.appupdate.AppUpdateInfo) {
        val payload = mutableMapOf<String, Any>(
            "status" to "resuming_immediate_update",
            "updateType" to "immediate"
        )
        currentFlowId?.let { payload["id"] = it }
        updateLastKnownStatus(payload)
        dispatchStateChanged(payload)

        val options = AppUpdateOptions.newBuilder(AppUpdateType.IMMEDIATE).build()

        try {
            val started = manager(activity).startUpdateFlowForResult(
                info,
                activity,
                options,
                UPDATE_REQUEST_CODE
            )

            if (!started) {
                dispatchFlowCompleted(
                    result = "failed",
                    updateType = "immediate",
                    id = currentFlowId,
                    error = "Unable to resume immediate update flow"
                )
            }
        } catch (error: Exception) {
            dispatchFlowCompleted(
                result = "failed",
                updateType = "immediate",
                id = currentFlowId,
                error = error.message ?: "Failed to resume immediate update flow"
            )
        }
    }

    private fun checkAvailability(
        activity: FragmentActivity,
        preferredType: String,
        minStalenessDays: Int?,
        minPriority: Int?
    ) {
        manager(activity).appUpdateInfo
            .addOnSuccessListener { info ->
                val updateAvailability = info.updateAvailability()
                val stalenessDays = info.clientVersionStalenessDays()
                val updatePriority = info.updatePriority()
                val flexibleAllowed = info.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE)
                val immediateAllowed = info.isUpdateTypeAllowed(AppUpdateType.IMMEDIATE)
                val isAvailable = updateAvailability == UpdateAvailability.UPDATE_AVAILABLE ||
                    updateAvailability == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS

                val staleEnough = minStalenessDays == null || ((stalenessDays ?: -1) >= minStalenessDays)
                val priorityEnough = minPriority == null || (updatePriority >= minPriority)
                val preferredAllowed = when (preferredType.lowercase()) {
                    "immediate" -> immediateAllowed
                    "flexible" -> flexibleAllowed
                    else -> flexibleAllowed || immediateAllowed
                }

                val payload = mutableMapOf<String, Any>(
                    "status" to "availability_checked",
                    "isUpdateAvailable" to isAvailable,
                    "isFlexibleAllowed" to flexibleAllowed,
                    "isImmediateAllowed" to immediateAllowed,
                    "preferredType" to preferredType.lowercase(),
                    "preferredTypeAllowed" to preferredAllowed,
                    "passesStalenessConstraint" to staleEnough,
                    "passesPriorityConstraint" to priorityEnough,
                    "updateAvailability" to updateAvailability,
                    "installStatus" to installStatusLabel(info.installStatus()),
                    "installStatusCode" to info.installStatus(),
                    "clientVersionStalenessDays" to (stalenessDays ?: -1),
                    "updatePriority" to updatePriority,
                    "availableVersionCode" to info.availableVersionCode(),
                )

                currentFlowId?.let { payload["id"] = it }
                updateLastKnownStatus(payload)
                dispatchStateChanged(payload)
            }
            .addOnFailureListener { error ->
                dispatchFlowCompleted(
                    result = "failed",
                    updateType = preferredType.lowercase(),
                    id = currentFlowId,
                    error = error.message ?: "Unable to check update availability"
                )
            }
    }

    private fun startUpdateFlow(
        activity: FragmentActivity,
        type: Int,
        allowAssetPackDeletion: Boolean,
        flowId: String?
    ) {
        currentFlowType = type
        currentFlowId = flowId ?: currentFlowId

        manager(activity).appUpdateInfo
            .addOnSuccessListener { info ->
                val isAvailable = info.updateAvailability() == UpdateAvailability.UPDATE_AVAILABLE ||
                    info.updateAvailability() == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS

                if (!isAvailable) {
                    dispatchFlowCompleted(
                        result = "failed",
                        updateType = updateTypeLabel(type),
                        id = currentFlowId,
                        error = "No in-app update available"
                    )
                    return@addOnSuccessListener
                }

                if (!info.isUpdateTypeAllowed(type)) {
                    dispatchFlowCompleted(
                        result = "failed",
                        updateType = updateTypeLabel(type),
                        id = currentFlowId,
                        error = "Requested update type is not allowed by Google Play"
                    )
                    return@addOnSuccessListener
                }

                val options = AppUpdateOptions.newBuilder(type)
                    .setAllowAssetPackDeletion(allowAssetPackDeletion)
                    .build()

                try {
                    val started = manager(activity).startUpdateFlowForResult(
                        info,
                        activity,
                        options,
                        UPDATE_REQUEST_CODE
                    )

                    if (!started) {
                        dispatchFlowCompleted(
                            result = "failed",
                            updateType = updateTypeLabel(type),
                            id = currentFlowId,
                            error = "Google Play did not start the update flow"
                        )
                        return@addOnSuccessListener
                    }

                    val payload = mutableMapOf<String, Any>(
                        "status" to "flow_started",
                        "updateType" to updateTypeLabel(type),
                        "allowAssetPackDeletion" to allowAssetPackDeletion
                    )
                    currentFlowId?.let { payload["id"] = it }
                    updateLastKnownStatus(payload)
                    dispatchStateChanged(payload)
                } catch (error: Exception) {
                    dispatchFlowCompleted(
                        result = "failed",
                        updateType = updateTypeLabel(type),
                        id = currentFlowId,
                        error = error.message ?: "Failed to start in-app update flow"
                    )
                }
            }
            .addOnFailureListener { error ->
                dispatchFlowCompleted(
                    result = "failed",
                    updateType = updateTypeLabel(type),
                    id = currentFlowId,
                    error = error.message ?: "Failed to retrieve AppUpdateInfo"
                )
            }
    }

    private fun completeFlexible(activity: FragmentActivity, flowId: String?) {
        currentFlowType = AppUpdateType.FLEXIBLE
        currentFlowId = flowId ?: currentFlowId

        manager(activity).completeUpdate()
            .addOnSuccessListener {
                val payload = mutableMapOf<String, Any>(
                    "status" to "completing_flexible_update",
                    "updateType" to "flexible"
                )
                currentFlowId?.let { payload["id"] = it }
                updateLastKnownStatus(payload)
                dispatchStateChanged(payload)
            }
            .addOnFailureListener { error ->
                dispatchFlowCompleted(
                    result = "failed",
                    updateType = "flexible",
                    id = currentFlowId,
                    error = error.message ?: "Failed to complete flexible update"
                )
            }
    }

    private fun dispatchStateChanged(payload: Map<String, Any>) {
        dispatchEvent(EVENT_STATE_CHANGED, payload)
    }

    private fun dispatchFlowCompleted(
        result: String,
        updateType: String,
        id: String? = null,
        error: String? = null,
        errorCode: Int? = null
    ) {
        val payload = mutableMapOf<String, Any>(
            "result" to result,
            "updateType" to updateType
        )
        id?.let { payload["id"] = it }
        error?.let { payload["error"] = it }
        errorCode?.let { payload["errorCode"] = it }

        if (result == "installed" || result == "downloaded" || result == "canceled" || result == "failed") {
            updateLastKnownStatus(
                mutableMapOf<String, Any>(
                    "status" to "flow_completed",
                    "result" to result,
                    "updateType" to updateType
                ).apply {
                    id?.let { put("id", it) }
                    error?.let { put("error", it) }
                    errorCode?.let { put("errorCode", it) }
                }
            )
        }

        dispatchEvent(EVENT_FLOW_COMPLETED, payload)
    }

    private fun dispatchEvent(eventClass: String, payload: Map<String, Any>) {
        val activity = currentActivity ?: return

        Handler(Looper.getMainLooper()).post {
            val json = JSONObject()
            payload.forEach { (key, value) -> json.put(key, value) }
            NativeActionCoordinator.dispatchEvent(activity, eventClass, json.toString())
        }
    }

    private fun installStatusLabel(status: Int): String = when (status) {
        InstallStatus.PENDING -> "pending"
        InstallStatus.DOWNLOADING -> "downloading"
        InstallStatus.DOWNLOADED -> "downloaded"
        InstallStatus.INSTALLING -> "installing"
        InstallStatus.INSTALLED -> "installed"
        InstallStatus.FAILED -> "failed"
        InstallStatus.CANCELED -> "canceled"
        InstallStatus.REQUIRES_UI_INTENT -> "requires_ui_intent"
        InstallStatus.UNKNOWN -> "unknown"
        else -> "unknown"
    }

    private fun updateTypeLabel(type: Int?): String = when (type) {
        AppUpdateType.FLEXIBLE -> "flexible"
        AppUpdateType.IMMEDIATE -> "immediate"
        else -> "unknown"
    }

    private fun updateLastKnownStatus(payload: Map<String, Any>) {
        lastKnownStatus = payload.toMutableMap()
    }

    class CheckForUpdate(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val preferredType = parameters["preferredType"] as? String ?: "flexible"
            if (preferredType.lowercase() !in listOf("flexible", "immediate", "any")) {
                return BridgeResponse.error(
                    BridgeError.InvalidParameters(
                        "preferredType must be one of: flexible, immediate, any"
                    )
                )
            }

            val minStalenessDays = (parameters["minStalenessDays"] as? Number)?.toInt()
            val minPriority = (parameters["minPriority"] as? Number)?.toInt()
            val id = parameters["id"] as? String

            currentFlowId = id ?: currentFlowId
            checkAvailability(activity, preferredType, minStalenessDays, minPriority)

            return BridgeResponse.success(
                mapOf(
                    "status" to "checking",
                    "preferredType" to preferredType.lowercase(),
                    "id" to (currentFlowId ?: "")
                )
            )
        }
    }

    class StartFlexibleUpdate(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val allowAssetPackDeletion = parameters["allowAssetPackDeletion"] as? Boolean ?: false
            val id = parameters["id"] as? String

            startUpdateFlow(
                activity = activity,
                type = AppUpdateType.FLEXIBLE,
                allowAssetPackDeletion = allowAssetPackDeletion,
                flowId = id
            )

            return BridgeResponse.success(
                mapOf(
                    "status" to "starting",
                    "updateType" to "flexible",
                    "allowAssetPackDeletion" to allowAssetPackDeletion,
                    "id" to (id ?: "")
                )
            )
        }
    }

    class StartImmediateUpdate(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val allowAssetPackDeletion = parameters["allowAssetPackDeletion"] as? Boolean ?: false
            val id = parameters["id"] as? String

            startUpdateFlow(
                activity = activity,
                type = AppUpdateType.IMMEDIATE,
                allowAssetPackDeletion = allowAssetPackDeletion,
                flowId = id
            )

            return BridgeResponse.success(
                mapOf(
                    "status" to "starting",
                    "updateType" to "immediate",
                    "allowAssetPackDeletion" to allowAssetPackDeletion,
                    "id" to (id ?: "")
                )
            )
        }
    }

    class CompleteFlexibleUpdate(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            completeFlexible(activity, id)

            return BridgeResponse.success(
                mapOf(
                    "status" to "completing",
                    "updateType" to "flexible",
                    "id" to (id ?: "")
                )
            )
        }
    }

    class GetInstallStatus(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            manager(activity)
            return BridgeResponse.success(lastKnownStatus)
        }
    }
}
