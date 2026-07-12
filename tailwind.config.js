/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./app/**/*.php', './resources/views/**/*.php', './public/assets/js/**/*.js'],
  theme: { extend: { colors: { ink: '#101828', accent: '#f97316' } } },
  plugins: []
};

