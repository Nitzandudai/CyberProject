<?php
/**
 * Shared shell for every academy page.
 *
 * Usage from a lesson/index page:
 *
 *   $page_title = 'My Lesson';
 *   require __DIR__ . '/_layout.php';
 *   academy_layout_start($page_title, $slug);  // optional slug enables per-lab DB reset
 *   // ... page body HTML ...
 *   academy_layout_end();
 *
 * The academy is intentionally stateless: no session_start(), no auth.
 * It only renders static teaching content and links into the vulnerable site.
 */

if (!function_exists('academy_layout_start')) {
    function academy_layout_start(string $title = 'Cyber Academy', ?string $slug = null): void {
        $GLOBALS['academy_page_slug'] = $slug;
        ini_set('highlight.default', '#e2e8f0'); // light text (also used for non-PHP files)
        ini_set('highlight.html',    '#e2e8f0'); // content outside <?php tags
        ini_set('highlight.string',  '#a5d6ff'); // string literals
        ini_set('highlight.comment', '#8b949e'); // comments
        ini_set('highlight.keyword', '#ff7b72'); // keywords / language tokens
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?> &middot; Cyber Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="_styles.css">
</head>
<body class="academy-body">
    <header class="academy-header">
        <div class="academy-header-inner">
            <a class="academy-brand" href="index.php">
                <span class="academy-brand-mark">CA</span>
                <span class="academy-brand-text">Cyber Academy</span>
            </a>
            <nav class="academy-nav">
                <a href="index.php">All labs</a>
                <a href="../home.php" target="_blank" rel="noopener">Target site</a>
            </nav>
        </div>
    </header>
    <main class="academy-main">
        <?php
    }

    /**
     * Render the "Prerequisites" / "Used in" chip strip for cross-linking
     * between individual labs and capstone chains.
     *
     * On a capstone page  -> shows its prerequisites (from lessons.php).
     * On an individual lab -> shows the capstones that list this lab as a
     *                         prerequisite (computed by reverse-scanning,
     *                         so the metadata only needs to be authored
     *                         in one direction).
     * Renders nothing if there are no related labs.
     */
    function academy_render_related_labs(string $current_slug): void {
        $lessons = require_once __DIR__ . '/lessons.php';
        if (!isset($lessons[$current_slug])) {
            return;
        }
        $current = $lessons[$current_slug];

        $is_capstone = ($current['category'] ?? '') === 'Capstone';

        if ($is_capstone) {
            $label    = 'Prerequisites';
            $intro    = 'Individual labs combined in this chain.';
            $slugs    = $current['prerequisites'] ?? [];
        } else {
            $label    = 'Used in';
            $intro    = 'Capstone chains that build on this lab.';
            $slugs    = [];
            foreach ($lessons as $slug => $other) {
                if (($other['category'] ?? '') !== 'Capstone') continue;
                if (in_array($current_slug, $other['prerequisites'] ?? [], true)) {
                    $slugs[] = $slug;
                }
            }
        }

        $slugs = array_values(array_filter($slugs, fn($s) => isset($lessons[$s])));
        if (!$slugs) {
            return;
        }
        ?>
        <aside class="academy-related-strip" aria-label="<?= htmlspecialchars($label) ?>">
            <div class="academy-related-head">
                <span class="academy-related-label"><?= htmlspecialchars($label) ?></span>
                <span class="academy-related-intro"><?= htmlspecialchars($intro) ?></span>
            </div>
            <div class="academy-related-chips">
                <?php foreach ($slugs as $slug):
                    $rel = $lessons[$slug];
                    $href = htmlspecialchars($slug) . '.php';
                ?>
                    <a class="academy-related-chip" href="<?= $href ?>">
                        <span class="academy-related-chip-cat"><?= htmlspecialchars($rel['category']) ?></span>
                        <span class="academy-related-chip-title"><?= htmlspecialchars($rel['title']) ?></span>
                        <span class="academy-related-chip-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
    }

    /**
     * Reset banner + form for labs that mutate app.db / internal.db.
     * Enabled per lesson via needs_db_reset in lessons.php.
     */
    function academy_render_db_reset(string $current_slug): void {
        $lessons = require __DIR__ . '/lessons.php';
        if (!isset($lessons[$current_slug]) || empty($lessons[$current_slug]['needs_db_reset'])) {
            return;
        }
        $reset_flash = isset($_GET['reset']) && $_GET['reset'] === 'ok';
        if ($reset_flash): ?>
            <div class="academy-flash">Lab environment reset. Databases restored from seed.</div>
        <?php endif; ?>
        <form class="academy-reset-form" method="post" action="reset.php"
              onsubmit="return confirm('Reset databases to their seed copy? Any data created during labs will be lost.');">
            <input type="hidden" name="return" value="<?= htmlspecialchars($current_slug) ?>">
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
    }

    function academy_layout_end(): void {
        if (!empty($GLOBALS['academy_page_slug'])) {
            academy_render_db_reset($GLOBALS['academy_page_slug']);
        }
        unset($GLOBALS['academy_page_slug']);
        ?>
    </main>
    <footer class="academy-footer">
        <div class="academy-footer-inner">
            <span>Cyber Academy &middot; Educational lab environment</span>
            <span>For learning only. Do not run these techniques against systems you do not own.</span>
        </div>
    </footer>
    <script>
    (function () {
        var blocks = document.querySelectorAll('.academy-script');
        if (!blocks.length || !navigator.clipboard) return;

        function extractText(block) {
            var clone = block.cloneNode(true);
            clone.querySelectorAll('.academy-script-copy').forEach(function (b) { b.remove(); });
            clone.querySelectorAll('br').forEach(function (br) {
                br.replaceWith(document.createTextNode('\n'));
            });
            return clone.textContent;
        }

        function flash(btn, label, cls) {
            var prev = btn.textContent;
            btn.textContent = label;
            btn.classList.add(cls);
            setTimeout(function () {
                btn.textContent = 'Copy';
                btn.classList.remove(cls);
            }, 1500);
        }

        blocks.forEach(function (block) {
            if (block.querySelector('.academy-script-copy')) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'academy-script-copy';
            btn.textContent = 'Copy';
            btn.setAttribute('aria-label', 'Copy script to clipboard');
            block.appendChild(btn);
            btn.addEventListener('click', function () {
                navigator.clipboard.writeText(extractText(block))
                    .then(function () { flash(btn, 'Copied!', 'is-copied'); })
                    .catch(function () { flash(btn, 'Failed',  'is-failed'); });
            });
        });
    })();
    </script>
</body>
</html>
        <?php
    }
}
