#!/bin/bash  
# Language: bash  
# This script demonstrates how to authenticate using OAuth2 Client Credentials  
# and make an API request with the obtained access token.  
  
# ---------- Configuration ----------  
# Replace these values with your actual API endpoint and OAuth credentials  
CLIENT_ID="..."
CLIENT_SECRET="..."
TOKEN_URL="https://MY_SERVER/webtrees/oauth/token"
API_URL="https://MY_SERVER/webtrees/api/get-version"

# ---------- Step 1: Obtain Access Token ----------  
# Use curl to request an access token using client credentials flow  
response=$(curl -s -X POST $TOKEN_URL \
-H "Content-Type: application/x-www-form-urlencoded" \
-H "User-Agent: MyScript/1.0" \
-d "grant_type=client_credentials" \
-d "scope=api_read" \
-u "$CLIENT_ID:$CLIENT_SECRET")

# Extract the line containing access_token 
access_token=$(echo "$response" | sed -n 's/.*"access_token"[ ]*:[ ]*"\([^"]*\)".*/\1/p')

if [ "$access_token" == "null" ] || [ -z "$access_token" ]; then
echo "Failed to retrieve access token."
echo "Response: $response"
exit 1
fi

# ---------- Step 2: Make an API Request ----------  
api_response=$(curl -s -X GET $API_URL \
-H "Authorization: Bearer $access_token" \
-H "User-Agent: MyScript/1.0" \
-H "Accept: application/json")
# Print API response  
echo "API Response:"
echo $api_response

# ---------- Usage ----------  
# 1. Update CLIENT_ID, CLIENT_SECRET, TOKEN_URL, and API_URL with your parameters.  
# 2. Make the script executable: chmod +x example_script.sh  
# 3. Run: ./example_script.sh
