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
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\WebtreesSearchResultItem;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;

use function in_array;
use function preg_replace;
use function trim;

use const PREG_SET_ORDER;

/**
 * Search for genealogy data
 */
class SearchGeneral implements WebtreesMcpToolRequestHandlerInterface
{
    private SearchService $search_service;

    private TreeService $tree_service;

    /**
     * @param SearchService $search_service
     * @param TreeService   $tree_service
     */
    public function __construct(SearchService $search_service, TreeService $tree_service)
    {
        $this->search_service = $search_service;
        $this->tree_service   = $tree_service;
    }
    
    #[OA\Get(
        path: '/search-general',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'tree',
                in: 'query',
                description: 'The name of the tree. If not provided, all trees will be searched.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                    pattern: '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    example: 'mytree',
                ),
            ),
            new OA\Parameter(
                name: 'query',
                in: 'query',
                description: 'The search query.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 8192,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The result of a general search in webtrees. The result contains a list of records, each with the tree name and the XREF of the record.', 
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'records',
                                type: 'array', 
                                items: new OA\Items(ref: WebtreesSearchResultItem::class),
                            ),
                        ],
                        required: ['records'],
                    ),
                ),
            ),
            new OA\Response(
                response: '400', 
                description: 'Invalid tree or query parameter.', 
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
                description: 'Not found: Tree does not exist.',
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
            return $this->searchGeneral($request);        
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
    private function searchGeneral(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $query     = Validator::queryParams($request)->string('query', '');

        // Validate tree
        if ($tree_name === '') {
            $tree = null;
        }
        elseif (strlen($tree_name) > 1024) {
            return new Response400('Invalid tree parameter');
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $tree_name)) {
            return new Response400('Invalid tree parameter');
        }
        elseif (!Functions::isValidTree($tree_name)) {
            return new Response404('Tree does not exist');
        } 
        else {
            $tree = $this->tree_service->all()[$tree_name] ?? null;
        }

        // Validate query
        if ($query === '') {
            return new Response400('Missing query parameter');
        }
        elseif (strlen($query) > 8192) {
            return new Response400('Query parameter too long');
        }

        // What type of records to search?
        $search_individuals  = Validator::queryParams($request)->boolean('search_individuals', false);
        $search_families     = Validator::queryParams($request)->boolean('search_families', false);
        $search_locations    = Validator::queryParams($request)->boolean('search_locations', false);
        $search_repositories = Validator::queryParams($request)->boolean('search_repositories', false);
        $search_sources      = Validator::queryParams($request)->boolean('search_sources', false);
        $search_notes        = Validator::queryParams($request)->boolean('search_notes', false);

        // Where to search
        $search_tree_names = Validator::queryParams($request)->array('search_trees');

        // Default to families and individuals only
        if (!$search_individuals && !$search_families && !$search_repositories && !$search_sources && !$search_notes) {
            $search_families    = true;
            $search_individuals = true;
        }

        // What to search for?
        $search_terms = $this->extractSearchTerms($query);

        // What trees to search?
        if ($tree !== null) {
            if (Site::getPreference('ALLOW_CHANGE_GEDCOM') === '1') {
                $all_trees = $this->tree_service->all();
            } else {
                $all_trees = new Collection([$tree]);
            }

            $search_trees = $all_trees
                ->filter(static fn (Tree $tree): bool => in_array($tree->name(), $search_tree_names, true));

            if ($search_trees->isEmpty()) {
                $search_trees->add($tree);
            }
        }
        else {
            $search_trees = Functions::getAllTrees();
        }

        // Do the search
        $individuals  = new Collection();
        $families     = new Collection();
        $locations    = new Collection();
        $repositories = new Collection();
        $sources      = new Collection();
        $notes        = new Collection();

        if ($search_terms !== []) {

            if ($search_individuals) {
                $individuals = $this->search_service->searchIndividuals($search_trees->all(), $search_terms);
            }

            if ($search_families) {
                $tmp1 = $this->search_service->searchFamilies($search_trees->all(), $search_terms);
                $tmp2 = $this->search_service->searchFamilyNames($search_trees->all(), $search_terms);

                $families = $tmp1->merge($tmp2)->unique(static fn (Family $family): string => $family->xref() . '@' . $family->tree()->id());
            }

            if ($search_repositories) {
                $repositories = $this->search_service->searchRepositories($search_trees->all(), $search_terms);
            }

            if ($search_sources) {
                $sources = $this->search_service->searchSources($search_trees->all(), $search_terms);
            }

            if ($search_notes) {
                $notes = $this->search_service->searchNotes($search_trees->all(), $search_terms);
            }

            if ($search_locations) {
                $locations = $this->search_service->searchLocations($search_trees->all(), $search_terms);
            }
        }

        //Create a comma-separated list with the xrefs of all the records found
        $all_records_found = $individuals->union($families)->union($locations)->union($repositories)->union($sources)->union($notes);
        $search_results = [];

        foreach($all_records_found as $record) {
            /** @var \Fisharebest\Webtrees\GedcomRecord $record  To avoid IDE warnings */
            $search_results[] = new WebtreesSearchResultItem(
                tree: $record->tree()->name(),
                xref: $record->xref(),
            );
        }

        return Registry::responseFactory()->response(json_encode(['records' => $search_results]), StatusCodeInterface::STATUS_OK);        
    }

    /**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'get-search-general',
            'description' => 'GET /search-general [API: GET /search-general]',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => [
                        'type' => 'string',
                        'description' => 'The name of the tree. If not provided, all trees will be searched. (in: query)',
                        'maxLength' => 1024,
                        'pattern' => WebtreesApi::REGEX_FILE_NAME,
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query. (in: query)',
                        'maxLength' => 8192
                    ]
                ],
                'required' => ['query']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'records' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'tree' => [
                                    'type' => 'string'
                                ],
                                'xref' => [
                                    'type' => 'string'
                                ],
                            ],
                            'required' => ['tree', 'xref'],
                        ],
                    ],
                ],
                'required' => ['records'],
            ],                       
            'annotations' => [
                'title' => 'GET /search-general',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }

    /**
     * Convert the query into an array of search terms
     *
     * @param string $query
     *
     * @return array<string>
     */
    private function extractSearchTerms(string $query): array
    {
        $search_terms = [];

        // Words in double quotes stay together
        preg_match_all('/"([^"]+)"/', $query, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $search_terms[] = trim($match[1]);
            // Remove this string from the search query
            $query = strtr($query, [$match[0] => '']);
        }

        // Treat CJK characters as separate words, not as characters.
        $query = preg_replace('/\p{Han}/u', '$0 ', $query);

        // Other words get treated separately
        preg_match_all('/[\S]+/', $query, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $search_terms[] = $match[0];
        }

        return $search_terms;
    }
}
