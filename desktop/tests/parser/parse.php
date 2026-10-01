<?php
/**
 * Test harness: feeds text to the REAL production parser (app/sgk.php → sgk_parse)
 * and prints the result as JSON. Used by tests/parser/parser-regression.test.ts.
 * Reads the text from STDIN. No database, no network.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$root = getenv('OPTIFLOW_ROOT') ?: dirname(__DIR__, 3);
require $root . '/app/sgk.php';
$metin = stream_get_contents(STDIN);
$r = sgk_parse((string) $metin);
unset($r['satirlar']);
$r['lens_design'] = sgk_lens_design($r);
echo json_encode($r, JSON_UNESCAPED_UNICODE);
