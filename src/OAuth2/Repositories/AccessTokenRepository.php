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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\AccessToken;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Client;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;


/**
 * Access token repository for OAuth2 server
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use TokenEntityTrait;

    private array $access_tokens;

    public const DEFAULT_EXPIRATION_INTERVAL = 'PT1H'; // 1 hour


    public function __construct() {

        // Load persisted tokens
        $this->access_tokens = $this->loadAccessTokens();
        
        // Persist tokens, since expired tokens might have been removed
        $this->persistAccessTokens();
    }

    /**
     * Get access tokens
     *      *
     * @return array<AccessToken>
     */    
    public function getAccessTokens() : array {

        return $this->access_tokens;
    }

    /**
     * Get new token
     * 
     * @param ClientEntityInterface $clientEntity
     * @param array                 $scopes
     * @param string|null           $userIdentifier
     *
     * @return AccessTokenEntityInterface
     */    
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array                 $scopes,
        string|null           $userIdentifier = null,
        string                $expiration_interval = self::DEFAULT_EXPIRATION_INTERVAL
        ): AccessTokenEntityInterface {

        /** @var Client $clientEntity */
        $allowed_scopes = [];
        $expiration_datetime = new DateTimeImmutable('now')->add(new DateInterval($expiration_interval));

        foreach ($scopes as $scope) {
            if ($clientEntity->hasScope($scope)) {
                $allowed_scopes[] = $scope;
            }
        }

        return new AccessToken(
            client_entity:       $clientEntity, 
            scopes:              $allowed_scopes, 
            user_identifier:     (string) $clientEntity->getTechnicalUserId(), 
            expiration_datetime: $expiration_datetime
        );
    }

    /**
     * Whether an access token is revoked
     * 
     * @param string $tokenId
     *
     * @return bool
     */       
    public function isAccessTokenRevoked(string $tokenId): bool {

        foreach($this->access_tokens as $access_token) {            
            if ($access_token->getIdentifier() === $tokenId) {
                return $access_token->isRevoked();
            }
        }

        return false;
    }

    /**
     * Persist new access token
     * 
     * @param AccessTokenEntityInterface $accessTokenEntity
     *
     * @return bool
     * 
     * @throws UniqueTokenIdentifierConstraintViolationException
     */       
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {

        if (!($accessTokenEntity instanceof AccessToken)) {
            throw new InvalidArgumentException('Invalid access token entity');
        }

        // Check if identifier is unique, i.e. already persisted
        foreach($this->access_tokens as $access_token) {            
            if ($access_token->getIdentifier() === $accessTokenEntity->getIdentifier()) {
                throw new UniqueTokenIdentifierConstraintViolationException('Could not create unique access token identifier', 100, 'access_token_duplicate', 500);
            }
        }        

        // Add to set of access tokens
        $this->access_tokens[] = $accessTokenEntity;

        // Save access tokens
        $this->persistAccessTokens();

        return;
    }

    /**
     * Revoke access token
     * 
     * @param string $tokenId
     *
     * @return void
     */  
    public function revokeAccessToken(string $tokenId): void {

        foreach($this->access_tokens as $access_token) {            
            if ($access_token->getIdentifier() === $tokenId) {
                $access_token->setRevoked();
                $this->persistAccessTokens();
                return;
            }
        }

        return;
    }

    /**
     * Load persisted access tokens
     * 
     * @return array<AccessToken>
     */  
    public function loadAccessTokens(): array {

        //return [];

        /** @var WebtreesApi $webtrees_api */
        $webtrees_api = Registry::container()->get(WebtreesApi::class);
        $access_tokens = [];  

        // Load tokens
        $tokens_json = $webtrees_api->getPreference(WebtreesApi::PREF_ACCESS_TOKENS, '');
        $serialized_tokens = json_decode($tokens_json, true) ?? [];

        foreach($serialized_tokens as $serialized_token) {
            $token = AccessToken::deSerializeTokenFromArray($serialized_token);

            // Add to list of tokens if not expired yet
            if (!$token->isExpired()) {
                $access_tokens[] = $token;
            }
        }

        return $access_tokens;
    }

    /**
     * Save access tokens
     * 
     * @return void
     */  
    public function persistAccessTokens(): void {

        $webtrees_api = Registry::container()->get(WebtreesApi::class);

        $serialization_array = [];
        foreach($this->access_tokens as $access_token) {
            $serialization_array[] = $access_token->jsonSerialize();
        }

        $tokens_json = json_encode($serialization_array);

        $webtrees_api->setPreference(WebtreesApi::PREF_ACCESS_TOKENS, $tokens_json);

        return;
    }

    /**
     * Reset access tokens
     * 
     * @return void
     */  
    public function resetAccessTokens(): void {

        $this->access_tokens = [];
        $this->persistAccessTokens();
               
        return;
    }    

    /**
     * Get expiration intervals
     * 
     * @return array<string>
     */  
    public static function getExpirationIntervals(): array {    
        return [
            'PT15M'  => I18N::translate('15 minutes'),
            'PT1H'   => I18N::translate('1 hour'),
            'P1M'    => I18N::translate('1 month'),
            'P1Y'    => I18N::translate('1 year'),
        ];
    }

    /**
     * Get all active access tokens for a client identifier
     * 
     * @param string $clientIdentifier
     * 
     * @return array<AccessToken>
     */  
    public function accessTokensForClient(string $clientIdentifier): array {

        $access_tokens = [];

        foreach($this->access_tokens as $token) {

            // Add to list of tokens if not expired yet
            if ($token->getClient()->getIdentifier() === $clientIdentifier && !$token->isExpired()) {
                $access_tokens[] = $token;
            }
        }

        return $access_tokens;
    }
}
