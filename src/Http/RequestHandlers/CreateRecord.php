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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Xref;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class CreateRecord implements McpToolRequestHandlerInterface
{
    #[OA\Post(
        path: '/create-record',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'tree',
                in: 'query',
                description: 'The name of the tree.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                    pattern: '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    example: 'mytree',
                ),
            ),
            new OA\Parameter(
                name: 'record-type',
                in: 'query',
                description: 'The type of the GEDCOM record to create.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: [
                        Family::RECORD_TYPE, 
                        Individual::RECORD_TYPE, 
                        Media::RECORD_TYPE, 
                        Note::RECORD_TYPE, 
                        Repository::RECORD_TYPE, 
                        Source::RECORD_TYPE, 
                        Submitter::RECORD_TYPE
                    ],
                    pattern: '^' . Gedcom::REGEX_TAG . '$',
                    maxLength: 4,
                    example: 'INDI',
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
            ),            
            new OA\Response(
                response: '201', 
                description: 'Created',
                content: new OA\MediaType(
                    mediaType: 'application/json', 
                    schema: new OA\Schema(ref: Xref::class),
                ),
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
                ref: Response429::class,
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
            return $this->createRecord($request);        
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
    private function createRecord(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name   = Validator::queryParams($request)->string('tree', '');
        $record_type = Validator::queryParams($request)->string('record-type', '');

        // Validate tree       
        if ($tree_name === '') {
            return new Response400('Invalid tree parameter');
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $tree_name)) {
            return new Response400('Invalid tree parameter');
        }
        elseif (strlen($tree_name) > 1024) {
            return new Response400('Invalid tree parameter');
        }
        elseif (!Functions::isValidTree($tree_name)) {
            return new Response404('Tree not found');
        } 
        else {
            $tree = Functions::getAllTrees()[$tree_name];
        }

        // Validate record type
        $record_types = [ 
            Family::RECORD_TYPE, 
            Individual::RECORD_TYPE, 
            Media::RECORD_TYPE, 
            Note::RECORD_TYPE, 
            Repository::RECORD_TYPE, 
            Source::RECORD_TYPE, 
            Submitter::RECORD_TYPE
        ];
        if (!in_array($record_type, $record_types, true)) {
            return new Response400('Invalid record-type parameter');
        }

        //Check if user uses automatically accepts edits 
        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return new Response403('Unauthorized: Automatically accept changes is activated for the API user.');
        }

        if (Auth::isModerator($tree)) {
            return new Response403('Unauthorized: API users must not have moderator rights');
        }        

        if (!Auth::isEditor($tree)) {
            return new Response403('Unauthorized: API user does not have editor rights for the tree.');
        }        

        //Default GEDCOM
        $gedcom = "\n1 NOTE Created by: Webtrees API";
        $record = $tree->createRecord('0 @@ ' . $record_type . $gedcom);

        //Logout
        Auth::logout();

        return Registry::responseFactory()->response(
            json_encode(new Xref($record->xref())) ,
            StatusCodeInterface::STATUS_CREATED
        );
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'create-record',
            'description' => 'POST /create-record [API: POST /create-record]',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => [
                        'type' => 'string',
                        'description' => 'The name of the tree. (in: query)',
                        'maxLength' => 1024,
                        'pattern' => '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    ],
                    'record-type' => [
                        'type' => 'string',
                        'description' => 'The type of the GEDCOM record to create.',
                        'enum' => [ 
                            Family::RECORD_TYPE, 
                            Individual::RECORD_TYPE, 
                            Media::RECORD_TYPE, 
                            Note::RECORD_TYPE, 
                            Repository::RECORD_TYPE, 
                            Source::RECORD_TYPE, 
                            Submitter::RECORD_TYPE
                        ],
                        'maxLength' => 4,
                        'pattern' => '^' . Gedcom::REGEX_TAG .'$',
                        ]
                ],
                'required' => ['tree', 'xref']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'xref' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['xref'],
            ],
            'annotations' => [
                'title' => 'POST /create-record',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
