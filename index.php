<?php

/*
 * Data Index addon for Bear Framework
 * https://github.com/ivopetkov/data-index-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
    ->add('IvoPetkov\BearFrameworkAddons\DataIndex', 'classes/DataIndex.php');

$app->shortcuts
    ->add('dataIndex', function () {
        return new IvoPetkov\BearFrameworkAddons\DataIndex();
    });
