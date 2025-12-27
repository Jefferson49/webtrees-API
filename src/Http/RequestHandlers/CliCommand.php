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

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Cli\Console;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\CliConsoleOutput;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

use RuntimeException;
use Throwable;

use function fopen;


class CliCommand implements RequestHandlerInterface
{
    private StreamFactoryInterface $stream_factory;
    private const MAX_COMMAND_LENGTH = 8096;

    public function __construct(StreamFactoryInterface $stream_factory)
    {
        $this->stream_factory = $stream_factory;
    }

    #[OA\Post(
        path: '/cli-command',
        tags: ['webtrees'],
        description: 'Execute a command on the webtrees command line interface (CLI).',
        parameters: [
            new OA\Parameter(
                name: 'command',
                in: 'query',
                description: 'The command to be executed by the command line interface.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: self::MAX_COMMAND_LENGTH,
                    example: 'config-ini --rewrite-urls',
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The result of a command line execution.',
                content: new OA\MediaType(
                    mediaType: 'application/json', 
                    schema: new OA\Schema(ref: CliConsoleOutput::class),
                ),
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
        ]
    )]
	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->cliCommand($request);        
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
    private function cliCommand(ServerRequestInterface $request): ResponseInterface
    {
        $command = Validator::queryParams($request)->string('command', '');

        // Validate command       
        if (strlen($command) === 0 OR strlen($command) > self::MAX_COMMAND_LENGTH ) {
            return new Response400('Invalid command parameter');
        }

        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        $console = new Console();
        $console->setAutoExit(false);
        $input   = new StringInput($command);
        $output  = new StreamOutput($stream);
        
        $exit_code = $console->loadCommands()->bootstrap()->run($input, $output);
        $error     = $exit_code !== 0;

        $output_stream = $this->stream_factory->createStreamFromResource($stream);
        $output_stream->rewind();
        $console_output = $output_stream->getContents();

        return Registry::responseFactory()->response(
            json_encode(new CliConsoleOutput(
                error: $error,
                exit_code: $exit_code,
                console_output: $console_output
            )) ,
            StatusCodeInterface::STATUS_OK
        );
    }

	/**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        //The CLI command tool is not available for MCP
        return [];
    }
}
