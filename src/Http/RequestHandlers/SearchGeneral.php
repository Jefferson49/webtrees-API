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
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Gedcom\GedcomX\Generator;
use Illuminate\Support\Collection;
use Jefferson49\Webtrees\Module\WebtreesApi\GedcomX\StringParser;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\GedcomFormat as GedcomFormatParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Mcp as McpSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Tree as TreeSchema;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\WebtreesSearchResultItem;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;

use function preg_replace;
use function trim;
use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;

use const PREG_SET_ORDER;


/**
 * Search for genealogy data
 */
class SearchGeneral implements WebtreesMcpToolRequestHandlerInterface
{
    private SearchService $search_service;
    private TreeService   $tree_service;

    public const string METHOD_DESCRIPTION = 'Perform a general search in webtrees.';

    private const string PARAM_DESC_SEARCH_INDIVIDUALS = 'Whether to search in individuals.';
    private const string PARAM_DESC_SEARCH_FAMILIES = 'Whether to search in families.';
    private const string PARAM_DESC_SEARCH_LOCATIONS = 'Whether to search in locations.';
    private const string PARAM_DESC_SEARCH_REPOSITORIES = 'Whether to search in repositories.';
    private const string PARAM_DESC_SEARCH_SOURCES = 'Whether to search in sources.';
    private const string PARAM_DESC_SEARCH_NOTES = 'Whether to search in notes.';
    private const string PARAM_DESC_INCL_REC_DATA = 'Whether to include the data of the records found.';


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
        path: '/' . WebtreesApi::PATH_SEARCH_GENERAL,
        description: self::METHOD_DESCRIPTION,
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'tree',
                in: 'query',
                description: 'The name of the tree. If not provided, all trees will be searched.',
                required: false,
                schema: new OA\Schema(
                    ref: TreeSchema::class
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
            new OA\Parameter(
                name: 'search_individuals',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_INDIVIDUALS,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: true,
                ),
            ),
            new OA\Parameter(
                name: 'search_families',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_FAMILIES,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: true,
                ),
            ),
            new OA\Parameter(
                name: 'search_locations',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_LOCATIONS,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'search_repositories',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_REPOSITORIES,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'search_sources',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_SOURCES,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'search_notes',
                in: 'query',
                description: self::PARAM_DESC_SEARCH_NOTES,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'include_record_data',
                in: 'query',
                description: self::PARAM_DESC_INCL_REC_DATA,
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                ref: GedcomFormatParameter::class,
                required: false,
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
                                items: new OA\Items(
									ref: WebtreesSearchResultItem::class
								),
                            ),
                        ],
                        required: ['records'],
                    ),
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
            return $this->searchGeneral($request);        
        }
        catch (Throwable $th) {
            return api_response($th->getMessage(), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    private function searchGeneral(ServerRequestInterface $request): ResponseInterface
    {
        $scopes                    = Validator::attributes($request)->array('oauth_scopes');
        $tree_name                 = Validator::queryParams($request)->string('tree', '');
        $query                     = Validator::queryParams($request)->string('query', '');
        $search_individuals_param  = Validator::queryParams($request)->string('search_individuals', 'true');
        $search_families_param     = Validator::queryParams($request)->string('search_families', 'true');
        $search_locations_param    = Validator::queryParams($request)->string('search_locations', 'false');
        $search_repositories_param = Validator::queryParams($request)->string('search_repositories', 'false');
        $search_sources_param      = Validator::queryParams($request)->string('search_sources', 'false');
        $search_notes_param        = Validator::queryParams($request)->string('search_notes', 'false');
        $include_record_data_param = Validator::queryParams($request)->string('include_record_data', 'false');
        $format                    = Validator::queryParams($request)->string('format', GedcomFormatParameter::DEFAULT_VALUE);

        // Validate tree
        if ($tree_name === '') {
            $tree = null;
        }       
        else {
            $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
            if ($tree_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                return $tree_validation_response;
            }

            $tree = $this->tree_service->all()[$tree_name];
        }

        // If we search all trees, take a default access level, since we cannot evaluate each tree's privacy and user access level
        if ($tree === null) {
            $access_level = Auth::PRIV_PRIVATE;
        }
        // If less reading scope than member
        elseif (empty(array_intersect([ScopeRepository::SCOPE_API_READ_MEMBER, ScopeRepository::SCOPE_MCP_READ_MEMBER], $scopes))) {

            // Validate the privacy settings of the tree to assure a minimum privacy level
            $privacy_validation_response = CheckAccess::checkTreePrivacy($tree);
            if ($privacy_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                return $privacy_validation_response;
            }

            // Set record access level to private
            $access_level = Auth::PRIV_PRIVATE;
        }
        else {
            // Use the access level of the user for the tree
            $access_level = Auth::accessLevel($tree);
        }

        // Validate query
        if ($query === '') {
            return api_response('Missing query parameter', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (strlen($query) > 8192) {
            return api_response('Query parameter too long', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        // Validate search flags
        $search_individuals_response = QueryParamValidator::validateBoolean($search_individuals_param);
        if ($search_individuals_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_individuals_response;
        }

        $search_families_response = QueryParamValidator::validateBoolean($search_families_param);
        if ($search_families_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_families_response;
        }

        $search_locations_response = QueryParamValidator::validateBoolean($search_locations_param);
        if ($search_locations_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_locations_response;
        }

        $search_repositories_response = QueryParamValidator::validateBoolean($search_repositories_param);
        if ($search_repositories_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_repositories_response;
        }

        $search_sources_response = QueryParamValidator::validateBoolean($search_sources_param);
        if ($search_sources_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_sources_response;
        }

        $search_notes_response = QueryParamValidator::validateBoolean($search_notes_param);
        if ($search_notes_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $search_notes_response;
        }

        // Validate include record data
        $include_record_data_validation_response = QueryParamValidator::validateBoolean($include_record_data_param);
        if ($include_record_data_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $include_record_data_validation_response;
        }

        // Validate GEDCOM format
        $gedcom_format_validation_response = QueryParamValidator::validateGedcomFormat($format);
        if ($gedcom_format_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $gedcom_format_validation_response;
        }

        $search_individuals  = $search_individuals_param === 'true' ? true : false;
        $search_families     = $search_families_param === 'true' ? true : false;
        $search_locations    = $search_locations_param === 'true' ? true : false;
        $search_repositories = $search_repositories_param === 'true' ? true : false;
        $search_sources      = $search_sources_param === 'true' ? true : false;
        $search_notes        = $search_notes_param === 'true' ? true : false;
        $include_record_data = $include_record_data_param === 'true' ? true : false;

    
        // Code from: Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage

        // Default to families and individuals only
        if (!$search_individuals && !$search_families && !$search_locations && !$search_repositories && !$search_sources && !$search_notes) {
            $search_families    = true;
            $search_individuals = true;
        }

        // What to search for?
        $search_terms = $this->extractSearchTerms($query);

        // What trees to search?
        if ($tree !== null) {
            $search_trees = new Collection([$tree]);
        }
        else {
            $search_trees = $this->tree_service->all();
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
            /** @var GedcomRecord $record */

            //Add record to search result if validation of record access is successfull
            $record_access_validation_response = CheckAccess::checkRecordAccess($record, false, $access_level === Auth::PRIV_PRIVATE);
            if ($record_access_validation_response->getStatusCode() === StatusCodeInterface::STATUS_OK) {

                if ($include_record_data) {
                    $gedcom_data = self::getGedcomData($record, $access_level, $format);
                }
                else {
                    $gedcom_data = (object) null;
                }

                $search_results[] = new WebtreesSearchResultItem(
                    tree:        $record->tree()->name(),
                    xref:        $record->xref(),
                    gedcom_data: $gedcom_data
                );
            }
        }

        return api_response(['records' => $search_results], StatusCodeInterface::STATUS_OK);        
    }

    /**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => WebtreesApi::PATH_SEARCH_GENERAL,
            'description' => self::METHOD_DESCRIPTION,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => McpSchema::withDescription(McpSchema::TREE,
                        ' If not provided, all trees will be searched.',
                        McpSchema::APPEND,
                    ),
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query. (in: query)',
                        'maxLength' => 8192
                    ],
                    'search_individuals' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_INDIVIDUALS,
                        'default' => false
                    ],
                    'search_families' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_FAMILIES,
                        'default' => false
                    ],
                    'search_locations' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_LOCATIONS,
                        'default' => false
                    ],
                    'search_repositories' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_REPOSITORIES,
                        'default' => false
                    ],
                    'search_sources' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_SOURCES,
                        'default' => false
                    ],
                    'search_notes' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_SEARCH_NOTES,
                        'default' => false
                    ],
                    'include_record_data' => [
                        'type' => 'boolean',
                        'description' => self::PARAM_DESC_INCL_REC_DATA,
                        'default' => false
                    ],
                    'format' => McpSchema::GEDCOM_FORMAT,
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
                                'tree' => McpSchema::TREE,
                                'xref' => McpSchema::XREF,
                            ],
                            'required' => ['tree', 'xref'],
                        ],
                    ],
                ],
                'required' => ['records'],
            ],                       
            'annotations' => [
                'title' => WebtreesApi::PATH_SEARCH_GENERAL,
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

    /**
     * Get the Gedcom data for a record
     *
     * @param GedcomRecord $record
     * @param int          $access_level
     * @param string       $format
     *
     * @return object|string
     */
    private function getGedcomData(GedcomRecord $record, int $access_level, string $format): object|string {

        // Create GEDCOM
        $gedcom = $record->privatizeGedcom($access_level) . "\n";

        if ($format === GedcomFormatParameter::FORMAT_GEDCOM_RECORD) {
            return $gedcom;
        }    

        $gedcom  = GetRecord::getGedcomHeader() . $gedcom;
        $gedcom .= GetRecord::getGedcomOfLinkedRecords($record->tree(), $gedcom, [$record->xref()], $access_level);
        $gedcom .= "0 TRLR\n";

        if ($format === GedcomFormatParameter::FORMAT_GEDCOM) {
            return $gedcom;
        }
        elseif (in_array($format, [GedcomFormatParameter::FORMAT_GEDCOM_X, GedcomFormatParameter::FORMAT_JSON])) { 
            
            // We can only generate GEDCOM-X for INDI and FAM records
            if (in_array($record->tag(), ['INDI', 'FAM'])) {
                
                $parser = new StringParser();
                $gedcom_object = $parser->parse($gedcom);
                $generator = new Generator($gedcom_object);
                $gedcom_x_json = $generator->generate();
                $gedcom_x_json = GetRecord::substituteXREFs($generator, $gedcom_x_json);

                return json_decode($gedcom_x_json);
            }
        }

        return (object) null;
    }
}
