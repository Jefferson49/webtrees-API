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

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

use DateTimeImmutable;

/**
 * OAuth2 access token
 */
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait;

    private ClientEntityInterface $client;
    private array $scopes;
    private string|null $user_identifier;
    private DateTimeImmutable $expiration_datetime;
    private string $identifier;


    /**
     * @param ClientEntityInterface       $clientEntity
     * @param array<ScopeEntityInterface> $scopes
     * @param string|null                 $userIdentifier
     * @param DateTimeImmutable           $expiration_datetime
     * @param string                      $identifier
     */  
    public function __construct(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null, DateTimeImmutable $expiration_datetime, string $identifier = '') {

        $this->client              = $clientEntity;
        $this->scopes              = $scopes;
        $this->user_identifier     = $userIdentifier;
        $this->expiration_datetime = $expiration_datetime;
        $this->identifier          = $identifier;
    }  

    /**
     * Add scope
     * 
     * @param ScopeEntityInterface $scope
     *
     * @return void
     */        
    public function addScope(ScopeEntityInterface $scope): void {

        if (!in_array($scope, $this->scopes)) {
            $this->scopes[] = $scope;    
        }

        return;
    }

    /**
     * Get client
     * 
     * @return ClientEntityInterface
     */      
    public function getClient(): ClientEntityInterface {

        return $this->client;
    }

    /**
     * Get expiry date time
     * 
     * @return DateTimeImmutable
     */  
    public function getExpiryDateTime(): DateTimeImmutable {

        return $this->expiration_datetime;
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
     * Get scopes
     * 
     * @return array
     */      
    public function getScopes(): array {

        return $this->scopes;
    }

    /**
     * Get user identifier
     * 
     * @return string|null
     */      
    public function getUserIdentifier(): string|null {

        return $this->user_identifier ?? null;
    }

    /**
     * Set client
     * 
     * @param ClientEntityInterface $client
     * 
     * @return void
     */      
    public function setClient(ClientEntityInterface $client): void {

        $this->client = $client;
        return;
    }

    /**
     * Set expiry date time
     * 
     * @param DateTimeImmutable $dateTime
     * 
     * @return void
     */       
    public function setExpiryDateTime(DateTimeImmutable $dateTime): void {

        $this->expiration_datetime = $dateTime;
        return;
    }

    /**
     * Set identifier
     * 
     * @param string $identifier
     *      * 
     * @return void
     */      
    public function setIdentifier(string $identifier): void {

        $this->identifier = $identifier;
        return;
    }

    /**
     * Set user identifier
     * 
     * @param string $identifier
     * 
     * @return void
     */      
    public function setUserIdentifier(string $identifier): void {

        $this->user_identifier = $identifier;
        return;
    }
}
