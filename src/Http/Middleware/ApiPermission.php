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
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddParentToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddUnlinkedRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CliCommand;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GetRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkSpouseToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\ModifyRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\SearchGeneral;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\TestApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Trees;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\WebtreesVersion;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
    

/**
 * Middleware to authorize access to the webtrees API based on OAuth2 scopes
 */
class ApiPermission implements MiddlewareInterface
{
    public const array API_READ_HANDLERS = [
        GetRecord::class,
        SearchGeneral::class,
        Trees::class,
        WebtreesVersion::class
    ];

    public const array API_WRITE_HANDLERS = [
        AddChildToFamily::class,
        AddChildToIndividual::class,
        AddParentToIndividual::class,
        AddSpouseToFamily::class,
        AddSpouseToIndividual::class,
        AddUnlinkedRecord::class,
        LinkChildToFamily::class,
        LinkSpouseToIndividual::class,
        ModifyRecord::class,
    ];

    public const array API_CLI_HANDLERS = [
        CliCommand::class,
    ];

    public const array API_SWAGGER_UI_HANDLERS = [
        TestApi::class,
    ];


    /**
     * Authorize access to the API based on the provided OAuth2 scopes
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        $scopes = Validator::attributes($request)->array('oauth_scopes');
        $route  = Validator::attributes($request)->route();

        $all_handlers = array_merge(self::API_READ_HANDLERS, self::API_WRITE_HANDLERS, self::API_CLI_HANDLERS, self::API_SWAGGER_UI_HANDLERS); 

        // Check if requested handler is available
        if (!in_array($route->handler, $all_handlers)) {

            return new Response404('Requested API not found.');
        }

        // Check scopes and process API requests
        if (in_array($route->handler, self::API_READ_HANDLERS) && array_intersect($scopes, [ScopeRepository::SCOPE_API_READ_PRIVACY, ScopeRepository::SCOPE_API_READ_MEMBER])) {

            // If api_read_privacy scope only, we logout the user and validate the tree privacy settings
            if (in_array(ScopeRepository::SCOPE_API_READ_PRIVACY, $scopes) && !in_array(ScopeRepository::SCOPE_API_READ_MEMBER, $scopes)) {

                Auth::logout();
            }

            return $handler->handle($request);
        }
        elseif (in_array($route->handler, self::API_WRITE_HANDLERS) && array_intersect($scopes, [ScopeRepository::SCOPE_API_WRITE])) {

            return $handler->handle($request);
        }
        elseif (in_array($route->handler, self::API_CLI_HANDLERS) && array_intersect($scopes, [ScopeRepository::SCOPE_API_CLI])) {

            return $handler->handle($request);
        }

        return new Response403('Insufficient permissions: Provided scope(s) insufficient to access API.');
    }
}
