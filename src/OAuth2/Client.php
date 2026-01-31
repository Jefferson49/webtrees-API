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

namespace Jefferson49\Webtrees\Module\WebtreesApi\OAuth2;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;


/**
 * OAuth2 client
 */
class Client implements ClientEntityInterface
{
    use ClientTrait;

    protected string $identifier;
    protected string $client_secret_hash;
    protected array  $scopes;
    protected array  $supported_grants;

    // The user id of the Technical User associated with this client
    protected int    $technical_user_id;


    /**
     * @param string         $name
     * @param string         $identifier
     * @param string         $client_secret_hash
     * @param array<Scope>   $scopes
     * @param array<string>  $supported_grants
     * @param bool           $isConfidential
     */
    public function __construct(string $name, string $identifier, string $client_secret_hash, array $scopes, array $supported_grants, int $technical_user_id, bool $isConfidential = true) {

        $this->name               = $name;
        $this->identifier         = $identifier;
        $this->client_secret_hash = $client_secret_hash;
        $this->scopes             = $scopes;
        $this->supported_grants   = $supported_grants;
        $this->technical_user_id  = $technical_user_id;
        $this->isConfidential     = true;
    }

    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier(): string {
        return $this->identifier;
    }

    /**
     * Whether the client supports the given grant type.
     * 
     * @return bool
     */
    public function supportsGrantType(string $grantType): bool
    {
        if (in_array($grantType, $this->supported_grants)) {
            return true;
        }

        return false;
    }

    /**
     * Validate client secret
     * 
     * @param string $clientSecret
     *
     * @return bool
     */
    public function validate(string $clientSecret): bool {

        return password_verify($clientSecret, $this->client_secret_hash);
    }

    /**
     * Get scopes
     * 
     * @return array
     */
    public function getScopes(): array {

        return $this->scopes;
    }

    /**
     * Whether the client has a certain scope
     * 
     * @param Scope $scope
     * 
     * @return bool
     */
    public function hasScope(Scope $scope): bool {

        return in_array($scope, $this->scopes);
    }

    /**
     * Whether the client has a set of scopes
     * 
     * @param array<Scope> $scopes
     * 
     * @return bool
     */
    public function hasScopes(array $scopes): bool {

        foreach ($scopes as $scope) {
            if (!$this->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }
  
    /**
     * Get user id of the Technical User associated with this client
     * 
     * @return int
     */
    public function getTechnicalUserId(): int {

        return $this->technical_user_id;
    }   

    /**
     * Get the client secret hash
     * 
     * @return string
     */
    public function getClientSecretHash(): string {

        return $this->client_secret_hash;
    }

    /**
     * Serialize
     *
     * @return void
     */      
    public function jsonSerialize(): array {
        
        return [
            'name'               => $this->name,
            'identifier'         => $this->identifier,
            'client_secret_hash' => $this->client_secret_hash,
            'scopes'             => array_map(fn($scope) => $scope->getIdentifier(), $this->getScopes()),
            'supported_grants'   => $this->supported_grants,
            'technical_user_id'  => $this->technical_user_id,
            'isConfidential'     => $this->isConfidential,
        ];
    }

    /**
     * De-serialize a client from an array (used within JSON serialization)
     *
     * @param array $serialized_client
     * 
     * @return Client
     */      
    public static function deSerializeClientFromArray(array $serialized_client): Client {
        
        return new Client(
            name:               $serialized_client['name'],
            identifier:         $serialized_client['identifier'],
            client_secret_hash: $serialized_client['client_secret_hash'],
            scopes:             array_map(fn($identifier) => new Scope ($identifier), $serialized_client['scopes']),
            supported_grants:   $serialized_client['supported_grants'],
            technical_user_id:  $serialized_client['technical_user_id'],
            isConfidential:     $serialized_client['isConfidential'],
        );
    }
}