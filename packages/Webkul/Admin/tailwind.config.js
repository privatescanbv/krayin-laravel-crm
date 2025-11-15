/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],

    theme: {
        container: {
            center: true,

            screens: {
                "4xl": "1920px",
            },

            padding: {
                DEFAULT: "16px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            "3xl": "1680px",
            "4xl": "1920px",
        },

        extend: {
            colors: {
                brandColor: "var(--brand-color)",
                brand: {
                    DEFAULT: "var(--brand-color)",
                    soft: "var(--brand-color)",
                    medium: "var(--brand-color)",
                    strong: "var(--brand-color)",
                },
                neutral: {
                    'secondary-medium': '#f3f4f6',
                },
                default: {
                    medium: '#d1d5db',
                },
                heading: '#111827',
                body: '#6b7280',
            },
            borderRadius: {
                base: '0.375rem',
                xs: '0.125rem',
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
            fontFamily: {
                inter: ['Inter'],
                icon: ['icomoon']
            }
        },
    },

    darkMode: 'class',

    plugins: [require('@tailwindcss/forms')],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};
