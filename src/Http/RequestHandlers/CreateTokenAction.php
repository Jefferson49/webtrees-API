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
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\AccessToken;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\AccessTokenRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ClientRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use League\OAuth2\Server\CryptKey;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * View a modal to change XML export settings.
 */
class CreateTokenAction implements RequestHandlerInterface
{
    /**
     * Handle a request to view a modal XML export settings
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $client_identifier   = Validator::parsedBody($request)->string('client_identifier', '');
        $token_scopes        = Validator::parsedBody($request)->array('token_scopes');
        $expiration_interval = Validator::parsedBody($request)->string('expiration_interval', '');

        $access_token_repository = Registry::container()->get(AccessTokenRepository::class);
        $client_repository       = Registry::container()->get(ClientRepository::class);
        $scope_repository        = Registry::container()->get(ScopeRepository::class);
        $error = false;

        $client = $client_repository->getClientEntity($client_identifier);

        if (!$client->hasScopes($scope_repository->getScopesForIdentifiers($token_scopes))) {
            $error = true;
            $message = I18N::translate('The client does not have the requested scopes');
            $long_token = '';
        }
        else {
            $access_token = $access_token_repository->getNewToken(
                $client_repository->getClientEntity($client_identifier), 
                $scope_repository->getScopesForIdentifiers($token_scopes), 
                null, 
                $expiration_interval
            );

            $access_token->setPrivateKey(new CryptKey(Webtrees::DATA_DIR . WebtreesApi::PRIVATE_KEY_PATH));
            $long_token = $access_token->toString();
            $access_token->setCreatedInControlPanel();
            $access_token->setShortToken(AccessToken::createShortToken($long_token));

            $access_token_repository->persistNewAccessToken($access_token);

            $message = I18N::translate('Sucessfully created new access token.');
        }
        
        return response(
            [
                'html'  => view(
                    WebtreesApi::viewsNamespace() . '::modals/message',
                    [
                        'title'             => I18N::translate('Create Access Token'),
                        'error'             => $error,
                        'message'           => $message,
                        'client_identifier' => $client_identifier,
                        'new_client_secret' => '',
                        'access_token'      => $long_token,
                    ]
                )
            ]
        );
    }
}
