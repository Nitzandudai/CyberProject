<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
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
        (any username, any password) into a 1D one (known username, guess password).
    </p>
    <p>
        Our registration page gives away which usernames exist. When you POST a new user,
        the server uses a different error message for &quot;name taken&quot; than for any
        other failure:
    </p>
    <pre><code>// login.php (register branch)
$check_stmt = $db-&gt;prepare("SELECT COUNT(*) FROM users WHERE username = :username");
$check_stmt-&gt;execute([':username' =&gt; $reg_username]);

if ($check_stmt-&gt;fetchColumn() &gt; 0) {
    $error = "Username already exists in the system"; // &lt;-- oracle
} else {
    // insert new row
}</code></pre>
    <p>
        Any name that triggers that exact string is a confirmed existing account. We
        sweep a wordlist of candidate names, collect every hit, and then we have a list
        of real usernames to brute-force.
    </p>
    <p>
        The login endpoint helps the second phase. It is unauthenticated, has no rate
        limiting, no CAPTCHA, no account lockout, and the redirect to
        <code>home.php</code> on success is a clear signal - a 302 with
        <code>Location: home.php</code> means we got in, anything else means we didn&apos;t.
        Loop a small dictionary of common passwords against each enumerated user and
        cracks fall out of any account with a weak password.
    </p>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Find an &quot;oracle&quot; on the registration form that distinguishes
            existing accounts from new ones.</li>
        <li>Sweep <code>scripts/usernames.txt</code> against that oracle and build the
            list of real accounts.</li>
        <li>Brute-force each one with a short list of common passwords (try the ten or so
            in the script&apos;s default list).</li>
        <li><strong>Goal:</strong> recover at least one <code>(username, password)</code>
            pair that lets you log in successfully.</li>
    </ol>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The login + registration page opens in a new tab.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable login page</a>
</section>

<!-- 4. REVEAL SOLUTION -->
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
            For each enumerated user, POST to <code>login.php</code> with each of:
        </p>
        <pre><code>123456, admin123, 12345678, 123456789, 12345,
password, Aa123456, 1234567890, 111111, qwerty</code></pre>
        <p>
            Detect success by <code>response.status_code == 302</code> and
            <code>Location</code> containing <code>home.php</code> &mdash; that is the
            same signal the application itself uses internally.
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

        <h3>Automated exploit</h3>
        <p>
            The script wires both phases together and prints a summary at the end.
            <code>chain_1_web_shell()</code> in <code>Master_kill_chain.py</code> uses it
            as the discovery step before deciding whether to brute-force harder or fall
            back to the broken password reset.
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/enum_and_brute.py'); ?>
        </div>

        <h3>The wordlists</h3>
        <p><code>scripts/usernames.txt</code>:</p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/usernames.txt'); ?>
        </div>
        <p><code>scripts/passwords.txt</code>:</p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/passwords.txt'); ?>
        </div>

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
