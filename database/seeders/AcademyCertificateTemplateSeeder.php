<?php

namespace Database\Seeders;

use App\Models\AcademyCertificateTemplate;
use Illuminate\Database\Seeder;

class AcademyCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        AcademyCertificateTemplate::query()->updateOrCreate(
            ['name' => 'Default academy certificate'],
            [
                'title' => 'Certificate of Completion',
                'body' => "This is to certify that {student_name}, son/daughter of {father_name}, student ID {student_id}, has successfully completed the {program} programme in {class} at our institution.\n\nThe course ran from {start_date} to {end_date} and was completed on {completion_date}.",
                'signatory_title' => 'Authorized Signatory',
                'is_active' => true,
            ],
        );
    }
}
