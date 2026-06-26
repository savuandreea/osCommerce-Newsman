<?php

namespace common\extensions\NewsMAN;

class Setup extends \common\classes\modules\SetupExtensions
{
    public static function getVersionHistory()
    {
        return [
            '1.0.0' => ['whats_new' => 'First version'],
        ];
    }

    public static function getDescription()
    {
        return 'NewsMAN remarketing tracking.';
    }

    public static function getAdminHooks()
    {
        $path = \Yii::getAlias('@common') . DIRECTORY_SEPARATOR .
            'extensions' . DIRECTORY_SEPARATOR .
            'NewsMAN' . DIRECTORY_SEPARATOR .
            'hooks' . DIRECTORY_SEPARATOR;

        return [
            [
                'page_name' => 'frontend/layouts-main',
                'page_area' => 'before-body-close',
                'extension_file' => $path . 'frontend.footer.tpl',
            ],
            [
                'page_name' => 'frontend/layouts-ajax',
                'page_area' => 'before-body-close',
                'extension_file' => $path . 'frontend.footer.tpl',
            ],
        ];
    }

    public static function getConfigureKeys()
    {
        return [
            'NEWSMAN_REMARKETING_ID' => [
                'title' => 'NewsMAN remarketing ID',
                'value' => '',
                'description' => 'Fill in this ID to enable NewsMAN remarketing.',
            ],
            'NEWSMAN_API_USER_ID' => [
                'title' => 'NewsMAN user ID',
                'value' => '',
                'description' => 'Used for address sync.',
            ],
            'NEWSMAN_API_KEY' => [
                'title' => 'NewsMAN API key',
                'value' => '',
                'description' => 'Used for address sync.',
            ],
            'NEWSMAN_LIST_ID' => [
                'title' => 'NewsMAN list ID',
                'value' => '',
                'description' => 'Subscribers/customers go to this list.',
            ],
            'NEWSMAN_SHOP_PATH' => [
                'title' => 'Shop path',
                'value' => 'printshop',
                'description' => 'Public shop path used for feed links.',
            ],
            'NEWSMAN_ENABLE_EVENTS' => [
                'title' => 'Enable ecommerce events',
                'value' => '1',
                'description' => 'Use 1 to enable, 0 to disable.',
            ],
            'NEWSMAN_ENABLE_FEED' => [
                'title' => 'Enable product feed',
                'value' => '1',
                'description' => 'Use 1 to enable, 0 to disable.',
            ],
            'NEWSMAN_ENABLE_SYNC' => [
                'title' => 'Enable address sync',
                'value' => '0',
                'description' => 'Use 1 to enable after API credentials are filled, 0 to disable.',
            ],
        ];
    }
}
