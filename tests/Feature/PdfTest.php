<?php

namespace Tests\Feature;

use App\Models\Maquette;
use App\Models\Syllabus;
use Database\Seeders\Agri3S5Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_maquette_publiee_se_telecharge_en_pdf(): void
    {
        $this->seed(Agri3S5Seeder::class);
        $maquette = Maquette::firstOrFail();

        $this->get("/api/maquettes/{$maquette->public_id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_syllabus_publie_se_telecharge_en_pdf(): void
    {
        $this->seed(Agri3S5Seeder::class);
        $syllabus = Syllabus::firstOrFail();

        $this->get("/api/syllabus/{$syllabus->public_id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
