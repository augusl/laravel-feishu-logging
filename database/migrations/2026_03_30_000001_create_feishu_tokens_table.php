<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 飞书 token 表（组件内置）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feishu_tokens', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('token_key', 64)->unique()->comment('token 标识 key');
            $table->string('token_description', 255)->nullable()->comment('描述');
            $table->string('token', 512)->comment('token 内容');
            $table->timestamp('expired_at')->nullable()->comment('过期时间，null 表示永不过期');
            $table->tinyInteger('status')->unsigned()->default(1)->comment('状态 1=有效 2=失效');
            $table->unsignedBigInteger('created_at')->comment('创建时间戳（秒）');
            $table->unsignedBigInteger('updated_at')->comment('更新时间戳（秒）');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feishu_tokens');
    }
};
