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

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Factories\GedcomRecordFactory;
use Gedcom\GedcomX\Generator;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\McpApi\GedcomX\StringParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;


class GetGedcomData implements RequestHandlerInterface
{
	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree_name = Validator::queryParams($request)->string('tree', '');
        $xref      = Validator::queryParams($request)->string('xref', '');

        if ($tree_name === '') {
            $tree = null;
        }
        elseif (!Functions::isValidTree($tree_name)) {
            return response(McpApi::ERROR_WEBTREES_ERROR . ': Tree not found');
        } else {
            $tree = Functions::getAllTrees()[$tree_name];
        }                

        $gedcom_factory = new GedcomRecordFactory();
        $record = $gedcom_factory->make( $xref, $tree);

        if ($record === null) {
            return response(McpApi::ERROR_WEBTREES_ERROR . ': No matching Gedcom record found');
        }
        else {
            $gedcom = self::getGedcomHeader();
            $gedcom .= $record->gedcom() . "\n";
            $gedcom .= self::getGedcomOfLinkedRecords($tree, $gedcom, [$record->xref()]);
            $gedcom .= "0 TRLR\n";
            $parser = new StringParser();
            $gedcom_object = $parser->parse($gedcom);
            $generator = new Generator($gedcom_object);
            $gedcom_x_json = $generator->generate();
            $gedcom_x_json = self::substituteXREFs($generator, $gedcom_x_json);

            //return response($record->gedcom());
            return response($gedcom_x_json);
        }
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

        $gedcom_factory = new GedcomRecordFactory();
        preg_match_all('/@('.Gedcom::REGEX_XREF.')@/', $gedcom, $matches);
        $linked_records_gedcom = '';

        foreach ($matches[1] as $xref) {

            if (in_array($xref, $excluded_xrefs)) {
                continue;
            }

            $record = $gedcom_factory->make( $xref, $tree);

            if ($record !== null) {
                if ($record->tag() === 'FAM') {
                    $linked_records_gedcom .= $record->gedcom() . "\n";
                    $linked_records_gedcom .= self::getGedcomOfLinkedRecords($tree, $record->gedcom(),array_merge($excluded_xrefs, [$record->xref()]));
                }
                else {
                    $linked_records_gedcom .= '0 @' . $xref . '@ ' . $record->tag() . "\n";
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
