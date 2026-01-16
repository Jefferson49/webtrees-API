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
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Gedcom as GedcomParameter;
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


class AddSpouseToIndividual implements WebtreesMcpToolRequestHandlerInterface
{
    public const string METHOD_DESCRIPTION = 'Add a new spouse to an individual, creating a new family.';
    public const string XREF_DESCRIPTION   = 'The XREF (i.e. GEDOM cross-reference identifier) of the individual, to which the spouse shall be added.';


    #[OA\Post(
        path: '/' . WebtreesApi::PATH_ADD_SPOUSE_TO_INDI,
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
                description: self::XREF_DESCRIPTION,
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
            new OA\Parameter(
                ref: GedcomParameter::class,
                required: false,
            ),
        ],
        responses: [
            new OA\Response(
                response: '201', 
                description: 'Successfully added spouse to individual.',
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
            return $this->addSpouseToIndividual($request);        
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
    private function addSpouseToIndividual(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('xref', '');
        $gedcom    = Validator::queryParams($request)->string('gedcom', '');

        // Adopt line breaks for GEDCOM text        
        $gedcom    = str_replace(["\r\n", '\n', "%OA"], ["\n", "\n", "\n"], $gedcom);
        $gedcom    = trim($gedcom);

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = Functions::getAllTrees()[$tree_name];

        // Validate XREF
        $xref_validation_response = QueryParamValidator::validateXref($tree, $xref);
        if (get_class($xref_validation_response) !== Response200::class) {
            return $xref_validation_response;
        }

        // Validate individual
        $individual = Registry::individualFactory()->make($xref, $tree);

        if ($individual === null) {
            return new Response404('Individual not found');
        }

        //Validate record access
        $xref_validation_response = CheckAccess::checkRecordAccess($individual);
        if (get_class($xref_validation_response) !== Response200::class) {
            return $xref_validation_response;
        }

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

        try {
            $individual = Auth::checkIndividualAccess($individual, true);
        } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
            return new Response403('Insufficient permissions: No access to individual record.');
        }

        // Create the new spouse
        $spouse = $tree->createIndividual("0 @@ INDI\n" . $gedcom);

        // Create the new family
        $i_link = "\n1 " . ($individual->sex() === 'F' ? 'WIFE' : 'HUSB') . ' @' . $individual->xref() . '@';
        $s_link = "\n1 " . ($individual->sex() !== 'F' ? 'WIFE' : 'HUSB') . ' @' . $spouse->xref() . '@';
        $family = $tree->createFamily('0 @@ FAM' . $i_link . $s_link);

        // Link the individual to the family
        $individual->createFact('1 FAMS @' . $family->xref() . '@', false);

        // Link the spouse to the family
        $spouse->createFact('1 FAMS @' . $family->xref() . '@', false);

        return Registry::responseFactory()->response(
            json_encode(new XrefItem($spouse->xref())),
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
            'name' => WebtreesApi::PATH_ADD_SPOUSE_TO_INDI,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::TREE,
                    'xref' => McpSchema::withDescription(
                        McpSchema::XREF,
                        self::XREF_DESCRIPTION,
                        McpSchema::APPEND
                    ),
                    'gedcom' => McpSchema::withDescription(
                        McpSchema::GEDCOM,
                        'The GEDCOM text, which shall be added to the newly created record.',
                        McpSchema::PREPEND
                    ),
                ],
                'required' => ['tree', 'xref']
            ],
            'outputSchema' => [
                'description' => 'The XREF of the newly created record.',
                'type' => 'object',
                'properties' => [
                    'xref' => McpSchema::XREF,
                ],
                'required' => ['xref'],
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_ADD_SPOUSE_TO_INDI,
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
