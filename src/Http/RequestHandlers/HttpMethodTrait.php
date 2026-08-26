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

use Fig\Http\Message\RequestMethodInterface;
use OpenApi\Annotations\Operation;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;


/**
 * Trait to get the HTTP method of request handlers based on the OpenAPi attributes
 */
trait HttpMethodTrait
{
	/**
     * Get the HTTP method of the class based on the OpenAPi attributes
     *
     * @return string
     */

    public function getHttpMethod(): string
    {
        $attributes = (new ReflectionMethod($this, 'handle'))
            ->getAttributes(
                Operation::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

        $operation = $attributes[0]?->newInstance();
        $httpMethod = $operation !== null ? (new ReflectionClass($operation))->getShortName() : '';

        switch ($httpMethod) {
            case 'Get':
                return RequestMethodInterface::METHOD_GET;
            case 'Post':
                return RequestMethodInterface::METHOD_POST;
            case 'DELETE':
                return RequestMethodInterface::METHOD_DELETE;
            case 'Put':
                return RequestMethodInterface::METHOD_PUT;
            default:
                return '';
        }
    }
}
