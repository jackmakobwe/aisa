<?php

namespace Tests\Feature;

use App\Models\CrisisResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpContactsTest extends TestCase
{
    use RefreshDatabase;

    private function contact(array $attributes = [], bool $verified = true): CrisisResource
    {
        $resource = new CrisisResource(array_merge(['category' => 'health', 'name' => 'Test Hospital', 'phone' => '0222 000 000'], $attributes));
        $resource->forceFill(['is_verified' => $verified, 'verified_at' => $verified ? now() : null])->save();

        return $resource;
    }

    public function test_maps_link_uses_coordinates_or_name_and_address_but_never_for_national_services(): void
    {
        $exact = $this->contact(['address' => 'Upanga', 'region' => 'Dar es Salaam', 'latitude' => -6.8037, 'longitude' => 39.2722]);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query='.rawurlencode('-6.8037,39.2722'), $exact->mapsUrl());

        $byName = $this->contact(['name' => 'Amana Hospital', 'address' => 'Ilala', 'district' => 'Ilala', 'region' => 'Dar es Salaam']);
        $this->assertStringEndsWith(rawurlencode('Amana Hospital, Ilala, Ilala, Dar es Salaam, Tanzania'), $byName->mapsUrl());
        $this->assertSame('Ilala, Dar es Salaam', $byName->place());

        $national = $this->contact(['name' => 'Gender Desk', 'phone' => null, 'address' => 'At most police stations']);
        $this->assertNull($national->mapsUrl());
        $this->assertNull($national->place());
    }

    public function test_hotlines_first_then_a_region_list_with_numbers_beside_names(): void
    {
        $this->contact(['category' => 'emergency', 'name' => 'Police', 'phone' => '112']);
        $this->contact(['category' => 'legal', 'name' => 'National Legal Aid', 'phone' => '0800 751 010']);
        $this->contact(['name' => 'Mwanza Hospital', 'phone' => '028 250 2171', 'address' => 'Isamilo', 'region' => 'Mwanza']);
        $this->contact(['name' => 'Dodoma Hospital', 'address' => 'Dodoma', 'region' => 'Dodoma']);
        $this->contact(['name' => 'Hidden Unverified', 'region' => 'Mwanza'], verified: false);

        // Without a region: hotlines and national services, but no regional list yet.
        $this->get(route('get-help'))->assertOk()
            ->assertSee('Emergency numbers')->assertSee('href="tel:112"', false)
            ->assertSee('Help across Tanzania')->assertSee('National Legal Aid')
            ->assertSee('Find help in your region')
            ->assertDontSee('Mwanza Hospital')
            ->assertDontSee('<iframe', false);

        // With a region: every service there as a list, number beside the name.
        $this->get(route('get-help', ['region' => 'Mwanza']))->assertOk()
            ->assertSeeInOrder(['Mwanza Hospital', 'href="tel:0282502171"', '028 250 2171'], false)
            ->assertSee('aria-label="Open in Google Maps: Mwanza Hospital"', false)
            ->assertDontSee('Dodoma Hospital')
            ->assertDontSee('Hidden Unverified');

        $this->get(route('get-help', ['region' => 'Kusini Pemba']))->assertOk()
            ->assertSee('No services have been added for Kusini Pemba yet.');

        // Unknown regions are ignored.
        $this->get(route('get-help', ['region' => 'Atlantis']))->assertOk()->assertDontSee('Dodoma Hospital');
    }

    public function test_unverified_contacts_are_previewed_only_on_a_developers_machine(): void
    {
        $this->contact(['category' => 'emergency', 'name' => 'Police', 'phone' => '112'], verified: false);

        $this->get(route('get-help'))->assertOk()->assertDontSee('href="tel:112"', false);

        $this->app['env'] = 'local';
        $this->get(route('get-help'))->assertOk()
            ->assertSee('href="tel:112"', false)
            ->assertSee('Not verified')
            ->assertSee('Development preview');
    }

    public function test_google_map_loads_only_when_asked_and_only_on_this_page(): void
    {
        $page = $this->get(route('get-help'));
        $page->assertOk()->assertDontSee('<iframe', false)->assertSee('Prefer a map?');
        $this->assertStringContainsString('frame-src https://www.google.com', $page->headers->get('Content-Security-Policy'));

        $this->get(route('get-help', ['map' => 1, 'service' => 'police', 'region' => 'Mwanza']))->assertOk()
            ->assertSee('<iframe', false)
            ->assertSee(e('https://maps.google.com/maps?q='.rawurlencode('Police station in Mwanza, Tanzania').'&output=embed'), false)
            ->assertSee('referrerpolicy="no-referrer"', false);

        config(['help.maps_embed_key' => 'test-key']);
        $this->get(route('get-help', ['map' => 1, 'service' => 'legal_aid']))->assertOk()
            ->assertSee(e('https://www.google.com/maps/embed/v1/search?key=test-key&q='.rawurlencode('Legal aid in Tanzania')), false)
            ->assertSee('referrerpolicy="strict-origin"', false);

        // Every other page still forbids frames.
        $this->assertStringContainsString("frame-src 'none'", $this->get(route('about'))->headers->get('Content-Security-Policy'));
    }

    public function test_admin_saves_location_fields_and_validates_them(): void
    {
        $this->actingAsStaff();

        $this->post(route('admin.resources.store'), [
            'category' => 'health', 'name' => 'Kigoma Hospital', 'address' => 'Ujiji Road', 'district' => 'Kigoma',
            'region' => 'Kigoma', 'latitude' => '-4.8770', 'longitude' => '29.6270', 'source_note' => 'Called on 17 Oct',
        ])->assertRedirect(route('admin.resources.index'));

        $saved = CrisisResource::where('name', 'Kigoma Hospital')->firstOrFail();
        $this->assertSame('Kigoma', $saved->region);
        $this->assertEqualsWithDelta(-4.877, $saved->latitude, 0.0001);
        $this->assertFalse($saved->is_verified);

        $this->post(route('admin.resources.store'), ['category' => 'health', 'name' => 'Nowhere'])
            ->assertSessionHasErrors('phone');
        $this->post(route('admin.resources.store'), ['category' => 'health', 'name' => 'Bad', 'address' => 'x', 'region' => 'Atlantis', 'latitude' => '200'])
            ->assertSessionHasErrors(['region', 'latitude', 'longitude']);

        $this->get(route('admin.resources.index'))->assertOk()->assertSee('Called on 17 Oct')->assertSee('check on Google Maps');
    }

    public function test_import_creates_unverified_contacts_once(): void
    {
        $this->artisan('aisa:import-contacts')->expectsOutputToContain('UNVERIFIED')->assertSuccessful();
        $count = CrisisResource::count();

        $this->assertGreaterThanOrEqual(50, $count);
        $this->assertSame(0, CrisisResource::verified()->count());
        $this->assertTrue(CrisisResource::where('phone', '112')->exists());
        $this->assertTrue(CrisisResource::where('phone', '116')->exists());
        foreach (CrisisResource::whereNotNull('region')->get() as $resource) {
            $this->assertContains($resource->region, config('help.regions'));
            $this->assertNotNull($resource->mapsUrl(), "{$resource->name} should have a map link");
        }

        $this->artisan('aisa:import-contacts')->assertSuccessful();
        $this->assertSame($count, CrisisResource::count());

        // Nothing imported is public until verified.
        $this->get(route('get-help'))->assertDontSee('Muhimbili National Hospital');
    }
}
