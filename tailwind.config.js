import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                wz: {
                    bg: 'var(--wz-bg)',
                    elevated: 'var(--wz-bg-elevated)',
                    muted: 'var(--wz-bg-muted)',
                    fg: 'var(--wz-fg)',
                    'fg-muted': 'var(--wz-fg-muted)',
                    border: 'var(--wz-border)',
                    brand: 'var(--wz-brand)',
                    'brand-fg': 'var(--wz-brand-fg)',
                    'brand-soft': 'var(--wz-brand-soft)',
                    accent: 'var(--wz-accent)',
                    'accent-soft': 'var(--wz-accent-soft)',
                    success: 'var(--wz-success)',
                    warning: 'var(--wz-warning)',
                    danger: 'var(--wz-danger)',
                },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', '"IBM Plex Sans Arabic"', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', '"IBM Plex Sans Arabic"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                wz: 'var(--wz-radius)',
            },
            boxShadow: {
                wz: 'var(--wz-shadow)',
            },
        },
    },

    plugins: [forms],
};
