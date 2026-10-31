<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationTest extends TestCase
{
    public function test_renamed_tables_of_the_old_chat_are_completed_and_keep_their_conversations(): void
    {
        Schema::drop('larachat_messages');
        Schema::drop('larachat_sessions');

        // The tables as the older chat left them, without the newer columns.
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->unique();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('visitor_ip', 45)->nullable();
            $table->string('visitor_page')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->enum('sender', ['visitor', 'admin', 'assistant'])->default('visitor');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        $id = DB::table('chat_sessions')->insertGetId(['session_token' => 'old-token', 'status' => 'open']);
        DB::table('chat_messages')->insert(['chat_session_id' => $id, 'sender' => 'visitor', 'message' => 'Hola']);

        Schema::rename('chat_sessions', 'larachat_sessions');
        Schema::rename('chat_messages', 'larachat_messages');
        (require __DIR__ . '/../../database/migrations/create_larachat_tables.php.stub')->up();

        foreach (['visitor_phone', 'mode', 'unread_count', 'compressed_summary', 'blocked_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('larachat_sessions', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('larachat_messages', 'off_topic'));
        $this->assertTrue(Schema::hasColumn('larachat_messages', 'sources'));

        $session = ChatSession::where('session_token', 'old-token')->firstOrFail();
        $this->assertSame('ai', $session->mode);
        $this->assertSame(['Hola'], $session->messages()->pluck('message')->all());
    }
}
