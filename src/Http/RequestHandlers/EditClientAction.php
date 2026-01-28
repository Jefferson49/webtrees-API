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
 * Edit an OAuth2 client in the client repository.
 */
class EditClientAction implements RequestHandlerInterface
{	
    public const string EDIT_CLIENT_ACTION_ADD  = 'add';
    public const string EDIT_CLIENT_ACTION_EDIT = 'edit';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $edit_client_action = Validator::parsedBody($request)->string('edit_client_action');
        $client_identifier  = Validator::parsedBody($request)->string('client_identifier', '');
        $client_name        = Validator::parsedBody($request)->string('client_name', '');
        $client_secret      = Validator::parsedBody($request)->string('client_secret', '');
        $client_scopes      = Validator::parsedBody($request)->array('client_scopes');
        $technical_user_id  = Validator::parsedBody($request)->integer('technical_user_id', 0);

        $client_repository = Registry::container()->get(ClientRepository::class);
        $scope_repository  = Registry::container()->get(ScopeRepository::class);

        // Validate client data
        if ($client_identifier === '' OR $client_name === '' OR $client_secret === '' OR empty($client_scopes) OR $technical_user_id === 0) {
            $message = I18N::translate('All fields are required. Client data rejected, because one of the provided client fields is empty.');
		}
        else {
            // If client already exists, remove existing client from repository
            if ($edit_client_action === self::EDIT_CLIENT_ACTION_EDIT) {
                $client_repository->removeClient($client_identifier);
            }

            // Add new or changed client
            $successfully_added = $client_repository->addClient(new Client(
                name:               $client_name,
                identifier:         $client_identifier,
                clientSecret:       $client_secret,
                scopes:             $scope_repository->getScopesForIdentifiers($client_scopes),
                supported_grants:   ['client_credentials'],
                technical_user_id:  $technical_user_id
            ));

            if ($successfully_added) {
                $message = I18N::translate('Client successfully added or changed.');
            }
            else {
                $message = I18N::translate('Could not add client, because the client identifier already exists.');
            }
        }

        return response(
            [
                'html'  => view(
                    WebtreesApi::viewsNamespace() . '::modals/message',
                    [
                        'title' => I18N::translate('Edit Client'),
                        'text'  => $message,
                    ]
                )
            ]
        );
    }
}
