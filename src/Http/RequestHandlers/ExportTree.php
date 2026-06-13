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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ExportAction;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ExportEncoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\FileFormat;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\FileName;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\GedcomFilter;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\LineEndings;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Privacy;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\TimeStamp;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\CheckAccess;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation\QueryParamValidator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Nyholm\Psr7\ServerRequest;
use OpenApi\Attributes as OA;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


class ExportTree implements RequestHandlerInterface
{
    private TreeService   $tree_service;
    private ModuleService $module_service;


    public function __construct(ModuleService $module_service, TreeService $tree_service)
    {
        $this->module_service = $module_service;
        $this->tree_service   = $tree_service;
    }

    #[OA\Get(
        path: '/' . WebtreesApi::PATH_EXPORT_TREE,
        description: 'Export a tree as a GEDCOM file to the webtrees server.',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                ref: TreeParameter::class,
                required: true,
            ),
            new OA\Parameter(
                name: 'filename',
                in: 'query',
                description: 'The name of the file into which the GEDCOM data is exported. Defaults to the tree name. The path can be specified in the Extended Import/Export module and defaults to the webtrees data folder.',
                required: false,
                schema: new OA\Schema(
                    ref: FileName::class,
                ),
            ),
            new OA\Parameter(
                name: 'file_format',
                in: 'query',
                description: 'The format of the exported file. Defaults to "gedcom".',
                required: false,
                schema: new OA\Schema(
                    ref: FileFormat::class,
                ),
            ),
            new OA\Parameter(
                name: 'encoding',
                in: 'query',
                description: 'The character encoding of the GEDCOM file. Defaults to UTF-8.',
                required: false,
                schema: new OA\Schema(
                    ref: ExportEncoding::class,
                ),
            ),
            new OA\Parameter(
                name: 'line_endings',
                in: 'query',
                description: 'The line endings of the GEDCOM file. Defaults to CRLF.',
                required: false,
                schema: new OA\Schema(
                    ref: LineEndings::class,
                ),
            ),
            new OA\Parameter(
                name: 'privacy',
                in: 'query',
                description: 'The privacy level for the exported GEDCOM file. Defaults to "none".',
                required: false,
                schema: new OA\Schema(
                    ref: Privacy::class,
                ),
            ),
            new OA\Parameter(
                name: 'time_stamp',
                in: 'query',
                description: 'The type of time stamp, which is added to the filename of an exported GEDCOM file. Defaults to "none".',
                required: false,
                schema: new OA\Schema(
                    ref: TimeStamp::class,
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
            ),
            new OA\Parameter(
                name: 'gedbas_api_key',
                in: 'query',
                description: 'GEDBAS API key, which allows to upload GEDCOM files for a certain GEDBAS account. Will default to a value in the module settings of Extended Import/Export if it has been stored before.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                ),
            ),
            new OA\Parameter(
                name: 'gedbas_id',
                in: 'query',
                description: 'The ID of the GEDBAS database, to which the GEDCOM file shall be uploaded or which shall be created. Will default to a value in the module settings of Extended Import/Export if it has been stored before.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                ),
            ),
            new OA\Parameter(
                name: 'gedbas_title',
                in: 'query',
                description: 'The title of the GEDBAS database. By default, the GEDBAS database title is generated from the tree title. Will default to a value in the module settings of Extended Import/Export if it has been stored before.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                ),
            ),
            new OA\Parameter(
                name: 'gedbas_description',
                in: 'query',
                description: 'The description of the GEDBAS database. By default, the GEDBAS database description is generated from HEAD:NOTE or from the tree title. Will default to a value in the module settings of Extended Import/Export if it has been stored before.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                ),
            ),
        ],
        responses: [          
            new OA\Response(
                response: '200', 
                description: 'Successfully exported tree.',
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
            return $this->exportTree($request);        
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
    private function exportTree(ServerRequestInterface $request): ResponseInterface
    {
        $base_url              = Validator::attributes($request)->string('base_url');

        $tree_name             = Validator::queryParams($request)->string('tree', '');
        $filename              = Validator::queryParams($request)->string('filename', '');
        $file_format           = Validator::queryParams($request)->string('file_format', '');
        $export_encoding       = Validator::queryParams($request)->string('encoding', '');
        $line_endings          = Validator::queryParams($request)->string('line_endings', '');
        $privacy               = Validator::queryParams($request)->string('privacy', '');
        $time_stamp            = Validator::queryParams($request)->string('time_stamp', '');
        $gedcom_filter1        = Validator::queryParams($request)->string('gedcom_filter1', '');
        $gedcom_filter2        = Validator::queryParams($request)->string('gedcom_filter2', '');
        $gedcom_filter3        = Validator::queryParams($request)->string('gedcom_filter3', '');
        $GEDBAS_apiKey         = Validator::parsedBody($request)->string('GEDBAS_apiKey', '');
        $GEDBAS_Id             = Validator::parsedBody($request)->string('GEDBAS_Id', '');
        $GEDBAS_title          = Validator::parsedBody($request)->string('GEDBAS_title', '');
        $GEDBAS_description    = Validator::parsedBody($request)->string('GEDBAS_description', '');

        //Check availability of Extended Import/Export module
        try {
            /** @var DownloadGedcomWithURL $download_gedcom_with_url To avoid IDE warnings */
            $download_gedcom_with_url = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        }
        catch (Throwable $th) {
            return api_response(
                'Cannot export tree, because the required custom module Extended "Import/Export" is not available.',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        if ($download_gedcom_with_url->customModuleVersion() < WebtreesApi::REQUIRED_IMPORT_EXPORT_VERSION) {
            return api_response(
                'Cannot export tree, because the custom module version of Extended Import/Export does not support webtrees-API. Please upgrade the module to a version ' . WebtreesApi::REQUIRED_IMPORT_EXPORT_VERSION . ' or higher.',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        // Validate tree
        $tree_validation_response = QueryParamValidator::validateTreeName($this->tree_service, $tree_name);
        if ($tree_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $tree_validation_response;
        }

        $tree = $this->tree_service->all()[$tree_name];

        // Validate filename
        if ($filename !== '') {
            $filename_validation_response = QueryParamValidator::validateFileName($filename);
            if ($filename_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                return $filename_validation_response;
            }
        }
        else {
            $filename = $tree->name();
        }

        // Validate file format
        $file_format_validation_response = QueryParamValidator::validateFileFormat($file_format);
        if ($file_format_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $file_format_validation_response;
        }

        // Validate encoding
        $export_encoding_validation_response = QueryParamValidator::validateExportEncoding($export_encoding);
        if ($export_encoding_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $export_encoding_validation_response;
        }

        // Validate line endings
        $line_endings_validation_response = QueryParamValidator::validateLineEndings($line_endings);
        if ($line_endings_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $line_endings_validation_response;
        }

        // Validate privacy level
        $privacy_validation_response = QueryParamValidator::validatePrivacy($privacy);
        if ($privacy_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $privacy_validation_response;
        }

        // Validate time_stamp
        $time_stamp = $time_stamp !== '' ? $time_stamp : DownloadGedcomWithURL::TIME_STAMP_NONE;
        
        $time_stamp_validation_response = QueryParamValidator::validateTimeStamp($time_stamp);
        if ($time_stamp_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return $time_stamp_validation_response;
        }

        // Validate GEDCOM filters
        foreach (['gedcom_filter1' => $gedcom_filter1, 'gedcom_filter2' => $gedcom_filter2, 'gedcom_filter3' => $gedcom_filter3] as $filter_name => $filter_value) {
            $gedcom_filter_validation_response = QueryParamValidator::validateGedcomFilter($filter_value);
            if ($gedcom_filter_validation_response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                return api_response('Invalid GEDCOM filter parameter "' . $filter_name . '": ' . $filter_value, StatusCodeInterface::STATUS_BAD_REQUEST);
            }
        }

        // Export tree by calling Extended Import/Export custom module
        $data = [
            'called_from'           => DownloadGedcomWithURL::CALLED_FROM_WEBTREES_API,
            'action'                => DownloadGedcomWithURL::ACTION_SAVE,
            'source'                => 'server',
            'export_clippings_cart' => false,
            'tree'                  => $tree->name(),
            'filename'              => $filename,
            'format'                => $file_format,
            'encoding'              => $export_encoding,
            'line_endings'          => $line_endings,
            'privacy'               => $privacy,
            'time_stamp'            => $time_stamp,
            'gedcom_filter1'        => $gedcom_filter1,
            'gedcom_filter2'        => $gedcom_filter2,
            'gedcom_filter3'        => $gedcom_filter3,
            'GEDBAS_apiKey'         => $GEDBAS_apiKey,
            'GEDBAS_Id'             => $GEDBAS_Id,            
            'GEDBAS_title'          => $GEDBAS_title,
            'GEDBAS_description'    => $GEDBAS_description,
        ];

        $request = new ServerRequest(method: 'POST', uri: '')
            ->withAttribute('base_url', $base_url)
            ->withParsedBody($data);

        $response = $download_gedcom_with_url->handle($request);

        if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            return api_response('Failed to export tree: ' . $response->getBody(), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }
        else {
            return api_response('Successfully exported tree', StatusCodeInterface::STATUS_OK);
        }
    }
}
