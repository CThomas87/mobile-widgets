const baseUrl = '/_native/api/call';

function normalizeBridgeResult(raw) {
    if (raw === null || typeof raw === 'undefined') {
        return {
            ok: false,
            data: null,
            error: {
                code: 'INVALID_BRIDGE_RESPONSE',
                message: 'Bridge response was empty',
                recoverable: true,
            },
        };
    }

    const isError = raw?.status === 'error' || !!raw?.error;

    if (isError) {
        const code = raw?.code || raw?.error?.code || 'EXECUTION_FAILED';
        const message = raw?.message || raw?.error?.message || 'Bridge call failed';

        return {
            ok: false,
            data: null,
            error: {
                code,
                message,
                recoverable: true,
            },
        };
    }

    return {
        ok: true,
        data: raw?.data ?? raw ?? {},
        error: null,
    };
}

async function resolveNativeBridgeCall() {
    try {
        const nativeModule = await import('@nativephp/native');

        if (typeof nativeModule.bridgeCall === 'function') {
            return nativeModule.bridgeCall;
        }
    } catch (_) {
    }

    return async (method, params = {}) => {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ method, params }),
        });

        return response.json();
    };
}

async function call(method, params = {}) {
    try {
        const bridgeCall = await resolveNativeBridgeCall();
        const raw = await bridgeCall(method, params);

        return normalizeBridgeResult(raw);
    } catch (error) {
        return {
            ok: false,
            data: null,
            error: {
                code: 'EXECUTION_FAILED',
                message: error instanceof Error ? error.message : 'Bridge call failed',
                recoverable: true,
            },
        };
    }
}

export async function execute(options = {}) {
    return setData(options);
}

export async function setData(payload) {
    return call('Widget.SetData', { payload });
}

export async function reloadAll() {
    return call('Widget.ReloadAll', {});
}

export async function configure(options = {}) {
    return call('Widget.Configure', options);
}

export async function getStatus() {
    return call('Widget.GetStatus', {});
}

export { normalizeBridgeResult };

export const widget = {
    execute,
    setData,
    reloadAll,
    configure,
    getStatus,
};

export const widgets = widget;

export default widget;
