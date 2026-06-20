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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedbasMcp as McpSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedbasTimelimit;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Exception;
use Throwable;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


class SearchSimple implements GedbasMcpToolRequestHandlerInterface
{
    public const string METHOD_DESCRIPTION    = 'Simple GEDBAS search based on lastname, firstname, and placename. Returns a list of IDs for persons matching the search criteria.';
    private const string PARAM_DESC_LASTNAME  = 'The lastname of a person to search for.';
    private const string PARAM_DESC_FIRSTNAME = 'The first name of a person to search for.';
    private const string PARAM_DESC_PLACENAME = 'The place name of a person to search for.';
    private const string PARAM_DESC_TIMELIMIT = 'Limit search to records added to the GEDBAS database within the specified time frame.';

    #[OA\Get(
        path: '/' . WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE,
        description: self::METHOD_DESCRIPTION,
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'lastname',
                in: 'query',
                description: self::PARAM_DESC_LASTNAME,
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                ),
            ),
            new OA\Parameter(
                name: 'firstname',
                in: 'query',
                description: self::PARAM_DESC_FIRSTNAME,
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                ),
            ),
            new OA\Parameter(
                name: 'placename',
                in: 'query',
                description: self::PARAM_DESC_PLACENAME,
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                ),
            ),
            new OA\Parameter(
                name: 'timelimit',
                in: 'query',
                description: self::PARAM_DESC_TIMELIMIT,
                required: false,
                schema: new OA\Schema(
                    ref: GedbasTimelimit::class,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The result of a simple search in GEDBAS, which contains a list with GEDBAS IDs of persons matching the search criteria.', 
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'ids',
                                type: 'array', 
                                items: new OA\Items(
									ref: GedbasID::class
								),
                            ),
                        ],
                        required: ['ids'],
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
            return $this->searchSimple($request);        
        }
        catch (Throwable $th) {
            return api_response($th->getMessage(),StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    private function searchSimple(ServerRequestInterface $request): ResponseInterface
    {
        $lastname  = Validator::queryParams($request)->string('lastname', '');
        $firstname = Validator::queryParams($request)->string('firstname', '');
        $placename = Validator::queryParams($request)->string('placename', '');
        $timelimit = Validator::queryParams($request)->string('timelimit', GedbasTimelimit::TIMELIMIT_NONE);

        // Validate query params
        foreach (['lastname' => $lastname, 'firstname' => $firstname, 'placename' => $placename] as $param_name => $param_value) {
            if (strlen($param_value) > 1024) {
                return api_response('Parameter {' . $param_name . '} too long', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
        }

        if ($lastname === '') {
            return api_response('Missing {lastname} parameter', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        if (!in_array($timelimit, [
                GedbasTimelimit::TIMELIMIT_NONE,
                GedbasTimelimit::TIMELIMIT_YEAR,
                GedbasTimelimit::TIMELIMIT_MONTH,
                GedbasTimelimit::TIMELIMIT_WEEK,
            ])) {
            return api_response('Invalid value for parameter {timelimit}', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        // Add query parameters
        $queryParams = [];
        $queryParams['timelimit'] = $timelimit;

        if ($lastname !== '') {
            $queryParams['lastname'] = $lastname;
        }
        if ($firstname !== '') {
            $queryParams['firstname'] = $firstname;
        }
        if ($placename !== '') {
            $queryParams['placename'] = $placename;
        }

        // Execute request
        $client = new Client();
        $url = 'https://gedbas.genealogy.net/search/simple';

        try {
            $response = $client->get($url, [
                'query'   => $queryParams,
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                $contents = $response->getBody()->getContents();                
            }
            else {
                throw new Exception('GEDBAS request failed with status code ' . $response->getStatusCode());
            }
        }
        catch (GuzzleException $e) {
            throw new Exception('GEDBAS request failed: ' . $e->getMessage());
        }

        //Extract IDs from HTML response
        preg_match_all('/person\/show\/(\d+)"/', $contents, $matches);

        $ids = [];
        foreach ($matches[1] as $id) {
            if (!in_array($id, $ids)) {
                $ids[] = $id;
            }
        }

        return api_response(['ids' => $ids], StatusCodeInterface::STATUS_OK);
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return array
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lastname' => [
                        'type' => 'string',
                        'description' => self::PARAM_DESC_LASTNAME,
                        'maxLength' => 1024,
                    ],
                    'firstname' => [
                        'type' => 'string',
                        'description' => self::PARAM_DESC_FIRSTNAME,
                        'maxLength' => 1024,
                    ],
                    'placename' => [
                        'type' => 'string',
                        'description' => self::PARAM_DESC_PLACENAME,
                        'maxLength' => 1024,
                    ],
                    'timelimit' => [
                        'type' => 'string',
                        'description' => self::PARAM_DESC_TIMELIMIT,
                        'enum' => ['none', 'year', 'month', 'week'],
                        'default' => 'none',
                    ],
                ],
                'required' => ['lastname','timelimit']
            ],
            'outputSchema' => [
                'type' => 'object',
                'description' => 'A list with GEDBAS IDs of persons matching the search criteria.',
                'properties' => [
                    'ids' => [
                        'type' => 'array',
                        'items' => McpSchema::ID,
                    ],
                ],
                'required' => ['ids'],
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE,
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
