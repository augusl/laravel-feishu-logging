<?php

/*
 * This file is part of the nilsir/laravel-feishu-logging.
 *
 * (c) nilsir <nilsir@qq.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Nilsir\LaravelFeishuLogging;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

/**
 * Class FeishuLoggerServiceProvider.
 */
class FeishuLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/feishu-logger.php',
            'feishu-logger'
        );
    }

    public function boot(): void
    {
        $path = __DIR__.'/../config/feishu-logger.php';

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes(
                [$path => config_path('feishu-logger.php')],
                'feishu-logger'
            );

            // 发布组件迁移文件
            $this->publishes(
                [__DIR__.'/../database/migrations' => database_path('migrations')],
                'feishu-logger-migrations'
            );
        } elseif (class_exists(LumenApplication::class) && $this->app instanceof LumenApplication) {
            $this->app->configure('feishu-logger');
        }

        $this->mergeConfigFrom($path, 'feishu-logger');
    }
}
