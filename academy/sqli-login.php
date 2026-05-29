<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['sqli-login'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 01</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-easy"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('sqli-login'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        <strong>SQL injection</strong> is a class of vulnerability where attacker-controlled
        input is concatenated into a SQL query as raw text instead of being passed as a
        parameter. Because the database has no way to tell user data apart from query
        syntax, an attacker can break out of the data context and inject new SQL clauses.
    </p>
    <p>
        Our login page builds its authentication query like this:
    </p>
    <pre><code>$sql = "SELECT * FROM users
       WHERE username = '$username'
         AND password = '$password'";

$user = $db-&gt;query($sql)-&gt;fetch(PDO::FETCH_ASSOC);

if ($user) {
    $_SESSION["username"] = $user['username'];
    header("Location: home.php");
}</code></pre>
    <p>
        The variables <code>$username</code> and <code>$password</code> come straight from
        the POST body and are pasted into the query with no escaping and no prepared
        statement. Anything we put in <code>username</code> is interpreted as SQL.
    </p>
    <p>
        If we submit <code>' OR 1=1 -- </code> as the username, the query becomes:
    </p>
    <pre><code>SELECT * FROM users
 WHERE username = '' OR 1=1 -- ' AND password = 'anything'</code></pre>
    <p>
        The single quote closes the empty string literal, <code>OR 1=1</code> makes the
        <code>WHERE</code> clause true for every row, and <code>--&nbsp;</code> comments out
        the rest of the query (including the password check). The query returns the first
        row of <code>users</code>, and the application happily logs us in as that user.
    </p>
    <div class="academy-callout">
        <strong>SQLite quirk worth remembering:</strong> the <code>--</code> line-comment
        token in SQLite requires a trailing space or end-of-line. The payload is
        <code>' OR 1=1 -- </code> with a space at the end, not <code>' OR 1=1 --</code>.
    </div>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Open the login page on the target site.</li>
        <li>
            Log in as <strong>any existing user</strong> without registering an account and
            without knowing any real password.
        </li>
        <li>
            Confirm success: you should land on <code>home.php</code> with a "Logged in as:"
            banner showing a real account&apos;s username.
        </li>
    </ol>
    <p>
        <strong>Constraints:</strong> do not press the "Register" link, do not touch the
        "Forgot password" flow. The whole exploit must happen on the login form itself.
    </p>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The vulnerable login page opens in a new tab so you can keep this lesson visible.</p>
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

        <h3>Payload</h3>
        <table class="academy-payload">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>username</code></td>
                    <td><code>' OR 1=1 -- </code> (note the trailing space)</td>
                </tr>
                <tr>
                    <td><code>password</code></td>
                    <td><code>anything</code> (literally any non-empty string)</td>
                </tr>
            </tbody>
        </table>

        <h3>Step by step</h3>
        <ol>
            <li>Open <code>login.php</code>.</li>
            <li>In the <em>username</em> field, paste <code>' OR 1=1 -- </code>.</li>
            <li>In the <em>password</em> field, type anything, e.g. <code>x</code>.</li>
            <li>Click <strong>Login</strong>.</li>
            <li>
                The server responds with <code>302 Location: home.php</code> and sets a
                <code>PHPSESSID</code> cookie. You are now logged in as the first row in
                the <code>users</code> table.
            </li>
        </ol>

        <h3>Why it works</h3>
        <p>
            The injected username closes the string literal and turns the <code>WHERE</code>
            clause into a tautology. <code>fetch()</code> returns the first matching row, and
            the application sets <code>$_SESSION["username"]</code> to whatever username sits
            in that row.
        </p>

        <h3>Automated exploit</h3>
        <p>
            The script below reproduces the attack programmatically. It detects success by
            looking for the <code>302</code> redirect to <code>home.php</code> and returns
            the resulting <code>PHPSESSID</code>, which can be reused for further attacks.
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/SQLi.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <p>
            Replace string concatenation with a prepared statement that binds parameters,
            and hash passwords with <code>password_hash()</code> / <code>password_verify()</code>
            instead of comparing plaintext:
        </p>
        <pre><code>$stmt = $db-&gt;prepare(
    "SELECT * FROM users WHERE username = :u"
);
$stmt-&gt;execute([':u' =&gt; $username]);
$user = $stmt-&gt;fetch(PDO::FETCH_ASSOC);

if ($user &amp;&amp; password_verify($password, $user['password_hash'])) {
    // login ok
}</code></pre>
        <p>
            Note: this is for your reference only. We are intentionally <em>not</em> fixing
            <code>login.php</code> in this codebase, because the bug is the lesson.
        </p>
    </div>
</details>

<script>
(function () {
    var details = document.getElementById('academy-solution');
    if (!details) return;
    details.addEventListener('toggle', function () {
        if (this.open && !this.dataset.confirmed) {
            var ok = confirm('Are you sure you want to see the solution?\n\nTry the lab first - that is where the learning happens.');
            if (!ok) {
                this.open = false;
            } else {
                this.dataset.confirmed = '1';
            }
        }
    });
})();
</script>

<p style="margin-top: 28px;">
    <a href="index.php">&larr; Back to all labs</a>
</p>

<?php
academy_layout_end();
