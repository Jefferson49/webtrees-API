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

    public const string SCOPE_API_READ       = 'api_read';
    public const string SCOPE_API_WRITE      = 'api_write';
    public const string SCOPE_API_CLI        = 'api_cli';
    public const string SCOPE_API_SWAGGER_UI = 'api_swagger_ui';
    public const string SCOPE_MCP_GEDBAS     = 'mcp_gedbas';
    public const string SCOPE_MCP_READ       = 'mcp_read';
    public const string SCOPE_MCP_WRITE      = 'mcp_write';

    // All scopes
    private static array $scopes = [
        ScopeRepository::SCOPE_API_READ,
        ScopeRepository::SCOPE_API_WRITE,
        ScopeRepository::SCOPE_API_CLI,
        ScopeRepository::SCOPE_API_SWAGGER_UI,
        ScopeRepository::SCOPE_MCP_GEDBAS,
        ScopeRepository::SCOPE_MCP_READ,
        ScopeRepository::SCOPE_MCP_WRITE,
    ];

    // Scopes for MCP
    private static array $mcp_scope_identifiers = [
        ScopeRepository::SCOPE_MCP_GEDBAS,
        ScopeRepository::SCOPE_MCP_READ,
        ScopeRepository::SCOPE_MCP_WRITE,
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
     * @return array
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

        if (in_array($identifier, self::$scopes)) {
            return new Scope($identifier);
        }

        return null;
    } 

    /**
     * Get MCP scope identifiers
     * 
     * @return array
     */     
    public static function getMcpScopeIdentifiers(): array {

        return self::$mcp_scope_identifiers;
    } 
}
