<?php

use Illuminate\Database\Seeder;


/*
puoi farlo a mano in questo modo:

php artisan tinker
>>> use Illuminate\Database\Seeder;
>>> factory(App\Scontrino::class, 200)->create();
>>> quit

*/

class ScontrinoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Scontrino::class, 200)->create();
        // stessa cosa
        //factory(App\Scontrino::class, 200)->make()->save();
    }
}
