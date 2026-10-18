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

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


/**
 * Middleware to OAuth2 authorization.
 */
class OAuth2Authorization implements MiddlewareInterface
{
    /**
     * A middleware for OAuth2 authorization to the API
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resource_server = Registry::container()->get(ResourceServer::class);
        $route           = Validator::attributes($request)->route();
        $url             = version_compare(Webtrees::VERSION, '2.3.0', '>=') ? $route->url : $route->path;

        try {
            $request = $resource_server->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $exception) {

            // Log error
            /** @var CustomModuleLogInterface $log_module */
            $log_module = Functions::getFromContainer(WebtreesApi::class);

            CustomModuleLog::addDebugLog(
                $log_module,
                'Error in class '
                . substr(strrchr(get_class($this), '\\'), 1)
                . ' : '
                . $exception->getErrorType()
                . ' - '
                . $exception->getMessage()
            );

            // MCP-specific handling of authorization errors
            if ($exception->getHttpStatusCode() === StatusCodeInterface::STATUS_UNAUTHORIZED
                && str_ends_with($url, WebtreesApi::ROUTE_MCP)) {

                $authorization = $request->getHeaderLine('Authorization');

                //If no Authorization header is inlcuded, return WWW-Authenticate header
                if ($authorization === '') {
                    $resource_metadata = WebtreesApi::getWebtreesUrl($request) . WebtreesApi::ROUTE_OAUTH_PROTECTED_RESOURCE;
                    $www_authenticate  = sprintf('Bearer resource_metadata="%s"', $resource_metadata);

                    return api_response('Unauthorized', StatusCodeInterface::STATUS_UNAUTHORIZED)
                        ->withHeader('WWW-Authenticate', $www_authenticate);
                }
                //If Bearer token is included, return invalid token
                if (str_starts_with($authorization, 'Bearer ')) {
                    return api_response('invalid_token', StatusCodeInterface::STATUS_UNAUTHORIZED);
                }

                //Other authorization errors
                $error = sprintf(', error="%s"', $exception->getErrorType());

                if ($exception->getMessage() !== '') {
                    $error_description = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $exception->getMessage());
                    $error .= sprintf(', error_description="%s"', $error_description);
                }

                return api_response($error, StatusCodeInterface::STATUS_UNAUTHORIZED);
            }

            $response = api_response();
            return $exception->generateHttpResponse($response);
        }

        //If authorization is successful, proceed to the next middleware/request handler
        return $handler->handle($request);
    }
}
