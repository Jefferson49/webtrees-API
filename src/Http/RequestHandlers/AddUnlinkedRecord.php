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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Gedcom as GedcomParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Mcp as McpSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\XrefItem;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class AddUnlinkedRecord implements WebtreesMcpToolRequestHandlerInterface
{
    #[OA\Post(
        path: '/add-unlinked-record',
        description: 'Create a GEDCOM record, which is not linked to any other record.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
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
                ref: GedcomParameter::class,
            ),
        ],
        responses: [          
            new OA\Response(
                response: '201', 
                description: 'Created',
                content: new OA\MediaType(
                    mediaType: 'application/json', 
                    schema: new OA\Schema(ref: XrefItem::class),
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
                response: '404',
                description: 'Not found: Tree does not exist, or no matching GEDCOM record found for XREF.',
                ref: Response404::class,
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
        $tree_validation_response = QueryParamValidator::validateTreeName($tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = Functions::getAllTrees()[$tree_name];

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
        $gedcom = trim($gedcom);

        // Validate GEDCOM
        $gedcom_validation_response = QueryParamValidator::validateGedcomRecord($gedcom);
        if (get_class($gedcom_validation_response) !== Response200::class) {
            return $gedcom_validation_response;
        }  
        
        //Check user write access
        $user_rights_response = CheckAccess::checkUserWriteAccess($tree);
        if (get_class($user_rights_response) !== Response200::class) {
            return $user_rights_response;
        }  

        // Create record
        $record = $tree->createRecord('0 @@ ' . $record_type . "\n" . $gedcom);

        //Logout
        Auth::logout();

        return Registry::responseFactory()->response(
            json_encode(new XrefItem($record->xref())),
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
            'name' => 'add-unlinked-record',
            'description' => 'Create a GEDCOM record, which is not linked to any other record.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::TREE,
                    'record-type' => McpSchema::RECORD_TYPE,
                    'gedcom' => McpSchema::withDescription(McpSchema::GEDCOM,
                        'The GEDCOM text, which shall be added to the newly created record.',
                        McpSchema::PREPEND
                    ),
                ],
                'required' => ['tree', 'record-type']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'xref' => McpSchema::XREF,
                ],
                'required' => ['xref'],
            ],
            'annotations' => [
                'title' => 'add-unlinked-record',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
