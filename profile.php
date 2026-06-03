<?php
session_start();

/* AUTHENTICATION CHECK - same pattern as the rest of the site */
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$flash       = "";
$flash_type  = ""; // "ok" | "err"

/*
 * VULNERABLE PASSWORD CHANGE HANDLER
 * ----------------------------------
 * The only thing this endpoint checks is "is there a session?".
 * No CSRF token, no Origin/Referer check, no SameSite cookie,
 * and no current-password re-prompt. That makes the user's own
 * browser a confused deputy for any cross-origin page.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['new_password'])) {
    $new_password = (string)$_POST['new_password'];

    if ($new_password === '') {
        $flash = "New password cannot be empty.";
        $flash_type = "err";
    } else {
        $stmt = $db->prepare("UPDATE users SET password = :p WHERE username = :u");
        $stmt->execute([
            ':p' => $new_password,
            ':u' => $_SESSION['username'],
        ]);
        $flash = "Password updated successfully.";
        $flash_type = "ok";
    }
}

$stmt = $db->prepare("SELECT username, email FROM users WHERE username = :u");
$stmt->execute([':u' => $_SESSION['username']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => $_SESSION['username'], 'email' => ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Details - Anan Super Market</title>
    <link rel="stylesheet" href="assets/styles.css?v=1">
    <style>
        .profile-wrap {
            max-width: 640px;
            margin: 28px auto;
            padding: 28px 32px;
            background: #fff;
            border: 1px solid #e6e8eb;
            border-radius: 16px;
        }
        .profile-wrap h1 { margin: 0 0 6px; font-size: 1.5rem; }
        .profile-wrap .sub { color: #64748b; margin: 0 0 22px; }
        .profile-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 10px 16px;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
        .profile-grid .k { color: #64748b; }
        .profile-grid .v { color: #0f172a; font-weight: 600; }
        .profile-section-title {
            margin: 24px 0 10px;
            font-size: 1.05rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 18px;
        }
        .profile-form input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d9dde2;
            border-radius: 10px;
            background: #f8fafc;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .btn-primary {
            background: var(--primary, #5C9B81);
            color: #fff;
            border: 0;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Modal */
        .pw-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .pw-modal-backdrop.is-open { display: flex; }
        .pw-modal {
            background: #fff;
            border-radius: 16px;
            padding: 24px 26px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        }
        .pw-modal h2 {
            margin: 0 0 6px;
            font-size: 1.15rem;
        }
        .pw-modal .sub {
            color: #64748b;
            margin: 0 0 16px;
            font-size: 0.9rem;
        }
        .pw-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 6px;
        }
        .flash {
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .flash.ok  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .flash.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body class="home-page">
    <?php include 'header.php'; ?>

    <div class="profile-wrap">
        <h1>Personal Details</h1>
        <p class="sub">Manage your account information.</p>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <div class="k">Username</div>
            <div class="v"><?= htmlspecialchars($me['username']) ?></div>
            <div class="k">Email</div>
            <div class="v"><?= htmlspecialchars($me['email']) ?></div>
        </div>

        <h2 class="profile-section-title">Password</h2>
        <button type="button" class="btn-primary" id="open-pw-modal">Change password</button>
    </div>

    <div class="pw-modal-backdrop" id="pw-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="pw-modal-title">
        <div class="pw-modal">
            <h2 id="pw-modal-title">Change password</h2>
            <p class="sub">Enter a new password for your account.</p>
            <form class="profile-form" method="POST" action="profile.php">
                <input type="password" name="new_password" placeholder="new password" required autofocus>
                <div class="pw-modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-pw-modal">Cancel</button>
                    <button type="submit" class="btn-primary">Update password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var backdrop = document.getElementById('pw-modal-backdrop');
            var openBtn  = document.getElementById('open-pw-modal');
            var cancel   = document.getElementById('cancel-pw-modal');
            function open()  { backdrop.classList.add('is-open'); }
            function close() { backdrop.classList.remove('is-open'); }
            openBtn.addEventListener('click', open);
            cancel.addEventListener('click', close);
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) close();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
        })();
    </script>
</body>
</html>
