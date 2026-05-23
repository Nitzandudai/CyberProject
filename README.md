# CyberProject - Web Security Learning Lab

An intentionally vulnerable PHP e-commerce site ("Anan Super Market") paired with **Cyber Academy**, a self-contained learning platform that teaches each vulnerability hands-on.

The two halves are designed to live together on a single XAMPP install:

- The **vulnerable site** is the target. Its code is deliberately broken so that learners can attack it.
- The **academy** sits at `/academy/` and never touches the vulnerable code. It hosts theory, tasks, and reference solutions for each bug.

> ## ⚠ Ethics & scope
>
> This project ships with **real, working exploits** against a **deliberately vulnerable** application. It exists only for education on systems you own.
>
> - Do **not** expose this site to the public internet, a shared LAN, or any network you do not control.
> - Do **not** copy any of the patterns in the vulnerable site (`login.php`, `products.php`, `admin_reply.php`, etc.) into production code.
> - Do **not** run any of the scripts in `scripts/` or `AttackerServer/` against any system you do not personally own or do not have explicit written permission to test.

---

## What's inside

### The vulnerable site

A small supermarket app (browse products, search, add to cart, register, log in, reviews, password reset, admin replies). It intentionally contains:


| Page                    | Vulnerability                                                                                                                        |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `login.php`             | SQL injection (login bypass); user enumeration via the registration form's response message; no rate limiting (brute-force friendly) |
| `products.php`          | UNION-based SQL injection via the `q` and `cat` parameters                                                                           |
| `cart.php`              | Blind / time-based SQL injection in the `coupon_code` field (UNION explicitly blocked)                                               |
| `home.php`              | Reflected XSS via the `msg` GET parameter                                                                                            |
| `product_view.php`      | Stored XSS in user reviews                                                                                                           |
| `reset_password.php`    | Broken password reset / IDOR - any user's password can be reset by anyone who knows the username                                     |
| `admin_reply.php`       | CSRF                                                                                                                                 |
| (file upload + reviews) | Web shell via `.htaccess`-controlled file type for `.jpg` uploads                                                                    |


### The academy

A separate, learning interface at `/academy/` with **eight individual labs** and **three capstone chains**. Every lesson follows the same template: theory, your task, a "Start the lab" link into the vulnerable site, and a reveal-on-click solution complete with the attack source code rendered inline.

The full lab inventory is generated from a single metadata file ([`academy/lessons.php`](academy/lessons.php)) and currently looks like this:


| Lab                                                    | Category                   | Difficulty | Slug                    |
| ------------------------------------------------------ | -------------------------- | ---------- | ----------------------- |
| SQL Injection: Login Bypass                            | Injection                  | Easy       | `sqli-login`            |
| SQL Injection: UNION-based Data Exfiltration           | Injection                  | Medium     | `sqli-union`            |
| Blind SQL Injection                                    | Injection                  | Hard       | `sqli-blind`            |
| Reflected XSS                                          | Cross-Site Scripting       | Medium     | `xss-reflected`         |
| Stored XSS                                             | Cross-Site Scripting       | Medium     | `xss-stored`            |
| Broken Password Reset                                  | Authentication             | Medium     | `broken-password-reset` |
| User Enumeration & Brute Force                         | Authentication             | Easy       | `user-enum-bruteforce`  |
| CSRF: Forced Admin Replies                             | Cross-Site Request Forgery | Medium     | `csrf-admin-reply`      |
| Capstone: Full Web Shell (Enum → Brute / Reset → RCE)  | Capstone                   | Capstone   | `chain-web-shell`       |
| Capstone: Data Breach to XSS (UNION SQLi → Stored XSS) | Capstone                   | Capstone   | `chain-data-breach`     |
| Capstone: Stealth Leak (Login Bypass → Blind SQLi)     | Capstone                   | Capstone   | `chain-stealth-leak`    |


Each capstone consumes two or more individual labs as prerequisites. The cross-links are auto-generated from the `prerequisites` field in `lessons.php`, so a capstone page shows which labs feed into it, and every individual lab page shows which capstones build on top of it.

---

## Setup

### Prerequisites

- **XAMPP** (Apache + PHP 8.1+). Tested on PHP 8.2 under XAMPP on Windows.
- **PHP SQLite extension** - enabled by default in XAMPP, no action needed.
- **Python 3.10+** for the attack scripts in `scripts/`, with:
  - `requests`
  - `beautifulsoup4`
  ```bash
  pip install requests beautifulsoup4
  ```

### Install

1. Clone or copy the project so the folder lives at `C:\xampp\htdocs\CyberProject` (the URL path `/CyberProject/...` is hardcoded in the academy and the attack scripts; if you put it elsewhere, you'll need to update those references).
2. Start **Apache** in the XAMPP control panel. You do **not** need MySQL - the app uses SQLite files (`app.db`, `internal.db`) that already live in the project root.
3. Open the academy in a browser:
  ```
   http://localhost/CyberProject/academy/
  ```
4. Pick a lab and follow its instructions.

### First-run sanity check

- The academy index should list 8 individual labs and 3 capstones.
- The vulnerable site should be reachable at `http://localhost/CyberProject/login.php`.
- Log in with `carlos` / `1234` to confirm the seeded database is intact.

If anything fails, hit the **Reset databases** button on the academy index - it restores `app.db` and `internal.db` from the pristine copies in `academy/seed/`.

---

## Antivirus note

The web-shell capstone uses `scripts/web_shell.py`, which generates a PHP web shell + a `.htaccess` bypass to drop a `.jpg` that Apache will execute as PHP. The script **assembles its payload at runtime from byte fragments** specifically so that the malicious string never exists contiguously on disk and the file passes static signature scans (e.g. Windows Defender).

This is itself a teaching point in the [`chain-web-shell`](academy/chain-web-shell.php) lesson - real-world malware uses the same trick. If your AV still quarantines it, add a temporary exclusion for the `scripts/` directory while running the labs, or take Defender offline on a VM. Do not push the exclusion to production endpoints.

---

## Extending the academy

The academy is metadata-driven so adding a lab is a three-step recipe:

1. **Author the metadata.** Add a new entry to [`academy/lessons.php`](academy/lessons.php) with the slug, title, category, difficulty, target URL into the vulnerable site, and any scripts to display.
2. **Author the lesson page.** Copy [`academy/sqli-login.php`](academy/sqli-login.php) to `academy/<slug>.php` and rewrite the four sections (Theory, Your task, Start the lab, Reveal solution).
3. **(Optional) Wire cross-links.** If the new lab feeds into a capstone, add its slug to that capstone's `prerequisites` array. The "Used in" chip on the lab page is computed automatically - no manual back-link needed.

---

## License & disclaimer

Educational use only. No warranty. The author and the institution that supervised this project accept no liability for any misuse of the code in this repository. By cloning or running this project you accept full responsibility for staying within applicable laws and your institution's acceptable-use policy.