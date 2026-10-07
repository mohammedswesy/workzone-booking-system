<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/Ui/Button.vue';
import ThemeLocaleToggle from '@/Components/Ui/ThemeLocaleToggle.vue';
import Toast from '@/Components/Ui/Toast.vue';
import WorkspaceCard from '@/Components/Ui/WorkspaceCard.vue';

const props = defineProps({
    featured: { type: Array, default: () => [] },
});

const { t } = useI18n();

const safeFeatured = (props.featured || []).filter((space) => space && space.id);

/** Add or remove public/images paths here — carousel loops automatically. */
const heroImages = [
    '/images/home-hero-1.svg',
    '/images/home-hero-2.svg',
    '/images/home-hero-3.svg',
    '/images/home-hero-4.svg',
];

const activeHeroIndex = ref(0);
let heroTimer = null;

function stopHeroTimer() {
    if (heroTimer != null) {
        window.clearInterval(heroTimer);
        heroTimer = null;
    }
}

function startHeroTimer() {
    stopHeroTimer();
    if (heroImages.length < 2) return;
    heroTimer = window.setInterval(() => {
        activeHeroIndex.value = (activeHeroIndex.value + 1) % heroImages.length;
    }, 2000);
}

function goToHeroSlide(index) {
    if (index < 0 || index >= heroImages.length) return;
    activeHeroIndex.value = index;
    startHeroTimer();
}

onMounted(startHeroTimer);
onBeforeUnmount(stopHeroTimer);
</script>

<template>
    <Head :title="t('brand.name')" />
    <div class="min-h-screen bg-wz-bg text-wz-fg">
        <Toast />
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,var(--wz-brand-soft),transparent_35%),radial-gradient(circle_at_80%_0%,var(--wz-accent-soft),transparent_30%)]" />

        <header class="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
            <div>
                <p class="font-display text-xl font-bold text-wz-brand">{{ t('brand.name') }}</p>
                <p class="text-xs text-wz-fg-muted">{{ t('brand.tagline') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <ThemeLocaleToggle />
                <Link :href="route('login')" class="hidden sm:inline-flex">
                    <Button variant="secondary" size="sm">{{ t('nav.login') }}</Button>
                </Link>
            </div>
        </header>

        <main class="relative mx-auto max-w-6xl px-4 sm:px-6">
            <section class="grid items-center gap-10 py-10 lg:grid-cols-2 lg:py-16">
                <div>
                    <p class="mb-3 text-sm font-medium text-wz-accent">{{ t('home.eyebrow') }}</p>
                    <h1 class="font-display text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                        {{ t('home.title') }}
                    </h1>
                    <p class="mt-4 max-w-xl text-base text-wz-fg-muted sm:text-lg">
                        {{ t('home.subtitle') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('spaces.index')">
                            <Button size="lg">{{ t('home.cta') }}</Button>
                        </Link>
                        <Link :href="route('register')">
                            <Button size="lg" variant="secondary">{{ t('nav.register') }}</Button>
                        </Link>
                    </div>
                </div>
                <div>
                    <div class="wz-surface overflow-hidden">
                        <div class="relative">
                            <!-- Keeps the same layout height while slides fade on top -->
                            <img
                                :src="heroImages[0]"
                                alt=""
                                aria-hidden="true"
                                class="invisible min-h-[280px] w-full object-cover"
                                width="1200"
                                height="800"
                            />
                            <img
                                v-for="(src, index) in heroImages"
                                :key="src"
                                :src="src"
                                alt="WorkZone"
                                class="absolute inset-0 min-h-[280px] w-full object-cover transition-opacity duration-700 ease-in-out"
                                :class="index === activeHeroIndex ? 'opacity-100' : 'opacity-0'"
                                width="1200"
                                height="800"
                            />
                        </div>
                    </div>
                    <!-- Dots sit below the image (outside overflow) so they stay visible -->
                    <div
                        class="mt-3 flex items-center justify-center gap-2.5 sm:mt-4"
                        data-testid="hero-slider-dots"
                        role="tablist"
                        :aria-label="t('brand.name')"
                    >
                        <button
                            v-for="(src, index) in heroImages"
                            :key="`dot-${src}`"
                            type="button"
                            class="h-2.5 w-2.5 shrink-0 rounded-full transition-colors duration-300 sm:h-3 sm:w-3"
                            :class="index === activeHeroIndex
                                ? 'bg-[#ef4444] shadow-sm'
                                : 'bg-neutral-300 hover:bg-neutral-200 dark:bg-white/85 dark:hover:bg-white'"
                            :aria-label="`${index + 1}`"
                            :aria-selected="index === activeHeroIndex"
                            role="tab"
                            @click="goToHeroSlide(index)"
                        />
                    </div>
                </div>
            </section>

            <section class="py-10">
                <h2 class="mb-6 font-display text-2xl font-semibold">{{ t('home.howTitle') }}</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">01</div>
                        <h3 class="font-semibold">{{ t('home.how1') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how1Hint') }}</p>
                    </div>
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">02</div>
                        <h3 class="font-semibold">{{ t('home.how2') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how2Hint') }}</p>
                    </div>
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">03</div>
                        <h3 class="font-semibold">{{ t('home.how3') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how3Hint') }}</p>
                    </div>
                </div>
            </section>

            <section v-if="safeFeatured.length" class="py-10">
                <div class="mb-6 flex items-end justify-between gap-3">
                    <h2 class="font-display text-2xl font-semibold">{{ t('home.featuredTitle') }}</h2>
                    <Link :href="route('spaces.index')" class="text-sm text-wz-brand hover:underline">
                        {{ t('home.cta') }}
                    </Link>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <WorkspaceCard
                        v-for="space in safeFeatured"
                        :key="space.id"
                        :space="space"
                    />
                </div>
            </section>

            <section class="wz-surface my-10 overflow-hidden bg-wz-brand-soft p-8 sm:p-10">
                <h2 class="font-display text-2xl font-semibold text-wz-fg">{{ t('home.ctaBand') }}</h2>
                <p class="mt-2 max-w-xl text-wz-fg-muted">{{ t('home.ctaBandHint') }}</p>
                <div class="mt-6">
                    <Link :href="route('spaces.index')">
                        <Button size="lg">{{ t('home.cta') }}</Button>
                    </Link>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-wz-border py-6 text-center text-xs text-wz-fg-muted">
            © {{ new Date().getFullYear() }} {{ t('brand.name') }} · {{ t('brand.tagline') }}
        </footer>
    </div>
</template>
