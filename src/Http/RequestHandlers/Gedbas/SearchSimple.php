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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use Exception;
use Throwable;


class SearchSimple implements GedbasMcpToolRequestHandlerInterface
{
    private ResponseFactoryInterface $response_factory;

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
            return new Response500($th->getMessage());
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
        $timelimit = Validator::queryParams($request)->string('timelimit', '');

        // Validate query params
        foreach (['lastname' => $lastname, 'firstname' => $firstname, 'placename' => $placename] as $param_name => $param_value) {
            if (strlen($param_value) > 1024) {
                return new Response400('Parameter {' . $param_name . '} too long');
            }
        }

        if ($lastname === '') {
            return new Response400('Missing {lastname} parameter');
        }

        if (!in_array($timelimit, ['none', 'year', 'month', 'week'])) {
            return new Response400("Invalid value for parameter {timelimit}");
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

        return Registry::responseFactory()->response(json_encode(['records' => $ids]), StatusCodeInterface::STATUS_OK);                
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return array
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'search-simple',
            'description' => 'Simple GEDBAS search based on lastname, firstname, and placename. Returns a list of IDs for persons matching the search criteria.',
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
                        'description' => 'Limit search to records added to the GEDBAS database within the specified time frame',
                        'enum' => ['none', 'year', 'month', 'week'],
                        'default' => 'none',
                    ],
                ],
                'required' => ['lastname','timelimit']
            ],
            'outputSchema' => [
                'type' => 'object',
                'description' => 'A list of IDs for persons matching the search criteria',
                'properties' => [
                    'ids' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'description' => 'A person ID',
                            'pattern' => '^[0-9]+$',
                            'maxLength' => 12,
                        ],
                    ],
                ],
                'required' => ['ids'],
            ],
            'annotations' => [
                'title' => 'search-simple',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
