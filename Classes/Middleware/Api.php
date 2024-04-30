<?php
namespace PhilipHartmann\TYPO3FluidApi\Middleware;


use Egulias\EmailValidator\Warning\EmailTooLong;

use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class Api implements MiddlewareInterface
{
    protected $extKey = 'typo3fluid_api';

    /**
     * @var \TYPO3\CMS\Core\Configuration\ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /** @var ResponseFactoryInterface */
    private $responseFactory;


    /**
     * @var HeadlessUserInt
     */
    private $headlessUserInt;

    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        ResponseFactoryInterface $responseFactory,
        TypoScriptService $typoScriptService,
        HeadlessUserInt $headlessUserInt = null,
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->responseFactory = $responseFactory;
        $this->typoScriptService = $typoScriptService;
        $this->headlessUserInt = $headlessUserInt ?? GeneralUtility::makeInstance(HeadlessUserInt::class);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $apiUrlSegment = rtrim($this->extensionConfiguration->get($this->extKey, 'apiUrlSegment'), '/');





        if (empty($apiUrlSegment)) {
            $apiUrlSegment = '/api/typo3fluid';
        }

        if ($request->getRequestTarget() !== $apiUrlSegment) {
            return $handler->handle($request);
        }

        try {
            $requestBody = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->buildResponse([
                "error" => "JSON ERROR: " . $e->getMessage(),
            ]);
        }

        $apiPassword = $this->extensionConfiguration->get($this->extKey, 'apiPassword');

        if (!empty($apiPassword)
            && (empty($requestBody['password']) || $apiPassword !== $requestBody['password'])
        ) {
            /*
            return $this->buildResponse([
                "error" => "Access denied!",
            ]);
            */
        }

        if (empty($requestBody['extension'])) {
            return $this->buildResponse([
                "error" => "Extension name not set!"
            ]);
        }

        if (empty($requestBody['template']) && empty($requestBody['partial'])) {
            return $this->buildResponse([
                "error" => "Template or partial not set!"
            ]);
        }

/*
        return $this->buildResponse([
            var_dump( $request )
            ]);
*/

//        $body = $this->headlessUserInt->unwrap($response->getBody()->__toString());
//        $stream = new Stream('php://temp', 'r+');
//        $stream->write($body);
//        return $response->withBody($stream);

        $this->setGlobals($request);

        $response = $this->buildResponse([
            "data" => $this->parseTemplate(
                $requestBody['extension'],
                $requestBody['template'],
                $requestBody['partial'],
                !empty($requestBody['section']) ? $requestBody['section'] : null,
                !empty($requestBody['arguments']) ? $requestBody['arguments'] : []
            )
        ]);

        $body = $this->headlessUserInt->unwrap($response->getBody()->__toString());

        $stream = new Stream('php://temp', 'r+');
        $stream->write($body);

        return $response->withBody($stream);

        // return $response;

    }

    private function setGlobals(ServerRequestInterface $request) {

        $pageArguments = new PageArguments(0, '16', []);



        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        $request = $request->withAttribute('routing', $pageArguments);
        // $request = $request->withAttribute('frontend.user', $frontendUser);
        $request = $request->withAttribute('noCache', false);
        // $request = $request->withAttribute('noCache', true);

        $context = new Context();
        // $context->getPropertyFromAspect('typoscript', 'forcedTemplateParsing');
        // $context->setAspect('typoscript','forcedTemplateParsing');

//        $userIsLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');

        $site = $request->getAttribute('site', null);


        $controller = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            $context,
            $site,
            $request->getAttribute('language', $site->getDefaultLanguage()),
            $pageArguments,
            $frontendUser
        );
        $controller->tmpl = GeneralUtility::makeInstance(TemplateService::class, $context, null, $controller);



        // ??
        $controller->no_cache = true;

        $controller->determineId($request);

        $request = $request->withAttribute('frontend.controller', $controller);


        $this->tsfe = $request->getAttribute('frontend.controller');

        if ($this->tsfe === null && isset($GLOBALS['TSFE'])) {
            $this->tsfe = $GLOBALS['TSFE'];
        }
        // $GLOBALS['TSFE'] = $controller;

        $GLOBALS['TYPO3_REQUEST'] = $request;


    }

    private function parseTemplate(String $extension, $template, $partial, $section, $arguments)
    {



        $templateService = GeneralUtility::makeInstance(TemplateService::class);
        $templateService->tt_track = false;
        $templateService->setProcessExtensionStatics(false);



        $templateService->runThroughTemplates([], 0);
        $templateService->generateConfig();

        //$templateService->includeStaticTypoScriptSources();
        // TYPO3\CMS\Frontend\Middleware\TypoScriptFrontendInitialization()

        $setup = $templateService->setup;

        if (empty($setup['plugin.']['tx_' . strtolower($extension) . '.'])) {
            return "Could not find any configuration for the extension key `$extension`.";
        }

        $pluginConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['plugin.']['tx_' . strtolower($extension) . '.']);

        $templatePaths = new TemplatePaths([
//            'layoutRootPaths'   => $pluginConfiguration['view']['layoutRootPath'] ? [$pluginConfiguration['view']['layoutRootPath']] : null ?? $pluginConfiguration['view']['layoutRootPaths'] ?? [],
//            'templateRootPaths' => $pluginConfiguration['view']['templateRootPath'] ? [$pluginConfiguration['view']['templateRootPath']] : null ?? $pluginConfiguration['view']['templateRootPaths'] ?? [],
//            'partialRootPaths'  => $pluginConfiguration['view']['partialRootPath'] ? [$pluginConfiguration['view']['partialRootPath']] : null ?? $pluginConfiguration['view']['partialRootPaths'] ?? [],

            'layoutRootPaths'   => $pluginConfiguration['view']['layoutRootPaths'] ?? [],
            'templateRootPaths' => $pluginConfiguration['view']['templateRootPaths'] ?? [],
            'partialRootPaths'  => $pluginConfiguration['view']['partialRootPaths'] ?? [],
        ]);

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->getRenderingContext()->setTemplatePaths($templatePaths);
        $view->setFormat('html');

        $parsedTemplate = '';

        if (!empty($template)) {
            $view->assignMultiple($arguments);
            $parsedTemplate = $view->render($template);
        } elseif (!empty($partial)) {
            $parsedTemplate = $view->renderPartial($partial, $section, $arguments);
        }



        // $fullTypoScript = $request->getAttribute('frontend.typoscript')->getSetupArray();


        return $parsedTemplate;
    }

    private function buildResponse($body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        $response = $response->withHeader('Access-Control-Allow-Methods', '*');
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        $response->getBody()->write(json_encode($body));

        return $response;
    }
}
