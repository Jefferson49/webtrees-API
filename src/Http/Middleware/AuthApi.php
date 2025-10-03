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

use Fisharebest\Webtrees\Services\ModuleService;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to restrict access to administrators.
 */
class AuthApi implements MiddlewareInterface
{
    /**
     * A middleware to authorize access to the API
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        $authorization_header = $request->getHeader('Authorization')[0] ?? '';
        $bearer_token = str_replace('Bearer ','', $request->getHeader('Authorization')[0] ?? '');

        if ($authorization_header === '' OR $bearer_token === '') {
            return new Response401('Unauthorized: Missing authorization header or bearer token.');
        }

        if ($bearer_token === '') {
            $bearer_token = str_replace('Bearer ','', $request->getHeader('Custom-Authorization')[0] ?? '');
        }

        $module_service = New ModuleService();
        /** @var WebtreesApi $webtrees_api To avoid IDE warnings */
        $webtrees_api = $module_service->findByName(module_name: WebtreesApi::activeModuleName());

        $secret_webtrees_api_token = $webtrees_api->getPreference(WebtreesApi::PREF_WEBTREES_API_TOKEN, '');

        //Do not authorize if no secret token is configured or token is too short
        if ($secret_webtrees_api_token === '' OR strlen($secret_webtrees_api_token) < WebtreesApi::MINIMUM_API_KEY_LENGTH) {
            return new Response403('Unauthorized: Insufficient permissions.');
        }
        //Authorize if no hashing used and token is valid
        elseif (!boolval($webtrees_api->getPreference(WebtreesApi::PREF_USE_HASH, '0')) && $bearer_token === $secret_webtrees_api_token) {
            return $handler->handle($request);
        }
        //Authorize if hashing used and token fits to hash
        if (boolval($webtrees_api->getPreference(WebtreesApi::PREF_USE_HASH, '0')) && password_verify($bearer_token, $secret_webtrees_api_token)) {
            return $handler->handle($request);
        }

        return new Response403('Unauthorized: Insufficient permissions.');
    }
}
