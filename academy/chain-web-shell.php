<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$slug    = 'chain-web-shell';
$lesson  = $lessons[$slug];

academy_layout_start($lesson['title'], $slug);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Capstone 02</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-capstone"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('chain-web-shell'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Overview</h2>
    <p>
        This chain demonstrates how a single SQL injection escalates into full server
        control by laundering the final payload through a stolen account.
    </p>
    <p>Phases:</p>
    <ol>
        <li><strong>Foothold.</strong> Log in as the seeded test account
            <code>HUCKER</code> (<code>hucker123</code>) to obtain a valid
            <code>PHPSESSID</code>. In a real engagement this would be a self-registered account.</li>
        <li><strong>Database exfiltration.</strong> Reuse the session to drive a
            SQL injection vulnerability and dump the entire
            <code>users</code> table with plaintext passwords.</li>
        <li><strong>Remote code execution.</strong> Pick a non-admin victim from the dump,
            log in as them, and upload a web shell through the alcohol ID-photo flow to
            gain arbitrary command execution on the server.</li>
    </ol>
    <p>
        The brilliance is in the laundering: the SQL injection is performed under
        <em>HUCKER&apos;s</em> session, but the web shell is planted by a totally
        different stolen account. The forensic trail in the application&apos;s logs
        implicates someone who had nothing to do with the original breach.
    </p>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Log in as <code>HUCKER</code> / <code>hucker123</code> and keep the session
            cookie - you will use it to authenticate the SQL injection in the next step.</li>
        <li>Using HUCKER&apos;s <code>PHPSESSID</code>, walk through the
            <a href="sqli-union.php">UNION SQLi</a> lab and dump the full
            <code>users</code> table.</li>
        <li>Pick any non-admin user from the dump, log in as them, then find where the
            application lets you upload a file and get Apache to execute it as code.
            <strong>Goal:</strong> run at least one OS command via the browser (e.g.
            <code>?cmd=whoami</code>) and see the output.</li>
    </ol>
    <details class="academy-hint">
        <summary>Reveal the upload sink</summary>
        <p>
            Adding an <strong>alcohol</strong> product to the cart on <code>home.php</code>
            opens a modal that requires an ID photo. The handler saves the file to
            <code>uploaded_ID/</code> using the client-supplied filename:
        </p>
        <pre><code>// home.php (add-to-cart branch)
$fileName = $_FILES["id_photo"]["name"];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$not_allowed = ['php', 'exe', 'js'];

if (in_array($ext, $not_allowed)) {
    die("Error: File type not allowed for ID verification.");
}

$uploadDir = 'uploaded_ID/';
$targetPath = $uploadDir . $fileName;
move_uploaded_file($_FILES["id_photo"]["tmp_name"], $targetPath);</code></pre>
        <p>
            Extensions on that three-item denylist are blocked; everything else is accepted.
            The full bypass is in Phase 3 of the solution below, or work through the
            <a href="web-shell.php">Web Shell via File Upload</a> lab on your own first.
        </p>
    </details>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>The chain begins on the login page (Phase 1 is logging in as
       <code>HUCKER</code> / <code>hucker123</code>).</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable login page</a>
</section>

<!-- 4. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the chain on your own first. Each phase corresponds to an individual lab -
            it&apos;s much more rewarding to assemble them than to read the orchestration.
        </p>

        <h3>Phase 1 - pick up a session</h3>
        <pre><code>POST /CyberProject/login.php
username=HUCKER&amp;password=hucker123&amp;login_submit=</code></pre>
        <p>
            Follow the redirect to <code>home.php</code> and capture
            <code>response.cookies['PHPSESSID']</code>.
        </p>

        <h3>Phase 2 - dump users with UNION SQLi</h3>
        <p>
            Using the captured session cookie, drive the
            <a href="sqli-union.php">UNION SQLi</a> attack against
            <code>products.php?q=...</code>. End state: a list of
            <code>(username, password)</code> tuples for every user in the database.
        </p>

        <h3>Phase 3 - web shell</h3>
        <p>
            Pick a non-admin user (the script takes <code>users[-1]</code> - the last
            row in the dump). Log in as them and walk through the
            <a href="web-shell.php">Web Shell via File Upload</a> lab for the full
            upload bypass and manual exploit. The chain wires it in via
            <code>web_shell.run_ultimate_web_shell(username=..., password=...)</code>.
        </p>

        <h3>Why this is a chain rather than just &quot;SQLi + upload&quot;</h3>
        <ul>
            <li>The dump <em>provides</em> the credentials that make the web shell upload
                untraceable to the original attacker.</li>
            <li>UNION SQLi gives you every password at once; the upload sink gives you
                code execution - together they are a full compromise path.</li>
            <li>The stolen account in the logs is not HUCKER - forensic investigators
                start looking at the wrong user.</li>
        </ul>

        <h3>Orchestration script</h3>
        <p>Run <code>python scripts/Master_kill_chain.py 2</code> to execute all three
            phases unattended. Confirm the web shell responds to
            <code>?cmd=whoami</code>.</p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                <code>chain_2_web_shell()</code> in the master script. For the web shell
                helper itself, see the
                <a href="web-shell.php">Web Shell via File Upload</a> lab.
            </p>
            <div class="academy-script">
                <?php
                $src = file_get_contents(__DIR__ . '/../scripts/Master_kill_chain.py');
                if ($src === false) {
                    echo '<p class="academy-solution-warning">Script file could not be loaded.</p>';
                } else {
                    $p1 = strpos($src, "\ndef chain_1_breach_blind_sql_and_stored_xss");
                    $p2 = strpos($src, "\ndef chain_2_web_shell");
                    $p3 = strpos($src, "\ndef chain_3_stealth");
                    $pm = strpos($src, "\ndef main");
                    if ($p1 === false || $p2 === false || $p3 === false || $pm === false) {
                        echo '<p class="academy-solution-warning">Script markers not found - has Master_kill_chain.py been renamed?</p>';
                    } else {
                        $excerpt = substr($src, 0, $p1 + 1)
                                 . "# --- chain_1_breach_blind_sql_and_stored_xss() defined in chain-breach-blind-sql-stored-xss lab ---\n\n"
                                 . substr($src, $p2 + 1, $p3 - $p2 - 1)
                                 . "\n# --- chain_3_stealth() defined here (see chain-stealth-leak lab) ---\n\n"
                                 . substr($src, $pm + 1);
                        highlight_string($excerpt);
                    }
                }
                ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Fix the underlying UNION SQLi (see the
                <a href="sqli-union.php">SQLi UNION</a> lab) - that removes Phase 2
                entirely.</li>
            <li>Fix the upload sink (see the
                <a href="web-shell.php">Web Shell</a> lab).</li>
            <li>Hash passwords. Even if the UNION dump succeeds, what attackers get out
                should not be directly usable.</li>
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
