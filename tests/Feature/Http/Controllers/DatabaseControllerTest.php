<?php

declare(strict_types=1);

use App\Models\WowAppearance;

test('the garde-robe page renders server HTML with slot links', function (): void {
    WowAppearance::factory()->count(3)->create(['slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->count(2)->create(['slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);

    $response = $this->get('/base-de-donnees/garde-robe');

    $response->assertOk()
        ->assertSee('Transmogrification WoW', false)
        ->assertSee('/base-de-donnees/garde-robe/head', false);
});

test('the garde-robe page renders a filtered slot with wowhead item links', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'name_fr' => 'Casque légendaire', 'item_id' => 12345, 'is_active' => true]);

    $response = $this->get('/base-de-donnees/garde-robe/head');

    $response->assertOk()
        ->assertSee('Casque légendaire', false)
        ->assertSee('wowhead.com/fr/item=12345', false);
});

test('the garde-robe page redirects unknown slots', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $response = $this->get('/base-de-donnees/garde-robe/inexistant');

    $response->assertRedirect('/base-de-donnees/garde-robe');
});
