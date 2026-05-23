<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
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
            <code>PHPSESSID</code>. In a real engagement this would be a phished credential
            or an account created via the registration form.</li>
        <li><strong>Database exfiltration.</strong> Reuse the session to drive a
            UNION-based SQL injection against the product search and dump the entire
            <code>users</code> table with plaintext passwords.</li>
        <li><strong>Lateral movement &amp; persistence.</strong> Pick a non-admin victim
            from the dump (the script chooses the <em>last</em> row to avoid the
            <code>admin</code> at the top) and log in as them. From that account, plant a
            stored-XSS payload on the electric kettle product page so every future visitor
            - including legitimate admins - leaks their session cookie to the attacker.</li>
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
        <li>Confirm <code>HUCKER</code> / <code>hucker123</code> exists in the seeded
            <code>app.db</code> by logging in manually.</li>
        <li>Reproduce Phase 2 by walking through the
            <a href="sqli-union.php">UNION SQLi</a> lab using HUCKER&apos;s session.</li>
        <li>Pick a non-admin user from the dump and replicate Phase 3 from the
            <a href="xss-stored.php">Stored XSS</a> lab using their credentials.</li>
        <li>Run <code>python scripts/Master_kill_chain.py 2</code> end to end and
            confirm a stolen cookie lands in
            <code>AttackerServer/stolen_cookies.txt</code> when a second browser visits
            <code>product_view.php?id=81</code>.</li>
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

        <h3>Phase 0 - pick up a session</h3>
        <pre><code>POST /CyberProject/login.php
username=HUCKER&amp;password=hucker123&amp;login_submit=</code></pre>
        <p>
            Follow the redirect to <code>home.php</code> and capture
            <code>response.cookies['PHPSESSID']</code>.
        </p>

        <h3>Phase 1 - dump users with UNION SQLi</h3>
        <p>
            Using the captured session cookie, drive the
            <a href="sqli-union.php">UNION SQLi</a> attack against
            <code>products.php?q=...</code>. End state: a list of
            <code>(username, password)</code> tuples for every user in the database.
        </p>

        <h3>Phase 2 - lateral move &amp; stored XSS</h3>
        <p>
            Pick a non-admin user (the script just takes <code>users[-1]</code>). Log in
            as them. POST the
            <a href="xss-stored.php">stored-XSS</a> payload to
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
                privilege escalation falls out for free.</li>
        </ul>

        <h3>Orchestration script</h3>
        <p>Run with: <code>python scripts/Master_kill_chain.py 2</code>.</p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/Master_kill_chain.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Fix the underlying UNION SQLi (see the
                <a href="sqli-union.php">SQLi UNION</a> lab) - that removes Phase 1
                entirely.</li>
            <li>Escape on render for review content and mark cookies
                <code>HttpOnly</code> (see <a href="xss-stored.php">Stored XSS</a>).</li>
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
