<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MamaCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_explains_care_and_danger_signs_in_both_languages(): void
    {
        $this->get(route('mama-care'))->assertOk()
            ->assertSee('Caring for you and your baby')
            ->assertSee('Danger signs during pregnancy')
            ->assertSee('Danger signs for the mother after birth')
            ->assertSee('Leaking urine or stool after birth')
            ->assertSee('Planning your next pregnancy')
            ->assertSee('School, skills and your future')
            ->assertSee('href="'.route('get-help').'"', false)
            ->assertSee(e(route('home', ['topic' => 'parenting'])).'#start', false);

        $this->withSession(['locale' => 'sw'])->get(route('mama-care'))->assertOk()
            ->assertSee('Kukutunza wewe na mtoto wako')
            ->assertSee('Dalili za hatari wakati wa ujauzito')
            ->assertSee('Kuvuja mkojo au haja kubwa baada ya kujifungua');
    }

    public function test_draft_notice_shows_until_reviewed(): void
    {
        $this->get(route('mama-care'))->assertSee('waiting for review by a qualified health worker');

        config(['aisa.mama_care_reviewed' => true]);
        $this->get(route('mama-care'))->assertDontSee('waiting for review by a qualified health worker');
    }

    public function test_every_section_exists_in_both_languages(): void
    {
        $en = trans('mama.sections', [], 'en');
        $sw = trans('mama.sections', [], 'sw');

        $this->assertSame(array_keys($en), array_keys($sw));
        foreach ($en as $key => $section) {
            $this->assertSame(count($section['points']), count($sw[$key]['points']), "{$key} has a different number of points in Kiswahili");
            $this->assertSame($section['urgent'] ?? false, $sw[$key]['urgent'] ?? false);
        }
    }

    public function test_every_photo_has_files_alt_text_and_a_credit(): void
    {
        foreach (config('mama.photos') as $key => $photo) {
            $this->assertFileExists(public_path("images/mama/{$key}-640.webp"));
            $this->assertFileExists(public_path("images/mama/{$key}-1200.webp"));
            $this->assertNotSame("mama.photos.{$key}", trans("mama.photos.{$key}", [], 'en'));
            $this->assertNotSame("mama.photos.{$key}", trans("mama.photos.{$key}", [], 'sw'));
            $this->assertNotEmpty($photo['credit']);
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $photo['source']);
            if (str_starts_with($photo['licence'], 'CC')) {
                $this->assertNotEmpty($photo['licence_url'], "{$key} needs a licence link");
            }
            $this->assertTrue($key === 'hero' || array_key_exists($key, trans('mama.sections', [], 'en')), "{$key} matches no section");
        }

        $this->get(route('mama-care'))->assertOk()
            ->assertSee('images/mama/hero-1200.webp', false)
            ->assertSee('alt="'.e(__('mama.photos.pregnancy')).'"', false)
            ->assertSee('Arnold Tibaijuka')
            ->assertSee('rel="license noopener"', false)
            ->assertSee('not connected to the topics on this page');
    }

    public function test_home_and_navigation_link_to_mama_care(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Mama Care for young mothers')
            ->assertSee('href="'.route('mama-care').'"', false);
    }
}
