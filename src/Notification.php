<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification;

use Atk4\Core\Exception;
use secondarymodelforatk\SecondaryModel;

class Notification extends SecondaryModel
{

    public $table = 'notification';


    /**
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    protected function init(): void
    {
        parent::init();

        //A message stating what the notification is about, e.g. "Please select a country for this user".
        //A proper identifier for the notification, e.g. "COUNTRY_MISSING" is saved in the 'value' field which is
        //inherited from SecondaryModel
        $this->addField(
            'message',
            ['type' => 'text']
        );

        //In here, it is saved which field (or something else) the notification applies to.
        //This can be used by the UI to highlight the field
        $this->addField(
            'field',
            ['type' => 'string']
        );

        //The level of the notification. E.g. 1 = warning, 2 = error.
        $this->addField(
            'level',
            ['type' => 'integer']
        );

        //if some extra data is needed for a notification, it can be stored in here.
        $this->addField(
            'extra_data',
            ['type' => 'json']
        );

        //A notification can be deactivated (e.g. marked as "read").
        $this->addField(
            'deactivated',
            [
                'type' => 'boolean',
                'default' => false
            ],
        );

        $this->setOrder(['deactivated' => 'asc', 'level' => 'desc']);
    }
}
