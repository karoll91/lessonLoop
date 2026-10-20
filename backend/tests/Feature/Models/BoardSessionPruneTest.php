<?php

namespace Tests\Feature\Models;

use App\Models\BoardSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BoardSessionPruneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prune_deletes_board_tabs_not_seen_for_a_day_and_keeps_the_rest(): void
    {
        $closed = BoardSession::factory()->create(['last_seen_at' => now()->subHours(25)]);
        $neverPolled = BoardSession::factory()->create(['created_at' => now()->subDays(2)]);
        $active = BoardSession::factory()->create(['last_seen_at' => now()->subMinute()]);
        $justOpened = BoardSession::factory()->create();

        $this->artisan('model:prune', ['--model' => [BoardSession::class]])->assertSuccessful();

        $this->assertModelMissing($closed);
        $this->assertModelMissing($neverPolled);
        $this->assertModelExists($active);
        $this->assertModelExists($justOpened);
    }
}
