[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)](https://webtrees.net/download)

# webtrees API
A [webtrees](https://webtrees.net) 2.2 custom module to provide a web API for webtrees inlcuding an API for the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) for AI applications

##  Table of contents
This README file contains the following main sections:
+   [What are the benefits of this module?](#what-are-the-benefits-of-this-module)
+   [Screenshots](#screenshots)
+   [Requirements](#requirements)
+   [Security](#security)
+   [Implemented APIs ](#implemented-apis)
+   [API URLs](#api-urls)
*   [**How to use the module?**](#how-to-use-the-module)
    + [Configuration](#configuration)
    + [Access Rights](#access-rights)
    + [API URLs](#api-urls)
    + [Test webtrees API with Swagger API User Interface](#test-webtrees-api-with-swagger-api-user-interface)
    + [Test webtrees MCP API with MCP Inspector](#test-webtrees-mcp-api-with-mcp-inspector)
    + [Test webtrees MCP API with an AI chat](#test-webtrees-mcp-api-with-an-ai-chat)
+   [Trouble Shooting](#trouble-shooting)
+   [License](#license)
+   [Contributions and Copyrights](#contributions-and-copyrights)

## What are the benefits of this module?
+ Provide a web API for webtrees, which is compliant to the [OpenAPI Specification](https://swagger.io/resources/open-api/).
+ Provide a webtrees interface for AI applications using the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP).
+ Chat with webtrees using an AI application, which can connect to webtrees via the MCP protocol. 
+ The module includes a GEDCOM to JSON converter (based on GEDCOM-X) in order to make GEDCOM data more readible for AI applications.

## Screenshots
**Swagger UI for Web API**
![Screenshot](resources/img/screenshot_swagger_api_ui.jpg)
**Chat with AI Application**
![Screenshot](resources/img/screenshot_ai_chat_4.jpg)

## Requirements
+ [webtrees](https://webtrees.net/download): Version 2.2 or greater.
+ [PHP](https://www.php.net/): Version 8.4 or greater.

## Security
+ The web API and the MCP API require authorization with an API authorization key (bearer token).
+ Currently, all requests to the API only return **public** webtrees data.

## Implemented APIs
+ **POST/add-child-to-family**: Add a new INDI record for a child to a family.
+ **POST/add-unlinked-record**: Add a GEDCOM record, which is not linked to any other record. 
+ **POST/cli-command**: Execute a command on the webtrees command line interface (CLI)
+ **GET/get-record**: Retrieve the GEDCOM data for a record.
+ **POST/modify-record**: Modify the GEDCOM data of a record.
+ **GET/search-general**: Perform a general search in webtrees.
+ **GET/trees**: Get a list of the available trees.
+ **GET/version**: Get the webtrees version.

## How to use the module?

### Configuration
+ Go to "Control Panel/All Modules" and find the "webtrees API" module
+ Go to the module settings
+ Generate or enter an API authorization key
+ Save

### Access Rights
The **access rights for API/MCP requests can be defined by selecting a "technical user"** in the control panel. Any tree data requested via API/MCP is limited to the access rights of the selected (technical) user.

It is recommended to create a separate (technical) user, which is only used to define the API/MCP access rights. The maximum access role, wich is allowed for the (technical) user is limited to an "Editor". "Moderators" or "Administrators" are denied access during API/MCP requests.

If new records etc. are created with API/MCP, the data is created with the specified (technical) user. In order to have control about the changed data, it is not allowed that the selected (technical) user has "Automatically accept changes" activated. This ensures that a moderator can always reject unintended changes during a review of pending changes.

Currently, it is not allowed to use CLI commands with MCP.

### API URLs
The web API URL and the MCP URL are shown in the control panel.

### Test webtrees API with Swagger API User Interface
+ Press the "**Test webtrees API**" button to open the Swagger API User Interface
    + Authorize with the API authorization key, which is defined in the control panel
    + Test the webtrees API with the Swagger API User Interface

![Screenshot](resources/img/screenshot_swagger_api_ui.jpg)

### Test webtrees MCP API with MCP Inspector
+ Install MCP Inspector(https://modelcontextprotocol.io/docs/tools/inspector)
+ Enter the webtrees MCP URL, which is defined in the control panel
+ Configure the authorization:
    + Select "Custom authorization"
    + Use the following authorization: "Bearer API_AUTHORIZATION_KEY" (which is defined in the control panel)
+ Press "Connect"
+ Select Tools / List Tools
+ Select a tool, e.g. "get-version"
+ Press "Run Tool" 

![Screenshot](resources/img/screenshot_mcp_inspector.jpg)

### Test webtrees MCP API with an AI chat
+ Make the MCP configuration available to your AI application 
    + MCP server: URL, which is shown in the control panel
    + Authorization based on the API authorization key, which is defined in the control panel

#### Configure an AI chat with GitHub Copilot in VS Code
+ Configure your AI agent in VS Code (e.g. GitHub copilot)
+ Open an AI chat window
+ Configure the AI agent
+ Add the API authorization key to the environment variables, e.g. as "MCP_API_AUTHORIZATION_KEY" (which is defined in the control panel)
+ Add an MCP configuration file to your VS code environment (e.g. workspace or user)

```JSON
{
	"servers": {
		"webtrees-mcp-server": {
			"url": "https://WEBTREES_SERVER/mcp",
			"type": "http",
            "headers": {
                "Authorization": "Bearer ${env:MCP_API_AUTHORIZATION_KEY}"
            }            
		}
	}
}
```

+ In the agent chat window, select "Configure Tools". In the tool list, the MCP server should be listed now.

![Screenshot](resources/img/screenshot_ai_chat_1.jpg)
![Screenshot](resources/img/screenshot_ai_chat_2.jpg)
![Screenshot](resources/img/screenshot_ai_chat_3.jpg)
![Screenshot](resources/img/screenshot_ai_chat_4.jpg)

#### Configure an AI chat in Claude for Desktop
+ Install [Claude for Desktop](https://claude.com/download)
+ Configure the Claude AI in Claude for Desktop
+ Configure webtrees as remote MCP server in Claude for Desktop, see [Claude documentation](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp)

#### Create an AI agent with n8n
+ Install [n8n](https://n8n.io/)
+ Create a n8n workflow with an AI agent, a large language model, and a MCP interface to webtrees

![Screenshot](resources/img/n8n_agent.jpg)

## Trouble Shooting
+ If **401 errors** occur and authorization headers might not be forwarded:
    Add the following line to the .htaccess file: 
    ```
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
    ```

## License
+ [GNU General Public License, Version 3](LICENSE.md)

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/.

## Contributions and Copyrights
+ webtrees
    + [webtrees](https://webtrees.net): online genealogy
    + Copyright (c) 2025 [webtrees development team](http://webtrees.net)
+ php-gedcom
    + [php-gedcom](https://github.com/liberu-genealogy/php-gedcom)
