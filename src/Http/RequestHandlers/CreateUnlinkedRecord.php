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


class CreateUnlinkedRecord implements WebtreesMcpToolRequestHandlerInterface
{
    #[OA\Post(
        path: '/create-unlinked-record',
        description: 'Create a GEDCOM record in webtrees, which is not linked to any other record',
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
            new OA\Parameter(
                name: 'gedcom',
                in: 'query',
                description: 'The GEDCOM text, which shall be added to the newly created record. The GEDCOM text must not contain a level 0 line, because it is created automatically. "\n" or "%OA" will be detected as line break.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    default: '',
                    example: "1 NOTE A record created by the webtrees API.\n1 NOTE Read description about line breaks.",
                ),
            ),
        ],
        responses: [          
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
            return $this->createUnlinkedRecord($request);        
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
    private function createUnlinkedRecord(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name   = Validator::queryParams($request)->string('tree', '');
        $record_type = Validator::queryParams($request)->string('record-type', '');
        $gedcom      = Validator::queryParams($request)->string('gedcom', '');

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

        // Adopt line breaks for GEDCOM text
        $gedcom = str_replace(["\r\n", '\n', "%OA"], ["\n", "\n", "\n"], $gedcom);
        $gedcom_lines = explode("\n", $gedcom);

        // Validate GEDCOM text
        foreach ($gedcom_lines as $gedcom_line) {
            if (1 !== preg_match('/(\d+) (' . Gedcom::REGEX_TAG . ') (.*)/', $gedcom_line, $matches) ) {
                return new Response400('Invalid format of GEDCOM line: ' . $gedcom_line);
            }
            if ($matches[1] === '0') {
                return new Response400('The GEDCOM text must not contain a level 0 line: ' . $gedcom_line);
            }
        }

        //Check if user uses automatically accepts edits 
        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return new Response403('Unauthorized: Automatically accept changes must be activated for the API user.');
        }

        if (Auth::isModerator($tree)) {
            return new Response403('Unauthorized: API users must not have moderator rights');
        }        

        if (!Auth::isEditor($tree)) {
            return new Response403('Unauthorized: API user does not have editor rights for the tree.');
        }        

        //Normalize GEDCOM text
        $gedcom = preg_replace('/[\r\n]+/', "\n", $gedcom);
        $gedcom = trim($gedcom);

        // Create record
        $record = $tree->createRecord('0 @@ ' . $record_type . "\n" . $gedcom);

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
            'name' => 'create-unlinked-record',
            'description' => 'Create a GEDCOM record in webtrees, which is not linked to any other record [API: POST /create-unlinked-record]',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => [
                        'type' => 'string',
                        'description' => 'The name of the tree.',
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
                    ],
                    'gedcom' => [
                        'type' => 'string',
                        'description' => 'The GEDCOM text, which shall be added to the newly created record. The GEDCOM text must not contain a level 0 line, because it is created automatically. "\n" or "%OA" will be detected as line break.',
                        'default' => '',
                        'example' => '1 NOTE A record created by the webtrees API.\n1 NOTE Read description about line breaks.',
                    ],
                ],
                'required' => ['tree', 'record-type']
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
                'title' => 'create-unlinked-record',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
