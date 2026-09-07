import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                forest: {
                    50: '#F3F7F4',
                    100: '#E4EDE6',
                    200: '#C5D6C9',
                    300: '#9BB5A3',
                    400: '#6B9178',
                    500: '#3F6B52',
                    600: '#2A5340',
                    700: '#1C3F30',
                    800: '#0F3D2E',
                    900: '#0A2A20',
                    950: '#061910',
                },
                lime: {
                    50: '#F7FDE8',
                    100: '#ECF9C5',
                    200: '#D8F28A',
                    300: '#C6F25B',
                    400: '#B8E64A',
                    500: '#9ACC2C',
                    600: '#7AA31C',
                    700: '#5C7A16',
                    800: '#4A6116',
                    900: '#3E5118',
                },
                sand: {
                    50: '#F6F7F4',
                    100: '#EEF0EA',
                },
            },
            boxShadow: {
                card: '0 8px 24px rgba(15, 61, 46, 0.06)',
                soft: '0 1px 2px rgba(15, 61, 46, 0.06)',
            },
        },
    },

    plugins: [forms],
};
