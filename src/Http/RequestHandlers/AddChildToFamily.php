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
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Gedcom as GedcomParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Xref as XrefSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\XrefItem;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class AddChildToFamily implements WebtreesMcpToolRequestHandlerInterface
{
    #[OA\Post(
        path: '/add-child-to-family',
        description: 'Add a new INDI record for a child to a family',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
            ),
            new OA\Parameter(
                name: 'xref',
                in: 'query',
                description: 'The XREF (i.e. GEDOM cross-reference identifier) of the family, to which the child shall be added.',
                required: true,
                schema: new OA\Schema(
                    ref: XrefSchema::class,
                ),
            ),
            new OA\Parameter(
                ref: GedcomParameter::class,
            ),
        ],
        responses: [          
            new OA\Response(
                response: '201', 
                description: 'Added record successfully',
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
        $xref      = Validator::queryParams($request)->string('xref', '');
        $gedcom    = Validator::queryParams($request)->string('gedcom', '');

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

        // Validate xref
        if (!preg_match('/^' . Gedcom::REGEX_XREF .'$/', $xref)) {
            return new Response400('Invalid xref parameter');
        }

        // Adopt line breaks for GEDCOM text
        $gedcom = str_replace(["\r\n", '\n', "%OA"], ["\n", "\n", "\n"], $gedcom);
        $gedcom = trim($gedcom);

        // Validate GEDCOM text
        if ($gedcom !== '') {
            $gedcom_lines = explode("\n", $gedcom);
            foreach ($gedcom_lines as $gedcom_line) {
                if (1 !== preg_match('/(\d+) (' . Gedcom::REGEX_TAG . ') (.*)/', $gedcom_line, $matches) ) {
                    return new Response400('Invalid format of GEDCOM line: ' . $gedcom_line);
                }
                if ($matches[1] === '0') {
                    return new Response400('The GEDCOM text must not contain a level 0 line: ' . $gedcom_line);
                }
            }
        }

        //Check user settings and rights 
        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return new Response403('Unauthorized: Automatically accept changes must be activated for the API user.');
        }

        if (Auth::isModerator($tree)) {
            return new Response403('Unauthorized: API users must not have moderator rights');
        }        

        if (!Auth::isEditor($tree)) {
            return new Response403('Unauthorized: API user does not have editor rights for the tree.');
        }        

        // Validate family
        $family = Registry::familyFactory()->make($xref, $tree);

        if ($family === null) {
            return new Response404('Family not found');
        }

        try {
            $family = Auth::checkFamilyAccess($family, true);
        } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
            return new Response403('Unauthorized: No access to family record.');
        }

        // Create the new child
        $child = $tree->createIndividual("0 @@ INDI\n1 FAMC @" . $xref . '@' . "\n" . $gedcom);

        // Link the child to the family
        $family->createFact('1 CHIL @' . $child->xref() . '@', false);        

        //Logout
        Auth::logout();

        return Registry::responseFactory()->response(
            json_encode(new XrefItem($child->xref())),
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
            'name' => 'add-child-to-family',
            'description' => 'Add a new INDI record for a child to a family [API: POST /add-child-to-family]',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => [
                        'type' => 'string',
                        'description' => 'The name of the tree.',
                        'maxLength' => 1024,
                        'pattern' => '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    ],
                    'xref' => [
                        'type' => 'string',
                        'description' => 'The XREF (i.e. GEDOM cross-reference identifier) of the family, to which the child shall be added.',
                        'maxLength' => 20,
                        'pattern' => '^' . Gedcom::REGEX_XREF .'$'
                    ],
                    'gedcom' => [
                        'type' => 'string',
                        'description' => 'The GEDCOM text, which shall be added to the newly created record. The GEDCOM text must not contain a level 0 line, because it is created automatically. "\n" or "%OA" will be detected as line break.',
                        'default' => '',
                        'example' => '1 NOTE A record created by the webtrees API.\n1 NOTE Read description about line breaks.',
                    ],
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
                'title' => 'add-child-to-family',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ]
        ];
    }
}
