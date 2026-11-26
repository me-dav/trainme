<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_module_page_explains_what_deleting_removes(): void
    {
        $user = User::factory()->create();
        $module = Module::factory()->create(['title' => 'Permission']);
        Question::factory()->count(3)->create(['module_id' => $module->id]);
        Attempt::factory()->completed(90)->count(2)->create(['module_id' => $module->id, 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('Hapus modul ini')
            ->assertSee('beserta 3 soal dan 2 riwayat tes.');
    }

    public function test_deleting_a_module_removes_its_questions_and_history_but_keeps_the_board(): void
    {
        $user = User::factory()->create();
        $board = Course::factory()->create();
        $module = Module::factory()->create(['course_id' => $board->id, 'title' => 'Permission']);
        $sibling = Module::factory()->create(['course_id' => $board->id]);
        $question = Question::factory()->create(['module_id' => $module->id]);
        $attempt = Attempt::factory()->completed(90)->create(['module_id' => $module->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('modules.destroy', $module))
            ->assertRedirect(route('boards.show', $board))
            ->assertSessionHas('status', 'Modul "Permission" dihapus.');

        $this->assertModelMissing($module);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertModelMissing($attempt);
        $this->assertModelExists($board);
        $this->assertModelExists($sibling);
    }

    public function test_guests_cannot_delete_modules(): void
    {
        $module = Module::factory()->create();

        $this->delete(route('modules.destroy', $module))->assertRedirect(route('login'));

        $this->assertModelExists($module);
    }

    public function test_every_page_offers_the_theme_toggle_and_follows_the_device_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee("matchMedia('(prefers-color-scheme: light)')", false)
            ->assertSee("localStorage.getItem('theme')", false);
    }
}
