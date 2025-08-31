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
 * webtrees MCP server
 *
 * A webtrees(https://webtrees.net) 2.2 custom module to provide an MCP API for webtrees
 * 
 */


declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\McpApi\Http\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Jefferson49\Webtrees\Module\McpApi\McpApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to restrict access to administrators.
 */
class AuthMcpApi implements MiddlewareInterface
{
    /**
     * A middleware to authorize access to the MCP API
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {    
        if (!$this->isAuthorized($request)) {
            return response('Authorization failed.', StatusCodeInterface::STATUS_UNAUTHORIZED);
        }

        return $handler->handle($request);
    }

	/**
     * Whether a request is authorized
     * 
     * @param ServerRequestInterface $request
     *
     * @return bool
     */	
    public function isAuthorized(ServerRequestInterface $request): bool
    {
        $bearer_token = str_replace('Bearer ','', $request->getHeader('Authorization')[0] ?? '');

        $module_service = New ModuleService();
        /** @var McpApi $mcp_api To avoid IDE warnings */
        $mcp_api = $module_service->findByName(module_name: McpApi::activeModuleName());

        $secret_mcp_api_token = $mcp_api->getPreference(McpApi::PREF_MCP_API_TOKEN, '');

        //Do not authorize if no secret token is configured or token is too short
        if ($secret_mcp_api_token === '' OR strlen($secret_mcp_api_token) < McpApi::MINIMUM_API_KEY_LENGTH) {
            return false;
        }
        //Authorize if no hashing used and token is valid
        elseif (!boolval($mcp_api->getPreference(McpApi::PREF_USE_HASH, '0')) && $bearer_token === $secret_mcp_api_token) {
            return true;
        }
        //Authorize if hashing used and token fits to hash
        if (boolval($mcp_api->getPreference(McpApi::PREF_USE_HASH, '0')) && password_verify($bearer_token, $secret_mcp_api_token)) {
            return true;
        }

        return false;
    }
}
