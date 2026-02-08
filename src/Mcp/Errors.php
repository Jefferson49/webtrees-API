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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Mcp;

use Fig\Http\Message\StatusCodeInterface;

/**
 * MCP errors
 */
class Errors
{
    public const int PARSE_ERROR      = -32700;
    public const int INVALID_REQUEST  = -32600;
    public const int METHOD_NOT_FOUND = -32601;
    public const int INVALID_PARAMS   = -32602;
    public const int INTERNAL_ERROR   = -32603;
    public const int SERVER_ERROR     = -32000;


	/**
     * Get the MCP error message for an MCP error code
     * 
     * @param  int $error_code
     *
     * @return string
     */	
    public static function getMcpErrorMessage(int $error_code): string {

        return match ($error_code) {
            self::PARSE_ERROR      => 'Parse error',
            self::INVALID_REQUEST  => 'Invalid Request',
            self::METHOD_NOT_FOUND => 'Method not found',
            self::INVALID_PARAMS   => 'Invalid params',
            self::INTERNAL_ERROR   => 'Internal error',
            self::SERVER_ERROR     => 'Server error',
            default                => 'Unknown error',
        };
    }

	/**
     * Get the MCP error code corresponding to a HTTP error code
     * 
     * @param  int $http_status_code
     *
     * @return string
     */	
    public static function getMcpError(int $http_status_code): string {
        
        return match ($http_status_code) {
            StatusCodeInterface::STATUS_BAD_REQUEST => self::INVALID_PARAMS,
            StatusCodeInterface::STATUS_NOT_FOUND   => self::INVALID_PARAMS,
            default                                 => self::SERVER_ERROR,
        };
    }
}
