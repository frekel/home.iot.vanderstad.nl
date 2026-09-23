<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_the_mobile_measure_mode_returns_a_successful_response(): void
    {
        $this->get('/measure')
            ->assertStatus(200)
            ->assertSee('Van der Stad · Meetmodus');
    }
}
