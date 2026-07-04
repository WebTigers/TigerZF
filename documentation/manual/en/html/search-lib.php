<?php
/**
 * search-lib.php — shared FTS5 query for the docs search. Language-agnostic:
 * queries the search-index.sqlite in its OWN directory. Used by search.php
 * (JSON) and search.phtml (page).
 *
 * The index is SECTION-level, so each hit carries a page + section anchor. We
 * dedupe to the best-ranked section per page and return a page.phtml#anchor
 * link plus a "Page > Section" breadcrumb title.
 */
function tzf_search(string $dir, string $q, int $limit = 8): array
{
    // Safe FTS5 MATCH: tokenize, quote each token, prefix-match the last.
    if ($q === '' || !preg_match_all('/[\p{L}\p{N}_]+/u', $q, $mm)) {
        return [];
    }
    $tokens  = $mm[0];
    $last    = array_pop($tokens);
    $parts   = array_map(static fn($t) => '"' . $t . '"', $tokens);
    $parts[] = '"' . $last . '"*';
    $match   = implode(' ', $parts);

    $dbfile = $dir . '/search-index.sqlite';
    if (!is_file($dbfile)) {
        return [];
    }
    try {
        $db = new PDO('sqlite:' . $dbfile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // fetch extra rows so we can keep the best section per page after dedupe
        $st = $db->prepare(
            "SELECT url, anchor, title, heading,
                    snippet(pages, 2, '%%H%%', '%%/H%%', '…', 12) AS snippet
             FROM pages
             WHERE pages MATCH :m
             ORDER BY bm25(pages, 8.0, 6.0, 1.0)
             LIMIT :lim"
        );
        $st->bindValue(':m', $match, PDO::PARAM_STR);
        $st->bindValue(':lim', $limit * 6, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    $seen = [];
    foreach ($rows as $r) {
        if (isset($seen[$r['url']])) {
            continue;                          // keep only the top-ranked section per page
        }
        $seen[$r['url']] = true;

        $href  = $r['url'] . ($r['anchor'] !== '' ? '#' . $r['anchor'] : '');
        $crumb = $r['title'];
        if ($r['heading'] !== '' && $r['heading'] !== $r['title']) {
            $crumb .= ' › ' . $r['heading'];
        }
        $out[] = [
            'url'          => $href,
            'title_html'   => htmlspecialchars($crumb, ENT_QUOTES, 'UTF-8'),
            'snippet_html' => str_replace(
                ['%%H%%', '%%/H%%'],
                ['<mark>', '</mark>'],
                htmlspecialchars($r['snippet'], ENT_QUOTES, 'UTF-8')
            ),
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}
