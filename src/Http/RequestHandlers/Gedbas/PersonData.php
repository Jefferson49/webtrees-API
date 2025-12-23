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


class PersonData implements GedbasMcpToolRequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->Data($request);        
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
    private function Data(ServerRequestInterface $request): ResponseInterface
    {
        $person_data = [
            'characteristics'  => [
                [
                    'Type' => 'NAME',
                    'Value' => 'Henry Miller',
                ],
            ],
            'events' => [
                [
                    'Type' => 'BIRT',
                    'Date' => '1 JAN 1900',
                    'Place'=> 'Springfield, USA',
                ],
            ],
        ];

        $result = [
            'person-data' => $person_data,
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
        $id = [
            'type' => 'string',
            'description' => 'The GEDBAS ID of a person',
            'pattern' => '^[0-9]+$',
            'maxLength' => 12,
        ];

        $person_property = [
            "type"=> "object",
            "properties"=> [
                "Type"=> [
                    "type"=> "string"
                ],
                "Value"=> [
                    "type"=> "string"
                ],
                "Date"=> [
                    "type"=> "string"
                ],
                "Place"=> [
                    "type"=> "string"
                ]
            ]
        ];

        $parent = [
            "type"=> "object",
            "properties"=> [
                "parent"=> [
                    "type"=> "string"
                ],
                "name"=> [
                    "type"=> "string"
                ],
                "id"=> [
                    $id
                ]
            ]
        ];

        $spouse = [
            "type"=> "object",
            "properties"=> [
                "name"=> [
                    "type"=> "string"
                ],
                "id"=> [
                    $id
                ]
            ]
        ];

        $child = [
            "type"=> "object",
            "properties"=> [
                "name"=> [
                    "type"=> "string"
                ],
                "birthdate"=> [
                    "type"=> "string"
                ],
                "id"=> [
                    $id
                ]
            ]
        ];

        $family = [
            "type"=> "object",
            "properties"=> [
                "mariage"=> [
                    "type"=> "object",
                    "properties"=> [
                        "Date"=> [
                            "type"=> "string"
                        ],
                        "Place"=> [
                            "type"=> "string"
                        ]
                    ]
                ],
                "spouse"=> [
                    $spouse
                ],
                "children"=> [
                    "type"=> "array",
                    "items"=> [
                        $child
                    ]
                ]
            ]
        ];

        return [
            'name' => 'get-person-data',
            'description' => 'Get the data for a person with a certain ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        $id
                    ],
                ],
                'required' => ['id'],
            ],
            'outputSchema' => [
                'name' => 'person-data',
                'type' => 'object',
                'properties' => [
                    "characteristics" => [
                        "type"=> "array",
                        "items"=> [
                            $person_property
                        ],
                    ],
                    "events"=> [
                        "type"=> "array",
                        "items"=> [
                            $person_property
                        ]
                    ],
                    "parents"=> [
                        "type"=> "array",
                        "items"=> [
                            $parent,
                        ]
                    ],
                    "families"=> [
                        "type"=> "array",
                        "items"=> [
                            $family,
                        ],
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'get-person-data',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
