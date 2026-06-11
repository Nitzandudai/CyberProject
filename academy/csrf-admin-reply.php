<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['csrf-admin-reply'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 08</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('csrf-admin-reply'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        <strong>Cross-Site Request Forgery (CSRF)</strong> abuses one of the oldest
        rules of the web: browsers automatically attach a site&apos;s cookies to
        <em>every</em> request that goes to that site, no matter which page initiated
        the request. If the application&apos;s only proof of identity is the session
        cookie, then any page that can convince a victim&apos;s browser to fire a
        request at the target endpoint speaks with the victim&apos;s authority - even
        if the malicious page lives on a completely different origin.
    </p>
    <p>
        The admin-reply endpoint checks for an admin session and then blindly
        UPDATEs the database from the POST body:
    </p>
    <pre><code>// admin_reply.php
session_start();

if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $review_id     = (int)$_POST['review_id'];
    $reply_content = $_POST['reply_content'];

    $stmt = $db-&gt;prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
    $stmt-&gt;execute([$reply_content, $review_id]);

    header("Location: " . $_SERVER['HTTP_REFERER']);
}</code></pre>
    <p>What the server is <em>not</em> checking:</p>
    <ul>
        <li>No CSRF token in the POST body.</li>
        <li>No <code>Origin</code> or <code>Referer</code> header validation.</li>
        <li>No <code>SameSite</code> attribute on the session cookie, so the cookie
            travels on cross-origin POSTs by default.</li>
        <li>No re-authentication or step-up for an action that modifies public-facing
            store content.</li>
    </ul>
    <p>
        That leaves the &quot;is the user an admin?&quot; question as the
        <em>only</em> gate, and the admin&apos;s own browser is happy to answer that
        with the session cookie for anyone who asks.
    </p>
    <div class="academy-callout">
        <strong>Why a normal form POST is enough.</strong> The browser&apos;s
        same-origin policy blocks <em>JavaScript</em> from reading cross-origin
        responses, but it explicitly does <strong>not</strong> block
        <em>sending</em> form-encoded POSTs cross-origin - that has been allowed
        since HTML forms existed. CSRF is the gap between &quot;can&apos;t read
        the response&quot; and &quot;can still cause the side effect&quot;.
    </div>
</section>

<!-- 2. TASK 1 - FIND A STATE-CHANGING ADMIN ENDPOINT -->
<section class="academy-block">
    <h2>2. Task 1 - find a state-changing admin endpoint</h2>
    <p>
        Browse the site as <code>admin</code> and look for a POST that <em>changes
        state</em> (writes to the database, edits content, etc.) and whose only proof
        of identity is the session cookie - no CSRF token in the form, no
        <code>Origin</code>/<code>Referer</code> validation on the handler. That
        combination is what CSRF needs: the browser will auto-send the cookie from any
        origin, so a page on attacker.example.com can fire the same POST and the
        server cannot tell the difference.
    </p>
    <details class="academy-hint">
        <summary>Reveal the vulnerable endpoint</summary>
        <p>
            It is <code>admin_reply.php</code>. The admin uses it from any product page
            to post an &quot;Official Store Reply&quot; under a review. The handler
            only checks the admin session - nothing else:
        </p>
        <pre><code>// admin_reply.php
session_start();

if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $review_id     = (int)$_POST['review_id'];
    $reply_content = $_POST['reply_content'];

    $stmt = $db-&gt;prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
    $stmt-&gt;execute([$reply_content, $review_id]);

    header("Location: " . $_SERVER['HTTP_REFERER']);
}</code></pre>
        <p>What the server is <em>not</em> checking:</p>
        <ul>
            <li>No CSRF token in the POST body.</li>
            <li>No <code>Origin</code> or <code>Referer</code> header validation.</li>
            <li>No <code>SameSite</code> attribute on the session cookie, so the cookie
                travels on cross-origin POSTs by default.</li>
            <li>No re-authentication for an action that modifies public-facing store
                content.</li>
        </ul>
    </details>
</section>

<!-- 3. TASK 2 - WEAPONISE AN HTML PAGE -->
<section class="academy-block">
    <h2>3. Task 2 - weaponise an HTML page</h2>
    <ol>
        <li>Log in as <code>admin</code> on the target site. You can use the seeded
            account, or chain this lab on top of the
            <a href="sqli-login.php">login-bypass SQLi</a> lab to forge an admin
            session.</li>
        <li>Open an electronics product page (e.g. <code>product_view.php?id=81</code>)
            and note the current reviews - none of them have an &quot;Official Store
            Reply&quot; block yet.</li>
        <li>Build an HTML page (or reuse <code>scripts/win_iphone.html</code>) that,
            when loaded by the admin&apos;s browser, fires a POST to
            <code>admin_reply.php</code> for every <code>review_id</code> from 1 to
            100. Use one hidden <code>&lt;iframe&gt;</code> per form so the attacker
            page doesn&apos;t navigate away after the first submit.</li>
        <li>Dress the page up so the victim has no reason to suspect anything
            (&quot;You won an iPhone!&quot;) and redirect them off to Google a couple
            of seconds later so the broken-looking page disappears.</li>
        <li>
            <strong>Goal:</strong> after the admin loads your page once, every
            review on the site (or at least the first 100) shows an &quot;Official
            Store Reply&quot; that you wrote - without the admin ever clicking
            &quot;Post Official Reply&quot;.
        </li>
    </ol>
    <p>
        Use the &quot;Reset databases&quot; button on the academy index when
        you&apos;re done; this lab dirties every <code>admin_reply</code> in the
        <code>reviews</code> table.
    </p>

</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>
        Opens an electronics product page so you can see the &quot;before&quot;
        state. Make sure you are logged in as <code>admin</code> / <code>admin123</code>,
        then come back here and reveal the solution to run the attack page.
    </p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable product page</a>
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
            To force a single reply on a known review you would only need this much
            HTML - no JavaScript at all:
        </p>
        <pre><code>&lt;form action="http://localhost/CyberProject/admin_reply.php" method="POST"&gt;
    &lt;input name="review_id"     value="1"&gt;
    &lt;input name="reply_content" value="Officially endorsed by the store."&gt;
&lt;/form&gt;
&lt;script&gt;document.forms[0].submit();&lt;/script&gt;</code></pre>
        <p>
            The browser auto-attaches the admin&apos;s <code>PHPSESSID</code> cookie
            because the form action points at <code>localhost/CyberProject</code>, the
            server sees a normal admin POST, and the UPDATE runs. Done.
        </p>

        <h3>The full &quot;weaponised&quot; version (<code>win_iphone.html</code>)</h3>
        <p>The attached payload is a more realistic delivery vehicle. Three tricks:</p>
        <ol>
            <li>
                <strong>Visible-page disguise.</strong> The DOM is a glossy
                &quot;You won an iPhone!&quot; landing page with a spinner. The
                attack runs from <code>&lt;body onload="launchAttack()"&gt;</code> so
                it fires the moment the page renders. The victim never sees a button
                to click; the page does the dirty work itself.
            </li>
            <li>
                <strong>100 hidden iframes for parallelism.</strong> Each form
                <code>target=hidden_frame_N</code> goes to its own iframe so the
                attacker page doesn&apos;t navigate away. All 100 POSTs fire in
                quick succession.
            </li>
            <li>
                <strong>Exit redirect to <code>google.com</code>.</strong> After 2
                seconds the visible page sends the victim to Google - they
                conclude &quot;weird, that link was broken&quot; and move on,
                completely unaware that 100 reviews on a site they administer just
                got vandalised in their name.
            </li>
        </ol>

        <h3>Running it</h3>
        <ol>
            <li>Log in as <code>admin</code> in your browser
                (<code>http://localhost/CyberProject/login.php</code>).</li>
            <li>In a new tab, open
                <code>http://localhost/CyberProject/scripts/win_iphone.html</code>.</li>
            <li>Watch the spinner for ~1 second. Notice you get redirected to
                <code>google.com</code>.</li>
            <li>Open any electronics product page (e.g.
                <code>product_view.php?id=81</code>) and scroll the reviews -
                every existing review now has a fake &quot;Official Store Reply&quot;
                with one of the insults from the payload.</li>
        </ol>

        <h3>The attack page itself</h3>
        <p>Open <code>scripts/win_iphone.html</code> to read it inline. Note that the
            <code>targetUrl</code> at the top is the <em>victim</em> URL - that&apos;s
            where the admin&apos;s cookie lives. In a real cross-origin demo this
            file would be hosted on <code>attacker.example.com</code> while
            <code>targetUrl</code> would still point at the victim site.</p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View win_iphone.html</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../scripts/win_iphone.html'); ?>
            </div>
        </details>

        <h3>The vulnerable endpoint</h3>
        <p>For completeness, here is the full source of
            <code>admin_reply.php</code> - note the absence of any anti-CSRF
            mechanism:</p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View admin_reply.php</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../admin_reply.php'); ?>
            </div>
        </details>

        <h3>Why no Python script for this lab?</h3>
        <p>
            CSRF is unusual among the labs in that it cannot really be exploited by
            an external script. A <code>requests.post()</code> from Python carries
            no browser cookie, no admin session, no auth at all - the server returns
            &quot;Unauthorized access.&quot; instantly. The attack <em>has</em> to be
            delivered as HTML/JS that runs <em>inside the victim&apos;s browser</em>,
            because the victim&apos;s browser is what holds the credentials.
        </p>

        <h3>How to fix it</h3>
        <ul>
            <li>
                <strong>CSRF token (synchronizer pattern).</strong> On any sensitive
                form, embed a random per-session token in a hidden input and require
                it back on the POST. Cross-origin pages cannot read it (same-origin
                policy actually does protect <em>reads</em>), so they cannot forge a
                matching token.
                <pre><code>// when rendering the form
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
echo '&lt;input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'"&gt;';

// when handling the POST
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('CSRF token mismatch.');
}</code></pre>
            </li>
            <li>
                <strong><code>SameSite</code> cookie attribute.</strong> Set
                <code>session.cookie_samesite = 'Lax'</code> (or
                <code>'Strict'</code>) in <code>php.ini</code>, or call
                <code>session_set_cookie_params(['samesite' =&gt; 'Lax'])</code>
                before <code>session_start()</code>. Modern browsers then
                <em>won&apos;t even send</em> the session cookie on a cross-origin
                POST, which alone defeats this attack.
            </li>
            <li>
                <strong>Validate <code>Origin</code> / <code>Referer</code>.</strong>
                On state-changing POSTs, reject anything whose <code>Origin</code>
                header isn&apos;t your own site. (Cheap but defence-in-depth - easy
                to mis-implement, so it&apos;s never the only check.)
            </li>
            <li>
                <strong>Re-authenticate sensitive admin actions.</strong> Editing
                public-facing replies is high-impact; require a fresh password (or a
                second factor) before accepting the change.
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
