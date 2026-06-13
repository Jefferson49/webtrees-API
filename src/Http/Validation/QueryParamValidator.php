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

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ExportAction;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ExportEncoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\ImportEncoding;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\FileFormat;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\LineEndings;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Privacy;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\StringEncodedBoolean;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Http\Message\ResponseInterface;

use InvalidArgumentException;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


class QueryParamValidator
{
	/**
     * Validate tree name
     * 
     * @param TreeService $tree_service
     * @param string      $name
     * @param bool        $find_tree  Whether to check if a tree with the given name exists in the webtrees database
     *
     * @return ResponseInterface
     */	
    public static function validateTreeName(TreeService $tree_service, string $name, bool $find_tree = true): ResponseInterface {

        if ($name === '') {
            return api_response('Invalid tree parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $name)) {
            return api_response('Invalid tree parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (strlen($name) > 1024) {
            return api_response('Invalid tree parameter; maximum number of characters exceeded.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif ($find_tree && !array_key_exists($name, $tree_service->all()->toArray())) {
            return api_response('Tree not found' . ': ' . $name, StatusCodeInterface::STATUS_NOT_FOUND);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid xref parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        
        try {
            $record = Registry::gedcomRecordFactory()->make($xref, $tree);
        } catch (InvalidArgumentException $ex) {
            return api_response('No matching GEDCOM record found for XREF' . ': ' . $xref, StatusCodeInterface::STATUS_NOT_FOUND);
        }

        if ($record === null) {
            return api_response('No matching GEDCOM record found for XREF' . ': ' . $xref, StatusCodeInterface::STATUS_NOT_FOUND);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
                return api_response('OK', StatusCodeInterface::STATUS_OK);
            }
            else {
                return api_response('Empty GEDCOM received.', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
        }
  
        $gedcom_lines = explode("\n", $gedcom);
        foreach ($gedcom_lines as $gedcom_line) {

            if (1 === preg_match('/0 @(' . Gedcom::REGEX_XREF . ')@ (' .Gedcom::REGEX_TAG . ')/', $gedcom_line, $matches) ) {

                return api_response(
                    'The GEDCOM text must not contain a level 0 line, because it is created automatically' . ': '. $gedcom_line,
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
            elseif (1 !== preg_match('/(\d+) (' . Gedcom::REGEX_TAG . ')(.*)/', $gedcom_line, $matches) ) {

                return api_response(
                    'Invalid format of GEDCOM line: ' . $gedcom_line, 
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid filename parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $filename)) {
            return api_response('Invalid filename parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (strlen($filename) > 1024) {
            return api_response('Invalid filename parameter; maximum number of characters exceeded.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid import encoding parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid export encoding parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid string encoded boolean parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('OK', StatusCodeInterface::STATUS_OK);
        }
        if (!preg_match('/^' . WebtreesApi::REGEX_FILE_NAME . '$/', $filter_name)) {
            return api_response('Invalid GEDCOM filter parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        elseif (strlen($filter_name) > 1024) {
            return api_response(
                'Invalid GEDCOM filter parameter; maximum number of characters exceeded.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid boolean parameter', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid line endings parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid file format parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
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
            return api_response('Invalid privacy parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
    }

    /**
     * Validate export action
     * 
     * @param string $export_action
     *
     * @return ResponseInterface
     */	
    public static function validateExportAction(string $export_action): ResponseInterface {

        if (!in_array($export_action, ExportAction::SCHEMA_ENUM_VALUES, true) && $export_action !== '') {
            return api_response('Invalid export action parameter.', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
    }
}
