import { onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Tracks Inertia navigation for filter/list loading UI.
 * Uses router.on() unsubscribe functions — Inertia has no router.off().
 */
export function useNavigationLoading() {
    const loading = ref(false);

    const onStart = () => {
        loading.value = true;
    };
    const onStop = () => {
        loading.value = false;
    };

    const offStart = router.on('start', onStart);
    const offFinish = router.on('finish', onStop);
    const offError = router.on('error', onStop);

    onUnmounted(() => {
        offStart();
        offFinish();
        offError();
    });

    return { loading };
}
