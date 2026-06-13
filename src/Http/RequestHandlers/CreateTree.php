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
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response201;
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

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


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
                response: '201', 
                description: 'Successfully created tree.',
                ref: Response201::class,
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
            return api_response($th->getMessage(),StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
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

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name, false);
        if ($tree_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $tree_validation_response;
        }

        // Validate title
        if ($title === '') {
            return api_response('Empty title parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        // Code from: Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction

        if ($this->tree_service->all()->get($tree_name) instanceof Tree) {
            return api_response('The family tree ' . $tree_name . ' already exists.', StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }

        try {
            $tree = $this->tree_service->create($tree_name, $title);
        }
        catch (Throwable $th) {
            if (str_contains($th->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')) {
                return api_response('The family tree ' . $tree_name . ' already exists.', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            else {
                return api_response('Failed to create tree: ' . $th->getMessage(), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
        }
    
        return api_response('Successfully created tree.', StatusCodeInterface::STATUS_CREATED);
    }
}
