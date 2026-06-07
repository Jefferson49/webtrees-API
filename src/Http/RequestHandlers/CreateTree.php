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

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
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


class CreateTree implements RequestHandlerInterface
{
    private TreeService   $tree_service;
    private ModuleService $module_service;


    public function __construct(ModuleService $module_service, TreeService $tree_service)
    {
        $this->module_service = $module_service;
        $this->tree_service   = $tree_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_CREATE_TREE,
        description: 'Create a new tree on the webtrees server.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'title',
                in: 'query',
                description: 'The title of the new tree.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully created tree.',
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
            return $this->createTree($request);        
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
    private function createTree(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $title     = Validator::queryParams($request)->string('title', '');

        //Check availability of Extended Import/Export module
        try {
            /** @var DownloadGedcomWithURL $download_gedcom_with_url To avoid IDE warnings */
            $download_gedcom_with_url = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        }
        catch (Throwable $th) {
            return new Response500('Cannot import tree, because the required custom module Extended "Import/Export" is not available.');
        }

        if ($download_gedcom_with_url->customModuleVersion() < WebtreesApi::REQUIRED_IMPORT_EXPORT_VERSION) {
            return new Response400('Cannot import tree, because the custom module version of Extended Import/Export does not support webtrees-API. Please upgrade the module to a version ' . WebtreesApi::REQUIRED_IMPORT_EXPORT_VERSION . ' or higher.');
        }

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name, false);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        // Validate title
        if ($title === '') {
            return new Response400('Empty title parameter.');
        }

        // Code from: Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction

        if ($this->tree_service->all()->get($tree_name) instanceof Tree) {
            return new Response500('The family tree ' . $tree_name . ' already exists.');
        }

        try {
            $tree = $this->tree_service->create($tree_name, $title);
        }
        catch (Throwable $th) {
            if (str_contains($th->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')) {
                return new Response400('The family tree ' . $tree_name . ' already exists.');
            }
            else {
                return new Response500('Failed to create tree: ' . $th->getMessage());
            }
        }
    
        return new Response200('Successfully created tree.');
    }
}
