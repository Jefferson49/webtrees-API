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

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Factories\GedcomRecordFactory;
use Gedcom\GedcomX\Generator;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\GedcomX\StringParser;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

use Throwable;


class GedcomData implements McpToolRequestHandlerInterface
{
    public const FORMAT_GEDCOM   = 'gedcom';
    public const FORMAT_GEDCOM_X = 'gedcom-x';
    public const FORMAT_JSON     = 'json';

    #[OA\Get(
        path: '/gedcom-data',
        tags: ['webtrees'],
        parameters: [
            new OA\Parameter(
                name: 'tree',
                in: 'query',
                description: 'The name of the tree.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 1024,
                    pattern: '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    example: 'mytree',
                ),
            ),
            new OA\Parameter(
                name: 'xref',
                in: 'query',
                description: 'The XREF (i.e. GEDOM cross-reference identifier) of the record to retrieve.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 20,
                    pattern: '^' . Gedcom::REGEX_XREF .'$',
                    example: 'X1234',
                ),
            ),
            new OA\Parameter(
                name: 'format',
                in: 'query',
                description: 'The format of the output. Possible values are "gedcom" (GEDCOM 5.5.1), "gedcom-x" (default; a JSON GEDCOM format defined by Familysearch), and "json" (identical to gedcom-x).',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['gedcom', 'gedcom-x', 'json'],
                    default: 'gedcom-x',
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The GEDCOM data of a record in webtrees',
                content: [
                    new OA\JsonContent(
                        type: 'object',
                        description: 'The GEDCOM-X data of a record in webtrees',
                        example: 
                            ['persons' => [[
                                'id' => 'X1234',
                                'names' => [[
                                    'nameForms' => [[
                                        'fullText' => 'John Doe',
                                    ]]]],
                                'facts' => [[
                                    'type' => 'http://gedcomx.org/Birth',
                                    'date' => [
                                        'original' => '19 FEB 1870',
                            ]]]]]],
                    ),
                    new OA\MediaType(
                        mediaType: 'application/text',
                        schema: new OA\Schema(
                            type: 'string',
                            description: 'The GEDCOM 5.5.1 data of a record in webtrees',
                            example:
                                "0 @X1234@ INDI\n".
                                "1 NAME John /Doe/\n".
                                "1 BIRT\n".
                                "2 DATE 19 FEB 1870\n"
                        ),
                    ),
                ],
            ),
            new OA\Response(
                response: '400', 
                description: 'Invalid format parameter.', 
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
                ref: Response429::class,
            ),
        ],
    )]
	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->gedcomData($request);        
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
    private function gedcomData(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('xref', '');
        $format    = Validator::queryParams($request)->string('format', self::FORMAT_GEDCOM_X);

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

        // Validate format
        if (!in_array($format, [self::FORMAT_GEDCOM, self::FORMAT_GEDCOM_X, self::FORMAT_JSON])) {
            return new Response400('Invalid format parameter');
        }

        $gedcom_factory = new GedcomRecordFactory();
        $record = $gedcom_factory->make( $xref, $tree);

        if ($record === null) {
            return new Response404( 'No matching GEDCOM record found for XREF');
        }

        //Create GEDCOM
        $gedcom = self::getGedcomHeader();
        $gedcom .= $record->gedcom() . "\n";
        $gedcom .= self::getGedcomOfLinkedRecords($tree, $gedcom, [$record->xref()]);
        $gedcom .= "0 TRLR\n";

        if ($format === self::FORMAT_GEDCOM) {
            return Registry::responseFactory()->response($gedcom);
        }
        elseif (in_array($format, [self::FORMAT_GEDCOM_X, self::FORMAT_JSON])) {
            $parser = new StringParser();
            $gedcom_object = $parser->parse($gedcom);
            $generator = new Generator($gedcom_object);
            $gedcom_x_json = $generator->generate();
            $gedcom_x_json = self::substituteXREFs($generator, $gedcom_x_json);

            return Registry::responseFactory()->response($gedcom_x_json);
        }
        else {
            return new Response400('Invalid format parameter');
        }
    }

    /**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'get-gedcom-data',
            'description' => 'GET /gedcom-data [API: GET /gedcom-data]',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tree' => [
                        'type' => 'string',
                        'description' => 'The name of the tree. (in: query)',
                        'maxLength' => 1024,
                        'pattern' => '^' . WebtreesApi::REGEX_FILE_NAME . '$',
                    ],
                    'xref' => [
                        'type' => 'string',
                        'description' => 'The XREF (i.e. GEDOM cross-reference identifier) of the record to retrieve. (in: query)',
                        'maxLength' => 20,
                        'pattern' => '^' . Gedcom::REGEX_XREF .'$'
                    ],
                    'format' => [
                        'type' => 'string',
                        'description' => 'The format of the output. Possible values are "gedcom" (GEDCOM 5.5.1), "gedcom-x" (default; a JSON GEDCOM format defined by Familysearch), and "json" (identical to gedcom-x). (in: query)',
                        'enum' => ['gedcom', 'gedcom-x', 'json'],
                        'default' => 'gedcom-x'
                    ]
                ],
                'required' => ['tree', 'xref']
            ],
            'outputSchema' => [
                'type' => 'object',
            ],
            'annotations' => [
                'title' => 'GET /gedcom-data',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }

    /**
     * Get a GEDCOM string, which includes the combined GEDCOM strings of all records linked (by XREF)  
     * 
     * @param Tree   $tree
     * @param string $gedcom
     * @param array  $excluded_xrefs
     *
     * @return string
     */	
    public static function getGedcomOfLinkedRecords(Tree $tree, string $gedcom, array $excluded_xrefs = []): string {

        $linked_records_gedcom = '';
        $gedcom_factory = new GedcomRecordFactory();
        preg_match_all('/@('.Gedcom::REGEX_XREF.')@/', $gedcom, $matches);

        foreach ($matches[1] as $xref) {

            // Do nothing if record is in excluded list or is already included
            if (   in_array($xref, $excluded_xrefs)
                OR preg_match('/0 @' . $xref . '@/', $linked_records_gedcom) === 1) {

                continue;
            }

            $record = $gedcom_factory->make( $xref, $tree);

            if ($record !== null) {
                $record_tag = $record->tag();

                switch ($record_tag) {
                    case 'INDI':
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";

                        foreach (['NAME'] as $tag) {
                            preg_match_all('/1 ' . $tag . ' (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '1 ' . $tag . ' ' . $payload . "\n";
                            }
                        }
                        foreach (['BIRT', 'DEAT'] as $tag) {
                            preg_match_all('/1 ' . $tag . ".*\n2 DATE ([^\n]*)\n?/s", $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '1 ' . $tag . "\n2 DATE " . $payload . "\n";
                            }
                        }
                        break;
                    case 'FAM':
                        $linked_records_gedcom .= $record->gedcom() . "\n";
                        // Get already records in order to add them to the excluded list
                        preg_match_all('/0 @('.Gedcom::REGEX_XREF.')@/', $linked_records_gedcom, $matches);
                        $linked_records_gedcom .= self::getGedcomOfLinkedRecords($tree, $record->gedcom(),array_merge($excluded_xrefs, [$record->xref()], $matches[1]?? []));
                        break;
                    case 'NOTE':
                        preg_match_all('/0 @' . $xref . '@ NOTE (.*)/', $record->gedcom(), $matches);
                        $linked_records_gedcom .= $matches[0][0];
                        break;
                    case 'OBJE':
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";

                        preg_match_all('/1 FILE (.*)/', $record->gedcom(), $matches);

                        foreach ($matches[1] as $payload) {
                            $linked_records_gedcom .= '1 FILE ' . $payload . "\n";

                            preg_match_all('/2 FORM (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '2 FORM ' . $payload . "\n";
                            }

                            preg_match_all('/2 TITL (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '2 TITL ' . $payload . "\n";
                            }
                        }
                        break;
                    case 'SOUR':
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";

                        foreach (['TITL'] as $tag) {
                            preg_match_all('/1 ' . $tag . ' (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '1 ' . $tag . ' ' . $payload . "\n";
                            }
                        }
                        break;
                    case 'REPO':
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";

                        foreach (['NAME'] as $tag) {
                            preg_match_all('/1 ' . $tag . ' (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '1 ' . $tag . ' ' . $payload . "\n";
                            }
                        }
                        break;
                    case '_LOC':
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";

                        foreach (['NAME'] as $tag) {
                            preg_match_all('/1 ' . $tag . ' (.*)/', $record->gedcom(), $matches);

                            foreach ($matches[1] as $payload) {
                                $linked_records_gedcom .= '1 ' . $tag . ' ' . $payload . "\n";
                            }
                        }
                        break;
                    default:
                        $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record_tag . "\n";
                }
            }
        }

        return $linked_records_gedcom;
    }

	/**
     * Get a GEDCOM string, which includes the combined GEDCOM strings of all records linked (by XREF)  
     * 
     * @param Generator $generator  The GEDCOM-X generator
     * @param string    $gedcom
     *
     * @return string
     */	
    public static function substituteXREFs(Generator $generator, string $gedcom): string {

        // Create Reflection structure
        $reflection  = new ReflectionClass('\\Gedcom\\GedcomX\\Generator');
        $personIdMap  = $reflection->getProperty('personIdMap');
        $relationshipIdMap  = $reflection->getProperty('relationshipIdMap');

        $xrefs = array_merge($personIdMap->getValue($generator), $relationshipIdMap->getValue($generator));

        foreach ($xrefs as $replace => $search) {

            $replace = str_replace('_couple', '', $replace);
            $gedcom = str_replace($search, $replace, $gedcom);
        }

        return $gedcom;
    }

	/**
     * Create a GEDCOM header  
     *
     * @return string
     */	
    public static function getGedcomHeader(): string {

        return
            "0 HEAD\n".
            "1 SOUR webtrees\n".
            "1 CHAR UTF-8\n".
            "1 GEDC\n".
            "2 VERS 5.5.1\n".
            "2 FORM LINEAGE-LINKED\n";
    }
}
