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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Tree;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Psr\Http\Message\ResponseInterface;


class CheckAccess
{
    // Privacy settings for trees (see: \resources\views\admin\trees-privacy.phtml)
    public const string HIDE_LIVE_PEOPLE           = 'HIDE_LIVE_PEOPLE';
    public const string MAX_ALIVE_AGE              = 'MAX_ALIVE_AGE';
    public const string SHOW_LIVING_NAMES          = 'SHOW_LIVING_NAMES';


	/**
     * Check record access
     * 
     * @param GedcomRecord $record
     * @param bool $edit     Whether to check for edit (write) access instead of view (read) access
     * @param bool $privacy  Whether to check record access with privacy access level instead of user based access level
     *
     * @return ResponseInterface
     */	
    public static function checkRecordAccess(GedcomRecord $record, bool $edit = false, bool $privacy = false): ResponseInterface {

        if ($record === null) {
            return new Response404('Record not found');
        }

        if ($privacy) {
            // Check privacy settings of the record
            if (!$record->canShow(Auth::PRIV_PRIVATE)) {
                return new Response403('Insufficient permissions: No access to record due to privacy settings.');
            }
        }
        else {
            try {
                // Check record access based on user permissions
                $record = Auth::checkRecordAccess($record, $edit);
            } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
                return new Response403('Insufficient permissions: No access to record.');
            }
        }

        return new Response200();
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
            return new Response403('Insufficient permissions: API users must not have moderator rights');
        }        

        if (!Auth::isEditor($tree)) {
            return new Response403('Insufficient permissions: API user does not have editor rights for the tree.');
        }

        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return new Response403('Insufficient permissions: Automatically accept changes must be activated for the API user.');
        }

        return new Response200();
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
            return new Response400('Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Show living individuals must not be set to "Show to visitors"');
        }

        if ($max_alive_age < 120) {
            return new Response400('Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Age at which to assume an individual is dead must be at least 120 years.');
        }

        if ($show_living_names === Auth::PRIV_PRIVATE) {
            return new Response400('Access to tree rejected, because the privacy setting do not fulfill the minimum requirements. Show living names must not be set to "Show to visitors"');
        }

        return new Response200();
    }
}
