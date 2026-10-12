/** @type {import('tailwindcss').Config} */
// ---------------------------------------------------------------------------
// Tailwind theme — carried over unchanged from the previous React frontend so
// the platform keeps the same clinical blue identity.
// ---------------------------------------------------------------------------
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.{js,vue}',
    ],
    theme: {
        extend: {
            colors: {
                // Clinical blue — the platform's primary brand colour
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                    950: '#172554',
                },
                // Medical teal — success / correct states and accents
                teal: {
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
                // Exam semantics. Explicit so the exam UI can signal
                // correct/incorrect independently of the theme.
                correct: '#16a34a',
                incorrect: '#dc2626',
                flagged: '#f59e0b',
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                // Noto Sans Myanmar is loaded in the root Blade view so Myanmar
                // text renders correctly.
                myanmar: ['"Noto Sans Myanmar"', 'Inter', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 3px 0 rgb(15 23 42 / 0.08), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
                'card-hover': '0 10px 24px -6px rgb(15 23 42 / 0.14)',
            },
            animation: {
                'fade-in': 'fadeIn 0.25s ease-out',
                'slide-up': 'slideUp 0.3s ease-out',
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                slideUp: {
                    '0%': { opacity: 0, transform: 'translateY(8px)' },
                    '100%': { opacity: 1, transform: 'translateY(0)' },
                },
            },
        },
    },
    plugins: [],
};
