<?php

return [
    'frontend' => [
        'typo3fluid_api/api' => [
            'target' => \PhilipHartmann\TYPO3FluidApi\Middleware\Api::class,
            'after' => [
//                'typo3/cms-frontend/site',
//                'typo3/cms-frontend/authentication'
//                'typo3/cms-frontend/prepare-tsfe-rendering',
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect'

            ],
            'before' => [
//                'typo3/cms-frontend/maintenance-mode',
//                'typo3/cms-frontend/page-resolver',
//                'typo3/cms-frontend/shortcut-and-mountpoint-redirect'
                'typo3/cms-frontend/content-length-headers'
            ],
        ],
    ]
];
