<?php

defined('TYPO3') or die();

// use Faeb\StorybookBootstrapPackage\Controller\BlogController;
// use Faeb\StorybookBootstrapPackage\Controller\CommentController;
// use Faeb\StorybookBootstrapPackage\Controller\PostController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

call_user_func(function()
{
    $extensionKey = 'storybook_bootstrap_package';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        $extensionKey,
        'setup',
        "@import 'EXT:storybook_bootstrap_package/Configuration/TypoScript/setup.typoscript'"
    );
});