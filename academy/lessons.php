<?php
/**
 * Single source of truth for Cyber Academy lessons.
 *
 * Each entry's key is the lesson slug and matches the page filename:
 *   slug "sqli-login" -> academy/sqli-login.php
 *
 * Fields:
 *   title       human-readable lesson title
 *   short       one-line summary used on the index card
 *   category    grouping label (Injection, XSS, Authentication, Capstone)
 *   difficulty  Easy | Medium | Hard | Capstone
 *   target_url    link into the vulnerable site where the attack happens
 *                 (relative to /academy/, so prefixed with ../)
 *   scripts       array of paths (relative to /academy/) shown on the
 *                 solution view via highlight_file()
 *   status        ready | coming_soon
 *   prerequisites (capstones only) - array of individual-lab slugs this
 *                 chain combines. Used to render the "Prerequisites" chips
 *                 on the capstone page and (reverse-mapped) the "Used in"
 *                 chips on each individual lab page.
 *
 * Adding a new lesson later: add an entry here, then copy
 * academy/sqli-login.php to academy/<slug>.php and adjust the content.
 */

return [

    /* ===== Individual labs ===== */

    'sqli-login' => [
        'title'      => 'SQL Injection: Login Bypass',
        'short'      => 'Authenticate as an existing user without knowing any password by injecting into the login query.',
        'category'   => 'Injection',
        'difficulty' => 'Easy',
        'target_url' => '../login.php',
        'scripts'    => ['../scripts/SQLi.py'],
        'status'     => 'ready',
    ],

    'sqli-union' => [
        'title'      => 'SQL Injection: UNION-based Data Exfiltration',
        'short'      => 'Use UNION SELECT to dump arbitrary tables from the application database.',
        'category'   => 'Injection',
        'difficulty' => 'Medium',
        'target_url' => '../products.php',
        'scripts'    => ['../scripts/SQLi_UNION.py'],
        'status'     => 'ready',
    ],

    'sqli-blind' => [
        'title'      => 'Blind SQL Injection',
        'short'      => 'Leak secret data one character at a time using time-based blind injection.',
        'category'   => 'Injection',
        'difficulty' => 'Hard',
        'target_url' => '../cart.php',
        'scripts'    => ['../scripts/SQLi_Blind.py'],
        'status'     => 'ready',
    ],

    'xss-reflected' => [
        'title'      => 'Reflected XSS',
        'short'      => 'Craft a malicious link that executes JavaScript in a victim\'s browser and exfiltrates their session cookie.',
        'category'   => 'Cross-Site Scripting',
        'difficulty' => 'Medium',
        'target_url' => '../products.php',
        'scripts'    => [
            '../scripts/Reflected_XSS_Attack_Link_Builder.py',
            '../AttackerServer/catcher.php',
        ],
        'status'     => 'ready',
    ],

    'xss-stored' => [
        'title'      => 'Stored XSS',
        'short'      => 'Persist a malicious payload in the application so it fires for every visitor.',
        'category'   => 'Cross-Site Scripting',
        'difficulty' => 'Medium',
        'target_url' => '../product_view.php',
        'scripts'    => ['../scripts/stored_xss.py'],
        'status'     => 'ready',
    ],

    'xss-dom' => [
        'title'      => 'DOM-based XSS',
        'short'      => 'Smuggle JavaScript through a URL fragment that never reaches the server, exploiting an unsafe innerHTML sink in client-side code.',
        'category'   => 'Cross-Site Scripting',
        'difficulty' => 'Medium',
        'target_url' => '../product_view.php?id=1',
        'scripts'    => [
            '../scripts/DOM_XSS_Attack_Link_Builder.py',
            '../AttackerServer/catcher.php',
        ],
        'status'     => 'ready',
    ],

    'broken-password-reset' => [
        'title'      => 'Broken Password Reset',
        'short'      => 'Take over an arbitrary account by abusing flaws in the password-reset flow.',
        'category'   => 'Authentication',
        'difficulty' => 'Medium',
        'target_url' => '../login.php?view=forgot',
        'scripts'    => ['../scripts/Broken_Password_Reset.py'],
        'status'     => 'ready',
    ],

    'user-enum-bruteforce' => [
        'title'      => 'User Enumeration & Brute Force',
        'short'      => 'Discover valid usernames via registration responses, then brute-force their passwords from a wordlist.',
        'category'   => 'Authentication',
        'difficulty' => 'Easy',
        'target_url' => '../login.php',
        'scripts'    => [
            '../scripts/enum_and_brute.py',
            '../scripts/usernames.txt',
        ],
        'status'     => 'ready',
    ],

    'csrf-admin-reply' => [
        'title'      => 'CSRF: Forced Admin Replies',
        'short'      => 'Trick a logged-in admin into mass-posting fake "official" replies on every review with a single click on an attacker-hosted page.',
        'category'   => 'Cross-Site Request Forgery',
        'difficulty' => 'Medium',
        'target_url' => '../product_view.php?id=81',
        'scripts'    => [
            '../scripts/win_iphone.html',
            '../admin_reply.php',
        ],
        'status'     => 'ready',
    ],

    'csrf-account-takeover' => [
        'title'      => 'CSRF: Silent Account Takeover',
        'short'      => 'Lure any logged-in user to an attacker page that silently changes their password via a no-token POST to the profile endpoint.',
        'category'   => 'Cross-Site Request Forgery',
        'difficulty' => 'Medium',
        'target_url' => '../profile.php',
        'scripts'    => [
            '../scripts/free_giftcard.html',
            '../profile.php',
        ],
        'status'     => 'ready',
    ],

    /* ===== Capstone chains (split from Master_kill_chain.py) ===== */

    'chain-web-shell' => [
        'title'         => 'Capstone: Full Web Shell (Enum -> Brute / Reset -> Web Shell)',
        'short'         => 'Discover users, gain access via brute force or broken reset, then drop a web shell for remote code execution.',
        'category'      => 'Capstone',
        'difficulty'    => 'Capstone',
        'target_url'    => '../login.php',
        'scripts'       => [
            '../scripts/Master_kill_chain.py',
            '../scripts/enum_and_brute.py',
            '../scripts/Broken_Password_Reset.py',
            '../scripts/web_shell.py',
        ],
        'status'        => 'ready',
        'prerequisites' => ['user-enum-bruteforce', 'broken-password-reset'],
    ],

    'chain-data-breach' => [
        'title'         => 'Capstone: Data Breach to XSS (UNION SQLi -> Stored XSS)',
        'short'         => 'Dump the user table with UNION SQLi, then weaponise a stolen account to plant stored XSS.',
        'category'      => 'Capstone',
        'difficulty'    => 'Capstone',
        'target_url'    => '../login.php',
        'scripts'       => [
            '../scripts/Master_kill_chain.py',
            '../scripts/SQLi_UNION.py',
            '../scripts/stored_xss.py',
        ],
        'status'        => 'ready',
        'prerequisites' => ['sqli-union', 'xss-stored'],
    ],

    'chain-stealth-leak' => [
        'title'         => 'Capstone: Stealth Leak (Login Bypass -> Blind SQLi)',
        'short'         => 'Bypass login with classic SQLi, then quietly leak the VIP coupon code with blind SQLi.',
        'category'      => 'Capstone',
        'difficulty'    => 'Capstone',
        'target_url'    => '../login.php',
        'scripts'       => [
            '../scripts/Master_kill_chain.py',
            '../scripts/SQLi.py',
            '../scripts/SQLi_Blind.py',
        ],
        'status'        => 'coming_soon',
        'prerequisites' => ['sqli-login', 'sqli-blind'],
    ],

];
