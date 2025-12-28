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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;

use InvalidArgumentException;


class QueryParamValidator
{
	/**
     * Validate tree name
     * 
     * @param string $name
     *
     * @return ResponseInterface
     */	
    public static function validateTreeName(string $name): ResponseInterface {

        if ($name === '') {
            return new Response400('Invalid tree parameter');
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $name)) {
            return new Response400('Invalid tree parameter');
        }
        elseif (strlen($name) > 1024) {
            return new Response400('Invalid tree parameter');
        }
        elseif (!Functions::isValidTree($name)) {
            return new Response404('Tree not found');
        }

        return new Response200();
    }

	/**
     * Validate XREF
     * 
     * @param Tree $tree
     * @param string $xref
     *
     * @return ResponseInterface
     */	
    public static function validateXref(Tree $tree, string $xref): ResponseInterface {

        if (!preg_match('/^' . Gedcom::REGEX_XREF .'$/', $xref)) {
            return new Response400('Invalid xref parameter');
        }
        
        try {
            $record = Registry::gedcomRecordFactory()->make($xref, $tree);
        } catch (InvalidArgumentException $ex) {
            return new Response404( 'No matching GEDCOM record found for XREF');
        }

        if ($record === null) {
            return new Response404( 'No matching GEDCOM record found for XREF');
        }

        return new Response200();
    }

	/**
     * Validate GEDCOM
     * 
     * @param string       $gedcom
     *
     * @return ResponseInterface
     */	
    public static function validateGedcomRecord(string $gedcom): ResponseInterface {

        if ($gedcom === '' OR $gedcom === "\n") {
            return new Response400('Empty GEDCOM received.');
        }
        else {
            $count_level0 = 0;
            $gedcom_lines = explode("\n", $gedcom);
            foreach ($gedcom_lines as $gedcom_line) {

                if (1 === preg_match('/0 @(' . Gedcom::REGEX_XREF . ')@ (' .Gedcom::REGEX_TAG . ')/', $gedcom_line, $matches) ) {

                    $count_level0 ++;
                    if ($count_level0 > 1) {
                        return new Response400('The GEDCOM text contains more than 1 line with a level 0 tag: ' . $gedcom_line);
                    }
                }
                elseif (1 !== preg_match('/(\d+) (' . Gedcom::REGEX_TAG . ')(.*)/', $gedcom_line, $matches) ) {
                    return new Response400('Invalid format of GEDCOM line: ' . $gedcom_line);
                }
            }
        }

        return new Response200();
    }    
}
