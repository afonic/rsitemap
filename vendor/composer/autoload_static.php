<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit44c7131aeee3c973ef32e6b23132c564
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'Reach\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Reach\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit44c7131aeee3c973ef32e6b23132c564::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit44c7131aeee3c973ef32e6b23132c564::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
