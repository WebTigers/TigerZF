<?php
/**
 * build-search-index.php — build the per-language SQLite FTS5 full-text search
 * indexes for the TigerZF docs. Run at deploy time (the docs are static):
 *     php build-search-index.php
 * Produces <lang>/html/search-index.sqlite (git-ignored build artifacts).
 *
 * Each page contributes title + headings + body (shell wrapper first/last line,
 * prev/next nav chrome, and HTML tags stripped). unicode61 + remove_diacritics
 * so Spanish accents match ("validacion" finds "validacion"). bm25() weighting
 * is applied at query time in search.php.
 */
$base  = __DIR__;
$langs = ['en', 'es'];
$skip  = ['_header.phtml', '_footer.phtml', 'search.phtml', 'index.phtml'];

foreach ($langs as $lang) {
    $dir = "$base/$lang/html";
    if (!is_dir($dir)) { echo "$lang: no html dir\n"; continue; }

    $dbfile = "$dir/search-index.sqlite";
    @unlink($dbfile);
    $db = new PDO("sqlite:$dbfile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF;');
    $db->exec("CREATE VIRTUAL TABLE pages USING fts5(title, headings, body, url UNINDEXED, tokenize='unicode61 remove_diacritics 2')");
    $ins = $db->prepare('INSERT INTO pages(title,headings,body,url) VALUES(?,?,?,?)');
    $db->beginTransaction();

    $n = 0;
    foreach (glob("$dir/*.phtml") as $f) {
        $bn = basename($f);
        if (in_array($bn, $skip, true)) continue;

        // wrapper: first line is the header include (carries $title), last line the footer include
        $lines = explode("\n", file_get_contents($f));
        $first = array_shift($lines) ?? '';
        $title = preg_match("/title\\s*=\\s*'(.*?)';/", $first, $m) ? stripcslashes($m[1]) : $bn;
        while ($lines && (trim(end($lines)) === '' || strpos(end($lines), '_footer.phtml') !== false)) {
            array_pop($lines);
        }
        $body = implode("\n", $lines);

        // drop prev/next nav chrome so it does not pollute the index
        $body = preg_replace('#<div class="nav(?:header|footer)">.*?</div>#s', ' ', $body);

        preg_match_all('#<h[1-4][^>]*>(.*?)</h[1-4]>#si', $body, $hm);
        $headings = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(implode(' ', $hm[1])), ENT_QUOTES)));
        $text     = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES)));

        $ins->execute([$title, $headings, $text, $bn]);
        $n++;
    }
    $db->commit();
    $db->exec("INSERT INTO pages(pages) VALUES('optimize')");
    printf("%s: %d pages -> %s (%d KB)\n", $lang, $n, basename($dbfile), round(filesize($dbfile) / 1024));
}
