<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Scontrino;
use Faker\Generator as Faker;

$factory->define(Scontrino::class, function (Faker $faker) {
    $stato = $faker->numberBetween($min = 0, $max = 2);
    $idScontrino =  ($stato > 0 ) ? $faker->numerify('MF #####') : null;
    return [
        'id_documento' => $faker->numerify('NR ###'), // Nr. fattura
        'testo' => $faker->words(2, true), // Testo scontrino
        'prezzo' => $faker->randomFloat($nbMaxDecimals = 2, $min = 1, $max = 999),
        'stato' => $stato, // 0=nuovo, 1=stampato/emesso, 2=esito inviato al SAP, 3=errore emissione, 4=errore SAP
        'id_scontrino' => $idScontrino,
        'in_prova' => true,
        'response_url' => 'http://url.di.test/test'
    ]; // URL Server a cui inviare la risposta
});
