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

use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class RenumberXrefs implements RequestHandlerInterface
{
    private AdminService   $admin_service;
    private ModuleService  $module_service;
    private TimeoutService $timeout_service;
    private TreeService    $tree_service;


    public function __construct(AdminService $admin_service, ModuleService $module_service, TimeoutService $timeout_service, TreeService $tree_service)
    {
        $this->admin_service   = $admin_service;
        $this->module_service  = $module_service;
        $this->timeout_service = $timeout_service;
        $this->tree_service    = $tree_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_RENUMBER_XREFS,
        description: 'Renumber the XREFs in a tree.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully renumbered XREFs in tree.',
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
            return $this->renumberXrefs($request);        
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
    private function renumberXrefs(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = $this->tree_service->all()->get($tree_name);


        // Validate pending changes
        // Code from: Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction
        $xrefs = $this->admin_service->duplicateXrefs($tree);

        if ($xrefs !== [] && $tree->hasPendingEdit()) {
            return new Response500('Failed to renumber tree, because there are pending changes that would be lost.');
        }        

        // Generate and handle a request for a RenumberXrefsAction
        $request         = CommonFunctions::getFromContainer(ServerRequestInterface::class);
        $request         = $request->withAttribute('tree', $tree instanceof Tree ? $tree : null);
        $request_handler = new RenumberTreeAction($this->admin_service, $this->timeout_service);
            
        try {
            $response = $request_handler->handle($request);   
        }
        catch (Throwable $th) {
            return new Response500('Failed to renumber tree: ' . $th->getMessage());
        }

        return new Response200('Successfully renumbered XREFs in tree.');
    }
}
