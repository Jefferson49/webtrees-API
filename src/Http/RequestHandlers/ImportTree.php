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
use Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter\Tree as TreeParameter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Encoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\FileName;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedcomFilter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\StringEncodedBoolean;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Nyholm\Psr7\ServerRequest;
use OpenApi\Attributes as OA;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class ImportTree implements RequestHandlerInterface
{
    private TreeService   $tree_service;
    private ModuleService $module_service;

    public const string REQUIRED_IMPORT_EXPORT_VERSION = '4.2.13';


    public function __construct(ModuleService $module_service, TreeService $tree_service)
    {
        $this->module_service = $module_service;
        $this->tree_service   = $tree_service;
    }

    #[OA\Post(
        path: '/' . WebtreesApi::PATH_IMPORT_TREE,
        description: 'Import a tree from a GEDCOM file in the data folder on the webtrees server.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'filename',
                in: 'query',
                description: 'The filename (without path) of the GEDCOM file to import.',
                required: true,
                schema: new OA\Schema(
                    ref: FileName::class,
                ),
            ),
            new OA\Parameter(
                name: 'import_encoding',
                in: 'query',
                description: 'The character encoding of the GEDCOM file.',
                required: false,
                schema: new OA\Schema(
                    ref: Encoding::class,
                ),
            ),
            new OA\Parameter(
                name: 'keep_media',
                in: 'query',
                description: 'Whether to keep existing media objects in the tree.',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'word_wrapped_notes',
                in: 'query',
                description: 'Whether to wrap notes at word boundaries.',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                ),
            ),
            new OA\Parameter(
                name: 'gedcom_media_path',
                in: 'query',
                description: 'The path to GEDCOM media files on the webtrees server.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                ),
            ),
            new OA\Parameter(
                name: 'gedcom_filter1',
                in: 'query',
                description: 'A GEDCOM filter used by the Extended Import/Export custom module to filter GEDCOM data during import and export.',
                required: false,
                schema: new OA\Schema(
                    ref: GedcomFilter::class,
                ),
            ),
            new OA\Parameter(
                name: 'gedcom_filter2',
                in: 'query',
                description: 'A GEDCOM filter used by the Extended Import/Export custom module to filter GEDCOM data during import and export.',
                required: false,
                schema: new OA\Schema(
                    ref: GedcomFilter::class,
                ),  
            ),
            new OA\Parameter(
                name: 'gedcom_filter3',
                in: 'query',
                description: 'A GEDCOM filter used by the Extended Import/Export custom module to filter GEDCOM data during import and export.',
                required: false,
                schema: new OA\Schema(
                    ref: GedcomFilter::class,
                ),  
            ),],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully imported tree.',
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
            return $this->importTree($request);        
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
    private function importTree(ServerRequestInterface $request): ResponseInterface
    {
        $base_url           = Validator::attributes($request)->string('base_url');

        $tree_name          = Validator::queryParams($request)->string('tree', '');
        $file_name          = Validator::queryParams($request)->string('filename', '');
        $import_encoding    = Validator::queryParams($request)->string('import_encoding', '');
        $keep_media         = Validator::queryParams($request)->string('keep_media', '');
        $word_wrapped_notes = Validator::queryParams($request)->string('word_wrapped_notes', '');
        $gedcom_media_path  = Validator::queryParams($request)->string('gedcom_media_path', '');
        $gedcom_filter1     = Validator::queryParams($request)->string('gedcom_filter1', '');
        $gedcom_filter2     = Validator::queryParams($request)->string('gedcom_filter2', '');
        $gedcom_filter3     = Validator::queryParams($request)->string('gedcom_filter3', '');

        //Check availability of Extended Import/Export module
        try {
            /** @var DownloadGedcomWithURL $download_gedcom_with_url To avoid IDE warnings */
            $download_gedcom_with_url = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        }
        catch (Throwable $th) {
            return new Response500('Cannot import tree, because the required custom module Extended "Import/Export" is not available.');
        }

        if ($download_gedcom_with_url->customModuleVersion() < self::REQUIRED_IMPORT_EXPORT_VERSION) {
            return new Response400('Cannot import tree, because the custom module version of Extended Import/Export does not support webtrees-API. Please upgrade the module to a version ' . self::REQUIRED_IMPORT_EXPORT_VERSION . ' or higher.');
        }

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if (get_class($tree_validation_response) !== Response200::class) {
            return $tree_validation_response;
        }

        $tree = $this->tree_service->all()[$tree_name];

        //Check user write access 
        $user_rights_response = CheckAccess::checkUserWriteAccess($tree);
        if (get_class($user_rights_response) !== Response200::class) {
            return $user_rights_response;
        }  

        // Validate filename
        $filename_validation_response = QueryParamValidator::validateFileName($file_name);
        if (get_class($filename_validation_response) !== Response200::class) {
            return $filename_validation_response;
        }

        // Validate encoding
        $encoding_validation_response = QueryParamValidator::validateEncoding($import_encoding);
        if (get_class($encoding_validation_response) !== Response200::class) {
            return $encoding_validation_response;
        }

        // Validate keep_media
        $keep_media_validation_response = QueryParamValidator::validateBoolean($keep_media);
        if (get_class($keep_media_validation_response) !== Response200::class) {
            return new Response400('Invalid boolean parameter "keep_media parameter": ' . $keep_media);
        }

        // Validate word_wrapped_notes
        $word_wrapped_notes_validation_response = QueryParamValidator::validateBoolean($word_wrapped_notes);
        if (get_class($word_wrapped_notes_validation_response) !== Response200::class) {
            return new Response400('Invalid boolean parameter "word_wrapped_notes parameter": ' . $word_wrapped_notes);
        }

        // Validate GEDCOM filters
        foreach (['gedcom_filter1' => $gedcom_filter1, 'gedcom_filter2' => $gedcom_filter2, 'gedcom_filter3' => $gedcom_filter3] as $filter_name => $filter_value) {
            $gedcom_filter_validation_response = QueryParamValidator::validateGedcomFilter($filter_value);
            if (get_class($gedcom_filter_validation_response) !== Response200::class) {
                return new Response400('Invalid GEDCOM filter parameter "' . $filter_name . '": ' . $filter_value);
            }
        }

        // Import tree by calling Extended Import/Export custom module
        $data = [
            'called_from'        => DownloadGedcomWithURL::CALLED_FROM_WEBTREES_API,
            'action'             => DownloadGedcomWithURL::ACTION_UPLOAD,
            'source'             => 'server',
            'tree'               => $tree->name(),
            'filename'           => $file_name,
            'import_encoding'    => $import_encoding,
            'keep_media'         => $keep_media,
            'word_wrapped_notes' => $word_wrapped_notes,
            'gedcom_media_path'  => $gedcom_media_path,
            'gedcom_filter1'     => $gedcom_filter1,
            'gedcom_filter2'     => $gedcom_filter2,
            'gedcom_filter3'     => $gedcom_filter3,
        ];

        $request = new ServerRequest(method: 'POST', uri: '')
            ->withAttribute('base_url', $base_url)
            ->withParsedBody($data);

        $response = $download_gedcom_with_url->handle($request);

        if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return new Response500('Failed to import tree: ' . $response->getBody());
        }
        else {
            return new Response200('Successfully imported tree');
        }
    }
}
