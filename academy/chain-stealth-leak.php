<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['chain-stealth-leak'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Capstone 03</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-capstone"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('chain-stealth-leak'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Overview</h2>
    <p>
        Most of the other chains generate a lot of traffic and leave very obvious tracks
        (registration spam, password changes, planted reviews). This one shows the
        opposite philosophy: <strong>get in, take exactly one secret, leave nothing
        behind</strong>.
    </p>
    <p>Phases:</p>
    <ol>
        <li><strong>Quiet authentication.</strong> Bypass the login form with classic
            error-based SQLi (<code>' OR 1=1 -- </code>). No new accounts, no password
            resets - just one POST and a stolen session.</li>
        <li><strong>Targeted exfiltration.</strong> Reuse that session to drive
            <em>time-based blind</em> SQLi against the cart&apos;s coupon endpoint, where
            UNION is filtered. Walk the alphabet, character by character, until you have
            the VIP coupon code in <code>internal.db</code>.</li>
    </ol>
    <p>
        End-to-end footprint: two endpoints touched, the user table never mutated, no
        files written, no reviews planted. If the application has any logging at all,
        the only suspicious line is a single 302 redirect at login.
    </p>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Reproduce Phase 1 by manually performing the
            <a href="sqli-login.php">login-bypass SQLi</a> and capturing the
            <code>PHPSESSID</code>.</li>
        <li>With that session, perform the <a href="sqli-blind.php">blind SQLi</a>
            against <code>cart.php</code>&apos;s coupon input and extract
            <code>encrypted_code</code> from the <code>CUPONS</code> table.</li>
        <li>
            <strong>Goal:</strong> recover the value of the seeded VIP coupon - a freshly
            seeded DB returns <code>ANAN-VIP-2026</code>.
        </li>
        <li>Run <code>python scripts/Master_kill_chain.py 3</code> end to end and watch
            the chain extract the coupon unattended.</li>
    </ol>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The chain begins on the login page (the bypass is Phase 1).</p>
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

        <h3>Phase 1 - login bypass</h3>
        <pre><code>POST /CyberProject/login.php
username=' OR 1=1 -- &amp;password=anything&amp;login_submit=</code></pre>
        <p>
            Capture the <code>PHPSESSID</code> from the response&apos;s <code>Set-Cookie</code>
            header. Full walkthrough in the
            <a href="sqli-login.php">SQLi: Login Bypass</a> lab.
        </p>

        <h3>Phase 2 - blind SQLi against the coupon endpoint</h3>
        <p>
            Reuse the stolen session against <code>cart.php</code>. Because the coupon
            endpoint denylists the word <code>UNION</code>, we go time-based:
        </p>
        <pre><code>coupon_code=' OR (CASE WHEN
   UPPER(SUBSTR((SELECT encrypted_code FROM CUPONS LIMIT 1), &lt;pos&gt;, 1)) = '&lt;char&gt;'
   THEN randomblob(250000000) ELSE 1 END) --</code></pre>
        <p>
            One probe per <code>(pos, char)</code> combination; when the server hangs for
            a few seconds you have the next letter. Full walkthrough in the
            <a href="sqli-blind.php">Blind SQL Injection</a> lab.
        </p>

        <h3>Why this is &quot;stealth&quot;</h3>
        <ul>
            <li><strong>No writes</strong> - only <code>SELECT</code>s. Nothing in the DB
                changes.</li>
            <li><strong>No new accounts</strong> - uses the existing login form once.</li>
            <li><strong>Single target</strong> - the chain knows exactly what to leak
                (the VIP coupon) and stops as soon as the string ends. No directory
                enumeration, no schema dump.</li>
            <li><strong>Hostile detection cost</strong> - the only signal at the
                application layer is request latency on
                <code>POST /cart.php</code> with an oddly-shaped coupon value. Without
                request-body logging, the blue team has very little to work with.</li>
        </ul>

        <h3>Orchestration script</h3>
        <p>Run with: <code>python scripts/Master_kill_chain.py 3</code>. The chain glues
            the two scripts together - note how it injects the stolen session straight
            into <code>SQLi_Blind.COOKIES['PHPSESSID']</code> before running the leak.</p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/Master_kill_chain.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Use prepared statements on <em>both</em> endpoints. The denylist on
                <code>cart.php</code> is security theatre - it stops UNION but not
                time-based.</li>
            <li>Strictly validate coupon codes:
                <code>^[A-Z0-9-]{6,32}$</code>.</li>
            <li>Hash passwords. Even if the login bypass succeeds, it should give the
                attacker a session, not the contents of the row.</li>
            <li>Log request latency and content length per endpoint. Time-based blind has
                a very recognisable signature: thousands of POSTs to one endpoint with
                response times alternating between &quot;fast&quot; and &quot;suspiciously
                slow&quot;.</li>
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
