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
use Fisharebest\Webtrees\Elements\PedigreeLinkageType;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\XrefItem;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class LinkChildToFamily implements WebtreesMcpToolRequestHandlerInterface
{
    private TreeService $tree_service;

    public const string METHOD_DESCRIPTION = 'Link an existing individual as child in an existing family.';
    public const string INDI_XREF_DESCRIPTION   = 'The XREF (i.e. GEDOM cross-reference identifier) of the individual, which shall be linked to the family.';
    public const string FAM_XREF_DESCRIPTION  = 'The XREF (i.e. GEDOM cross-reference identifier) of the family, to which the individual shall be linked.';
    public const string RELATIONSHIP_DESCRIPTION  = 'The relationship of the child to the parents.';

    public function __construct(TreeService $tree_service)
    {
        $this->tree_service = $tree_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_LINK_CHILD_TO_FAMILY,
        tags: ['webtrees'],
        description: self::METHOD_DESCRIPTION,
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'individual-xref',
                in: 'query',
                description: self::INDI_XREF_DESCRIPTION,
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
            new OA\Parameter(
                name: 'family-xref',
                in: 'query',
                description: self::FAM_XREF_DESCRIPTION,
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
            new OA\Parameter(
                name: 'relationship',
                in: 'query',
                description: self::RELATIONSHIP_DESCRIPTION,
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: McpSchema::RELATIONSHIP_ENUM,
                    default: PedigreeLinkageType::VALUE_BIRTH,
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully linked child to family.',
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
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('individual-xref', '');
        $famid     = Validator::queryParams($request)->string('family-xref', '');
        $PEDI      = Validator::queryParams($request)->string('relationship', '');

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = $this->tree_service->all()[$tree_name];

        // Validate individual XREF
        $xref_validation_response = QueryParamValidator::validateXref($tree, $xref);
        if (get_class($xref_validation_response) !== Response200::class) {
            return $xref_validation_response;
        }

        // Validate indidvidual
        $individual = Registry::individualFactory()->make($xref, $tree);

        if ($individual === null) {
            return new Response404('Individual not found');
        }

        //Validate indidvidual access
        $individual_validation_response = CheckAccess::checkRecordAccess($individual);
        if (get_class($individual_validation_response) !== Response200::class) {
            return $individual_validation_response;
        }       

        // Validate famid
        $famid_validation_response = QueryParamValidator::validateXref($tree, $famid);
        if (get_class($famid_validation_response) !== Response200::class) {
            return $famid_validation_response;
        }

        // Validate family
        $family = Registry::familyFactory()->make($famid, $tree);

        if ($family === null) {
            return new Response404('Family not found');
        }

        //Validate family access
        $family_validation_response = CheckAccess::checkRecordAccess($family);
        if (get_class($family_validation_response) !== Response200::class) {
            return $family_validation_response;
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

        try {
            $family = Auth::checkFamilyAccess($family, true);
        } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
            return new Response403('Insufficient permissions: No access to family record.');
        }


        // Replace any existing child->family link (we may be changing the PEDI);
        $fact_id = '';
        foreach ($individual->facts(['FAMC']) as $fact) {
            if ($family === $fact->target()) {
                $fact_id = $fact->id();
                break;
            }
        }

        switch ($PEDI) {
            case '':
                $gedcom = "1 FAMC @$famid@";
                break;
            case PedigreeLinkageType::VALUE_ADOPTED:
                $gedcom = "1 FAMC @$famid@\n2 PEDI $PEDI\n1 ADOP\n2 FAMC @$famid@\n3 ADOP BOTH";
                break;
            case PedigreeLinkageType::VALUE_SEALING:
                $gedcom = "1 FAMC @$famid@\n2 PEDI $PEDI\n1 SLGC\n2 FAMC @$famid@";
                break;
            case PedigreeLinkageType::VALUE_FOSTER:
                $gedcom = "1 FAMC @$famid@\n2 PEDI $PEDI\n1 EVEN\n2 TYPE $PEDI";
                break;
            default:
                $gedcom = "1 FAMC @$famid@\n2 PEDI $PEDI";
                break;
        }

        $individual->updateFact($fact_id, $gedcom, true);

        // Only set the family->child link if it does not already exist
        $chil_link_exists = false;
        foreach ($family->facts(['CHIL']) as $fact) {
            if ($individual === $fact->target()) {
                $chil_link_exists = true;
                break;
            }
        }

        if (!$chil_link_exists) {
            $family->createFact('1 CHIL @' . $individual->xref() . '@', true);
        }

        return Registry::responseFactory()->response(
            json_encode(new XrefItem($individual->xref())),
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
            'name' => WebtreesApi::PATH_LINK_CHILD_TO_FAMILY,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::TREE,
                    'individual-xref' => McpSchema::withDescription(
                        McpSchema::XREF,
                        self::INDI_XREF_DESCRIPTION,
                        McpSchema::APPEND
                    ),
                    'family-xref' => McpSchema::withDescription(
                        McpSchema::XREF,
                        self::FAM_XREF_DESCRIPTION,
                        McpSchema::APPEND
                    ),
                    'relationship' => [
                        'type' => 'string',
                        'description' => self::RELATIONSHIP_DESCRIPTION,
                        'enum' => McpSchema::RELATIONSHIP_ENUM,
                        'default' => PedigreeLinkageType::VALUE_BIRTH,
                    ],
                ],
                'required' => ['individual-xref', 'family-xref']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'xref' => McpSchema::XREF,
                ],
                'required' => ['xref'],
            ],
            'annotations' => [
                'title' => WebtreesApi::PATH_LINK_CHILD_TO_FAMILY,
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
