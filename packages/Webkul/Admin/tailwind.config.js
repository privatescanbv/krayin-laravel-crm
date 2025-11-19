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
                // Dynamic brand hook (CSS variable)
                brandColor: "#11518f",

                // Brand object: keeps dynamic var + explicit brand palettes
                brand: {
                    DEFAULT: "var(--brand-color)",
                    soft: "var(--brand-color)",
                    medium: "var(--brand-color)",
                    strong: "var(--brand-color)",

                    privatescan: {
                        main: "#11518f",  // primary
                        accent: "#f37835", // accent
                    },
                    herniapoli: {
                        main: "#033b4a",  // primary
                        accent: "#45ccc5", // accent
                    },
                },

                // Neutral system for CRM UI
                neutral: {
                    bg: "#f4f7f9",
                    card: "#ffffff",
                    border: "#dde3e9",
                    text: "#4a5565",      // main body text
                    heading: "#1f2933",   // neutral heading
                    muted: "#6b7785",

                    // existing token from your config
                    "secondary-medium": "#f3f4f6",
                },

                // existing "default" group kept
                default: {
                    medium: "#d1d5db",
                },

                // existing single tokens kept for compatibility
                heading: "#111827",
                body: "#6b7280",

                // Semantic colors (success / warning / error / info)
                semantic: {
                    success: "#2ecc71",
                    warning: "#f1c40f",
                    error: "#e74c3c",
                    info: "#11518f",
                },

                // Soft tints for subtle backgrounds / badges
                soft: {
                    privatescanBlue: "#e8f0f9",
                    privatescanOrange: "#ffe7d8",
                    herniapoliTeal: "#e7f7f6",
                    cyan: "#e5f6fc",
                },

                // Activity colors (for email/note/call/etc)
                // Usage: bg-activity-email-bg text-activity-email-text
                activity: {
                    email: {
                        bg: "rgba(21,55,138, 0.15)",
                        text: "#15378a",
                        border: "#15378a",
                    },
                    note: {
                        bg: "rgba(58,104,199, 0.15)",
                        text: "#3a68c7",
                        border: "#3a68c7",
                    },
                    call: {
                        // Privatescan blue
                        bg: "rgba(17, 81, 143, 0.15)",
                        text: "#11518f",
                        border: "#11518f",
                    },
                    meeting: {
                        // Herniapoli teal
                        bg: "rgba(69, 204, 197, 0.15)",
                        text: "#45ccc5",
                        border: "#45ccc5",
                    },
                    task: {
                        // Success green
                        bg: "rgba(13,147,213, 0.15)",
                        text: "#0d93d5",
                        border: "#0d93d5",
                    },
                    file: {
                        // Purple for docs
                        bg: "rgba(99,64,133, 0.15)",
                        text: "#634085",
                        border: "#634085",
                    },
                    system: {
                        // Yellow for system/automated
                        bg: "rgba(241, 196, 15, 0.20)",
                        text: "#b68900",
                        border: "#b68900",
                    },
                    default: {
                        // Neutral gray fallback
                        bg: "rgba(107, 119, 133, 0.15)",
                        text: "#6b7785",
                        border: "#6b7785",
                    },
                },

                succes: "#2ecc71",
                warning: "#ffc641",
                error: "#e74c3c",

                // Status colors (in_progress/active/on_hold/expired/done)
                // Usage:
                //   bg-status-in_progress-bg
                //   text-status-in_progress-text
                //   border-status-in_progress-border
                //   ring-status-in_progress-ring
                status: {
                    in_progress: {
                        bg: "rgba(17, 81, 143, 0.15)",   // blue
                        text: "#11518f",
                        border: "#11518f",
                        ring: "rgba(17, 81, 143, 0.35)",
                    },
                    active: {
                        bg: "rgba(46, 204, 113, 0.15)",  // green
                        text: "#2ecc71",
                        border: "#2ecc71",
                        ring: "rgba(46, 204, 113, 0.35)",
                    },
                    on_hold: {
                        bg: "rgba(255,198,65, 0.20)",  // yellow
                        text: "#ffc641",
                        border: "#ffc641",
                        ring: "rgba(241, 196, 15, 0.35)",
                    },
                    expired: {
                        bg: "rgba(231, 76, 60, 0.15)",   // red
                        text: "#e74c3c",
                        border: "#e74c3c",
                        ring: "rgba(231, 76, 60, 0.35)",
                    },
                    done: {
                        bg: "rgba(107, 119, 133, 0.15)", // gray
                        text: "#6b7785",
                        border: "#6b7785",
                        ring: "rgba(107, 119, 133, 0.35)",
                    },
                },
            },
            borderRadius: {
                base: '0.375rem',
                xs: '0.125rem',
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
fontFamily: {
                inter: ["Inter", "system-ui", "sans-serif"],
                icon: ["icomoon"],
            },

            fontSize: {
                // Body sizes
                xs:  ["0.75rem",  { lineHeight: "1.25rem" }], // 12 / 20
                sm:  ["0.875rem", { lineHeight: "1.4rem"  }], // 14 / ~22
                base:["1rem",     { lineHeight: "1.5rem"  }], // 16 / 24
                md:  ["1.0625rem",{ lineHeight: "1.6rem"  }], // 17 / ~26
                lg:  ["1.125rem", { lineHeight: "1.75rem" }], // 18 / 28

                // Headings
                xl:   ["1.25rem",   { lineHeight: "1.8rem",  fontWeight: "600" }], // 20
                "2xl":["1.5rem",    { lineHeight: "2rem",    fontWeight: "600" }], // 24
                "3xl":["1.875rem",  { lineHeight: "2.25rem", fontWeight: "600" }], // 30
                "4xl":["2.25rem",   { lineHeight: "2.6rem",  fontWeight: "700" }], // 36
            },

            fontWeight: {
                normal: "400",
                medium: "500",
                semibold: "600",
                bold: "700",
            },
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
