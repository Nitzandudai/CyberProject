import argparse

import requests


DEFAULT_BASE_URL = "http://localhost/CyberProject"
DEFAULT_PRODUCT_ID = 77  # Beer


def build_dummy_file():
    # This is intentionally not a real photo. It tests whether the server only
    # checks that a file was uploaded instead of validating the file contents.
    content = b"This is a harmless dummy upload, not a real ID photo.\n"
    return ("dummy_id_photo.jpg", content, "image/jpeg")


def login(session, base_url, username, password):
    login_url = f"{base_url}/login.php"
    response = session.post(
        login_url,
        data={
            "username": username,
            "password": password,
            "login_submit": "",
        },
        allow_redirects=False,
        timeout=10,
    )

    location = response.headers.get("Location", "")
    if response.status_code == 302 and "home.php" in location:
        print("[+] Login succeeded.")
        return True

    print("[-] Login failed. Check the username and password.")
    return False


def upload_dummy_id(session, base_url, product_id, quantity):
    products_url = f"{base_url}/products.php?cat=alcohol"
    files = {"id_photo": build_dummy_file()}
    data = {
        "add_id": str(product_id),
        "qty": str(quantity),
    }

    response = session.post(
        products_url,
        data=data,
        files=files,
        allow_redirects=False,
        timeout=10,
    )

    if response.status_code == 302:
        print("[+] Dummy file upload was accepted and the item was added to the cart.")
        print(f"[+] Redirected to: {response.headers.get('Location', '(no Location header)')}")
        return True

    print(f"[-] Upload request did not succeed. HTTP status: {response.status_code}")
    return False


def main():
    parser = argparse.ArgumentParser(
        description="Upload a harmless dummy file to test the alcohol ID upload flow."
    )
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL)
    parser.add_argument("--username", required=True)
    parser.add_argument("--password", required=True)
    parser.add_argument("--product-id", type=int, default=DEFAULT_PRODUCT_ID)
    parser.add_argument("--qty", type=int, default=1)
    args = parser.parse_args()

    session = requests.Session()
    if not login(session, args.base_url.rstrip("/"), args.username, args.password):
        return

    upload_dummy_id(session, args.base_url.rstrip("/"), args.product_id, args.qty)


if __name__ == "__main__":
    main()
