<script setup>
import { useLocale } from '@/Composables/useLocale';
import { useTheme } from '@/Composables/useTheme';
import Button from './Button.vue';

const { locale, toggleLocale } = useLocale();
const { theme, toggleTheme } = useTheme();
</script>

<template>
    <div class="flex items-center gap-2">
        <Button variant="secondary" size="sm" type="button" @click="toggleLocale">
            {{ locale === 'ar' ? 'EN' : 'عربي' }}
        </Button>
        <Button variant="secondary" size="sm" type="button" @click="toggleTheme">
            {{ theme === 'dark' ? $t('common.light') : $t('common.dark') }}
        </Button>
    </div>
</template>
