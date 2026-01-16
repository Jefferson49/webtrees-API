<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 *                    <http://webtrees.net>
 *
 * CustomModuleManager (webtrees custom module):
 * Copyright (C) 2025 Markus Hemprich
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

use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
    

/**
 * Middleware to authorize access to MCP tools based on OAuth2 scopes
 */
class McpToolPermission implements MiddlewareInterface
{
    public static array $mcp_read_tools;
    public static array $mcp_write_tools;
    public static array $mcp_gedbas_tools;

    public function __construct()
    {
        self::$mcp_read_tools = [
            WebtreesApi::PATH_GET_RECORD,
            WebtreesApi::PATH_SEARCH_GENERAL,
            WebtreesApi::PATH_GET_TREES,
            WebtreesApi::PATH_GET_VERSION,
        ];

        self::$mcp_write_tools = [
            WebtreesApi::PATH_MODIFY_RECORD,
            WebtreesApi::PATH_ADD_UNLINKED_RECORD,
            WebtreesApi::PATH_ADD_CHILD_TO_INDI,
            WebtreesApi::PATH_ADD_CHILD_TO_FAMILY,
            WebtreesApi::PATH_ADD_PARENT_TO_INDI,
            WebtreesApi::PATH_ADD_SPOUSE_TO_INDI,
            WebtreesApi::PATH_ADD_SPOUSE_TO_FAMILY,
            WebtreesApi::PATH_LINK_SPOUSE_TO_INDI,
            WebtreesApi::PATH_LINK_CHILD_TO_FAMILY,
        ];

        self::$mcp_gedbas_tools = [
            WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE,
            WebtreesApi::PATH_GEDBAS_PERSON_DATA,
        ];
    }

    /**
     * Authorize access to MCP tools based on the provided OAuth2 scopes
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        $scopes    = Validator::attributes($request)->array('oauth_scopes');
        $tool_name = Validator::parsedBody($request)->string('name', McpProtocol::MCP_TOOL_NAME_DEFAULT);

        // Check if provided scopes allow API access
        if (in_array($tool_name, self::$mcp_read_tools) && !array_intersect($scopes, ScopeRepository::getMcpScopeIdentifiers())) {

            return new Response403('Insufficient permissions: Provided scope(s) insufficient to access MCP tool.');
        }
        elseif (in_array($tool_name, self::$mcp_write_tools) && !array_intersect($scopes, ScopeRepository::getMcpScopeIdentifiers())) {
            return new Response403('Insufficient permissions: Provided scope(s) insufficient to access MCP tool.');
        }
        elseif (in_array($tool_name, self::$mcp_gedbas_tools) && !array_intersect($scopes, ScopeRepository::getMcpScopeIdentifiers())) {
            return new Response403('Insufficient permissions: Provided scope(s) insufficient to access Gedbas MCP tool.');
        }

        // ToDo: No permission if tool name is unknown 

        //If authorization is successful, proceed to the next middleware/request handler
        return $handler->handle($request);
    }
}
