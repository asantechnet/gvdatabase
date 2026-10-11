<?php

namespace Database\Seeders;

use App\Support\ServiceBoard;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    /**
     * Seed the working boards the agency actually uses.
     *
     * One board per built-in service plus the Personal board for manually
     * created work. Titles and order come from {@see ServiceBoard} so the
     * contract-delivery automation and the seeders agree on every name.
     */
    public function run(): void
    {
        foreach (ServiceBoard::ORDER as $order => $title) {
            ServiceBoard::ensure($title, $order);
        }
    }
}
