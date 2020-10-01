<?php

declare(strict_types=1);

namespace notificationforatk;

use secondarymodelforatk\SecondaryModel;

class Notification extends SecondaryModel
{

    public $table = 'notification';

    public $reload_after_save = false;


    protected function init(): void
    {

        parent::init();

        $this->addFields(
            [
                [
                    'message',
                    'type' => 'text',
                ],
                [
                    'field',
                    'type' => 'array',
                    'serialize' => 'json'
                ],
                [
                    'level',
                    'type' => 'integer'
                ],
                [
                    'extra_data',
                    'type' => 'array',
                    'serialize' => 'json'
                ],
                [
                    'deactivated',
                    'type' => 'boolean',
                    'default' => 0
                ],
            ]
        );

        $this->setOrder(['deactivated' => 'asc', 'level' => 'desc']);
    }
}
