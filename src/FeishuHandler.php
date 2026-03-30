<?php

/*
 * This file is part of the nilsir/laravel-feishu-logging.
 *
 * (c) nilsir <nilsir@qq.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Nilsir\LaravelFeishuLogging;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Nilsir\LaravelFeishuLogging\Concerns\HasFeishuWebhook;

/**
 * Class FeishuHandler
 *
 * 通过 context['token_key'] 在运行时动态选择 feishu_tokens 表中的 webhook token。
 * 兜底 webhook 由 FeishuLogger 调用 buildWebhookUrl() 拼好后通过 setDefaultWebhook() 注入。
 */
class FeishuHandler extends AbstractProcessingHandler
{
    use HasFeishuWebhook;

    /**
     * 兜底 webhook，token_key 未传或查不到时使用
     * 为空时静默丢弃日志，不抛异常
     */
    protected string $defaultWebhook = '';

    /**
     * 运行时 token 缓存（按 token_key），同一请求内避免重复查库
     *
     * @var array<string, string>
     */
    protected array $tokenCache = [];

    public function setDefaultWebhook(string $webhook): void
    {
        $this->defaultWebhook = $webhook;
    }

    protected function write(LogRecord $record): void
    {
        $webhook = $this->resolveWebhook($record->context);

        // 找不到可用 webhook，静默丢弃，不影响主流程
        if (empty($webhook)) {
            return;
        }

        $arr = $record->toArray();
        $title = $arr['message'];

        // token_key 仅用于路由，不透传到消息体
        $context = array_filter(
            $arr['context'],
            fn ($key) => $key !== 'token_key',
            ARRAY_FILTER_USE_KEY
        );

        $contents = [];
        foreach ($context as $key => $item) {
            if ($key === 'at') {
                $contents[] = [
                    'tag' => 'at',
                    'user_id' => $item,
                ];
            } else {
                $contents[] = [
                    'tag' => 'text',
                    'text' => "{$key}: \n".json_encode(
                        $item,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )."\n",
                ];
            }
        }

        $data = [
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $title,
                        'content' => [$contents],
                    ],
                ],
            ],
        ];

        (new Client)->post($webhook, [
            'http_errors' => false,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
        ]);
    }

    /**
     * 根据 context['token_key'] 解析目标 webhook
     *
     * 优先级：context 传入的 token_key 查表 > defaultWebhook（兜底）
     */
    protected function resolveWebhook(array $context): string
    {
        $tokenKey = $context['token_key'] ?? null;

        // 未传 token_key，直接走兜底
        if ($tokenKey === null) {
            return $this->defaultWebhook;
        }

        // 命中内存缓存
        if (array_key_exists($tokenKey, $this->tokenCache)) {
            return $this->tokenCache[$tokenKey];
        }

        // 查 feishu_tokens 表（组件内置表）
        $token = DB::table('feishu_tokens')
            ->where('token_key', $tokenKey)
            ->where('status', 1)
            ->where(fn ($q) => $q
                ->whereNull('expired_at')
                ->orWhere('expired_at', '>', now())
            )
            ->value('token');

        // 查不到则降级到 defaultWebhook
        $webhook = $token
            ? $this->buildWebhookUrl($token)
            : $this->defaultWebhook;

        $this->tokenCache[$tokenKey] = $webhook;

        return $webhook;
    }
}
