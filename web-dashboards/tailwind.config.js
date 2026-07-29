/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: { DEFAULT: '#3183CC', 50: '#EAF3FB', 100: '#CFE4F5', 200: '#9EC9EB', 500: '#3183CC', 600: '#256BA8', 700: '#1D5486' },
        navy: { DEFAULT: '#032252', 50: '#E9EDF5', 600: '#0A3570', 700: '#062A5E', 800: '#032252', 900: '#011738' },
        accent: { DEFAULT: '#FC9D0D', 50: '#FFF4E0', 100: '#FFE3B0', 600: '#DB8600' },
      },
      fontFamily: { sans: ['Inter', 'sans-serif'] },
    },
  },
  plugins: [],
};
