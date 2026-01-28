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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers;

use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

/**
 * Process a form to create a new source.
 */
class CreateTokenModal implements RequestHandlerInterface
{
    /**
     * Handle the create source modal request
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $clients              = Validator::queryParams($request)->array('clients');
        $client_identifier    = Validator::queryParams($request)->string('client_identifier', '');
        $scope_identifiers    = Validator::queryParams($request)->array('scope_identifiers');
        $token_scopes         = Validator::queryParams($request)->array('token_scopes');
        $expiration_intervals = Validator::queryParams($request)->array('expiration_intervals');
        $expiration_interval  = Validator::queryParams($request)->string('expiration_interval', '');

        return response(
            view(WebtreesApi::viewsNamespace() . '::modals/create-token', [
                'clients'              => $clients,
                'client_identifier'    => $client_identifier,
                'scope_identifiers'    => $scope_identifiers,
                'token_scopes'         => $token_scopes,
                'expiration_intervals' => $expiration_intervals,
                'expiration_interval'  => $expiration_interval,
            ]
        ));
    }
}
