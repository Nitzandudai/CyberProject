<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['user-enum-bruteforce'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 07</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-easy"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('user-enum-bruteforce'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        Authentication has two halves: finding a valid username and guessing the matching
        password. Most public attacks against credentials start with
        <strong>user enumeration</strong> - turning a 2D guessing problem
        (any username, any password) into a 1D problem (known username, guess password).
        Once you have a confirmed list of usernames, brute force against an endpoint that
        lacks rate limiting, CAPTCHAs, or account lockout will eventually pop any account
        with a weak password.
    </p>
    <p>
        Enumeration works whenever the application leaks <em>which usernames exist</em>
        through any observable side channel: a different error message, a different HTTP
        status code, a measurable timing gap, a redirect that only happens in one case.
        Anywhere the server has to look the user up - login, registration, password reset,
        even an &quot;already invited?&quot; check - is a candidate.
    </p>
</section>

<!-- 2. TASK 1 - LOCATE THE ORACLE -->
<section class="academy-block">
    <h2>2. Task 1 - locate the oracle</h2>
    <p>
        Probe the application for a side-channel that distinguishes existing usernames
        from non-existent ones. Try the same input across login, registration, and
        forgot-password and compare the responses byte by byte: any difference - a
        different error string, a different status, a different redirect - is the oracle
        you need.
    </p>
    <details class="academy-hint">
        <summary>Reveal the oracle</summary>
        <p>
            It is on the registration form. When you POST a new user, the server uses a
            different error message for &quot;name taken&quot; than for any other
            failure:
        </p>
        <pre><code>// login.php (register branch)
$check_stmt = $db-&gt;prepare("SELECT COUNT(*) FROM users WHERE username = :username");
$check_stmt-&gt;execute([':username' -&gt; $reg_username]);

if ($check_stmt-&gt;fetchColumn() &gt; 0) {
    $error = "Username already exists in the system"; // &lt;-- oracle
} else {
    // insert new row
}</code></pre>
        <p>
            Any name that triggers that exact string is a confirmed existing account -
            no email confirmation, no CAPTCHA, no rate limit between attempts. Pair that
            with a username wordlist and you have a sweep.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - SWEEP AND BRUTE FORCE -->
<section class="academy-block">
    <h2>3. Task 2 - sweep and brute force</h2>
    <ol>
        <li>Sweep <code>scripts/usernames.txt</code> (shown below) against the oracle you
            found in Task 1 and build the list of real accounts.
            <br><br>
            <strong>Tip:</strong> to see the exact field names the registration form sends,
            open <strong>DevTools</strong> (<kbd>F12</kbd>) &rarr; <strong>Network</strong>
            tab, submit the form once, click the request, and open the
            <strong>Payload</strong> tab. Those field names are what your script needs to
            match.
        </li>
        <li>Brute-force each one with the ten-password list shown below (the script&apos;s
            hardcoded default). The login endpoint has no rate limiting and no account
            lockout, and a successful login returns a 302 redirect to
            <code>home.php</code> - that&apos;s your success signal.</li>
        <li><strong>Goal:</strong> recover at least one <code>(username, password)</code>
            pair that lets you log in successfully.</li>
    </ol>

    <p style="margin-top: 18px;"><strong>Your toolbox.</strong></p>
    <details class="academy-hint">
        <summary><code>scripts/usernames.txt</code> (expand to view)</summary>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/usernames.txt'); ?>
        </div>
    </details>
    <p>The script&apos;s hardcoded common-password list (10 entries):</p>
    <pre><code>123456, admin123, 12345678, 123456789, 12345,
password, Aa123456, 1234567890, 111111, qwerty</code></pre>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>The login + registration page opens in a new tab.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable login page</a>
</section>

<!-- 5. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>Step 1 - enumerate valid usernames</h3>
        <p>
            For each candidate name, POST to <code>login.php?view=register</code> with
            a junk email and password. If the response body contains
            <code>Username already exists in the system</code>, mark the name as valid.
            A clean baseline page (fetched once at the start) gives you something to
            diff against.
        </p>
        <pre><code>POST /CyberProject/login.php?view=register

reg_username=alice
reg_email=alice@test.com
reg_password=p
register_submit=</code></pre>

        <h3>Step 2 - spray common passwords</h3>
        <p>
            For each enumerated user, POST to <code>login.php</code> with each of the
            ten passwords shown in Task 2. Detect success by
            <code>response.status_code == 302</code> and <code>Location</code> containing
            <code>home.php</code> &mdash; that is the same signal the application itself
            uses internally.
        </p>

        <h3>Why it works</h3>
        <ul>
            <li><strong>Verbose registration</strong>: the server distinguishes &quot;name
                taken&quot; from any other error.</li>
            <li><strong>No rate limiting</strong>: thousands of requests per minute are
                tolerated.</li>
            <li><strong>No lockout</strong>: failed logins are not counted per account or
                per IP.</li>
            <li><strong>Weak passwords</strong>: combined with plaintext storage, even a
                10-entry dictionary cracks at least one account on a typical seed.</li>
        </ul>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                The script wires both phases together and prints a summary at the end.
            </p>
            <div class="academy-script">
                <?php highlight_file(__DIR__ . '/../scripts/enum_and_brute.py'); ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Use a generic message: &quot;If this account does not exist, you can
                create it.&quot; - never confirm or deny existence.</li>
            <li>Move registration behind a CAPTCHA after the first few attempts from an
                IP.</li>
            <li>Rate-limit both registration and login (per IP and per account).</li>
            <li>Lock accounts (or introduce an exponential delay) after N consecutive
                failed logins.</li>
            <li>Hash passwords with <code>password_hash()</code> and enforce a minimum
                strength on signup.</li>
            <li>Consider 2FA for high-privilege accounts (admin).</li>
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
