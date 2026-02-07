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
 * View a form to create new private/public keys.
 */
class EditClientModal implements RequestHandlerInterface
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
        $edit_client_action = Validator::queryParams($request)->string('edit_client_action');
        $client_identifier  = Validator::queryParams($request)->string('client_identifier', '');
        $client_secret_hash = Validator::queryParams($request)->string('client_secret_hash', '');
        $client_name        = Validator::queryParams($request)->string('client_name', '');
        $scope_identifiers  = Validator::queryParams($request)->array('scope_identifiers');
        $client_scopes      = Validator::queryParams($request)->array('client_scopes');
        $user_list          = Validator::queryParams($request)->array('user_list');
        $technical_user_id  = Validator::queryParams($request)->integer('technical_user_id', 0);

        // Create new client secret; if client secret already exists, the user might request to change it
        $new_client_secret  = WebtreesApi::generateSecurePassword(64);

        // If we need to add a new client, create the client
        if ($edit_client_action === EditClientAction::EDIT_CLIENT_ACTION_ADD) {

            $client_identifier  = WebtreesApi::generateSecurePassword(8, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') . '.webtrees-api';
            $client_secret_hash = password_hash($new_client_secret, PASSWORD_BCRYPT);
        } 

        return response(
            view(WebtreesApi::viewsNamespace() . '::modals/edit-client', [
                'edit_client_action' => $edit_client_action,
                'client_name'        => $client_name,
				'client_identifier'  => $client_identifier,
                'client_secret_hash' => $client_secret_hash,
                'new_client_secret'  => $new_client_secret,
                'scope_identifiers'  => $scope_identifiers,
                'client_scopes'      => $client_scopes,
                'user_list'          => $user_list,
                'technical_user_id'  => $technical_user_id,

            ]
        ));
    }
}
