#!/usr/bin/env python3
"""
Language: Python
This script demonstrates how to authenticate using OAuth2 Client Credentials
and make an API request with the obtained access token.
"""

import requests
import json
import sys

# ---------- Configuration ----------
# Replace these values with your actual API endpoint and OAuth credentials
CLIENT_ID     = "..."
CLIENT_SECRET = "..."
TOKEN_URL     = "https://MY_SERVER/webtrees/oauth/token"
API_URL       = "https://MY_SERVER/webtrees/api/get-version"

# ---------- Step 1: Obtain Access Token ----------
# Use requests to request an access token using client credentials flow
try:
    response = requests.post(
        TOKEN_URL,
        headers={
            "User-Agent": "MyScript/1.0",
            "Content-Type": "application/x-www-form-urlencoded",
        },
        data={
            "grant_type": "client_credentials",
            "scope": "api_read",
            "client_id": CLIENT_ID,
            "client_secret": CLIENT_SECRET
        }
    )
    response.raise_for_status()
    
    # Parse the access token from the JSON response
    token_data = response.json()
    access_token = token_data.get('access_token')
    
    if not access_token:
        print("Failed to retrieve access token.")
        print(f"Response: {json.dumps(token_data, indent=2)}")
        sys.exit(1)
    
    print("Access token obtained successfully.")

except requests.exceptions.RequestException as e:
    print(f"Error requesting access token: {e}")
    sys.exit(1)

# ---------- Step 2: Make an API Request ----------
try:
    api_response = requests.get(
        API_URL,
        headers={
            "Authorization": f"Bearer {access_token}",
            "User-Agent": "MyScript/1.0",
            "Accept": "application/json"
        }
    )
    api_response.raise_for_status()
    
    # Print API response
    print("API Response:")
    print(json.dumps(api_response.json(), indent=2))

except requests.exceptions.RequestException as e:
    print(f"Error making API request: {e}")
    sys.exit(1)

# ---------- Usage ----------
# 1. Update CLIENT_ID, CLIENT_SECRET, TOKEN_URL, and API_URL with your parameters.
# 2. Ensure you have requests library installed: pip install requests
# 3. Make the script executable: chmod +x example_script.py
# 4. Run: python example_script.py
