<?php

/*
 * Data Index addon for Bear Framework
 * https://github.com/ivopetkov/data-index-bearframework-addon
 * Copyright (c) 2018 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->context->get(__FILE__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\DataIndex', 'classes/DataIndex.php');

$app->shortcuts
        ->add('dataIndex', function() {
            return new IvoPetkov\BearFrameworkAddons\DataIndex();
        });
