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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Client;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ClientRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Process a form to edit an OAuth2 client in the client repository.
 */
class EditClientAction implements RequestHandlerInterface
{	
    public const string EDIT_CLIENT_ACTION_ADD  = 'add';
    public const string EDIT_CLIENT_ACTION_EDIT = 'edit';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $edit_client_action        = Validator::parsedBody($request)->string('edit_client_action');
        $client_identifier         = Validator::parsedBody($request)->string('client_identifier', '');
        $client_name               = Validator::parsedBody($request)->string('client_name', '');
        $client_secret_hash        = Validator::parsedBody($request)->string('client_secret_hash', '');
        $checked_new_client_secret = Validator::parsedBody($request)->boolean('checked_new_client_secret', false);
        $new_client_secret         = Validator::parsedBody($request)->string('new_client_secret', '');
        $client_scopes             = Validator::parsedBody($request)->array('client_scopes');
        $technical_user_id         = Validator::parsedBody($request)->integer('technical_user_id', 0);

        $client_repository = Registry::container()->get(ClientRepository::class);
        $scope_repository  = Registry::container()->get(ScopeRepository::class);
        $error = false;

        // Validate client data
        if ($client_identifier === '' OR $client_name === '' OR empty($client_scopes) OR $technical_user_id === 0) {
            $error = true;
            $message = I18N::translate('All fields are required. Client data rejected, because one of the provided client fields is empty.');
		}
        else {
            // If client already exists, remove existing client from repository
            if ($edit_client_action === self::EDIT_CLIENT_ACTION_EDIT) {
                $client_repository->removeClient($client_identifier);
            }

            // Change client secret hash if new client secret was requested
            if ($checked_new_client_secret) {

                $client_secret_hash = password_hash($new_client_secret, PASSWORD_BCRYPT);
            }

            // Add new or changed client
            $successfully_added = $client_repository->addClient(new Client(
                name:               $client_name,
                identifier:         $client_identifier,
                client_secret_hash: $client_secret_hash,
                scopes:             $scope_repository->getScopesForIdentifiers($client_scopes),
                supported_grants:   ['client_credentials'],
                technical_user_id:  $technical_user_id
            ));

            if ($successfully_added) {
                $message = I18N::translate('Client successfully added or changed.');
            }
            else {
                $error = true;
                $message = I18N::translate('Could not add client, because the client identifier already exists.');
            }
        }

        // Reset new client secret if it has not been changed
        if ($edit_client_action === self::EDIT_CLIENT_ACTION_EDIT && !$checked_new_client_secret) {
            $new_client_secret = '';
        }

        return response(
            [
                'html'  => view(
                    WebtreesApi::viewsNamespace() . '::modals/message',
                    [
                        'title'             => I18N::translate('Edit Client'),
                        'error'             => $error,
                        'message'           => $message,
                        'client_identifier' => $client_identifier,
                        'new_client_secret' => $new_client_secret,
                        'access_token'      => '',
                    ]
                )
            ]
        );
    }
}
