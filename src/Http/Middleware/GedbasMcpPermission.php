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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\GedbasMcpToolRequestHandlerInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
    

/**
 * Middleware to authorize access to GEDBAS MCP based on OAuth2 scopes
 */
class GedbasMcpPermission implements MiddlewareInterface
{

    /**
     * Authorize access to MCP
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        $scopes = Validator::attributes($request)->array('oauth_scopes');

        // Check if provided scopes allow GEDBAS MCP access
        if (empty(array_intersect(ScopeRepository::getGedbasMcpScopeIdentifiers(), $scopes))) {

            return new Response403('Insufficient permissions: Provided scope(s) insufficient to access MCP.');
        }

        // Set MCP tool interface attribute for GEDBAS
        $request = $request->withAttribute('mcp_tool_interface', GedbasMcpToolRequestHandlerInterface::class);

        //If authorization is successful, proceed to the next middleware/request handler
        return $handler->handle($request);
    }
}
