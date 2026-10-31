<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the chat tables, or completes them when they already exist: a site
 * moving from the chat larachat was extracted from renames its chat_sessions
 * and chat_messages tables to these names first (see the README), and this
 * adds the columns they lack. Existing data is never touched: only missing
 * tables and columns are added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('larachat_sessions')) {
            Schema::create('larachat_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_token', 64)->unique();
                $table->string('visitor_name')->nullable();
                $table->string('visitor_email')->nullable();
                $table->string('visitor_phone', 32)->nullable();
                $table->string('visitor_ip', 45)->nullable();
                $table->string('visitor_page')->nullable();
                $table->string('telegram_chat_id')->nullable();
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->enum('mode', ['ai', 'human'])->default('ai');
                $table->string('detected_locale', 5)->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->text('compressed_summary')->nullable();
                $table->unsignedBigInteger('compressed_until_id')->nullable();
                $table->timestamp('blocked_at')->nullable()->index();
                // First sign of commercial interest, and how many contact forms were shown.
                $table->timestamp('qualified_at')->nullable()->index();
                $table->unsignedTinyInteger('contact_forms')->default(0);
                $table->timestamps();

                $table->index('visitor_ip');
            });
        } else {
            $this->addMissing('larachat_sessions', [
                'visitor_phone' => fn (Blueprint $t) => $t->string('visitor_phone', 32)->nullable(),
                'telegram_chat_id' => fn (Blueprint $t) => $t->string('telegram_chat_id')->nullable(),
                'mode' => fn (Blueprint $t) => $t->enum('mode', ['ai', 'human'])->default('ai'),
                'detected_locale' => fn (Blueprint $t) => $t->string('detected_locale', 5)->nullable(),
                'unread_count' => fn (Blueprint $t) => $t->unsignedInteger('unread_count')->default(0),
                'compressed_summary' => fn (Blueprint $t) => $t->text('compressed_summary')->nullable(),
                'compressed_until_id' => fn (Blueprint $t) => $t->unsignedBigInteger('compressed_until_id')->nullable(),
                'blocked_at' => fn (Blueprint $t) => $t->timestamp('blocked_at')->nullable()->index(),
                'qualified_at' => fn (Blueprint $t) => $t->timestamp('qualified_at')->nullable()->index(),
                'contact_forms' => fn (Blueprint $t) => $t->unsignedTinyInteger('contact_forms')->default(0),
            ]);
        }

        if (! Schema::hasTable('larachat_messages')) {
            Schema::create('larachat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_session_id')->constrained('larachat_sessions')->cascadeOnDelete();
                $table->enum('sender', ['visitor', 'admin', 'assistant'])->default('visitor');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                // A visitor message the scope filter turned away (shown in the panel).
                $table->boolean('off_topic')->default(false);
                // The URLs the reply was built from.
                $table->json('sources')->nullable();
                // null for a message; contact_form (a form the widget draws) or contact
                // (the details the visitor sent through it), with their data in meta.
                $table->string('kind', 20)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        } else {
            $this->addMissing('larachat_messages', [
                'off_topic' => fn (Blueprint $t) => $t->boolean('off_topic')->default(false),
                'sources' => fn (Blueprint $t) => $t->json('sources')->nullable(),
                'kind' => fn (Blueprint $t) => $t->string('kind', 20)->nullable(),
                'meta' => fn (Blueprint $t) => $t->json('meta')->nullable(),
            ]);
        }

        $this->addMissing('users', [
            'is_available' => fn (Blueprint $t) => $t->boolean('is_available')->default(false),
            'last_seen_at' => fn (Blueprint $t) => $t->timestamp('last_seen_at')->nullable(),
        ]);
    }

    /**
     * Only the two columns added to the host's users table are removed: the
     * chat tables may hold conversations from before this migration ran.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['is_available', 'last_seen_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * @param  array<string, \Closure(Blueprint): mixed>  $columns
     */
    protected function addMissing(string $table, array $columns): void
    {
        $missing = array_filter($columns, fn ($column) => ! Schema::hasColumn($table, $column), ARRAY_FILTER_USE_KEY);

        if ($missing) {
            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $define) {
                    $define($blueprint);
                }
            });
        }
    }
};
