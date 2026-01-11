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

namespace Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories;

use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Client;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;


/**
 * Client repository for OAuth2 server
 */
class ClientRepository implements ClientRepositoryInterface
{
    use ClientTrait;
    use EntityTrait;

    /**
     * Get client entity
     * 
     * @param string $clientIdentifier
     *
     * @return ClientEntityInterface|null
     */    
    public function getClientEntity(string $clientIdentifier): ClientEntityInterface|null {
        return new Client();
    }

    /**
     * Validate client
     * If the client’s credentials are validated, true is returned, otherwise false.
     * 
     * @param string      $clientIdentifier
     * @param string|null $clientSecret
     *
     * @return bool
     */    
    public function validateClient(string $clientIdentifier, string|null $clientSecret, string|null $grantType): bool {
        return true;
    }
}
