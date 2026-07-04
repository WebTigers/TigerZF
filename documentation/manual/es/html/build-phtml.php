<?php
/**
 * build-phtml.php — regenerate the templated .phtml pages from the static .html.
 * The .html files are the source of truth (browsable, offline-friendly). Run this
 * after editing any .html to refresh the live site's .phtml wrappers:
 *     php build-phtml.php
 * Not a build system — just a DRY transform (extract <body>, rewrite links, wrap).
 */
$dir = __DIR__;
$relink = fn(string $s): string => preg_replace_callback(
    '/href="([A-Za-z0-9][A-Za-z0-9._-]*)\.html(#[^"]*)?"/',
    fn($m) => 'href="' . $m[1] . '.phtml' . ($m[2] ?? '') . '"',
    $s
);
$n = 0;
foreach (glob("$dir/*.html") as $file) {
    $base = basename($file, '.html');
    if ($base === '_nav') continue;                 // partial, not a page
    $html = file_get_contents($file);
    $title = preg_match('/<title>(.*?)<\/title>/is', $html, $t)
        ? addslashes(html_entity_decode(trim($t[1]), ENT_QUOTES)) : 'TigerZF Manual';
    $body = preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $b) ? $b[1] : $html;
    $body = trim($relink($body));
    $out = "<?php \$title = '$title'; include __DIR__ . '/_header.phtml'; ?>\n"
         . $body . "\n<?php include __DIR__ . '/_footer.phtml'; ?>\n";
    file_put_contents("$dir/$base.phtml", $out);
    $n++;
}
echo "Regenerated $n .phtml pages from .html\n";
