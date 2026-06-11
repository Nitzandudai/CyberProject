<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['chain-data-breach'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Capstone 02</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-capstone"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('chain-data-breach'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Overview</h2>
    <p>
        This chain demonstrates how a single SQL injection turns into a long-running,
        hard-to-trace foothold by laundering the actual malicious actions through a
        compromised user.
    </p>
    <p>Phases:</p>
    <ol>
        <li><strong>Foothold.</strong> Log in as the seeded test account
            <code>HUCKER</code> (<code>hucker123</code>) to obtain a valid
            <code>PHPSESSID</code>. In a real engagement this would be a self-registered account.</li>
        <li><strong>Database exfiltration.</strong> Reuse the session to drive a
            SQL injection vulnerability and dump the entire
            <code>users</code> table with plaintext passwords.</li>
        <li><strong>Lateral movement &amp; persistence.</strong> Pick a non-admin victim
            from the dump and log in as them. From that account, plant a stored-XSS
            payload on a product page so every future visitor - including legitimate
            admins - leaks their session cookie to the attacker.</li>
    </ol>
    <p>
        The brilliance is in the laundering: the SQL injection is performed under
        <em>HUCKER&apos;s</em> session, but the persistent XSS is planted by a totally
        different stolen account. The forensic trail in the application&apos;s logs
        implicates someone who had nothing to do with the original breach.
    </p>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Log in as <code>HUCKER</code> / <code>hucker123</code> and keep the session
            cookie - you will use it to authenticate the SQL injection in the next step.</li>
        <li>Using HUCKER&apos;s <code>PHPSESSID</code>, walk through the
            <a href="sqli-union.php">UNION SQLi</a> lab and dump the full
            <code>users</code> table.</li>
        <li>Pick any non-admin user from the dump, log in as them, then walk through the
            <a href="Stored_xss.php">Stored XSS</a> lab to plant a cookie-stealing payload
            on a product page under that stolen identity.</li>
        <li>Run <code>python scripts/Master_kill_chain.py 2</code> to execute all three
            phases unattended and confirm a stolen cookie lands in
            <code>AttackerServer/stolen_cookies.txt</code> when a second browser visits
            the product page.</li>
    </ol>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The chain begins on the login page (Phase 1 is logging in as
       <code>HUCKER</code> / <code>hucker123</code>).</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable login page</a>
</section>

<!-- 4. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the chain on your own first. Each phase corresponds to an individual lab -
            it&apos;s much more rewarding to assemble them than to read the orchestration.
        </p>

        <h3>Phase 1 - pick up a session</h3>
        <pre><code>POST /CyberProject/login.php
username=HUCKER&amp;password=hucker123&amp;login_submit=</code></pre>
        <p>
            Follow the redirect to <code>home.php</code> and capture
            <code>response.cookies['PHPSESSID']</code>.
        </p>

        <h3>Phase 2 - dump users with UNION SQLi</h3>
        <p>
            Using the captured session cookie, drive the
            <a href="sqli-union.php">UNION SQLi</a> attack against
            <code>products.php?q=...</code>. End state: a list of
            <code>(username, password)</code> tuples for every user in the database.
        </p>

        <h3>Phase 3 - lateral move &amp; stored XSS</h3>
        <p>
            Pick a non-admin user (the script takes <code>users[-1]</code> - the last
            row in the dump. Database rows are ordered by insertion time, so the admin
            account, created first when the database was seeded, sits near the top;
            taking the last row avoids accidentally using it). Log in
            as them. POST the
            <a href="Stored_xss.php">stored-XSS</a> payload to
            <code>product_view.php?id=81</code> (electric kettle, an electronics product
            which has the review form). From now on, anyone who visits the kettle page
            ships their cookie to the catcher.
        </p>

        <h3>Why this is a chain rather than just &quot;SQLi + XSS&quot;</h3>
        <ul>
            <li>The dump <em>provides</em> the credentials that make the stored XSS plant
                untraceable to the original attacker.</li>
            <li>Stored XSS persists past any session expiry, password reset, or even a
                fix to the UNION SQLi - the malicious row stays in the DB until it&apos;s
                explicitly removed.</li>
            <li>If an admin visits the kettle page, their cookie is exfiltrated too -
                privilege escalation falls out for free. Many real platforms notify
                administrators about new or flagged reviews, which means planting a
                malicious review actively draws the admin to the page rather than
                waiting passively for them to stumble across it.</li>
        </ul>

        <h3>Orchestration script</h3>
        <p>Run with: <code>python scripts/Master_kill_chain.py 2</code>.</p>
        <div class="academy-script">
            <?php
            $src = file_get_contents(__DIR__ . '/../scripts/Master_kill_chain.py');
            if ($src === false) {
                echo '<p class="academy-solution-warning">Script file could not be loaded.</p>';
            } else {
                $p1 = strpos($src, "\ndef chain_1_web_shell");
                $p2 = strpos($src, "\ndef chain_2_breach");
                $p3 = strpos($src, "\ndef chain_3_stealth");
                $pm = strpos($src, "\ndef main");
                if ($p1 === false || $p2 === false || $p3 === false || $pm === false) {
                    echo '<p class="academy-solution-warning">Script markers not found - has Master_kill_chain.py been renamed?</p>';
                } else {
                    $excerpt = substr($src, 0, $p1 + 1)
                             . "# --- chain_1_web_shell() defined here (see chain-web-shell lab) ---\n\n"
                             . substr($src, $p2 + 1, $p3 - $p2 - 1)
                             . "\n# --- chain_3_stealth() defined here (see chain-stealth-leak lab) ---\n\n"
                             . substr($src, $pm + 1);
                    highlight_string($excerpt);
                }
            }
            ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Fix the underlying UNION SQLi (see the
                <a href="sqli-union.php">SQLi UNION</a> lab) - that removes Phase 1
                entirely.</li>
            <li>Escape on render for review content and mark cookies
                <code>HttpOnly</code> (see <a href="Stored_xss.php">Stored XSS</a>).</li>
            <li>Hash passwords. Even if the UNION dump succeeds, what attackers get out
                should not be directly usable.</li>
            <li>Add a strict Content Security Policy: no inline scripts, restricted
                <code>connect-src</code>. The <code>fetch()</code> to the attacker server
                would be blocked.</li>
        </ul>
    </div>
</details>

<script>
(function () {
    var details = document.getElementById('academy-solution');
    if (!details) return;
    details.addEventListener('toggle', function () {
        if (this.open && !this.dataset.confirmed) {
            var ok = confirm('Are you sure you want to see the solution?\n\nTry the lab first - that is where the learning happens.');
            if (!ok) { this.open = false; } else { this.dataset.confirmed = '1'; }
        }
    });
})();
</script>

<p style="margin-top: 28px;">
    <a href="index.php">&larr; Back to all labs</a>
</p>

<?php
academy_layout_end();
