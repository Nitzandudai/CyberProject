<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';

$reset_flash = isset($_GET['reset']) && $_GET['reset'] === 'ok';

$labs     = array_filter($lessons, fn($l) => $l['category'] !== 'Capstone');
$capstones = array_filter($lessons, fn($l) => $l['category'] === 'Capstone');

function academy_difficulty_badge(string $difficulty): string {
    $cls = match (strtolower($difficulty)) {
        'easy'     => 'is-easy',
        'medium'   => 'is-medium',
        'hard'     => 'is-hard',
        'capstone' => 'is-capstone',
        default    => 'is-soon',
    };
    return '<span class="academy-badge ' . $cls . '">' . htmlspecialchars($difficulty) . '</span>';
}

function academy_render_card(string $slug, array $lesson): string {
    $isReady = $lesson['status'] === 'ready';
    $href = $isReady ? htmlspecialchars($slug) . '.php' : '#';
    $cardCls = 'academy-card' . ($isReady ? '' : ' is-disabled');
    $tag = $isReady ? 'a' : 'div';
    $hrefAttr = $isReady ? ' href="' . $href . '"' : '';
    $statusBadge = $isReady
        ? '<span class="academy-badge is-ready">Ready</span>'
        : '<span class="academy-badge is-soon">Coming soon</span>';

    return sprintf(
        '<%1$s class="%2$s"%3$s>
            <div class="academy-card-head">
                <span class="academy-card-cat">%4$s</span>
                %5$s
            </div>
            <h3 class="academy-card-title">%6$s</h3>
            <p class="academy-card-desc">%7$s</p>
            <div class="academy-card-foot">
                %8$s
                <span>%9$s</span>
            </div>
        </%1$s>',
        $tag,
        $cardCls,
        $hrefAttr,
        htmlspecialchars($lesson['category']),
        $statusBadge,
        htmlspecialchars($lesson['title']),
        htmlspecialchars($lesson['short']),
        academy_difficulty_badge($lesson['difficulty']),
        $isReady ? 'Open lesson &rarr;' : '&nbsp;'
    );
}

academy_layout_start('All labs');
?>

<?php if ($reset_flash): ?>
    <div class="academy-flash">Lab environment reset. Databases restored from seed.</div>
<?php endif; ?>

<section class="academy-hero">
    <h1>Cyber Academy</h1>
    <p>
        Hands-on labs for the vulnerabilities present in our supermarket demo site.
        Each lesson explains the bug, asks you to exploit it on the live target,
        and then reveals our reference solution and automation script.
    </p>
</section>

<h2 class="academy-section-title">Individual labs</h2>
<div class="academy-grid">
    <?php foreach ($labs as $slug => $lesson): ?>
        <?= academy_render_card($slug, $lesson) ?>
    <?php endforeach; ?>
</div>

<h2 class="academy-section-title">Capstone chains</h2>
<div class="academy-grid">
    <?php foreach ($capstones as $slug => $lesson): ?>
        <?= academy_render_card($slug, $lesson) ?>
    <?php endforeach; ?>
</div>

<form class="academy-reset-form" method="post" action="reset.php"
      onsubmit="return confirm('Reset databases to their seed copy? Any data created during labs will be lost.');">
    <div>
        <strong>Lab environment dirty?</strong>
        <div style="color:#475569; font-size:0.9rem;">
            Restore <code>app.db</code> and <code>internal.db</code> from the pristine seed copy
            taken when the academy was installed.
        </div>
    </div>
    <button type="submit">Reset databases</button>
</form>

<?php
academy_layout_end();
