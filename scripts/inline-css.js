// Inlines assets/css/tailwind.css and style-rebrand.css into index.html between
// the inline-css markers, so first paint needs no render-blocking CSS requests.
// Run via `npm run build:css` after changing classes or either stylesheet.
const fs = require('fs');
const read = (f) => fs.readFileSync(f, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').trim();
const css = read('assets/css/tailwind.css') + '\n' + read('assets/css/style-rebrand.css');
const html = fs.readFileSync('index.html', 'utf8');
const re = /<!-- inline-css:start -->[\s\S]*?<!-- inline-css:end -->/;
if (!re.test(html)) throw new Error('inline-css markers not found in index.html');
fs.writeFileSync('index.html', html.replace(re, () => `<!-- inline-css:start -->\n  <style>${css}</style>\n  <!-- inline-css:end -->`));
console.log(`Inlined ${css.length} bytes of CSS`);
