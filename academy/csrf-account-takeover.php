<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['csrf-account-takeover'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 09</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('csrf-account-takeover'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        <strong>Cross-Site Request Forgery (CSRF)</strong> abuses a basic browser
        behaviour: when a request is sent to a site, the browser may include that
        site&apos;s cookies automatically, even if the request was triggered from a
        different origin. If the application relies only on the session cookie to
        identify the user, then a malicious page may be able to cause the victim&apos;s
        browser to send an authenticated request to the target application.
    </p>
    <p>
        The same condition applies: any endpoint that accepts a form POST, grants
        authority based only on the session cookie, and carries no CSRF protection
        is a target. Find the password-management feature and check whether it is
        vulnerable - that is Task 1.
    </p>
    <div class="academy-callout">
        <strong>Why this is worse than the admin-reply CSRF.</strong> Posting a
        fake reply is vandalism, and the admin can edit it back. Changing the
        password is a full account takeover - the attacker can log in as the
        victim immediately, and the victim is <em>locked out</em> until they use
        the (also broken) password-reset flow. The blast radius covers every
        logged-in user, not just the admin.
    </div>
</section>

<!-- 2. TASK 1 - FIND THE ENDPOINT -->
<section class="academy-block">
    <h2>2. Task 1 - find a state-changing user endpoint</h2>
    <p>
        Log in as any normal user (or register a fresh one) and browse the site
        looking for a POST that mutates account state and whose only proof of
        identity is the session cookie. Open DevTools &rarr; Network and watch the
        form submissions; you want one with no <code>csrf</code> / <code>token</code>
        field in the body and no server-side check on the request&apos;s origin.
    </p>
    <details class="academy-hint">
        <summary>Reveal the vulnerable endpoint</summary>
        <p>
            It is <code>profile.php</code> (linked from the top nav as
            &quot;Personal Details&quot;). The &quot;Change password&quot; form
            posts a single <code>new_password</code> field back to the same URL.
            No hidden token, no current-password input, nothing - just the
            session cookie the browser already carries.
        </p>
        <p>The handler does basically nothing beyond &quot;is there a session?&quot;:</p>
        <pre><code>// profile.php (handler)
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" &amp;&amp; isset($_POST['new_password'])) {
    $new_password = (string)$_POST['new_password'];
    $stmt = $db-&gt;prepare("UPDATE users SET password = :p WHERE username = :u");
    $stmt-&gt;execute([
        ':p' =&gt; $new_password,
        ':u' =&gt; $_SESSION['username'],
    ]);
}</code></pre>
        <p>What the server is <em>not</em> checking:</p>
        <ul>
            <li>No CSRF token in the POST body.</li>
            <li>No <code>Origin</code> / <code>Referer</code> header validation.</li>
            <li>No <code>SameSite</code> attribute on the session cookie - the
                cookie travels on cross-origin POSTs.</li>
            <li><strong>No current-password re-prompt.</strong> A password-change
                endpoint should always require the <em>old</em> password as proof that
                the human at the keyboard is the account owner. This one trusts the
                session alone.</li>
        </ul>
    </details>
</section>

<!-- 3. TASK 2 - WEAPONISE AN HTML PAGE -->
<section class="academy-block">
    <h2>3. Task 2 - weaponise an HTML page</h2>
    <ol>
        <li>Register a victim account (e.g. <code>victim1</code> /
            <code>victim_pw</code>) and log in as that user in your browser. This
            is the session the attack will hijack.</li>
        <li>Build a standalone HTML page (or reuse
            <code>scripts/free_giftcard.html</code>) with a form that POSTs to
            <code>profile.php</code> with a single hidden <code>new_password</code>
            field, targeting a hidden <code>&lt;iframe&gt;</code> so the visible
            page does not navigate away after submit.</li>
        <li>Dress the page up so the victim has no reason to suspect anything
            (&quot;You won a $100 gift card!&quot;). Auto-submit the form on
            <code>onload</code>, then redirect the visible window to
            <code>google.com</code> after a couple of seconds.</li>
        <li>
            <strong>Goal:</strong> after the victim opens your page once, log out,
            try their old password - it no longer works. Log in with the password
            you embedded in the attack page. You are now them.
        </li>
    </ol>
    <p>
        Use the &quot;Reset databases&quot; button on the academy index when
        you&apos;re done to restore the seeded passwords.
    </p>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>
        Open the &quot;Personal Details&quot; page so you can see the
        &quot;before&quot; state of the form. Make sure you are logged in as the
        victim account, then come back here and reveal the solution to run the
        attack page.
    </p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable profile page</a>
</section>

<!-- 5. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>The smallest possible CSRF payload</h3>
        <p>
            To silently change the victim&apos;s password to a value you control,
            you only need this much HTML - no JavaScript at all beyond the
            auto-submit:
        </p>
        <pre><code>&lt;form action="http://localhost/CyberProject/profile.php" method="POST"&gt;
    &lt;input name="new_password" value="pwned_by_attacker_123"&gt;
&lt;/form&gt;
&lt;script&gt;document.forms[0].submit();&lt;/script&gt;</code></pre>
        <p>
            The browser auto-attaches the victim&apos;s <code>PHPSESSID</code>
            because the form action points at <code>localhost/CyberProject</code>,
            the server sees a normal authenticated POST, and the UPDATE on
            <code>users.password</code> runs against
            <code>$_SESSION['username']</code> - the victim&apos;s account.
        </p>

        <h3>The full &quot;weaponised&quot; version (<code>free_giftcard.html</code>)</h3>
        <p>The attached payload is a more realistic delivery vehicle. Three tricks
            (same playbook as <code>win_iphone.html</code>):</p>
        <ol>
            <li>
                <strong>Visible-page disguise.</strong> The DOM is a glossy
                &quot;You won a $100 gift card!&quot; landing page with a spinner.
                The attack runs from <code>&lt;body onload="launchAttack()"&gt;</code>
                so it fires the moment the page renders.
            </li>
            <li>
                <strong>Hidden iframe target.</strong> The form&apos;s
                <code>target=csrf_sink</code> points at a hidden iframe so the
                visible page does not navigate to the victim site after the POST.
                From the user&apos;s perspective, nothing happened.
            </li>
            <li>
                <strong>Exit redirect to <code>google.com</code>.</strong> After
                2 seconds the visible page sends the victim to Google - they
                conclude &quot;weird, that gift-card link was broken&quot; and
                move on, completely unaware that their password just got changed.
            </li>
        </ol>

        <h3>Running it</h3>
        <ol>
            <li>Register a victim account at
                <code>http://localhost/CyberProject/login.php?view=register</code>
                (e.g. <code>victim1</code> / <code>victim_pw</code>) and log in as
                that user.</li>
            <li>In a new tab, open
                <code>http://localhost/CyberProject/scripts/free_giftcard.html</code>.</li>
            <li>Watch the spinner for ~2 seconds. You get redirected to
                <code>google.com</code>.</li>
            <li>Go back to the site, log out, and try logging in with the old
                password - it fails. Log in with
                <code>pwned_by_attacker_123</code> - you are in.</li>
        </ol>

        <h3>The attack page itself</h3>
        <p>
            Open <code>scripts/free_giftcard.html</code> to read it inline. The
            <code>action</code> attribute is the <em>victim</em> URL - that&apos;s
            where the victim&apos;s cookie lives. In a real cross-origin demo this
            file would be hosted on <code>attacker.example.com</code> while the
            form action would still point at the victim site.
        </p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View free_giftcard.html</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../scripts/free_giftcard.html'); ?>
            </div>
        </details>

        <h3>The vulnerable endpoint</h3>
        <p>For completeness, here is <code>profile.php</code> - note that the
            password-change handler does nothing beyond the session check:</p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View profile.php</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../profile.php'); ?>
            </div>
        </details>

        <h3>Why no Python script for this lab?</h3>
        <p>
            Same reason as the admin-reply CSRF: a <code>requests.post()</code>
            from Python carries no browser cookie, no victim session, no auth at
            all - the server bounces it to the login page instantly. The attack
            <em>has</em> to be delivered as HTML/JS that runs <em>inside the
            victim&apos;s browser</em>, because the victim&apos;s browser is what
            holds the credentials.
        </p>

        <h3>How to fix it</h3>
        <ul>
            <li>
                <strong>Require the current password.</strong> The single biggest
                fix for a password-change endpoint: take a <code>current_password</code>
                field and verify it before accepting the new one. A cross-origin
                attacker does not know the victim&apos;s current password, so
                they cannot forge a valid request even with the session cookie.
                <pre><code>$stmt = $db-&gt;prepare("SELECT password FROM users WHERE username = :u");
$stmt-&gt;execute([':u' =&gt; $_SESSION['username']]);
if (!hash_equals((string)$stmt-&gt;fetchColumn(), (string)$_POST['current_password'])) {
    http_response_code(403);
    exit('Current password is incorrect.');
}</code></pre>
            </li>
            <li>
                <strong>CSRF token (synchronizer pattern).</strong> Embed a random
                per-session token in the form and require it back on the POST.
                Cross-origin pages cannot read it, so they cannot forge it.
                <pre><code>$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
echo '&lt;input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'"&gt;';

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('CSRF token mismatch.');
}</code></pre>
            </li>
            <li>
                <strong><code>SameSite</code> cookie attribute.</strong> Set
                <code>session.cookie_samesite = 'Lax'</code> (or
                <code>'Strict'</code>). Modern browsers then won&apos;t send the
                session cookie on a cross-origin POST at all.
            </li>
            <li>
                <strong>Hash the stored password.</strong> Tangential to CSRF, but
                this lab&apos;s database stores plaintext passwords. Use
                <code>password_hash()</code> / <code>password_verify()</code> so a
                future DB leak does not immediately compromise every account.
            </li>
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
