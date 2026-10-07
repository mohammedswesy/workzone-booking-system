<script setup>
import { ref, onErrorCaptured } from 'vue';
import Button from './Button.vue';

const failed = ref(false);
const detail = ref('');

function isUnmountOrCleanupError(info) {
    const text = String(info || '').toLowerCase();
    return (
        text.includes('unmount') ||
        text.includes('cleanup') ||
        text.includes('beforeunmount')
    );
}

onErrorCaptured((err, instance, info) => {
    // Cleanup/unmount failures must not blank the whole app (e.g. bad router.off).
    if (isUnmountOrCleanupError(info)) {
        // eslint-disable-next-line no-console
        console.warn('[AppErrorBoundary] ignored unmount/cleanup error:', err, info);
        return false;
    }

    failed.value = true;
    detail.value = err?.message
        ? String(err.message)
        : (typeof err === 'string' ? err : 'Render error');
    if (info) {
        detail.value = `${detail.value} (${info})`;
    }
    return false;
});

function reload() {
    window.location.reload();
}
</script>

<template>
    <div
        v-if="failed"
        class="flex min-h-screen flex-col items-center justify-center gap-4 bg-wz-bg px-6 text-center text-wz-fg"
        role="alert"
    >
        <h1 class="font-display text-2xl font-semibold">Something went wrong</h1>
        <p class="max-w-md text-sm text-wz-fg-muted">
            The page failed to render. Reload to continue.
        </p>
        <p
            v-if="detail"
            class="max-w-lg break-words rounded-lg bg-wz-muted px-3 py-2 font-mono text-xs text-wz-fg-muted"
        >
            {{ detail }}
        </p>
        <Button type="button" variant="primary" @click="reload">Reload page</Button>
    </div>
    <slot v-else />
</template>
