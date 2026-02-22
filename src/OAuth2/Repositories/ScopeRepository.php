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

use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Scope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;


/**
 * Scope repository for OAuth2 server
 */
class ScopeRepository implements ScopeRepositoryInterface
{
    use ClientTrait;
    use EntityTrait;

    public const string SCOPE_API_READ          = 'api_read';
    public const string SCOPE_API_READ_PRIVACY  = 'api_read_privacy';
    public const string SCOPE_API_READ_MEMBER   = 'api_read_member';
    public const string SCOPE_API_WRITE         = 'api_write';
    public const string SCOPE_API_CLI           = 'api_cli';
    public const string SCOPE_MCP_READ_PRIVACY  = 'mcp_read_privacy';
    public const string SCOPE_MCP_WRITE         = 'mcp_write';
    public const string SCOPE_MCP_GEDBAS        = 'mcp_gedbas';


    // All scope identifiers
    private static array $scope_identifiers = [
        ScopeRepository::SCOPE_API_READ_PRIVACY,
        ScopeRepository::SCOPE_API_READ_MEMBER,
        ScopeRepository::SCOPE_API_WRITE,
        ScopeRepository::SCOPE_API_CLI,
        ScopeRepository::SCOPE_MCP_READ_PRIVACY,
        ScopeRepository::SCOPE_MCP_WRITE,
        ScopeRepository::SCOPE_MCP_GEDBAS,
    ];

    // Scope identifiers for MCP
    private static array $mcp_scope_identifiers = [
        ScopeRepository::SCOPE_MCP_READ_PRIVACY,
        ScopeRepository::SCOPE_MCP_WRITE,
    ];

    // Scope identifiers for GEDBAS MCP
    private static array $gedbas_mcp_scope_identifiers = [
        ScopeRepository::SCOPE_MCP_GEDBAS,
    ];

    
    /**
     * Finalize scopes
     * 
     * @param array<Scope>          $scopes
     * @param string                $grantType
     * @param ClientEntityInterface $clientEntity
     * @param string|null           $userIdentifier 
     * @param string|null           $authCodeId
     *
     * @return array<Scope>
     */    
    function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = null, string|null $authCodeId = null): array {
        return $scopes;
    }

    /**
     * Get scope entity by identifier
     * 
     * @param string $identifier
     *
     * @return ScopeEntityInterface|null
     */     
    public function getScopeEntityByIdentifier(string $identifier): ScopeEntityInterface|null {

        if (in_array($identifier, self::$scope_identifiers)) {
            return new Scope($identifier);
        }

        return null;
    } 

    /**
     * Get scope identifiers
     * 
     * @return array<string,string>
     */     
    public static function getScopeIdentifiers(): array {

        return array_combine(self::$scope_identifiers, self::$scope_identifiers);
    }

    /**
     * Get MCP scope identifiers
     * 
     * @return array<string,string>
     */     
    public static function getMcpScopeIdentifiers(): array {

        return array_combine(self::$mcp_scope_identifiers, self::$mcp_scope_identifiers);
    }

    /**
     * Get GEDBAS MCP scope identifiers
     * 
     * @return array<string,string>
     */     
    public static function getGedbasMcpScopeIdentifiers(): array {

        return array_combine(self::$gedbas_mcp_scope_identifiers, self::$gedbas_mcp_scope_identifiers) ;
    }

    /**
     * Get a set of scopes corresponding to a set of scope identifiers
     * 
     * @param array<string> $scope_identifiers
     * 
     * @return array<string,Scope> An array with the scopes
     */     
    public function getScopesForIdentifiers(array $scope_identifiers): array {
    
        $scopes = [];

        foreach ($scope_identifiers as $identifier) {

            $scope = $this->getScopeEntityByIdentifier($identifier);

            if ($scope !== null) {
                $scopes[$identifier] = $scope;
            }
        } 

        return $scopes;
    }
}
