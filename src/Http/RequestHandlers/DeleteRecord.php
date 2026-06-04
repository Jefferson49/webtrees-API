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
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Validator;
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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Xref as XrefSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class DeleteRecord implements WebtreesMcpToolRequestHandlerInterface
{
    private LinkedRecordService $linked_record_service;
    private TreeService $tree_service;

    public const string METHOD_DESCRIPTION = 'Delete a GEDCOM record.';

    public function __construct(TreeService $tree_service, LinkedRecordService $linked_record_service)
    {
        $this->tree_service          = $tree_service;
        $this->linked_record_service = $linked_record_service;
    }

    #[OA\Delete(
        path: '/' . WebtreesApi::PATH_DELETE_RECORD,
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
                description: 'The XREF (i.e. GEDOM cross-reference identifier) of the record to delete.',
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully deleted record.',
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
            return $this->deleteRecord($request);        
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
    private function deleteRecord(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('xref', '');

        $message = '';

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

        // Use default language for API responses        
        $current_language = Session::get('language', '');
        $default_language = 'en-US';
        I18N::init($default_language);
        Session::put('language', $default_language);

        // Code from: Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord.php

        if (Auth::isEditor($record->tree()) && $record->canShow() && $record->canEdit()) {
            // Delete links to this record
            foreach ($this->linked_record_service->allLinkedRecords($record) as $linker) {
                $old_gedcom = $linker->gedcom();
                $new_gedcom = $this->removeLinks($old_gedcom, $record->xref());
                if ($old_gedcom !== $new_gedcom) {
                    // If we have removed a link from a family to an individual, and it now has only one member and no genealogy facts
                    if (
                        $linker instanceof Family &&
                        preg_match('/\n1 (ANUL|CENS|DIV|DIVF|ENGA|MAR[BCLRS]|RESI|EVEN)/', $new_gedcom, $match) !== 1 &&
                        preg_match_all('/\n1 (HUSB|WIFE|CHIL) @(' . Gedcom::REGEX_XREF . ')@/', $new_gedcom, $match) === 1
                    ) {
                        // Delete the family
                        /* I18N: %s is the name of a family group, e.g. “Husband name + Wife name” */
                        $message .= (I18N::translate('The family “%s” has been deleted because it only has one member.', $linker->fullName()));
                        $linker->deleteRecord();
                        // Delete the remaining link to this family
                        $relict = Registry::gedcomRecordFactory()->make($match[2][0], $tree);
                        if ($relict instanceof Individual) {
                            $relict_gedcom = $this->removeLinks($relict->gedcom(), $linker->xref());
                            $relict->updateRecord($relict_gedcom, false);
                            /* I18N: %s are names of records, such as sources, repositories or individuals */
                            $message .= (I18N::translate('The link from “%1$s” to “%2$s” has been deleted.', sprintf('<a href="%1$s" class="alert-link">%2$s</a>', e($relict->url()), $relict->fullName()), $linker->fullName()));
                        }
                    } else {
                        // Remove links from $linker to $record
                        /* I18N: %s are names of records, such as sources, repositories or individuals */
                        $message .= (I18N::translate('The link from “%1$s” to “%2$s” has been deleted.', sprintf('<a href="%1$s" class="alert-link">%2$s</a>', e($linker->url()), $linker->fullName()), $record->fullName()));
                        $linker->updateRecord($new_gedcom, false);
                    }
                }
            }
            // Delete the record itself
            $record->deleteRecord();
        }

        //Reset language
        I18N::init($current_language);
        Session::put('language', $current_language);

        return Registry::responseFactory()->response(
            'Record deleted successfully.' . ($message ? ' ' . strip_tags($message) : ''),
            StatusCodeInterface::STATUS_OK
        );
    }

    /**
     * Remove all links from $gedrec to $xref, and any sub-tags.
     * Code from: Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord.php
     *
     * @param string $gedrec
     * @param string $xref
     *
     * @return string
     */
    private function removeLinks(string $gedrec, string $xref): string
    {
        $gedrec = preg_replace('/\n1 ' . Gedcom::REGEX_TAG . ' @' . $xref . '@(\n[2-9].*)*/', '', $gedrec);
        $gedrec = preg_replace('/\n2 ' . Gedcom::REGEX_TAG . ' @' . $xref . '@(\n[3-9].*)*/', '', $gedrec);
        $gedrec = preg_replace('/\n3 ' . Gedcom::REGEX_TAG . ' @' . $xref . '@(\n[4-9].*)*/', '', $gedrec);
        $gedrec = preg_replace('/\n4 ' . Gedcom::REGEX_TAG . ' @' . $xref . '@(\n[5-9].*)*/', '', $gedrec);
        $gedrec = preg_replace('/\n5 ' . Gedcom::REGEX_TAG . ' @' . $xref . '@(\n[6-9].*)*/', '', $gedrec);

        return $gedrec;
    }    

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => WebtreesApi::PATH_DELETE_RECORD,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::TREE,
                    'xref' => McpSchema::withDescription(McpSchema::XREF,
                        'The XREF of the record to delete.',
                        McpSchema::APPEND
                    ),
                ],
                'required' => ['tree', 'xref']
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_DELETE_RECORD,
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
