<?php

declare(strict_types=1);

namespace notificationforatk\tests\TestClasses;

use atk4\ui\App;


class AppWithNofiticationSetting extends App {

    public $createNotifications = true;

    public $always_run = false;
}