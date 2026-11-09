/** Build: npm run build:css  (output: assets/css/tailwind.css) */
module.exports = Object.assign({
  theme: {
    extend: {
      colors: {
        h: {
          white:   '#ffffff',
          offwhite:'#f4f7fb',
          subtle:  '#fafcfe',
          body:    '#0b2140',
          mid:     '#44576f',
          muted:   '#5f7188',
          border:  '#d5dde7',
          divider: '#e6ecf3',
          greylt:  '#eef1f5',
          teal:    '#0a7c7e',
          tealb:   '#22adaf',
          tealc:   '#076c6e',
          teallt:  '#e6f2f2',
          tealbd:  '#cde5e5',
          navy:    '#072144',
          navylt:  '#eaeff6',
          navymid: '#123163',
          navybd:  '#d5e0ef',
          orange:  '#d0870d',
          orangeb: '#e0a63a',
          orangec: '#8a5a07',
          orangecd:'#6f4806',
          orangelt:'#fdf4e3',
          orangebd:'#f5e3be',
        }
      },
      fontFamily: {
        sans: ['-apple-system','BlinkMacSystemFont','Inter','system-ui','sans-serif'],
      },
    }
  }
}, {
  content: ['./index.html', './privacy.html', './assets/js/main.js', './scripts/build-insights.js', './insights/**/*.html'],
});
