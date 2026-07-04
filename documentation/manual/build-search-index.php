<?php
/**
 * build-search-index.php — build the per-language SQLite FTS5 search indexes for
 * the TigerZF docs. Run at deploy time (the docs are static):
 *     php build-search-index.php
 * Produces <lang>/html/search-index.sqlite (git-ignored build artifacts).
 *
 * SECTION-LEVEL: each page is split at its <a name="..."></a> anchors, so a hit
 * carries the exact anchor and the search result links to page.phtml#anchor.
 * One FTS row per section: page title + section heading + section body + anchor.
 * unicode61 + remove_diacritics so Spanish accents fold. bm25() weighting is
 * applied at query time in search-lib.php.
 */
$base  = __DIR__;
$langs = ['en', 'es'];
$skip  = ['_header.phtml', '_footer.phtml', 'search.phtml', 'index.phtml'];

function plain(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
}

foreach ($langs as $lang) {
    $dir = "$base/$lang/html";
    if (!is_dir($dir)) { echo "$lang: no html dir\n"; continue; }

    $dbfile = "$dir/search-index.sqlite";
    @unlink($dbfile);
    $db = new PDO("sqlite:$dbfile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF;');
    $db->exec("CREATE VIRTUAL TABLE pages USING fts5(title, heading, body, url UNINDEXED, anchor UNINDEXED, tokenize='unicode61 remove_diacritics 2')");
    $ins = $db->prepare('INSERT INTO pages(title,heading,body,url,anchor) VALUES(?,?,?,?,?)');
    $db->beginTransaction();

    $pages = 0; $rows = 0;
    foreach (glob("$dir/*.phtml") as $f) {
        $bn = basename($f);
        if (in_array($bn, $skip, true)) continue;

        // strip the shell wrapper (first line carries $title, last line = footer include)
        $lines = explode("\n", file_get_contents($f));
        $first = array_shift($lines) ?? '';
        $ptitle = preg_match("/title\\s*=\\s*'(.*?)';/", $first, $m) ? stripcslashes($m[1]) : $bn;
        while ($lines && (trim(end($lines)) === '' || strpos(end($lines), '_footer.phtml') !== false)) {
            array_pop($lines);
        }
        $body = implode("\n", $lines);
        $body = preg_replace('#<div class="nav(?:header|footer)">.*?</div>#s', ' ', $body);

        // split into sections at each named anchor
        preg_match_all('/<a\s+name="([^"]+)"\s*>\s*<\/a>/i', $body, $am, PREG_OFFSET_CAPTURE);
        $count = count($am[0]);
        $sections = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $am[0][$i][1];
            $end   = ($i + 1 < $count) ? $am[0][$i + 1][1] : strlen($body);
            $chunk = substr($body, $start, $end - $start);
            $heading = preg_match('#<h[1-6][^>]*>(.*?)</h[1-6]>#si', $chunk, $hm) ? plain($hm[1]) : '';
            $text    = plain($chunk);
            if ($text !== '') $sections[] = [$am[1][$i][0], $heading, $text];
        }
        if (!$sections) {                      // no anchors -> index whole page at the top
            $text = plain($body);
            if ($text !== '') $sections[] = ['', '', $text];
        }
        foreach ($sections as [$anchor, $heading, $text]) {
            $ins->execute([$ptitle, $heading, $text, $bn, $anchor]);
            $rows++;
        }
        $pages++;
    }
    $db->commit();
    $db->exec("INSERT INTO pages(pages) VALUES('optimize')");
    printf("%s: %d pages -> %d sections -> %s (%d KB)\n", $lang, $pages, $rows, basename($dbfile), round(filesize($dbfile) / 1024));
}
