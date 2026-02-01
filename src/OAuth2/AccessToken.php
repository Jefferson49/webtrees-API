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

use Fisharebest\Webtrees\Registry;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ClientRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

use DateTimeImmutable;
use JsonSerializable;

/**
 * OAuth2 access token
 */
class AccessToken implements AccessTokenEntityInterface, JsonSerializable 
{
    use AccessTokenTrait;

    private ClientEntityInterface $client;
    private array $scopes;
    private string|null $user_identifier;
    private DateTimeImmutable $expiration_datetime;
    private string $identifier;
    private string $short_token = '';
    private bool $revoked = false;
    private bool $created_in_control_panel = false;

    public const int CLIENT_ID_LENGTH   = 16;
    public const int SHORT_TOKEN_LENGTH = 16;


    /**
     * @param ClientEntityInterface       $client_entity
     * @param array<ScopeEntityInterface> $scopes
     * @param DateTimeImmutable           $expiration_datetime
     * @param string                      $identifier
     * @param string|null                 $user_identifier
     * @param string                      $short_token
     * @param bool                        $created_in_control_panel
     * @param bool                        $revoked
     */  
    public function __construct(
        ClientEntityInterface $client_entity,
        array                 $scopes, 
        DateTimeImmutable     $expiration_datetime,
        string                $identifier = '',
        string|null           $user_identifier = null,
        string                $short_token = '',
        bool                  $created_in_control_panel = false,
        bool                  $revoked = false
    ) {
        /** @var Client $client_entity */    
        $this->client                   = $client_entity;
        $this->scopes                   = $scopes;
        $this->expiration_datetime      = $expiration_datetime;
        $this->identifier               = $identifier !== '' ? $identifier : WebtreesApi::generateSecurePassword(self::CLIENT_ID_LENGTH);
        $this->user_identifier          = $user_identifier ?? (string) $client_entity->getTechnicalUserId();
        $this->short_token              = $short_token;
        $this->created_in_control_panel = $created_in_control_panel;
        $this->revoked                  = $revoked;
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
     * Get short token
     * 
     * @return string
     */  
    public function getShortToken(): string {

        return $this->short_token;
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
     *
     * @return void
     */      
    public function setIdentifier(string $identifier): void {

        $this->identifier = $identifier;
        return;
    }

    /**
     * Set short token
     * 
     * @param string $short_token
     *
     * @return void
     */      
    public function setShortToken(string $short_token): void {

        $this->short_token = $short_token;
        return;
    }

    /**
     * Create short token
     * 
     * @param string $long_token
     *
     * @return string
     */      
    public static function createShortToken(string $long_token): string {

        if (strlen($long_token) < self::SHORT_TOKEN_LENGTH) {
            return $long_token;
        }

        return substr($long_token, -1 * self::SHORT_TOKEN_LENGTH,self::SHORT_TOKEN_LENGTH);
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

    /**
     * Set revoked
     * 
     * @return void
     */      
    public function setRevoked(): void {

        $this->revoked = true;
        return;
    }

    /**
     * Set created in control panel
     * 
     * @return void
     */      
    public function setCreatedInControlPanel(): void {

        $this->created_in_control_panel = true;
        return;
    }

    /**
     * Whether the access token is revoked
     * 
     * @return bool
     */      
    public function isRevoked(): bool {

        return $this->revoked;
    }

    /**
     * Whether the access token is expired
     * 
     * @return bool
     */      
    public function isExpired(): bool {

        return $this->expiration_datetime < new DateTimeImmutable('now');
    }

    /**
     * Whether the access token was created in the control panel
     * 
     * @return bool
     */      
    public function wasCreatedInControlPanel(): bool {

        return $this->created_in_control_panel;
    }

    /**
     * Serialize
     *
     * @return void
     */      
    public function jsonSerialize(): array {

        return [
            'client_id'                => $this->client->getIdentifier(),
            'scopes'                   => array_map(fn($scope) => $scope->getIdentifier(), $this->scopes),
            'expiration_datetime'      => $this->expiration_datetime->format(DateTimeImmutable::ATOM),
            'user_id'                  => $this->user_identifier,
            'identifier'               => $this->identifier,
            'short_token'              => $this->short_token,
            'created_in_control_panel' => $this->created_in_control_panel,
            'revoked'                  => $this->revoked,
        ];
    }

    /**
     * De-serialize a client from an array (used within JSON serialization)
     * 
     * @param array $serialized_token
     *
     * @return AccessToken
     */      
    public static function deSerializeTokenFromArray(array $serialized_token): AccessToken {

        $client_repository = Registry::container()->get(ClientRepository::class);
        $scope_repository  = Registry::container()->get(ScopeRepository::class);

        return new AccessToken(
            client_entity:            $client_repository->getClientEntity($serialized_token['client_id']),
            scopes:                   $scope_repository->getScopesForIdentifiers($serialized_token['scopes']),
            expiration_datetime:      new DateTimeImmutable($serialized_token['expiration_datetime']),
            user_identifier:          $serialized_token['user_id'],
            identifier:               $serialized_token['identifier'],
            short_token:              $serialized_token['short_token'],
            created_in_control_panel: $serialized_token['created_in_control_panel'],
            revoked:                  $serialized_token['revoked'],
        );
    }
}
