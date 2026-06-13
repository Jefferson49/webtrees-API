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
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


class CheckAccess
{
    // Privacy settings for trees (see: \resources\views\admin\trees-privacy.phtml)
    public const string HIDE_LIVE_PEOPLE           = 'HIDE_LIVE_PEOPLE';
    public const string MAX_ALIVE_AGE              = 'MAX_ALIVE_AGE';
    public const string SHOW_LIVING_NAMES          = 'SHOW_LIVING_NAMES';


	/**
     * Check record access
     * 
     * @param GedcomRecord $record   The record to check access for
     * @param bool         $edit     Whether to check for edit (write) access instead of view (read) access
     * @param bool         $privacy  Whether to check record access with privacy access level instead of user based access level
     *
     * @return ResponseInterface
     */	
    public static function checkRecordAccess(GedcomRecord $record, bool $edit = false, bool $privacy = false): ResponseInterface {

        if ($privacy) {
            // Check privacy settings of the record
            if (!$record->canShow(Auth::PRIV_PRIVATE)) {
                return api_response(
                    'Insufficient permissions: No access to record due to privacy settings.', 
                    StatusCodeInterface::STATUS_FORBIDDEN
                );
            }
        }
        else {
            try {
                // Check record access based on user permissions
                $record = Auth::checkRecordAccess($record, $edit);
            } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
                return api_response(
                    'Insufficient permissions: No access to record.',
                    StatusCodeInterface::STATUS_FORBIDDEN
                );
            }
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
    }

	/**
     * Check whether the user has appropriate write access for API operations
     * 
     * @param Tree $tree
     *
     * @return ResponseInterface
     */	
    public static function checkUserWriteAccess(Tree $tree): ResponseInterface {
    
        if (Auth::isModerator($tree)) {
            return api_response(
                'Insufficient permissions: API users must not have moderator rights',
                StatusCodeInterface::STATUS_FORBIDDEN
            );
        }        

        if (!Auth::isEditor($tree)) {
            return api_response(
                'Insufficient permissions: API user does not have editor rights for the tree.',
                StatusCodeInterface::STATUS_FORBIDDEN
            );
        }

        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return api_response(
                'Insufficient permissions: Automatically accept changes must not be activated for the API user.',
                StatusCodeInterface::STATUS_FORBIDDEN
            );
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
    }

	/**
     * Check whether minimum privacy settings of the tree are met for API operations
     * 
     * @param Tree $tree
     *
     * @return ResponseInterface
     */	
    public static function checkTreePrivacy(Tree $tree): ResponseInterface {

        // Validate the privacy settings of the tree   
        // from: \resources\views\admin\trees-privacy.phtml
        // Default values from: Tree::DEFAULT_PREFERENCES

        $hide_live_people  = $tree->getPreference(self::HIDE_LIVE_PEOPLE); // Default: 1 (true)
        $max_alive_age     = (int) $tree->getPreference(self::MAX_ALIVE_AGE); // Default: 120
        $show_living_names = $tree->getPreference(self::SHOW_LIVING_NAMES);  // Auth::PRIV_USER (1)

        // Apply strict privacy settings
        if (!$hide_live_people) {
            return api_response(
                'Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Show living individuals must not be set to "Show to visitors"',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if ($max_alive_age < 120) {
            return api_response(
                'Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Age at which to assume an individual is dead must be at least 120 years.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if ($show_living_names === Auth::PRIV_PRIVATE) {
            return api_response(
                'Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Show living names must not be set to "Show to visitors"',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        return api_response('OK', StatusCodeInterface::STATUS_OK);
    }
}
