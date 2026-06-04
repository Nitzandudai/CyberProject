<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
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
        On a login form, the classic outcome is to make the <code>WHERE</code> clause
        match every row (a <em>tautology</em>) and comment out the password check, so
        the server fetches the first user in the table and logs you in as them with no
        password at all.
    </p>
</section>

<!-- 2. TASK 1 - FIND THE INJECTABLE FORM -->
<section class="academy-block">
    <h2>2. Task 1 - find the injectable form</h2>
    <p>
        Find a form whose input is concatenated straight into a SQL query with no
        prepared statement. A single stray quote (<code>'</code>) in the field is a quick
        smoke test: if the page reacts differently from a normal submission, the quote
        reached the parser.
    </p>
    <details class="academy-hint">
        <summary>Reveal the injectable form</summary>
        <p>
            It is the login form on <code>login.php</code>. The handler builds its
            authentication query by concatenation:
        </p>
        <pre><code>$username = $_POST["username"];
$password = $_POST["password"];

$sql = "SELECT * FROM users
         WHERE username = '$username'
           AND password = '$password'";

$user = $db-&gt;query($sql)-&gt;fetch(PDO::FETCH_ASSOC);
if ($user) { /* logged in */ }</code></pre>
        <p>
            Both fields are pasted in unescaped. Anything you put in <code>username</code>
            is interpreted as SQL.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - BYPASS THE LOGIN -->
<section class="academy-block">
    <h2>3. Task 2 - bypass the login</h2>
    <ol>
        <li>Craft a <code>username</code> value that closes the string literal, makes the
            <code>WHERE</code> clause true for every row, and comments out the
            <code>AND password = '...'</code> check.</li>
        <li>Put anything non-empty in the <code>password</code> field - it never gets
            evaluated.</li>
        <li>
            <strong>Goal:</strong> land on <code>home.php</code> with a &quot;Logged in
            as:&quot; banner showing a real account&apos;s username, without registering
            and without using the &quot;Forgot password&quot; flow.
        </li>
    </ol>
    <details class="academy-hint">
        <summary>Reveal the SQLite gotcha (read this if your payload looks right but isn&apos;t working)</summary>
        <p>
            The <code>--</code> line-comment token in SQLite requires a <strong>trailing
            space</strong> (or end-of-line) to be parsed as a comment. Without it, the
            rest of the query becomes part of the identifier and your payload silently
            fails with no error.
        </p>
    </details>
    <details class="academy-hint">
        <summary>Reveal the payload (last-resort spoiler)</summary>
        <p>
            With your input wrapped in single quotes inside the query, the minimal
            payload that closes the literal, makes <code>WHERE</code> always true and
            comments away the trailing <code>AND password = '...'</code> is:
        </p>
        <pre><code>username: ' OR 1=1 -- 
password: anything</code></pre>
        <p>
            That turns the query into
            <code>SELECT * FROM users WHERE username = '' OR 1=1 -- ' AND password = '...'</code>,
            which returns the first row of <code>users</code>.
        </p>
    </details>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>The vulnerable login page opens in a new tab so you can keep this lesson visible.</p>
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
            The root cause is the same as in the UNION lab: user input is glued into
            the SQL string. The fix is a <strong>prepared statement</strong> plus
            <strong>hashed passwords</strong>:
        </p>
        <pre><code>$stmt = $db-&gt;prepare(
    "SELECT * FROM users WHERE username = :u"
);
$stmt-&gt;execute([':u' =&gt; $username]);
$user = $stmt-&gt;fetch(PDO::FETCH_ASSOC);

if ($user &amp;&amp; password_verify($password, $user['password_hash'])) {
    // login ok
}</code></pre>
        <ul>
            <li><code>prepare()</code> + <code>:u</code> sends the query template to the
                database first; the user value is sent <em>separately</em> as data, so
                <code>' OR 1=1 --&nbsp;</code> is treated as a literal username to compare
                with, not as SQL syntax.</li>
            <li>The password is checked in PHP with <code>password_verify()</code> against
                a stored bcrypt hash, never compared as plaintext - so even if the user
                table leaks later, the passwords don&apos;t.</li>
        </ul>
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
