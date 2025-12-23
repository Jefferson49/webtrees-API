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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Registry;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class SearchSimple implements GedbasMcpToolRequestHandlerInterface
{
	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->Ids($request);        
        }
        catch (Throwable $th) {
            return new Response500($th->getMessage());
        }
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    private function Ids(ServerRequestInterface $request): ResponseInterface
    {
        $Ids = ['1234567890'];
        $result = [
            'ids' => $Ids,
        ];

        return Registry::responseFactory()->response(json_encode($result), StatusCodeInterface::STATUS_OK);
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return array
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'get-search-simple',
            'description' => 'Simple GEDBAS search based on lastname, firstname, placename. Returns a list of person IDs',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lastname' => [
                        'type' => 'string',
                        'description' => 'The lastname of a person to search for',
                        'maxLength' => 1024,
                    ],
                    'firstname' => [
                        'type' => 'string',
                        'description' => 'The first name of a person to search for',
                        'maxLength' => 1024,
                    ],
                    'placename' => [
                        'type' => 'string',
                        'description' => 'The place name of a person to search for',
                        'maxLength' => 1024,
                    ],
                    'timelimit' => [
                        'type' => 'string',
                        'description' => 'Limit search to records added within the specified time frame',
                        'enum' => ['none', 'year', 'month', 'week'],
                        'default' => 'none',
                    ],
                ],
                'required' => ['lastname','timelimit']
            ],
            'outputSchema' => [
                'type' => 'object',
                'description' => 'A list of person IDs matching the search criteria',
                'properties' => [
                    'ids' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'required' => ['ids'],
            ],
            'annotations' => [
                'title' => 'get-search-simple',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
