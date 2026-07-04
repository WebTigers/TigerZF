<?php
/**
 * search-lib.php — shared FTS5 query for the docs search. Language-agnostic:
 * queries the search-index.sqlite in its OWN directory, so it searches the
 * language it is deployed under. Used by search.php (JSON) and search.phtml (page).
 */
function tzf_search(string $dir, string $q, int $limit = 8): array
{
    // Build a safe FTS5 MATCH expression: tokenize, quote every token, and
    // prefix-match the final token (as-you-type). No raw user text reaches FTS5.
    if ($q === '' || !preg_match_all('/[\p{L}\p{N}_]+/u', $q, $mm)) {
        return [];
    }
    $tokens = $mm[0];
    $last   = array_pop($tokens);
    $parts  = array_map(static fn($t) => '"' . $t . '"', $tokens);
    $parts[] = '"' . $last . '"*';
    $match   = implode(' ', $parts);

    $dbfile = $dir . '/search-index.sqlite';
    if (!is_file($dbfile)) {
        return [];
    }
    try {
        $db = new PDO('sqlite:' . $dbfile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $st = $db->prepare(
            "SELECT url, title, snippet(pages, 2, '%%H%%', '%%/H%%', '…', 12) AS snippet
             FROM pages
             WHERE pages MATCH :m
             ORDER BY bm25(pages, 10.0, 5.0, 1.0)
             LIMIT :lim"
        );
        $st->bindValue(':m', $match, PDO::PARAM_STR);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    // escape content, then turn the safe %%H%% markers into <mark> highlights
    foreach ($rows as &$r) {
        $r['title_html']   = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
        $r['snippet_html'] = str_replace(
            ['%%H%%', '%%/H%%'],
            ['<mark>', '</mark>'],
            htmlspecialchars($r['snippet'], ENT_QUOTES, 'UTF-8')
        );
    }
    unset($r);
    return $rows;
}
