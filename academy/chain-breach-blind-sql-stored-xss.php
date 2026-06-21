<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$slug    = 'chain-breach-blind-sql-stored-xss';
$lesson  = $lessons[$slug];

academy_layout_start($lesson['title'], $slug);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Capstone 01</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-capstone"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('chain-breach-blind-sql-stored-xss'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Overview</h2>
    <p>
        Capstones chain several individual labs into a complete attack scenario. This one
        models a typical opportunistic intrusion:
        <strong>discover users &rarr; gain a foothold &rarr; steal a secret &rarr; plant persistent XSS</strong>.
        The access phase has three escalating methods - cheap password spray, intensive
        brute force, then broken password reset - falling back to the next if the previous
        one fails, so the chain succeeds on a wide range of database states.
    </p>
    <p>The phases:</p>
    <ol>
        <li><strong>Discovery.</strong> Use the user-enumeration oracle on
            <code>login.php?view=register</code> to find which names from
            <code>scripts/usernames.txt</code> are real accounts. Spray the top common
            passwords against each as you go (a &quot;cheap pass&quot;).</li>
        <li><strong>Access.</strong> If the cheap pass found a working credential, you can stop.
            Otherwise launch <em>intensive</em> brute force with
            <code>scripts/passwords.txt</code>. If that also fails, fall back to the
            <a href="broken-password-reset.php">broken password reset</a> - which always
            succeeds because it has no token.</li>
        <li><strong>Exfiltration.</strong> Log in as the compromised user and drive
            <a href="sqli-blind.php">blind SQLi</a> against the cart coupon endpoint to
            leak the VIP coupon from <code>internal.db</code>.</li>
        <li><strong>Persistence.</strong> With the same account, plant a
            <a href="Stored_xss.php">stored XSS</a> payload on a product review page so
            future visitors leak their session cookies to the attacker.</li>
    </ol>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Run the discovery phase manually for at least one username from
            <code>usernames.txt</code> and confirm the &quot;Username already exists&quot;
            oracle works.</li>
        <li>Crack at least one account either via brute force or via the broken password
            reset.</li>
        <li>
            <strong>Exfiltration goal:</strong> as the compromised user, use the
            <a href="sqli-blind.php">Blind SQLi</a> technique against the cart coupon
            endpoint to leak the hidden VIP coupon code from <code>internal.db</code>.
            Discovering which table and column hold it is part of the challenge.
        </li>
        <li>
            <strong>Persistence goal:</strong> with the same account, plant a stored-XSS
            payload through a product review form and confirm a stolen cookie lands in
            <code>AttackerServer/stolen_cookies.txt</code> when another browser visits
            that page.
        </li>
    </ol>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>Login page opens in a new tab. The chain begins by enumerating users via the
       register form on this page.</p>
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

        <h3>Phase 1 - discovery</h3>
        <p>
            See the
            <a href="user-enum-bruteforce.php">User Enumeration &amp; Brute Force</a> lab
            for details. The chain calls <code>enum_and_brute.run_attack()</code>, which
            returns both the cracked accounts (if any) and the full list of discovered
            usernames.
        </p>

        <h3>Phase 2 - access</h3>
        <p>
            If Phase 1 already cracked an account, skip ahead. Otherwise:
        </p>
        <ol>
            <li>For each discovered username, call
                <code>Broken_Password_Reset.try_brute_force()</code> against
                <code>scripts/passwords.txt</code>.</li>
            <li>If no password works, call <code>Broken_Password_Reset.run_reset_password()</code>
                - that POST always succeeds (no token, no email check). See the
                <a href="broken-password-reset.php">Broken Password Reset</a> lab.</li>
        </ol>

        <h3>Phase 3 - blind SQLi</h3>
        <p>
            Log in as the compromised user and capture <code>PHPSESSID</code>. Reuse that
            session against <code>cart.php</code>&apos;s coupon input - the same
            time-based blind technique from the
            <a href="sqli-blind.php">Blind SQL Injection</a> lab:
        </p>
        <pre><code>coupon_code=' OR (CASE WHEN
   UPPER(SUBSTR((SELECT encrypted_code FROM CUPONS LIMIT 1), &lt;pos&gt;, 1)) = '&lt;char&gt;'
   THEN randomblob(250000000) ELSE 1 END) --</code></pre>
        <p>
            The chain injects the stolen session into
            <code>SQLi_Blind.COOKIES['PHPSESSID']</code>, measures a baseline response
            time, discovers the target table, then leaks
            <code>encrypted_code</code> character by character.
        </p>

        <h3>Phase 4 - stored XSS</h3>
        <p>
            Still authenticated as the same user, POST the
            <a href="Stored_xss.php">stored-XSS</a> payload to
            <code>product_view.php?id=81</code> (electric kettle). The chain calls
            <code>stored_xss.perform_stored_xss(username=..., password=...)</code>.
            From now on, anyone who visits the kettle page ships their cookie to the
            catcher.
        </p>

        <h3>Why this is a chain rather than three separate labs</h3>
        <ul>
            <li>Enumeration and access give you a real account - blind SQLi and stored XSS
                both require an authenticated session on this app.</li>
            <li>The blind SQLi leak proves you can read secrets even when the app hides
                query results; stored XSS proves you can turn that foothold into ongoing
                harm against other users.</li>
            <li>Stored XSS persists past session expiry or a password change - the malicious
                review row stays in the DB until it is explicitly removed.</li>
        </ul>

        <h3>Orchestration script</h3>
        <p>
            Phases 1-4 are wired together by
            <code>chain_1_breach_blind_sql_and_stored_xss()</code>. Run
            <code>python scripts/Master_kill_chain.py 1</code> to execute the chain
            unattended.
        </p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                Excerpt of <code>chain_1_breach_blind_sql_and_stored_xss()</code> from the
                master script.
            </p>
            <div class="academy-script">
                <?php
                $src = file_get_contents(__DIR__ . '/../scripts/Master_kill_chain.py');
                if ($src === false) {
                    echo '<p class="academy-solution-warning">Script file could not be loaded.</p>';
                } else {
                    $p2 = strpos($src, "\ndef chain_2_web_shell");
                    $pm = strpos($src, "\ndef main");
                    if ($p2 === false || $pm === false) {
                        echo '<p class="academy-solution-warning">Script markers not found - has Master_kill_chain.py been renamed?</p>';
                    } else {
                        $excerpt = substr($src, 0, $p2)
                                 . "\n\n# --- chain_2_web_shell() defined in chain-web-shell lab ---"
                                 . "\n# --- chain_3_stealth() defined in chain-stealth-leak lab ---\n\n"
                                 . substr($src, $pm + 1);
                        highlight_string($excerpt);
                    }
                }
                ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>The chain&apos;s discovery and access phases are neutralised by the fixes in
                the <a href="user-enum-bruteforce.php">enumeration</a> and
                <a href="broken-password-reset.php">password-reset</a> labs.</li>
            <li>Fix blind SQLi on <code>cart.php</code> with prepared statements and strict
                coupon validation (see the <a href="sqli-blind.php">Blind SQLi</a> lab).</li>
            <li>Escape review content on render and mark cookies
                <code>HttpOnly</code> (see <a href="Stored_xss.php">Stored XSS</a>).</li>
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
