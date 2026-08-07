import defaultTheme from 'tailwindcss/defaultTheme';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Enums/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                accent: {
                    50: '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0d9488',
                    700: '#0f766e',
                    800: '#115e59',
                    900: '#134e4a',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            typography: (theme) => ({
                invert: {
                    css: {
                        '--tw-prose-body': theme('colors.slate.300'),
                        '--tw-prose-headings': theme('colors.white'),
                        '--tw-prose-links': theme('colors.accent.400'),
                        '--tw-prose-bold': theme('colors.white'),
                        '--tw-prose-code': theme('colors.accent.300'),
                        '--tw-prose-quotes': theme('colors.slate.200'),
                        '--tw-prose-quote-borders': theme('colors.accent.700'),
                    },
                },
            }),
        },
    },
    plugins: [typography],
};
