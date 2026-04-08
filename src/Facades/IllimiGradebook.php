<?php

namespace Illimi\Gradebook\Facades;

use Illuminate\Support\Facades\Facade;

class IllimiGradebook extends Facade {
    protected static function getFacadeAccessor() {
        return "illimi-gradebook";
    }
}
