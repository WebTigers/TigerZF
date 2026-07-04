<?php
/*
 * i18n for the TigerZF manual shell — ONE file per language directory.
 *
 * The shell partials (_header.phtml, _footer.phtml), docs.js and docs.css are
 * language-AGNOSTIC: they pull every piece of visible text from here, so those
 * files stay byte-identical across every /docs/<lang>/ directory.
 *
 *   $LANG  — this directory's language code.
 *   $LANGS — the language menu, each language written in its OWN name (autonym).
 *            It is therefore IDENTICAL in every language and never needs
 *            translating. Adding a language = create its dir + add one line here
 *            (mirrored in each _i18n.php).
 *   $T     — the UI strings that DO differ per language (translate these).
 */
$LANG  = 'en';
$LANGS = [
    'en' => 'English',
    'es' => 'Español',
];
$T = [
    'manual'      => 'TigerZF Manual',
    'search_ph'   => 'Search the docs…',
    'search_aria' => 'Search the documentation',
    'filter_ph'   => 'Filter pages…',
    'filter_aria' => 'Filter navigation',
    'toggle_nav'  => 'Toggle navigation',
    'language'    => 'Language',
    'onthispage'  => 'On this page',
];
