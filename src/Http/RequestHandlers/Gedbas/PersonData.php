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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedbasID;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedbasMcp as GedbasMcpSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedbasPersonProperties;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Exception;
use Throwable;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


class PersonData implements GedbasMcpToolRequestHandlerInterface
{
    public const string METHOD_DESCRIPTION = 'Get the GEDBAS data for a person with a certain GEDBAS ID.';
    
    #[OA\Get(
        path: '/' . WebtreesApi::PATH_GEDBAS_PERSON_DATA,
        description: self::METHOD_DESCRIPTION,
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'query',
                description: 'The GEDBAS ID of a person.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    pattern: '^[0-9]{1,12}$',
                    maxLength: 12,
                ),
            ),
            new OA\Parameter(
                name: 'uid',
                in: 'query',
                description: 'The Unique Identifier (UID) of a person.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'A record with person data as result of a GEDBAS search.',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'characteristics',
                                type: 'array',
                                items: new OA\Items(
                                    ref: GedbasPersonProperties::class
                                ),
                            ),
                            new OA\Property(
                                property: 'events',
                                type: 'array',
                                items: new OA\Items(
                                    ref: GedbasPersonProperties::class
                                ),
                            ),
                            new OA\Property(
                                property: 'parents',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'parent',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'name',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'id',
                                            ref: GedbasID::class
                                        ),
                                    ],
                                ),
                            ),
                            new OA\Property(
                                property: 'families',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'marriage',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'Date',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'Place',
                                                    type: 'string',
                                                ),
                                            ],
                                        ),
                                        new OA\Property(
                                            property: 'spouse',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'name',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'id',
                                                    ref: GedbasID::class
                                                ),
                                            ],
                                        ),
                                        new OA\Property(
                                            property: 'children',
                                            type: 'array',
                                            items: new OA\Items(
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(
                                                        property: 'name',
                                                        type: 'string',
                                                    ),
                                                    new OA\Property(
                                                        property: 'birthday',
                                                        type: 'string',
                                                    ),
                                                    new OA\Property(
                                                        property: 'id',
                                                        ref: GedbasID::class
                                                    ),
                                                ],
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                            new OA\Property(
                                property: 'sources',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'id',
                                            description: 'The ID of the source',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'title',
                                            description: 'The title of the source',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'author',
                                            description: 'The author of the source',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'text',
                                            description: 'The text of the source',
                                            type: 'string',
                                        ),
                                    ],
                                ),
                            ),                            
                            new OA\Property(
                                property: 'database',
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'id',
                                        description: 'The ID of the database.',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'title',
                                        description: 'The title of the database.',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'description',
                                        description: 'The description of the database.',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'submitter',
                                        description: 'The submitter of the database.',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'email',
                                        description: 'The email of the submitter of the database.',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'upload_date',
                                        description: 'The date, at which the database was uploaded to GEDBAS.',
                                        type: 'string',
                                    ),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: '400', 
                description: 'Bad request: Validation of input parameters failed.',
                ref: Response400::class,
            ),
            new OA\Response(
                response: '401', 
                description: 'Unauthorized: Missing authorization header or bearer token.',
                ref: Response401::class,
            ),
            new OA\Response(
                response: '403', 
                description: 'Unauthorized: Insufficient permissions.',
                ref: Response403::class,
            ),
            new OA\Response(
                response: '406', 
                description: 'Not acceptable',
                ref: Response406::class,
            ),
            new OA\Response(
                response: '429', 
                description: 'Too many requests',
                ref: Response429::class,
            ),
            new OA\Response(
                response: '500', 
                description: 'Internal server error',
                ref: Response500::class,
            ),
        ]
    )]
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
            return api_response($th->getMessage(), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    private function personData(ServerRequestInterface $request): ResponseInterface {

        $id  = Validator::queryParams($request)->string('id', '');
        $uid = Validator::queryParams($request)->string('uid', '');

        // One of either id or uid needs to exist
        if ($id === '' && $uid === '') {
            return api_response('Neither id nor uid received.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        // Validate id
        if ($id !== '' && !preg_match('/^[0-9]{1,12}$/', $id)) {
            return api_response('Invalid {id} parameter', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        // Execute request
        $client = new Client();

        if ($uid !== '') {
            $url = 'https://gedbas.de/uid/{uid}';
            $url = str_replace('{uid}', $uid, $url);
        }
        else {
            $url = 'https://gedbas.genealogy.net/person/show/{id}';
            $url = str_replace('{id}', $id, $url);
        }

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

        return api_response($data, StatusCodeInterface::STATUS_OK);
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

                    if (isset($row['Place'])) {
                        $row['Place'] = preg_replace("/\\n     .*/", '', $row['Place']);
                    }

                    if (isset($row['Sources'])) {
                        preg_match_all("/\[(.+?)\]/", $row['Sources'], $matches);
                        unset($row['Sources']);
                        $row['Source IDs'] = $matches[1];
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

        //Extract sources
        foreach ($html->getElementsByTagName('div') as $div) {
            if (!in_array($div->getAttribute('id'), ['gedbas-sources'] )) {
                continue;
            }

            $tr = $div->getElementsByTagName('tr');

            // We iteratate in steps of 2, beacuse each source contains 2 rows
            for ($i = 0; $i < $tr->length; $i += 2) {

                $td1 = $tr->item($i)->getElementsByTagName('td');
                $td2 = $tr->item($i+1)->getElementsByTagName('td');

				//Extract source data
				$source_id_element     = $td1 !== null ? $td1->item(0)->getElementsByTagName('a')->item(0) : null;
				$source_title_element  = $td1 !== null ? $td1->item(1)->getElementsByTagName('b')->item(0) : null;
				$source_author_element = $td1 !== null ? $td1->item(1)->getElementsByTagName('span')->item(1) : null;
                $source_text_element   = $td2 !== null ? $td2->item(1) : null;
				
                if ($source_id_element !== null) {

                    $sources[] = [
                        'id'     => $source_id_element !== null ? str_replace('source_', '', $source_id_element->id) : '',
                        'title'  => $source_title_element !== null ? $source_title_element->innerHTML : '',
                        'author' => $source_author_element !== null ? $source_author_element->innerHTML : '',
                        'text'   => $source_text_element !== null ? $source_text_element->innerHTML : '',
                    ];					
                }
            }
        }        

        //Extract database information
        $database = [];

        foreach ($html->getElementsByTagName('div') as $div) {
            if (!in_array($div->getAttribute('id'), ['gedbas-database'] )) {
                continue;
            }

            $tr = $div->getElementsByTagName('tr');

            if ($tr->length > 4) {

                $database_title_element          = $tr->item(0)->getElementsByTagName('td')->item(0);
                $database_description_paragraphs = $tr->item(1)->getElementsByTagName('p');
                $database_id_element             = $tr->item(2)->getElementsByTagName('td')->item(0);
                $database_upload_date_element    = $tr->item(3)->getElementsByTagName('td')->item(0);
                $database_submitter_element      = $tr->item(4)->getElementsByTagName('span')->item(0);
                $database_email_element          = $tr->item(5)->getElementsByTagName('a')->item(0);

                $database_description = '';

                for ($i = 0; $i < $database_description_paragraphs->length; $i++) {

                    $database_description .= ' ' . $database_description_paragraphs->item($i)->innerHTML;
                }

                $database[] = [
                    'id'          => $database_id_element !== null ? $database_id_element->innerHTML : '',
                    'title'       => $database_title_element !== null ? $database_title_element->innerHTML : '',
                    'description' => $database_description,
                    'submitter'   => $database_submitter_element !== null ? $database_submitter_element->innerHTML : '',
                    'email'       => $database_email_element !== null ? $database_email_element->innerHTML : '',
                    'upload_date' => $database_upload_date_element !== null ? $database_upload_date_element->innerHTML : '',
                ];
            }
        }        

        $data = [
            'characteristics' => $characteristics,
            'events'          => $events,
            'parents'         => $parents,
            'families'        => $families,
            'sources'         => $sources,
            'database'        => $database,
        ];

        return $data;
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return array
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => WebtreesApi::PATH_GEDBAS_PERSON_DATA,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    "id"  => GedbasMcpSchema::ID,
                    "uid" => GedbasMcpSchema::UID,
                ],
            ],
            'outputSchema' => [
                'name' => 'person-data',
                'type' => 'object',
                'properties' => [
                    "characteristics" => [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::PERSON_PROPERTY,
                    ],
                    "events" => [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::PERSON_PROPERTY,
                    ],
                    "parents" => [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::PARENT,
                    ],
                    "families"=> [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::FAMILY,
                    ],
                    "sources" => [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::SOURCE,
                    ],
                    "database" => [
                        "type"  => "array",
                        "items" => GedbasMcpSchema::DATABASE,
                    ],
                ],
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_GEDBAS_PERSON_DATA,
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
