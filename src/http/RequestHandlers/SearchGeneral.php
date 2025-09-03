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
 * webtrees MCP server
 *
 * A webtrees(https://webtrees.net) 2.2 custom module to provide an MCP API for webtrees
 * 
 */


declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\McpApi\Http\RequestHandlers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\McpApi\McpApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;
use function preg_replace;
use function trim;

use const PREG_SET_ORDER;

/**
 * Search for genealogy data
 */
class SearchGeneral implements RequestHandlerInterface
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
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $query     = Validator::queryParams($request)->string('query', '');

        if ($tree_name === '') {
            $tree = null;
        }
        elseif (!Functions::isValidTree($tree_name)) {
            return response(McpApi::ERROR_WEBTREES_ERROR . ': Tree not found');
        } else {
            $tree = $this->tree_service->all()[$tree_name] ?? null;
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
            $search_results[] = [
                'tree' => $record->tree()->name(),
                'xref' => $record->xref(),
            ];
        }

        return response(json_encode($search_results));        
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
