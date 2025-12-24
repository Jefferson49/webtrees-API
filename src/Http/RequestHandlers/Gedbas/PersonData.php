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
use Fisharebest\Webtrees\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Exception;
use Throwable;


class PersonData implements GedbasMcpToolRequestHandlerInterface
{
    const array ID_SCHEMA = [
        'type' => 'string',
        'description' => 'The GEDBAS ID of a person',
        'pattern' => '^[0-9]{1,12}$',
        'maxLength' => 12,
    ];

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->personData($request);        
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
    private function personData(ServerRequestInterface $request): ResponseInterface {

        $id  = Validator::queryParams($request)->string('id', '');

        // Validate id
        if (!preg_match('/^[0-9]{1,12}$/', $id)) {
            return new Response400('Invalid {id} parameter');
        }

        // Execute request
        $client = new Client();
        $url = 'https://gedbas.genealogy.net/person/show/{id}';
        $url = str_replace('{id}', $id, $url);

        try {
            $response = $client->get($url, [
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                $content = $response->getBody()->getContents();                
            }
            else {
                throw new Exception('GEDBAS request failed with status code ' . $response->getStatusCode());
            }
        }
        catch (GuzzleException $e) {
            throw new Exception('GEDBAS request failed: ' . $e->getMessage());
        }

        $data = $this->parsePersonData($content);

        return Registry::responseFactory()->response(json_encode($data), StatusCodeInterface::STATUS_OK);                
    }

	/**
     * Parse person data from GEDBAS HTML response
     * 
     * @param string $content
     *
     * @return array
     */	
    private function parsePersonData(string $content): array {

		$characteristics = [];
        $events = [];
        $parents = [];
        $families = [];

		libxml_use_internal_errors(true);
		$html  = \Dom\HTMLDocument::createFromString($content);
		libxml_clear_errors();

        //Extract characteristics and events
        foreach ($html->getElementsByTagName('table') as $table) {

            $table_id = $table->getAttribute('id');
            
            if (!in_array($table_id, ['characteristics', 'events'] )) {
                continue;
            }

            $tableHeaders = [];
            foreach ($table->getElementsByTagName('th') as $th) {
                $tableHeaders[] = trim($th->textContent);
            }

            foreach ($table->getElementsByTagName('tr') as $tr) {
                $cells = $tr->getElementsByTagName('td');

                if ($cells->length > 0) {
                    $row = [];

                    // Use index-based access for DOMNodeList to ensure numeric indexing
                    for ($i = 0; $i < $cells->length; $i++) {
                        $cell = $cells->item($i);
                        // guard against missing header names
                        $headerName = $tableHeaders[$i] ?? (string) $i;
                        $row[$headerName] = trim($cell->textContent);
                    }

                    // Do not include certain data
                    unset($row['Sources']);

                    if (isset($row['Place'])) {
                        $row['Place'] = preg_replace("/\\n     .*/", '', $row['Place']);
                    }

                    if ($table_id === 'characteristics') {
                        $characteristics[] = $row;
                    }
                    elseif ($table_id === 'events') {
                        $events[] = $row;
                    }
                }
            }
        }

        //Extract parents
        foreach ($html->getElementsByTagName('table') as $table) {
            if (!in_array($table->getAttribute('id'), ['parents'] )) {
                continue;
            }

            foreach ($table->getElementsByTagName('tr') as $tr) {
                $td = $tr->getElementsByTagName('td');

				for ($i = 0; $i < $td->length; $i++) {
					$cell = $td->item($i);

					$sex = $cell->getAttribute('class');
					$name = trim($cell->textContent);

					$a = $cell->getElementsByTagName('a')->item(0);
					$href = $a->getAttribute('href');
					preg_match_all('/person\/show\/(\d+)/', $href, $matches);
					$id = $matches[1][0] ?? '';

					$parents[] = [
						'parent' => $sex === 'Mann' ? 'father' : 'mother',
						'name'   => $name,
						'id'     => $id,
					];
				}
			}
        }

        //Extract spouses and children
        foreach ($html->getElementsByTagName('table') as $table) {
            if (!in_array($table->getAttribute('id'), ['spouses-and-children'] )) {
                continue;
            }

            $tr = $table->getElementsByTagName('tr');

            //We skip the first table row as it contains the header
            for ($i = 1; $i < $tr->length; $i++) {

				$td = $tr->item($i)->getElementsByTagName('td');

				//Extract marriage data				
				$marriage = $td->item(0);				
				$marriage_date =  $marriage->getElementsByTagName('span')->item(0)->textContent;
				$marriage_place = $marriage->getElementsByTagName('span')->item(1)->textContent;
				
                //Extract spouse data
				$spouse = $td->item(1);
				$spouse_name = trim($spouse->textContent);
				$a = $spouse->getElementsByTagName('a')->item(0);

				//Only continue if there is a spouse link available
				if ($a !== null) {				
					$href = $a->getAttribute('href');
					preg_match_all('/person\/show\/(\d+)/', $href, $matches);
					$spouse_id = $matches[1][0] ?? '';
					
					//Extract children data
					$children_list = [];
					$children = $td->item(2);

					foreach ($children->getElementsByTagName('li') as $child) {

						$birthdate = $child->getElementsByTagName('span')->item(0)->textContent;
						$a = $child->getElementsByTagName('a')->item(0);
						$name = $a->textContent;
						$href = $a->getAttribute('href');
						preg_match_all('/person\/show\/(\d+)/', $href, $matches);
						$id = $matches[1][0] ?? '';

						$children_list[] = [
							'birthdate' => $birthdate,
							'name'      => $name,
							'id'        => $id,
						];
					}

					$families[] = [
						'marriage' => [
							'Date'  => $marriage_date,
							'Place' => $marriage_place,
						],
						'spouse' => [
							'name' => $spouse_name,
							'id'   => $spouse_id,
						],
						'children' => $children_list,
					];					
				}
            }
        }
		
        $data = [
            'characteristics' => $characteristics,
            'events'          => $events,
            'parents'         => $parents,
            'families'        => $families,
        ];

        return $data;
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
                "id"=> self::ID_SCHEMA,
            ]
        ];

        $spouse = [
            "type"=> "object",
            "properties"=> [
                "name"=> [
                    "type"=> "string"
                ],
                "id"=> self::ID_SCHEMA,
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
                "id"=> self::ID_SCHEMA,
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
                "spouse"=> $spouse,
                "children"=> [
                    "type"=> "array",
                    "items"=> $child,
                ]
            ]
        ];

        return [
            'name' => 'get-person-data',
            'description' => 'Get the data for a person with a certain ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    "id"=> self::ID_SCHEMA,
                ],
                'required' => ['id'],
            ],
            'outputSchema' => [
                'name' => 'person-data',
                'type' => 'object',
                'properties' => [
                    "characteristics" => [
                        "type"=> "array",
                        "items"=> $person_property,
                    ],
                    "events"=> [
                        "type"=> "array",
                        "items"=> $person_property,
                    ],
                    "parents"=> [
                        "type"=> "array",
                        "items"=> $parent,
                    ],
                    "families"=> [
                        "type"=> "array",
                        "items"=> $family,
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
