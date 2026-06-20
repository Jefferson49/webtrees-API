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

    public const string SCOPE_API_READ_PRIVACY  = 'api_read_privacy';
    public const string SCOPE_API_READ_MEMBER   = 'api_read_member';
    public const string SCOPE_API_WRITE         = 'api_write';
    public const string SCOPE_API_IMPORT        = 'api_import';
    public const string SCOPE_API_EXPORT        = 'api_export';
    public const string SCOPE_API_TREES         = 'api_trees';
    public const string SCOPE_API_GEDBAS        = 'api_gedbas';
    public const string SCOPE_MCP_READ_PRIVACY  = 'mcp_read_privacy';
    public const string SCOPE_MCP_READ_MEMBER   = 'mcp_read_member';
    public const string SCOPE_MCP_WRITE         = 'mcp_write';
    public const string SCOPE_MCP_GEDBAS        = 'mcp_gedbas';


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

        if (in_array($identifier, self::getScopeIdentifiers())) {
            return new Scope($identifier);
        }

        return null;
    } 

    /**
     * Get scope identifiers
     * 
     * @param bool $include_api_scopes
     * @param bool $include_mcp_scopes
     * @param bool $include_mcp_read_member_scopes
     * @param bool $include_gedbas_scopes
     *
     * @return array
     */     
    public static function getScopeIdentifiers(
        bool $include_api_scopes             = true,
        bool $include_mcp_scopes             = true,
        bool $include_mcp_read_member_scopes = true,
        bool $include_gedbas_scopes          = true
        ): array {

        $scope_identifiers = [];

        if ($include_api_scopes) {
            array_push($scope_identifiers, 
                self::SCOPE_API_READ_MEMBER,
                self::SCOPE_API_READ_PRIVACY,
                self::SCOPE_API_WRITE,
                self::SCOPE_API_IMPORT,
                self::SCOPE_API_EXPORT,
                self::SCOPE_API_TREES,
                self::SCOPE_API_GEDBAS,
            );
        }

        if ($include_mcp_read_member_scopes) {
            array_push($scope_identifiers, 
                self::SCOPE_MCP_READ_MEMBER,
            );
        }

        if ($include_mcp_scopes) {
            array_push($scope_identifiers, 
                self::SCOPE_MCP_READ_PRIVACY,
                self::SCOPE_MCP_WRITE,
            );
        }

        if ($include_gedbas_scopes) {
            array_push($scope_identifiers, 
                self::SCOPE_MCP_GEDBAS,
            );
        }

        return array_combine($scope_identifiers, $scope_identifiers);
    } 

    /**
     * Get MCP scope identifiers
     * 
     * @param bool $include_mcp_read_member
     * 
     * @return array<string,string>
     */     
    public static function getMcpScopeIdentifiers(bool $include_mcp_read_member = false): array {

        return self::getScopeIdentifiers(false, true, $include_mcp_read_member, false);
    }

    /**
     * Get GEDBAS MCP scope identifiers
     * 
     * @return array<string,string>
     */     
    public static function getGedbasMcpScopeIdentifiers(): array {

        return self::getScopeIdentifiers(false, false, false, true);
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
