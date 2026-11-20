<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_a_board_can_be_created_from_the_dashboard(): void
    {
        $this->actingAs($this->user)
            ->post(route('boards.store'), ['title' => 'Docker & Container', 'description' => 'Image, volume, network.'])
            ->assertRedirect(route('boards.show', 'docker-container'));

        $this->get(route('dashboard'))->assertOk()->assertSee('Docker &amp; Container', false)->assertSee('0 modul');
        $this->get(route('boards.show', 'docker-container'))->assertOk()
            ->assertSee('Unduh template')
            ->assertSee('Salin prompt')
            ->assertSee('id` modul diawali `docker-container-`');
    }

    public function test_duplicate_or_empty_board_names_are_rejected(): void
    {
        Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);

        $this->actingAs($this->user)->from(route('dashboard'))
            ->post(route('boards.store'), ['title' => 'LINUX'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertSee('data-open-on-load', false)->assertSee('Board dengan nama itu sudah ada');

        $this->from(route('dashboard'))->post(route('boards.store'), ['title' => '!!!'])->assertSessionHasErrors('title', null, 'board');

        $this->assertSame(1, Course::query()->count());
    }

    public function test_a_board_can_be_renamed_but_keeps_its_slug(): void
    {
        $board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);

        $this->actingAs($this->user)
            ->patch(route('boards.update', $board), ['title' => 'Linux Fundamentals', 'description' => null])
            ->assertRedirect(route('boards.show', 'linux'));

        $this->assertSame('Linux Fundamentals', $board->fresh()->title);
        $this->assertSame('linux', $board->fresh()->slug);
    }

    public function test_only_empty_boards_can_be_deleted(): void
    {
        $full = Course::factory()->create();
        Module::factory()->create(['course_id' => $full->id]);
        $empty = Course::factory()->create();

        $this->actingAs($this->user)->delete(route('boards.destroy', $full))->assertSessionHas('error');
        $this->delete(route('boards.destroy', $empty))->assertRedirect(route('dashboard'));

        $this->assertModelExists($full);
        $this->assertModelMissing($empty);
    }

    public function test_cli_import_does_not_overwrite_a_renamed_board(): void
    {
        $board = Course::factory()->create(['slug' => 'test-course', 'title' => 'Judul dari aplikasi']);

        $this->artisan('dojo:import', ['path' => base_path('tests/Fixtures/mini-module.md')])->assertSuccessful();

        $this->assertSame('Judul dari aplikasi', $board->fresh()->title);
        $this->assertSame($board->id, Module::query()->sole()->course_id);
    }
}
