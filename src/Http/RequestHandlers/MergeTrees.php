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

use Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesAction;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions as CommonFunctions;
use Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Tree as TreeSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class MergeTrees implements RequestHandlerInterface
{
    private TreeService   $tree_service;
    private ModuleService $module_service;
    private AdminService  $admin_service;


    public function __construct(ModuleService $module_service, TreeService $tree_service, AdminService $admin_service)
    {
        $this->module_service = $module_service;
        $this->tree_service   = $tree_service;
        $this->admin_service  = $admin_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_MERGE_TREES,
        description: 'Merge two trees.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'tree_to_merge',
                in: 'query',
                description: 'The name (i.e. filename) of the tree to merge into the other webtrees tree.',
                required: true,
                schema: new OA\Schema(
                    ref: TreeSchema::class,
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully merged trees.',
                ref: Response200::class,
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
            return $this->mergeTrees($request);        
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
    private function mergeTrees(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name          = Validator::queryParams($request)->string('tree', '');
        $tree_name_to_merge = Validator::queryParams($request)->string('tree_to_merge', '');

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        // Validate tree to merge
        $tree_to_merge_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name_to_merge);
        if (get_class($tree_to_merge_validation_response) !== Response200::class) {
            return $tree_to_merge_validation_response;
        }

        // Check if the two trees contain common XREFs
        $tree          = $this->tree_service->all()->get($tree_name);
        $tree_to_merge = $this->tree_service->all()->get($tree_name_to_merge);

        if ($this->admin_service->countCommonXrefs($tree, $tree_to_merge) !== 0) {
            return new Response500('Cannot merge trees, because the trees contain common XREFs.' );
        }

        // Generate and handle a request for a MergeTreesAction
        $request         = CommonFunctions::getFromContainer(ServerRequestInterface::class);
        $request         = $request->withParsedBody(['tree1_name' => $tree_name_to_merge, 'tree2_name' => $tree_name]);
        $request_handler = new MergeTreesAction($this->admin_service, $this->tree_service);
        
        try {
            $response = $request_handler->handle($request);   
        }
        catch (Throwable $th) {
            return new Response500('Failed to merge trees: ' . $th->getMessage());
        }

        return new Response200('Successfully merged trees');
    }
}
