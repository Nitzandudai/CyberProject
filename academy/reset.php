<?php
/**
 * Restore the vulnerable site's databases from the seed copies that were
 * taken when the academy was scaffolded. POST-only so it can't be triggered
 * by an accidental link or a stray <img> tag.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$seedDir   = __DIR__ . '/seed';
$projectDir = dirname(__DIR__);

$pairs = [
    $seedDir . '/app.db.seed'      => $projectDir . '/app.db',
    $seedDir . '/internal.db.seed' => $projectDir . '/internal.db',
];

$errors = [];
foreach ($pairs as $src => $dst) {
    if (!is_file($src)) {
        $errors[] = "Missing seed file: " . basename($src);
        continue;
    }
    if (!@copy($src, $dst)) {
        $errors[] = "Failed to restore " . basename($dst);
    }
}

if ($errors) {
    http_response_code(500);
    require __DIR__ . '/_layout.php';
    academy_layout_start('Reset failed');
    echo '<div class="academy-block">';
    echo '<h2>Reset failed</h2><ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul>';
    echo '<p><a href="index.php">Back to all labs</a></p>';
    echo '</div>';
    academy_layout_end();
    exit;
}

$return = $_POST['return'] ?? '';
$lessons = require __DIR__ . '/lessons.php';
$redirect = 'index.php';
if ($return !== '' && isset($lessons[$return])) {
    $redirect = $return . '.php?reset=ok';
}

header('Location: ' . $redirect);
exit;
