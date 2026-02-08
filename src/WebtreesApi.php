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

namespace Jefferson49\Webtrees\Module\WebtreesApi;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\View;
use Jefferson49\Webtrees\Exceptions\GithubCommunicationError;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Helpers\GithubService;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Exceptions\Oauth2KeysException;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\ApiPermission;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\ApiSession;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\GedbasMcpPermission;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\Login;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpPermission;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpToolPermission;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\OAuth2AccessToken;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\OAuth2Authorization;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpProtocol;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\OAuth2Initialization;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\ProcessApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\ProcessMcp;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AccessToken;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddParentToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CliCommand;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddUnlinkedRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CreateKeysModal;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CreateKeysAction;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CreateTokenAction;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\CreateTokenModal;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\DeleteClient;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\EditClientAction;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\EditClientModal;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GetRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkSpouseToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\McpTool;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\ModifyRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\RevokeToken;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\SearchGeneral;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Trees;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\TestApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\WebtreesVersion;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ClientRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\ScopeRepository;
use Jefferson49\Webtrees\Module\WebtreesApi\OAuth2\Repositories\AccessTokenRepository;
use League\Flysystem\Filesystem;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\ResourceServer;
use OpenApi\Attributes as OA;
use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use DateInterval;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function PHPUnit\Framework\directoryExists;


#[OA\OpenApi(openapi: OA\OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(
    title: 'webtrees API', 
    version: self::CUSTOM_VERSION,
)]
#[OA\Tag(
    name: 'webtrees',
    description: 'webtrees API'
)]
#[OA\Server(url: 'https://localhost/webtrees/api', description: 'webtrees server')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', description: 'Basic Auth')]

class WebtreesApi extends AbstractModule implements
	ModuleCustomInterface, 
	ModuleConfigInterface,
    CustomModuleLogInterface    
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;

    private Filesystem $data_filesystem;

	// Custom module version
	public const CUSTOM_VERSION = '1.0.0-rc.1';

	// Routes
    public const string ROUTE_MCP                 = '/mcp';
    public const string ROUTE_GEDBAS_MCP          = '/gedbas/mcp';
    public const string ROUTE_API                 = '/api';
    public const string ROUTE_OAUTH2_ACCESS_TOKEN = '/oauth/token';
    public const string ROUTE_EDIT_CLIENT_MODAL   = '/edit-client';
    public const string ROUTE_EDIT_CLIENT_ACTION  = '/edit-client-action';
    public const string ROUTE_DELETE_CLIENT       = '/delete-client';
    public const string ROUTE_CREATE_TOKEN_MODAL  = '/create-token';
    public const string ROUTE_CREATE_TOKEN_ACTION = '/create-token-action';
    public const string ROUTE_REVOKE_TOKEN        = '/revoke-token';
    public const string ROUTE_CREATE_KEYS_MODAL   = '/create-keys';
    public const string ROUTE_CREATE_KEYS_ACTION  = '/create-keys-action';

    // Paths
    public const string PATH_ADD_CHILD_TO_FAMILY  = 'add-child-to-family';
    public const string PATH_ADD_CHILD_TO_INDI    = 'add-child-to-individual';
    public const string PATH_ADD_PARENT_TO_INDI   = 'add-parent-to-individual';
    public const string PATH_ADD_SPOUSE_TO_INDI   = 'add-spouse-to-individual';
    public const string PATH_ADD_SPOUSE_TO_FAMILY = 'add-spouse-to-family';
    public const string PATH_LINK_CHILD_TO_FAMILY = 'link-child-to-family';
    public const string PATH_LINK_SPOUSE_TO_INDI  = 'link-spouse-to-individual';
    public const string PATH_GET_VERSION          = 'get-version';
    public const string PATH_SEARCH_GENERAL       = 'search-general';
    public const string PATH_GET_RECORD           = 'get-record';
    public const string PATH_MODIFY_RECORD        = 'modify-record';
    public const string PATH_ADD_UNLINKED_RECORD  = 'add-unlinked-record';
    public const string PATH_CLI_COMMAND          = 'cli-command';
    public const string PATH_GET_TREES            = 'get-trees';
    public const string PATH_TEST_API             = 'test-api';
    public const string PATH_GEDBAS_SEARCH_SIMPLE = 'search-simple';
    public const string PATH_GEDBAS_PERSON_DATA   = 'get-person-data';

	//Github repository
	public const string GITHUB_REPO = 'Jefferson49/webtrees-api';

	//Github API URL to get the information about the latest releases
	public const string GITHUB_API_LATEST_VERSION  = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
	public const string GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

	//Author of custom module
	public const string CUSTOM_AUTHOR = 'Markus Hemprich';

    //Prefences, Settings
	public const string PREF_WEBTREES_API_TOKEN = "webtrees_api_token";
	public const string PREF_USE_HASH           = "use_hash";
    public const string PREF_USER_ID            = 'user_id';
    public const string USER_PREF_BEARER_HASH   = 'bearer_token_hash ';
    public const string PREF_OAUTH2_CLIENTS     = 'oauth2_clients';
    public const string PREF_ACCESS_TOKENS      = 'access_tokens';
    public const string PREF_PATH_FOR_KEYS      = 'path_for_keys';
    public const string PREF_ENCRYPTION_KEY     = 'encryption_key';

    //Errors
    public const string ERROR_WEBTREES_ERROR    = "webtrees error";
    
    //Other constants
    public const string PRIVATE_KEY_FILE        = 'private.key';
    public const string PUBLIC_KEY_FILE         = 'public.key';
    public const string DEFAULT_PATH_FOR_KEYS   = 'oauth2_server_keys/';

    public const int    ENCRYPTION_KEY_LENGTH   = 32;
    public const string REGEX_FILE_NAME         = '[^<>:\"\/\\|?*\r\n]+';

    public const bool PREF_DEBUGGING_ACTIVATED = false;

   /**
     * WebtreesApi constructor.
     */
    public function __construct()
    {
        //Caution: Do not use the shared library jefferson47/webtrees-common within __construct(), 
        //         because it might result in wrong autoload behavior        
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        //Register this class in the webtrees container
        //This allows to access the module instance from other places, e.g. views/scripts (->assetUrl)
        Registry::container()->set(self::class, $this);

        // Create filesystem for the webtrees data directory
        $this->data_filesystem = Registry::filesystem()->data();

        $router         = Registry::routeFactory()->routeMap();

        $api_middleware        = [OAuth2Initialization::class, OAuth2Authorization::class, ApiPermission::class,       ApiSession::class, Login::class, ProcessApi::class];
        $mcp_middleware        = [OAuth2Initialization::class, OAuth2Authorization::class, McpPermission::class,       ApiSession::class, Login::class, ProcessMcp::class, McpProtocol::class, McpToolPermission::class];
        $gedbas_mcp_middleware = [OAuth2Initialization::class, OAuth2Authorization::class, GedbasMcpPermission::class, ApiSession::class, Login::class, ProcessMcp::class, McpProtocol::class, McpToolPermission::class];

        //Register the routes for API requests
        $router
            ->get(McpTool::class, self::ROUTE_MCP)
            ->allows(RequestMethodInterface::METHOD_POST)
            ->extras(['middleware' => $mcp_middleware]);
        $router
            ->get('GedbasMcp', self::ROUTE_GEDBAS_MCP, McpTool::class)
            ->allows(RequestMethodInterface::METHOD_POST)
            ->extras(['middleware' => $gedbas_mcp_middleware]);
        $router
            ->get(TestApi::class, self::ROUTE_API . '/' . self::PATH_TEST_API);
        $router
            ->get(WebtreesVersion::class, self::ROUTE_API . '/' . self::PATH_GET_VERSION)
            ->extras(['middleware' => $api_middleware]);
        $router
            ->get(SearchGeneral::class,   self::ROUTE_API . '/' . self::PATH_SEARCH_GENERAL)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->get(GetRecord::class,   self::ROUTE_API . '/' . self::PATH_GET_RECORD)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(ModifyRecord::class,   self::ROUTE_API . '/' . self::PATH_MODIFY_RECORD)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->get(Trees::class,   self::ROUTE_API . '/' . self::PATH_GET_TREES)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddUnlinkedRecord::class,   self::ROUTE_API . '/' . self::PATH_ADD_UNLINKED_RECORD)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddChildToFamily::class,   self::ROUTE_API . '/' . self::PATH_ADD_CHILD_TO_FAMILY)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddChildToIndividual::class,   self::ROUTE_API . '/' . self::PATH_ADD_CHILD_TO_INDI)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddParentToIndividual::class,   self::ROUTE_API . '/' . self::PATH_ADD_PARENT_TO_INDI)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddSpouseToFamily::class,   self::ROUTE_API . '/' . self::PATH_ADD_SPOUSE_TO_FAMILY)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AddSpouseToIndividual::class,   self::ROUTE_API . '/' . self::PATH_ADD_SPOUSE_TO_INDI)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(LinkChildToFamily::class,   self::ROUTE_API . '/' . self::PATH_LINK_CHILD_TO_FAMILY)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(LinkSpouseToIndividual::class,   self::ROUTE_API . '/' . self::PATH_LINK_SPOUSE_TO_INDI)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(CliCommand::class, self::ROUTE_API . '/' . self::PATH_CLI_COMMAND)
            ->extras(['middleware' =>  $api_middleware]);
        $router
            ->post(AccessToken::class, self::ROUTE_OAUTH2_ACCESS_TOKEN)
            ->extras(['middleware' =>  [OAuth2AccessToken::class]]);

        //Register the routes for settings and modals
        $router
            ->get(EditClientModal::class, self::ROUTE_EDIT_CLIENT_MODAL);
        $router
            ->post(EditClientAction::class, self::ROUTE_EDIT_CLIENT_ACTION);
        $router
            ->get(DeleteClient::class, self::ROUTE_DELETE_CLIENT);
        $router
            ->get(CreateTokenModal::class, self::ROUTE_CREATE_TOKEN_MODAL);
        $router
            ->post(CreateTokenAction::class, self::ROUTE_CREATE_TOKEN_ACTION);
        $router
            ->get(RevokeToken::class, self::ROUTE_REVOKE_TOKEN);
        $router
            ->get(CreateKeysModal::class, self::ROUTE_CREATE_KEYS_MODAL);
        $router
            ->post(CreateKeysAction::class, self::ROUTE_CREATE_KEYS_ACTION);
            
		// Register a namespace for the views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        //Register the custom module in the webtrees container
        Registry::container()->set(WebtreesApi::class, $this);

        // Initialize the OAuth2 server
        try  {
            $this->initializeKeys();
            $this->initializeOauth2Server();
        }
        catch (Oauth2KeysException $e) {
            // Fail gracefully; errors are handled in the module settings
            return;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return I18N::translate('webtrees API');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A custom module to provide an API for webtrees.');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(dirname(__DIR__, 1)) . '_';
    }
    
    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {

                try {
                    //Get latest release from GitHub
                    return GithubService::getLatestReleaseTag(self::GITHUB_REPO);
                }
                catch (GithubCommunicationError $ex) {
                    // Can't connect to GitHub?
                    return $this->customModuleVersion();
                }
            },
            86400
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * View module settings in control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $base_url           = Validator::attributes($request)->string('base_url');
        $pretty_urls        = Validator::attributes($request)->boolean('rewrite_urls', false);
        $path               = parse_url($base_url, PHP_URL_PATH) ?? '';
        $parameters         = ['route' => $path];
        $url                = $base_url . '/index.php';

        $webtrees_api_url        = Html::url($url, $parameters) . self::ROUTE_API;
        $pretty_webtrees_api_url = $base_url . self::ROUTE_API;
        $mcp_url                 = Html::url($url, $parameters) . self::ROUTE_MCP;
        $pretty_mcp_url          = $base_url . self::ROUTE_MCP;
        $access_token_url        = Html::url($url, $parameters) . self::ROUTE_OAUTH2_ACCESS_TOKEN;
        $pretty_access_token_url = $base_url . self::ROUTE_OAUTH2_ACCESS_TOKEN;
        $path_for_keys           = $this->getPreference(self::PREF_PATH_FOR_KEYS, str_replace('\\', '/', Registry::filesystem()->dataName()) . self::DEFAULT_PATH_FOR_KEYS);

        $user_list = self::getUserList();
        $user_list[0] = I18N::translate('— No user selected —');

        // Initialize the OAuth2 server
        $error_message = '';
        try  {
            $this->initializeKeys();
            $this->initializeOauth2Server();
        }
        catch (Oauth2KeysException $e) {
            $error_message = I18N::translate($e->getMessage());
        }

        $access_token_repository = Registry::container()->get(AccessTokenRepository::class);
        $client_repository       = Registry::container()->get(ClientRepository::class);
        $scope_repository        = Registry::container()->get(ScopeRepository::class);

        // Generate the OpenApi json file (because we want to include the specific base URL)
        self::generateOpenApiFile($pretty_webtrees_api_url);

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                       => $this->title(),
                'pretty_urls'                 => $pretty_urls,
                'webtrees_api_url'            => $webtrees_api_url,
                'pretty_webtrees_api_url'     => $pretty_webtrees_api_url,
                'mcp_url'                     => $mcp_url,
                'pretty_mcp_url'              => $pretty_mcp_url,
                'access_token_url'            => $access_token_url,
                'pretty_access_token_url'     => $pretty_access_token_url,
                'uses_https'                  => strpos(Strtoupper($base_url), 'HTTPS://') === false ? false : true,
                'user_list'                   => $user_list,
                'clients'                     => $client_repository->getClients(),
                'access_token_repository'     => $access_token_repository,
                'access_tokens'               => $access_token_repository->getAccessTokens(),
                'scope_identifiers'           => $scope_repository::getScopeIdentifiers(),
                'encryption_key'              => $this->getPreference(self::PREF_ENCRYPTION_KEY, ''),
                'path_for_keys'               => $path_for_keys,
                'public_key_path'             => $path_for_keys . self::PUBLIC_KEY_FILE,
                'private_key_path'            => $path_for_keys . self::PRIVATE_KEY_FILE,
                'error_message'               => $error_message
            ]
        );
    }

    /**
     * Save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $save          = Validator::parsedBody($request)->string('save', '');
        $path_for_keys = Validator::parsedBody($request)->string('path_for_keys', str_replace('\\', '/', Registry::filesystem()->dataName()) . self::DEFAULT_PATH_FOR_KEYS);
        
        //Save the received settings to the user preferences
        if ($save === '1') {

            // Asure that path ends with slash
            $path_for_keys = str_replace("\\", '/', $path_for_keys);

            if (substr($path_for_keys, -1, 1) !== '/') {
                $path_for_keys .= '/';
            } 

            // Save settings to preferences
            if (is_dir($path_for_keys)) {

                $this->setPreference(self::PREF_PATH_FOR_KEYS, $path_for_keys);

                $message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
                FlashMessages::addMessage($message, 'success');	
            }
            else {
                $message = I18N::translate('Could not change path to private/public keys. The directory provided in the path to private/public keys does not exist.');
                FlashMessages::addMessage($message, 'danger');
            }
		}

        return redirect($this->getConfigLink());
    }

    /**
     * Get the namespace for the views
     *
     * @return string
     */
    public static function viewsNamespace(): string
    {
        return self::activeModuleName();
    }    

    /**
     * Generate an OpenApi JSON file
     *
     * @return void
     */
    public static function generateOpenApiFile(string $api_url): void {

        $json_file   = __DIR__ . '/../resources/OpenApi/OpenApi.json';
        $soure_pathes = [__DIR__ . '/../src/Http', __DIR__ . '/../src/WebtreesApi.php'];

        //Delete file if already existing
        if (file_exists($json_file)) {
            unlink($json_file);
        }

        //Open stream
        if (!$stream = fopen($json_file, "c")) {
            throw new RuntimeException('Cannot open file: ' . $json_file);
        }

        //Create OpenAPi description
        $open_api = Generator::scan($soure_pathes, ['*.php']);
        $json = $open_api->toJson();

        //Patch the base URL
        $json = str_replace('https://localhost/webtrees/api', $api_url, $json);

        //Write to json file
        try {
            fwrite($stream, $json);
        }
        catch (Throwable $th) {
            throw new RuntimeException('Cannot write to file: ' . $json_file);
        }

        return;
    }

    /**
     * Get the prefix for custom module specific logs
     * 
     * @return string
     */
    public static function getLogPrefix() : string {
        return 'WebtreesApi';
    }  
    
    /**
     * Whether debugging is activated
     * 
     * @return bool
     */
    public function debuggingActivated(): bool {
        return self::PREF_DEBUGGING_ACTIVATED;
    }

    /**
     * Create a a simple user list
     * array: user_id => real_name 
     * 
     * @return array<int, string>
     */
    public function getUserList(): array {

        $user_list = [];

        foreach (Functions::getAllUsers() as $user) {
            $user_list[$user->id()] = $user->realName();
        }   

        return $user_list;
    }

    /**
     * Generate a secure random password using OpenSSL
     *
     * @param int    $length  Length of the password
     * @param string $chars   Allowed characters
     * 
     * @return string Generated password
     * 
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function generateSecurePassword($length = 12, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_=+[]{};:,.<>?') {

        if ($length <= 0) {
            throw new InvalidArgumentException("Password length must be greater than zero.");
        }

        $charLen = strlen($chars);
        if ($charLen < 2) {
            throw new InvalidArgumentException("Character set must contain at least two characters.");
        }

        // Generate cryptographically secure random bytes
        $bytes = openssl_random_pseudo_bytes($length);
        if ($bytes === '') {
            throw new RuntimeException("Unable to generate secure random bytes.");
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            // Convert each byte to an index in the allowed characters
            $password .= $chars[ord($bytes[$i]) % $charLen];
        }

        return $password;
    }

    /**
     * Create a new encryption key for the OAuth2 server
     * 
     * @return void
     * 
     * @throws Oauth2KeysException
     */
    private function createNewEncryptionKey($update = false): void {

        $this->setPreference(self::PREF_ENCRYPTION_KEY, self::generateSecurePassword(self::ENCRYPTION_KEY_LENGTH));
    }

    /**
     * Initialize the OAuth2 server keys
     *
     * @return void
     * 
     * @throws Oauth2KeysException
     */
    public function initializeKeys(): void {

        $default_path_for_keys = str_replace('\\', '/', Registry::filesystem()->dataName()) . self::DEFAULT_PATH_FOR_KEYS;
        $path_for_keys         = $this->getPreference(self::PREF_PATH_FOR_KEYS, '');

        // Create path for keys, if does not exist
        if ($path_for_keys === '') {
            $path_for_keys = $default_path_for_keys;
            $this->setPreference(self::PREF_PATH_FOR_KEYS, $default_path_for_keys);
        }

        // Initialize default path and keys
        if ($path_for_keys === $default_path_for_keys) {
            
            // Create folder for private/public keys folder, if does not exist
            if (!$this->data_filesystem->directoryExists(self::DEFAULT_PATH_FOR_KEYS)) {
                try {
                    $this->data_filesystem->createDirectory(self::DEFAULT_PATH_FOR_KEYS, ['visibility' => 'private']);
                } catch (Throwable $th) {
                    throw new Oauth2KeysException(I18N::translate('Failed to create directory for keys') .': ' . $path_for_keys);
                }            
            }

            // If private/public keys do not exist, we generate new ones
            if (    !$this->data_filesystem->fileExists(self::DEFAULT_PATH_FOR_KEYS . self::PRIVATE_KEY_FILE)
                OR  !$this->data_filesystem->fileExists(self::DEFAULT_PATH_FOR_KEYS . self::PUBLIC_KEY_FILE) ) {

                $this->createNewKeys();
            }
        }
        elseif (!is_dir($path_for_keys)) {
            throw new Oauth2KeysException(I18N::translate('No access to private/public keys directory') . ': ' . $path_for_keys);
        }
        elseif (!file_exists($path_for_keys . self::PRIVATE_KEY_FILE)) {
            throw new Oauth2KeysException(I18N::translate('Private key file does not exist') . ': ' . $path_for_keys . self::PRIVATE_KEY_FILE);
        }
        elseif (!file_exists($path_for_keys . self::PUBLIC_KEY_FILE)) {
            throw new Oauth2KeysException(I18N::translate('Public key file does not exist') . ': ' . $path_for_keys . self::PUBLIC_KEY_FILE);
        }

        // Generate encryption key, if does not exist
        if ($this->getPreference(self::PREF_ENCRYPTION_KEY, '') === '') {
            $this->createNewEncryptionKey();
        }        

        return;
    }

    /**
     * Get the path to the private/public key
     *
     * @param bool $private_key
     * 
     * @return string
     */
    public function getKeyPath(bool $private_key): string {

        // Create path for keys, if does not exist
        if ($this->getPreference(self::PREF_PATH_FOR_KEYS, '') === '') {
            $this->setPreference(self::PREF_PATH_FOR_KEYS, str_replace('\\', '/', Registry::filesystem()->dataName()) . self::DEFAULT_PATH_FOR_KEYS);
        }

        $path_for_keys = $this->getPreference(self::PREF_PATH_FOR_KEYS);

        if ($private_key) {
            return $path_for_keys . self::PRIVATE_KEY_FILE;
        }

        return $path_for_keys . self::PUBLIC_KEY_FILE;
    }

    /**
     * Create new private/public keys
     * 
     * @return void
     * 
     * @throws Oauth2KeysException
     */
    public function createNewKeys(): void {

        $path_for_keys = $this->getPreference(self::PREF_PATH_FOR_KEYS);

        if (!extension_loaded('openssl')) {
            throw new Oauth2KeysException(I18N::translate('Cannot create private/public keys, because the PHP extension openssl is not available. Please install the PHP extension or create public/private keys manually, e.g. by using OpenSSL on the command line.'));
        }

        //Define key configuration
        $config = array(
            "private_key_bits" => 2048,                // Key size in bits (2048 is standard)
            "private_key_type" => OPENSSL_KEYTYPE_RSA, // Key type (RSA)
        );

        // Generate a new private and public key pair with error suppression and retry logic
        $res = @openssl_pkey_new($config);
        $failed_to_create_keys_message =  
            I18N::translate('Failed to generate private/public keys') . ': ' . openssl_error_string() . ' ' .
            I18N::translate(' Please create public/private keys manually, e.g. by using OpenSSL on the command line. Put the keys into the keys path, which is defined in the module settings.');
        $failed_to_write_key_message = I18N::translate('Failed to write key to the following path');

        if ($res === false) {
            throw new Oauth2KeysException($failed_to_create_keys_message);
        }

        // Extract and save the private key
        if (!openssl_pkey_export($res, $private_key, null, ['private_key_type' => OPENSSL_KEYTYPE_RSA])) {
            throw new Oauth2KeysException($failed_to_create_keys_message);
        }
        try {
            $file = fopen($path_for_keys . self::PRIVATE_KEY_FILE, "w");
            fwrite($file, $private_key);
            fclose($file);
            chmod($path_for_keys . self::PRIVATE_KEY_FILE, 0600);
        } catch (Throwable $th) {
            throw new Oauth2KeysException($failed_to_write_key_message . ': ' . Webtrees::DATA_DIR . $path_for_keys . self::PRIVATE_KEY_FILE);
        }

        // Extract and save the public key
        $key_details = openssl_pkey_get_details($res);
        if ($key_details === false) {
            throw new Oauth2KeysException($failed_to_create_keys_message);
        }

        $public_key = $key_details['key'];
        try {
            $file = fopen($path_for_keys . self::PUBLIC_KEY_FILE, "w");
            fwrite($file, $public_key);
            fclose($file);
            chmod($path_for_keys . self::PUBLIC_KEY_FILE, 0600);
        } catch (Throwable $th) {
            throw new Oauth2KeysException($failed_to_write_key_message . ': ' . Webtrees::DATA_DIR . $path_for_keys . self::PUBLIC_KEY_FILE);
        }

        // If keys were successfully updated, we need to delete all existing access tokens
        $access_token_repository = Registry::container()->get(AccessTokenRepository::class);        
        $access_token_repository->resetAccessTokens();

        // Finally, also create a new encryption key
        $this->createNewEncryptionKey();
    }

    /**
     * Initialize the OAuth2 server and its repositories
     * 
     * @return void
     * 
     * @throws Oauth2KeysException
     */    
    public function initializeOauth2Server(): void {

        // Initialize the OAuth2 server repositories
        $clientRepository = new ClientRepository();
        $scopeRepository = new ScopeRepository();
        $accessTokenRepository = new AccessTokenRepository();

        // Path to OAuth2 server keys
        $privateKeyPath = $this->getKeyPath(true);
        $publicKeyPath  = $this->getKeyPath(false);
        $encryptionKey  = $this->getPreference(self::PREF_ENCRYPTION_KEY, '');

        // Setup the OAuth2 authorization server
        try {
            $authorization_server = new AuthorizationServer(
                $clientRepository,
                $accessTokenRepository,
                $scopeRepository,
                $privateKeyPath,
                $encryptionKey
            );
        }
        catch (Throwable $th) {
            throw new Oauth2KeysException(I18N::translate('Error during initialization of the OAuth2 server') . ': ' . $th->getMessage());
        }
        
        // Enable the client credentials grant on the server
        $authorization_server->enableGrantType(
            new ClientCredentialsGrant(),
            new DateInterval('PT1H') // access tokens will expire after 1 hour
        );        

        // Init access token repository
        $accessTokenRepository = new AccessTokenRepository();

        // Setup the resource server
        $resource_server = new ResourceServer(
            $accessTokenRepository,
            $publicKeyPath
        );

        //Register the OAuth2 server resources in the webtrees container
        Registry::container()->set(ResourceServer::class, $resource_server);
        Registry::container()->set(AuthorizationServer::class, $authorization_server);
        Registry::container()->set(AccessTokenRepository::class, $accessTokenRepository);
        Registry::container()->set(ClientRepository::class, $clientRepository);
        Registry::container()->set(ScopeRepository::class, $scopeRepository);
    }
}
