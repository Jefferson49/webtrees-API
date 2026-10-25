<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 *                    <http://webtrees.net>
 *
 * CustomModuleManager (webtrees custom module):
 * Copyright (C) 2026 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * webtrees API
 *
 * A webtrees(https://webtrees.net) 2.2 custom module to provide an API for webtrees
 *
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware;

use Fisharebest\Webtrees\Auth;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;


/**
 * Authorization of MCP tool calls based on OAuth2 scopes.
 *
 * Since the MCP protocol is handled by the MCP SDK (see McpProtocol), the tool name is not known
 * to the middleware pipeline any more. Therefore, the authorization is executed for every tool call
 * right before the request handler of the MCP tool is called.
 */
class McpToolPermission
{
    public const array MCP_READ_TOOLS = [
        WebtreesApi::PATH_GET_RECORD,
        WebtreesApi::PATH_SEARCH_GENERAL,
        WebtreesApi::PATH_GET_TREES,
        WebtreesApi::PATH_GET_VERSION,
    ];

    public const array MCP_WRITE_TOOLS = [
        WebtreesApi::PATH_MODIFY_RECORD,
        WebtreesApi::PATH_ADD_UNLINKED_RECORD,
        WebtreesApi::PATH_ADD_CHILD_TO_INDI,
        WebtreesApi::PATH_ADD_CHILD_TO_FAMILY,
        WebtreesApi::PATH_ADD_PARENT_TO_INDI,
        WebtreesApi::PATH_ADD_SPOUSE_TO_INDI,
        WebtreesApi::PATH_ADD_SPOUSE_TO_FAMILY,
        WebtreesApi::PATH_DELETE_RECORD,
        WebtreesApi::PATH_LINK_SPOUSE_TO_INDI,
        WebtreesApi::PATH_LINK_CHILD_TO_FAMILY,
    ];

    public const array MCP_GEDBAS_TOOLS = [
        WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE,
        WebtreesApi::PATH_GEDBAS_PERSON_DATA,
    ];

    /**
     * Authorize access to an MCP tool based on the provided OAuth2 scopes
     *
     * @param string        $tool_name
     * @param array<string> $scopes
     *
     * @return string|null  Null if access is granted, otherwise an error message
     */
    public static function authorize(string $tool_name, array $scopes): ?string
    {
        // MCP read access
        if (in_array($tool_name, self::MCP_READ_TOOLS) && array_intersect($scopes, [ScopeRepository::SCOPE_MCP_READ_PRIVACY, ScopeRepository::SCOPE_MCP_READ_MEMBER])) {

            // If mcp_read_privacy scope only, we logout the user and validate the tree privacy settings
            if (in_array(ScopeRepository::SCOPE_MCP_READ_PRIVACY, $scopes) && !in_array(ScopeRepository::SCOPE_MCP_READ_MEMBER, $scopes)) {
                Auth::logout();
            }

            return null;
        }

        // MCP write access
        if (in_array($tool_name, self::MCP_WRITE_TOOLS) && array_intersect($scopes, [ScopeRepository::SCOPE_MCP_WRITE])) {

            return null;
        }

        // GEDBAS MCP access
        if (in_array($tool_name, self::MCP_GEDBAS_TOOLS) && array_intersect($scopes, [ScopeRepository::SCOPE_MCP_GEDBAS])) {

            return null;
        }

        return 'Insufficient permissions: Provided scope(s) insufficient to access MCP tool.';
    }
}
