<?php
/**
 * Smoke test. Does not boot WordPress.
 */

$root = dirname(__DIR__);
$failures = 0;

/**
 * @param string $label
 * @param bool   $ok
 */
function dlm_check($label, $ok)
{
    global $failures;
    if ($ok) {
        fwrite(STDOUT, 'PASS ' . $label . PHP_EOL);
        return;
    }
    fwrite(STDERR, 'FAIL ' . $label . PHP_EOL);
    $failures++;
}

$bootstrap = $root . DIRECTORY_SEPARATOR . 'dynamic-location-menus.php';
dlm_check('plugin bootstrap exists', is_file($bootstrap));

$composer = json_decode((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'composer.json'), true);
dlm_check(
    'composer package name',
    is_array($composer) && isset($composer['name']) && $composer['name'] === 'duppins-technology/dynamic-location-menus'
);
dlm_check(
    'composer project type',
    is_array($composer) && isset($composer['type']) && $composer['type'] === 'wordpress-plugin'
);

exit($failures > 0 ? 1 : 0);
