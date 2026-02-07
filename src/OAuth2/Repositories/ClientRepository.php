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

namespace Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories;

use Fisharebest\Webtrees\Registry;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Client;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
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

    private array $clients;


    public function __construct() {

        $this->clients = $this->loadClients();
    }

    /**
     * Get clients
     * 
     * @return array<string,Client>  client_identifier => client
     */  
    public function getClients(): array {

        $client_identifiers = array_map(fn($client) => $client->getIdentifier(), $this->clients);

        return array_combine($client_identifiers, $this->clients);
    }

    /**
     * Load persisted clients
     *
     * @return array<string>
     */    
    public function loadClients(): array {

        /** @var WebtreesApi $webtrees_api */
        $webtrees_api = Registry::container()->get(WebtreesApi::class);
        $clients = [];  

        // Reset clients and tokens
        //$webtrees_api->setPreference(WebtreesApi::PREF_OAUTH2_CLIENTS, json_encode([]));
        //$webtrees_api->setPreference(WebtreesApi::PREF_ACCESS_TOKENS, json_encode([]));

        // Load clients
        $clients_json = $webtrees_api->getPreference(WebtreesApi::PREF_OAUTH2_CLIENTS, '');
        $serialized_clients = json_decode($clients_json, true) ?? [];

        foreach($serialized_clients as $serialized_client) {

            if ($serialized_client !== []) {
                $clients[] = Client::deSerializeClientFromArray($serialized_client);
            }
        }

        return $clients;
    }

    /**
     * Persist clients
     * 
     * @return void
     */  
    public function persistClients(): void {

        /** @var WebtreesApi $webtrees_api */
        $webtrees_api = Registry::container()->get(WebtreesApi::class);

        $serialization_array = [];
        foreach($this->clients as $client) {
            $serialization_array[] = $client->jsonSerialize();
        }

        $clients_json = json_encode($serialization_array);

        $webtrees_api->setPreference(WebtreesApi::PREF_OAUTH2_CLIENTS, $clients_json);

        return;
    }

    /**
     * Add client
     * 
     * @param Client $client
     *
     * @return bool Whether the client was added successfully
     */    
    public function addClient(Client $client): bool {

        foreach($this->clients as $existingClient) {
            if ($existingClient->getIdentifier() === $client->getIdentifier()) {
                return false; // Client with the same identifier already exists
            }
            if ($existingClient->getName() === $client->getName()) {
                return false; // Client with the same name already exists
            }
        }

        // Add new client and persist updated clients
        $this->clients[] = $client;
        $this->persistClients();

        return true;
    }

    /**
     * Remove client
     * 
     * @param string $clientIdentifier
     *
     * @return bool Whether the client was removed successfully
     */    
    public function removeClient(string $clientIdentifier): bool {

        foreach($this->clients as $existing_key => $existingClient) {

            if ($existingClient->getIdentifier() === $clientIdentifier) {

                // Remove client and persist updated clients
                unset($this->clients[$existing_key]);
                $this->persistClients();      

                return true;
            }
        }

        return false;
    }

    /**
     * Get client entity by identifier
     * 
     * @param string $clientIdentifier
     *
     * @return ClientEntityInterface|null
     */    
    public function getClientEntity(string $clientIdentifier): ClientEntityInterface|null {

        foreach ($this->clients as $client) {
            if ($client->getIdentifier() === $clientIdentifier) {
                return $client;
            }
        }

        return null;
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

        /** @var Client $client */
        $client = $this->getClientEntity($clientIdentifier);

        return (    $client !== null 
                &&  $client->validate($clientSecret) 
                &&  $client->supportsGrantType($grantType ?? '')
                && $client->isConfidential()
        );
    }
}
