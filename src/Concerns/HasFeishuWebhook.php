<?php

/*
 * This file is part of the nilsir/laravel-feishu-logging.
 *
 * (c) nilsir <nilsir@qq.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Nilsir\LaravelFeishuLogging\Concerns;

/**
 * Trait HasFeishuWebhook
 *
 * 统一维护飞书 Webhook URL 拼接。
 */
trait HasFeishuWebhook
{
    protected function buildWebhookUrl(string $token): string
    {
        return 'https://open.feishu.cn/open-apis/bot/v2/hook/'.$token;
    }
}
