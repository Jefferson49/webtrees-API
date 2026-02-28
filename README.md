[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)](https://webtrees.net/download)

# webtrees API
A [webtrees](https://webtrees.net) 2.2 custom module to provide a web API for webtrees inlcuding an API for the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) for AI applications

##  Table of contents
This README file contains the following main sections:
+   [What are the benefits of this module?](#what-are-the-benefits-of-this-module)
+   [Screenshots](#screenshots)
+   [Requirements](#requirements)
+   [Security](#security)
+   [Implemented APIs](#implemented-apis)
+   [Installation](#installation)
*   [**How to use the module?**](#how-to-use-the-module)
    + [Configuration](#configuration)
        + [Configure Private/Public Keys](#configure-privatepublic-keys)
        + [Configure Clients](#configure-clients)
        + [Technical Users](#technical-users)
        + [Access Tokens](#access-tokens)
    + [API/MCP/OAuth2 URLs](#apimcpoauth2-urls)
    + [Authorization Flow with the OAuth2 Client Credentials Grant](#authorization-flow-with-the-oauth2-client-credentials-grant)
        + [Example Scripts for Authorization Flow](#example-scripts-for-authorization-flow)
    + [Authorization with Bearer Token](#authorization-with-bearer-token)
    + [Examples for Authorization with Different Client Applications](#examples-for-authorization-with-different-client-applications)
    + [Test webtrees API with Swagger API User Interface](#test-webtrees-api-with-swagger-api-user-interface)
    + [MCP](#mcp)
        + [Test webtrees MCP API with MCP Inspector](#test-webtrees-mcp-api-with-mcp-inspector)
        + [Test webtrees MCP API with an AI Chat](#test-webtrees-mcp-api-with-an-ai-chat)
        + [Configure an AI chat with GitHub Copilot in VS Code](#configure-an-ai-chat-with-github-copilot-in-vs-code)
        + [Configure an AI chat in Claude for Desktop](#configure-an-ai-chat-in-claude-for-desktop)    
        + [Create an AI Agent with n8n](#create-an-ai-agent-with-n8n)
+   [Trouble Shooting](#trouble-shooting)
+   [Translation](#translation)
+   [License](#license)
+   [Contributions and Copyrights](#contributions-and-copyrights)

## What are the benefits of this module?
+ Provide a Web API for webtrees, which is compliant to the [OpenAPI Specification](https://swagger.io/resources/open-api/).
+ Provide a webtrees interface for AI applications using the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP).
+ Chat with webtrees using an AI application, which can connect to webtrees via the MCP protocol.
+ Integrate webtrees into AI applications via the MCP protocol, e.g. for data comparison and data exchange with other genealogic tools and databases. Examples for GEDBAS and Gramps are attached to the module resources.
+ The module includes a GEDCOM to JSON converter (based on GEDCOM-X) in order to make GEDCOM data more readible for AI applications.

## Screenshots
**Swagger UI for Web API**  
<img src="resources/img/screenshot_swagger_api_ui.jpg" width="300"/>

**Chat with an AI Application using MCP**  
<img src="resources/img/screenshot_ai_chat_4.jpg"/>

## Requirements
+ [webtrees](https://webtrees.net/download): Version 2.2 or greater
+ [PHP](https://www.php.net/): Version 8.4 or greater
+ PHP extension "openssl"

## Security
+ For securing the access to the API, the module uses [OAuth2](https://en.wikipedia.org/wiki/OAuth) authorization based on the [Client Credentials Grant](https://en.wikipedia.org/wiki/OAuth).
+ The module code is based on the PHP OAuth2 implementation of the [League/oauth2-sever](https://oauth2.thephpleague.com/).
+ Access to the webtrees data is be controlled by selecting a webtrees user. All API requests are limited to the webtrees rights, which are assigned to this user.
+ Any write request is rejected if "automatically accept changes" is activated. This ensures that a moderator can always reject unintended changes during a review of pending changes.
+ All read operations with MCP enforce privacy settings in order to prevent AI access to living individuals' data. If the privacy settings for a tree do not fulfill certain minimum requirements, read access is denied.
+ By using OAuth2 scopes, webtrees users, and token expiration times, the access to the API can be controlled on a detailed granularity.
+ It is highly recommended to use HTTPS in order to ensure the encryption of client credentials and access tokens within API requests. HTTPS can be activated by changing "base_url" in the "config.ini.php".

## Implemented API
|<div style="width:250px">API</div> |Description|
|:----------|:----------|
|POST/add-child-to-family|Add a new INDI record for a child to a family.|
|POST/add-child-to-individual|Add a new INDI record for a child to a parent.|
|POST/add-parent-to-individual|Add a new INDI record for a parent to an indivudal.|
|POST/add-spouse-to-family|Add a new INDI record for a spouse to a family.|
|POST/add-spouse-to-individual|Add a new INDI record for a spouse to an indivudal.|
|POST/add-unlinked-record|Add a GEDCOM record, which is not linked to any other record. |
|POST/cli-command|Execute a command on the webtrees command line interface (CLI).|
|GET/get-record|Retrieve the GEDCOM data for a record (as GEDCOM file, GEDCOM record, GEDCOM-X file, or JSON).|
|POST/link-child-to-family|Link an existing INDI record as a child to a family.|
|POST/link-spouse-to-individual|Link an existing INDI record as a spouse to an individual.|
|POST/modify-record|Modify the GEDCOM data of a record.|
|GET/search-general|Perform a general search in webtrees.|
|GET/trees|Get a list of the available trees.|
|GET/version|Get the webtrees version.|

## Installation
+ Install and use [Custom Module Manager](https://github.com/Jefferson49/CustomModuleManager) for an easy and convenient installation of webtrees custom modules.
+ Open the Custom Module Manager view in webtrees, select "webtrees-API", and click on the "Install Module" button.

**Manual installation**:
+ Download the [latest release](https://github.com/Jefferson49/webtrees-API/releases/latest) of the module by downloading the "**webtrees-api_v\*.zip**" file from "Assets". Do not download "Source code.zip".
+ Unzip the downloaded file and copy the included folder "api-mcp" into the "module_v4" folder of your webtrees installation
+ Configure the module, see chapter [Configuration](#configuration).

## How to use the module?

### Configuration
+ Go to "Control Panel/All Modules" and find the "webtrees API" module
+ Go to the module settings
+ Configure keys, clients, and access tokens like decribed below
+ Check and resolve any errors, which are shown. Most probably, API/MCP access will not be possible with unresolved errors.

#### Configure Private/Public Keys
+ The OAuth2 server needs a pair of private/public keys to create access tokens and to verify access
+ The module creates a default folder for the private/public keys within the webtrees data directory

  <img src="resources/img/path_for_keys.jpg"/>
  
+ You can change the key directory to a different path (and press the "**save**" button):
    + It can increase the security if you move the keys directory outside of the webtrees installation.
    + If you keep the keys within the webtrees installation, it is highly recommended to use a path within the webtrees data directory, because webtrees protects unauthorized access to this folder. If you choose a folder outside of the webtrees data folder, the keys might be unprotected against unauthorized access.
+ The module tries to generate a private/public key pair with the "openssl" extension and stores them into the keys folder

  <img src="resources/img/private_public_key_files.jpg"/>

+ If the key generation fails, you might need to take one of the following steps:
    + Check the error message in the module settings and try to resolve the error.
    + Check the configuration of the "openssl" extension in your PHP installation, because the "openssl" extension is used to generate the keys.
    + [Manually create a pair of keys](https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys) and put them into the keys folder. The filenames need to be "private.key" and "public.key".
+ You can create new private/public keys by pressing the "**Create new keys**" button. Please be aware that this will also reset (i.e. revoke) all existing access tokens.

#### Configure Clients
A client is a software application that sends requests to and receives responses from the API. Clients can be configured in the module settings. By assigning scopes and a (technical) webtrees user, the access rights of the client can be configured.

To configure a client:
+ Press the "**Add client**" button to create a new client
+ Provide a client name, which is free of choice
+ Select the scopes, which specify the access right to the API and MCP. The following scopes can be chosen:  

    |Scope|Description|
    |:----------|:----------|
    |api_read_privacy|Allows API requests to read data from webtrees. Read access is strictly limited to data, which is not protected by privacy settings. If the privacy settings for a tree do not fulfill certain minimum requirements, read access is denied.|
    |api_read_user|Allows API requests to read data from webtrees. Data access will be limited to the user rights of the technical user assigned.|
    |api_write|Allows API requests to write or modify data in webtrees. Data access will be limited to the user rights of the technical user assigned. Write requests are rejected if "Automatically accept changes" is activated.|
    |api_cli|Allows API requess to the webtrees command line interface (CLI)|
    |mcp_read_privacy|Allows to use MCP tools to read data from webtrees. Read access is strictly limited to data, which is not protected by privacy settings. If the privacy settings for a tree do not fulfill certain minimum requirements, read access is denied.|
    |mcp_write|Allows to use MCP tools to write or modify data in webtrees. Data access will be limited to the user rights of the technical user assigned. Write requests are rejected if "Automatically accept changes" is activated.|
    |mcp_gedbas|Allows to use MCP tools to search and retrieve data from the [GEDBAS database](https://gedbas.genealogy.net/)|
+ Assign a technical webtrees user to the client, see decription below. The technical user specifies the access rights for webtrees trees during API/MCP requests.
+ Take care to copy and **store the client secret**, which is shown after the client is created. **The client secret cannot be shown a seccond time**. If not stored, a new client needs to be created.

<br>  
<img src="resources/img/client_credentials.jpg"/>

#### Technical Users
+ The **access rights for API/MCP requests can be specified by assigning a "technical user"** to OAuth2 clients in the module settings. Any tree data requested via API/MCP is limited to the access rights of the assigned (technical) user.
+ The technical user is a regular webtrees user, which can be created and configured in the webtrees control panel.
+ It is recommended to create a separate (technical) user, which is only used to specify the access rights for API/MCP.
+ The maximum access role, which is allowed for the technical user is limited to an "Editor". "Moderators" or "Administrators" are denied access during API/MCP requests.
+ If new records etc. are created with API/MCP, the data is created with the specified technical user. In order to have control over the changed data, it is not allowed that the selected technical user has "automatically accept changes" activated. This ensures that a moderator can always reject unintended changes during a review of pending changes.

#### Access Tokens
Within the OAuth2 authorization to the API, access tokens serve as the key cryptographic credential enabling secure and authorized access. In API requests, access tokens are provided as bearer tokens in the request header. Specified scopes, user rights, and expiration times are encoded within the access token and will be decoded during the authorization process.

There are two different ways how access tokens can be created:
1. Requesting a token by using the client credentials. This is the key procedure of the OAuth2 Client Credentials Grant and the most secure option.
2. Creating an access token in the module settings.

If using the first option, no further configuration is needed; a token is generated and returned as a result of a client request to the API.  

If using the second option, tokens can be added by pressing the "**Create access token**" button:
+ Select a subset of the overall scopes, which are assigned to the client.
+ Select the expiration interval of the access token. From a security point of view, it is preferable to use short intervals.
+ Take care to copy and **store the access token string**, which is shown after the token is created. **The access token string cannot be shown a seccond time**. If not stored, a new token needs to be created.

<br>
<img src="resources/img/access_tokens.jpg"/>

### Authorization Flow with the OAuth2 Client Credentials Grant
If using the client credential grant, the authorization process has three steps:
1. The client sends a POST request with the client credentials and the scope to the authorization server
2. The authorization server will respond with a JSON object containing the access token
3. The client sends a request to the API by using the access token as a bearer token within an authorization header

#### Step 1: Request with client credentials and scope
```bash
curl -X POST "https://WEBTREES-URL/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "User-Agent: MyScript/1.0" \
  -d "grant_type=client_credentials" \
  -d "scope=api_read" \
  -u "CLIENT_ID:CLIENT_SECRET"
```

#### Step 2: Authorization server responds with access token
```JSON
{
    "token_type":"Bearer",
    "expires_in":3600,
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}  
```

#### Step 3: API request
```bash
curl -X GET "https://WEBTREES-URL/api/get-version" \
  -H 'accept: application/json' \
  -H "User-Agent: MyScript/1.0" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

#### Example Scripts for Authorization Flow
Example scripts for the complete authorization flow are provdided in the following folder: [/resources/scripts](https://github.com/Jefferson49/webtrees-API/tree/master/resources/scripts)
+ [bash example script](https://github.com/Jefferson49/webtrees-API/tree/master/resources/scripts/example_script.sh)
+ [Python example script](https://github.com/Jefferson49/webtrees-API/tree/master/resources/scripts/example_script.py)

### Authorization with Bearer Token
If manually creating an access token (for an existing client) like decribed above, the token can be used as a bearer token in the authorization header of a HTTP request.

```bash
curl -X GET "https://WEBTREES-URL/api/get-version" \
  -H "accept: application/json" \
  -H "User-Agent: MyScript/1.0" \
  -H "Authorization: Bearer ACCESS-TOKEN"
```

### Examples for Authorization with Different Client Applications
|Application|Description of Application|Client Configuration|Creation of Access Token|Authorization Tpye|Descríption of Authorization|
|:----------|:-------------------------|:-------------------|:-----------------------|:-----------------|:---------------------------|
|Script, e.g. [example scripts](#example-scripts-for-authorization-flow)|Script with client credentials workflow|In module settings|Automatic|Authorization flow with client credentials grant|Request an access token with the client credentials. Receive an access token. Use the access token as a bearer token within the header of an API request.|
|Simple script|Script using single API request|In module settings|Manually, in module settings|Bearer token|Use the access token as a bearer token within the header of an API request.|
|Postman|API test tool|In module settings|Manually, in module settings|Authorization flow with client credentials grant (manually)|Provide client_id and client_secret within the body of a POST request to the OAuth2 token URL. Copy the received access token. Use the access token as a bearer token within the authorization for an API request.|
|MCP Inspector|MCP test tool|In module settings|Manually, in module settings|Bearer token|Provide an access token within Authentification / Custom Headers / Authorization / "Bearer "|
|n8n|AI workflow modeling tool|In module settings|Automatic|Authorization flow with client credentials grant|Select "Authorization: MCP OAuth2". Select "Credential for MCP OAuth2 API: OAuth2 client credentials". Configure "Client ID" and "Client Credentials".|


### API / MCP / OAuth2 Token URLs
The URLs for the API, MCP, and OAuth2 are shown in the module settings. Pretty URLs are shown in parallel.

### Test webtrees API with Swagger API User Interface
+ Select a technical user to specifiy the access rights to trees in webtrees (see description in the corresponding chapter)
+ Press the "**Test webtrees API**" button to open the Swagger API User Interface, see screenshot above.

### MCP
Provide the MCP URL to an AI application.

Please note: For secuity reasons, the module **does not allow to use CLI commands** with MCP.

### Test webtrees MCP API with MCP Inspector
+ Install [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector)
+ Enter the webtrees MCP URL, which is shown in the module settings
+ Manually create an access token in the module settings like described above
+ Configure the authorization:
    + Select "Custom authorization"
    + Use the following settings for "Authentification":
        + Custom Headers / Authorization
        + "Bearer <ACCESS TOKEN>" (with ACCESS TOKEN created in module settings)
        + <img src="resources/img/mcp_inspector_authorization.jpg" width="200"/>
+ Press "Connect"
+ Select Tools / List Tools
+ Select a tool, e.g. "get-version"
+ Press "Run Tool" 

![Screenshot](resources/img/screenshot_mcp_inspector.jpg)

### Test webtrees MCP API with an AI chat
+ Make the MCP configuration available to your AI application
    + MCP URL: Shown in the module settings
    + Authorization (depending on the code or tool environment of the AI application):
        1. By using the OAuth2 Clients Credentials Grant. Preferred option, see description above.
        2. Use an access token, which is manually created in the module settings, as bearer token within an authorization header.

#### Configure an AI chat with GitHub Copilot in VS Code
+ Configure an AI agent in VS Code (e.g. GitHub copilot)
+ Open an AI chat window
+ Configure the AI agent
+ Add the API access token (see description above) to the environment variables, e.g. as "MCP_ACCESS_TOKEN"
+ Add a MCP configuration file "**mcp.json**" to your VS code environment (e.g. workspace or user) with the following content:

```JSON
{   
	"servers": {
		"webtrees-mcp-server": {
			"url": "https://MY_SERVER/webtrees/mcp",
			"type": "http",
            "headers": {
                "Authorization": "Bearer ${env:MCP_ACCESS_TOKEN}"
            }            
		}
	}
}
```

+ Alternatively - however less secure - you can directly insert the access token in the JSON configuration file.
+ In the agent chat window, select "Configure Tools". In the tool list, the MCP server should be listed now.

**Example for AI chat with webtrees using a GitHub-Copilot chat window in VS code**
<img src="resources/img/screenshot_ai_chat_2.jpg"/>

#### Configure an AI chat in Claude for Desktop
+ Install [Claude for Desktop](https://claude.com/download)
+ Configure the Claude AI in Claude for Desktop
+ Configure webtrees as remote MCP server in Claude for Desktop, see [Claude documentation](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp)

#### Create an AI Agent with n8n
+ [n8n](https://n8n.io/) is a modeling tool to design AI automation workflows
+ Install [n8n](https://n8n.io/)
+ Create a n8n workflow with an AI agent, a large language model, and a MCP interface to webtrees. Example workflows for n8n can be found in the following folder: [/resources/n8n/workflows](https://github.com/Jefferson49/webtrees-API/tree/master/resources/n8n/workflows).
+ Configure the prompt of the AI agent. Examples prompts for n8n can be found in the following folder: [/resources/n8n/prompts](https://github.com/Jefferson49/webtrees-API/tree/master/resources/n8n/prompts) .

![Screenshot](resources/img/n8n_agent.jpg)

## Trouble Shooting
+ If **401 errors** occur and authorization headers might not be forwarded:
    Add the following line to the .htaccess file of your server: 
    ```
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
    ```
+ If **406 errors** occur and you cannot connect to the webtrees API, your client might be blocked by the "bad bot blocker" of webtrees. In order to avoid being blocked, try to add a description of your agent to the header of your requests:

    ```
    -H "User-Agent: MyScript/1.0"
    ```
## Translation
Currently, the following languages are available:
+ Catalan
+ Dutch
+ English
+ German
+ Spanish

You can help to translate this module. The language files are available on [POEditor](https://poeditor.com/join/project/PpVneEtQMs), where you can update or add a language.

Alternatively, you can directly edit the .po translation text files, which can be found in [/resources/lang/](resources/lang). You can use a specific editor like [Poedit](https://poedit.net/) or a text editor like [notepad++](https://notepad-plus-plus.org/) to work on translations and provide them in the [GitHub repository](https://github.com/Jefferson49/CustomFilesystem) of the module. You can do this via a pull request (if you know how to do), or by opening a new issue and attaching a .po file. 

Updated translations will be included in the next release of this module.

## License
+ [GNU General Public License, Version 3](LICENSE.md)

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/.

## Contributions and Copyrights
+ webtrees
    + [webtrees](https://webtrees.net): online genealogy
    + Copyright (c) 2026 [webtrees development team](http://webtrees.net)

+ liberu-genealogy/php-gedcom
    + [php-gedcom](https://github.com/liberu-genealogy/php-gedcom)
    + Gedcom 5.5.1 and Gedcom X - Reading and writing for PHP 8.4
