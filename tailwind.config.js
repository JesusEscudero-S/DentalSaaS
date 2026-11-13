import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Tokens tomados de Figma (archivo "Interfaces Graficas Dental Saas").
 * Las escalas slate/blue/sky/emerald/violet/amber/red de Tailwind coinciden
 * con los colores de Figma, así que se reutilizan tal cual.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/**/*.php',
        './app/Enums/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                },
                danger: {
                    50: '#fef2f2',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                },
                surface: '#ffffff',
            },
            fontSize: {
                // Estilos de texto de Figma
                h1: ['24px', { lineHeight: '32px', fontWeight: '600' }],
                body: ['14px', { lineHeight: '20px' }],
                'body-md': ['13px', { lineHeight: '20px' }],
                caption: ['12px', { lineHeight: '16px', fontWeight: '600' }],
                overline: ['11px', { lineHeight: '16px', letterSpacing: '0.06em', fontWeight: '600' }],
            },
            borderRadius: {
                DEFAULT: '8px',
            },
            spacing: {
                // Medidas del Shell de Figma (w-sidebar, pl-sidebar, h-topbar, top-topbar…)
                sidebar: '235px',
                topbar: '60px',
            },
        },
    },
    plugins: [],
};
