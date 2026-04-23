/**
 * InAppUpdate Plugin for NativePHP Mobile
 *
 * @example
 * import { InAppUpdate, Events } from '@wilsonatb/in-app-update';
 * import { on } from '@nativephp/native';
 *
 * const id = crypto.randomUUID();
 *
 * // 1) Check availability
 * await InAppUpdate.checkForUpdate({ preferredType: 'flexible', id });
 *
 * // 2) Start flow
 * await InAppUpdate.startFlexibleUpdate({ id });
 *
 * // 3) Listen for install state changes/completion
 * on(Events.InAppUpdateStateChanged, (payload) => console.log(payload));
 * on(Events.InAppUpdateFlowCompleted, (payload) => console.log(payload));
 */

const baseUrl = '/_native/api/call';

/**
 * Internal bridge call function
 * @private
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    const nativeResponse = result.data;
    if (nativeResponse && nativeResponse.data !== undefined) {
        return nativeResponse.data;
    }

    return nativeResponse;
}

/**
 * @param {string} method
 * @param {Record<string, any>} params
 * @returns {Promise<any>}
 */
async function call(method, params = {}) {
    return bridgeCall(method, params);
}

/**
 * Check Play Core update availability.
 *
 * @param {Object} options
 * @param {'flexible'|'immediate'|'any'} [options.preferredType='flexible']
 * @param {number|null} [options.minStalenessDays]
 * @param {number|null} [options.minPriority]
 * @param {string|null} [options.id]
 * @returns {Promise<Object|null>}
 */
export async function checkForUpdate(options = {}) {
    return call('InAppUpdate.CheckForUpdate', options);
}

/**
 * Start flexible update flow.
 *
 * @param {Object} options
 * @param {boolean} [options.allowAssetPackDeletion=false]
 * @param {string|null} [options.id]
 * @returns {Promise<Object|null>}
 */
export async function startFlexibleUpdate(options = {}) {
    return call('InAppUpdate.StartFlexibleUpdate', options);
}

/**
 * Start immediate update flow.
 *
 * @param {Object} options
 * @param {boolean} [options.allowAssetPackDeletion=false]
 * @param {string|null} [options.id]
 * @returns {Promise<Object|null>}
 */
export async function startImmediateUpdate(options = {}) {
    return call('InAppUpdate.StartImmediateUpdate', options);
}

/**
 * Install downloaded flexible update.
 *
 * @param {Object} options
 * @param {string|null} [options.id]
 * @returns {Promise<Object|null>}
 */
export async function completeFlexibleUpdate(options = {}) {
    return call('InAppUpdate.CompleteFlexibleUpdate', options);
}

/**
 * Read the latest known native in-app update state.
 *
 * @returns {Promise<Object|null>}
 */
export async function getInstallStatus() {
    return call('InAppUpdate.GetInstallStatus');
}

// PascalCase export per NativePHP plugin conventions.
export const InAppUpdate = {
    checkForUpdate,
    startFlexibleUpdate,
    startImmediateUpdate,
    completeFlexibleUpdate,
    getInstallStatus,
};

export const Events = {
    InAppUpdateStateChanged: 'Wilsonatb\\InAppUpdate\\Events\\InAppUpdateStateChanged',
    InAppUpdateFlowCompleted: 'Wilsonatb\\InAppUpdate\\Events\\InAppUpdateFlowCompleted',
};

export default InAppUpdate;
