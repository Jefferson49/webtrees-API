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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema;

use OpenApi\Attributes as OA;


/**
 * CliConsoleOutput
 *
 * The output of a command line execution
 */

#[OA\Schema(
    title: 'CliCommandOutput', 
    description: 'The output of a command line execution.',
    additionalProperties: false,
)]
class CliConsoleOutput
{
    public function __construct(bool $error, int $exit_code, string $console_output) {
        $this->error          = $error;
        $this->exit_code      = $exit_code;
        $this->console_output = $console_output;
    }
    
    #[OA\Property(
        property: 'error', 
        type: 'boolean', 
        description: 'Whether an error occurred during the execution of the command.',
    )]
    public bool $error;
    
    #[OA\Property(
        property: 'exit_code', 
        type: 'integer', 
        description: 'The exit code returned from the command line execution.',
    )]
    public int $exit_code;

    #[OA\Property(
        property: 'console_output', 
        type: 'string', 
        description: 'The console output returned from the command line execution.',
    )]
    public string $console_output;
}
