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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Mcp\Exception\InvalidArgumentException;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


/**
 * Provide the ressource meta data
 *
 * Background: MCP servers MUST implement OAuth 2.0 Protected Resource Metadata
 */
class ProtectedResourceMetadataHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route        = Validator::attributes($request)->route();
        $request_path = version_compare(Webtrees::VERSION, '2.3.0', '>=') ? $route->url : $route->path;
        $webtrees_url = WebtreesApi::getSecureWebtreesUrl($request);

        $resource_path = match ($request_path) {
            WebtreesApi::ROUTE_API,
            WebtreesApi::ROUTE_OAUTH_PROTECTED_RESOURCE_API => WebtreesApi::ROUTE_API,
            WebtreesApi::ROUTE_MCP,
            WebtreesApi::ROUTE_OAUTH_PROTECTED_RESOURCE_MCP => WebtreesApi::ROUTE_MCP,
            default => null,
        };

        if ($resource_path === null) {
            return api_response('Protected resource metadata not found.', StatusCodeInterface::STATUS_NOT_FOUND);
        }

        /** @var WebtreesApi $webtrees_api */
        $webtrees_api          = Registry::container()->get(WebtreesApi::class);
        $allow_mcp_read_member = boolval($webtrees_api->getPreference(WebtreesApi::PREF_ALLOW_MCP_READ_MEMBER, '0'));
        $scopes_supported      = $resource_path === WebtreesApi::ROUTE_API
            ? ScopeRepository::getScopeIdentifiers(true, false, false, false)
            : ScopeRepository::getScopeIdentifiers(false, true, $allow_mcp_read_member, true);

        try {
            $meta_data = new ProtectedResourceMetadata(
                resource:              $webtrees_url . $resource_path,
                authorizationServers:  [$webtrees_url . WebtreesApi::ROUTE_OAUTH_SERVER],
                scopesSupported:       array_keys($scopes_supported),
                resourceName:          $webtrees_api->title(),
                resourceDocumentation: 'https://github.com/Jefferson49/webtrees-API',
            );
        } catch (InvalidArgumentException $e) {
            return api_response($e->getMessage(), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }

        return api_response(
            json_encode($meta_data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            StatusCodeInterface::STATUS_OK,
            ['content-type' => 'application/json']
        );
    }
}
