/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/scripts/admin/**/*.jsx",
    "./assets/scripts/admin/translation-dashboard/**/*.jsx"
  ],
  theme: {
    extend: {
      animation: {
        spin: 'spin 1s linear infinite',
      },
      keyframes: {
        spin: {
          '0%': { transform: 'rotate(0deg)' },
          '100%': { transform: 'rotate(360deg)' },
        },
      },
    },
  },
  plugins: [],
};
