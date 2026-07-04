<?php
/*
 * English-fallback renderer for the Spanish manual.
 *
 * While the ES translation is incomplete, Apache rewrites any request for a
 * /docs/es/<page>.phtml that does NOT exist yet to:  _fallback.php?p=<page>.phtml
 * We then render the SPANISH shell (header + full nav + footer) around the
 * ENGLISH page's body, prefaced by a "not translated yet" notice — so links
 * never 404 and the reader stays inside the Spanish site + language switcher.
 *
 * When the page is later translated, its real es/<page>.phtml is served directly
 * and this fallback is bypassed (Apache only rewrites missing files).
 */

$p = basename($_GET['p'] ?? '');
$valid = (bool) preg_match('/^[A-Za-z0-9._-]+\.phtml$/', $p) && $p !== '_fallback.phtml';
$enSrc = $valid ? __DIR__ . '/../en/' . $p : null;

if (!$valid || !is_file($enSrc)) {
    http_response_code(404);
    $title  = 'Página no encontrada';
    $__file = 'index.phtml';
    include __DIR__ . '/_header.phtml';
    echo '<div class="chapter"><div class="titlepage"><div><div>'
       . '<h2 class="title">404 &mdash; Página no encontrada</h2></div></div></div>'
       . '<p>La página solicitada no existe. Vuelva al <a href="index.phtml">inicio del manual</a>.</p></div>';
    include __DIR__ . '/_footer.phtml';
    exit;
}

$raw = file_get_contents($enSrc);

/* title comes from the EN wrapper's first line: <?php $title = '...'; include ... ?> */
$title = preg_match("/\\\$title\\s*=\\s*'(.*?)';/s", $raw, $m) ? stripcslashes($m[1]) : 'TigerZF';

/* the body is everything between the header-include line and the footer-include line */
$hdrEnd = strpos($raw, '?>');
$ftrPos = strrpos($raw, "<?php include __DIR__ . '/_footer.phtml'");
$body   = ($hdrEnd !== false && $ftrPos !== false && $ftrPos > $hdrEnd)
        ? trim(substr($raw, $hdrEnd + 2, $ftrPos - $hdrEnd - 2))
        : $raw;

$__file = $p;   // so the language switcher's "English" link targets the right page
include __DIR__ . '/_header.phtml';
?>
<div class="tzf-xlate-note">
  Esta página aún no está traducida al español; se muestra la versión en inglés.
  <a href="../en/<?= htmlspecialchars($p) ?>">Ver en inglés</a>.
</div>
<?= $body ?>
<?php include __DIR__ . '/_footer.phtml'; ?>
