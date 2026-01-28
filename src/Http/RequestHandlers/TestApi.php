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

use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Client;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\AccessTokenRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Scope;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use RuntimeException;


class TestApi implements RequestHandlerInterface
{
    use ViewResponseTrait;
 

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $pretty_urls  = Validator::attributes($request)->boolean('rewrite_urls', false);

        $access_token_repository = Registry::container()->get(AccessTokenRepository::class);

        $client = new Client(
            name:               'Swagger UI',
            identifier:         'swagger_ui',
            clientSecret:       '',
            scopes:             [
                                    new Scope(ScopeRepository::SCOPE_API_CLI),
                                    new Scope(ScopeRepository::SCOPE_API_READ),
                                    new Scope(ScopeRepository::SCOPE_API_WRITE),
                                ],
            supported_grants:   [new ClientCredentialsGrant()->getIdentifier()],
            technical_user_id:  1
        );

        $access_token = $access_token_repository->getNewToken($client, $client->getScopes());
        $access_token->setPrivateKey(new CryptKey(Webtrees::DATA_DIR . WebtreesApi::PRIVATE_KEY_PATH));

        return $this->viewResponse(WebtreesApi::viewsNamespace() . '::swagger', [
            'title'              => I18N::translate('webtrees API'),
            'pretty_urls'        => $pretty_urls,
            'webtrees_api'       => Registry::container()->get(WebtreesApi::class),
            'webtrees_api_token' => $access_token->toString(),
        ]);
    }
}
