/* Lightweight PHP syntax highlighting for the manual's code blocks.
   highlight.js core + php only (~10KB gzip), ES-module, self-hosted. */
import hljs from './hljs-core.min.js';
import php from './hljs-php.min.js';
hljs.registerLanguage('php', php);
document.querySelectorAll('.tzf-content pre.programlisting').forEach(function (pre) {
  if (pre.querySelector('code')) return;            // already processed
  var code = document.createElement('code');
  code.className = 'language-php';
  code.textContent = pre.textContent;               // raw source (entities already decoded)
  pre.textContent = '';
  pre.appendChild(code);
  try { hljs.highlightElement(code); } catch (e) {}
});
