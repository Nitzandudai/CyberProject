<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['broken-password-reset'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 06</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        A password-reset flow is supposed to prove two things before changing a password:
        that you control the account, and that the request is fresh. Real implementations
        do this by emailing a single-use, time-limited, cryptographically random token
        and only accepting the new password through a URL containing that token.
    </p>
    <p>
        When that token is missing - or when the server identifies the target user from
        client-supplied data the attacker can edit - you get an <strong>IDOR</strong>
        (Insecure Direct Object Reference) on top of broken authentication. The reset
        endpoint will happily change <em>whoever the URL points at</em>, including
        accounts the attacker has no claim to.
    </p>
</section>

<!-- 2. TASK 1 - LOCATE WHAT'S MISSING -->
<section class="academy-block">
    <h2>2. Task 1 - locate what is missing</h2>
    <p>
        Walk through the legitimate &quot;Forgot password?&quot; flow once, end to end.
        Pay attention to what the server is - and is not - verifying. What proof of
        ownership is required before the password actually changes? Is there a single-use
        token? An email round-trip? A check that the user clicking the reset link is the
        one who requested it?
    </p>
    <details class="academy-hint">
        <summary>Reveal the missing checks</summary>
        <p>
            None of the above. <code>reset_password.php</code> takes the username straight
            from the URL and updates its password to whatever you POST - no token, no
            email confirmation, no ownership check:
        </p>
        <pre><code>// reset_password.php
$user_to_reset = $_GET['user'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" &amp;&amp; $user_to_reset) {
    $new_password = $_POST['new_password'];

    $sql = "UPDATE users SET password = :new_pw WHERE username = :user_name";
    $stmt = $db-&gt;prepare($sql);
    $stmt-&gt;execute([':new_pw' =&gt; $new_password, ':user_name' =&gt; $user_to_reset]);
}</code></pre>
        <p>Any attacker who can reach the URL can:</p>
        <ol>
            <li>Pick any username they want as the <code>?user=</code> parameter.</li>
            <li>POST a new password to that URL.</li>
            <li>Log in to the victim&apos;s account immediately.</li>
        </ol>
        <p>
            The &quot;email&quot; step in the forgot-password flow is theatre: it just
            stashes the username in <code>$_SESSION</code> and renders a fake mailbox at
            <code>mailbox.php</code>. The actual reset endpoint never verifies that the
            user clicking the link is the user who requested the reset.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - TAKE OVER ADMIN -->
<section class="academy-block">
    <h2>3. Task 2 - take over the admin account</h2>
    <ol>
        <li>Using the missing checks you found in Task 1, change <code>admin</code>&apos;s
            password without ever knowing its current password, email, or any token.</li>
        <li>
            <strong>Goal:</strong> log in successfully as <code>admin</code> using a new
            password you chose.
        </li>
    </ol>
    <p>Run the &quot;Reset databases&quot; button on the academy index when you&apos;re
       done if you want to start from a clean state again.</p>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>The login page opens in a new tab. Use the &quot;Forgot password?&quot; link to
       walk through the legitimate flow first, then notice what is (and isn&apos;t) being
       verified.</p>
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

        <h3>The one-line exploit</h3>
        <p>You don&apos;t even need to touch the forgot-password form. Just hit the
            reset endpoint directly:</p>
        <pre><code>POST /CyberProject/reset_password.php?user=admin

new_password=hunter2</code></pre>
        <p>The server runs <code>UPDATE users SET password = 'hunter2' WHERE username = 'admin'</code>,
            <code>rowCount()</code> is 1, and the response confirms the change. Now go to
            the login page and sign in as <code>admin / hunter2</code>.</p>

        <h3>Doing it from the browser</h3>
        <ol>
            <li>Open <code>http://localhost/CyberProject/reset_password.php?user=admin</code>
                directly - no login required.</li>
            <li>The page renders the &quot;Setting new password for: <strong>admin</strong>&quot;
                form because the only check is &quot;is <code>?user=</code> non-empty&quot;.</li>
            <li>Submit your new password.</li>
            <li>Log in normally.</li>
        </ol>

        <h3>What a real password reset should look like</h3>
        <ul>
            <li>Generate a 256-bit random token, hash it, and store the hash with an
                expiry (≤ 1 hour) in a dedicated <code>password_resets</code> table.</li>
            <li>Email the raw token to the address <em>on file</em> for that user - never
                trust an email from the form input.</li>
            <li>The reset URL contains the token, not the username. The server looks up
                the user via the token row.</li>
            <li>On successful reset: delete the token row, invalidate all the
                user&apos;s sessions, send a notification email.</li>
            <li>Rate-limit reset requests per IP / per account.</li>
        </ul>

        <h3>Automated exploit</h3>
        <p>
            The script has two functions: a brute-force fallback against
            <code>login.php</code> using a wordlist, and the actual reset bypass via
            <code>run_reset_password()</code>.
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/Broken_Password_Reset.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <pre><code>// At the top of reset_password.php
if (!isset($_GET['token'])) {
    http_response_code(400);
    exit('Missing token.');
}

$stmt = $db-&gt;prepare(
    "SELECT user_id, expires_at FROM password_resets WHERE token_hash = :h"
);
$stmt-&gt;execute([':h' =&gt; hash('sha256', $_GET['token'])]);
$row = $stmt-&gt;fetch();

if (!$row || strtotime($row['expires_at']) &lt; time()) {
    http_response_code(400);
    exit('Token expired.');
}

// only here may we accept POST and update the password
// for $row['user_id'] (not from the URL!)</code></pre>
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
