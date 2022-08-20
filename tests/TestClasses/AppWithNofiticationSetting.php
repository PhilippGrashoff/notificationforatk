<?php

declare(strict_types=1);

namespace notificationforatk\tests\TestClasses;

use Atk4\Ui\App;


class AppWithNofiticationSetting extends App
{

    public $createNotifications = true;

    public $always_run = false;
}