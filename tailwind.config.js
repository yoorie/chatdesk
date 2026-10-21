import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.tsx',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        ui: {
          green: 'rgb(var(--brand-primary-rgb, 0 165 72) / <alpha-value>)',
          'green-dark': 'rgb(var(--brand-secondary-rgb, 5 58 53) / <alpha-value>)',
          'green-darker': '#075E54',
          'green-soft': '#E7FBEF',
          ink: '#0B1220',
          sidebar: '#1A1A2E',
          'sidebar-hover': '#252544',
          'sidebar-muted': '#8A8AA8',
          bg: '#F6F7F9',
          text: '#111827',
          'text-muted': '#6B7280',
          border: '#E5E7EB',
          'dark-bg': '#0B1220',
          'dark-surface': '#111827',
          'dark-surface-2': '#172033',
          'dark-border': '#253044',
          'dark-text': '#F8FAFC',
          'dark-text-muted': '#94A3B8',
          'dark-green-soft': 'rgba(0,165,72,0.14)',
        },
      },
      borderRadius: {
        btn: '8px',
        card: '12px',
      },
      boxShadow: {
        card: '0 1px 2px rgba(16,24,40,0.04), 0 1px 3px rgba(16,24,40,0.06)',
        'card-lg': '0 4px 6px -1px rgba(16,24,40,0.05), 0 10px 15px -3px rgba(16,24,40,0.06)',
        pop: '0 10px 30px -10px rgba(16,24,40,0.2)',
        glow: '0 20px 60px -20px rgba(0,165,72,0.5)',
      },
      keyframes: {
        ticker: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(-50%)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-8px)' },
        },
        pulseRing: {
          '0%': { transform: 'scale(0.7)', opacity: '1' },
          '100%': { transform: 'scale(2)', opacity: '0' },
        },
        fadeUp: {
          from: { opacity: '0', transform: 'translateY(20px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        ticker: 'ticker 30s linear infinite',
        float: 'float 6s ease-in-out infinite',
        'pulse-ring': 'pulseRing 2s cubic-bezier(0.455,0.03,0.515,0.955) infinite',
        fadeUp: 'fadeUp 800ms cubic-bezier(0.16,1,0.3,1) both',
      },
    },
  },

  plugins: [forms],
};
