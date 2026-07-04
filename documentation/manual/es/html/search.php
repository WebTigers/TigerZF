<?php
/* JSON full-text search endpoint. Searches this directory's language index. */
require __DIR__ . '/search-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q     = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 8;

$results = tzf_search(__DIR__, $q, $limit);
$out = array_map(static fn($r) => [
    'url'     => $r['url'],
    'title'   => $r['title_html'],
    'snippet' => $r['snippet_html'],
], $results);

echo json_encode(['q' => $q, 'results' => $out], JSON_UNESCAPED_UNICODE);
