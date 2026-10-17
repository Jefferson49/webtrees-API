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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


/**
 * Provide the authorization server meta data
 *
 * Background: MCP servers MUST implement OAuth 2.0 Protected Resource Metadata
 */
class AuthorizationServerMetadata implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $meta_data = [
            'issuer' => Functions::getWebtreesUrl($request) . WebtreesApi::ROUTE_OAUTH_SERVER,
            'token_endpoint' => Functions::getWebtreesUrl($request) . WebtreesApi::ROUTE_OAUTH_TOKEN_ENDPOINT,
            'grant_types_supported' => ['client_credentials'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => array_keys(ScopeRepository::getScopeIdentifiers(false, true, false, true)),
        ];

        return api_response(json_encode($meta_data), StatusCodeInterface::STATUS_OK);
    }
}
