<?php


namespace App\Util;

trait Singleton
{
    private static $instance;

    static function getInstance(...$args)
    {
        if (!isset(self::$instance)) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            self::$instance = new static(...$args);
        }
        return self::$instance;
    }

}
