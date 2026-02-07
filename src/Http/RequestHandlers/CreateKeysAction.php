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
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\AccessTokenRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Process a form to create new private/public keys.
 */
class CreateKeysAction implements RequestHandlerInterface
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
        $webtrees_api            = Registry::container()->get(WebtreesApi::class);
        $access_token_repository = Registry::container()->get(AccessTokenRepository::class);

        $error   = false;
        $message = I18N::translate('Sucessfully created new private/public keys.');

        try {
            $webtrees_api->createNewKeys();
        }
        catch (Throwable $th) {
            $error = true;
            $message = $th->getMessage();    
        }

        // If keys were successfully updated, we need to delete all existing access tokens
        if (!$error) {
            $access_token_repository->resetAccessTokens();
        }
        
        return response(
            [
                'html'  => view(
                    WebtreesApi::viewsNamespace() . '::modals/message',
                    [
                        'title'             => I18N::translate('Create new private/public keys'),
                        'error'             => $error,
                        'message'           => $message,
                        'client_identifier' => 0,
                        'new_client_secret' => '',
                        'access_token'      => '',
                    ]
                )
            ]
        );
    }
}
