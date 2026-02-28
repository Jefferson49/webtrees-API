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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Gedcom as GedcomSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Mcp as McpSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Xref as XrefSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\XrefItem;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class ModifyRecord implements WebtreesMcpToolRequestHandlerInterface
{
    private TreeService $tree_service;

    public const string METHOD_DESCRIPTION = 'Modify the GEDCOM data of a record.';

    public function __construct(TreeService $tree_service)
    {
        $this->tree_service = $tree_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_MODIFY_RECORD,
        description: self::METHOD_DESCRIPTION,
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'xref',
                in: 'query',
                description: 'The XREF (i.e. GEDOM cross-reference identifier) of the record to modify.',
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
            new OA\Parameter(
                // We cannot take the Gedcom parameter, since it is NOT required by default
                name: 'gedcom',
                in: 'query',
                description: GedcomParameter::GEDCOM_DESCRIPTION,
                required: true,
                schema: new OA\Schema(
                    ref: GedcomSchema::class,
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully modified record.',
                content: new OA\MediaType(
                    mediaType: 'application/json', 
                    schema: new OA\Schema(ref: XrefItem::class),
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
            return $this->modifyRecord($request);        
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
    private function modifyRecord(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('xref', '');
        $gedcom    = Validator::queryParams($request)->string('gedcom', '');

        // Adopt line breaks for GEDCOM text        
        $gedcom    = str_replace(["\r\n", '\n', "%OA"], ["\n", "\n", "\n"], $gedcom);
        $gedcom    = trim($gedcom);

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = $this->tree_service->all()[$tree_name];

        // Validate XREF
        $xref_validation_response = QueryParamValidator::validateXref($tree, $xref);
        if (get_class($xref_validation_response) !== Response200::class) {
            return $xref_validation_response;
        }

        $record = Registry::gedcomRecordFactory()->make($xref, $tree);

        // Validate record access
        $xref_validation_response = CheckAccess::checkRecordAccess($record, true);
        if (get_class($xref_validation_response) !== Response200::class) {
            return $xref_validation_response;
        }       

        // Check user write access
        $user_rights_response = CheckAccess::checkUserWriteAccess($tree);
        if (get_class($user_rights_response) !== Response200::class) {
            return $user_rights_response;
        }

        // Validate GEDCOM
        $gedcom_validation_response = QueryParamValidator::validateGedcomRecord($gedcom, false);
        if (get_class($gedcom_validation_response) !== Response200::class) {
            return $gedcom_validation_response;
        }

        // Validate level 0 structure in first line
        if (1 === preg_match('/0 @(' . Gedcom::REGEX_XREF . ')@ (' .Gedcom::REGEX_TAG . ')/', $gedcom, $matches)) {

            $level0 = $matches[0];

            if ($matches[1] !== $xref) {
                return new Response400('Level 0 GEDCOM line contains different XREF than query parameter: ' . $level0);
            }
            if ($matches[2] !== $record->tag()) {
                return new Response400('Level 0 GEDCOM line contains different record type than record: ' . $level0);
            }
        }
        else {
            $level0 = '';
        }

        // Generate the level-0 line for the record.
        switch ($record->tag()) {
            case GedcomRecord::RECORD_TYPE:
                // Unknown type? - copy the existing data.
                $modified_gedcom = explode("\n", $record->gedcom(), 2)[0];
                break;
            case Header::RECORD_TYPE:
                $modified_gedcom = '0 HEAD';
                break;
            default:
                $modified_gedcom = '0 @' . $xref . '@ ' . $record->tag();
        }

        if ($level0 !== '') {
            $modified_gedcom = $level0;
            $gedcom = str_replace([$level0 . "\n", $level0], ['', ''], $gedcom);
        }

        // Retain any private facts
        $all_facts  = $record->facts([], false, Auth::PRIV_HIDE, true);
        $user_facts = $record->facts([], false, Auth::accessLevel($tree), true);

        foreach ($all_facts->toArray() as $fact) {
            if (!in_array($fact, $user_facts->toArray(), true)) {
                $modified_gedcom .= "\n" . $fact->gedcom();
            }
        }

        // Append the updated GEDCOM
        $modified_gedcom .= "\n" . $gedcom;

        // Empty lines and MSDOS line endings.
        $modified_gedcom = preg_replace('/[\r\n]+/', "\n", $modified_gedcom);
        $modified_gedcom = trim($modified_gedcom);

        $record->updateRecord($modified_gedcom, false);

        return Registry::responseFactory()->response(
            json_encode(new XrefItem($record->xref())),
            StatusCodeInterface::STATUS_OK
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
            'name' => WebtreesApi::PATH_MODIFY_RECORD,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::TREE,
                    'xref' => McpSchema::withDescription(McpSchema::XREF,
                        'The XREF of the record to modify.',
                        McpSchema::APPEND
                    ),
                    'gedcom' => McpSchema::withDescription(McpSchema::GEDCOM,
                        'The GEDCOM text for to the modified record.',
                        McpSchema::PREPEND
                    ),
                ],
                'required' => ['tree', 'xref', 'gedcom']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'xref' => McpSchema::XREF,
                ],
                'required' => ['xref'],
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_MODIFY_RECORD,
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
