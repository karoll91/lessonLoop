<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_start_page_opens_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin/');
    }

    public function test_admin_panel_addresses_return_its_index_page_after_a_reload(): void
    {
        $index = public_path('admin/index.html');
        $existed = is_file($index);
        if (! $existed) {
            File::ensureDirectoryExists(dirname($index));
            File::put($index, '<!doctype html><title>LessonLoop — admin</title>');
        }

        try {
            $response = $this->get('/admin/groups/3')->assertOk();

            $this->assertSame(realpath($index), $response->baseResponse->getFile()->getRealPath());
        } finally {
            if (! $existed) {
                File::deleteDirectory(dirname($index));
            }
        }
    }
}
