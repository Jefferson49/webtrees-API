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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ExportEncoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ImportEncoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\FileFormat;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\LineEndings;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Privacy;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\StringEncodedBoolean;
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
    public static function validateTreeName(TreeService $tree_service, string $name): ResponseInterface {

        if ($name === '') {
            return new Response400('Invalid tree parameter.');
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $name)) {
            return new Response400('Invalid tree parameter.');
        }
        elseif (strlen($name) > 1024) {
            return new Response400('Invalid tree parameter; maximum number of characters exceeded.');
        }
        elseif (!array_key_exists($name, $tree_service->all()->toArray())) {
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
            return new Response400('Invalid xref parameter.');
        }
        
        try {
            $record = Registry::gedcomRecordFactory()->make($xref, $tree);
        } catch (InvalidArgumentException $ex) {
            return new Response404( 'No matching GEDCOM record found for XREF.');
        }

        if ($record === null) {
            return new Response404( 'No matching GEDCOM record found for XREF.');
        }

        return new Response200();
    }

	/**
     * Validate GEDCOM
     * 
     * @param string $gedcom
     * @param bool   $allow_empty
     *
     * @return ResponseInterface
     */	
    public static function validateGedcomRecord(string $gedcom, bool $allow_empty = true): ResponseInterface {

        if ($gedcom === '' OR $gedcom === "\n") {
            if ($allow_empty) {
                return new Response200();
            }
            else {
                return new Response400('Empty GEDCOM received.');
            }
        }
  
        $gedcom_lines = explode("\n", $gedcom);
        foreach ($gedcom_lines as $gedcom_line) {

            if (1 === preg_match('/0 @(' . Gedcom::REGEX_XREF . ')@ (' .Gedcom::REGEX_TAG . ')/', $gedcom_line, $matches) ) {

                return new Response400('The GEDCOM text must not contain a level 0 line, because it is created automatically' . ': '. $gedcom_line);
            }
            elseif (1 !== preg_match('/(\d+) (' . Gedcom::REGEX_TAG . ')(.*)/', $gedcom_line, $matches) ) {

                return new Response400('Invalid format of GEDCOM line: ' . $gedcom_line);
            }
        }

        return new Response200();
    }

	/**
     * Validate filename name
     * 
     * @param string $filename
     *
     * @return ResponseInterface
     */	
    public static function validateFileName(string $filename): ResponseInterface {

        if ($filename === '') {
            return new Response400('Invalid filename parameter.');
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $filename)) {
            return new Response400('Invalid filename parameter.');
        }
        elseif (strlen($filename) > 1024) {
            return new Response400('Invalid filename parameter; maximum number of characters exceeded.');
        }

        return new Response200();
    }

	/**
     * Validate import encoding
     * 
     * @param string $encoding
     *
     * @return ResponseInterface
     */	
    public static function validateImportEncoding(string $encoding): ResponseInterface {

        if (!in_array($encoding, ImportEncoding::SCHEMA_ENUM_VALUES, true) && $encoding !== '') {
            return new Response400('Invalid import encoding parameter.');
        }

        return new Response200();
    }

    /**
     * Validate export encoding
     * 
     * @param string $encoding
     *
     * @return ResponseInterface
     */	
    public static function validateExportEncoding(string $encoding): ResponseInterface {

        if (!in_array($encoding, ExportEncoding::SCHEMA_ENUM_VALUES, true) && $encoding !== '') {
            return new Response400('Invalid export encoding parameter.');
        }

        return new Response200();
    }    

    /**
     * Validate string encoded boolean
     * 
     * @param string $string_encoded_boolean
     *
     * @return ResponseInterface
     */	
    public static function validateStringEncodedBoolean(string $string_encoded_boolean): ResponseInterface {

        if (!in_array($string_encoded_boolean, [''] + StringEncodedBoolean::SCHEMA_ENUM_VALUES, true)) {
            return new Response400('Invalid string encoded boolean parameter.');
        }

        return new Response200();
    }

    /**
     * Validate GEDCOM filter name
     * 
     * @param string $filter_name
     *
     * @return ResponseInterface
     */	
    public static function validateGedcomFilter(string $filter_name): ResponseInterface {

        if ($filter_name === '') {
            return new Response200();
        }
        if (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $filter_name)) {
            return new Response400('Invalid GEDCOM filter parameter.');
        }
        elseif (strlen($filter_name) > 1024) {
            return new Response400('Invalid GEDCOM filter parameter; maximum number of characters exceeded.');
        }

        return new Response200();
    }
    
	/**
     * Validate boolean
     * 
     * @param string $value
     *
     * @return ResponseInterface
     */	
    public static function validateBoolean(string $value): ResponseInterface {

        if ($value !== 'true' && $value !== 'false') {
            return new Response400('Invalid boolean parameter');
        }

        return new Response200();
    }

    /**
     * Validate line endings
     * 
     * @param string $line_endings
     *
     * @return ResponseInterface
     */	
    public static function validateLineEndings(string $line_endings): ResponseInterface {

        if (!in_array($line_endings, LineEndings::SCHEMA_ENUM_VALUES, true) && $line_endings !== '') {
            return new Response400('Invalid line endings parameter.');
        }

        return new Response200();
    }

    /**
     * Validate file format
     * 
     * @param string $file_format
     *
     * @return ResponseInterface
     */	
    public static function validateFileFormat(string $file_format): ResponseInterface {

        if (!in_array($file_format, FileFormat::SCHEMA_ENUM_VALUES, true) && $file_format !== '') {
            return new Response400('Invalid file format parameter.');
        }

        return new Response200();
    }

    /**
     * Validate privacy level
     * 
     * @param string $privacy
     *
     * @return ResponseInterface
     */	
    public static function validatePrivacy(string $privacy): ResponseInterface {

        if (!in_array($privacy, Privacy::SCHEMA_ENUM_VALUES, true) && $privacy !== '') {
            return new Response400('Invalid privacy parameter.');
        }

        return new Response200();
    }
}
