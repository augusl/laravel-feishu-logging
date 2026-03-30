<?php

/*
 * This file is part of the nilsir/laravel-feishu-logging.
 *
 * (c) nilsir <nilsir@qq.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Nilsir\LaravelFeishuLogging;

use Illuminate\Support\Facades\DB;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;
use Nilsir\LaravelFeishuLogging\Concerns\HasFeishuWebhook;

/**
 * Class FeishuLogger
 *
 * Laravel 自定义 channel 驱动入口（logging.php 中配置 'via' 指向此类）。
 *
 * logging.php 配置示例：
 * ```php
 * 'feishu' => [
 *     'driver'            => 'custom',
 *     'via'               => \Nilsir\LaravelFeishuLogging\FeishuLogger::class,
 *     'level'             => 'debug',
 *     'default_token_key' => env('FEISHU_LOGGER_DEFAULT_TOKEN_KEY'),
 * ],
 * ```
 */
class FeishuLogger
{
    use HasFeishuWebhook;

    /**
     * 创建 Monolog 实例（Laravel custom channel 驱动约定）
     */
    public function __invoke(array $config): Logger
    {
        $handler = new FeishuHandler;
        $handler->setBubble($config['bubble'] ?? true);
        $handler->setLevel($config['level'] ?? 'debug');
        $handler->setFormatter(
            new NormalizerFormatter(
                $config['date_format'] ?? config('feishu-logger.date_format')
            )
        );

        // 解析兜底 webhook 后注入 handler
        $defaultWebhook = $this->resolveDefaultWebhook($config);
        $handler->setDefaultWebhook($defaultWebhook);

        return new Logger(config('app.name'), [$handler]);
    }

    /**
     * 解析兜底 webhook
     *
     * 优先级：channel 配置的 default_token_key > feishu-logger.php 全局配置
     * 均未配置或查不到时返回空字符串（handler 侧静默丢弃日志）
     */
    protected function resolveDefaultWebhook(array $config): string
    {
        $tokenKey = $config['default_token_key']
            ?? config('feishu-logger.default_token_key');

        if (empty($tokenKey)) {
            $token = config('feishu-logger.token');
            return $token ? $this->buildWebhookUrl($token) : '';
        }

        $token = DB::table('feishu_tokens')
            ->where('token_key', $tokenKey)
            ->where('status', 1)
            ->where(fn ($q) => $q
                ->whereNull('expired_at')
                ->orWhere('expired_at', '>', now())
            )
            ->value('token');

        return $token ? $this->buildWebhookUrl($token) : '';
    }
}
